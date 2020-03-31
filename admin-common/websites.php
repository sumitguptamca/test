<?php
/*
                Inroads Control Panel/Shopping Cart - Web Sites Dialog

                         Written 2012-2019 by Randall Severy
                          Copyright 2012-2019 Inroads, LLC
*/

require '../engine/dialog.php';
require '../engine/db.php';
require 'utility.php';

if (! isset($shopping_cart)) {
   if (file_exists(__DIR__.'/cartconfig-common.php')) $shopping_cart = true;
   else $shopping_cart = false;
}
if (! isset($catalog_site)) {
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
}
if ($shopping_cart) {
   require_once __DIR__.'/cartconfig-common.php';
   require_once __DIR__.'/catalogconfig-common.php';
   require_once __DIR__.'/analytics.php';
}
else require_once 'countrystate-common.php';

function website_record_definition()
{
    global $shopping_cart,$catalog_site;

    $website_record = array();
    $website_record['id'] = array('type' => INT_TYPE);
    $website_record['id']['key'] = true;
    $website_record['name'] = array('type' => CHAR_TYPE);
    $website_record['domain'] = array('type' => CHAR_TYPE);
    $website_record['rootdir'] = array('type' => CHAR_TYPE);
    $website_record['base_href'] = array('type' => CHAR_TYPE);
    $website_record['cms_program'] = array('type' => CHAR_TYPE);
    $website_record['cms_url'] = array('type' => CHAR_TYPE);
    $website_record['icon'] = array('type' => CHAR_TYPE);
    $website_record['config_companyname'] = array('type' => CHAR_TYPE);
    $website_record['config_admin_email'] = array('type' => CHAR_TYPE);
    $website_record['config_email_logo'] = array('type' => CHAR_TYPE);
    $website_record['config_map_address1'] = array('type' => CHAR_TYPE);
    $website_record['config_map_address2'] = array('type' => CHAR_TYPE);
    $website_record['config_map_city'] = array('type' => CHAR_TYPE);
    $website_record['config_map_state'] = array('type' => CHAR_TYPE);
    $website_record['config_map_zip'] = array('type' => CHAR_TYPE);
    $website_record['config_map_country'] = array('type' => INT_TYPE);
    $website_record['config_map_phone'] = array('type' => CHAR_TYPE);
    $website_record['config_map_fax'] = array('type' => CHAR_TYPE);
    if ($shopping_cart || $catalog_site)
       $website_record['top_category'] = array('type' => INT_TYPE);
    if ($shopping_cart) {
       $website_record['cart_contactemail'] = array('type' => CHAR_TYPE);
       $website_record['cart_contactphone'] = array('type' => CHAR_TYPE);
       $website_record['cart_contacthours'] = array('type' => CHAR_TYPE);
       $website_record['cart_companylogo'] = array('type' => CHAR_TYPE);
    }
    return $website_record;
}

function website_config_record_definition()
{
    $config_record = array();
    $config_record['parent'] = array('type' => INT_TYPE);
    $config_record['parent']['key'] = true;
    $config_record['config_name'] = array('type' => CHAR_TYPE);
    $config_record['config_name']['key'] = true;
    $config_record['config_value']['key'] = true;
    $config_record['config_value'] = array('type' => CHAR_TYPE);
    return $config_record;
}

function add_script_prefix(&$dialog)
{
    global $shopping_cart;

    if (! $shopping_cart) return;
    $head_block = "<script type=\"text/javascript\">" .
                  "script_prefix='../cartengine/';</script>";
    $dialog->add_head_line($head_block);
}

function display_websites_dialog()
{
    $website_settings = get_website_settings();
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('websites.css');
    $dialog->add_script_file('websites.js');
    add_script_prefix($dialog);
    $dialog->set_body_id('websites');
    $dialog->set_help('websites');
    $dialog->start_body('Web Sites',filemtime('websites.php'));
    $dialog->set_button_width(140);
    $dialog->start_button_column();
    $dialog->add_button('Add Web Site','images/AddWebSite.png',
                        'add_website(); return false;');
    $dialog->add_button('Edit Web Site','images/EditWebSite.png',
                        'edit_website(); return false;');
    $dialog->add_button('Delete Web Site','images/DeleteWebSite.png',
                        'delete_website(); return false;');
    $dialog->add_button('Settings','images/Update.png',
                        'edit_website_settings(); return false;');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog(); return false;');
    $dialog->end_button_column();
    $dialog->write("          <script type=\"text/javascript\">\n");
    $dialog->write('             website_settings = '.$website_settings.";\n");
    $dialog->write("             load_grid();\n");
    $dialog->write("          </script>\n");
    $dialog->end_body();
}

function display_website_fields($dialog,$edit_type,$row,$db)
{
    global $shopping_cart,$catalog_site,$website_settings;

    $website_settings = get_website_settings($db);
    if ($edit_type == UPDATERECORD) {
       $id = get_row_value($row,'id');
       $query = 'select * from web_site_config where parent=?';
       $query = $db->prepare_query($query,$id);
       $config_values = $db->get_records($query,'config_name',
                                         'config_value');
    }
    else {
       $config_values = array();   $id = null;
    }

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('website_tab','website_content','change_tab');
    $dialog->add_tab('website_tab','Web Site','website_tab','website_content',
                     'change_tab',true,null,FIRST_TAB);
    if ($shopping_cart) $tab_sequence = 0;
    else $tab_sequence = LAST_TAB;
    $dialog->add_tab('contact_tab','Contact Us','contact_tab',
                     'contact_content','change_tab',true,null,$tab_sequence);
    if ($shopping_cart) {
       $dialog->add_tab('cartconfig_tab','Cart Config','cartconfig_tab',
                        'cartconfig_content','change_tab');
       $dialog->add_tab('catalogconfig_tab','Catalog Config',
                        'catalogconfig_tab','catalogconfig_content',
                        'change_tab');
       if ($website_settings & WEBSITE_SEPARATE_PAYMENT)
          $dialog->add_tab('payment_tab','Payment','payment_tab',
                           'payment_content','change_tab');
       $dialog->add_tab('analytics_tab','Analytics','analytics_tab',
                        'analytics_content','change_tab',true,null,LAST_TAB);
    }
    $dialog->end_tab_row('tab_row_middle');

    $dialog->start_tab_content('website_content',true);
    $dialog->start_field_table('website_table');

    if ($edit_type == UPDATERECORD) $dialog->add_hidden_field('id',$id);
    $dialog->add_edit_row('Name:','name',$row,50);
    $dialog->add_edit_row('Hostname:','domain',$row,50);
    $dialog->add_edit_row('Root Directory:','rootdir',$row,50);
    $dialog->add_edit_row('Base Href:','base_href',$row,50);
    if ($website_settings & WEBSITE_SEPARATE_CMS) {
       $dialog->add_edit_row('CMS Program:','cms_program',$row,50);
       $dialog->add_edit_row('CMS URL:','cms_url',$row,50);
    }
    if ($edit_type == UPDATERECORD) $frame_name = 'edit_website';
    else $frame_name = 'add_website';
    $dialog->add_browse_row('Icon:','icon',$row,50,$frame_name,'/images',
                            false,false,true);
    $dialog->add_edit_row('Admin E-Mail:','config_admin_email',$row,50);
    $dialog->add_browse_row('E-Mail Logo:','config_email_logo',$row,
                            50,$frame_name,'',false,false,true,true);
    if ($shopping_cart || $catalog_site) {
       $dialog->start_row('Top Category:','middle');
       $top_category = get_row_value($row,'top_category');
       $dialog->write("<select name=\"top_category\" id=\"top_category\" " .
                      "class=\"select\" style=\"width:310px;\">\n");
       $dialog->add_list_item('0','',! $top_category);
       $query = 'select id,name from categories where (status=0)';
       if ($edit_type == UPDATERECORD)
          $query .= ' and (isnull(websites) or (websites="") or ' .
                    'find_in_set(?,websites))';
       else $query .= ' and (isnull(websites) or (websites=""))';
       $query .= ' order by name';
       if ($edit_type == UPDATERECORD) $query = $db->prepare_query($query,$id);
       $result = $db->query($query);
       if ($result) {
          while ($cat_row = $db->fetch_assoc($result))
             $dialog->add_list_item($cat_row['id'],$cat_row['name'],
                                    $top_category == $cat_row['id']);
          $db->free_result($result);
       }
       $dialog->end_choicelist();
       $dialog->end_row();
    }

    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->start_tab_content('contact_content',false);
    $dialog->start_field_table('contact_table');
    $dialog->add_edit_row('Company Name:','config_companyname',$row,50);
    $dialog->add_edit_row('Address:','config_map_address1',$row,50);
    $dialog->add_edit_row('Address2:','config_map_address2',$row,50);
    $dialog->add_edit_row('City:','config_map_city',$row,50);
    $dialog->start_row('State:');
    $dialog->add_input_field('config_map_state',$row,19);
    $dialog->add_inner_prompt('&nbsp;&nbsp;&nbsp;Zip:');
    $dialog->add_input_field('config_map_zip',$row,19);
    $dialog->end_row();
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('config_map_country',null,
                              'select" style="width: 327px;');
    load_country_list(get_row_value($row,'config_map_country'));
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Phone:','config_map_phone',$row,50);
    $dialog->add_edit_row('Fax:','config_map_fax',$row,50);
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt\" ");
    $dialog->write("style=\"text-align: center; padding-right: 50px;\"><i><u>");
    $dialog->write("Google Map Data</u></i>");
    $dialog->end_row();
    $dialog->start_row('Latitude:','middle');
    $dialog->add_input_field('map_latitude',$config_values,7);
    $dialog->add_inner_prompt('&nbsp;&nbsp;&nbsp;Longitude:&nbsp;');
    $dialog->add_input_field('map_longitude',$config_values,7);
    $dialog->write("&nbsp;&nbsp;<a href=\"#\" class=\"lookup_link\" onClick=\"" .
                   "lookup_address(); return false;\">Lookup</a>");
    $dialog->end_row();
    $dialog->start_row('Zoom:','middle');
    $dialog->add_input_field('map_zoom',$config_values,7);
    $dialog->write("&nbsp;&nbsp;&nbsp;\n");
    $dialog->add_checkbox_field('map_streetview','Show Street View',
                                $config_values,'toggle_street_view();');
    $dialog->write("&nbsp;&nbsp;<a href=\"#\" class=\"lookup_link\" " .
                   "onClick=\"return find_address(this);\" " .
                   "target=\"_blank\">Find Address</a>");
    $dialog->end_row();
    if (! empty($config_values['map_sv_latitude'])) $show_streetview = true;
    else $show_streetview = false;
    $dialog->write("<tr id=\"sv_row_0\"");
    if (! $show_streetview) $dialog->write(" style=\"display: none;\"");
    $dialog->write("><td colspan=\"2\" class=\"fieldprompt\" ");
    $dialog->write("style=\"text-align: center; padding-right: 50px;\"><i><u>" .
                   "Street View Data</u></i>");
    $dialog->end_row();
    $dialog->write("<tr id=\"sv_row_1\"");
    if (! $show_streetview) $dialog->write(" style=\"display: none;\"");
    $dialog->write("><td colspan=\"2\" style=\"padding-left:43px;\">\n");
    $dialog->write("<table cellspacing=\"4\" cellpadding=\"0\">\n");
    $dialog->write("<tr><td class=\"fieldprompt\" style=\"width: 98px;\">" .
                   "Latitude:</td>\n<td>");
    $dialog->add_input_field('map_sv_latitude',$config_values,7);
    $dialog->write("</td>\n<td class=\"fieldprompt\">&nbsp;&nbsp;&nbsp;" .
                   "Longitude:</td>\n<td>");
    $dialog->add_input_field('map_sv_longitude',$config_values,7);
    $dialog->end_row();
    $dialog->write("<tr style=\"height: 4px;\"><td colspan=\"4\"></td></tr>\n");
    $dialog->start_row('Pitch:');
    $dialog->add_input_field('map_pitch',$config_values,7);
    $dialog->write("</td>\n<td class=\"fieldprompt\">Yaw:</td>\n<td>");
    $dialog->add_input_field('map_yaw',$config_values,7);
    $dialog->end_row();
    $dialog->end_table();
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_tab_content();

    if ($shopping_cart) {
       $dialog->start_tab_content('cartconfig_content',false);
       $dialog->start_field_table('cartconfig_table');
       $dialog->add_edit_row('Contact Email:','contactemail',$config_values,50);
       $dialog->add_edit_row('Contact Phone:','contactphone',$config_values,50);
       $dialog->add_edit_row('Contact Hours:','contacthours',$config_values,50);
       $dialog->add_browse_row('Invoice Logo:','companylogo',$config_values,
                               50,$frame_name,'',false,false,true,true);
       $dialog->end_field_table();
       $dialog->end_tab_content();

       $dialog->start_tab_content('catalogconfig_content',false);
       $dialog->start_field_table('catalogconfig_table');
       add_catalog_config_rows($db,$dialog,$config_values,true,$id);
       $dialog->end_field_table();
       $dialog->end_tab_content();

       if ($website_settings & WEBSITE_SEPARATE_PAYMENT) {
          $dialog->start_tab_content('payment_content',false);
          $dialog->start_field_table('payment_table');
          $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
          load_payment_modules($db,true);
          call_payment_event('payment_cart_config_section',
                              array($db,&$dialog,$config_values));
          $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }

       load_analytics_config_tab($db,$dialog,$config_values);
    }

    $dialog->end_tab_section();
}

function save_website_config($db,$parent)
{
    global $website_settings;

    $config_fields = array('contactemail','contactphone','contacthours',
       'companylogo','map_latitude','map_longitude','map_zoom','map_streetview',
       'map_sv_latitude','map_sv_longitude','Longitude','map_pitch','map_yaw');

    $catalog_config_fields = get_catalog_config_fields(true);
    $config_fields = array_merge($config_fields,$catalog_config_fields);
    $website_settings = get_website_settings($db);
    if ($website_settings & WEBSITE_SEPARATE_PAYMENT) {
       load_payment_modules($db,true);
       call_payment_event('payment_update_cart_config_fields',
                           array(&$config_fields));
    }
    setup_analytics_config_fields($config_fields);

    $query = 'select * from web_site_config where parent=?';
    $query = $db->prepare_query($query,$parent);
    $config_values = $db->get_records($query,'config_name','config_value');
    $config_record = website_config_record_definition();
    $config_record['parent']['value'] = $parent;
    foreach ($config_fields as $field_name) {
       if (isset($config_values[$field_name]))
          $old_field_value = $config_values[$field_name];
       else $old_field_value = '';
       if (($website_settings & WEBSITE_SEPARATE_PAYMENT) &&
           call_payment_event('payment_update_cart_config_field',
                              array($field_name,&$new_field_value,$db),
                              true,true)) {
          if ($new_field_value === null) continue;
       }
       else $new_field_value = get_form_field($field_name);
       if ($old_field_value == $new_field_value) continue;
       $config_record['config_name']['value'] = $field_name;
       if (empty($new_field_value))
          $config_record['config_value']['value'] = '';
       else $config_record['config_value']['value'] = $new_field_value;
       if (isset($config_values[$field_name])) {
          if (! $db->update('web_site_config',$config_record)) {
             http_response(422,$db->error);   return false;
          }
       }
       else if (! $db->insert('web_site_config',$config_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    if ($website_settings & WEBSITE_SEPARATE_PAYMENT)
       call_payment_event('payment_update_cart_config',
                           array($config_values,$config_record,$db));
    return true;
}

function add_website()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('websites.css');
    $dialog->add_script_file('websites.js');
    $dialog->set_body_id('add_website');
    $dialog->set_help('add_website');
    $dialog->start_body('Add Web Site');
    $dialog->set_button_width(135);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Add Web Site','images/AddWebSite.png',
                        'process_add_website();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('websites.php','AddWebSite');
    if (! $dialog->skin) $dialog->start_field_table();
    display_website_fields($dialog,ADDRECORD,array(),$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_website()
{
    global $shopping_cart;

    $db = new DB;
    $website_record = website_record_definition();
    $db->parse_form_fields($website_record);
    if (! $db->insert('web_sites',$website_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    if ($shopping_cart) {
       if (! save_website_config($db,$id)) return;
    }
    http_response(201,'Web Site Added');
    log_activity('Added Web Site '.$website_record['domain']['value'] .
                 ' (#'.$id.')');
}

function edit_website()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from web_sites where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Web Site not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('websites.css');
    $dialog->add_script_file('websites.js');
    $dialog_title = 'Edit Web Site #'.$id.' ('.$row['name'].')';
    $dialog->set_body_id('edit_website');
    $dialog->set_help('edit_website');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(135);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Update','images/Update.png','update_website();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('websites.php','EditWebSite');
    if (! $dialog->skin) $dialog->start_field_table();
    display_website_fields($dialog,UPDATERECORD,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_website()
{
    global $shopping_cart;

    $db = new DB;
    $website_record = website_record_definition();
    $db->parse_form_fields($website_record);
    if (! $db->update('web_sites',$website_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $website_record['id']['value'];
    if ($shopping_cart) {
       if (! save_website_config($db,$id)) return;
    }
    http_response(201,'Web Site Updated');
    log_activity('Updated Web Site '.$website_record['domain']['value'] .
                 ' (#'.$id.')');
}

function delete_website()
{
    global $shopping_cart;

    $id = get_form_field('id');
    $db = new DB;
    $website_record = website_record_definition();
    $website_record['id']['value'] = $id;
    if (! $db->delete('web_sites',$website_record)) {
       http_response(422,$db->error);   return;
    }
    if ($shopping_cart) {
       $query = 'delete from web_site_config where parent=?';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,'Web Site Deleted');
    log_activity('Deleted Web Site #'.$id);
}

function website_settings()
{
    $website_settings = get_website_settings();
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('websites.css');
    $dialog->add_script_file('websites.js');
    $dialog->set_body_id('website_settings');
    $dialog->set_help('website_settings');
    $dialog->start_body('Web Site Settings');
    $dialog->set_button_width(135);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_settings();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('websites.php','Settings');
    $dialog->start_field_table('settings_table');

    $dialog->start_row('Cart/Orders:','middle');
    $dialog->add_radio_field('SharedCart','No','One per site',
                             ! ($website_settings & WEBSITE_SHARED_CART));
    $dialog->add_radio_field('SharedCart','Yes','Shared',
                             ($website_settings & WEBSITE_SHARED_CART));
    $dialog->end_row();

    $dialog->start_row('CMS:','middle');
    $dialog->add_radio_field('SharedCMS','No','One per site',
                             ($website_settings & WEBSITE_SEPARATE_CMS));
    $dialog->add_radio_field('SharedCMS','Yes','Shared',
                             ! ($website_settings & WEBSITE_SEPARATE_CMS));
    $dialog->end_row();

    $dialog->start_row('Payment Gateways:','middle');
    $dialog->add_radio_field('SharedPayment','No','One per site',
                             ($website_settings & WEBSITE_SEPARATE_PAYMENT));
    $dialog->add_radio_field('SharedPayment','Yes','Shared',
                             ! ($website_settings & WEBSITE_SEPARATE_PAYMENT));
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_settings()
{
    $db = new DB;

    $website_settings = 0;
    if (get_form_field('SharedCart') == 'Yes')
       $website_settings |= WEBSITE_SHARED_CART;
    if (get_form_field('SharedCMS') == 'No')
       $website_settings |= WEBSITE_SEPARATE_CMS;
    if (get_form_field('SharedPayment') == 'No')
       $website_settings |= WEBSITE_SEPARATE_PAYMENT;
    $query = 'select config_value from config where ' .
             'config_name="website_settings"';
    $row = $db->get_record($query);
    if ($row)
       $query = 'update config set config_value=? where ' .
                'config_name="website_settings"';
    else $query = 'insert into config values("website_settings",?)';
    $query = $db->prepare_query($query,$website_settings);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Web Site Settings Updated');
    log_activity('Updated Web Site Settings');
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'addwebsite') add_website();
else if ($cmd == 'processaddwebsite') process_add_website();
else if ($cmd == 'editwebsite') edit_website();
else if ($cmd == 'updatewebsite') update_website();
else if ($cmd == 'deletewebsite') delete_website();
else if ($cmd == 'settings') website_settings();
else if ($cmd == 'updatesettings') update_settings();
else display_websites_dialog();

DB::close_all();

?>

