<?php
/*
                   Inroads Control Panel/Shopping Cart - Admin Tab

                        Written 2007-2019 by Randall Severy
                         Copyright 2007-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';

if (isset($db_host)) $cms_site = false;
else $cms_site = true;
if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;
   require_once '../cartengine/importdata.php';
   require_once '../cartengine/exportdata.php';
   require_once '../cartengine/cartconfig.php';
   require_once '../cartengine/cartconfig-common.php';
   require_once '../cartengine/adminperms.php';
   $catalog_site = false;
}
else {
   $shopping_cart = false;
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
   if (! $cms_site) {
      if (file_exists('importdata.php')) require_once 'importdata.php';
      if (file_exists('exportdata.php')) require_once 'exportdata.php';
      require_once 'countrystate-common.php';
   }
   require_once 'utility.php';
   require_once 'adminperms.php';
}
require_once 'admin-common.php';
if (! isset($enable_schedule)) $enable_schedule = false;
if ($enable_schedule) require_once 'schedule-admin.php';

if (! isset($enable_manage_images)) $enable_manage_images = false;
if (! isset($enable_alt_images)) {
   if ($shopping_cart && file_exists($attr_image_dir.'/original/'))
      $enable_alt_images = true;
   else $enable_alt_images = false;
}

$admin_tabs = array();
$admin_tab_order = array();

function add_admin_tab($new_tab,$label,$url,$perm,$perm_type,$before_order=null,
                      $use_cover=true,$width=null,$click_function=null)
{
    global $admin_tabs,$admin_tab_order;

    $admin_tabs[$new_tab] = array($label,$url,$perm,$perm_type,$use_cover,
                                  $width,$click_function);
    if ($before_order == null) $admin_tab_order[] = $new_tab;
    else {
       $insert_pos = -1;   $index = 0;
       while (list($tab_index,$tab_name) = each($admin_tab_order))
       foreach ($admin_tab_order as $tab_name) {
          if ($tab_name == $before_order) {
             $insert_pos = $index;   break;
          }
          else $index++;
       }
       if ($insert_pos == -1) $admin_tab_order[] = $new_tab;
       else array_splice($admin_tab_order,$insert_pos,0,array($new_tab));
    }
}

function remove_admin_tab($tab)
{
    global $admin_tabs,$admin_tab_order;

    unset($admin_tabs[$tab]);
    foreach ($admin_tab_order as $index => $tab_name)
       if ($tab_name == $tab) {
          unset($admin_tab_order[$index]);   break;
       }
}

function display_admin_screen()
{
    global $cms_site,$siteid,$admin_tabs,$admin_tab_order,$show_dashboard;

    if (! isset($show_dashboard)) {
       if (isset($siteid) && $siteid) $show_dashboard = true;
       else $show_dashboard = false;
    }
    get_user_perms($user_perms,$module_perms,$custom_perms);

    $screen = new Screen;
    $screen->set_body_class('hasInnerFrames');
    $screen->add_style_sheet('admin.css');
    $screen->add_script_file('admin.js');
    if (! $screen->skin) {
       $head_block = "<script type=\"text/javascript\">\n";
       $head_block .= "      iframe_height_offset = 46;\n";
       $head_block .= "      iframe_width_offset = 18;\n";
       $head_block .= '    </script>';
       $screen->add_head_line($head_block);
    }
    $screen->set_body_id('admin');
    $screen->set_help('admin');
    $screen->start_body();

    if ($show_dashboard)
       add_admin_tab('home','Home',
                     'https://www.inroads.us/siteadmin/billboard.php?siteid=' .
                     $siteid,0,0,null,false,'55px');
    if (! $cms_site)
       add_admin_tab('config','Config','admin.php?cmd=adminconfig',
                     CONFIG_TAB_PERM,USER_PERM,null,true,'60px');
    add_admin_tab('reports','Reports','reports.php',REPORTS_TAB_PERM,
                  USER_PERM,null,true,'65px');
    add_admin_tab('forms','Forms','forms.php',FORMS_TAB_PERM,USER_PERM,null,
                  true,'55px');
    add_admin_tab('templates','Templates','templates.php',TEMPLATES_TAB_PERM,
                  USER_PERM,null,true,'76px');
    if (! $cms_site) {
       add_admin_tab('users','Users','adminusers.php',ADMIN_USERS_TAB_PERM,
                     USER_PERM,null,true,'55px');
       add_admin_tab('data','Data','admin.php?cmd=admindata',DATA_TAB_PERM,
                     USER_PERM,null,true,'55px');
    }
    add_admin_tab('other','Other','admin.php?cmd=adminother',0,0,null,
                  true,'60px');
    require_once '../engine/modules.php';
    call_module_event('admin_tabs',array($user_perms,$module_perms,
                                         $custom_perms));
    if (function_exists('setup_custom_admin_tabs')) setup_custom_admin_tabs();

    $initial_cover = true;
    $start_tab = get_form_field('admintab');
    if ($start_tab) {
       if (! isset($admin_tabs[$start_tab])) $start_tab = '';
       else if (($admin_tabs[$start_tab][2] == 0) ||
                (($admin_tabs[$start_tab][3] == USER_PERM) &&
                 ($user_perms & $admin_tabs[$start_tab][2])) ||
                (($admin_tabs[$start_tab][3] == MODULE_PERM) &&
                 ($module_perms & $admin_tabs[$start_tab][2])) ||
                (($admin_tabs[$start_tab][3] == CUSTOM_PERM) &&
                 ($custom_perms & $admin_tabs[$start_tab][2])))
          $initial_cover = $admin_tabs[$start_tab][4];
       else $start_tab = '';
    }
    if (! $start_tab) {
       if ($show_dashboard) $start_tab = 'home';
       else $start_tab = 'other';
    }
    $screen->start_iframe_tab_row($start_tab,$initial_cover);
    reset($admin_tab_order);
    end($admin_tab_order);   $last_tab = key($admin_tab_order);
    $first_tab = true;
    foreach ($admin_tab_order as $index => $tab_name) {
       if (($admin_tabs[$tab_name][2] == 0) ||
           (($admin_tabs[$tab_name][3] == USER_PERM) &&
            ($user_perms & $admin_tabs[$tab_name][2])) ||
           (($admin_tabs[$tab_name][3] == MODULE_PERM) &&
            ($module_perms & $admin_tabs[$tab_name][2])) ||
           (($admin_tabs[$tab_name][3] == CUSTOM_PERM) &&
            ($custom_perms & $admin_tabs[$tab_name][2]))) {
          $tab_sequence = 0;
          if ($first_tab) {
             $tab_sequence |= FIRST_TAB;   $first_tab = false;
          }
          if ($index == $last_tab) $tab_sequence |= LAST_TAB;
          $screen->add_iframe_tab($tab_name,$admin_tabs[$tab_name][0],
             $admin_tabs[$tab_name][1],$admin_tabs[$tab_name][4],
             $admin_tabs[$tab_name][6],$admin_tabs[$tab_name][5],$tab_sequence);
       }
    }
    $screen->end_iframe_tab_row();
    $screen->end_body();
}

function display_old_admin_screen()
{
    global $login_cookie,$shopping_cart,$catalog_site,$cms_site,$cpanel_base;
    global $google_email,$siteid,$cms_cookie,$cms_url,$admin_path;
    global $enable_punch_list,$enable_support_requests,$enable_multisite;

    get_user_perms($user_perms,$module_perms,$custom_perms);

    if (isset($cms_url) && ini_get('suhosin.cookie.encrypt') &&
        (strpos(ini_get('suhosin.cookie.plainlist'),$cms_cookie) === false))
       $use_cms_button = true;
    else $use_cms_button = false;
    if (! isset($enable_punch_list)) $enable_punch_list = false;
    if (! isset($enable_support_requests)) $enable_support_requests = false;
    if (! isset($enable_multisite)) $enable_multisite = false;

    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_other_body');
    $screen->add_style_sheet('admin.css');
    $screen->add_script_file('admin.js');
    if (! $cms_site) {
       $screen->add_script_file('adminusers.js');
       if ($user_perms & IMPORT_DATA_BUTTON_PERM)
          $screen->add_script_file('importdata.js');
       if ($user_perms & EXPORT_DATA_BUTTON_PERM)
          $screen->add_script_file('exportdata.js');
    }
    if ($shopping_cart)
       $screen->add_script_file('cartconfig.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $head_block = "<script type=\"text/javascript\">\n" .
                  "      login_cookie = '".$login_cookie."';\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix = '../cartengine/';\n";
    if ($use_cms_button)
       $head_block .= "       cms_url = '".$cms_url."';\n";
    $head_block .= "      sysconfig_dialog_width = ";
    if ($shopping_cart) $head_block .= '580';
    else if ($catalog_site) $head_block .= '650';
    else $head_block .= '650';
    $head_block .= ";\n";
    $head_block .= '      sysconfig_dialog_height = ';
    if ($shopping_cart) $head_block .= '430';
    else if ($catalog_site) $head_block .= '480';
    else $head_block .= '480';
    $head_block .= ";\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
    if ($onload_function = get_form_field('onload'))
       $screen->set_onload_function($onload_function);
    $screen->set_body_id('old_admin');
    $screen->set_help('old_admin');
    $screen->start_body();
    if (isset($siteid) && $siteid) {
       $screen->write("    <div class=\"info_box\">\n");
       $screen->write("      <iframe width=\"100%\" height=\"100%\" frameborder=\"0\"\n");
       $screen->write("       src=\"https://www.inroads.us/admin/support.php?" .
                      "cmd=getsitestatus&siteid=".$siteid."\"></iframe>\n");
       $screen->write("    </div>\n");
    }
    $screen->set_button_width(165);
    if (function_exists('custom_start_admin_buttons'))
       custom_start_admin_buttons($screen);
    $screen->start_button_column();
    if (! $cms_site) {
       if ($user_perms & ADMIN_USERS_BUTTON_PERM)
          $screen->add_button('Admin Users','images/AdminUsers.png',
                              'admin_users();');
       if ($user_perms & SYSTEM_CONFIG_BUTTON_PERM)
          $screen->add_button('System Config','images/AdminUsers.png',
                              'system_config();');
       if (($shopping_cart) && ($user_perms & CART_CONFIG_BUTTON_PERM))
          $screen->add_button('Cart Config','images/AdminUsers.png',
                              'cart_config();');
       if ($enable_multisite && ($user_perms & WEB_SITES_BUTTON_PERM))
          $screen->add_button('Web Sites','images/AdminUsers.png',
                              'web_sites()');
       if ($user_perms & IMPORT_DATA_BUTTON_PERM)
          $screen->add_button('Import Data','images/ImportData.png',
                              'import_data();');
       if ($user_perms & EXPORT_DATA_BUTTON_PERM)
          $screen->add_button('Export Data','images/ImportData.png',
                              'export_data();');
    }
/*
    if (isset($cpanel_base) && ($user_perms & CPANEL_BUTTON_PERM))
       $screen->add_button('Site Config','images/AdminUsers.png',
                           'open_cpanel()');
*/
    if (isset($google_email) && ($user_perms & GOOGLE_BUTTON_PERM))
       $screen->add_button('Google Analytics','images/AdminUsers.png',
                           'open_google(true)');
    if ($enable_punch_list && ($user_perms & TICKETS_BUTTON_PERM))
       $screen->add_button('Punch List','images/Tickets.png','open_tickets()');
    else if ($enable_support_requests && ($user_perms & TICKETS_BUTTON_PERM))
       $screen->add_button('Support Requests','images/Tickets.png',
                           'open_tickets()');
    if ($use_cms_button && ($module_perms & CMS_TAB_PERM))
       $screen->add_button('CMS','images/AdminUsers.png','launch_cms();');
    if (function_exists('custom_admin_buttons'))
       custom_admin_buttons($screen,$user_perms,$module_perms,$custom_perms);
    require_once '../engine/modules.php';
    call_module_event('admin_buttons',array(&$screen,$module_perms)); /* remove when no longer used */
    call_module_event('display_custom_buttons',array('admin',&$screen,$db));
    $screen->end_button_column();
    if (isset($siteid) && $siteid) {
       $screen->write("<iframe width=\"100%\" height=\"100%\" frameborder=\"0\"\n");
       $screen->write("         src=\"https://www.inroads.us/siteadmin/news.php?siteid=" .
                      $siteid."\"></iframe>\n");
    }
    $screen->end_body();
}

function admin_config()
{
    global $shopping_cart,$catalog_site,$cpanel_base,$enable_multisite;

    get_user_perms($user_perms,$module_perms,$custom_perms);
    if (! isset($enable_multisite)) $enable_multisite = false;

    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_screen_body');
    $screen->enable_ajax();
    $screen->add_style_sheet('admin.css');
    $screen->add_script_file('admin.js');
    if ($shopping_cart)
       $screen->add_script_file('cartconfig.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix = '../cartengine/';\n";
    $head_block .= '      sysconfig_dialog_width = ';
    if ($shopping_cart) $head_block .= '580';
    else if ($catalog_site) $head_block .= '650';
    else $head_block .= '650';
    $head_block .= ";\n";
    $head_block .= '      sysconfig_dialog_height = ';
    if ($shopping_cart) $head_block .= '430';
    else if ($catalog_site) $head_block .= '480';
    else $head_block .= '480';
    $head_block .= ";\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
    $screen->set_body_id('admin_config');
    $screen->set_help('admin_config');
    $screen->start_body();
    $screen->set_button_width(165);
    $screen->start_button_column();
    if ($user_perms & SYSTEM_CONFIG_BUTTON_PERM)
       $screen->add_button('System Config','images/AdminUsers.png',
                           'system_config();');
    if (($shopping_cart) && ($user_perms & CART_CONFIG_BUTTON_PERM))
       $screen->add_button('Cart Config','images/AdminUsers.png',
                           'cart_config();');
/*
    if (isset($cpanel_base) && ($user_perms & CPANEL_BUTTON_PERM))
       $screen->add_button('Site Config','images/AdminUsers.png',
                           'open_cpanel()');
*/
    if ($enable_multisite && ($user_perms & WEB_SITES_BUTTON_PERM))
       $screen->add_button('Web Sites','images/AdminUsers.png',
                           'web_sites()');
    if (function_exists('custom_admin_config_buttons'))
       custom_admin_config_buttons($screen,$user_perms,$module_perms,
                                   $custom_perms);
    $screen->end_button_column();
    $screen->end_body();
}

function admin_data()
{
    global $shopping_cart,$cms_site;

    get_user_perms($user_perms,$module_perms,$custom_perms);

    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_screen_body');
    $screen->enable_ajax();
    $screen->add_style_sheet('admin.css');
    $screen->add_script_file('admin.js');
    if (! $cms_site) {
       $screen->add_script_file('importdata.js');
       $screen->add_script_file('exportdata.js');
    }
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    if ($shopping_cart) {
       $head_block = "<script type=\"text/javascript\">\n";
       $head_block .= "      script_prefix = '../cartengine/';\n";
       $head_block .= "    </script>";
       $screen->add_head_line($head_block);
    }
    $screen->set_body_id('admin_data');
    $screen->set_help('admin_data');
    $screen->start_body();
    $screen->set_button_width(165);
    $screen->start_button_column();
    if ($user_perms & IMPORT_DATA_BUTTON_PERM)
       $screen->add_button('Import Data','images/ImportData.png',
                           'import_data();');
    if ($user_perms & EXPORT_DATA_BUTTON_PERM)
       $screen->add_button('Export Data','images/ImportData.png',
                           'export_data();');
    $screen->end_button_column();
    $screen->end_body();
}

function admin_other()
{
    global $shopping_cart,$google_email,$siteid,$cms_cookie,$cms_url;
    global $enable_punch_list,$enable_support_requests;

    get_user_perms($user_perms,$module_perms,$custom_perms);

    if (isset($cms_url) && ini_get('suhosin.cookie.encrypt') &&
        (strpos(ini_get('suhosin.cookie.plainlist'),$cms_cookie) === false))
       $use_cms_button = true;
    else $use_cms_button = false;
    if (! isset($enable_punch_list)) $enable_punch_list = false;
    if (! isset($enable_support_requests)) $enable_support_requests = false;

    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_other_body');
    $screen->add_style_sheet('admin.css');
    $screen->add_script_file('admin.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    if ($onload_function = get_form_field('onload'))
       $screen->set_onload_function($onload_function);
    if ($shopping_cart) {
       $head_block = "<script type=\"text/javascript\">\n";
       $head_block .= "      script_prefix = '../cartengine/';\n";
       $head_block .= '    </script>';
       $screen->add_head_line($head_block);
    }
    $screen->set_body_id('admin_other');
    $screen->set_help('admin_other');
    $screen->start_body();
    if (isset($siteid) && $siteid) {
       $screen->write("    <div class=\"info_box\">\n");
       $screen->write("      <iframe width=\"100%\" height=\"100%\" frameborder=\"0\"\n");
       $screen->write("       src=\"https://www.inroads.us/admin/support.php?" .
                      "cmd=getsitestatus&siteid=".$siteid."\"></iframe>\n");
       $screen->write("    </div>\n");
    }
    $screen->set_button_width(165);
    if (function_exists('custom_start_admin_buttons'))
       custom_start_admin_buttons($screen);
    $screen->start_button_column();
    if (isset($google_email) && ($user_perms & GOOGLE_BUTTON_PERM))
       $screen->add_button('Google Analytics','images/AdminUsers.png',
                           'open_google(true)');
    if ($enable_punch_list && ($user_perms & TICKETS_BUTTON_PERM))
       $screen->add_button('Punch List','images/Tickets.png','open_tickets()');
    else if ($enable_support_requests && ($user_perms & TICKETS_BUTTON_PERM))
       $screen->add_button('Support Requests','images/Tickets.png',
                           'open_tickets()');
    if ($use_cms_button && ($module_perms & CMS_TAB_PERM))
       $screen->add_button('CMS','images/AdminUsers.png','launch_cms();');
    if (function_exists('custom_admin_buttons'))
       custom_admin_buttons($screen,$user_perms,$module_perms,$custom_perms);
    require_once '../engine/modules.php';
    call_module_event('admin_buttons',array(&$screen,$module_perms)); /* remove when no longer used */
    call_module_event('display_custom_buttons',array('admin_other',&$screen,$db));
    $screen->end_button_column();
    if (isset($siteid) && $siteid) {
       $screen->write("<iframe width=\"100%\" height=\"100%\" frameborder=\"0\"\n");
       $screen->write("         src=\"https://www.inroads.us/siteadmin/news.php?siteid=" .
                      $siteid."\"></iframe>\n");
    }
    $screen->end_body();
}

$config_fields = array();
if (empty($enable_multisite)) {
   $config_fields['admin_email'] = 'Admin E-Mail Address';
   $config_fields['domain_name'] = 'Domain Name';
   $config_fields['website_hostname'] = 'Web Site Hostname';
   $config_fields['email_logo'] = 'E-Mail Logo';
}
$config_fields['activate_min'] = 'Minify Site Files';
if (! $cms_site) {
   if (empty($enable_multisite)) {
      $config_fields['map_company'] = '&nbsp;&nbsp;&nbsp;Company Name';
      $config_fields['map_address1'] = 'Address';
      $config_fields['map_address2'] = 'Address2';
      $config_fields['map_city'] = 'City';
      $config_fields['map_state'] = 'State';
      $config_fields['map_zip'] = 'Zip';
      $config_fields['map_country'] = 'Country';
      $config_fields['map_phone'] = 'Phone';
      $config_fields['map_fax'] = 'Fax';
   }
   if (empty($enable_multisite)) {
      $config_fields['map_latitude'] = 'Latitude';
      $config_fields['map_longitude'] = 'Longitude';
      $config_fields['map_zoom'] = 'Zoom';
      $config_fields['map_streetview'] = 'Street View';
      $config_fields['map_sv_latitude'] = 'Latitude';
      $config_fields['map_sv_longitude'] = 'Longitude';
      $config_fields['map_pitch'] = 'Pitch';
      $config_fields['map_yaw'] = 'Yaw';
   }
}
if ($shopping_cart || $catalog_site || $enable_manage_images) {
   $config_fields['image_color'] = 'Fill Color';
   if ((! isset($main_image_sizes)) || in_array('small',$main_image_sizes))
      $config_fields['image_size_small'] = 'Small Size';
   if ((! isset($main_image_sizes)) || in_array('medium',$main_image_sizes))
      $config_fields['image_size_medium'] = 'Medium Size';
   if ((! isset($main_image_sizes)) || in_array('large',$main_image_sizes))
      $config_fields['image_size_large'] = 'Large Size';
   if ((! isset($main_image_sizes)) || in_array('zoom',$main_image_sizes))
      $config_fields['image_size_zoom'] = 'Zoom Size';
   $config_fields['image_crop_ratio'] = 'Crop Ratio';
}
if ($enable_alt_images) {
   $config_fields['attrimage_color'] = 'Fill Color';
   if ((! isset($alt_image_sizes)) || in_array('small',$alt_image_sizes))
      $config_fields['attrimage_size_small'] = 'Small Size';
   if ((! isset($alt_image_sizes)) || in_array('medium',$alt_image_sizes))
      $config_fields['attrimage_size_medium'] = 'Medium Size';
   if ((! isset($alt_image_sizes)) || in_array('large',$alt_image_sizes))
      $config_fields['attrimage_size_large'] = 'Large Size';
   $config_fields['attrimage_crop_ratio'] = 'Crop Ratio';
}
if (function_exists('setup_custom_config_fields'))
   setup_custom_config_fields($config_fields);

$config_tab_labels = array();
$config_tab_order = array();

function add_config_tab($new_tab,$label,$before_order=null)
{
    global $config_tab_labels,$config_tab_order;

    $config_tab_labels[$new_tab] = $label;
    if ($before_order == null) $config_tab_order[] = $new_tab;
    else {
       $insert_pos = -1;
       foreach ($config_tab_order as $index => $tab_name) {
          if ($tab_name == $before_order) {
             $insert_pos = $index;   break;
          }
       }
       if ($insert_pos == -1) $config_tab_order[] = $new_tab;
       else array_splice($config_tab_order,$insert_pos,0,array($new_tab));
    }
}

function remove_config_tab($tab)
{
    global $config_tab_labels,$config_tab_order;

    unset($config_tab_labels[$tab]);
    foreach ($config_tab_order as $index => $tab_name) {
       if ($tab_name == $tab) {
          unset($config_tab_order[$index]);   break;
       }
    }
}

function config_record_definition()
{
    $config_record = array();
    $config_record['config_name'] = array('type' => CHAR_TYPE);
    $config_record['config_name']['key'] = true;
    $config_record['config_value'] = array('type' => CHAR_TYPE);
    return $config_record;
}

function system_config()
{
    global $config_fields,$shopping_cart,$catalog_site,$cms_site;
    global $config_tab_labels,$config_tab_order,$cms_base_url,$image_dir;
    global $product_label,$category_label,$enable_manage_images;
    global $use_dynamic_images,$image_subdir_prefix,$enable_alt_images;
    global $main_image_label,$main_image_sizes,$alt_image_label;
    global $alt_image_sizes,$attr_image_buttons_row_height;
    global $manage_image_buttons,$enable_schedule,$enable_multisite;
    global $cloudflare_site,$enable_product_callouts;

    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    if (! isset($manage_image_buttons)) $manage_image_buttons = true;
    if (! isset($enable_product_callouts)) $enable_product_callouts = false;
    $db = new DB;
    $config_values = load_config_values($db);
    if ($config_values === null) return;
    require_once '../engine/modules.php';

    $dialog = new Dialog;
    $dialog->add_script_file('../engine/sarissa.js');
    $dialog->add_script_file('admin.js');
    $dialog->add_script_file('utility.js');
    if ($enable_schedule) $dialog->add_script_file('schedule-admin.js');
    if (! $shopping_cart) {
       $dialog->enable_aw();
       $dialog->enable_ajax();
       $dialog->add_style_sheet('countrystate.css');
       $dialog->add_script_file('countrystate.js');
    }
    if ($enable_product_callouts) $dialog->add_script_file('callouts.js');
    $dialog->add_style_sheet('admin.css');
    $dialog->add_style_sheet('utility.css');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart)
       $head_block .= "       script_prefix = '../cartengine/';\n";
    if (isset($cms_base_url))
       $head_block .= "       cms_url = '".$cms_base_url."';\n";
    if ($use_dynamic_images)
       $head_block .= "       use_dynamic_images = true;\n";
    if (isset($image_subdir_prefix))
       $head_block .= "       image_subdir_prefix = " .
                      $image_subdir_prefix.";\n";
    if (get_form_field("insidecms")) {
       $head_block .= "       inside_cms = true;\n";
       $dialog->set_onload_function('system_config_onload();');
       $dialog->use_cms_top();
    }
    foreach ($config_fields as $field_name => $prompt)
       $head_block .= "       config_fields['".$field_name."'] = '" .
                      $prompt."';\n";
    $head_block .= "    </script>\n";
    $head_block .= "    <style> .fieldtable { width: 100%; } </style>";
    $dialog->add_head_line($head_block);
    if ($enable_schedule) {
       add_schedule_head_lines($dialog);
       $dialog->enable_calendar();
    }
    if (function_exists('setup_config_dialog'))
       setup_config_dialog($dialog,$db);
    call_module_event('config_head',array(&$dialog,$db)); /* remove when no longer used */
    call_module_event('update_head',array('system_config',&$dialog,$db));
    $dialog->set_body_id('system_config');
    $dialog->set_help('system_config');
    $dialog->start_body('System Config');
    $dialog->set_button_width(143);
    $dialog->start_button_column(false,false,true);
    $dialog->start_bottom_buttons(false);
    $dialog->add_button('Update','images/Update.png','update_config();');
    $dialog->add_button('Cancel','images/Update.png','close_system_config();');
    $dialog->end_bottom_buttons();
    if ((($shopping_cart || $catalog_site) &&
         file_exists($image_dir.'/original/')) || $enable_manage_images) {
       $dialog->add_button_separator('image_buttons_row',20);
       if ($manage_image_buttons)
          $dialog->add_button('Manage Images','images/AdminUsers.png',
                              'manage_images();','manage_images',false);
       if (! $use_dynamic_images)
          $dialog->add_button('Update Images','images/EditUser.png',
                              'update_images();','update_images',false);
    }
    if ($enable_alt_images) {
       if (! isset($attr_image_buttons_row_height))
          $attr_image_buttons_row_height = 110;
       $dialog->add_button_separator('attr_image_buttons_row',
                                     $attr_image_buttons_row_height);
       if ($manage_image_buttons)
          $dialog->add_button('Manage Images','images/AdminUsers.png',
                              'manage_attr_images();','manage_attr_images',false);
       if (! $use_dynamic_images)
          $dialog->add_button('Update Images','images/EditUser.png',
                              'update_attr_images();','update_attr_images',false);
    }
    if ($enable_product_callouts) {
       require_once 'callouts.php';
       add_callout_config_buttons($dialog);
    }
    if (! $shopping_cart) {
       $dialog->add_button_separator('country_buttons_row',20);
       $dialog->add_button('Add Country','images/AddUser.png',
                           'add_country();','add_country',false);
       $dialog->add_button('Edit Country','images/EditUser.png',
                           'edit_country();','edit_country',false);
       $dialog->add_button('Delete Country','images/DeleteUser.png',
                           'delete_country();','delete_country',false);
    }
    if ($enable_schedule) add_schedule_buttons($dialog);
    if (function_exists('add_custom_config_buttons'))
       add_custom_config_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('admin.php','SystemConfig');
    foreach ($config_values as $config_name => $config_value) {
       if (! isset($config_fields[$config_name]))
          $dialog->add_hidden_field($config_name,$config_value);
    }
    if (! $dialog->skin) $dialog->start_field_table();
    if (! $cms_site) {
       add_config_tab('settings','Settings');
       if ($shopping_cart || $catalog_site || $enable_manage_images)
          add_config_tab('images','Images');
       if (empty($enable_multisite)) add_config_tab('map','Contact Us');
       if (! $shopping_cart) {
          add_config_tab('countries','Countries');
          add_config_tab('states','States');
       }
       if ($enable_schedule) add_config_tab('schedule','Schedule');
       call_module_event('add_config_tabs',array($db));
       if (function_exists('setup_config_tabs')) setup_config_tabs($db);
       $dialog->start_tab_section('config_tab_section');
       $dialog->start_tab_row('settings_tab','settings_content');
       reset($config_tab_order);
       end($config_tab_order);   $last_tab = key($config_tab_order);
       $first_tab = true;
       foreach ($config_tab_order as $index => $tab_name) {
          $tab_sequence = 0;
          if ($first_tab) {
             $tab_sequence |= FIRST_TAB;   $first_tab = false;
          }
          if ($index == $last_tab) $tab_sequence |= LAST_TAB;
          $dialog->add_tab($tab_name.'_tab',$config_tab_labels[$tab_name],
                           'config_'.$tab_name.'_tab',$tab_name.'_content',
                           'change_tab',true,null,$tab_sequence);
       }
       $dialog->end_tab_row('config_tab_row_middle');
       $dialog->start_tab_content('settings_content',true);
       $dialog->start_field_table();
    }
    $dialog->write("<tr style=\"height: 5px;\"><td colspan=\"2\"></td></tr>\n");
    foreach ($config_fields as $field_name => $prompt) {
       if (substr($field_name,0,4) == 'map_') continue;
       if (substr($field_name,0,6) == 'image_') continue;
       if (substr($field_name,0,10) == 'attrimage_') continue;
       if (isset($config_values[$field_name]))
          $config_value = $config_values[$field_name];
       else $config_value = '';
       if (function_exists('process_config_field')) {
          if (process_config_field($dialog,$field_name,$prompt,
                                   $config_value,$db))
             continue;
       }
       if ($field_name == 'email_logo')
          $dialog->add_browse_row($prompt.':',$field_name,$config_values,
                                  30,'system_config','',false,false,true,true);
       else if ($field_name == 'activate_min') {
          $dialog->start_row($prompt,'middle');
          $dialog->add_checkbox_field($field_name,'',$config_value);
          $dialog->end_row();
       }
       else $dialog->add_edit_row($prompt.':',$field_name,$config_value,30);
    }
    if (isset($cloudflare_site)) {
       require_once '../admin/cloudflare-admin.php';
       display_cloudflare_config($dialog);
    }
    $dialog->write("<tr style=\"height: 5px;\"><td colspan=\"2\"></td></tr>\n");

    if ($shopping_cart || $catalog_site || $enable_manage_images) {
       if (! isset($product_label)) $product_label = 'Product';
       if (! isset($category_label)) $category_label = 'Category';
       if (! isset($main_image_label))
          $main_image_label = $category_label.'/'.$product_label.' Images';
       if (! isset($alt_image_label)) $alt_image_label = 'Attribute Images';
       $dialog->end_field_table();
       $dialog->end_tab_content();

       $dialog->start_tab_content('images_content',false);
       $dialog->write("<table id=\"fieldtable\" class=\"fieldtable\" " .
                      "border=\"0\" cellpadding=\"4\" cellspacing=\"0\" " .
                      "align=\"center\">\n");
       $dialog->write("<tr style=\"height: 5px;\"><td colspan=\"2\"></td></tr>\n");
       $show_streetview = false;
       foreach ($config_fields as $field_name => $prompt) {
          if ((substr($field_name,0,6) != 'image_') &&
              (substr($field_name,0,10) != 'attrimage_')) continue;
          if (isset($config_values[$field_name]))
             $config_value = $config_values[$field_name];
          else $config_value = '';
          if (function_exists('process_config_field')) {
             if (process_config_field($dialog,$field_name,$prompt,
                                      $config_value,$db))
                continue;
          }
          if (($field_name == 'image_color') &&
              ($shopping_cart || $catalog_site)) {
             $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt\" ");
             $dialog->write("style=\"text-align:center;\"><i><u>" .
                            $main_image_label."</u></i></td></tr>\n");
          }
          else if ($field_name == 'attrimage_color') {
             $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt\" ");
             $dialog->write("style=\"text-align:center;\"><i><u>" .
                            $alt_image_label."</u></i></td></tr>\n");
          }
          if ((substr($field_name,0,11) == 'image_size_') ||
              (substr($field_name,0,15) == 'attrimage_size_')) {
             if ($config_value == '') $size_values = array('','');
             else $size_values = explode('|',$config_value);
             $dialog->start_row($prompt.':','middle');
             $dialog->write("Width:&nbsp;<input type=\"text\" class=\"text\" name=\"" .
                            $field_name."_width\" size=5 value=\"".$size_values[0] .
                            "\">&nbsp;&nbsp;&nbsp;\n");
             $dialog->write("Height:&nbsp;<input type=\"text\" class=\"text\" name=\"" .
                            $field_name."_height\" size=5 value=\"".$size_values[1] .
                            "\">\n");
             $dialog->end_row();
             continue;
          }
          if (($field_name == 'image_crop_ratio') ||
              ($field_name == 'attrimage_crop_ratio')) {
             $dialog->start_row($prompt.':','middle');
             $dialog->write("<input type=\"text\" class=\"text\" name=\"" .
                            $field_name."\" size=\"5\" value=\"");
             write_form_value($config_value);
             $dialog->write("\"> (Width:Height)\n");
             $dialog->end_row();
             continue;
          }
          $dialog->add_edit_row($prompt.':',$field_name,$config_value,30);
       }
       if ($enable_product_callouts) {
          require_once 'callouts.php';
          display_callout_config($dialog,$config_values);
       }
       $dialog->write("<tr style=\"height: 5px;\"><td colspan=\"2\">" .
                      "</td></tr>\n");
    }

    if (! $cms_site) {
       $dialog->end_field_table();
       $dialog->end_tab_content();

       $dialog->start_tab_content('map_content',false);
       $dialog->start_field_table();
       $dialog->write("<tr style=\"height: 5px;\"><td colspan=\"2\"></td></tr>\n");

       $show_streetview = false;
       foreach ($config_fields as $field_name => $prompt) {
          if (substr($field_name,0,4) != 'map_') continue;
          if (isset($config_values[$field_name]))
             $config_value = $config_values[$field_name];
          else $config_value = '';
          if (function_exists('process_config_field')) {
             if (process_config_field($dialog,$field_name,$prompt,
                                      $config_value,$db))
                continue;
          }
          if ($field_name == 'map_state') {
             $dialog->start_row('State:');
             $dialog->add_input_field('map_state',$config_value,10);
             $dialog->write("&nbsp;&nbsp;&nbsp;\n");
             continue;
          }
          else if ($field_name == 'map_zip') {
             $dialog->write("        <span class=\"fieldprompt\">Zip:&nbsp;</span><input type=\"text\" " .
                            "class=\"text\" name=\"map_zip\" size=\"9\" value=\"" .
                            $config_value."\">&nbsp;&nbsp;&nbsp;\n");
             $dialog->write("</td></tr>\n");
             continue;
          }
          else if ($field_name == 'map_country') {
             $dialog->start_row('Country:','middle');
             $dialog->write("<select name=\"map_country\" id=\"map_country\" " .
                            "class=\"select\" style=\"width: 204px;\">\n");
             load_country_list($config_value);
             $dialog->end_choicelist();
             $dialog->end_row();
             continue;
          }
          else if ($field_name == 'map_latitude') {
             $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt\" ");
             $dialog->write("style=\"padding-right: 180px;\"><i><u>Google Map Data</u></i></td></tr>\n");
             $dialog->start_row('Latitude:','middle');
             $dialog->add_input_field('map_latitude',$config_value,7);
             $dialog->write("&nbsp;&nbsp;&nbsp;\n");
             continue;
          }
          else if ($field_name == 'map_longitude') {
             $dialog->write("<span class=\"fieldprompt\">Longitude:&nbsp;</span>" .
                            "<input type=\"text\" class=\"text\" name=\"map_longitude\" " .
                            "size=\"7\" value=\"".$config_value."\">\n");
             $dialog->write("&nbsp;&nbsp;<a href=\"#\" class=\"lookup_link\" onClick=\"" .
                            "lookup_address(); return false;\">Lookup</a>");
             $dialog->write("</td></tr>\n");
             continue;
          }
          else if ($field_name == 'map_zoom') {
             $dialog->start_row('Zoom:','middle');
             $dialog->add_input_field('map_zoom',$config_value,7);
             $dialog->write("&nbsp;&nbsp;&nbsp;\n");
             continue;
          }
          else if ($field_name == 'map_streetview') {
             $dialog->add_checkbox_field('map_streetview','Show Street View',
                                         $config_value,'toggle_street_view();');
             $dialog->write("&nbsp;&nbsp;<a href=\"#\" class=\"lookup_link\" " .
                            "onClick=\"return find_address(this);\" " .
                            "target=\"_blank\">Find Address</a>");
             $dialog->write("</td></tr>\n");
             if ($config_value) $show_streetview = true;
             continue;
          }
          else if ($field_name == 'map_sv_latitude') {
             $dialog->write("<tr id=\"sv_row_0\"");
             if (! $show_streetview) $dialog->write(" style=\"display: none;\"");
             $dialog->write("><td colspan=\"2\" class=\"fieldprompt\" ");
             $dialog->write("style=\"padding-right: 180px;\"><i><u>Street View Data</u></i></td></tr>\n");
             $dialog->write("<tr id=\"sv_row_1\"");
             if (! $show_streetview) $dialog->write(" style=\"display: none;\"");
             $dialog->write("><td colspan=\"2\">\n");
             $dialog->write("<table cellspacing=\"4\" cellpadding=\"0\">\n");
             $dialog->write("<tr><td class=\"fieldprompt\" style=\"width: 98px;\">Latitude:</td>\n" .
                            "<td><input type=\"text\" class=\"text\" name=\"map_sv_latitude\" " .
                            "size=\"7\" value=\"".$config_value."\"></td>\n");
             continue;
          }
          else if ($field_name == 'map_sv_longitude') {
             $dialog->write("<td class=\"fieldprompt\">&nbsp;&nbsp;&nbsp;Longitude:</td>\n" .
                            "<td><input type=\"text\" class=\"text\" name=\"map_sv_longitude\" " .
                            "size=\"7\" value=\"".$config_value."\">\n");
             $dialog->write("</td></tr>\n");
             $dialog->write("<tr style=\"height: 4px;\"><td colspan=\"4\"></td></tr>\n");
             continue;
          }
          else if ($field_name == 'map_pitch') {
             $dialog->start_row('Pitch:');
             $dialog->add_input_field('map_pitch',$config_value,7);
             $dialog->write("</td>\n");
             continue;
          }
          else if ($field_name == 'map_yaw') {
             $dialog->write("<td class=\"fieldprompt\">Yaw:</td>\n" .
                            "<td><input type=\"text\" class=\"text\" name=\"map_yaw\" " .
                            "size=\"7\" value=\"".$config_value."\">\n");
             $dialog->write("</td></tr>\n</table></td></tr>\n");
             continue;
          }
          $dialog->add_edit_row($prompt.':',$field_name,$config_value,30);
       }

       $dialog->write("<tr style=\"height: 5px;\"><td colspan=\"2\"></td></tr>\n");
       $dialog->end_field_table();
       $dialog->end_tab_content();

       if (! $shopping_cart) {
          $dialog->start_tab_content('countries_content',false);
          if ($dialog->skin)
             $dialog->write("        <div class=\"fieldSection\">\n");
          else $dialog->write("        <div style=\"padding: 4px;\">\n");
          $dialog->write("        <script>create_countries_grid();</script>\n");
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();
   
          $dialog->start_tab_content('states_content',false);
          if ($dialog->skin)
             $dialog->write("        <div class=\"fieldSection\">\n");
          else $dialog->write("        <div style=\"padding: 4px;\">\n");
          $dialog->write("        <script>create_states_grid();</script>\n");
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();
       }

       if ($enable_schedule) add_schedule_tab_content($db,$dialog);

       call_module_event('config_tabs',array($config_values,&$dialog,$db));

       if (function_exists('display_custom_config_tab_sections'))
          display_custom_config_tab_sections($dialog,$db,$config_values);
   
       $dialog->end_tab_section();
    }
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_admin_image($new_image)
{
    global $docroot,$image_dir,$attr_image_dir,$images_parent_type;

    require_once 'image.php';

    $filename = get_form_field('Filename');
    $images_parent_type = get_form_field('ParentType');
    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    if ($images_parent_type == 3) {
       $original_filename = $image_dir.'/callouts/'.$filename;
       $single_size = 'callout';
    }
    else {
       $new_image_dir = get_form_field('ImageDir');
       if ($new_image_dir) $image_dir = $docroot.$new_image_dir;
       $original_filename = $image_dir.'/original/'.$filename;
       $single_size = null;
    }
    $config_values = load_config_values();
    if ($config_values === null) return;
    else if (! $config_values) {
       http_response(406,'Image Size Information has not been configured');
       return;
    }
    if (! process_image($filename,$original_filename,null,null,null,null,
                        $config_values,false,true,$single_size))
       return;

    if ($new_image) {
       http_response(201,'Processed Uploaded Image');
       log_activity('Processed Uploaded '.image_type_name($images_parent_type) .
                    ' Image '.$filename);
    }
    else {
       http_response(201,'Updated Image');
       log_activity('Updated '.image_type_name($images_parent_type) .
                    ' Image '.$filename);
    }
}

function resize_admin_image()
{
    require_once 'image.php';

    $filename = get_form_field('Filename');
    $resize = get_form_field('Resize');
    $config_values = load_config_values();
    if ($config_values === null) return;
    else if (! $config_values) {
       http_response(406,'Image Size Information has not been configured');
       return;
    }
    if (function_exists('custom_resize_admin_image') &&
        custom_resize_admin_image($filename,$resize,$config_values)) {}
    else if (process_resize_admin_image($filename,$filename,$resize,
                                        $config_values,'image_size_')) {
       http_response(201,'Resized Image');
       log_activity('Resized Image '.$filename);
    }
}

function move_admin_image()
{
    global $image_dir,$image_subdir_prefix,$new_dir_perms;

    if ((! isset($image_subdir_prefix)) || (! $image_subdir_prefix)) {
       http_response(201,'Image Move Not Needed');   return;
    }
    $filename = get_form_field('Filename');

    if (file_exists($image_dir.'/original/'))
       $basedir = $image_dir.'/original/';
    else $basedir = $image_dir.'/large/';
    $image_subdir = $basedir.substr($filename,0,$image_subdir_prefix);
    $old_filename = $basedir.$filename;
    $new_filename = $image_subdir.'/'.$filename;
    if (! file_exists($image_subdir)) {
       mkdir($image_subdir);
       if (isset($new_dir_perms) && (! chmod($image_subdir,$new_dir_perms))) {
          $error_msg = 'Unable to set permissions on '.$image_subdir;
          log_error($error_msg);   http_response(422,$error_msg);   return;
       }
    }
    if (! rename($old_filename,$new_filename)) {
       $error_msg = 'Unable to rename '.$old_filename.' to '.$new_filename;
       log_error($error_msg);   http_response(422,$error_msg);   return;
    }

    http_response(201,'Moved Image');
    log_activity('Moved Image '.$old_filename.' to '.$new_filename);
}

function send_xml_response($status,$message)
{
    print "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    print "<xmlresponse>\n";
    print "   <status>" . $status . "</status>\n";
    print '   <message>';
    write_xml_data($message);
    print "</message>\n";
    print "</xmlresponse>\n";
}

function load_dir_images($basedir,$image_files)
{
    $images_dir = @opendir($basedir);
    if ($images_dir) {
       while (($filename = readdir($images_dir)) !== false) {
          if ($filename[0] == '.') continue;
          if (is_dir($basedir.$filename))
             $image_files = load_dir_images($basedir.$filename.'/',
                                            $image_files);
          else $image_files[] = $filename;
       }
       closedir($images_dir);
    }
    return $image_files;
}

function update_images($bg_data)
{
    global $image_dir,$attr_image_dir,$images_parent_type,$max_image_count;
    global $image_subdir_prefix;

    require_once 'image.php';

    set_time_limit(0);
    ignore_user_abort(true);
    $config_values = load_config_values();
    if ($config_values === null) return;
    else if (! $config_values) {
       $error_msg = 'Image Size Information has not been configured';
       if (! $bg_data) http_response(404,$error_msg);
       log_error($error_msg);   return;
    }
    if (! isset($max_image_count)) $max_image_count = MAX_IMAGE_COUNT;
    if (! isset($image_subdir_prefix)) $image_subdir_prefix = null;

    if ($bg_data) {
       $data_fields = explode('|',rawurldecode($bg_data));
       $image_page = $data_fields[0];
       $num_pages = $data_fields[1];
       $images_parent_type = $data_fields[2];
    }
    else {
       $images_parent_type = get_form_field('ParentType');
       if ($images_parent_type === null) $images_parent_type = 99;
    }
    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    else if ($images_parent_type == 3) $image_dir .= '/callouts';
    if ($images_parent_type == 3) $single_size = 'callout';
    else $single_size = null;
    $image_type_name = image_type_name($images_parent_type);

    if (function_exists('get_custom_update_image_files'))
       $image_files = get_custom_update_image_files($images_parent_type,
                         $image_dir,$image_type_name);
    else {
       $image_files = array();
       if ($images_parent_type == 3) $basedir = $image_dir.'/';
       else $basedir = $image_dir.'/original/';
       $images_dir = @opendir($basedir);
       if ($images_dir) {
          while (($filename = readdir($images_dir)) !== false) {
             if ($filename[0] == '.') continue;
             if (is_dir($basedir.$filename))
                $image_files = load_dir_images($basedir.$filename.'/',
                                               $image_files);
             else $image_files[] = $filename;
          }
          closedir($images_dir);
       }
    }

    if ((! $bg_data) && (count($image_files) > $max_image_count)) {
       $num_pages = ceil(count($image_files) / $max_image_count);
       $data = '|'.$num_pages.'|'.$images_parent_type;
       for ($image_page = 0;  $image_page < $num_pages;  $image_page++) {
          print 'Page #'.$image_page."\n";  // keep the web server connection alive
          $command = 'admin.php updateimages '.rawurlencode($image_page.$data);
          $process = new Process($command);
          if ($process->return != 0) {
             $error_msg = 'Unable to start image update ('.$process->return.')';
             log_error($error_msg);   send_xml_response(422,$error_msg);
             return;
          }
          $counter = 0;
          while ($process->status()) {
             if ($counter == 500) {
                $process->stop();
                $error_msg = 'Image Update took too long';
                log_error($error_msg);   send_xml_response(422,$error_msg);
                return;
             }
             sleep(1);
             $counter++;
          }
       }
       send_xml_response(201,'Images Updated');
       log_activity('Updated '.$image_type_name.' Images');
       return;
    }

    if ($bg_data) {
       $start_num = ($image_page * $max_image_count) + 1;
       $end_num = ($start_num + $max_image_count);
       log_activity('Starting '.$image_type_name.' Image Update (' .
                    $start_num.'-'.($end_num - 1).')');
    }
    $update_num = 1;
    foreach ($image_files as $filename) {
       if ($bg_data && ($update_num < $start_num)) {
          $update_num++;   continue;
       }
       if ($images_parent_type == 3) $original_filename = $image_dir.'/';
       else {
          $original_filename = $image_dir.'/original/';
          if ($image_subdir_prefix)
             $original_filename .= substr($filename,0,$image_subdir_prefix).'/';
       }
       $original_filename .= $filename;
       if (function_exists('custom_process_image'))
          custom_process_image($filename,$image_dir,$images_parent_type,
                               $image_type_name,$config_values);
       else if (process_image($filename,$original_filename,null,null,null,null,
                         $config_values,false,false,$single_size))
          log_activity('Updated '.$image_type_name.' Image '.$filename);
       $update_num++;
       if ($bg_data && ($update_num == $end_num)) break;
    }

    if ($bg_data) {
       if ($update_num != $end_num) $end_num = $update_num;
       log_activity('Updated '.$image_type_name.' Images ('.$start_num.'-' .
                    ($end_num - 1).')');
    }
    else {
       http_response(201,'Images Updated');
       log_activity('Updated '.$image_type_name.' Images');
    }
}

function get_google_location($address)
{
    $url = 'http://maps.google.com/maps/api/geocode/json?address=' .
           urlencode($address).'&sensor=false&language=en';
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_HEADER,0);
    curl_setopt($ch,CURLOPT_USERAGENT,
                'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.2.8) ' .
                'Gecko/20100722 Firefox/3.6.8 ( .NET CLR 3.5.30729; .NET4.0C)');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    $data = json_decode(curl_exec($ch),true);
    curl_close($ch);
    if (count($data['results']) > 0)
       return $data['results'][0]['geometry']['location'];
    return null;
}

function lookup_address()
{
    $country = get_form_field('map_country');
    if ($country) $country_info = get_country_info($country);
    else $country_info = null;
    if ($country_info) $country_code = $country_info['code'];
    else $country_code = 'US';
    $address = get_form_field('map_address1').', '.get_form_field('map_city').', ' .
               get_form_field('map_state').' '.get_form_field('map_zip').' ' .
               $country_code;
    $location = get_google_location($address);
    if ($location) {
       print 'latitude = '.$location['lat'].';';
       print 'longitude = '.$location['lng'].';';
    }
    else http_response(422,'Unable to lookup address');
}

function update_config()
{
    global $config_fields,$enable_schedule,$cloudflare_site;
    global $enable_product_callouts;

    $db = new DB;
    $config_values = load_config_values($db);
    $db->log_query('delete from config');
    $result = $db->query('delete from config');
    if (! $result) {
       process_error('Database Error: '.$db->error,-1);   return;
    }

    $config_record = config_record_definition();
    foreach ($config_fields as $field_name => $prompt) {
       if (function_exists('update_config_field')) {
          if (update_config_field($db,$field_name)) {
             unset($config_values[$field_name]);   continue;
          }
       }
       $config_record['config_name']['value'] = $field_name;
       if ((substr($field_name,0,11) == 'image_size_') ||
           (substr($field_name,0,15) == 'attrimage_size_')) {
          $width = get_form_field($field_name.'_width');
          $height = get_form_field($field_name.'_height');
          $config_record['config_value']['value'] = $width.'|'.$height;
       }
       else if ($field_name == 'activate_min') {
          $field_value = get_form_field($field_name);
          if (($field_value == 'on') || ($field_value == 1)) $field_value = 1;
          else $field_value = (string) 0;
          $config_record['config_value']['value'] = $field_value;
       }
       else $config_record['config_value']['value'] = get_form_field($field_name);
       if (! $db->insert('config',$config_record)) {
          http_response(422,$db->error);   return;
       }
       if ($config_values && isset($config_values[$field_name]))
          unset($config_values[$field_name]);
    }
    if ($config_values) {
       foreach ($config_values as $config_name => $config_value) {
          $field_value = get_form_field($config_name);
          $config_record['config_name']['value'] = $config_name;
          $config_record['config_value']['value'] = $field_value;
          if (! $db->insert('config',$config_record)) {
             http_response(422,$db->error);   return;
          }
       }
    }

    if ($enable_schedule && (! update_schedule($db))) return;

    if (isset($cloudflare_site)) {
       require_once '../admin/cloudflare-admin.php';
       if (! update_cloudflare_config()) return;
    }

    if (isset($enable_product_callouts)) {
       require_once 'callouts.php';
       if (! update_callout_config($db,$config_record)) return;
    }

    require_once '../engine/modules.php';
    if (module_attached('update_config')) {
       $config_values = load_config_values($db);
       if ($config_values === null) return;
       if (! call_module_event('update_config',
                               array($config_values,$config_record,$db))) {
          http_response(422,get_module_errors());   return;
       }
    }

    http_response(201,'Config Updated');
    log_activity('Updated System Config');
}
/*
function open_cpanel()
{
    $dialog = new Dialog;
    $style_block = "<style>\n" .
                   "      .dialog_body { margin: 0px; padding: 0px;\n" .
                   "                     overflow-x: hidden; overflow-y: hidden; }\n";
    if ($dialog->skin) 
       $style_block .= "      html,body { height: 100%; }\n" .
                       "      body.dialogBody { padding: 0px !important; }\n";
    $style_block .= "    </style>";
    $dialog->add_head_line($style_block);
    $dialog->set_body_id('site_config');
    $dialog->set_help('site_config');
    $dialog->start_body('Site Config');
    $dialog->write("<iframe src=\"../engine/wrapper.php?wrapperconfig=cpanel.conf\" ");
    $dialog->write("width=\"100%\" height=\"100%\" frameborder=\"0\"></iframe>\n");
    $dialog->end_body();
}
*/
function open_google()
{
    $dialog = new Dialog;
    $style_block = "<style>\n" .
                   "      .dialog_body { margin: 0px; padding: 0px;\n" .
                   "                     overflow-x: hidden; overflow-y: hidden; }\n" .
                   '    </style>';
    $dialog->add_head_line($style_block);
    $dialog->set_body_id('analytics');
    $dialog->set_help('analytics');
    $dialog->start_body('Analytics');
    $dialog->write("<iframe src=\"../engine/wrapper.php?config=google.conf\" ");
    $dialog->write("width=\"100%\" height=\"100%\" frameborder=\"0\"></iframe>\n");
    $dialog->end_body();
}

function open_tickets()
{
    global $siteid;

    $dialog = new Dialog;
    $style_block = "<style>\n" .
                   "      .dialog_body { margin: 0px; padding: 0px;\n" .
                   "                     overflow-x: hidden; overflow-y: hidden; }\n" .
                   '    </style>';
    $dialog->add_head_line($style_block);
    $dialog->set_body_id('open_tickets');
    $dialog->set_help('open_tickets');
    $dialog->start_body('Technical Support');
    $dialog->write("<iframe src=\"https://www.inroads.us/siteadmin/tickets.php?" .
                   "siteid=".$siteid."\" ");
    $dialog->write("width=\"100%\" height=\"100%\" frameborder=\"0\"></iframe>\n");
    $dialog->end_body();
}

function open_wp_plugins()
{
    $dialog = new Dialog;
    $style_block = "<style>\n" .
                   "      .dialog_body { margin: 0px; padding: 0px;\n" .
                   "                     overflow-x: hidden; overflow-y: hidden; }\n";
    if ($dialog->skin) 
       $style_block .= "      html,body { height: 100%; }\n" .
                       "      body.dialogBody { padding: 0px !important; }\n";
    $style_block .= "    </style>";
    $dialog->add_head_line($style_block);
    $dialog->set_body_id('wp_plugins');
    $dialog->set_help('wp_plugins');
    $dialog->start_body('Plugins');
    $dialog->write("<iframe src=\"../engine/wordpress/wp-admin/plugins.php\" ");
    $dialog->write("width=\"100%\" height=\"100%\" frameborder=\"0\"></iframe>\n");
    $dialog->end_body();
}

if (isset($argc) && ($argc > 1) && ($argv[1] == 'updateimages')) {
   if (isset($argv[2])) update_images($argv[2]);
   else update_images(null);
   exit(0);
}
if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'adminconfig') admin_config();
else if ($cmd == 'admindata') admin_data();
else if ($cmd == 'adminother') admin_other();
else if ($cmd == 'systemconfig') system_config();
else if ($cmd == 'processnewimage') process_admin_image(true);
else if ($cmd == 'updateimagefile') process_admin_image(false);
else if ($cmd == 'resizeimage') resize_admin_image();
else if ($cmd == 'moveimage') move_admin_image();
else if ($cmd == 'updateimages') update_images(null);
else if ($cmd == 'lookupaddress') lookup_address();
else if ($cmd == 'updateconfig') update_config();
/* else if ($cmd == 'opencpanel') open_cpanel(); */
else if ($cmd == 'opengoogle') open_google();
else if ($cmd == 'opentickets') open_tickets();
else if ($cmd == 'openwpplugins') open_wp_plugins();
else if ((! $cms_site) && function_exists('process_import_function') &&
         process_import_function($cmd)) {}
else if ((! $cms_site) && function_exists('process_export_function') &&
         process_export_function($cmd)) {}
else if ($shopping_cart && process_cartconfig_function($cmd)) {}
else if (function_exists('process_admin_command') &&
         process_admin_command($cmd)) {}
else if ($enable_schedule && process_schedule_function($cmd)) {}
else if (strstr($cmd,'callout') !== false) {
   require_once 'callouts.php';   process_callout_command($cmd);
}
else if (! isset($prefs_cookie)) display_old_admin_screen();
else display_admin_screen();

DB::close_all();

?>

