/*
             Inroads Shopping Cart - Customers Tab JavaScript Functions

                        Written 2008-2018 by Randall Severy
                         Copyright 2008-2018 Inroads, LLC
*/

var customers_grid = null;
var shipping_grid;
var customer_notices_grid;
var activity_grid;
var cancel_add_customer = true;
var main_grid_flag;
var accounts_where = null;
var enable_wholesale = false;
var enable_sales_reps = false;
var enable_reminders = false;
var enable_rmas = false;
var enable_reorders = false;
var enable_customer_notices = false;
var using_saved_cards = false;
var multiple_customer_accounts = false;
var account_label = 'Account';
var accounts_label = 'Accounts';
var filter_where = '';

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(customers_grid,-1,new_height - get_grid_offset(customers_grid));
    else resize_grid(customers_grid,new_width,new_height);
}

function resize_dialog(new_width,new_height)
{
    if (customers_grid) {
       if (top.skin)
          resize_grid(customers_grid,-1,new_height -
                      get_grid_offset(customers_grid));
       else resize_grid(customers_grid,new_width,new_height);
    }
}

function load_grid(main_screen_flag)
{
   main_grid_flag = main_screen_flag;
   var grid_size = get_default_grid_size();
   customers_grid = new Grid('customers',grid_size.width,grid_size.height);
   customers_grid.set_server_sorting(true);
   if (main_screen_flag) {
      if (typeof(customer_field_names) == 'undefined') {
         if (enable_wholesale)
            customer_field_names = ['id','status','account','email',
               'create_date','fname','lname','city','state','zipcode',
               'country','phone','fax','mobile'];
         else customer_field_names = ['id','status','email','create_date',
                 'fname','lname','city','state','zipcode','country','phone',
                 'fax','mobile'];
         if (enable_sales_reps) customer_field_names.push('sales_rep');
      }
      customers_grid.set_field_names(customer_field_names);
      if (typeof(customer_columns) == 'undefined') {
         if (enable_wholesale) {
            if (multiple_customer_accounts) var label = accounts_label;
            else var label = account_label;
            customer_columns = ['Id','Status',label,'Email Address',
               'Registered Date','First Name','Last Name','City','State','Zip',
               'Country','Phone','Fax','Mobile'];
         }
         else customer_columns = ['Id','Status','Email Address',
                 'Registered Date','First Name','Last Name','City','State',
                 'Zip','Country','Phone','Fax','Mobile'];
         if (enable_sales_reps) customer_columns.push('Sales Rep');
      }
      customers_grid.set_columns(customer_columns);
      if (typeof(customer_column_widths) == 'undefined') {
         if (enable_wholesale)
            customer_column_widths = [0,50,100,180,90,80,80,90,50,50,80,90,0,0];
         else customer_column_widths = [0,50,180,90,80,80,90,50,50,80,90,0,0];
         if (enable_sales_reps) customer_column_widths.push(100);
      }
      customers_grid.set_column_widths(customer_column_widths);
      if (typeof(customer_query) == 'undefined') {
         if (enable_wholesale) {
            var query = 'select c.id,c.status,';
            if (multiple_customer_accounts)
               query += '(select group_concat(a.name order by a.name ' +
                  'separator ", ") from accounts a where a.id in (select ' +
                  'account_id from customer_accounts where customer_id=c.id)) ' +
                  'as account';
               else query += '(select name from accounts where id=' +
                  'c.account_id) as account';
               query += ',c.email,c.create_date,c.fname,c.lname,' +
               'b.city,b.state,b.zipcode,(select country from countries ' +
               'where id=b.country) as country,b.phone,b.fax,b.mobile';
         }
         else var query = 'select c.id,c.status,c.email,c.create_date,c.fname,c.lname,' +
                 'b.city,b.state,b.zipcode,(select country from countries where ' +
                 'id=b.country) as country,b.phone,b.fax,b.mobile';
         if (enable_sales_reps)
            query += ',(select concat(firstname," ",lastname) from users ' +
                     'where username=c.sales_rep) as sales_rep';
         query += ' from customers c left join billing_information b on ' +
                  'b.parent=c.id';
      }
      else var query = customer_query;
   }
   else {
      customers_grid.set_columns(['Id','Email Address','First Name','Last Name',
                                  'City','State','Status','','','','','','','',
                                  '','','','','','','','','','','','','','']);
      customers_grid.set_column_widths([0,150,80,80,70,35,50,0,0,0,0,0,0,0,0,0,
                                        0,0,0,0,0,0,0,0,0,0,0,0]);
      var query = 'select c.id,c.email,c.fname,c.lname,b.city,b.state,' +
                  'c.status,c.company,c.mname,b.address1,b.address2,b.zipcode,' +
                  'b.country,b.phone,b.fax,b.mobile,s.profilename,s.shipto,' +
                  's.company as ship_company,s.address1 as ship_address1,' +
                  's.address2 as ship_address2,s.city as ship_city,' +
                  's.state as ship_state,s.zipcode as ship_zipcode,' +
                  's.country as ship_country,s.address_type,c.account_id,' +
                  'c.credit_balance from customers c left join ' +
                  'billing_information b on b.parent=c.id left join ' +
                  'shipping_information s on (s.parent=c.id) and ' +
                  '(s.default_flag=1)';
      customers_grid.set_where('s.default_flag=1');
   }
   customers_grid.set_query(query);
   customers_grid.table.set_decrypt_table('customers,billing_information');
   if (main_screen_flag)
      query = 'select count(c.id) from customers c left join ' +
                'billing_information b on b.parent=c.id';
   else query = 'select count(c.id) from customers c left join ' +
                'shipping_information s on (s.parent=c.id) and ' +
                '(s.default_flag=1) left join ' +
                'billing_information b on b.parent=c.id';
   customers_grid.set_info_query(query);
   if (typeof(customer_where) != 'undefined') var where = customer_where;
   else var where = '';
   if (accounts_where) {
      if (where) where += ' and ';
      where += accounts_where;
   }
   customers_grid.set_where(where);
   if (typeof(customer_order_by) != 'undefined')
      customers_grid.set_order_by(customer_order_by);
   else customers_grid.set_order_by('c.create_date desc,c.lname,c.fname');
   if (typeof(convert_customer_data) != 'undefined')
      customers_grid.table.set_convert_cell_data(convert_customer_data);
   else customers_grid.table.set_convert_cell_data(convert_data);
   customers_grid.load(false);
   if (main_screen_flag)
      customers_grid.set_double_click_function(edit_customer);
   else customers_grid.set_double_click_function(select_customer);
   if (main_screen_flag) {
      date_format.setTextFormat('m/d/yyyy');
      if (enable_wholesale) customers_grid.set_cell_format(date_format,4);
      else customers_grid.set_cell_format(date_format,3);
   }
   customers_grid.display();
}

function reload_grid()
{
   customers_grid.table.reset_data(false);
   customers_grid.grid.refresh();
   window.setTimeout(function() { customers_grid.table.restore_position(); },0);
}

function add_customer()
{
   cancel_add_customer = true;
   top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('customers.php','cmd=createcustomer',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_customer,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_customer(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var customer_id = -1;
   eval(ajax_request.request.responseText);

   customers_grid.table.save_position();
   top.create_dialog('add_customer',null,null,800,400,false,
                     '../cartengine/customers.php?cmd=addcustomer&id=' +
                     customer_id,null);
}

function add_customer_onclose(user_close)
{
   if (cancel_add_customer) {
      var customer_id = document.AddCustomer.id.value;
      call_ajax('customers.php','cmd=deletecustomer&cancel=true&id=' +
                customer_id,true);
   }
}

function add_customer_onload()
{
   top.set_current_dialog_onclose(add_customer_onclose);
}

function process_add_customer()
{
   if (! validate_form_field(document.AddCustomer.email,'E-Mail Address')) return;

   top.enable_current_dialog_progress(true);
   submit_form_data('customers.php','cmd=processaddcustomer',
                    document.AddCustomer,finish_add_customer);
}

function update_frame_customer()
{
   var form = document.AddCustomer;
   if (form.account_id) var account_id = get_selected_list_value('account_id');
   else var account_id = null;
   var customer_data = {
      id: form.id.value,
      status: get_selected_list_value('status'),
      email: form.email.value,
      fname: form.fname.value,
      mname: form.mname.value,
      lname: form.lname.value,
      company: form.company.value,
      account_id: account_id,
      bill_address1: form.address1.value,
      bill_address2: form.address2.value,
      bill_city: form.city.value,
      bill_state: form.state.value,
      bill_zipcode: form.zipcode.value,
      bill_country: get_selected_list_value('country'),
      bill_phone: form.phone.value,
      bill_fax: form.fax.value,
      bill_mobile: form.mobile.value
   };
   if (shipping_grid.table._num_rows < 1) {
      customer_data.ship_profilename = 'Default';
      customer_data.ship_shipto = '';
      customer_data.ship_company = customer_data.company;
      customer_data.ship_address1 = customer_data.bill_address1;
      customer_data.ship_address2 = customer_data.bill_address2;
      customer_data.ship_city = customer_data.bill_city;
      customer_data.ship_state = customer_data.bill_state;
      customer_data.ship_zipcode = customer_data.bill_zipcode;
      customer_data.ship_country = customer_data.bill_country;
      customer_data.ship_address_type = 0;
   }
   else {
      var grid = shipping_grid.grid;
      var grid_row = grid.getCurrentRow();
      customer_data.ship_profilename = grid.getCellText(1,grid_row);
      customer_data.ship_shipto = grid.getCellText(3,grid_row);
      customer_data.ship_company = grid.getCellText(10,grid_row);
      customer_data.ship_address1 = grid.getCellText(4,grid_row);
      customer_data.ship_address2 = grid.getCellText(11,grid_row);
      customer_data.ship_city = grid.getCellText(5,grid_row);
      customer_data.ship_state = grid.getCellText(6,grid_row);
      customer_data.ship_zipcode = grid.getCellText(7,grid_row);
      customer_data.ship_country = grid.getCellText(9,grid_row);
      customer_data.ship_address_type = grid.getCellText(12,grid_row);
   }
   var frame = form.frame.value;
   var iframe = top.get_dialog_frame(frame).contentWindow;
   iframe.change_customer(customer_data);
}

function finish_add_customer(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      cancel_add_customer = false;
      if (document.AddCustomer.frame) update_frame_customer();
      else top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_customer()
{
   if (customers_grid.table._num_rows < 1) {
      alert('There are no customers to edit');   return;
   }
   var grid_row = customers_grid.grid.getCurrentRow();
   var id = customers_grid.grid.getCellText(0,grid_row);
   customers_grid.table.save_position();
   var dialog_width = 800;
   if (enable_reminders) dialog_width += 80;
   if (enable_rmas) dialog_width += 50;
   if (enable_reorders) dialog_width += 60;
   if (enable_customer_notices) dialog_width += 60;
   if (using_saved_cards) dialog_width += 100;
   if (multiple_customer_accounts) dialog_width += 80;
   top.create_dialog('edit_customer',null,null,dialog_width,400,false,
                     '../cartengine/customers.php?cmd=editcustomer&id=' + id,null);
}

function update_customer()
{
   if (! validate_form_field(document.EditCustomer.email,'E-Mail Address')) return;

   top.enable_current_dialog_progress(true);
   submit_form_data('customers.php','cmd=updatecustomer',document.EditCustomer,
                    finish_update_customer);
}

function finish_update_customer(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var frame = top.get_content_frame();
      if (typeof(frame.reload_grid) == 'function') frame.reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_customer()
{
   if (customers_grid.table._num_rows < 1) {
      alert('There are no customers to delete');   return;
   }
   var grid_row = customers_grid.grid.getCurrentRow();
   var id = customers_grid.grid.getCellText(0,grid_row);
   if (enable_wholesale) {
      var fname_col = 5;   var lname_col = 6;
   }
   else {
      var fname_col = 4;   var lname_col = 5;
   }
   var first_name = customers_grid.grid.getCellText(fname_col,grid_row);
   var last_name = customers_grid.grid.getCellText(lname_col,grid_row);
   var response = confirm('Are you sure you want to delete '+first_name+' '+
                          last_name+'?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   customers_grid.table.save_position();
   call_ajax('customers.php','cmd=deletecustomer&id=' + id,true,
             finish_delete_customer);
}

function finish_delete_customer(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function enable_shipping_buttons(enable_flag)
{
   var add_shipping_button = document.getElementById('add_shipping');
   var edit_shipping_button = document.getElementById('edit_shipping');
   var delete_shipping_button = document.getElementById('delete_shipping');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_shipping_button.style.display = display_style;
   edit_shipping_button.style.display = display_style;
   delete_shipping_button.style.display = display_style;
}

function enable_history_buttons(enable_flag)
{
   var view_order_button = document.getElementById('view_order');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   view_order_button.style.display = display_style;
}

function enable_notice_buttons(enable_flag)
{
    var add_notice_button = document.getElementById('add_notice');
    var edit_notice_button = document.getElementById('edit_notice');
    var delete_notice_button = document.getElementById('delete_notice');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    add_notice_button.style.display = display_style;
    edit_notice_button.style.display = display_style;
    delete_notice_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
   var show_sep = false;
   if (content_id == 'shipping_content') {
      enable_shipping_buttons(true);   show_sep = true;
   }
   else enable_shipping_buttons(false);
   if (content_id == 'history_content') {
      enable_history_buttons(true);   show_sep = true;
   }
   else enable_history_buttons(false);
   if (content_id == 'notices_content') {
      enable_notice_buttons(true);   show_sep = true;
   }
   else enable_notice_buttons(false);
   var shipping_buttons_row = document.getElementById('shipping_buttons_row');
   if (shipping_buttons_row) {
      if (show_sep) shipping_buttons_row.style.display = '';
      else shipping_buttons_row.style.display = 'none';
   }
   if (enable_reminders) change_reminders_tab(tab,content_id);
   if (enable_rmas) change_rmas_tab(tab,content_id);
   if (typeof(cards_grid) != 'undefined') change_cards_tab(tab,content_id);
   if (typeof(custom_change_customer_tab) != 'undefined')
      custom_change_customer_tab(tab,content_id);
   tab_click(tab,content_id);
   if (content_id == 'shipping_content') shipping_grid.resize_column_headers();
   top.grow_current_dialog();
}

function convert_data(col,row,text)
{
   if (main_grid_flag) {
      var status_column = 1;
      var date_column = 3;
      if (enable_wholesale) date_column++;
   }
   else var status_column = 6;
   if (col == status_column) {
      var status = parse_int(text);
      if (typeof(customer_status_values[status]) == 'undefined') return text;
      return customer_status_values[status];
   }
   else if (main_grid_flag && (col == date_column)) {
      if (text == '') return text;
      var create_date = new Date(parse_int(text) * 1000);
      var date_string = (create_date.getMonth() + 1) + '/' + create_date.getDate() + '/' +
                        create_date.getFullYear();
      return date_string;
   }
   return text;
}

function filter_customers()
{
    filter_where = '';
    var account_list = document.getElementById('account');
    if (! account_list) var account = null;
    else if (account_list.selectedIndex == -1) var account = null;
    else var account = account_list.options[account_list.selectedIndex].value;
    if (account) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(c.account_id=' + account + ')';
    }
    var status_list = document.getElementById('status');
    if (status_list.selectedIndex == -1) var status = null;
    else var status = status_list.options[status_list.selectedIndex].value;
    if (status) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(c.status=' + status + ')';
    }
    if (accounts_where) {
       if (where) filter_where += ' and ';
       filter_where += accounts_where;
    }
    var query = document.SearchForm.query.value;
    if (query) search_customers();
    else {
       top.display_status('Search','Searching Customers...',350,100,null);
       window.setTimeout(function() {
          customers_grid.set_where(filter_where);
          customers_grid.table.reset_data(false);
          customers_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

function search_customers()
{
   var query = document.SearchForm.query.value;
   var status_list = document.getElementById('customerStatusSelector');
   var status = null;
   if (status_list) {
      if (status_list.selectedIndex >= 0) {
         status = status_list.options[status_list.selectedIndex].value;
      }
   }
   if ((query == '') && (status == null)) {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   top.display_status('Search','Searching Customers...',350,100,null);
   window.setTimeout(function() {
      if (typeof(customer_search_where) != 'undefined')
         var where = customer_search_where.replace(/\$query\$/g,query);
      else if (typeof(encrypted_fields) != 'undefined') {
         if (array_index(encrypted_fields,'email') != -1)
            var where = '(%DECRYPT%(c.email)';
         else var where = '(c.email';
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'fname') != -1)
            where += "%DECRYPT%(c.fname)";
         else where += "c.fname";
         where += " like '%" + query +"%' or ";
         if (array_index(encrypted_fields,'lname') != -1)
            where += "%DECRYPT%(c.lname)";
         else where += "c.lname";
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'company') != -1)
            where += "%DECRYPT%(c.company)";
         else where += "c.company";
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'phone') != -1)
            where += "%DECRYPT%(b.phone)";
         else where += "b.phone";
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'fax') != -1)
            where += "%DECRYPT%(b.fax)";
         else where += "b.fax";
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'mobile') != -1)
            where += "%DECRYPT%(b.mobile)";
         else where += "b.mobile";
         where += " like '%" + query + "%' or c.id like '%" + query + "%')";
      }
      else var where = '(c.email like "%' + query + '%" or c.fname like "%' +
                  query + '%" or c.lname like "%' + query +
                  '%" or c.company like "%' + query + '%" or b.phone like "%' +
                  query + '%" or b.fax like "%' + query +
                  '%" or b.mobile like "%' + query + '%" or c.id like "%' +
                  query + '%" or concat(c.fname," ",c.lname) like "%' +
                  query + '%")';
//      if (! main_grid_flag) where += ' and (s.default_flag=1)';
      if (status) {
         if (where) where += ' and ';
         where += '(c.status=' + status + ')';
      }

      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      if (typeof(customer_where) != 'undefined')
         where = customer_where + ' and ' + where;
      customers_grid.set_where(where);
      customers_grid.table.reset_data(false);
      customers_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status("Search","Loading All Customers...",350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      var status_list = document.getElementById('customerStatusSelector');
      if (status_list) {
         status_list.selectedIndex = 0;
      }
      if (typeof(customer_where) != "undefined") {
         if (! main_grid_flag)
            var where = customer_where + " and (s.default_flag=1)";
         else var where = customer_where;
      }
      else if (! main_grid_flag) var where = 's.default_flag=1';
      else var where = '';
      if (filter_where) {
         if (where) where += '(' + where + ') and ';
         where += filter_where;
      }
      customers_grid.set_where(where);
      customers_grid.table.reset_data(false);
      customers_grid.grid.refresh();
      top.remove_status();
   },0);
}

function select_customer()
{
   if (customers_grid.table._num_rows < 1) {
      alert('There are no customers to select');   return;
   }
   var grid = customers_grid.grid;
   var grid_row = grid.getCurrentRow();
   var customer_data = {
      id: grid.getCellText(0,grid_row),
      status: grid.getCellText(6,grid_row),
      email: grid.getCellText(1,grid_row),
      fname: grid.getCellText(2,grid_row),
      mname: grid.getCellText(8,grid_row),
      lname: grid.getCellText(3,grid_row),
      company: grid.getCellText(7,grid_row),
      account_id: grid.getCellText(26,grid_row),
      credit_balance: grid.getCellText(27,grid_row),
      bill_address1: grid.getCellText(9,grid_row),
      bill_address2: grid.getCellText(10,grid_row),
      bill_city: grid.getCellText(4,grid_row),
      bill_state: grid.getCellText(5,grid_row),
      bill_zipcode: grid.getCellText(11,grid_row),
      bill_country: grid.getCellText(12,grid_row),
      bill_phone: grid.getCellText(13,grid_row),
      bill_fax: grid.getCellText(14,grid_row),
      bill_mobile: grid.getCellText(15,grid_row),
      ship_profilename: grid.getCellText(16,grid_row),
      ship_shipto: grid.getCellText(17,grid_row),
      ship_company: grid.getCellText(18,grid_row),
      ship_address1: grid.getCellText(19,grid_row),
      ship_address2: grid.getCellText(20,grid_row),
      ship_city: grid.getCellText(21,grid_row),
      ship_state: grid.getCellText(22,grid_row),
      ship_zipcode: grid.getCellText(23,grid_row),
      ship_country: grid.getCellText(24,grid_row),
      ship_address_type: grid.getCellText(25,grid_row)
   };

   var iframe = top.get_dialog_frame(select_frame).contentWindow;
   if (typeof(change_function) != 'undefined')
      iframe.window[change_function](customer_data);
   else iframe.change_customer(customer_data);
   top.close_current_dialog();
}

function create_shipping_grid(parent)
{
   if (top.skin) var grid_width = -100;
   else var grid_width = 500;
   shipping_grid = new Grid('shipping_information',grid_width,250);
   shipping_grid.set_columns(['Id','Profile','Default','Ship To','Address',
                              'City','State','Zip','Country','Country ID',
                              'Company','Address #2','Address Type']);
   shipping_grid.set_column_widths([0,50,45,95,120,60,35,40,80,0,0,0,0]);
   var query = 'select s.id,s.profilename,s.default_flag,s.shipto,s.address1,' +
      's.city,s.state,s.zipcode,(select country from countries where id=' +
      's.country) as country,s.country as country_id,s.company,s.address2,' +
      's.address_type from shipping_information s';
   shipping_grid.set_query(query);
   shipping_grid.table.set_decrypt_table('shipping_information');
   shipping_grid.set_where('parent=' + parent);
   shipping_grid.set_order_by('profilename');
   shipping_grid.table.set_convert_cell_data(convert_shipping_data);
   shipping_grid.load(true);
   shipping_grid.set_double_click_function(edit_shipping);
   shipping_grid.display();
}

function add_shipping()
{
   if (document.EditCustomer) var customer_id = document.EditCustomer.id.value;
   else var customer_id = document.AddCustomer.id.value;
   top.create_dialog('add_shipping',null,null,650,350,false,
                     '../cartengine/customers.php?cmd=addshipping&parent=' +
                     customer_id,null);
}

function get_customer_frame_name()
{
   for (var loop = 0;  loop < top.num_dialogs;  loop++)
      if (top.dialog_names[loop] == 'edit_customer') return 'edit_customer';
   return 'add_customer';
}

function process_add_shipping()
{
   var country = get_selected_list_value('country');
   if (! validate_form_field(document.AddShipping.profilename,"Profile Name")) return;
   if (! validate_form_field(document.AddShipping.address1,"Address Line 1")) return;
   if (country == 1) {
      if (! validate_form_field(document.AddShipping.city,"City")) return;
      if (! validate_form_field(document.AddShipping.state,"State")) return;
      if (! validate_form_field(document.AddShipping.zipcode,"Zip Code")) return;
   }
   else if (country == 29) {
      if (! validate_form_field(document.AddShipping.city,"Parish")) return;
      if (! validate_form_field(document.AddShipping.zipcode,"Postal Code")) return;
   }
   else if (country == 43) {
      if (! validate_form_field(document.AddShipping.city,"City")) return;
      if (! validate_form_field(document.AddShipping.canada_province,"Province")) return;
      if (! validate_form_field(document.AddShipping.zipcode,"Postal Code")) return;
   }
   else {
      if (! validate_form_field(document.AddShipping.city,"City")) return;
      if (! validate_form_field(document.AddShipping.province,"Province")) return;
      if (! validate_form_field(document.AddShipping.zipcode,"Postal Code")) return;
   }
   if (! validate_form_field(document.AddShipping.country,"Country")) return;

   top.enable_current_dialog_progress(true);
   submit_form_data("customers.php","cmd=processaddshipping",document.AddShipping,
                    finish_add_shipping);
}

function finish_add_shipping(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(get_customer_frame_name()).contentWindow;
      iframe.shipping_grid.table.reset_data(true);
      iframe.shipping_grid.grid.refresh();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function edit_shipping()
{
   if (shipping_grid.table._num_rows < 1) {
      alert('There are no shipping profiles to edit');   return;
   }
   var grid_row = shipping_grid.grid.getCurrentRow();
   var id = shipping_grid.grid.getCellText(0,grid_row);
   top.create_dialog('edit_shipping',null,null,650,350,false,
                     '../cartengine/customers.php?cmd=editshipping&id=' + id,null);
}

function update_shipping()
{
   var country = get_selected_list_value('country');
   if (! validate_form_field(document.EditShipping.profilename,"Profile Name")) return;
   if (! validate_form_field(document.EditShipping.address1,"Address Line 1")) return;
   if (country == 1) {
      if (! validate_form_field(document.EditShipping.city,"City")) return;
      if (! validate_form_field(document.EditShipping.state,"State")) return;
      if (! validate_form_field(document.EditShipping.zipcode,"Zip Code")) return;
   }
   else if (country == 29) {
      if (! validate_form_field(document.EditShipping.city,"Parish")) return;
      if (! validate_form_field(document.EditShipping.zipcode,"Postal Code")) return;
   }
   else if (country == 43) {
      if (! validate_form_field(document.EditShipping.city,"City")) return;
      if (! validate_form_field(document.EditShipping.canada_province,"Province")) return;
      if (! validate_form_field(document.EditShipping.zipcode,"Postal Code")) return;
   }
   else {
      if (! validate_form_field(document.EditShipping.city,"City")) return;
      if (! validate_form_field(document.EditShipping.province,"Province")) return;
      if (! validate_form_field(document.EditShipping.zipcode,"Postal Code")) return;
   }
   if (! validate_form_field(document.EditShipping.country,"Country")) return;

   top.enable_current_dialog_progress(true);
   submit_form_data("customers.php","cmd=updateshipping",document.EditShipping,
                    finish_update_shipping);
}

function finish_update_shipping(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(get_customer_frame_name()).contentWindow;
      iframe.shipping_grid.table.reset_data(true);
      iframe.shipping_grid.grid.refresh();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function delete_shipping()
{
   if (shipping_grid.table._num_rows < 1) {
      alert('There are no shipping profiles to delete');   return;
   }
   var grid_row = shipping_grid.grid.getCurrentRow();
   var id = shipping_grid.grid.getCellText(0,grid_row);
   var profilename = shipping_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete the '+profilename +
                          ' shipping address?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   call_ajax("customers.php","cmd=deleteshipping&id=" + id,true,
             finish_delete_shipping);
}

function finish_delete_shipping(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(get_customer_frame_name()).contentWindow;
      iframe.shipping_grid.table.reset_data(true);
      iframe.shipping_grid.grid.refresh();
   }
   else ajax_request.display_error();
}

var default_values = ["No","Yes"];

function convert_shipping_data(col,row,text)
{
   if (col == 2) return default_values[parse_int(text)];
   return text;
}

var history_grid;

function create_history_grid(parent)
{
   if (top.skin) var grid_width = -100;
   else var grid_width = 500;
   history_grid = new Grid("orders",grid_width,200);
   history_grid.table.set_field_names([]);
   history_grid.set_columns(["Id","Date","Order Number","Total","Status"]);
   history_grid.set_column_widths([0,150,130,70,120]);
   var query = "select id,order_date,order_number,total,status from orders";
   history_grid.set_query(query);
   history_grid.set_where("customer_id=" + parent);
   history_grid.table.set_order_by("id desc");
   history_grid.table.set_convert_cell_data(convert_history_data);
   history_grid.load(true);
   history_grid.set_double_click_function(view_order);
   history_grid.display();
}

function view_order()
{
   if (history_grid.table._num_rows < 1) {
      alert('There are no orders to view');   return;
   }
   var grid_row = history_grid.grid.getCurrentRow();
   var id = history_grid.grid.getCellText(0,grid_row);
   top.create_dialog('view_order',null,null,750,460,false,
                     '../cartengine/orders.php?cmd=vieworder&id=' + id,null);
}

function convert_history_data(col,row,text)
{
   if (col == 1) {
      if (text == '') return text;
      var order_date = new Date(parse_int(text) * 1000);
      return order_date.format('mmmm d, yyyy h:MM tt');
   }
   if (col == 3) return "$" + format_amount(text);
   if (col == 4) return order_status_values[parse_int(text)];
   return text;
}

function select_country(country_list)
{
    var country = 0;
    if (country_list.selectedIndex != -1)
       country = country_list.options[country_list.selectedIndex].value;
    var city_prompt = document.getElementById('city_prompt');
    var state_row = document.getElementById('state_row');
    var province_row = document.getElementById('province_row');
    var canada_province_row = document.getElementById('canada_province_row');
    var zip_cell = document.getElementById('zip_cell');
    if (country == 1) {
       state_row.style.display = '';   province_row.style.display = 'none';
       canada_province_row.style.display = 'none';
       city_prompt.innerHTML = 'City:';   zip_cell.innerHTML = 'Zip Code:';
    }
    else if (country == 29) {
       state_row.style.display = 'none';   province_row.style.display = 'none';
       canada_province_row.style.display = 'none';
       city_prompt.innerHTML = 'Parish:';   zip_cell.innerHTML = 'Postal Code:';
    }
    else if (country == 43) {
       state_row.style.display = 'none';   province_row.style.display = 'none';
       canada_province_row.style.display = '';
       city_prompt.innerHTML = 'City:';   zip_cell.innerHTML = 'Postal Code:';
    }
    else {
       state_row.style.display = 'none';   province_row.style.display = '';
       canada_province_row.style.display = 'none';
       city_prompt.innerHTML = 'City:';   zip_cell.innerHTML = 'Postal Code:';
    }
}

function create_activity_grid(parent)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    activity_grid = new Grid('customer_activity',grid_width,350);
    activity_grid.table.set_field_names([]);
    activity_grid.set_columns(['Id','Date','Activity']);
    activity_grid.set_column_widths([0,140,1000]);
    var query = 'select id,activity_date,activity from customer_activity';
    activity_grid.set_query(query);
    activity_grid.set_where('parent=' + parent);
    activity_grid.table.set_order_by('activity_date desc,id desc');
    activity_grid.table.set_convert_cell_data(convert_activity_data);
    activity_grid.set_id('activity_grid');
    activity_grid.load(false);
    activity_grid.display();
}

function convert_activity_data(col,row,text)
{
    if (col == 1) {
       if (text == '') return text;
       var activity_date = new Date(parse_int(text) * 1000);
       return activity_date.format('mm/dd/yyyy hh:MM:ss tt');
    }
    return text;
}

function create_category_views_grids(parent,ip_address)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    var views_grid = new Grid('category_views',grid_width,200);
    views_grid.table.set_field_names([]);
    views_grid.set_columns(['Category','Viewed']);
    views_grid.set_column_widths([500,150]);
    var query = 'select c.name,v.view_date from category_views v join ' +
                'categories c on c.id=v.parent';
    views_grid.set_query(query);
    views_grid.set_info_query('select count(v.id) from category_views v');
    var where = '(v.customer_id=' + parent + ')';
    if (ip_address) where += ' or (v.ip_address="' + ip_address + '")';
    views_grid.set_where(where);
    views_grid.table.set_order_by('v.view_date desc');
    views_grid.table.set_convert_cell_data(convert_view_data);
    views_grid.set_id('category_views_grid');
    views_grid.load(false);
    views_grid.display();
}

function create_product_views_grids(parent,ip_address)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    var views_grid = new Grid('product_views',grid_width,200);
    views_grid.table.set_field_names([]);
    views_grid.set_columns(['Product','Viewed']);
    views_grid.set_column_widths([500,150]);
    var query = 'select p.name,v.view_date from product_views v join ' +
                'products p on p.id=v.parent';
    views_grid.set_query(query);
    views_grid.set_info_query('select count(v.id) from product_views v');
    var where = '(v.customer_id=' + parent + ')';
    if (ip_address) where += ' or (v.ip_address="' + ip_address + '")';
    views_grid.set_where(where);
    views_grid.table.set_order_by('v.view_date desc');
    views_grid.table.set_convert_cell_data(convert_view_data);
    views_grid.set_id('product_views_grid');
    views_grid.load(false);
    views_grid.display();
}

function convert_view_data(col,row,text)
{
    if (col == 1) {
       if (text == '') return text;
       var view_date = new Date(parse_int(text) * 1000);
       return view_date.format('mm/dd/yyyy hh:MM:ss tt');
    }
    return text;
}

function create_customer_notices_grid(parent)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    customer_notices_grid = new Grid('customer_notices',grid_width,350);
    customer_notices_grid.table.set_field_names([]);
    customer_notices_grid.set_columns(['Id','Product','Followup','Created','Notified']);
    customer_notices_grid.set_column_widths([0,310,60,70,70]);
    var query = 'select id,(select name from products where id=product_id) as ' +
                'product,followup,create_date,notify_date from customer_notices';
    customer_notices_grid.set_query(query);
    customer_notices_grid.set_where('parent=' + parent);
    customer_notices_grid.table.set_order_by('id desc');
    customer_notices_grid.table.set_convert_cell_data(convert_notice_data);
    customer_notices_grid.set_id('customer_notices_grid');
    customer_notices_grid.load(true);
    customer_notices_grid.set_double_click_function(edit_notice);
    customer_notices_grid.display();
}

function select_notice_product()
{
    if (document.AddNotice) var frame = 'add_notice';
    else var frame = 'edit_notice';
    top.create_dialog('select_product',null,null,830,400,false,
                      '../cartengine/products.php?cmd=selectproduct&frame=' +
                      frame + '&changefunction=change_notice_product',null);
}

function change_notice_product(product_data)
{
    if (document.AddNotice) var form = document.AddNotice;
    else var form = document.EditNotice;
    form.product_id.value = product_data.id;
    var product_cell = document.getElementById('product_cell');
    product_cell.innerHTML = product_data.name;
    top.grow_current_dialog();
}

function add_notice()
{
    if (document.EditCustomer) var customer_id = document.EditCustomer.id.value;
    else var customer_id = document.AddCustomer.id.value;
    top.create_dialog('add_notice',null,null,700,140,false,
                      '../cartengine/customers.php?cmd=addnotice&parent=' +
                      customer_id,null);
}

function process_add_notice()
{
    if (! validate_form_field(document.AddNotice.product_id,'Product')) return;
    top.enable_current_dialog_progress(true);
    submit_form_data('customers.php','cmd=processaddnotice',document.AddNotice,
                     finish_add_notice);
}

function finish_add_notice(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('edit_customer').contentWindow;
       iframe.customer_notices_grid.table.reset_data(true);
       iframe.customer_notices_grid.grid.refresh();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_notice()
{
    if (customer_notices_grid.table._num_rows < 1) {
       alert('There are no notices to edit');   return;
    }
    var grid_row = customer_notices_grid.grid.getCurrentRow();
    var id = customer_notices_grid.grid.getCellText(0,grid_row);
    top.create_dialog('edit_notice',null,null,700,140,false,
                      '../cartengine/customers.php?cmd=editnotice&id=' + id,null);
}

function update_notice()
{
    if (! validate_form_field(document.EditNotice.product_id,'Product')) return;
    top.enable_current_dialog_progress(true);
    submit_form_data('customers.php','cmd=updatenotice',document.EditNotice,
                     finish_update_notice);
}

function finish_update_notice(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('edit_customer').contentWindow;
       iframe.customer_notices_grid.table.reset_data(true);
       iframe.customer_notices_grid.grid.refresh();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_notice()
{
    if (customer_notices_grid.table._num_rows < 1) {
       alert('There are no notices to delete');   return;
    }
    var grid_row = customer_notices_grid.grid.getCurrentRow();
    var id = customer_notices_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete the selected ' +
                           'notice?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax('customers.php','cmd=deletenotice&id=' + id,true,
              finish_delete_notice);
}

function finish_delete_notice(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       customer_notices_grid.table.reset_data(true);
       customer_notices_grid.grid.refresh();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function convert_notice_data(col,row,text)
{
    if (col == 2) {
       if (text == 0) return 'E-Mail';
       else return 'Phone';
    }
    if ((col == 3) || (col == 4)) {
       if (text == '') return text;
       var notice_date = new Date(parse_int(text) * 1000);
       return notice_date.format('mm/dd/yyyy');
    }
    return text;
}

