/*
             Inroads Shopping Cart - Orders Tab JavaScript Functions

              Written 2008-2019 by Randall Severy and James Mussman
                      Copyright 2008-2019 Inroads, LLC
*/

var ORDER_TYPE = 0;
var QUOTE_TYPE = 1;
var INVOICE_TYPE = 2;
var SALESORDER_TYPE = 3;

var order_type = ORDER_TYPE;
var orders_grid = null;
var terms_grid = null;
var main_grid_flag;
var features = null;
var notify_flags = 0;
var shipped_option = 1;
var backorder_option = 2;
var cancelled_option = 3;
var account_info = {};
var account_discount = 0;
var account_products = [];
var no_account_products = [];
var filter_where = '';
var ajax_request_pending = false;
var account_info_request = null;
var customer_profiles_request = null;
var customer_profile_request = null;
var customer_accounts_request = null;
var customer_saved_cards_request = null;
var customer_orders_request = null;
var load_product_inventory_request = null;
var lookup_fees_request = null;
var partial_shipment_request = null;
var load_terms_request = null;
var order_number_is_id = false;
var enable_sales_reps = false;
var update_and_print_template = null;
var update_and_print_label = null;
var loading_fees = false;
var order_status_column = 2;
var payment_status_column = -1;
var order_date_column = 10;
var total_column = -1;
var balance_due_column = -1;
var num_items_column = -1;
var status_width = 100;
var order_number_width;
var use_packing_slip_template = false;
var cancel_copy_order = false;
var multiple_customer_accounts = false;
var accounts = null;
var account_product_prices = false;
var admin_use_saved_cards = false;
var products_script_name;
var product_dialog_height;
var website_settings = 0;
var edit_order_item_flags = 0;
var vertical_attribute_prompt = false;
var enable_reorders = false;
var adjustments_hidden = true;
var file_url = '';
var show_cost_column = true;
var adding_shipment = false;
var enable_salesorders = false;
var item_fields = [];
var tax_shipping = false;
var selected_shipping_info = null;

var MAINTAIN_INVENTORY = 1;
var USE_PART_NUMBERS = 4;
var REGULAR_PRICE_PRODUCT = 32;
var REGULAR_PRICE_INVENTORY = 64;
var REGULAR_PRICE_BREAKS = 128;
var LIST_PRICE_PRODUCT = 256;
var LIST_PRICE_INVENTORY = 512;
var SALE_PRICE_PRODUCT = 1024;
var SALE_PRICE_INVENTORY = 2048;
var HIDE_OUT_OF_STOCK = 32768;
var PRODUCT_COST_PRODUCT = 524288;
var PRODUCT_COST_INVENTORY = 1048576;

var NOTIFY_NEW_ORDER_ADMIN = 1;
var NOTIFY_NEW_ORDER_CUST = 2;
var NOTIFY_BACK_ORDER = 4;
var NOTIFY_SHIPPED = 8;
var NOTIFY_ORDER_DECLINED = 16;

var WEBSITE_SHARED_CART = 1;

var HIDE_NAME_IN_ORDERS = 256;

var EDIT_ORDER_ITEM_NAME = 1;
var EDIT_ORDER_ITEM_PART_NUMBER = 2;
var EDIT_ORDER_ITEM_COST = 4;
var EDIT_ORDER_ITEM_PRICE = 8;

var payment_status_values = ['None','Authorized','Voided','Captured',
                             'Refunded'];

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(orders_grid,-1,new_height - get_grid_offset(orders_grid));
    else resize_grid(orders_grid,new_width,new_height)
}

function resize_dialog(new_width,new_height)
{
    if (! terms_grid) return;
    if (top.skin)
       resize_grid(terms_grid,-1,new_height - get_grid_offset(terms_grid));
    else resize_grid(terms_grid,new_width,new_height)
}

function activate()
{
    window.setTimeout(function() { orders_grid.auto_refresh(true); },0);
}

function get_website_where()
{
    if (typeof(top.website) == 'undefined') return '';
    if (top.website == 0) return '';
    if (website_settings & WEBSITE_SHARED_CART) return '';
    return '(website=' + top.website + ')';
}

function get_orders_table()
{
    switch (order_type) {
       case ORDER_TYPE: return 'orders';
       case QUOTE_TYPE: return 'quotes';
       case INVOICE_TYPE: return 'invoices';
       case SALESORDER_TYPE: return 'sales_orders';
    }
}

function get_order_label()
{
    switch (order_type) {
       case ORDER_TYPE: return 'order';
       case QUOTE_TYPE: return 'quote';
       case INVOICE_TYPE: return 'invoice';
       case SALESORDER_TYPE: return 'sales order';
    }
}

function load_grid(main_screen_flag)
{
   var email_width = 250 - order_number_width;
   main_grid_flag = main_screen_flag;
   var grid_size = get_default_grid_size();
   orders_grid = new Grid(get_orders_table(),grid_size.width,grid_size.height);
   orders_grid.set_server_sorting(true);
   if (main_screen_flag) {
      switch (order_type) {
         case ORDER_TYPE:
            var grid_id = 'orders_grid';   order_status_column = 2;
            order_date_column = 10;   payment_status_column = 3;
            total_column = 8;   balance_due_column = 9;   break;
         case QUOTE_TYPE:
            var grid_id = 'quotes_grid';   order_status_column = 1;
            order_date_column = 7;   total_column = 6;   num_items_column = 11;
            break;
         case INVOICE_TYPE:
            var grid_id = 'invoices_grid';   order_status_column = 1;
            order_date_column = 7;   total_column = 6;   break;
         case SALESORDER_TYPE:
            var grid_id = 'salesorders_grid';   order_status_column = 1;
            order_date_column = 7;   total_column = 6;   num_items_column = 11;
            break;
      }
      if ((typeof(order_field_names) != 'undefined') &&
          (order_type == ORDER_TYPE)) {
         var field_names = order_field_names;
         order_status_column = field_names.indexOf('status');
         order_date_column = field_names.indexOf('order_date');
         payment_status_column = field_names.indexOf('payment_status');
         total_column = field_names.indexOf('total_info');
         balance_due_column = field_names.indexOf('balance_info');
      }
      else {
         switch (order_type) {
            case ORDER_TYPE:
               var field_names = ['id','order_number','status',
                  'payment_status','email','fname','lname','state','total_info',
                  'balance_info','order_date','phone','fax','mobile'];
               if (enable_sales_reps) field_names.push('sales_rep');
               break;
            case QUOTE_TYPE:
               var field_names = ['id','status','email','fname','lname',
                  'state','total_info','quote_date','phone','fax','mobile',
                  'num_items'];
               break;
            case INVOICE_TYPE:
               var field_names = ['id','status','email','fname','lname',
                  'state','total_info','invoice_date','phone','fax','mobile'];
               break;
            case SALESORDER_TYPE:
               var field_names = ['id','status','email','fname','lname',
                  'state','total_info','order_date','phone','fax','mobile',
                  'num_items'];
               break;
         }
      }
      orders_grid.set_field_names(field_names);
      if ((typeof(order_columns) != 'undefined') && (order_type == ORDER_TYPE))
         var columns = order_columns;
      else {
         switch (order_type) {
            case ORDER_TYPE:
               var columns = ['Id','Number','Status','Payment Status',
                  'Email','First Name','Last Name','State','Total',
                  'Balance Due','Order Date','Phone','Fax','Mobile'];
               if (enable_sales_reps) columns.push('Sales Rep');
               break;
            case QUOTE_TYPE:
               var columns = ['Number','Status','Email','First Name',
                  'Last Name','State','Total','Quote Date','Phone','Fax',
                  'Mobile','# Items'];
               break;
            case INVOICE_TYPE:
               var columns = ['Number','Status','Email','First Name',
                  'Last Name','State','Total','Invoice Date','Phone','Fax',
                  'Mobile'];
               break;
            case SALESORDER_TYPE:
               var columns = ['Number','Status','Email','First Name',
                  'Last Name','State','Total','Order Date','Phone','Fax',
                  'Mobile','# Items'];
               break;
         }
      }
      orders_grid.set_columns(columns);
      if ((typeof(order_column_widths) != 'undefined') &&
          (order_type == ORDER_TYPE))
         var column_widths = order_column_widths;
      else {
         switch (order_type) {
            case ORDER_TYPE:
               var column_widths = [0,order_number_width,status_width,90,
                  email_width,100,100,40,70,75,155,0,0,0];
               if (enable_sales_reps) column_widths.push(100);
               break;
            case QUOTE_TYPE:
               var column_widths = [75,status_width,email_width,100,100,
                  40,70,155,0,0,0,0];
               break;
            case INVOICE_TYPE:
               var column_widths = [75,status_width,email_width,100,100,
                  40,70,155,0,0,0];
               break;
            case SALESORDER_TYPE:
               var column_widths = [75,status_width,email_width,100,100,
                  40,70,155,0,0,0,0];
               break;
         }
      }
      orders_grid.set_column_widths(column_widths);
      if ((typeof(order_query) != 'undefined') && (order_type == ORDER_TYPE))
         var query = order_query;
      else {
         switch (order_type) {
            case ORDER_TYPE:
               var query = 'select o.id,IF(reorder_id,IF(flags&1,concat(' +
                  'order_number," (A)"),concat(order_number," (R)")),' +
                  'order_number) as order_number,status,ifnull((select ' +
                  'p.payment_status from order_payments p where ' +
                  'p.parent=o.id order by p.payment_date desc limit 1),0) as ' +
                  'payment_status,email,o.fname,o.lname,' +
                  '(select state from order_shipping where (parent_type=0) && ' +
                  '(parent=o.id) limit 1) as state,concat(currency,"^",total) ' +
                  'as total_info,concat(currency,"^",balance_due) as ' +
                  'balance_info,o.order_date,b.phone,b.fax,b.mobile';
               if (enable_sales_reps)
                  query += ',(select concat(u.firstname," ",u.lastname) ' +
                                 'from users u where u.username=o.sales_rep) as ' +
                                 'sales_rep';
               query += ' from orders o left join order_billing b on ' +
                  '(b.parent_type=0) and (b.parent=o.id)';
               break;
            case QUOTE_TYPE:
               var query = 'select o.id,status,email,o.fname,o.lname,' +
                  '(select state from order_shipping where (parent_type=1) ' +
                  'and (parent=o.id) limit 1) as state,concat(currency,"^",' +
                  'total) as total_info,quote_date,b.phone,b.fax,b.mobile,' +
                  '(select sum(oi.qty) from order_items oi where ' +
                  '(oi.parent_type=1) and (oi.parent=o.id)) as num_items ' +
                  'from quotes o left join order_billing b on ' +
                  '(b.parent_type=1) and (b.parent=o.id)';
               break;
            case INVOICE_TYPE:
               var query = 'select o.id,status,email,o.fname,o.lname,' +
                  '(select state from order_shipping where (parent_type=2) ' +
                  'and (parent=o.id) limit 1) as state,concat(currency,"^",' +
                  'total) as total_info,invoice_date,b.phone,b.fax,b.mobile ' +
                  'from invoices o left join order_billing b on ' +
                  '(b.parent_type=2) and (b.parent=o.id)';
               break;
            case SALESORDER_TYPE:
               var query = 'select o.id,status,email,o.fname,o.lname,' +
                  '(select state from order_shipping where (parent_type=3) ' +
                  'and (parent=o.id) limit 1) as state,concat(currency,"^",' +
                  'total) as total_info,order_date,b.phone,b.fax,b.mobile,' +
                  '(select sum(oi.qty) from order_items oi where ' +
                  '(oi.parent_type=3) and (oi.parent=o.id)) as num_items ' +
                  'from sales_orders o left join order_billing b on ' +
                  '(b.parent_type=3) and (b.parent=o.id)';
               break;
         }
      }
   }
   else {
      var grid_id = 'orders_grid';
      order_status_column = 8;   order_date_column = 7;
      orders_grid.set_columns(['Id','Number','Email','First Name','Last Name',
         'State','Zip Code','Order Date','Status','','','','','','','','','',
         '','','','','','','','','','']);
      orders_grid.set_column_widths([0,order_number_width,email_width,100,100,
         40,70,155,status_width,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]);
      var query = 'select o.id,IF(reorder_id,concat(order_number," (R)"),' +
         'order_number) as order_number,o.email,o.fname,o.lname,s.state as ' +
         'ship_state,s.zipcode as ship_zipcode,o.order_date,o.status,o.mname,' +
         'o.company,b.address1,b.address2,b.city,b.state,b.zipcode,b.country,' +
         'b.phone,b.fax,b.mobile,s.profilename,s.shipto,s.company as ' +
         'ship_company,s.address1 as ship_address1,s.address2 as ship_address2,' +
         's.city as ship_city,s.country as ship_country,s.address_type from ' +
         'orders o left join order_billing b on b.parent=o.id left join ' +
         'order_shipping s on s.parent=o.id';
      var field_names = ['id','order_number','email','fname','lname','state',
         'zipcode','order_date','status','mname','company','address1','address2',
         'city','state','zipcode','country','phone','fax','mobile','profilename',
         'shipto','ship_company','ship_address1','ship_address2','ship_city',
         'ship_country','address_type'];
      orders_grid.set_field_names(field_names);
   }
   orders_grid.table.set_query(query);
   orders_grid.table.set_decrypt_table(get_orders_table() + ',order_shipping');
   if (typeof(order_where) != 'undefined') var where = order_where;
   else var where = get_website_where();
   orders_grid.set_where(where);
   if ((typeof(order_info_query) != 'undefined') && (order_type == ORDER_TYPE))
      var info_query = order_info_query;
   else var info_query = 'select count(o.id) from ' + get_orders_table() +
         ' o left join order_billing b on (b.parent=o.id) and ' +
         '(b.parent_type=' + order_type + ')';
   orders_grid.set_info_query(info_query);
   if ((typeof(order_order_by) != 'undefined') && (order_type == ORDER_TYPE))
      orders_grid.set_order_by(order_order_by);
   else orders_grid.table.set_order_by('o.id desc');
   if ((typeof(convert_order_data) != 'undefined') && (order_type == ORDER_TYPE))
      orders_grid.table.set_convert_cell_data(convert_order_data);
   else orders_grid.table.set_convert_cell_data(convert_data);
   orders_grid.set_id(grid_id);
   if (main_screen_flag) orders_grid.set_auto_refresh(60);
   orders_grid.load(false);
   if (main_screen_flag) {
      orders_grid.grid.clearSelectedModel();
      orders_grid.grid.setSelectionMode('multi-row');
   }
   if (typeof(setup_order_grid) != 'undefined') setup_order_grid();
   if (main_screen_flag)
      orders_grid.set_double_click_function(view_order);
   else orders_grid.set_double_click_function(select_order);
   orders_grid.display();
}

function reload_grid()
{
   orders_grid.table.reset_data(false);
   orders_grid.grid.refresh();
   window.setTimeout(function() { orders_grid.table.restore_position(); },0);
}

function update_refresh()
{
   var refresh = parseInt(get_selected_list_value('refresh'));
   if (orders_grid.auto_refresh_time && orders_grid.refresh_timeout) {
      window.clearTimeout(orders_grid.refresh_timeout);
      orders_grid.refresh_timeout = null;
   }
   orders_grid.set_auto_refresh(refresh);
   if (refresh) {
      orders_grid.refresh_timeout = window.setTimeout(function() {
          orders_grid.auto_refresh();
      },(orders_grid.auto_refresh_time * 1000));
   }
}

function get_order_form()
{
   if (document.AddOrder) return document.AddOrder;
   else return document.EditOrder;
}

function add_order()
{
   orders_grid.table.save_position();
   var url = '../cartengine/orders.php?cmd=addorder&ordertype=' + order_type;
   top.create_dialog('add_order',null,null,995,700,false,url,null);
}

function find_customer()
{
   if (document.AddOrder) var frame = 'add_order';
   else var frame = 'edit_order';
   var url = '../cartengine/customers.php?cmd=selectcustomer&frame=' + frame +
             '&ordertype=' + order_type;
   top.create_dialog('find_customer',null,null,670,400,false,url,null);
}

function new_customer()
{
   top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('customers.php','cmd=createcustomer',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_new_customer,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_new_customer(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var customer_id = -1;
   eval(ajax_request.request.responseText);

   if (document.AddOrder) var frame = 'add_order';
   else var frame = 'edit_order';
   var url = '../cartengine/customers.php?cmd=addcustomer&id='+customer_id +
             '&frame=' + frame + '&ordertype=' + order_type;
   top.create_dialog('add_customer',null,null,850,400,false,url,null);
}

function add_order_type()
{
   if (document.AddOrder) return '&Action=Add';
   else return '&Action=Edit';
}

function copy_shipment_fields(shipped)
{
    var new_shipment_div = document.getElementById('new_shipment_div');
    if (! new_shipment_div) return;
    var add_shipment_div = document.getElementById('add_shipment_div');
    if (! shipped) {
       add_shipment_div.style.display = 'none';
       new_shipment_div.style.display = 'none';
       adding_shipment = false;
    }
    var table = new_shipment_div.firstElementChild;
    var rows = ['shipping_carrier','shipping_method','shipping_cost',
                'shipping_weight'];
    for (var row_index in rows) {
       var row_name = rows[row_index];
       var row = document.getElementById(row_name + '_row');
       if (! row) return;
       if (shipped) {
          var new_row = table.insertRow(table.rows.length);
          new_row.id = 'new_' + row_name + '_row';
          new_row.innerHTML = row.innerHTML;
          row.innerHTML = '';
          row.style.display = 'none';
       }
       else {
          var new_row = document.getElementById('new_' + row_name + '_row');
          row.innerHTML = new_row.innerHTML;
          row.style.display = '';
          new_row.innerHTML = '';
       }
    }
    if (shipped) {
       var tracking_row = table.insertRow(table.rows.length);
       tracking_row.id = 'tracking_row';
       tracking_row.vAlign = 'bottom';
       tracking_row.innerHTML = '<td class="fieldprompt" nowrap>Tracking #s:' +
          '</td><td><input type="text" class="text" name="tracking" ' +
          'id="tracking" size="35" value=""></td>';
    }
    else table.deleteRow(3);
    if (shipped) {
       new_shipment_div.style.display = '';
       add_shipment_div.style.display = '';
       adding_shipment = true;
       var button_div = document.getElementById('add_shipment_button_div');
       if (button_div) button_div.style.display = 'none';
    }
}

function add_shipment()
{
    copy_shipment_fields(true);
    var form = get_order_form();
    form.NewShipment.value = 'Yes';
}

function select_order_status(status_list)
{
    var form = get_order_form();
    if (! status_list) {
       status_list = form.status;
       var initial_status = true;
    }
    else var initial_status = false;
    var order_status = status_list.options[status_list.selectedIndex].value;
    if ((order_status == shipped_option) && (! initial_status)) {
       var fields = document.getElementsByTagName('input');
       for (var loop = 0;  loop < fields.length;  loop++) {
          if ((fields[loop].type == 'checkbox') &&
              (fields[loop].name.substr(0,16) == 'capture_payment_') &&
              (! fields[loop].disabled))
             fields[loop].checked = true;
       }
    }

    var send_emails = form.send_emails;
    var display = 'none';   var checked = false;
    if (document.EditOrder) var old_status = form.OldStatus.value;
    if ((order_status == 0) &&
        (notify_flags & (NOTIFY_NEW_ORDER_ADMIN|NOTIFY_NEW_ORDER_CUST))) {
       display = '';
       if (document.AddOrder) checked = true;
       else if (document.EditOrder.copying &&
                (document.EditOrder.copying.value == 'true')) checked = true;
    }
    else if ((order_status == shipped_option) &&
             (notify_flags & NOTIFY_SHIPPED)) {
       display = '';
       if (document.AddOrder || (old_status != shipped_option)) checked = true;
    }
    else if ((order_status == backorder_option) &&
             (notify_flags & NOTIFY_BACK_ORDER)) {
       display = '';
       if (document.AddOrder || (old_status != backorder_option))
          checked = true;
    }
    else if ((order_status == cancelled_option) &&
             (notify_flags & NOTIFY_ORDER_DECLINED)) {
       display = '';
       if (document.AddOrder || (old_status != cancelled_option))
          checked = true;
    }
    if (send_emails) {
       send_emails.style.display = display;
       send_emails.nextSibling.style.display = display;
       send_emails.checked = checked;
    }
    if ((order_status == shipped_option) && (old_status != shipped_option))
       copy_shipment_fields(true);
    else if (adding_shipment && (order_status != shipped_option))
       copy_shipment_fields(false);
    if (typeof(custom_select_order_status) != 'undefined')
       custom_select_order_status(form,order_status,initial_status);
}

function load_customer_orders(customer_id,order_id)
{
   var fields = 'cmd=loadcustomerorders&id=' + customer_id;
   if (order_id) fields += '&skip=' + order_id;
   call_ajax('../cartengine/orders.php',fields,true,
             finish_load_customer_orders);
   customer_orders_request = current_ajax_request;
}

function display_customer_order(table,order)
{
   var new_row = table.insertRow(table.rows.length);
   new_row.vAlign = 'middle';
   new_row.style.cursor = 'pointer';
   new_row.onmouseover = function() { item_row_mouseover(this); };
   new_row.onmouseout = function() { item_row_mouseout(this); };
   new_row.onclick = function() {
      var dialog_name = 'view_order_'+order.id;
      top.create_dialog(dialog_name,null,null,750,460,false,
                        '../cartengine/orders.php?cmd=vieworder&id=' +
                        order.id,null);
   }
   var new_cell = new_row.insertCell(0);
   new_cell.align = 'center';
   new_cell.className = 'left_product_cell';
   new_cell.innerHTML = order.number;
   new_cell = new_row.insertCell(1);
   new_cell.align = 'center';
   new_cell.innerHTML = order.status;
   new_cell = new_row.insertCell(2);
   new_cell.align = 'right';
   new_cell.className = 'product_total_cell';
   var form = get_order_form();
   var currency = form.currency.value;
   new_cell.innerHTML = format_amount(order.total,currency);
   var order_date = new Date(order.order_date * 1000);
   var date = order_date.getDate();
   if (date < 10) date = '0' + date;
   var month = order_date.getMonth() + 1;
   if (month < 10) month = '0' + month;
   var year = order_date.getFullYear() - 2000;
   if (year < 10) year = '0' + year;
   var date_string = month + "/" + date + "/" + year;
   new_cell = new_row.insertCell(3);
   new_cell.align = 'center';
   new_cell.innerHTML = date_string;
}

function finish_load_customer_orders(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   customer_orders_request = null;
   var status = ajax_request.get_status();
   if (status != 200) {
      lookup_fees();   return;
   }

   var customer_orders = [];
   eval(ajax_request.request.responseText);
   var orders_table = document.getElementById('orders_table');
   var num_rows = orders_table.rows.length;
   for (var loop = num_rows - 1;  loop > 0;  loop--)
      orders_table.deleteRow(loop);
   for (var order_id in customer_orders)
      display_customer_order(orders_table,customer_orders[order_id]);
   var orders_div = document.getElementById('orders_div');
   if (customer_orders.length == 0) orders_div.style.display = 'none';
   else orders_div.style.display = '';
}

function load_customer_accounts(customer_id)
{
   var fields = 'cmd=loadcustomeraccounts&id=' + customer_id;
   call_ajax('../cartengine/orders.php',fields,true,
             finish_load_customer_accounts);
   customer_accounts_request = current_ajax_request;
}

function finish_load_customer_accounts(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    customer_accounts_request = null;
    var status = ajax_request.get_status();
    if (status != 200) return;

    var form = get_order_form();
    var account_div = document.getElementById('account_div');
    if (! ajax_request.request.responseText) accounts = null;
    else accounts = JSON.parse(ajax_request.request.responseText);
    if ((! accounts) || (accounts.length == 0)) {
       form.account.innerHTML = '';
       account_div.style.display = 'none';   return;
    }
    if (form.current_account)
       var selected_account = form.current_account.value;
    else var selected_account = 0;
    var html = '';
    for (var index in accounts) {
       var name = accounts[index].name;
       if (accounts[index].company) name += ' - ' + accounts[index].company;
       html += '<option value="' + accounts[index].id + '"';
       if (selected_account == accounts[index].id) html += ' selected';
       html += '>' +name + '</option>';
    }
    form.account.innerHTML = html;
    account_div.style.display = '';
}

function load_account_info(account_id)
{
   var fields = 'cmd=loadaccountinfo&id=' + account_id;
   call_ajax('../cartengine/orders.php',fields,true,
             finish_load_account_info);
   account_info_request = current_ajax_request;
}

function finish_load_account_info(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   account_info_request = null;
   var status = ajax_request.get_status();
   if (status != 200) return;
   eval(ajax_request.request.responseText);
   if (typeof(custom_process_account) != "undefined") custom_process_account();
}

function load_customer_saved_cards(customer_id)
{
   var fields = 'cmd=loadcustomersavedcards&id=' + customer_id;
   call_ajax('../cartengine/orders.php',fields,true,
             finish_load_customer_saved_cards);
   customer_saved_cards_request = current_ajax_request;
}

function finish_load_customer_saved_cards(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    customer_saved_cards_request = null;
    var status = ajax_request.get_status();
    if (status != 200) return;

    var payment_type_list = document.getElementById('payment_type');
    var list_length = payment_type_list.options.length;
    for (var index=list_length - 1;  index >= 0;  index--) {
       if (payment_type_list.options[index].value.substring(0,6) == 'saved-')
          payment_type_list.remove(index);
    }
    if (! ajax_request.request.responseText) saved_cards = null;
    else saved_cards = JSON.parse(ajax_request.request.responseText);
    if ((! saved_cards) || (saved_cards.length == 0)) return;
    for (var index in saved_cards) {
       var value = 'saved-' + saved_cards[index].id + '|' +
                   saved_cards[index].profile_id;
       var text = 'Saved: ' + saved_cards[index].card_number + ' (Exp ' +
                  saved_cards[index].card_month + '/' +
                  saved_cards[index].card_year + ')';
       var new_option = new Option(text,value);
       payment_type_list.options[payment_type_list.options.length] = new_option;
    }
}

var current_ship_state = '';
var current_ship_zipcode = '';
var current_ship_country = 1;

function change_customer(customer_data)
{
   var form = get_order_form();
   form.customer_id.value = customer_data.id;
   form.email.value = customer_data.email;
   form.fname.value = customer_data.fname;
   form.mname.value = customer_data.mname;
   form.lname.value = customer_data.lname;
   form.company.value = customer_data.company;
   form.account_id.value = customer_data.account_id;
   form.address1.value = customer_data.bill_address1;
   form.address2.value = customer_data.bill_address2;
   form.city.value = customer_data.bill_city;
   set_list_value(form.state,customer_data.bill_state);
   form.zipcode.value = customer_data.bill_zipcode;
   set_list_value(form.country,customer_data.bill_country);
   form.phone.value = customer_data.bill_phone;
   form.fax.value = customer_data.bill_fax;
   form.mobile.value = customer_data.bill_mobile;
   set_list_value(form.ship_profilename,customer_data.ship_profilename);
   form.shipto.value = customer_data.ship_shipto;
   form.ship_company.value = customer_data.ship_company;
   form.ship_address1.value = customer_data.ship_address1;
   form.ship_address2.value = customer_data.ship_address2;
   form.ship_city.value = customer_data.ship_city;
   set_list_value(form.ship_state,customer_data.ship_state);
   form.ship_zipcode.value = customer_data.ship_zipcode;
   set_list_value(form.ship_country,customer_data.ship_country);
   set_radio_button('address_type',customer_data.ship_address_type);
   current_ship_state = customer_data.ship_state;
   current_ship_zipcode = customer_data.ship_zipcode;
   current_ship_country = customer_data.ship_country;
   if (form.card_name)
      form.card_name.value = customer_data.fname + ' ' + customer_data.lname;
   load_shipping_profiles(customer_data.id);
   if (document.AddOrder) load_customer_orders(customer_data.id,null);
   else load_customer_orders(customer_data.id,form.id.value);
   account_info = {};
   account_discount = 0;
   account_products = [];
   no_account_products = [];
   if (multiple_customer_accounts) load_customer_accounts(customer_data.id);
   else if (customer_data.account_id)
      load_account_info(customer_data.account_id);
   if (admin_use_saved_cards) load_customer_saved_cards(customer_data.id);
   if (typeof(custom_change_customer) != 'undefined')
      custom_change_customer(customer_data);
   var name_div = document.getElementById('customer_name_div');
   name_div.innerHTML = '<a href="" onClick="top.get_content_frame().' +
      'edit_customer(' + customer_data.id +'); return false;">' +
      'Customer #' + customer_data.id + '</a>';
   if (customer_data.credit_balance) {
      var orders_div = document.getElementById('orders_div');
      var div = document.createElement('div');
      div.id = 'credit_balance_div';
      div.className = 'add_edit_order_box';
      div.style.marginTop = '20px';
      div.style.width = '320px';
      div.style.height = '30px';
      div.style.textAlign = 'center';
      var html = '<div class="add_edit_order_legend">Store Credit</div>';
      html += '<div class="buttonwrapper" style="width: 211px; display: ' +
              'inline-block; margin-top: 3px;"><a class="ovalbutton" style="' +
              'width: 200px;" href="#" onClick="apply_store_credit(' +
              customer_data.credit_balance + ');"><span>Apply Store Credit (' +
              format_amount(customer_data.credit_balance,'USD') +
              ')</span></a></div></div>';
      div.innerHTML = html;
      orders_div.parentNode.appendChild(div);
   }
   else {
      var div = document.getElementById('credit_balance_div');
      if (div) div.parentNode.removeChild(div);
   }
   lookup_fees();
}

function apply_store_credit(credit_balance)
{
    var form = get_order_form();
    form.discount_name.value = 'Credit Balance';
    form.discount_amount.value = format_amount(credit_balance);
    update_totals();
    if (adjustments_hidden) toggle_adjustments();
}

function copy_customer_info()
{
   var form = get_order_form();
   var name = form.fname.value;
   if ((name != '') && (form.mname.value != '')) name += ' ';
   name += form.mname.value;
   if ((name != '') && (form.lname.value != '')) name += ' ';
   name += form.lname.value;
   form.shipto.value = name;
   form.ship_company.value = form.company.value;
   form.ship_address1.value = form.address1.value;
   form.ship_address2.value = form.address2.value;
   form.ship_city.value = form.city.value;
   var ship_state = get_selected_list_value('state');
   set_list_value(form.ship_state,ship_state);
   form.ship_zipcode.value = form.zipcode.value;
   var country = get_selected_list_value('country');
   set_list_value(form.ship_country,country);
   if (form.company.value != '') set_radio_button('address_type',1);
   else set_radio_button('address_type',2);
   current_ship_state = ship_state;
   current_ship_zipcode = form.zipcode.value;
   current_ship_country = country;
   lookup_fees();
}

function update_card_name()
{
   var form = get_order_form();
   if (form.card_name)
      form.card_name.value = form.fname.value + ' ' + form.lname.value;
}

function find_product()
{
   if (document.AddOrder) var frame = 'add_order';
   else var frame = 'edit_order';
   var url = '../cartengine/products.php?cmd=selectproduct&frame=' + frame;
   var form = get_order_form();
   if (multiple_customer_accounts) {
      if (form.account && (form.account.selectedIndex != -1)) {
         var account_id =
            form.account.options[form.account.selectedIndex].value;
         if ((account_id != 0) && (account_id != ''))
            url += '&account=' + account_id;
      }
   }
   else if (form.account_id.value) url += '&account=' + form.account_id.value;
   top.create_dialog('find_product',null,null,830,400,false,url,null);
}

var num_product_rows = 1;
var num_product_ids = 0;
var product_quantities = [];
var product_prices = [];

function load_product_inventory()
{
   var ids = '';   var id_values = new Array();
   var option_ids = '';   var option_id_values = new Array();
   var form = get_order_form();
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var div = document.getElementById('attributes_' + loop);
      if (div) continue;
      var id_field = form['product_id_' + loop];
      if (id_field && id_field.value) {
         var id = id_field.value;
         if (array_index(id_values,id) == -1) {
            if (ids != '') ids += ',';
            ids += id;   id_values.push(id);
         }
      }
      var attr_field = form['product_attributes_' + loop];
      if (attr_field && attr_field.value) {
         var attributes_string = attr_field.value;
         if (attributes_string.indexOf('|') != -1)
            var product_attributes = attributes_string.split('|');
         else var product_attributes = attributes_string.split('-');
         for (var index in product_attributes) {
            var option_id = product_attributes[index];
            if (isNaN(option_id)) continue;
            if (array_index(option_id_values,option_id) == -1) {
               if (option_ids != '') option_ids += ',';
               option_ids += option_id;   option_id_values.push(option_id);
            }
         }
      }
   }
   if (ids == '') return;
   var fields = 'jscmd=loadinventory&ids=' + ids + '&optionids=' + option_ids;
   call_ajax('../cartengine/products-public.php',fields,true,
             finish_load_product_inventory);
   load_product_inventory_request = current_ajax_request;
}

function get_sorted_attribute_indices(product_id)
{
   var id_index = array_index(attribute_fields,'id');
   var indices = new Array();
   var attributes = attribute_data[product_id];
   var sub_index = 0;
   for (var index in attributes) {
      indices.push({index: index, id: parse_int(attributes[index][id_index]),
                    sub_index: sub_index});
      sub_index++;
   }
   indices.sort(function(a,b){ return a.id-b.id;});
   return indices;
}

function check_inv_attrs(inv_attrs,value1,value2)
{
   for (var index in inv_attrs) {
      if (value1 && value2) {
         if ((inv_attrs[index].indexOf(value1) != -1) &&
             (inv_attrs[index].indexOf(value2) != -1)) return true;
      }
      else if (value1) {
         if (inv_attrs[index].indexOf(value1) != -1) return true;
      }
      else if (value2) {
         if (inv_attrs[index].indexOf(value2) != -1) return true;
      }
   }
   return false;
}

function set_dependencies(row,product_id)
{
   var sub_product_index = array_index(attribute_fields,'sub_product');
   var attributes = attribute_data[product_id];
   var attr_indices = get_sorted_attribute_indices(product_id);
   var dep_attrs = new Array();
   for (var attr_index in attr_indices) {
      var index = attr_indices[attr_index].index;
      var attribute_info = attributes[index];
      var type_index = array_index(attribute_fields,'admin_type');
      var attribute_type = attribute_info[type_index];
      if (attribute_type == '') {
         type_index = array_index(attribute_fields,'type');
         attribute_type = attribute_info[type_index];
      }
      if (attribute_type != '0') continue; // ! select
      if (attribute_info[sub_product_index] != '1') continue;
      var sub_index = attr_indices[attr_index].sub_index;
      var field = document.getElementById('attr_' + sub_index + '_' + row);
      if (field.options.length <= 1) continue;
      dep_attrs[sub_index] = true;
   }
   var inv_attr_index = array_index(inventory_fields,'attributes');
   var inventory = inventory_data[product_id];
   var inv_attrs = new Array();
   for (var index in inventory)
      inv_attrs.push(inventory[index][inv_attr_index].split('-'));
   var first_attr = true;
   for (var index1 in dep_attrs) {
      var field1 = document.getElementById('attr_' + index1 + '_' + row);
      if (field1.selectedIndex == -1) var value1 = '';
      else var value1 = field1.options[field1.selectedIndex].value;
      if (first_attr) {
         for (var index = 0;  index < field1.options.length;  index++) {
            var option_value = field1.options[index].value;
            if (! option_value) continue;
            if (! check_inv_attrs(inv_attrs,option_value,null))
               field1.options[index].style.color = '#CCCCCC';
            else field1.options[index].style.color = '#000000';
            first_attr = false;
         }
      }
      for (var index2 in dep_attrs) {
         if (index2 <= index1) continue;
         var field2 = document.getElementById('attr_' + index2 + '_' + row);
         for (var index = 0;  index < field2.options.length;  index++) {
            var option_value = field2.options[index].value;
            if (! option_value) continue;
            if (! check_inv_attrs(inv_attrs,value1,option_value))
               field2.options[index].style.color = '#CCCCCC';
            else field2.options[index].style.color = '#000000';
         }
      }
   }
}

function get_option_price(option_info,form,row,qty)
{
   var adjust_type_index = array_index(option_fields,'adjust_type');
   if (option_info[adjust_type_index] == '4') {
      var price_break_type_index = array_index(option_fields,
                                               'price_break_type');
      var price_breaks_index = array_index(option_fields,'price_breaks');
      var price_break_type = option_info[price_break_type_index];
      var price_breaks = option_info[price_breaks_index];
      var price_entries = price_breaks.split('|');
      var num_entries = price_entries.length;
      for (var loop = 0;  loop < num_entries;  loop++) {
         if (price_entries[loop] == '') continue;
         var price_details = price_entries[loop].split('-');
         if ((qty >= price_details[0]) && (qty <= price_details[1])) {
            if (price_break_type == '1') var price = price_details[2];
            else var price = price_details * qty;
            break;
         }
      }
   }
   else if (option_info[adjust_type_index] == '1') {
      var adjustment_index = array_index(option_fields,'adjustment');
      var base_price = form['product_price_' + row].value;
      var adjustment = parseFloat(option_info[adjustment_index]);
      price = Math.round((base_price * (adjustment / 100)) * 100) / 100;
   }
   else {
      var adjustment_index = array_index(option_fields,'adjustment');
      price = parseFloat(option_info[adjustment_index]);
   }
   return price;
}

function select_attribute(field,row,product_id,index,attr_index)
{
   var attribute = attribute_data[product_id][index];
   var type_index = array_index(attribute_fields,'admin_type');
   var attribute_type = attribute[type_index];
   if (attribute_type == '') {
      type_index = array_index(attribute_fields,'type');
      attribute_type = attribute[type_index];
   }
   var sub_product_index = array_index(attribute_fields,'sub_product');
   if (attribute[sub_product_index] == '1') var sub_product = true;
   else var sub_product = false;
   if (attribute_type == '0') {
      var selected_option = field.options[field.selectedIndex].value;
      if (typeof(attribute_options[product_id][index][selected_option]) != "undefined")
         var option_info = attribute_options[product_id][index][selected_option];
      else var option_info = null;
   }
   else if (attribute_type == '1') {
      var selected_value = get_selected_radio_button(field.name);
      if (typeof(attribute_options[product_id][index][selected_value]) != "undefined")
         var option_info = attribute_options[product_id][index][selected_value];
      else var option_info = null;
   }
   else option_info = null;
   var unit_price_cell = document.getElementById('product_price_cell_' + row);
   if ((features & REGULAR_PRICE_PRODUCT) &&
       ((features & LIST_PRICE_PRODUCT) || (! (features & LIST_PRICE_INVENTORY))) &&
       ((features & SALE_PRICE_PRODUCT) || (! (features & SALE_PRICE_INVENTORY))))
      var use_product_price = true;
   else var use_product_price = false;
   var form = get_order_form();
   var currency = form.currency.value;
   var qty = document.getElementById('product_qty_' + row).value;
   if (sub_product && ((! use_product_price) || (features & USE_PART_NUMBERS))) {
      var attr_indices = get_sorted_attribute_indices(product_id);
      var attributes = '';
      for (var attr_index in attr_indices) {
         var sub_id = attr_indices[attr_index].index;
         var sub_index = attr_indices[attr_index].sub_index;
         var type_index = array_index(attribute_fields,'admin_type');
         var attribute_type = attribute_data[product_id][sub_id][type_index];
         if (attribute_type == '') {
            type_index = array_index(attribute_fields,'type');
            attribute_type = attribute_data[product_id][sub_id][type_index];
         }
         if ((attribute_type != '6') && (attribute_type != '7')) {
            if (attribute_data[product_id][sub_id][sub_product_index] == '1') {
               var field_name = 'attr_' + sub_index + '_' + row;
               var attr_list = document.getElementById(field_name);
               var sub_option = attr_list.options[attr_list.selectedIndex].value;
               if (attributes != '') attributes += '-';
               attributes += sub_option;
            }
         }
      }
      var inv_attr_index = array_index(inventory_fields,'attributes');
      if (! use_product_price) {
         var inv_price_index = array_index(inventory_fields,'price');
         var inv_sale_price_index = array_index(inventory_fields,'sale_price');
         var unit_price = 0;
      }
      if (features & USE_PART_NUMBERS) {
         var part_number_index = array_index(inventory_fields,'part_number');
         var part_number = '';
      }
      for (var inv_index in inventory_data[product_id]) {
         if (inventory_data[product_id][inv_index][inv_attr_index] == attributes) {
            if (! use_product_price) {
               if (inv_sale_price_index != -1)
                  var sale_price = parse_amount(inventory_data[product_id]
                                                [inv_index][inv_sale_price_index]);
               else var sale_price = 0;
               if (sale_price) unit_price = sale_price;
               else unit_price = parse_amount(inventory_data[product_id]
                                              [inv_index][inv_price_index]);
            }
            if ((features & USE_PART_NUMBERS) && (part_number_index != -1))
               part_number = inventory_data[product_id][inv_index][part_number_index];
            break;
         }
      }
      if (! use_product_price) {
         unit_price_cell.innerHTML = format_amount(unit_price,currency);
         form['product_price_' + row].value = unit_price;
      }
      if (features & USE_PART_NUMBERS) {
         var part_number_cell = document.getElementById('part_number_cell_' + row);
         if (part_number_cell) part_number_cell.innerHTML = part_number;
      }
   }
   else {
      if (attribute_type == '2') {
         if ((typeof(attribute_options) != "undefined") &&
             (typeof(attribute_options[product_id]) != "undefined") &&
             (typeof(attribute_options[product_id][index]) != "undefined"))
            var options = attribute_options[product_id][index];
         else var options = null;
         var price = 0;
         if (options) {
            var field_name = 'attr_' + attr_index + '_' + row;
            var base_price = form['product_price_' + row].value;
            for (var option_index in options) {
               var option_info = options[option_index];
               var checkbox = document.getElementById(field_name + '_' +
                                                      option_info[0]);
               if (checkbox && checkbox.checked)
                  price = get_option_price(option_info,form,row,qty);
            }
         }
      }
      else {
         if (option_info)
            var price = get_option_price(option_info,form,row,qty);
         else var price = 0;
      }
      var price_field_name = 'attr_price_' + row + '_' + attr_index;
      var price_field = form[price_field_name];
      if (price_field) price_field.value = price;
      var span = document.getElementById(price_field_name + '_span');
      if (span) {
         if (price) span.innerHTML = format_amount(price,currency);
         else span.innerHTML = '';
      }
   }
   var flags = parse_int(form['item_flags_'+row].value);
   if (unit_price_cell) {
      total_price = parse_amount(unit_price_cell.innerHTML);
      if (! (flags & 1)) total_price *= qty;
   }
   else total_price = 0;
   var attr_price_index = 0;
   for (var sub_id in attribute_data[product_id]) {
      var price_field_name = 'attr_price_' + row + '_' + attr_price_index;
      if (form[price_field_name] && form[price_field_name].value) {
         var attr_price = parseFloat(form[price_field_name].value);
         if (flags & 1) total_price += attr_price;
         else total_price += (attr_price * qty);
      }
      attr_price_index++;
   }
   var total_cell = document.getElementById('product_total_' + row);
   if (total_cell) total_cell.innerHTML = format_amount(total_price,currency);
   update_totals();
   if (typeof(custom_select_attribute) != "undefined")
      custom_select_attribute(field,row,product_id,index,attr_index);
   set_dependencies(row,product_id);
}

function get_text_width(text)
{
   var div = document.createElement('div');
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var name_cell = document.getElementById('product_name_cell_' + loop);
      if (name_cell) break;
   }
   name_cell.appendChild(div);
   div.style.position = "absolute";
   div.style.left = -1000;
   div.style.top = -1000;
   div.innerHTML = text;
   var width = div.clientWidth;
   name_cell.removeChild(div);
   div = null;
   return width;
}

function find_attribute_options(attr_index)
{
/* this doesn't work for multiple line items, and I'm not sure why it was needed */
   for (var parent in attribute_options) {
      for (var index in attribute_options[parent]) {
         if (index == attr_index) return attribute_options[parent][index];
      }
   }
   return null;
}

function resequence_options(options)
{
   var sequence_index = array_index(option_fields,'sequence');
   options.sort(function(a,b) {
      return a[sequence_index]-b[sequence_index];
   });
   return options;
}

function display_attributes(row,product_id)
{
   var div = document.getElementById('attributes_' + row);
   if (div) return;
   if (typeof(display_custom_attributes) != 'undefined') {
      if (display_custom_attributes(row,product_id)) return;
   }
   var cell = document.getElementById('product_name_cell_' + row);
   var div = top.document.createElement('DIV');
   div.id = 'attributes_' + row;
   div.className = 'attributes_div';
   if ((typeof(attribute_data) == 'undefined') ||
       (typeof(attribute_data[product_id]) == 'undefined')) {
      cell.appendChild(div);   return;
   }
   var form = get_order_form();
   var currency = form.currency.value;
   var attributes_string = form['product_attributes_' + row].value;
   if (attributes_string.indexOf('|') != -1)
      var product_attributes = attributes_string.split('|');
   else var product_attributes = attributes_string.split('-');
   var attribute_names_string = form['product_attribute_names_' + row].value;
   var attribute_names = attribute_names_string.split("|");
   var attribute_prices_string = form['product_attribute_prices_' + row].value;
   var attribute_prices = attribute_prices_string.split("|");
   var attributes = attribute_data[product_id];
   var name_index = array_index(attribute_fields,'name');
   var order_name_index = array_index(attribute_fields,'order_name');
   var display_name_index = array_index(attribute_fields,'display_name');
   var type_index = array_index(attribute_fields,'type');
   var admin_type_index = array_index(attribute_fields,'admin_type');
   var default_index = array_index(attribute_fields,'default_value');
   var width_index = array_index(attribute_fields,'width');
   var height_index = array_index(attribute_fields,'height');
   var flags_index = array_index(attribute_fields,'flags');
   var option_id_index = array_index(option_fields,'id');
   var option_name_index = array_index(option_fields,'name');
   var option_order_name_index = array_index(option_fields,'order_name');
   var adjust_type_index = array_index(option_fields,'adjust_type');
   var max_length = 0;   var longest_text = '';

   for (var index in product_attributes) {
      var option_id = product_attributes[index];
      if (isNaN(option_id)) continue;
      var option_found = false;
      if (typeof(attribute_options[product_id]) != 'undefined') {
         for (var attr_index in attribute_options[product_id]) {
            for (var attr_option_id in attribute_options[product_id][attr_index]) {
               if (attr_option_id == option_id) {
                  option_found = true;   break;
               }
            }
            if (option_found) break;
         }
      }
      if (! option_found) for (var parent in attribute_options) {
         for (var attr_index in attribute_options[parent]) {
            for (var attr_option_id in attribute_options[parent][attr_index]) {
               if (attr_option_id == option_id) {
                  if (parent != product_id)
                     attributes[attr_index] = attribute_data[parent][attr_index];
                  option_found = true;   break;
               }
            }
            if (option_found) break;
         }
         if (option_found) break;
      }
   }

   for (var index in attributes) {
      var attribute_info = attributes[index];
      var attribute_type = attribute_info[admin_type_index];
      if (attribute_type === '')
         attribute_type = attribute_info[type_index];
      if (attribute_type == '0') { //select
         var prompt_index = array_index(attribute_fields,'order_name');
         var prompt = attribute_info[prompt_index];
         if (prompt == '') {
            var prompt_index = array_index(attribute_fields,'display_name');
            var prompt = attribute_info[prompt_index];
            if (prompt == '') {
               prompt_index = array_index(attribute_fields,'name');
               prompt = attribute_info[prompt_index];
            }
         }
         if (prompt.length > max_length) {
            max_length = prompt.length;
            longest_text = prompt;
         }
         var options = attribute_options[product_id][index];
         if (options) for (var option_index in options) {
            var option_info = options[option_index];
            if (option_info[option_order_name_index])
               var option_name = option_info[option_order_name_index];
            else var option_name = option_info[option_name_index];
            if (option_name.length > max_length) {
               max_length = option_name.length;
               longest_text = option_name;
            }
         }
      }
   }
   var select_width = get_text_width(longest_text) + 20;
   if (select_width > 400) select_width = 300;
   var file_fields = new Array();

   var html = '<table cellspacing="0" cellpadding="0" border="0" width="100%">';
   var attr_index = 0;
   for (var index in attributes) {
      var attribute_info = attributes[index];
      var attribute_type = attribute_info[admin_type_index];
      if (attribute_type === '')
         attribute_type = attribute_info[type_index];
      var default_value = attribute_info[default_index];
      if ((attribute_type == '5') && // Custom
          (typeof(display_custom_attribute_row) != 'undefined')) {
            html += display_custom_attribute_row(row,product_id,attribute_info,
                                                 index,attr_index);
            attr_index++;
      }
      else if (attribute_type != '7') {  // ! Skip
         var flags = attribute_info[flags_index];
         if (flags & 1) var prompt = '';
         else {
            var prompt = attribute_info[order_name_index];
            if (! prompt) prompt = attribute_info[display_name_index];
            if (! prompt) prompt = attribute_info[name_index];
         }
         html += '<tr valign="top">';
         if (vertical_attribute_prompt) {
            html += '<td style="position: relative;">';
            if (prompt)
               html += '<span class="fieldprompt">' + prompt + ':</span><br>';
         }
         else {
            html += '<td nowrap class="fieldprompt"><div style="max-width: ' +
                      '225px;">';
            if (prompt) html += prompt + ':';
            html += '</div></td><td nowrap style="position: relative;">';
         }
         var index_field_name = 'attr_index_' + attr_index + '_' + row;
         html += '<input type="hidden" name="' + index_field_name + '" id="' +
                 index_field_name + '" value="' + index + '">';
         var field_name = 'attr_' + attr_index + '_' + row;
         var current_price = -1;
         if (typeof(product_attributes[attr_index]) == "undefined")
            var current_attribute = '';
         else var current_attribute = product_attributes[attr_index];
         var options = attribute_options[product_id][index];
         if (attribute_type == '0') { // Choice List
            html += '<select name="' + field_name + '" id="' + field_name +
                    '" class="select" style="width: ' + select_width +
                    'px;" onChange="select_attribute(this,' + row + ',' +
                    product_id + ',' + index + ',' + attr_index + ');">';
            html += '<option value=""></option>';
            var option_found = false;
            if (options) {
               options = resequence_options(options);
               for (var option_index in options) {
                  var option_info = options[option_index];
                  if ((option_info[adjust_type_index] != '0') &&
                      (option_info[adjust_type_index] != '1') &&
                      (option_info[adjust_type_index] != '4') &&
                      (option_info[adjust_type_index] != '')) continue;
                  html += '<option value="' + option_info[option_id_index] + '"';
                  if (! option_found) {
                     current_attribute = array_index(product_attributes,
                                                     option_info[option_id_index]);
                     if (current_attribute != -1) {
                        html += ' selected';
                        current_price = attribute_prices[current_attribute];
                        option_found = true;
                     }
                  }
                  if (option_info[option_order_name_index])
                     var option_name = option_info[option_order_name_index];
                  else var option_name = option_info[option_name_index];
                  html += '>' + option_name + '</option>';
               }
            }
            if (! option_found) {
               if ((current_attribute == -1) || (current_attribute == ''))
                  current_attribute = product_attributes[attr_index];
               if (typeof(attribute_names[(attr_index * 2) + 1]) != 'undefined')
                  var attr_value = attribute_names[(attr_index * 2) + 1];
               else var attr_value = '';
               if (! attr_value) attr_value = current_attribute;
               if (current_attribute && attr_value) {
                  html += '<option value="' + current_attribute + '" selected>' +
                          attr_value + '</option>';
                  if (typeof(attribute_prices[attr_index]) != 'undefined')
                     current_price = attribute_prices[attr_index];
                  else current_price = 0;
               }
            }
            html += '</select>';
         }
         else if (attribute_type == '1') { // Radio
            if (options) {
               options = resequence_options(options);
               var option_found = false;   var first_option = true;
               html += '<span>';
               for (var option_index in options) {
                  var option_info = options[option_index];
                  if (first_option) first_option = false;
                  else html += '<br>';
                  html += '<input type="radio" class="radio" name="' +
                          field_name + '" id="' + field_name + '_' +
                          option_info[option_id_index] + '" value="' +
                          option_info[option_id_index] + '"';
                  if (! option_found) {
                     current_attribute = array_index(product_attributes,
                                                     option_info[option_id_index]);
                     if (current_attribute != -1) {
                        html += ' checked';
                        current_price = attribute_prices[current_attribute];
                        option_found = true;
                     }
                  }
                  if (option_info[option_order_name_index])
                     var option_name = option_info[option_order_name_index];
                  else var option_name = option_info[option_name_index];
                  html += ' onChange="select_attribute(this,' + row + ',' +
                          product_id + ',' + index + ',' + attr_index +
                          ');"><label for="' + field_name +
                          option_info[option_id_index] + '">' +
                          option_name + '</label>';
               }
               html += '</span>';
            }
         }
         else if (attribute_type == '2') { // Check Box
            if (options) {
               options = resequence_options(options);
               var first_option = true;
               html += '<span>';
               for (var option_index in options) {
                  var option_info = options[option_index];
                  if (first_option) first_option = false;
                  else html += '<br>';
                  html += '<input type="checkbox" class="checkbox" name="' +
                          field_name + '_' + option_info[option_id_index] +
                          '" id="' + field_name + '_' + option_info[option_id_index] +
                          '" value="' + option_info[option_id_index] + '"';
                  current_attribute = array_index(product_attributes,
                                                  option_info[option_id_index]);
                  if (current_attribute != -1) html += ' checked';
                  if (option_info[option_order_name_index])
                     var option_name = option_info[option_order_name_index];
                  else var option_name = option_info[option_name_index];
                  html += ' onChange="select_attribute(this,' + row + ',' +
                          product_id + ',' + index + ',' + attr_index +
                          ');"><label for="' + field_name +
                          option_info[option_id_index] + '">' +
                          option_name + '</label>';
               }
               html += '</span>';
            }
         }
         else if (attribute_type == '3') { // File
            html += '<div class="file_upload_attr" id="' + field_name +
                    '_div"></div>';
            html += '<input type="hidden" name="' + field_name + '" id="' +
                    field_name + '">';
            var uploaded_files = new Array();
            if (current_attribute) {
               var files = current_attribute.split('^');
               for (var file_index in files) {
                  var file_parts = files[file_index].substr(2).split('#}');
                  if (file_parts.length > 1) {
                     var cart_id = file_parts[0];
                     var filename = file_parts[1];
                     var url = file_url + '/cart/' + cart_id + '/' +
                               filename.replace('#','%23');
                     uploaded_files.push({'filename':filename,'cart_id':cart_id,
                                         'url':url});
                  }
               }
            }
            file_fields[field_name] = uploaded_files;
         }
         else if (attribute_type == '4') { // Text Area
            var num_cols = attribute_info[width_index];
            if (! num_cols) num_cols = 20;
            var num_rows = attribute_info[height_index];
            if (! num_rows) num_rows = 2;
            if (navigator.userAgent.indexOf('Firefox') != -1) num_rows -= 1;
            if (typeof(attribute_names[(attr_index * 2) + 1]) != 'undefined')
               var attr_value = attribute_names[(attr_index * 2) + 1];
            else var attr_value = default_value;
            html += '<textarea name="' + field_name + '" id="' + field_name +
                    '" class="textarea" rows="' + num_rows + '" ';
            if (num_cols < 1)
               html += 'style="width: ' + Math.abs(num_cols) + '%"';
            else html += 'cols="' + num_cols + '"';
            html += ' onBlur="select_attribute(this,' + row + ',' +
                    product_id + ',' + index + ',' + attr_index +
                    ');">' + attr_value + '</textarea>';
         }
         else if (attribute_type == '8') { // Designer
            if (current_attribute) {
               var url = file_url + '/designer/' + current_attribute;
               html += current_attribute + ' <a target="_blank" href="' + url +
                       '">View</a>';
               html += '<input type="hidden" name="' + field_name + '" id="' +
                       field_name + '" value="' + current_attribute + '">';
            }
         }
         else {
            html += '<input type="text" class="text" name="' + field_name +
                    '" id="' + field_name + '" size="80" style="width: ' +
                    select_width + 'px;" value="' + default_value +
                    '" onBlur="select_attribute(this,' +
                    row + ',' + product_id + ',' + index + ',' + attr_index +
                    ');">';
         }
         if (attribute_type != '4') {
            var price_field_name = 'attr_price_' + row + '_' + attr_index;
            html += '<input type="hidden" name="' + price_field_name +
                    '" id="' + price_field_name + '"';
            if (current_price > 0) html += ' value="' + current_price + '"';
            html += '>';
            html += '<span id="' + price_field_name +
                    '_span" class="attr_price"';
            if ((attribute_type == '1') || (attribute_type == '2'))
               html += ' style="position: absolute; top: 0px; left: ' +
                       select_width + 'px;"';
            html += '>';
            if (current_price > 0) html += format_amount(current_price,currency);
            html += '</span>';
         }
         html += '</td></tr>';
         attr_index++;
      }
      else attr_index++;
   }
   if (typeof(add_custom_attributes) != "undefined")
      html += add_custom_attributes(row,product_id,attr_index);
   html += '</table>';
   div.innerHTML = html;
   cell.appendChild(div);

   for (var field_name in file_fields)
      create_uploader(field_name,file_fields[field_name]);

   if (typeof(finish_display_custom_attributes) != "undefined")
      finish_display_custom_attributes(row,product_id);
}

function build_uploaded_file_html(field_name,filename,url,index)
{
    html = '<span class="qq-upload-file">';
    html += '<a href="'+url+'" target="_blank">' + filename + '</a>';
    html += '</span><span class="qq-delete-file">(<a href="#" ' +
           'onClick="delete_uploaded_file("'+field_name+'",'+index +
           ');">Delete';
    html += '</a>)</span><span class="qq-upload-failed-text">' +
           'Failed</span>';
    return html;
}

function create_uploader(field_name,uploaded_files)
{
    var uploaded_files_div = document.getElementById(field_name + '_div');
    var template = '<div class="qq-uploader">' + 
                   '<div class="qq-upload-drop-area"><span>Drop files here to upload</span></div>' +
                   '<div class="qq-upload-button">Attach File</div>';
    if (navigator.userAgent.indexOf('MSIE') == -1)
       template += '<div class="qq-drop-label">(or drop here)</div>';
    template += '<ul class="qq-upload-list"></ul></div>';
    if (document.EditOrder) var order_id = document.EditOrder.id.value;
    else var order_id = 0;
    var uploader = new qq.FileUploader({
       element: uploaded_files_div,
       action: 'orders.php',
       params: { cmd: 'uploaditemfile', order: order_id },
       onComplete: finish_uploaded_file,
       template: template,
       debug: false
    });
    var upload_list = uploaded_files_div.getElementsByTagName('ul')[0];
    upload_list.parent_id = parent;
    for (var index in uploaded_files) {
       var filename = uploaded_files[index].filename;
       var cart_id = uploaded_files[index].cart_id;
       var url = uploaded_files[index].url;
       var upload_row = document.createElement('li');
       upload_row.className = 'qq-upload-success';
       upload_row.id = 'upload_' + index;
       upload_row.filename = filename;
       upload_row.cart_id = cart_id;
       upload_row.innerHTML = build_uploaded_file_html(field_name,filename,
                                                       url,index);
       upload_list.appendChild(upload_row);
    }
}

function finish_uploaded_file(id,filename,response)
{
    var attachments_div = document.getElementById('attachments');
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    var attach_rows = upload_list.getElementsByTagName('li');
    var attach_index = -1;
    for (var index=0,length=attach_rows.length; index < length; index++) {
       var row_filename = attach_rows[index].getElementsByTagName('span')[0].firstChild.innerHTML;
       if (filename == row_filename) {
          attach_index = index;   break;
       }
    }
    if (attach_index == -1) return;
    var attach_row = attach_rows[attach_index];
    if (typeof(response.success) != 'undefined') {
       attach_row.id = 'attach_' + attach_index;
       attach_row.filename = filename;
       var file_size = attach_row.getElementsByTagName('span')[1].innerHTML;
       attach_row.innerHTML = build_uploaded_file_html(upload_list.parent_id,
                                                    filename,file_size,
                                                    attach_index);
       top.grow_current_dialog();
    }
    else attach_row.parentNode.removeChild(attach_row);
}

function add_uploaded_file(filename,file_size)
{
    var attachments_div = document.getElementById('attachments');
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    var attach_rows = upload_list.getElementsByTagName('li');
    for (var index=0,length=attach_rows.length; index < length; index++) {
       var row_filename = attach_rows[index].getElementsByTagName('span')[0].firstChild.innerHTML;
       if (filename == row_filename) return;
    }
    var new_index = attach_rows.length;
    var attach_row = document.createElement('li');
    attach_row.className = 'qq-upload-success';
    attach_row.id = 'attach_' + new_index;
    attach_row.filename = filename;
    attach_row.innerHTML = build_uploaded_file_html(upload_list.parent_id,
                                                 filename,file_size,new_index);
    upload_list.appendChild(attach_row);
    top.grow_current_dialog();
}

var delete_row;

function delete_uploaded_file(parent,index)
{
    delete_row = document.getElementById('attach_' + index);
    var response = confirm('Are you sure you want to delete the "' +
                           delete_row.filename + '" attachment?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=deleteattachment&parent=' + parent +
                 '&filename=' + encodeURIComponent(delete_row.filename);
    if (attach_dir) fields += '&AttachDir=' + encodeURIComponent(attach_dir);
    call_ajax('templates.php',fields,true,finish_delete_attachment);
}

function finish_delete_uploaded_file(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) delete_row.parentNode.removeChild(delete_row);
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function finish_load_product_inventory(ajax_request)
{
   if (ajax_request.state != 4) return;

   load_product_inventory_request = null;
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 200) {
      if (window.execScript)
         window.execScript(ajax_request.request.responseText);
      else window.eval.call(null,ajax_request.request.responseText);
      var form = get_order_form();
      for (var loop = 0;  loop < num_product_ids;  loop++) {
         var id_field = form['product_id_' + loop];
         if ((! id_field) || (! id_field.value)) continue;
         var cell = document.getElementById('product_name_cell_' + loop);
         if (! cell) continue;
         display_attributes(loop,id_field.value);
         set_dependencies(loop,id_field.value);
      }
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function add_edit_order_onclose(user_close)
{
   if (customer_orders_request) customer_orders_request.abort();
   if (account_info_request) account_info_request.abort();
   if (customer_profiles_request) customer_profiles_request.abort();
   if (customer_profile_request) customer_profile_request.abort();
   if (customer_accounts_request) customer_accounts_request.abort();
   if (customer_saved_cards_request) customer_saved_cards_request.abort();
   if (load_product_inventory_request) load_product_inventory_request.abort();
   if (lookup_fees_request) lookup_fees_request.abort();
   if (load_terms_request) load_terms_request.abort();
}

function order_dialog_onclose(user_close)
{
   if (cancel_copy_order) {
      var order_id = document.EditOrder.id.value;
      var fields = 'cmd=deleteorder&id=' + order_id + '&ordertype=' + order_type;
      call_ajax('orders.php',fields,true);
      top.get_content_frame().reload_grid();
   }
}

function add_edit_order_onload()
{
   top.set_current_dialog_onclose(add_edit_order_onclose);
   if (num_product_ids > 0) load_product_inventory();
   var form = get_order_form();
   if ((! form) || (! form.coupon_id) || (! form.gift_id) ||
       (! form.fee_name) || (! form.discount_name)) return;
   if (form.coupon_id.value || form.gift_id.value || form.fee_name.value ||
       form.discount_name.value) toggle_adjustments();
   if (document.EditOrder) {
      if (document.EditOrder.copying) {
         cancel_copy_order = true;
         top.set_current_dialog_onclose(order_dialog_onclose);
      }
      var customer_id = form.customer_id.value;
      if ((customer_id != '') && (customer_id != 0)) {
         load_customer_orders(customer_id,form.id.value);
         if (multiple_customer_accounts) load_customer_accounts(customer_id);
         else if ((form.account_id.value != 0) &&
                  (form.account_id.value != ''))
            load_account_info(form.account_id.value);
      }
      lookup_fees();
   }
   select_order_status(null);
   if (typeof(custom_add_edit_order_onload) != 'undefined')
      custom_add_edit_order_onload();
}

function update_total_fields(tax,shipping)
{
   var form = get_order_form();
   var subtotal_cell = document.getElementById('subtotal');
   var subtotal = parse_amount(subtotal_cell.innerHTML);
   if (form.coupon_amount)
      var coupon_amount = parse_amount(form.coupon_amount.value);
   else var coupon_amount = 0;
   if (form.gift_amount)
      var gift_amount = parse_amount(form.gift_amount.value);
   else var gift_amount = 0;
   var fee_amount = parse_amount(form.fee_amount.value);
   var discount_amount = parse_amount(form.discount_amount.value);
   var total = subtotal + tax + shipping - coupon_amount - gift_amount +
               fee_amount - discount_amount;
   total = Math.round(total * 100) / 100;
   var total_cell = document.getElementById('total');
   var currency = form.currency.value;
   total_cell.innerHTML = format_amount(total,currency);
   if (form.payment_type)
      var payment_type = get_selected_list_value('payment_type');
   else var payment_type = 0;
   if (form.new_balance_due) {
      var balance_due = total - parse_amount(form.payment_total.value);
      balance_due = Math.round(balance_due * 100) / 100;
      if (balance_due > 0) {
         form.new_balance_due.value = format_amount(balance_due);
         if (payment_type)
            form.payment_amount.value = format_amount(balance_due);
      }
      else {
         form.new_balance_due.value = '';
         form.payment_amount.value = '';
      }
      form.new_balance_due.value = form.new_balance_due.value.replace(/,/g,'');
   }
   else if (payment_type) {
      if (form.payment_amount)
         form.payment_amount.value = format_amount(total);
   }
   if (form.payment_amount)
      form.payment_amount.value = form.payment_amount.value.replace(/,/g,'');
}

function select_order_source()
{
   var source = get_selected_list_value('external_source');
   var span = document.getElementById('custom_source_span');
   if (source == 'custom') span.style.display = '';
   else span.style.display = 'none';
   if (source == 'new') {
      if (document.AddOrder) var frame = 'add_order';
      else var frame = 'edit_order';
      var url = '../cartengine/orders.php?cmd=addsource&Frame=' + frame;
      top.create_dialog('add_source',null,null,440,90,false,url,null);
   }
}

function cancel_add_order_source()
{
   var frame = document.AddOrderSource.Frame.value;
   var iframe = top.get_dialog_frame(frame).contentWindow;
   iframe.reset_source_list_selection();
   top.close_current_dialog();
}

function add_order_source()
{
   if (! validate_form_field(document.AddOrderSource.external_source,
                             'Order Source')) return;
   top.enable_current_dialog_progress(true);
   submit_form_data('admin.php','cmd=addordersource',
                    document.AddOrderSource,finish_add_order_source,30);
}

function finish_add_order_source(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var external_source = document.AddOrderSource.external_source.value;
       var frame = document.AddOrderSource.Frame.value;
       var iframe = top.get_dialog_frame(frame).contentWindow;
       iframe.update_source_list(external_source);
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function reset_source_list_selection()
{
    var source_list = document.getElementById('external_source');
    source_list.selectedIndex = 0;
}

function update_source_list(external_source)
{
    var source_list = document.getElementById('external_source');
    var new_option = new Option(external_source,external_source);
    var index = source_list.options.length - 2;
    source_list.add(new_option,index);
    source_list.selectedIndex = index;
}

function lookup_fees()
{
   var form = get_order_form();
   var subtotal_cell = document.getElementById('subtotal');
   var subtotal = parse_amount(subtotal_cell.innerHTML);
   var ship_country = get_selected_list_value('ship_country');
   if (ship_country == 1)
      var ship_state = get_selected_list_value('ship_state');
   else if (ship_country == 43)
      var ship_state = get_selected_list_value('ship_canada_province');
   else var ship_state = 'None';
   var ship_zipcode = form.ship_zipcode.value;
   var currency = form.currency.value;
   if ((subtotal == 0) || (ship_state == '') || (ship_zipcode == '')) {
      var tax_cell = document.getElementById('tax_cell');
      tax_cell.innerHTML = format_amount(0,currency);
      var tax_field = form.tax;
      if (tax_field) tax_field.value = '0';
      var shipping_method_span = document.getElementById('shipping_method_span');
      shipping_method_span.innerHTML = '';
      var shipping_row = document.getElementById('shipping_row');
      shipping_row.style.display = 'none';
      update_total_fields(0,0);
      return;
   }

   var fields = 'cmd=lookupfees' + add_order_type();
   var shipping_flags = document.getElementById('shipping_flags').value;
   fields += '&ShippingFlags=' + shipping_flags;
   ajax_request_pending = true;
   submit_form_data('orders.php',fields,form,finish_lookup_fees,120);
   lookup_fees_request = current_ajax_request;
}

var tax = 0;
var shipping_options = [];
var shipping_error = null;
var shipping_modules = [];

function get_shipping_value(field)
{
   if (field.nodeName == 'SELECT') {
      if (field.selectedIndex == -1) return '';
      return field.options[field.selectedIndex].value;
   }
   else return field.value;
}

function select_shipping_method()
{
   if (document.EditOrder) var form = document.EditOrder;
   else if (document.AddPartialShipment) var form = document.AddPartialShipment;
   else return;
   var current_module = get_shipping_value(form.shipping_carrier);
   var current_method = get_shipping_value(form.shipping_method);
   if (form.shipping_method.nodeName == 'SELECT') {
      var field = form.shipping_method;
      if (field.selectedIndex == -1) var current_method_label = null;
      else {
         var current_method_label = field.options[field.selectedIndex].text;
         var sep_pos = current_method_label.indexOf(' : ');
         if (sep_pos != -1)
            current_method_label = current_method_label.substring(0,sep_pos);
      }
   }
   else var current_method_label = null;
   var method_info = current_method.split("|");
   var selected_method = method_info[0];
   var shipping_field = form.shipping;
   if (document.EditOrder) {
      var tax_field = form.tax;
      if (tax_field) tax = parseFloat(tax_field.value);
      else tax = 0;
   }

   for (var index in shipping_options) {
      shipping_option = shipping_options[index];
      var option_info = shipping_option.split("|");
      if (option_info[0] != current_module) continue;
      if (option_info[1] == selected_method) {
         if (current_method_label && (option_info[3] != current_method_label))
            continue;
         if (option_info[2] == '') {
            var option_amount = 0;
            shipping_field.value = '';
         }
         else {
            var option_amount = parseFloat(option_info[2]);
            shipping_field.value = option_amount.toFixed(2);
         }
         if (document.EditOrder) update_total_fields(tax,option_amount);
         break;
      }
   }
}

function select_shipping_carrier()
{
   if (document.EditOrder) var form = document.EditOrder;
   else if (document.AddPartialShipment) var form = document.AddPartialShipment;
   else return;
   var current_module = get_shipping_value(form.shipping_carrier);
   var shipping_method_cell = document.getElementById('shipping_method_cell');
   var current_method = get_shipping_value(form.shipping_method);
   var method_info = current_method.split("|");
   var selected_method = method_info[0];
/*
   if (form.shipping_method.nodeName == 'INPUT')
      var initial_load = true;
   else var initial_load = false;
*/
   var html = '<select name="shipping_method" class="select" onChange="' +
              'select_shipping_method();">';
   var option_found = false;   var lowest_option = null;
   var lowest_price = -1;
   for (var index in shipping_options) {
      shipping_option = shipping_options[index];
      var option_info = shipping_option.split("|");
      if (option_info[0] != current_module) continue;
      if (option_info[2] == '') var option_amount = 0;
      else var option_amount = parseFloat(option_info[2]);
      if (option_info[1] == selected_method) option_found = true;
      else if ((lowest_price == -1) || (option_amount < lowest_price)) {
         lowest_option = option_info[1];   lowest_price = option_amount;
      }
   }
   for (var index in shipping_options) {
      shipping_option = shipping_options[index];
      var option_info = shipping_option.split("|");
      if (option_info[0] != current_module) continue;
      var option_value = option_info[1] + '|' + option_info[3];
      if (option_info[2] == '') var option_amount = 0;
      else var option_amount = parseFloat(option_info[2]);
      var option_label = option_info[3];
      if (option_info[2] != '')
         option_label += ' : $' + option_amount.toFixed(2);
      html += '<option value="' + option_value + '"';
      if ((option_found && (option_info[1] == selected_method)) ||
          ((! option_found) && lowest_option &&
           (option_info[1] == lowest_option)))
         html += ' selected';
      html += '>' + option_label + '</option>';
   }
   if ((! option_found) && current_method && loading_fees) {
      var option_info = current_method.split("|");
      if (typeof(option_info[1]) != 'undefined')
         var option_label = option_info[1];
      else var option_label = current_method;
      html += '<option value="' + current_method + '" selected>' +
              option_label + '</option>';
   }
   html += '</select>';
   shipping_method_cell.innerHTML = html;
   if (current_module == 'stamps') {
      var signature_confirm = document.getElementById('sig_conf_span');
      if (signature_confirm) signature_confirm.style.display = '';
   }
   if (! loading_fees) select_shipping_method();
}

function change_signature_confirm(check_field)
{
   var flags_field = document.getElementById('shipping_flags');
   var shipping_flags = flags_field.value;
   if (check_field.checked) shipping_flags |= 1;
   else shipping_flags &= ~1;
   flags_field.value = shipping_flags;
   lookup_fees();
}

function finish_lookup_fees(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   lookup_fees_request = null;
   var status = ajax_request.get_status();
   if (status != 200) {
      ajax_request_pending = false;   return;
   }

   loading_fees = true;
   var form = get_order_form();
   var coupon_id = null;
   eval(ajax_request.request.responseText);
   if (shipping_error) alert('Shipping Error: ' + shipping_error);
   var currency = form.currency.value;
   if (coupon_id && (! form.coupon_code.value)) {
      form.coupon_id.value = coupon_id;
      form.coupon_amount.value = coupon_amount;
      var coupon_amount_cell = document.getElementById('coupon_amount_cell');
      coupon_amount_cell.innerHTML = "-" +
              format_amount(coupon_amount,currency);
      if (adjustments_hidden) toggle_adjustments();
   }
   else if (form.coupon_id.value && form.coupon_amount.value &&
            (! form.coupon_code.value)) {
      form.coupon_id.value = '';
      form.coupon_amount.value = '';
      var coupon_amount_cell = document.getElementById('coupon_amount_cell');
      coupon_amount_cell.innerHTML = '';
      if (! adjustments_hidden) toggle_adjustments();
   }
   var tax_field = form.tax;
   if (tax !== null) {
      var tax_cell = document.getElementById('tax_cell');
      tax_cell.innerHTML = format_amount(tax,currency);
      if (tax_field) tax_field.value = tax;
   }
   else if (tax_field) tax = parseFloat(tax_field.value);
   else tax = 0;
   var shipment_table = document.getElementById('shipment_table_0');
   if (document.AddOrder) {
      var shipping_method = document.getElementById('shipping_method_span');
      var shipping_row = document.getElementById('shipping_row');
      var default_rate = -1;
      if (shipping_options.length == 0) {
         shipping_method.innerHTML = '';
         shipping_row.style.display = 'none';
         if (form.shipping) default_rate = parse_amount(form.shipping.value);
      }
      else {
         var html = '<select name="shipping_method" class="select" onChange="' +
                    'select_shipping(this);">';
         html += '<option value=""></option>';
         for (var index in shipping_options) {
            shipping_option = shipping_options[index];
            var option_info = shipping_option.split("|");
            html += '<option value="' + shipping_option + '"';
            if (selected_shipping_info) {
               if ((selected_shipping_info[0] == option_info[0]) &&
                   (selected_shipping_info[1] == option_info[1])) {
                  html += ' selected';
                  if (option_info[2] == '') default_rate = 0;
                  else default_rate = parse_amount(option_info[2]);
               }
            }
            else if (option_info[4] == 1) {
               html += ' selected';
               if (option_info[2] == '') default_rate = 0;
               else default_rate = parse_amount(option_info[2]);
            }
            html += '>';
            if (option_info[3] != '') {
               html += option_info[3];
               if (option_info[2] != '') html += ' : ';
            }
            if (option_info[2] != '')
               html += format_amount(option_info[2],currency);
            html += '</option>';
         }
         html += '</select>';
         shipping_method.innerHTML = html;
         shipping_row.style.display = '';
         if (default_rate != -1)
            document.AddOrder.shipping.value = default_rate.toFixed(2);
      }
      if (default_rate == -1) default_rate = 0;
   }
   else if (! shipment_table) {
      var shipping = document.EditOrder.shipping.value;
      if (shipping == '') var default_rate = 0;
      else var default_rate = parseFloat(shipping,10);
      var shipping_carrier_row = document.getElementById('shipping_carrier_row');
      var shipping_carrier_cell = document.getElementById('shipping_carrier_cell');
      var shipping_method_row = document.getElementById('shipping_method_row');
      var shipping_method_cell = document.getElementById('shipping_method_cell');
      if (shipping_options.length == 0) {
         shipping_carrier_row.style.display = 'none';
         shipping_carrier_cell.innerHTML = '';
         shipping_method_row.style.display = 'none';
         shipping_method_cell.innerHTML = '';
      }
      else {
         var active_modules = new Array();
         for (var index in shipping_options) {
            shipping_option = shipping_options[index];
            var option_info = shipping_option.split("|");
            active_modules[option_info[0]] = true;
         }
         var current_module = get_shipping_value(document.EditOrder.shipping_carrier);
         var html = '<select name="shipping_carrier" class="select" onChange="' +
                    'select_shipping_carrier();">';
         html += '<option value=""></option>';
         for (var module in active_modules) {
            shipping_option = shipping_options[index];
            var option_info = shipping_option.split("|");
            html += '<option value="' + module + '"';
            if (module == current_module) html += ' selected';
            html += '>' + shipping_modules[module] + '</option>';
         }
         html += '</select>';
         if (typeof(active_modules['stamps']) != 'undefined') {
            var shipping_flags = document.getElementById('shipping_flags').value;
            html += '<span id="sig_conf_span" style="display:none;">';
            html += '&nbsp;&nbsp;&nbsp;&nbsp;';
            html += '<input type="checkbox" class="checkbox" name="' +
                    'signature_confirm" id="signature_confirm"';
            if (shipping_flags & 1) html += ' checked';
            html += ' onChange="change_signature_confirm(this);"><label for="' +
                    'signature_confirm">Signature Confirm</label></span>';
         }
         shipping_carrier_cell.innerHTML = html;
         shipping_carrier_row.style.display = '';
         select_shipping_carrier();
         shipping_method_row.style.display = '';
      }
   }
   update_total_fields(tax,default_rate);
   ajax_request_pending = false;
   loading_fees = false;
}

function select_shipping(shipping_field)
{
   var tax_cell = document.getElementById('tax_cell');
   var tax = parse_amount(tax_cell.innerHTML);
   var shipping_method = shipping_field.options[shipping_field.selectedIndex].value;
   if (shipping_method == '') {
      document.AddOrder.shipping.value = '';
      return;
   }
   var shipping_info = shipping_method.split('|');
   var shipping = shipping_info[2];
   if (shipping == '') {
      shipping = 0;   document.AddOrder.shipping.value = '';
   }
   else {
      shipping = parseFloat(shipping,10);
      document.AddOrder.shipping.value = shipping.toFixed(2);
   }
   if (! update_gift_amount()) update_total_fields(tax,shipping);
   if (tax_shipping) {
      selected_shipping_info = shipping_info;   lookup_fees();
   }
}

function update_subtotal()
{
   var form = get_order_form();
   var subtotal = 0;
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var total_cell = document.getElementById('product_total_' + loop);
      if (total_cell) subtotal += parse_amount(total_cell.innerHTML);
   }
   var currency = form.currency.value;
   var subtotal_cell = document.getElementById('subtotal');
   subtotal_cell.innerHTML = format_amount(subtotal,currency);
   var subtotal_field = form.subtotal;
   if (subtotal_field) subtotal_field.value = subtotal;
}

function update_totals()
{
   update_subtotal();
   lookup_fees();
}

function update_line_item_total(row_num,custom,total_flag)
{
   var form = get_order_form();
   var qty_field = form['product_qty_' + row_num];
   if (qty_field.tagName == 'SELECT')
      var qty = parse_int(qty_field.options[qty_field.selectedIndex].value);
   else var qty = parse_int(qty_field.value);
   var price_field = form['product_price_' + row_num];
   var price = parse_amount(price_field.value);
   if (product_prices[row_num] && (price == product_prices[row_num]) &&
       product_quantities[row_num] && (qty == product_quantities[row_num]))
      return;
   var currency = form.currency.value;
   if (! custom) var product_id = form['product_id_' + row_num].value;
   if ((! custom) && (features & REGULAR_PRICE_BREAKS)) {
      var break_index = array_index(price_break_fields,'price_breaks');
      var price_breaks = price_break_data[product_id][break_index].split('|');
      for (var loop = 0;  loop < price_breaks.length;  loop++) {
         var break_info = price_breaks[loop].split('-');
         if ((qty >= parse_int(break_info[0])) &&
             (qty <= parse_int(break_info[1]))) {
            price = parse_amount(break_info[2]);
            price_field.value = price;
            var price_cell = document.getElementById('product_price_cell_' +
                                                     row_num);
            price_cell.innerHTML = format_amount(price,currency);
            break;
         }
      }
   }
   if (custom) var flags = 0;
   else var flags = parse_int(form['item_flags_'+row_num].value);
   if (flags & 1) var total = price;
   else var total = qty * price;
   var total_cell = document.getElementById('product_total_' + row_num);
   product_prices[row_num] = price;
   if (typeof(attribute_data) == 'undefined') var num_attrs = 0;
   else if (typeof(attribute_data[product_id]) == 'undefined')
      var num_attrs = 0;
   else var num_attrs = attribute_data[product_id].length;
   for (var attr_index = 0;  attr_index < num_attrs;  attr_index++) {
      var price_field_name = 'attr_price_' + row_num + '_' + attr_index;
      var attr_price = form[price_field_name];
      if (attr_price && attr_price.value) {
         var attr_amount = parseFloat(attr_price.value);
         if (flags & 1) total += attr_amount;
         else total += (attr_amount * qty);
      }
   }
   total_cell.innerHTML = format_amount(total,currency);
   product_quantities[row_num] = qty;
   if (total_flag) {
      update_subtotal();
      if (! update_gift_amount()) lookup_fees();
   }
}

function delete_line_item(row_id)
{
   var table = document.getElementById('add_edit_order_product_table');
   var delete_id = 'product_row_' + row_id;
   var num_rows = table.rows.length;
   for (var loop = 0;  loop < num_rows;  loop++)
      if (table.rows[loop].id == delete_id) {
         table.deleteRow(loop);   break;
      }
   num_product_rows--;
   update_subtotal();
   if (! update_gift_amount()) update_totals();
}

function append_form_value(field_value)
{
    field_value = field_value.replace(/\"/g,'&quot;');
    return field_value;
}

function get_account_name(account_id)
{
    if ((account_id == 0) || (account_id == '')) return '';
    for (var index in accounts) {
       if (accounts[index].id == account_id) return accounts[index].name;
    }
    return '';
}

function insert_item_field(new_row,column,num_product_ids,product_data,
                           before_field)
{
    if (Object.keys(item_fields).length == 0) return false;
    var found = false;
    for (var field_name in item_fields) {
       if (item_fields[field_name].before != before_field) continue;
       var field = item_fields[field_name];
       new_cell = new_row.insertCell(column);
       if (typeof(field.align) == 'undefined') new_cell.align = 'left';
       else new_cell.align = field.align;
       if ((! product_data) ||
           (typeof(product_data[field_name]) == 'undefined')) var value = '';
       else var value = product_data[field_name];
       var html = '<input type="text" class="text" style="border: 0px;';
       if (typeof(field.align) != 'undefined')
          html += ' text-align: ' + field.align + ';';
       var name = field_name + '_' + num_product_ids;
       html += '" name="' + name + '" id="' + name + '" size="' +
               field.fieldwidth + '" value="' + value + '">';
       new_cell.innerHTML = html;
       found = true;
    }
    return found;
}

function change_product(product_data)
{
   var form = get_order_form();
   var currency = form.currency.value;
   if (product_data.cost)
      var formatted_cost = format_amount(product_data.cost,currency);
   else var formatted_cost = '';
   var price = parse_amount(product_data.sale_price);
   if (! price) var price = parse_amount(product_data.price);
   if (no_account_products.indexOf(parseInt(product_data.id)) != -1) {}
   else if (typeof(account_products[product_data.id]) != 'undefined')
      price = account_products[product_data.id];
   else if ((typeof(account_info.id) != 'undefined') &&
            product_data.account_discount) {
      var discount = Math.round(price * product_data.account_discount) / 100;
      price -= discount;
      price = Math.round(price * 100) / 100;
      if (typeof(custom_update_discount_price) != 'undefined')
         price = custom_update_discount_price(price,product_data);
   }
   else if (account_discount) {
      var discount = Math.round(price * account_discount) / 100;
      price -= discount;
      price = Math.round(price * 100) / 100;
      if (typeof(custom_update_discount_price) != 'undefined')
         price = custom_update_discount_price(price,product_data);
   }
   var formatted_price = format_amount(price,currency);
   if (product_data.price_break_type == 1) var item_flags = 1;
   else var item_flags = 0;
   if (product_data.order_name)
      var product_name = product_data.order_name;
   else if (product_data.display_name)
      var product_name = product_data.display_name;
   else var product_name = product_data.name;
   var table = document.getElementById('add_edit_order_product_table');
   var column = 0;
   var new_row = table.insertRow(num_product_rows);
   new_row.id = 'product_row_' + num_product_ids;
   new_row.vAlign = 'top';

   if (insert_item_field(new_row,column,num_product_ids,product_data,'name'))
      column++;

   var new_cell = new_row.insertCell(column++);
   new_cell.align = 'left';
   new_cell.className = 'left_product_cell';
   new_cell.id = 'product_name_cell_' + num_product_ids;
   he.encode.options.useNamedReferences = true;
   he.encode.options.allowUnsafeSymbols = true;
   if (product_data.flags & HIDE_NAME_IN_ORDERS) {
      var display_product_name = '';
      edit_order_item_flags &= ~EDIT_ORDER_ITEM_NAME;
   }
   else var display_product_name = he.decode(product_name);
   var html = '<input type="hidden" name="product_id_' + num_product_ids +
              '" id="product_id_' + num_product_ids +
              '" value="' + append_form_value(product_data.id) + '">';
   if (edit_order_item_flags & EDIT_ORDER_ITEM_NAME)
      html += '<input type="text" class="text" style="border: 0px; width: 100%;" ' +
              'name="product_name_' + num_product_ids +
              '" id="product_name_' + num_product_ids + '" value="' +
              display_product_name + '">';
   else html += '<input type="hidden" ' +
                'name="product_name_' + num_product_ids + '" id="product_name_' +
                num_product_ids + '" value="' +
                append_form_value(product_name) + '">';
   if (! (edit_order_item_flags & EDIT_ORDER_ITEM_PRICE))
      html += '<input type="hidden" name="product_price_' + num_product_ids +
              '" id="product_price_' + num_product_ids +
              '" value="' + price + '">';
   html += '<input type="hidden" ' +
           'name="product_attributes_' + num_product_ids +
           '" id="product_attributes_' + num_product_ids +
           '" value=""><input type="hidden" name="product_attribute_names_' +
           num_product_ids + '" id="product_attribute_names_' +
           num_product_ids + '" value=""><input type="hidden" ' +
           'name="product_attribute_prices_' + num_product_ids +
           '" id="product_attribute_prices_' + num_product_ids +
           '" value=""><input type="hidden" ' +
           'name="item_flags_' + num_product_ids +
           '" id="item_flags_' + num_product_ids +
           '" value="' + item_flags + '">' + 
           '<input type="hidden" name="product_custom_attrs_' +
           num_product_ids + '" id="product_custom_attrs_' +
           num_product_ids + '" value="">';
   if (! (edit_order_item_flags & EDIT_ORDER_ITEM_NAME))
      html += display_product_name;
   if (enable_reorders)
      html += add_order_item_reorder_option(num_product_ids,product_data);
   new_cell.innerHTML = html;

   if (insert_item_field(new_row,column,num_product_ids,product_data,
                         'account')) column++;

   if (multiple_customer_accounts) {
      if (form.account && (form.account.selectedIndex != -1))
         var account_id =
            form.account.options[form.account.selectedIndex].value;
      else var account_id = 0;
      new_cell = new_row.insertCell(column++);
      new_cell.align = 'left';
      new_cell.className = 'account_id_cell';
      new_cell.id = 'account_id_cell_' + num_product_ids;
      new_cell.innerHTML = '<input type="hidden" name="account_id_' +
         num_product_ids + '" id="account_id_' + num_product_ids +
         '" value="' + account_id + '">' + get_account_name(account_id);
   }

   if (insert_item_field(new_row,column,num_product_ids,product_data,
                         'part_number')) column++;

   if (features & USE_PART_NUMBERS) {
      new_cell = new_row.insertCell(column++);
      new_cell.align = 'center';
      new_cell.className = 'part_number_cell';
      new_cell.id = 'part_number_cell_' + num_product_ids;
      if (edit_order_item_flags & EDIT_ORDER_ITEM_PART_NUMBER)
         new_cell.innerHTML = '<input type="text" class="text" ' +
                              'style="border: 0px; width: 100%;" ' +
                              'name="part_number_' + num_product_ids +
                             '" id="part_number_' + num_product_ids +
                             '" value="' + product_data.part_number + '">';
      else new_cell.innerHTML = product_data.part_number;
   }

   if (insert_item_field(new_row,column,num_product_ids,product_data,'cost'))
      column++;

   if ((features & (PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY)) &&
       show_cost_column) {
      new_cell = new_row.insertCell(column++);
      new_cell.align = 'right';
      new_cell.className = 'product_cost_cell';
      new_cell.id = 'product_cost_cell_' + num_product_ids;
      if (edit_order_item_flags & EDIT_ORDER_ITEM_COST) {
         if (product_data.cost) var product_cost = product_data.cost;
         else product_cost = '';
         new_cell.innerHTML = '<input type="text" class="text" style="border: 0px; ' +
                              'text-align: right;" name="product_cost_' + num_product_ids +
                              '" id="product_cost_' + num_product_ids +
                              '" size="5" value="' + product_cost + '">';
      }
      else new_cell.innerHTML = formatted_cost;
   }

   if (insert_item_field(new_row,column,num_product_ids,product_data,'price'))
      column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'right';
   new_cell.className = 'product_price_cell';
   new_cell.id = 'product_price_cell_' + num_product_ids;
   if (edit_order_item_flags & EDIT_ORDER_ITEM_PRICE)
      new_cell.innerHTML = '<input type="text" class="text" style="border: 0px; ' +
                           'text-align: right;" name="product_price_' + num_product_ids +
                           '" id="product_price_' + num_product_ids +
                           '" onBlur="update_line_item_total(' + num_product_ids +
                           ',true,true);" size="5" value="' + price + '">';
   else new_cell.innerHTML = formatted_price;

   if (insert_item_field(new_row,column,num_product_ids,product_data,'qty'))
      column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'center';
   new_cell.className = 'product_qty_cell';
   new_cell.innerHTML = '<input type="text" class="text" style="border: 0px; ' +
                        'text-align: center;" name="product_qty_' + num_product_ids +
                        '" id="product_qty_' + num_product_ids +
                        '" onBlur="update_line_item_total(' + num_product_ids +
                        ',false,true);" value="1" size="2">';

   if (insert_item_field(new_row,column,num_product_ids,product_data,'total'))
      column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'right';
   new_cell.className = 'product_total_cell';
   new_cell.id = 'product_total_' + num_product_ids;
   new_cell.innerHTML = formatted_price;

   if (insert_item_field(new_row,column,num_product_ids,product_data,''))
      column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'center';
   new_cell.className = 'product_delete_cell';
   new_cell.innerHTML = '<a href=\"#\" class=\"perms_link\" ' +
                        'onClick=\"delete_line_item(' +
                        num_product_ids + ');\">Delete</a>';

   var qty_field = document.getElementById('product_qty_' + num_product_ids);
   product_quantities[num_product_ids] = 1;
   qty_field.focus();
   qty_field.select();
   num_product_rows++;
   num_product_ids++;
   form.NumIds.value = num_product_ids;
   update_subtotal();
   if (! update_gift_amount()) lookup_fees();
   load_product_inventory();
}

function add_custom_product()
{
   var form = get_order_form();
   var table = document.getElementById('add_edit_order_product_table');
   var new_row = table.insertRow(num_product_rows);
   new_row.id = 'product_row_' + num_product_ids;
   new_row.vAlign = 'top';
   var column = 0;

   if (insert_item_field(new_row,column,num_product_ids,null,'name')) column++;

   var new_cell = new_row.insertCell(column++);
   new_cell.align = 'left';
   new_cell.className = 'left_product_cell';
   new_cell.id = 'product_name_cell_' + num_product_ids;
   new_cell.innerHTML = '<input type="text" class="text" ' +
                        'style="border: 0px; width: 100%;" ' +
                        'name="product_name_' + num_product_ids +
                        '" id="product_name_' + num_product_ids + '">' +
                        '<input type="hidden" name="product_custom_attrs_' +
                        num_product_ids + '" id="product_custom_attrs_' +
                        num_product_ids + '" value="">';

   if (insert_item_field(new_row,column,num_product_ids,null,'account'))
      column++;

   if (multiple_customer_accounts) {
      var account_id = form.account.options[form.account.selectedIndex].value;
      new_cell = new_row.insertCell(column++);
      new_cell.align = 'left';
      new_cell.className = 'account_id_cell';
      new_cell.id = 'account_id_cell_' + num_product_ids;
      new_cell.innerHTML = '<input type="hidden" name="account_id_' +
         num_product_ids + '" id="account_id_' + num_product_ids +
         '" value="' + account_id + '">' + get_account_name(account_id);
   }

   if (insert_item_field(new_row,column,num_product_ids,null,'part_number'))
      column++;

   if (features & USE_PART_NUMBERS) {
      new_cell = new_row.insertCell(column++);
      new_cell.align = 'center';
      new_cell.className = 'part_number_cell';
      new_cell.id = 'part_number_cell_' + num_product_ids;
      new_cell.innerHTML = '<input type="text" class="text" ' +
                           'style="border: 0px; width: 100%;" ' +
                           'name="part_number_' + num_product_ids +
                           '" id="part_number_' + num_product_ids + '">';
   }

   if (insert_item_field(new_row,column,num_product_ids,null,'cost')) column++;

   if ((features & (PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY)) &&
       show_cost_column) {
      new_cell = new_row.insertCell(column++);
      new_cell.align = 'right';
      new_cell.className = 'product_cost_cell';
      new_cell.innerHTML = '<input type="text" class="text" style="border: 0px; ' +
                           'text-align: right;" name="product_cost_' + num_product_ids +
                           '" id="product_cost_' + num_product_ids + '" size="5">';
   }

   if (insert_item_field(new_row,column,num_product_ids,null,'price'))
      column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'right';
   new_cell.className = 'product_price_cell';
   new_cell.innerHTML = '<input type="text" class="text" style="border: 0px; ' +
                        'text-align: right;" name="product_price_' + num_product_ids +
                        '" id="product_price_' + num_product_ids +
                        '" onBlur="update_line_item_total(' + num_product_ids +
                        ',true,true);" size="5">';

   if (insert_item_field(new_row,column,num_product_ids,null,'total'))
      column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'center';
   new_cell.innerHTML = '<input type="text" class="text" style="border: 0px; ' +
                        'text-align: center;" name="product_qty_' + num_product_ids +
                        '" id="product_qty_' + num_product_ids +
                        '" onBlur="update_line_item_total(' + num_product_ids +
                        ',true,true);" value="1" size="2">';

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'right';
   new_cell.className = 'product_total_cell';
   new_cell.id = 'product_total_' + num_product_ids;
   new_cell.innerHTML = '$0.00';

   if (insert_item_field(new_row,column,num_product_ids,null,'')) column++;

   new_cell = new_row.insertCell(column++);
   new_cell.align = 'center';
   new_cell.innerHTML = '<a href=\"#\" class=\"perms_link\" ' +
                        'onClick=\"delete_line_item(' +
                        num_product_ids + ');\">Delete</a>';

   document.getElementById('product_name_' + num_product_ids).focus();
   product_quantities[num_product_ids] = 1;
   product_prices[num_product_ids] = 0;
   if (typeof(finish_add_custom_product) != 'undefined')
      finish_add_custom_product(num_product_ids);
   num_product_rows++;
   num_product_ids++;
   form.NumIds.value = num_product_ids;
}

function add_new_product()
{
   top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('../cartengine/' + products_script_name,
                               'cmd=createproduct',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_new_product,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_new_product(ajax_request,data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var product_id = -1;
   eval(ajax_request.request.responseText);

   if (document.AddOrder) var frame = 'add_order';
   else var frame = 'edit_order';
   url = '../cartengine/' + products_script_name + '?cmd=addproduct&id=' +
         product_id + '&OrderFrame=' + frame;
   var window_width = top.get_document_window_width();
   if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
   else window_width -= top.default_dialog_frame_width;
   url += '&window_width=' + window_width;
   var dialog_name = 'add_product_' + (new Date()).getTime();
   top.create_dialog(dialog_name,null,null,1050,product_dialog_height,false,
                     url,null);
}

function update_product_prices()
{
   var form = get_order_form();
   var fields = 'cmd=getprices';
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var id_field = form['product_id_' + loop];
      if (id_field) fields += '&id_' + loop + '=' + id_field.value;
   }
   call_ajax('orders.php',fields,true,finish_update_product_prices);
}

function finish_update_product_prices(ajax_request)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) return;

   var form = get_order_form();
   eval(ajax_request.request.responseText);

   var currency = form.currency.value;
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var id_field = form['product_id_' + loop];
      if ((! id_field) || (! id_field.value)) var custom = true;
      else var custom = false;
      if (custom) continue;
      var unit_price_cell = document.getElementById('product_price_cell_' +
                                                    loop);
      var unit_price = form['product_price_' + loop].value;
      unit_price_cell.innerHTML = format_amount(unit_price,currency);
      product_quantities[loop] = -1;
      update_line_item_total(loop,custom,false);
   }
   update_totals();
}

function load_shipping_profiles(customer_id)
{
    var fields = 'cmd=loadshippingprofiles&id=' + customer_id;
    call_ajax('../cartengine/orders.php',fields,true,
              finish_load_shipping_profiles);
    customer_profiles_request = current_ajax_request;
}

function finish_load_shipping_profiles(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    var ship_profile_row = document.getElementById('ship_profile_row');
    ship_profile_row.style.display = 'none';
    customer_profiles_request = null;
    var status = ajax_request.get_status();
    if (status != 200) return;

    var profile_list = document.getElementById('ship_profilename');
    var list_length = profile_list.options.length;
    for (var index=list_length - 1;  index >= 0;  index--)
       profile_list.remove(index);
    if (! ajax_request.request.responseText) profiles = null;
    else profiles = JSON.parse(ajax_request.request.responseText);
    if ((! profiles) || (profiles.length == 0)) {
       var new_option = new Option('Default','Default');
       profile_list.options[profile_list.options.length] = new_option;
       profile_list.selectedIndex = 0;   return;
    }
    for (var index in profiles) {
       var name = profiles[index].profilename;
       if (! name) name = 'Default';
       var new_option = new Option(name,name);
       profile_list.options[profile_list.options.length] = new_option;
       if (profiles[index].default_flag) profile_list.selectedIndex = index;
    }
    if (profiles.length > 1) ship_profile_row.style.display = '';
}

function select_shipping_profile(profile_field)
{
    var profile = profile_field.options[profile_field.selectedIndex].value;
    var form = get_order_form();
    var customer_id = form.customer_id.value;
    var fields = 'cmd=loadshippingprofile&id=' + customer_id +'&Profile=' +
                 encodeURIComponent(profile);
    call_ajax('../cartengine/orders.php',fields,true,
              finish_select_shipping_profile);
    customer_profile_request = current_ajax_request;
}

function finish_select_shipping_profile(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    customer_profile_request = null;
    var status = ajax_request.get_status();
    if (status != 200) return;

    if (! ajax_request.request.responseText) return;
    var shipping_info = JSON.parse(ajax_request.request.responseText);
    var form = get_order_form();
    form.shipto.value = shipping_info.shipto;
    form.ship_company.value = shipping_info.company;
    form.ship_address1.value = shipping_info.address1;
    form.ship_address2.value = shipping_info.address2;
    form.ship_city.value = shipping_info.city;
    set_list_value(form.ship_state,shipping_info.state);
    form.ship_zipcode.value = shipping_info.zipcode;
    set_list_value(form.ship_country,shipping_info.country);
    set_radio_button('address_type',shipping_info.address_type);
    current_ship_state = shipping_info.state;
    current_ship_zipcode = shipping_info.zipcode;
    current_ship_country = shipping_info.country;
    lookup_fees();
}

function shipping_zip_onblur(zip_field)
{
   if (zip_field.value == current_ship_zipcode) return;
   current_ship_zipcode = zip_field.value;
   if (! update_gift_amount()) lookup_fees();
}

function select_shipping_state(state_field)
{
   var selected_state = state_field.options[state_field.selectedIndex].value;
   if (selected_state == current_ship_state) return;
   current_ship_state = selected_state;
   if (! update_gift_amount()) lookup_fees();
}

function select_shipping_country(country_field)
{
   var selected_country = country_field.options[country_field.selectedIndex].value;
   if (selected_country == current_ship_country) return;
   current_ship_country = selected_country;
   select_country(country_field,'ship_');
   if (! update_gift_amount()) lookup_fees();
}

function change_shipping()
{
   var row = document.getElementById('shipped_date_text_row');
   row.style.display = 'none';
   row = document.getElementById('shipped_date_edit_row');
   row.style.display = '';
}

function toggle_adjustments()
{
   var coupon_row = document.getElementById('coupon_row');
   if (coupon_row) {
      if (adjustments_hidden) coupon_row.style.display = '';
      else coupon_row.style.display = 'none';
   }
   var gift_row = document.getElementById('gift_row');
   if (gift_row) {
      if (adjustments_hidden) gift_row.style.display = '';
      else gift_row.style.display = 'none';
   }
   var fee_row = document.getElementById('fee_row');
   if (adjustments_hidden) fee_row.style.display = '';
   else fee_row.style.display = 'none';
   var discount_row = document.getElementById('discount_row');
   if (adjustments_hidden) discount_row.style.display = '';
   else discount_row.style.display = 'none';
   adjustments_hidden = ! adjustments_hidden;
}

var saved_coupon_code = '';

function set_coupon_fields(coupon_id,coupon_type,coupon_amount)
{
   var form = get_order_form();
   var currency = form.currency.value;
   form.coupon_id.value = coupon_id;
   form.coupon_type.value = coupon_type;
   form.coupon_amount.value = coupon_amount;
   var coupon_amount_cell = document.getElementById('coupon_amount_cell');
   if (coupon_amount == '') coupon_amount_cell.innerHTML = '';
   else coupon_amount_cell.innerHTML = "-" +
           format_amount(coupon_amount,currency);
   if (coupon_type == 3) form.shipping.value = '';
   if (! update_gift_amount()) update_totals();
}

function coupon_onblur(coupon_id_field)
{
   var form = get_order_form();
   var coupon_code = form.coupon_code.value;
   if (coupon_code == saved_coupon_code) return;
   saved_coupon_code = coupon_code;
   if (coupon_code == '') {
      set_coupon_fields('',0,'');   return;
   }
   var fields = 'cmd=processcoupon' + add_order_type();
   submit_form_data("orders.php",fields,form,finish_process_coupon);
}

function finish_process_coupon(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) return;

   var coupon_id = '';
   var coupon_amount = 0;
   var coupon_type = 0;
   var coupon_error = '';
   eval(ajax_request.request.responseText);
   if (coupon_error) {
      alert(coupon_error);
      set_coupon_fields('',0,'');
      var form = get_order_form();
      form.coupon_code.value = '';
      form.coupon_code.focus();
   }
   set_coupon_fields(coupon_id,coupon_type,coupon_amount);
}

var saved_gift_code = '';

function set_gift_fields(gift_id,gift_amount,gift_balance)
{
   var form = get_order_form();
   var currency = form.currency.value;
   form.gift_id.value = gift_id;
   form.gift_amount.value = gift_amount;
   form.gift_balance.value = gift_balance;
   var gift_amount_cell = document.getElementById('gift_amount_cell');
   if (gift_amount == '') gift_amount_cell.innerHTML = '';
   else gift_amount_cell.innerHTML = "-" + format_amount(gift_amount,currency);
   update_totals();
}

function gift_onblur(gift_id_field)
{
   var form = get_order_form();
   var gift_code = form.gift_code.value;
   if (gift_code == saved_gift_code) return;
   saved_gift_code = gift_code;
   if (gift_code == '') {
      set_gift_fields('','','');   return;
   }
   var fields = 'cmd=processgift' + add_order_type();
   submit_form_data("orders.php",fields,form,finish_process_gift);
}

function finish_process_gift(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) return;

   var gift_id = '';
   var gift_amount = 0;
   var gift_balance = 0;
   var gift_error = '';
   eval(ajax_request.request.responseText);
   if (gift_error != '') {
      alert(gift_error);   saved_gift_code = '';
      set_gift_fields('','','');
      var form = get_order_form();
      form.gift_code.focus();
   }
   set_gift_fields(gift_id,gift_amount,gift_balance);
}

function update_gift_amount()
{
   var form = get_order_form();
   if (! form.gift_code) return false;
   var gift_code = form.gift_code.value;
   if (gift_code != '') {
      saved_gift_code = '';   gift_onblur(form.gift_code);
      return true;
   }
   return false;
}

function fee_onblur(fee_amount_field)
{
   if (! update_gift_amount()) update_totals();
}

function discount_onblur(discount_amount_field)
{
   if (! update_gift_amount()) update_totals();
}

function refund_onclick(checkbox,payment_row,cancel_suffix)
{
   var refund_amount_div = document.getElementById('refund_amount_div_' +
                                                   cancel_suffix);
   if (checkbox.checked) {
      var top = 4 + (payment_row * 16);
      refund_amount_div.style.top = top + 'px';
      refund_amount_div.style.display = '';
   }
   else refund_amount_div.style.display = 'none';
}

var current_payment_type = '';

function change_payment_type()
{
   var payment_type = get_selected_list_value('payment_type');

   if (current_payment_type == 'cc') {
      for (var loop = 0;  loop < 6;  loop++) {
         var row = document.getElementById('cc_row_' + loop);
         if (row) row.style.display = 'none';
      }
   }
   else if (payment_type == 'cc') {
      for (var loop = 0;  loop < 6;  loop++) {
         var row = document.getElementById('cc_row_' + loop);
         if (row) row.style.display = '';
      }
   }
   if (current_payment_type == 'Check') {
      var row = document.getElementById('check_row_0');
      if (row) row.style.display = 'none';
   }
   else if (payment_type == 'Check') {
      var row = document.getElementById('check_row_0');
      if (row) row.style.display = '';
   }
   current_payment_type = payment_type;
   if (typeof(custom_change_payment_type) != "undefined")
      custom_change_payment_type(payment_type);

   var form = get_order_form();
   if (! form.payment_amount.value) {
      if (form.new_balance_due)
         form.payment_amount.value = form.new_balance_due.value;
      else {
         var total_cell = document.getElementById('total');
         var total = parse_amount(total_cell.innerHTML);
         form.payment_amount.value = format_amount(total);
      }
      form.payment_amount.value = form.payment_amount.value.replace(/,/g,'');
   }
}

function card_number_keyup(card_number)
{
    var card_type_list = document.getElementById('card_type');
    switch (card_number.charAt(0)) {
       case '3': var card_type = 'amex';   break;
       case '4': var card_type = 'visa';   break;
       case '5': var card_type = 'master';   break;
       case '6': var card_type = 'discover';   break;
       default: var card_type = '';   break;
    }
    var length = card_type_list.options.length;
    for (var index = 0;  index < length;  index++) {
       if (card_type_list.options[index].value == card_type) {
          card_type_list.selectedIndex = index;   break;
       }
    }
}

function validate_attributes()
{
   var form = get_order_form();
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var id_field = form['product_id_' + loop];
      if ((! id_field) || (! id_field.value)) continue;
      var attr_index = 0;
      var product_id = id_field.value;
      var field_name = 'attr_' + attr_index + '_' + loop;
      while (form[field_name]) {
         var index_field_name = 'attr_index_' + attr_index + '_' + loop;
         if (form[index_field_name]) {
            var attr_data_index = form[index_field_name].value;
            var required = attribute_data[product_id][attr_data_index]
                              [array_index(attribute_fields,'required')];
            if (required != '1') {
               attr_index++;
               var field_name = 'attr_' + attr_index + '_' + loop;
               continue;
            }
            var attr_field = form[field_name];
            if (attr_field.selectedIndex < 1) {
               var attr_name = attr_field.options[0].text;
               if (document.AddOrder) {
                  alert('You must select a ' + attr_name);
                  attr_field.focus();   return false;
               }
               else if (! confirm(attr_name + ' is a required field, do you ' +
                                  'wish to continue anyway?')) {
                  attr_field.focus();   return false;
               }
            }
         }
         attr_index++;
         var field_name = 'attr_' + attr_index + '_' + loop;
      }
   }
   return true;
}

function validate_payment()
{
   var form = get_order_form();
   if (! form.payment_type) return true;
   var payment_type = get_selected_list_value('payment_type');
   //payment type check
//   function check_type(array){
//       var i = 0;
//       Array.from(array).forEach(function(el){
//           if(el.style.display === 'none'){i++;}
//       });
//       
//       if(i===array.length){return false;}else{return true;}
//   }
//   
//   var CreditCardRows = document.querySelectorAll("[id*=cc_row_]");
//   var CheckRows = document.querySelectorAll("[id*=check_row_]");
////   Check that all Credit card rows filled
//   if(check_type(CreditCardRows)){
//     if(!form.card_number.value){
//        alert('You must enter a Credt Card Number');
//        form.card_number.focus();
//     }  
//     if(!form.card_cvv.value){
//        alert('You must enter a Credt Card cvv Number');
//        form.card_cvv.focus();
//     }
//     if(!form.card_name.value){
//        alert('You must enter a Credt Card Name');
//        form.card_name.focus();         
//     }
//   }
////   Check that Check fields filled
//   if(check_type(CheckRows)){
//     if(!form.check_number.value){
//        alert('You must enter a Check number');
//        form.check_number.focus();         
//     } 
//   }   
   
   var payment_amount = form.payment_amount.value;
   if (payment_amount == '0') payment_amount = '';
   if ((! payment_type) && (! payment_amount)) return true;//Uncomment if 
  // you'll need to apply without payment method
   if (payment_type && payment_amount) return true;
   if (payment_type) {
      alert('You must enter a Payment Amount');
      form.payment_amount.focus();
   }
   else {
      alert('You must select a Payment Method');
      form.payment_type.focus();
   }
}

function adding_payment()
{
   var form = get_order_form();
   if (! form.payment_type) return false;
   var payment_type = get_selected_list_value('payment_type');
   var payment_amount = form.payment_amount.value;
   if (payment_amount == '0') payment_amount = '';
   if (payment_type && payment_amount) return true;
   return false;
}

function update_file_upload_fields()
{
   var form = get_order_form();
   if (typeof(attribute_fields) != 'undefined')
      var admin_type_index = array_index(attribute_fields,'admin_type');
   for (var loop = 0;  loop < num_product_ids;  loop++) {
      var id_field = form['product_id_' + loop];
      if ((! id_field) || (! id_field.value)) continue;
      var product_id = id_field.value;
      var attributes = attribute_data[product_id];
      var attr_index = 0;
      for (var index in attributes) {
         var attribute_info = attributes[index];
         var attribute_type = attribute_info[admin_type_index];
         if (attribute_type == '3') {
            var field_name = 'attr_' + attr_index + '_' + loop;
            var attr_value = '';
            var uploaded_files_div = document.getElementById(field_name +
                                                             '_div');
            if (uploaded_files_div) {
               var upload_list = uploaded_files_div.getElementsByTagName('ul')[0];
               for (upload_row in upload_list.children) {
                  if (typeof(upload_list.children[upload_row].filename) ==
                      'undefined') continue;
                  var filename = upload_list.children[upload_row].filename;
                  if (filename) {
                     var cart_id = upload_list.children[upload_row].cart_id;
                     if (attr_value) attr_value += '^';
                     attr_value += '{#' + cart_id + '#}' + filename;
                  }
               }
            }
            form[field_name].value = attr_value;
         }
         attr_index++;
      }
    }
}

function shipping_amount_onblur(shipping_amount_field)
{
   var tax_cell = document.getElementById('tax_cell');
   var tax = parse_amount(tax_cell.innerHTML);
   var amount_value = shipping_amount_field.value;
   if ((amount_value.indexOf('$') != -1) ||
       (amount_value.indexOf(',') != -1)) {
      amount_value = amount_value.replace(/\$/g,"");
      amount_value = amount_value.replace(/,/g,"");
      shipping_amount_field.value = amount_value;
   }
   var shipping = parse_amount(shipping_amount_field.value);
   update_total_fields(tax,shipping);
}

var adding_order = false;

function process_add_order()
{
   if (adding_order) {
      alert('New Order is already being processed');   return;
   }
   if (! validate_attributes()) return;
   if (! validate_payment()) return;
   adding_order = true;
   update_file_upload_fields();
   if (typeof(custom_process_add_order) != 'undefined')
      custom_process_add_order();
   if (adding_payment() &&
       (typeof(module_process_dialog_payment) != 'undefined') &&
       module_process_dialog_payment(null,null,null)) return;
   top.enable_current_dialog_progress(true);
   submit_form_data('orders.php','cmd=processaddorder',document.AddOrder,
                    finish_add_order,60);
}

function show_invalid_address(prefix)
{
    if (prefix == 'bill') {
       var label = 'Billing Address';   var skip_field = 'SkipBillValidate';
    }
    else {
       var label = 'Shipping Address';   var skip_field = 'SkipShipValidate';
    }
    var msg = 'We were unable to validate the '+label +
       '.  Do you want to go back and make changes? Click Ok to make changes, ' +
       'otherwise, click Cancel to continue with the entered address.';
    var response = confirm(msg);
    if (! response) {
       document.AddOrder.UseOwnAddress.value = 'Yes';
       document.AddOrder[skip_field].value = 'Yes';
       adding_order = false;   process_add_order();
    }
}

function show_address_option(prefix,address)
{
    if (prefix == 'bill') {
       var label = 'Billing Address';   var skip_field = 'SkipBillValidate';
    }
    else {
       var label = 'Shipping Address';   var skip_field = 'SkipShipValidate';
    }
    var msg = 'The '+label +
       ' you provided matched the following suggested address:\n\n' +
       address['match'] + '\n\nDo you want to use the suggested address? ' +
       'Click Ok to use the suggested address, otherwise, click Cancel to ' +
       'use the entered address.';
    var response = confirm(msg);
    document.AddOrder[skip_field].value = 'Yes';
    if (response) {
       document.AddOrder[prefix + '_address1'].value = address['address1'];
       document.AddOrder[prefix + '_address2'].value = address['address2'];
       document.AddOrder[prefix + '_city'].value = address['city'];
       set_list_value(document.AddOrder[prefix + '_state'],address['state']);
       document.AddOrder[prefix + '_zipcode'].value = address['zipcode'];
    }
    else document.AddOrder.UseOwnAddress.value = 'Yes';
    adding_order = false;   process_add_order();
}

function finish_add_order(ajax_request)
{
   ajax_request.show_alert = false;
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var message = ajax_request.parse_value(ajax_request.request.responseText,
                                             'message');
      alert(message);
      top.get_content_frame().reload_grid();
      adding_order = false;
      top.close_current_dialog();
   }
   else if (status == 406) {
      var msg = ajax_request.parse_value(ajax_request.request.responseText,'message');
      if (msg.substring(0,6) == 'QTYERR') {
         var msg_info = msg.split('|');
         var item_row = msg_info[1];
         msg = msg_info[2];
         var cell = document.getElementById('product_name_cell_'+item_row);
         if (cell.innerHTML.indexOf('qty_error') == -1) {
            var div_pos = cell.innerHTML.indexOf('<div');
            if (div_pos != -1)
               cell.innerHTML = cell.innerHTML.substring(0,div_pos) + 
                  '<span class="qty_error">' + msg + '</span>' +
                  cell.innerHTML.substring(div_pos);
         }
         var qty_field = document.getElementById('product_qty_'+item_row);
         qty_field.focus();
         alert(msg);   log_server(msg);
         document.AddOrder.SkipBillValidate.value = '';
         document.AddOrder.SkipShipValidate.value = '';
      }
      else ajax_request.display_error();
   }
   else if (status == 409) {
      var msg = ajax_request.parse_value(ajax_request.request.responseText,'message');
      var data = JSON.parse(msg);
      window.setTimeout(function() {
         switch (data['code']) {
            case 'BillAddress': show_invalid_address('bill');   break;
            case 'ShipAddress': show_invalid_address('ship');   break;
            case 'SuggestedBillAddress': show_address_option('bill',data);
                 break;
            case 'SuggestedShipAddress': show_address_option('ship',data);
                 break;
         }
         document.AddOrder.SkipBillValidate.value = '';
         document.AddOrder.SkipShipValidate.value = '';
      },0);
   }
   else {
      ajax_request.display_error();
      document.AddOrder.SkipBillValidate.value = '';
      document.AddOrder.SkipShipValidate.value = '';
   }
   adding_order = false;
}

function view_order()
{
   var label = get_order_label();
   if (orders_grid.table._num_rows < 1) {
      alert('There are no ' + label + 's to view');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one ' + label + ' to view');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var dialog_name = 'view_order_'+id;
   var url = '../cartengine/orders.php?cmd=vieworder&id=' + id +
             '&ordertype=' + order_type;
   top.create_dialog(dialog_name,null,null,750,460,false,url,null);
}

function view_order_link(id,dest_type)
{
   var dialog_name = 'view_order_link_' + id + '_' + (new Date()).getTime();
   var url = '../cartengine/orders.php?cmd=vieworder&id=' + id +
             '&ordertype=' + dest_type;
   top.create_dialog(dialog_name,null,null,750,460,false,url,null);
}

function view_rma(rma_id)
{
   var dialog_name = 'view_rma_'+rma_id;
   top.create_dialog(dialog_name,null,null,750,300,false,
                     '../cartengine/rmas.php?cmd=viewrma&id=' + rma_id,null);
}

function edit_customer(id)
{
   top.create_dialog('edit_customer',null,null,1000,400,false,
      '../cartengine/customers.php?cmd=editcustomer&id=' + id,null);
}

function edit_order(order_id,copying)
{
   if (typeof(order_id) == 'undefined') order_id = 0;
   if (typeof(copying) == 'undefined') copying = false;
   var label = get_order_label();
   if ((orders_grid.table._num_rows < 1) && (! order_id)) {
      alert('There are no ' + label + 's to edit');   return;
   }
   if ((orders_grid.grid.getSelectedRows().length > 1) && (! order_id)) {
      alert('You must select only one ' + label + ' to edit');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   orders_grid.table.save_position();
   var url = '../cartengine/orders.php?cmd=editorder&ordertype=' + order_type +
             '&id=';
   if (order_id) url += order_id;
   else url += id;
   if (copying) url += '&copying=true';
   top.create_dialog('edit_order',null,null,995,700,false,url,null);
}

function select_country(country_list,prefix)
{
    var country = 0;
    if (country_list.selectedIndex != -1)
       country = country_list.options[country_list.selectedIndex].value;
    var city_prompt = document.getElementById(prefix + 'city_prompt');
    var state_row = document.getElementById(prefix + 'state_row');
    var province_row = document.getElementById(prefix + 'province_row');
    var canada_province_row = document.getElementById(prefix + 'canada_province_row');
    var zip_cell = document.getElementById(prefix + 'zip_cell');
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

var current_order_item = -1;

function item_row_mouseover(row)
{
   row.style.backgroundColor = '#E7F7FF';
}

function item_row_mouseout(row)
{
   if (row.id == current_order_item) row.style.backgroundColor = '#72B8D8';
   else row.style.backgroundColor = '';
}

function item_row_click(row)
{
   if (current_order_item != -1) {
      var old_row = document.getElementById(current_order_item);
      if (old_row) old_row.style.backgroundColor = '';
   }
   current_order_item = row.id;
   row.style.backgroundColor = '#72B8D8';
}

function update_order(print_function,template,label)
{
   if (! validate_attributes()) return;
   if (! validate_payment()) return;
   update_file_upload_fields();
   if (! print_function) {
      if (typeof(template) == 'undefined') var template = '';
   }
   else if (print_function == 'print') {
      var options_div = document.getElementById('print_options');
      options_div.style.display = 'inline';   return;
   }
   else if (typeof(print_function) == 'object') {
      var options_div = document.getElementById('print_options');
      var template = print_function.options[print_function.selectedIndex].value;
      options_div.style.display = 'none';
      if (! template) return;
      update_and_print_label =
         print_function.options[print_function.selectedIndex].text;
   }
   else var template = print_function;
   if (template == 'shipping_label') {
      var status = get_selected_list_value('status');
      if (status != 1) {
         var response = confirm('Printing the shipping label will switch ' +
            'the order status to Shipped, do you still wish to print the ' +
            'shipping label?');
         if (! response) return;
      }
   }
   if (typeof(custom_update_order) != 'undefined') custom_update_order();
   if (ajax_request_pending) {
      window.setTimeout(function() { update_order(print_function); },100);
      return;
   }
   cancel_copy_order = false;
   if (adding_payment() &&
       (typeof(module_process_dialog_payment) != 'undefined') &&
       module_process_dialog_payment(print_function,template,label)) return;
   top.enable_current_dialog_progress(true);
   update_and_print_template = template;
   if (typeof(label) != 'undefined') update_and_print_label = label;
   submit_form_data('orders.php','cmd=updateorder',document.EditOrder,
                    finish_update_order);
}

function finish_update_order(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      if (update_and_print_template) {
         var id = document.EditOrder.id.value;
         if (update_and_print_template == 'shipping_label')
            shipping_label(id);
         else print_order(null,id,update_and_print_template,
                          update_and_print_label);
      }
      var frame = top.get_content_frame();
      if (typeof(frame.reload_grid) == 'function') frame.reload_grid();
      top.close_current_dialog();
   }
   else if (status == 428) {
      var frame = top.get_content_frame();
      if (typeof(frame.reload_grid) == 'function') frame.reload_grid();
      ajax_request.display_error();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
   update_and_print_template = null;
}

function delete_order()
{
   var label = get_order_label();
   if (orders_grid.table._num_rows < 1) {
      alert('There are no ' + label + 's to delete');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one ' + label + ' to delete');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to delete this ' +
                          label + '?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   orders_grid.table.save_position();
   var fields = 'cmd=deleteorder&id=' + id + '&ordertype=' + order_type;
   call_ajax('orders.php',fields,true,finish_delete_order);
}

function finish_delete_order(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

var current_ship_qtys = null;

function get_ship_qtys()
{
   var form = document.AddPartialShipment;
   var qtys = '';
   for (i=0;  i < form.elements.length;  i++) {
      if (form.elements[i].name.substr(0,4) == 'qty_') {
         var field_value = parseInt(form.elements[i].value);
         if (isNaN(field_value)) field_value = 0;
         if (qtys) qtys += '|';
         qtys += field_value;
      }
   }
   return qtys;
}

function add_partial_shipment_onclose(user_close)
{
   if (partial_shipment_request) partial_shipment_request.abort();
}

function add_partial_shipment_onload()
{
   top.set_current_dialog_onclose(add_partial_shipment_onclose);
   current_ship_qtys = get_ship_qtys();
}

function add_partial_shipment()
{
   if (orders_grid.table._num_rows < 1) {
      alert('There are no orders to partially ship');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one order to partial ship');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var status = orders_grid.grid.getCellValue(order_status_column,grid_row);
   if (status == 'Shipped Order') {
      alert('That Order has already been shipped');   return;
   }
   var url = '../cartengine/orders.php?cmd=addpartialshipment&id=' + id;
   top.create_dialog('partial_ship',null,null,820,175,false,url,null);
}

function process_add_partial_shipment()
{
   var form = document.AddPartialShipment;
   var qtys = 0;
   for (i=0;  i < form.elements.length;  i++) {
      if (form.elements[i].name.substr(0,4) == 'qty_') {
         var field_value = parseInt(form.elements[i].value);
         if (isNaN(field_value)) field_value = 0;
         qtys += field_value;
      }
   }
   if (qtys == 0) {
      alert('You must specify a Qty to Ship');   return;
   }
   top.enable_current_dialog_progress(true);
   submit_form_data('orders.php','cmd=processaddpartialshipment',
                    document.AddPartialShipment,finish_add_partial_shipment);
}

function finish_add_partial_shipment(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function cancel_partial_shipment()
{
   top.close_current_dialog();
}

function update_partial_shipping_info()
{
   var form = document.AddPartialShipment;
   for (i=0;  i < form.elements.length;  i++) {
      if (form.elements[i].name.substr(0,4) == 'qty_') {
         var field_value = parseInt(form.elements[i].value);
         if (isNaN(field_value)) {
            if (! form.elements[i].value) continue;
            alert('Invalid Quantity');   form.elements[i].focus();   return;
         }
         var item_id = form.elements[i].name.substr(4);
         var max_qty = form['maxqty_'+item_id].value;
         if (field_value > max_qty) {
            alert('Quantity must be '+max_qty+' or less');
            form.elements[i].focus();   return;
         }
      }
   }

   new_ship_qtys = get_ship_qtys();
   if (new_ship_qtys == current_ship_qtys) return;
   current_ship_qtys = new_ship_qtys;
   submit_form_data('orders.php','cmd=lookuppartialshipping',
                    document.AddPartialShipment,
                    finish_lookup_partial_shipping);
   partial_shipment_request = current_ajax_request;
}

function finish_lookup_partial_shipping(ajax_request)
{
   if (ajax_request.state != 4) return;
   var status = ajax_request.get_status();
   partial_shipment_request = null;
   if (status != 200) return;
   eval(ajax_request.request.responseText);
   if (shipping_error) alert('Shipping Error: ' + shipping_error);
   var shipping_carrier_cell = document.getElementById('shipping_carrier_cell');
   var shipping_method_cell = document.getElementById('shipping_method_cell');
   if (shipping_options.length == 0) {
      shipping_carrier_cell.innerHTML = '';
      shipping_method_cell.innerHTML = '';
   }
   else {
      var active_modules = new Array();
      for (var index in shipping_options) {
         shipping_option = shipping_options[index];
         var option_info = shipping_option.split("|");
         active_modules[option_info[0]] = true;
      }
      var current_module = get_shipping_value(document.AddPartialShipment.shipping_carrier);
      var html = '<select name="shipping_carrier" class="select" onChange="' +
                 'select_shipping_carrier();">';
      html += '<option value=""></option>';
      for (var module in active_modules) {
         shipping_option = shipping_options[index];
         var option_info = shipping_option.split("|");
         html += '<option value="' + module + '"';
         if (module == current_module) html += ' selected';
         html += '>' + shipping_modules[module] + '</option>';
      }
      html += '</select>';
      shipping_carrier_cell.innerHTML = html;
      loading_fees = false;
      select_shipping_carrier();
   }
}

function click_edit_order_button(tab_name,order_id,copying)
{
    var frame = top.window.frames[tab_name + '_iframe'];
    var button = frame.document.getElementById('edit_order');
    if (! button) {
       window.setTimeout(function() {
          click_edit_order_button(tab_name,order_id);
       },100);
    }
    else {
//     button.click();  // Need to find correct grid row, select, and click button
       frame.reload_grid();
       frame.edit_order(order_id,copying);
    }
}

function edit_copied_order(order_id,dest_order_type,copying)
{
   switch (dest_order_type) {
      case ORDER_TYPE: var tab_name = 'orders';   break;
      case QUOTE_TYPE: var tab_name = 'quotes';   break;
      case INVOICE_TYPE: var tab_name = 'invoices';   break;
      case SALESORDER_TYPE: var tab_name = 'salesorders';   break;
   }
   if (current_tab != tab_name) {
      var tab = top.document.getElementById(tab_name);
      top.tab_click(tab.firstElementChild,'../cartengine/orders.php?ordertype=' +
                    dest_order_type,false,true);
   }
   click_edit_order_button(tab_name,order_id,copying);
}

function copy_order()
{
   var label = get_order_label();
   if (orders_grid.table._num_rows < 1) {
      alert('There are no ' + label + 's to copy');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one ' + label + ' to copy');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to copy this ' + label + '?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   orders_grid.table.save_position();
   var fields = 'cmd=copyorder&id=' + id + '&ordertype=' + order_type;
   call_ajax('orders.php',fields,true,finish_copy_order);
}

function finish_copy_order(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 200) {
      var order_id = -1;
      eval(ajax_request.request.responseText);
      edit_copied_order(order_id,order_type,true);
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function convert_quote()
{
   if (orders_grid.table._num_rows < 1) {
      alert('There are no quotes to convert');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one quote to convert');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to convert this quote?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   orders_grid.table.save_position();
   var fields = 'cmd=convertquote&id=' + id;
   call_ajax('orders.php',fields,true,finish_convert_quote);
}

function finish_convert_quote(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 200) {
      var order_id = -1;
      eval(ajax_request.request.responseText);
      if (enable_salesorders) var dest_order_type = SALESORDER_TYPE;
      else var dest_order_type = ORDER_TYPE;
      edit_copied_order(order_id,dest_order_type,false);
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function generate_invoice()
{
   if (order_type == ORDER_TYPE) var order_label = 'order';
   else if (order_type == SALESORDER_TYPE) var order_label = 'sales order';
   else var order_label = 'quote';
   if (orders_grid.table._num_rows < 1) {
      alert('There are no ' + order_label + 's to generate an invoice from');
      return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one ' + order_label +
            ' to generate an invoice from');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to generate an invoice ' +
                          'from this ' + order_label + '?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   orders_grid.table.save_position();
   var num_items = orders_grid.grid.getCellText(num_items_column,grid_row);
//   if (num_items > 1) {
      var url = '../cartengine/orders.php?cmd=generatepartialinvoice&id=' +
                id + '&ordertype=' + order_type;
      top.create_dialog('generate_invoice',null,null,820,100,false,url,null);
/*
   }
   else {
      var fields = 'cmd=generateinvoice&id=' + id + '&ordertype=' + order_type;
      call_ajax('orders.php',fields,true,finish_generate_invoice);
   }
*/
}

function process_generate_invoice()
{
   var form = document.GenerateInvoice;
   var qtys = 0;
   for (i=0;  i < form.elements.length;  i++) {
      if (form.elements[i].name.substr(0,4) == 'qty_') {
         var qty = parseFloat(form.elements[i].value);
         if (isNaN(form.elements[i].value - qty)) {
            alert('Invalid quantity');   form.elements[i].focus();   return;
         }
         qtys += qty;
         var item_id = form.elements[i].name.substr(4);
         var max_qty = document.GenerateInvoice['maxqty_' + item_id].value;
         if (qty > max_qty) {
            alert('You can not specify a quantity higher than ' + max_qty);
            form.elements[i].focus();   return;
         }
      }
   }
   if (qtys == 0) {
      alert('You must specify a Qty to Invoice');   return;
   }
   top.enable_current_dialog_progress(true);
   submit_form_data('orders.php','cmd=processgenerateinvoice',
                    document.GenerateInvoice,finish_generate_invoice);
}

function finish_generate_invoice(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 200) {
      var invoice_id = -1;
      eval(ajax_request.request.responseText);
      edit_copied_order(invoice_id,INVOICE_TYPE,false);
      top.close_dialog('generate_invoice');
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function select_order()
{
   if (orders_grid.table._num_rows < 1) {
      alert('There are no orders to select');   return;
   }
   var grid = orders_grid.grid;
   var grid_row = grid.getCurrentRow();
   var order_data = {
      id: grid.getCellText(0,grid_row),
      order_number: grid.getCellText(1,grid_row),
      email: grid.getCellText(2,grid_row),
      fname: grid.getCellText(3,grid_row),
      lname: grid.getCellText(4,grid_row),
      ship_state: grid.getCellText(5,grid_row),
      ship_zipcode: grid.getCellText(6,grid_row),
      order_date: grid.getCellValue(7,grid_row),
      status: grid.getCellText(8,grid_row),
      mname: grid.getCellText(9,grid_row),
      company: grid.getCellText(10,grid_row),
      bill_address1: grid.getCellText(11,grid_row),
      bill_address2: grid.getCellText(12,grid_row),
      bill_city: grid.getCellText(13,grid_row),
      bill_state: grid.getCellText(14,grid_row),
      bill_zipcode: grid.getCellText(15,grid_row),
      bill_country: grid.getCellText(16,grid_row),
      bill_phone: grid.getCellText(17,grid_row),
      bill_fax: grid.getCellText(18,grid_row),
      bill_mobile: grid.getCellText(19,grid_row),
      ship_profilename: grid.getCellText(20,grid_row),
      ship_shipto: grid.getCellText(21,grid_row),
      ship_company: grid.getCellText(22,grid_row),
      ship_address1: grid.getCellText(23,grid_row),
      ship_address2: grid.getCellText(24,grid_row),
      ship_city: grid.getCellText(25,grid_row),
      ship_country: grid.getCellText(26,grid_row),
      ship_address_type: grid.getCellText(27,grid_row)
   };

   var iframe = top.get_dialog_frame(select_frame).contentWindow;
   iframe.change_order(order_data);
   top.close_current_dialog();
}

function add_reorder()
{
   if (orders_grid.table._num_rows < 1) {
      alert('There are no orders to reorder');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one order to reorder');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to reorder this order?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   orders_grid.table.save_position();
   call_ajax("orders.php","cmd=reorder&id=" + id,true,finish_add_reorder);
}

function finish_add_reorder(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function print_invoice(ids)
{
   if (ids) num_selected = 1;
   else {
      if (orders_grid.table._num_rows < 1) {
         alert('There are no orders to view');   return;
      }
      var selected_rows = orders_grid.grid._rowsSelected;
      var ids = '';   var num_selected = 0;
      for (var grid_row in selected_rows) {
         if (grid_row == '$') continue;
         var id = orders_grid.grid.getCellText(0,grid_row);
         if (ids != '') ids += ',';
         ids += id;
         num_selected++;
      }
      if (num_selected == 0) {
         alert('You must select at least one order to print invoices for');
         return;
      }
   }
   var url = '../cart/view-order.php?';
   if (num_selected == 1) url += 'id='+ids;
   else url += 'ids='+ids;
   url += '&print=true&internal=true';
   if (typeof(custom_print_invoice_url) != "undefined")
      url = custom_print_invoice_url(url);
   var window_opts = 'toolbar=no,location=no,directories=no,status=no,' +
                     'menubar=no,scrollbars=yes,resizable=yes,copyhistory=no';
   try {
      var viewwin = window.open(url,'print_invoice',window_opts);
      if (! viewwin)
         alert('Unable to open window, please enable popups for this domain');
   } catch(e) {
      alert('Unable to open window, please enable popups for this domain');
   }
}

function print_order(template_list,ids,template,label)
{
   var options_div = document.getElementById('print_options');
   if (! template_list) {
      if (typeof(template) == 'undefined') {
         options_div.style.display = '';   return;
      }
   }
   else {
      var template = template_list.options[template_list.selectedIndex].value;
      var label = template_list.options[template_list.selectedIndex].text;
      options_div.style.display = 'none';
      template_list.selectedIndex = 0;
   }
   if (! template) return;

   if (ids) num_selected = 1;
   else {
      if (orders_grid.table._num_rows < 1) {
         alert('There are no orders to print');   return;
      }
      var selected_rows = orders_grid.grid._rowsSelected;
      var ids = '';   var num_selected = 0;
      for (var grid_row in selected_rows) {
         if (grid_row == '$') continue;
         var id = orders_grid.grid.getCellText(0,grid_row);
         if (ids != '') ids += ',';
         ids += id;
         num_selected++;
      }
      if (num_selected == 0) {
         alert('You must select at least one order to print');   return;
      }
   }
   if (template == '~invoice')
      var url = '../cart/view-order.php?print=true&internal=true&ordertype=' +
         order_type;
   else if (template == '~packingslip')
      var url = '../cart/view-order.php?print=true&slip=true&ordertype=' +
                order_type;
   else var url = 'orders.php?cmd=printorder&Template=' + template +
        '&Label='+encodeURIComponent(label) + '&ordertype=' + order_type;
   if (num_selected == 1) url += '&id='+ids;
   else url += '&ids='+ids;
   var window_opts = 'toolbar=no,location=no,directories=no,status=no,' +
                     'menubar=no,scrollbars=yes,resizable=yes,copyhistory=no';
   try {
      var viewwin = window.open(url,'print_order',window_opts);
      if (! viewwin)
         alert('Unable to open window, please enable popups for this domain');
   } catch(e) {
      alert('Unable to open window, please enable popups for this domain');
   }
}

function shipping_label(ids)
{
   if (ids) num_selected = 1;
   else {
      if (orders_grid.table._num_rows < 1) {
         alert('There are no orders to generate shipping labels for');
         return;
      }
      var selected_rows = orders_grid.grid._rowsSelected;
      var ids = '';   var num_selected = 0;   var status_warning = false;
      for (var grid_row in selected_rows) {
         if (grid_row == '$') continue;
         var id = orders_grid.grid.getCellText(0,grid_row);
         var status = orders_grid.grid.getCellValue(order_status_column,
                                                    grid_row);
         if (status != 'Shipped Order') status_warning = true;
         if (ids != '') ids += ',';
         ids += id;
         num_selected++;
      }
      if (num_selected == 0) {
         alert('You must select at least one order to print packing slips for');
         return;
      }
      if (status_warning) {
         if (num_selected == 1)
            var confirm_text = 'Printing the shipping label will switch ' +
               'the order status to Shipped, do you still wish to print the ' +
               'shipping label?';
         else var confirm_text = 'Printing shipping labels will switch ' +
                 'the order status to Shipped, do you still wish to print ' +
                  'the shipping labels?';
         var response = confirm(confirm_text);
         if (! response) return;
      }
   }
   ids = ids.split(',');
   for (var index in ids) {
      var id = ids[index];
      var url = 'orders.php?cmd=shippinglabel&id='+id;
      var window_opts = 'toolbar=no,location=no,directories=no,status=no,' +
                        'menubar=no,scrollbars=yes,resizable=yes,copyhistory=no';
      try {
         var dialog_name = 'shipping_label_' + id + '_' + (new Date()).getTime();
         var viewwin = window.open(url,dialog_name,window_opts);
         if (! viewwin)
            alert('Unable to open window, please enable popups for this domain');
      } catch(e) {
         alert('Unable to open window, please enable popups for this domain');
      }
   }
   if (viewwin) {
      viewwin.onload = function() {
         var frame = top.get_content_frame();
         frame.orders_grid.table.save_position();
         frame.reload_grid();
      };
   }
}

function cancel_shipment()
{
   if (orders_grid.table._num_rows < 1) {
      alert('There are no orders to cancel shipments for');   return;
   }
   if (orders_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one order to cancel shipments for');   return;
   }
   var grid_row = orders_grid.grid.getCurrentRow();
   var id = orders_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to cancel the shipment for this order?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   call_ajax("orders.php","cmd=cancelshipment&id=" + id,true,finish_cancel_shipment);
}

function finish_cancel_shipment(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) alert('Shipment Cancelled');
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function enable_order_item_buttons(enable_flag)
{
   var add_order_item_button = document.getElementById('add_order_item');
   var edit_order_item_button = document.getElementById('edit_order_item');
   if (! edit_order_item_button) return;
   var delete_order_item_button = document.getElementById('delete_order_item');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   if (add_order_item_button) add_order_item_button.style.display = display_style;
   edit_order_item_button.style.display = display_style;
   delete_order_item_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
   if (content_id == 'items_content') enable_order_item_buttons(true);
   else enable_order_item_buttons(false);
   tab_click(tab,content_id);
}

function convert_data(col,row,text)
{
   if (col == order_status_column) {
      var status = parse_int(text);
      if (typeof(order_status_values[status]) == 'undefined') return text;
      return order_status_values[status];
   }
   if (col == payment_status_column) {
      var payment_status = parse_int(text);
      if (typeof(payment_status_values[payment_status]) == 'undefined')
         return text;
      return payment_status_values[payment_status];
   }
   if ((col == total_column) || (col == balance_due_column)) {
      if (text == '') return text;
      var amount_info = text.split("^");
      if (amount_info.length == 2) {
         var currency = amount_info[0];
         if ((col == balance_due_column) && (amount_info[1] == 0.00))
            return '';
         return format_amount(amount_info[1],currency,false);
      }
      else return format_amount(text);
   }
   if (col == order_date_column) {
      if (text == '') return text;
      var order_date = new Date(parse_int(text) * 1000);
      return order_date.format('mmmm d, yyyy h:MM tt');
   }
   return text;
}

function search_orders()
{
   var query = document.SearchForm.query.value;
   if (! query) {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'').trim();
   top.display_status('Search','Searching Orders...',350,100,null);
   window.setTimeout(function() {
      if (typeof(reset_order_search) != 'undefined') reset_order_search(true);
      if (typeof(order_search_where) != 'undefined')
         var where = order_search_where.replace(/\$query\$/g,query);
      else {
         if (typeof(order_search_fields) == 'undefined') {
            var order_search_fields = ['o.email','o.fname','o.lname',
               'o.company','b.phone','b.fax','b.mobile','o.id',
               'o.id|parent|order_shipping|address1',
               'o.id|parent|order_shipping|address2',
               'o.id|parent|order_shipping|city',
               'o.id|parent|order_shipping|state',
               'o.id|parent|order_shipping|zipcode',
               'o.id|parent|order_items|product_name',
               'concat_ws(" ",o.fname,o.lname)',
               'concat_ws(" ",o.fname,o.mname,o.lname)'];
             if (order_type == ORDER_TYPE) {
                order_search_fields.push('o.order_number');
                order_search_fields.push('o.external_source');
                order_search_fields.push('o.external_id');
             }
         }
         var where = '';   var first_where = true;
         for (var index in order_search_fields) {
            if (first_where) first_where = false;
            else where += ' or ';
            where += '(';
            var field = order_search_fields[index];
            var field_parts = field.split('|');
            if (field_parts.length > 1) {
               where += field_parts[0] + ' in (select ' + field_parts[1] +
                        ' from ' + field_parts[2] + ' where ';
               var field_name = field_parts[3];
            }
            else var field_name = field_parts[0];
            if (typeof(encrypted_fields) != 'undefined') {
               var field_name_parts = field_name.split('.');
               if (field_name_parts.length == 2)
                  var name = field_name_parts[1];
               else var name = field_name;
               if (array_index(encrypted_fields,name) != -1)
                  where += '%DECRYPT%(' + field_name + ')';
               else where += field_name;
            }
            else where += field_name;
            where += ' like "%' + query + '%"';
            if ((field_parts.length > 1) && (field_parts[1] == 'parent'))
               where += ' and (parent_type=' + order_type + ')';
            where += ')';
            if (field_parts.length > 1) where += ')';
         }
      }
      var website_where = get_website_where();
      if (website_where) {
         if (where) where = '(' + where + ') and ';
         where += website_where;
      }
      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      orders_grid.set_where(where);
      orders_grid.table.reset_data(false);
      orders_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status('Search','Loading All Orders...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      if (typeof(order_where) != 'undefined') var where = order_where;
      else var where = get_website_where();
      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      orders_grid.set_where(where);
      orders_grid.table.reset_data(false);
      orders_grid.grid.refresh();
      if (typeof(reset_order_search) != 'undefined') reset_order_search(false);
      top.remove_status();
   },0);
}

function filter_orders()
{
    filter_where = '';
    var status_list = document.getElementById('status');
    if (status_list.selectedIndex == -1) var status = '';
    else var status = status_list.options[status_list.selectedIndex].value;
    if (status) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(status=' + status + ')';
    }
    var balance_due_only = document.getElementById('balance_due_only');
    if (balance_due_only && balance_due_only.checked) {
       if (filter_where) filter_where += ' and ';
       filter_where += '((not isnull(balance_due)) or (balance_due!=0.00))';
    }
    if (typeof(update_order_filters) != 'undefined')
       filter_where = update_order_filters(filter_where);
    if (typeof(get_custom_order_filters) != 'undefined')
       filter_where += get_custom_order_filters();
    var query = document.SearchForm.query.value;
    if (query) search_orders();
    else {
       top.display_status('Search','Searching Orders...',350,100,null);
       window.setTimeout(function() {
          orders_grid.set_where(filter_where);
          orders_grid.table.reset_data(false);
          orders_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

function add_order_item()
{
   var order_id = document.EditOrder.id.value;
   var url = '../cartengine/orders.php?cmd=addorderitem&Order=' + order_id;
   top.create_dialog('add_order_item',null,null,590,130,false,url,null);
}

function process_add_order_item()
{
   top.enable_current_dialog_progress(true);
   submit_form_data("orders.php","cmd=processaddorderitem",
                    document.EditOrderItem,finish_add_order_item);
}

function finish_add_order_item(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.reload_dialog('edit_order');
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_order_item()
{
   if (current_order_item == -1) {
      alert('You must select an order item to delete');   return;
   }
   var order_id = document.EditOrder.id.value;
   top.create_dialog('edit_order_item',null,null,580,120,false,
                     '../cartengine/orders.php?cmd=editorderitem&id=' +
                     current_order_item + '&Order=' + order_id,null);
}

function update_order_item()
{
   top.enable_current_dialog_progress(true);
   submit_form_data("orders.php","cmd=updateorderitem",
                    document.EditOrderItem,finish_update_order_item);
}

function finish_update_order_item(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.reload_dialog('edit_order');
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_order_item()
{
   if (current_order_item == -1) {
      alert('You must select an order item to delete');   return;
   }
   var name_field = document.getElementById('item_name_'+current_order_item)
   if (name_field.innerText) var item_name = name_field.innerText;
   else if (name_field.textContent) var item_name = name_field.textContent;
   else var item_name = name_field.text;
   var response = confirm('Are you sure you want to delete the "' +
                          item_name + '" order item?');
   if (! response) return;
   var order_id = document.EditOrder.id.value;
   top.enable_current_dialog_progress(true);
   call_ajax("../cartengine/orders.php","cmd=deleteorderitem&id=" +
             current_order_item + '&Order=' + order_id,true,
             finish_delete_order_item);
}

function finish_delete_order_item(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) top.reload_dialog('edit_order');
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function send_to_vendors()
{
    if (orders_grid.table._num_rows < 1) {
       alert('There are no orders to send to vendors');   return;
    }
    var selected_rows = orders_grid.grid._rowsSelected;
    var ids = '';   var num_ids = 0;
    for (var grid_row in selected_rows) {
       if (grid_row == '$') continue;
       var id = orders_grid.grid.getCellText(0,grid_row);
       if (ids != '') ids += ',';
       ids += id;   num_ids++;
    }
    if (ids == '') {
       alert('You must select at least one order to send to vendors');
       return;
    }
    if (num_ids > 1)
       var response = confirm('Are you sure you want to send these orders to vendors?');
    else var response = confirm('Are you sure you want to send this order to vendors?');
    if (! response) return;

    top.display_status('Send To Vendors','Sending to Vendors...',350,100,null);
    orders_grid.table.save_position();
    call_ajax('orders.php','cmd=sendtovendors&ids=' + ids,true,
              finish_send_to_vendors,900);
}

function finish_send_to_vendors(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
    if (status == 201) reload_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function send_order_to_vendors(id)
{
    var response = confirm('Are you sure you want to send this order to vendors?');
    if (! response) return;

    top.display_status('Send To Vendors','Sending to Vendors...',350,100,null);
    call_ajax('orders.php','cmd=sendtovendors&ids=' + id,true,
              finish_send_order_to_vendors,900);
}

function finish_send_order_to_vendors(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
    if (status == 201) return;
    if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function manage_terms_onload()
{
    if (document.ManageTerms.Frame)
       top.set_current_dialog_onclose(manage_terms_onclose);
}

function manage_terms_onclose(user_close)
{
    var frame = document.ManageTerms.Frame.value;
    top.get_dialog_frame(frame).contentWindow.reset_terms_list();
}

function create_terms_grid()
{
    var grid_size = get_default_grid_size();
    terms_grid = new Grid('order_terms',grid_size.width,grid_size.height);
    terms_grid.set_columns(['ID','Name',]);
    terms_grid.set_column_widths([0,400]);
    var query = 'select id,name from order_terms';
    terms_grid.set_query(query);
    terms_grid.set_order_by('name');
    terms_grid.set_id('terms_grid');
    terms_grid.load(true);
    terms_grid.set_double_click_function(edit_term);
    terms_grid.display();
}

function reload_terms_grid()
{
    terms_grid.table.reset_data(true);
    terms_grid.grid.refresh();
    window.setTimeout(function() { terms_grid.table.restore_position(); },0);
}

function add_term(frame)
{
    if (terms_grid) terms_grid.table.save_position();
    var url = '../cartengine/orders.php?cmd=addterm';
    if (frame) url += '&Frame=' + frame;
    top.create_dialog('add_term',null,null,630,300,false,url,null);
}

function process_add_term()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('orders.php','cmd=processaddterm',document.AddTerm,
                     finish_add_term);
}

function finish_add_term(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var form = document.AddTerm;
       if (form.Frame)
          top.get_dialog_frame(form.Frame.value).contentWindow.
             insert_new_term(form.name.value,form.content.value)
       else top.get_dialog_frame('manage_terms').contentWindow.
               reload_terms_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_term()
{
    if (terms_grid.table._num_rows < 1) {
       alert('There are no order terms to edit');   return;
    }
    var grid_row = terms_grid.grid.getCurrentRow();
    var id = terms_grid.grid.getCellText(0,grid_row);
    terms_grid.table.save_position();
    top.create_dialog('edit_term',null,null,630,300,false,
                      '../cartengine/orders.php?cmd=editterm&id=' + id,
                      null);
}

function update_term()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('orders.php','cmd=updateterm',document.EditTerm,
                      finish_update_term);
}

function finish_update_term(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('manage_terms').contentWindow.reload_terms_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_term()
{
    if (terms_grid.table._num_rows < 1) {
       alert('There are no order terms to delete');   return;
    }
    var grid_row = terms_grid.grid.getCurrentRow();
    var id = terms_grid.grid.getCellText(0,grid_row);
    var term = terms_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the "' + term +
                           '" order term?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    terms_grid.table.save_position();
    call_ajax('orders.php','cmd=deleteterm&id=' + id,true,
              finish_delete_term);
}

function finish_delete_term(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_terms_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

var terms_doc;
var terms_win;
var terms_frame;
var terms = [];

function insert_terms_list(dialog_doc,dialog_win,frame)
{
    terms_doc = dialog_doc;
    terms_win = dialog_win;
    terms_frame = frame;
    dialog_doc.write('<select name="term_id" id="term_id" onChange="' +
                     'top.get_dialog_frame(\'' + frame +
                      '\').contentWindow.select_term(this);" ' +
                     'style="float:left; margin:5px 0px 0px 10px; ' +
                     'padding:3px">');
    terms_doc.write('<option value="">Insert Term</option>');
    terms_doc.write('<option value="new">Add New Term...</option>');
    terms_doc.write('<option value="manage">Manage Terms...</option>');
    terms_doc.write('</select>');
    call_ajax('../cartengine/orders.php','cmd=loadterms',true,
              finish_load_terms);
    load_terms_request = current_ajax_request;
}

function finish_load_terms(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    load_terms_request = null;
    var status = ajax_request.get_status();
    if (status != 200) {
       ajax_request_pending = false;   return;
    }

    if (! ajax_request.request.responseText) return;
    eval(ajax_request.request.responseText);
    if (typeof(error) != 'undefined') {
       alert(error);   return;
    }
    var list = terms_doc.getElementById('term_id');
    for (var index in terms) {
       var new_option = new Option(terms[index].name,terms[index].id);
       list.options[list.options.length] = new_option;
    }
}

function select_term(terms_list)
{
    var term_id = terms_list.options[terms_list.selectedIndex].value;
    terms_list.selectedIndex = 0;
    if (! term_id) return;
    if (term_id == 'new') {
       add_term(terms_frame);   return;
    }
    if (term_id == 'manage') {
       top.manage_order_terms(terms_frame);   return;
    }
    var terms_index = -1;
    for (var index in terms) {
       if (terms[index].id == term_id) {
          terms_index = index;   break;
       }
    }
    if (terms_index == -1) {
       alert('Term Not Found');   return;
    }
    var editor = terms_win.CKEDITOR.instances.content;
    editor.insertHtml(terms[terms_index].content);
}

function insert_new_term(name,content)
{
    var editor = terms_win.CKEDITOR.instances.content;
    editor.insertHtml(content);
    reset_terms_list();
}

function reset_terms_list()
{
    var list = terms_doc.getElementById('term_id');
    var num_options = list.options.length;
    for (var loop = num_options;  loop > 2;  loop--) {
       list.remove(loop);   list.options[loop] = null;
    }
    terms = [];
    call_ajax('../cartengine/orders.php','cmd=loadterms',true,
              finish_load_terms);
    load_terms_request = current_ajax_request;
}

