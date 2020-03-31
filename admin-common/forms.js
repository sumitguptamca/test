/*
            Inroads Control Panel/Shopping Cart - Forms Tab JavaScript Functions

                  Written 2008-2015 by Kevin Rice and Randall Severy
                          Copyright 2008-2015 Inroads, LLC
*/

var script_prefix = '';
var data_grid = null;

var HTML_OUTPUT = 'html';

var ONSCREEN_DESTINATION = '0';
var NEWTAB_DESTINATION = '1';
var DIALOG_DESTINATION = '2';
var POPUP_DESTINATION = '3';

function resize_form_iframe()
{
   var forms_screen = top.document.getElementById('forms_iframe');
   var screen_height = parseInt(forms_screen.style.height);
   var form = document.forms.Forms;
   var rect = form.getBoundingClientRect();
   var bottom = rect.bottom;
   var iframe = document.getElementById('form_iframe');
   iframe.style.height = (screen_height - bottom - 25) + 'px';
}

function resize_screen(new_width,new_height)
{
    resize_form_iframe();
}

function set_row_visible(id,state)
{
    var row = document.getElementById(id);
    if (row) row.style.display = state;
}

function select_output()
{
    var output_type = get_selected_list_value('Output');
    if (output_type == HTML_OUTPUT) set_row_visible('dest_row','');
    else set_row_visible('dest_row','none');
}

function select_form()
{
    var iframe = document.getElementById('form_iframe');
    iframe.style.display = 'none';
}

function export_data()
{
   var form = get_selected_list_value('Form');
   if (! form) {
      alert('You must select a Form');   return;
   }
   var output_type = get_selected_list_value('Output');
   var url = script_prefix + 'forms.php?cmd=exportdata&Form=' + form +
             '&Output=' + output_type;
   
   var date_option = get_selected_radio_button('form_date');
   if (date_option == 'Year') {
      var year = get_selected_list_value('form_year');
      url += '&Year=' + year;
   }
   else {
      var start_date = document.Forms.start_date.value;
      var end_date = document.Forms.end_date.value;
      url += '&StartDate=' + start_date + '&EndDate=' + end_date;
   }

    if (output_type == HTML_OUTPUT) {
       var destination = get_selected_radio_button('Destination');
       switch (destination) {
          case ONSCREEN_DESTINATION:
             url += '&Destination=onscreen';
             var export_button = document.getElementById('export_data');
             export_button.href = url;
             export_button.setAttribute('target','form_iframe');
             resize_form_iframe();
             var iframe = document.getElementById('form_iframe');
             var loading = '<p style="text-align: center; font-family: ' +
                'Arial,Helvetica,sans-serif; font-size: 24px; font-weight: ' +
                'bold; font-style: italic; padding-top: 40px;">' +
                'Loading Form Data...</p>';
             iframe.contentWindow.document.open();
             iframe.contentWindow.document.write(loading);
             iframe.contentWindow.document.close();
             iframe.style.display = '';
             return true;
          case NEWTAB_DESTINATION:
             var export_button = document.getElementById('export_data');
             export_button.href = url;
             export_button.setAttribute('target','_blank');
             return true;
          case DIALOG_DESTINATION:
             url += '&Destination=dialog';
             top.create_dialog('ExportData',null,null,null,null,false,url,null);
             break;
          case POPUP_DESTINATION:
             var window_options = 'toolbar=yes,directories=no,menubar=yes,' +
                'status=no,scrollbars=yes,resizable=yes';
             var output_window = window.open(url,'ExportData',window_options);
             if (! output_window)
                alert('Unable to open report window, please enable popups ' +
                      'for this domain');
             break;
       }
    }
    else location.href = url;

    return false;
}

function edit_data()
{
   var form = get_selected_list_value('Form');
   if (! form) {
      alert('You must select a Form');   return;
   }
   var url = script_prefix + 'forms.php?cmd=editdata&Form=' + form;
   var date_option = get_selected_radio_button('form_date');
   if (date_option == 'Year') {
      var year = get_selected_list_value('form_year');
      url += '&Year=' + year;
   }
   else {
      var start_date = document.Forms.start_date.value;
      var end_date = document.Forms.end_date.value;
      url += '&StartDate=' + start_date + '&EndDate=' + end_date;
   }
   top.create_dialog('edit_data',null,null,800,400,false,url,null);
}

var tipobj;

function setup_tooltip()
{
    var body_array = top.document.getElementsByTagName('body');
    if ((! body_array) || (! body_array[0])) return null;
    var body = body_array[0];
    var div = top.document.createElement('DIV');
    div.id = 'tooltipdiv';
    div.style.position = 'absolute';
    div.style.width = '400px';
    div.style.height = '100px';
    div.style.font = '12px Arial,Helvetica,sans-serif';
    div.style.backgroundColor = '#B0E0E6';
    div.style.border = '1px solid black';
    div.style.padding = '8px';
    div.style.zIndex = 9999;
    div.style.filter = 'progid:DXImageTransform.Microsoft.Shadow(color=gray,direction=135)';
    div.style.mozBoxShadow = '5px 5px 5px gray';
    div.style.webkitBoxShadow = '5px 5px 5px gray';
    if (body.firstChild) body.insertBefore(div,body.firstChild);
    else body.appendChild(div);
    return div;
}

function ietruebody()
{
    return (document.compatMode && (document.compatMode != 'BackCompat'))?
            document.documentElement : document.body
}

function data_grid_mouseover(event,col,row)
{
    try {
    if (col != 1) return;
    var form_fields = data_grid.grid.getCellText(col,row);
    if (! tipobj) tipobj = top.document.getElementById('tooltipdiv');
    if (! tipobj) tipobj = setup_tooltip();
    tipobj.innerHTML = form_fields.replace(/\|/g,'<br>');
    var field_array = form_fields.split('|');
    var ns6 = document.getElementById && (! document.all);
    var curX = (ns6)? event.pageX : event.clientX + ietruebody().scrollLeft;
    var curY = (ns6)? event.pageY : event.clientY + ietruebody().scrollTop;
    var dialog = top.get_dialog_object('edit_data');
    tipobj.style.height = ((field_array.length * 15) + 100) + 'px';
    tipobj.style.left = parseInt(dialog.style.left) + curX + 30 + 'px';
    tipobj.style.top = parseInt(dialog.style.top) + curY + 40 + 'px';
    tipobj.style.display = '';
    } catch(e) {
       var msg = 'Script Error (mouseover): '+e.message;   alert(msg);
    }
}

function data_grid_mouseout(event,col,row)
{
    tipobj.style.display = 'none';
}

function resize_dialog(new_width,new_height)
{
    if (! data_grid) return;
    if (top.skin)
       resize_grid(data_grid,-1,new_height - get_grid_offset(data_grid));
    else resize_grid(data_grid,new_width-8,new_height-50);
}

function create_data_grid(parent)
{
   var grid_size = get_default_grid_size();
   if (! top.skin) {
      grid_size.width -= 8;   grid_size.height -= 50;
   }
   data_grid = new Grid('forms',grid_size.width,grid_size.height);
   data_grid.set_columns(['Id','Form Fields','Date/Time']);
   data_grid.set_column_widths([0,505,105]);
   data_grid.set_query('select id,form_fields,creation_date from forms');
   data_grid.set_where("form_id='" + parent + "'");
   data_grid.set_order_by('creation_date desc');
   data_grid.set_id('data_grid');
   data_grid.table.set_convert_cell_data(convert_form_data);
   data_grid.load(false);
   data_grid.set_double_click_function(view_entry);
   data_grid.grid.onCellMouseOver = data_grid_mouseover;
   data_grid.grid.onCellMouseOut = data_grid_mouseout;
   data_grid.display();
}

function reload_grid()
{
   data_grid.table.reset_data(false);
   data_grid.grid.refresh();
   window.setTimeout(function() { data_grid.table.restore_position(); },0);
}

function view_entry()
{
   if (data_grid.table._num_rows < 1) {
      alert('There are no form records to view');   return;
   }
   var grid_row = data_grid.grid.getCurrentRow();
   var id = data_grid.grid.getCellText(0,grid_row);
   top.create_dialog('view_entry',null,null,600,200,false,
                     script_prefix + 'forms.php?cmd=viewentry&id=' + id,null);
}

function add_entry()
{
   var form_id = document.EditData.FormId.value;
   data_grid.table.save_position();
   top.create_dialog('add_entry',null,null,680,390,false,
                     script_prefix + 'forms.php?cmd=addentry&Form=' + form_id,null);
}

function process_add_entry()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('forms.php','cmd=processaddentry',document.AddEntry,
                    finish_add_entry);
}

function finish_add_entry(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_dialog_frame('edit_data').contentWindow.reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function edit_entry()
{
   if (data_grid.table._num_rows < 1) {
      alert('There are no form records to edit');   return;
   }
   var grid_row = data_grid.grid.getCurrentRow();
   var id = data_grid.grid.getCellText(0,grid_row);
   data_grid.table.save_position();
   top.create_dialog('edit_entry',null,null,680,390,false,
                     script_prefix + 'forms.php?cmd=editentry&id=' + id,null);
}

function update_entry()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('forms.php','cmd=updateentry',document.EditEntry,
                    finish_update_entry);
}

function finish_update_entry(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_dialog_frame('edit_data').contentWindow.reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function delete_entry()
{
   if (data_grid.table._num_rows < 1) {
      alert('There are no form records to delete');   return;
   }
   var grid_row = data_grid.grid.getCurrentRow();
   var id = data_grid.grid.getCellText(0,grid_row);
   var form_fields = data_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete the form record "' +
                          form_fields + '"?');
   if (! response) return;
   data_grid.table.save_position();
   call_ajax('forms.php','cmd=deleteentry&id=' + id,
             true,finish_delete_entry);
}

function finish_delete_entry(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function convert_form_data(col,row,text)
{
   if (col == 1) {
      var field_array = text.split('^');
      var data_length = field_array.length;
      var curr_pos = 0;   var form_fields = '';
      while (curr_pos < data_length) {
         var field_name = field_array[curr_pos];   curr_pos++;
         if (curr_pos < data_length) {
            var field_value = field_array[curr_pos];   curr_pos++;
         }
         else var field_value = '';
         var field_label = field_name;
         for (var field_id in field_info) {
            if (field_info[field_id].name == field_name) {
               field_label = field_info[field_id].label;   break;
            }
         }
         if (field_value == '') continue;
         if (form_fields != '') form_fields += '|';
         form_fields += field_label + ': ' + field_value;
      }
      return form_fields;
   }
   if (col == 2) {
      if (text == '') return text;
      var save_date = new Date(parse_int(text) * 1000);
      return save_date.format('mm/dd/yy hh:MM tt');
   }
   return text;
}

