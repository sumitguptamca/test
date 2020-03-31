<?php
/*
                Inroads Control Panel/Shopping Cart - Templates Tab

                       Written 2007-2019 by Randall Severy
                        Copyright 2007-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once '../engine/editorutil.php';
require_once '../engine/modules.php';
require_once '../engine/mime.php';
if (file_exists("../admin/custom-config.php"))
   require_once '../admin/custom-config.php';

if (isset($enable_multisite) && $enable_multisite &&
    isset($_COOKIE[$website_cookie]))
   $website_id = $_COOKIE[$website_cookie];
require_once '../engine/email.php';

if (get_current_directory() == 'cartengine') $shopping_cart = true;
else $shopping_cart = false;
if (! isset($enable_multisite_templates)) $enable_multisite_templates = false;
if (! isset($custom_template_start)) $custom_template_start = 0;
if (! isset($formcraft_template_start)) $formcraft_template_start = 500;

function add_script_prefix(&$screen)
{
    global $shopping_cart;

    if (! $shopping_cart) return;
    $head_block = "<script type=\"text/javascript\">" .
                  "script_prefix='../cartengine/';</script>";
    $screen->add_head_line($head_block);
}

function add_default_template()
{
    global $template_names,$custom_template_start;

    if ((! $custom_template_start) && (! module_installed('formcraft')))
       return;
    $template_names[-1] = 'Default Template';
}

function add_formcraft_templates()
{
    global $template_names,$formcraft_template_start;

    if (! module_installed('formcraft')) return;
    require_once '../admin/modules/formcraft.php';
    $formcraft = new FormCraft();
    $forms = $formcraft->load_forms();
    if (! $forms) return;
    foreach ($forms as $form_id => $form_info) {
       $admin_template = ($form_id * 2) + $formcraft_template_start - 2;
       $user_template = ($form_id * 2) + $formcraft_template_start - 1;
       $form_title = $form_info['title'];
       $template_names[$admin_template] = $form_title.' (Admin)';
       $template_names[$user_template] = $form_title;
    }
}

function add_template_filters($screen,$website)
{
    $db = new DB;
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Web Site:');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    $screen->write("<select name=\"website\" id=\"website\" " .
                   "onChange=\"filter_templates();\" class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    $query = 'select * from web_sites order by domain';
    $result = $db->query($query);
    if ($result) {
       while ($row = $db->fetch_assoc($result)) {
          $screen->add_list_item($row['id'],$row['domain'],
                                 $website == $row['id']);
       }
       $db->free_result($result);
    }
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');
    else $screen->write("</td></tr>\n");
}

function display_templates_screen()
{
    global $template_names,$shopping_cart,$templates_config_path;
    global $enable_multisite_templates,$website_id,$custom_template_start;
    global $order_templates;

    $orders = get_form_field('orders');
    if ($orders) {
       $num_templates = count($order_templates);
       $custom_template_start = null;
    }
    else {
       add_default_template();   add_formcraft_templates();
       $num_templates = 0;
       foreach ($template_names as $index => $template_name)
          if ($template_name) $num_templates++;
       if ($num_templates > 20) $num_templates = 20;
       if (isset($template_names[0])) $index_offset = 1;
       else $index_offset = 0;
    }

    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_screen_body');
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('templates.css');
    $screen->add_script_file('templates.js');
    add_script_prefix($screen);
    if ($enable_multisite_templates) {
       $website = get_form_field('website');
       if (($website === null) && isset($website_id)) $website = $website_id;
       if (! $website) $website = 0;
    }
    $head_block = "<script type=\"text/javascript\">\n";
    if ($custom_template_start)
       $head_block .= '      custom_template_start = ' .
                      $custom_template_start.";\n";
    if ($orders) {
       $head_block .= "      orders = true;\n";
       foreach ($order_templates as $filename => $template_info) {
          $template_name = str_replace("'","\\'",$template_info['name']);
          $head_block .= '      template_names[\''.$filename.'\'] = \'' .
                         $template_name."';\n";
          $head_block .= '      template_types[\''.$filename.'\'] = \'' .
                         $template_info['type']."';\n";
       }
    }
    else {
       $template_ids = '';
       foreach ($template_names as $index => $template_name) {
          if (! $template_name) continue;
          if ($template_ids != '') $template_ids .= ',';
          $template_ids .= ($index + $index_offset);
          $template_name = str_replace("'","\\'",$template_name);
          $head_block .= '      template_names['.($index + $index_offset) .
                         "] = '".$template_name."';\n";
       }
       $head_block .= "      template_ids = '".$template_ids."';\n";
       if (file_exists('../emailbuilder/'))
          $head_block .= "      emailbuilder_installed = true;\n";
    }
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
    $screen->set_body_id('templates');
    $screen->set_help('templates');
    $screen->start_body();
    $screen->start_form('templates.php','Templates');
    if ($screen->skin) {
       if ($custom_template_start) {
          $screen->start_section();
          $screen->start_title_bar('Standard E-Mail Templates');
       }
       else if ($orders) $screen->start_title_bar('Order Templates');
       else $screen->start_title_bar('E-Mail Templates');
       if ($enable_multisite_templates) {
          $screen->start_title_filters();
          add_template_filters($screen,$website);
          $screen->end_title_filters();
       }
       $screen->end_title_bar();
    }
    if ($custom_template_start) $screen->set_button_width(140);
    else $screen->set_button_width(132);
    $screen->start_button_column();
    $screen->add_button('Edit Template','images/EditUser.png',
                        'edit_template();');

    if ($screen->skin) {
       if ($custom_template_start) {
          $screen->end_button_column();
          $screen->write("          <script>load_standard_grid();</script>\n");
          $screen->end_section();
          $screen->start_section();
          $screen->start_title_bar('Custom E-Mail Templates');
          $screen->end_title_bar();
          $screen->start_button_column();
       }
    }
    else {
       if ($enable_multisite_templates) add_template_filters($screen,$website);
       if ($custom_template_start) {
          $screen->add_button_separator('templates_sep_row',5);
          $screen->write("<td colspan=\"2\"></td></tr>\n");
       }
    }
    if ($custom_template_start) {
       $screen->add_button('Add Template','images/AddUser.png',
                           'add_template();');
       $screen->add_button('Edit Template','images/EditUser.png',
                           'edit_custom_template();');
       $screen->add_button('Delete Template','images/DeleteUser.png',
                           'delete_template();');
    }

    $screen->end_button_column();
    if ((! $custom_template_start) || (! $screen->skin))
       $screen->write("<script>load_standard_grid();</script>\n");
    if ($custom_template_start) {
       if (! $screen->skin) {
          $screen->write('          <br><span class="fieldprompt"' .
                         ' style="text-align: left; font-weight: bold;">' .
                         "Custom E-Mail Templates</span><br>\n");
       }
       $screen->write("          <script>load_custom_grid();</script>\n");
       if ($screen->skin) $screen->end_section(true);
    }
    $screen->end_body(true);
}

function template_record_definition()
{
    $template_record = array();
    $template_record['type'] = array('type' => INT_TYPE);
    $template_record['type']['key'] = true;
    $template_record['name'] = array('type' => CHAR_TYPE);
    $template_record['format'] = array('type' => INT_TYPE);
    $template_record['subject'] = array('type' => CHAR_TYPE);
    $template_record['from_addr'] = array('type' => CHAR_TYPE);
    $template_record['to_addr'] = array('type' => CHAR_TYPE);
    $template_record['cc_addr'] = array('type' => CHAR_TYPE);
    $template_record['bcc_addr'] = array('type' => CHAR_TYPE);
    return $template_record;
}

function edit_order_template()
{
    global $docroot,$login_cookie,$cms_program;
    global $cms_support_url,$admin_path,$cms_url,$prefix,$htmleditor_url;

    if (get_server_type() == WINDOWS) $dirsep = "\\";
    else $dirsep = '/';

    $template = get_form_field('Template');
    if (get_server_type() == WINDOWS) $dirsep = "\\";
    else $dirsep = '/';
    if (substr($template,0,1) != $dirsep)
       $template = $docroot.'/admin/templates/'.$template;
    $template .= '.html';
    $label = get_form_field('Label');
    if (file_exists($template)) {
       $template_content = file_get_contents($template);
       if (! $template_content) $template_content = '';
       else $template_content = str_replace("\r",'',$template_content);
    }
    else $template_content = '';

    $question_pos = strpos($cms_url,'?');
    if ($question_pos !== false)
       $browse_base_url = substr($cms_url,0,$question_pos);
    else $browse_base_url = $cms_url;
    if (isset($prefix) && $prefix) $toolbar_prefix = $prefix;
    else $toolbar_prefix = '';
    $editor = get_prefs_editor();
    $templates_path = load_templates_path('sliptemplates.xml');
    $support_url = $cms_support_url;
    if (substr($support_url,-1) != '/') $support_url .= '/';
    if ($editor == 'ckeditor') {
       $editor_base_path = $support_url.'ckeditor/';
       $editor_url = $support_url.'ckeditor/ckeditor.js';
       load_ckeditor_options($templates_path,$extra_plugins,$toolbar_buttons);
    }
    else {
       $editor_base_path = $support_url.'fckeditor/';
       $editor_url = $support_url.'fckeditor/fckeditor.js';
    }
    if (! $templates_path) $templates_path = 'null';

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('templates.css');
    $dialog->add_script_file('templates.js');
    $dialog->add_script_file($editor_url,null);
    if (file_exists("../admin/custom-config.js"))
       $dialog->add_script_file("../admin/custom-config.js");
    $head_block = "<script type=\"text/javascript\">\n" .
                  "      html_editor = '".$editor."';\n" .
                  "      editor_base_path = '".$editor_base_path."';\n" .
                  "      browse_base_url = '".$browse_base_url."';\n" .
                  "      templates_path = ".$templates_path.";\n" .
                  "      toolbar_prefix = '".$toolbar_prefix."';\n" .
                  "      admin_path = '".$admin_path."';\n";
    if ($editor == 'ckeditor') {
       $head_block .= "      extra_plugins = ".$extra_plugins.";\n";
       if ($toolbar_buttons)
          $head_block .= "      toolbar_buttons = ".$toolbar_buttons.";\n";
    }
    if (isset($htmleditor_url))
       $head_block .= "      htmleditor_url = '".$htmleditor_url."';\n";
    $head_block .= "      script_prefix = '../cartengine/';\n";
    $head_block .= '    </script>'."\n";;
    $head_block .= '    <style> .fieldtable { width: 100%; } </style>';
    $dialog->add_head_line($head_block);
    $dialog->set_onload_function('edit_template_onload();');
    $dialog->set_field_padding(1);
    $dialog->set_body_id('edit_order_template');
    $dialog->set_help('edit_order_template');
    $dialog->start_body('Edit '.$label.' Template');
    $dialog->start_content_area(true);
    $dialog->start_form('templates.php','EditOnline');
    $dialog->add_hidden_field('SupportURL',$support_url);
    $dialog->add_hidden_field('PluginURL',$support_url .
                            'plugins/index.php');
    $dialog->add_hidden_field('User',get_cookie($login_cookie));
    $dialog->add_hidden_field('Program',$cms_program);
    $dialog->add_hidden_field('Product','3');
    $dialog->add_hidden_field('Template',$template);
    $dialog->add_hidden_field('Label',$label);
    $dialog->start_field_table();
    $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: left;\">");
    $dialog->write("Template:</td>\n<td align=\"right\"><input type=\"button\" " .
                   "value=\"Insert Template Field\" ");
    $dialog->write("class=\"insert_button\" onClick=\"insert_template_field();\">");
    $dialog->write("</td></tr>\n");
    $dialog->write("<tr><td colspan=\"2\" style=\"padding:4px 8px 0px 0px;\">");
    $dialog->write("<div id=\"content_div\" style=\"width: ");
    if ($dialog->skin) $dialog->write('100%');
    else $dialog->write('670px');
    $dialog->write("; height: 330px;\">");
    $dialog->write("<textarea name=\"content\" id=\"content\" rows=\"22\" ");
    $dialog->write("cols=\"80\" class=\"textarea\" style=\"width: ");
    if ($dialog->skin) $dialog->write('100%');
    else $dialog->write('670px');
    $dialog->write('; height: 330px; display: none;');
    $dialog->write("\" wrap=\"soft\">");
    $dialog->write($template_content);
    $dialog->end_textarea_field();
    $dialog->write("</div></td></tr>\n");
    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<tr><td align=\"center\" colspan=\"2\">\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\" " .
                      "style=\"margin-top: 5px;\"><tr>");
       $dialog->write("<td style=\"padding-right: 5px;\">\n");
    }
    $dialog->add_dialog_button('Update','images/Update.png',
                               'update_order_template();');
    if (! $dialog->skin)
       $dialog->write("</td><td style=\"padding-left: 5px;\">\n");
    $dialog->add_dialog_button('Cancel','images/Update.png',
                               'top.close_current_dialog();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else {
       $dialog->write("</td></tr></table>\n");
       $dialog->write("</td></tr>\n");
    }
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_content_area(true);
    $dialog->end_body();
}

function update_order_template()
{
    $template_content = get_form_field('content');
    $template = get_form_field('Template');
    $label = get_form_field('Label');
    $template_file = fopen($template,'wt');
    if (! $template_file) {
       log_error('Unable to open '.$template);
       http_response(422,'Unable to open '.$template);   return;
    }
    fwrite($template_file,$template_content);
    fclose($template_file);

    http_response(201,$label.' Template Updated');
    log_activity('Updated '.$label.' Template');
}

function load_attachments($db,$template)
{
    global $template_dir;

    $result = $db->query('select filename from attachments ' .
                         'where template_type='.$template);
    if (! $result) return;
    $attach_dir = get_form_field('AttachDir');
    $attachments = '{';   $index = 0;
    while ($attach_row = $db->fetch_assoc($result)) {
       $filename = $attach_row['filename'];
       if (get_server_type() == WINDOWS) $dirsep = "\\";
       else $dirsep = '/';
       $full_filename = $template_dir.$dirsep.$template.'-'.$filename;
       if ($attach_dir) {
          $attach_filename = $attach_dir.$filename;
          if (! file_exists($attach_filename))
             copy($full_filename,$attach_filename);
          $file_size = filesize($attach_filename);
       }
       else $file_size = filesize($full_filename);
       $size_index = 0;
       while ($file_size > 99) {
          $file_size = $file_size / 1024;   $size_index++;
       }
       if ($file_size < 0.1) $file_size = 0.1;
       $sizes = array('B','kB','MB','GB','TB','PB','EB');
       $file_size = sprintf('%.1f',$file_size).$sizes[$size_index];
       if ($index > 0) $attachments .= ',';
       $filename = str_replace("'","\\'",$filename);
       $attachments .= $index.":{filename:'".$filename."', size:'".$file_size."'}";
       $index++;
    }
    $db->free_result($result);
    $attachments .= '}';
    return $attachments;
}

function display_template_fields($edit_type,$row,$attachments)
{
    global $cms_support_url,$admin_path,$template_dir,$template_url;
    global $shopping_cart,$cms_url,$prefix,$doctype,$htmleditor_url;
    global $login_cookie,$cms_program,$custom_template_start;
    global $send_email_browse_function;

    if (get_server_type() == WINDOWS) $dirsep = "\\";
    else $dirsep = '/';
    $template = $row['type'];

    $attach_dir = get_form_field('AttachDir');
    $send_data = get_form_field('SendData');
    if ($send_data) {
       $send_data_array = explode('|',$send_data);
       $template_data = array();
       foreach ($send_data_array as $send_field) {
          $field_parts = explode(':',$send_field);
          if (count($field_parts) != 2) continue;
          $template_data[$field_parts[0]] = $field_parts[1];
       }
       $email = new Email($template,$template_data);
       if (! $email->send(false)) {
          process_error($email->error,0);   return;
       }
       $template_content = $email->template_content;
       $row['subject'] = $email->template_info['subject'];
       $row['from_addr'] = $email->template_info['from_addr'];
       $row['to_addr'] = $email->template_info['to_addr'];
       $row['cc_addr'] = $email->template_info['cc_addr'];
       $row['bcc_addr'] = $email->template_info['bcc_addr'];
    }
    else {
       $template_filename = $template_dir.$dirsep.$template.'.msg';
       if (file_exists($template_filename)) {
          $template_content = file_get_contents($template_filename);
          if (! $template_content) $template_content = '';
          else $template_content = str_replace("\r",'',$template_content);
       }
       else {
          $template_filename = $template_dir.$dirsep.'-1.msg';
          if (file_exists($template_filename)) {
             $template_content = file_get_contents($template_filename);
             if (! $template_content) $template_content = '';
             else $template_content = str_replace("\r",'',$template_content);
          }
         else $template_content = '';
       }
    }

    $question_pos = strpos($cms_url,'?');
    if ($question_pos !== false) $browse_base_url = substr($cms_url,0,$question_pos);
    else $browse_base_url = $cms_url;
    if (isset($prefix) && $prefix) $toolbar_prefix = $prefix;
    else $toolbar_prefix = '';
    $editor = get_prefs_editor();
    $templates_path = load_templates_path('emailtemplates.xml');
    $support_url = $cms_support_url;
    if (substr($support_url,-1) != '/') $support_url .= '/';
    if ($editor == 'ckeditor') {
       $editor_base_path = $support_url.'ckeditor/';
       $editor_url = $support_url.'ckeditor/ckeditor.js';
       load_ckeditor_options($templates_path,$extra_plugins,$toolbar_buttons);
    }
    else {
       $editor_base_path = $support_url.'fckeditor/';
       $editor_url = $support_url.'fckeditor/fckeditor.js';
    }
    if (! $templates_path) $templates_path = 'null';

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('fileuploader.css');
    $dialog->add_style_sheet('templates.css');
    $dialog->add_script_file('fileuploader.js');
    $dialog->add_script_file('templates.js');
    $dialog->add_script_file($editor_url,null);
    if (file_exists("../admin/custom-config.js"))
       $dialog->add_script_file("../admin/custom-config.js");
    $head_block = "<script type=\"text/javascript\">\n" .
                  "      html_editor = '".$editor."';\n" .
                  "      editor_base_path = '".$editor_base_path."';\n" .
                  "      browse_base_url = '".$browse_base_url."';\n" .
                  "      templates_path = ".$templates_path.";\n" .
                  "      toolbar_prefix = '".$toolbar_prefix."';\n" .
                  "      admin_path = '".$admin_path."';\n" .
                  "      attach_dir = '".$attach_dir."';\n";
    if ($editor == 'ckeditor') {
       if (file_exists('../emailbuilder/')) {
          $head_block .= "      emailbuilder_installed = true;\n";
          $extra_plugins = substr($extra_plugins,0,-1) .
                           ',iframedialog,emailbuilder\'';
       }
       $head_block .= "      extra_plugins = ".$extra_plugins.";\n";
       if ($toolbar_buttons)
          $head_block .= "      toolbar_buttons = ".$toolbar_buttons.";\n";
    }
    if (isset($htmleditor_url))
       $head_block .= "      htmleditor_url = '".$htmleditor_url."';\n";
    if (isset($template_url))
       $head_block .= "      template_url = '".$template_url."';\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix = '../cartengine/';\n";
    if (get_form_field('insidecms')) {
       $head_block .= "      inside_cms = true;\n";
       $dialog->use_cms_top();
    }
    if ($custom_template_start)
       $head_block .= '      custom_template_start = ' .
                      $custom_template_start.";\n";
    $head_block .= '    </script>'."\n";
    $head_block .= '    <style> .fieldtable { width: 100%; } </style>';
    $dialog->add_head_line($head_block);
    if ($edit_type == ADDRECORD) $onload_function = 'add_template_onload(); ';
    else $onload_function = '';
    $onload_function .= 'create_uploader('.$template.','.$attachments .
                                         '); edit_template_onload();';
    $dialog->set_onload_function($onload_function);
    $dialog->set_field_padding(1);
    if ($send_data) $dialog_title = 'Send E-Mail to '.$row['to_addr'];
    else if ($edit_type == ADDRECORD) {
       $dialog_title = 'Add Template (#'.$template.')';
       $dialog->set_body_id('add_template');
       $dialog->set_help('add_template');
    }
    else {
       $dialog_title = 'Edit Template (#'.$template.')';
       $dialog->set_body_id('edit_template');
       $dialog->set_help('edit_template');
    }
    $dialog->start_body($dialog_title);
    $dialog->start_content_area(true);
    $dialog->start_form('templates.php','EditOnline');
    $dialog->add_hidden_field('Template',$template);
    $finish_function = get_form_field('FinishFunction');
    if ($finish_function)
       $dialog->add_hidden_field('FinishFunction',$finish_function);
    if (($edit_type == UPDATERECORD) && isset($row['NewRecord']))
       $dialog->add_hidden_field('NewRecord','Yes');
    $dialog->add_hidden_field('SupportURL',$support_url);
    $dialog->add_hidden_field('PluginURL',$support_url .
                            'plugins/index.php');
    $dialog->add_hidden_field('User',get_cookie($login_cookie));
    $dialog->add_hidden_field('Program',$cms_program);
    $dialog->add_hidden_field('Product','3');
    if ($send_data) $dialog->add_hidden_field('SendData',$send_data);
    $dialog->start_field_table();
    if ($custom_template_start && ($template >= $custom_template_start)) {
       if ($send_data)
          $dialog->add_hidden_field('name',get_row_value($row,'name'));
       else $dialog->add_edit_row('Name:','name',get_row_value($row,'name'),90);
    }
    $dialog->add_edit_row('Subject:','subject',
                          get_row_value($row,'subject'),90);
    $dialog->add_edit_row('From Address:','from_addr',
                          get_row_value($row,'from_addr'),90);
    $dialog->add_edit_row('To Addresses:','to_addr',
                          get_row_value($row,'to_addr'),90);
    $dialog->add_edit_row('Cc Addresses:','cc_addr',
                          get_row_value($row,'cc_addr'),90);
    $dialog->add_edit_row('Bcc Addresses:','bcc_addr',
                          get_row_value($row,'bcc_addr'),90);
    $dialog->start_row('Attachments:','top');
    $dialog->write("<div id=\"attachments\"></div>");
    if ($send_data && isset($send_email_browse_function))
       $dialog->write("<input type=\"button\" class=\"browse-server-button\" " .
                      "onClick=\"".$send_email_browse_function."\" value=\"" .
                      "Browse Server...\">");
    $dialog->end_row();
    $dialog->write("<tr><td colspan=\"2\">");
    $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr>\n");
    $dialog->write("<td class=\"fieldprompt\" style=\"text-align: left;\">");
    if ($send_data) $dialog->write("Body:</td>\n");
    else $dialog->write("Template:</td>\n");
    $dialog->write("<td align=\"center\" class=\"fieldprompt\" " .
                   "style=\"text-align: center;\">Format:\n");
    $format = get_row_value($row,'format');
    $dialog->add_radio_field('format','1','Text',$format != 2,
                             'update_template_format();');
    $dialog->add_radio_field('format','2','HTML',$format == 2,
                             'update_template_format();');
    if (! $send_data) {
       $dialog->write("</td>\n<td align=\"right\"><input type=\"button\" " .
                      "value=\"Insert Template Field\" ");
       $dialog->write("class=\"insert_button\" onClick=\"insert_template_field();\">");
    }
    $dialog->write("</td></tr></table>\n");
    $dialog->write("<div id=\"content_div\" style=\"width: ");
    if ($dialog->skin) $dialog->write('100%');
    else $dialog->write('670px');
    $dialog->write("; height: 330px;\">");
    $dialog->write("<textarea name=\"content\" id=\"content\" rows=\"22\" ");
    $dialog->write("cols=\"80\" class=\"textarea\" style=\"width: ");
    if ($dialog->skin) $dialog->write('100%');
    else $dialog->write('670px');
    $dialog->write('; height: 330px;');
    if ($format == 2) $dialog->write(' display: none;');
    $dialog->write("\" wrap=\"soft\">");
    $dialog->write($template_content);
    $dialog->end_textarea_field();
    $dialog->write("</div></td></tr>\n");
    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<tr><td align=\"center\" colspan=\"2\">\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\" " .
                      "style=\"margin-top: 5px;\"><tr>");
       $dialog->write("<td style=\"padding-right: 5px;\">\n");
    }
    if ($send_data)
       $dialog->add_dialog_button('Send','images/Update.png',
                                  'send_email();');
    else if ($edit_type == ADDRECORD)
       $dialog->add_dialog_button('Add','images/Update.png',
                                  'process_add_template();');
    else $dialog->add_dialog_button('Update','images/Update.png',
                                    'update_template();');
    if (! $dialog->skin)
       $dialog->write("</td><td style=\"padding-left: 5px;\">\n");
    $dialog->add_dialog_button('Cancel','images/Update.png',
                               'close_edit_template();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else {
       $dialog->write("</td></tr></table>\n");
       $dialog->write("</td></tr>\n");
    }
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_content_area(true);
    $dialog->end_body();
}

function create_template()
{
    global $template_dir,$custom_template_start;

    $db = new DB;
    $query = 'select max(type) as max_type from templates';
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    $max_type = $row['max_type'];
    if ($max_type < $custom_template_start) $template = $custom_template_start;
    else $template = $max_type + 1;

    $template_record = template_record_definition();
    $row = $db->get_record('select * from templates where type=-1');
    if ($row) {
       foreach ($row as $field_name => $field_value)
          $template_record[$field_name]['value'] = $row[$field_name];
       unset($template_record['subject']['value']);
       if (get_server_type() == WINDOWS) $dirsep = "\\";
       else $dirsep = '/';
       $src_filename = $template_dir.$dirsep.'-1.msg';
       if (file_exists($src_filename)) {
          $dest_filename = $template_dir.$dirsep.$template.'.msg';
          if (! copy($src_filename,$dest_filename))
             log_error("Unable to copy ".$src_filename." to ".$dest_filename);
       }
    }
    $template_record['type']['value'] = $template;
    $template_record['name']['value'] = 'New Template';
    if (! $db->insert('templates',$template_record)) {
       http_response(422,$db->error);   return;
    }
    print 'template = '.$template.';';
    log_activity('Created New Custom Template #'.$template);
}

function add_template()
{
    $template = get_form_field('Template');
    $db = new DB;
    $row = $db->get_record('select * from templates where type='.$template);
    if ($row) $row['name'] = '';
    else {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,0);   return;
       }
       $row = array();
       $row['type'] = $template;
    }
    display_template_fields(ADDRECORD,$row,'{}');
}

function process_add_template()
{
    global $template_dir;

    if (get_server_type() == WINDOWS) $dirsep = "\\";
    else $dirsep = '/';
    $template = get_form_field('Template');

    $db = new DB;
    $template_filename = $template_dir.$dirsep.$template.'.msg';
    $template_content = get_form_field('content');
    $template_file = fopen($template_filename,'wt');
    if (! $template_file) {
       log_error('Unable to open '.$template_filename);
       http_response(422,'Unable to open '.$template_filename);   return;
    }
    fwrite($template_file,$template_content);
    fclose($template_file);

    $template_record = template_record_definition();
    $db->parse_form_fields($template_record);
    $template_record['type']['value'] = $template;
    if (! $db->update('templates',$template_record)) {
       http_response(422,$db->error);   return;
    }

    http_response(201,'Template Updated');
    log_activity('Added Custom Template #'.$template.' (' .
                 $template_record['name']['value'].')');
}

function edit_template()
{
    if (get_form_field('orders')) return edit_order_template();

    $template = get_form_field('Template');
    $db = new DB;
    $row = $db->get_record('select * from templates where type='.$template);
    if (! $row) {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,0);   return;
       }
       $row = $db->get_record('select * from templates where type=-1');
       if ($row) $row['subject'] = '';
       else $row = array();
       $row['type'] = $template;
       $row['NewRecord'] = true;
    }
    $attachments = load_attachments($db,$template);
    display_template_fields(UPDATERECORD,$row,$attachments);
}

function update_template()
{
    global $template_names,$template_dir,$custom_template_start;

    if (get_server_type() == WINDOWS) $dirsep = "\\";
    else $dirsep = '/';
    $template = get_form_field('Template');
    $template_filename = $template_dir.$dirsep.$template.'.msg';
    $template_content = get_form_field('content');
    $template_file = fopen($template_filename,'wt');
    if (! $template_file) {
       log_error('Unable to open '.$template_filename);
       http_response(422,'Unable to open '.$template_filename);   return;
    }
    fwrite($template_file,$template_content);
    fclose($template_file);

    $new_record = get_form_field('NewRecord');
    $db = new DB;
    $template_record = template_record_definition();
    $db->parse_form_fields($template_record);
    $template_record['type']['value'] = $template;
    if ($new_record) {
       if (! $db->insert('templates',$template_record)) {
          http_response(422,$db->error);   return;
       }
    }
    else if (! $db->update('templates',$template_record)) {
       http_response(422,$db->error);   return;
    }

    http_response(201,'Template Updated');
    if ($custom_template_start && ($template >= $custom_template_start))
       log_activity('Updated Template #'.$template.' (' .
                    $template_record['name']['value'].')');
    else {
       add_default_template();   add_formcraft_templates();
       if (isset($template_names[0])) $index_offset = 1;
       else $index_offset = 0;
       log_activity('Updated Template #'.$template.' (' .
                    $template_names[$template - $index_offset].')');
    }
}

function delete_template()
{
    global $template_dir;

    if (get_server_type() == WINDOWS) $dirsep = "\\";
    else $dirsep = '/';
    $template = get_form_field('Template');
    $db = new DB;
    $query = 'select filename from attachments where template_type='.$template;
    $attachments = $db->get_records($query);
    if ((! $attachments) && isset($db->error)) {
       http_response(422,$db->error);   return;
    }
    if ($attachments) foreach ($attachments as $attachment) {
       $filename = $attachment['filename'];
       $full_filename = $template_dir.$dirsep.$template.'-'.$filename;
       unlink($full_filename);
    }
    $query = 'delete from attachments where template_type='.$template;
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $template_record = template_record_definition();
    $template_record['type']['value'] = $template;
    if (! $db->delete('templates',$template_record)) {
       http_response(422,$db->error);   return;
    }
    $template_filename = $template_dir.$dirsep.$template.'.msg';
    unlink($template_filename);
    http_response(201,'Template Deleted');
    log_activity('Deleted Custom Template #'.$template);
}

function insert_template_field()
{
    global $template_tables,$shopping_cart,$custom_template_start;
    global $formcraft_template_start;

    $template = get_form_field('Template');
    $dialog = new Dialog;
    $dialog->set_onload_function('load_insert_lists();');
    $dialog->add_script_file('templates.js');
    $dialog->add_script_file('templates-config.js');
    if ($shopping_cart) {
       require_once 'shopping-common.php';
       call_shopping_event('templates_head',array(&$dialog));
       $dialog->add_script_file('../admin/templates-config.js');
    }
    add_script_prefix($dialog);
    if (get_form_field('insidecms')) {
       $head_block = "<script type=\"text/javascript\"> " .
                     "inside_cms = true;</script>";
       $dialog->add_head_line($head_block);
       $dialog->use_cms_top();
    }

    if (($template >= $formcraft_template_start) &&
        ((! $custom_template_start) || ($template < $custom_template_start)) &&
        module_installed('formcraft')) {
       $form_id = floor(($template - ($formcraft_template_start - 2))/2);
       require_once '../admin/modules/formcraft.php';
       $formcraft = new FormCraft();
       $form_info = $formcraft->load_form($form_id);
       $head_block = "<script type=\"text/javascript\">\n";
       $head_block .= "      insert_lists['form'] = [";
       if ($form_info && isset($form_info['fields'])) {
          $first_def = true;
          foreach ($form_info['fields'] as $field_def) {
             if ($first_def) $first_def = false;
             else $head_block .= ",\n                        ";
             $name = str_replace("'","\\'",$field_def['name']);
             $prompt = str_replace("'","\\'",$field_def['prompt']);
             $head_block .= "['".$name."','".$prompt."']";
          }
       }
       if (! $first_def) $head_block .= ",\n                        ";
       $head_block .= "['attachments_text','Attachments (Text)']";
       $head_block .= ",\n                        ";
       $head_block .= "['attachments_html','Attachments (HTML)']";
       $head_block .= "\n      ];\n";
       $head_block .= "    </script>";
       $dialog->add_head_line($head_block);
    }
    $head_block = "<style type=\"text/css\">\n" .
                  "      select { max-width: 800px; }\n" .
                  "    </style>";
    $dialog->add_head_line($head_block);

    $dialog->set_body_id('insert_template_field');
    $dialog->set_help('insert_template_field');
    $dialog->start_body('Insert Template Field');
    $dialog->start_button_column(false,true);
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('templates.php','InsertField');
    $dialog->start_field_table();
    foreach ($template_tables as $table_name => $prompt) {
       $dialog->start_row($prompt,'top');
       $dialog->start_choicelist($table_name,"insert_field('".$table_name."');");
       $dialog->add_list_item('','',true);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function attachment_record_definition()
{
    $attachment_record = array();
    $attachment_record['template_type'] = array('type' => INT_TYPE);
    $attachment_record['template_type']['key'] = true;
    $attachment_record['description'] = array('type' => CHAR_TYPE);
    $attachment_record['filename'] = array('type' => CHAR_TYPE);
    $attachment_record['filename']['key'] = true;
    $attachment_record['file_type'] = array('type' => CHAR_TYPE);
    $attachment_record['sequence'] = array('type' => INT_TYPE);
    return $attachment_record;
}

function write_upload_response($response)
{
    print htmlspecialchars(json_encode($response),ENT_NOQUOTES);
}

function get_attach_filename($parent,$filename,$attach_dir)
{
    global $template_dir;

    if ($attach_dir) $full_filename = $attach_dir.$filename;
    else {
       if (get_server_type() == WINDOWS) $dirsep = "\\";
       else $dirsep = '/';
       $full_filename = $template_dir.$dirsep.$parent.'-'.$filename;
    }
    if (file_exists($full_filename)) {
       write_upload_response(array('error'=>'That file is already attached'));
       return null;
    }
    return $full_filename;
}

function upload_attachment()
{
    global $template_names,$template_dir,$custom_template_start;

    $parent = get_form_field('parent');
    $attach_dir = get_form_field('AttachDir');
    if (isset($_GET['qqfile'])) {
       $filename = $_GET['qqfile'];
       $attach_filename = get_attach_filename($parent,$filename,$attach_dir);
       if (! $attach_filename) return;
       $input = fopen('php://input','r');
       $output = fopen($attach_filename,'w');
       stream_copy_to_stream($input,$output);
       fclose($input);   fclose($output);
       $file_type = get_mime_type($attach_filename);
    }
    else if (isset($_FILES['qqfile'])) {
       $filename = $_FILES['qqfile']['name'];
       $file_type = $_FILES['qqfile']['type'];
       $attach_filename = get_attach_filename($parent,$filename,$attach_dir);
       if (! $attach_filename) return;
       if (! move_uploaded_file($_FILES['qqfile']['tmp_name'],
                                $attach_filename)) {
          write_upload_response(array('error'=>'Unable to save uploaded file'));
          return;
       }
    }
    else {
       write_upload_response(array('error'=>'No files were uploaded'));
       return;
    }

    if ($attach_dir) {
       write_upload_response(array('success'=>true));
       log_activity('Uploaded Attachment '.$attach_filename);
       return;
    }

    $db = new DB;
    $row = $db->get_record('select max(sequence) as max_sequence from ' .
                           'attachments where template_type='.$parent);
    if ((! $row) && isset($db->error)) {
       write_upload_response(array('error'=>$db->error));   return;
    }
    $sequence = intval($row['max_sequence']) + 1;
    $attachment_record = attachment_record_definition();
    $attachment_record['template_type']['value'] = $parent;
    $attachment_record['filename']['value'] = $filename;
    $attachment_record['file_type']['value'] = $file_type;
    $attachment_record['sequence']['value'] = $sequence;
    if (! $db->insert('attachments',$attachment_record)) {
       write_upload_response(array('error'=>$db->error));   return;
    }
    write_upload_response(array('success'=>true));
    if ($custom_template_start && ($parent >= $custom_template_start))
       log_activity('Uploaded Attachment '.$filename.' to Template #'.$parent);
    else {
       add_default_template();   add_formcraft_templates();
       if (isset($template_names[0])) $index_offset = 1;
       else $index_offset = 0;
       log_activity('Uploaded Attachment '.$filename.' to Template #'.$parent .
                    ' ('.$template_names[$parent - $index_offset].')');
    }
}

function delete_attachment()
{
    global $template_names,$template_dir,$custom_template_start;

    $template = get_form_field('parent');
    $filename = get_form_field('filename');
    $attach_dir = get_form_field('AttachDir');

    if ($attach_dir) $attach_filename = $attach_dir.$filename;
    else {
       if (get_server_type() == WINDOWS) $dirsep = "\\";
       else $dirsep = '/';
       $attach_filename = $template_dir.$dirsep.$template.'-'.$filename;
    }
    if (! unlink($attach_filename)) {
       log_error('Unable to delete attachment '.$attach_filename);
       http_response(422,'Unable to delete attachment');   return;
    }

    if ($attach_dir) {
       http_response(201,'Attachment Deleted');
       log_activity('Deleted Attachment '.$attach_filename);
       return;
    }

    $db = new DB;
    $attachment_record = attachment_record_definition();
    $attachment_record['template_type']['value'] = $template;
    $attachment_record['filename']['value'] = $filename;
    if (! $db->delete('attachments',$attachment_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Attachment Deleted');
    if ($custom_template_start && ($template >= $custom_template_start))
       log_activity('Deleted Attachment '.$filename.' from Template #'.$template);
    else {
       add_default_template();   add_formcraft_templates();
       if (isset($template_names[0])) $index_offset = 1;
       else $index_offset = 0;
       log_activity('Deleted Attachment '.$filename.' from Template #'.$template .
                    ' ('.$template_names[$template - $index_offset].')');
    }
}

function send_template_email()
{
    global $template_dir;

    $template = get_form_field('Template');
    $email = new Email($template);
    $email->name = get_form_field('name');
    $email->template_content = get_form_field('content');
    $email->template_info = array();
    $email->template_info['subject'] = strip_tags(get_form_field('subject'));
    $email->template_info['from_addr'] = get_form_field('from_addr');
    $email->template_info['to_addr'] = get_form_field('to_addr');
    $email->template_info['cc_addr'] = get_form_field('cc_addr');
    $email->template_info['bcc_addr'] = get_form_field('bcc_addr');
    $email->template_info['format'] = get_form_field('format');
    $email->tables['config'] = $email->db->get_records('select * from config',
                                  'config_name','config_value');
    $email->attach_dir = get_form_field('AttachDir');
    $attachments = get_form_field('Attachments');

    if ($attachments) {
       $attachments = explode('|',rawurldecode($attachments));
       foreach ($attachments as $filename) {
          if (class_exists('finfo')) {
             if ($email->attach_dir)
                $attach_filename = $email->attach_dir.$filename;
             else {
                if (get_server_type() == WINDOWS) $dirsep = "\\";
                else $dirsep = '/';
                $attach_filename = $template_dir.$dirsep.$template.'-' .
                                   $filename;
             }
             $finfo = new finfo(FILEINFO_MIME);
             $file_type = $finfo->file($attach_filename);
          }
          else if (function_exists('mime_content_type'))
             $file_type = mime_content_type($filename);
          else $file_type = '';
          if (! $file_type) $file_type = lookup_mime_type($filename);
          $email->attachments[$filename] = $file_type;
       }
    }
    if (function_exists('custom_update_template_email'))
       custom_update_template_email($email);
    if (! $email->send(true,false)) {
       log_error($email->error);   http_response(422,$email->error);
       return;
    }
    if (function_exists('custom_finish_send_template_email'))
       custom_finish_send_template_email($email);
    http_response(201,'E-Mail Sent');
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'createtemplate') create_template();
else if ($cmd == 'addtemplate') add_template();
else if ($cmd == 'processaddtemplate') process_add_template();
else if ($cmd == 'edittemplate') edit_template();
else if ($cmd == 'updatetemplate') update_template();
else if ($cmd == 'deletetemplate') delete_template();
else if ($cmd == 'inserttemplatefield') insert_template_field();
else if ($cmd == 'uploadattachment') upload_attachment();
else if ($cmd == 'deleteattachment') delete_attachment();
else if ($cmd == 'sendemail') send_template_email();
else if ($cmd == 'updateordertemplate') update_order_template();
else display_templates_screen();

DB::close_all();

?>
