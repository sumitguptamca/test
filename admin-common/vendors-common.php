<?php
/*
                   Inroads Shopping Cart - Vendors Common Functions

                        Written 2015-2019 by Randall Severy
                          Copyright 2015-2019 Inroads, LLC
*/

define('DO_NOT_SEND',0);
define('SEND_ORDER_BY_EMAIL',1);
define('SEND_ORDER_BY_EDI',2);
define('SEND_ORDER_BY_UPLOAD',3);
define('SEND_ORDER_BY_API',4);

define('SEND_ORDER_MANUALLY',0);
define('SEND_ORDER_AUTO',1);

define('PRODUCTS_IMPORT_TYPE',1);
define('INVENTORY_IMPORT_TYPE',2);
define('PRICES_IMPORT_TYPE',3);
define('IMAGES_IMPORT_TYPE',4);
define('DATA_IMPORT_TYPE',5);

define('FTP_IMPORT_SOURCE',1);
define('UPLOAD_IMPORT_SOURCE',2);
define('EDI_IMPORT_SOURCE',3);
define('SFTP_IMPORT_SOURCE',4);
define('DOWNLOAD_IMPORT_SOURCE',5);
define('OTHER_IMPORT_SOURCE',6);
define('API_IMPORT_SOURCE',7);

define('AUTO_UPDATE_NONE',0);
define('AUTO_UPDATE_HOURLY',1);
define('AUTO_UPDATE_DAILY',2);
define('AUTO_UPDATE_WEEKLY',3);
define('AUTO_UPDATE_MONTHLY',4);

define('LOAD_ALL_VENDOR_PRODUCTS',0);
define('LOAD_IMPORT_PRODUCTS',1);
define('LOAD_OTHER_IMPORT_PRODUCTS',2);

define('MATCH_BY_PART_NUMBER',1);
define('MATCH_BY_MPN',2);
define('MATCH_BY_UPC',3);

define('DOWNLOAD_NEW_IMAGES_ONLY',1);
define('DO_NOT_DELETE_MISSING_IMAGES',2);

define('NON_MATCH_STATUS',-3);
define('NON_MATCH_SKIP',-2);
define('NON_MATCH_DELETE',-1);

define('ADD_GROUP_RELATED',1);
define('RESET_VENDOR_SUB_PRODUCTS',2);

function vendor_record_definition($vendor_info=null)
{
    $vendor_record = array();
    $vendor_record['id'] = array('type' => INT_TYPE);
    $vendor_record['id']['key'] = true;
    $vendor_record['module'] = array('type' => CHAR_TYPE);
    $vendor_record['module_date'] = array('type' => INT_TYPE);
    $vendor_record['name'] = array('type' => CHAR_TYPE);
    $vendor_record['contact'] = array('type' => CHAR_TYPE);
    $vendor_record['company'] = array('type' => CHAR_TYPE);
    $vendor_record['address1'] = array('type' => CHAR_TYPE);
    $vendor_record['address2'] = array('type' => CHAR_TYPE);
    $vendor_record['city'] = array('type' => CHAR_TYPE);
    $vendor_record['state'] = array('type' => CHAR_TYPE);
    $vendor_record['zipcode'] = array('type' => CHAR_TYPE);
    $vendor_record['country'] = array('type' => INT_TYPE);
    $vendor_record['phone'] = array('type' => CHAR_TYPE);
    $vendor_record['email'] = array('type' => CHAR_TYPE);
    $vendor_record['name_on_check'] = array('type' => CHAR_TYPE);
    $vendor_record['account_number'] = array('type' => CHAR_TYPE);
    $vendor_record['return_address'] = array('type' => CHAR_TYPE);
    $vendor_record['username'] = array('type' => CHAR_TYPE);
    $vendor_record['password'] = array('type' => CHAR_TYPE);
    $vendor_record['num_markups'] = array('type' => INT_TYPE);
    $vendor_record['default_shipping'] = array('type' => FLOAT_TYPE);
    $vendor_record['new_order_flag'] = array('type' => INT_TYPE);
    $vendor_record['send_order_flag'] = array('type' => INT_TYPE);
    $vendor_record['submit_email'] = array('type' => CHAR_TYPE);
    $vendor_record['sent_status'] = array('type' => INT_TYPE);
    $vendor_record['edi_interface'] = array('type' => INT_TYPE);
    $vendor_record['edi_sender_qual'] = array('type' => CHAR_TYPE);
    $vendor_record['edi_sender_id'] = array('type' => CHAR_TYPE);
    $vendor_record['edi_receiver_qual'] = array('type' => CHAR_TYPE);
    $vendor_record['edi_receiver_id'] = array('type' => CHAR_TYPE);
    $vendor_record['edi_ftp_directory'] = array('type' => CHAR_TYPE);
    $vendor_record['last_modified'] = array('type' => INT_TYPE);
    if (function_exists('custom_vendor_fields'))
       custom_vendor_fields($vendor_record);
    if ($vendor_info)
       call_vendor_event($vendor_info,'vendor_fields',array(&$vendor_record));
    return $vendor_record;
}

function log_vendor_activity($activity_msg)
{
    global $activity_log;

    $path_parts = pathinfo($activity_log);
    $vendor_log = $path_parts['dirname'].'/vendors.log';
    $log_file = @fopen($vendor_log,'at');
    if ($log_file) {
       $remote_user = getenv('REMOTE_USER');
       if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
          $remote_user = $_SERVER['REMOTE_ADDR'];
       fwrite($log_file,$remote_user." [".date("D M d Y H:i:s")."] " .
              $activity_msg."\n");
       fclose($log_file);
    }
}

function log_import_error($import_id,$msg)
{
    global $activity_log;

    $path_parts = pathinfo($activity_log);
    $import_log_filename = $path_parts['dirname'].'/vendors/import-' .
                           $import_id.'.log';
    $log_file = @fopen($import_log_filename,'at');
    if ($log_file) {
       fwrite($log_file,'['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($log_file);
    }
}

function get_unique_field_names($field_names)
{
     $field_name_counts = array();
     foreach ($field_names as $index => $field_name) {
        $field_name = trim($field_name);
        $field_name = str_replace("\n",' ',$field_name);
        if ($field_names[$index] != $field_name)
           $field_names[$index] = $field_name;
        if (! $field_name) {
           unset($field_names[$index]);   continue;
        }
        if (detect_utf8($field_name)) {
           $field_name = make_utf8($field_name);
           $field_names[$index] = $field_name;
        }
        if (! isset($field_name_counts[$field_name]))
           $field_name_counts[$field_name] = 1;
        else {
           $field_name_counts[$field_name]++;
           $field_name .= ' (#'.$field_name_counts[$field_name].')';
           $field_names[$index] = $field_name;
        }
     }
     return $field_names;
}

function cleanup_data($data,$field_name)
{
    $data = make_utf8($data);
    $bull_pos = strpos($data,'&bull;');
    if ($bull_pos !== false) {
       $data = substr($data,0,$bull_pos).'<ul>'.substr($data,$bull_pos);
       $bull_pos += 4;
       while ($bull_pos !== false) {
          $data = substr($data,0,$bull_pos).'<li>'.substr($data,$bull_pos + 6);
          $eol_pos = strpos($data,"\n",$bull_pos);
          if ($eol_pos !== false) {
             $data = substr($data,0,$eol_pos).'</li>' .
                     substr($data,$eol_pos + 1);
             $eol_pos += 5;
          }
          $bull_pos = strpos($data,'&bull;');
          if (($bull_pos !== false) && ($eol_pos === false)) {
             $data = substr($data,0,$bull_pos).'</li>' .
                     substr($data,$bull_pos);
             $bull_pos += 5;
          }
       }
       if ($eol_pos !== false)
          $data = substr($data,0,$eol_pos).'</ul>'.substr($data,$eol_pos);
       else $data .= '</li></ul>';
       $start_ul = strpos($data,'<ul>');
       $end_ul = strpos($data,'</ul>') + 5;
    }
    else {
       $start_ul = false;   $end_ul = false;
    }

    if ($field_name == 'long_description') {
       $eol_pos = strpos($data,"\n");
       if ($eol_pos !== false) {
          $start_pos = 0;
          while ($eol_pos !== false) {
             if (($end_ul !== false) && ($start_pos < $end_ul) &&
                 ($eol_pos > $end_ul)) $start_pos = $end_ul;
             if (($start_ul !== false) && ($start_pos < $start_ul) &&
                 ($eol_pos > $start_ul)) {
                $data = substr($data,0,$start_pos).'<p>' .
                        substr($data,$start_pos,$start_ul - $start_pos).'</p>' .
                        substr($data,$start_ul);
                $start_pos = $end_ul + 7;
             }
             else {
                $data = substr($data,0,$start_pos).'<p>' .
                        substr($data,$start_pos,$eol_pos - $start_pos).'</p>' .
                        substr($data,$eol_pos + 1);
                $start_pos = $eol_pos + 8;
             }
             $eol_pos = strpos($data,"\n");
          }
       }
       else if ($start_ul !== false) {
          if ($start_ul != 0) {
             $data = '<p>'.substr($data,0,$start_ul).'</p>' .
                     substr($data,$start_ul);
             $end_ul += 7;
          }
          if ($end_ul != strlen($data))
             $data = substr($data,0,$end_ul).'<p>'.substr($data,$end_ul) .
                     '</p>';
       }
       else if ($data) $data = '<p>'.$data.'</p>';
    }

    return $data;
}

function update_vendor_info($db,&$vendor_info)
{
    if (! $vendor_info['country']) $vendor_info['country'] = 1;
    $country_id = $vendor_info['country'];
    $country_info = get_country_info($country_id,$db);
    if ($country_info) {
       $vendor_info['country_code'] = $country_info['code'];
       $vendor_info['country_name'] = $country_info['country'];
    }
    else {
       $vendor_info['country_code'] = '';
       $vendor_info['country_name'] = '';
    }
}

function get_last_modified($url,$timeout=2)
{
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_HEADER,true);
    curl_setopt($ch,CURLOPT_FILETIME,true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
    curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
    curl_setopt($ch,CURLOPT_NOBODY,true);
    curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
    curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
    $head = curl_exec($ch);
    if (! $head) $curl_error = curl_error($ch);
    curl_close($ch);
    if (! $head) {
       log_vendor_activity('Error downloading URL '.$url.': '.$curl_error);
       return -1;
    }
    $head_lines = explode("\n",$head);
    foreach ($head_lines as $header) {
       if (substr(strtolower($header),0,15) == 'last-modified: ')
          return strtotime(substr($header,15));
    }
    return null;
}

$loaded_vendor_modules = array();

function load_vendor_module($vendor_info)
{
    global $admin_directory,$loaded_vendor_modules;

    if (empty($vendor_info['module'])) return;
    $module = $vendor_info['module'];
    if (! isset($admin_directory)) $admin_directory = '../admin/';
    $module_file = $admin_directory.'vendors/'.$module.'.php';
    if (! file_exists($module_file)) return;
    require_once $module_file;
    $loaded_vendor_modules[$module] = true;
}

function module_event_exists($vendor_info,$event)
{
    global $loaded_vendor_modules;

    if (empty($vendor_info['module'])) return false;
    $module = $vendor_info['module'];
    $function_name = $module.'_'.$event;
    if (empty($loaded_vendor_modules[$module]))
       load_vendor_module($vendor_info);
    return function_exists($function_name);
}

function call_vendor_event($vendor_info,$event,$parameters)
{
    global $loaded_vendor_modules;

    if (empty($vendor_info['module'])) return true;
    $module = $vendor_info['module'];
    $function_name = $module.'_'.$event;
    if (empty($loaded_vendor_modules[$module]))
       load_vendor_module($vendor_info);
    if (! function_exists($function_name)) return true;
    $ret_value = call_user_func_array($function_name,$parameters);
    return $ret_value;
}

?>
