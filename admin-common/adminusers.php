<?php
/*
        Inroads Control Panel/Shopping Cart - Admin Tab - Admin Users Processing

                       Written 2007-2019 by Randall Severy
                        Copyright 2007-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'adminusers-common.php';
$adminusers_module = true;
require_once 'adminperms.php';
require_once 'utility.php';
if (file_exists('custom-config.php')) require_once 'custom-config.php';
else if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
if (! isset($enable_wholesale)) $enable_wholesale = false;
if ($enable_wholesale) require_once 'accounts-common.php';

function add_script_prefix(&$dialog)
{
    global $shopping_cart,$login_cookie,$prefs_cookie,$user_pref_names;
    global $enable_wholesale;

    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix='../cartengine/';\n";
    $admin_user = get_cookie($login_cookie);
    $head_block .= "      admin_user = '".$admin_user."';\n";
    if (isset($prefs_cookie))
       $head_block .= "      prefs_cookie = '".$prefs_cookie."';\n";
    $head_block .= "      user_prefs_names = [";
    foreach ($user_pref_names as $index => $name) {
       if ($index != 0) $head_block .= ',';
       $head_block .= "'".$name."'";
    }
    $head_block .= "];\n";
    if ($enable_wholesale) $user_dialog_width = 600;
    else $user_dialog_width = 480;
    $head_block .= '      user_dialog_width = '.$user_dialog_width.";\n";
    $head_block .= '    </script>';
    $dialog->add_head_line($head_block);
}

function display_admin_users()
{
    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_screen_body');
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('adminusers.css');
    $screen->add_script_file('adminusers.js');
    add_script_prefix($screen);
    $screen->set_body_id('admin_users_screen');
    $screen->set_help('admin_users_screen');
    $screen->start_body();
    $screen->set_button_width(121);
    $screen->start_button_column();
    $screen->add_button('Add User','images/AddUser.png','add_user();',
                        null,true,false,ADD_BUTTON);
    $screen->add_button('Edit User','images/EditUser.png','edit_user();',
                        null,true,false,EDIT_BUTTON);
    $screen->add_button('Change Password','images/EditUser.png',
                        'change_password();');
    $screen->add_button('Delete User','images/DeleteUser.png',
                        'delete_user();',null,true,false,DELETE_BUTTON);
    $screen->add_button('Defaults','images/AdminUsers.png',
                         'edit_defaults();');
    $screen->end_button_column();
    $screen->write("<script>load_grid();</script>\n");
    $screen->end_body();
}

function display_user_fields($dialog,$edit_type,$row,$db)
{
    global $user_admin_restricted_edit,$enable_wholesale,$prefs_cookie;
    global $enable_sales_reps,$accounts_label,$admin_directory,$websocket_port;

    require_once '../engine/modules.php';

    if (! isset($user_admin_restricted_edit))
       $user_admin_restricted_edit = false;
    else if ($user_admin_restricted_edit)
       get_user_perms($user_user_perms,$user_module_perms,$user_custom_perms);
    if (($edit_type == UPDATERECORD) && ($row['username'] == 'default')) {
       $default = true;   $initial_tab = 'perms_tab';
       $initial_content_id = 'perms_content';
    }
    else {
       $default = false;   $initial_tab = 'info_tab';
       $initial_content_id = 'info_content';
    }
    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row($initial_tab,$initial_content_id,'change_tab');
    if ($default)
       $dialog->add_tab('perms_tab','Permissions','perms_tab',
                        'perms_content','change_tab',true,null,FIRST_TAB);
    else {
       $dialog->add_tab('info_tab','User Info','info_tab','info_content',
                        'change_tab',true,null,FIRST_TAB);
       $dialog->add_tab('perms_tab','Permissions','perms_tab',
                        'perms_content','change_tab');
    }
    if (isset($prefs_cookie))
       $dialog->add_tab('prefs_tab','Preferences','prefs_tab',
                        'prefs_content','change_tab');
    if ($enable_wholesale)
       $dialog->add_tab('accounts_tab',$accounts_label,'accounts_tab',
                        'accounts_content','change_tab');
    call_module_event('add_user_tabs',array(&$dialog,$row,$edit_type,$db));
    if (function_exists('custom_add_user_tabs'))
       custom_add_user_tabs($dialog,$row,$edit_type,$db);
    $dialog->end_tab_row('tab_row_middle');
    if ($default) $dialog->add_hidden_field('username',$row['username']);
    else {
       $dialog->start_tab_content('info_content',true);
       $dialog->start_field_table();
       if (function_exists('custom_start_user_fields'))
          custom_start_user_fields($dialog,$row,$edit_type,$db);
       if ($edit_type == ADDRECORD)
          $dialog->add_edit_row('Username:','username','',30);
       else {
          $dialog->add_hidden_field('username',$row['username']);
          $dialog->add_text_row('Username:',$row['username']);
       }
       $dialog->add_edit_row('First Name:','firstname',$row,30);
       $dialog->add_edit_row('Last Name:','lastname',$row,30);
       if ($edit_type == ADDRECORD) {
          $dialog->add_password_row('Password:','password',$row,30);
          $dialog->add_password_row('Confirm Password:','ConfirmPassword',
                                    get_row_value($row,'password'),30);
       }
       $dialog->add_edit_row('E-mail Address:','email',$row,30);
       $flags = get_row_value($row,'flags');
       $suffix = '&nbsp;<input type="checkbox" class="checkbox" ' .
                 'name="flag_1" id="flag_1"';
       if ($flags & 1) $suffix .= ' checked';
       $suffix .= '><label for="flag_1">Use Skype for outgoing calls</label>';
       $dialog->add_edit_row('Skype Name:','skype_name',$row,15,null,
                             $suffix,true);
       $dialog->add_edit_row('Office Phone:','office_phone',$row,30);
       $dialog->add_edit_row('Mobile Phone:','mobile_phone',$row,30);
       $dialog->add_edit_row('Home Phone:','home_phone',$row,30);
       $dialog->add_edit_row('Other Phone:','other_phone',$row,30);
       if (isset($enable_sales_reps) && $enable_sales_reps) {
          $dialog->start_row('Sales Rep:','middle');
          $dialog->add_checkbox_field('sales_rep','',$row);
          $dialog->end_row();
       }
       call_module_event('add_user_fields',array($dialog,$row,$edit_type,$db));
       if (function_exists('add_custom_user_fields'))
          add_custom_user_fields($dialog,$row,$edit_type,$db);
       if ($edit_type == UPDATERECORD) {
          if (isset($row['creation_date']))
             $date_string = date('D M j Y G:i:s',$row['creation_date']);
          else $date_string = 'Unknown';
          $dialog->add_text_row('Creation Date:',$date_string);
          if (isset($row['modified_date']))
             $date_string = date('D M j Y G:i:s',$row['modified_date']);
          else $date_string = 'Unknown';
          $dialog->add_text_row('Modified Date:',$date_string);
          if (isset($row['ip_address']) && $row['ip_address'])
             $dialog->add_text_row('Last IP Address:',$row['ip_address']);
          if (isset($row['last_login']))
             $date_string = date('D M j Y G:i:s',$row['last_login']);
          else $date_string = 'Unknown';
          $dialog->add_text_row('Last Login:',$date_string);
       }
       if (function_exists('custom_end_user_fields'))
          custom_end_user_fields($dialog,$row,$edit_type,$db);
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    $dialog->start_tab_content('perms_content',$default);
    $dialog->start_field_table();
    $user_perms = intval(get_row_value($row,'perms'));
    $module_perms = intval(get_row_value($row,'module_perms'));
    $custom_perms = intval(get_row_value($row,'custom_perms'));
    $dialog->write("<tr valign=\"top\"><td class=\"fieldprompt\" nowrap>");
    $dialog->write("Permissions:\n<br><br><div class=\"perms_link\"><a href=\"#\" " .
                   "onClick=\"check_all(); return false;\">Select All</a>" .
                   "<br><a href=\"#\" onClick=\"uncheck_all(); return false;\">" .
                   "Select None</a></div>\n");
    $dialog->write('</td><td nowrap>');
    $admin_perms_choices = get_admin_perms_choices();
    if (function_exists('setup_custom_perms'))
       setup_custom_perms($admin_perms_choices);
    foreach ($admin_perms_choices as $index => $perm_info) {
       if ($user_admin_restricted_edit) {
          if (($perm_info[3] == USER_PERM) &&
              (! ($user_user_perms & $perm_info[0]))) {
             $dialog->add_hidden_field('perm_0_'.$perm_info[0],
                ($user_perms & $perm_info[0])?'on':'');
             continue;
          }
          if (($perm_info[3] == MODULE_PERM) &&
              (! ($user_module_perms & $perm_info[0]))) {
             $dialog->add_hidden_field('perm_1_'.$perm_info[0],
                ($module_perms & $perm_info[0])?'on':'');
             continue;
          }
          if (($perm_info[3] == CUSTOM_PERM) &&
              (! ($user_custom_perms & $perm_info[0]))) {
             $dialog->add_hidden_field('perm_2_'.$perm_info[0],
                ($custom_perms & $perm_info[0])?'on':'');
             continue;
          }
       }
       if ((($perm_info[3] == PERM_HEADER) ||
            ($perm_info[3] == TAB_CONTAINER)) &&
           ((! isset($admin_perms_choices[$index + 1])) ||
            ($admin_perms_choices[$index + 1][2] <= $perm_info[2]))) continue;
       for ($loop = 0;  $loop < $perm_info[2];  $loop++)
          print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
       switch ($perm_info[3]) {
          case USER_PERM:
             $dialog->add_checkbox_field('perm_0_'.$perm_info[0],$perm_info[1],
                                         $user_perms & $perm_info[0]);
             break;
          case MODULE_PERM:
             $module_name = str_replace("'","\\'",$perm_info[1]);
             $dialog->add_checkbox_field('perm_1_'.$perm_info[0],$perm_info[1],
                $module_perms & $perm_info[0],
                'module_permission_onclick(event,this,\''.$module_name.'\');');
             break;
          case CUSTOM_PERM:
             $dialog->add_checkbox_field('perm_2_'.$perm_info[0],$perm_info[1],
                                         $custom_perms & $perm_info[0]);
             break;
          case PERM_HEADER:
          case TAB_CONTAINER:
             $dialog->write('<strong>'.$perm_info[1].'</strong>');
             break;
       }
       $dialog->write("<br>\n");
    }
    $dialog->write("</td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_tab_content();

    if (isset($prefs_cookie)) {
       if ($edit_type == UPDATERECORD) $prefs_user = $row['username'];
       else $prefs_user = 'default';
       $user_prefs = get_user_prefs($db,$prefs_user);
       $dialog->start_tab_content('prefs_content',false);
       if (isset($user_prefs['skin'])) $skin = $user_prefs['skin'];
       else $skin = '';
       if (isset($user_prefs['editor'])) $editor = $user_prefs['editor'];
       else $editor = 'fckeditor';
       $dialog->add_hidden_field('OldSkin',$skin);
       $dialog->add_hidden_field('OldEditor',$editor);
       $dialog->start_field_table();
       $dialog->start_row('Skin:','middle');
       $dialog->start_choicelist('skin');
       $dialog->add_list_item('','None',(! $skin));
       if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
       $skins_dir = @opendir($admin_directory.'skins/');
       if ($skins_dir) {
          while (($skin_name = readdir($skins_dir)) !== false) {
             if ($skin_name[0] == '.') continue;
             $dialog->add_list_item($skin_name,$skin_name,$skin == $skin_name);
          }
          closedir($skins_dir);
       }
       $dialog->end_choicelist();
       $dialog->end_row();
       $dialog->start_row('Buttons:','middle');
       $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
       if (isset($user_prefs['buttons'])) $buttons = $user_prefs['buttons'];
       else $buttons = 'left';
       $dialog->add_radio_field('buttons','left','Left Side',
                                $buttons == 'left');
       $dialog->write("</div>\n");
       $dialog->add_radio_field('buttons','top','Top and Bottom',
                                $buttons == 'top');
       $dialog->end_row();
       $dialog->start_row('Editor:','middle');
       $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
       $dialog->add_radio_field('editor','ckeditor','CKEditor',
                                $editor == 'ckeditor');
       $dialog->write("</div>\n");
       $dialog->add_radio_field('editor','fckeditor','Classic',
                                $editor == 'fckeditor');
       $dialog->end_row();
       $dialog->start_row('Admin Layout:','middle');
       $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
       if (isset($user_prefs['layout'])) $layout = $user_prefs['layout'];
       else $layout = 'normal';
       $dialog->add_radio_field('layout','maximized','Maximized',
                                $layout == 'maximized',null);
       $dialog->write("</div>\n");
       $dialog->add_radio_field('layout','normal','Normal',
                                $layout == 'normal',null);
       $dialog->end_row();
       $dialog->start_row('Preload Screens:','middle');
       $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
       if (isset($user_prefs['preload'])) $preload = $user_prefs['preload'];
       else $preload = 'no';
       $dialog->add_radio_field('preload','yes','Yes',$preload == 'yes');
       $dialog->write("</div>\n");
       $dialog->add_radio_field('preload','no','No',$preload != 'yes');
       $dialog->end_row();
       if (isset($websocket_port)) {
          $dialog->start_row('Desktop Notifications:','middle','fieldprompt',
                             null,true);
          $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
          if (isset($user_prefs['notify'])) $notify = $user_prefs['notify'];
          else $notify = 'no';
          $dialog->add_radio_field('notify','yes','Yes',$notify == 'yes');
          $dialog->write("</div>\n");
          $dialog->add_radio_field('notify','no','No',$notify != 'yes');
          $dialog->end_row();
       }
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if ($enable_wholesale) {
       $username = get_row_value($row,'username');
       $accounts = load_accounts($db);
       $user_accounts = load_user_accounts($username,$db);

       $dialog->start_tab_content('accounts_content',false);
       $dialog->start_field_table();
       $all_accounts = get_row_value($row,'all_accounts');
       if ($all_accounts == '')  $all_accounts = 0;
       $dialog->write("<tr><td colspan=\"2\">");
       $dialog->add_radio_field('all_accounts','0','All '.$accounts_label,
                                $all_accounts == 0);
       $dialog->write("</td></tr>\n");
       $dialog->write("<tr><td colspan=\"2\">");
       $dialog->add_radio_field('all_accounts','1','Selected '.$accounts_label,
                                $all_accounts == 1);
       $dialog->write("</td></tr>\n");
       $dialog->write("<tr><td colspan=\"2\" style=\"padding-left: 50px;\">");
       $last_id = 0;
       foreach ($accounts as $acct_id => $account_info) {
          $field_name = 'account_'.$acct_id;
          $account_name = $account_info['name'];
          if ($account_info['company'])
             $account_name .= ' - '.$account_info['company'];
          $selected = isset($user_accounts[$acct_id]);
          $dialog->add_checkbox_field($field_name,$account_name,$selected);
          $dialog->write("<br>\n");
          if ($acct_id > $last_id) $last_id = $acct_id;
       }
       $dialog->write("</td></tr>\n");
       $dialog->add_hidden_field('LastID',$last_id);

       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    call_module_event('user_tabs',array(&$dialog,$row,$edit_type,$db));
    if (function_exists('custom_user_tabs'))
       custom_user_tabs($dialog,$row,$edit_type,$db);
    $dialog->end_tab_section();
}

function parse_user_fields($db,&$user_record)
{
    $db->parse_form_fields($user_record);
    $user_perms = 0;   $module_perms = 0;   $custom_perms = 0;
    $admin_perms_choices = get_admin_perms_choices();
    if (function_exists('setup_custom_perms'))
       setup_custom_perms($admin_perms_choices);
    foreach ($admin_perms_choices as $perm_info) {
       switch ($perm_info[3]) {
          case USER_PERM:
             if (get_form_field('perm_0_'.$perm_info[0]) == 'on')
                $user_perms |= $perm_info[0];
             break;
          case MODULE_PERM:
             if (get_form_field('perm_1_'.$perm_info[0]) == 'on')
                $module_perms |= $perm_info[0];
             break;
          case CUSTOM_PERM:
             if (get_form_field('perm_2_'.$perm_info[0]) == 'on')
                $custom_perms |= $perm_info[0];
             break;
       }
    }
    $user_record['perms']['value'] = $user_perms;
    $user_record['module_perms']['value'] = $module_perms;
    $user_record['custom_perms']['value'] = $custom_perms;
    $flags = 0;
    if (get_form_field('flag_1') == 'on') $flags |= 1;
    $user_record['flags']['value'] = $flags;
}

function admin_users()
{
    $user_list = load_user_list();
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('adminusers.css');
    $dialog->add_script_file('adminusers.js');
    add_script_prefix($dialog);
    $dialog->set_onload_function('top.grow_current_dialog();');
    $dialog->set_body_id('admin_users');
    $dialog->set_help('admin_users');
    $dialog->start_body('Admin Users');
    $dialog->set_button_width(155);
    $dialog->start_button_column();
    $dialog->add_button('Add User','images/AddUser.png','add_user();');
    $dialog->add_button('Edit User','images/EditUser.png','edit_user();');
    $dialog->add_button('Change Password','images/EditUser.png',
                        'change_password();');
    $dialog->add_button('Delete User','images/DeleteUser.png',
                        'delete_user();');
    $dialog->add_button('Defaults','images/AdminUsers.png',
                        'edit_defaults();');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('adminusers.php','AdminUsers');
    $dialog->start_field_table();
    $dialog->start_row('Users:','top');
    $dialog->start_listbox('UserID',count($user_list),false,null,
                           'edit_user();');
    display_user_list($dialog,$user_list);
    $dialog->end_listbox();
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function get_user_record($db,$username)
{
    $query = 'select * from users where username=';
    if ($db->check_encrypted_field('users','username'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $db->prepare_query($query,$username);
    $row = $db->get_record($query);
    if ($row) $db->decrypt_record('users',$row);
    return $row;
}

function add_user()
{
    $db = new DB;
    $row = get_user_record($db,'default');
    if (! $row) $row = array();

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('adminusers.css');
    $dialog->add_script_file('adminusers.js');
    add_script_prefix($dialog);
    require_once '../engine/modules.php';
    call_module_event('user_head',array(&$dialog,ADDRECORD,$db));
    if (function_exists('custom_init_user_dialog'))
       custom_init_user_dialog($dialog);
    $head_block = '    <style> .fieldtable { width: 100%; } </style>';
    $dialog->add_head_line($head_block);
    $dialog->set_body_id('add_user');
    $dialog->set_help('add_user');
    $dialog->start_body('Add User');
    $dialog->set_button_width(100);
    $dialog->start_button_column(false,true,true);
    $dialog->add_button('Add User','images/AddUser.png','process_add_user();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('adminusers.php','AddUser');
    if (! $dialog->skin) $dialog->start_field_table();
    display_user_fields($dialog,ADDRECORD,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_user()
{
    global $enable_wholesale;

    $db = new DB;
    $user_record = user_record_definition();
    parse_user_fields($db,$user_record);
    $username = trim($user_record['username']['value']);
    $user_record['username']['value'] = $username;
    if ($username == 'default') {
       http_response(409,'That Username is reserved');   return;
    }
    $row = get_user_record($db,$username);
    if ($row) {
       http_response(409,'Username Already Exists');   return;
    }

    $user_record['creation_date']['value'] = time();
    $user_record['modified_date']['value'] = time();
    if (function_exists('custom_update_user')) {
       if (! custom_update_user($db,$user_record,ADDRECORD)) return;
    }
    require_once '../engine/modules.php';
    if (module_attached('add_user')) {
       $user_info = $db->convert_record_to_array($user_record);
       if (! call_module_event('add_user',array($db,$user_info))) {
          call_module_event('delete_user',array($db,$user_info));
          http_response(422,get_module_errors());   return;
       }
    }
    if (! $db->insert('users',$user_record)) {
       http_response(422,$db->error);   return;
    }
    $error_msg = null;
    if (! save_user_prefs($db,$user_record,ADDRECORD,$error_msg)) {
       http_response(422,$error_msg);   return;
    }
    if ($enable_wholesale) {
       $account_record = user_account_record_definition();
       $account_record['username']['value'] = $user_record['username']['value'];
       $last_id = get_form_field('LastID');
       for ($loop = 0;  $loop <= $last_id;  $loop++)
          if (get_form_field('account_'.$loop) == 'on') {
             $account_record['account_id']['value'] = $loop;
             if (! $db->insert('user_accounts',$account_record)) {
                http_response(422,$db->error);   return;
             }
          }
    }
    if (function_exists('custom_save_user')) {
       if (! custom_save_user($db,$user_record,ADDRECORD)) return;
    }
    http_response(201,'User Added');
    log_activity('Added User '.$user_record['username']['value']);
}

function edit_user()
{
    $db = new DB;
    $username = get_form_field('username');
    $row = get_user_record($db,$username);
    if (! $row) {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,-1);   return;
       }
       else if ($username == 'default') $row = array('username'=>$username);
       else {
          process_error('User Not Found',-1);   return;
       }
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('adminusers.css');
    $dialog->add_script_file('adminusers.js');
    add_script_prefix($dialog);
    $head_block = '    <style> .fieldtable { width: 100%; } </style>';
    $dialog->add_head_line($head_block);
    require_once '../engine/modules.php';
    call_module_event('user_head',array(&$dialog,UPDATERECORD,$db));
    if (function_exists('custom_init_user_dialog'))
       custom_init_user_dialog($dialog);
    if ($username == 'default') $dialog_title = 'Edit User Defaults';
    else $dialog_title = 'Edit User '.$username;
    $dialog->set_body_id('edit_user');
    $dialog->set_help('edit_user');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column(false,true,true);
    $dialog->add_button('Update','images/Update.png','update_user();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('adminusers.php','EditUser');
    if (! $dialog->skin) $dialog->start_field_table();
    display_user_fields($dialog,UPDATERECORD,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_user()
{
    global $enable_wholesale;

    $db = new DB;
    $user_record = user_record_definition();
    parse_user_fields($db,$user_record);
    $username = $user_record['username']['value'];
    $user_record['modified_date']['value'] = time();
    if (function_exists('custom_update_user')) {
       if (! custom_update_user($db,$user_record,UPDATERECORD)) return;
    }
    $row = get_user_record($db,$username);
    if ($username == 'default') {
       if ($row) {
          if (! $db->update('users',$user_record)) {
             http_response(422,$db->error);   return;
          }
       }
       else if (! $db->insert('users',$user_record)) {
          http_response(422,$db->error);   return;
       }
    }
    else {
       if (! $row) {
          if (isset($db->error)) http_response(422,$db->error);
          else http_response(409,'User Not Found');
          return;
       }
       $user_record['password']['value'] = $row['password'];
       require_once '../engine/modules.php';
       if (module_attached('update_user')) {
          $user_info = $db->convert_record_to_array($user_record);
          if (! call_module_event('update_user',array($db,$user_info,$row))) {
             http_response(422,get_module_errors());   return;
          }
       }
       unset($user_record['password']['value']);
       if (! $db->update('users',$user_record)) {
          http_response(422,$db->error);   return;
       }
    }
    $error_msg = null;
    if (! save_user_prefs($db,$user_record,UPDATERECORD,$error_msg)) {
       http_response(422,$error_msg);   return;
    }
    if ($enable_wholesale) {
       $query = 'delete from user_accounts where username=?';
       $query = $db->prepare_query($query,$username);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
       $account_record = user_account_record_definition();
       $account_record['username']['value'] = $username;
       $last_id = get_form_field('LastID');
       for ($loop = 0;  $loop <= $last_id;  $loop++)
          if (get_form_field('account_'.$loop) == 'on') {
             $account_record['account_id']['value'] = $loop;
             if (! $db->insert('user_accounts',$account_record)) {
                http_response(422,$db->error);   return;
             }
          }
    }
    if (function_exists('custom_save_user')) {
       if (! custom_save_user($db,$user_record,UPDATERECORD)) return;
    }
    http_response(201,'User Updated');
    if ($username == 'default') $username = 'Defaults';
    log_activity('Updated User '.$username);
}

function change_password()
{
    $username = get_form_field('username');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('adminusers.css');
    $dialog->add_script_file('adminusers.js');
    add_script_prefix($dialog);
    $head_block = '    <style> .fieldtable { width: 100%; } </style>';
    $dialog->add_head_line($head_block);
    $dialog->set_body_id('change_password');
    $dialog->set_help('change_password');
    $dialog->start_body('Change User Password');
    $dialog->start_button_column();
    $dialog->add_button('Change','images/Update.png','update_password();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('adminusers.php','ChangePassword');
    $dialog->add_hidden_field('username',$username);
    $dialog->start_field_table();
    $dialog->add_text_row('Username:',$username);
    $dialog->add_password_row('Password:','password','',30);
    $dialog->add_password_row('Confirm Password:','ConfirmPassword','',30);
    if (function_exists('add_custom_password_fields'))
       add_custom_password_fields($dialog);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_password()
{
    global $module_errors;

    $db = new DB;
    $user_record = user_record_definition();
    $db->parse_form_fields($user_record);
    $username = $user_record['username']['value'];
    $user_record['modified_date']['value'] = time();
    if (function_exists('custom_update_password')) {
       if (! custom_update_password($db,$user_record,$error)) {
          http_response(422,$error);   return;
       }
    }
    $row = get_user_record($db,$username);
    $row['password'] = $user_record['password']['value'];
    $row['modified_date'] = $user_record['modified_date']['value'];
    require_once '../engine/modules.php';
    call_module_event('change_password',array($db,$row),null,true);
    if (! $db->update('users',$user_record)) {
       http_response(422,$db->error);   return;
    }
    if (function_exists('custom_finish_update_password')) {
       if (! custom_finish_update_password($db,$user_record,$error)) {
          http_response(422,$error);   return;
       }
    }
    log_activity('Changed Password for User '.$username);
    $errors = get_module_errors();
    if (! empty($errors)) {
       http_response(422,$errors);   return;
    }
    http_response(201,'Password Changed');
}

function delete_user()
{
    global $enable_wholesale;

    $db = new DB;
    $username = get_form_field('username');
    $row = get_user_record($db,$username);
    if (! $row) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(409,'User Not Found');
       return;
    }
    require_once '../engine/modules.php';
    if (! call_module_event('delete_user',array($db,$row))) {
       http_response(422,get_module_errors());   return;
    }
    $user_record = user_record_definition();
    $user_record['username']['value'] = $username;
    if (! $db->delete('users',$user_record)) {
       http_response(422,$db->error);   return;
    }
    if ($enable_wholesale) {
       $query = 'delete from user_accounts where username=?';
       $query = $db->prepare_query($query,$username);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    if (function_exists('custom_delete_user')) {
       if (! custom_delete_user($db,$user_record,$row)) return;
    }
    http_response(201,'User Deleted');
    log_activity('Deleted User '.$username);
}

function user_preferences()
{
    global $admin_directory,$websocket_port;

    $db = new DB;
    $user_prefs = get_user_prefs($db);

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('adminusers.css');
    $dialog->add_script_file('adminusers.js');
    add_script_prefix($dialog);
    $dialog->set_onload_function('user_preferences_onload();');
    $dialog->set_body_id('user_preferences');
    $dialog->set_help('user_preferences');
    $dialog->start_body('User Preferences');
    $dialog->start_button_column(false,true);
    $dialog->add_button('Update','images/Update.png','update_userprefs();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('adminusers.php','UserPrefs');
    if (isset($user_prefs['skin'])) $skin = $user_prefs['skin'];
    else $skin = '';
    $dialog->add_hidden_field('OldSkin',$skin);
    $dialog->start_field_table();

    $dialog->start_row('Skin:','middle');
    $dialog->start_choicelist('skin','change_skin(this);');
    $dialog->add_list_item('','None',(! $skin));
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $skins_dir = @opendir($admin_directory.'skins/');
    if ($skins_dir) {
       while (($skin_name = readdir($skins_dir)) !== false) {
          if ($skin_name[0] == '.') continue;
          $dialog->add_list_item($skin_name,$skin_name,$skin == $skin_name);
       }
       closedir($skins_dir);
    }
    $dialog->end_choicelist();
    $dialog->end_row();

    $dialog->start_hidden_row('Buttons:','pref_button_row',(! $skin),'middle',
                              'fieldprompt',null,true);
    $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
    if (! $skin) $pref_buttons = 'left';
    else if (isset($user_prefs['buttons']))
       $pref_buttons = $user_prefs['buttons'];
    else $pref_buttons = 'left';
    $dialog->add_radio_field('buttons','left','Left Side',
                             $pref_buttons == 'left');
    $dialog->write("</div>\n");
    $dialog->add_radio_field('buttons','top','Top and Bottom',
                             $pref_buttons == 'top');
    $dialog->end_row();

    $dialog->start_row('Editor','middle');
    $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
    if (isset($user_prefs['editor'])) $editor = $user_prefs['editor'];
    else $editor = 'fckeditor';
    $dialog->add_radio_field('editor','ckeditor','CKEditor',
                             $editor == 'ckeditor');
    $dialog->write("</div>\n");
    $dialog->add_radio_field('editor','fckeditor','Classic',
                             $editor == 'fckeditor');
    $dialog->end_row();

    $dialog->start_hidden_row('Admin Layout:','pref_button_row',(! $skin),
                              'middle','fieldprompt',null,true);
    $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
    if (isset($user_prefs['layout'])) $layout = $user_prefs['layout'];
    else $layout = 'normal';
    $dialog->add_radio_field('layout','maximized','Maximized',
                             $layout == 'maximized');
    $dialog->write("</div>\n");
    $dialog->add_radio_field('layout','normal','Normal',
                             $layout == 'normal');
    $dialog->end_row();

    $dialog->start_row('Preload Screens:','middle','fieldprompt',null,true);
    $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
    if (isset($user_prefs['preload'])) $preload = $user_prefs['preload'];
    else $preload = 'no';
    $dialog->add_radio_field('preload','yes','Yes',$preload == 'yes');
    $dialog->write("</div>\n");
    $dialog->add_radio_field('preload','no','No',$preload != 'yes');
    $dialog->end_row();

    if (isset($websocket_port)) {
       $dialog->start_row('Desktop Notifications:','middle','fieldprompt',
                          null,true);
       $dialog->write("<div style=\"width:80px; display: inline-block;\">\n");
       if (isset($user_prefs['notify'])) $notify = $user_prefs['notify'];
       else $notify = 'no';
       $dialog->add_radio_field('notify','yes','Yes',$notify == 'yes',
                                'change_notify();');
       $dialog->write("</div>\n");
       $dialog->add_radio_field('notify','no','No',$notify != 'yes',
                                'change_notify();');
       $dialog->end_row();
       $dialog->write('<tr id="grant_permission" style="display:none;"><td ' .
                      'colspan="2" align="center">'."\n");
       $dialog->write('<p>This browser has not yet granted permission for ' .
                      'desktop notifications</p>'."\n");
       $dialog->add_oval_button('Grant Permission',
                                'grant_notify_permissions();');
       $dialog->end_row();
    }

    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_user_preferences()
{
    global $login_cookie;

    $db = new DB;
    $admin_user = get_cookie($login_cookie);
    $row = get_user_record($db,$admin_user);
    if (! $row) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(404,'User Not Found');
       return;
    }
    $user_record = user_record_definition();
    foreach ($user_record as $field_name => $field_info)
       if (isset($row[$field_name]))
          $user_record[$field_name]['value'] = $row[$field_name];
    $error_msg = null;
    if (! save_user_prefs($db,$user_record,UPDATERECORD,$error_msg)) {
       http_response(422,$error_msg);   return;
    }
    http_response(201,'User Preferences Updated');
    log_activity('Updated User Preferences for '.$admin_user);
}

function set_user_preference()
{
    global $login_cookie;

    $pref_name = get_form_field('name');
    $pref_value = get_form_field('value');
    $db = new DB;
    $admin_user = get_cookie($login_cookie);
    $user_pref_record = user_pref_record_definition();
    $user_pref_record['username']['value'] = $admin_user;
    $user_pref_record['pref_name']['value'] = $pref_name;
    $user_pref_record['pref_value']['value'] = $pref_value;
    if (! $db->update('user_prefs',$user_pref_record)) {
       http_response(422,$db->error);   return;
    }
    if ($db->affected_rows() == 0) {
       if (! $db->insert('user_prefs',$user_pref_record)) {
          http_response(422,$db->error);   return;
       }
    }

    http_response(201,'User Preference Set');
}

function set_all_users_skin()
{
    $skin = get_form_field('skin');
    if (! $skin) {
       print "You must specify a skin<br>\n";   return;
    }
    require_once '../engine/modules.php';
    $db = new DB;
    $user_list = load_user_list($db);
    $user_list['default'] = array('username'=>'default','perms'=>0,
                                  'module_perms'=>0,'custom_perms'=>0);

    $user_pref_record = user_pref_record_definition();
    $user_pref_record['pref_name']['value'] = 'skin';
    $user_pref_record['pref_value']['value'] = $skin;
    foreach ($user_list as $username => $user_info) {
       $user_pref_record['username']['value'] = $username;
       $old_skin = get_user_prefs($db,$username,'skin');
       if ($old_skin !== null) {
          if ($old_skin == $skin)
             print 'Skin already set for '.$username."<br>\n";
          else if (! $db->update('user_prefs',$user_pref_record))
             print 'Unable to update user preference: '.$db->error."<br>\n";
          else print 'Updated skin for '.$username."<br>\n";
       }
       else if (! $db->insert('user_prefs',$user_pref_record))
          print 'Unable to add user preference: '.$db->error."<br>\n";
       else print 'Created skin for '.$username."<br>\n";
       if ($skin != $old_skin) {
          if (! call_module_event('change_skin',array($skin,$user_info)))
             print 'Error in change_skin event call: '.get_module_errors() .
                   "<br>\n";
       }
    }
}

function set_all_users_editor()
{
    $editor = get_form_field('editor');
    if (! $editor) {
       print "You must specify an editor<br>\n";   return;
    }
    require_once '../engine/modules.php';
    $db = new DB;
    $user_list = load_user_list($db);
    $user_list['default'] = array('username'=>'default','module_perms'=>0);

    $user_pref_record = user_pref_record_definition();
    $user_pref_record['pref_name']['value'] = 'editor';
    $user_pref_record['pref_value']['value'] = $editor;
    foreach ($user_list as $username => $user_info) {
       $user_pref_record['username']['value'] = $username;
       $old_editor = get_user_prefs($db,$username,'editor');
       if ($old_editor !== null) {
          if ($old_editor == $editor)
             print 'Editor already set for '.$username."<br>\n";
          else if (! $db->update('user_prefs',$user_pref_record))
             print 'Unable to update user preference: '.$db->error."<br>\n";
          else print 'Updated editor for '.$username."<br>\n";
       }
       else if (! $db->insert('user_prefs',$user_pref_record))
          print 'Unable to add user preference: '.$db->error."<br>\n";
       else print 'Set editor for '.$username."<br>\n";
       if ($editor != $old_editor) {
          $module_perms = $user_info['module_perms'];
          if (! call_module_event('change_editor',array($editor,$user_info)))
             print 'Error in change_editor event call: '.get_module_errors(). 
                   "<br>\n";
       }
    }
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'adminusers') admin_users();
else if ($cmd == 'adduser') add_user();
else if ($cmd == 'processadduser') process_add_user();
else if ($cmd == 'edituser') edit_user();
else if ($cmd == 'updateuser') update_user();
else if ($cmd == 'changepw') change_password();
else if ($cmd == 'updatepw') update_password();
else if ($cmd == 'deleteuser') delete_user();
else if ($cmd == 'userprefs') user_preferences();
else if ($cmd == 'updateuserprefs') update_user_preferences();
else if ($cmd == 'setuserpref') set_user_preference();
else if ($cmd == 'setskin') set_all_users_skin();
else if ($cmd == 'seteditor') set_all_users_editor();
else display_admin_users();

DB::close_all();

?>
