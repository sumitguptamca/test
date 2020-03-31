/*
              Inroads Shopping Cart - RMAs Tab JavaScript Functions

                        Written 2013-2017 by Randall Severy
                         Copyright 2013-2017 Inroads, LLC
*/

var rmas_grid = null;
var filter_where = '';
var order_coupon_amount = 0;
var order_gift_amount = 0;
var order_discount_amount = 0;

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(rmas_grid,-1,new_height - get_grid_offset(rmas_grid));
    else resize_grid(rmas_grid,new_width,new_height)
}

function load_grid()
{
   var grid_size = get_default_grid_size();
   rmas_grid = new Grid('rmas',grid_size.width,grid_size.height);
   rmas_grid.table.set_field_names([]);
   if (typeof(rma_columns) == 'undefined') {
      rma_columns = ['RMA #','Order #','Status','Email','First Name',
                     'Last Name','State','Zip Code','Request Date'];
   }
   rmas_grid.set_columns(rma_columns);
   if (typeof(rma_column_widths) == 'undefined')
      rma_column_widths = [50,75,100,200,100,100,40,70,155,0,0,0];
   rmas_grid.set_column_widths(rma_column_widths);
   if (typeof(rma_query) == 'undefined')
      rma_query = 'select r.id,o.order_number,r.status,r.email,r.fname,' +
                  'r.lname,r.state,r.zipcode,r.request_date from rmas r left ' +
                  'join orders o on r.order_id=o.id';
   rmas_grid.table.set_query(rma_query);
   rmas_grid.table.set_decrypt_table('rmas,orders');
   if (typeof(rma_where) != 'undefined') rmas_grid.set_where(rma_where);
   else if ((typeof(top.website) != 'undefined') && (top.website != 0)) {
      var where = 'o.website=' + top.website;
      rmas_grid.set_where(where);
   }
   if (typeof(rma_info_query) == 'undefined')
      rma_info_query = 'select count(r.id) from rmas r join orders o on r.order_id=o.id';
   rmas_grid.set_info_query(rma_info_query);
   if (typeof(rma_order_by) != 'undefined')
      rmas_grid.set_order_by(rma_order_by);
   else rmas_grid.table.set_order_by('r.id desc');
   if (typeof(convert_rma_data) != 'undefined')
      rmas_grid.table.set_convert_cell_data(convert_rma_data);
   else rmas_grid.table.set_convert_cell_data(convert_data);
   rmas_grid.set_id('rmas_grid');
   rmas_grid.load(false);
   rmas_grid.grid.clearSelectedModel();
   rmas_grid.grid.setSelectionMode('multi-row');
   rmas_grid.set_double_click_function(view_rma);
   rmas_grid.display();
}

function reload_grid()
{
   rmas_grid.table.reset_data(false);
   rmas_grid.grid.refresh();
   window.setTimeout(function() { rmas_grid.table.restore_position(); },0);
}

function add_rma()
{
   rmas_grid.table.save_position();
   top.create_dialog('add_rma',null,null,1095,700,false,
                     '../cartengine/rmas.php?cmd=addrma',null);
}

function find_order()
{
   top.create_dialog('find_order',null,null,1020,400,false,
                     '../cartengine/orders.php?cmd=selectorder&frame=add_rma',
                     null);
   return false;
}

function load_order_items(order_id)
{
   var fields = 'cmd=loadorderitems&id=' + order_id;
   submit_form_data('rmas.php',fields,document.AddRMA,
                    finish_load_order_items);
}

function display_order_item(table,item)
{
   var row_num = table.rows.length - 1;
   var new_row = table.insertRow(table.rows.length);
   new_row.vAlign = 'middle';
   var new_cell = new_row.insertCell(0);
   new_cell.align = 'center';
   var html = '<input type="hidden" name="item_id_'+row_num+'" value="' +
              item.id + '"><input type="hidden" name="item_price_'+row_num +
              '" value="'+item.price+'"><input type="hidden" name="item_qty_' +
              row_num+'" value="'+item.qty+'"><input type="checkbox" class="checkbox" ' +
              'name="return_'+row_num+'" onClick="check_return('+row_num+');">';
   new_cell.innerHTML = html;
   var new_cell = new_row.insertCell(1);
   new_cell.align = 'left';
   new_cell.innerHTML = item.name;
   new_cell = new_row.insertCell(2);
   new_cell.align = 'right';
   new_cell.innerHTML = '$' + format_amount(item.price);
   new_cell = new_row.insertCell(3);
   new_cell.align = 'center';
   new_cell.innerHTML = item.qty;
   new_cell = new_row.insertCell(4);
   new_cell.align = 'center';
   var html = '<input type="text" class="text" name="return_qty_'+row_num +
              '" id="return_qty_'+row_num+'" value="" size="1" ' +
              'onBlur="update_refund();">';
   new_cell.innerHTML = html;
}

function add_order_info_row(table,prompt,currency,data)
{
   var row_num = table.rows.length - 1;
   var new_row = table.insertRow(table.rows.length);
   new_row.vAlign = 'bottom';
   var new_cell = new_row.insertCell(0);
   new_cell.className = 'fieldprompt';
   new_cell.noWrap = true;
   new_cell.innerHTML = prompt;
   var new_cell = new_row.insertCell(1);
   if (currency) data = format_amount(data,currency);
   new_cell.innerHTML = '<tt>' + data + '</tt>';
}

function update_order_info_section(order_info)
{
   var info_table = document.getElementById('order_info_table');
   var num_rows = info_table.rows.length;
   for (var loop = 0;  loop < num_rows;  loop++) info_table.deleteRow(loop);

   var currency = order_info.currency;
   if (order_info.subtotal && (order_info.subtotal != 0))
      add_order_info_row(info_table,'Sub Total:',currency,order_info.subtotal);
   if (order_info.tax && (order_info.tax != 0))
      add_order_info_row(info_table,'Tax:',currency,order_info.tax);
   if (order_info.shipping && (order_info.shipping != 0))
      add_order_info_row(info_table,'Shipping:',currency,order_info.shipping);
   if (order_info.coupon_code)
      add_order_info_row(info_table,'Coupon:',null,order_info.coupon_code);
   if (order_info.coupon_amount && (order_info.coupon_amount != 0)) {
      add_order_info_row(info_table,'Coupon Amount:',currency,
                         -order_info.coupon_amount);
      order_coupon_amount = order_info.coupon_amount;
   }
   else order_coupon_amount = 0;
   if (order_info.gift_amount && (order_info.gift_amount != 0)) {
      add_order_info_row(info_table,'Gift Certificate:',currency,
                         -order_info.gift_amount);
      order_gift_amount = order_info.gift_amount;
   }
   else order_gift_amount = 0;
   if (order_info.fee_name || order_info.fee_amount) {
      if (! order_info.fee_name) order_info.fee_name = 'Fee';
      if (! order_info.fee_amount) order_info.fee_amount = 0;
      add_order_info_row(info_table,order_info.fee_name + ':',currency,
                         order_info.fee_amount);
   }
   if (order_info.discount_name || order_info.discount_amount) {
      if (! order_info.discount_name) order_info.discount_name = 'Discount';
      if (! order_info.discount_amount) order_info.discount_amount = 0;
      add_order_info_row(info_table,order_info.discount_name + ':',currency,
                         -order_info.discount_amount);
      order_discount_amount = order_info.discount_amount;
   }
   else order_discount_amount = 0;
   if (order_info.total && (order_info.total != 0))
      add_order_info_row(info_table,'Total:',currency,order_info.total);
   if (order_info.balance_due && (order_info.balance_due != 0))
      add_order_info_row(info_table,'Balance Due:',currency,
                         order_info.balance_due);
}

function finish_load_order_items(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;
   var status = ajax_request.get_status();
   if (status != 200) return;

   var order_items = [];   var order_info = null;
   eval(ajax_request.request.responseText);
   var items_table = document.getElementById('items_table');
   var num_rows = items_table.rows.length;
   for (var loop = 1;  loop < num_rows;  loop++) items_table.deleteRow(loop);
   for (var item_id in order_items) {
      display_order_item(items_table,order_items[item_id]);
   }
   document.AddRMA.NumItems.value = items_table.rows.length - 1;
   order_info = JSON.parse(order_info);
   update_order_info_section(order_info);
   top.grow_current_dialog();
}

function change_order(order_data)
{
   var form = document.AddRMA;
   var order_number_cell = document.getElementById('order_number_cell');
   form.order_id.value = order_data.id;
   order_number_cell.innerHTML = '<tt>' + order_data.order_number + '</tt>';
   var country_list = form.country;
   set_list_value(country_list,order_data.ship_country);
   select_country(country_list,'');
   form.fname.value = order_data.fname;
   form.mname.value = order_data.mname;
   form.lname.value = order_data.lname;
   form.company.value = order_data.company;
   form.address1.value = order_data.ship_address1;
   form.address2.value = order_data.ship_address2;
   form.city.value = order_data.ship_city;
   set_list_value(form.state,order_data.ship_state);
   form.zipcode.value = order_data.ship_zipcode;
   set_radio_button('address_type',order_data.ship_address_type);
   form.phone.value = order_data.bill_phone;
   form.fax.value = order_data.bill_fax;
   form.mobile.value = order_data.bill_mobile;
   form.email.value = order_data.email;
   load_order_items(order_data.id);
}

function update_refund()
{
    if (document.AddRMA) var form = document.AddRMA;
    else var form = document.EditRMA;
    if (! form.refund_amount) return;
    var restocking_fee = parse_amount(form.restocking_fee.value);
    var fields = document.getElementsByTagName('input');
    var refund_amount = 0;   var all_checked = true;
    for (var loop = 0;  loop < fields.length;  loop++) {
       if ((fields[loop].type == 'checkbox') &&
           (fields[loop].name.substr(0,7) == 'return_') &&
           (! fields[loop].disabled)) {
          if (fields[loop].checked) {
             var index = fields[loop].name.substr(7);
             var field_name = 'item_price_' + index;
             var price = parse_amount(form[field_name].value);
             var field_name = 'return_qty_' + index;
             var qty = parseInt(form[field_name].value);
             refund_amount += (price * qty);
          }
          else all_checked = false;
       }
    }
    if (restocking_fee > refund_amount) refund_amount = 0;
    else refund_amount -= restocking_fee;
    if (refund_amount && all_checked)
       refund_amount -= (order_coupon_amount + order_gift_amount +
                         order_discount_amount);
    if (refund_amount < 0) refund_amount = 0;
    if (! refund_amount) form.refund_amount.value = '';
    else form.refund_amount.value = format_amount(refund_amount);
}

function check_return(index)
{
    if (document.AddRMA) var form = document.AddRMA;
    else var form = document.EditRMA;
    var checked = form['return_'+index].checked;
    if (checked) {
       var item_qty = form['item_qty_'+index].value;
       form['return_qty_'+index].value = item_qty;
    }
    else form['return_qty_'+index].value = '';
    update_refund();
}

function change_request_type()
{
   var refund_amount_row = document.getElementById('refund_amount_row');
   if (! refund_amount_row) return;
   var request_type = get_selected_radio_button('request_type');
   if (request_type == 1) {
      update_refund();
      refund_amount_row.style.display = '';
   }
   else {
      refund_amount_row.style.display = 'none';
      if (document.AddRMA) var form = document.AddRMA;
      else var form = document.EditRMA;
      form.refund_amount.value = '';
   }
}

function select_vendor()
{
}

function append_form_value(field_value)
{
    field_value = field_value.replace(/&/g,'&amp;');
    field_value = field_value.replace(/\"/g,'&quot;');
    field_value = field_value.replace(/</g,'&lt;');
    field_value = field_value.replace(/>/g,'&gt;');
    return field_value;
}

function validate_form_fields(form)
{
   var order_id = form.order_id.value;
   if (order_id == '') {
      alert('You must select an Order');   return false;
   }
   var request_type = get_selected_radio_button('request_type');
   if (request_type == '') {
      alert('You must select a Request Type');   return false;
   }
   if (! validate_form_field(form.reason,'Reason')) return false;
   var num_checked = 0;
   for (var index = 0;  index < form.NumItems.value;  index++) {
      if (form['return_'+index].checked) {
         var item_qty = parseInt(form['item_qty_'+index].value);
         var return_qty = parseInt(form['return_qty_'+index].value);
         if (! return_qty) {
            alert('You must specify a Return Quantity');
            form['return_qty_'+index].focus();   return false;
         }
         if (return_qty > item_qty) {
            alert('Return Quantity must be less than or equal to the Order Quantity');
            form['return_qty_'+index].focus();   return false;
         }
         num_checked++;
      }
   }
   if (num_checked == 0) {
      alert('You must select at least one item to return');   return false;
   }
   return true;
}

function process_add_rma()
{
   if (! validate_form_fields(document.AddRMA)) return;
   top.enable_current_dialog_progress(true);
   submit_form_data('rmas.php','cmd=processaddrma',document.AddRMA,
                    finish_add_rma);
}

function finish_add_rma(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var message = ajax_request.parse_value(ajax_request.request.responseText,
                                             'message');
      alert(message);
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if (status == 420) {
      ajax_request.display_error();
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function view_rma()
{
   if (rmas_grid.table._num_rows < 1) {
      alert('There are no RMAs to view');   return;
   }
   if (rmas_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one RMA to view');   return;
   }
   var grid_row = rmas_grid.grid.getCurrentRow();
   var id = rmas_grid.grid.getCellText(0,grid_row);
   var dialog_name = 'view_rma_'+id;
   top.create_dialog(dialog_name,null,null,650,300,false,
                     '../cartengine/rmas.php?cmd=viewrma&id=' + id,null);
}

function edit_rma()
{
   if (rmas_grid.table._num_rows < 1) {
      alert('There are no RMAs to edit');   return;
   }
   if (rmas_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one RMA to edit');   return;
   }
   var grid_row = rmas_grid.grid.getCurrentRow();
   var id = rmas_grid.grid.getCellText(0,grid_row);
   rmas_grid.table.save_position();
   top.create_dialog('edit_rma',null,null,1095,700,false,
                     '../cartengine/rmas.php?cmd=editrma&id=' + id,null);
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

function update_rma()
{
   if (! validate_form_fields(document.EditRMA)) return;
   top.enable_current_dialog_progress(true);
   submit_form_data('rmas.php','cmd=updaterma',document.EditRMA,
                    finish_update_rma);
}

function finish_update_rma(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if (status == 420) {
      ajax_request.display_error();
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_rma()
{
   if (rmas_grid.table._num_rows < 1) {
      alert('There are no RMAs to delete');   return;
   }
   if (rmas_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one RMA to delete');   return;
   }
   var grid_row = rmas_grid.grid.getCurrentRow();
   var id = rmas_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to delete this RMA?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   rmas_grid.table.save_position();
   call_ajax('rmas.php','cmd=deleterma&id=' + id,true,finish_delete_rma);
}

function finish_delete_rma(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function convert_data(col,row,text)
{
   if (col == 2) {
      var status = parse_int(text);
      if (typeof(rma_status_values[status]) == 'undefined') return text;
      return rma_status_values[status];
   }
   if (col == 8) {
      if (text == '') return text;
      var rma_date = new Date(parse_int(text) * 1000);
      return rma_date.format('mmmm d, yyyy h:MM tt');
   }
   return text;
}

function search_rmas()
{
   var query = document.SearchForm.query.value;
   if (query == '') {
      reset_search();   return;
   }
   top.display_status('Search','Searching RMAs...',350,100,null);
   window.setTimeout(function() {
      if (typeof(rmas_search_where) != 'undefined')
         var where = rmas_search_where.replace(/\$query\$/g,query);
      else if (typeof(encrypted_fields) != 'undefined') {
         if (array_index(encrypted_fields,'email') != -1)
            var where = '%DECRYPT%(r.email)';
         else var where = 'r.email';
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'fname') != -1)
            where += '%DECRYPT%(r.fname)';
         else where += 'r.fname';
         where += " like '%" + query + "%' or ";
         if (array_index(encrypted_fields,'lname') != -1)
            where += '%DECRYPT%(r.lname)';
         else where += 'r.lname';
      }
      else var where = "r.email like '%" + query + "%' or r.fname like '%" + query +
                       "%' or r.lname";
      if (typeof(rmas_search_where) == 'undefined')
         where += " like '%" + query + "%' or o.order_number like '%" +
                  query + "%' or r.state like '%" + query +
                  "%' or r.zipcode like '%" + query + "%' or r.id='" + query + "'";
      if ((typeof(top.website) != 'undefined') && (top.website != 0))
         where = '(' + where + ') and (o.website=' + top.website+')';
      if (filter_where != '') where = '(' + where + ') and ' + filter_where;
      rmas_grid.set_where(where);
      rmas_grid.table.reset_data(false);
      rmas_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status('Search','Loading All RMAs...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      if (typeof(rmas_where) != 'undefined') rmas_grid.set_where(rmas_where);
      else if ((typeof(top.website) != 'undefined') && (top.website != 0)) {
         var where = 'o.website=' + top.website;
         rmas_grid.set_where(where);
      }
      else rmas_grid.set_where('');
      rmas_grid.table.reset_data(false);
      rmas_grid.grid.refresh();
      top.remove_status();
   },0);
}

