/*
        Inroads Control Panel/Shopping Cart - Admin Tab - Import Data JavaScript Functions

                            Written 2008-2015 by Randall Severy
                             Copyright 2008-2015 Inroads, LLC
*/

var script_prefix = '';

function import_data()
{
    top.create_dialog('import_data',null,null,485,195,false,
                      script_prefix + 'admin.php?cmd=importdata',null);
}

function import_data_onload()
{
    top.resize_current_dialog(485,195);
    top.grow_current_dialog();
}

function enable_import_images(checkbox_field)
{
    var imagedir_row = document.getElementById('imagedir_row');
    var imagetype_row = document.getElementById('imagetype_row');
    var imageoptions_row = document.getElementById('imageoptions_row');
    if (checkbox_field.checked) var display_style = '';
    else var display_style = 'none';
    imagedir_row.style.display = display_style;
    imagetype_row.style.display = display_style;
    imageoptions_row.style.display = display_style;
    var table_field = document.ImportData.Table;
    var list_length = table_field.options.length;
    for (var loop = 0; loop < list_length; loop++)
       if (table_field.options[loop].value == 'images') {
          table_field.options[loop].selected = true;   break;
       }
    top.grow_current_dialog();
}

function process_import()
{
    var import_images = document.ImportData.ImportImages.checked;
    var image_dir = document.ImportData.ImageDir.value;
    if (import_images && (image_dir == '')) {
       alert('You must specify an Image Directory');   return;
    }
    var table = get_selected_list_value('Table');
    if (table == '') {
       alert('You must select a Database Table');   return;
    }
    var filename = document.ImportData.Filename.value;
    if (! filename) {
       alert('You must select an Import File to upload');
       return;
    }
/*
    var server_filename = document.ImportData.ServerFilename.value;
    var import_data = document.ImportData.ImportData.value;
    if ((filename == '') && (server_filename == '') && (import_data == '')) {
       alert('You must specify an Import File or Server File or Import Data');
       return;
    }
*/

    top.enable_current_dialog_progress(true);
    document.ImportData.submit();
}

function process_import_data_onload()
{
    top.resize_current_dialog(750,195);
    top.grow_current_dialog();
    top.enable_current_dialog_progress(false);
}

function finish_import()
{
    top.enable_current_dialog_progress(true);
    top.display_status('Import','Importing Data...',250,100,null);
    submit_form_data('admin.php','cmd=finishimport',document.ImportData,
                     finish_import_request,0);
}

function finish_import_request(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       alert('Import Completed');
       top.close_current_dialog();
    }
    else if (status == 202) {
       alert('Image Import Started in Background');
       top.close_current_dialog();
    }
}

function cancel_import()
{
    var import_filename = document.ImportData.ImportFilename.value;
//    var server_filename = document.ImportData.ServerFilename.value;
    top.enable_current_dialog_progress(true);
/*
    call_ajax('admin.php','cmd=cancelimport&Filename=' +
              encodeURIComponent(import_filename) +
              '&ServerFilename=' + encodeURIComponent(server_filename),
              true,finish_cancel_import);
*/
    call_ajax('admin.php','cmd=cancelimport&Filename=' +
              encodeURIComponent(import_filename),
              true,finish_cancel_import);
}

function finish_cancel_import(ajax_request)
{
    if (ajax_request.state != 4) return;
    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 205)
       location.href = script_prefix + 'admin.php?cmd=importdata';
}

function unmap_all_fields()
{
    var fields = document.getElementsByTagName('input');
    for (var loop = 0;  loop < fields.length;  loop++) {
       if ((fields[loop].type == 'checkbox') && (! fields[loop].disabled))
          fields[loop].checked = false;
    }
    var fields = document.getElementsByTagName('select');
    for (var loop = 0;  loop < fields.length;  loop++)
       fields[loop].selectedIndex = 0;
}

