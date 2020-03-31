<?php
/*
                       Inroads Shopping Cart - Vendors Tab

                       Written 2014-2019 by Randall Severy
                        Copyright 2014-2019 Inroads, LLC
*/

require '../engine/screen.php';
require '../engine/dialog.php';
require '../engine/db.php';
require 'utility.php';
require_once 'cartconfig-common.php';
require_once 'vendors-common.php';
require_once 'shopping-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

function vendor_import_record_definition($vendor_info=null)
{
    $vendor_import_record = array();
    $vendor_import_record['id'] = array('type' => INT_TYPE);
    $vendor_import_record['id']['key'] = true;
    $vendor_import_record['parent'] = array('type' => INT_TYPE);
    $vendor_import_record['name'] = array('type' => CHAR_TYPE);
    $vendor_import_record['import_type'] = array('type' => INT_TYPE);
    $vendor_import_record['import_source'] = array('type' => INT_TYPE);
    $vendor_import_record['auto_update'] = array('type' => INT_TYPE);
    $vendor_import_record['ftp_hostname'] = array('type' => CHAR_TYPE);
    $vendor_import_record['ftp_username'] = array('type' => CHAR_TYPE);
    $vendor_import_record['ftp_password'] = array('type' => CHAR_TYPE);
    $vendor_import_record['ftp_filename'] = array('type' => CHAR_TYPE);
    $vendor_import_record['unzip_filename'] = array('type' => CHAR_TYPE);
    $vendor_import_record['image_dir'] = array('type' => CHAR_TYPE);
    $vendor_import_record['import_file'] = array('type' => CHAR_TYPE);
    $vendor_import_record['sheet_num'] = array('type' => INT_TYPE);
    $vendor_import_record['start_row'] = array('type' => INT_TYPE);
    $vendor_import_record['txt_delim'] = array('type' => CHAR_TYPE);
    $vendor_import_record['avail_value'] = array('type' => CHAR_TYPE);
    $vendor_import_record['avail_status'] = array('type' => INT_TYPE);
    $vendor_import_record['notavail_value'] = array('type' => CHAR_TYPE);
    $vendor_import_record['notavail_status'] = array('type' => INT_TYPE);
    $vendor_import_record['new_status'] = array('type' => INT_TYPE);
    $vendor_import_record['noimage_status'] = array('type' => INT_TYPE);
    $vendor_import_record['load_existing'] = array('type' => INT_TYPE);
    $vendor_import_record['match_existing'] = array('type' => INT_TYPE);
    $vendor_import_record['addl_match_field'] = array('type' => CHAR_TYPE);
    $vendor_import_record['non_match_action'] = array('type' => INT_TYPE);
    $vendor_import_record['attribute_set'] = array('type' => INT_TYPE);
    $vendor_import_record['image_options'] = array('type' => INT_TYPE);
    $vendor_import_record['num_markups'] = array('type' => INT_TYPE);
    $vendor_import_record['discon_field'] = array('type' => INT_TYPE);
    $vendor_import_record['discon_value'] = array('type' => CHAR_TYPE);
    $vendor_import_record['min_qty_for_sale'] = array('type' => INT_TYPE);
    $vendor_import_record['partnum_prefix'] = array('type' => CHAR_TYPE);
    $vendor_import_record['new_inv_qty'] = array('type' => INT_TYPE);
    $vendor_import_record['group_template'] = array('type' => CHAR_TYPE);
    $vendor_import_record['shopping_gender'] = array('type' => CHAR_TYPE);
    $vendor_import_record['shopping_age'] = array('type' => CHAR_TYPE);
    $vendor_import_record['shopping_condition'] = array('type' => CHAR_TYPE);
    $vendor_import_record['shopping_flags'] = array('type' => INT_TYPE);
    $vendor_import_record['import_started'] = array('type' => INT_TYPE);
    $vendor_import_record['import_finished'] = array('type' => INT_TYPE);
    $vendor_import_record['flags'] = array('type' => INT_TYPE);
    if (function_exists('custom_vendor_import_fields'))
       custom_vendor_import_fields($vendor_import_record);
    if ($vendor_info)
       call_vendor_event($vendor_info,'import_fields',
                         array(&$vendor_import_record));
    call_shopping_event('import_fields',array(&$vendor_import_record));
    return $vendor_import_record;
}

function vendor_mapping_record_definition()
{
    $vendor_mapping_record = array();
    $vendor_mapping_record['id'] = array('type' => INT_TYPE);
    $vendor_mapping_record['id']['key'] = true;
    $vendor_mapping_record['parent'] = array('type' => INT_TYPE);
    $vendor_mapping_record['vendor_field'] = array('type' => CHAR_TYPE);
    $vendor_mapping_record['update_field'] = array('type' => CHAR_TYPE);
    $vendor_mapping_record['sequence'] = array('type' => INT_TYPE);
    $vendor_mapping_record['sep_char'] = array('type' => CHAR_TYPE);
    $vendor_mapping_record['required'] = array('type' => INT_TYPE);
    $vendor_mapping_record['required']['fieldtype'] = CHECKBOX_FIELD;
    $vendor_mapping_record['convert_funct'] = array('type' => CHAR_TYPE);
    return $vendor_mapping_record;
}

function vendor_markup_record_definition()
{
    $vendor_markup_record = array();
    $vendor_markup_record['id'] = array('type' => INT_TYPE);
    $vendor_markup_record['id']['key'] = true;
    $vendor_markup_record['parent'] = array('type' => INT_TYPE);
    $vendor_markup_record['markup_start'] = array('type' => FLOAT_TYPE);
    $vendor_markup_record['markup_end'] = array('type' => FLOAT_TYPE);
    $vendor_markup_record['markup_type'] = array('type' => INT_TYPE);
    $vendor_markup_record['markup_value'] = array('type' => FLOAT_TYPE);
    return $vendor_markup_record;
}

function add_vendor_fields($db,$fields)
{
    $field_defs = $db->get_field_defs('vendors');
    foreach ($fields as $field_def) {
       if (isset($field_defs[$field_def['name']])) continue;
       $field_column = $db->build_field_column($field_def);
       $query = 'alter table vendors add '.$field_column;
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    return true;
}

function add_import_fields($db,$fields)
{
    $field_defs = $db->get_field_defs('vendor_imports');
    foreach ($fields as $field_def) {
       if (isset($field_defs[$field_def['name']])) continue;
       $field_column = $db->build_field_column($field_def);
       $query = 'alter table vendor_imports add '.$field_column;
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    return true;
}

function add_module_vendor($db,$vendor_info)
{
    global $admin_directory;

    $module = $vendor_info['module'];
    $vendor_record = vendor_record_definition();
    foreach ($vendor_info as $field_name => $field_value)
       $vendor_record[$field_name]['value'] = $field_value;
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $module_file = $admin_directory.'vendors/'.$vendor_info['module'].'.php';
    $vendor_record['module_date']['value'] = filemtime($module_file);
    $vendor_record['last_modified']['value'] = time();
    if (! $db->insert('vendors',$vendor_record)) return 0;
    $id = $db->insert_id();
    log_activity('Created New Vendor #'.$id.' for Module '.$module);
    return $id;
}

function add_module_import($db,$vendor_info,$import_info)
{
    $import_record = vendor_import_record_definition($vendor_info);
    foreach ($import_info as $field_name => $field_value) {
       if ($field_name == 'mapping') continue;
       $import_record[$field_name]['value'] = $field_value;
    }
    if (! $db->insert('vendor_imports',$import_record)) return false;
    $id = $db->insert_id();
    if (! empty($import_info['mapping'])) {
       foreach ($import_info['mapping'] as $map_info) {
          $mapping_record = vendor_mapping_record_definition();
          $mapping_record['sep_char']['value'] = '';
          $mapping_record['required']['value'] = 0;
          $mapping_record['convert_funct']['value'] = '';
          foreach ($map_info as $field_name => $field_value)
             $mapping_record[$field_name]['value'] = $field_value;
          $mapping_record['parent']['value'] = $id;
          if (! $db->insert('vendor_mapping',$mapping_record)) return false;
       }
    }
    log_activity('Created New Vendor Import #'.$id.' for Module ' .
                 $vendor_info['module']);
    return true;
}

function delete_module_import($db,$import_info)
{
    $import_id = $import_info['id'];
    $query = 'delete from vendor_imports where id=?';
    $query = $db->prepare_query($query,$import_id);
    $db->log_query($query);
    if (! $db->query($query)) return;
    if (! empty($import_info['import_file'])) {
       $import_filename = '../admin/vendors/'.$import_info['import_file'];
       if (file_exists($import_filename)) unlink($import_filename);
    }
    log_activity('Deleted Vendor Import #'.$import_id.' by Vendor #' .
                 $import_info['parent']);
}

function initialize_vendor_modules($db)
{
    global $admin_directory;

    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $modules_dir = @opendir($admin_directory.'vendors/');
    if (! $modules_dir) return;
    $query = 'select id,name,module,module_date from vendors';
    $vendors = $db->get_records($query);

    while (($module = readdir($modules_dir)) !== false) {
       if (substr($module,-4) == '.php') {
          $module_name = substr($module,0,-4);
          $module_found = false;
          $module_file = $admin_directory.'vendors/'.$module;
          foreach ($vendors as $row) {
             if ($row['module'] == $module_name) {
                $module_date = filemtime($module_file);
                if ($module_date != $row['module_date']) {
                   require_once $module_file;
                   call_vendor_event($row,'upgrade',array($db));
                   $query = 'update vendors set module_date=? where id=?';
                   $query = $db->prepare_query($query,$module_date,$row['id']);
                   $db->log_query($query);
                   $db->query($query);
                }
                $module_found = true;   break;
             }
          }
          if (! $module_found) {
             require_once $module_file;
             call_vendor_event(array('module'=>$module_name),'install',
                               array($db));
          }
       }
    }
}

function load_vendor_info($db,$id)
{
    $query = 'select * from vendors where id=?';
    $query = $db->prepare_query($query,$id);
    $vendor_info = $db->get_record($query);
    return $vendor_info;
}

function display_vendors_screen()
{
    global $enable_vendor_imports;

    $db = new DB;
    initialize_vendor_modules($db);
    call_shopping_event('init_vendors',array($db));
    if (! empty($enable_vendor_imports)) {
       $query = 'select id,name from vendors order by name limit 1';
       $row = $db->get_record($query);
       if ($row) {
          $vendor_id = $row['id'];   $name = $row['name'];
       }
       else {
          $vendor_id = -1;   $name = '';
       }
       $imports_label = 'Imports for <span id="imports_label">'.$name.'</span>:';
    }

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_style_sheet('vendors.css');
    $screen->add_script_file('vendors.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    if (! empty($enable_vendor_imports)) {
       $script = "<script type=\"text/javascript\">\n";
       $script .= "       enable_vendor_imports = true;\n";
       $script .= "    </script>";
       $screen->add_head_line($script);
    }
    if (function_exists('custom_init_vendors_screen'))
       custom_init_vendors_screen($screen);
    $screen->set_body_id('vendors');
    $screen->set_help('vendors');
    $screen->start_body();
    $screen->set_button_width(148);
    if ($screen->skin) {
       if (! empty($enable_vendor_imports)) $screen->start_section();
       $screen->start_title_bar('Vendors');
       $screen->start_title_filters();
       add_search_box($screen,'search_vendors','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->start_button_column();
    $screen->add_button('Add Vendor','../cartengine/images/AddOrder.png',
                        'add_vendor();');
    $screen->add_button('Edit Vendor','../cartengine/images/EditOrder.png',
                        'edit_vendor();');
    $screen->add_button('Delete Vendor','../cartengine/images/DeleteOrder.png',
                        'delete_vendor();');
    if ($screen->skin) {
       $screen->end_button_column();
       $screen->write("          <script>load_vendors_grid();</script>\n");
       if (! empty($enable_vendor_imports)) {
          $screen->end_section();
          $screen->start_section();
          $screen->start_title_bar($imports_label);
          $screen->end_title_bar();
          $screen->start_button_column();
       }
    }
    else {
       add_search_box($screen,'search_vendors','reset_search');
       if (! empty($enable_vendor_imports)) {
          $screen->add_button_separator('imports_sep_row',20);
          $screen->write("<td colspan=\"2\"></td></tr>\n");
       }
       else $screen->end_button_column();
    }
    if (! empty($enable_vendor_imports)) {
       $screen->add_button('Add Import','../cartengine/images/AddOrder.png',
                           'add_import();');
       $screen->add_button('Edit Import','../cartengine/images/EditOrder.png',
                           'edit_import();','edit_import');
       $screen->add_button('Copy Import','../cartengine/images/EditOrder.png',
                           'copy_import();','copy_import');
       $screen->add_button('Delete Import','../cartengine/images/DeleteOrder.png',
                           'delete_import();','delete_import');
       $screen->add_button('Mapping','../cartengine/images/Update.png',
                           'import_mapping();','import_mapping');
       $screen->add_button('Upload Data','../cartengine/images/Update.png',
                           'import_upload();','import_upload');
       $screen->add_button('Get Data','../cartengine/images/Update.png',
                           'import_get();','import_get');
       $screen->add_button('Download Data','../cartengine/images/Update.png',
                           'import_download();','import_download');
       $screen->add_button('Run Import','../cartengine/images/Update.png',
                           'start_import();','start_import');
       $screen->add_button('Clear Errors','../cartengine/images/Update.png',
                           'clear_import();','clear_import',false);
       $screen->add_button('Reset Import','../cartengine/images/Update.png',
                           'reset_import();','reset_import',false);
       $screen->end_button_column();
    }
    if (! $screen->skin) {
       if (! empty($enable_vendor_imports))
          $screen->write("          <span class=\"fieldprompt\"" .
                         " style=\"text-align: left; font-weight: bold;\">" .
                         "Vendors</span><br>\n");
       $screen->write("          <script>load_vendors_grid();</script>\n");
       if (! empty($enable_vendor_imports))
          $screen->write("          <br><span class=\"fieldprompt\"" .
                         " style=\"text-align: left; font-weight: bold;\">" .
                         $imports_label."</span><br>\n");
    }
    if (! empty($enable_vendor_imports)) {
       $screen->write("          <script>load_imports_grid(".$vendor_id .
                      ");</script>\n");
       if ($screen->skin) $screen->end_section(true);
    }
    $screen->end_body();
}

function get_tab_sequence($tab,$first_tab,$last_tab)
{
    $tab_sequence = 0;
    if ($tab == $first_tab) $tab_sequence |= FIRST_TAB;
    if ($tab == $last_tab) $tab_sequence |= LAST_TAB;
    return $tab_sequence;
}

function add_markup_row($dialog,$index,$start,$end,$type,$markup)
{
    $dialog->start_row('From:');
    $dialog->add_input_field('markup_start_'.$index,$start,3);
    $dialog->write("</td>\n<td class=\"fieldprompt\">To:</td><td>");
    $dialog->add_input_field('markup_end_'.$index,$end,3);
    $dialog->write("</td>\n<td>");
    $dialog->add_radio_field('markup_type_'.$index,'1','+ %: ',($type == 1));
    $dialog->add_radio_field('markup_type_'.$index,'2','+ $: ',($type == 2));
    $dialog->write("</td>\n<td>");
    $dialog->add_input_field('markup_value_'.$index,$markup,3);
    $dialog->end_row();
}

function display_markup_tab($dialog,$edit_type,$num_markups,$parent,
                            $vendor_info,$db)
{
    $query = 'select * from vendor_markups where parent=? ' .
             'order by markup_start';
    $query = $db->prepare_query($query,$parent);
    $vendor_markups = $db->get_records($query);
    if (! $vendor_markups) $vendor_markups = array();
    $dialog->start_tab_content('markups_content',false);
    $dialog->start_field_table('markups_table',null,0);
    $index = 0;
    foreach ($vendor_markups as $vendor_markup)
       add_markup_row($dialog,$index++,$vendor_markup['markup_start'],
          $vendor_markup['markup_end'],$vendor_markup['markup_type'],
          $vendor_markup['markup_value']);
    while ($index < $num_markups)
       add_markup_row($dialog,$index++,'','','','');
    $dialog->end_field_table();
    if (count($vendor_markups) > $num_markups)
       $num_markups = count($vendor_markups);
    $dialog->add_hidden_field('NumMarkups',$num_markups);
    $dialog->add_hidden_field('OldNumMarkups',count($vendor_markups));
    if ($parent > 0) {
       if (function_exists('add_custom_vendor_fields'))
          add_custom_vendor_fields($dialog,$edit_type,$vendor_info,'markups');
       call_vendor_event($vendor_info,'add_vendor_fields',
                         array($dialog,$edit_type,$vendor_info,$db,'markups'));
    }
    $dialog->end_tab_content();
}

function display_vendor_fields($dialog,$edit_type,$row,$db)
{
    global $enable_vendor_imports;

    $id = get_row_value($row,'id');
    $query = 'select count(id) as num_products from products where vendor=?';
    $query = $db->prepare_query($query,$id);
    $summary_info = $db->get_record($query);
    if (! $summary_info) return;
    $num_products = $summary_info['num_products'];
    $query = 'select sum(i.price*i.qty) as total_sales,sum(i.cost*i.qty) as ' .
             'total_cost from order_items i join products p on p.id=' .
             'i.product_id where p.vendor=?';
    $query = $db->prepare_query($query,$id);
    $summary_info = $db->get_record($query);
    if (! $summary_info) return;
    $total_sales = $summary_info['total_sales'];
    $total_cost = $summary_info['total_cost'];
    $num_markups = get_row_value($row,'num_markups');
    if ($num_markups === '') $num_markups = 10;

    if (! empty($enable_vendor_imports))
       $vendor_tabs = array('summary'=>'Summary','account'=>'Account',
                            'markups'=>'Markups','settings'=>'Settings',
                            'edi'=>'EDI');
    else $vendor_tabs = array('summary'=>'Summary','account'=>'Account',
                              'settings'=>'Settings');
    if ((! $num_markups) || ($num_markups == -1))
       unset($vendor_tabs['markups']);
    call_vendor_event($row,'add_vendor_tabs',array(&$vendor_tabs,$db));
    $dialog->start_tab_section('tab_section');
    $first_tab = key($vendor_tabs);
    end($vendor_tabs);   $last_tab = key($vendor_tabs);
    $dialog->start_tab_row($first_tab.'_tab',$first_tab.'_content',
                           'change_tab');
    foreach ($vendor_tabs as $tab_name => $tab_label) {
       $tab_sequence = 0;
       if ($tab_name == $first_tab) $tab_sequence |= FIRST_TAB;
       if ($tab_name == $last_tab) $tab_sequence |= LAST_TAB;
       $dialog->add_tab($tab_name.'_tab',$tab_label,$tab_name.'_tab',
                        $tab_name.'_content','change_tab',true,null,
                        $tab_sequence);
    }
    $dialog->end_tab_row('tab_row_middle');

    foreach ($vendor_tabs as $tab_name => $tab_label) {
       if ($tab_name == 'summary') {
          $dialog->start_tab_content('summary_content',
                                     $first_tab == 'summary');
          $dialog->start_field_table('summary_table');
          $dialog->add_text_row('Total Sales:',
                                '$'.number_format($total_sales,2));
          $dialog->add_text_row('Total Cost:',
                                '$'.number_format($total_cost,2));
          $dialog->add_text_row('Number of Products:',$num_products);
          $last_products_import = get_row_value($row,'last_data_import');
          if ($last_products_import)
             $dialog->add_text_row('Latest Products Import:',
                date('n/j/y g:i:s a',$last_products_import));
          $last_inv_import = get_row_value($row,'last_inv_import');
          if ($last_inv_import)
             $dialog->add_text_row('Latest Inventory Import:',
                                   date('n/j/y g:i:s a',$last_inv_import));
          $last_data_import = get_row_value($row,'last_pdata_import');
          if ($last_data_import)
             $dialog->add_text_row('Latest Data Import:',
                                   date('n/j/y g:i:s a',$last_data_import));
          $last_price_import = get_row_value($row,'last_price_import');
          if ($last_price_import)
             $dialog->add_text_row('Latest Prices Import:',
                                   date('n/j/y g:i:s a',$last_price_import));
          $last_images_import = get_row_value($row,'last_images_import');
          if ($last_images_import)
             $dialog->add_text_row('Latest Images Import:',
                                   date('n/j/y g:i:s a',$last_images_import));
          if (function_exists('add_custom_vendor_fields'))
             add_custom_vendor_fields($dialog,$edit_type,$row,$tab_name);
          call_vendor_event($row,'add_vendor_fields',
                            array($dialog,$edit_type,$row,$db,$tab_name));
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'account') {
          $dialog->start_tab_content('account_content',
                                     $first_tab == 'account');
          $dialog->add_hidden_field('id',$id);
          $dialog->add_hidden_field('module',$row);
          $dialog->start_field_table('account_table');
          $dialog->add_edit_row('Name:','name',$row,30);
          $dialog->add_edit_row('Contact:','contact',$row,30);
          $dialog->add_edit_row('Company:','company',$row,50);
          $country = get_row_value($row,'country');
          if ($country == '') $country = 1;
          $dialog->add_edit_row('Address Line 1:','address1',$row,50);
          $dialog->add_edit_row('Address Line 2:','address2',$row,50);
          $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" " .
                         "nowrap id=\"city_prompt\">");
          if ($country == 29) print 'Parish';
          else print 'City';
          $dialog->write(":</td>\n<td>");
          $dialog->add_input_field('city',$row,30);
          $dialog->end_row();
          $state = get_row_value($row,'state');
          $dialog->start_hidden_row('State:','state_row',($country != 1),
                                    'middle');
          $dialog->start_choicelist('state',null);
          $dialog->add_list_item('','',false);
          load_state_list($state,true,$db);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->start_hidden_row('Province:','province_row',
             (($country == 1) || ($country == 29) || ($country == 43)));
          $dialog->add_input_field('province',$state,30);
          $dialog->end_row();
          $dialog->start_hidden_row('Province:','canada_province_row',
                                    ($country != 43),'middle');
          $dialog->start_choicelist('canada_province',null);
          $dialog->add_list_item('','',false);
          load_canada_province_list($state);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" " .
                         "nowrap id=\"zip_cell\">");
          if ($country == 1) $dialog->write('Zip Code:');
          else $dialog->write('Postal Code:');
          $dialog->write("</td><td>\n");
          $dialog->add_input_field('zipcode',$row,30);
          $dialog->end_row();
          $dialog->start_row('Country:','middle');
          $dialog->start_choicelist('country','select_country(this);');
          load_country_list($country,true,$db);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->add_edit_row('Contact Telephone:','phone',$row,30);
          $dialog->add_edit_row('Contact E-Mail Address:','email',$row,30);
          $dialog->add_edit_row('Name on Check:','name_on_check',$row,30);
          $dialog->add_edit_row('Account Number:','account_number',$row,30);
          if (! empty($enable_vendor_imports))
             $dialog->add_textarea_row('Return Address:','return_address',$row,
                                       4,50,WRAP_SOFT);
          if (function_exists('add_custom_vendor_fields'))
             add_custom_vendor_fields($dialog,$edit_type,$row,$tab_name);
          call_vendor_event($row,'add_vendor_fields',
                            array($dialog,$edit_type,$row,$db,$tab_name));
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'markups')
          display_markup_tab($dialog,$edit_type,$num_markups,$id,$row,$db);
       else if ($tab_name == 'settings') {
          $dialog->start_tab_content('settings_content',
                                     $first_tab == 'settings');
          $dialog->start_field_table('settings_table');
          if (! empty($enable_vendor_imports)) {
             $dialog->add_edit_row('Account Username:','username',$row,40);
             $dialog->add_edit_row('Account Password:','password',$row,40);
             $dialog->start_row('Markups By:','middle');
             $dialog->add_radio_field('markups_by','0','Vendor',
                                      ($num_markups != -1),'change_markups();');
             $dialog->add_radio_field('markups_by','-1','Import',
                                      ($num_markups == -1),'change_markups();');
             $dialog->end_row();
             $dialog->start_hidden_row('# of Markups:','num_markups_row',
                                       ($num_markups == -1));
             $dialog->add_input_field('num_markups',$num_markups,5);
             $dialog->end_row();
          }
          $dialog->add_edit_row('Default Shipping Cost:','default_shipping',
                                $row,5);
          if (! empty($enable_vendor_imports)) {
             $new_order_flag = get_row_value($row,'new_order_flag');
             $dialog->start_row('Send New Orders By:','top','fieldprompt',null,true);
             $dialog->add_radio_field('new_order_flag',DO_NOT_SEND,
                'Do Not Send',($new_order_flag == DO_NOT_SEND),
                'change_new_order_flag();');
             $dialog->add_radio_field('new_order_flag',SEND_ORDER_BY_EMAIL,
                'E-Mail',($new_order_flag == SEND_ORDER_BY_EMAIL),
                'change_new_order_flag();');
             $dialog->add_radio_field('new_order_flag',SEND_ORDER_BY_EDI,
                 'EDI',($new_order_flag == SEND_ORDER_BY_EDI),
                 'change_new_order_flag();');
             $dialog->add_radio_field('new_order_flag',SEND_ORDER_BY_UPLOAD,
                 'Upload',($new_order_flag == SEND_ORDER_BY_UPLOAD),
                 'change_new_order_flag();');
             $dialog->add_radio_field('new_order_flag',SEND_ORDER_BY_API,
                 'API',($new_order_flag == SEND_ORDER_BY_API),
                 'change_new_order_flag();');
             $dialog->end_row();
             $send_order_flag = get_row_value($row,'send_order_flag');
             $dialog->start_hidden_row('Send Orders:','send_order_row',
                (! $new_order_flag),'middle','fieldprompt',null,true);
             $dialog->add_radio_field('send_order_flag',SEND_ORDER_MANUALLY,
                'Send Manually',($send_order_flag == SEND_ORDER_MANUALLY));
             $dialog->add_radio_field('send_order_flag',SEND_ORDER_AUTO,
                'Send Automatically',($send_order_flag == SEND_ORDER_AUTO));
             $dialog->end_row();
             $dialog->start_hidden_row('E-Mail Orders To:',
                'submit_email_row',($new_order_flag != 1));
             $dialog->add_input_field('submit_email',$row,40);
             $dialog->end_row();
             $status_values = load_cart_options(ORDER_STATUS,$db);
             $num_status_values = count($status_values);
             $dialog->start_hidden_row('Status for Sent Orders:','sent_status_row',
                                       (! $new_order_flag),'middle');
             $sent_status = get_row_value($row,'sent_status');
             $dialog->start_choicelist('sent_status');
             $dialog->add_list_item('','',($sent_status === ''));
             for ($loop = 0;  $loop < $num_status_values;  $loop++)
                if (isset($status_values[$loop]))
                   $dialog->add_list_item($loop,$status_values[$loop],
                                          (string) $loop === $sent_status);
             $dialog->end_choicelist();
             $dialog->end_row();
          }
          if (function_exists('add_custom_vendor_fields'))
             add_custom_vendor_fields($dialog,$edit_type,$row,$tab_name);
          call_vendor_event($row,'add_vendor_fields',
                            array($dialog,$edit_type,$row,$db,$tab_name));
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'edi') {
          $dialog->start_tab_content('edi_content',$first_tab == 'edi');
          $dialog->start_field_table('edi_table');
          $edi_interface = get_row_value($row,'edi_interface');
          $dialog->start_row('Interface:','middle','fieldprompt',null,true);
          $dialog->add_radio_field('edi_interface','0','None',
             ($edi_interface == 0),'change_edi_interface()');
          $dialog->add_radio_field('edi_interface','1','AS2',
             ($edi_interface == 1),'change_edi_interface()');
          $dialog->add_radio_field('edi_interface','2','FTP',
             ($edi_interface == 2),'change_edi_interface()');
          $dialog->end_row();
          $dialog->start_hidden_row('Sender Qualifier:','edi_sender_row',
                                    ($edi_interface == 0));
          $dialog->add_input_field('edi_sender_qual',$row,2);
          $dialog->add_inner_prompt('Sender ID:');
          $dialog->add_input_field('edi_sender_id',$row,20);
          $dialog->end_row();
          $dialog->start_hidden_row('Receiver Qualifier:','edi_receiver_row',
                                    ($edi_interface == 0));
          $dialog->add_input_field('edi_receiver_qual',$row,2);
          $dialog->add_inner_prompt('Receiver ID:');
          $dialog->add_input_field('edi_receiver_id',$row,20);
          $dialog->end_row();
          $dialog->start_hidden_row('FTP Directory:','ftp_dir_row',
                                    ($edi_interface != 2));
          $dialog->add_input_field('edi_ftp_directory',$row,50);
          $dialog->end_row();
          if (function_exists('add_custom_vendor_fields'))
             add_custom_vendor_fields($dialog,$edit_type,$row,$tab_name);
          call_vendor_event($row,'add_vendor_fields',
                            array($dialog,$edit_type,$row,$db,$tab_name));
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else call_vendor_event($row,'vendor_tabs',
                              array($db,$row,&$dialog,$tab_name,$vendor_tabs));
    }
    $dialog->end_tab_section();
}

function parse_vendor_fields($db,&$vendor_record)
{
    $db->parse_form_fields($vendor_record);
    $markups_by = get_form_field('markups_by');
    if ($markups_by == -1) $vendor_record['num_markups']['value'] = -1;
    if ($vendor_record['country']['value'] == 43)
       $vendor_record['state']['value'] = get_form_field('canada_province');
    else if ($vendor_record['country']['value'] != 1)
       $vendor_record['state']['value'] = get_form_field('province');
    $vendor_record['last_modified']['value'] = time();
    if (function_exists('parse_custom_vendor_fields'))
       parse_custom_vendor_fields($db,$vendor_record);
    $vendor_info = $db->convert_record_to_array($vendor_record);
    call_vendor_event($vendor_info,'parse_vendor_fields',
                      array($db,&$vendor_record));
}

function update_vendor_markups($db,$parent)
{
    $old_num_markups = get_form_field('OldNumMarkups');
    if ($old_num_markups) {
       $query = 'delete from vendor_markups where parent=?';
       $query = $db->prepare_query($query,$parent);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return false;
       }
    }
    $num_markups = get_form_field('NumMarkups');
    $markup_record = vendor_markup_record_definition();
    $markup_record['parent']['value'] = $parent;
    for ($index = 0;  $index < $num_markups;  $index++) {
       $markup_start = get_form_field('markup_start_'.$index);
       $markup_end = get_form_field('markup_end_'.$index);
       $markup_value = get_form_field('markup_value_'.$index);
       if ((! $markup_start) && (! $markup_end) && (! $markup_value)) continue;
       $markup_record['markup_start']['value'] = $markup_start;
       $markup_record['markup_end']['value'] = $markup_end;
       $markup_record['markup_type']['value'] =
          get_form_field('markup_type_'.$index);
       $markup_record['markup_value']['value'] = $markup_value;
       if (! $db->insert('vendor_markups',$markup_record)) {
          http_response(422,$db->error);   return;
       }
    }
    return true;
}

function create_vendor()
{
    $db = new DB;
    $vendor_record = vendor_record_definition();
    $vendor_record['name']['value'] = 'New Vendor';
    if (! $db->insert('vendors',$vendor_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'vendor_id = '.$id.';';
    log_activity('Created New Vendor #'.$id);
}

function add_vendor()
{
    $id = get_form_field('id');

    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('vendors.css');
    $dialog->add_script_file('vendors.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_onload_function('add_vendor_onload();');
    $dialog_title = 'Add Vendor (#'.$id.')';
    $dialog->set_body_id('add_vendor');
    $dialog->set_help('add_vendor');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Vendor','../cartengine/images/AddOrder.png',
                        'process_add_vendor();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('vendors.php','AddVendor');
    if (! $dialog->skin) $dialog->start_field_table();
    $row = array();
    $row['id'] = $id;
    display_vendor_fields($dialog,ADDRECORD,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_vendor()
{
    $db = new DB;
    $vendor_record = vendor_record_definition();
    parse_vendor_fields($db,$vendor_record);
    if (! $db->update('vendors',$vendor_record)) {
       http_response(422,$db->error);   return;
    }
    $vendor_id = $vendor_record['id']['value'];
    if (($vendor_record['num_markups']['value'] != -1) &&
        (! update_vendor_markups($db,$vendor_id))) return;
    require_once '../engine/modules.php';
    if (module_attached('add_vendor')) {
       $vendor_info = $db->convert_record_to_array($vendor_record);
       update_vendor_info($db,$vendor_info);
       if (! call_module_event('add_vendor',array($db,$vendor_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Vendor Added');
    log_activity('Added Vendor '.$vendor_record['name']['value'].' (#'.
                 $vendor_id.')');
}

function edit_vendor()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = load_vendor_info($db,$id);
    if (! $row) {
       if (isset($db->error))
          process_error('Database Error: '.$db->error,0);
       else process_error('Vendor not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('vendors.css');
    $dialog->add_script_file('vendors.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog_title = 'Edit Vendor (#'.$id.')';
    if ($row['module']) $dialog_title .= ' ['.$row['module'].']';
    $dialog->set_body_id('edit_vendor');
    $dialog->set_help('edit_vendor');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_vendor();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('vendors.php','EditVendor');
    if (! $dialog->skin) $dialog->start_field_table();
    display_vendor_fields($dialog,UPDATERECORD,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_vendor()
{
    $db = new DB;
    $vendor_info = load_vendor_info($db,get_form_field('id'));
    $vendor_record = vendor_record_definition($vendor_info);
    parse_vendor_fields($db,$vendor_record);
    if (! $db->update('vendors',$vendor_record)) {
       http_response(422,$db->error);   return;
    }
    $vendor_id = $vendor_record['id']['value'];
    if (($vendor_record['num_markups']['value'] != -1) &&
        (! update_vendor_markups($db,$vendor_id))) return;
    require_once '../engine/modules.php';
    if (module_attached('update_vendor')) {
       $vendor_info = $db->convert_record_to_array($vendor_record);
       update_vendor_info($db,$vendor_info);
       if (! call_module_event('update_vendor',array($db,$vendor_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Vendor Updated');
    log_activity('Updated Vendor '.$vendor_record['name']['value'].' (#' .
                 $vendor_id.')');
}

function delete_vendor()
{
    $id = get_form_field('id');
    $db = new DB;
    $query = 'delete from vendor_imports where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from vendor_markups where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $cancel = get_form_field('cancel');
    if (! $cancel) {
       require_once '../engine/modules.php';
       if (module_attached('delete_vendor'))
          $vendor_info = load_vendor_info($db,$id);
    }
    $vendor_record = vendor_record_definition();
    $vendor_record['id']['value'] = $id;
    if (! $db->delete('vendors',$vendor_record)) {
       http_response(422,$db->error);   return;
    }
    if ($cancel) {
       $query = 'alter table vendors auto_increment=0';
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    else if (module_attached('delete_vendor')) {
       update_vendor_info($db,$vendor_info);
       if (! call_module_event('delete_vendor',array($db,$vendor_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Vendor Deleted');
    log_activity('Deleted Vendor #'.$id);
}

function add_vendor_shopping_flag($dialog,$index,$shopping_flags)
{
    global $max_shopping_flag;

    $checked = ($shopping_flags & (1 << $index));
    $dialog->add_checkbox_field('shopping_flag'.$index,'Publish',$checked);
    if ($index > $max_shopping_flag) $max_shopping_flag = $index;
}

function display_import_fields($db,$dialog,$edit_type,$row,$vendor_info)
{
    global $disable_catalog_config,$product_group_field,$shopping_modules;
    global $max_shopping_flag,$status_values,$num_status_values;

    if (! isset($disable_catalog_config)) $disable_catalog_config = false;
    if (! $disable_catalog_config) require_once 'catalogconfig-common.php';
    $status_values = load_cart_options(PRODUCT_STATUS,$db);
    $num_status_values = count($status_values);
    load_shopping_modules();

    $import_tabs = array('settings'=>'Settings','options'=>'Other Options',
                         'shopping'=>'Shopping');
    if (count($shopping_modules) == 0) unset($import_tabs['shopping']);
    if (($vendor_info['num_markups'] == -1)) {
       $num_markups = get_row_value($row,'num_markups');
       if ($num_markups === '') $num_markups = 10;
       if ($num_markups && ($edit_type == UPDATERECORD))
          $import_tabs['markups'] = 'Markups';
    }
    else $num_markups = 0;
    call_vendor_event($vendor_info,'add_import_tabs',
                      array(&$import_tabs,$vendor_info,$db));
    $dialog->start_tab_section('tab_section');
    $first_tab = key($import_tabs);
    end($import_tabs);   $last_tab = key($import_tabs);
    $dialog->start_tab_row($first_tab.'_tab',$first_tab.'_content',
                           'change_tab');
    foreach ($import_tabs as $tab_name => $tab_label) {
       $tab_sequence = 0;
       if ($tab_name == $first_tab) $tab_sequence |= FIRST_TAB;
       if ($tab_name == $last_tab) $tab_sequence |= LAST_TAB;
       $dialog->add_tab($tab_name.'_tab',$tab_label,$tab_name.'_tab',
                        $tab_name.'_content','change_tab',true,null,
                        $tab_sequence);
    }
    $dialog->end_tab_row('tab_row_middle');

    foreach ($import_tabs as $tab_name => $tab_label) {
       if ($tab_name == 'settings') {
          $dialog->start_tab_content('settings_content',
                                     $first_tab == 'settings');
          $dialog->start_field_table('import_settings',
                                     'fieldtable add_edit_import_table');

          $dialog->add_section_row('Import Settings');
          if ($edit_type == UPDATERECORD) {
             $dialog->add_hidden_field('id',$row);
             $dialog->add_hidden_field('parent',$row);
          }
          else $dialog->add_hidden_field('parent',get_form_field('parent'));
          $dialog->add_edit_row('Name:','name',$row,48);
          $import_type = get_row_value($row,'import_type');
          $dialog->start_row('Import Type:','middle');
          $dialog->add_radio_field('import_type','1','Products',
             ($import_type == PRODUCTS_IMPORT_TYPE),'change_import_type()');
          $dialog->add_radio_field('import_type','2','Inventory',
             ($import_type == INVENTORY_IMPORT_TYPE),'change_import_type()');
          $dialog->add_radio_field('import_type','5','Data',
             ($import_type == DATA_IMPORT_TYPE),'change_import_type()');
          $dialog->add_radio_field('import_type','3','Prices',
             ($import_type == PRICES_IMPORT_TYPE),'change_import_type()');
          $dialog->add_radio_field('import_type','4','Images',
             ($import_type == IMAGES_IMPORT_TYPE),'change_import_type()');
          $dialog->end_row();
          $import_source = get_row_value($row,'import_source');
          if ($import_source < 0) {
             $other_import_source = -$import_source;
             $import_source = OTHER_IMPORT_SOURCE;
          }
          else $other_import_source = -1;
          $dialog->start_row('Import Source:','middle');
          $dialog->add_radio_field('import_source','1','FTP',
                                   ($import_source == FTP_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->add_radio_field('import_source','4','SFTP',
                                   ($import_source == SFTP_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->add_radio_field('import_source','2','Upload',
                                   ($import_source == UPLOAD_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->add_radio_field('import_source','5','Download',
                                   ($import_source == DOWNLOAD_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->add_radio_field('import_source','3','EDI',
                                   ($import_source == EDI_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->add_radio_field('import_source','7','API',
                                   ($import_source == API_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->add_radio_field('import_source','6','Other Import',
                                   ($import_source == OTHER_IMPORT_SOURCE),
                                   'change_import_source()');
          $dialog->end_row();
          if ($import_source == OTHER_IMPORT_SOURCE) $hide_other = false;
          else $hide_other = true;
          $dialog->start_hidden_row('Other Import Source:',
             'other_import_source_row',$hide_other,'middle','fieldprompt',
             null,true);
          $dialog->start_choicelist('other_import_source');
          $dialog->add_list_item('','',$other_import_source == -1);
          $query = 'select id,name from vendor_imports where parent=? ' .
                   'order by name';
          $query = $db->prepare_query($query,$row['parent']);
          $imports = $db->get_records($query);
          if ($imports) foreach ($imports as $import_info) {
             if ((! empty($row['id'])) && ($import_info['id'] == $row['id']))
                continue;
             $dialog->add_list_item($import_info['id'],$import_info['name'],
                $import_info['id'] == $other_import_source);
          }
          $dialog->end_choicelist();
          $dialog->end_row();
          $auto_update = get_row_value($row,'auto_update');
          $dialog->start_row('Auto Update Frequency:','middle');
          $dialog->add_radio_field('auto_update','0','None',
                                   ($auto_update == AUTO_UPDATE_NONE));
          $dialog->add_radio_field('auto_update','1','Hourly',
                                   ($auto_update == AUTO_UPDATE_HOURLY));
          $dialog->add_radio_field('auto_update','2','Daily',
                                   ($auto_update == AUTO_UPDATE_DAILY));
          $dialog->add_radio_field('auto_update','3','Weekly',
                                   ($auto_update == AUTO_UPDATE_WEEKLY));
          $dialog->add_radio_field('auto_update','4','Monthly',
                                   ($auto_update == AUTO_UPDATE_MONTHLY));
          $dialog->end_row();
          if (($import_source == FTP_IMPORT_SOURCE) ||
              ($import_source == SFTP_IMPORT_SOURCE)) $hide_ftp = false;
          else $hide_ftp = true;
          if ($import_source == EDI_IMPORT_SOURCE) $edi_source = true;
          else $edi_source = false;
          $dialog->start_hidden_row('FTP Host:','ftp_host_row',$hide_ftp,'bottom',
                                    'fieldprompt',null,true);
          $dialog->add_input_field('ftp_hostname',$row,57);
          $dialog->end_row();
          $dialog->start_hidden_row('FTP User:','ftp_user_row',$hide_ftp,'bottom',
                                    'fieldprompt',null,true);
          $dialog->add_input_field('ftp_username',$row,20);
          $dialog->add_inner_prompt('Password:');
          $dialog->add_input_field('ftp_password',$row,20);
          $dialog->end_row();
          $dialog->start_hidden_row('FTP Filename:','ftp_file_row',$hide_ftp,
                                    'bottom','fieldprompt',null,true);
          $dialog->add_input_field('ftp_filename',$row,75);
          $dialog->end_row();
          $ftp_filename = get_row_value($row,'ftp_filename');
          if (substr(strtolower($ftp_filename),-4) == '.zip')
             $hide_unzip = false;
          else if ($import_source == DOWNLOAD_IMPORT_SOURCE)
             $hide_unzip = false;
          else $hide_unzip = true;
          $dialog->start_hidden_row('Unzip Filename:','unzip_file_row',
                                    $hide_unzip,'bottom','fieldprompt',null,
                                    true);
          $dialog->add_input_field('unzip_filename',$row,75);
          $dialog->end_row();
          $dialog->start_hidden_row('Image Dir/URL:','image_dir_row',$edi_source);
          $dialog->add_input_field('image_dir',$row,75);
          $dialog->end_row();
          $import_file = get_row_value($row,'import_file');
          if ($import_file && (! file_exists('../admin/vendors/'.$import_file)))
             $import_file = null;
          $dialog->add_hidden_field('import_file',$import_file);
          if ($import_file) {
             $file_url = '../admin/vendors/'.$import_file;
             $dialog->start_row('Data File:');
             $dialog->write('<tt><a href="'.$file_url.'">'.$import_file.'</a></tt>');
             $dialog->add_inner_prompt('Data Updated:');
             $updated = filemtime('../admin/vendors/'.$import_file);
             $dialog->write('<tt>'.date('n/j/y g:i:s a',$updated).'</tt>');
             $dialog->end_row();
          }

          $import_started = get_row_value($row,'import_started');
          if ($import_started) {
             $dialog->start_row('Import Started:');
             $dialog->write('<tt>'.date('n/j/y g:i:s a',$import_started).'</tt>');
             $import_finished = get_row_value($row,'import_finished');
             if ($import_finished) {
                $dialog->add_inner_prompt('Import Finished:');
                $dialog->write('<tt>'.date('n/j/y g:i:s a',$import_finished).'</tt>');
             }
             $dialog->end_row();
          }
          if ($edit_type == UPDATERECORD) {
             $import_id = get_row_value($row,'id');
             $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
             if (file_exists($import_log_filename)) {
                $last_error = file_get_contents($import_log_filename);
                if (strlen($last_error) > 200)
                   $last_error = substr($last_error,0,200).'...';
                $dialog->add_text_row('Last Error:',$last_error,'top');
             }
          }
          if (function_exists('add_custom_vendor_import_fields'))
             add_custom_vendor_import_fields($dialog,$row,'import_settings');
          call_vendor_event($vendor_info,'add_import_fields',
                            array($db,$dialog,$edit_type,$row,'import_settings'));
      
          $dialog->add_section_row('Import Options');

          $dialog->start_hidden_row('Sheet #:','sheet_row',$edi_source);
          $dialog->add_input_field('sheet_num',$row,1);
          $dialog->add_inner_prompt('Header Row:');
          $dialog->add_input_field('start_row',$row,1);
          $dialog->add_inner_prompt('Delimiter:');
          $dialog->add_input_field('txt_delim',$row,1);
          $dialog->end_row();

          $load_existing = get_row_value($row,'load_existing');
          $dialog->start_hidden_row('Load Existing Products:','load_existing_row',
                                    $edi_source,'middle');
          $dialog->add_radio_field('load_existing','0','All Vendor Products',
                                   $load_existing == LOAD_ALL_VENDOR_PRODUCTS);
          $dialog->add_radio_field('load_existing','1','This Import Only',
                                   $load_existing == LOAD_IMPORT_PRODUCTS);
          $dialog->add_radio_field('load_existing','2','Other Import',
                                   $load_existing == LOAD_OTHER_IMPORT_PRODUCTS);
          $dialog->end_row();

          $match_existing = get_row_value($row,'match_existing');
          $dialog->start_row('Match Existing Products By:','middle');
          $dialog->add_radio_field('match_existing','1','Part Number',
                                   $match_existing == MATCH_BY_PART_NUMBER);
          $dialog->add_radio_field('match_existing','2','MPN',
                                   $match_existing == MATCH_BY_MPN);
          $dialog->add_radio_field('match_existing','3','UPC',
                                   $match_existing == MATCH_BY_UPC);
          call_vendor_event($vendor_info,'display_custom_match',
                            array($dialog,$match_existing));
          $dialog->end_row();

          $dialog->start_row('Additional Match Field:');
          if ($edit_type == ADDRECORD) $update_fields = null;
          else {
             $query = 'select update_field from vendor_mapping where parent=? ' .
                      'order by update_field';
             $query = $db->prepare_query($query,$row['id']);
             $update_fields = $db->get_records($query,null,'update_field');
          }
          if (! $update_fields) $dialog->write('No Mapped Fields Found');
          else {
             $addl_match_field = get_row_value($row,'addl_match_field');
             $dialog->start_choicelist('addl_match_field');
             $dialog->add_list_item('','',(! $addl_match_field));
             foreach ($update_fields as $update_field) {
                $field_parts = explode('|',$update_field);
                if ((count($field_parts) != 2) || ($field_parts[1] == '*')) continue;
                $dialog->add_list_item($update_field,$field_parts[1],
                                       $addl_match_field === $update_field);
             }
             $dialog->end_choicelist();
          }
          $dialog->end_row();

          $query = 'select * from attribute_sets';
          $sets = $db->get_records($query,'id','name');
          if (! empty($sets)) {
             $attribute_set = get_row_value($row,'attribute_set');
             $dialog->start_row('Attribute Set for New Products:');
             $dialog->start_choicelist('attribute_set');
             $dialog->add_list_item('','',(! $attribute_set));
             foreach ($sets as $set_id => $set_name) {
                $dialog->add_list_item($set_id,$set_name,$attribute_set == $set_id);
             }
             $dialog->end_choicelist();
             $dialog->end_row();
          }

          $dialog->start_hidden_row('Image Options:','image_options_row',
                                    $edi_source,'top');
          $image_options = get_row_value($row,'image_options');
          $dialog->add_checkbox_field('image_options_1','Download Only New Images',
                                      $image_options & DOWNLOAD_NEW_IMAGES_ONLY);
          $dialog->write("<br>\n");
          $dialog->add_checkbox_field('image_options_2',
                                      'Do Not Delete Images Not In Import',
                                      $image_options & DO_NOT_DELETE_MISSING_IMAGES);
          $dialog->end_row();
          if ($vendor_info['num_markups'] == -1)
             $dialog->add_edit_row('# of Markups:','num_markups',
                                   $num_markups,5);
          if (function_exists('add_custom_vendor_import_fields'))
             add_custom_vendor_import_fields($dialog,$row,'import_options');
          call_vendor_event($vendor_info,'add_import_fields',
                            array($db,$dialog,$edit_type,$row,'import_options'));

          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'options') {
          $dialog->start_tab_content('options_content',
                                     ($first_tab == 'options'));
          $dialog->start_field_table('import_options',
                                     'fieldtable add_edit_import_table');

          $dialog->add_section_row('Status Options');

          $dialog->start_hidden_row('Available Value:','avail_row',$edi_source,
                                    'middle');
          $dialog->add_input_field('avail_value',$row,10);
          $dialog->add_inner_prompt('Status:');
          $avail_status = get_row_value($row,'avail_status');
          $dialog->start_choicelist('avail_status');
          $dialog->add_list_item('','',($avail_status === ''));
          for ($loop = 0;  $loop < $num_status_values;  $loop++)
             if (isset($status_values[$loop]))
                $dialog->add_list_item($loop,$status_values[$loop],
                                       (string) $loop === $avail_status);
          $dialog->end_choicelist();
          $dialog->end_row();

          $dialog->start_hidden_row('Not Available Value:','notavail_row',
                                    $edi_source,'middle');
          $dialog->add_input_field('notavail_value',$row,10);
          $dialog->add_inner_prompt('Status:');
          $notavail_status = get_row_value($row,'notavail_status');
          $dialog->start_choicelist('notavail_status');
          $dialog->add_list_item('','',($notavail_status === ''));
          for ($loop = 0;  $loop < $num_status_values;  $loop++)
             if (isset($status_values[$loop]))
                $dialog->add_list_item($loop,$status_values[$loop],
                                       (string) $loop === $notavail_status);
          $dialog->end_choicelist();
          $dialog->end_row();

          $dialog->start_hidden_row('Status for New Products:','new_status_row',
                              $edi_source,'middle');
          $new_status = get_row_value($row,'new_status');
          $dialog->start_choicelist('new_status');
          $dialog->add_list_item('','Do Not Add',($new_status === ''));
          for ($loop = 0;  $loop < $num_status_values;  $loop++)
             if (isset($status_values[$loop]))
                $dialog->add_list_item($loop,$status_values[$loop],
                                       (string) $loop === $new_status);
          $dialog->end_choicelist();
          $dialog->end_row();

          $non_match_action = get_row_value($row,'non_match_action');
          $dialog->start_hidden_row('Products Not In Import:','non_match_row',
                                    $edi_source,'middle','fieldprompt',null,true);
          $dialog->add_radio_field('non_match_action','-2','Skip',
                                   $non_match_action == NON_MATCH_SKIP);
          $dialog->add_radio_field('non_match_action','-1','Delete',
                                   $non_match_action == NON_MATCH_DELETE);
          $dialog->add_radio_field('non_match_action','-3','Status:',
                                   $non_match_action >= 0);
          $dialog->start_choicelist('non_match_status');
          $dialog->add_list_item('','',($non_match_action < 0));
          for ($loop = 0;  $loop < $num_status_values;  $loop++)
             if (isset($status_values[$loop]))
                $dialog->add_list_item($loop,$status_values[$loop],
                                       $loop == $non_match_action);
          $dialog->end_choicelist();
          $dialog->end_row();

          $dialog->start_hidden_row('Status for Products w/o Images:',
                                    'noimage_status_row',$edi_source,'middle');
          $noimage_status = get_row_value($row,'noimage_status');
          $dialog->start_choicelist('noimage_status');
          $dialog->add_list_item('','',($noimage_status === ''));
          for ($loop = 0;  $loop < $num_status_values;  $loop++)
             if (isset($status_values[$loop]))
                $dialog->add_list_item($loop,$status_values[$loop],
                                       (string) $loop === $noimage_status);
          $dialog->end_choicelist();
          $dialog->end_row();

          $dialog->start_hidden_row('Discontinued Field:','discon_row',
                                    (! $edi_source));
          $dialog->add_input_field('discon_field',$row,1);
          $dialog->add_inner_prompt('Value:');
          $dialog->add_input_field('discon_value',$row,15);
          $dialog->end_row();
          $dialog->start_hidden_row('Min Qty for On Sale:','min_qty_row',
                                    (! $edi_source));
          $dialog->add_input_field('min_qty_for_sale',$row,1);
          $dialog->end_row();
          $dialog->start_hidden_row('Discontinued Status:','discon_status_row',
                                    (! $edi_source));
          $notavail_status = get_row_value($row,'notavail_status');
          $dialog->start_choicelist('discon_status');
          $dialog->add_list_item('','',($notavail_status === ''));
          for ($loop = 0;  $loop < $num_status_values;  $loop++)
             if (isset($status_values[$loop]))
                $dialog->add_list_item($loop,$status_values[$loop],
                                       (string) $loop === $notavail_status);
          $dialog->end_choicelist();
          $dialog->end_row();
          if (function_exists('add_custom_vendor_import_fields'))
             add_custom_vendor_import_fields($dialog,$row,'status_options');
          call_vendor_event($vendor_info,'add_import_fields',
                            array($db,$dialog,$edit_type,$row,'status_options'));

          $dialog->add_section_row('Inventory Options');

          $dialog->add_edit_row('Part Number Prefix:','partnum_prefix',$row,10);

          $dialog->start_hidden_row('Inventory Quantity:','new_inv_qty_row',
                                    $edi_source);
          $dialog->add_input_field('new_inv_qty',$row,5);
          $dialog->write('for New Products');
          $dialog->end_row();

          $dialog->add_hidden_section_row('Product Group Options',
                                          'group_options_row',$edi_source);

          if (isset($product_group_field))
             $dialog->add_text_row('Product Group Field:',$product_group_field);

          if (! $disable_catalog_config) {
             $templates = load_catalog_templates($db);
             $dialog->start_hidden_row('Product Group Template:',
                                       'group_template_row',$edi_source,'middle');
             $group_template = get_row_value($row,'group_template');
             $dialog->start_choicelist('group_template');
             $dialog->add_list_item('','',($group_template === ''));
             if ($templates) foreach ($templates as $filename)
                $dialog->add_list_item($filename,$filename,
                                       $filename == $group_template);
             $dialog->end_choicelist();
             $dialog->end_row();
          }
          if (function_exists('add_custom_vendor_import_fields'))
             add_custom_vendor_import_fields($dialog,$row,'inventory_options');
          call_vendor_event($vendor_info,'add_import_fields',
                            array($db,$dialog,$edit_type,$row,'inventory_options'));

          $dialog->start_hidden_row('Product Group Options:','flags_row',
                                    $edi_source,'top');
          $flags = get_row_value($row,'flags');
          $dialog->add_checkbox_field('flags_1',
                                      'Add Products in Group as Related Products',
                                      $flags & ADD_GROUP_RELATED);
          $dialog->write("<br>\n");
          $dialog->add_checkbox_field('flags_2',
                                      'Reset All Vendor Sub Products after Import',
                                      $flags & RESET_VENDOR_SUB_PRODUCTS);
          $dialog->end_row();
          if (function_exists('add_custom_vendor_import_fields'))
             add_custom_vendor_import_fields($dialog,$row,'group_options');
          call_vendor_event($vendor_info,'add_import_fields',
                            array($db,$dialog,$edit_type,$row,'group_options'));
      
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'shopping') {
          $dialog->start_tab_content('shopping_content',
                                     ($first_tab == 'shopping'));
          $dialog->start_field_table('import_shopping',
                                     'fieldtable add_edit_import_table');
          $dialog->add_section_row('Default Shopping Field Values for New Products');
          $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                         "Standard Shopping Fields</td></tr>\n");
          $dialog->start_row('Gender:','middle');
          $gender = strtolower(trim(get_row_value($row,'shopping_gender')));
          $genders = array('male','female','unisex');
          $dialog->start_choicelist('shopping_gender');
          $dialog->add_list_item('','',false);
          foreach ($genders as $value)
             $dialog->add_list_item($value,$value,$gender == $value);
          if ($gender && (! in_array($gender,$genders)))
             $dialog->add_list_item($gender,$gender,true);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->start_row('Age:','middle');
          $age = strtolower(trim(get_row_value($row,'shopping_age')));
          if ($age == 'child') $age = 'kids';
          $ages = array('newborn','infant','toddler','kids','adult');
          $dialog->start_choicelist('shopping_age');
          $dialog->add_list_item('','',false);
          foreach ($ages as $value)
             $dialog->add_list_item($value,$value,$age == $value);
          if ($age && (! in_array($age,$ages)))
             $dialog->add_list_item($age,$age,true);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->start_row('Condition:','middle');
          $condition = strtolower(trim(get_row_value($row,'shopping_condition')));
          $conditions = array('new','refurbished','used');
          $dialog->start_choicelist('shopping_condition');
          $dialog->add_list_item('','',false);
          foreach ($conditions as $value)
             $dialog->add_list_item($value,$value,$condition == $value);
          if ($condition && (! in_array($condition,$conditions)))
             $dialog->add_list_item($condition,$condition,true);
          $dialog->end_choicelist();
          $dialog->end_row();
          call_shopping_event('add_import_fields',
                              array($db,&$dialog,$edit_type,$row));
          if ($max_shopping_flag != -1)
             $dialog->add_hidden_field('MaxShoppingFlag',$max_shopping_flag);
          if (function_exists('add_custom_vendor_import_fields'))
             add_custom_vendor_import_fields($dialog,$row,'shopping');
          call_vendor_event($vendor_info,'add_import_fields',
                            array($db,$dialog,$edit_type,$row,'shopping'));
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'markups') {
          $parent = -$row['id'];
          display_markup_tab($dialog,$edit_type,$num_markups,$parent,
                             $vendor_info,$db);
       }
       else call_vendor_event($vendor_info,'import_tabs',
                              array($db,$row,&$dialog,$tab_name,$vendor_info,
                                    $import_tabs));
    }

    $dialog->end_tab_section();
}

function parse_import_fields($db,&$import_record,$vendor_info)
{
    $db->parse_form_fields($import_record);
    if (isset($import_record['import_source']['value']) &&
        ($import_record['import_source']['value'] == EDI_IMPORT_SOURCE))
       $import_record['notavail_status']['value'] =
          get_form_field('discon_status');
    if (isset($import_record['import_source']['value']) &&
        ($import_record['import_source']['value'] == OTHER_IMPORT_SOURCE))
       $import_record['import_source']['value'] =
          -get_form_field('other_import_source');
    if ($import_record['non_match_action']['value'] == NON_MATCH_STATUS) {
       $non_match_status = get_form_field('non_match_status');
       if ($non_match_status === '')
          $import_record['non_match_action']['value'] = NON_MATCH_SKIP;
       else $import_record['non_match_action']['value'] = $non_match_status;
    }
    $image_options = 0;
    if (get_form_field('image_options_1') == 'on')
       $image_options |= DOWNLOAD_NEW_IMAGES_ONLY;
    if (get_form_field('image_options_2') == 'on')
       $image_options |= DO_NOT_DELETE_MISSING_IMAGES;
    $import_record['image_options']['value'] = $image_options;
    $max_shopping_flag = get_form_field('MaxShoppingFlag');
    if ($max_shopping_flag !== null) {
       $shopping_flags = parse_shopping_flags($max_shopping_flag);
       $import_record['shopping_flags']['value'] = $shopping_flags;
    }
    $flags = 0;
    if (get_form_field('flags_1') == 'on') $flags |= ADD_GROUP_RELATED;
    if (get_form_field('flags_2') == 'on') $flags |= RESET_VENDOR_SUB_PRODUCTS;
    $import_record['flags']['value'] = $flags;
    call_vendor_event($vendor_info,'parse_import_fields',
                      array($db,&$import_record));
}

function add_import()
{
    $db = new DB;
    $vendor_id = get_form_field('parent');
    $vendor_info = load_vendor_info($db,$vendor_id);
    $row = array('parent'=>$vendor_id);
    call_vendor_event($vendor_info,'init_add_import',array($db,&$row));
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('vendors.css');
    $dialog->add_script_file('vendors.js');
    $dialog->set_onload_function('add_edit_import_onload();');
    call_shopping_event('vendor_import_head',array(&$dialog,$db));
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('add_vendor_import');
    $dialog->set_help('add_vendor_import');
    $dialog->start_body('Add Import');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Import','../cartengine/images/AddOrder.png',
                        'process_add_import();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('vendors.php','AddImport');
    display_import_fields($db,$dialog,ADDRECORD,$row,$vendor_info);
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_import()
{
    $db = new DB;
    $vendor_info = load_vendor_info($db,get_form_field('parent'));
    $import_record = vendor_import_record_definition($vendor_info);
    parse_import_fields($db,$import_record,$vendor_info);
    if (function_exists('update_custom_vendor_import_fields'))
       update_custom_vendor_import_fields($db,$import_record);
    call_vendor_event($vendor_info,'update_import_fields',
                      array($db,$import_record));
    if (! $db->insert('vendor_imports',$import_record)) {
       http_response(422,$db->error);   return;
    }
    $import_id = $db->insert_id();
    if (($vendor_info['num_markups'] == -1) &&
        (! update_vendor_markups($db,-$import_id))) return;
    call_vendor_event($vendor_info,'finish_add_import',
                      array($db,$import_id,$vendor_info,$import_record));
    http_response(201,'Vendor Import Added');
    log_activity('Added Vendor Import '.$import_record['name']['value'] .
                 ' (#'.$import_id.')');
}

function edit_import()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from vendor_imports where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Import not found',0);
       return;
    }
    $vendor_info = load_vendor_info($db,$row['parent']);
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('vendors.css');
    $dialog->add_script_file('vendors.js');
    $dialog->set_onload_function('add_edit_import_onload();');
    call_shopping_event('vendor_import_head',array(&$dialog,$db));
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog_title = 'Edit Import (#'.$id.')';
    $dialog->set_body_id('edit_vendor_import');
    $dialog->set_help('edit_vendor_import');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_import();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('vendors.php','EditImport');
    display_import_fields($db,$dialog,UPDATERECORD,$row,$vendor_info);
    $dialog->end_form();
    $dialog->end_body();
}

function update_import()
{
    $db = new DB;
    $vendor_info = load_vendor_info($db,get_form_field('parent'));
    $import_record = vendor_import_record_definition($vendor_info);
    parse_import_fields($db,$import_record,$vendor_info);
    if (function_exists('update_custom_vendor_import_fields'))
       update_custom_vendor_import_fields($db,$import_record);
    call_vendor_event($vendor_info,'update_import_fields',
                      array($db,$import_record));
    if (! $db->update('vendor_imports',$import_record)) {
       http_response(422,$db->error);   return;
    }
    $import_id = $import_record['id']['value'];
    if (($vendor_info['num_markups'] == -1) &&
        (! update_vendor_markups($db,-$import_id))) return;
    http_response(201,'Vendor Import Updated');
    log_activity('Updated Vendor Import '.$import_record['name']['value'] .
                 ' (#'.$import_id.')');
}

function copy_import()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from vendor_imports where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    $import_name = $row['name'];
    $vendor_info = load_vendor_info($db,$row['parent']);
    $import_record = vendor_import_record_definition($vendor_info);
    foreach ($row as $field_name => $field_value) {
       if (isset($import_record[$field_name]))
          $import_record[$field_name]['value'] = $field_value;
    }
    unset($import_record['id']['value']);
    unset($import_record['import_file']['value']);
    unset($import_record['import_started']['value']);
    unset($import_record['import_finished']['value']);
    $import_record['name']['value'] = $import_name.' (Copy)';
    if (! $db->insert('vendor_imports',$import_record)) {
       http_response(422,$db->error);   return;
    }
    $new_id = $db->insert_id();

    $query = 'select * from vendor_mapping where parent=? order by id';
    $query = $db->prepare_query($query,$id);
    $rows = $db->get_records($query);
    if (! empty($rows)) {
       $mapping_record = vendor_mapping_record_definition();
       foreach ($rows as $row) {
          foreach ($row as $field_name => $field_value) {
             if (isset($mapping_record[$field_name]))
                $mapping_record[$field_name]['value'] = $field_value;
          }
          unset($mapping_record['id']['value']);
          $mapping_record['parent']['value'] = $new_id;
          if (! $db->insert('vendor_mapping',$mapping_record)) {
             http_response(422,$db->error);   return;
          }
       }
    }

    $query = 'select * from vendor_markups where parent=-? order by id';
    $query = $db->prepare_query($query,$id);
    $rows = $db->get_records($query);
    if (! empty($rows)) {
       $markup_record = vendor_markup_record_definition();
       foreach ($rows as $row) {
          foreach ($row as $field_name => $field_value) {
             if (isset($markup_record[$field_name]))
                $markup_record[$field_name]['value'] = $field_value;
          }
          unset($markup_record['id']['value']);
          $markup_record['parent']['value'] = -$new_id;
          if (! $db->insert('vendor_markups',$markup_record)) {
             http_response(422,$db->error);   return;
          }
       }
    }

    log_activity('Copied Import #'.$id.' to #'.$new_id.' ('.$import_name.')');
    http_response(201,'Import Copied');
}

function delete_import()
{
    $id = get_form_field('id');
    $db = new DB;
    $query = 'delete from vendor_markups where parent=-?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $import_record = vendor_import_record_definition();
    $import_record['id']['value'] = $id;
    if (! $db->delete('vendor_imports',$import_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Vendor Import Deleted');
    log_activity('Deleted Vendor Import #'.$id);
}

function build_db_fields($db,$vendor_info)
{
    global $related_types,$enable_multisite,$shopping_feeds_enabled;
    global $include_product_downloads,$enable_rebates;

    if (empty($shopping_feeds_enabled))
       $shopping_feeds_enabled = shopping_modules_installed();
    if (! isset($enable_multisite)) $enable_multisite = false;

    $features = get_cart_config_value('features',$db);
    $product_fields = $db->get_field_defs('products');
    $skip_product_fields = array('id','last_modified');
    if (! ($features & REGULAR_PRICE_PRODUCT)) $skip_product_fields[] = 'price';
    if (! ($features & LIST_PRICE_PRODUCT)) $skip_product_fields[] = 'list_price';
    if (! ($features & SALE_PRICE_PRODUCT)) $skip_product_fields[] = 'sale_price';
    if (! ($features & PRODUCT_COST_PRODUCT)) $skip_product_fields[] = 'cost';
    if (! ($features & REGULAR_PRICE_BREAKS))
       $skip_product_fields = array_merge($skip_product_fields,
          array('price_break_type','price_breaks'));
    if (! $enable_multisite) $skip_product_fields[] = 'websites';
    if (empty($shopping_feeds_enabled))
       $skip_product_fields = array_merge($skip_product_fields,
          array('shopping_gtin','shopping_brand','shopping_mpn','shopping_gender',
                'shopping_color','shopping_age','shopping_flags'));

    $inventory_fields = $db->get_field_defs('product_inventory');
    $skip_inventory_fields = array('id','sequence','parent','last_modified');
    if ((! $features & USE_PART_NUMBERS)) $skip_inventory_fields[] = 'part_number';
    if (! ($features & MAINTAIN_INVENTORY))
       $skip_inventory_fields = array_merge($skip_inventory_fields,
          array('qty','min_qty'));
    if (! ($features & REGULAR_PRICE_INVENTORY)) $skip_inventory_fields[] = 'price';
    if (! ($features & LIST_PRICE_INVENTORY)) $skip_inventory_fields[] = 'list_price';
    if (! ($features & SALE_PRICE_INVENTORY)) $skip_inventory_fields[] = 'sale_price';
    if (! ($features & PRODUCT_COST_INVENTORY)) $skip_inventory_fields[] = 'cost';
    if (! ($features & DROP_SHIPPING)) $skip_inventory_fields[] = 'origin_zip';
    if (! ($features & WEIGHT_ITEM)) $skip_inventory_fields[] = 'weight';
    $db_fields = array();
    foreach ($product_fields as $field_name => $field_info) {
       if (in_array($field_name,$skip_product_fields)) continue;
       $index = 'product|'.$field_name;
       $db_fields[$index] = $field_name;
    }
    foreach ($inventory_fields as $field_name => $db_field_info) {
       if (in_array($field_name,$skip_inventory_fields)) continue;
       $index = 'inventory|'.$field_name;
       if ($field_name == 'image') $field_name = 'Inventory Image';
       $db_fields[$index] = $field_name;
    }
    $db_fields['images|*'] = 'Image';
    $db_fields['category|*'] = 'Category';
    if (isset($related_types)) {
       foreach ($related_types as $related_type => $label)
          $db_fields['related_'.$related_type.'|*'] = $label;
    }
    $db_fields['related|related_type'] = 'Related Type';
    $db_fields['related|related_id'] = 'Related ID';
    $db_fields['related|sequence'] = 'Related Sequence';
    if (! empty($include_product_downloads))
       $db_fields['product_data|1'] = 'Product Downloads';
    if (! empty($enable_rebates)) {
       $db_fields['rebate|url'] = 'Rebate URL';
       $db_fields['rebate|label'] = 'Rebate Label';
       $db_fields['rebate|start_date'] = 'Rebate Start Date';
       $db_fields['rebate|end_date'] = 'Rebate End Date';
    }
    if (function_exists('custom_update_vendor_db_fields'))
       custom_update_vendor_db_fields($db_fields);
    call_vendor_event($vendor_info,'update_db_fields',
                      array($db,&$db_fields));
    asort($db_fields);
    return $db_fields;
}

function import_mapping()
{
    $conversions = array('parsecurrency' => 'Parse Currency',
                         'downloadimage' => 'Download Image',
                         'downloadboximage' => 'Download Box.com Image',
                         'trimfilename' => 'Trim Filename',
                         'setmarkupprice' => 'Set Marked Up Price',
                         'lookuppartnumber' => 'Lookup Part Number',
                         'usecolumnaslabel' => 'Use Column Header as Label',
                         'parseyoutube' => 'Parse YouTube Link',
                         'combinefields' => 'Combine Fields',
                         'productgroup' => 'Product Group',
                         'skipempty' => 'Skip Empty Cell',
                         'convertdate' => 'Convert Date to Timestamp',
                         'setbitvalue' => 'Set/Unset Bit in Seq');

    set_time_limit(0);
    ini_set('max_execution_time',0);
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from vendor_imports where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Import not found',0);
       return;
    }
    $vendor_info = load_vendor_info($db,$row['parent']);

    ini_set('memory_limit',-1);
    if (function_exists('update_vendor_conversions'))
       update_vendor_conversions($conversions);
    call_vendor_event($vendor_info,'update_conversions',
                      array($db,&$conversions));
    asort($conversions);
    $vendor = get_row_value($row,'parent');
    $import_source = get_row_value($row,'import_source');
    if ($import_source == API_IMPORT_SOURCE)
       call_vendor_event($vendor_info,'import_field_names',
                         array($db,$row,&$field_names));
    else {
       if ($import_source < 0) {
          $query = 'select import_file from vendor_imports where id=?';
          $query = $db->prepare_query($query,-$import_source);
          $import_row = $db->get_record($query);
          $import_file = get_row_value($import_row,'import_file');
       }
       else $import_file = get_row_value($row,'import_file');
       if (! $import_file) {
          process_error('No Import File Found',0);   return;
       }
       $import_type = get_row_value($row,'import_type');
       $sheet_num = get_row_value($row,'sheet_num');
       $header_row = get_row_value($row,'start_row');
       $delimiter = get_row_value($row,'txt_delim');
       $field_names = null;
       if (! $header_row) {
          call_vendor_event($vendor_info,'import_field_names',
                            array($db,$row,&$field_names));
          if ($field_names) $header_row = 0;
          else $header_row = 1;
       }
       $full_filename = '../admin/vendors/'.$import_file;
       if (! file_exists($full_filename)) {
          process_error('Import File '.$import_file.' does not exist',0);
          return;
       }
       $extension = pathinfo($import_file,PATHINFO_EXTENSION);
       if ($extension == 'xls') $format = 'Excel5';
       else if ($extension == 'xlsx') $format = 'Excel2007';
       else if ($extension == 'xlsm') $format = 'Excel2007';
       else if ($extension == 'csv') $format = 'CSV';
       else if ($extension == 'txt') $format = 'CSV';
       else {
          process_error('Unsupported Spreadsheet Format in '.$import_file,0);
          return;
       }
       require_once '../engine/excel.php';
       try {
          $reader = PHPExcel_IOFactory::createReader($format);
          if ($format == 'CSV')
             PHPExcel_Cell::setValueBinder(new BindValueAsString());
          if ($extension == 'txt') {
             if (empty($delimiter)) $delimiter = "\t";
             $reader->setDelimiter($delimiter);
          }
          else if (($extension == 'csv') && (! empty($delimiter)))
             $reader->setDelimiter($delimiter);
          $row_filter = new RowFilter($header_row + 3);
          $reader->setReadFilter($row_filter);
          $excel = $reader->load($full_filename);
          if (! $sheet_num) $sheet_num = 0;
          $worksheet = $excel->setActiveSheetIndex($sheet_num);
          $data = $worksheet->toArray(null,true,false,false);
       }
       catch (Exception $e) {
          process_error('Exception loading data from '.$import_file.': ' .
                        $e->getMessage(),0);
       }
       if (! $data) {
          process_error('Unable to load data from '.$import_file,0);
          return;
       }
       if (! $field_names)
          $field_names = get_unique_field_names($data[$header_row - 1]);
       if (isset($data[$header_row])) $first_sample = $data[$header_row];
       else $first_sample = null;
       if (isset($data[$header_row + 1])) $second_sample = $data[$header_row + 1];
       else $second_sample = null;
    }

    $db = new DB;
    $db_fields = build_db_fields($db,$vendor_info);
    $query = 'select * from vendor_mapping where parent=?';
    $query = $db->prepare_query($query,$id);
    $update_fields = $db->get_records($query,'vendor_field');
    if (! $update_fields) $update_fields = array();

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('vendors.css');
    $dialog->add_script_file('vendors.js');
    $dialog->set_body_id('vendor_import_mapping');
    $dialog->set_help('vendor_import_mapping');
    $dialog->start_body('Import Mapping');
    $dialog->set_button_width(115);
    $dialog->start_button_column();
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_mapping();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('vendors.php','ImportMapping');
    $dialog->add_hidden_field('id',$id);
    $dialog->start_field_table(null,'fieldtable mappingtable',1);
    $dialog->write('<tr>');
    $dialog->write('<th>Vendor Field</th>');
    $dialog->write("<th>Update Field</th>\n");
    $dialog->write("<th>Seq</th>\n");
    $dialog->write("<th>Sep</th>\n");
    $dialog->write("<th>Req</th>\n");
    $dialog->write("<th>Convert</th>\n");
    if ($import_source != API_IMPORT_SOURCE) {
       $dialog->write("<th>Sample</th>\n");
       $dialog->write('<th>Sample</th>');
    }
    $dialog->write("</tr>\n");

    $last_index = -1;
    foreach ($field_names as $index => $field_name) {
       if (isset($update_fields[$field_name])) {
          $update_field = $update_fields[$field_name]['update_field'];
          $sequence = $update_fields[$field_name]['sequence'];
          $sep_char = $update_fields[$field_name]['sep_char'];
          $convert_funct = $update_fields[$field_name]['convert_funct'];
          $required = $update_fields[$field_name]['required'];
       }
       else {
          $update_field = $sequence = $sep_char = $convert_funct = null;
          $required = false;
       }
       if (strlen($field_name) > 30) {
          $display_name = substr($field_name,0,30).'...';
          $title = str_replace('"','&quot;',$field_name);
       }
       else {
          $display_name = $field_name;   $title = null;
       }
       $dialog->write("<tr><td style=\"padding-left:5px;\" nowrap");
       if ($title) $dialog->write(" title=\"".$title."\"");
       $dialog->write('>');
       $dialog->add_hidden_field('field_'.$index,$field_name);
       $dialog->write($display_name."</td>\n");
       $dialog->write("<td align=\"center\">");
       $dialog->start_choicelist('update_'.$index);
       $dialog->add_list_item('','',! $update_field);
       foreach ($db_fields as $db_field => $label)
          $dialog->add_list_item($db_field,$label,
                                 ($db_field == $update_field));
       $dialog->end_choicelist();
       $dialog->write("</td>\n<td align=\"center\" class=\"sequence_cell\">");
       $dialog->add_input_field('sequence_'.$index,$sequence,1);
       $dialog->write("</td>\n<td align=\"center\" class=\"sep_cell\">");
       $dialog->add_input_field('sep_'.$index,$sep_char,1);
       $dialog->write("</td>\n<td align=\"center\" class=\"req_cell\">");
       $dialog->add_checkbox_field('required_'.$index,'',$required);
       $dialog->write("</td>\n<td align=\"center\">");
       $dialog->start_choicelist('convert_'.$index);
       $dialog->add_list_item('','',! $convert_funct);
       foreach ($conversions as $convert_id => $label)
          $dialog->add_list_item($convert_id,$label,
                                 ($convert_id == $convert_funct));
       $dialog->end_choicelist();
       if ($import_source != API_IMPORT_SOURCE) {
          $dialog->write("</td>\n<td style=\"padding-left:5px;\" nowrap");
          if (($first_sample !== null) && isset($first_sample[$index])) {
             $sample_value = strip_tags(cleanup_data($first_sample[$index],
                                                     $update_field));
             if (strlen($sample_value) > 30) {
                $title = $sample_value;
                $sample_value = substr($sample_value,0,30);
             }
             else $title = null;
          }
          else {
             $sample_value = '';   $title = null;
          }
          if ($title) $dialog->write(" title=\"".htmlspecialchars($title)."\"");
          $dialog->write('>');
          if (($sample_value !== ''))
             $dialog->write(htmlspecialchars($sample_value));
          else $dialog->write('&nbsp;');
          $dialog->write("</td>\n<td style=\"padding-left:5px;\" nowrap");
          if (($second_sample !== null) && isset($second_sample[$index])) {
             $sample_value = strip_tags(cleanup_data($second_sample[$index],
                                                     $update_field));
             if (strlen($sample_value) > 30) {
                $title = $sample_value;
                $sample_value = substr($sample_value,0,30);
             }
             else $title = null;
          }
          else {
             $sample_value = '';   $title = null;
          }
          if ($title) $dialog->write(" title=\"".htmlspecialchars($title)."\"");
          $dialog->write('>');
          if ($sample_value !== '')
             $dialog->write(htmlspecialchars($sample_value));
          else $dialog->write('&nbsp;');
       }
       $dialog->write("</td></tr>\n");
       $last_index = $index;
    }

    $dialog->end_field_table();
    $dialog->add_hidden_field('LastIndex',$last_index);
    $dialog->end_form();
    $dialog->end_body();
}

function update_mapping()
{
    set_time_limit(0);
    ini_set('max_execution_time',0);
    $db = new DB;
    $parent = get_form_field('id');
    $query = 'delete from vendor_mapping where parent=?';
    $query = $db->prepare_query($query,$parent);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }
    $last_index = get_form_field('LastIndex');
    if ($last_index == -1) {
       http_response(422,'There are no columns to map');   return false;
    }
    $mapping_record = vendor_mapping_record_definition();
    $mapping_record['parent']['value'] = $parent;
    for ($index = 0;   $index <= $last_index;  $index++) {
       $update_field = get_form_field('update_'.$index);
       $sequence = get_form_field('sequence_'.$index);
       $sep_char = get_form_field('sep_'.$index);
       $required = get_form_field('required_'.$index);
       if ($required == 'on') $required = 1;
       else $required = 0;
       $convert_funct = get_form_field('convert_'.$index);
       if ((! $update_field) && (! $convert_funct)) continue;
       $mapping_record['vendor_field']['value'] = get_form_field('field_'.$index);
       $mapping_record['update_field']['value'] = $update_field;
       $mapping_record['sequence']['value'] = $sequence;
       $mapping_record['sep_char']['value'] = $sep_char;
       $mapping_record['required']['value'] = $required;
       $mapping_record['convert_funct']['value'] = $convert_funct;
       if (! $db->insert('vendor_mapping',$mapping_record)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,'Vendor Import Mapping Updated');
    log_activity('Updated Vendor Import Mapping for Vendor Import #'.$parent);
}

function upload_import_file()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from vendor_imports where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Import not found',0);
       return;
    }

    $vendor = get_row_value($row,'parent');
    $import_file = get_row_value($row,'import_file');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('vendors.css');
    $dialog->add_script_file('vendors.js');
    $dialog->set_body_id('upload_vendor_import');
    $dialog->set_help('upload_vendor_import');
    $dialog->start_body('Upload Vendor Import File');
    $dialog->set_button_width(115);
    $dialog->start_button_column();
    $dialog->add_button('Upload File','../cartengine/images/AddImage.png',
                        'process_import_upload();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->write("<form method=\"POST\" action=\"vendors.php\" " .
                   "name=\"UploadFile\" encType=\"multipart/form-data\">\n");
    $dialog->add_hidden_field('cmd','processuploadfile');
    $dialog->add_hidden_field('vendor',$vendor);
    $dialog->add_hidden_field('import_file',$import_file);
    $dialog->add_hidden_field('id',$id);
    $dialog->start_field_table();
    $dialog->start_row('Filename:');
    $dialog->write("<input type=\"file\" name=\"Filename\" size=\"35\" ");
    $dialog->write("class=\"browse_button\">\n");
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_import_file()
{
    $filename = $_FILES['Filename']['name'];
    $temp_name = $_FILES['Filename']['tmp_name'];
    $file_type = $_FILES['Filename']['type'];

    $vendor = get_form_field('vendor');
    $import_file = get_form_field('import_file');
    $import_id = get_form_field('id');

    $vendor_dir = '../admin/vendors/';
    $extension = pathinfo(strtolower($filename),PATHINFO_EXTENSION);
    if ($import_file) {
       $old_extension = pathinfo($import_file,PATHINFO_EXTENSION);
       if ($old_extension != $extension) {
          unlink($vendor_dir.$import_file);   $import_file = null;
       }
    }
    if (! $import_file) {
       $import_file = 'import-'.$import_id.'.'.$extension;
       $new_import_file = true;
    }
    else $new_import_file = false;

    $full_filename = $vendor_dir.$import_file;
    if (! move_uploaded_file($temp_name,$full_filename)) {
       log_error('Attempted to move '.$temp_name.' to '.$full_filename);
       process_error('Unable to save uploaded vendor file',-1);   return;
    }

    if ($new_import_file) {
       $db = new DB;
       $query = 'update vendor_imports set import_file=? where id=?';
       $query = $db->prepare_query($query,$import_file,$import_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          process_error('Database Error: '.$db->error,-1);   return;
       }
    }

    log_activity('Uploaded '.$filename.' to Vendor Import File '.$import_file .
                 ' for Vendor #'.$vendor);
    print '<html><head><script>top.get_content_frame().finish_import_upload();' .
          '</script></head><body></body></html>';
}

function get_import_file()
{
    $import_id = get_form_field('id');
    if (! $import_id) {
       http_response(406,'Import ID is Required');   return;
    }
    $db = new DB;
    $query = 'select v.name as vendor_name,v.module,vi.* from vendors v ' .
             'join vendor_imports vi on vi.parent=v.id where vi.id=?';
    $query = $db->prepare_query($query,$import_id);
    $import = $db->get_record($query);
    $import['manual'] = true;
    if ($import['import_source'] == DOWNLOAD_IMPORT_SOURCE) {
       if (! call_vendor_event($import,'download_catalog',
                               array($db,&$import))) {
          if (! empty($import['pending']))
             http_response(202,'Vendor Import Download Started');
          else http_response(422,'Unable to get vendor import');
          return;
       }
       http_response(201,'Vendor Import Downloaded');
    }
    else {
       spawn_program('vendors-import.php get '.$import_id);
       http_response(202,'Vendor Import Download Started');
    }
}

function check_import_get()
{
    $import_id = get_form_field('import');
    if (! $import_id) {
       http_response(406,'Import ID is Required');   return;
    }
    $db = new DB;
    $query = 'select import_file from vendor_imports where id=?';
    $query = $db->prepare_query($query,$import_id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    if (empty($row['import_file'])) {
       http_response(202,'Import Download Still In Progress');   return;
    }
    $import_filename = '../admin/vendors/'.$row['import_file'];
    if (! file_exists($import_filename)) {
       http_response(202,'Import Download Still In Progress');   return;
    }
    $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
    if (file_exists($import_log_filename)) {
       $last_error = file_get_contents($import_log_filename);
       $last_error = substr($last_error,0,200);
       http_response(412,'Import #'.$import_id.' Failed: '.$last_error);
       return;
    }
    http_response(201,'Import Download Finished');
}

function start_import()
{
    $import_id = get_form_field('import');
    if (! $import_id) {
       http_response(406,'Import ID is Required');   return;
    }
    $db = new DB;
    $query = 'select import_started,import_finished from vendor_imports ' .
             'where id=?';
    $query = $db->prepare_query($query,$import_id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    if ((! empty($row['import_started'])) && empty($row['import_finished'])) {
       http_response(410,'Vendor Import has already been started');
       return;
    }
    spawn_program('vendors-import.php import '.$import_id);

    http_response(201,'Import Started');
    log_activity('Started Vendor Import #'.$import_id);
}

function download_import_file()
{
    ini_set('memory_limit',-1);
    $import_file = get_form_field('file');
    $vendor_dir = '../admin/vendors/';
    $content = file_get_contents($vendor_dir.$import_file);
    if (get_browser_type() == MSIE)
       header('Content-type: application/inroads');
    else header('Content-type: application/octet-stream');
    header('Content-disposition: attachment; filename="'.$import_file.'"');
    header('Cache-Control: no-cache');
    header('Expires: -1441');
    print $content;
    log_activity('Downloaded Vendor Import File '.$import_file);
}

function check_import()
{
    $import_id = get_form_field('import');
    if (! $import_id) {
       http_response(406,'Import ID is Required');   return;
    }
    $db = new DB;
    $query = 'select i.import_file,i.import_started,i.import_finished,' .
       '(select count(p.id) as num_products from products p where ' .
       'p.import_id=i.id) as num_products from vendor_imports i where i.id=?';
    $query = $db->prepare_query($query,$import_id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    if ($row['import_finished']) {
       http_response(201,'Import Finished');   return;
    }
    $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
    if (file_exists($import_log_filename)) {
       $last_error = file_get_contents($import_log_filename);
       $last_error = substr($last_error,0,200);
       http_response(412,'Import #'.$import_id.' Failed: '.$last_error);
       return;
    }
    $import_file = $row['import_file'];
    if (file_exists('../admin/vendors/'.$import_file))
       $row['data_updated'] = filemtime('../admin/vendors/'.$import_file);
    else $row['data_updated'] = 0;
    if (! $row['import_finished']) $row['import_finished'] = '';
    http_response(202,json_encode($row));
}

function clear_import()
{
    $import_id = get_form_field('import');
    if (! $import_id) {
       http_response(406,'Import ID is Required');   return;
    }
    $db = new DB;
    $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
    if (! file_exists($import_log_filename)) {
       http_response(410,'Vendor Import has no errors');   return;
    }
    unlink($import_log_filename);
    $query = 'update vendor_imports set import_started=null,' .
             'import_finished=null where id=?';
    $query = $db->prepare_query($query,$import_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       process_error('Database Error: '.$db->error,-1);   return;
    }
    http_response(201,'Errors Cleared');
    log_activity('Cleared Errors for Vendor Import #'.$import_id);
}

function reset_import()
{
    $import_id = get_form_field('import');
    if (! $import_id) {
       http_response(406,'Import ID is Required');   return;
    }
    $db = new DB;
    $query = 'update vendor_imports set import_started=null,' .
             'import_finished=null where id=?';
    $query = $db->prepare_query($query,$import_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       process_error('Database Error: '.$db->error,-1);   return;
    }
    http_response(201,'Import Reset');
    log_activity('Reset Import for Vendor Import #'.$import_id);
}

function get_table_info()
{
    $table_name = get_form_field('Table');
    $where = get_form_field('Where');
    $query = get_form_field('InfoQuery');
    $db = new DB;
    $num_records = $db->get_num_records($table_name,$where,$query,false);
    print 'this.num_records = '.$num_records.'; ';
    $column_names = $db->get_column_names($table_name);
}

function get_records($include_table_data)
{
    $query = get_form_field('Query');
    $where = get_form_field('Where');
    if ($where) $query .= ' where '.$where;
    $order_by = get_form_field('OrderBy');
    $db = new DB;
    if ($include_table_data) {
       $query .= ' order by '.$order_by;
       $table_name = get_form_field('Table');
       $column_names = $db->get_column_names($table_name);
    }
    else {
       $start_record = get_form_field('StartRecord');
       $num_records = get_form_field('NumRecords');
       $query .= ' order by '.$order_by.' limit '.$start_record.',' .
                 $num_records;
    }
    $result = $db->query($query);
    if (! $result) {
       http_response(422,$db->error);   return;
    }
    $num_records = mysql_num_rows($result);
    if ($num_records == 0) {
       http_response(410,'No records found to retrieve');
       return;
    }
    if ($include_table_data) {
       print 'this.num_records = '.$num_records.'; ';
       $row_num = 0;
    }
    else $row_num = $start_record;
    while ($row = mysql_fetch_assoc($result)) {
       print 'this._data['.$row_num."]='";
       $first_field = true;
       foreach ($row as $field_name => $field_value) {
          if ($first_field) $first_field = false;
          else print '|';
          if ($field_name == 'data_updated') {
             if ($field_value && file_exists('../admin/vendors/'.$field_value))
                $field_value = filemtime('../admin/vendors/'.$field_value);
             else $field_value = '';
          }
          else if ($field_name == 'last_error') {
             $import_id = get_row_value($row,'id');
             $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
             if (file_exists($import_log_filename)) {
                $field_value = file_get_contents($import_log_filename);
                $field_value = substr($field_value,0,200);
                $field_value = str_replace("\n",' ',$field_value);
             }
             else $field_value = '';
          }
          else $field_value = str_replace('|','^',$field_value);
          write_field_data($field_value);
       }
       print "'; ";
       $row_num++;
    }
    mysql_free_result($result);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');
if (! $cmd) $cmd = get_form_field('Command');

if ($cmd == 'createvendor') create_vendor();
else if ($cmd == 'addvendor') add_vendor();
else if ($cmd == 'processaddvendor') process_add_vendor();
else if ($cmd == 'editvendor') edit_vendor();
else if ($cmd == 'updatevendor') update_vendor();
else if ($cmd == 'deletevendor') delete_vendor();
else if ($cmd == 'addimport') add_import();
else if ($cmd == 'processaddimport') process_add_import();
else if ($cmd == 'editimport') edit_import();
else if ($cmd == 'updateimport') update_import();
else if ($cmd == 'copyimport') copy_import();
else if ($cmd == 'deleteimport') delete_import();
else if ($cmd == 'importmapping') import_mapping();
else if ($cmd == 'updatemapping') update_mapping();
else if ($cmd == 'uploadfile') upload_import_file();
else if ($cmd == 'processuploadfile') process_import_file();
else if ($cmd == 'getimport') get_import_file();
else if ($cmd == 'checkimportget') check_import_get();
else if ($cmd == 'downloadfile') download_import_file();
else if ($cmd == 'startimport') start_import();
else if ($cmd == 'checkimport') check_import();
else if ($cmd == 'clearimport') clear_import();
else if ($cmd == 'resetimport') reset_import();
else if ($cmd == 'GetTableInfo') get_table_info();
else if ($cmd == 'GetTableData') get_records(true);
else if ($cmd == 'GetRecords') get_records(false);
else display_vendors_screen();

DB::close_all();

?>
