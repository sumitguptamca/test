/*
        Inroads Shopping Cart - Products Tab - Product Data JavaScript Functions

                            Written 2011-2012 by Randall Severy
                             Copyright 2011-2012 Inroads, LLC
*/

var script_prefix = '';

function process_add_data()
{
   top.enable_current_dialog_progress(true);
   var iframe = top.get_dialog_frame(document.AddData.Frame.value).contentWindow;
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(iframe.admin_path) != "undefined")) var url = iframe.admin_path;
   else var url = '';
   var data_type = document.AddData.data_type.value;
   url += iframe.data_grids[data_type].script_url;
   var fields = "cmd=processadddata&" + build_form_data(document.AddData);
   call_ajax(url,fields,true,finish_add_data);
}

function finish_add_data(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.AddData.Frame.value).contentWindow;
      var data_type = document.AddData.data_type.value;
      iframe.data_grids[data_type].table.reset_data(true);
      iframe.data_grids[data_type].grid.refresh();
      var last_row = iframe.data_grids[data_type].table._num_rows - 1;
      iframe.data_grids[data_type].grid.setSelectedRows([last_row]);
      iframe.data_grids[data_type].grid.setCurrentRow(last_row);
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function update_data()
{
   var iframe = top.get_dialog_frame(document.EditData.Frame.value).contentWindow;
   var data_type = document.EditData.DataType.value;
   top.enable_current_dialog_progress(true);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(iframe.admin_path) != "undefined")) var url = iframe.admin_path;
   else var url = '';
   url += iframe.data_grids[data_type].script_url;
   submit_form_data(url,"cmd=updatedata",document.EditData,finish_update_data);
}

function finish_update_data(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.EditData.Frame.value).contentWindow;
      var data_type = document.EditData.DataType.value;
      iframe.data_grids[data_type].table.reset_data(true);
      iframe.data_grids[data_type].grid.refresh();
      window.setTimeout(function() {
         iframe.data_grids[data_type].table.restore_position();
         top.close_current_dialog();
      },0);
   }
   else ajax_request.display_error();
}

