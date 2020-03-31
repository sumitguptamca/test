<?php
/*
        Inroads Control Panel/Shopping Cart - LDAP Authentication Functions

                     Written 2018-2019 by Randall Severy
                      Copyright 2018-2019 Inroads, LLC
*/

require_once 'config.php';

define(LDAP_OPT_DIAGNOSTIC_MESSAGE,0x0032);

function get_ldap_error($ldap)
{
    if (ldap_get_option($ldap,LDAP_OPT_DIAGNOSTIC_MESSAGE,$error))
       return $error;
    return null;
}

function get_data_code($error)
{
    $start_pos = strpos($error,'data ');
    if ($start_pos === false) return null;
    $start_pos += 5;
    $end_pos = strpos($error,',',$start_pos);
    if ($end_pos === false) return null;
    return substr($error,$start_pos,$end_pos - $start_pos);
}

function connect_ldap_host(&$error)
{
    global $ldap_host;

    putenv('LDAPTLS_REQCERT=never');
    $url_info = parse_url($ldap_host);
    $fp = @fsockopen($url_info['host'],$url_info['port'],$errno,
                     $error_string,1);
    if (! $fp) {
       if (! $error_string) {
          $last_error = error_get_last();
          $error_string = $last_error['message'];
       }
       $error = 'Unable to connect to LDAP Server: '.$error_string .
                ' ('.$errno.')';
       return null;
    }
    $ldap = @ldap_connect($ldap_host);
    if (! $ldap) {
       $error = 'Unable to connect to LDAP Server';   return null;
    }
    return $ldap;
}

function validate_ldap_user($username,$password,&$error)
{
    $ldap = connect_ldap_host($error);
    if (! $ldap) return false;
    $bind = @ldap_bind($ldap,$username,$password);
    if (! $bind) {
       $error = get_ldap_error($ldap);
       $data_code = get_data_code($error);
       if ($data_code == '52e')
          $error = 'Invalid LDAP Username/Password for User '.$username;
       else $error = 'LDAP Bind Failed = '.$error;
       return false;
    }
    return $ldap;
}

function convert_ldap_date($date)
{
    $y = substr($date,0,4);
    $m = substr($date,4,2);
    $d = substr($date,6,2);
    $h = substr($date,8,2);
    $i = substr($date,10,2);
    $s = substr($date,12,2);
    $timestamp = mktime($h,$i,$s,$m,$d,$y);
    return $timestamp;
}

function get_ldap_users($ldap,&$error)
{
    global $ldap_dn;

    $search_filter = '(objectCategory=person)';
    $ldap_result = ldap_search($ldap,$ldap_dn,$search_filter);
    if (! $ldap_result) {
       $error = 'LDAP Search Failed = '.get_ldap_error($ldap);
       return null;
    }
    $entries = ldap_get_entries($ldap,$ldap_result);
    $users = array();
    foreach ($entries as $index => $entry) {
       if (! is_numeric($index)) continue;
       $username = $entry['userprincipalname'][0];
       $fname = $entry['givenname'][0];
       $lname = $entry['sn'][0];
       $email = $entry['mail'][0];
       $phone = $entry['telephonenumber'][0];
       $created = convert_ldap_date($entry['whencreated'][0]);
       $modified = convert_ldap_date($entry['whenchanged'][0]);
       $users[] = array('username'=>$username,'fname'=>$fname,'lname'=>$lname,
                        'email'=>$email,'phone'=>$phone,'created'=>$created,
                        'modified'=>$modified);
    }
    return $users;
}

function get_ldap_user($ldap,$username,&$error)
{
    global $ldap_dn;

    $search_filter = '(userprincipalname='.$username.')';
    $ldap_result = ldap_search($ldap,$ldap_dn,$search_filter);
    if (! $ldap_result) {
       $error = 'LDAP Search Failed = '.get_ldap_error($ldap);
       return null;
    }
    $entries = ldap_get_entries($ldap,$ldap_result);
    if (! isset($entries[0])) {
       $error = 'LDAP User '.$username.' Not Found';   return null;
    }
    $username = $entries[0]['userprincipalname'][0];
    if (isset($entries[0]['givenname'][0]))
       $fname = $entries[0]['givenname'][0];
    else $fname = '';
    if (isset($entries[0]['sn'][0])) $lname = $entries[0]['sn'][0];
    else if (isset($entries[0]['name'][0])) $lname = $entries[0]['name'][0];
    else $lname = '';
    if (isset($entries[0]['mail'][0])) $email = $entries[0]['mail'][0];
    else $email = $username;
    if (isset($entries[0]['telephonenumber'][0]))
       $phone = $entries[0]['telephonenumber'][0];
    else $phone = '';
    $created = convert_ldap_date($entries[0]['whencreated'][0]);
    $modified = convert_ldap_date($entries[0]['whenchanged'][0]);
    $user_info = array('username'=>$username,'fname'=>$fname,'lname'=>$lname,
                       'email'=>$email,'phone'=>$phone,'created'=>$created,
                       'modified'=>$modified);
    return $user_info;
}

function lookup_ldap_user($username,$password)
{
    global $user_pref_names,$ldap_user_info;

    require_once 'adminusers-common.php';

    $username = trim($username);   $password = trim($password);
    $ldap = validate_ldap_user($username,$password,$error);
    if (! $ldap) {
       log_error($error);   return null;
    }
    $user_info = get_ldap_user($ldap,$username,$error);
    if (! $user_info) {
       log_error($error);   return null;
    }

    $db = new DB;
    $query = 'select * from users where username="default"';
    $default_info = $db->get_record($query);
    if (! $default_info) {
       log_error('Default User not found');   return null;
    }
    $query = 'select * from user_prefs where username="default"';
    $default_prefs = $db->get_records($query,'pref_name','pref_value');
    if (! $default_prefs) {
       log_error('Default User Preferences not found');   return null;
    }
    $user_record = user_record_definition();
    $user_record['username']['value'] = $username;
    $user_record['password']['value'] = $password;
    $user_record['firstname']['value'] = $user_info['fname'];
    $user_record['lastname']['value'] = $user_info['lname'];
    $user_record['email']['value'] = $user_info['email'];
    $user_record['office_phone']['value'] = $user_info['phone'];
    $user_record['perms']['value'] = $default_info['perms'];
    $user_record['module_perms']['value'] = $default_info['module_perms'];
    $user_record['custom_perms']['value'] = $default_info['custom_perms'];
    $user_record['creation_date']['value'] = $user_info['created'];
    $user_record['modified_date']['value'] = $user_info['modified'];
    if (! empty($ldap_user_info)) {
       foreach ($ldap_user_info as $field_name => $field_value)
          $user_record[$field_name]['value'] = $field_value;
    }
    if (function_exists('custom_update_user')) {
       if (! custom_update_user($db,$user_record,ADDRECORD)) return null;
    }
    require_once '../engine/modules.php';
    if (module_attached('add_user')) {
       $user_info = $db->convert_record_to_array($user_record);
       if (! call_module_event('add_user',array($db,$user_info))) {
          call_module_event('delete_user',array($db,$user_info));
          log_error(get_module_errors());   return null;
       }
    }
    if (! $db->insert('users',$user_record)) return null;
    $error_msg = null;
    if (! save_user_prefs($db,$user_record,ADDRECORD,$error_msg,
                          $default_prefs)) {
       log_error($error_msg);   return null;
    }
    if (function_exists('custom_save_user')) {
       if (! custom_save_user($db,$user_record,ADDRECORD)) return null;
    }
    log_activity('Added LDAP User '.$username);
    return $user_info;
}

?>
