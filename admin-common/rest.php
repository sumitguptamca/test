<?php
/*
               Inroads Control Panel/Shopping Cart - REST API Interface

                        Written 2014-2019 by Randall Severy
                         Copyright 2014-2019 Inroads, LLC
*/

require_once '../engine/ui.php';
require_once '../engine/db.php';
if (file_exists("../cartengine/adminperms.php")) {
   $shopping_cart = true;
   require_once '../cartengine/api.php';
   require_once '../cartengine/adminperms.php';
}
else {
   $shopping_cart = false;
   require_once 'api.php';
   require_once 'adminperms.php';
}
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

define('FORMAT_XML',0);
define('FORMAT_JSON',1);

$format = get_form_field('Format');
if ($format == 'JSON') $format = FORMAT_JSON;
else $format = FORMAT_XML;

function write_json_header()
{
    header("Cache-Control: no-cache");
    header("Expires: -1441");
    header("Content-Type: application/json; charset=utf-8");
}

function json_response($status,$message)
{
    $response = new stdClass();
    $response->status = $status;
    $response->message = $message;
    write_json_header();
    print json_encode($response);
}

function rest_response($status,$message)
{
    global $format;

    if ($format == FORMAT_XML) http_response($status,$message);
    else json_response($status,$message);
}

function rest_error($error_code,$error_msg)
{
    log_error($error_msg);
    rest_response($error_code,$error_msg);
}

function process_validate_token_command($db)
{
    $token = get_form_field('token');
    if (! $token) {
       rest_error(406,'VALIDATETOKEN: Missing Token');   return;
    }
    $query = 'select config_value from config where ' .
             'config_name="security_token"';
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) rest_error(422,'VALIDATETOKEN: '.$db->error);
       else rest_error(404,'VALIDATETOKEN: Missing Security Token');
       return;
    }
    $query = 'delete from config where config_name="security_token"';
    if (! $db->query($query)) {
       rest_error(422,'VALIDATETOKEN: '.$db->error);   return;
    }
    rest_response(201,'Token Validated');
}

function check_command_user($api_user,$password)
{
    global $siteid,$command_center;

    if (! empty($command_center))
       $url = $command_center.'/index.php?cmd=validateuser';
    else if (substr($siteid,0,3) == 'qw-')
       $url = 'http://www.quik-web.net/admin/accounts.php?' .
              'cmd=validateuser&siteid='.urlencode($siteid);
    else $url = 'https://www.inroads.us/admin/index.php?cmd=validateuser';
    $url .= '&username='.urlencode($api_user).'&password=' .
            urlencode($password).'&validateonly=true';
    $validation = file_get_contents($url);
    $start_pos = strpos($validation,'<status>');
    if ($start_pos === false) return false;
    $start_pos += 8;
    $end_pos = strpos($validation,'</status>',$start_pos);
    if ($end_pos === false) return false;
    $status = substr($validation,$start_pos,$end_pos - $start_pos);
    if ($status != '201') return false;
    set_remote_user($api_user);
    return true;
}

function get_api_user_perms($db,&$api_user)
{
    $api_user = get_cookie('User');
    if (! $api_user) $api_user = get_form_field('APIUser');
    $password = get_cookie('Password');
    if (! $password) $password = get_form_field('APIPassword');
    if (! $api_user) {
       rest_error(401,'API Login is required');   return -1;
    }
    $query = 'select perms,password from users where username=?';
    $query = $db->prepare_query($query,$api_user);
    $row = $db->get_record($query);
    if (! $row) {
       if (check_command_user($api_user,$password))
          return ADMIN_USERS_TAB_PERM|SYSTEM_CONFIG_BUTTON_PERM;
       rest_error(404,'API User "'.$api_user.'" not found');   return -1;
    }
    $db->decrypt_record('users',$row);
    if ($password != $row['password']) {
       if (check_command_user($api_user,$password))
          return ADMIN_USERS_TAB_PERM|SYSTEM_CONFIG_BUTTON_PERM;
       rest_error(401,'Invalid API Password for API User "'.$api_user.'"');
       return -1;
    }
    set_remote_user($api_user);
    return $row['perms'];
}

function process_user_command($command,$user_perms,$quikweb,$api_user)
{
    global $login_cookie,$format;

    if (! ($user_perms & ADMIN_USERS_TAB_PERM)) {
       rest_error(401,'Insufficient Permissions for Admin Functions');
       return;
    }
    $_COOKIE[$login_cookie] = $api_user;
    switch ($command) {
       case 'LISTUSER':
          $username = get_form_field('Username');
          if (! $username) {
             rest_error(406,'LISTUSER: Username is required');   return;
          }
          $users = $quikweb->get_users($username);
          if ($users === null) {
             rest_error(422,'LISTUSER: '.$quikweb->error);   return;
          }
          if (! isset($users[$username])) {
             rest_error(404,'LISTUSER: User "'.$username.'" not found');
             return;
          }
          if ($format == FORMAT_XML) {
             write_xml_header();
             print '<User>';
             foreach ($users[$username] as $field_name => $field_value) {
                if ($field_name == 'password') continue;
                print '<'.$field_name.'>';   write_xml_data($field_value);
                print '</'.$field_name.'>';
             }
             print '</User>';
          }
          else {
             $user = new stdClass();
             foreach ($users[$username] as $field_name => $field_value) {
                if ($field_name == 'password') continue;
                $user->$field_name = $field_value;
             }
             write_json_header();
             print json_encode($user);
          }
          break;
       case 'LISTUSERS':
          $users = $quikweb->get_users();
          if ($users === null) {
             rest_error(422,'LISTUSERS: '.$quikweb->error);   return;
          }
          if ($format == FORMAT_XML) {
             write_xml_header();
             print '<Users>';
             foreach ($users as $user_info) {
                print '<User>';
                foreach ($user_info as $field_name => $field_value) {
                   if ($field_name == 'password') continue;
                   print '<'.$field_name.'>';   write_xml_data($field_value);
                   print '</'.$field_name.'>';
                }
                print '</User>';
             }
             print '</Users>';
          }
          else {
             $user_array = array();
             foreach ($users as $user_info) {
                $user = new stdClass();
                foreach ($user_info as $field_name => $field_value) {
                   if ($field_name == 'password') continue;
                   $user->$field_name = $field_value;
                }
                $user_array[] = $user;
             }
             write_json_header();
             print json_encode($user_array);
          }
          break;
       case 'ADDUSER':
          $form_fields = get_form_fields();
          $user_info = array();
          foreach ($form_fields as $field_name => $field_value)
             $user_info[$field_name] = $field_value;
          if ((! isset($user_info['username'])) ||
              (! $user_info['username'])) {
             rest_error(406,'ADDUSER: Username is required');   return;
          }
          if ((! isset($user_info['password'])) ||
              (! $user_info['password'])) {
             rest_error(406,'ADDUSER: Password is required');   return;
          }
          if (! $quikweb->add_user($user_info)) {
             rest_error(422,'ADDUSER: '.$quikweb->error);   return;
          }
          rest_response(201,'User Added');
          break;
       case 'UPDATEUSER':
          $form_fields = get_form_fields();
          $user_info = array();
          foreach ($form_fields as $field_name => $field_value)
             $user_info[$field_name] = $field_value;
          if ((! isset($user_info['username'])) ||
              (! $user_info['username'])) {
             rest_error(406,'UPDATEUSER: Username is required');   return;
          }
          if (! $quikweb->update_user($user_info)) {
             rest_error(422,'UPDATEUSER: '.$quikweb->error);   return;
          }
          rest_response(201,'User Updated');
          break;
       case 'DELETEUSER':
          $username = get_form_field('username');
          if (! $username) {
             rest_error(406,'DELETEUSER: Username is required');   return;
          }
          if (! $quikweb->delete_user($username)) {
             rest_error(422,'DELETEUSER: '.$quikweb->error);   return;
          }
          rest_response(201,'User Deleted');
          break;
       case 'CHANGEPW':
          $username = get_form_field('username');
          if (! $username) {
             rest_error(406,'CHANGEPW: Username is required');   return;
          }
          $password = get_form_field('password');
          if (! $password) {
             rest_error(406,'CHANGEPW: Password is required');   return;
          }
          if (! $quikweb->get_user_record($username)) {
             rest_response(410,'CHANGEPW: User Not Found');   return;
          }
          if (! $quikweb->change_password($username,$password)) {
             rest_error(422,'CHANGEPW: '.$quikweb->error);   return;
          }
          rest_response(201,'Password Changed');
          break;
       case 'UPDATEPREFS':
          break;
    }
}

function process_system_command($command,$user_perms,$quikweb,$api_user,$db)
{
    global $login_cookie,$format,$event_names;

    if (! ($user_perms & SYSTEM_CONFIG_BUTTON_PERM)) {
       rest_error(401,'Insufficient Permissions for System Functions');
       return;
    }
    require_once '../engine/modules.php';
    $_COOKIE[$login_cookie] = $api_user;
    switch ($command) {
       case 'SETHOSTNAME':
          $old_hostname = get_form_field('old_hostname');
          $old_prefix = get_form_field('old_prefix');
          $new_hostname = get_form_field('new_hostname');
          $new_prefix = get_form_field('new_prefix');
          $ssl_flag = get_form_field('ssl_flag');
          if (! call_module_event('set_hostname',
                   array($old_hostname,$old_prefix,$new_hostname,$new_prefix,
                         $ssl_flag)))
             rest_error(422,'SETHOSTNAME: '.get_module_errors());
          else rest_response(201,'Hostname Changed');
          break;
       case 'SETACCOUNTPASSWORD':
          $username = get_form_field('username');
          $old_password = get_form_field('old_password');
          $new_password = get_form_field('new_password');
          if (! call_module_event('set_account_password',
                   array($username,$old_password,$new_password)))
             rest_error(422,'SETACCOUNTPASSWORD: '.get_module_errors());
          else rest_response(201,'Password Changed');
          break;
       case 'INITMODULES':
          require_once '../engine/modules.php';
          initialize_modules($event_names);
          rest_response(201,'Modules Initialized');
          break;
       case 'UPDATEDB':
          $query_data = get_form_field('Query');
          $queries = explode("\n",$query_data);
          $db = new DB;   $inside_create = false;
          foreach ($queries as $query) {
             if ((strtolower(substr($query,0,12)) == 'create table') ||
                 (strtolower(substr($query,0,19)) == 'create unique index') ||
                 (strtolower(substr($query,0,12)) == 'create index')) {
                $inside_create = true;   $create_query = '';
             }
             if ($inside_create) {
                $comment_pos = strpos($query,'#');
                if ($comment_pos !== false)
                   $query = substr($query,0,$comment_pos);
                $create_query .= $query;
                if ((substr($query,-2) == "\\g") ||
                    (substr($query,-1) == ';')) {
                   $query = $create_query;   $inside_create = false;
                }
                else continue;
             }
             if (substr($query,-2) == "\\g") $query = substr($query,0,-2);
             else if (substr($query,-1) == ';') $query = substr($query,0,-1);
             if (substr($query,0,1) == '#') continue;
             if ($query == '') continue;
             if (! $db->query($query)) {
                rest_error(422,'UPDATEDB: '.$db->error);   return;
             }
          }
          log_activity('Updated Database from UPDATEDB API Call');
          rest_response(201,'Database Updated');
          break;
       case 'LISTWEBSITES':
          $query = 'select * from web_sites order by id';
          $web_sites = $db->get_records($query);
          if ($web_sites === null) {
             rest_error(422,'LISTWEBSITES: '.$db->error);   return;
          }
          if ($format == FORMAT_XML) {
             write_xml_header();
             print '<WebSites>';
             foreach ($web_sites as $web_site) {
                print '<WebSite>';
                foreach ($web_site as $field_name => $field_value) {
                   print '<'.$field_name.'>';   write_xml_data($field_value);
                   print '</'.$field_name.'>';
                }
                print '</WebSite>';
             }
             print '</WebSites>';
          }
          else {
             $web_site_array = array();
             foreach ($web_sites as $web_site_info) {
                $web_site = new stdClass();
                foreach ($web_site_info as $field_name => $field_value) {
                   $web_site->$field_name = $field_value;
                }
                $web_site_array[] = $web_site;
             }
             write_json_header();
             print json_encode($web_site_array);
          }
          break;
    }
}

set_remote_user('api');
$db = new DB;
$command = get_form_field('Command');
if (! $command) {
   rest_error(406,'API Command is required');   DB::close_all();   exit(0);
}
if ($command == 'VALIDATETOKEN') {
   process_validate_token_command($db);   DB::close_all();   exit(0);
}
$user_perms = get_api_user_perms($db,$api_user);
if ($user_perms == -1) {
   DB::close_all();   exit(0);
}

$quikweb = new QuikWebAPI(null,$db);
if (function_exists('custom_process_rest_command') &&
    custom_process_rest_command($command,$user_perms,$quikweb,$api_user,$db)) {}
else if (in_array($command,array('LISTUSER','LISTUSERS','ADDUSER','DELETEUSER',
                            'UPDATEUSER','CHANGEPW','UPDATEPREFS')))
   process_user_command($command,$user_perms,$quikweb,$api_user);
else if (in_array($command,array('SETHOSTNAME','SETACCOUNTPASSWORD',
                                 'INITMODULES','UPDATEDB','LISTWEBSITES')))
   process_system_command($command,$user_perms,$quikweb,$api_user,$db);
else rest_error(410,'Invalid API Command '.$command);

DB::close_all();

?>
