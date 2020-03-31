<?php
/*
               Inroads Shopping Cart - S.P. Richards Vendor Module

                         Written 2019 by Randall Severy
                          Copyright 2019 Inroads, LLC
*/

global $vendor_fields;
$vendor_fields = array(array('name'=>'group_customer','type'=>TEXT_TYPE));

class SPRichards {

function __construct($db,$vendor_id)
{
    require_once '../engine/http.php';

    $this->url = 'http://www.sprdealerservices.com';
    $this->db = $db;
    $this->vendor_id = $vendor_id;
    $query = 'select * from vendors where id=?';
    $query = $this->db->prepare_query($query,$this->vendor_id);
    $this->vendor_info = $this->db->get_record($query);
    $this->fstate = null;
    $this->error = null;
}

function SPRichards($db,$vendor_id)
{
    self::__construct($db,$vendor_id);
}

function login()
{
    if (empty($this->vendor_info['username']) ||
        empty($this->vendor_info['password']) ||
        empty($this->vendor_info['group_customer'])) {
       $this->error = 'Missing Vendor Username, Password, or Group/Customer ID';
       log_error($this->error);   log_vendor_activity($this->error);
       return false;
    }

    $url = $this->url.'/actLogin.asp';
    $http = new HTTP($url);
    $http->set_timeout(120);
    $post_data = 'txtAccount='.urlencode($this->vendor_info['group_customer']) .
                 '&txtLogin='.urlencode($this->vendor_info['username']) .
                 '&pwdPassword='.urlencode($this->vendor_info['password']) .
                 '&cmdLogin=Login';
    $data = $http->call($post_data);
    if ($http->status != 200) {
       $error = 'Unable to login to '.$url.': '.$http->error.' (' .
                $http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $start_pos = strpos($data,'?FState=');
    if ($start_pos === false) {
       $error = 'Missing FState field in '.$url.' Response';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $start_pos += 8;
    $end_pos = strpos($data,'&',$start_pos);
    $this->fstate = substr($data,$start_pos,$end_pos - $start_pos);
    return true;
}

function download_catalog($unzip_filename,$import_filename)
{
    $url = $this->url.'/iPD/PDS19.asp?FState='.urlencode($this->fstate) .
           '&eCustName=&eShowHideCustomer=SHOW&eIROEConn=&eBeg=1&eCustNo=' .
           urlencode($this->vendor_info['group_customer']).'&ecmdGo=Go&eFmt=V';
    $http = new HTTP($url);
    $http->set_method('GET');
    $http->set_timeout(300);
    $data = $http->call();
    if (! $data) {
       $error = 'Unable to retrieve download page from ' .
                $url.': '.$http->error.' ('.$http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $start_pos = strpos($data,'PDSDownload.asp');
    if ($start_pos === false) {
       $error = 'Unable to find download link in ' .
                $url.': '.$http->error.' ('.$http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $end_pos = strpos($data,' ',$start_pos);
    $url = $this->url.'/iPD/'.substr($data,$start_pos,$end_pos - $start_pos);

    $http = new HTTP($url);
    $http->set_method('GET');
    $http->set_timeout(300);
    $data = $http->call();
    if (! $data) {
       $error = 'Unable to download catalog from ' .
                $url.': '.$http->error.' ('.$http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $zip_filename = substr($import_filename,0,-4).'.zip';
    file_put_contents($zip_filename,$data);

    $zip = new ZipArchive;
    $result = $zip->open($zip_filename);
    if ($result !== true) {
       $error = 'Unable to open zip file '.$zip_filename.' ('.$result.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if ($unzip_filename[0] == '*') {
       $match = substr($unzip_filename,1);   $match_len = strlen($match);
    }
    else $match = null;
    $unzip_file = null;
    for ($index = 0;  $index < $zip->numFiles;  $index++) {
       $filename = $zip->getNameIndex($index);
       if (($match && (substr($filename,-$match_len) == $match)) ||
           ($filename == $unzip_filename)) {
          $unzip_file = $zip->getFromName($filename);   break;
       }
    }
    if (! $unzip_file) {
       $error = 'Zip file '.$zip_filename.' does not contain the file ' .
                $unzip_filename;
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if (! file_put_contents($import_filename,$unzip_file)) {
       $error = 'Unable to save import file '.$import_filename;
       log_error($error);   log_vendor_activity($error);   return false;
    }
    unlink($zip_filename);
    log_vendor_activity('Downloaded S.P. Richards Catalog File '.$filename);
    return true;
}

};

function sprichards_install($db)
{
    global $vendor_product_fields,$vendor_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
}

function sprichards_upgrade($db)
{
    global $vendor_product_fields,$vendor_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
}

function sprichards_vendor_fields(&$vendor_record)
{
    $vendor_record['group_customer'] = array('type'=>CHAR_TYPE);
}

function sprichards_add_vendor_fields($dialog,$edit_type,$row,$db,$tab_name)
{
    if ($tab_name != 'settings') return;

    $dialog->add_edit_row('Group/Customer ID:','group_customer',$row,40);
}

function sprichards_update_db_fields($db,&$db_fields)
{
    if (defined('FEATURES_DATA_TYPE'))
       $db_fields['product_data|'.FEATURES_DATA_TYPE] = 'Features';
    if (defined('SPECIFICATIONS_DATA_TYPE'))
       $db_fields['product_data|'.SPECIFICATIONS_DATA_TYPE] = 'Specifications';
    if (defined('BULLETS_DATA_TYPE'))
       $db_fields['product_data|'.BULLETS_DATA_TYPE] = 'Bullets';
}

function sprichards_import_field_names($db,$import,&$field_names)
{
    $unzip_filename = $import['unzip_filename'];
    switch ($import['import_type']) {
       case DATA_IMPORT_TYPE:
          $ftp_filename = $import['ftp_filename'];
          if (strpos($ftp_filename,'featuresnbenefits.txt') !== false)
             $field_names = array('Product ID','Description','Is Default',
                                  'Type','Locale ID');
          else if (strpos($ftp_filename,'accessories') !== false)
             $field_names = array('Product ID','Accessory ID','Is Active',
                'Is Preferred','Is Option','Note','Weight');
          else if (strpos($ftp_filename,'productalsobought.txt') !== false)
             $field_names = array('Product ID','Accessory ID','Is Active',
                'Is Preferred','Is Option','Note','Weight');
          else if (strpos($ftp_filename,'similar') !== false)
             $field_names = array('Product ID','Similar ID','Locale ID');
          else if (strpos($ftp_filename,'upsell') !== false)
             $field_names = array('Product ID','Upsell ID','Locale ID');
          else if (strpos($ftp_filename,'productresources') !== false)
             $field_names = array('Product ID','Distributor','SKU','Type',
                'URL','Text','Locale','Status','Start Date','End Date');
          else if (strpos($ftp_filename,'featurebullet') !== false)
             $field_names = array('Product ID','Locale','Sequence','Bullet',
                                  'Modified Date','Is Active');
          else if (strpos($unzip_filename,'productattributes') !== false)
             $field_names = array('Product ID','Attribute ID','Category Id',
                'Display Value','Absolute Value','Unit ID','Is Absolute',
                'Is Active','Locale');
          else if (strpos($unzip_filename,'productdescriptions') !== false)
             $field_names = array('Product ID','Description','Is Default',
                                  'Type','Locale');
          return;
       case IMAGES_IMPORT_TYPE:
          $field_names = array('Product ID','Type','Status');
          return;
    }
    switch ($unzip_filename) {
       case '*-I.txt':
          $field_names = array('Record Type','Stock Number',
             'Stripped Stock Number','Description','status','New Item Number',
             'Unit of Measure','General Page Number','Special Page Number',
             'Furniture Page Number','Unused','Unused','Packing Qty 1',
             'Packing UOM 1','Per Unit 1','Packing Qty 2','Packing UOM 2',
             'Per Unit 2','Packing Qty 3','Packing UOM 3','Per Unit 3',
             'Item Weight','Item Height','Item Length','Item Width',
             'Classification','Country of Origin','Ready To Assemble',
             'Recycled','Ship via UPS','Broken Qty Allowed','List Price',
             'Unit of Measure','Retail Units','MSDS Required','Substitutions',
             'Old Item #','Catalog List Price','Catalog UOM','MCV Vendor',
             'Custom','Dated Goods','Qty for SUOM','Non-Returnable',
             'Always Net','Special Order','Future Use');
          return;
       case '*-X.txt':
          $field_names = array('Record Type','Stock Number',
             'Stripped Stock Number','UPC','United Stock #','MPC Number',
             'Moore Stock Number','UPC Packing Factor','Retail Pack UPC',
             'UPC Intermediate Packing Factor','Intermediate Pack UPC',
             'UPC Case Packing Factor','Case Pack UPC','Stocking Status',
             'Old Model','New Model','Future Use');
          return;
       case '*-P.txt':
          $field_names = array('Record Type','Stock Number',
             'Stripped Stock Number','Program Name','Program Code',
             'Future Use','Start Date','End Date','Catalog Page Number',
             'Minimum Selling Qty','Net Cost non-CCP','Net Cost CCP-3',
             'Net Cost CCP-4','Vendor Drop Ship','Shipping Lead Time',
             'Automatically Procure','Project Number','Future Use',
             'Promo 1 Qty','Promo 1 Cost','Promo 2 Qty','Promo 2 Cost',
             'Promo 3 Qty','Promo 3 Cost','Future Use','Price 1 Qty','Price 1',
             'Price 2 Qty','Price 2','Price 3 Qty','Price 3',
             'Lead Time Description','Catalog Price','Unit of Measure',
             'Price Code','Firm Cost','Net Cost');
          return;
    }
}

function sprichards_download_catalog($db,$import)
{
    $vendor_id = $import['parent'];
    $sprichards = new SPRichards($db,$vendor_id);
    if (! $sprichards->login()) return false;
    if (! $import['import_file']) {
       $import['import_file'] = 'import-'.$import['id'].'.txt';
       $new_import_file = true;
    }
    else $new_import_file = false;
    $import_filename = '../admin/vendors/'.$import['import_file'];
    $unzip_filename = $import['unzip_filename'];
    if (! $sprichards->download_catalog($unzip_filename,$import_filename))
       return false;
    if ($new_import_file) {
       $query = 'update vendor_imports set import_file=? where id=?';
       $query = $db->prepare_query($query,$import['import_file'],
                                   $import['id']);
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    return true;
}

function sprichards_update_catalog_filename(&$catalog_file,&$extension,
                                            $import_file,$files)
{
    if ($catalog_file != 'sprfull.ezoh') return true;
    $extension = 'csv';
    if (empty($import_file)) return;
    $local_filename = '../admin/vendors/'.$import_file;
    if (file_exists($local_filename) &&
        ($files[$catalog_file]['mtime'] == filemtime($local_filename)))
       return 'skip';
}

function sprichards_convert_catalog_file(&$import_file,$local_filename,
                                         &$import,$ftp)
{
    if ($import['ftp_filename'] != '/sprfull.ezoh') return;
    $mtime = filemtime($local_filename);
    $data = file($local_filename,FILE_IGNORE_NEW_LINES);
    if (! $data) {
       $error = 'Unable to load '.$local_filename;
       log_error($error);   log_vendor_activity($error);   return;
    }
    $num_lines = sizeof($data);
    $output_file = fopen($local_filename,'w');
    fwrite($output_file,'"Stock Number","Status","UoM","Qty On Hand"'."\n");
    for ($index = 0;  $index < $num_lines;  $index++) {
       $line = $data[$index];
       $record_type = substr($line,0,2);
       if ($record_type != 'Q1') continue;
       $stocknum = trim(substr($line,2,15));
       $status = substr($line,17,1);
       $uom = substr($line,18,2);
       $numdcs = (strlen($line) - 20) / 6;
       $qty = 0;
       for ($dc = 0;  $dc < $numdcs;  $dc++) {
          $dc_qty = substr($line,20 + ($dc * 6),6);
          $qty += intval(ltrim($dc_qty,'0'));
       }
       fwrite($output_file,'"'.$stocknum.'","'.$status.'","'.$uom.'",' .
              $qty."\n");
    }
    fclose($output_file);
    touch($local_filename,$mtime);
}

function sprichards_display_custom_match($dialog,$match_existing)
{
    require_once '../admin/office-common.php';
    display_vendor_number_match($dialog,$match_existing);
}

function sprichards_load_custom_data($db,&$product_data,&$custom_data)
{
    require_once '../admin/office-common.php';
    if (! load_vendor_numbers($product_data)) return;
    if ($product_data->import['import_type'] == IMAGES_IMPORT_TYPE)
       $product_data->vendor_images = array();
}

function sprichards_find_matching_product(&$part_number,$mpn,$upc,
   $product_data,$addl_match,&$product_id,&$inv_id,&$match_name,&$match_value)
{
    require_once '../admin/office-common.php';
    find_matching_vendor_number($part_number,$product_data,$addl_match,
       $product_id,$inv_id,$match_name,$match_value);
}

function sprichards_update_conversions($db,&$conversions)
{
    $conversions['sprmarkup'] = 'Set SPR Marked Up Price';
    $conversions['divide100'] = 'Divide by 100';
    $conversions['lookupattrlabel'] = 'Lookup Attribute Label';
    $conversions['importonlymsds'] = 'Import only MSDS Type';
    $conversions['importonlyrebate'] = 'Import only Rebate Type';
    $conversions['importonlylogo'] = 'Import only Brand Logo Type';
    $conversions['importonlyseqtype'] = 'Import only Type in Seq';
}

function load_sprichards_attributes(&$product_data)
{
    $product_data->spr_attributes = array();
    $ftp = open_ftp($product_data->import);
    $local_filename = '../admin/vendors/tax.zip';
    $zip_filename = '/Enhanced_Content/tax/EN_US/tax_EN_US_current_mysql.zip';
    $csv_filename = 'EN_US_attributenames.csv';
    $return = ftp_get($ftp,$local_filename,$zip_filename,FTP_BINARY);
    if (! $return) {
       $error = 'Unable to download Attributes Zip File '.$zip_filename;
       close_ftp($ftp);   process_import_error($error);   return;
    }
    close_ftp($ftp);
    $zip = new ZipArchive;
    $result = $zip->open($local_filename);
    if ($result !== true) {
       $error = 'Unable to open zip file '.$local_filename .
                ' ('.$result.')';
       unlink($local_filename);   process_import_error($error);   return;
    }
    $attributes = $zip->getFromName($csv_filename);
    if (! $attributes) {
       $error = 'Zip file '.$local_filename .
                ' does not contain the file '.$csv_filename;
       unlink($local_filename);   process_import_error($error);   return;
    }
    $lines = explode("\n",$attributes);
    foreach ($lines as $line) {
       $fields = str_getcsv($line);
       if (! isset($fields[0],$fields[1])) continue;
       $product_data->spr_attributes[$fields[0]] = $fields[1];
    }
}

function sprichards_process_conversion(&$map_info,&$product_data)
{
    $convert_function = $map_info['convert_funct'];
    switch ($convert_function) {
       case 'sprmarkup':
       case 'divide100':
          $field_value = get_update_field($map_info,$product_data);
          $field_value = floatval($field_value / 100);
          set_update_field($map_info,$product_data,$field_value);
          if ($convert_function == 'sprmarkup') {
             $map_info['convert_funct'] = 'setmarkupprice';
             return true;
          }
          return null;
       case 'lookupattrlabel':
          if (! isset($product_data->product_data[0])) return null;
          if (! isset($product_data->spr_attributes))
             load_sprichards_attributes($product_data);
          $field_value = $product_data->row[$map_info['index']];
          $product_data->product_data[0]['data_value']['value'] =
             $product_data->product_data[0]['label']['value'];
          if (! empty($product_data->spr_attributes[$field_value]))
             $product_data->product_data[0]['label']['value'] =
                $product_data->spr_attributes[$field_value];
          else $product_data->product_data[0]['label']['value'] = '';
          return null;
       case 'importonlymsds':
          if (! isset($product_data->product_data[0])) return null;
          $field_value = $product_data->row[$map_info['index']];
          if ($field_value != 'MSDS') return 'skip';
          $product_data->product_data[0]['data_value']['value'] =
             $product_data->product_data[0]['label']['value'];
          $product_data->product_data[0]['label']['value'] = 'Safety Datasheet';
          return null;
       case 'importonlyrebate':
          $field_value = $product_data->row[$map_info['index']];
          if (substr($field_value,0,6) != 'Rebate') return 'skip';
          return null;
       case 'importonlylogo':
          $field_value = $product_data->row[$map_info['index']];
          if ($field_value != 'SPR-Brand-Logo') return 'skip';
          return null;
       case 'importonlyseqtype':
          $field_value = $product_data->row[$map_info['index']];
          $sequence = $map_info['sequence'];
          if ($field_value != $sequence) return 'skip';
          return null;
    }
    return true;
}

function sprichards_process_image_conversion($map_info,&$product_data,
   &$image_filename,&$image_modified)
{
    if ($product_data->import['import_type'] != IMAGES_IMPORT_TYPE)
       return false;

    $image_type = trim($product_data->row[$map_info['index']]);
    $product_id = $product_data->product_id;
    if (! isset($product_data->vendor_images[$product_id]))
       $product_data->vendor_images[$product_id] = array();
    $product_data->vendor_images[$product_id][] = $image_type;
    $image_filename = null;
    return true;
}

function sprichards_finish_import($product_data)
{
    if ($product_data->import['import_type'] != IMAGES_IMPORT_TYPE)
       return true;

    if (DEBUG_LOGGING) log_import('Processing Images');

    $size = get_largest_image_size_info($product_data->config_values);
    $largest_size = $size[0];
    $image_sequence = array('Life-Style','Line-Art','Finish','Shell','Swatch',
       'In-Package','Out-of-package','FrontMaximum','LeftMaximum',
       'RightMaximum','Zoom-Closeup','TopMaximum','BottomMaximum',
       'RearMaximum','Hero-Shot','Jack-Pack');
    $db = $product_data->db;
    $import_id = $product_data->import_id;
    $image_options = $product_data->import['image_options'];
    $image_record = image_record_definition();
    $image_record['parent_type']['value'] = 1;

    foreach ($product_data->products as $product_id => $product) {
       if (empty($product_data->vendor_images[$product_id])) continue;
       $vendor_images = $product_data->vendor_images[$product_id];
       $vendor_number = $product['vendor_number'];
       if (DEBUG_LOGGING)
          log_import('Processing Images for Product ID '.$product_id .
                     ' (Vendor Number '.$vendor_number.')');
       $main_image = 0;   $main_image_size = 0;   $images = array();
       foreach ($vendor_images as $image_type) {
          if (is_numeric($image_type)) $image_size = $image_type;
          else if ($image_type == 'Large') $image_size = 300;
          else if ($image_type == 'Thumbnail') $image_size = 160;
          else $image_size = -1;
          if ($image_size != -1) {
             if ($main_image_size > $largest_size) {
                if (($image_size >= $largest_size) &&
                    ($image_size < $main_image_size)) {
                   $main_image = $image_type;   $main_image_size = $image_size;
                }
             }
             else if ($image_size > $main_image_size) {
                $main_image = $image_type;   $main_image_size = $image_size;
             }
             continue;
          }
          $images[$image_type] = true;
       }

       $product_images = array();
       if ($main_image_size > 0) $product_images[] = $main_image;
       else if (isset($images['Original'])) $product_images[] = 'Original';
       foreach ($image_sequence as $image_type) {
          if (isset($images[$image_type])) $product_images[] = $image_type;
       }

       $query = 'delete from images where parent_type=1 and parent=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = 'Database Error Deleting Product Images: '.$db->error;
          process_import_error($error);   return false;
       }

       $image_record['parent']['value'] = $product_id;
       if (isset($product_data->product_info['name']))
          $image_record['caption']['value'] =
             $product_data->product_info['name'];
       else unset($image_record['caption']['value']);
       $sequence = 0;

       foreach ($product_images as $index => $image) {
          if (DEBUG_LOGGING) log_import('   Processing Vendor Image '.$image);
          $image_url = 'http://content.etilize.com/'.$image.'/' .
                       $vendor_number.'.jpg';
          if ($index == 0) $image_filename = $vendor_number.'.jpg';
          else $image_filename = $vendor_number.'-'.$image.'.jpg';
          $local_filename = '../images/original/'.$image_filename;
          if (DEBUG_LOGGING)
             log_import('      Local Filename = '.$local_filename);
          if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
              file_exists($local_filename))
             $last_modified = filemtime($local_filename);
          else {
             if (DEBUG_LOGGING)
                log_import('      Getting Last Modified for '.$image_url);
             $last_modified = get_last_modified($image_url,5);
             if (DEBUG_LOGGING)
                log_import('      Last Modified = '.$last_modified);
             if ($last_modified == -1) {
                if (DEBUG_LOGGING)
                   log_import('      Image Not Found, Skipping');
                continue;
             }
          }
          if (file_exists($local_filename) &&
              ($last_modified == filemtime($local_filename))) {
             $new_image = false;
             if (DEBUG_LOGGING)
                log_import('      Image is unchanged, Skipping Download');
          }
          else {
             $new_image = true;
             if (DEBUG_LOGGING) log_import('      Downloading '.$image_url);
             $image_data = @file_get_contents($image_url);
             if (! $image_data) {
                if (DEBUG_LOGGING)
                   log_import('      Image Not Found, Skipping');
                continue;
             }
             file_put_contents($local_filename,$image_data);
             if (! $last_modified) $last_modified = time();
             touch($local_filename,$last_modified);
             log_vendor_activity('Downloaded Product Image '.$image_url);
             if (DEBUG_LOGGING)
                log_import('      Processing Image '.$image_filename);
             if (process_image($image_filename,$local_filename,null,null,
                               null,null,$product_data->config_values,
                               false,null,null,$image_record))
                log_vendor_activity('Updated Product Image ' .$image_filename);
          }
          $image_record['filename']['value'] = $image_filename;
          $image_record['sequence']['value'] = $sequence;
          if (! $db->insert('images',$image_record)) {
             $error = 'Database Error Adding Image Record: '.$db->error;
             process_import_error($error);   return false;
          }
          if ($new_image) {
             if (DEBUG_LOGGING)
                log_import('      Added Image ' .$image_filename);
             write_product_activity('Image '.$image_filename .
                ' Added by Vendor Import #'.$import_id,$product_id,$db);
          }
          $sequence++;
       }
    }
    if (DEBUG_LOGGING) log_import('Finished Processing Images');
    return true;
}

?>
