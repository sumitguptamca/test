<?php
/*
                        Inroads Shopping Cart - Accounts Tab

                         Written 2011-2019 by Randall Severy
                          Copyright 2011-2019 Inroads, LLC
*/

require '../engine/screen.php';
require '../engine/dialog.php';
require '../engine/db.php';
require 'utility.php';
require_once 'cartconfig-common.php';
require_once 'customers-common.php';
require_once 'accounts-common.php';
require_once 'sublist.php';

if (! isset($account_tabs))
   $account_tabs = array('info'=>$account_label.' Info','rates'=>'Rates',
                         'products'=>'Products');

function add_account_filters($screen,$status_values,$db)
{
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Status:');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    if (! $screen->skin) $class = 'select filter_select';
    else $class = null;
    $screen->start_choicelist('status','filter_accounts();',$class);
    $screen->add_list_item('','All',true);
    foreach ($status_values as $index => $status)
       $screen->add_list_item($index,$status,false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');
    else $screen->write("</td></tr>\n");
}

function display_accounts_screen()
{
    global $account_label,$accounts_label;

    $db = new DB;
    $status_values = load_cart_options(ACCOUNT_STATUS,$db);
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('accounts.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $script = "<script type=\"text/javascript\">\n";
    $script .= "      account_label = '".$account_label."';\n";
    $script .= "      accounts_label = '".$accounts_label."';\n";
    $script .= '    </script>';
    $screen->add_head_line($script);
    $screen->set_body_id('accounts');
    $screen->set_help('accounts');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar($accounts_label);
       $screen->start_title_filters();
       add_account_filters($screen,$status_values,$db);
       add_search_box($screen,'search_accounts','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->set_button_width(148);
    $screen->start_button_column();
    $screen->add_button('Add '.$account_label,'images/AddAttribute.png',
                        'add_account();');
    $screen->add_button('Edit '.$account_label,'images/EditAttribute.png',
                        'edit_account();');
    $screen->add_button('Copy '.$account_label,'images/EditAttribute.png',
                        'copy_account();');
    $screen->add_button('Delete '.$account_label,'images/DeleteAttribute.png',
                        'delete_account();');
    if (function_exists('display_custom_account_buttons'))
       display_custom_account_buttons($screen);
    if (! $screen->skin) {
       add_account_filters($screen,$status_values,$db);
       add_search_box($screen,'search_accounts','reset_search');
    }
    $screen->end_button_column();
    $screen->write("\n          <script type=\"text/javascript\">\n");
    $screen->write('             var account_status_values = [');
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $screen->write(',');
       if (isset($status_values[$loop]))
          $screen->write('"'.$status_values[$loop].'"');
       else $screen->write('""');
    }
    $screen->write("];\n");
    $screen->write("             var account_where = null;\n");
    $screen->write("             load_grid(true);\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function add_account_variables(&$dialog)
{
    global $account_product_prices,$on_account_products;

    if ((! $account_product_prices) && empty($on_account_products)) return;

    $script = "<script type=\"text/javascript\">\n";
    if ($account_product_prices) {
       $script .= '      account_product_prices = ';
       if ($account_product_prices === 'both') $script .= '\'both\'';
       else $script .= 'true';
       $script .= ";\n";
    }
    if (! empty($on_account_products))
       $script .= '      on_account_products = true;'."\n";
    $script .= '    </script>';
    $dialog->add_head_line($script);
    if (! empty($on_account_products)) {
       if ($on_account_products === 'both') $column = 5;
       else $column = 4;
       $styles = '<style>#products_grid .aw-column-'.$column .
                 ' { text-align: center; }</style>';
       $dialog->add_head_line($styles);
    }
}

function add_account_buttons($dialog)
{
    $dialog->add_button_separator('account_row_sep',20);
    $dialog->add_button('Add Product','images/AddProduct.png',
                        'add_account_product();','add_account_product',false);
    $dialog->add_button('Delete Product','images/DeleteProduct.png',
                        'delete_account_product();','delete_account_product',
                        false);
}

function get_tab_sequence($tab,$first_tab,$last_tab)
{
    $tab_sequence = 0;
    if ($tab == $first_tab) $tab_sequence |= FIRST_TAB;
    if ($tab == $last_tab) $tab_sequence |= LAST_TAB;
    return $tab_sequence;
}

function display_account_fields($db,$dialog,$edit_type,$row)
{
    global $account_tabs,$category_label,$categories_label,$categories_table;
    global $custom_account_product_prices,$account_label,$account_credit_limit;

    $status_values = load_cart_options(ACCOUNT_STATUS);
    $id = get_row_value($row,'id');
    $features = get_cart_config_value('features',$db);
    $dialog->start_tab_section('tab_section');
    reset($account_tabs);   $first_tab = key($account_tabs);
    end($account_tabs);   $last_tab = key($account_tabs);
    $dialog->start_tab_row($first_tab.'_tab',$first_tab.'_content','change_tab');
    foreach ($account_tabs as $tab_name => $tab_label) {
       $tab_sequence = 0;
       if ($tab_name == $first_tab) $tab_sequence |= FIRST_TAB;
       if ($tab_name == $last_tab) $tab_sequence |= LAST_TAB;
       $dialog->add_tab($tab_name.'_tab',$tab_label,$tab_name.'_tab',
                        $tab_name.'_content','change_tab',true,null,
                        $tab_sequence);
    }
    $dialog->end_tab_row('tab_row_middle');

    foreach ($account_tabs as $tab_name => $tab_label) {
       if ($tab_name == 'info') {
          $dialog->start_tab_content('info_content',$first_tab == 'info');
          $dialog->start_field_table('account_info_table');
          $dialog->add_hidden_field('id',$id);
          $dialog->start_row('Status:','middle');
          $status = get_row_value($row,'status');
          if ($edit_type == UPDATERECORD)
             $dialog->add_hidden_field('OldStatus',$status);
          $dialog->start_choicelist('status',null);
          foreach ($status_values as $index => $status_label)
             $dialog->add_list_item($index,$status_label,$status == $index);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->add_edit_row($account_label.' Name:','name',
                                get_row_value($row,'name'),45);
          $dialog->add_edit_row('First Name:','fname',
                                get_row_value($row,'fname'),45);
          $dialog->add_edit_row('Last Name:','lname',
                                get_row_value($row,'lname'),45);
          $dialog->add_edit_row('Company:','company',
                                get_row_value($row,'company'),45);
          $dialog->add_edit_row('Address Line 1:','address1',
                                get_row_value($row,'address1'),45);
          $dialog->add_edit_row('Address Line 2:','address2',
                                get_row_value($row,'address2'),45);
          $dialog->add_edit_row('City:','city',get_row_value($row,'city'),30);
          $country = get_row_value($row,'country');
          $state = get_row_value($row,'state');
          $dialog->start_hidden_row('State:','state_row',($country != 1),
                                    'middle');
          $dialog->start_choicelist('state',null);
          $dialog->add_list_item('','',false);
          load_state_list($state);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->start_hidden_row('Province:','province_row',($country == 1),
                                    'bottom');
          $dialog->add_input_field('province',$state,20);
          $dialog->end_row();
          $dialog->write('<tr valign="bottom"><td class="fieldprompt" nowrap id="zip_cell">');
          if ($country == 1) $dialog->write('Zip Code:');
          else $dialog->write('Postal Code:');
          $dialog->write("</td><td>\n");
          $dialog->add_input_field('zipcode',get_row_value($row,'zipcode'),20);
          $dialog->end_row();
          $dialog->start_row('Country:','middle');
          $dialog->start_choicelist('country','select_country(this);');
          load_country_list($country);
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->add_edit_row('Telephone:','phone',$row,30);
          $dialog->add_edit_row('E-Mail Address:','email',$row,30);
          $dialog->start_row('No Shipping:','middle');
          $dialog->add_checkbox_field('no_shipping_flag','',$row);
          $dialog->end_row();
          $dialog->start_row('Tax Exempt:','middle');
          $dialog->add_checkbox_field('tax_exempt','',$row);
          $dialog->end_row();
          if ($features & USE_COUPONS) {
             $dialog->start_row('No Coupons:','middle');
             $dialog->add_checkbox_field('no_coupons',
                '(No Coupons/Special Offers Allowed)',$row);
             $dialog->end_row();
          }
          if (function_exists('display_custom_account_fields'))
             display_custom_account_fields($dialog,$edit_type,$row,$db);
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'rates') {
          $dialog->start_tab_content('rates_content',$first_tab == 'rates');
          $dialog->start_field_table('account_rates_table');
          if (! $custom_account_product_prices) {
             $dialog->start_row('Discount Rate:');
             $dialog->write('<input type="text" ' .
                            'class="text discount" name="discount_rate" ' .
                            'size="3" value="');
             write_form_value(get_row_value($row,'discount_rate'));
             $dialog->write("\">% off of regular price\n");
             $dialog->end_row();
          }
          if (! empty($account_credit_limit))
             $dialog->add_edit_row('Credit Limit:','credit_limit',$row,20);
          $payment_types = build_payment_types($db);
          $payment_options = get_row_value($row,'payment_options');
          $max_type = max(array_keys($payment_types));
          if (! $payment_options) $payment_options = 1;
          $dialog->start_row('Payment Options:','top');
          foreach ($payment_types as $index => $label) {
             $dialog->add_checkbox_field('payment_options_'.$index,
                $label,$payment_options & (1 << $index));
             if ($index != $max_type) $dialog->write("<br>\n");
          }
          $dialog->add_hidden_field('MaxPaymentType',$max_type);
          $dialog->end_row();
          if (function_exists('display_custom_account_rate_fields'))
             display_custom_account_rate_fields($dialog,$edit_type,$row,$db);
          $dialog->end_field_table();
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'products') {
          $dialog->start_tab_content('products_content',$first_tab == 'products');
          if ($dialog->skin)
             $dialog->write("        <div class=\"fieldSection\">\n");
          else $dialog->write("        <div style=\"padding: 4px;\">\n");
          if ($features & REGULAR_PRICE_INVENTORY) $use_inventory = true;
          else $use_inventory = false;
          $dialog->write("        <script type=\"text/javascript\">\n");
          $dialog->write('          create_products_grid('.$id.',');
          if ($use_inventory) $dialog->write('true');
          else $dialog->write('false');
          $dialog->write(");\n");
          if ($use_inventory) {
             $query = 'select ap.related_id from account_products ap join ' .
                      'products p on p.id=ap.related_id where ap.parent=? ' .
                      'order by p.name limit 1';
             $query = $db->prepare_query($query,$id);
             $product_row = $db->get_record($query);
             if ($product_row) $product_id = $product_row['related_id'];
             else $product_id = 0;
             $dialog->write('          create_inventory_grid('.$id.',' .
                            $product_id.");\n");
          }
          $dialog->write("        </script>\n");
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();
       }
       else if ($tab_name == 'categories') {
          if (! isset($category_label)) $category_label = 'Category';
          if (! isset($categories_label)) $categories_label = 'Categories';
          if (! isset($categories_table)) $categories_table = 'categories';
          $dialog->start_tab_content('categories_content',$first_tab == 'categories');
          if ($dialog->skin)
             $dialog->write("        <div class=\"fieldSection\">\n");
          else $dialog->write("        <div style=\"padding: 4px;\">\n");
          $dialog->write("        <script type=\"text/javascript\">\n");
          $dialog->write("           var categories = new SubList();\n");
          $dialog->write("           categories.name = 'categories';\n");
          $dialog->write("           categories.script_url = 'accounts.php';\n");
          $dialog->write("           categories.frame_name = '");
          if ($edit_type == UPDATERECORD) $dialog->write("edit_account';\n");
          else $dialog->write("add_account;'\n");
          $dialog->write("           categories.form_name = '");
          if ($edit_type == UPDATERECORD) $dialog->write("EditAccount';\n");
          else $dialog->write("AddAccount';\n");
          $dialog->write("           categories.grid_width = 220;\n");
          $dialog->write("           categories.grid_height = 320;\n");
          $dialog->write("           categories.left_table = 'account_categories';\n");
          $dialog->write("           categories.left_titles = ['".$category_label .
                         " Name'];\n");
          $dialog->write("           categories.left_label = 'categories';\n");
          $dialog->write("           categories.right_table = '" .
                         $categories_table."';\n");
          $dialog->write("           categories.right_titles = ['".$category_label .
                         " Name'];\n");
          $dialog->write("           categories.right_label = 'categories';\n");
          $dialog->write("           categories.right_single_label = 'category';\n");
          $dialog->write("           categories.default_frame = 'edit_account';\n");
          $dialog->write("           categories.enable_double_click = false;\n");
          $dialog->write("           categories.reverse_list = true;\n");
          $dialog->write("           categories.categories = true;\n");
          $dialog->write("        </script>\n");
          create_sublist_grids('categories',$dialog,$id,$categories_label,
                               'All '.$categories_label,true,'CategoryQuery',
                               $categories_label,false);
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();
       }
       else if (function_exists('display_custom_account_tab_sections'))
          display_custom_account_tab_sections($tab_name,$first_tab,$db,$dialog,
                                              $edit_type,$row);
    }

    $dialog->end_tab_section();
}

function parse_account_record($db,&$account_record)
{
    $db->parse_form_fields($account_record);
    if ($account_record['country']['value'] != 1)
       $account_record['state']['value'] = get_form_field('province');
    $max_type = get_form_field('MaxPaymentType');
    $payment_options = 0;
    for ($index = 0;  $index <= $max_type;  $index++)
       if (get_form_field('payment_options_'.$index) == 'on')
          $payment_options |= (1 << $index);
    $account_record['payment_options']['value'] = $payment_options;
}

function create_account()
{
    global $account_label;

    $db = new DB;
    $account_record = account_record_definition();
    $account_record['name']['value'] = 'New '.$account_label;
    if (! $db->insert('accounts',$account_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'account_id = '.$id.';';
    log_activity('Created New Account #'.$id);
}

function add_account()
{
    global $account_label;

    $id = get_form_field('id');

    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('utility.css');
    $dialog->add_style_sheet('accounts.css');
    $dialog->add_script_file('accounts.js');
    $dialog->add_script_file('sublist.js');
    $dialog->add_script_file('utility.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    add_account_variables($dialog);
    $dialog->set_onload_function('add_account_onload();');
    $dialog_title = 'Add '.$account_label.' (#'.$id.')';
    $dialog->set_body_id('add_account');
    $dialog->set_help('add_account');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button('Add '.$account_label,
                        '../cartengine/images/AddAttribute.png',
                        'process_add_account();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    add_account_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('accounts.php','AddAccount');
    if (! $dialog->skin) $dialog->start_field_table();
    $row = array();
    $row['id'] = $id;
    $row['country'] = 1;
    display_account_fields($db,$dialog,ADDRECORD,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_account()
{
    $db = new DB;
    $account_record = account_record_definition();
    parse_account_record($db,$account_record);
    if (function_exists('custom_update_account_record'))
       custom_update_account_record($db,$account_record);
    if (! $account_record['payment_options']['value']) {
       http_response(406,'You must select at least one Payment Option');
       return;
    }
    if (! $db->update('accounts',$account_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('add_account')) {
       $account_info = $db->convert_record_to_array($account_record);
       update_account_info($db,$account_info);
       if (! call_module_event('add_account',array($db,$account_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    if (function_exists('custom_finish_add_account'))
       custom_finish_add_account($db,$account_record);
    http_response(201,'Account Added');
    log_activity('Added Account '.$account_record['name']['value'].' (#'.
                 $account_record['id']['value'].')');
}

function edit_account()
{
    global $account_label;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from accounts where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Account not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('utility.css');
    $dialog->add_style_sheet('accounts.css');
    $dialog->add_script_file('accounts.js');
    $dialog->add_script_file('sublist.js');
    $dialog->add_script_file('utility.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    add_account_variables($dialog);
    $dialog_title = 'Edit '.$account_label.' (#'.$id.')';
    $dialog->set_body_id('edit_account');
    $dialog->set_help('edit_account');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_account();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    add_account_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('accounts.php','EditAccount');
    if (! $dialog->skin) $dialog->start_field_table();
    display_account_fields($db,$dialog,UPDATERECORD,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_account()
{
    $db = new DB;
    $account_record = account_record_definition();
    parse_account_record($db,$account_record);
    if (function_exists('custom_update_account_record'))
       custom_update_account_record($db,$account_record);
    if (! $account_record['payment_options']['value']) {
       http_response(406,'You must select at least one Payment Option');
       return;
    }
    if (! $db->update('accounts',$account_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('update_account')) {
       $account_info = $db->convert_record_to_array($account_record);
       update_account_info($db,$account_info);
       if (! call_module_event('update_account',array($db,$account_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    if (function_exists('custom_finish_update_account'))
       custom_finish_update_account($db,$account_record);
    http_response(201,'Account Updated');
    log_activity('Updated Account '.$account_record['name']['value'].' (#'.
                 $account_record['id']['value'].')');
}

function copy_account_child_records($table,$old_parent,$new_parent,$db)
{
    $query = 'select * from '.$db->escape($table).' where (parent=?)';
    $query = $db->prepare_query($query,$old_parent);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return false;
       }
       return true;
    }
    $child_record = account_product_record_definition();
    foreach ($rows as $row) {
       $child_record['parent']['value'] = $new_parent;
       $child_record['related_id']['value'] = $row['related_id'];
       $child_record['discount']['value'] = $row['discount'];
       if (! $db->insert($table,$child_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    log_activity('Copied Items for '.$table.' Item #'.$old_parent .
                 ' to Item #'.$new_parent);
    return true;
}

function copy_account()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from accounts where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    $account_name = $row['name'];
    $account_record = account_record_definition();
    foreach ($row as $field_name => $field_value)
       if (isset($account_record[$field_name]))
          $account_record[$field_name]['value'] = $field_value;
    unset($account_record['id']['value']);
    $account_record['name']['value'] = $account_name.' (Copy)';
    if (function_exists('custom_update_account_record'))
       custom_update_account_record($db,$account_record);
    if (! $db->insert('accounts',$account_record)) {
       http_response(422,$db->error);   return;
    }
    $new_id = $db->insert_id();
    $account_record['id']['value'] = $new_id;
    if (! copy_account_child_records('account_products',$id,$new_id,$db))
       return false;
    if (! copy_account_child_records('account_inventory',$id,$new_id,$db))
       return false;
    require_once '../engine/modules.php';
    if (module_attached('add_account')) {
       $account_info = $db->convert_record_to_array($account_record);
       if (! call_module_event('add_account',array($db,$account_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    if (function_exists('custom_finish_update_account'))
       custom_finish_update_account($db,$account_record);
    log_activity('Copied Account #'.$id.' to #'.$new_id.' (' .
                 $account_name.')');
    http_response(201,'Account Copied');
}

function delete_account()
{
    $id = get_form_field('id');
    $db = new DB;
    require_once '../engine/modules.php';
    if (module_attached('delete_account')) {
       $query = 'select * from accounts where id=?';
       $query = $db->prepare_query($query,$id);
       $account_info = $db->get_record($query);
    }
    $query = 'delete from account_inventory where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from account_products where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $query = 'update customers set account_id=NULL where account_id=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $account_record = account_record_definition();
    $account_record['id']['value'] = $id;
    if (! $db->delete('accounts',$account_record)) {
       http_response(422,$db->error);   return;
    }
    if (module_attached('delete_account')) {
       update_account_info($db,$account_info);
       if (! call_module_event('delete_account',array($db,$account_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    if (function_exists('custom_finish_delete_account'))
       custom_finish_delete_account($db,$id);
    http_response(201,'Account Deleted');
    log_activity('Deleted Account #'.$id);
}

function select_account()
{
    global $account_status_values,$account_label;

    $allowed_accounts = load_allowed_accounts($account_list);
    $frame = get_form_field('frame');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('../cartengine/utility.css');
    $dialog->add_script_file('accounts.js');
    $dialog->set_body_id('select_account');
    $dialog->set_help('select_account');
    $dialog->start_body('Select '.$account_label);
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button('Select','../cartengine/images/Update.png',
                        'select_account();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    add_search_box($dialog,'search_accounts','reset_search');
    $dialog->end_button_column();
    $dialog->write("\n          <script type=\"text/javascript\">\n");
    $dialog->write("             var select_frame = '".$frame."';\n");
    $dialog->write('             var account_status_values = [');
    for ($loop = 0;  $loop < count($account_status_values);  $loop++) {
       if ($loop > 0) $dialog->write(',');
       if (isset($account_status_values[$loop]))
          $dialog->write('"'.$account_status_values[$loop].'"');
       else $dialog->write('""');
    }
    $dialog->write("];\n");
    $dialog->write('             var account_where = ');
    if ($allowed_accounts)
       $dialog->write("'(id in (".$account_list."))'");
    else $dialog->write('null');
    $dialog->write(";\n");
    $dialog->write("             load_grid(false);\n");
    $dialog->write("          </script>\n");
    $dialog->end_body();
}

function update_account_product()
{
    require_once 'products-common.php';

    $db = new DB;
    $account_product_record = account_product_record_definition();
    $db->parse_form_fields($account_product_record);
    $product_id = $account_product_record['related_id']['value'];
    $account_id = $account_product_record['parent']['value'];
    $product_name = get_form_field('name');
    $command = get_form_field('Command');
    if (($account_product_record['id']['value']) &&
        ($command == 'AddRecord')) $command = 'UpdateRecord';
    else if ((! $account_product_record['id']['value']) &&
             ($command == 'UpdateRecord')) $command = 'AddRecord';
    if ($command == 'AddRecord') {
       if (! $db->insert('account_products',$account_product_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Account Product Added');
       log_activity('Added Account Product #'.$product_id .
                    ' ('.$product_name.') to Account #'.$account_id);
       write_product_activity('Added to Account #'.$account_id .
                              get_product_activity_user($db),$product,$db);
    }
    else if ($command == 'UpdateRecord') {
       if (! $db->update('account_products',$account_product_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Account Product Updated');
       log_activity('Updated Account Product #'.$product_id .
                    ' ('.$product_name.') for Account #'.$account_id);
       write_product_activity('Updated in Account #'.$account_id .
                              get_product_activity_user($db),$product,$db);
    }
    else if ($command == 'DeleteRecord') {
       if (! $account_product_record['id']['value']) return;
       if (! $db->delete('account_products',$account_product_record)) {
          http_response(422,$db->error);   return;
       }
       $query = 'delete from account_inventory where parent=? and related_id ' .
                'in (select id from product_inventory where parent=?)';
       $query = $db->prepare_query($query,$account_id,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Account Product Deleted');
       log_activity('Deleted Account Product #'.$product_id .
                    ' ('.$product_name.') from Account #'.$account_id);
       write_product_activity('Deleted from Account #'.$account_id .
                              get_product_activity_user($db),$product,$db);
    }
    else log_activity('update_account_product, Unknown Command = '.$command);
}

function update_account_inventory()
{
    $db = new DB;
    $account_inv_record = account_product_record_definition();
    $db->parse_form_fields($account_inv_record);
    $part_number = get_form_field('part_number');
    $command = get_form_field('Command');
    if (isset($account_inv_record['id']['value']) &&
        $account_inv_record['id']['value'] &&
        ($command == 'AddRecord')) $command = 'UpdateRecord';
    else if (((! isset($account_inv_record['id']['value'])) ||
              (! $account_inv_record['id']['value'])) &&
             ($command == 'UpdateRecord')) $command = 'AddRecord';
    if ($command == 'AddRecord') {
       if (! $db->insert('account_inventory',$account_inv_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Account Inventory Added');
       log_activity('Added Account Inventory #' . 
                    $account_inv_record['related_id']['value'] .
                    ' ('.$part_number.') to Acccount #' .
                    $account_inv_record['parent']['value']);
    }
    else if ($command == 'UpdateRecord') {
       if (! $db->update('account_inventory',$account_inv_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Account Inventory Updated');
       log_activity('Updated Account Inventory #' .
                    $account_inv_record['related_id']['value'] .
                    ' ('.$part_number.') for Account #' .
                    $account_inv_record['parent']['value']);
    }
    else if ($command == 'DeleteRecord') {
       if ((! isset($account_inv_record['id']['value'])) ||
           (! $account_inv_record['id']['value'])) {
          http_response(201,'No Update');   return;
       }
       if (! $db->delete('account_inventory',$account_inv_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Account Inventory Deleted');
       log_activity('Deleted Account Inventory #' .
                    $account_inv_record['related_id']['value'] .
                    ' ('.$part_number.') from Account #' .
                    $account_inv_record['parent']['value']);
    }
    else log_activity('update_account_inventory, Unknown Command = '.$command);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'createaccount') create_account();
else if ($cmd == 'addaccount') add_account();
else if ($cmd == 'processaddaccount') process_add_account();
else if ($cmd == 'editaccount') edit_account();
else if ($cmd == 'updateaccount') update_account();
else if ($cmd == 'copyaccount') copy_account();
else if ($cmd == 'deleteaccount') delete_account();
else if ($cmd == 'selectaccount') select_account();
else if ($cmd == 'updateaccountproduct') update_account_product();
else if ($cmd == 'updateaccountinventory') update_account_inventory();
else if (process_sublist_command($cmd)) {}
else if (function_exists('custom_accounts_command') &&
         custom_accounts_command($cmd)) {}
else display_accounts_screen();

DB::close_all();

?>

