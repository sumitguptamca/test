/*
             Inroads Shopping Cart - Catalog Config JavaScript Functions

                     Written 2015-2016 by Randall Severy
                      Copyright 2015-2016 Inroads, LLC
*/

var fields_grid = null;
var script_prefix = '';

function resize_dialog(new_width,new_height)
{
    if (fields_grid) {
       if (top.skin)
          resize_grid(fields_grid,-1,new_height - get_grid_offset(fields_grid));
       else resize_grid(fields_grid,new_width,new_height)
    }
}

function update_catalog_config()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('catalogconfig.php','cmd=updatecatalogconfig',document.CatalogConfig,
                    finish_update_catalog_config);
}

function finish_update_catalog_config(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) top.close_current_dialog();
   else ajax_request.display_error();
}

function enable_field_buttons(enable_flag)
{
    var row = document.getElementById('extra_buttons_row');
    var add_button = document.getElementById('add_field');
    var edit_button = document.getElementById('edit_field');
    var delete_button = document.getElementById('delete_field');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    if (row) row.style.display = display_style;
    add_button.style.display = display_style;
    edit_button.style.display = display_style;
    delete_button.style.display = display_style;
}

function enable_backup_buttons(enable_flag)
{
    var row = document.getElementById('extra_buttons_row');
    var add_button = document.getElementById('add_backup');
    var restore_button = document.getElementById('restore_backup');
    var delete_button = document.getElementById('delete_backup');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    if (row) row.style.display = display_style;
    add_button.style.display = display_style;
    restore_button.style.display = display_style;
    delete_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
    if (content_id == 'fields_content') enable_field_buttons(true);
    else enable_field_buttons(false);
    if (content_id == 'backups_content') enable_backup_buttons(true);
    else enable_backup_buttons(false);
    tab_click(tab,content_id);
    top.grow_current_dialog();
}

function create_fields_grid()
{
    var grid_size = get_default_grid_size();
    var field_columns = ['Id','Tab','Seq','Field','Label','Search','S Grp',
                         'Compare','Filter','F Grp','F Seq','Sub Attr'];
    var field_column_widths = [0,55,30,100,100,50,40,55,35,40,40,50];
    var fields_query = 'select id,admin_tab,field_sequence,field_name,' +
       'field_label,search,search_group,compare,filter,filter_group,' +
       'filter_sequence,subproduct_select from catalog_fields';
    fields_grid = new Grid('catalog_fields',grid_size.width,grid_size.height);
    fields_grid.set_columns(field_columns);
    fields_grid.set_column_widths(field_column_widths);
    fields_grid.set_query(fields_query);
    fields_grid.set_order_by('admin_tab,field_sequence,id');
    fields_grid.table.set_convert_cell_data(convert_data);
    fields_grid.set_id('fields_grid');
    fields_grid.load(true);
    fields_grid.set_double_click_function(edit_field);
    fields_grid.display();
}

function reload_grid()
{
    fields_grid.table.reset_data(true);
    fields_grid.grid.refresh();
    window.setTimeout(function() { fields_grid.table.restore_position(); },0);
}

function add_field()
{
    fields_grid.table.save_position();
    top.create_dialog('add_field',null,null,850,715,false,
                      script_prefix + 'catalogconfig.php?cmd=addfield');
}

function validate_form_fields(form)
{
    if (! validate_form_field(form.field_name,'Field Name'))
       return false;
    return true;
}

function process_add_field()
{
    if (! validate_form_fields(document.AddField)) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('catalogconfig.php','cmd=processaddfield',document.AddField,
                     finish_add_field);
}

function finish_add_field(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('catalog_config').contentWindow;
       iframe.reload_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_field()
{
    if (fields_grid.table._num_rows < 1) {
       alert('There are no fields to edit');   return;
    }
    var grid_row = fields_grid.grid.getCurrentRow();
    var id = fields_grid.grid.getCellText(0,grid_row);
    fields_grid.table.save_position();
    top.create_dialog('edit_field',null,null,850,715,false,
                      script_prefix + 'catalogconfig.php?cmd=editfield&id=' +
                      id,null);
}

function update_field()
{
    if (! validate_form_fields(document.EditField)) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('catalogconfig.php','cmd=updatefield',document.EditField,
                     finish_update_field);
}

function finish_update_field(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('catalog_config').contentWindow;
       iframe.reload_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_field()
{
    if (fields_grid.table._num_rows < 1) {
       alert('There are no fields to delete');   return;
    }
    var grid_row = fields_grid.grid.getCurrentRow();
    var id = fields_grid.grid.getCellText(0,grid_row);
    var field_name = fields_grid.grid.getCellText(2,grid_row);
    var response = confirm('Are you sure you want to delete the "'+field_name +
                           '" field?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    fields_grid.table.save_position();
    call_ajax('catalogconfig.php','cmd=deletefield&id=' + id,true,
              finish_delete_field);
}

function finish_delete_field(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_filter_value_source()
{
    var value_source = get_selected_list_value('filter_value_source');
    var values_row1 = document.getElementById('values_row1');
    if (value_source == 1) values_row1.style.display = '';
    else values_row1.style.display = 'none';
    var values_row4 = document.getElementById('values_row4');
    if (value_source == 4) values_row4.style.display = '';
    else values_row4.style.display = 'none';
    var values_row8 = document.getElementById('values_row8');
    if (value_source == 8) values_row8.style.display = '';
    else values_row8.style.display = 'none';
    var values_row16 = document.getElementById('values_row16');
    if (value_source == 16) values_row16.style.display = '';
    else values_row16.style.display = 'none';
    top.grow_current_dialog();
}

function add_backup()
{
    var response = confirm('This will generate a backup of all category and ' +
       'product tables in the database, do you wish to continue?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax('catalogconfig.php','cmd=addbackup',true,finish_add_backup,300);
}

function finish_add_backup(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       alert('Backup Added');
       top.reload_dialog('catalog_config');
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function restore_backup()
{
    var backups = document.CatalogConfig.Backup;
    if (backups.selectedIndex == -1) {
       alert('You must select a Backup to Restore');   return;
    }
    var backup = backups.options[backups.selectedIndex].value;
    var backup_name = backups.options[backups.selectedIndex].text;
    var response = confirm('This will restore all category and product ' +
       'tables from the '+backup_name+' backup, overwriting any existing ' +
       'tables in the database, do you wish to continue?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax('catalogconfig.php','cmd=restorebackup&backup=' + backup,true,
              finish_restore_backup,300);
}

function finish_restore_backup(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) alert('Backup Restored');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_backup()
{
    var backups = document.CatalogConfig.Backup;
    if (backups.selectedIndex == -1) {
       alert('You must select a Backup to Delete');   return;
    }
    var backup = backups.options[backups.selectedIndex].value;
    var backup_name = backups.options[backups.selectedIndex].text;
    var response = confirm('Are you sure you want to delete the '+backup_name +
                           ' backup?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax('catalogconfig.php','cmd=deletebackup&backup=' + backup,true,
              finish_delete_backup);
}

function finish_delete_backup(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       alert('Backup Deleted');
       top.reload_dialog('catalog_config');
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function convert_data(col,row,text)
{
    if ((col == 5) || (col == 8) || (col == 11)) {
       if (parse_int(text) == 1) return 'Yes';
       else return '';
    }
    if (col == 7) {
       var compare = parse_int(text);
       if (compare == 2) return 'Main';
       else if (compare == 1) return 'Other';
       else return '';
    }
    return text;
}

