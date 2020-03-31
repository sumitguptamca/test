/*
       Inroads Control Panel/Shopping Cart - Common Export/Import Data JavaScript Functions

                            Written 2017-2018 by Randall Severy
                             Copyright 2017-2018 Inroads, LLC
*/

function select_module()
{
    var module = get_selected_list_value('Module');
    var fields = 'cmd=loadtables&module=' + module;
    var table_list = document.getElementById('Table');
    while (table_list.options.length > 0) table_list.remove(0);
    top.enable_current_dialog_progress(true);
    call_ajax('admin.php',fields,true,finish_select_module);
}

function finish_select_module(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if ((status != 200) || (! ajax_request.request.responseText)) return;

    var tables = JSON.parse(ajax_request.request.responseText);
    if (tables.length == 0) return;

    var table_list = document.getElementById('Table');
    var new_option = new Option('','');
    table_list.options[0] = new_option;
    for (var index in tables) {
       if (tables[index].indexOf('|') != -1) {
          var table_parts = tables[index].split('|');
          var value = table_parts[0];   var label = table_parts[1];
       }
       else {
          var value = tables[index];   var label = tables[index];
       }
       var new_option = new Option(label,value);
       table_list.options[parseInt(index) + 1] = new_option;
    }
    table_list.selectedIndex = 0;
    top.grow_current_dialog();
}

