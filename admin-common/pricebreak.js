/*
            Inroads Shopping Cart - Price Break JavaScript Functions

                        Written 2008-2017 by Randall Severy
                         Copyright 2008-2017 Inroads, LLC
*/

var price_breaks_grid;
var price_form_name;

function create_price_breaks_grid(form_name,price_breaks)
{
   price_form_name = form_name;
   var num_records = 0;
   if (price_breaks != '') {
      var price_array = price_breaks.split("|");
      var array_length = price_array.length;
      var data = new Array();
      for (var loop = 0;  loop < array_length;  loop++) {
         if (price_array[loop] == '') continue;
         num_records++;
         var price_data = price_array[loop].split("-");
         data[loop] = '' + price_data[0] + '|' + price_data[1] + '|' + price_data[2];
      }
   }
   if (typeof(product_dialog_height) == "undefined") var grid_height = 300;
   else var grid_height = product_dialog_height - 100;
   price_breaks_grid = new Grid(null,280,grid_height);
   price_breaks_grid.set_columns(["Start Quantity","End Quantity","Price"]);
   price_breaks_grid.set_column_widths([90,90,90]);
   price_breaks_grid.set_id('price_breaks_grid');
   price_breaks_grid.table.num_records = num_records;
   if (num_records > 0) price_breaks_grid.table._data = data;
   price_breaks_grid.load(false);
   price_breaks_grid.grid.setCellEditable(true,0);
   price_breaks_grid.grid.setCellEditable(true,1);
   price_breaks_grid.grid.setCellEditable(true,2);
   price_breaks_grid.grid.setSelectionMode("single-cell");
   price_breaks_grid.grid.setVirtualMode(false);
   set_grid_navigation(price_breaks_grid.grid);
   price_breaks_grid.display();
}

function enable_price_break_buttons(enable_flag)
{
   var add_price_button = document.getElementById('add_price');
   var delete_price_button = document.getElementById('delete_price');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_price_button.style.display = display_style;
   delete_price_button.style.display = display_style;
   var price_buttons_row = document.getElementById('price_buttons_row');
   if (price_buttons_row) price_buttons_row.style.display = display_style;
}

function add_price()
{
   var row_data = [];
   row_data[0] = '';   row_data[1] = '';   row_data[2] = '';
   price_breaks_grid.table.add_row(row_data);
   price_breaks_grid.grid.focus_cell(0,price_breaks_grid.table._num_rows - 1);
}

function delete_price()
{
   if (price_breaks_grid.table._num_rows < 1) {
      alert('There are no prices to delete');   return;
   }
   price_breaks_grid.table.delete_row();
}

function update_price_breaks()
{
   var num_rows = price_breaks_grid.table._num_rows;
   var price_breaks_field = document.forms[price_form_name].price_breaks;
   var price_breaks = '';
   for (var loop = 0;  loop < num_rows;  loop++) {
      var start_qty_value = price_breaks_grid.grid.getCellText(0,loop);
      if (start_qty_value == '') continue;
      var start_qty = parse_int(start_qty_value);
      var end_qty_value = price_breaks_grid.grid.getCellText(1,loop);
      if (end_qty_value == '') continue;
      var end_qty = parse_int(end_qty_value);
      var price_value = price_breaks_grid.grid.getCellText(2,loop);
      if (price_value == '') continue;
      var price = parse_amount(price_value);
      if (price_breaks != '') price_breaks += '|';
      price_breaks += start_qty + '-' + end_qty + '-' + price;
   }
   price_breaks_field.value = price_breaks;
}

function set_price_breaks()
{
   if (products.left_grid.table._num_rows < 1) {
      alert('There are no products to set price breaks for');   return;
   }
   var id = document.forms[products.form_name].id.value;
   top.create_dialog('set_price_breaks',null,null,500,375,false,
                     '../cartengine/categories.php?cmd=setpricebreaks&id=' + id,null);
}

function apply_price_breaks()
{
   top.enable_current_dialog_progress(true);
   update_price_breaks();
   submit_form_data("categories.php","cmd=applypricebreaks",document.SetPriceBreaks,
                    finish_apply_price_breaks);
}

function finish_apply_price_breaks(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) top.close_current_dialog();
   else ajax_request.display_error();
}

