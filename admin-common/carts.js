/*
            Inroads Shopping Cart - Carts Screen JavaScript Functions

                        Written 2009-2018 by Randall Severy
                         Copyright 2009-2018 Inroads, LLC
*/

var cart_grid = null;
var show_mpns = false;
var wish_lists = false;
var website_settings = 0;

var WEBSITE_SHARED_CART = 1;

function get_website_where()
{
    if (typeof(top.website) == 'undefined') return '';
    if (top.website == 0) return '';
    if (website_settings & WEBSITE_SHARED_CART) return '';
    return '(website=' + top.website + ')';
}

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(cart_grid,-1,new_height - get_grid_offset(cart_grid));
    else resize_grid(cart_grid,new_width,new_height)
}

function load_grid()
{
   if (wish_lists) var table = 'wishlist';
   else var table = 'cart';
   var grid_size = get_default_grid_size();
   cart_grid = new Grid(table,grid_size.width,grid_size.height);
   cart_grid.table.set_field_names([]);
   if (typeof(cart_columns) != 'undefined')
      cart_grid.set_columns(cart_columns);
   else {
      var columns = ['Id','Email','First Name','Last Name','IP Address',
                     'Sub Total','Created On'];
      if (show_mpns) columns.push('MPNs');
      cart_grid.set_columns(columns);
   }
   if (typeof(cart_column_widths) != 'undefined')
      cart_grid.set_column_widths(cart_column_widths);
   else {
      var column_widths = [0,180,100,100,90,70,155];
      if (show_mpns) column_widths.push(200);
      cart_grid.set_column_widths(column_widths);
   }
   if (typeof(cart_query) != 'undefined')
      var query = cart_query;
   else {
      var query = 'select ca.id,ifnull(cu.email,ca.email) as email,cu.fname,cu.lname,' +
                  'ca.ip_address,(select sum(IF(flags&1,price+ifnull(attribute_prices,0),' +
                  '(price+ifnull(attribute_prices,0))*qty)) from ' + table + '_items ' +
                  'where parent=ca.id)+ifnull((select sum(price) from ' +
                  table + '_attributes where parent in (select id from ' + table +
                  '_items where parent=ca.id)),0) as total,ca.create_date';
      if (show_mpns)
         query += ',(select group_concat(p.shopping_mpn separator ", ") from ' +
                  'products p where p.id in (select product_id from ' +
                  'cart_items ci where ci.parent=ca.id)) as mpns';
      query += ' from ' + table + ' ca left join customers cu on ca.customer_id=cu.id';
   }
   cart_grid.table.set_query(query);
   cart_grid.table.set_decrypt_table('customers,' + table);
   if (typeof(cart_info_query) != 'undefined')
      cart_grid.set_info_query(cart_info_query);
   var where = 'isnull(flags) or (not flags&2)';
   var website_where = get_website_where();
   if (website_where) where = '(' + where + ') and ' + website_where;
   cart_grid.set_where(where);
   if (typeof(cart_order_by) != 'undefined')
      cart_grid.set_order_by(cart_order_by);
   else cart_grid.table.set_order_by('ca.create_date desc');
   if (typeof(convert_cart_data) != 'undefined')
      cart_grid.table.set_convert_cell_data(convert_cart_data);
   else cart_grid.table.set_convert_cell_data(convert_data);
   cart_grid.set_id('cart_grid');
   cart_grid.load(false);
   cart_grid.grid.clearSelectedModel();
   cart_grid.grid.setSelectionMode('multi-row');
   cart_grid.set_double_click_function(view_cart);
   cart_grid.display();
}

function append_wish_lists()
{
    if (top.get_content_frame().wish_lists) return '&wishlists=true';
    return '';
}

function reload_grid()
{
   cart_grid.table.reset_data(false);
   cart_grid.grid.refresh();
   window.setTimeout(function() { cart_grid.table.restore_position(); },0);
}

function add_cart()
{
   cart_grid.table.save_position();
   var fields = '../cartengine/carts.php?cmd=addcart' + append_wish_lists();
   top.create_dialog('add_cart',null,null,685,420,false,fields,null);
}

function process_add_cart()
{
   top.enable_current_dialog_progress(true);
   var fields = 'cmd=processaddcart' + append_wish_lists();
   submit_form_data('carts.php',fields,document.AddCart,finish_add_cart);
}

function finish_add_cart(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function view_cart()
{
   if (wish_lists) var label = 'wish list';
   else var label = 'pending cart';
   if (cart_grid.table._num_rows < 1) {
      alert('There are no ' + label + 's to view');   return;
   }
   if (cart_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one ' + label + ' to view');   return;
   }
   var grid_row = cart_grid.grid.getCurrentRow();
   var id = cart_grid.grid.getCellText(0,grid_row);
   var dialog_name = 'view_cart_'+id;
   var fields = '../cartengine/carts.php?cmd=viewcart&id=' + id +
                append_wish_lists();
   top.create_dialog(dialog_name,null,null,650,300,false,fields,null);
}

function view_reorder(id)
{
   var dialog_name = 'view_reorder_' + id;
   var fields = '../cartengine/orders.php?cmd=vieworder&id=' + id +
       '&ordertype=0' + append_wish_lists();
   top.create_dialog(dialog_name,null,null,650,460,false,fields,null);
}

function edit_cart()
{
   if (cart_grid.table._num_rows < 1) {
      alert('There are no pending carts to edit');   return;
   }
   if (cart_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one pending cart to edit');   return;
   }
   var grid_row = cart_grid.grid.getCurrentRow();
   var id = cart_grid.grid.getCellText(0,grid_row);
   cart_grid.table.save_position();
   var fields = '../cartengine/carts.php?cmd=editcart&id=' + id +
                append_wish_lists();
   top.create_dialog('edit_cart',null,null,685,360,false,fields,null);
}

var current_cart_item = -1;

function item_row_mouseover(row)
{
   row.style.backgroundColor = '#E7F7FF';
}

function item_row_mouseout(row)
{
   if (row.id == current_cart_item) row.style.backgroundColor = '#72B8D8';
   else row.style.backgroundColor = '';
}

function item_row_click(row)
{
   if (current_cart_item != -1) {
      var old_row = document.getElementById(current_cart_item);
      if (old_row) old_row.style.backgroundColor = '';
   }
   current_cart_item = row.id;
   row.style.backgroundColor = '#72B8D8';
}

function update_cart()
{
   top.enable_current_dialog_progress(true);
   var fields = 'cmd=updatecart' + append_wish_lists();
   submit_form_data('carts.php',fields,document.EditCart,finish_update_cart);
}

function finish_update_cart(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function delete_cart()
{
   if (wish_lists) var label = 'wish list';
   else var label = 'pending cart';
   if (cart_grid.table._num_rows < 1) {
      alert('There are no ' + label + 's to delete');   return;
   }
   var selected_rows = cart_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = cart_grid.grid.getCellText(0,grid_row);
      var status = cart_grid.grid.getCellText(4,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   if (num_selected == 0) {
      alert('You must select at least one ' + label + ' to delete');   return;
   }
   if (num_selected == 1)
      var response = confirm('Are you sure you want to delete this ' +
                             label + '?');
   else var response = confirm('Are you sure you want to delete these ' +
                               label + 's?');
   if (! response) return;
   cart_grid.table.save_position();
   var fields = 'cmd=deletecart&ids=' + ids + append_wish_lists();
   call_ajax('carts.php',fields,true,finish_delete_cart);
}

function finish_delete_cart(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) reload_grid();
   else ajax_request.display_error();
}

function submit_order()
{
   if (wish_lists) var label = 'wish list';
   else var label = 'pending cart';
   if (cart_grid.table._num_rows < 1) {
      alert('There are no ' + label + 's to submit');   return;
   }
   var selected_rows = cart_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = cart_grid.grid.getCellText(0,grid_row);
      var status = cart_grid.grid.getCellText(4,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   if (num_selected == 0) {
      alert('You must select at least one ' + label + ' to submit');   return;
   }
   if (num_selected == 1)
      var response = confirm('Are you sure you want to submit this ' +
                             label + ' as a new order?');
   else var response = confirm('Are you sure you want to submit these ' +
                               label + 's as new orders?');
   if (! response) return;
   cart_grid.table.save_position();
   var fields = 'cmd=submitorder&ids=' + ids + append_wish_lists();
   call_ajax('carts.php',fields,true,finish_submit_order,0);
}

function finish_submit_order(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201)
      top.get_content_frame().reload_grid();
}

function enable_cart_item_buttons(enable_flag)
{
   var add_cart_item_button = document.getElementById('add_cart_item');
   var edit_cart_item_button = document.getElementById('edit_cart_item');
   if (! edit_cart_item_button) return;
   var delete_cart_item_button = document.getElementById('delete_cart_item');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   if (add_cart_item_button)
      add_cart_item_button.style.display = display_style;
   edit_cart_item_button.style.display = display_style;
   delete_cart_item_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
   if (content_id == 'items_content') enable_cart_item_buttons(true);
   else enable_cart_item_buttons(false);
   tab_click(tab,content_id);
}

function convert_data(col,row,text)
{
   if (col == 5) {
      if (text != '') return '$' + format_amount(text);
   }
   if (col == 6) {
      if (text == '') return text;
      var cart_date = new Date(parse_int(text) * 1000);
      return cart_date.format('mmmm d, yyyy h:MM tt');
   }
   return text;
}

function search_cart()
{
   var query = document.SearchForm.query.value;
   if (query == '') {
      reset_search();   return;
   }
   if (wish_lists) {
      var label = 'Wish Lists';   var table = 'wishlist';
   }
   else {
      var label = 'Pending Carts';   var table = 'cart';
   }
   top.display_status('Search','Searching ' + label + '...',350,100,null);
   window.setTimeout(function() {
      var where = '(cu.email like "%' + query + '%" or cu.fname like "%' + query +
                  '%" or cu.lname like "%' + query + '%" or ca.id like "%' + query +
                  '%") and (isnull(flags) or (not flags&2))';
      var website_where = get_website_where();
      if (website_where) where = '(' + where + ') and ' + website_where;
      cart_grid.set_where(where);
      var info_query = 'select count(*) from ' + table + ' ca ' +
                       'left join customers cu on ca.customer_id=cu.id';
      cart_grid.set_info_query(info_query);
      cart_grid.table.reset_data(false);
      cart_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   if (wish_lists) var label = 'Wish Lists';
   else var label = 'Pending Carts';
   top.display_status('Search','Loading All ' + label + '...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      var where = 'isnull(flags) or (not flags&2)';
      var website_where = get_website_where();
      if (website_where) where = '(' + where + ') and ' + website_where;
      cart_grid.set_where(where);
      cart_grid.table.reset_data(false);
      cart_grid.grid.refresh();
      top.remove_status();
   },0);
}

function add_cart_item()
{
   var cart_id = document.EditCart.id.value;
   var url = '../cartengine/carts.php?cmd=addcartitem&Cart=' + cart_id +
             append_wish_lists();
   top.create_dialog('add_cart_item',null,null,590,130,false,url,null);
}

function process_add_cart_item()
{
   top.enable_current_dialog_progress(true);
   var fields = 'cmd=processaddcartitem' + append_wish_lists();
   submit_form_data('carts.php',fields,document.EditCartItem,
                    finish_add_cart_item);
}

function finish_add_cart_item(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.reload_dialog('edit_cart');
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function edit_cart_item()
{
   if (current_cart_item == -1) {
      if (wish_lists) var label = 'wish list';
      else var label = 'cart';
      alert('You must select a ' + label + ' item to edit');   return;
   }
   var cart_id = document.EditCart.id.value;
   var fields = '../cartengine/carts.php?cmd=editcartitem&id=' +
                current_cart_item + '&Cart=' + cart_id + append_wish_lists();
   top.create_dialog('edit_cart_item',null,null,580,120,false,fields,null);
}

function update_cart_item()
{
   top.enable_current_dialog_progress(true);
   var fields = 'cmd=updatecartitem' + append_wish_lists();
   submit_form_data('carts.php',fields,document.EditCartItem,
                    finish_update_cart_item);
}

function finish_update_cart_item(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.reload_dialog('edit_cart');
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function delete_cart_item()
{
   if (wish_lists) var label = 'wish list';
   else var label = 'cart';
   if (current_cart_item == -1) {
      alert('You must select a ' + label + ' item to delete');   return;
   }
   var name_field = document.getElementById('item_name_'+current_cart_item)
   if (name_field.innerText) var item_name = name_field.innerText;
   else if (name_field.textContent) var item_name = name_field.textContent;
   else var item_name = name_field.text;
   var response = confirm('Are you sure you want to delete the "' +
                          item_name + '" ' + label + ' item?');
   if (! response) return;
   var cart_id = document.EditCart.id.value;
   var fields = 'cmd=deletecartitem&id=' + current_cart_item + '&Cart=' +
                cart_id + append_wish_lists();
   call_ajax('../cartengine/carts.php',fields,true,finish_delete_cart_item);
}

function finish_delete_cart_item(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) top.reload_dialog('edit_cart');
   else ajax_request.display_error();
}

