/*
          Inroads Shopping Cart - Quantity Discounts JavaScript Functions

                       Written 2012-2019 by Randall Severy
                        Copyright 2012-2019 Inroads, LLC
*/

var standard_discounts_grid = null;
var wholesale_discounts_grid = null;
var discount_header;
var discount_label;

function create_discounts_grid(discount_type,product_id)
{
   if (top.skin) var grid_width = -100;
   else var grid_width = 310;
   if (typeof(product_dialog_height) == "undefined") var grid_height = 300;
   else var grid_height = product_dialog_height - 100;
   var discounts_grid = new Grid("product_discounts",grid_width,grid_height);
   discounts_grid.set_columns(["Id","Start Quantity","End Quantity",
                               discount_header]);
   discounts_grid.set_field_names(["id","start_qty","end_qty","discount"]);
   discounts_grid.set_column_widths([0,100,100,100]);
   var query = "select id,start_qty,end_qty,discount from product_discounts";
   discounts_grid.set_query(query);
   var where = "((discount_type=" + discount_type + ") and (parent=" +
               product_id + "))";
   discounts_grid.set_where(where);
   discounts_grid.set_order_by("start_qty,end_qty");
   discounts_grid.set_id('discounts_' + discount_type + '_grid');
   discounts_grid.table._url = "products.php";
   discounts_grid.table.add_update_parameter("cmd","updatediscount");
   discounts_grid.table.add_update_parameter("discount_type",discount_type);
   discounts_grid.table.add_update_parameter("parent",product_id);
   discounts_grid.load(true);
   discounts_grid.grid.setCellEditable(true,1);
   discounts_grid.grid.setCellEditable(true,2);
   discounts_grid.grid.setCellEditable(true,3);
   discounts_grid.grid.setSelectionMode("single-cell");
   discounts_grid.grid.setVirtualMode(false);
   set_grid_navigation(discounts_grid.grid);
   discounts_grid.display();
   if (discount_type == 0) standard_discounts_grid = discounts_grid;
   else wholesale_discounts_grid = discounts_grid;
}

function set_discounts_parent(parent)
{
   if (standard_discounts_grid) {
      var where = '((discount_type=0) and (parent=' + parent + '))';
      standard_discounts_grid.set_where(where);
      standard_discounts_grid.table.add_update_parameter('parent',parent);
      standard_discounts_grid.table.reset_data(true);
      standard_discounts_grid.grid.refresh();
   }
   if (wholesale_discounts_grid) {
      var where = '((discount_type=1) and (parent=' + parent + '))';
      wholesale_discounts_grid.set_where(where);
      wholesale_discounts_grid.table.add_update_parameter('parent',parent);
      wholesale_discounts_grid.table.reset_data(true);
      wholesale_discounts_grid.grid.refresh();
   }
}

function enable_discount_buttons(enable_flag)
{
   var add_discount_button = document.getElementById('add_standard_discount');
   var delete_discount_button = document.getElementById('delete_standard_discount');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_discount_button.style.display = display_style;
   delete_discount_button.style.display = display_style;
   var discount_row = document.getElementById('standard_discount_row');
   if (discount_row) {
      discount_row.style.display = display_style;
      var discount_row_sep = document.getElementById('discount_row_sep');
      if (discount_row_sep) discount_row_sep.style.display = display_style;
      discount_row = document.getElementById('wholesale_discount_row');
      if (discount_row) discount_row.style.display = display_style;
      add_discount_button = document.getElementById('add_wholesale_discount');
      delete_discount_button = document.getElementById('delete_wholesale_discount');
      if (add_discount_button) add_discount_button.style.display = display_style;
      if (delete_discount_button) delete_discount_button.style.display = display_style;
   }
}

function add_discount(discount_type)
{
   if (discount_type == 0) var discounts_grid = standard_discounts_grid;
   else var discounts_grid = wholesale_discounts_grid;
   var row_data = [];
   row_data[0] = '';   row_data[1] = '';   row_data[2] = '';
   row_data[3] = '';
   discounts_grid.table.add_row(row_data);
   discounts_grid.grid.focus_cell(0,discounts_grid.table._num_rows - 1);
}

function delete_discount(discount_type)
{
   if (discount_type == 0) var discounts_grid = standard_discounts_grid;
   else var discounts_grid = wholesale_discounts_grid;
   if (discounts_grid.table._num_rows < 1) {
      alert('There are no ' + discount_label + 's to delete');   return;
   }
   discounts_grid.table.delete_row();
}

function update_discounts()
{
   if (standard_discounts_grid)
      standard_discounts_grid.table.process_updates(false);
   if (wholesale_discounts_grid)
      wholesale_discounts_grid.table.process_updates(false);
}

