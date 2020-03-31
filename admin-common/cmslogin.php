<?php
/*
           Inroads Control Panel/Shopping Cart - CMS Login Processing

                      Written 2010-2018 by Randall Severy
                       Copyright 2010-2018 Inroads, LLC
*/

require '../engine/topscreen.php';
require_once '../engine/db.php';

if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;   chdir('../admin');
   require_once '../cartengine/adminperms.php';
}
else {
   $shopping_cart = false;
   require_once 'adminperms.php';
}

function display_login_page()
{
    global $base_url,$ssl_url,$cms_url,$company_name,$company_logo;
    global $shopping_cart,$admin_path,$prefs_cookie;

    $https_flag = getenv('HTTPS');
    if (isset($base_url) && ($https_flag != 'on')) {
       $base_href = $base_url;
       if (substr($base_href,-1) != '/') $base_href .= '/';
       $base_href .= 'admin/';
    }
    else if (isset($ssl_url) && ($https_flag == 'on')) {
       $base_href = $ssl_url;
       if (substr($base_href,-1) != '/') $base_href .= '/';
       $base_href .= 'admin/';
    }
    else {
       if ($https_flag == 'on') $base_href = 'https';
       else $base_href = 'http';
       $base_href .= '://'.getenv('HTTP_HOST').$admin_path;
    }
    if ($shopping_cart) $path_prefix = '../cartengine/';
    else $path_prefix = '';
    $question_pos = strpos($cms_url,'?');
    if ($question_pos !== false) $cms_url = substr($cms_url,0,$question_pos);
    $form_fields = get_form_fields();
    if (isset($prefs_cookie))
       $user_prefs = get_user_prefs(null,'default');
    else $user_prefs = array();
    $screen = new TopScreen(700);
    $screen->set_body_class('login');
    $screen->set_prefs($user_prefs);
    $screen->set_base_href($base_href);
    $screen->enable_ajax();
    $screen->add_style_sheet($path_prefix.'login.css');
    $head_block = "<script type=\"text/javascript\">\n" .
                  "      function login_onload()\n" .
                  "      {\n" .
                  "         var cover_div = top.document.getElementById('cover_div');\n" .
                  "         if (cover_div) cover_div.style.display = 'none';\n" .
                  "         if (cms_top() != top) {\n" .
                  "            var cover_div = document.getElementById('cover_div');\n" .
                  "            if (cover_div) cover_div.style.display = 'none';\n" .
                  "         }\n" .
                  "         document.Login.UserID.focus();\n" .
                  "      }\n" .
                  "      function process_login() { document.Login.submit(); }\n" .
                  "      function onkeydown_handler(evt) {\n" .
                  "         if ((evt.which ? evt.which : evt.keyCode) == 13)\n" .
                  "            document.Login.submit();\n" .
                  "         return true;\n" .
                  "      }\n";
    $head_block .= '   </script>';
    $screen->add_head_line($head_block);
    $screen->set_onload_function("login_onload();\"\n   " .
                                 "onKeyDown=\"return onkeydown_handler(event);");
    $screen->display_header(strip_tags($company_name).' Control Panel',null);
    $screen->start_tab_row();
    $screen->end_tab_row();
    $screen->set_body_id('cms_login');
    $screen->set_help('cms_login');
    $screen->start_body(null);
    if ($screen->skin) $screen->write("<div class=\"loginForm\">\n");
    $screen->write('<h1>'.$company_name."<br> CMS Login </h1>\n");
    $screen->start_form($cms_url,'Login');
    $screen->start_field_table();
    $screen->add_hidden_field('ProcessLogin','Go');
    foreach ($form_fields as $field_name => $field_value)
       $screen->add_hidden_field($field_name,$field_value);
    $screen->add_edit_row('Username:','UserID','',30);
    $screen->add_password_row('Password:','Password','',30);
    $screen->write("<tr><td colspan=2 align=\"center\">\n");
    $screen->add_dialog_button('Login',$path_prefix.'images/Update.png',
                               'process_login(); return false;');
    $screen->write("</td></tr>\n");
    $screen->end_field_table();
    $screen->end_form();
    if ($screen->skin) $screen->write("</div>\n");
    $screen->end_body($company_logo,$company_name);
}

display_login_page();

?>
