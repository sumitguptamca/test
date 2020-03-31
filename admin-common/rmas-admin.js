/*

      Inroads Control Panel - Customer RMAs Tabs JavaScript Functions

                     Written 2015-2018 by Randall Severy
                      Copyright 2015-2018 Inroads, LLC
*/

var rmas_grid = null;

function create_rmas_grid(parent)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    rmas_grid = new Grid('rmas',grid_width,250);
    rmas_grid.set_columns(['RMA #','Order #','Status','Request Date']);
    rmas_grid.set_column_widths([50,75,100,155]);
    var query = 'select r.id,o.order_number,r.status,r.request_date from ' +
                'rmas r left join orders o on r.order_id=o.id';
    rmas_grid.set_query(query);
    rmas_grid.set_where('o.customer_id=' + parent);
    rmas_grid.set_order_by('r.id desc');
    rmas_grid.table.set_convert_cell_data(convert_rma_data);
    rmas_grid.load(true);
    rmas_grid.set_double_click_function(view_rma);
    rmas_grid.display();
}

function view_rma()
{
   if (rmas_grid.table._num_rows < 1) {
      alert('There are no RMAs to view');   return;
   }
   var grid_row = rmas_grid.grid.getCurrentRow();
   var id = rmas_grid.grid.getCellText(0,grid_row);
   var dialog_name = 'view_rma_'+id;
   top.create_dialog(dialog_name,null,null,650,300,false,
                     '../cartengine/rmas.php?cmd=viewrma&id=' + id,null);
}

function convert_rma_data(col,row,text)
{
    if (col == 2) {
       var status = parse_int(text);
       if (typeof(rma_status_values[status]) == 'undefined') return text;
       return rma_status_values[status];
    }
    if (col == 3) {
       if (text == '') return text;
       var rma_date = new Date(parse_int(text) * 1000);
       return rma_date.format('mmmm d, yyyy h:MM tt');
    }
    return text;
}

function enable_rma_buttons(enable_flag)
{
    var view_rma_button = document.getElementById('view_rma');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    view_rma_button.style.display = display_style;
}

function change_rmas_tab(tab,content_id)
{
    var show_sep = false;
    if (content_id == 'rmas_content') {
       enable_rma_buttons(true);   show_sep = true;
    }
    else enable_rma_buttons(false);
    var rma_buttons_row = document.getElementById('rma_buttons_row');
    if (rma_buttons_row) {
       if (show_sep) rma_buttons_row.style.display = '';
       else rma_buttons_row.style.display = 'none';
    }
}

