<?php
/*
                Inroads Control Panel/Shopping Cart - Utility Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

define('WEBSITE_SHARED_CART',1);
define('WEBSITE_SEPARATE_CMS',2);
define('WEBSITE_SEPARATE_PAYMENT',4);

if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/importdata.php')) $shopping_cart = true;
   else $shopping_cart = false;
}

function add_encrypted_fields($screen,$table)
{
    global $encrypt_base,$encrypted_fields;

    if (isset($encrypt_base) && isset($encrypted_fields) &&
        isset($encrypted_fields[$table])) {
       $head_block = "<script type=\"text/javascript\">var encrypted_fields = [";
       $first_field = true;
       foreach ($encrypted_fields[$table] as $field_name) {
          if ($first_field) $first_field = false;
          else $head_block .= ',';
          $head_block .= "'".$field_name."'";
       }
       $head_block .= '];</script>';
       $screen->add_head_line($head_block);
    }
}

function add_search_box($screen,$search_function,$reset_function,
                        $search_id=null,$show=true)
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    if ($screen->skin) {
       $screen->write("  <div class=\"search_div\"");
       if ($search_id) $screen->write(" id=\"".$search_id."_row\"");
       if (! $show) $screen->write(" style=\"display:none;\"");
       $screen->write(">\n");
    }
    else if ($screen->buttons == 'left') {
       $screen->write('            <tr');
       if ($search_id) $screen->write(" id=\"".$search_id."_sep_row\"");
       if (! $show) $screen->write(" style=\"display:none;\"");
       $screen->write(" height=\"20\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("            <tr");
       if ($search_id) $screen->write(" id=\"".$search_id."_row\"");
       if (! $show) $screen->write(" style=\"display:none;\"");
       $screen->write("><td colspan=2 class=\"search_cell\"><div class=\"search_div\">\n");
    }
    else {
       $screen->write("            <td class=\"small_search_cell\"");
       if ($search_id) $screen->write(" id=\"".$search_id."_row\"");
       if (! $show) $screen->write(" style=\"display:none;\"");
       $screen->write("><div class=\"small_search_div\">\n");
    }
    $screen->write('              ');
    if ($search_id) $form_name = $search_id;
    else $form_name = 'SearchForm';
    $screen->start_form('index.php',$form_name);
    if (! $screen->skin) {
       $screen->write("              <img src=\"");
       $screen->write($prefix.'images/search.gif');
       $screen->write("\" onClick=\"");
       $screen->write($search_function."();\" class=\"");
       $screen->write("search_button\">\n");
    }
    $screen->write("              <input type=text class=\"");
    $screen->write("search_input\" name=\"query\" value=\"\"\n");
    $screen->write("               onkeypress=\"return on_enter(event,function() {" .
                   $search_function."();});\">");
    if ($screen->skin)
       $screen->write("<span class=\"search_button\" onclick=\"" .
                      $search_function."();\"><span>Search</span></span>\n");
    $screen->write("\n              <a href=\"#\" class=\"");
    $screen->write("search_reset\" onClick=\"");
    $screen->write($reset_function."();\">Reset</a>\n");
    $screen->write('              ');
    $screen->end_form();
    if ($screen->skin) $screen->write("  </div>\n");
    else {
       $screen->write("            </div></td>");
       if ($screen->buttons == 'left') $screen->write("</tr>\n");
    }
}

function add_small_search_box($screen,$search_field,$search_function,$reset_function)
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $screen->write("            <div class=\"small_search_div\">\n");
    if ($screen->skin)
       $screen->write("              <span class=\"search_button\" onclick=\"" .
                      $search_function."();\"><span>Search</span></span>\n");
    else {
       $screen->write("              <img src=\"");
       $screen->write($prefix.'images/search.gif');
       $screen->write("\" onClick=\"");
       $screen->write($search_function."();\" class=\"small_search_button\">\n");
    }
    $screen->write("              <input type=text class=\"small_search_input\" name=\"");
    $screen->write($search_field);
    $screen->write("\" value=\"\"\n");
    $screen->write("               onkeypress=\"return on_enter(event,function() {".$search_function."();});\">\n");
    $screen->write("              <a href=\"#\" class=\"small_search_reset\" onClick=\"");
    $screen->write($reset_function."();\">Reset</a>\n");
    $screen->write("            </div>\n");
}

function add_base_href(&$dialog,$base_href,$inline_flag)
{
    $base_line = "<base href=\"".$base_href."\">";
    if ($inline_flag) {
       $dialog->write($base_line."\n");
       $dialog->write("<script>document.getElementsByTagName(\"base\")[0].href=\"");
       $dialog->write($base_href);
       $dialog->write("\";</script>\n");
    }
    else $dialog->add_head_line($base_line);
}

function get_product_screen_height($db = null)
{
    global $product_fields;
    global $features;
    global $desc_field_type;
    global $include_product_downloads;
    global $minimum_product_dialog_height;

    if (! isset($product_fields)) $product_fields = array();
    $dialog_height = 320;
    foreach ($product_fields as $field_name => $field) {
       if (isset($field['fieldtype'])) switch ($field['fieldtype']) {
          case EDIT_FIELD: $dialog_height += 31;   break;
          case TEXTAREA_FIELD: $dialog_height += ($field['height'] * 15) + 17;
                               break;
          case HTMLEDIT_FIELD: $dialog_height += $field['height'] + 17;
                               break;
       }
    }
    if (! isset($desc_field_type)) $desc_field_type = HTMLEDIT_FIELD;
    switch ($desc_field_type) {
       case EDIT_FIELD: $dialog_height += 31;   break;
       case TEXTAREA_FIELD: $dialog_height += 77;   break;
       case HTMLEDIT_FIELD: $dialog_height += 77;   break;
    }
    if ($features & LIST_PRICE_PRODUCT) $dialog_height += 31;
    if ($features & REGULAR_PRICE_PRODUCT) $dialog_height += 31;
    if ($features & SALE_PRICE_PRODUCT) $dialog_height += 31;
    if ($features & PRODUCT_COST_PRODUCT) $dialog_height += 31;
    if (isset($include_product_downloads) && $include_product_downloads)
       $dialog_height += 110;
    if (! isset($db)) $db = new DB;
    $query = 'select config_value from config where config_name=' .
             '"image_size_medium"';
    $row = $db->get_record($query);
    if ($row) {
       $size_info = explode("|",$row['config_value']);
       $new_height = $size_info[1] + 250;
       if ($new_height > $dialog_height) $dialog_height = $new_height;
    }
    if (isset($minimum_product_dialog_height)) {
       if ($dialog_height < $minimum_product_dialog_height)
          $dialog_height = $minimum_product_dialog_height;
    }
    else if ($dialog_height < 430) $dialog_height = 430;
    return $dialog_height;
}

function get_website_settings($db=null)
{
    if (! $db) $db = new DB;
    $query = 'select config_value from config where ' .
             'config_name="website_settings"';
    $row = $db->get_record($query);
    if ($row) return intval($row['config_value']);
    return 0;
}

function list_websites($db,$dialog,$website)
{
    $dialog->add_list_item(0,'All',$website == 0);
    $query = 'select * from web_sites order by domain';
    $web_sites = $db->get_records($query);
    if (! $web_sites) return;
    foreach ($web_sites as $row) {
       if ($row['name']) $name = $row['name'];
       else $name = $row['domain'];
       $dialog->add_list_item($row['id'],$name,$website == $row['id']);
    }
}

function list_website_checkboxes($db,$dialog,$websites,$field_suffix='',
                                 $num_cols=6,$min_width=500)
{
    $query = 'select * from web_sites order by domain';
    $website_rows = $db->get_records($query);
    if ($website_rows) $num_websites = count($website_rows);
    else $num_websites = 0;
    $dialog->write('<div style="position: relative; width: 100%;');
    if ($min_width) $dialog->write(' min-width: '.$min_width.'px;');
    $dialog->write("\">\n");
    $dialog->write("<table cellpadding=\"0\" cellspacing=\"0\">" .
                   "<tr><td style=\"padding-bottom:3px;\">\n");
    $dialog->write("<div class=\"perms_link check_websites\"><a href=\"#\" " .
                   "onClick=\"check_all_websites('".$field_suffix .
                   "'); return false;\">Select All</a>");
    if ($num_websites > $num_cols) $dialog->write('<br>');
    else $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->write("<a href=\"#\" onClick=\"uncheck_all_websites('" .
                   $field_suffix."'); return false;\">" .
                   "Select None</a></div>\n");
    if ($website_rows) {
       $row_num = 0;   $web_sites = explode(',',$websites);
       foreach ($website_rows as $row) {
          if ($row_num > 0) {
             if ($row_num % $num_cols)
                $dialog->write("</td><td style=\"padding-bottom:3px;\">\n");
             else $dialog->write("</td></tr><tr><td style=\"padding-bottom:3px;\">\n");
          }
          $id = $row['id'];
          if ($row['name']) $name = $row['name'];
          else $name = $row['domain'];
          $field_name = 'website_'.$id.$field_suffix;
          $dialog->add_checkbox_field($field_name,$name.'&nbsp;&nbsp;',
                                      in_array($id,$web_sites));
          $row_num++;
       }
    }
    $dialog->write("</td></tr></table></div>\n");
}

function parse_website_checkboxes(&$db_record,$field_suffix='')
{
    $websites = '';
    $form_fields = get_form_fields();
    foreach ($form_fields as $field_name => $field_value) {
       if ((substr($field_name,0,8) == 'website_') && ($field_value == 'on')) {
          if ($field_suffix &&
              (substr($field_name,-strlen($field_suffix)) != $field_suffix))
             continue;
          $id = substr($field_name,8);
          if ($field_suffix) $id = substr($id,0,-strlen($field_suffix));
          if ($websites != '') $websites .= ',';
          $websites .= $id;
       }
    }
    $db_record['websites']['value'] = $websites;
}

function get_current_website($db=null)
{
    if (! $db) $db = new DB;
    $current_dir = getcwd();
    $dir = basename($current_dir);
    if ($dir == 'public_html') $dir = '/';
    else if ($dir == 'cart') {
       $dir = basename(substr($current_dir,0,-5)).'/';
       if ($dir == 'public_html/') $dir = '/';
    }
    else $dir = $dir.'/';
    $full_dir = $current_dir;
    if (substr($full_dir,-5) == '/cart') $full_dir = substr($full_dir,0,-4);
    $query = 'select id from web_sites where (rootdir=?) or (rootdir=?)';
    $query = $db->prepare_query($query,$dir,$full_dir);
    $row = $db->get_record($query);
    if (! empty($row['id'])) return $row['id'];
    $query = 'select id from web_sites where (rootdir="") or ' .
             '(isnull(rootdir))';
    $row = $db->get_record($query);
    if (! empty($row['id'])) return $row['id'];
    return null;
}

function get_website_where($alias=null)
{
    global $website_cookie;

    if (! isset($website_cookie)) return null;
    $website = get_cookie($website_cookie);
    if (! $website) return null;
    if (! is_numeric($website)) return null;
    $where = 'find_in_set('.$website.',';
    if ($alias) $where .= $alias;
    $where .= 'websites)';
    return $where;
}

function add_website_js_array(&$screen,$db)
{
    global $enable_multisite;

    if (empty($enable_multisite)) return;

    $query = 'select id,base_href from web_sites order by id';
    $rows = $db->get_records($query);
    $head_block = '<script type="text/javascript">'."\n";
    $head_block .= '      var website_urls = [];'."\n";
    foreach ($rows as $row)
       $head_block .= '      website_urls['.$row['id'].'] = \'' .
                      $row['base_href']."';\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
}

function add_website_select_row($dialog,$db,$prompt,$field_name,
                                $hidden_row_id=null,$single_column=false)
{
    global $enable_multisite,$website_cookie;

    if (empty($enable_multisite)) return;

    if (isset($_COOKIE[$website_cookie]))
       $website = $_COOKIE[$website_cookie];
    else $website = 0;
    $query = 'select id,name,domain from web_sites';
    $web_sites = $db->get_records($query);
    if ($hidden_row_id)
       $dialog->start_hidden_row($prompt,$hidden_row_id,true,'middle');
    else if ($single_column)
       $dialog->write('<tr><td><span class="fieldprompt">'.$prompt .
                      "</span>\n");
    else $dialog->start_row($prompt,'middle');
    $dialog->start_choicelist($field_name);
    $dialog->add_list_item('0','All',(! $website));
    foreach ($web_sites as $web_site) {
       if ($web_site['name']) $web_site_name = $web_site['name'];
       else $web_site_name = $web_site['domain'];
       $dialog->add_list_item($web_site['id'],$web_site_name,
                              ($web_site['id'] == $website));
    }
    $dialog->end_choicelist();
    $dialog->end_row();
}

function add_payment_section($dialog,$label,$prefix,$values)
{
    global $website_settings;

    $dialog->write('<tr><td colspan="2" class="fieldprompt payment_section">' .
                   '<div class="payment_title">'.$label);
    if (isset($website_settings) &&
        ($website_settings & WEBSITE_SEPARATE_PAYMENT)) {
       $dialog->write("<span class=\"payment_flag\">");
       $dialog->add_checkbox_field($prefix.'_active','Active',$values);
       $dialog->write("</span>\n");    
    }
    $dialog->write("</div></td></tr>\n");
}

function upload_file()
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $label = get_form_field('Label');
    $process_cmd = get_form_field('Process');
    $upload_dir = get_form_field('Dir');
    $finish_function = get_form_field('Finish');
    $frame = get_form_field('Frame');

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file($prefix.'utility.js');
    $dialog->set_body_id('upload_file');
    $dialog->set_help('upload_file');
    $dialog->start_body('Upload '.$label);
    $dialog->start_button_column();
    $dialog->add_button('Upload '.$label,$prefix.'images/AddImage.png',
                        'process_upload_file();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_field_table();
    $dialog->write("<form method=\"POST\" action=\"".$_SERVER["PHP_SELF"] .
                   "\" name=\"UploadFile\" ");
    $dialog->write("encType=\"multipart/form-data\">\n");
    $dialog->add_hidden_field('cmd',$process_cmd);
    $dialog->add_hidden_field('Label',$label);
    $dialog->add_hidden_field('Dir',$upload_dir);
    $dialog->add_hidden_field('Finish',$finish_function);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_row('Filename:');
    $dialog->write("<input type=\"file\" name=\"Filename\" size=\"35\" ");
    $dialog->write("class=\"browse_button\" onBlur=\"update_server_filename();\">\n");
    $dialog->end_row();
    $dialog->start_row('Server Filename:');
    $dialog->write("<input type=\"text\" class=\"text\" name=\"ServerFilename\" ");
    $dialog->write("size=\"35\" value=\"\" onFocus=\"update_server_filename();\">\n");
    $dialog->end_row();
    $dialog->end_form();
    $dialog->end_field_table();
    $dialog->end_body();
}

function process_upload_file()
{
    $filename = $_FILES['Filename']['name'];
    $temp_name = $_FILES['Filename']['tmp_name'];
    $file_type = $_FILES['Filename']['type'];

    $server_filename = get_form_field('ServerFilename');
    $label = get_form_field('Label');
    $upload_dir = get_form_field('Dir');
    $finish_function = get_form_field('Finish');
    $frame = get_form_field('Frame');
    $filename = $upload_dir.$server_filename;
    if (file_exists($filename)) {
       process_error('That '.$label.' ('.$server_filename.') already exists on the server',-1);
       return;
    }

    if (! move_uploaded_file($temp_name,$filename)) {
       log_error('Attempted to move '.$temp_name.' to '.$filename);
       process_error('Unable to save uploaded file',-1);   return;
    }

    if (function_exists('custom_finish_upload_file') &&
        (! custom_finish_upload_file($server_filename,$errormsg))) {
       process_error('Unable to save uploaded file',-1);   return;
    }

    log_activity('Uploaded '.$label.' '.$server_filename.' to '.$filename);

    print '<html><head><script>';
    if ($frame) print "top.get_dialog_frame(\"".$frame."\").contentWindow";
    else print 'top.get_content_frame()';
    print '.'.$finish_function."('".$server_filename."');";
    print '</script></head><body></body></html>';
}

function spawn_program($parameters)
{
    global $php_program,$use_at_spawn;

    if (get_server_type() == WINDOWS) {
       if (class_exists('COM') && file_exists('shellexec.bat')) {
          $command_string = 'cmd /C shellexec.bat '.$parameters.' > spawn.log';
          $WshShell = new COM('WScript.Shell');
          $return_var = $WshShell->Run($command_string,0,false);
       }
       else {
          $command_string = $php_program.' '.$parameters.' > spawn.log';
          $exec_output = array();
          $Result = exec($command_string,$exec_output,$return_var);
       }
    }
    else {
       if (! isset($use_at_spawn)) $use_at_spawn = true;
       if ($use_at_spawn) $command_string = 'echo "';
       else $command_string = '';
       $command_string .= $php_program.' '.$parameters.' > spawn.log 2>&1';
       if ($use_at_spawn)
          $command_string .= '" | SHELL=/bin/bash at now > at.log 2>&1';
       else $command_string .= ' &';
       $exec_output = array();
       $Result = exec($command_string,$exec_output,$return_var);
    }
    return $return_var;
}

if (! function_exists('load_config_values')) {
   function load_config_values($db=null)
   {
       if (! $db) $db = new DB;

       $config_values = $db->get_records('select * from config','config_name',
                                         'config_value');
       if ((! $config_values) && isset($db->error)) {
          process_error('Database Error: '.$db->error,0);   return null;
       }
       return $config_values;
   }
}

class Process {

function Process($command)
{
    global $php_program;

    if (! file_exists($php_program)) {
       $this->return = 'PHP Program '.$php_program.' not found';
       return;
    }
    $command_string = '/usr/bin/nohup '.$php_program.' '.$command .
                      ' > spawn.log 2>&1 & echo $!';
    $exec_output = array();
    $result = exec($command_string,$exec_output,$return_var);
    $this->pid = (int) $exec_output[0];
    $this->return = $return_var;
}

function get_pid()
{
    return $this->pid;
}

function status()
{
    $command_string = 'ps -p '.$this->pid;
    $exec_output = array();
    $result = exec($command_string,$exec_output,$return_var);
    if (! isset($exec_output[1])) return false;
    return true;
}

function stop()
{
    $command_string = 'kill '.$this->pid;
    $result = exec($command_string);
    if ($this->status() == false) return true;
    return false;
}

};

function get_plugin_dir()
{
    global $cms_module;

    $plugin_dir = $cms_module;
    if (get_server_type() == WINDOWS) $dirchar = "\\";
    else $dirchar = '/';
    $slash_pos = strrpos($plugin_dir,$dirchar);
    if ($slash_pos === false) return '';
    $plugin_dir = substr($plugin_dir,0,$slash_pos);
    $slash_pos = strrpos($plugin_dir,$dirchar);
    if ($slash_pos === false) return '';
    $plugin_dir = substr($plugin_dir,0,$slash_pos);
    $plugin_dir .= $dirchar.'plugins';
    return $plugin_dir;
}

function plugin_installed($plugin_name)
{
    $original_dir = getcwd();
    chdir(get_plugin_dir());
    if (! class_exists('PluginEvent')) {
       $inline_plugin = true;
       require_once 'index.php';
    }
    $event = new PluginEvent(null);
    $event->load_plugin_data();
    $event->plugin_name = $plugin_name;
    $installed = $event->activated();
    chdir($original_dir);
    return $installed;
}

function get_widget_output($plugin_name,$parameters)
{
    global $cms_module,$cms_program,$cms_support_url,$prefix;
    global $plugin_activity_log,$plugin_error_log,$log_plugin_events;
    global $log_plugin_output;

    $parameters['Plugin'] = $plugin_name;
    $parameters['SupportDir'] = $cms_support_url;
    $original_dir = getcwd();
    chdir(get_plugin_dir());
    if (! class_exists('PluginEvent')) {
       $inline_plugin = true;
       require_once 'index.php';
    }
    $event = new PluginEvent(DISPLAY_WIDGET);
    $event->form_fields = $parameters;
    $event->program = $cms_program;
    $event->prefix = $prefix;
    $event->product = WSDLITE_PRODUCT;
    $event->module_filename = $cms_module;
    if (! class_exists('WSD')) require_once 'wsd.php';
    $event->wsd = new WSD($event->program,null);
    $event->browser_type = get_browser_type();
    $event->browser_version = get_browser_version();
    $event->load_plugin_data();
    $event->setup_widget();
    $event->call_plugins();
    chdir($original_dir);
    return ($event->output);
}

function load_user_list($db=null,$sales_rep_flag=false)
{
    if (! $db) $db = new DB;
    if ($db->check_encrypted_field('users','username'))
       $default = '%ENCRYPT%("default")';
    else $default = '"default"';
    $query = 'select * from users where (username!='.$default.')';
    if ($sales_rep_flag) $query .= ' and (sales_rep=1)';
    $query .= ' order by ';
    if ($db->check_encrypted_field('users','lastname'))
       $query .= '%DECRYPT%(lastname)';
    else $query .= 'lastname';
    if ($db->check_encrypted_field('users','firstname'))
       $query .= ',%DECRYPT%(firstname)';
    else $query .= ',firstname';
    $user_list = $db->get_records($query,'username');
    if (! $user_list) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
       return null;
    }
    $db->decrypt_records('users',$user_list,'username');
    if (function_exists('update_user_list')) update_user_list($db,$user_list);
    return $user_list;
}

function display_user_list($dialog,$user_list,$current_username=null)
{
    global $login_cookie;

    if ($current_username === null)
       $current_username = get_cookie($login_cookie);
    foreach ($user_list as $username => $user_info) {
       $full_name = $user_info['lastname'];
       if ($user_info['firstname'] != '')
          $full_name .= ', '.$user_info['firstname'];
       $dialog->add_list_item($username,$full_name,
                              $username == $current_username);
    }
}

function get_user_name($db,$username)
{
    $query = 'select firstname,lastname from users where username=';
    if ($db->check_encrypted_field('users','username'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $db->prepare_query($query,$username);
    $row = $db->get_record($query);
    if (! $row) return '';
    $db->decrypt_record('users',$row);
    return $row['firstname'].' '.$row['lastname'];
}

global $profile_start,$profile_time;

function start_profile()
{
    global $profile_start,$profile_time;

    $profile_start = microtime(true);
    $profile_time = $profile_start;
}

function log_profile($event)
{
    global $profile_time;

    $profile_end = microtime(true);
    $profile_file = @fopen('profile.log','at');
    $elapsed_time = round($profile_end - $profile_time,2);
    $memory_usage = round(memory_get_usage(true)/1048576,2);
    fwrite($profile_file,'event = '.$event.', time = '.$elapsed_time .
           's, memory = '.$memory_usage."mb\n");
    fclose($profile_file);
    $profile_time = $profile_end;
}

function end_profile()
{
    global $profile_start;

    $profile_end = microtime(true);
    $profile_file = @fopen('profile.log','at');
    $elapsed_time = round($profile_end - $profile_start,2);
    $memory_usage = round(memory_get_usage(true)/1048576,2);
    fwrite($profile_file,'file = '.$_SERVER['PHP_SELF'].', url = ' .
           $_SERVER['REQUEST_URI'].', time = '.$elapsed_time.'s, memory = ' .
           $memory_usage."mb\n");
    fclose($profile_file);
}

?>
