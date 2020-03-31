<?php
/*
                  Inroads Shopping Cart - Vendors Import Processing

                         Written 2015-2019 by Randall Severy
                          Copyright 2015-2019 Inroads, LLC

If running on CloudLinux, prevent killing of background tasks by editing
/usr/sbin/kill_php_script and add: grep -v vendors-import.php

Set up to run once an hour by cron using the following crontab command:

0 * * * * cd /home/{domain}/public_html/cartengine; /usr/local/bin/php vendors-import.php import

*/

define('DEBUG_MAPPING',false);
define('DEBUG_LOGGING',false);

require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../engine/modules.php';
require_once 'utility.php';
require_once 'cartconfig-common.php';
require_once 'vendors-common.php';
require_once 'products-common.php';
require_once 'inventory-common.php';
require_once 'shopping-common.php';
require_once 'inventory.php';
require_once 'image.php';
require_once 'seo.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

$import_log_filename = '../admin/import.log';

if (DEBUG_LOGGING) $start_debug_log = time();

function log_import($msg)
{
    global $import_log_filename,$start_debug_log;

    $log_file = @fopen($import_log_filename,'at');
    if ($log_file) {
       if (DEBUG_LOGGING) {
          $elapsed_time = time() - $start_debug_log;
          fwrite($log_file,'['.date('D M d Y H:i:s').'] '.$msg.' [' .
                 $elapsed_time."]\n");
       }
       else fwrite($log_file,'['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($log_file);
    }
}

function process_import_error($error)
{
    if (DEBUG_MAPPING) print $error."\n";
    log_error($error);   log_vendor_activity($error);
    log_import($error);
}

function error_handler($errno,$errstr,$errfile,$errline)
{
    if (defined('E_DEPRECATED') && ($errno == E_DEPRECATED)) return true;
    $errortype = array (E_ERROR              => 'Error',
                        E_WARNING            => 'Warning',
                        E_PARSE              => 'Parsing Error',
                        E_NOTICE             => 'Notice',
                        E_CORE_ERROR         => 'Core Error',
                        E_CORE_WARNING       => 'Core Warning',
                        E_COMPILE_ERROR      => 'Compile Error',
                        E_COMPILE_WARNING    => 'Compile Warning',
                        E_USER_ERROR         => 'User Error',
                        E_USER_WARNING       => 'User Warning',
                        E_USER_NOTICE        => 'User Notice'
                );
    if (defined('E_STRICT')) $errortype[E_STRICT] = 'Runtime Notice';
    if (defined('E_RECOVERABLE_ERROR'))
       $errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
    $error_string = 'PHP Error ('.$errortype[$errno].'): '.$errstr.' in ' .
                    $errfile.' on line '.$errline;
    process_import_error($error_string);
    return true;
}

function call_vendor_module_event($db,$edit_type,$obj_type,$product_id,
                                 $product_info,$inventory_info,&$error)
{
    switch ($edit_type) {
       case ADDRECORD: $event_name = 'add_'.$obj_type;   break;
       case UPDATERECORD: $event_name = 'update_'.$obj_type;   break;
       case DELETERECORD: $event_name = 'delete_'.$obj_type;   break;
    }
    if (! module_attached($event_name)) return true;

    if (! $product_info)
       $product_info = load_product_info($db,$product_id);
    set_product_category_info($db,$product_info);
    if (! $inventory_info)
       $inventory_info = load_inventory_records($db,$product_id);
    update_inventory_records($db,$product_info,$inventory_info);
    $parameters = array($db,$product_info,$inventory_info);
    if (! call_module_event($event_name,$parameters,null,true)) {
       $error = get_module_errors();   return false;
    }
    if (DEBUG_LOGGING) log_import('   Called '.$event_name.' module event');
    return true;
}

function open_sftp($import)
{
    set_include_path(get_include_path().PATH_SEPARATOR.'../engine/phpseclib');
    require_once 'Crypt/Base.php';
    require_once 'Crypt/Rijndael.php';
    require_once 'Crypt/AES.php';
    require_once 'Crypt/Blowfish.php';
    require_once 'Crypt/DES.php';
    require_once 'Crypt/Hash.php';
    require_once 'Crypt/Random.php';
    require_once 'Crypt/RC2.php';
    require_once 'Crypt/RC4.php';
    require_once 'Crypt/RSA.php';
    require_once 'Crypt/TripleDES.php';
    require_once 'Crypt/Twofish.php';
    require_once 'Math/BigInteger.php';
    require_once 'Net/SSH2.php';
    require_once 'Net/SFTP.php';

    $ftp = new phpseclib\Net\SFTP($import['ftp_hostname']);
    if (! $ftp->login($import['ftp_username'],$import['ftp_password']))
       return null;
    return $ftp;
}

function open_ftp($import)
{
    if ($import['import_source'] == SFTP_IMPORT_SOURCE)
       return open_sftp($import);

    ob_start();
    $ftp = ftp_connect($import['ftp_hostname']);
    if ($ftp === false) {
       $ftp_error = ob_get_contents();
       $start_pos = strpos($ftp_error,'function.ftp-connect</a>]: ');
       $end_pos = strpos($ftp_error,' in <b>');
       if (($start_pos !== false) && ($end_pos !== false))
          $ftp_error = substr($ftp_error,$start_pos + 27,
                              $end_pos - $start_pos - 27);
       $error = 'Unable to connect to FTP Server '.$import['ftp_hostname'] .
                ' ('.$ftp_error.')';
       ob_end_clean();   process_import_error($error);   return null;
    }
    ob_end_clean();
    ob_start();
    if (! @ftp_login($ftp,$import['ftp_username'],$import['ftp_password'])) {
       $ftp_error = ob_get_contents();
       $start_pos = strpos($ftp_error,'function.ftp-login</a>]: ');
       $end_pos = strpos($ftp_error,' in <b>');
       if (($start_pos !== false) && ($end_pos !== false))
          $ftp_error = substr($ftp_error,$start_pos + 25,
                              $end_pos - $start_pos - 25);
       $ftp_error = str_replace("\n",' ',$ftp_error);
       $error = 'Unable to login to FTP Server '.$import['ftp_hostname'] .
                ' using username '.$import['ftp_username'].' ('.$ftp_error.')';
       ftp_close($ftp);   ob_end_clean();   process_import_error($error);
       return null;
    }
    ob_end_clean();
    ftp_pasv($ftp,true);
    return $ftp;
}

function close_ftp($ftp)
{
    if (gettype($ftp) == 'resource') ftp_close($ftp);
    else $ftp->disconnect();
}

function cleanup_ftp_filename($filename)
{
    $link_pos = strpos($filename,' -> ');
    if ($link_pos !== false) $filename = substr($filename,0,$link_pos);
    return $filename;
}

function load_ftp_dir($ftp,$import,$dirname)
{
    $months = array('Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,
                    'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12);

    if (gettype($ftp) == 'resource') {
       $sys_type = ftp_systype($ftp);
       $return = @ftp_chdir($ftp,$dirname);
    }
    else {
       $sys_type = 'FTP';
       $return = $ftp->chdir($dirname);
    }
    if (! $return) {
       $error = 'Unable to change to '.$dirname.' directory';
       close_ftp($ftp);   process_import_error($error);   return null;
    }

    if (gettype($ftp) == 'resource') $dir_info = ftp_rawlist($ftp,'.');
    else $dir_info = $ftp->rawlist('.');
    if (! is_array($dir_info)) return null;
    $files = array();
    foreach ($dir_info as $file_info) {
       if (is_array($file_info)) {
          if ($file_info['type'] == 2) $file_info['type'] = 'file';
          else $file_info['type'] = 'directory';
          $files[cleanup_ftp_filename($file_info['filename'])] = $file_info;
       }
       else {
          $chunks = preg_split("/\s+/",$file_info);
          $file = array();
          if ($sys_type == 'Windows_NT') {
             list($file['date'],$file['time'],$file['size']) = $chunks;
             $file['date'] = str_replace('-','/',$file['date']);
             $file['mtime'] = strtotime($file['date'].' '.$file['time']);
             array_splice($chunks,0,3);
             $files[cleanup_ftp_filename(implode(' ',$chunks))] = $file;
          }
          else if (count($chunks) > 8) {
             list($file['rights'],$file['number'],$file['user'],$file['group'],
                  $file['size'],$file['month'],$file['day'],$file['time']) =
                $chunks;
             $file['type'] = $chunks[0]{0} === 'd'?'directory':'file';
             $month = $months[$file['month']];
             if (strpos($file['time'],':') !== false) {
                $year = date('Y');   $curr_month = date('n');
                if ($month > $curr_month) $year -= 1;
                $file['mtime'] = mktime(substr($file['time'],0,2),
                   substr($file['time'],-2),0,$month,$file['day'],$year);
             }
             else $file['mtime'] = mktime(0,0,0,$month,$file['day'],
                                          $file['time']);
             array_splice($chunks,0,8);
             $files[cleanup_ftp_filename(implode(' ',$chunks))] = $file;
          }
       }
    }
    return $files;
}

function update_import_file($db,$import_file,&$import)
{
    if ($import_file != $import['import_file']) {
       $query = 'update vendor_imports set import_file=? where id=?';
       $query = $db->prepare_query($query,$import_file,$import['id']);
       $db->log_query($query);
       if (! $db->query($query)) {
          process_import_error('Update Import File Database Error: ' .
                               $db->error);
          return false;
       }
       $import['import_file'] = $import_file;
    }
    return true;
}

function download_catalog($ftp,$db,&$import)
{
    $path_parts = pathinfo($import['ftp_filename']);
    $dirname = $path_parts['dirname'];
    if (! $dirname) $dirname = '/';
    $files = load_ftp_dir($ftp,$import,$dirname);
    if (! $files) {
       $error = 'Unable to get directory list for '.$dirname;
       close_ftp($ftp);   process_import_error($error);   return false;
    }
    if ($path_parts['filename'] == '*') {
       $catalog_file = null;   $extension = $path_parts['extension'];
       $ext_length = strlen($extension) + 1;
       foreach ($files as $filename => $file_info) {
          if (strtolower(substr($filename,-$ext_length)) == '.'.$extension) {
             $catalog_file = $filename;   break;
          }
       }
    }
    else {
       $catalog_file = $path_parts['basename'];
       $extension = $path_parts['extension'];
    }
    if (! $catalog_file) {
       $error = 'Catalog File not found in '.$dirname;
       close_ftp($ftp);   process_import_error($error);   return false;
    }
    $extension = strtolower($extension);
    $import_file = $import['import_file'];
    if (call_vendor_event($import,'update_catalog_filename',
           array(&$catalog_file,&$extension,$import_file,$files)) === 'skip')
       return $import['manual'];

    $vendor_dir = '../admin/vendors/';
    if ($import_file) {
       $old_extension = pathinfo($import_file,PATHINFO_EXTENSION);
       if (($old_extension != $extension) && ($extension != 'zip')) {
          if (file_exists($vendor_dir.$import_file))
             unlink($vendor_dir.$import_file);
          $import_file = null;
       }
    }
    if (! $import_file) $import_file = 'import-'.$import['id'].'.'.$extension;
    $local_filename = $download_filename = $vendor_dir.$import_file;
    if (($extension == 'zip') && (! empty($import['unzip_filename']))) {
       $unzip_filename = $import['unzip_filename'];
       $unzip_extension = pathinfo($unzip_filename,PATHINFO_EXTENSION);
       $local_filename = substr($local_filename,0,-4).'.'.$unzip_extension;
       $check_size = false;
    }
    else {
       $unzip_filename = null;   $check_size = true;
    }
    if (file_exists($local_filename) &&
        ($files[$catalog_file]['mtime'] == filemtime($local_filename)) &&
        ((! $check_size) ||
         ($files[$catalog_file]['size'] == filesize($local_filename))))
       return $import['manual'];
    if (gettype($ftp) == 'resource')
       $return = ftp_get($ftp,$download_filename,$catalog_file,FTP_BINARY);
    else $return = $ftp->get($catalog_file,$download_filename);
    if (! $return) {
       $error = 'Unable to download Catalog File '.$catalog_file;
       close_ftp($ftp);   process_import_error($error);   return false;
    }

    if ($unzip_filename) {
       $zip = new ZipArchive;
       $result = $zip->open($download_filename);
       if ($result !== true) {
          $error = 'Unable to open zip file '.$download_filename .
                   ' ('.$result.')';
          process_import_error($error);   return false;
       }
       $unzip_file = $zip->getFromName($unzip_filename);
       if (! $unzip_file) {
          $error = 'Zip file '.$download_filename .
                   ' does not contain the file '.$unzip_filename;
          process_import_error($error);   return false;
       }
       if (! file_put_contents($local_filename,$unzip_file)) {
          $error = 'Unable to save import file '.$local_filename;
          process_import_error($error);   return false;
       }
       if (substr(strtolower($import_file),-4) == '.zip') {
          $import_file = substr($import_file,0,-4).'.'.$unzip_extension;
          if (! unlink($download_filename)) {
             $error = 'Unable to delete zip file '.$download_filename;
             process_import_error($error);   return false;
          }
       }
    }

    touch($local_filename,$files[$catalog_file]['mtime']);
    call_vendor_event($import,'convert_catalog_file',
                      array(&$import_file,$local_filename,&$import,$ftp));
    if (! update_import_file($db,$import_file,$import)) return false;
    log_vendor_activity('Downloaded New Catalog File '.$catalog_file.' to ' .
                        $import_file);
    return true;
}

function download_images($ftp,$import,$config_values)
{
    require_once '../cartengine/image.php';

    $image_dir = $import['image_dir'];
    $files = load_ftp_dir($ftp,$import,$image_dir);
    if (! $files) {
       $error = 'Unable to get '.$image_dir.' directory list';
       close_ftp($ftp);   process_import_error($error);   return false;
    }
    $image_record = image_record_definition();
    foreach ($files as $filename => $file_info) {
       if ($file_info['type'] == 'directory') continue;
       $image_filename = strtolower($filename);
       $extension = pathinfo($image_filename,PATHINFO_EXTENSION);
       if (! in_array($extension,array('jpg','gif','png'))) continue;
       $local_filename = '../images/original/'.$image_filename;
       if (file_exists($local_filename) &&
           ($file_info['mtime'] <= filemtime($local_filename))) continue;
       if (gettype($ftp) == 'resource')
          $return = @ftp_get($ftp,$local_filename,$filename,FTP_BINARY);
       else $return = $ftp->get($filename,$local_filename);
       if (! $return) {
          $error = 'Unable to download Image File '.$filename;
          close_ftp($ftp);   process_import_error($error);   return false;
       }
       touch($local_filename,$file_info['mtime']);
       if (process_image($image_filename,$local_filename,null,null,null,null,
                         $config_values,false,null,null,$image_record))
          log_vendor_activity('Updated Product Image '.$image_filename);
    }
    return true;
}

function load_spreadsheet($import)
{
    $import_file = $import['import_file'];
    $full_filename = '../admin/vendors/'.$import_file;
    if ((! $import_file) || (! file_exists($full_filename))) {
       process_import_error('Import File '.$import_file.' not found');
       return null;
    }
    $extension = pathinfo($import_file,PATHINFO_EXTENSION);
    if ($extension == 'xls') $format = 'Excel5';
    else if ($extension == 'xlsx') $format = 'Excel2007';
    else if ($extension == 'xlsm') $format = 'Excel2007';
    else if ($extension == 'csv') $format = 'CSV';
    else if ($extension == 'txt') $format = 'CSV';
    else {
       process_import_error('Unsupported Spreadsheet Format in ' .
                            $import_file);
       return null;
    }
    require_once '../engine/excel.php';
    try {
       $reader = PHPExcel_IOFactory::createReader($format);
       if ($format == 'CSV')
          PHPExcel_Cell::setValueBinder(new BindValueAsString());
       if ($extension == 'txt') {
          if (empty($import['txt_delim'])) $delimiter = "\t";
          else $delimiter = $import['txt_delim'];
          $reader->setDelimiter($delimiter);
       }
       else if (($extension == 'csv') && (! empty($import['txt_delim'])))
          $reader->setDelimiter($import['txt_delim']);
       $excel = $reader->load($full_filename);
       if ($import['sheet_num']) $sheet_num = $import['sheet_num'];
       else $sheet_num = 0;
       $worksheet = $excel->setActiveSheetIndex($sheet_num);
       $data = $worksheet->toArray(null,true,false,false);
    }
    catch (Exception $e) {
       process_import_error('Exception loading data from '.$import_file.': ' .
                            $e->getMessage());
    }

    if (! $data) {
       process_import_error('Unable to load data from '.$import_file);
       return null;
    }
    return $data;
}

function load_map($db,$import,$field_names)
{
    $query = 'select * from vendor_mapping where parent=?' .
             ' order by update_field,sequence';
    $query = $db->prepare_query($query,$import['id']);
    $mapping = $db->get_records($query,'vendor_field');
    if (! $mapping) {
       if (isset($db->error)) $error = $db->error;
       else $error = 'Import does not have any mapped fields';
       process_import_error($error);   return null;
    }
    $map = array();
    foreach ($field_names as $index => $field_name) {
       if (isset($mapping[$field_name])) {
          $map[$index] = $mapping[$field_name];
          unset($mapping[$field_name]);
       }
    }
    if (count($mapping) != 0) {
       $missing_fields = '';
       foreach ($mapping as $field_name => $field_info) {
          if ($missing_fields) $missing_fields .= ', ';
          $missing_fields .= $field_name;
       }
       process_import_error('Missing Mapped Columns: '.$missing_fields);
       return null;
    }
    return $map;
}

function sort_map_sequence($a,$b)
{
    $retval = strcmp($a['update_field'],$b['update_field']);
    if ($retval != 0) return $retval;
    if ($a['sequence'] < $b['sequence']) return -1;
    return 1;
}

function get_sequence_map($map)
{
    $sequence_map = array();
    foreach ($map as $index => $map_info) {
       $map_info['index'] = $index;
       $sequence_map[] = $map_info;
    }
    usort($sequence_map,'sort_map_sequence');
    return $sequence_map;
}

function get_mapped_tables($map,$import)
{
    $tables = array();
    foreach ($map as $map_info) {
       if (! $map_info['update_field']) continue;
       $update_info = explode('|',$map_info['update_field']);
       if (! isset($tables[$update_info[0]]))
          $tables[$update_info[0]] = true;
    }
    if ($import['new_inv_qty']) $tables['inventory'] = true;
    if (function_exists('custom_update_mapped_tables'))
       custom_update_mapped_tables($tables);
    call_vendor_event($import,'update_mapped_tables',array($tables));
    return $tables;
}

function extract_map($map,$table_name)
{
    $extracted_map = array();
    foreach ($map as $index => $map_info) {
       if (! $map_info['update_field']) continue;
       $update_info = explode('|',$map_info['update_field']);
       if ($update_info[0] == $table_name) {
          $map_info['update_field'] = $update_info[1];
          $extracted_map[$index] = $map_info;
       }
    }
    return $extracted_map;
}

function load_markups($db,$import)
{
    if ($import['vendor_markups'] == -1) $parent = -$import['id'];
    else $parent = $import['parent'];
    $query = 'select markup_start,markup_end,markup_type,markup_value from ' .
             'vendor_markups where parent=? order by markup_start,markup_end';
    $query = $db->prepare_query($query,$parent);
    $markups = $db->get_records($query);
    if (! $markups) {
       if (isset($db->error)) {
          process_import_error('Database Error [2]: '.$db->error);
          return null;
       }
       return array();
    }
    return $markups;
}

function load_existing_products($db,$product_data)
{
    $load_existing = $product_data->import['load_existing'];
    $query = 'select * from products where (vendor=?)';
    if (($load_existing == LOAD_IMPORT_PRODUCTS) ||
        ($load_existing == LOAD_OTHER_IMPORT_PRODUCTS))
       $query .= ' and (import_id=?)';
    if (function_exists('custom_update_vendor_import_query'))
       custom_update_vendor_import_query($query,'load_existing_products',1,
                                         $product_data);
    call_vendor_event($product_data->import,'update_import_query',
                      array('load_existing_products',1,$product_data));
    if ($load_existing == LOAD_IMPORT_PRODUCTS)
       $query = $db->prepare_query($query,$product_data->vendor_id,
                                   $product_data->import_id);
    else if ($load_existing == LOAD_OTHER_IMPORT_PRODUCTS)
       $query = $db->prepare_query($query,$product_data->vendor_id,
                                   -$product_data->import['import_source']);
    else $query = $db->prepare_query($query,$product_data->vendor_id);
    $products = $db->get_records($query,'id');
    if (! $products) {
       if (isset($db->error)) {
          process_import_error('Database Error [3]: '.$db->error);
          return null;
       }
       return array();
    }
    return $products;
}

function load_existing_inventory($db,$product_data)
{
    $load_existing = $product_data->import['load_existing'];
    $query = 'select * from product_inventory where parent in (select id ' .
             'from products where (vendor=?)';
    if (($load_existing == LOAD_IMPORT_PRODUCTS) ||
        ($load_existing == LOAD_OTHER_IMPORT_PRODUCTS))
       $query .= ' and (import_id=?)';
    $query .= ')';
    if (function_exists('custom_update_vendor_import_query'))
       custom_update_vendor_import_query($query,'load_existing_inventory',1,
                                         $product_data);
    call_vendor_event($product_data->import,'update_import_query',
                      array('load_existing_inventory',1,$product_data));
    if ($load_existing == LOAD_IMPORT_PRODUCTS)
       $query = $db->prepare_query($query,$product_data->vendor_id,
                                   $product_data->import_id);
    else if ($load_existing == LOAD_OTHER_IMPORT_PRODUCTS)
       $query = $db->prepare_query($query,$product_data->vendor_id,
                                   -$product_data->import['import_source']);
    else $query = $db->prepare_query($query,$product_data->vendor_id);
    $inventory = $db->get_records($query,'id');
    if (! $inventory) {
       if (isset($db->error)) {
          process_import_error('Database Error [4]: '.$db->error);
          return null;
       }
       return array();
    }
    foreach ($inventory as $id => $inv_record)
       $inventory[$id]['index'] = strtolower($inventory[$id]['part_number']);
    return $inventory;
}

function extract_mpns($products)
{
    $mpns = array();
    foreach ($products as $product) {
       if (! $product['shopping_mpn']) continue;
       $mpns[strtolower($product['shopping_mpn'])] = $product['id'];
    }
    return $mpns;
}

function extract_upcs($products)
{
    $upcs = array();
    foreach ($products as $product) {
       if (! $product['shopping_gtin']) continue;
       $upcs[strtolower($product['shopping_gtin'])] = $product['id'];
    }
    return $upcs;
}

function extract_inventory_ids($inventory)
{
    $inventory_ids = array();
    foreach ($inventory as $inv_record) {
       if (! $inv_record['parent']) continue;
       $inventory_ids[$inv_record['parent']] = $inv_record['id'];
    }
    return $inventory_ids;
}

function extract_part_numbers($inventory)
{
    $part_numbers = array();
    foreach ($inventory as $inv_record) {
       if (! $inv_record['part_number']) continue;
       $part_numbers[strtolower($inv_record['part_number'])] =
          $inv_record['id'];
    }
    return $part_numbers;
}

function extract_part_number_products($inventory)
{
    $product_ids = array();
    foreach ($inventory as $inv_record) {
       if (! $inv_record['part_number']) continue;
       $product_ids[strtolower($inv_record['part_number'])] =
          $inv_record['parent'];
    }
    return $product_ids;
}

function extract_additional_match_values($products,$inventory,
                                         $addl_match_table,$addl_match_field)
{
    $match_values = array();
    if ($addl_match_table == 'product') {
       foreach ($products as $product)
          $match_values[$product['id']] =
             strtolower($product[$addl_match_field]);
    }
    else if ($addl_match_table == 'inventory') {
       foreach ($inventory as $inv_record)
          $match_values[$inv_record['id']] =
             strtolower($inventory[$addl_match_field]);
    }
    return $match_values;
}

function find_matching_product(&$part_number,$mpn,$upc,$product_data,
   $addl_match,&$product_id,&$inv_id,&$match_name,&$match_value)
{
    $match_existing = $product_data->import['match_existing'];
    if ($match_existing == MATCH_BY_PART_NUMBER) {
       if (! $part_number) {
          if (DEBUG_LOGGING) log_import('No Part Number found in Product Match');
          return;
       }
       $match_part_number = strtolower($part_number);
       if (isset($product_data->part_numbers[$match_part_number])) {
          $pn_inv_id = $product_data->part_numbers[$match_part_number];
          $inv_record = $product_data->inventory[$pn_inv_id];
          if ($product_data->match_values === null)
             $index = $inv_record['id'];
          else if ($product_data->addl_match_table == 'product')
             $index = $inv_record['parent'];
          else $index = $inv_record['id'];
          if (isset($product_data->match_values[$index]) &&
              ($product_data->match_values[$index] == $addl_match))
             $inv_id = $inv_record['id'];
          else $inv_id = $pn_inv_id;
       }
       if ($inv_id) $product_id = $product_data->inventory[$inv_id]['parent'];
       $match_name = 'Part Number';   $match_value = $match_part_number;
    }
    else if ($match_existing == MATCH_BY_MPN) {
       if (! $mpn) {
          if (DEBUG_LOGGING) log_import('No MPN found in Product Match');
          return;
       }
       $mpn = strtolower($mpn);
       if (isset($product_data->mpns[$mpn])) {
          $mpn_product_id = $product_data->mpns[$mpn];
          if ($product_data->match_values === null)
             $product_id = $mpn_product_id;
          else if ($product_data->addl_match_table == 'product') {
             if (isset($product_data->match_values[$mpn]) &&
                 ($product_data->match_values[$mpn] == $addl_match))
                $product_id = $mpn_product_id;
          }
          else if (isset($product_data->inventory_ids[$mpn_product_id])) {
             $parent_inv_id = $product_data->inventory_ids[$mpn_product_id];
             $inv_record = $product_data->inventory[$parent_inv_id];
             $compare_id = $inv_record['id'];
             if (isset($product_data->match_values[$compare_id]) &&
                 ($product_data->match_values[$compare_id] ==
                  $inv_record['index'])) {
                $product_id = $mpn_product_id;   $inv_id = $compare_id;
             }
          }
       }
       if ($product_id) {
          if ((! $inv_id) && isset($product_data->inventory_ids[$product_id]))
             $inv_id = $product_data->inventory_ids[$product_id];
          if (isset($product_data->inventory[$inv_id]['index']))
             $part_number = $product_data->inventory[$inv_id]['index'];
       }
       $match_name = 'MPN';   $match_value = $mpn;
    }
    else if ($match_existing == MATCH_BY_UPC) {
       if (! $upc) {
          if (DEBUG_LOGGING) log_import('No UPC found in Product Match');
          return;
       }
       $upc = strtolower($upc);
       if (isset($product_data->upcs[$upc])) {
          $upc_product_id = $product_data->upcs[$upc];
          if ($product_data->match_values === null)
             $product_id = $upc_product_id;
          else if ($product_data->addl_match_table == 'product') {
             if (isset($product_data->match_values[$upc]) &&
                 ($product_data->match_values[$upc] == $addl_match))
                $product_id = $upc_product_id;
          }
          else if (isset($product_data->inventory_ids[$upc_product_id])) {
             $parent_inv_id = $product_data->inventory_ids[$upc_product_id];
             $inv_record = $product_data->inventory[$parent_inv_id];
             $compare_id = $inv_record['id'];
             if (isset($product_data->match_values[$compare_id]) &&
                 ($product_data->match_values[$compare_id] ==
                  $inv_record['index'])) {
                $product_id = $upc_product_id;   $inv_id = $compare_id;
             }
          }
       }
       if ($product_id) {
          if ((! $inv_id) && isset($product_data->inventory_ids[$product_id]))
             $inv_id = $product_data->inventory_ids[$product_id];
          if (isset($product_data->inventory[$inv_id]['index']))
             $part_number = $product_data->inventory[$inv_id]['index'];
       }
       $match_name = 'UPC';   $match_value = $upc;
    }
    call_shopping_event('find_matching_vendor_product',
          array(&$part_number,$mpn,$upc,$product_data,$addl_match,
                &$product_id,&$inv_id,&$match_name,&$match_value),false,true);
    call_vendor_event($product_data->import,'find_matching_product',
          array(&$part_number,$mpn,$upc,$product_data,$addl_match,
                &$product_id,&$inv_id,&$match_name,&$match_value));
    if (DEBUG_LOGGING) {
       if ((! $product_id) && (! $inv_id)) {
          $log_string = 'Unable to match '.$match_name.' '.$match_value;
          if ($addl_match) $log_string .= ' ('.$addl_match.')';
       }
       else {
          $log_string = 'Matched '.$match_name.' '.$match_value;
          if ($addl_match) $log_string .= ' ('.$addl_match.')';
          $log_string .= ' to Product ID '.$product_id.' and Inventory ID ' .
                         $inv_id;
       }
       log_import($log_string);
    }
}

function load_category_map($db,$vendor_id)
{
    $query = 'select vendor_category,category_id from category_mapping ' .
             'where vendor_id=?';
    $query = $db->prepare_query($query,$vendor_id);
    $category_map = $db->get_records($query,'vendor_category');
    if (! $category_map) {
       if (isset($db->error)) {
          process_import_error('Database Error [5]: '.$db->error);
          return null;
       }
       return array();
    }
    return $category_map;
}

function sublist_record_definition($related_type=null)
{
    $sublist_record = array();
    if ($related_type !== null) {
       $sublist_record['related_type'] = array('type' => INT_TYPE);
       $sublist_record['related_type']['value'] = $related_type;
    }
    $sublist_record['parent'] = array('type' => INT_TYPE);
    $sublist_record['related_id'] = array('type' => INT_TYPE);
    $sublist_record['sequence'] = array('type' => INT_TYPE);
    return $sublist_record;
}

function data_record_definition()
{
    $data_record = array();
    $data_record['sequence'] = array('type' => INT_TYPE);
    $data_record['parent'] = array('type' => INT_TYPE);
    $data_record['data_type'] = array('type' => INT_TYPE);
    $data_record['label'] = array('type' => CHAR_TYPE);
    $data_record['data_value'] = array('type' => CHAR_TYPE);
    return $data_record;
}

function mapping_record_definition()
{
    $mapping_record = array();
    $mapping_record['vendor_id'] = array('type' => INT_TYPE);
    $mapping_record['vendor_category'] = array('type' => CHAR_TYPE);
    $mapping_record['num_products'] = array('type' => INT_TYPE);
    return $mapping_record;
}

function format_data($data,$field_name,$field_defs)
{
    $data = cleanup_data(trim($data),$field_name);
    $field_type = $field_defs[$field_name]['type'];
    if ($field_type == INT_TYPE) {
       $field_value = strtolower($data);
       if ($data == '') $data = null;
       else if (($field_value == 'yes') || ($field_value == 'y')) $data = 1;
       else if ($field_value == 'true') $data = 1;
       else if (($field_value == 'no') || ($field_value == 'n')) $data = 0;
       else if ($field_value == 'false') $data = 0;
       else if (substr($field_value,0,2) == 'no') $data = 0;
       else if (is_numeric($data)) $data = intval($data);
       else if ($data) $data = 1;
       else $data = 0;
    }
    else if ($field_type == FLOAT_TYPE) {
       if ($data === '') $data = null;
       else if ($data !== null) {
          $data = floatval($data);
          $size_info = explode(',',$field_defs[$field_name]['size']);
          $data = number_format($data,$size_info[1],'.','');
       }
    }
    return $data;
}

function get_attachment_filename($url)
{
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_HEADER,true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,2);
    curl_setopt($ch,CURLOPT_TIMEOUT,2);
    curl_setopt($ch,CURLOPT_NOBODY,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
    $head = curl_exec($ch);
    curl_close($ch);
    $head_lines = explode("\n",$head);
    foreach ($head_lines as $header) {
       if (substr($header,0,21) == 'Content-Disposition: ') {
          $start_pos = strpos($header,'filename');
          if ($start_pos === false) continue;
          $start_pos = strpos($header,'"',$start_pos);
          if ($start_pos === false) continue;
          $start_pos += 1;
          $end_pos = strpos($header,'"',$start_pos);
          if ($end_pos === false) continue;
          return substr($header,$start_pos,$end_pos - $start_pos);
       }
    }
    return null;
}

function get_update_field($map_info,$product_data)
{
    $update_info = explode('|',$map_info['update_field']);
    if (($update_info[0] == 'product') &&
        isset($product_data->product_record[$update_info[1]]['value']))
       return $product_data->product_record[$update_info[1]]['value'];
    else if (($update_info[0] == 'inventory') &&
             isset($product_data->inventory_record[$update_info[1]]['value']))
       return $product_data->inventory_record[$update_info[1]]['value'];
    else if (($update_info[0] == 'rebate') &&
             isset($product_data->rebate_record[$update_info[1]]['value']))
       return $product_data->rebate_record[$update_info[1]]['value'];
    return '';
}

function set_update_field($map_info,&$product_data,$field_value)
{
    $update_info = explode('|',$map_info['update_field']);
    if ($update_info[0] == 'product')
       $product_data->product_record[$update_info[1]]['value'] = $field_value;
    else if ($update_info[0] == 'inventory')
       $product_data->inventory_record[$update_info[1]]['value'] =
          $field_value;
    else if ($update_info[0] == 'rebate')
       $product_data->rebate_record[$update_info[1]]['value'] =
          $field_value;
}

function unset_update_field($map_info,&$product_data)
{
    $update_info = explode('|',$map_info['update_field']);
    if ($update_info[0] == 'product')
       unset($product_data->product_record[$update_info[1]]['value']);
    else if ($update_info[0] == 'inventory')
       unset($product_data->inventory_record[$update_info[1]]['value']);
    else if ($update_info[0] == 'rebate')
       unset($product_data->rebate_record[$update_info[1]]['value']);
}

function calc_markup($product_price,$markups)
{
    $price = floatval($product_price);
    foreach ($markups as $markup) {
       if ((($markup['markup_start'] === null) ||
            ($price >= $markup['markup_start'])) &&
           (($markup['markup_end'] === null) ||
            ($price <= $markup['markup_end']))) {
          $markup_value = floatval($markup['markup_value']);
          if ($markup['markup_type'] == 1) 
             $new_price = $price * (($markup_value/100) + 1.0);
          else if ($markup['markup_type'] == 2)
             $new_price = $price + $markup_value;
          else $new_price = $price;
          $new_price = number_format($new_price,2,'.','');
          return $new_price;
       }
    }
    return $price;
}


function process_conversion($map_info,&$product_data)
{
    if (! $map_info['update_field']) return true;
    $convert_function = $map_info['convert_funct'];
    switch ($convert_function) {
       case 'trimfilename':
          $field_value = get_update_field($map_info,$product_data);
          $slash_pos = strrpos($field_value,'/');
          if ($slash_pos !== false) {
             $field_value = substr($field_value,$slash_pos + 1);
             set_update_field($map_info,$product_data,$field_value);
          }
          break;
       case 'setmarkupprice':
          $field_value = get_update_field($map_info,$product_data);
          if (! $field_value) return;
          $markups = $product_data->markups;
          $new_price = calc_markup($field_value,$markups);
          $product_data->product_record['price']['value'] = $new_price;
          break;
       case 'parseyoutube':
          $field_value = get_update_field($map_info,$product_data);
          $start_pos = strpos($field_value,'v=');
          if ($start_pos === false) return;
          $end_pos = strpos($field_value,'?',$start_pos);
          if ($end_pos === false) $field_value = substr($field_value,$start_pos + 2);
          else $field_value = substr($field_value,$start_pos + 2,
                                     $end_pos - $start_pos - 2);
          set_update_field($map_info,$product_data,$field_value);
          break;
       case 'convertdate':
          $field_value = get_update_field($map_info,$product_data);
          $field_value = strtotime($field_value);
          set_update_field($map_info,$product_data,$field_value);
          break;
       case 'setbitvalue':
          $update_info = explode('|',$map_info['update_field']);
          $field_name = $update_info[1];
          $field_value = $product_data->row[$map_info['index']];
          $sequence = $map_info['sequence'];
          $field_value = strtolower($field_value);
          if (($field_value == 'yes') || ($field_value == 'y')) $bitvalue = 1;
          else if ($field_value == 'true') $bitvalue = 1;
          else if (($field_value == 'no') || ($field_value == 'n'))
             $bitvalue = 0;
          else if ($field_value == 'false') $bitvalue = 0;
          else if (is_numeric($field_value)) $bitvalue = intval($field_value);
          else if ($field_value) $bitvalue = 1;
          else $bitvalue = 0;
          if (isset($product_data->product_record[$field_name]['value']))
             $field_value = intval($product_data->
                                   product_record[$field_name]['value']);
          else if (! empty($product_data->product_info[$field_name]))
             $field_value = intval($product_data->product_info[$field_name]);
          else $field_value = 0;
          if ($bitvalue) $field_value |= (1 << $sequence);
          else $field_value &= ~(1 << $sequence);
          set_update_field($map_info,$product_data,$field_value);
          break;
    }
    return true;
}

function update_product_seo_url(&$product_data,$product_info)
{
    if (! empty($product_data->product_record['seo_url']['value'])) return;
    if (! empty($product_info['seo_url'])) {
       $product_data->product_record['seo_url']['value'] =
          $product_info['seo_url'];
       return;
    }
    if (! empty($product_data->product_record['display_name']['value']))
       $name = $product_data->product_record['display_name']['value'];
    else if (! empty($product_data->product_record['name']['value']))
       $name = $product_data->product_record['name']['value'];
    else return;
    $seo_url = create_default_seo_url($product_data->db,$name,null);
    if ($seo_url) $product_data->product_record['seo_url']['value'] = $seo_url;
}

function delete_product_records($db,$product_id,$delete_info)
{
    global $related_types;

    if (! empty($delete_info['inventory'])) {
       if (module_attached('delete_inventory') || using_linked_inventory($db)) {
          $query = 'select * from product_inventory where parent=?';
          $query = $db->prepare_query($query,$product_id);
          $rows = $db->get_records($query);
          if ($rows) {
             $product_info = load_product_info($db,$product_id);
             foreach ($rows as $inventory_info) {
                if (! call_vendor_module_event($db,DELETERECORD,'inventory',
                         $product_id,$product_info,$inventory_info,$error)) {
                   process_import_error($error);   return false;
                }
             }
             if (using_linked_inventory($db))
                delete_linked_inventory($db,$inventory_info['id']);
          }
       }
       $query = 'delete from product_inventory where parent=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error [6]: '.$db->error;
          process_import_error($error);   return false;
       }
    }
    if (! empty($delete_info['images'])) {
       $query = 'delete from images where parent_type=1 and parent=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error [7]: '.$db->error;
          process_import_error($error);   return false;
       }
    }
    if (! empty($delete_info['category_products'])) {
       $query = 'delete from category_products where related_id=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error [8]: '.$db->error;
          process_import_error($error);   return false;
       }
    }
    if (isset($related_types)) {
       foreach ($related_types as $related_type => $label) {
          if (! empty($delete_info['related_'.$related_type])) {
             $query = 'delete from related_products where ' .
                      '(related_type=?) and (parent=?)';
             $query = $db->prepare_query($query,$related_type,$product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                $error = 'Database Error [9]: '.$db->error;
                process_import_error($error);   return false;
             }
             $query = 'delete from related_products where ' .
                      '(related_type=?) and (related_id=?)';
             $query = $db->prepare_query($query,$related_type,$product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                $error = 'Database Error [10]: '.$db->error;
                process_import_error($error);   return false;
             }
          }
       }
    }
    if (! empty($delete_info['product_data'])) {
       $query = 'delete from product_data where parent=?';
       if ($delete_info['product_data'] != '*') {
          $product_data = explode(',',$delete_info['product_data']);
          $query .= ' and data_type in (?)';
          $query = $db->prepare_query($query,$product_id,$product_data);
       }
       else $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error [11]: '.$db->error;
          process_import_error($error);   return false;
       }
    }
    if (! empty($delete_info['attributes'])) {
       $query = 'delete from product_attributes where parent=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error [11a]: '.$db->error;
          process_import_error($error);   return false;
       }
    }
    if (! empty($delete_info['rebates'])) {
       $query = 'delete from product_rebates where parent=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error [11b]: '.$db->error;
          process_import_error($error);   return false;
       }
    }
    return true;
}

function delete_vendor_product($db,$product_id,$old_status)
{
    global $off_sale_option,$related_types,$enable_rebates;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    $delete_info = array('inventory'=>true,'images'=>true,
       'category_products'=>true,'product_data'=>'*',
       'attributes'=>true);
    if (isset($related_types)) {
       foreach ($related_types as $related_type => $label)
          $delete_info['related_'.$related_type] = true;
    }
    if (! empty($enable_rebates)) $delete_info['rebates'] = true;
    $new_status = $off_sale_option;
    if (! call_shopping_event('update_product_status',
             array($db,$product_id,$old_status,$new_status,&$error),false)) {
       process_import_error($error);   return false;
    }
    if (! call_vendor_module_event($db,DELETERECORD,'product',
                                   $product_id,null,null,$error)) {
       process_import_error($error);   return false;
    }
    $query = 'delete from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = 'Database Error [12-1]: '.$db->error;
       process_import_error($error);   return false;
    }
    if (! delete_product_records($db,$product_id,$delete_info))
       return false;
    if (! call_shopping_event('delete_product_record',
                              array($db,$row,&$error),false)) {
       $error = 'Shopping Delete Error [13]: '.$error;
       process_import_error($error);   return false;
    }
    log_vendor_activity('Deleted Product #'.$product_id);
    return true;
}

function remove_deleted_products($db,$import_id,$products,$action)
{
    require_once 'shopping-common.php';

    if (DEBUG_LOGGING) log_import('Removing Deleted Products');

    foreach ($products as $product_id => $row) {
       if ($action == NON_MATCH_DELETE) {
          if (! delete_vendor_product($db,$product_id,$row['status']))
             return false;
       }
       else {
          if (! call_shopping_event('update_product_status',
                   array($db,$product_id,$row['status'],$action,&$error),
                   false)) {
             process_import_error($error);   return false;
          }
          if ($row['status'] == $action) continue;
          $query = 'update products set status=? where id=?';
          $query = $db->prepare_query($query,$action,$product_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             $error = 'Database Error [12-3]: '.$db->error;
             process_import_error($error);   return false;
          }
          if (! call_vendor_module_event($db,UPDATERECORD,'product',
                                         $product_id,null,null,$error)) {
             process_import_error($error);   return false;
          }
          log_vendor_activity('Set Product #'.$product_id.' to Status #' .
                              $action);
          $activity = 'Product not in import updated by Vendor Import #' .
                      $import_id.', Status: '.$row['status'].'=>'.$action;
          write_product_activity($activity,$product_id,$db);
       }
    }
    return true;
}

function reset_vendor_sub_products($db,$vendor_id)
{
    global $subproduct_type,$product_group_field;

    if (! isset($subproduct_type)) {
       process_import_error('SubProduct Type is not defined');
       return false;
    }
    $query = 'delete from related_products where (related_type=?) and ' .
             '(parent in (select id from products where vendor=?))';
    $query = $db->prepare_query($query,$subproduct_type,$vendor_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = 'Database Error [14]: '.$db->error;
       process_import_error($error);   return false;
    }
    $query = 'select id,'.$product_group_field.' from products where ' .
             'vendor=? order by '.$product_group_field.',id';
    $query = $db->prepare_query($query,$vendor_id);
    $products = $db->get_records($query);
    $product_groups = array();
    foreach ($products as $product) {
       $group_id = $product[$product_group_field];
       if (! $group_id) continue;
       if (! isset($product_groups[$group_id]))
          $product_groups[$group_id] = array();
       $product_groups[$group_id][] = $product['id'];
    }
    $subproduct_record = sublist_record_definition($subproduct_type);
    foreach ($product_groups as $products) {
       if (count($products) == 1) continue;
       $parent_products = $products;
       $related_products = $products;
       foreach ($parent_products as $parent) {
          $sequence = 0;
          foreach ($related_products as $related_id) {
             if ($related_id == $parent) continue;
             $subproduct_record['parent']['value'] = $parent;
             $subproduct_record['related_id']['value'] = $related_id;
             $subproduct_record['sequence']['value'] = $sequence;
             if (! $db->insert('related_products',$subproduct_record)) {
                $error = 'Database Error [15]: '.$db->error;
                process_import_error($error);   return false;
             }
             $sequence++;
          }
       }
    }

    return true;
}

function cleanup_image_filename($filename,$image)
{
    if (strpos($image,'ebayimg.com') !== false) {
       $slash_pos = strrpos($image,'/');
       if ($slash_pos !== false) {
          $image = substr($image,0,$slash_pos);
          $slash_pos = strrpos($image,'/');
          if ($slash_pos !== false) {
             $dir = substr($image,$slash_pos + 1);
             $filename = $dir.'-'.$filename;
          }
       }
    }
    $filename = strtolower($filename);
    $filename = str_replace(' ','_',$filename);
    $char_pos = strpos($filename,'%');
    while ($char_pos !== false) {
       $filename = substr($filename,0,$char_pos).'_' .
                   substr($filename,$char_pos + 3);
       $char_pos = strpos($filename,'%');
    }
    return $filename;
}

function empty_required($map_info,$field_value)
{
    if (! $map_info['required']) return false;
    $field_value = trim($field_value);
    if (empty($field_value)) return true;
    if (! $field_value) return true;
    if ($field_value == '0.0') return true;
    return false;
}

function process_vendor_import($import)
{
    global $import_log_filename,$skip_vendor_import_statuses;
    global $free_shipping_option,$related_types,$subproduct_type;
    global $product_group_field;

    $import_id = $import['id'];
    if (! $import_id) {
       process_import_error('Invalid Import ID');   return;
    }
    $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
    if (file_exists($import_log_filename)) unlink($import_log_filename);
    set_remote_user('Vendor Import #'.$import_id);
    $vendor_id = $import['parent'];
    $import_types = array(PRODUCTS_IMPORT_TYPE => 'Products',
                          INVENTORY_IMPORT_TYPE => 'Inventory',
                          PRICES_IMPORT_TYPE => 'Prices',
                          IMAGES_IMPORT_TYPE => 'Images',
                          DATA_IMPORT_TYPE => 'Data');
    if (! $import['import_type']) {
       process_import_error('Invalid Import Type');   return;
    }
    if ($import['import_source'] == EDI_IMPORT_SOURCE) return;
    if ((! $import['manual']) &&
        ($import['import_source'] == UPLOAD_IMPORT_SOURCE)) {
       $updated = filemtime('../admin/vendors/'.$import['import_file']);
       if (($import['import_started'] > $updated) ||
           ($import['import_finished'] > $updated)) return;
    }

    $db = new DB;
    if (! call_vendor_event($import,'start_import',array(&$db,&$import))) {
       $db->close();   return;
    }
    $log_msg = 'Started Vendor Import #'.$import_id.' ('.$import['name'] .
               ') for Vendor '.$import['vendor_name'];
    log_vendor_activity($log_msg);
    if (DEBUG_LOGGING) log_import($log_msg);
    if (! isset($free_shipping_option)) $free_shipping_option = 3;
    $query = 'update vendor_imports set import_started=?,' .
             'import_finished=null where id=?';
    $query = $db->prepare_query($query,time(),$import_id);
    $db->log_query($query);
    if (DEBUG_LOGGING) log_import($query);
    if (! $db->query($query)) {
       process_import_error('Database Error [16]: '.$db->error);
       $db->close();   return;
    }
    $config_values = load_config_values($db);

    if (($import['import_source'] == FTP_IMPORT_SOURCE) ||
        ($import['import_source'] == SFTP_IMPORT_SOURCE)) {
       $ftp = open_ftp($import);
       if (! $ftp) return;
       if ($import['import_type'] != IMAGES_IMPORT_TYPE) {
          if (! download_catalog($ftp,$db,$import)) {
             close_ftp($ftp);   $db->close();   return;
          }
          else if (DEBUG_LOGGING) log_import('Downloaded Catalog');
       }
    }
    else if ($import['import_source'] == DOWNLOAD_IMPORT_SOURCE) {
       if (function_exists('download_custom_vendor_catalog')) {
          if (! download_custom_vendor_catalog($db,$import)) {
             $db->close();   return;
          }
       }
       if (! call_vendor_event($import,'download_catalog',
                               array($db,&$import))) {
          $db->close();   return;
       }
    }
    else if ($import['import_source'] < 0) {
       $query = 'select import_file from vendor_imports where id=?';
       $query = $db->prepare_query($query,-$import['import_source']);
       $row = $db->get_record($query);
       if (! empty($row['import_file']))
          $import['import_file'] = $row['import_file'];
    }

    if ((($import['import_source'] == FTP_IMPORT_SOURCE) ||
         ($import['import_source'] == SFTP_IMPORT_SOURCE)) &&
        $import['image_dir'] && (substr($import['image_dir'],0,4) != 'http')) {
       $downloading_images = true;
       if (DEBUG_LOGGING) log_import('Started Downloading Images');
       while ($downloading_images) {
          if (download_images($ftp,$import,$config_values))
             $downloading_images = false;
          else {
             close_ftp($ftp);
             $ftp = open_ftp($import);
             if (! $ftp) {   $db->close();   return;   }
          }
       }
       if (DEBUG_LOGGING) log_import('Finished Downloading Images');
    }

    if (($import['import_source'] == FTP_IMPORT_SOURCE) ||
        ($import['import_source'] == SFTP_IMPORT_SOURCE)) close_ftp($ftp);

    if ($import['import_source'] == API_IMPORT_SOURCE) {
       if (DEBUG_LOGGING) log_import('Started Loading Vendor Data from API');
       if (! call_vendor_event($import,'get_data',
                               array(&$db,&$import,&$data))) {
          $db->close();   return;
       }
       if (DEBUG_LOGGING) log_import('Finished Loading Vendor Data from API');
       call_vendor_event($import,'import_field_names',
                         array($db,$import,&$field_names));
       $header_row = -1;
    }
    else {
       if (DEBUG_LOGGING) log_import('Started Loading Spreadsheet');
       $data = load_spreadsheet($import);
       if (! $data) {   $db->close();   return;   }
       if (DEBUG_LOGGING) log_import('Finished Loading Spreadsheet');
       $header_row = $import['start_row'];
       $field_names = null;
       if (! $header_row) {
          call_vendor_event($import,'import_field_names',
                            array($db,$import,&$field_names));
          if ($field_names) $header_row = 0;
          else $header_row = 1;
       }
       if (! $field_names)
          $field_names = get_unique_field_names($data[$header_row - 1]);
    }
    $map = load_map($db,$import,$field_names);
    if (! $map) {   $db->close();   return;   }
    $sequence_map = get_sequence_map($map);
    $tables = get_mapped_tables($map,$import);
    $markups = load_markups($db,$import);
    if ($markups === null) {   $db->close();   return;   }
    $match_existing = $import['match_existing'];
    if (! $match_existing) {
       process_import_error('No Match Existing Products By option set');
       $db->close();   return;
    }
    $image_options = $import['image_options'];

    $product_data = new stdClass();
    $product_data->db = $db;
    $product_data->vendor_id = $vendor_id;
    $product_data->import_id = $import_id;
    $product_data->import = $import;
    $product_data->map = $map;
    $product_data->markups = $markups;
    $product_data->tables = $tables;
    $product_data->config_values = $config_values;
    $product_data->product_ids = array();

    if (DEBUG_LOGGING) log_import('Loading Existing Products');
    $products = load_existing_products($db,$product_data);
    if ($products === null) {   $db->close();   return;   }
    $product_data->products = $products;
    if (DEBUG_LOGGING)
       log_import('Finished Loading Existing Products, ' .
                  'Loading Existing Inventory');
    $inventory = load_existing_inventory($db,$product_data);
    if ($inventory === null) {   $db->close();   return;   }
    $product_data->inventory = $inventory;
    if (DEBUG_LOGGING) log_import('Finished Loading Existing Inventory');
    if (function_exists('load_custom_vendor_import_data')) {
       $custom_data = load_custom_vendor_import_data($db,$product_data);
       if ($custom_data === null) {   $db->close();   return;   }
    }
    else $custom_data = null;
    call_vendor_event($import,'load_custom_data',
                      array($db,&$product_data,&$custom_data));
    $product_data->custom_data = $custom_data;
    $tables = $product_data->tables;
    $mpns = $upcs = $inventory_ids = null;
    if ($match_existing == MATCH_BY_PART_NUMBER) {
       $part_number_index = -1;
       foreach ($map as $index => $map_info) {
          if ($map_info['update_field'] == 'inventory|part_number') {
             $part_number_index = $index;   break;
          }
          else if ($map_info['convert_funct'] == 'lookuppartnumber') {
             $part_number_index = $index;   break;
          }
       }
       if ($part_number_index == -1) {
          process_import_error('Part Number must be mapped');
          $db->close();   return;
       }
       $product_data->product_ids = extract_part_number_products($inventory);
    }
    else if ($match_existing == MATCH_BY_MPN) {
       $mpn_index = -1;
       foreach ($map as $index => $map_info) {
          if ($map_info['update_field'] == 'product|shopping_mpn') {
             $mpn_index = $index;   break;
          }
       }
       if ($mpn_index == -1) {
          process_import_error('MPN must be mapped');   $db->close();   return;
       }
       $mpns = extract_mpns($products);
       $inventory_ids = extract_inventory_ids($inventory);
       $product_data->product_ids = $mpns;
    }
    else if ($match_existing == MATCH_BY_UPC) {
       $upc_index = -1;
       foreach ($map as $index => $map_info) {
          if ($map_info['update_field'] == 'product|shopping_gtin') {
             $upc_index = $index;   break;
          }
       }
       if ($upc_index == -1) {
          process_import_error('UPC must be mapped');   $db->close();   return;
       }
       $upcs = extract_upcs($products);
       $inventory_ids = extract_inventory_ids($inventory);
       $product_data->product_ids = $upcs;
    }
    if (! isset($product_data->mpns)) $product_data->mpns = $mpns;
    if (! isset($product_data->upcs)) $product_data->upcs = $upcs;
    if (! isset($product_data->inventory_ids))
       $product_data->inventory_ids = $inventory_ids;
    $product_data->part_numbers = extract_part_numbers($inventory);
    $addl_match_index = -1;
    if ($import['addl_match_field']) {
       foreach ($map as $index => $map_info) {
          if ($map_info['update_field'] == $import['addl_match_field']) {
             $addl_match_index = $index;   break;
          }
       }
       if ($addl_match_index == -1) {
          process_import_error('Additional Match Field must be mapped');
          $db->close();   return;
       }
       $field_parts = explode('|',$import['addl_match_field']);
       $addl_match_table = $field_parts[0];
       $addl_match_field = $field_parts[1];
       $match_values = extract_additional_match_values($products,$inventory,
                          $addl_match_table,$addl_match_field);
       $product_data->match_values = $match_values;
       $product_data->addl_match_table = $addl_match_table;
    }
    else $product_data->match_values = null;
    $status_index = -1;
    if ($import['avail_value'] === null) $import['avail_value'] == '';
    if ($import['notavail_value'] === null) $import['notavail_value'] == '';
    $using_product_groups = false;
    if (isset($product_group_field))
       $product_group_match = 'product|'.$product_group_field;
    else $product_group_match = null;
    foreach ($map as $index => $map_info) {
       if ($map_info['update_field'] == 'product|status')
          $status_index = $index;
       if ($product_group_match &&
           ($map_info['update_field'] == $product_group_match)) {
          $using_product_groups = true;   $product_group_index = $index;
       }
       else if ($map_info['convert_funct'] == 'productgroup') {
          $using_product_groups = true;   $product_group_index = $index;
          $field_info = explode('|',$map_info['update_field']);
          if ($field_info[0] != 'product') {
             process_import_error('Product Group can only be a Product field');
             $db->close();   return;
          }
          $product_group_field = $field_info[1];
       }
    }
    $product_data->using_product_groups = $using_product_groups;

    $current_time = time();
    if (isset($tables['product'])) {
       $product_map = extract_map($map,'product');
       $product_fields = $db->get_field_defs('products');
       $product_record = product_record_definition();
       $product_record['vendor']['value'] = $vendor_id;
       $product_record['last_modified']['value'] = $current_time;
    }
    if (isset($tables['inventory'])) {
       $inventory_map = extract_map($map,'inventory');
       $inventory_fields = $db->get_field_defs('product_inventory');
       $inventory_record = inventory_record_definition();
       $inventory_record['sequence']['value'] = (string) 0;
       $inventory_record['last_modified']['value'] = $current_time;
    }
    if (isset($tables['images'])) {
       $image_record = image_record_definition();
       $image_record['parent_type']['value'] = 1;
       $image_map = extract_map($sequence_map,'images');
    }
    if (isset($tables['category'])) {
       $category_map = load_category_map($db,$vendor_id);
       if ($category_map === null) {
          $db->close();   return;
       }
       $category_product_record = sublist_record_definition();
       $categories = array();
       $cat_map = extract_map($sequence_map,'category');
    }
    if (isset($tables['product_data'])) {
       $data_record = data_record_definition();
       if ($import['import_type'] == DATA_IMPORT_TYPE)
          $data_sequences = array();
       $product_data_map = extract_map($sequence_map,'product_data');
       $delete_product_data = '';
       foreach ($product_data_map as $map_info) {
          if ($delete_product_data) $delete_product_data .= ',';
          $delete_product_data .= $map_info['update_field'];
       }
    }
    if (isset($tables['related'])) {
       $related_product_record = sublist_record_definition(0);
       $related_map = extract_map($map,'related');
    }
    else {
       $related_product_record = array();
       if (isset($tables['related_0']) ||
           ($import['flags'] & ADD_GROUP_RELATED)) {
          $related_product_record[0] = sublist_record_definition(0);
       }
       if (isset($related_types)) {
          $related_maps = array();
          foreach ($related_types as $related_type => $label) {
             if ($related_type == 0) continue;
             if (isset($tables['related']) ||
                 isset($tables['related_'.$related_type])) {
                $related_product_record[$related_type] =
                   sublist_record_definition($related_type);
             }
             $related_map = extract_map($map,'related_'.$related_type);
             foreach ($related_map as $index => $map_info) {
                if ($map_info['update_field'] != '*')
                   unset($related_map[$index]);
             }
             $related_maps[$related_type] = $related_map;
          }
       }
    }
    if (isset($tables['rebate'])) {
       $rebate_record = product_rebate_record_definition();
       $rebate_map = extract_map($map,'rebate');
    }

    $mapping_record = mapping_record_definition();
    $mapping_record['vendor_id']['value'] = $vendor_id;
    $mapping_record['vendor_id']['key'] = true;
    $mapping_record['vendor_category']['key'] = true;
    if ($using_product_groups && isset($subproduct_type)) {
       $product_groups = array();
       $subproduct_record = sublist_record_definition($subproduct_type);
    }
    if ($import['attribute_set']) {
       $product_attribute_record = sublist_record_definition();
       $query = 'select * from attribute_set_attributes ' .
                'order by parent,sequence';
       $set_rows = $db->get_records($query);
       $attribute_sets = array();
       if ($set_rows) foreach ($set_rows as $set_row) {
          $parent = $set_row['parent'];
          if (! isset($attribute_sets[$parent]))
             $attribute_sets[$parent] = array();
          $attribute_sets[$parent][] = $set_row;
       }
    }

    $downloaded_images = array();
    $failed_image_download_products = array();
    $db->close();

/* START OF MAIN LOOP */

    foreach ($data as $row_index => $row) {
       if ($row_index < $header_row) continue;
       if (function_exists('custom_check_vendor_data') &&
           (! custom_check_vendor_data($product_data,$row))) continue;
       if (! call_shopping_event('check_vendor_data',
                                 array(&$product_data,$row),false,false))
          continue;
       if (! call_vendor_event($import,'check_vendor_data',
                               array(&$product_data,$row))) continue;
       $db = new DB;
       $product_data->db = $db;
       $product_data->row = $row;
       $failed_image_download_products[0] = false;
       $mpn = $upc = $part_number = null;
       if ($match_existing == MATCH_BY_PART_NUMBER) {
          $part_number = strtolower(trim($row[$part_number_index]));
          if ($import['partnum_prefix'])
             $part_number = strtolower($import['partnum_prefix']).$part_number;
       }
       else if ($match_existing == MATCH_BY_MPN)
          $mpn = strtolower(trim($row[$mpn_index]));
       else if ($match_existing == MATCH_BY_UPC)
          $upc = strtolower(trim($row[$upc_index]));
       if ($import['addl_match_field'])
          $addl_match = strtolower(trim($row[$addl_match_index]));
       else $addl_match = null;

       $inventory_info = $product_info = $product_id = $inv_id = null;
       find_matching_product($part_number,$mpn,$upc,$product_data,$addl_match,
                             $product_id,$inv_id,$match_name,$match_value);
       if ($product_id && isset($product_data->products[$product_id])) {
          $product_info = $product_data->products[$product_id];
          if (isset($products[$product_id])) {
             unset($products[$product_id]);   $first_product_row = true;
          }
          else $first_product_row = false;
       }
       else $first_product_row = false;
       if ($inv_id && isset($inventory[$inv_id]))
          $inventory_info = $inventory[$inv_id];

       if ((! $product_id) && (($import['new_status'] === '') ||
                               ($import['new_status'] === null))) {
          if (DEBUG_LOGGING)
             log_import('Skipping New Product for '.$match_name.' ' .
                        $match_value);
          $db->close();   continue;
       }

       if (DEBUG_LOGGING) {
          if ($product_id)
             log_import('Updating Product #'.$product_id.' for '.$match_name .
                        ' '.$match_value);
          else log_import('Adding New Product for '.$match_name.' ' .
                         $match_value);
       }

       if (isset($tables['product'])) {
          $product_data->product_record = $product_record;
          if ($product_id)
             $product_data->product_record['id']['value'] = $product_id;
          else if ($product_data->import['load_existing'] ==
                   LOAD_OTHER_IMPORT_PRODUCTS)
             $product_data->product_record['import_id']['value'] =
                -$product_data->import['import_source'];
          else $product_data->product_record['import_id']['value'] =
                  $product_data->import_id;
       }
       if (isset($tables['inventory']))
          $product_data->inventory_record = $inventory_record;
       if (isset($tables['related']))
          $product_data->related_product_record = $related_product_record;
       $product_data->product_info = $product_info;
       $product_data->inventory_info = $inventory_info;
       $product_data->product_id = $product_id;
       if ($using_product_groups) {
          $product_group = $row[$product_group_index];
          if ($product_group && (! isset($product_groups[$product_group])))
             $product_data->parent_product = true;
          else $product_data->parent_product = false;
          $product_data->product_group = $product_group;
       }

       if (isset($tables['product'])) {
          $name_mapped = false;   $skip_required = false;
          foreach ($product_map as $index => $map_info) {
             $field_name = $map_info['update_field'];
             if (empty_required($map_info,$row[$index])) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Product Field '.$field_name .
                              ' - Skipping');
                $skip_required = true;   break;
             }
             if (! isset($product_data->product_record[$field_name]))
                continue;
             if (($map_info['convert_funct'] == 'skipempty') &&
                 ($row[$index] === '')) continue;
             if ($field_name == 'name') $name_mapped = true;
             if (($map_info['sequence'] > 1) &&
                 ($map_info['convert_funct'] == 'combinefields') &&
                 isset($product_data->product_record[$field_name]['value']))
                $product_data->product_record[$field_name]['value'] .=
                   format_data($row[$index],$field_name,$product_fields);
             else $product_data->product_record[$field_name]['value'] =
                     format_data($row[$index],$field_name,$product_fields);
          }
          if ($skip_required) {   $db->close();   continue;   }
          if ($using_product_groups && $import['group_template'])
             $product_data->product_record['template']['value'] =
                $import['group_template'];
          if (! $product_id) {
             if (empty($import['shopping_flags'])) $shopping_flags = 0;
             else $shopping_flags = $import['shopping_flags'];
             $product_data->product_record['shopping_flags']['value'] =
                $shopping_flags;
          }
       }
       if (isset($tables['inventory'])) {
          if ($inventory_info) {
             foreach ($inventory_info as $field_name => $field_value) {
                if (! isset($product_data->inventory_record[$field_name]))
                   continue;
                $product_data->inventory_record[$field_name]['value'] =
                   $field_value;
             }
          }
          else {
             foreach ($inventory_record as $field_name => $field_info) {
                if (! isset($product_data->inventory_record[$field_name]))
                   continue;
                if (($field_name == 'sequence') ||
                    ($field_name == 'last_modified')) continue;
                unset($product_data->inventory_record[$field_name]['value']);
             }
             if ($import['new_inv_qty'])
                $product_data->inventory_record['qty']['value'] =
                   $import['new_inv_qty'];
          }
          $skip_required = false;
          foreach ($inventory_map as $index => $map_info) {
             $field_name = $map_info['update_field'];
             if (empty_required($map_info,$row[$index])) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Inventory Field ' .
                              $field_name.' - Skipping');
                $skip_required = true;   break;
             }
             if (! isset($product_data->inventory_record[$field_name]))
                continue;
             if (($map_info['convert_funct'] == 'skipempty') &&
                 ($row[$index] === '')) continue;
             if ($import['partnum_prefix'] && ($field_name == 'part_number'))
                $row[$index] = $import['partnum_prefix'].$row[$index];
             $product_data->inventory_record[$field_name]['value'] =
                format_data($row[$index],$field_name,$inventory_fields);
          }
          if ($skip_required) {   $db->close();   continue;   }
       }

       if (isset($tables['images'])) {
          if (DEBUG_LOGGING) log_import('   Processing Images');
          $product_data->images = array();
          $skip_required = false;
          foreach ($image_map as $map_info) {
             $index = $map_info['index'];
             $image = trim($row[$index]);
             if (empty_required($map_info,$image)) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Image Field ' .
                              $map_info['update_field'].' - Skipping');
                $skip_required = true;   break;
             }
             if (! $image) continue;

             if ($map_info['sep_char'])
                $images = explode($map_info['sep_char'],$image);
             else $images = array($image);
             foreach ($images as $image) {
                if (DEBUG_LOGGING) log_import('      Processing Image '.$image);
                $image_modified = false;
                if ($map_info['convert_funct'] == 'downloadboximage') {
                   $url_parts = explode('/',$image);
                   if (count($url_parts) < 8) $map_info['convert_funct'] = null;
                   else {
                      $hostname = $url_parts[2];
                      if (strpos($hostname,'box.com') === false)
                         $map_info['convert_funct'] = null;
                   }
                }
                if ($map_info['convert_funct'] == 'downloadboximage') {
                   $shared_name = $url_parts[4];
                   $file_id = $url_parts[7];
                   $image = 'https://'.$hostname.'/index.php?rm=box_download_' .
                            'shared_file&shared_name='.$shared_name.'&file_id=f_' .
                            $file_id;
                   $image_filename = get_attachment_filename($image);
                   if (! $image_filename) continue;
                   $image_filename = cleanup_image_filename($image_filename,
                                                            $image);
                   $extension = pathinfo($image_filename,PATHINFO_EXTENSION);
                   if (! in_array($extension,array('jpg','gif','png'))) {
                      if (DEBUG_LOGGING)
                         log_import('      Invalid Image Extension, Skipping');
                      continue;
                   }
                }
                else if (function_exists('process_custom_vendor_image_conversion') &&
                         process_custom_vendor_image_conversion($map_info,
                            $product_data,$image_filename,$image_modified)) {}
                else if (module_event_exists($import,'process_image_conversion') &&
                   call_vendor_event($import,'process_image_conversion',
                      array($map_info,&$product_data,&$image_filename,
                            &$image_modified))) {}
                else {
                   $slash_pos = strrpos($image,'/');
                   if ($slash_pos !== false)
                      $image_filename = substr($image,$slash_pos + 1);
                   else $image_filename = $image;
                   $question_pos = strpos($image_filename,'?');
                   if ($question_pos !== false)
                      $image_filename = substr($image_filename,0,$question_pos);
                   $image_filename = cleanup_image_filename($image_filename,
                                                            $image);
                }
                if (function_exists('custom_convert_vendor_image_filename'))
                   custom_convert_vendor_image_filename($image_filename,
                                                        $product_data);
                call_vendor_event($import,'convert_image_filename',
                                  array($image_filename,$product_data));
                if ($image_filename)
                   $local_filename = '../images/original/'.$image_filename;
                else $local_filename = null;
                if (DEBUG_LOGGING && $local_filename)
                   log_import('      Local Filename = '.$local_filename);
                if ($local_filename && (! DEBUG_MAPPING)) {
                   if (($map_info['convert_funct'] == 'downloadimage') ||
                       ($map_info['convert_funct'] == 'downloadboximage')) {
                      if (substr($image,0,4) != 'http') {
                         if ($import['image_dir'] &&
                             (substr($import['image_dir'],0,4) == 'http')) {
                            $image_dir = $import['image_dir'];
                            if (substr($image_dir,-1) != '/') $image_dir .= '/';
                            $image = $image_dir.$image;
                         }
                         else $image = 'http://'.$image;
                      }
                      if (isset($downloaded_images[$image])) $last_modified = -1;
                      else if ($map_info['convert_funct'] == 'downloadboximage') {
                         if (file_exists($local_filename))
                            $last_modified = filemtime($local_filename);
                         else $last_modified = time();
                      }
                      else if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
                               file_exists($local_filename))
                         $last_modified = filemtime($local_filename);
                      else {
                         if (DEBUG_LOGGING)
                            log_import('      Getting Last Modified for '.$image);
                         $last_modified = get_last_modified($image);
                         if (DEBUG_LOGGING)
                            log_import('      Last Modified = '.$last_modified);
                         if ($last_modified == -1) {
                            $downloaded_images[$image] = true;
                            if ($product_id)
                               $failed_image_download_products[$product_id] = true;
                            else $failed_image_download_products[0] = true;
                         }
                      }
                      if ($last_modified == -1) {
                         $image_modified = false;
                         if (DEBUG_LOGGING)
                            log_import('      Image Not Found, Skipping');
                      }
                      else if ($image_filename == 'image-coming-soon.jpg') {
                         $image_modified = false;
                         if (DEBUG_LOGGING)
                            log_import('      Image is Coming Soon, Skipping');
                      }
                      else if ((! file_exists($local_filename)) ||
                               ($last_modified != filemtime($local_filename))) {
                         if (DEBUG_LOGGING) log_import('      Downloading '.$image);
                         $image_data = @file_get_contents($image);
                         if ($image_data)
                            file_put_contents($local_filename,$image_data);
                         if ($image_data &&
                             (substr($image_filename,-5) == '.webp')) {
                            $original_filename = $local_filename;
                            $local_filename = substr($local_filename,0,-4).'png';
                            $cmd = '/usr/local/bin/dwebp '.$original_filename .
                                   ' -o '.$local_filename;
                            $output = array();
                            $result = exec($cmd,$output,$return_var);
                            if ($return_var != 0) {
                               $error = 'Unable to convert WebP Image ' .
                                        $original_filename.': ('.$return_var.') ' .
                                        print_r($output,true);
                               log_error($error);
                               if (DEBUG_LOGGING)
                                  log_import('   '.$error.' - Skipping');
                               $skip_required = true;   break;
                            }
                            unlink($original_filename);
                            $image_filename = substr($image_filename,0,-4).'png';
                         }
                         if ($image_data) {
                            if (! $last_modified) $last_modified = time();
                            touch($local_filename,$last_modified);
                            $image_modified = true;
                            $downloaded_images[$image] = true;
                            log_vendor_activity('Downloaded Product Image '.$image);
                         }
                      }
                      else if (DEBUG_LOGGING)
                         log_import('      Image is unchanged, Skipping');
                   }
                }
                if ($local_filename &&
                    (file_exists($local_filename) || DEBUG_MAPPING)) {
                   $image_record['sequence']['value'] = $map_info['sequence'];
                   $image_record['filename']['value'] = $image_filename;
                   $product_data->images[] = $image_record;
                   if ((! DEBUG_MAPPING) && $image_modified) {
                      if (DEBUG_LOGGING)
                         log_import('      Processing Image '.$image_filename);
                      if (process_image($image_filename,$local_filename,null,
                                        null,null,null,$config_values,false,
                                        null,null,$image_record))
                         log_vendor_activity('Updated Product Image ' .
                                             $image_filename);
                   }
                }
             }
          }
          if ($skip_required) {   $db->close();   continue;   }
       }

       if (isset($tables['category'])) {
          $product_data->category_products = array();
          $category = '';   $skip_required = false;
          foreach ($cat_map as $map_info) {
             $cat_value = trim($row[$map_info['index']]);
             if (empty_required($map_info,$cat_value)) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Category Field ' .
                              $map_info['update_field'].' - Skipping');
                $skip_required = true;   break;
             }
             if (! $cat_value) continue;
             if ($category) $category .= ' > ';
             $category .= $cat_value;
          }
          if ($skip_required) {   $db->close();   continue;   }
          $category = cleanup_data(trim($category),'category');
          if ($category) {
             if (! isset($categories[$category])) $categories[$category] = 0;
             else $categories[$category]++;
             if (isset($category_map[$category]) &&
                       $category_map[$category]['category_id']) {
                $category_product_record['parent']['value'] =
                   $category_map[$category]['category_id'];
                $category_product_record['sequence']['value'] =
                   $categories[$category];
                $product_data->category_products[] = $category_product_record;
             }
          }
       }

       if (isset($tables['product_data'])) {
          $product_data->product_data = array();
          if ($import['import_type'] == DATA_IMPORT_TYPE) {
             if (! isset($data_sequences[$product_id]))
                $data_sequences[$product_id] = 0;
             $data_sequence = $data_sequences[$product_id];
          }
          else $data_sequence = 0;
          $skip_required = false;
          foreach ($product_data_map as $map_info) {
             $data_type = intval($map_info['update_field']);
             $data_record['data_type']['value'] = $data_type;
             $data_value = trim($row[$map_info['index']]);
             if (empty_required($map_info,$data_value)) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Product Data Field ' .
                              $map_info['update_field'].' - Skipping');
                $skip_required = true;   break;
             }
             if (! $map_info['sep_char']) {
                $sep_chars = null;
                $data_values = array($data_value);
             }
             else {
                $sep_chars = explode(',',$map_info['sep_char']);
                if (! isset($sep_chars[1])) $sep_chars[1] = ':';
                $data_values = explode($sep_chars[0],$data_value);
             }
             foreach ($data_values as $data_value) {
                if (! $data_value) continue;
                if ($sep_chars && strpos($data_value,$sep_chars[1])) {
                   $data_array = explode($sep_chars[1],$data_value);
                   if ((! $data_array[0]) && (! $data_array[1])) continue;
                   $data_record['label']['value'] = $data_array[0];
                   $data_record['data_value']['value'] = $data_array[1];
                }
                else if ($map_info['convert_funct'] == 'usecolumnaslabel') {
                   $data_record['label']['value'] = $map_info['vendor_field'];
                   $data_record['data_value']['value'] = $data_value;
                }
                else {
                   $data_record['label']['value'] = $data_value;
                   unset($data_record['data_value']['value']);
                }
                $data_record['sequence']['value'] = (string) $data_sequence;
                $product_data->product_data[] = $data_record;
                $data_sequence++;
                if ($import['import_type'] == DATA_IMPORT_TYPE)
                   $data_sequences[$product_id]++;
             }
          }
          if ($skip_required) {   $db->close();   continue;   }
       }

       if ($import['attribute_set'] &&
           isset($attribute_sets[$import['attribute_set']])) {
          $attributes = $attribute_sets[$import['attribute_set']];
          $product_data->product_attributes = array();
          foreach ($attributes as $attribute) {
             $product_attribute_record['related_id']['value'] =
                $attribute['related_id'];
             $product_attribute_record['sequence']['value'] =
                $attribute['sequence'];
             $product_data->product_attributes[] = $product_attribute_record;
          }
       }

       if (isset($tables['related'])) {
          $skip_required = false;
          foreach ($related_map as $index => $map_info) {
             $field_name = $map_info['update_field'];
             if (empty_required($map_info,$row[$index])) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Related Field ' .
                              $field_name.' - Skipping');
                $skip_required = true;   break;
             }
             if (! isset($product_data->related_product_record[$field_name]))
                continue;
             if (($map_info['convert_funct'] == 'skipempty') &&
                 ($row[$index] === '')) continue;
             $field_value = $row[$index];
             if ($field_name == 'related_id') {
                $match_pn = $match_mpn = $match_upc = $match_id =
                   $match_inv_id = null;
                if ($import['addl_match_field'])
                   $addl_match = strtolower(trim($row[$addl_match_index]));
                else $addl_match = null;
                if ($match_existing == MATCH_BY_PART_NUMBER) {
                   $match_pn = strtolower($field_value);
                   if ($import['partnum_prefix'])
                      $match_pn = strtolower($import['partnum_prefix']) .
                                  $match_pn;
                   if (! isset($product_data->product_ids[$match_pn])) {
                      $skip_required = true;   break;
                   }
                   $match_id = $product_data->product_ids[$match_pn];
                }
                else if ($match_existing == MATCH_BY_MPN) {
                   $match_mpn = strtolower($field_value);
                   if (! isset($product_data->product_ids[$match_mpn])) {
                      $skip_required = true;   break;
                   }
                   $match_id = $product_data->product_ids[$match_mpn];
                }
                else if ($match_existing == MATCH_BY_UPC) {
                   $match_upc = strtolower($field_value);
                   if (! isset($product_data->product_ids[$match_upc])) {
                      $skip_required = true;   break;
                   }
                   $match_id = $product_data->product_ids[$upc];
                }
                call_shopping_event('find_matching_vendor_product',
                   array(&$match_pn,$match_mpn,$match_upc,$product_data,
                         $addl_match,&$match_id,&$match_inv_id,
                         &$rel_match_name,&$rel_match_value),false,true);
                call_vendor_event($product_data->import,
                   'find_matching_product',array(&$match_pn,$match_mpn,
                      $match_upc,$product_data,$addl_match,&$match_id,
                      &$match_inv_id,&$rel_match_name,&$rel_match_value));
                if (! $match_id) {   $skip_required = true;   break;   }
                $field_value = $match_id;
             }
             $product_data->related_product_record[$field_name]['value'] =
                $field_value;
          }
          if ($skip_required) {   $db->close();   continue;   }
       }

       if (isset($tables['rebate'])) {
          $skip_required = false;
          foreach ($rebate_map as $index => $map_info) {
             $field_name = $map_info['update_field'];
             if (empty_required($map_info,$row[$index])) {
                if (DEBUG_LOGGING)
                   log_import('   Missing Required Rebate Field ' .
                              $field_name.' - Skipping');
                $skip_required = true;   break;
             }
             if (! isset($rebate_record[$field_name])) continue;
             if (($map_info['convert_funct'] == 'skipempty') &&
                 ($row[$index] === '')) continue;
             $rebate_record[$field_name]['value'] = $row[$index];
          }
          if ($skip_required) {   $db->close();   continue;   }
          $product_data->rebate_record = $rebate_record;
       }

       if (! isset($tables['product'])) {}
       else if ($product_id) {
          unset($product_data->product_record['status']['value']);
          if ($status_index != -1) {
             $vendor_status = trim($row[$status_index]);
             $avail = $import['avail_value'];
             $notavail = $import['notavail_value'];
             if (isset($skip_vendor_import_statuses) && $product_info &&
                 in_array($product_info['status'],
                          $skip_vendor_import_statuses)) {
                $avail = '';   $notavail = '';
             }
             if (($avail !== '') &&
                 (($vendor_status == $avail) ||
                  (($avail == '*') && ($notavail !== '') &&
                   ($vendor_status != $notavail)))) {
                if (($import['avail_status'] != 0) ||
                    ($product_info['status'] != $free_shipping_option))
                   $product_data->product_record['status']['value'] =
                      $import['avail_status'];
             }
             else if (($notavail !== '') &&
                      (($vendor_status == $notavail) ||
                       (($notavail == '*') && ($avail !== '') &&
                        ($vendor_status != $avail))))
                $product_data->product_record['status']['value'] =
                   $import['notavail_status'];
          }
       }
       else $product_data->product_record['status']['value'] =
               $import['new_status'];

       $skip_product = false;
       foreach ($map as $index => $map_info) {
          $convert_function = $map_info['convert_funct'];
          if (! $convert_function) continue;
          $map_info['index'] = $index;
          if (function_exists('process_custom_vendor_conversion')) {
             $retval = process_custom_vendor_conversion($map_info,
                                                        $product_data);
             if ($retval === false) {
                $db->close();   return;
             }
             if ($retval === 'skip') {
                if (DEBUG_LOGGING)
                   log_import('   Custom Conversion returned skip for Field ' .
                              $map_info['update_field'].' - Skipping');
                $skip_product = true;   break;
             }
          }
          else if (module_event_exists($import,'process_conversion')) {
             $retval = call_vendor_event($import,'process_conversion',
                          array(&$map_info,&$product_data));
             if ($retval === false) {
                $db->close();   return;
             }
             if ($retval === 'skip') {
                if (DEBUG_LOGGING)
                   log_import('   Module Conversion returned skip for Field ' .
                              $map_info['update_field'].' - Skipping');
                $skip_product = true;   break;
             }
          }
          else $retval = true;
          if ($retval) {
             $retval = process_conversion($map_info,$product_data);
             if ($retval === false) {
                $db->close();   return;
             }
             if ($retval === 'skip') {
                if (DEBUG_LOGGING)
                   log_import('   Conversion returned skip for Field ' .
                              $map_info['update_field'].' - Skipping');
                $skip_product = true;   break;
             }
          }
       }
       if ($skip_product) {   $db->close();   continue;   }

       if (function_exists('custom_check_vendor_import')) {
          $retval = custom_check_vendor_import($product_data);
          if ($retval === false) {   $db->close();   return;   }
          if ($retval === null) {
             if (DEBUG_LOGGING)
                log_import('   Check Vendor Import returned null - Skipping');
             $db->close();   continue;
          }
       }
       if (module_event_exists($import,'check_import')) {
          $retval = call_vendor_event($import,'check_import',
                                      array(&$product_data));
          if ($retval === false) {   $db->close();   return;   }
          if ($retval === null) {
             if (DEBUG_LOGGING)
                log_import('   Module Check Import returned null - Skipping');
             $db->close();   continue;
          }
       }

       if (isset($tables['product']) && $name_mapped &&
           (! $product_data->product_record['name']['value'])) {
          if (DEBUG_LOGGING)
             log_import('   Missing Product Name - Skipping');
          $db->close();   continue;
       }

       if ($product_id) {
          if (function_exists('get_vendor_product_delete_info'))
             $delete_info = get_vendor_product_delete_info($product_data);
          else if (module_event_exists($import,'get_delete_info'))
             $delete_info = call_vendor_event($import,'get_delete_info',
                                              array($product_data));
          else {
             $delete_info = array('inventory'=>false,'images'=>true,
                                  'category_products'=>true,
                                  'product_data'=>false,'attributes'=>true);
             if (isset($related_types)) {
                foreach ($related_types as $related_type => $label)
                   $delete_info['related_'.$related_type] = true;
             }
             if (isset($tables['product_data']))
                $delete_info['product_data'] = $delete_product_data;
          }
          if (! isset($tables['inventory'])) $delete_info['inventory'] = false;
          if (! isset($tables['images'])) $delete_info['images'] = false;
          else if (empty($product_data->images))
             $delete_info['images'] = false;
          else if (($import['import_type'] == IMAGES_IMPORT_TYPE) &&
                   (! $first_product_row))
             $delete_info['images'] = false;
          if (! isset($tables['category']))
             $delete_info['category_products'] = false;
          if (isset($tables['related'])) {
             if (! $first_product_row) {
                foreach ($related_types as $related_type => $label)
                   $delete_info['related_'.$related_type] = false;
             }
          }
          else {
             if ((! isset($tables['related_0'])) &&
                 (! ($import['flags'] & ADD_GROUP_RELATED)))
                $delete_info['related_0'] = false;
             if (isset($related_types)) {
                foreach ($related_types as $related_type => $label) {
                   if ($related_type == 0) continue;
                   if (! isset($tables['related_'.$related_type]))
                      $delete_info['related_'.$related_type] = false;
                }
             }
          }
        
          if (! isset($tables['product_data']))
             $delete_info['product_data'] = false;
          else if (($import['import_type'] == DATA_IMPORT_TYPE) &&
                   (! $first_product_row))
             $delete_info['product_data'] = false;
          if ($using_product_groups && isset($subproduct_type))
             $delete_info['related_'.$subproduct_type] = true;
          if (! $import['attribute_set']) $delete_info['attributes'] = false;
          if (isset($tables['rebate']) && $first_product_row)
             $delete_info['rebates'] = true;
          if (DEBUG_LOGGING) {
             $log_str = '';
             foreach ($delete_info as $delete_table => $delete_flag) {
                if ($delete_flag) {
                   if ($log_str) $log_str .= ', ';
                   $log_str .= $delete_table;
                }
             }
             if ($log_str) log_import('   Deleting Product Records '.$log_str);
          }
          if (DEBUG_MAPPING)
             print 'Delete Info = '.print_r($delete_info,true)."\n";
          else if (! delete_product_records($db,$product_id,$delete_info)) {
             $db->close();   return;
          }
       }

       if (function_exists('custom_update_vendor_import')) {
          $retval = custom_update_vendor_import($product_data);
          if ($retval === false) {   $db->close();   return;   }
          if ($retval === null) {
             if (DEBUG_LOGGING)
                log_import('   Update Vendor Import returned null - Skipping');
             $db->close();   continue;
          }
       }
       if (module_event_exists($import,'update_import')) {
          $retval = call_vendor_event($import,'update_import',
                                      array(&$product_data));
          if ($retval === false) {   $db->close();   return;   }
          if ($retval === null) {
             if (DEBUG_LOGGING)
                log_import('   Module Update Import returned null - Skipping');
             $db->close();   continue;
          }
       }
       if (shopping_module_event_exists('update_import')) {
          $retval = call_shopping_event('update_import',array(&$product_data),
                                        false);
          if ($retval === false) {   $db->close();   return;   }
          if ($retval === null) {
             if (DEBUG_LOGGING)
                log_import('   Shopping Module Update Import returned null ' .
                           '- Skipping');
             $db->close();   continue;
          }
       }

       if (DEBUG_MAPPING) {
          unset($product_data->map);
          unset($product_data->markups);
          unset($product_data->custom_data);
          print 'Product Record = ' .
                print_r($product_data->product_record,true)."\n";
          print 'Product Images = '.print_r($product_data->images,true)."\n";
          $db->close();   break;
       }

       $updated_product = false;
       if (! isset($tables['product'])) $edit_type = -1;
       else if ($product_id) {
          if (DEBUG_LOGGING) log_import('   Updating Product');
          update_product_seo_url($product_data,$product_info);
          $product_record = $product_data->product_record;
          if ($product_info) {
             foreach ($product_info as $field_name => $field_value) {
                if ($field_name == 'id') continue;
                if (array_key_exists($field_name,$product_info) &&
                    array_key_exists($field_name,$product_record) &&
                    array_key_exists('value',$product_record[$field_name]) &&
                    ($product_info[$field_name] ===
                     $product_record[$field_name]['value']))
                   unset($product_record[$field_name]['value']);
             }
          }
          foreach ($product_record as $field_name => $field_info) {
             if (! array_key_exists('value',$field_info)) continue;
             if (($field_name != 'id') && ($field_name != 'last_modified')) {
                $updated_product = true;   break;
             }
          }
          if ($updated_product) {
             if (! $db->update('products',$product_record)) {
                $error = 'Database Error [17]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
             if (module_attached('update_product')) {
                $product_row = $db->convert_record_to_array($product_data->
                                                            product_record);
                if (! call_vendor_module_event($db,UPDATERECORD,'product',
                         $product_id,$product_row,null,$error)) {
                   process_import_error($error);   $db->close();   return;
                }
             }
             if (isset($product_data->product_record['name']['value']))
                $product_name = $product_data->product_record['name']['value'];
             else if (isset($product_info,$product_info['name']))
                $product_name = $product_info['name'];
             else $product_name = '';
             $edit_type = UPDATERECORD;
             if ($product_name)
                $activity = 'Updated Product '.$product_name.' (#'.$product_id.')';
             else $activity = 'Updated Product #'.$product_id;
             log_vendor_activity($activity);   log_activity($activity);
             if (DEBUG_LOGGING) log_import($activity);
             $activity = 'Product Updated by Vendor Import #'.$import_id;
             if (isset($product_data->product_record['status']['value']) &&
                 ($product_info['status'] !=
                  $product_data->product_record['status']['value']))
                $activity .= ', Status: '.$product_info['status'].'=>' .
                   $product_data->product_record['status']['value'];
             if (isset($product_data->inventory_record['qty']['value']) &&
                 ($inventory_info['qty'] !=
                  $product_data->inventory_record['qty']['value']))
                $activity .= ', Qty: '.$inventory_info['qty'].'=>' .
                   $product_data->inventory_record['qty']['value'];
             write_product_activity($activity,$product_id,$db);
          }
          else if (DEBUG_LOGGING) log_import('   Product has not changed');
       }
       else {
          $updated_product = true;
          if (DEBUG_LOGGING) log_import('   Adding Product');
          update_product_seo_url($product_data,$product_info);
          unset($product_data->product_record['id']['value']);
          if (! $db->insert('products',$product_data->product_record)) {
             $error = 'Database Error [18]: '.$db->error;
             process_import_error($error);   $db->close();   return;
          }
          $product_id = $db->insert_id();
          $product_data->product_id = $product_id;
          if (module_attached('add_product')) {
             $product_row = $db->convert_record_to_array($product_data->
                                                         product_record);
             $product_row['id'] = $product_id;
             if (! call_vendor_module_event($db,ADDRECORD,'product',
                      $product_id,$product_row,null,$error)) {
                process_import_error($error);   $db->close();   return;
             }
          }
          $edit_type = ADDRECORD;
          if (empty($product_data->product_record['name']['value']))
             $product_name = '#'.$product_id;
          else $product_name = $product_data->product_record['name']['value'] .
                               ' (#'.$product_id.')';
          $activity = 'Added Product '.$product_name;
          log_vendor_activity($activity);   log_activity($activity);
          if (DEBUG_LOGGING) log_import($activity);
          $activity = 'Product Added by Vendor Import #'.$import_id .
             ', Status: '.$product_data->product_record['status']['value'];
          if (isset($product_data->inventory_record['qty']['value']))
             $activity .= ', Qty: ' .
                          $product_data->inventory_record['qty']['value'];
          write_product_activity($activity,$product_id,$db);
       }
       $product_data->product_ids[$match_value] = $product_id;
       if ($using_product_groups && $product_group) {
          if ($product_data->parent_product)
             $product_groups[$product_group] =
                array('parent'=>$product_id,'sequence'=>0,'products'=>array());
          else $product_data->category_products = array();
          $product_groups[$product_group]['products'][] = $product_id;
       }

       if (isset($tables['inventory'])) {
          if (DEBUG_LOGGING) {
             if (! empty($product_data->inventory_record['id']['value']))
                log_import('   Updating Product Inventory');
             else log_import('   Adding Product Inventory');
          }
          $product_data->inventory_record['parent']['value'] = $product_id;
          if (! empty($product_data->inventory_record['id']['value'])) {
             $inventory_record = $product_data->inventory_record;
             unset($inventory_record['parent']['value']);
             if ($inventory_info) {
                foreach ($inventory_info as $field_name => $field_value) {
                   if ($field_name == 'id') continue;
                   if (array_key_exists($field_name,$inventory_info) &&
                       array_key_exists($field_name,$inventory_record) &&
                       array_key_exists('value',$inventory_record[$field_name]) &&
                       ($inventory_info[$field_name] ===
                        $inventory_record[$field_name]['value']))
                      unset($inventory_record[$field_name]['value']);
                }
             }
             $updated_inventory = false;
             foreach ($inventory_record as $field_name => $field_info) {
                if (! array_key_exists('value',$field_info)) continue;
                if ($field_name != 'id') {
                   $updated_inventory = true;   break;
                }
             }
             if ($updated_inventory) {
                if (! $db->update('product_inventory',$inventory_record)) {
                   $error = 'Database Error [19a]: '.$db->error;
                   process_import_error($error);   $db->close();   return;
                }
                if (using_linked_inventory($db))
                   update_linked_inventory($db,
                      $product_data->inventory_record['id']['value'],
                      $product_data->inventory_record['qty']['value'],
                      $product_id,
                      $product_data->inventory_record['attributes']['value']);
                if (module_attached('update_inventory')) {
                   $inventory_row = $db->convert_record_to_array($product_data->
                                                                 inventory_record);
                   if (! call_vendor_module_event($db,UPDATERECORD,'inventory',
                            $product_id,null,$inventory_row,$error)) {
                      process_import_error($error);   $db->close();   return;
                   }
                }
                if ((! $updated_product) && $product_id) {
                   if (isset($product_data->product_record['name']['value']))
                      $product_name = $product_data->product_record['name']['value'];
                   else if (isset($product_info,$product_info['name']))
                      $product_name = $product_info['name'];
                   else $product_name = '';
                   $activity = 'Updated Product Inventory for '.$product_name .
                               ' (#'.$product_id.')';
                   log_vendor_activity($activity);   log_activity($activity);
                   if (DEBUG_LOGGING) log_import($activity);
                   $activity = 'Inventory Updated by Vendor Import #'.$import_id;
                   if (isset($product_data->inventory_record['qty']['value']) &&
                       ($inventory_info['qty'] !=
                        $product_data->inventory_record['qty']['value']))
                      $activity .= ', Qty: '.$inventory_info['qty'].'=>' .
                         $product_data->inventory_record['qty']['value'];
                   write_product_activity($activity,$product_id,$db);
                }
             }
             else if (DEBUG_LOGGING)
                log_import('   Product Inventory has not changed');
          }
          else {
             if (! $db->insert('product_inventory',
                               $product_data->inventory_record)) {
                $error = 'Database Error [19b]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
             if (module_attached('add_inventory')) {
                $inventory_id = $db->insert_id();
                $inventory_row = $db->convert_record_to_array($product_data->
                                                              inventory_record);
                $inventory_row['id'] = $inventory_id;
                if (! call_vendor_module_event($db,ADDRECORD,'inventory',
                         $product_id,null,$inventory_row,$error)) {
                   process_import_error($error);   $db->close();   return;
                }
             }
          }
       }

       if (isset($tables['images'])) {
          if (DEBUG_LOGGING && (count($product_data->images) > 0))
             log_import('   Adding Images');
          $sequence = 0;
          foreach ($product_data->images as $image_record) {
             $image_record['parent']['value'] = $product_id;
             if (isset($product_data->product_record['name']['value']))
                $image_record['caption']['value'] =
                   $product_data->product_record['name']['value'];
             else if (isset($product_info['name']))
                $image_record['caption']['value'] = $product_info['name'];
             else unset($image_record['caption']['value']);
             $image_record['sequence']['value'] = $sequence;
             if (! $db->insert('images',$image_record)) {
                $error = 'Database Error [20]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
             $image_filename = $image_record['filename']['value'];
             if (DEBUG_LOGGING)
                log_import('      Added Image ' .$image_filename);
             write_product_activity('Image '.$image_filename .
                ' Added by Vendor Import #'.$import_id,$product_id,$db);
             $sequence++;
          }
       }

       if (($import['noimage_status'] !== '') &&
           ($import['noimage_status'] !== null) &&
           (empty($failed_image_download_products[$product_id])) &&
           (empty($failed_image_download_products[0]))) {
          $query = 'select count(id) as num_images from images where ' .
                   '(parent_type=1) and (parent=?)';
          $query = $db->prepare_query($query,$product_id);
          $image_info = $db->get_record($query);
          if ((! $image_info) && isset($db->error)) {
             $error = 'Database Error [21]: '.$db->error;
             process_import_error($error);   $db->close();   return;
          }
          if ($image_info) $num_images = $image_info['num_images'];
          else $num_images = 0;
          if (($num_images == 0) &&
              ($product_info['status'] != $import['noimage_status']))  {
             if (DEBUG_LOGGING)
                log_import('   Setting Product to Off Sale for No Images');
             $query = 'update products set status=? where id=?';
             $query = $db->prepare_query($query,$import['noimage_status'],
                                         $product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                $error = 'Database Error [22]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
             if (! call_vendor_module_event($db,UPDATERECORD,'product',
                      $product_id,null,null,$error)) {
                process_import_error($error);   $db->close();   return;
             }
             if (isset($tables['product']))
                $product_data->product_record['status']['value'] =
                   $import['noimage_status'];
             write_product_activity('Set Status from '.$product_info['status'] .
                ' to '.$import['noimage_status'] .
                ' for No Images by Vendor Import #'.$import_id,$product_id,
                $db);
          }
       }

       if (isset($tables['category'])) {
          if (DEBUG_LOGGING) log_import('   Adding Category Products');
          foreach ($product_data->category_products as
                   $category_product_record) {
             $category_product_record['related_id']['value'] = $product_id;
             if (! $db->insert('category_products',$category_product_record)) {
                $error = 'Database Error [23]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
          }
       }

       if (isset($tables['product_data'])) {
          if (DEBUG_LOGGING) log_import('   Adding Product Data');
          foreach ($product_data->product_data as $data_record) {
             $data_record['parent']['value'] = $product_id;
             if (! $db->insert('product_data',$data_record)) {
                $error = 'Database Error [24]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
          }
       }

       if (! empty($product_data->product_attributes)) {
          if (DEBUG_LOGGING) log_import('   Adding Product Attributes');
          foreach ($product_data->product_attributes as $attribute_record) {
             $attribute_record['parent']['value'] = $product_id;
             if (! $db->insert('product_attributes',$attribute_record)) {
                $error = 'Database Error [24a]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
          }
       }

       if (isset($tables['product']) && $product_info) {
          if (isset($product_data->product_record['status']['value']) &&
              ($product_info['status'] !=
               $product_data->product_record['status']['value']))
             $update_shopping = true;
          else if (isset($product_data->product_record['price']['value']) &&
              ($product_info['price'] !=
               $product_data->product_record['price']['value']))
             $update_shopping = true;
          else $update_shopping = false;
          if ($update_shopping) {
             if (DEBUG_LOGGING) log_import('   Updating Shopping Products');
             if (isset($product_data->product_record['status']['value']))
                $new_status = $product_data->product_record['status']['value'];
             else $new_status = $product_info['status'];
             if (! call_shopping_event('update_product_status',
                      array($db,$product_id,$product_info['status'],
                            $new_status,&$error_msg),false)) {
                $error = 'Shopping Error: '.$error_msg;
                process_import_error($error);   $db->close();   return;
             }
          }
       }

       if (isset($tables['related'])) {
          if (DEBUG_LOGGING) log_import('   Adding Related Product');
          $product_data->related_product_record['parent']['value'] =
             $product_id;
          if (! $db->insert('related_products',$product_data->related_product_record)) {
             $error = 'Database Error [24b]: '.$db->error;
             process_import_error($error);   $db->close();   return;
          }
       }

       if (isset($tables['rebate'])) {
          if (DEBUG_LOGGING) log_import('   Adding Product Rebate');
          $product_data->rebate_record['parent']['value'] = $product_id;
          if (! $db->insert('product_rebates',$product_data->rebate_record)) {
             $error = 'Database Error [24c]: '.$db->error;
             process_import_error($error);   $db->close();   return;
          }
       }

       if (function_exists('custom_finish_vendor_row_import')) {
          $retval = custom_finish_vendor_row_import($product_data,$db);
          if ($retval === false) {   $db->close();   return;   }
       }
       if (module_event_exists($import,'finish_row_import')) {
          $retval = call_vendor_event($import,'finish_row_import',
                                      array($product_data,$db));
          if ($retval === false) {   $db->close();   return;   }
       }
       if (shopping_module_event_exists('finish_vendor_row_import')) {
          $retval = call_shopping_event('finish_vendor_row_import',
                       array(&$product_data,$edit_type),false);
          if ($retval === false) {   $db->close();   return;   }
       }

       $db->close();
       if (DEBUG_LOGGING) log_import('   Finished');
    }

/* END OF MAIN LOOP */

    $db = new DB;
    $product_data->db = $db;
    if (isset($tables['category']) && (! DEBUG_MAPPING)) {
       if (DEBUG_LOGGING) log_import('Starting Category Mapping Updates');
       foreach ($categories as $category => $sequence) {
          if (! $category) continue;
          $mapping_record['vendor_category']['value'] = $category;
          $mapping_record['num_products']['value'] = $sequence + 1;
          if (array_key_exists($category,$category_map)) {
             if (! $category_map[$category]['category_id'])
                $mapping_record['num_products']['value'] =
                   -($mapping_record['num_products']['value']);
             if (! $db->update('category_mapping',$mapping_record)) {
                $error = 'Database Error [25]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
          }
          else {
             $mapping_record['num_products']['value'] =
                -($mapping_record['num_products']['value']);
             if (! $db->insert('category_mapping',$mapping_record)) {
                $error = 'Database Error [26]: '.$db->error;
                process_import_error($error);   $db->close();   return;
             }
          }
       }
       if (DEBUG_LOGGING) log_import('Finished Category Mapping Updates');
    }

    if ($using_product_groups) {
       if (! ($import['flags'] & RESET_VENDOR_SUB_PRODUCTS)) {
          if (DEBUG_LOGGING) log_import('   Adding SubProduct Records');
          foreach ($product_groups as $group_info) {
             if (count($group_info['products']) == 1) {
                $product_id = $group_info['products'][0];
                $query = 'update products set template=null where id=?';
                $query = $db->prepare_query($query,$product_id);
                $db->log_query($query);
                if (! $db->query($query)) {
                   $error = 'Database Error [27]: '.$db->error;
                   process_import_error($error);   $db->close();   return;
                }
             }
             else {
                $parent_products = $group_info['products'];
                $related_products = $group_info['products'];
                foreach ($parent_products as $parent) {
                   $sequence = 0;
                   foreach ($related_products as $related_id) {
                      if ($related_id == $parent) continue;
                      $subproduct_record['parent']['value'] = $parent;
                      $subproduct_record['related_id']['value'] = $related_id;
                      $subproduct_record['sequence']['value'] = $sequence;
                      if (! $db->insert('related_products',
                                        $subproduct_record)) {
                         $error = 'Database Error [28]: '.$db->error;
                         process_import_error($error);   $db->close();
                         return;
                      }
                      $sequence++;
                   }
                }
             }
          }
       }
       if ($import['flags'] & ADD_GROUP_RELATED) {
          if (DEBUG_LOGGING) log_import('   Adding Child Related Records');
          foreach ($product_groups as $group_info) {
             if (count($group_info['products']) == 1) continue;
             $parent_products = $group_info['products'];
             $related_products = $group_info['products'];
             foreach ($parent_products as $parent) {
                $sequence = 0;
                foreach ($related_products as $related_id) {
                   if ($related_id == $parent) continue;
                   $related_product_record[0]['parent']['value'] = $parent;
                   $related_product_record[0]['related_id']['value'] =
                      $related_id;
                   $related_product_record[0]['sequence']['value'] = $sequence;
                   if (! $db->insert('related_products',
                                     $related_product_record[0])) {
                      $error = 'Database Error [29]: '.$db->error;
                      process_import_error($error);   $db->close();   return;
                   }
                   $sequence++;
                }
             }
          }
       }
    }

    if (isset($related_types)) {
       foreach ($related_types as $related_type => $label) {
          if (isset($tables['related_'.$related_type]) && (! DEBUG_MAPPING)) {
             if (DEBUG_LOGGING) log_import('Starting '.$label.' Updates');
             $related_map = $related_maps[$related_type];
             foreach ($data as $row_index => $row) {
                if ($row_index < $header_row) continue;
                $product_data->row = $row;
                foreach ($related_map as $index => $map_info) {
                   $related = $row[$index];
                   if (! $related) continue;
                   $part_number = $mpn = $upc = $product_id = $inv_id = null;
                   if ($import['addl_match_field'])
                      $addl_match = strtolower(trim($row[$addl_match_index]));
                   else $addl_match = null;
                   if ($match_existing == MATCH_BY_PART_NUMBER) {
                      $part_number = strtolower($row[$part_number_index]);
                      if ($import['partnum_prefix'])
                         $part_number = strtolower($import['partnum_prefix']) .
                                        $part_number;
                      if (! isset($product_data->product_ids[$part_number]))
                         continue;
                      $product_id = $product_data->product_ids[$part_number];
                   }
                   else if ($match_existing == MATCH_BY_MPN) {
                      $mpn = strtolower($row[$mpn_index]);
                      if (! isset($product_data->product_ids[$mpn])) continue;
                      $product_id = $product_data->product_ids[$mpn];
                   }
                   else if ($match_existing == MATCH_BY_UPC) {
                      $upc = strtolower($row[$upc_index]);
                      if (! isset($product_data->product_ids[$upc])) continue;
                      $product_id = $product_data->product_ids[$upc];
                   }
                   call_shopping_event('find_matching_vendor_product',
                      array(&$part_number,$mpn,$upc,$product_data,$addl_match,
                            &$product_id,&$inv_id,&$match_name,&$match_value),
                      false,true);
                   call_vendor_event($product_data->import,
                      'find_matching_product',array(&$part_number,$mpn,$upc,
                         $product_data,$addl_match,&$product_id,&$inv_id,
                         &$match_name,&$match_value));
                   if (! $product_id) continue;
                   $sequence = 0;
                   if ($map_info['sep_char'])
                      $related_array = explode($map_info['sep_char'],$related);
                   else $related_array = array($related);
                   foreach ($related_array as $related_id) {
                      $related_id = trim(strtolower($related_id));
                      if (! isset($product_data->product_ids[$related_id])) continue;
                      $related_product_record[$related_type]['parent']
                                             ['value'] = $product_id;
                      $related_product_record[$related_type]['related_id']
                         ['value'] = $product_data->product_ids[$related_id];
                      $related_product_record[$related_type]['sequence']
                                             ['value'] = $sequence;
                      if (! $db->insert('related_products',
                               $related_product_record[$related_type])) {
                         $error = 'Database Error [30]: '.$db->error;
                         process_import_error($error);   $db->close();
                         return;
                      }
                      $sequence++;
                   }
                }
             }
             if (DEBUG_LOGGING) log_import('Finished '.$label.' Updates');
          }
       }
    }

    if (! DEBUG_MAPPING) {
       $action = $import['non_match_action'];
       if ($action != NON_MATCH_SKIP) {
          if (! remove_deleted_products($db,$import_id,$products,$action)) {
             $db->close();   return;
          }
          else if (DEBUG_LOGGING) log_import('Removed Deleted Products');
       }
    }

    if ($using_product_groups &&
        ($import['flags'] & RESET_VENDOR_SUB_PRODUCTS)) {
       if (! reset_vendor_sub_products($db,$vendor_id)) {
          $db->close();   return;
       }
    }

    if (function_exists('custom_finish_vendor_import')) {
       $retval = custom_finish_vendor_import($product_data);
       if ($retval === false) {
          $db->close();   return;
       }
    }
    if (module_event_exists($import,'finish_import')) {
       $retval = call_vendor_event($import,'finish_import',
                                   array($product_data));
       if ($retval === false) {
          $db->close();   return;
       }
    }

    $import_finished = time();
    $query = 'update vendor_imports set import_finished=? where id=?';
    $query = $db->prepare_query($query,$import_finished,$import_id);
    $db->log_query($query);
    if (DEBUG_LOGGING) log_import($query);
    if (! $db->query($query)) {
       process_import_error('Database Error [31]: '.$db->error);
       $db->close();   return;
    }
    switch ($import['import_type']) {
       case PRODUCTS_IMPORT_TYPE:
          $vendor_field = 'last_data_import';   break;
       case INVENTORY_IMPORT_TYPE:
          $vendor_field = 'last_inv_import';   break;
       case PRICES_IMPORT_TYPE:
          $vendor_field = 'last_price_import';   break;
       case IMAGES_IMPORT_TYPE:
          $vendor_field = 'last_images_import';   break;
       case DATA_IMPORT_TYPE:
          $vendor_field = 'last_pdata_import';   break;
    }
    $query = 'update vendors set '.$vendor_field.'=? where id=?';
    $query = $db->prepare_query($query,$import_finished,$vendor_id);
    $db->log_query($query);
    if (DEBUG_LOGGING) log_import($query);
    if (! $db->query($query)) {
       process_import_error('Database Error [32]: '.$db->error);
       $db->close();   return;
    }
    $db->close();

    $log_msg = 'Finished Vendor Import #'.$import_id.' ('.$import['name'] .
               ') for Vendor '.$import['vendor_name'];
    log_vendor_activity($log_msg);   log_activity($log_msg);
    if (DEBUG_LOGGING) log_import($log_msg);
}

function import_vendor_data($argv)
{
    global $import_hour,$import_weekday,$import_day;

    if (! isset($import_hour)) $import_hour = 0;
    $current_hour = date('G');
    if (! isset($import_weekday)) $import_weekday = 0;
    $current_weekday = date('w');
    if (! isset($import_day)) $import_day = 1;
    $current_day = date('j');

    ini_set('memory_limit',-1);
    set_time_limit(0);
    ini_set('max_execution_time',0);
    ignore_user_abort(true);
    if ($current_hour == $import_hour) {
       $log_msg = 'Starting Daily Auto Update Vendor Imports';
       log_vendor_activity($log_msg);   log_activity($log_msg);
    }
    $db = new DB;
    $query = 'select v.name as vendor_name,v.module,v.num_markups as ' .
             'vendor_markups,vi.* from vendors v join vendor_imports vi ' .
             'on vi.parent=v.id';
    if (isset($argv[2])) {
       $import_id = $argv[2];   $query .= ' where vi.id=?';
       $query = $db->prepare_query($query,$import_id);
    }
    else {
       $import_id = null;   $query .= ' order by v.name,vi.id';
    }
    $imports = $db->get_records($query);
    if (! $imports) {
       if (isset($db->error))
          process_import_error('Database Error [33]: '.$db->error);
       $db->close();   return;
    }
    $db->close();
    foreach ($imports as $import) {
       if (isset($argv[2])) $import['manual'] = true;
       else {
          switch ($import['auto_update']) {
             case AUTO_UPDATE_NONE: continue 2;
             case AUTO_UPDATE_HOURLY: break;
             case AUTO_UPDATE_DAILY:
                if ($current_hour != $import_hour) continue 2;
                break;
             case AUTO_UPDATE_WEEKLY:
                if ($current_hour != $import_hour) continue 2;
                if ($current_weekday != $import_weekday) continue 2;
                break;
             case AUTO_UPDATE_MONTHLY:
                if ($current_hour != $import_hour) continue 2;
                if ($current_day != $import_day) continue 2;
                break;
          }
          $import['manual'] = false;
       }
       process_vendor_import($import);
    }
    if ($current_hour == $import_hour) {
       set_remote_user('Vendor Import');
       $log_msg = 'Finished Daily Auto Update Vendor Imports';
       log_vendor_activity($log_msg);   log_activity($log_msg);
    }
}

function get_vendor_data($argv)
{
    if (empty($argv[2])) {
       process_import_error('No Import ID Specified');   return;
    }
    ini_set('memory_limit',-1);
    set_time_limit(0);
    ini_set('max_execution_time',0);
    ignore_user_abort(true);
    $import_id = $argv[2];
    set_remote_user('Vendor Import #'.$import_id);
    $log_msg = 'Started Download of Catalog for Vendor Import #'.$import_id;
    log_vendor_activity($log_msg);   log_activity($log_msg);
    $db = new DB;
    $query = 'select v.name as vendor_name,v.module,v.num_markups as ' .
             'vendor_markups,vi.* from vendors v join vendor_imports vi ' .
             'on vi.parent=v.id where vi.id=?';
    $query = $db->prepare_query($query,$import_id);
    $import = $db->get_record($query);
    if (! $import) {
       if (isset($db->error))
          process_import_error('Database Error: '.$db->error);
       else process_import_error('Import #'.$import_id.' not found');
       $db->close();   return;
    }
    $import['manual'] = true;
    if (($import['import_source'] == FTP_IMPORT_SOURCE) ||
        ($import['import_source'] == SFTP_IMPORT_SOURCE)) {
       $ftp = open_ftp($import);
       if (! $ftp) {
          $db->close();   return;
       }
       if (! download_catalog($ftp,$db,$import)) {
          close_ftp($ftp);   $db->close();   return;
       }
       close_ftp($ftp);
    }
    else if ($import['import_source'] == DOWNLOAD_IMPORT_SOURCE) {
       if (function_exists('download_custom_vendor_catalog')) {
          if (! download_custom_vendor_catalog($db,$import)) {
             $db->close();   return;
          }
       }
       if (! call_vendor_event($import,'download_catalog',
                               array($db,&$import))) {
          $db->close();   return;
       }
    }
    $log_msg = 'Downloaded Catalog for Vendor Import #'.$import_id;
    log_vendor_activity($log_msg);   log_activity($log_msg);
}

if (isset($argc) && ($argc > 1)) $cmd = $argv[1];
else {
   process_import_error('No Vendor Import Command Specified');   exit(1);
}
set_remote_user('Vendor Import');
if ($cmd == 'import') import_vendor_data($argv);
else if ($cmd == 'get') get_vendor_data($argv);
else {
   process_import_error('Invalid Vendor Import Command: '.$cmd);   exit(1);
}

DB::close_all();

?>
