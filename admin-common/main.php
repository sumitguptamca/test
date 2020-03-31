<?php
/*
                 Inroads Control Panel/Shopping Cart - Main Screen

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

$platform_version = 3.0;

require_once '../engine/topscreen.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;   $catalog_site = false;
   require_once 'adminperms.php';
   require_once 'cartconfig-common.php';
}
else {
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
   $shopping_cart = false;
   require_once 'adminperms.php';
}
require_once 'maintabs.php';
require_once 'utility.php';
if (isset($db_host)) $cms_site = false;
else $cms_site = true;

function display_login_page()
{
    global $login_cookie,$company_name,$company_logo,$shopping_cart;
    global $webmail_url,$panel_label,$prefs_cookie,$admin_cookie_domain;

    if ($shopping_cart) $path_prefix = '../cartengine/';
    else $path_prefix = '';
    if (isset($panel_label)) {
       $title_label = $header_label = $panel_label;
    }
    else {
       $title_label = 'Control Panel';
       $header_label = 'Administration';
    }
    if (isset($prefs_cookie))
       $user_prefs = get_user_prefs(null,'default');
    else $user_prefs = array();

    $screen = new TopScreen(700);
    $screen->set_body_class('login');
    $screen->set_prefs($user_prefs);
    $screen->enable_ajax();
    $screen->add_script_file($path_prefix.'login.js');
    $screen->add_style_sheet($path_prefix.'login.css');
    $head_block = "<script type=\"text/javascript\">\n" .
                  "   login_cookie = '".$login_cookie."';\n";
    if (isset($admin_cookie_domain)) 
       $head_block .= "   admin_cookie_domain = '".$admin_cookie_domain."';\n";
    $head_block .= '</script>';
    $screen->add_head_line($head_block);
    $screen->set_onload_function('login_onload();');
    $screen->display_header(strip_tags($company_name).' '.$title_label,null);
    $screen->start_tab_row();
    if (isset($webmail_url))
       $screen->add_tab('webmail','WebMail',null,false,
                        "location.href='".$webmail_url."';",'75px',
                        FIRST_TAB|LAST_TAB);
    $screen->end_tab_row();
    $screen->set_body_id('login');
    $screen->set_help('login');
    $screen->start_body();
    if ($screen->skin) $screen->write("<div class=\"loginForm\">\n");
    $screen->write('<h1>'.$company_name.'<br> '.$header_label." Login </h1>\n");
    $screen->start_form('index.php','Login');
    $screen->start_field_table();
    $form_fields = get_form_fields();
    while (list($field_name,$field_value) = each($form_fields)) {
       if (($field_name == 'username') || ($field_name == 'password')) continue;
       $screen->add_hidden_field($field_name,$field_value);
    }
    $screen->add_edit_row('Username:','username','',30);
    $screen->add_password_row('Password:','password','',30);
    $screen->write("<tr><td colspan=2 align=\"center\">\n");
    $screen->add_dialog_button('Login',$path_prefix.'images/Update.png',
                               'process_login();');
    $screen->write("</td></tr>\n");
    $screen->end_field_table();
    $screen->end_form();
    if ($screen->skin) $screen->write("</div>\n");
    $screen->end_body($company_logo,$company_name);
}

function admin_logout()
{
    global $login_cookie,$prefs_cookie,$prefix,$admin_cookie_domain;

    if (isset($_COOKIE[$login_cookie])) {
       if (! isset($admin_cookie_domain)) $admin_cookie_domain = null;
       setcookie($login_cookie,'',time()-(3600 * 25),'/',$admin_cookie_domain);
       if (isset($prefs_cookie) && isset($_COOKIE[$prefs_cookie]))
          setcookie($prefs_cookie,'',time()-(3600 * 25),'/',
                    $admin_cookie_domain);
       $db = new DB;
       if (get_user_perms($user_perms,$module_perms,$custom_perms,$db)) {
          require_once '../engine/modules.php';
          call_module_event('logout',array($user_perms,$module_perms,
                                           $custom_perms));
       }
    }
    if (function_exists('custom_admin_logout')) custom_admin_logout();
    header('Location: '.$prefix.'/admin/');
}

function encrypt_database()
{
    global $encrypt_base,$encrypted_fields,$old_encrypted_fields;
    global $new_encrypted_fields;

    if (! isset($encrypt_base)) return;
    if ((! isset($encrypted_fields)) && (! isset($new_encrypted_fields)))
       return;
    if (isset($new_encrypted_fields)) $encrypted_fields = $new_encrypted_fields;
    $db = new DB;
    if (isset($old_encrypted_fields)) {
       while (list($table_name,$fields) = each($old_encrypted_fields)) {
          foreach ($fields as $field_name) {
             if (isset($new_encrypted_fields[$table_name]) &&
                 in_array($field_name,$new_encrypted_fields[$table_name]))
                continue;
             $query = 'update '.$table_name.' set '.$field_name.'=%DECRYPT%(' .
                      $field_name.')';
             if (! $db->query($query)) {
                print 'Query: '.$query."<br>\n";
                print 'Database Error: '.$db->error."<br>\n";   return;
             }
             print 'Decrypted '.$table_name.'.'.$field_name."<br>\n";
          }
       }
    }
    while (list($table_name,$fields) = each($encrypted_fields)) {
       foreach ($fields as $field_name) {
          if (isset($old_encrypted_fields[$table_name]) &&
              in_array($field_name,$old_encrypted_fields[$table_name]))
             continue;
          $query = 'update '.$table_name.' set '.$field_name.'=%ENCRYPT%(' .
                   $field_name.')';
          if (! $db->query($query)) {
             print 'Query: '.$query."<br>\n";
             print 'Database Error: '.$db->error."<br>\n";   return;
          }
          print 'Encrypted '.$table_name.'.'.$field_name."<br>\n";
       }
    }
    print "Database Encryption Complete<br>\n";
}

function update_database()
{
    global $company_name,$company_logo,$shopping_cart,$prefs_cookie;

    if (isset($prefs_cookie)) $user_prefs = get_user_prefs();
    else $user_prefs = array();
    if ($shopping_cart) $path_prefix = '../cartengine/';
    else $path_prefix = '';
    $screen = new TopScreen(1000);
    $screen->set_prefs($user_prefs);
    $screen->enable_ajax();
    $screen->add_script_file($path_prefix.'admin.js');
    $screen->add_style_sheet($path_prefix.'login.css');
    $screen->set_onload_function('updatedb_onload();');
    $screen->display_header(strip_tags($company_name).' Control Panel',null);
    $screen->start_tab_row();
    $screen->end_tab_row();
    $screen->set_body_id('update_database');
    $screen->set_help('update_database');
    $screen->start_body();
    if ($screen->skin) $screen->write("<div class=\"loginForm\">\n");
    $screen->write('<h1>'.$company_name."<br>Update Database</h1>\n");
    $screen->start_form('index.php','UpdateDB');
    $screen->start_field_table();
    $screen->write("<tr valign=\"top\"><td class=\"fieldprompt\" style=\"text-align: left;\">" .
                   "SQL Queries:<br>\n");
    $screen->start_textarea_field('Query',25,110,WRAP_OFF);
    $screen->end_textarea_field();
    $screen->write("</td></tr>\n");
    $screen->write("<tr><td colspan=2 align=\"center\">\n");
    $screen->add_dialog_button('Submit',$path_prefix.'images/Update.png',
                               'update_database();');
    $screen->write("</td></tr>\n");
    $screen->end_field_table();
    $screen->end_form();
    if ($screen->skin) $screen->write("</div>\n");
    $screen->end_body($company_logo,$company_name);
}

function process_update_database()
{
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
          if ($comment_pos !== false) $query = substr($query,0,$comment_pos);
          $create_query .= $query;
          if ((substr($query,-2) == "\\g") || (substr($query,-1) == ';')) {
             $query = $create_query;   $inside_create = false;
          }
          else continue;
       }
       if (substr($query,-2) == "\\g") $query = substr($query,0,-2);
       else if (substr($query,-1) == ';') $query = substr($query,0,-1);
       if (substr($query,0,1) == '#') continue;
       if ($query == '') continue;
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    log_activity('Updated Database');
    http_response(201,'Database Updated');
}

function set_db_charset()
{
    global $db_charset,$db_name;

    $db = new DB;
    if (! isset($db_charset)) $db_charset = 'latin1';
    if ($db_charset == 'utf8') $char_collation = 'utf8_general_ci';
    else $char_collation = 'latin1_swedish_ci';

    $db_tables = $db->list_db_tables();
    foreach ($db_tables as $table_name) {
       $query = 'ALTER TABLE '.$db->escape($table_name) .
                ' CONVERT TO CHARACTER SET '.$db_charset.' COLLATE '.$char_collation; 
       if (! $db->query($query)) print $db->error."<br>\n";
       else print 'Set table '.$table_name.' to '.$db_charset."<br>\n";
    }
    $query = 'ALTER DATABASE CHARACTER SET '.$db_charset;
    if (! $db->query($query)) print $db->error."<br>\n";
    else print 'Set database '.$db_name.' to '.$db_charset."<br>\n";
    $db->close();
}

function display_about()
{
    global $company_name,$panel_label,$platform_version,$no_inroads;
    global $admin_copyright,$siteid;

    require_once '../engine/dialog.php';

    if (isset($panel_label)) $title_label = $panel_label;
    else $title_label = 'Control Panel';
    $about_title = strip_tags($company_name).' '.$title_label;
    if (! isset($admin_copyright))
       $admin_copyright = 'Copyright 2019 Inroads, LLC. All Rights Reserved';
    if (! isset($no_inroads)) $no_inroads = false;

    $dialog = new Dialog;
    $dialog->add_style_sheet('main.css');
    $dialog->add_script_file('main.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('about');
    $dialog->set_help('about');
    $dialog->start_body($about_title);
    $dialog->start_content_area(true);
    $dialog->write('<div class="about_header_div">'.$about_title .
                   "</div>\n");
    $dialog->write('<div class="about_platform_div">Powered by Inroads ' .
                   'AxiumPro version '.number_format($platform_version,1) .
                   "</div>\n");
    if (! empty($siteid)) {
       $url = 'https://www.inroads.us/admin/support.php?cmd=getsitestatus&' .
              'siteid='.$siteid.'&format=html';
       $site_info = file_get_contents($url);
       $dialog->write('<div class="site_info_div">'.$site_info."</div>\n");
    }
    $dialog->write('<div class="about_footer_div">'.$admin_copyright .
                   "</div>\n");
    $dialog->end_content_area(true);

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr>");
       $dialog->write("  <td style=\"padding-right: 10px;\">\n");
    }
    $dialog->add_dialog_button('Credits','images/Update.png',
                               'display_credits(); return false;');
    if (! $dialog->skin)
       $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    $dialog->add_dialog_button('Close','images/Update.png',
                               'top.close_current_dialog();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr></table>\n");
    $dialog->end_body();
}

function sort_module_info($a,$b)
{
    $retval = strcasecmp($a['productname'],$b['productname']);
    if ($retval != 0) return $retval;
    return 1;
}

function display_credits()
{
    global $prefix,$cms_support_url;

    require_once '../engine/dialog.php';
    require_once '../engine/modules.php';

    $modules = array();

    $support_url = $cms_support_url;
    if ($prefix) {
       $length = strlen($prefix);
       if ($length && (substr($support_url,0,$length) == $prefix))
          $support_url = substr($support_url,$length);
    }
    $script = file_get_contents('..'.$support_url.'/ckeditor/ckeditor.js');
    if (! $script) $version = 'Unknown';
    else {
       $start_pos = strpos($script,',version:"');
       $end_pos = strpos($script,'",revision:');
       if (($start_pos === false) || ($end_pos === false))
          $version = 'Unknown';
       else $version = substr($script,$start_pos + 10,
                              $end_pos - $start_pos - 10);
    }
    $modules[] = array('productname'=>'CKEditor','version'=>$version,
       'homepage'=>'http://ckeditor.com/',
       'license'=>'..'.$support_url.'/ckeditor/LICENSE.md',
       'licenseformat'=>'text','credits'=>true);

    define('PHPEXCEL_ROOT','.');
    require_once '../engine/PHPExcel/Calculation/Functions.php';
    $version = PHPExcel_Calculation_Functions::VERSION();
    $start_pos = strpos($version,' ');
    $end_pos = strpos($version,',');
    if (($start_pos === false) || ($end_pos === false)) $version = 'Unknown';
    else $version = substr($version,$start_pos + 1,$end_pos - $start_pos - 1);
    $modules[] = array('productname'=>'PHPExcel','version'=>$version,
       'homepage'=>'https://github.com/PHPOffice/PHPExcel',
       'license'=>'../engine/PHPExcel/license.txt','licenseformat'=>'text',
       'credits'=>true);

    call_module_event('module_info',array(&$modules));
    usort($modules,'sort_module_info');

    $dialog = new Dialog;
    $dialog->add_style_sheet('main.css');
    $dialog->add_script_file('main.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('credits');
    $dialog->set_help('credits');
    $dialog->start_body('Credits');

    $dialog->start_content_area(true);
    $dialog->start_field_table('fieldtable','fieldtable',6,0);
    $dialog->write('<tr><th align="left">Product Name</th>' .
                  '<th>Installed Version</th><th>Home Page</th>' .
                  "<th>License</th></tr>\n");
    foreach ($modules as $module) {
       if ((! isset($module['credits'])) || (! $module['credits'])) continue;
       $dialog->write('<tr><td class="product_name" nowrap>');
       if (isset($module['productname']))
          $dialog->write($module['productname']);
       $dialog->write("</td>\n<td class=\"installed_version\">");
       if (isset($module['version'])) $dialog->write($module['version']);
       $dialog->write("</td>\n<td class=\"home_page\">");
       if (isset($module['homepage'])) {
          $function = "window.open('".$module['homepage']."');";
          $dialog->add_dialog_button('Open','images/Update.png',$function);
       }
       $dialog->write("</td>\n<td class=\"license\">");
       if (isset($module['license'])) {
          $function = "display_license('".$module['productname']."','" .
                      $module['license'];
          if (isset($module['licenseformat']))
             $function .= "','".$module['licenseformat'];
          else $function .= "','html";
          $function .= "');";
          $dialog->add_dialog_button('View','images/Update.png',$function);
       }
       $dialog->write("</td></tr>\n");
    }
    $dialog->end_field_table();
    $dialog->end_content_area(true);

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" " .
                      "border=\"0\"><tr>");
       $dialog->write("  <td style=\"padding-right: 10px;\">\n");
    }
    if (! $dialog->skin)
       $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    $dialog->add_dialog_button('Close','images/Update.png',
                               'top.close_current_dialog();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr></table>\n");
    $dialog->end_body();
}

function display_license()
{
    $url = get_form_field('url');
    $format = get_form_field('format');
    $content = file_get_contents($url);
    if ($format == 'text') {
       print "<style type=\"text/css\">\n";
       print "  pre {\n";
       print "    white-space: pre-wrap;\n";
       print "    white-space: -moz-pre-wrap;\n";
       print "    white-space: -pre-wrap;\n";
       print "    white-space: -o-pre-wrap;\n";
       print "    word-wrap: break-word;\n";
       print "  }\n";
       print "</style>\n";
       print "<pre>\n";
    }
    else if ($format == 'html') {
       if (strpos($content,'<base href=') === false)
          print "<base href=\"".$url."\">\n";
    }
    print $content;
    if ($format == 'text') print '</pre>';
}

function check_status()
{
    error_reporting(0); 
    ini_set('display_errors',false);
    ini_set('track_errors',false);

    $test = substr(str_shuffle(str_repeat(
       $x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
       ceil(255/strlen($x)))),1,255);
    $db = new DB;
    $query = 'select * from config where config_name="statuscheck"';
    $row = $db->get_record($query);
    if ((! $row) && isset($db->error)) {
       http_status(422,$db->error);   return;
    }
    if ($row)
       $query = 'update config set config_value=? where ' .
                'config_name="statuscheck"';
    else $query = 'insert into config values("statuscheck",?)';
    $query = $db->prepare_query($query,$test);
    if (! $db->query($query)) {
       http_status(422,$db->error);   return;
    }
    $query = 'select * from config where config_name="statuscheck"';
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_status(422,$db->error);
       else http_status(422,'Database Record Not Found');   return;
       return;
    }
    $query = 'delete from config where config_name="statuscheck"';
    if (! $db->query($query)) {
       http_status(422,$db->error);   return;
    }
    if ($row['config_value'] != $test) {
       http_status(409,'Database Record Corrupted');   return;
    }

    $filename = 'statuscheck.txt';
    if (! file_put_contents($filename,$test)) {
       http_status(406,'Unable to write file contents');   return;
    }
    $compare = file_get_contents($filename);
    if (! unlink($filename)) {
       http_status(417,'Unable to delete file');   return;
    }
    if (! $compare) {
       http_status(410,'Unable to read file contents');   return;
    }
    if ($compare != $test) {
       http_status(409,'File Contents Corrupted');   return;
    }

    http_status(200,'Web Site Operational');
}

function get_ini_flag($flag_name)
{
    $flag = @ini_get($flag_name);
    if ($flag === false) return $flag;
    if ((strtolower($flag) == 'on') || (strtolower($flag) == 'yes') ||
        (strtolower($flag) == 'true') || ($flag == 1)) $flag = 'On';
    else if ((strtolower($flag) == 'off') || (strtolower($flag) == 'no') ||
        (strtolower($flag) == 'false') || ($flag == 0)) $flag = 'Off';
    return $flag;
}

define('COMPARE_NONE',0);
define('COMPARE_VALUE',1);
define('COMPARE_INT',2);
define('COMPARE_TRUE',3);
define('COMPARE_FALSE',4);

function add_check_row($screen,$setting,$recommended,$actual,
                       $compare=COMPARE_VALUE)
{
    switch ($compare) {
       case COMPARE_VALUE:
          if ($recommended == $actual) $color = '#008800';
          else $color = '#DD0000';
          break;
       case COMPARE_INT:
          if (intval($actual) >= intval($recommended)) $color = '#008800';
          else $color = '#DD0000';
          break;
       case COMPARE_TRUE:
          $color = '#008800';   break;
       case COMPARE_FALSE:
          $color = '#DD0000';   break;
       default:
          $color = null;
    }
    $screen->write('<tr><td>'.$setting.'</td><td align="center">' .
       $recommended.'</td><td align="center"');
    if ($color) $screen->write(' style="color:'.$color.';"');
    $screen->write('>'.$actual.'</td></tr>'."\n");
}

function check_php()
{
    global $company_name,$company_logo,$shopping_cart,$prefs_cookie;

    if (isset($prefs_cookie)) $user_prefs = get_user_prefs();
    else $user_prefs = array();
    if ($shopping_cart) $path_prefix = '../cartengine/';
    else $path_prefix = '';
    $screen = new TopScreen(1000);
    $screen->set_prefs($user_prefs);
    $screen->enable_ajax();
    $screen->add_script_file($path_prefix.'admin.js');
    $screen->add_style_sheet($path_prefix.'login.css');
    $screen->add_style_sheet($path_prefix.'main.css');
    $screen->set_onload_function('checkphp_onload();');
    $screen->display_header(strip_tags($company_name).' Control Panel',null);
    $screen->start_tab_row();
    $screen->end_tab_row();
    $screen->set_body_id('check_php');
    $screen->set_help('check_php');
    $screen->start_body();
    if ($screen->skin) $screen->write("<div class=\"loginForm\">\n");
    $screen->write('<h1>'.$company_name."<br>PHP Configuration Check</h1>\n");
    $screen->start_field_table('fieldtable','fieldtable checkphp',6.0);
    $screen->write('<tr><th align="left">Configuration Setting</th>' .
                   '<th>Recommended Value</th><th>Actual Value</th></tr>'."\n");

    $safe_mode = get_ini_flag('safe_mode');
    if ($safe_mode !== false)
       add_check_row($screen,'Safe Mode','Off',$safe_mode);
    $short_open_tag = get_ini_flag('short_open_tag');
    add_check_row($screen,'Short Open Tag','On',$short_open_tag);
    $display_errors = get_ini_flag('display_errors');
    add_check_row($screen,'Display Errors','On',$display_errors);
    $register_argc_argv = get_ini_flag('register_argc_argv');
    add_check_row($screen,'Register Argc/Argv','On',$register_argc_argv);
    $suhosin_encrypt = get_ini_flag('suhosin.cookie.encrypt');
    if ($suhosin_encrypt !== false)
       add_check_row($screen,'Suhosin Cookie Encrypt','Off',$suhosin_encrypt);
    $memory_limit = @ini_get('memory_limit');
    add_check_row($screen,'Memory Limit','512M',$memory_limit,COMPARE_INT);
    $post_max_size = @ini_get('post_max_size');
    add_check_row($screen,'Post Max Size','256M',$post_max_size,COMPARE_INT);
    $upload_max_filesize = @ini_get('upload_max_filesize');
    add_check_row($screen,'Upload Max Filesize','256M',$upload_max_filesize,
                  COMPARE_INT);
    $always_populate = @ini_get('always_populate_raw_post_data');
    add_check_row($screen,'Always Populate Raw Post Data','-1',
                  $always_populate);
    $disabled = explode(',',@ini_get('disable_functions'));
    foreach ($disabled as $index => $function)
       $disabled[$index] = trim($function);
    $exec_pos = array_search('exec',$disabled);
    $shell_exec_pos = array_search('shell_exec',$disabled);
    $recommended = $disabled;
    if ($exec_pos !== false) unset($recommended[$exec_pos]);
    if ($shell_exec_pos !== false) unset($recommended[$shell_exec_pos]);
    add_check_row($screen,'Disabled Functions',implode(',',$recommended),
                  implode(',',$disabled));
    $extensions = array('date','ftp','gd','gettext','imagick','json',
                        'mbstring','mysql','mysqli','soap','zip');
    foreach ($extensions as $extension) {
       if (extension_loaded($extension)) $installed = 'Installed';
       else $installed = 'Not Installed';
       add_check_row($screen,$extension.' Extension','Installed',$installed);
    }

    $screen->end_field_table();
    if ($screen->skin) $screen->write("</div>\n");
    $screen->end_body($company_logo,$company_name);
}

function build_header_content($screen,$db,$start_tab_label)
{
    global $login_cookie,$enable_multisite,$website_cookie,$prefs_cookie;
    global $user_prefs_name_link,$screen_title_in_masthead;

    ob_start();

    if (! isset($enable_multisite)) $enable_multisite = false;
    $screen->write("\n");
    $admin_user = get_cookie($login_cookie);
    if ($admin_user) {
       $full_name = get_user_name($db,$admin_user);
       if ($full_name) {
          if ($screen->skin) {
             if (! isset($user_prefs_name_link)) $user_prefs_name_link = false;
             if (isset($screen_title_in_masthead) && $screen_title_in_masthead)
                $screen->write('   <div class="screen_title" ' .
                               'id="screen_title">'.$start_tab_label .
                               '</div>'."\n");
             $screen->write('   <div class="layout_icon" id="layout_icon">');
             if ($screen->layout == 'maximized') {
                $img = 'collapse.png';   $title = 'Switch to Normal Layout';
             }
             else {
                $img = 'expand.png';   $title = 'Switch to Maximized Layout';
             }
             if (file_exists('../admin/skins/'.$screen->skin.'/images/'.$img))
                $img = '../admin/skins/'.$screen->skin.'/images/'.$img;
             else $img = '../engine/images/'.$img;
             $screen->write('<img id="layout_icon" src="'.$img .
                            '" title="'.$title.'" onClick="switch_layout();">');
             $screen->write("</div>\n");
?>
   <div class="quickLinks<? if ($enable_multisite) print ' multisiteQuickLinks'; ?>" id="quickLinks">
    <div class="welcome">
<? if ($user_prefs_name_link) { ?>
      <div class="name"><a class="tooltips" href="#" data-label="User Preferences" onClick="return user_preferences();"><?
   print $full_name; ?><span>User Preferences</span></a></div>
<? } else { ?>
      <p>Welcome, <span class="name"><? print $full_name; ?></span></p>
<? } ?>
    </div>
      <div class="miniNav">
<? if ($user_prefs_name_link) { ?>
         <a class="tooltips log_out_icon" href="#" data-label="Logout" onClick="return logout();"><span>Logout</span></a>
<? } else { ?>
         <ul>
            <li class="first"><a class="tooltips user_preference_icon" href="#" data-label="User Preferences" onClick="return user_preferences();"><span>User Preferences</span></a></li>
            <li><a class="tooltips log_out_icon" href="#" data-label="Logout" onClick="return logout();"><span>Logout</span></a></li>
         </ul>
<? } ?>
         <div class="clear"><!-- --></div>
      </div>
      <div class="clear"><!-- --></div>
   </div>
<?
          } else {
             if (isset($prefs_cookie))
                $screen->write("        <div class=\"welcome_div\">Welcome " .
                               "<span class=\"welcome_name\">".$full_name .
                               "</span></div>\n");
             $screen->write("        <div class=\"top_links\">");
             if (isset($prefs_cookie))
                $screen->write("<a href=\"#\" data-label=\"User Preferences\" onClick=\"return " .
                               "user_preferences();\">User Preferences</a>\n" .
                               "         |&nbsp;");
             $screen->write("<a href=\"#\" data-label=\"Logout\" " .
                            "onClick=\"return logout();\">Logout</a></div>\n");
          }
       }
    }

    if ($enable_multisite) {
       if (isset($_COOKIE[$website_cookie]))
          $website = $_COOKIE[$website_cookie];
       else $website = 0;
       if ($website == '') $website = 0;
       $query = 'select * from web_sites order by name,domain';
       $web_sites = $db->get_records($query);
       if ($web_sites) {
          $screen->write('        <script>'."\n");
          foreach ($web_sites as $row)
             $screen->write('          website_hostnames['.$row['id'] .
                            '] = \''.$row['domain'].'\';'."\n");
          $screen->write('        </script>'."\n");
          $screen->write('        <div class="');
          if ($screen->skin) $screen->write('selectWebSite');
          else $screen->write('website_div');
          $screen->write("\">Web Site:\n          ");
          $screen->start_choicelist('website','select_website(this);');
          $screen->write('            ');
          $screen->add_list_item(0,'All',$website == 0);
          foreach ($web_sites as $row) {
             $screen->write('            ');
             if ($row['name']) $name = $row['name'];
             else $name = $row['domain'];
             @UI::add_list_item($row['id'],$row['name'],
                                $website == $row['id']);
          }
          $screen->write('          ');
          $screen->end_choicelist();
          $screen->write("        </div>\n");
       }
    }

    $screen->write('      ');

    $end_row_content = ob_get_contents();
    ob_end_clean();
    return $end_row_content;
}

function display_main_page()
{
    global $shopping_cart,$catalog_site,$company_name,$company_logo;
    global $panel_label,$login_cookie,$enable_multisite,$website_cookie;
    global $prefs_cookie,$event_names,$cms_base_url;
    global $admin_cookie_domain,$cart_config_tabs,$cms_cookie,$admin_base_url;
    global $websocket_port,$prefix;

    if (get_form_field('logstartup')) $log_startup = true;
    else $log_startup = false;
    if ($shopping_cart) $path_prefix = '../cartengine/';
    else $path_prefix = '';
    if (! isset($panel_label)) $panel_label = 'Control Panel';

    $db = new DB;
    if (! get_user_perms($user_perms,$module_perms,$custom_perms,$db)) {
       if ($log_startup) log_activity('No Permissions, loading login page');
       display_login_page();   return;
    }
    if (isset($websocket_port)) {
       if ($log_startup) log_activity('Checking Daemon');
       require_once '../engine/websocket.php';
       if (! check_daemon($error)) $websocket_port = null;
    }
    else $websocket_port = null;
    require_once '../engine/modules.php';
    if ($log_startup) log_activity('Initializing Modules');
    initialize_modules($event_names);
    if ($log_startup) log_activity('Setting Admin Cookies');
    set_admin_cookies($user_perms,$module_perms,$custom_perms);
    $skin = '';
    if (isset($prefs_cookie)) {
       $user_prefs = get_user_prefs($db);
       $prefs_string = '';
       while (list($pref_name,$pref_value) = each($user_prefs)) {
          if ($prefs_string != '') $prefs_string .= '|';
          $prefs_string .= $pref_name.'|'.$pref_value;
       }
       if (! isset($admin_cookie_domain)) $admin_cookie_domain = null;
       setcookie($prefs_cookie,$prefs_string,time() + 86400,'/',
                 $admin_cookie_domain);
       if (isset($user_prefs['skin'])) {
          $skin = $user_prefs['skin'];   load_skin_config($skin);
       }
    }
    else $user_prefs = array();

    if ($log_startup) log_activity('Calling startup event');
    call_module_event('startup',array($user_perms,$module_perms,$custom_perms,
                                      $user_prefs));
    if ($log_startup) log_activity('Loading Main Tabs');
    load_main_tabs($db,$user_perms,$module_perms,$custom_perms,$user_prefs,
                   true);
    if ($log_startup) log_activity('Setting Up Main Tabs');
    setup_main_tabs($start_tab,$start_tab_label,$start_top_tab,$initial_cover,
                    $user_perms,$module_perms,$custom_perms);
    if ($shopping_cart) {
       if ($log_startup) log_activity('Getting Cart Config Dialog Width');
       call_module_event('add_cart_config_tabs',array(&$cart_config_tabs,$db));
       $cartconfig_dialog_width = 222;
       foreach ($cart_config_tabs as $tab_label)
          $cartconfig_dialog_width += (8 * strlen($tab_label)) + 32;
    }

    if (isset($prefs_cookie)) {
       $saved_prefs_cookie = $prefs_cookie;   unset($GLOBALS['prefs_cookie']);
    }
    else $saved_prefs_cookie = null;
    if ($log_startup) log_activity('Creating Top Screen');
    $screen = new TopScreen();
    if ($saved_prefs_cookie) $GLOBALS['prefs_cookie'] = $saved_prefs_cookie;
    $screen->set_prefs($user_prefs);
    $screen->add_script_file($path_prefix.'main.js');
    if ($websocket_port) {
       $screen->add_script_file($path_prefix.'websocket.js');
       $screen->add_script_file('../engine/Talisman/phonetics/double-metaphone.js');
       $screen->add_script_file('../engine/Talisman/metrics/distance/damerau-levenshtein.js');
    }
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $screen->add_style_sheet($path_prefix.'main.css');
    $screen->add_style_sheet('../engine/font-awesome/css/font-awesome.min.css');
    $head_block = "<script type=\"text/javascript\">\n" .
                  "      login_cookie = '".$login_cookie."';\n" .
                  "      buttons = '".$screen->buttons."';\n";
    if (isset($prefs_cookie))
       $head_block .= "      prefs_cookie = '".$prefs_cookie."';\n";
    if (isset($cms_cookie))
       $head_block .= "      cms_cookie = '".$cms_cookie."';\n";
    if (isset($admin_cookie_domain)) 
       $head_block .= "      admin_cookie_domain = '".$admin_cookie_domain."';\n";
    if (isset($cms_base_url))
       $head_block .= "      cms_base_url = '".$cms_base_url."';\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix = '../cartengine/';\n";
    if (isset($admin_base_url)) {
       if ($shopping_cart)
          $head_block .= "      admin_base_url = '".$admin_base_url .
                         "cartengine/';\n";
       else $head_block .= "      admin_base_url = '".$admin_base_url .
                           "admin/';\n";
    }
    if (isset($prefs_cookie)) {
       $head_block .= '      sysconfig_dialog_width = ';
       if ($shopping_cart) $head_block .= '580';
       else if ($catalog_site) $head_block .= '750';
       else $head_block .= '650';
       $head_block .= ";\n";
       $head_block .= '      sysconfig_dialog_height = ';
       if ($shopping_cart) $head_block .= '430';
       else if ($catalog_site) $head_block .= '480';
       else $head_block .= '480';
       $head_block .= ";\n";
    }
    if ($shopping_cart)
       $head_block .= '      cartconfig_dialog_width = ' .
                      $cartconfig_dialog_width.";\n";
    if (isset($enable_multisite) && $enable_multisite) {
       $head_block .= "      website_cookie = '".$website_cookie."';\n" .
                      "      website = ";
       if (isset($_COOKIE[$website_cookie]))
          $head_block .= $_COOKIE[$website_cookie];
       else $head_block .= '0';
       $head_block .= ";\n";
    }
    if (isset($user_prefs['notify']) && ($user_prefs['notify'] == 'yes'))
       $head_block .= "      notifications_enabled = true;\n";
    $head_block .= "      notification_icon = '".$prefix.'/admin/skins/';
    $logo_filename = '../admin/skins/';
    if ($skin) {
       $head_block .= $skin;   $logo_filename .= $skin;
    }
    else {
       $head_block .= 'Default';   $logo_filename .= 'Default';
    }
    $logo_filename .= '/images/notify-logo.png';
    if (file_exists($logo_filename)) $head_block .= "/images/notify-logo.png';\n";
    else $head_block .= "/images/logo.png';\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
    if ($websocket_port)
       $screen->set_onload_function('init_websocket('.$websocket_port.');');
    if (function_exists('setup_main_page')) setup_main_page($screen,$db);
    $screen->display_header(strip_tags($company_name).' '.$panel_label,
                            $start_tab,null,$initial_cover,$start_top_tab);
    if ($screen->skin)
       $screen->start_tab_row(build_header_content($screen,$db,
                                                   $start_tab_label));
    else $screen->start_tab_row();
    process_main_tabs($screen);
    if ($screen->skin) $screen->end_tab_row();
    else $screen->end_tab_row(build_header_content($screen,$db,
                              $start_tab_label));
    $screen->set_body_id('main');
    $screen->set_help('main');
    if (isset($user_prefs['preload']) && ($user_prefs['preload'] == 'yes')) {
       $preload_screens = true;
       if (! file_exists('preloadtabs.txt')) $preload_tabs = null;
       else {
          $preload_tabs = file_get_contents('preloadtabs.txt');
          $preload_tabs = explode("\n",$preload_tabs);
       }
    }
    else {
       $preload_screens = false;   $preload_tabs = null;
    }
    $screen->start_body($start_tab,$preload_screens,$preload_tabs);
    $screen->end_body($company_logo,$company_name);
    if ($log_startup) log_activity('Finished Displaying Top Screen');
}

if (isset($argc) && ($argc == 2)) {
   if ($argv[1] == 'encryptdb') {
      encrypt_database();   DB::close_all();   exit(0);
   }
   if ($argv[1] == 'initmodules') {
      require_once '../engine/modules.php';
      initialize_modules($event_names);
      exit(0);
   }
}

$cmd = get_form_field('cmd');
if ($cmd == 'validateuser') validate_admin_user();
else if ($cmd == 'logout') admin_logout();
else if ($cmd == 'status') check_status();
else {
   if (! check_login_cookie(null)) display_login_page();
   else if ($cmd == 'phpinfo') phpinfo();
   else if ($cmd == 'encryptdb') encrypt_database();
   else if ($cmd == 'updatedb') update_database();
   else if ($cmd == 'processupdatedb') process_update_database();
   else if ($cmd == 'setdbcharset') set_db_charset();
   else if ($cmd == 'about') display_about();
   else if ($cmd == 'credits') display_credits();
   else if ($cmd == 'license') display_license();
   else if ($cmd == 'checkphp') check_php();
   else if (function_exists('process_main_command') &&
            process_main_command($cmd)) {}
   else display_main_page();
}

DB::close_all();

?>
