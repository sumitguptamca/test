/*
             Inroads Shopping Cart - Coupons Tab JavaScript Functions

                        Written 2007-2019 by Randall Severy
                         Copyright 2007-2019 Inroads, LLC
*/

var coupons_grid = null;
var offers_grid = null;
var inventory_grid = null;
var discounts_grid = null;
var cancel_add_coupon = true;
var flags = 0;
var enable_schedule = false;
var filter_where = '';
var enable_coupon_inventory = false;
var attribute_options = [];

function coupons_onload()
{
    if (document.forms[0].coupon_code) document.forms[0].coupon_code.focus();
    else document.forms[0].description.focus();
}

function enable_discount_buttons(enable_flag)
{
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    var coupon_buttons_row = document.getElementById('coupon_buttons_row');
    if (coupon_buttons_row) coupon_buttons_row.style.display = display_style;
    var add_discount_button = document.getElementById('add_discount');
    var delete_discount_button = document.getElementById('delete_discount');
    add_discount_button.style.display = display_style;
    delete_discount_button.style.display = display_style;
}

function enable_usage_buttons(enable_flag)
{
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    var coupon_buttons_row = document.getElementById('coupon_buttons_row');
    if (coupon_buttons_row) coupon_buttons_row.style.display = display_style;
    var view_order_button = document.getElementById('view_order');
    view_order_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
    tab_click(tab,content_id);
    if (content_id == 'discounts_content') enable_discount_buttons(true);
    else enable_discount_buttons(false);
    if (content_id == 'usage_content') enable_usage_buttons(true);
    else enable_usage_buttons(false);
    if (top.skin) {
       if (content_id == 'products_content') products.resize(false);
       if (content_id == 'ex_products_content') ex_products.resize(false);
       if (content_id == 'customers_content') customers.resize(false);
    }
    top.grow_current_dialog();
}

function resize_screen(new_width,new_height)
{
    if (top.skin) {
       if (top.buttons == 'left') {
          var button_column1 = document.getElementById('buttonColumn1');
          var button_column2 = document.getElementById('buttonColumn2');
          var offset = get_body_height() -
                       Math.max(coupons_grid.height,button_column1.offsetHeight) -
                       Math.max(offers_grid.height,button_column2.offsetHeight);
       }
       else var offset = get_body_height() - coupons_grid.height -
                         offers_grid.height;
       var grid_height = Math.floor((new_height - offset) / 2);
       if (grid_height < 0) grid_height = 0;
       if (top.buttons == 'left') {
          resize_grid(coupons_grid,offers_grid.width,
                      Math.max(grid_height,button_column1.offsetHeight));
          resize_grid(offers_grid,offers_grid.width,
                      Math.max(grid_height,button_column2.offsetHeight));
       }
       else {
          resize_grid(coupons_grid,coupons_grid.width,grid_height);
          resize_grid(offers_grid,offers_grid.width,grid_height);
       }
    }
    else {
       new_height = ((new_height + 20) / 2) - 15;
       if (top.buttons == 'top') new_height -= 40;
       resize_grid(coupons_grid,new_width,new_height)
       resize_grid(offers_grid,new_width,new_height);
       var sep_row = document.getElementById('coupons_sep_row');
       var row_height = new_height - 125;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function resize_dialog(new_width,new_height)
{
    if (top.skin) {
       if ((typeof(products) != 'undefined') && (products !== null))
          products.resize(true);
       if (typeof(ex_products) != 'undefined') ex_products.resize(true);
       if (typeof(customers) != 'undefined') customers.resize(true);
    }
}

function load_coupons_grid(coupon_flag)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2) - 15;
    if (top.buttons == 'top') grid_height -= 40;
    if (typeof(coupon_field_names) == 'undefined')
       coupon_field_names = ['id','coupon_code','coupon_type','amount',
          'start_date','end_date','max_qty','qty_used','total','description'];
    if (typeof(coupon_columns) == 'undefined')
       coupon_columns = ['Id','Code','Type','Amount','Start','End','Qty',
                         'Usage','Total','Description'];
    if (typeof(coupon_column_widths) == 'undefined')
       coupon_column_widths = [0,150,120,60,75,75,40,40,75,300];
    if (! coupon_flag) coupon_column_widths[1] = 0;
    if (typeof(coupon_query) == 'undefined') {
       var query = 'select id,coupon_code,coupon_type,amount,start_date,' +
          'end_date,max_qty,qty_used,(select sum(coupon_amount) from orders ' +
          'where coupon_id=';
       query += 'coupons.id) as total,description';
       if (enable_schedule) {
          coupon_field_names.push('flags');   coupon_columns.push('Schedule');
          coupon_column_widths.push(60);   query += ',flags';
       }
       query += ' from coupons';
    }
    else var query = coupon_query;
    var grid = new Grid('coupons',grid_size.width,grid_height);
    grid.set_server_sorting(true);
    grid.set_field_names(coupon_field_names);
    grid.set_columns(coupon_columns);
    grid.set_column_widths(coupon_column_widths);
    grid.set_query(query);
    if (coupon_flag) grid.set_where('isnull(flags) or (not (flags&32))');
    else grid.set_where('flags&32');
    grid.set_order_by('start_date desc,end_date desc,coupon_code');
    if (typeof(convert_coupon_data) != 'undefined')
       grid.table.set_convert_cell_data(convert_coupon_data);
    else grid.table.set_convert_cell_data(convert_data);
    if (coupon_flag) grid.set_id('coupons_grid');
    else grid.set_id('offers_grid');
    grid.load(false);
    grid.set_double_click_function(function() { edit_coupon(coupon_flag); });
    grid.display();
    if (coupon_flag) coupons_grid = grid;
    else offers_grid = grid;
    if (coupon_flag && (! top.skin)) {
       var sep_row = document.getElementById('coupons_sep_row');
       var row_height = grid_height - 105;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }

}

function get_grid(coupon_flag)
{
    if (coupon_flag) return coupons_grid;
    return offers_grid;
}

function reload_grid(coupon_flag)
{
    var grid = get_grid(coupon_flag);
    grid.table.reset_data(false);
    grid.grid.refresh();
    window.setTimeout(function() { grid.table.restore_position(); },0);
}

function add_coupon(coupon_flag)
{
    cancel_add_coupon = true;
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=createcoupon&flag=' + coupon_flag;
    var ajax_request = new Ajax('coupons.php',fields,true);
    ajax_request.coupon_flag = coupon_flag;
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_coupon,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function continue_add_coupon(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status != 200) return;

    var coupon_id = -1;
    eval(ajax_request.request.responseText);
    var coupon_flag = ajax_request.coupon_flag;
    var grid = get_grid(coupon_flag);
    grid.table.save_position();
    var url = '../cartengine/coupons.php?cmd=addcoupon&id=' + coupon_id +
              '&flag=' + coupon_flag;
    top.create_dialog('add_coupon',null,null,950,260,false,url,null);
}

function add_coupon_onclose(user_close)
{
    if (cancel_add_coupon) {
       var coupon_id = document.AddCoupon.id.value;
       var coupon_flag = document.AddCoupon.flag6.value;
       var fields = 'cmd=deletecoupon&id=' + coupon_id + '&flag=' +
                    coupon_flag;
       call_ajax('coupons.php',fields,true);
    }
}

function add_coupon_onload()
{
    coupons_onload();
    top.set_current_dialog_onclose(add_coupon_onclose);
    if (typeof(custom_add_coupon_onload) != 'undefined')
       custom_add_coupon_onload();
}

function validate_form_fields(form)
{
    if (form.coupon_code && (! validate_form_field(form.coupon_code,'Coupon Code',
        "change_tab(document.getElementById('coupon_tab'),'coupon_content');")))
       return false;
    var coupon_type = get_selected_list_value('coupon_type');
    if (coupon_type == '') {
       change_tab(document.getElementById('coupon_tab'),'coupon_content');
       alert('You must select a Coupon Type');   return false;
    }
    if (((coupon_type == 1) || (coupon_type == 2) || (coupon_type == 4)) &&
        (form.amount.value == '')) {
       change_tab(document.getElementById('coupon_tab'),'coupon_content');
       alert('You must enter an Amount');   form.amount.focus();
       return false;
    }
    return true;
}

function process_add_coupon()
{
    if (! validate_form_fields(document.AddCoupon)) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('coupons.php','cmd=processaddcoupon',document.AddCoupon,
                     finish_add_coupon);
}

function finish_add_coupon(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_coupon = false;
       if (inventory_grid) inventory_grid.table.process_updates(false);
       discounts_grid.table.process_updates(false);
       if (document.AddCoupon.flag6.value == 'true') var coupon_flag = true;
       else var coupon_flag = false;
       top.get_content_frame().reload_grid(coupon_flag);
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_coupon(coupon_flag)
{
    var grid = get_grid(coupon_flag);
    if (grid.table._num_rows < 1) {
       if (coupon_flag) alert('There are no coupons to edit');
       else alert('There are no special offers to edit');
       return;
    }
    var grid_row = grid.grid.getCurrentRow();
    var id = grid.grid.getCellText(0,grid_row);
    grid.table.save_position();
    var url = '../cartengine/coupons.php?cmd=editcoupon&id=' + id + '&flag=' +
              coupon_flag;
    top.create_dialog('edit_coupon',null,null,950,260,false,url,null);
}

function edit_coupon_onload()
{
    coupons_onload();
    if (typeof(custom_edit_coupon_onload) != 'undefined')
       custom_edit_coupon_onload();
}

function update_coupon()
{
    if (! validate_form_fields(document.EditCoupon)) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('coupons.php','cmd=updatecoupon',document.EditCoupon,
                     finish_update_coupon);
}

function finish_update_coupon(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (inventory_grid) inventory_grid.table.process_updates(false);
       discounts_grid.table.process_updates(false);
       if (document.EditCoupon.flag6.value == 'true') var coupon_flag = true;
       else var coupon_flag = false;
       top.get_content_frame().reload_grid(coupon_flag);
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_coupon(coupon_flag)
{
    var grid = get_grid(coupon_flag);
    if (grid.table._num_rows < 1) {
       if (coupon_flag) alert('There are no coupons to delete');
       else alert('There are no special offers to delete');
       return;
    }
    var grid_row = grid.grid.getCurrentRow();
    var id = grid.grid.getCellText(0,grid_row);
    if (coupon_flag) {
       var coupon_code = grid.grid.getCellText(1,grid_row);
       var response = confirm('Are you sure you want to delete the "' +
                              coupon_code + '" coupon?');
    }
    else var response = confirm('Are you sure you want to delete the ' +
                                'selected special offer?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    grid.table.save_position();
    var fields = 'cmd=deletecoupon&id=' + id + '&flag=' + coupon_flag;
    var ajax_request = new Ajax('coupons.php',fields,true);
    ajax_request.coupon_flag = coupon_flag;
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_delete_coupon,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function finish_delete_coupon(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid(ajax_request.coupon_flag);
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

var type_values = ['','Percentage Off','Amount Off','Free Shipping',
                   'Gift Certificate','Free Order','Free Product',
                   'Buy 1 Get 1 at x% Off','Quantity Discount'];

function convert_data(col,row,text)
{
    if (col == 2) {
       var coupon_type = parse_int(text);
       if ((typeof(coupon_types) != 'undefined') &&
           (typeof(coupon_types[coupon_type]) != 'undefined'))
          return coupon_types[coupon_type];
       if (typeof(type_values[coupon_type]) == 'undefined') return text;
       return type_values[coupon_type];
    }
    if ((col == 4) || (col == 5)) {
       if (text == '') return text;
       var coupon_date = new Date(parse_int(text) * 1000);
       return coupon_date.format('mm/dd/yyyy');
    }
    if (col == 8) return format_amount(text,'USD',false);
    if (col == 10) {
      if (parse_int(text) & 4) return 'Yes';
      else return '';
    }
    return text;
}

function search_coupons()
{
   var query = document.SearchForm.query.value;
   if (query == '') {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   top.display_status('Search','Searching Coupons...',350,100,null);
   window.setTimeout(function() {
      var where = "coupon_code like '%" + query + "%' or description like '%" +
                  query + "%'";
      if (filter_where != '') where = '(' + where + ') and ' + filter_where;
      coupons_grid.set_where(where);
      coupons_grid.table.reset_data(false);
      coupons_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status('Search','Loading All Coupons...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      coupons_grid.set_where(filter_where);
      coupons_grid.table.reset_data(false);
      coupons_grid.grid.refresh();
      top.remove_status();
   },0);
}

function filter_coupons()
{
    filter_where = '';
    var type_list = document.getElementById('coupon_type');
    if (type_list.selectedIndex == -1) var coupon_type = '';
    else var coupon_type = type_list.options[type_list.selectedIndex].value;
    if (coupon_type != '') {
       if (filter_where != '') filter_where += ' and ';
       filter_where += '(coupon_type=' + coupon_type + ')';
    }
    var query = document.SearchForm.query.value;
    if (query != '') search_coupons();
    else {
       top.display_status("Search","Searching Coupons...",350,100,null);
       window.setTimeout(function() {
          coupons_grid.set_where(filter_where);
          coupons_grid.table.reset_data(false);
          coupons_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

function change_coupon_type()
{
    var coupon_type = get_selected_list_value('coupon_type');
    var amount_row = document.getElementById('amount_row');
    var min_amount_row = document.getElementById('min_amount_row');
    var balance_row = document.getElementById('balance_row');
    var start_date_row = document.getElementById('start_date_row');
    var end_date_row = document.getElementById('end_date_row');
    var max_qty_row = document.getElementById('max_qty_row');
    var max_qty_per_cust_row = document.getElementById('max_qty_per_cust_row');
    var qty_used_row = document.getElementById('qty_used_row');
    var percent_sign = document.getElementById('percent_sign');
    var usedby_row = document.getElementById('usedby_row');
    if (coupon_type == '') {
       amount_row.style.display = 'none';
       min_amount_row.style.display = 'none';
       balance_row.style.display = 'none';
       start_date_row.style.display = 'none';
       end_date_row.style.display = 'none';
       max_qty_row.style.display = 'none';
       max_qty_per_cust_row.style.display = 'none';
       qty_used_row.style.display = 'none';
       if (usedby_row) usedby_row.style.display = 'none';
    }
    else if ((coupon_type == '3') || (coupon_type == '5') ||
             (coupon_type == '6') || (coupon_type == '8')) {
       amount_row.style.display = 'none';
       min_amount_row.style.display = '';
       balance_row.style.display = 'none';
       start_date_row.style.display = '';
       end_date_row.style.display = '';
       max_qty_row.style.display = '';
       max_qty_per_cust_row.style.display = '';
       qty_used_row.style.display = '';
       if (usedby_row) usedby_row.style.display = 'none';
    }
    else if (coupon_type == '4') {
       amount_row.style.display = '';
       min_amount_row.style.display = 'none';
       balance_row.style.display = '';
       start_date_row.style.display = '';
       end_date_row.style.display = '';
       max_qty_row.style.display = 'none';
       max_qty_per_cust_row.style.display = 'none';
       qty_used_row.style.display = 'none';
       if (usedby_row) usedby_row.style.display = '';
    }
    else {
       amount_row.style.display = '';
       min_amount_row.style.display = '';
       balance_row.style.display = 'none';
       start_date_row.style.display = '';
       end_date_row.style.display = '';
       max_qty_row.style.display = '';
       max_qty_per_cust_row.style.display = '';
       qty_used_row.style.display = '';
       if (usedby_row) usedby_row.style.display = 'none';
    }
    var free_product_row = document.getElementById('free_product_row');
    if (coupon_type == '6') free_product_row.style.display = '';
    else {
       if (free_product_row.style.display != 'none') {
          if (document.AddCoupon) var form = document.AddCoupon;
          else var form = document.EditCoupon;
          form.free_product.value = 0;
          document.getElementById('free_product_cell').innerHTML = '';
       }
       free_product_row.style.display = 'none';
    }
    var buy1_flag = document.getElementById('buy1_flag');
    if (coupon_type == '7') buy1_flag.style.display = '';
    else buy1_flag.style.display = 'none';
    if ((coupon_type == '1') || (coupon_type == '7'))
       percent_sign.style.display = '';
    else percent_sign.style.display = 'none';
    var available_row = document.getElementById('available_row');
    available_row.style.display = '';
    var schedule_row = document.getElementById('schedule_row');
    if (schedule_row) schedule_row.style.display = '';
    if (top.skin) var discounts_tab = document.getElementById('discounts_tab');
    else var discounts_tab = document.getElementById('discounts_tab_cell');
    if (coupon_type == '8') discounts_tab.style.display = '';
    else discounts_tab.style.display = 'none';
    if (typeof(custom_change_coupon_type) != 'undefined')
       custom_change_coupon_type();
    top.grow_current_dialog();
}

function change_flags()
{
    flags = 0;   
    var flag1 = get_selected_radio_button('flag1');
    if (top.skin) var products_tab = document.getElementById('products_tab');
    else var products_tab = document.getElementById('products_tab_cell');
    if (flag1 == 1) {
       flags |= 1;   products_tab.style.display = '';
    }
    else products_tab.style.display = 'none';

    var flag8 = get_selected_radio_button('flag8');
    if (top.skin)
       var ex_products_tab = document.getElementById('ex_products_tab');
    else var ex_products_tab = document.getElementById('ex_products_tab_cell');
    if (flag8 == 'on') {
       flags |= 128;   ex_products_tab.style.display = '';
    }
    else ex_products_tab.style.display = 'none';

    var flag2 = get_selected_radio_button('flag2');
    if (top.skin) var customers_tab = document.getElementById('customers_tab');
    else var customers_tab = document.getElementById('customers_tab_cell');
    if (flag2 == 2) {
       flags |= 2;   customers_tab.style.display = '';
    }
    else customers_tab.style.display = 'none';

    var flag4 = document.getElementById('flag4');
    if (flag4) {
       if (top.skin) var schedule_tab = document.getElementById('schedule_tab');
       else var schedule_tab = document.getElementById('schedule_tab_cell');
       if (flag4.checked) {
          flags |= 4;   if (schedule_tab) schedule_tab.style.display = '';
       }
       else if (schedule_tab) schedule_tab.style.display = 'none';
    }
}

function set_balance()
{
    var coupon_type = get_selected_list_value('coupon_type');
    if (coupon_type != 4) return;
    var amount = document.AddCoupon.amount.value;
    document.AddCoupon.balance.value = amount;
}

function add_multiple_products(type)
{
    var form = document.forms[products.form_name];
    var coupon_id = form.id.value;
    if (type == 0) var grid = products;
    else var grid = ex_products;
    if (grid.right_grid.table._num_rows < 1) {
       alert('There are no products to add');   return;
    }
    var selected_rows = grid.right_grid.grid._rowsSelected;
    var ids = '';   var num_selected = 0;
    for (var grid_row in selected_rows) {
       if (grid_row == '$') continue;
       var id = grid.right_grid.grid.getCellText(0,grid_row);
       if (ids != '') ids += ',';
       ids += id;
       num_selected++;
    }
    var url = '../cartengine/coupons.php?cmd=addmultipleproducts&id=' +
              coupon_id + '&ids=' + ids + '&Frame=' + products.frame_name +
              '&Type=' + type;
    top.create_dialog('add_multiple_products',null,null,500,100,false,url,null);
}

function validate_list_field(field_name,label,field)
{
    var field_value = get_selected_list_value(field_name);
    if (field_value == '') {
       alert('You must select a ' + label);
       field.focus();
       return false;
    }
    return true;
}

function process_add_multiple_products()
{
    var select = get_selected_radio_button('select');
    if ((select == 'category') &&
        (! validate_list_field('category','Category',
                               document.AddMultipleProducts.category)))
       return;
    if ((select == 'vendor') &&
        (! validate_list_field('vendor','Vendor',
                               document.EditMultiple.vendor)))
       return;
    if ((typeof(custom_validate_change_select) != 'undefined') &&
        (! custom_validate_change_select(select,document.AddMultipleProducts)))
       return;
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=processaddmultipleproducts&' +
                 build_form_data(document.AddMultipleProducts);
    call_ajax('coupons.php',fields,true,finish_process_add_multiple_products);
}

function finish_process_add_multiple_products(ajax_request)
{
    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       var frame = document.AddMultipleProducts.Frame.value;
       var iframe = top.get_dialog_frame(frame).contentWindow;
       var type = document.AddMultipleProducts.Type.value;
       if (type == 0) var grid = iframe.products;
       else var grid = iframe.ex_products;
       grid.left_grid.table.reset_data(false);
       grid.left_grid.grid.refresh();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function select_product()
{
    if (document.AddCoupon) var frame = 'add_coupon';
    else var frame = 'edit_coupon';
    var url = '../cartengine/products.php?cmd=selectproduct&frame=' + frame;
    if (enable_coupon_inventory) {
       url += '&inventory=true';   var height = 600;
    }
    else var height = 400;
    top.create_dialog('select_product',null,null,830,height,false,url,null);
}

function change_product(product_data)
{
    if (document.AddCoupon) var form = document.AddCoupon;
    else var form = document.EditCoupon;
    form.free_product.value = product_data.id;
    var free_product_cell = document.getElementById('free_product_cell');
    var product_name = product_data.name;
    if (enable_coupon_inventory) {
       form.free_prod_attrs.value = product_data.attributes;
       if (product_data.part_number)
          product_name += ' (' + product_data.part_number + ')';
    }
    free_product_cell.innerHTML = product_name;
    free_product_cell.style.display = '';
}

function set_inventory_query(coupon_id,product_id)
{
    var query = 'select ci.id,i.id as related_id,i.attributes,i.part_number,ci.id as available,' +
       'i.sequence from coupon_inventory ci join product_inventory i on ' +
       'i.id=ci.related_id where (ci.parent=' + coupon_id + ') and ' +
       '(i.parent=' + product_id + ') union select null,id as related_id,' +
       'attributes,part_number,0,sequence from product_inventory where (parent=' +
       product_id + ') and (id not in (select related_id from ' +
       'coupon_inventory where parent=' + coupon_id + '))';
    inventory_grid.set_query(query);
}

function create_inventory_grid(coupon_id,product_id)
{
    inventory_grid = new Grid('coupon_inventory',-100,300);
    inventory_grid.set_columns(['Id','InvID','Attributes','Part Number',
                                'Available','Sequence']);
    inventory_grid.set_field_names(['id','related_id','attributes',
                                    'part_number','available','sequence']);
    inventory_grid.set_column_widths([0,0,250,250,65,0]);
    set_inventory_query(coupon_id,product_id);
    inventory_grid.set_order_by('sequence');
    inventory_grid.set_id('inventory_grid');
    inventory_grid.table.set_convert_cell_data(convert_inventory_data);
    inventory_grid.table._url = 'coupons.php';
    inventory_grid.table.add_update_parameter('cmd','updatecouponinventory');
    inventory_grid.table.add_update_parameter('parent',coupon_id);
    inventory_grid.load(true);
    var checkbox_template = new AW.Templates.Checkbox;
    var checkbox = new AW.HTML.SPAN;
    checkbox.setContent("html",function() { return ''; });
    checkbox_template.setContent("box/text",checkbox);
    inventory_grid.grid.setCellTemplate(checkbox_template,4);
    inventory_grid.display();

    products.left_grid.grid.onCurrentRowChanged = select_product_row;
}

function select_product_row(row)
{
    if (document.AddCoupon) var coupon_id = document.AddCoupon.id.value;
    else var coupon_id = document.EditCoupon.id.value;
    var sublist_id = products.left_grid.grid.getCellText(0,row);
    var product_id = '(select related_id from coupon_products where id=' +
                     sublist_id + ')';
    set_inventory_query(coupon_id,product_id);
    inventory_grid.table.reset_data(true);
    inventory_grid.grid.refresh();
}

function convert_inventory_data(col,row,text)
{
    if (col == 2) {
       var attributes = text.split('-');
       var text = '';
       for (var index in attributes) {
          var attribute = attributes[index];
          if (text) text += ', ';
          if (typeof(attribute_options[attribute]) != 'undefined')
             text += attribute_options[attribute];
          else text += '#' + attribute;
       }
       return text;
    }
    if (col == 4) {
       if (parse_int(text)) return true;
       else return false;
    }
    return text;
}

function create_discounts_grid(coupon_id)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 310;
    var grid_height = 350;
    discounts_grid = new Grid('coupon_discounts',grid_width,grid_height);
    discounts_grid.set_columns(['Id','Start Quantity','End Quantity',
                                'Discount (% off)']);
    discounts_grid.set_field_names(['id','start_qty','end_qty','discount']);
    discounts_grid.set_column_widths([0,100,100,100]);
    var query = 'select id,start_qty,end_qty,discount from coupon_discounts';
    discounts_grid.set_query(query);
    var where = 'parent=' + coupon_id;
    discounts_grid.set_where(where);
    discounts_grid.set_order_by('start_qty,end_qty');
    discounts_grid.set_id('discounts_grid');
    discounts_grid.table._url = 'coupons.php';
    discounts_grid.table.add_update_parameter('cmd','updatediscount');
    discounts_grid.table.add_update_parameter('parent',coupon_id);
    discounts_grid.load(true);
    discounts_grid.grid.setCellEditable(true,1);
    discounts_grid.grid.setCellEditable(true,2);
    discounts_grid.grid.setCellEditable(true,3);
    discounts_grid.grid.setSelectionMode('single-cell');
    discounts_grid.grid.setVirtualMode(false);
    set_grid_navigation(discounts_grid.grid);
    discounts_grid.display();
}

function add_discount(discount_type)
{
    var row_data = [];
    row_data[0] = '';   row_data[1] = '';   row_data[2] = '';   row_data[3] = '';
    discounts_grid.table.add_row(row_data);
    discounts_grid.grid.focus_cell(0,discounts_grid.table._num_rows - 1);
}

function delete_discount(discount_type)
{
    if (discounts_grid.table._num_rows < 1) {
       alert('There are no discounts to delete');   return;
    }
    discounts_grid.table.delete_row();
}

function create_usage_grid(coupon_id)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    usage_grid = new Grid('orders',grid_width,400);
    usage_grid.table.set_field_names(['id','order_date','order_number',
       'fname','lname','email','coupon_amount','total']);
    usage_grid.set_columns(['Id','Date','Order Number','First Name',
       'Last Name','Email Address','Coupon Amount','Order Total']);
    usage_grid.set_column_widths([0,150,90,90,100,175,90,70]);
    var query = 'select id,order_date,order_number,fname,lname,email,' +
                'coupon_amount,total from orders';
    usage_grid.set_query(query);
    usage_grid.set_where('coupon_id=' + coupon_id);
    usage_grid.table.set_order_by('id desc');
    usage_grid.set_id('usage_grid');
    usage_grid.table.set_convert_cell_data(convert_usage_data);
    usage_grid.load(false);
    usage_grid.set_double_click_function(view_order);
    usage_grid.display();
}

function view_order()
{
   if (usage_grid.table._num_rows < 1) {
      alert('There are no orders to view');   return;
   }
   var grid_row = usage_grid.grid.getCurrentRow();
   var id = usage_grid.grid.getCellText(0,grid_row);
   top.create_dialog('view_order',null,null,750,460,false,
                     '../cartengine/orders.php?cmd=vieworder&id=' + id,null);
}

function convert_usage_data(col,row,text)
{
   if (col == 1) {
      if (text == '') return text;
      var order_date = new Date(parse_int(text) * 1000);
      return order_date.format('mmmm d, yyyy h:MM tt');
   }
   if ((col == 6) || (col == 7)) return format_amount(text,'USD',false);
   return text;
}

