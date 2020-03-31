<?php
/*
               Inroads Control Panel/Shopping Cart - Desktop Tab

                       Written 2016-2019 by Randall Severy
                        Copyright 2016-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
require_once 'adminperms.php';
require_once 'maintabs.php';
require_once '../engine/modules.php';

if (file_exists("../cartengine/adminperms.php")) {
   $shopping_cart = true;   $catalog_site = false;
   require_once 'cartconfig-common.php';
}
else {
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
   $shopping_cart = false;
}
if (isset($db_host)) $cms_site = false;
else $cms_site = true;

function display_desktop_screen()
{
    global $main_tabs,$main_tab_order,$prefs_cookie,$admin_directory;
    global $admin_url;

    $db = new DB;
    get_user_perms($user_perms,$module_perms,$custom_perms,$db);
    if (isset($prefs_cookie)) $user_prefs = get_user_prefs($db);
    else $user_prefs = array();
    load_main_tabs($db,$user_perms,$module_perms,$custom_perms,$user_prefs,
                   false);
    foreach ($main_tab_order as $index => $tab_name) {
       if ($main_tabs[$tab_name][2] == 0) continue;
       if (($main_tabs[$tab_name][3] == USER_PERM) &&
           ($user_perms & $main_tabs[$tab_name][2])) continue;
       if (($main_tabs[$tab_name][3] == MODULE_PERM) &&
           ($module_perms & $main_tabs[$tab_name][2])) continue;
       if (($main_tabs[$tab_name][3] == CUSTOM_PERM) &&
           ($custom_perms & $main_tabs[$tab_name][2])) continue;
       remove_main_tab($tab_name);
    }
    $current_tab = get_form_field('tab');

    $screen = new Screen;
    if ($screen->skin) {
       if (! isset($admin_directory)) {
          $skin_dir = '../admin/skins/';   $skin_url = $skin_dir;
       }
       else {
          $skin_dir = $admin_directory.'skins/';
          if (isset($admin_url)) $skin_url = $admin_url.'skins/';
          else $skin_url = '../admin/skins/';
       }
       $skin_dir .= $screen->skin.'/';   $skin_url .= $screen->skin.'/';
    }
    $screen->add_style_sheet('desktop.css');
    if ($screen->skin && file_exists($skin_dir.'desktop.css'))
       $screen->add_style_sheet($skin_url.'desktop.css',
                                $skin_dir.'desktop.css');
    $screen->add_script_file('../engine/jquery-3.1.0.min.js');
    $screen->add_script_file('desktop.js');
    if ($screen->skin && file_exists($skin_dir.'desktop.js'))
       $screen->add_script_file($skin_url.'desktop.js',$skin_dir.'desktop.js');
    if (function_exists('custom_init_desktop_screen'))
       custom_init_desktop_screen($screen);
    $screen->set_body_id('desktop');
    $screen->set_help('desktop');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Desktop');
       $screen->end_title_bar();
    }
    $screen->start_content_area(true);
    if ($current_tab) {
       $inside_tab = false;   $include_level = $main_tabs[$current_tab][7] + 1;
    }
    else {
       $inside_tab = true;   $include_level = 0;
    }
    $screen->write('   <ul class="buttons ');
    if ($current_tab) {
       $screen->write($current_tab);
       $screen->write(' level_'.$include_level);
    }
    else $screen->write('toplevel');
    $screen->write("\">\n");
    foreach ($main_tab_order as $index => $tab_name) {
       $tab = $main_tabs[$tab_name];
       $level = $tab[7];
       if (isset($main_tab_order[$index + 1])) {
          $next_tab = $main_tabs[$main_tab_order[$index + 1]];
          $next_level = $next_tab[7];
       }
       else {
          $next_tab = null;   $next_level = -1;
       }
       if (($tab[3] == TAB_CONTAINER) &&
           (($next_level < 1) || ($next_tab[3] == TAB_CONTAINER))) continue;
       if ($inside_tab) {
          if ($level == $include_level) {
             $tab_label = $tab[0];
             $url = $tab[1];
             $click_function = $tab[6];
             $screen->write("      <li id=\"".$tab_name."\">\n");
             if ($url || $click_function) 
                $screen->write("        <a href=\"#\" onClick=\"click_tab('" .
                               $tab_name."'); return false;\">");
             else $screen->write("        <a href=\"desktop.php?tab=" .
                                 $tab_name."\">");
             $screen->write("<span class=\"before\">&nbsp;</span>".$tab_label .
                            "<span class=\"after\">&nbsp;</span></a>\n");
             $screen->write("      </li>\n");
          }
          else if ($current_tab && ($level < $include_level)) break;
       }
       else if ($tab_name == $current_tab) $inside_tab = true;
    }
    $screen->write("   </ul>\n");
    $screen->end_content_area(true);
    $screen->end_body();
}

if (! check_login_cookie()) exit;

display_desktop_screen();

DB::close_all();

?>
