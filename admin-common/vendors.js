/*

            Inroads Shopping Cart - Vendors Tab JavaScript Functions

                        Written 2014-2019 by Randall Severy
                         Copyright 2014-2019 Inroads, LLC
*/

var cancel_add_vendor = true;
var vendors_grid = null;
var imports_grid = null;
var enable_vendor_imports = false;

function resize_screen(new_width,new_height)
{
    if (! enable_vendor_imports) {
       if (top.skin)
          resize_grid(vendors_grid,-1,new_height - get_grid_offset(vendors_grid));
       else resize_grid(vendors_grid,new_width,new_height)
    }
    else if (top.skin) {
       if (top.buttons == 'left') {
          var button_column1 = document.getElementById('buttonColumn1');
          var button_column2 = document.getElementById('buttonColumn2');
          var offset = get_body_height() -
                       Math.max(vendors_grid.height,button_column1.offsetHeight) -
                       Math.max(imports_grid.height,button_column2.offsetHeight);
       }
       else var offset = get_body_height() - vendors_grid.height -
                         imports_grid.height;
       var grid_height = Math.floor((new_height - offset) / 2);
       if (grid_height < 0) grid_height = 0;
       if (top.buttons == 'left') {
          resize_grid(vendors_grid,vendors_grid.width,
                      Math.max(grid_height,button_column1.offsetHeight));
          resize_grid(imports_grid,imports_grid.width,
                      Math.max(grid_height,button_column2.offsetHeight));
       }
       else {
          resize_grid(vendors_grid,vendors_grid.width,grid_height);
          resize_grid(imports_grid,imports_grid.width,grid_height);
       }
    }
    else {
       new_height = ((new_height + 20) / 2) - 15;
       if (top.buttons == 'top') new_height -= 40;
       resize_grid(vendors_grid,new_width,new_height)
       resize_grid(imports_grid,new_width,new_height);
       var sep_row = document.getElementById('imports_sep_row');
       var row_height = new_height - 125;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function load_vendors_grid()
{
    var grid_size = get_default_grid_size();
    if (enable_vendor_imports) {
       var grid_height = Math.floor(grid_size.height / 2) - 15;
       if (top.buttons == 'top') grid_height -= 40;
    }
    else var grid_height = grid_size.height;
    vendors_grid = new Grid('vendors',grid_size.width,grid_height);
    if (typeof(vendor_columns) == 'undefined')
       vendor_columns = ['ID','Name','Contact','Company','# Products',
                         'Total Sales','Total Cost','Default Shipping'];
    vendors_grid.set_columns(vendor_columns);
    if (typeof(vendor_column_widths) == 'undefined')
       vendor_column_widths = [0,200,200,200,75,100,100,100];
    vendors_grid.set_column_widths(vendor_column_widths);
    if (typeof(vendor_query) == 'undefined')
       vendor_query = 'select v.id,v.name,v.contact,v.company,' +
          '(select count(p.id) as num_products from products p where ' +
          'p.vendor=v.id) as num_products,' +
          '(select sum(i.price*i.qty) from order_items i join products p on ' +
          'p.id=i.product_id where p.vendor=v.id) as total_sales,' +
          '(select sum(i.cost*i.qty) from order_items i join products p on ' +
          'p.id=i.product_id where p.vendor=v.id) as total_cost,' +
          'v.default_shipping from vendors v';
    vendors_grid.set_query(vendor_query);
    if (typeof(vendor_info_query) == 'undefined')
       vendor_info_query = 'select count(v.id) from vendors v';
    vendors_grid.set_info_query(vendor_info_query);
    vendors_grid.set_order_by('v.name');
    if (typeof(custom_convert_vendor_data) != 'undefined')
       vendors_grid.table.set_convert_cell_data(custom_convert_vendor_data);
    else vendors_grid.table.set_convert_cell_data(convert_vendor_data);
    vendors_grid.set_id('vendors_grid');
    vendors_grid.load(false);
    vendors_grid.set_double_click_function(edit_vendor);
    if (enable_vendor_imports)
       vendors_grid.grid.onCurrentRowChanged = select_vendor;
    vendors_grid.display();
    if (enable_vendor_imports && (! top.skin)) {
       var sep_row = document.getElementById('imports_sep_row');
       var row_height = grid_height - 105;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function reload_vendors_grid()
{
    vendors_grid.table.reset_data(false);
    vendors_grid.grid.refresh();
    window.setTimeout(function() {
       vendors_grid.table.restore_position();
       var grid_row = vendors_grid.grid.getCurrentRow();
       select_vendor(grid_row);
    },0);
}

function select_vendor(row)
{
    var vendor_id = vendors_grid.grid.getCellText(0,row);
    var name = vendors_grid.grid.getCellText(1,row);
    var imports_label = document.getElementById('imports_label');
    imports_label.innerHTML = name;
    imports_grid.table.set_where('i.parent=' + vendor_id);
    imports_grid.table.reset_data(true);
    imports_grid.grid.refresh();
}

function add_vendor()
{
    cancel_add_vendor = true;
    top.enable_current_dialog_progress(true);
    var ajax_request = new Ajax('../cartengine/vendors.php',
                                'cmd=createvendor',true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_vendor,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function continue_add_vendor(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status != 200) return;

    var vendor_id = -1;
    eval(ajax_request.request.responseText);

    vendors_grid.table.save_position();
    top.create_dialog('add_vendor',null,null,680,400,false,
                      '../cartengine/vendors.php?cmd=addvendor&id=' +
                      vendor_id,null);
}

function add_vendor_onclose(user_close)
{
    if (cancel_add_vendor) {
       var vendor_id = document.AddVendor.id.value;
       call_ajax('../cartengine/vendors.php','cmd=deletevendor&cancel=true&id=' +
                 vendor_id,true);
    }
}

function add_vendor_onload()
{
    top.set_current_dialog_onclose(add_vendor_onclose);
}

function process_add_vendor()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/vendors.php','cmd=processaddvendor',
                     document.AddVendor,finish_add_vendor);
}

function finish_add_vendor(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_vendor = false;
       top.get_content_frame().reload_vendors_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_vendor()
{
    if (vendors_grid.table._num_rows < 1) {
       alert('There are no vendors to edit');   return;
    }
    var grid_row = vendors_grid.grid.getCurrentRow();
    var id = vendors_grid.grid.getCellText(0,grid_row);
    vendors_grid.table.save_position();
    top.create_dialog('edit_vendor',null,null,680,400,false,
                      '../cartengine/vendors.php?cmd=editvendor&id=' + id,null);
}

function update_vendor()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/vendors.php','cmd=updatevendor',
                     document.EditVendor,finish_update_vendor);
}

function finish_update_vendor(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_vendors_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_vendor()
{
    if (vendors_grid.table._num_rows < 1) {
       alert('There are no vendors to delete');   return;
    }
    var grid_row = vendors_grid.grid.getCurrentRow();
    var id = vendors_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this vendor?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    vendors_grid.table.save_position();
    call_ajax('../cartengine/vendors.php','cmd=deletevendor&id=' + id,true,
              finish_delete_vendor);
}

function finish_delete_vendor(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_vendors_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function convert_vendor_data(col,row,text)
{
    if ((col == 5) || (col == 6) || (col == 7)) {
       if (text == '') return text;
       return format_amount(text,'USD',false);
    }
    return text;
}

function search_vendors()
{
    var query = document.SearchForm.query.value;
    if (query == '') {
       reset_search();   return;
    }
    top.display_status('Search','Searching Vendors...',350,100,null);
    window.setTimeout(function() {
       var where = '(v.name like "%' + query + '%") or ' +
                   '(v.contact like "%' + query + '%") or ' +
                   '(v.company like "%' + query + '%")';
       vendors_grid.set_where(where);
       vendors_grid.table.reset_data(false);
       vendors_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_search()
{
    top.display_status('Search','Loading All Vendors...',350,100,null);
    window.setTimeout(function() {
       document.SearchForm.query.value = '';
       vendors_grid.set_where('');
       vendors_grid.table.reset_data(false);
       vendors_grid.grid.refresh();
       top.remove_status();
    },0);
}

function change_tab(tab,content_id)
{
    tab_click(tab,content_id);
    top.grow_current_dialog();
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

function change_markups()
{
    var markups_by = get_selected_radio_button('markups_by');
    var num_markups_row = document.getElementById('num_markups_row');
    if (markups_by == -1) num_markups_row.style.display = 'none';
    else num_markups_row.style.display = '';
}

function change_new_order_flag()
{
    var new_order_flag = get_selected_radio_button('new_order_flag');
    var send_order_row = document.getElementById('send_order_row');
    var submit_email_row = document.getElementById('submit_email_row');
    var sent_status_row = document.getElementById('sent_status_row');
    if (new_order_flag == 0) {
       send_order_row.style.display = 'none';
       sent_status_row.style.display = 'none';
    }
    else {
       send_order_row.style.display = '';
       sent_status_row.style.display = '';
    }
    if (new_order_flag == 1) submit_email_row.style.display = '';
    else submit_email_row.style.display = 'none';
}

function change_edi_interface()
{
    var edi_interface = get_selected_radio_button('edi_interface');
    var edi_sender_row = document.getElementById('edi_sender_row');
    var edi_receiver_row = document.getElementById('edi_receiver_row');
    var ftp_dir_row = document.getElementById('ftp_dir_row');
    if (edi_interface == 0) {
       edi_sender_row.style.display = 'none';
       edi_receiver_row.style.display = 'none';
    }
    else {
       edi_sender_row.style.display = '';
       edi_receiver_row.style.display = '';
    }
    if (edi_interface == 2) ftp_dir_row.style.display = '';
    else ftp_dir_row.style.display = 'none';
}

function load_imports_grid(vendor_id)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2) - 15;
    if (top.buttons == 'top') grid_height -= 40;
    imports_grid = new Grid('vendor_imports',grid_size.width,grid_height);
    imports_grid.table.set_field_names(['id','name','import_type',
       'import_source','data_updated','import_started','import_finished',
       'import_file','last_error']);
    imports_grid.set_columns(['ID','Name','Type','Source','# Products',
       'Data Updated','Started','Finished','Import File','Last Error']);
    imports_grid.set_column_widths([30,300,100,60,70,140,140,140,0,800]);
    var query = 'select i.id,i.name,i.import_type,i.import_source,(select ' +
       'count(p.id) as num_products from products p where p.import_id=i.id) ' +
       'as num_products,i.import_file as data_updated,i.import_started,' +
       'i.import_finished,i.import_file,0 as last_error from vendor_imports i';
    imports_grid.table.set_query(query);
    imports_grid.set_where('i.parent=' + vendor_id);
    imports_grid.set_info_query('select count(i.id) from vendor_imports i');
    imports_grid.table.set_order_by('i.id');
    imports_grid.table.set_convert_cell_data(convert_import_data);
    imports_grid.set_id('imports_grid');
    imports_grid.table.setURL('../cartengine/vendors.php');
    imports_grid.load(false);
    imports_grid.set_double_click_function(edit_import);
    imports_grid.grid.onCurrentRowChanged = select_import;
    imports_grid.grid.onControlRefreshed = function() {
       var grid_row = imports_grid.grid.getCurrentRow();
       select_import(grid_row);
    };
    imports_grid.display();
}

function reload_imports_grid()
{
    imports_grid.table.reset_data(true);
    imports_grid.grid.refresh();
    window.setTimeout(function() {
       imports_grid.table.restore_position();
       var grid_row = imports_grid.grid.getCurrentRow();
       select_import(grid_row);
    },0);
}

function select_import(row)
{
    var edit_button = document.getElementById('edit_import');
    var copy_button = document.getElementById('copy_import');
    var delete_button = document.getElementById('delete_import');
    if (imports_grid.table._num_rows == 0) {
       edit_button.style.display = 'none';
       copy_button.style.display = 'none';
       delete_button.style.display = 'none';
       var source = null;   var import_file = null;   var import_error = null;
       var started_date = null;   var finished_date = null;
    }
    else {
       edit_button.style.display = '';
       copy_button.style.display = '';
       delete_button.style.display = '';
       var source = imports_grid.grid.getCellText(3,row);
       var started_date = imports_grid.grid.getCellText(6,row);
       var finished_date = imports_grid.grid.getCellText(7,row);
       var import_file = imports_grid.grid.getCellText(8,row);
       var import_error = imports_grid.grid.getCellText(9,row);
    }
    var mapping_button = document.getElementById('import_mapping');
    var upload_button = document.getElementById('import_upload');
    var get_button = document.getElementById('import_get');
    var download_button = document.getElementById('import_download');
    var import_button = document.getElementById('start_import');
    var clear_button = document.getElementById('clear_import');
    var reset_button = document.getElementById('reset_import');
    mapping_button.style.display = '';
    upload_button.style.display = '';
    get_button.style.display = 'none';
    download_button.style.display = '';
    import_button.style.display = '';
    if (import_error) clear_button.style.display = '';
    else clear_button.style.display = 'none';
    if (started_date && (! finished_date) && (! import_error))
       reset_button.style.display = '';
    else reset_button.style.display = 'none';
    if ((source == 'Upload') && import_file) return;
    if (! import_file) {
       if (source >= 0) mapping_button.style.display = 'none';
       download_button.style.display = 'none';
       if (source == 'Upload') import_button.style.display = 'none';
    }
    if (source != 'Upload') upload_button.style.display = 'none';
    if (source == 'EDI') import_button.style.display = 'none';
    if ((source == 'Download') || (source == 'FTP') || (source == 'SFTP'))
       get_button.style.display = '';
}

function add_edit_import_onload()
{
    var import_source = get_selected_radio_button('import_source');
    if (import_source != 6) {
       var other_import = document.getElementById('load_existingOther Import');
       if (other_import) {
          other_import.style.display = 'none';
          other_import.nextSibling.style.display = 'none';
       }
    }
}

function change_import_type()
{
    var import_type = get_selected_radio_button('import_type');
    var import_source = get_selected_radio_button('import_source');
    if ((import_source == 1) || (import_source == 4)) {
       var ftp_file_row = document.getElementById('ftp_file_row');
       if (import_type == 4) ftp_file_row.style.display = 'none';
       else ftp_file_row.style.display = '';
    }
    top.grow_current_dialog();
}

function set_row_style(row_id,display)
{
    var row = document.getElementById(row_id);
    if (row) row.style.display = display;
}

function change_import_source()
{
    var import_type = get_selected_radio_button('import_type');
    var import_source = get_selected_radio_button('import_source');
    var load_existing = get_selected_radio_button('load_existing');
    if (import_source == 6) {
       var other_display = '';
       if (load_existing == 1) set_radio_button('load_existing',2);
    }
    else {
       var other_display = 'none';
       if (load_existing == 2) set_radio_button('load_existing',-1);
    }
    set_row_style('other_import_source_row',other_display);
    var other_import = document.getElementById('load_existingOther Import');
    other_import.style.display = other_display;
    other_import.nextSibling.style.display = other_display;
    if ((import_source == 1) || (import_source == 4)) var ftp_display = '';
    else var ftp_display = 'none';
    set_row_style('ftp_host_row',ftp_display);
    set_row_style('ftp_user_row',ftp_display);
    if (import_type == 4) sftp_display = 'none';
    else sftp_display = ftp_display;
    set_row_style('ftp_file_row',sftp_display);
    if (import_source == 3) {
       var edi_display = '';   var non_edi_display = 'none';
       var sheet_display = 'none';
    }
    else if (import_source == 7) {
       var edi_display = 'none';   var non_edi_display = '';
       var sheet_display = 'none';
    }
    else {
       var edi_display = 'none';   var non_edi_display = '';
       var sheet_display = '';
    }
    set_row_style('image_dir_row',non_edi_display);
    set_row_style('sheet_row',sheet_display);
    set_row_style('image_options_row',non_edi_display);
    set_row_style('avail_row',non_edi_display);
    set_row_style('notavail_row',non_edi_display);
    set_row_style('new_status_row',non_edi_display);
    set_row_style('noimage_status_row',non_edi_display);
    set_row_style('non_match_row',non_edi_display);
    set_row_style('discon_row',edi_display);
    set_row_style('min_qty_row',edi_display);
    set_row_style('discon_status_row',edi_display);
    set_row_style('new_inv_qty_row',non_edi_display);
    set_row_style('group_options_row',non_edi_display);
    set_row_style('group_template_row',non_edi_display);
    set_row_style('flags_row',non_edi_display);
    if (typeof(custom_change_import_source) != 'undefined')
       custom_change_import_source();
    top.grow_current_dialog();
}

function add_import()
{
    if (vendors_grid.table._num_rows < 1) {
       alert('There are no vendors to add an import for');   return;
    }
    var grid_row = vendors_grid.grid.getCurrentRow();
    var id = vendors_grid.grid.getCellText(0,grid_row);
    imports_grid.table.save_position();
    top.create_dialog('add_import',null,null,800,440,false,
                      '../cartengine/vendors.php?cmd=addimport&parent=' + id,
                      null);
}

function process_add_import()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/vendors.php','cmd=processaddimport',
                     document.AddImport,finish_add_import);
}

function finish_add_import(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_imports_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_import()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to edit');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var id = imports_grid.grid.getCellText(0,grid_row);
    imports_grid.table.save_position();
    top.create_dialog('edit_import',null,null,800,440,false,
                      '../cartengine/vendors.php?cmd=editimport&id=' + id,
                      null);
}

function update_import()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/vendors.php','cmd=updateimport',
                     document.EditImport,finish_update_import);
}

function finish_update_import(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_imports_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function copy_import()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to copy');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var id = imports_grid.grid.getCellText(0,grid_row);
    var name = imports_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to copy the '+name +
                           ' import?');
    if (! response) return;
    imports_grid.table.save_position();
    call_ajax('../cartengine/vendors.php','cmd=copyimport&id=' + id,true,
              finish_copy_import,60);
}

function finish_copy_import(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) reload_imports_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_import()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to delete');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var id = imports_grid.grid.getCellText(0,grid_row);
    var name = imports_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the ' + name +
                           ' import?');
    if (! response) return;
    imports_grid.table.save_position();
    call_ajax('../cartengine/vendors.php','cmd=deleteimport&id=' + id,true,
              finish_delete_import);
}

function finish_delete_import(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) reload_imports_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function import_mapping()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to map fields for');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var id = imports_grid.grid.getCellText(0,grid_row);
    var import_file = imports_grid.grid.getCellText(8,grid_row);
    var source = imports_grid.grid.getCellText(3,grid_row);
    if ((! import_file) && (source >= 0)) {
       alert('There is no import file to map fields for');   return;
    }
    var url = '../cartengine/vendors.php?cmd=importmapping&id=' + id;
    top.create_dialog('import_mapping',null,null,1050,100,false,url,null);
}

function update_mapping()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/vendors.php','cmd=updatemapping',
                     document.ImportMapping,finish_update_mapping);
}

function finish_update_mapping(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) top.close_current_dialog();
}

function import_upload()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to upload data for');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var id = imports_grid.grid.getCellText(0,grid_row);
    var url = '../cartengine/vendors.php?cmd=uploadfile&id=' + id;
    top.create_dialog('import_upload',null,null,475,80,false,url,null);
}

function process_import_upload()
{
   var filename = document.UploadFile.Filename.value;
   if (filename == '') {
      alert('You must select a File to Upload');   return;
   }
   top.enable_current_dialog_progress(true);
   document.UploadFile.submit();
}

function finish_import_upload()
{
    reload_imports_grid();
    top.close_current_dialog();
}

function import_get()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to get data for');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var import_id = imports_grid.grid.getCellText(0,grid_row);
    grid_row = vendors_grid.grid.getCurrentRow();
    var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
    imports_grid.table.save_position();
    var fields = 'cmd=getimport&id=' + import_id;
    var ajax_request = new Ajax('../cartengine/vendors.php',fields,true);
    ajax_request.set_timeout(300);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_import_get,{
       vendor_id: vendor_id, import_id: import_id });
    top.display_status('Import','Getting Import Data...',300,100,null);
    ajax_request.send();

}

function finish_import_get(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) reload_imports_grid();
    else if (status == 202) {
       reload_imports_grid();
       alert('Import Get Data Started');
       window.setTimeout(function() {
          check_import_get(ajax_data.vendor_id,ajax_data.import_id);
       },(60 * 1000));
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function check_import_get(vendor_id,import_id)
{
    var fields = 'cmd=checkimportget&import=' + import_id;
    var ajax_request = new Ajax('../cartengine/vendors.php',fields,true);
    ajax_request.enable_parse_response();
    ajax_request.disable_log_server_errors();
    ajax_request.set_callback_function(finish_check_import_get,{
       vendor_id: vendor_id, import_id: import_id });
    ajax_request.send();
}

function finish_check_import_get(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;
    var status = ajax_request.get_status();
    if (status == 201) {
       grid_row = vendors_grid.grid.getCurrentRow();
       var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
       if (vendor_id == ajax_data.vendor_id) reload_imports_grid();
       alert('Import #' + ajax_data.import_id + ' Get Data has finished');
    }
    else if (status == 202) {
       window.setTimeout(function() {
          check_import_get(ajax_data.vendor_id,ajax_data.import_id);
       },(60 * 1000));
    }
    else if (status == 412) {
       grid_row = vendors_grid.grid.getCurrentRow();
       var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
       if (vendor_id == ajax_data.vendor_id) reload_imports_grid();
       ajax_request.display_error();
    }
    else ajax_request.display_error();
}

function import_download()
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to download data for');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var import_file = imports_grid.grid.getCellText(8,grid_row);
    var url = '../cartengine/vendors.php?cmd=downloadfile&file=' +
              encodeURIComponent(import_file);
    location.href = url;
}

function start_import(index)
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to run');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var import_id = imports_grid.grid.getCellText(0,grid_row);
    grid_row = vendors_grid.grid.getCurrentRow();
    var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
    var fields = 'cmd=startimport&import=' + import_id;
    imports_grid.table.save_position();
    var ajax_request = new Ajax('../cartengine/vendors.php',fields,true);
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_start_import,{
       vendor_id: vendor_id, import_id: import_id });
    top.display_status('Import','Starting Import...',300,100,null);
    ajax_request.send();
}

function finish_start_import(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) {
       reload_imports_grid();
       alert('Import Started');
       window.setTimeout(function() {
          check_import_status(ajax_data.vendor_id,ajax_data.import_id);
       },(60 * 1000));
    }
    else if (status == 410) alert('Import has already been started');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function check_import_status(vendor_id,import_id)
{
    var fields = 'cmd=checkimport&import=' + import_id;
    var ajax_request = new Ajax('../cartengine/vendors.php',fields,true);
    ajax_request.enable_parse_response();
    ajax_request.disable_log_server_errors();
    ajax_request.set_callback_function(finish_check_import_status,{
       vendor_id: vendor_id, import_id: import_id });
    ajax_request.send();
}

function import_data_changed(ajax_request,ajax_data)
{
    grid_row = vendors_grid.grid.getCurrentRow();
    var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
    if (vendor_id != ajax_data.vendor_id) return false;
    var data = ajax_request.parse_value(ajax_request.request.responseText,
                                        'message');
    if (! data) return false;
    data = JSON.parse(data);
    if (! data) return false;
    var grid_row = -1;
    for (var loop = 0;  loop < imports_grid.table._num_rows;  loop++) {
       var import_id = imports_grid.grid.getCellText(0,loop);
       if (import_id == ajax_data.import_id) {
          grid_row = loop;   break;
       }
    }
    if (grid_row == -1) return false;
    imports_grid.table.getData(1,grid_row);
    var num_products = imports_grid.table._row_data[4];
    if (num_products != data.num_products) return true;
    var data_updated = imports_grid.table._row_data[5];
    if (data_updated != data.data_updated) return true;
    var import_started = imports_grid.table._row_data[6];
    if (import_started != data.import_started) return true;
    var import_finished = imports_grid.table._row_data[7];
    if (import_finished != data.import_finished) return true;
    return false;
}

function finish_check_import_status(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;
    var status = ajax_request.get_status();
    if (status == 201) {
       grid_row = vendors_grid.grid.getCurrentRow();
       var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
       if (vendor_id == ajax_data.vendor_id) reload_imports_grid();
       alert('Import #' + ajax_data.import_id + ' has finished');
    }
    else if (status == 202) {
       if (import_data_changed(ajax_request,ajax_data)) reload_imports_grid();
       window.setTimeout(function() {
          check_import_status(ajax_data.vendor_id,ajax_data.import_id);
       },(60 * 1000));
    }
    else if (status == 412) {
       grid_row = vendors_grid.grid.getCurrentRow();
       var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
       if (vendor_id == ajax_data.vendor_id) reload_imports_grid();
       ajax_request.display_error();
    }
    else ajax_request.display_error();
}

function clear_import(index)
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to clear');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var import_id = imports_grid.grid.getCellText(0,grid_row);
    grid_row = vendors_grid.grid.getCurrentRow();
    var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
    var fields = 'cmd=clearimport&import=' + import_id;
    imports_grid.table.save_position();
    var ajax_request = new Ajax('../cartengine/vendors.php',fields,true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_clear_import,{
       vendor_id: vendor_id, import_id: import_id });
    top.display_status('Import','Clearing Errors...',300,100,null);
    ajax_request.send();
}

function finish_clear_import(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) reload_imports_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function reset_import(index)
{
    if (imports_grid.table._num_rows < 1) {
       alert('There are no imports to reset');   return;
    }
    var grid_row = imports_grid.grid.getCurrentRow();
    var import_id = imports_grid.grid.getCellText(0,grid_row);
    grid_row = vendors_grid.grid.getCurrentRow();
    var vendor_id = vendors_grid.grid.getCellText(0,grid_row);
    var fields = 'cmd=resetimport&import=' + import_id;
    imports_grid.table.save_position();
    var ajax_request = new Ajax('../cartengine/vendors.php',fields,true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_reset_import,{
       vendor_id: vendor_id, import_id: import_id });
    top.display_status('Import','Resetting Import...',300,100,null);
    ajax_request.send();
}

function finish_reset_import(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) reload_imports_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function convert_import_data(col,row,text)
{
    var import_types = ['','Products','Inventory','Prices','Images','Data'];
    var import_sources = ['','FTP','Upload','EDI','SFTP','Download',
                          'Other','API'];
    if (col == 2) {
       var type = parse_int(text);
       return import_types[type];
    }
    if (col == 3) {
       var source = parse_int(text);
       if (source < 0) return 'Import #' + (-source);
       return import_sources[source];
    }
    if ((col == 4) || (col == 5)) {
       var source = imports_grid.table._row_data[3];
       if (source < 0) return 'n/a';
    }
    if ((col == 5) || (col == 6) || (col == 7)) {
       if (text == '') return text;
       var import_date = new Date(parse_int(text) * 1000);
       return import_date.format('mm/dd/yyyy hh:MM:ss tt');
    }
    return text;
}

