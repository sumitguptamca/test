/*

     Inroads Shopping Cart - Vendor Category Mapping Tab JavaScript Functions

                          Written 2014-2018 by Randall Severy
                           Copyright 2014-2018 Inroads, LLC
*/

var mapping_grid = null;
var cancel_add_category = true;

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(mapping_grid,-1,new_height -
                   get_grid_offset(mapping_grid));
    else resize_grid(mapping_grid,new_width,new_height)
}

function build_where()
{
    var vendor_list = document.getElementById('vendor');
    var vendor = vendor_list.options[vendor_list.selectedIndex].value;
    var unmapped_only = document.getElementById('unmapped_only').checked;
    if (vendor) var where = '(vendor_id=' + vendor + ')';
    else var where = 'isnull(vendor_id)';
    if (unmapped_only) where += ' and isnull(category_id)';
    var query = document.SearchForm.query.value;
    if (query) {
       query = query.replace(/'/g,'\\\'');
       where += ' and (vendor_category like "%' + query + '%")';
    }
    return where;
}

function set_cell_color()
{
    return this.getCellProperty('color');
}

function load_grid()
{
    var grid_size = get_default_grid_size();
    mapping_grid = new Grid('category_mapping',grid_size.width,grid_size.height);
    mapping_grid.set_server_sorting(true);
    mapping_grid.set_field_names(['id','vendor_category','num_products',
                                  'mapped_category']);
    mapping_grid.set_columns(['ID','Vendor Category','#','Mapped Category']);
    mapping_grid.set_column_widths([0,400,30,400]);
    var query = 'select id,vendor_category,num_products,(select c.name from ' +
                'categories c where c.id=category_id) as mapped_category ' +
                'from category_mapping';
    mapping_grid.set_query(query);
    mapping_grid.set_where(build_where());
    mapping_grid.set_order_by('vendor_category');
    mapping_grid.table.set_convert_cell_data(convert_data);
    mapping_grid.set_id('mapping_grid');
    mapping_grid.load(true);
    mapping_grid.grid.defineCellProperty('color',get_cell_color);
    mapping_grid.grid.getCellTemplate(2).setStyle('color',set_cell_color);
    mapping_grid.set_double_click_function(edit_mapping);
    mapping_grid.display();
}

function get_cell_color(col,row)
{
    var num_products = this.table._row_data[col];
    if (num_products < 0) return '#DD0000';
    return '#000000';
}

function reload_grid()
{
    mapping_grid.table.reset_data(true);
    mapping_grid.grid.refresh();
    window.setTimeout(function() { mapping_grid.table.restore_position(); },0);
}

function update_grid_where()
{
    mapping_grid.set_where(build_where());
    mapping_grid.table.reset_data(true);
    mapping_grid.grid.refresh();
}

function select_vendor()
{
    update_grid_where();
    var vendor_list = document.getElementById('vendor');
    var vendor = vendor_list.options[vendor_list.selectedIndex].value;
    if (vendor == '-1') var display = '';
    else var display = 'none';
    var add_mapping_button = document.getElementById('add_mapping');
    add_mapping_button.style.display = display;
    var import_button_row = document.getElementById('import_button_row');
    import_button_row.style.display = display;
    var import_products_button = document.getElementById('import_products');
    import_products_button.style.display = display;
}

function search_mapping()
{
   var query = document.SearchForm.query.value;
   if (query == '') {
      reset_search();   return;
   }
   top.display_status('Search','Searching Vendor Categories...',350,100,null);
   window.setTimeout(function() {
      update_grid_where();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status('Search','Loading All Vendor Categories...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      update_grid_where();
      top.remove_status();
   },0);
}

function add_mapping()
{
    mapping_grid.table.save_position();
    top.create_dialog('add_mapping',null,null,880,90,false,
                      '../cartengine/mapping.php?cmd=addmapping',null);
}

function process_add_mapping()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('mapping.php','cmd=processaddmapping',
                     document.AddMapping,finish_add_mapping);
}

function finish_add_mapping(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_mapping()
{
    if (mapping_grid.table._num_rows < 1) {
       alert('There are no category mappings to edit');   return;
    }
    var grid_row = mapping_grid.grid.getCurrentRow();
    var id = mapping_grid.grid.getCellText(0,grid_row);
    mapping_grid.table.save_position();
    top.create_dialog('edit_mapping',null,null,880,90,false,
                      '../cartengine/mapping.php?cmd=editmapping&id=' + id,null);
}

function update_mapping()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('mapping.php','cmd=updatemapping',
                     document.EditMapping,finish_update_mapping);
}

function finish_update_mapping(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_grid();
       top.close_current_dialog();
    }
}

function delete_mapping()
{
    if (mapping_grid.table._num_rows < 1) {
       alert('There are no category mappings to delete');   return;
    }
    var grid_row = mapping_grid.grid.getCurrentRow();
    var id = mapping_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this category mapping?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    mapping_grid.table.save_position();
    call_ajax('mapping.php','cmd=deletemapping&id=' + id,true,
              finish_delete_mapping);
}

function finish_delete_mapping(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function mapping_form()
{
    if (document.AddMapping) return document.AddMapping;
    return document.EditMapping;
}

function update_mapped_category_list(query)
{
    if (query) query = query.toLowerCase();
    var list = document.getElementById('category_id');
    var display = '';
    for (var index in list.options) {
       if (isNaN(index)) continue;
       if (! list.options[index].text) display = '';
       else if (! query) display = '';
       else if (list.options[index].text.toLowerCase().indexOf(query) === -1)
          display = 'none';
       else display = '';
       list.options[index].style.display = display;
    }
    list.selectedIndex = 0;
}

function map_search()
{
    var form = mapping_form();
    var query = form.query.value;
    if (query == '') {
       reset_map_search();   return;
    }
    update_mapped_category_list(query);
}

function map_reset_search()
{
    var form = mapping_form();
    form.query.value = '';
    update_mapped_category_list('');
}

function add_category()
{
   cancel_add_category = true;
   top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('../cartengine/categories.php',
                               'cmd=createcategory',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_category,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_category(ajax_request,sublist)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var category_id = -1;
   eval(ajax_request.request.responseText);

   var url = '../cartengine/categories.php?cmd=addcategory&id=' + category_id;
   var dialog_name = 'add_category';
   if (top.skin) var dialog_width = 1000;
   else var dialog_width = 900;
   top.create_dialog(dialog_name,null,null,dialog_width,535,false,url,null);
}

function convert_data(col,row,text)
{
   if ((col == 2) && (text < 0)) {
      text = -text;   return text.toString();
   }
   return text;
}

function import_products()
{
   top.create_dialog('import_products',null,null,500,145,false,
      '../cartengine/mapping.php?cmd=importproducts',null);
}

function process_import_products()
{
   var filename = document.ImportProducts.Filename.value;
   if (filename == "") {
      alert('You must select an Import File');   return;
   }
   top.enable_current_dialog_progress(true);
   top.set_current_dialog_title('Import Product Results');
   top.display_status('Import','Importing Products...',500,100,null);
   document.ImportProducts.submit();
}

function finish_import_products()
{
   top.remove_status();
   top.resize_current_dialog();
   top.enable_current_dialog_progress(false);
}

