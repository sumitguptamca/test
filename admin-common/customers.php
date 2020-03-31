<?php
/*
                       Inroads Shopping Cart - Customers Tab

                        Written 2008-2019 by Randall Severy
                          Copyright 2008-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'customers-common.php';
require_once 'cartconfig-common.php';
require_once 'utility.php';
require_once 'adminperms.php';
if (! isset($enable_sales_reps)) $enable_sales_reps = false;
if (! isset($enable_wholesale)) $enable_wholesale = false;
if ($enable_wholesale) require_once 'accounts-common.php';
if (! isset($enable_reminders)) $enable_reminders = false;
if ($enable_reminders) require_once '../admin/reminders-admin.php';
if (! isset($enable_reorders)) $enable_reorders = false;
if ($enable_reorders) require_once '../admin/reorders-admin.php';
if (! isset($enable_rmas)) $enable_rmas = false;
if ($enable_rmas) require_once 'rmas-admin.php';
if (! isset($multiple_customer_accounts)) $multiple_customer_accounts = false;
if (! isset($enable_customer_notices)) $enable_customer_notices = false;

function add_customer_filters($screen,$status_values,$db)
{
    global $enable_wholesale,$account_label,$accounts_label;

    if (! $screen->skin) $class = 'select filter_select';
    else $class = null;
    if ($enable_wholesale) {
       get_user_perms($user_perms,$module_perms,$custom_perms,$db);
       if (! ($user_perms & ACCOUNTS_TAB_PERM)) $enable_wholesale = false;
    }

    if ($enable_wholesale) {
       $screen->write("          <script>\n");
       $screen->write("             enable_wholesale = true;\n");
       $screen->write('             account_label = "'.$account_label."\";\n");
       $screen->write('             accounts_label = "'.$accounts_label."\";\n");
       $accounts = load_accounts($db);
       $allowed_accounts = load_allowed_accounts($accounts_list,$db);
       if ($allowed_accounts) {
          $screen->write('             accounts_where = ');
          $screen->write("'(c.account_id in (".$accounts_list."))';\n");
       }
       $screen->write("          </script>\n");
       if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
       else {
          $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
          $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
          $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
       }
       $screen->write($account_label.':');
       if ($screen->skin) $screen->write('</span>');
       else $screen->write("<br>\n");
       $screen->start_choicelist('account','filter_customers();',$class);
       if ($allowed_accounts) {
          if (count($allowed_accounts) > 1)
             $screen->add_list_item('','All Available',true);
       }
       else $screen->add_list_item('','All',true);
       foreach ($accounts as $account_id => $account_info) {
          if ($allowed_accounts &&
              (! array_key_exists($account_id,$allowed_accounts))) continue;
          $screen->add_list_item($account_id,$account_info['name'],false);
       }
       $screen->end_choicelist();
       if ($screen->skin) $screen->write('</div>');
       else $screen->write("</td></tr>\n");
    }

    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Status:');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    $screen->start_choicelist('status','filter_customers();',$class);
    $screen->add_list_item('','All',true);
    foreach ($status_values as $index => $status)
       $screen->add_list_item($index,$status,false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');
    else $screen->write("</td></tr>\n");
}

function display_customers_screen()
{
    global $enable_sales_reps,$enable_reminders,$enable_rmas;
    global $enable_reorders,$multiple_customer_accounts;
    global $enable_customer_notices;

    $db = new DB;
    $status_values = load_cart_options(CUSTOMER_STATUS,$db);
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('customers.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    add_encrypted_fields($screen,'customers');
    if (function_exists('custom_init_customers_screen'))
       custom_init_customers_screen($screen);
    $screen->set_body_id('customers');
    $screen->set_help('customers');
    require_once '../engine/modules.php';
    call_module_event('update_head',array('customers',&$screen,$db));
    $screen->start_body(filemtime('customers.php'));
    if ($screen->skin) {
       $screen->start_title_bar('Customers');
       $screen->start_title_filters();
       add_customer_filters($screen,$status_values,$db);
       add_search_box($screen,'search_customers','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->set_button_width(148);
    $screen->start_button_column();
    $screen->add_button('Add Customer','images/AddCustomer.png',
                        'add_customer();',null,true,false,ADD_BUTTON);
    $screen->add_button('Edit Customer','images/EditCustomer.png',
                        'edit_customer();',null,true,false,EDIT_BUTTON);
    $screen->add_button('Delete Customer','images/DeleteCustomer.png',
                        'delete_customer();',null,true,false,DELETE_BUTTON);
    if (function_exists('display_custom_customer_buttons'))
       display_custom_customer_buttons($screen);
    call_module_event('display_custom_buttons',
                      array('customers',&$screen,$db));
    if (! $screen->skin) {
       add_customer_filters($screen,$status_values,$db);
       add_search_box($screen,'search_customers','reset_search');
    }
    $screen->end_button_column();
    $screen->write("\n          <script>\n");
    if ($enable_sales_reps)
       $screen->write("             enable_sales_reps = true;\n");
    if ($enable_reminders)
       $screen->write("             enable_reminders = true;\n");
    if ($enable_rmas)
       $screen->write("             enable_rmas = true;\n");
    if ($enable_reorders)
       $screen->write("             enable_reorders = true;\n");
    if ($enable_customer_notices)
       $screen->write("             enable_customer_notices = true;\n");
    $saved_cards_flag = get_saved_cards_flag();
    if ($saved_cards_flag)
       $screen->write("             using_saved_cards = true;\n");
    if ($multiple_customer_accounts)
       $screen->write("             multiple_customer_accounts = true;\n");
    $screen->write('             var customer_status_values = [');
    $max_status = max(array_keys($status_values));
    for ($loop = 0;  $loop <= $max_status;  $loop++) {
       if ($loop > 0) $screen->write(',');
       if (isset($status_values[$loop]))
          $screen->write("\"".$status_values[$loop]."\"");
       else $screen->write("\"\"");
    }
    $screen->write("];\n");
    $screen->write("             load_grid(true);\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function display_customer_fields($dialog,$edit_type,$customer_row,
                                 $billing_row,$db,$saved_cards_flag)
{
    global $enable_wholesale,$enable_rewards,$enable_customer_notices;
    global $enable_sales_reps,$enable_reminders,$enable_reorders,$enable_rmas;
    global $account_label,$accounts_label,$multiple_customer_accounts;
    global $admin_mailing_list_prompt,$enable_credit_balance;

    if ($enable_wholesale) {
       get_user_perms($user_perms,$module_perms,$custom_perms,$db);
       if (! ($user_perms & ACCOUNTS_TAB_PERM)) {
          $enable_wholesale = false;   $multiple_customer_accounts = false;
       }
    }
    $customer_status_values = load_cart_options(CUSTOMER_STATUS,$db);
    $order_status_values = load_cart_options(ORDER_STATUS,$db);
    $id = get_row_value($customer_row,'id');
    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('customer_tab','customer_content','change_tab');
    $dialog->add_tab('customer_tab','Customer','customer_tab',
                     'customer_content','change_tab',true,null,FIRST_TAB);
    $dialog->add_tab('billing_tab','Billing','billing_tab','billing_content',
                     'change_tab');
    $dialog->add_tab('shipping_tab','Shipping','shipping_tab',
                     'shipping_content','change_tab');
    if ($edit_type == UPDATERECORD) {
       $dialog->add_tab('history_tab','History','history_tab','history_content',
                        'change_tab');
       $dialog->add_tab('activity_tab','Activity','activity_tab',
                        'activity_content','change_tab');
       $dialog->add_tab('views_tab','Views','views_tab',
                        'views_content','change_tab');
       if ($enable_reminders) add_reminders_tabs($dialog);
       if ($enable_reorders) add_reorders_tab($dialog);
       if ($enable_rmas) add_rmas_tab($dialog);
       if ($saved_cards_flag) add_saved_cards_tab($dialog);
    }
    if ($multiple_customer_accounts) {
       $dialog->add_tab('accounts_tab',$accounts_label,'accounts_tab',
                        'accounts_content','change_tab');
    }
    if ($enable_customer_notices)
       $dialog->add_tab('notices_tab','Notices','notices_tab',
                        'notices_content','change_tab');
    require_once '../engine/modules.php';
    call_module_event('add_customer_tabs',
                      array(&$dialog,$edit_type,$customer_row,$db));
    if (function_exists('custom_add_customer_tabs'))
       custom_add_customer_tabs($dialog,$edit_type,$customer_row,$db);
    $dialog->end_tab_row('tab_row_middle');

    $dialog->start_tab_content('customer_content',true);
    $dialog->start_field_table('customer_table');
    $dialog->add_hidden_field('id',$id);
    $dialog->start_row('Status:','middle');
    $status = get_row_value($customer_row,'status');
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('OldStatus',$status);
    $dialog->start_choicelist('status',null);
    foreach ($customer_status_values as $index => $status_label)
       $dialog->add_list_item($index,$status_label,$status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('E-Mail Address:','email',$customer_row,30);
    $ip_address = get_row_value($customer_row,'ip_address');
    if (! $ip_address) $ip_address = $_SERVER['REMOTE_ADDR'];
    $dialog->add_hidden_field('ip_address',$ip_address);
    if ($edit_type == ADDRECORD)
       $dialog->add_edit_row('Password:','password','',30);
    else $dialog->add_edit_row('New Password:','password','',20,null,
                               '(Fill in only to change password)');
    $dialog->add_edit_row('First Name:','fname',$customer_row,30);
    $dialog->add_edit_row('Middle Name:','mname',$customer_row,30);
    $dialog->add_edit_row('Last Name:','lname',$customer_row,30);
    $dialog->add_edit_row('Company:','company',$customer_row,50);
    if ($enable_sales_reps) {
       $sales_rep = get_row_value($customer_row,'sales_rep');
       $user_list = load_user_list($db,true);
       $dialog->start_row('Sales Rep:','middle');
       $dialog->start_choicelist('sales_rep',null);
       $dialog->add_list_item('','',! $sales_rep);
       display_user_list($dialog,$user_list,$sales_rep);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    $create_date = get_row_value($customer_row,'create_date');
    if ($create_date)
       $dialog->add_text_row('Registered Date:',date('m/d/Y',$create_date));
    else $dialog->add_text_row('Registered Date:','n/a');
    $dialog->add_text_row('IP Address:',$ip_address);
    if ($enable_reminders) {
       $dialog->start_row('Reminders:','middle');
       $dialog->add_checkbox_field('reminders','',$customer_row);
       $dialog->end_row();
    }
    $dialog->start_row('Tax Exempt:','middle');
    $dialog->add_checkbox_field('tax_exempt','',$customer_row);
    $dialog->end_row();
    if ($enable_wholesale && (! $multiple_customer_accounts)) {
       $accounts = load_accounts($db);
       $dialog->start_row($account_label.':','middle');
       $account_id = get_row_value($customer_row,'account_id');
       $dialog->start_choicelist('account_id',null);
       $dialog->add_list_item('','',! $account_id);
       foreach ($accounts as $acct_id => $account_info) {
          $account_name = $account_info['name'];
          if ($account_info['company'])
             $account_name .= ' - '.$account_info['company'];
          $dialog->add_list_item($acct_id,$account_name,
                                 $account_id == $acct_id);
       }
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    if ($enable_rewards)
       $dialog->add_edit_row('Rewards:','rewards',$customer_row,10);
    if (! empty($enable_credit_balance))
       $dialog->add_edit_row('Credit Balance:','credit_balance',
                             $customer_row,6);
    if (! isset($admin_mailing_list_prompt))
       $admin_mailing_list_prompt = 'Mailing List:';
    $dialog->start_row($admin_mailing_list_prompt,'middle');
    $dialog->add_checkbox_field('mailing','',$customer_row);
    $dialog->end_row();
    if (module_attached('display_custom_fields'))
       call_module_event('display_custom_fields',
          array('customers',$db,&$dialog,$edit_type,$customer_row));
    if (function_exists('display_custom_customer_fields'))
       display_custom_customer_fields($dialog,$edit_type,$customer_row,$db);
    else if (function_exists('display_custom_customer_account_fields'))
       display_custom_customer_account_fields($dialog,$edit_type,
                                              $customer_row,$db);
    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->start_tab_content('billing_content',false);
    $dialog->start_field_table('billing_table');
    $country = get_row_value($billing_row,'country');
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('country','select_country(this);');
    load_country_list($country,true,$db);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Address Line 1:','address1',$billing_row,50);
    $dialog->add_edit_row('Address Line 2:','address2',$billing_row,50);
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"city_prompt\">");
    if ($country == 29) print 'Parish';
    else print 'City';
    $dialog->write(":</td>\n");
    $dialog->write("<td><input type=\"text\" class=\"text\" name=\"city\" " .
                   "size=\"30\" value=\"");
    write_form_value(get_row_value($billing_row,'city'));
    $dialog->write("\"></td></tr>\n");
    $state = get_row_value($billing_row,'state');
    $dialog->start_hidden_row('State:','state_row',($country != 1),'middle');
    $dialog->start_choicelist('state',null);
    $dialog->add_list_item('','',false);
    load_state_list($state,true,$db);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','province_row',
       (($country == 1) || ($country == 29) || ($country == 43)));
    $dialog->add_input_field('province',$state,20);
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','canada_province_row',
                              ($country != 43),'middle');
    $dialog->start_choicelist('canada_province',null);
    $dialog->add_list_item('','',false);
    load_canada_province_list($state);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"zip_cell\">");
    if ($country == 1) $dialog->write('Zip Code:');
    else $dialog->write('Postal Code:');
    $dialog->write("</td><td>\n");
    $dialog->add_input_field('zipcode',get_row_value($billing_row,'zipcode'),20);
    $dialog->end_row();
    $dialog->add_edit_row('Telephone:','phone',
                          get_row_value($billing_row,'phone'),20);
    $dialog->add_edit_row('Fax:','fax',
                          get_row_value($billing_row,'fax'),20);
    $dialog->add_edit_row('Mobile:','mobile',
                          get_row_value($billing_row,'mobile'),20);
    if (function_exists('display_custom_customer_billing_fields'))
       display_custom_customer_billing_fields($dialog,$edit_type,$customer_row,$db);
    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->start_tab_content('shipping_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script>create_shipping_grid(".$id.");</script>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    if ($edit_type == UPDATERECORD) {
       $query = 'select count(id) as num_orders,sum(total) as total_sales ' .
                'from orders where customer_id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! $row) {
          $num_orders = 0;   $total_sales = 0;
       }
       else {
          $num_orders = $row['num_orders'];
          $total_sales = $row['total_sales'];
       }

       $dialog->start_tab_content('history_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write('           var order_status_values = [');
       for ($loop = 0;  $loop < count($order_status_values);  $loop++) {
          if ($loop > 0) $dialog->write(',');
          if (isset($order_status_values[$loop]))
             $dialog->write("\"".$order_status_values[$loop]."\"");
          else $dialog->write("\"\"");
       }
       $dialog->write("];\n");
       $dialog->write('           create_history_grid('.$id.");\n");
       $dialog->write("        </script>\n");
       $dialog->write("        <table cellspacing=0 cellpadding=2 border=1>\n");
       $dialog->write("          <tr><td class=\"fieldprompt\">" .
                      "Number of Orders</td>");
       $dialog->write("<td class=\"fieldprompt\">Total Sales</td></tr>\n");
       $dialog->write("          <tr><td align=\"center\">".$num_orders.'</td>');
       $dialog->write("<td align=\"center\">$".number_format($total_sales,2) .
                      "</td></tr>\n");
       $dialog->write("        </table>\n");
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();

       $dialog->start_tab_content('activity_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write('        <script>create_activity_grid('.$id.");</script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();

       $ip_address = $customer_row['ip_address'];
       $dialog->start_tab_content('views_content',false);
       $dialog->start_field_table('views_table','fieldtable',0);
       $dialog->write("<tr><td>\n");
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write('        <div class="fieldprompt views_label">' .
                      'Categories</div>'."\n");
       $dialog->write('        <script>create_category_views_grids('.$id .
                      ',"'.$ip_address."\");</script>\n");
       $dialog->write('        <div class="fieldprompt views_label">' .
                      'Products</div>'."\n");
       $dialog->write('        <script>create_product_views_grids('.$id .
                      ',"'.$ip_address."\");</script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_row();
       $dialog->end_field_table();
       $dialog->end_tab_content();

       if ($enable_reminders)
          add_reminders_tab_sections($dialog,$customer_row,$billing_row,$db);

       if ($enable_reorders)
          add_reorders_tab_section($dialog,$id,$db);

       if ($enable_rmas)
          add_rmas_tab_section($dialog,$id,$db);

       if ($saved_cards_flag)
          add_saved_cards_tab_section($dialog,$customer_row,$db);
    }

    if ($multiple_customer_accounts) {
       $accounts = load_accounts($db);
       $customer_accounts = load_customer_accounts($id,$db);
       $dialog->start_tab_content('accounts_content',false);
       $dialog->start_field_table('accounts_table');
       $dialog->write('<tr><td>');
       $last_id = 0;
       foreach ($accounts as $acct_id => $account_info) {
          $field_name = 'account_'.$acct_id;
          $account_name = $account_info['name'];
          if ($account_info['company'])
             $account_name .= ' - '.$account_info['company'];
          $selected = isset($customer_accounts[$acct_id]);
          $dialog->add_checkbox_field($field_name,$account_name,$selected);
          $dialog->write("<br>\n");
          if ($acct_id > $last_id) $last_id = $acct_id;
       }
       $dialog->write("</td></tr>\n");
       $dialog->add_hidden_field('LastID',$last_id);
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if ($enable_customer_notices && ($edit_type == UPDATERECORD)) {
       $dialog->start_tab_content('notices_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write('        <script>create_customer_notices_grid('.$id .
                      ");</script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    call_module_event('customer_tabs',
                      array(&$dialog,$edit_type,$customer_row,$db));
    if (function_exists('custom_customer_tabs'))
       custom_customer_tabs($dialog,$edit_type,$customer_row,$db);
}

function save_customer_accounts($db,$id,$edit_type)
{
    get_user_perms($user_perms,$module_perms,$custom_perms,$db);
    if (! ($user_perms & ACCOUNTS_TAB_PERM)) return true;
    if ($edit_type == UPDATERECORD) {
       $query = 'delete from customer_accounts where customer_id=?';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    $account_record = customer_account_record_definition();
    $account_record['customer_id']['value'] = $id;
    $last_id = get_form_field('LastID');
    for ($loop = 0;  $loop <= $last_id;  $loop++) {
       if (get_form_field('account_'.$loop) == 'on') {
          $account_record['account_id']['value'] = $loop;
          if (! $db->insert('customer_accounts',$account_record)) return false;
       }
    }
    return true;
}

function add_shipping_buttons($dialog)
{
    $dialog->add_button_separator('shipping_buttons_row',20);
    $dialog->add_button('Add Shipping','images/AddImage.png',
                        'add_shipping();','add_shipping',null,false);
    $dialog->add_button('Edit Shipping','images/EditImage.png',
                        'edit_shipping();','edit_shipping',null,false);
    $dialog->add_button('Delete Shipping','images/DeleteImage.png',
                        'delete_shipping();','delete_shipping',null,false);
    $dialog->add_button('View Order','images/ViewOrder.png','view_order();',
                        'view_order',null,false);
}

function add_notice_buttons($dialog)
{
    $dialog->add_button('Add Notice','images/AddImage.png',
                        'add_notice();','add_notice',null,false);
    $dialog->add_button('Edit Notice','images/EditImage.png',
                        'edit_notice();','edit_notice',null,false);
    $dialog->add_button('Delete Notice','images/DeleteImage.png',
                        'delete_notice();','delete_notice',null,false);
}

function create_customer()
{
    $db = new DB;
    $customer_record = customers_record_definition();
    $customer_record['fname']['value'] = 'New';
    $customer_record['lname']['value'] = 'Customer';
    $customer_record['create_date']['value'] = time();
    if (! $db->insert('customers',$customer_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'customer_id = '.$id.';';
    log_activity('Created New Customer #'.$id);
}

function add_customer()
{
    global $enable_reorders;

    $db = new DB;
    $id = get_form_field('id');
    $customer_row = array();
    $customer_row['id'] = $id;
    $customer_row['create_date'] = time();
    $billing_row = array();
    $billing_row['country'] = 1;
    $frame = get_form_field('frame');
    $order_type = get_form_field('ordertype');

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('customers.css');
    $dialog->add_script_file('customers.js');
    if ($enable_reorders)
       $dialog->add_script_file('../admin/reorders-admin.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_onload_function('add_customer_onload();');
    if (function_exists('init_customer_dialog'))
       init_customer_dialog($dialog,ADDRECORD,$customer_row,$billing_row,$db);
    $dialog_title = 'Add Customer (#'.$id.')';
    $dialog->set_body_id('add_customer');
    $dialog->set_help('add_customer');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(145);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Add Customer','images/AddCustomer.png',
                        'process_add_customer();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_shipping_buttons($dialog);
    add_notice_buttons($dialog);
    if (function_exists('add_custom_customer_tab_buttons'))
       add_custom_customer_tab_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('customers.php','AddCustomer');
    if ($frame) $dialog->add_hidden_field('frame',$frame);
    if ($order_type !== null)
       $dialog->add_hidden_field('ordertype',$order_type);
    if (! $dialog->skin) $dialog->start_field_table();
    display_customer_fields($dialog,ADDRECORD,$customer_row,$billing_row,$db,
                            false);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_customer()
{
    global $multiple_customer_accounts;

    $db = new DB;
    $customer_record = customers_record_definition();
    $db->parse_form_fields($customer_record);
    $id = $customer_record['id']['value'];
    $current_time = time();
    $customer_record['create_date']['value'] = $current_time;
    $customer_record['last_modified']['value'] = $current_time;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $encrypted_password = crypt($customer_record['password']['value'],$ip_address);
    $customer_record['password']['value'] = $encrypted_password;
    $customer_record['ip_address']['value'] = $ip_address;
    if (function_exists('custom_update_customer')) {
       if (! custom_update_customer($db,$customer_record,ADDRECORD)) return;
    }
    if (! $db->update('customers',$customer_record)) {
       http_response(422,$db->error);   return;
    }
    $billing_record = build_billing_record($db,$id);
    $query = 'select id from billing_information where parent=?';
    $query = $db->prepare_query($query,$id);
    $billing_row = $db->get_record($query);
    if (! $billing_row) {
       if (isset($db->error)) {
          http_response(422,$db->error);   return;
       }
       unset($billing_record['id']['value']);
       if (! $db->insert('billing_information',$billing_record)) {
          http_response(422,$db->error);   return;
       }
    }
    else {
       $billing_record['id']['value'] = $billing_row['id'];
       if (! $db->update('billing_information',$billing_record)) {
          http_response(422,$db->error);   return;
       }
    }
    if ($multiple_customer_accounts &&
        (! save_customer_accounts($db,$id,ADDRECORD))) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('add_customer')) {
       $customer_info = $db->convert_record_to_array($customer_record);
       $billing_info = $db->convert_record_to_array($billing_record);
       update_customer_info($db,$customer_info,$billing_info);
       if (! call_module_event('add_customer',
                array($db,$customer_info,$billing_info,null),null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }

    http_response(201,'Customer Added');
    log_activity('Added Customer '.$customer_record['fname']['value'].' '.
                 $customer_record['lname']['value'].' (#'.$id.')');
    write_customer_activity('Customer Added by ' .
                            get_customer_activity_user($db),$id,$db);
}

function edit_customer()
{
    global $enable_reminders,$enable_reorders,$enable_rmas;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from customers where id=?';
    $query = $db->prepare_query($query,$id);
    $customer_row = $db->get_record($query);
    if (! $customer_row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Customer not found',0);
       return;
    }
    $db->decrypt_record('customers',$customer_row);
    $query = 'select * from billing_information where parent=?';
    $query = $db->prepare_query($query,$id);
    $billing_row = $db->get_record($query);
    if (! $billing_row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Customer Billing Information not found',0);
       return;
    }
    $db->decrypt_record('billing_information',$billing_row);
    $saved_cards_flag = get_saved_cards_flag(null,$db);
    if ($saved_cards_flag) require_once 'savedcards.php';
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('customers.css');
    if ($enable_reminders)
       $dialog->add_style_sheet('../admin/reminders-admin.css');
    if ($enable_reorders)
       $dialog->add_style_sheet('../admin/reorders-admin.css');
    if ($enable_rmas)
       $dialog->add_style_sheet('rmas-admin.css');
    $dialog->add_script_file('customers.js');
    if ($saved_cards_flag) $dialog->add_style_sheet('savedcards.css');
    if ($enable_reorders)
       $dialog->add_script_file('../admin/reorders-admin.js');
    if ($enable_reminders)
       $dialog->add_script_file('../admin/reminders-admin.js');
    if ($enable_rmas)
       $dialog->add_script_file('rmas-admin.js');
    if ($saved_cards_flag) $dialog->add_script_file('savedcards.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    if (function_exists('init_customer_dialog'))
       init_customer_dialog($dialog,UPDATERECORD,$customer_row,
                            $billing_row,$db);
    $dialog_title = 'Edit Customer (#'.$id.')';
    $dialog->set_body_id('edit_customer');
    $dialog->set_help('edit_customer');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(145);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Update','images/Update.png','update_customer();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_shipping_buttons($dialog);
    add_notice_buttons($dialog);
    if (function_exists('add_custom_customer_tab_buttons'))
       add_custom_customer_tab_buttons($dialog);
    if ($enable_reminders) add_reminder_buttons($dialog);
    if ($enable_rmas) add_rma_buttons($dialog);
    if ($saved_cards_flag) add_saved_cards_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('customers.php','EditCustomer');
    if (! $dialog->skin) $dialog->start_field_table();
    display_customer_fields($dialog,UPDATERECORD,$customer_row,
                            $billing_row,$db,$saved_cards_flag);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_customer()
{
    global $enable_reorders,$multiple_customer_accounts;

    $db = new DB;
    $customer_record = customers_record_definition();
    $db->parse_form_fields($customer_record);
    if (! isset($customer_record['id']['value'])) {
       http_response(409,'Customer not found');   return;
    }
    if (empty($customer_record['email']['value'])) {
       http_response(406,'E-Mail Address is required');   return;
    }
    if (! validate_customer_email($customer_record['email']['value'])) {
       http_response(406,'Invalid E-Mail Address');   return;
    }
    $id = $customer_record['id']['value'];
    if (! empty($customer_record['password']['value'])) {
       $encrypted_password = crypt($customer_record['password']['value'],
                                   $customer_record['ip_address']['value']);
       $customer_record['password']['value'] = $encrypted_password;
    }
    else unset($customer_record['password']['value']);
    if (function_exists('custom_update_customer')) {
       if (! custom_update_customer($db,$customer_record,UPDATERECORD)) return;
    }
    $billing_record = build_billing_record($db,$id);
    $customer_info = $db->convert_record_to_array($customer_record);
    $billing_info = $db->convert_record_to_array($billing_record);
    $changes = get_customer_changes($db,$customer_info,$billing_info,null);
    if (! empty($changes)) {
       $customer_record['last_modified']['value'] = time();
       if (! $db->update('customers',$customer_record)) {
          http_response(422,$db->error);   return;
       }
       $query = 'select id from billing_information where parent=?';
       $query = $db->prepare_query($query,$id);
       $billing_row = $db->get_record($query);
       if (! $billing_row) {
          if (isset($db->error)) {
             http_response(422,$db->error);   return;
          }
          unset($billing_record['id']['value']);
          if (! $db->insert('billing_information',$billing_record)) {
             http_response(422,$db->error);   return;
          }
       }
       else {
          $billing_record['id']['value'] = $billing_row['id'];
          if (! $db->update('billing_information',$billing_record)) {
             http_response(422,$db->error);   return;
          }
       }
    }
    if ($enable_reorders && (! update_reorders($db))) {
       http_response(422,$db->error);   return;
    }
    if ($multiple_customer_accounts &&
        (! save_customer_accounts($db,$id,UPDATERECORD))) {
       http_response(422,$db->error);   return;
    }
    if (function_exists('change_customer_status')) {
       $old_status = get_form_field('OldStatus');
       $new_status = $customer_record['status']['value'];
       if ($new_status != $old_status) {
          $customer = load_customer($db,$id,$error_msg);
          if (! $customer) {
             http_response(422,$error_msg);   return;
          }
          if (! change_customer_status($old_status,$new_status,$db,$customer))
             return;
       }
    }
    if (! empty($changes)) {
       require_once '../engine/modules.php';
       if (module_attached('update_customer')) {
          $query = 'select * from shipping_information where (parent=?) and ' .
                   '(default_flag=1)';
          $query = $db->prepare_query($query,$id);
          $shipping_info = $db->get_record($query);
          update_customer_info($db,$customer_info,$billing_info,$shipping_info);
          if (! call_module_event('update_customer',
                   array($db,$customer_info,$billing_info,$shipping_info),
                   null,true)) {
             http_response(422,get_module_errors());   return;
          }
       }
    }

    http_response(201,'Customer Updated');
    if (! empty($changes)) {
       log_activity('Updated Customer '.$customer_record['fname']['value'].' '.
                    $customer_record['lname']['value'].' (#'.$id.')');
       $activity = 'Customer Updated by '.get_customer_activity_user($db) .
                   ' ['.implode(',',$changes).']';
       write_customer_activity($activity,$id,$db);
    }
}

function delete_customer()
{
    global $multiple_customer_accounts,$enable_customer_notices;

    $cancelling = get_form_field('cancel');
    $id = get_form_field('id');
    $db = new DB;
    if (! $cancelling) {
       require_once '../engine/modules.php';
       if (module_attached('delete_customer')) {
          $query = 'select * from customers where id=?';
          $query = $db->prepare_query($query,$id);
          $customer_info = $db->get_record($query);
          $query = 'select * from billing_information where parent=?';
          $query = $db->prepare_query($query,$id);
          $billing_info = $db->get_record($query);
          $query = 'select * from shipping_information where (parent=?) and ' .
                   '(default_flag=1)';
          $query = $db->prepare_query($query,$id);
          $shipping_info = $db->get_record($query);
       }
    }
    $query = 'delete from billing_information where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from shipping_information where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    if ((! $cancelling) && get_saved_cards_flag(null,$db)) {
       require_once 'savedcards.php';
       if (! delete_customer_cards($db,$id)) return;
    }
    if ($multiple_customer_accounts) {
       $query = 'delete from customer_accounts where customer_id=?';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    if ($enable_customer_notices) {
       $query = 'delete from customer_notices where parent=?';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return false;
       }
    }
    $customer_record = customers_record_definition();
    $customer_record['id']['value'] = $id;
    if (! $db->delete('customers',$customer_record)) {
       http_response(422,$db->error);   return;
    }
    if (function_exists('custom_update_customer')) {
       if (! custom_update_customer($db,$customer_record,DELETERECORD)) return;
    }
    if ((! $cancelling) && module_attached('delete_customer')) {
       update_customer_info($db,$customer_info,$billing_info,$shipping_info);
       if (! call_module_event('delete_customer',
                array($db,$customer_info,$billing_info,$shipping_info),
                null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Customer Deleted');
    log_activity('Deleted Customer #'.$id);
}

function display_shipping_fields($dialog,$edit_type,$row)
{
    $country = get_row_value($row,'country');
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('id',get_row_value($row,'id'));
    $dialog->add_hidden_field('parent',get_row_value($row,'parent'));
    $dialog->add_edit_row('Profile Name:','profilename',$row,30);
    $dialog->start_row('Default:','middle');
    $dialog->add_checkbox_field('default_flag','',$row);
    $dialog->end_row();
    $dialog->add_edit_row('Ship To:','shipto',$row,50);
    $dialog->add_edit_row('Company:','company',$row,50);
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('country','select_country(this);');
    load_country_list($country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Address Line 1:','address1',$row,50);
    $dialog->add_edit_row('Address Line 2:','address2',$row,50);
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"city_prompt\">");
    if ($country == 29) print 'Parish';
    else print 'City';
    $dialog->write(":</td>\n");
    $dialog->write("<td><input type=\"text\" class=\"text\" name=\"city\" " .
                   "size=\"30\" value=\"");
    write_form_value(get_row_value($row,'city'));
    $dialog->write("\"></td></tr>\n");
    $state = get_row_value($row,'state');
    $dialog->start_hidden_row('State:','state_row',($country != 1),'middle');
    $dialog->start_choicelist('state',null);
    $dialog->add_list_item('','',false);
    load_state_list($state,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','province_row',
       (($country == 1) || ($country == 29) || ($country == 43)));
    $dialog->add_input_field('province',$state,20);
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','canada_province_row',
                              ($country != 43),'middle');
    $dialog->start_choicelist('canada_province',null);
    $dialog->add_list_item('','',false);
    load_canada_province_list($state);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap id=\"zip_cell\">");
    if ($country == 1) $dialog->write('Zip Code:');
    else $dialog->write('Postal Code:');
    $dialog->write("</td><td>\n");
    $dialog->write("<input type=\"text\" class=\"text\" name=\"zipcode\" size=\"20\" value=\"");
    write_form_value(get_row_value($row,'zipcode'));
    $dialog->write("\"></td></tr>\n");
    $address_type = get_row_value($row,'address_type');
    if ($address_type === '') $address_type = get_address_type();
    $dialog->start_row('Address Type:','middle');
    $dialog->add_radio_field('address_type','1','Business',$address_type == 1,null);
    $dialog->write('&nbsp;&nbsp;');
    $dialog->add_radio_field('address_type','2','Residential',$address_type == 2,null);
    $dialog->end_row();

    if (function_exists('display_custom_customer_shipping_fields'))
       display_custom_customer_shipping_fields($dialog,$edit_type,$row);
}

function add_shipping()
{
    $db = new DB;
    $customer_id = get_form_field('parent');
    $query = 'select count(id) as num_records from shipping_information ' .
             'where parent=?';
    $query = $db->prepare_query($query,$customer_id);
    $row = $db->get_record($query);
    $shipping_row = array();
    $shipping_row['parent'] = $customer_id;
    if (empty($row['num_records'])) {
       $shipping_row['profilename'] = 'Default';
       $shipping_row['default_flag'] = 1;
    }
    $shipping_row['country'] = 1;

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('customers.css');
    $dialog->add_script_file('customers.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('add_shipping_address');
    $dialog->set_help('add_shipping_address');
    $dialog->start_body('Add Shipping Address');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Address','images/AddCustomer.png',
                        'process_add_shipping();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('customers.php','AddShipping');
    $dialog->start_field_table();
    display_shipping_fields($dialog,ADDRECORD,$shipping_row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_shipping()
{
    $db = new DB;
    $shipping_record = shipping_record_definition();
    $db->parse_form_fields($shipping_record);
    $shipping_record['parent_type']['value'] = 0;
    if ($shipping_record['country']['value'] == 43)
       $shipping_record['state']['value'] = get_form_field('canada_province');
    else if ($shipping_record['country']['value'] != 1)
       $shipping_record['state']['value'] = get_form_field('province');
    if (! $db->insert('shipping_information',$shipping_record)) {
       http_response(422,$db->error);   return;
    }
    $customer_id = $shipping_record['parent']['value'];
    require_once '../engine/modules.php';
    if ($shipping_record['default_flag']['value'] == 1) {
       if (module_attached('update_customer')) {
          $query = 'select * from customers where id=?';
          $query = $db->prepare_query($query,$customer_id);
          $customer_info = $db->get_record($query);
          $query = 'select * from billing_information where parent=?';
          $query = $db->prepare_query($query,$customer_id);
          $billing_info = $db->get_record($query);
          $shipping_info = $db->convert_record_to_array($shipping_record);
          update_customer_info($db,$customer_info,$billing_info,
                               $shipping_info);
          if (! call_module_event('update_customer',
                   array($db,$customer_info,$billing_info,$shipping_info),
                   null,true)) {
             http_response(422,get_module_errors());   return;
          }
       }
    }
    else if (module_attached('add_shipping')) {
       $shipping_info = $db->convert_record_to_array($shipping_record);
       if (! call_module_event('add_shipping',array($db,$shipping_info),
                               null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Shipping Address Added');
    $activity = 'Added Shipping Address '. 
                $shipping_record['profilename']['value'];
    log_activity($activity.' to Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function edit_shipping()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from shipping_information where id=?';
    $query = $db->prepare_query($query,$id);
    $shipping_row = $db->get_record($query);
    if (! $shipping_row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Shipping Information not found',0);
       return;
    }
    $db->decrypt_record('shipping_information',$shipping_row);
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('customers.css');
    $dialog->add_script_file('customers.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog_title = 'Edit Shipping Address (#'.$id.')';
    $dialog->set_body_id('edit_shipping_address');
    $dialog->set_help('edit_shipping_address');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_shipping();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('customers.php','EditShipping');
    $dialog->start_field_table();
    display_shipping_fields($dialog,UPDATERECORD,$shipping_row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_shipping()
{
    $db = new DB;
    $shipping_record = shipping_record_definition();
    $db->parse_form_fields($shipping_record);
    $shipping_record['parent_type']['value'] = 0;
    if ($shipping_record['country']['value'] == 43)
       $shipping_record['state']['value'] = get_form_field('canada_province');
    else if ($shipping_record['country']['value'] != 1)
       $shipping_record['state']['value'] = get_form_field('province');
    if (! $db->update('shipping_information',$shipping_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('update_shipping')) {
       $shipping_info = $db->convert_record_to_array($shipping_record);
       if (! call_module_event('update_shipping',array($db,$shipping_info),
                               null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Shipping Address Updated');
    $activity = 'Updated Shipping Address ' .
                $shipping_record['profilename']['value'];
    $customer_id = $shipping_record['parent']['value'];
    log_activity($activity.' for Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function delete_shipping()
{
    $id = get_form_field('id');
    $db = new DB;
    $shipping_record = shipping_record_definition();
    $shipping_record['id']['value'] = $id;
    if (! $db->delete('shipping_information',$shipping_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (! call_module_event('delete_shipping',array($db,$id),null,true)) {
       http_response(422,get_module_errors());   return;
    }
    http_response(201,'Shipping Address Deleted');
    log_activity('Deleted Shipping Address '.$id);
}

function select_customer()
{
    $status_values = load_cart_options(CUSTOMER_STATUS);
    $frame = get_form_field('frame');
    $change_function = get_form_field('change_function');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('utility.css');
    $dialog->add_script_file('customers.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('select_customer');
    $dialog->set_help('select_customer');
    $dialog->start_body('Select Customer');
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button('Select','images/Update.png','select_customer();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    $dialog->add_button('View','images/Update.png','edit_customer();');
    add_search_box($dialog,'search_customers','reset_search');

    $dialog->write("<div class=\"filter compact-filter\"><span>");
    $dialog->write('Status:');
    $dialog->write('</span>');
    $dialog->start_choicelist('customerStatusSelector', null);
    $dialog->add_list_item('','All',true);
    foreach ($status_values as $index => $status)
        $dialog->add_list_item($index,$status,false);
    $dialog->end_choicelist();
    $dialog->write('</div>');

    $dialog->end_button_column();
    $dialog->write("\n          <script>\n");
    $dialog->write("             var select_frame = '".$frame."';\n");
    if ($change_function)
       $dialog->write("             var change_function = '".$change_function .
                      "';\n");
    $dialog->write('             var customer_status_values = [');
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $dialog->write(',');
       if (isset($status_values[$loop]))
          $dialog->write("\"".$status_values[$loop]."\"");
       else $dialog->write("\"\"");
    }
    $dialog->write("];\n");
    $dialog->write("             load_grid(false);\n");
    $dialog->write("          </script>\n");
    $dialog->end_body();
}

function display_notice_fields($db,$dialog,$edit_type,$row)
{
    global $customer_notice_attributes;

    if (! isset($customer_notice_attributes))
       $customer_notice_attributes = true;
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('id',get_row_value($row,'id'));
    $dialog->add_hidden_field('parent',get_row_value($row,'parent'));
    $dialog->start_row('Product:','middle','fieldprompt',null,true);
    $dialog->write("<span id=\"product_cell\">\n");
    $product_id = get_row_value($row,'product_id');
    if ($product_id) {
       $query = 'select name from products where id=?';
       $query = $db->prepare_query($query,$product_id);
       $product_row = $db->get_record($query);
       if ($product_row && $product_row['name'])
          $dialog->write($product_row['name']);
    }
    $dialog->write("</span>\n");
    $dialog->add_hidden_field('product_id',$product_id);
    $dialog->write("<input type=\"button\" class=\"small_button\" ");
    $dialog->write("value=\"Select...\" onClick=\"select_notice_product();\">\n");
    $dialog->end_row();
    if ($customer_notice_attributes)
       $dialog->add_edit_row('Attributes:','attributes',$row,10);
    $followup = get_row_value($row,'followup');
    $dialog->start_row('Followup:','middle');
    $dialog->add_radio_field('followup','0','E-Mail',$followup == 1);
    $dialog->write('&nbsp;&nbsp;');
    $dialog->add_radio_field('followup','1','Phone',$followup == 1);
    $dialog->end_row();
    $create_date = get_row_value($row,'create_date');
    $dialog->start_row('Create Date:','middle');
    $dialog->add_date_field('create_date',$create_date,false);
    $dialog->end_row();
    $notify_date = get_row_value($row,'notify_date');
    $dialog->start_row('Notify Date:','middle');
    $dialog->add_date_field('notify_date',$notify_date,false);
    $dialog->end_row();
}

function add_notice()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('customers.css');
    $dialog->add_script_file('customers.js');
    $dialog->set_body_id('add_notice');
    $dialog->set_help('add_notice');
    $dialog->start_body('Add Notice');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Notice','images/AddCustomer.png',
                        'process_add_notice();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('customers.php','AddNotice');
    $dialog->start_field_table('notice_table');
    $row = array();
    $row['parent'] = get_form_field('parent');
    display_notice_fields($db,$dialog,ADDRECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_notice()
{
    $db = new DB;
    $notices_record = notices_record_definition();
    $db->parse_form_fields($notices_record);
    if (! $db->insert('customer_notices',$notices_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Customer Notice Added');
    $activity = 'Added Customer Notice for Product #' .
                 $notices_record['product_id']['value'];
    $customer_id = $notices_record['parent']['value'];
    log_activity($activity.' to Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function edit_notice()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from customer_notices where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Customer Notice not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('customers.css');
    $dialog->add_script_file('customers.js');
    $dialog_title = 'Edit Notice (#'.$id.')';
    $dialog->set_body_id('edit_notice');
    $dialog->set_help('edit_notice');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_notice();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('customers.php','EditNotice');
    $dialog->start_field_table('notice_table');
    display_notice_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_notice()
{
    $db = new DB;
    $notices_record = notices_record_definition();
    $db->parse_form_fields($notices_record);
    if (! $db->update('customer_notices',$notices_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Customer Notice Updated');
    $activity = 'Updated Customer Notice #'.$notices_record['id']['value'];
    $customer_id = $notices_record['parent']['value'];
    log_activity($activity.' for Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function delete_notice()
{
    $id = get_form_field('id');
    $db = new DB;
    $notices_record = notices_record_definition();
    $notices_record['id']['value'] = $id;
    if (! $db->delete('customer_notices',$notices_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Customer Notice Deleted');
    log_activity('Deleted Customer Notice #'.$id);
}

function customer_address_error($msg,$customer,$order)
{
    $output = $msg.' for Customer '.$customer['fname'].' '.$customer['lname'];
    if ($order) $output .= ' (Order #'.$customer['id'].')';
    else $output .= ' (#'.$customer['id'].')';
    print $output."<br>\n";
}

function delete_orphan_address($db,$address,$billing,$order)
{
    if ($billing) {
       if ($order) $table = 'order_billing';
       else $table = 'billing_information';
       $activity = 'Deleted Orphaned Billing Record';
    }
    else {
       if ($order) $table = 'order_shipping';
       else $table = 'shipping_information';
       $activity = 'Deleted Orphaned Shipping Record';
    }
    $query = 'delete from '.$table.' where id=?';
    $query = $db->prepare_query($query,$address['id']);
    $db->log_query($query);
    if (! $db->query($query)) {
       print 'Database Error: '.$db->error."<br>\n";   return;
    }
    if ($order) $activity .= ' for Order #'.$address['parent'];
    else $activity .= ' for Customer #'.$address['parent'];
    print $activity."<br>\n";
}

function cleanup_customer_address($db,&$address,$customers,$billing,$order)
{
    if (! empty($address['address1'])) return;
    if (empty($address['address2'])) return;
    if (empty($address['city'])) return;
    if (empty($address['country'])) return;

    if ($billing) {
       $record = billing_record_definition();
       if ($order) $table = 'order_billing';
       else $table = 'billing_information';
       $activity = 'Fixed Billing Street Address';
    }
    else {
       $record = shipping_record_definition();
       if ($order) $table = 'order_shipping';
       else $table = 'shipping_information';
       $activity = 'Fixed Shipping Street Address';
    }
    $record['id']['value'] = $address['id'];
    $record['address1']['value'] = $address['address2'];
    $record['address2']['value'] = '';
    $address['address1'] = $address['address2'];
    $address['address2'] = '';
    $parent = $address['parent'];
    if (! $db->update($table,$record)) {
       print 'Database Error: '.$db->error."<br>\n";   return;
    }
    if (! $order)
       write_customer_activity($activity.' by ' .
                               get_customer_activity_user($db) .
                               ' (cleanup)',$parent,$db);
    customer_address_error($activity,$customers[$parent],$order);
}

function empty_address($address,$billing,$indexing=false)
{
    if ($address['zipcode'] == '123456') return false;
    if (empty($address['address1']) || empty($address['city']) ||
        empty($address['country'])) {
       if ($indexing) return true;
       print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
       if ($billing) print 'Billing';
       else print 'Shipping';
       print ' Address1: '.$address['address1'].', Address2: ' .
             $address['address2'].', City: '.$address['city'].', State: ' .
             $address['state'].', Zip: '.$address['zipcode'].', Country: ' .
             $address['country']."<br>\n";
       return true;
    }
    return false;
}

function index_billing_records($db,$billing_records,$customers,$order=false)
{
    $new_records = array();
    foreach ($billing_records as $billing) {
       $parent = $billing['parent'];
       if (isset($new_records[$parent])) {
          if ($order) $table = 'order_billing';
          else $table = 'billing_information';
          $query = 'delete from '.$table.' where id=?';
          $query = $db->prepare_query($query,$billing['id']);
          $db->log_query($query);
          if (! $db->query($query)) {
             print 'Database Error: '.$db->error."<br>\n";   return;
          }
          if (! $order)
             write_customer_activity('Deleted Duplicate Billing Address by ' .
                                     get_customer_activity_user($db) .
                                     ' (cleanup)',$parent,$db);
          customer_address_error('Deleted Duplicate Billing Address',
                                 $customers[$parent],$order);
       }
       else if (! isset($customers[$parent]))
          delete_orphan_address($db,$billing,true,$order);
       else {
          cleanup_customer_address($db,$billing,$customers,true,$order);
          $new_records[$parent] = $billing;
       }
    }
    return $new_records;
}

function index_shipping_records($db,$shipping_records,$customers,$order=false)
{
    $new_records = array();
    foreach ($shipping_records as $shipping) {
       $parent = $shipping['parent'];
       if (isset($new_records[$parent])) {
          if (empty_address($new_records[$parent],false,true) &&
              (! empty_address($shipping,false,true))) {
             cleanup_customer_address($db,$shipping,$customers,false,$order);
             $new_records[$parent] = $shipping;
          }
       }
       else if (! isset($customers[$parent]))
          delete_orphan_address($db,$shipping,false,$order);
       else {
          cleanup_customer_address($db,$shipping,$customers,false,$order);
          $new_records[$parent] = $shipping;
       }
    }
    return $new_records;
}

function update_empty_address($src_address,&$dest_address)
{
    $dest_address['parent_type']['value'] = 0;
    $dest_address['parent']['value'] = $src_address['parent'];
    if (isset($dest_address['profilename'])) {
       $dest_address['profilename']['value'] = 'Default';
       $dest_address['default_flag']['value'] = 1;
    }
    $dest_address['address1']['value'] = $src_address['address1'];
    if (! empty($src_address['address2']))
       $dest_address['address2']['value'] = $src_address['address2'];
    else $dest_address['address2']['value'] = '';
    $dest_address['city']['value'] = $src_address['city'];
    if (! empty($src_address['state']))
       $dest_address['state']['value'] = $src_address['state'];
    else $dest_address['state']['value'] = '';
    if (! empty($src_address['zipcode']))
       $dest_address['zipcode']['value'] = $src_address['zipcode'];
    $dest_address['country']['value'] = $src_address['country'];
}

function update_customer_address($db,$customer,$billing_records,
                                 $shipping_records,$order=false)
{
    $activity = null;
    $id = $customer['id'];
    if (isset($billing_records[$id])) {
       $billing_address = $billing_records[$id];
       if (empty_address($billing_address,true)) $empty_billing = true;
       else $empty_billing = false;
    }
    else $billing_address = null;
    if (isset($shipping_records[$id])) {
       $shipping_address = $shipping_records[$id];
       if (empty_address($shipping_address,false)) $empty_shipping = true;
       else $empty_shipping = false;
    }
    else $shipping_address = null;

    if ((! $billing_address) && (! $shipping_address))
       customer_address_error('No Billing or Shipping Address Found',
                              $customer,$order);
    else if (! $shipping_address) {
       if ($empty_billing)
          customer_address_error(
             'Empty Billing Address and No Shipping Address Found',
             $customer,$order);
       else {
          $shipping_record = shipping_record_definition();
          update_empty_address($billing_address,$shipping_record);
          if ($order) $table = 'order_shipping';
          else $table = 'shipping_information';
          if (! $db->insert($table,$shipping_record)) {
             print 'Database Error: '.$db->error."<br>\n";   return;
          }
          $activity = 'Copied Billing Address to Missing Shipping Address';
          customer_address_error('Added New Shipping Address',$customer,$order);
       }
    }
    else if (! $billing_address) {
       if ($empty_shipping)
          customer_address_error(
             'Empty Shipping Address and No Billing Address Found',
             $customer,$order);
       else {
          $billing_record = billing_record_definition();
          update_empty_address($shipping_address,$billing_record);
          if ($order) $table = 'order_billing';
          else $table = 'billing_information';
          if (! $db->insert($table,$billing_record)) {
             print 'Database Error: '.$db->error."<br>\n";   return;
          }
          $activity = 'Copied Shipping Address to Missing Billing Address';
          customer_address_error('Added New Billing Address',$customer,$order);
       }
    }
    else if ($empty_billing && $empty_shipping)
       customer_address_error('Billing and Shipping Addresses are both Empty',
                              $customer,$order);
    else if ($empty_shipping) {
       $shipping_record = shipping_record_definition();
       update_empty_address($billing_address,$shipping_record);
       $shipping_record['id']['value'] = $shipping_address['id'];
       if ($order) $table = 'order_shipping';
       else $table = 'shipping_information';
       if (! $db->update($table,$shipping_record)) {
          print 'Database Error: '.$db->error."<br>\n";   return;
       }
       $activity = 'Copied Billing Address to Empty Shipping Address';
       customer_address_error('Copied Billing to Shipping Address',
                              $customer,$order);
    }
    else if ($empty_billing) {
       $billing_record = billing_record_definition();
       update_empty_address($shipping_address,$billing_record);
       $billing_record['id']['value'] = $billing_address['id'];
       if ($order) $table = 'order_billing';
       else $table = 'billing_information';
       if (! $db->update($table,$billing_record)) {
          print 'Database Error: '.$db->error."<br>\n";   return;
       }
       $activity = 'Copied Shipping Address to Empty Billing Address';
       customer_address_error('Copied Shipping to Billing Address',
                              $customer,$order);
    }
    if ($activity && (! $order))
       write_customer_activity($activity.' by ' .
                               get_customer_activity_user($db).' (cleanup)',
                               $id,$db);
}

function cleanup_addresses()
{
    set_time_limit(0);
    ignore_user_abort(true);
    ini_set('memory_limit','3072M');

    $db = new DB;

    print "<h3>Cleaning up Customer Addresses</h3>\n";

    $query = 'select * from customers order by id';
    $customers = $db->get_records($query,'id');
    if (! $customers) {
      print 'Database Error: '.$db->error."<br>\n";   return;
    }
    $query = 'select * from billing_information order by id';
    $billing_records = $db->get_records($query);
    if (! $billing_records) {
      print 'Database Error: '.$db->error."<br>\n";   return;
    }
    $query = 'select * from shipping_information order by id';
    $shipping_records = $db->get_records($query);
    if (! $shipping_records) {
      print 'Database Error: '.$db->error."<br>\n";   return;
    }
    $billing_records = index_billing_records($db,$billing_records,$customers);
    $shipping_records = index_shipping_records($db,$shipping_records,
                                               $customers);
    foreach ($customers as $customer)
       update_customer_address($db,$customer,$billing_records,
                               $shipping_records);

    print "<h3>Cleaning up Order Addresses</h3>\n";

    $query = 'select * from orders order by id';
    $customers = $db->get_records($query,'id');
    if (! $customers) {
      print 'Database Error: '.$db->error."<br>\n";   return;
    }
    $query = 'select * from order_billing where (parent_type=0) order by id';
    $billing_records = $db->get_records($query);
    if (! $billing_records) {
      print 'Database Error: '.$db->error."<br>\n";   return;
    }
    $query = 'select * from order_shipping where (parent_type=0) order by id';
    $shipping_records = $db->get_records($query);
    if (! $shipping_records) {
      print 'Database Error: '.$db->error."<br>\n";   return;
    }
    $billing_records = index_billing_records($db,$billing_records,$customers,
                                             true);
    $shipping_records = index_shipping_records($db,$shipping_records,
                                               $customers,true);
    foreach ($customers as $customer)
       update_customer_address($db,$customer,$billing_records,
                               $shipping_records,true);

    log_activity('Cleaned up Customer and Order Addresses');
    print "<h3>Cleaned up Customer and Order Addresses</h3>\n";
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'createcustomer') create_customer();
else if ($cmd == 'addcustomer') add_customer();
else if ($cmd == 'processaddcustomer') process_add_customer();
else if ($cmd == 'editcustomer') edit_customer();
else if ($cmd == 'updatecustomer') update_customer();
else if ($cmd == 'deletecustomer') delete_customer();
else if ($cmd == 'addshipping') add_shipping();
else if ($cmd == 'processaddshipping') process_add_shipping();
else if ($cmd == 'editshipping') edit_shipping();
else if ($cmd == 'updateshipping') update_shipping();
else if ($cmd == 'deleteshipping') delete_shipping();
else if ($cmd == 'selectcustomer') select_customer();
else if ($cmd == 'addnotice') add_notice();
else if ($cmd == 'processaddnotice') process_add_notice();
else if ($cmd == 'editnotice') edit_notice();
else if ($cmd == 'updatenotice') update_notice();
else if ($cmd == 'deletenotice') delete_notice();
else if ($cmd == 'cleanupaddresses') cleanup_addresses();
else if ($enable_reminders && process_reminders_command($cmd)) {}
else if (function_exists('process_customer_command') &&
         process_customer_command($cmd)) {}
else if (substr($cmd,-9) == 'savedcard') {
   require_once 'savedcards.php';
   process_saved_card_command($cmd);
}
else {
   require_once '../engine/modules.php';
   if (call_module_event('custom_command',array('customers',$cmd),
                         null,true,true)) {}
   else display_customers_screen();
}

DB::close_all();

?>
