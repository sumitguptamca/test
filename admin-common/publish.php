<?php
/*
            Inroads Control Panel/Shopping Cart - Publish to Live Site Module

                         Written 2013-2018 by Randall Severy
                          Copyright 2013-2018 Inroads, LLC
*/

define('PUBLISH_TO_CMS',true);

require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once 'utility.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

$live_to_dev_tables = array('cart','cart_items','cart_attributes','wishlist',
   'wishlist_attributes','customers','billing_information','shipping_information',
   'orders','order_items','order_attributes','order_billing','order_shipping',
   'order_payments','coupons','coupon_products','coupon_inventory','coupon_customers',
   'forms','registry','registry_items');
$dev_to_live_tables = array('users','user_prefs','user_accounts','accounts',
   'account_products','account_inventory','categories','subcategories',
   'category_products','products','related_products',
   'product_attributes','product_data','product_discounts',
   'attributes','attribute_options','images','templates','attachments',
   'cart_config','cart_options','countries','states','config','web_sites',
   'schedule','schedule_items');
$skip_directories = array('blog','cgi-bin','cmsimport','editor-preview',
   'update','mobile','files','editor-support/thumbs','admin/tickets');
$skip_files = array('admin/shoppingcart.err','admin/shoppingcart.log',
   'admin/shoppingcart.sql','admin/payment.log','admin/shipping.log',
   'admin/eye4fraud.log','robots.txt','/cartengine/spawn.log',
   'admin/modules/cms.php');
$skip_props = array('name','filesize','accessdate','versionfile','revision',
   'nextrev','modifydate','thumb_name','thumb_width','thumb_height','thumb_date',
   'image_width','locked');
if (function_exists('custom_update_publish_arrays'))
   custom_update_publish_arrays();

function open_databases(&$dev_db,&$live_db)
{
    global $db_host,$db_name,$db_user,$db_pass,$live_site_db_host;
    global $live_site_db_user,$live_site_db_name,$live_site_db_pass;

    $dev_db = new DB;
    $dev_db->enable_log_query(false);
    save_db_info();
    $db_host = $live_site_db_host;   $db_user = $live_site_db_user;
    $db_name = $live_site_db_name;   $db_pass = $live_site_db_pass;
    $live_db = new DB;
    $live_db->enable_log_query(false);
    restore_db_info();
}

function create_directory($dir)
{
    global $new_dir_perms;

    $slash_pos = strpos($dir,'/');
    while ($slash_pos !== false) {
       $subdir = substr($dir,0,$slash_pos + 1);
       if (! is_dir($subdir)) {
          if (! @mkdir($subdir)) {
             $error = 'Unable to create directory '.$subdir;
             log_error($error);   http_response(422,$error);   return false;
          }
          if (isset($new_dir_perms) && (! chmod($subdir,$new_dir_perms))) {
             $error = 'Unable to set permissions on '.$subdir;
             log_error($error);   http_response(422,$error);   return false;
          }
       }
       $slash_pos = strpos($dir,'/',$slash_pos + 1);
    }
    if (! is_dir($dir)) {
       if (! @mkdir($dir)) {
          $error = 'Unable to create directory '.$dir;
          log_error($error);   http_response(422,$error);   return false;
       }
       if (isset($new_dir_perms) && (! chmod($dir,$new_dir_perms))) {
          $error = 'Unable to set permissions on '.$dir;
          log_error($error);   http_response(422,$error);   return false;
       }
    }
    return true;
}

function convert_admin_config($page_content)
{
    global $dev_site_hostname,$live_site_hostname,$live_site_path;
    global $live_site_db_host,$live_site_db_user,$live_site_db_name;
    global $live_site_db_pass,$db_host,$db_user,$db_name,$db_pass,$docroot;

    $replaces = array();
    $replaces[] = array('use_development_site','on_live_site');
    $replaces[] = array("\$live_site_hostname = \"".$live_site_hostname."\";\n",'');
    $replaces[] = array("\$live_site_path = \"".$live_site_path."\";\n",'');
    $replaces[] = array("\$live_site_db_host = \"".$live_site_db_host."\";\n",'');
    $replaces[] = array("\$live_site_db_user = \"".$live_site_db_user."\";\n",'');
    $replaces[] = array("\$live_site_db_name = \"".$live_site_db_name."\";\n",'');
    $replaces[] = array("\$live_site_db_pass = \"".$live_site_db_pass."\";\n",'');
    $replaces[] = array("db_host = \"".$db_host,"db_host = \"".$live_site_db_host);
    $replaces[] = array("db_user = \"".$db_user,"db_user = \"".$live_site_db_user);
    $replaces[] = array("db_name = \"".$db_name,"db_name = \"".$live_site_db_name);
    $replaces[] = array("db_pass = \"".$db_pass,"db_pass = \"".$live_site_db_pass);
    $replaces[] = array("docroot = \"".$docroot,"docroot = \"".$live_site_path);
    foreach ($replaces as $index => $replace)
       $page_content = str_replace($replace[0],$replace[1],$page_content);
    $page_content = str_replace($dev_site_hostname,$live_site_hostname,
                                $page_content);
    $page_content = str_replace("dev_site_hostname = \"".$live_site_hostname,
                                "dev_site_hostname = \"".$dev_site_hostname,
                                $page_content);
    return $page_content;
}

function compare_properties($dev_properties,$live_properties)
{
    global $skip_props;

    foreach ($dev_properties as $propname => $propvalue) {
       if (in_array($propname,$skip_props)) continue;
       if ((! isset($live_properties[$propname])) ||
           ($propvalue != $live_properties[$propname])) return false;
    }
    foreach ($live_properties as $propname => $propvalue) {
       if (in_array($propname,$skip_props)) continue;
       if (! isset($dev_properties[$propname])) return false;
    }
       
    return true;
}

function build_property_list($dev_properties,$live_properties,
                             &$empty_properties)
{
    global $skip_props;

    $property_list = '';   $old_properties = $live_properties;
    foreach ($dev_properties as $propname => $propvalue) {
       if (in_array($propname,$skip_props)) {
          if ($old_properties && isset($old_properties[$propname]))
             unset($old_properties[$propname]);
          continue;
       }
       if ($old_properties && isset($old_properties[$propname]) &&
           ($propvalue == $old_properties[$propname])) {
          unset($old_properties[$propname]);   continue;
       }
       if (($propvalue !== '') && ($propname != 'flags')) {
          if ($property_list != '') $property_list .= '&';
          $property_list .= $propname.'='.urlencode($propvalue);
       }
       else $empty_properties[] = $propname;
       if ($old_properties) unset($old_properties[$propname]);
    }
    if ($old_properties) {
       foreach ($old_properties as $propname => $propvalue) {
          if ($propname == 'flags') {
             if ($property_list != '') $property_list .= '&';
             $property_list .= $propname.'=';
          }
          else $empty_properties[] = $propname;
       }
    }
    return $property_list;
}

function process_file($dev_site_path,$live_site_path,$current_dir,$filename,
                      $dev_wsd,$live_wsd,$dev_files,$live_files)
{
    global $dev_site_hostname,$live_site_hostname,$skip_directories;
    global $skip_files;

    $basepath = $dev_site_path.'/'.$current_dir;
    if (is_dir($basepath.$filename)) {
       $subdir = $current_dir.$filename;
       if (in_array($subdir,$skip_directories)) return true;
       $dest_dir = $live_site_path.'/'.$subdir;
       if ((! is_dir($dest_dir)) && (! create_directory($dest_dir)))
          return false;
       $dir_handle = @opendir($basepath.$filename.'/');
       if ($dir_handle) {
          $subdir .= '/';
          while (($filename = readdir($dir_handle)) !== false) {
             if (($filename == '.') || ($filename == '..')) continue;
             if (! process_file($dev_site_path,$live_site_path,$subdir,
                                $filename,$dev_wsd,$live_wsd,$dev_files,
                                $live_files)) {
                closedir($dir_handle);   return false;
             }
          }
          closedir($dir_handle);
       }
       return true;
    }

    $rel_path = $current_dir.$filename;
    if (in_array($rel_path,$skip_files)) return true;

    $full_filename = '/'.$current_dir.$filename;
    $source_filename = $basepath.$filename;
    $source_fp = @fopen($source_filename,'r');
    if ($source_fp) {
       $source_fstat = fstat($source_fp);
       fclose($source_fp);
       $source_atime = $source_fstat['atime'];
       if (PUBLISH_TO_CMS && isset($dev_files[$full_filename],
                                   $dev_files[$full_filename]['versionfile'],
                                   $dev_files[$full_filename]['checkindate']))
          $source_mtime = $dev_files[$full_filename]['checkindate'];
       else $source_mtime = $source_fstat['mtime'];
    }
    else {
       $source_mtime = 0;   $source_atime = 0;
    }
    $dest_filename = $live_site_path.'/'.$current_dir.$filename;
    if (PUBLISH_TO_CMS && isset($live_files[$full_filename],
                                $live_files[$full_filename]['versionfile'],
                                $live_files[$full_filename]['checkindate']))
       $dest_mtime = $live_files[$full_filename]['checkindate'];
    else {
       $dest_fp = @fopen($dest_filename,'r');
       if ($dest_fp) {
          $dest_fstat = fstat($dest_fp);
          fclose($dest_fp);
          $dest_mtime = $dest_fstat['mtime'];
       }
       else $dest_mtime = 0;
    }
    if ($source_mtime != $dest_mtime) {
       $extension = pathinfo($filename,PATHINFO_EXTENSION);
       if (PUBLISH_TO_CMS) $update_checkindate = false;
       if (PUBLISH_TO_CMS && isset($dev_files[$full_filename],
                                   $dev_files[$full_filename]['versionfile'])) {
          $page_content = $dev_wsd->get($full_filename);
          if ($page_content === null) {
             $error = 'Unable to get file content for '.$source_filename.': ' .
                      $dev_wsd->error;
             log_error($error);   http_response(422,$error);   return false;
          }
          if (($extension == 'html') || ($extension == 'php') ||
              ($extension == 'js') || ($extension == 'txt') ||
              ($filename == '.htaccess')) {
             if ($rel_path == 'admin/config.php')
                $page_content = convert_admin_config($page_content);
             else $page_content = str_replace($dev_site_hostname,$live_site_hostname,
                                              $page_content);
          }
          if (! isset($live_files[$full_filename],
                      $live_files[$full_filename]['versionfile'])) {
             if (isset($live_files[$full_filename])) {
                if ($live_wsd->init_version($full_filename) != '200') {
                   $error = 'Unable to create version file for '.$dest_filename .
                            ': '.$live_wsd->error;
                   log_error($error);   http_response(422,$error);   return false;
                }
                if ($live_wsd->put($full_filename,$page_content,true,false) != '200') {
                   $error = 'Unable to update file content for '.$dest_filename .
                            ': '.$live_wsd->error;
                   log_error($error);   http_response(422,$error);   return false;
                }
             }
             else if ($live_wsd->create($full_filename,$page_content) != '200') {
                $error = 'Unable to create file content for '.$dest_filename .
                         ': '.$live_wsd->error;
                log_error($error);   http_response(422,$error);   return false;
             }
          }
          else if ($live_wsd->put($full_filename,$page_content,true,false) != '200') {
             $error = 'Unable to update file content for '.$dest_filename .
                      ': '.$live_wsd->error;
             log_error($error);   http_response(422,$error);   return false;
          }
          if (isset($dev_files[$full_filename]['checkindate']))
             $update_checkindate = true;
       }
       else if (($extension == 'html') || ($extension == 'php') ||
           ($extension == 'js') || ($extension == 'txt') ||
           ($filename == '.htaccess')) {
          $page_content = file_get_contents($source_filename);
          if ($rel_path == 'admin/config.php')
             $page_content = convert_admin_config($page_content);
          else $page_content = str_replace($dev_site_hostname,$live_site_hostname,
                                           $page_content);
          $page_file = fopen($dest_filename,'w');
          if (! $page_file) {
             $error = 'Unable to open file '.$dest_filename;
             log_error($error);   http_response(422,$error);   return false;
          }
          if (fwrite($page_file,$page_content) === false) {
             $error = 'Unable to update file '.$dest_filename;
             log_error($error);   http_response(422,$error);   return false;
          }
          fclose($page_file);
       }
       else if (! copy($source_filename,$dest_filename)) {
          $error = 'Unable to copy '.$source_filename.' to '.$dest_filename;
          log_error($error);   http_response(422,$error);   return false;
       }
       if (PUBLISH_TO_CMS) {
          if ((! isset($dev_files[$full_filename],
                       $dev_files[$full_filename]['versionfile'])) &&
              isset($live_files[$full_filename],
                    $live_files[$full_filename]['versionfile'])) {
             $status = $live_wsd->delete_version($live_files[$full_filename]['versionfile']);
             if (($status != '200') && ($status != '410')) {
                $error = 'Unable to delete version file for '.$dest_filename;
                log_error($error);   http_response(422,$error);   return false;
             }
          }
          if ((! isset($dev_files[$full_filename])) &&
              isset($live_files[$full_filename])) {
             if ($live_wsd->delete_properties($full_filename) != '200') {
                $error = 'Unable to delete properties for '.$dest_filename;
                log_error($error);   http_response(422,$error);   return false;
             }
          }
          else if (isset($dev_files[$full_filename])) {
             $empty_properties = array();
             if (! isset($live_files[$full_filename]))
                $property_list = build_property_list($dev_files[$full_filename],
                                                     null,$empty_properties);
             else if (! compare_properties($dev_files[$full_filename],
                                           $live_files[$full_filename]))
                $property_list = build_property_list($dev_files[$full_filename],
                                                     $live_files[$full_filename],
                                                     $empty_properties);
             else if ($update_checkindate) {
                if ($dev_files[$full_filename]['checkindate'])
                   $property_list = 'checkindate=' .
                                    $dev_files[$full_filename]['checkindate'];
                else $empty_properties[] = 'checkindate';
             }
             else $property_list = null;
             if ($property_list) {
                if ($live_wsd->put_properties($full_filename,$property_list) != '200') {
                   $error = 'Unable to update properties for '.$dest_filename;
                   log_error($error);   http_response(422,$error);   return false;
                }
             }
             foreach ($empty_properties as $propname) {
                if ($propname == 'flags') continue;
                $status = $live_wsd->delete_property($full_filename,$propname);
                if (($status != '200') && ($status != '410') && ($status != '404')) {
                   $error = 'Unable to delete property '.$propname.' for '.$dest_filename;
                   log_error($error);   http_response(422,$error);   return false;
                }
             }
          }
       }
       if ($source_mtime && (! touch($dest_filename,$source_mtime,
                                     $source_atime))) {
          $error = 'Unable to update modify time for '.$dest_filename;
          log_error($error);   http_response(422,$error);   return false;
       }
    }
    return true;
}

function copy_table($table_name,$src_db,$dest_db)
{
    $table_fields = $src_db->get_field_defs($table_name);
    if (! $table_fields) return false;
    $query = 'truncate table  '.$table_name;
    if (! $dest_db->query($query)) return false;
    $query = 'select * from '.$table_name;
    $result = $src_db->query($query);
    if ($result) {
       while ($row = $src_db->fetch_assoc($result)) {
       $src_db->decrypt_record($table_name,$row);
          $fields = $table_fields;
          foreach ($table_fields as $field_name => $field_def) {
             if ($row[$field_name] === null) $row[$field_name] = '';
             $fields[$field_name]['value'] = $row[$field_name];
          }
          if (! $dest_db->insert($table_name,$fields)) return false;
       }
       $src_db->free_result($result);
    }
    return true;
}

function upgrade_live_database($db)
{
    $sql_lines = file('../admin/updatecartsite.sql',
                      FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if (! $sql_lines) {
       $error = 'Unable to open updatecartsite.sql';
       log_error($error);   http_response(422,$error);   return false;
    }
    $db->enable_log_errors(false);
    $inside_create = false;
    for ($index = 0;  $index < sizeof($sql_lines);  $index++) {
       $sql = $sql_lines[$index];
       if (substr($sql,0,2) == '--') continue;
       $pound_pos = strpos($sql,'#');
       if ($pound_pos !== false) {
          $quote_pos = strpos($sql,'"',$pound_pos);
          if ($quote_pos === false) $quote_pos = strpos($sql,'\'',$pound_pos);
          if ($quote_pos === false) $sql = substr($sql,0,$pound_pos);
       }
       if (substr($sql,-1) == ';') $sql = substr($sql,0,-1);
       if ($sql == '') continue;
       if ((substr($sql,0,7) == 'create ') ||
           (substr($sql,0,7) == 'CREATE ')) {
          if (substr($sql,-1) != ')') {
             $inside_create = true;   $create_sql = $sql;   continue;
          }
       }
       else if ($inside_create) {
          $create_sql .= $sql;
          if ($sql[0] == ')') {
             $inside_create = false;   $sql = $create_sql;
          }
          else continue;
       }
       $result = $db->query($sql);
       if ((! $result) && isset($db->error)) {
          $db_error = $db->error;   unset($db->error);
          if (substr($db_error,0,15) == 'Duplicate entry') continue;
          if (substr($db_error,0,21) == 'Duplicate column name') continue;
          if (substr($db_error,0,18) == 'Duplicate key name') continue;
          if (substr($db_error,-14) == 'already exists') continue;
          if (substr($db_error,0,10) == "Can't DROP") continue;
          if (substr($db_error,-24) == 'must be defined as a key') continue;
          if (substr($db_error,0,14) == 'Unknown column') continue;
          if (substr($db_error,0,15) == "Can't find file") continue;
          log_error('Query: '.$sql);
          $db->enable_log_errors(true);
          $error = 'Error updating database: '.$db_error;
          log_error($error);   http_response(422,$error);   return false;
       }
    }
    $db->enable_log_errors(true);
    return true;
}

function process_file_list($dirname,$dir_info,$dir_sep,$file_list)
{
    foreach ($dir_info['files'] as $file_info) {
       $filename = $dirname.$file_info['name'];
       if (isset($file_info['versionfile']) &&
           ($file_info['versionfile'] == '~'))
          unset($file_info['versionfile']);
       $file_list[$filename] = $file_info;
    }
    foreach ($dir_info['subdirs'] as $subdir_info) {
       $subdirname = $dirname.$subdir_info['properties']['name'].$dir_sep;
       $file_list = process_file_list($subdirname,$subdir_info,$dir_sep,
                                      $file_list);
    }
    return $file_list;
}

function sync_cms_users($dev_wsd,$live_wsd,$dev_db,&$error_msg)
{
    $dev_users = $dev_wsd->list_users();
    $live_users = $live_wsd->list_users();
    $query = 'select username,password from users';
    $admin_users = $dev_db->get_records($query,'username');
    if (! $admin_users) {
       if (isset($dev_db->error)) $error_msg = $dev_db->error;
       else $error_msg = 'No Users Found to Sync';
       return false;
    }
    $dev_db->decrypt_records('users',$admin_users);
    foreach ($dev_users as $username => $user_info) {
       if (! isset($admin_users[$username])) {
          unset($live_users[$username]);   continue;
       }
       $password = $admin_users[$username]['password'];
       if (isset($user_info['workingdir']))
          $workingdir = $user_info['workingdir'];
       else $workingdir = null;
       if (! isset($live_users[$username])) {
          if ($live_wsd->add_user($username,$password,$user_info['firstname'],
                 $user_info['lastname'],$user_info['perms'],$workingdir,null,
                 $user_info['email']) != '200') {
             $error_msg = $live_wsd->error;   return false;
          }
       }
       else {
          if ($live_wsd->update_user($username,$password,$user_info['firstname'],
                 $user_info['lastname'],$user_info['email'],$user_info['perms'],
                 null,$workingdir) != '200') {
             $error_msg = $live_wsd->error;   return false;
          }
          unset($live_users[$username]);
       }
    }
    foreach ($live_users as $username => $user_info) {
       if ($live_wsd->delete_user($username) != '200') {
          $error_msg = $live_wsd->error;   return false;
       }
    }
    return true;
}

function publish_live_site($bg_flag)
{
    global $cms_module,$cms_program,$skip_directories;
    global $docroot,$live_site_path,$live_site_path,$dev_to_live_tables;

    set_time_limit(0);
    ignore_user_abort(true);
    ini_set('memory_limit','1024M');

    if (PUBLISH_TO_CMS) {
       if (! $bg_flag) {
          $spawn_result = spawn_program('publish.php publishlive');
          if ($spawn_result != 0)
             http_response(422,'Publish Request returned '.$spawn_result);
          else http_response(202,'Submitted Publishing Request');
          return;
       }
       log_activity('Publishing Development Site Content and Data to Live Site');
       require_once $cms_module;
       putenv('DOCUMENT_ROOT');
       $dev_wsd = new WSD($cms_program,'api');
       $dev_wsd->get_options();
       if ($dev_wsd->get_server_type() == WINDOWS) $dir_sep = "\\";
       else $dir_sep = '/';
       $skip_dirs = '';
       foreach ($skip_directories as $dirname) {
          if ($skip_dirs != '') $skip_dirs .= ',';
          $skip_dirs .= $dir_sep.$dirname.$dir_sep;
       }
       $dir_info = $dev_wsd->get_directory_tree('/',null,4097,null,$skip_dirs);
       if ($dir_info === null) {
          $error = 'Unable to load dev file list: '.$dev_wsd->error;
          if (! $bg_flag) http_response(422,$error);
          log_error($error);   return;
       }
       $dev_files = process_file_list($dir_sep,$dir_info,$dir_sep,array());

       $live_cms_program = str_replace($docroot,$live_site_path,$cms_program);
       $live_wsd = new WSD($live_cms_program,'api');
       $live_wsd->get_options();
       $dir_info = $live_wsd->get_directory_tree('/',null,4097,null,$skip_dirs);
       if ($dir_info === null) {
          $error = 'Unable to load live file list: '.$dev_wsd->error;
          if (! $bg_flag) http_response(422,$error);
          log_error($error);   return;
       }
       $live_files = process_file_list($dir_sep,$dir_info,$dir_sep,array());
    }
    else {
       $dev_wsd = null;   $live_wsd = null;   $dev_files = null;   $live_files = null;
    }

    $dev_site_path = '..';
    $dir_handle = @opendir($dev_site_path);
    if ($dir_handle) {
       while (($filename = readdir($dir_handle)) !== false) {
          if (($filename == '.') || ($filename == '..')) continue;
          if (! process_file($dev_site_path,$live_site_path,'',$filename,
                             $dev_wsd,$live_wsd,$dev_files,$live_files)) {
             closedir($dir_handle);   return;
          }
       }
       closedir($dir_handle);
    }

    open_databases($dev_db,$live_db);
    if (! upgrade_live_database($live_db)) return;
    foreach ($dev_to_live_tables as $table_name) {
       if (! copy_table($table_name,$dev_db,$live_db)) {
          if (isset($dev_db->error)) $error = $dev_db->error;
          else $error = $live_db->error;
          if (! $bg_flag) http_response(422,$error);
          log_error($error);   return;
       }
    }

    if (PUBLISH_TO_CMS) {
       if (! sync_cms_users($dev_wsd,$live_wsd,$dev_db,$error_msg)) {
          if (! $bg_flag) http_response(422,$error);
          log_error($error);   return false;
       }
       if ($live_wsd->rebuildall(null,null,'No') != '200') {
          $error = 'Unable to Rebuild Web Site';
          if (! $bg_flag) http_response(422,$error);
          log_error($error);   return false;
       }
    }

    log_activity('Published Development Site Content and Data to Live Site');
    if (! $bg_flag) http_response(201,'Published Web Site');
}

function publish_structure_data()
{
    global $dev_to_live_tables;

    set_time_limit(0);
    ignore_user_abort(true);
    open_databases($dev_db,$live_db);
    foreach ($dev_to_live_tables as $table_name) {
       if (! copy_table($table_name,$dev_db,$live_db)) {
          if (isset($dev_db->error)) $error = $dev_db->error;
          else $error = $live_db->error;
          log_error($error);   http_response(422,$error);   return;
       }
    }

    log_activity('Published Development Site Structure Data to Live Site');
    http_response(201,'Published Development Site Structure Data');
}

function copy_live_data_to_dev()
{
    global $live_to_dev_tables;

    set_time_limit(0);
    ignore_user_abort(true);
    open_databases($dev_db,$live_db);
    foreach ($live_to_dev_tabls as $table_name) {
       if (! copy_table($table_name,$live_db,$dev_db)) {
          if (isset($live_db->error)) $error = $live_db->error;
          else $error = $dev_db->error;
          log_error($error);   http_response(422,$error);   return;
       }
    }
    log_activity('Copied Live Data to Development Site');
    http_response(201,'Copied Live Data');
}

function copy_live_structure_data_to_dev()
{
    global $dev_to_live_tables;

    set_time_limit(0);
    ignore_user_abort(true);
    open_databases($dev_db,$live_db);
    foreach ($dev_to_live_tables as $table_name) {
       if (! copy_table($table_name,$live_db,$dev_db)) {
          if (isset($live_db->error)) $error = $live_db->error;
          else $error = $dev_db->error;
          log_error($error);   http_response(422,$error);   return;
       }
    }
    log_activity('Copied Live Structure Data to Development Site');
    http_response(201,'Copied Live Structure Data');
}

function test_copy_wsd()
{
    global $cms_module,$cms_program,$skip_directories;
    global $docroot,$live_site_path;

    set_time_limit(0);
    ignore_user_abort(true);
    ini_set('memory_limit','1024M');
    require_once $cms_module;
    putenv('DOCUMENT_ROOT');
    $dev_wsd = new WSD($cms_program,'api');
    $dev_wsd->get_options();
    if ($dev_wsd->get_server_type() == WINDOWS) $dir_sep = "\\";
    else $dir_sep = '/';
    $skip_dirs = '';
    foreach ($skip_directories as $dirname) {
       if ($skip_dirs != '') $skip_dirs .= ',';
       $skip_dirs .= $dir_sep.$dirname.$dir_sep;
    }
    $dir_info = $dev_wsd->get_directory_tree('/',null,4097,null,$skip_dirs);
    if ($dir_info === null) {
       print 'Unable to load dev file list: '.$dev_wsd->error."<br>\n";
       return;
    }
    $dev_files = process_file_list($dir_sep,$dir_info,$dir_sep,array());
    $cms_program = str_replace($docroot,$live_site_path,$cms_program);
    $live_wsd = new WSD($cms_program,'api');
    $live_wsd->get_options();
    $dir_info = $live_wsd->get_directory_tree('/',null,4097,null,$skip_dirs);
    if ($dir_info === null) {
       print 'Unable to load live file list: '.$live_wsd->error."<br>\n";
       return;
    }
    $live_files = process_file_list($dir_sep,$dir_info,$dir_sep,array());
    foreach ($dev_files as $filename => $file_info) {
       print 'Copying '.$filename.' - ';
       if (isset($live_files[$filename])) {
          if (! compare_properties($file_info,$live_files[$filename]))
             print 'Properties = '.build_property_list($file_info,
                                                       $live_files[$filename])."<br>\n";
          else print "Properties the Same<br>\n";
          unset($live_files[$filename]);
       }
       else print "New File<br>\n";
    }
    foreach ($live_files as $filename => $file_info)
       print 'Deleting '.$filename."<br>\n";
}

if (isset($argc) && ($argc == 2) && ($argv[1] == 'publishlive')) {
   publish_live_site(true);   DB::close_all();   exit(0);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');
if ($cmd == 'publish') publish_live_site(false);
else if ($cmd == 'publishdata') publish_structure_data();
else if ($cmd == 'copydata') copy_live_data_to_dev();
else if ($cmd == 'copylivedata') copy_live_structure_data_to_dev();
else if ($cmd == 'testcopywsd') test_copy_wsd();

DB::close_all();

?>
