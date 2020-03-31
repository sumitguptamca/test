/*

     Inroads Control Panel/Shopping Cart - Ticket Dialog JavaScript Functions

                        Written 2012-2015 by Randall Severy
                         Copyright 2012-2015 Inroads, LLC
*/

var PUNCH_LIST = 0;
var SUPPORT_REQUEST = 1;

var script_prefix = '';
var tickets_grid = null;
var ticket_url = '';
var cancel_add_ticket = true;
var ticket_type;
var full_name;
var status_values = [];
var departments = [];
var priority_values = [];
var users = [];

function resize_dialog(new_width,new_height)
{
    if (! tickets_grid) return;
    if (top.skin)
       resize_grid(tickets_grid,-1,new_height - get_grid_offset(tickets_grid));
    else resize_grid(tickets_grid,new_width,new_height)
}

var tipobj = null;

function setup_tooltip()
{
    var body_array = top.document.getElementsByTagName("body");
    if ((! body_array) || (! body_array[0])) return null;
    var body = body_array[0];
    var div = top.document.createElement('DIV');
    div.id = 'tooltipdiv';
    div.style.position = 'absolute';
    div.style.width = '600px';
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
    return (document.compatMode && (document.compatMode != "BackCompat"))?
            document.documentElement : document.body
}

function cleanup_tip_text(text)
{
    if (! text) return text;
    text = text.replace(/&amp;/g,'&');
    text = text.replace(/&lt;/g,'<').replace(/&gt;/g,'>');
    text = text.replace(/&quot;/g,'"').replace(/&nbsp;/g,' ');
    text = text.replace(/<img/g,'&lt;img');
    text = text.replace(/\\n/g,'');
    return text;
}

function tickets_grid_mouseover(event,col,row)
{
    try {
       if (ticket_type == PUNCH_LIST) var offset = 0;
       else var offset = 1;
       var title = cleanup_tip_text(tickets_grid.grid.getCellValue(3+offset,row));
       var tooltip = title;
       var message = cleanup_tip_text(tickets_grid.grid.getCellValue(4+offset,row));
       if (message != '') tooltip += '<hr>' + message;
       var history = cleanup_tip_text(tickets_grid.grid.getCellValue(5+offset,row));
       if (history != '') tooltip += '<hr>' + history;
       if (! tipobj) tipobj = top.document.getElementById("tooltipdiv");
       if (! tipobj) tipobj = setup_tooltip();
       tipobj.innerHTML = tooltip;
       var ns6 = document.getElementById && (! document.all);
       var curX = (ns6)? event.pageX : event.clientX + ietruebody().scrollLeft;
       var curY = (ns6)? event.pageY : event.clientY + ietruebody().scrollTop;
       var dialog = top.get_dialog_object('tickets');
       tipobj.style.height = 'auto';
       tipobj.style.left = parseInt(dialog.style.left) + curX + 30 + "px";
       tipobj.style.top = parseInt(dialog.style.top) + curY + 40 + "px";
       tipobj.style.display = '';
    } catch(e) {
       var msg = 'Script Error (mouseover): '+e.message;   alert(msg);
    }
}

function tickets_grid_mouseout(event,col,row)
{
    tipobj.style.display = 'none';
}

function load_grid()
{
    var grid_size = get_default_grid_size();
    tickets_grid = new Grid("tickets",grid_size.width,grid_size.height);
    if (ticket_type == PUNCH_LIST) {
       tickets_grid.set_columns(["#","Status","Department","Title","Description",
                                 "History","Comments","Submitted","Submitter",
                                 "Assigned To"]);
       tickets_grid.set_column_widths([30,65,68,350,0,0,0,65,110,110]);
       var query = "select id,status,department,title,description,history," +
                   "comments,submitted,submitter,assignedto from tickets";
    }
    else {
       tickets_grid.set_columns(["#","Status","Department","Priority","Title",
                                 "Description","History","Comments","Submitted",
                                 "Submitter","Assigned To"]);
       tickets_grid.set_column_widths([30,65,68,50,350,0,0,0,65,110,110]);
       var query = "select id,status,department,priority,title,description," +
                   "history,comments,submitted,submitter,assignedto from " +
                   "tickets";
    }
    tickets_grid.set_query(query);
    tickets_grid.set_order_by('field(status,6,0,1,3,2,5,4),submitted');
    tickets_grid.table.set_convert_cell_data(convert_ticket_data);
    if (ticket_type == PUNCH_LIST) tickets_grid.set_id('punchlist_grid');
    else tickets_grid.set_id('support_grid');
    tickets_grid.load(false);
    tickets_grid.grid.onCellMouseOver = tickets_grid_mouseover;
    tickets_grid.grid.onCellMouseOut = tickets_grid_mouseout;
    tickets_grid.set_double_click_function(function () { view_ticket(null); });
    var number_format = new AW.Formats.Number;
    tickets_grid.grid.setCellFormat(number_format,0);
    date_format.setTextFormat('mm/dd/yy');
    if (ticket_type == PUNCH_LIST)
       tickets_grid.grid.setCellFormat(date_format,7);
    else tickets_grid.grid.setCellFormat(date_format,8);
    tickets_grid.display();
}

function reload_grid()
{
    tickets_grid.table.reset_data(false);
    tickets_grid.grid.refresh();
    window.setTimeout(function() { tickets_grid.table.restore_position(); },0);
}

function build_status_note(prefix,suffix)
{
    var date_value = new Date();
    var date_string = date_value.format('mmmm d, yyyy h:MM tt');
    var status_note = '<p class="status_note">' + prefix + ' ' + date_string +
                      suffix + '</p>';
    return status_note;
}

function build_attachment_html(parent,filename,file_size,index)
{
    html = '<span class="qq-upload-file"><a href="'+ticket_url+'/'+parent +
           '/'+filename+'" target="_blank">'+filename+'</a></span>' +
           '<span class="qq-upload-size" style="display: inline;">' +
           file_size+'</span><span class="qq-delete-file">(<a href="#" ' +
           'onClick="delete_attachment('+parent+','+index +
           ');">Delete</a>)</span><span class="qq-upload-failed-text">' +
           'Failed</span>';
    return html;
}

function create_uploader(parent,attachments)
{
    var attachments_div = document.getElementById('attachments');
    var template = '<div class="qq-uploader">' + 
                   '<div class="qq-upload-drop-area"><span>Drop files here to upload</span></div>' +
                   '<div class="qq-upload-button">Attach File</div>';
    if (navigator.userAgent.indexOf('MSIE') == -1)
       template += '<div class="qq-drop-label">(or drop files here to upload)</div>';
    template += '<ul class="qq-upload-list"></ul></div>';
    var uploader = new qq.FileUploader({
       element: attachments_div,
       action: 'tickets.php',
       params: { cmd: 'uploadattachment', parent: parent },
       onComplete: finish_uploaded_file,
       template: template,
       debug: false
    });
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    upload_list.parent_id = parent;
    for (var index in attachments) {
       var filename = attachments[index].filename;
       var file_size = attachments[index].size;
       var attach_row = document.createElement('li');
       attach_row.className = 'qq-upload-success';
       attach_row.id = 'attach_' + index;
       attach_row.filename = filename;
       attach_row.innerHTML = build_attachment_html(parent,filename,file_size,
                                                    index);
       upload_list.appendChild(attach_row);
    }
    top.grow_current_dialog();
}

function finish_uploaded_file(id,filename,response)
{
    var attachments_div = document.getElementById('attachments');
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    var attach_rows = upload_list.getElementsByTagName('li');
    var attach_index = -1;
    for (var index=0,length=attach_rows.length; index < length; index++) {
       var row_filename = attach_rows[index].getElementsByTagName('span')[0].innerHTML;
       if (filename == row_filename) {
          attach_index = index;   break;
       }
    }
    if (attach_index == -1) return;
    var attach_row = attach_rows[attach_index];
    if (typeof(response.success) != 'undefined') {
       attach_row.id = 'attach_' + attach_index;
       attach_row.filename = filename;
       var file_size = attach_row.getElementsByTagName('span')[1].innerHTML;
       attach_row.innerHTML = build_attachment_html(upload_list.parent_id,
                                                    filename,file_size,
                                                    attach_index);
       var status_note = build_status_note('Attachment added by '+full_name,
                                           ': '+filename);
       var history_field = document.getElementById('history');
       history_field.value += status_note;
       var history_div = document.getElementById('history_div');
       history_div.innerHTML += status_note;
       top.grow_current_dialog();
    }
    else attach_row.parentNode.removeChild(attach_row);
}

var delete_row;

function delete_attachment(parent,index)
{
    delete_row = document.getElementById('attach_' + index);
    var response = confirm('Are you sure you want to delete the "' +
                           delete_row.filename + '" attachment?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax("tickets.php","cmd=deleteattachment&parent=" + parent +
              '&filename=' + encodeURIComponent(delete_row.filename),true,
              finish_delete_attachment);
}

function finish_delete_attachment(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var status_note = build_status_note('Attachment deleted by '+full_name,
                                           ': '+delete_row.filename);
       var history_field = document.getElementById('history');
       history_field.value += status_note;
       var history_div = document.getElementById('history_div');
       history_div.innerHTML += status_note;
       delete_row.parentNode.removeChild(delete_row);
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function create_ticket(showing_warranty)
{
    cancel_add_ticket = true;
    top.enable_current_dialog_progress(true);
    var ajax_request = new Ajax("tickets.php","cmd=createticket",true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_ticket,showing_warranty);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function add_ticket()
{
    if (tipobj) tipobj.style.display = 'none';
    tickets_grid.table.save_position();
    if (ticket_type == SUPPORT_REQUEST) {
       top.enable_current_dialog_progress(true);
       var ajax_request = new Ajax("tickets.php","cmd=getwarrantyinfo",true);
       ajax_request.enable_alert();
       ajax_request.enable_parse_response();
       ajax_request.set_callback_function(get_warranty_info,null);
       ajax_request.set_timeout(30);
       ajax_request.send();
    }
    else create_ticket(false);
}

function warranty_onload()
{
    if (tipobj = top.get_dialog_frame('tickets').contentWindow.tipobj)
       tipobj.style.display = 'none';
}

function get_warranty_info(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       var client_type = ajax_request.parse_value(ajax_request.request.responseText,
                                                  'type');
       var minutes = ajax_request.parse_value(ajax_request.request.responseText,
                                              'minutes');
       var expire = ajax_request.parse_value(ajax_request.request.responseText,
                                             'expire');
       if (navigator.userAgent.indexOf('MSIE') == -1) var width = 444;
       else var width = 450;
       if (tipobj) tipobj.style.display = 'none';
       top.create_dialog('show_warranty',null,null,width,400,false,
                         script_prefix+'tickets.php?cmd=showwarranty&Type=' +
                         client_type+'&Minutes='+minutes+'&Expire='+expire,null);
       showing_warranty = true;
    }
    else if (status == 204) create_ticket(false);
}

function approve_out_of_warranty()
{
    top.get_dialog_frame('tickets').contentWindow.create_ticket(true);
}

function continue_add_ticket(ajax_request,showing_warranty)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    if (showing_warranty) top.close_current_dialog();
    var status = ajax_request.get_status();
    if (status != 200) return;

    var ticket_id = -1;
    eval(ajax_request.request.responseText);
    if (tipobj) tipobj.style.display = 'none';
    top.create_dialog('add_ticket',null,null,705,520,false,
                      script_prefix + 'tickets.php?cmd=addticket&id='+ticket_id,
                      null);
}

function add_ticket_onclose(user_close)
{
    if (cancel_add_ticket) {
       var ticket_id = document.AddTicket.id.value;
       call_ajax("tickets.php","cmd=deleteticket&id=" + ticket_id,true);
    }
}

function add_ticket_onload()
{
    top.set_current_dialog_onclose(add_ticket_onclose);
    if (tipobj = top.get_dialog_frame('tickets').contentWindow.tipobj)
       tipobj.style.display = 'none';
}

function process_add_ticket()
{
    var department = get_selected_list_value('department');
    if (department == '') {
       alert('You must select a Department');
       document.AddTicket.department.focus();   return;
    }
    if (! validate_form_field(document.AddTicket.title,"Title")) return;
    top.enable_current_dialog_progress(true);
    submit_form_data("tickets.php","cmd=processaddticket",
                     document.AddTicket,finish_add_ticket);
}

function finish_add_ticket(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_ticket = false;
       top.get_dialog_frame('tickets').contentWindow.reload_grid();
       if (ticket_type == SUPPORT_REQUEST) {
          var ticket_id = document.AddTicket.id.value;
          alert('Your Support Request has been submitted with Ticket #' +
                ticket_id + ' and will be ' + 'processed as soon as possible');
       }
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function edit_ticket(id)
{
    if (tipobj) tipobj.style.display = 'none';
    var url = script_prefix + 'tickets.php?cmd=editticket';
    if (typeof(id) == 'undefined') {
       if (tickets_grid.table._num_rows < 1) {
          if (ticket_type == PUNCH_LIST) var label = 'punch list items';
          else var label = 'support requests';
          alert('There are no '+label+' to edit');   return;
       }
       var grid_row = tickets_grid.grid.getCurrentRow();
       var id = tickets_grid.grid.getCellText(0,grid_row);
       tickets_grid.table.save_position();
    }
    else url += '&viewing=true';
    url += '&id=' + id;
    top.create_dialog('edit_ticket',null,null,680,520,false,url,null);
}

function update_ticket()
{
    if (! validate_form_field(document.EditTicket.title,"Title")) return;
    top.enable_current_dialog_progress(true);
    submit_form_data("tickets.php","cmd=updateticket",document.EditTicket,
                     finish_update_ticket);
}

function finish_update_ticket(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('tickets').contentWindow.reload_grid();
       if (document.EditTicket.viewing.value == 'true')
          top.reload_dialog('view_ticket');
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function delete_ticket()
{
    if (tipobj) tipobj.style.display = 'none';
    if (tickets_grid.table._num_rows < 1) {
       if (ticket_type == PUNCH_LIST) var label = 'punch list items';
       else var label = 'support requests';
       alert('There are no '+label+' to delete');   return;
    }
    var grid_row = tickets_grid.grid.getCurrentRow();
    var id = tickets_grid.grid.getCellText(0,grid_row);
    if (ticket_type == PUNCH_LIST) {
       var offset = 0;   var label = 'punch list item';
    }
    else {
       var offset = 1;   var label = 'support request';
    }
    if (ticket_type == SUPPORT_REQUEST) {
       var status = tickets_grid.grid.getCellText(1,grid_row);
       if (status != 'New') {
          alert('Only support requests with a status of New can be deleted');
          return;
       }
    }
    var title = tickets_grid.grid.getCellText(3 + offset,grid_row);
    var response = confirm('Are you sure you want to delete the "' + title +
                           '" ' + label + '?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    tickets_grid.table.save_position();
    call_ajax("tickets.php","cmd=deleteticket&id=" + id,true,
              finish_delete_ticket);
}

function finish_delete_ticket(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid();
    else ajax_request.display_error();
}

function view_ticket(id)
{
    if (tipobj) tipobj.style.display = 'none';
    if (! id) {
       if (tickets_grid.table._num_rows < 1) {
          if (ticket_type == PUNCH_LIST) var label = 'punch list items';
          else var label = 'support requests';
          alert('There are no '+label+' to view');   return;
       }
       var grid_row = tickets_grid.grid.getCurrentRow();
       id = tickets_grid.grid.getCellText(0,grid_row);
    }
    else {
       var num_rows = tickets_grid.table._num_rows;
       var grid_row = -1;
       for (var loop = 0;  loop < num_rows;  loop++) {
          var ticket_id = tickets_grid.grid.getCellText(0,loop);
          if (ticket_id == id) {
             grid_row = loop;   break;
          }
       }
       if (grid_row != -1) {
          tickets_grid.grid.setSelectedRows([grid_row]);
          tickets_grid.grid.setCurrentRow(grid_row);
       }
    }
    tickets_grid.table.save_position();
    top.create_dialog('view_ticket',null,null,680,520,false,
                      script_prefix + 'tickets.php?cmd=viewticket&id=' + id,
                      null);
}

function convert_ticket_data(col,row,text)
{
    if (col == 1) {
       var status = parse_int(text);
       if (typeof(status_values[status]) == 'undefined') return text;
       return status_values[status];
    }
    if (col == 2) {
       if (text == '') return text;
       var department = parse_int(text);
       if (department == -1) return '';
       if (typeof(departments[department]) == 'undefined') return text;
       return departments[department];
    }
    if (ticket_type == PUNCH_LIST) var offset = 0;
    else {
       if (col == 3) {
          if (text == '') return text;
          var priority = parse_int(text);
          if (typeof(priority_values[priority]) == 'undefined') return text;
          return priority_values[priority];
       }
       var offset = 1;
    }
    if (col == (7 + offset)) {
       if (text == '') return text;
       var date_value = new Date(parse_int(text) * 1000);
       var month = String(date_value.getMonth() + 1);
       if (month.length == 1) month = '0' + month;
       var day = String(date_value.getDate());
       if (day.length == 1) day = '0' + day;
       var year = String(date_value.getFullYear());
       year = year.substr(2);
       var date_string = month + "/" + day + "/" + year;
       return date_string;
    }
    else if ((col == (8 + offset)) || (col == (9 + offset))) {
       for (index in users) {
          if (users[index][0] == text) return users[index][1];
       }
    }
    return text;
}

function search_tickets()
{
    var query = document.SearchForm.query.value;
    if (query == '') {
       reset_search();   return;
    }
    if (ticket_type == PUNCH_LIST) var label = 'Punch List';
    else var label = 'Support Requests';
    top.display_status("Search","Searching "+label+"...",400,100,null);
    window.setTimeout(function() {
       var where = "title like '%" + query + "%' or description like '%" +
                   query + "%' or history like '%" + query +
                   "%' or comments like '%" + query + "%'";
       tickets_grid.set_where(where);
       tickets_grid.table.reset_data(false);
       tickets_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_search()
{
    if (ticket_type == PUNCH_LIST) var label = 'Punch List Items';
    else var label = 'Support Requests';
    top.display_status("Search","Loading All "+label+"...",400,100,null);
    window.setTimeout(function() {
       document.SearchForm.query.value = '';
       tickets_grid.set_where("");
       tickets_grid.table.reset_data(false);
       tickets_grid.grid.refresh();
       top.remove_status();
    },0);
}

function select_user(user_list,fieldname)
{
    if (document.AddTicket) var form = document.AddTicket;
    else var form = document.EditTicket;
    var date_value = new Date();
    var month = String(date_value.getMonth() + 1);
    if (month.length == 1) month = '0' + month;
    var day = String(date_value.getDate());
    if (day.length == 1) day = '0' + day;
    var year = String(date_value.getFullYear());
    year = year.substr(2);
    var date_string = month + "/" + day + "/" + year;
    var form_field = form[fieldname + '_string'];
    if (form_field) {
       if (form_field.value == '') form_field.value = date_string;
       parse_date_field(form_field,fieldname);
    }
    if (form.status.options) {
       var status = form.status.options[form.status.selectedIndex].value;
       if ((fieldname == 'assigned') && (status == 0))
          form.status.selectedIndex = 1;
       else if ((fieldname == 'verified') && (status == 2))
          form.status.selectedIndex = 3;
    }
}

function approve_ticket()
{
    if (document.AddTicket) var form = document.AddTicket;
    else if (document.EditTicket) var form = document.EditTicket;
    var status_note = build_status_note('Approved by '+full_name,'');
    var history_field = document.getElementById('history');
    var history_div = document.getElementById('history_div');
    var new_history = history_field.value + status_note;
    history_field.value = new_history;
    history_div.innerHTML = new_history;
    var id = form.id.value;
    call_ajax("tickets.php","cmd=approveticket&id=" + id + '&history=' +
              encodeURIComponent(new_history),true,finish_approve_ticket);
}

function finish_approve_ticket(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {
       var approved_cell = document.getElementById('approved_cell');
       approved_cell.innerHTML = 'Yes';
    }
    else if ((status > 201) && (status < 300)) ajax_request.display_error();
}

function add_comment()
{
    var dialog_name = top.get_current_dialog_name();
    if (htmleditor_url) var url = htmleditor_url;
    else var url = '../engine/htmleditor.php';
    url += '?dialog=' + dialog_name + '&field=NewComments&title=' +
           encodeURIComponent('*Add Comment') + '&label=Add';
    top.create_dialog('htmleditor',null,null,720,400,false,url,null);
}

function custom_update_htmleditor_field(field_name)
{
    if (field_name != 'NewComments') return;
    var new_comments_field = document.getElementById('NewComments');
    var new_comments = new_comments_field.value;
    var new_comments_row = document.getElementById('new_comments_row');
    var history_field = document.getElementById('history');
    var history_div = document.getElementById('history_div');
    var comments_field = document.getElementById('comments');
    var comments_div = document.getElementById('comments_div');
    if (document.AddTicket) {
       var form = document.AddTicket;   var dialog_name = 'add_ticket';
    }
    else if (document.EditTicket) {
       var form = document.EditTicket;   var dialog_name = 'edit_ticket';
    }
    else if (document.ViewTicket) {
       var form = document.ViewTicket;   var dialog_name = 'view_ticket';
    }
    if (! new_comments) return;

    var status_note = build_status_note('Comment by '+full_name,':');
    var new_history = history_field.value + status_note + new_comments;
    history_field.value = new_history;
    history_div.innerHTML = new_history;
    new_comments = comments_field.value + status_note + new_comments;
    comments_field.value = new_comments;
    if (comments_div) {
       comments_div.innerHTML = new_comments;
       new_comments_row.style.display = '';
    }
    top.grow_dialog(top.get_dialog_object(dialog_name));
    var id = form.id.value;
    call_ajax("tickets.php","cmd=addcomment&id=" + id + '&history=' +
              encodeURIComponent(new_history) + '&comments=' +
              encodeURIComponent(new_comments),true,finish_add_comment);
}

function finish_add_comment(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {
       var new_comments_field = document.getElementById('NewComments');
       new_comments_field.value = '';
       if (ticket_type == SUPPORT_REQUEST) {
          var comments_field = document.getElementById('comments');
          comments_field.value = '';
       }
    }
    else if ((status > 201) && (status < 300)) ajax_request.display_error();
}

function reopen_ticket()
{
    if (document.EditTicket) var form = document.EditTicket;
    else if (document.ViewTicket) var form = document.ViewTicket;
    var status_note = build_status_note('Reopened by '+full_name,'');
    var id = form.id.value;
    var fields = "cmd=reopenticket&id=" + id;
    if (document.EditTicket) {
       var history_field = document.getElementById('history');
       var history_div = document.getElementById('history_div');
       var new_history = history_field.value + status_note;
       history_field.value = new_history;
       history_div.innerHTML = new_history;
       fields += '&history=' + encodeURIComponent(new_history);
    }
    call_ajax("tickets.php",fields,true,finish_reopen_ticket);
}

function finish_reopen_ticket(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {
       if (document.EditTicket) var form = document.EditTicket;
       else if (document.ViewTicket) var form = document.ViewTicket;
       var status_list = form.status;
       if (status_list && status_list.options) status_list.selectedIndex = 0;
       else {
          var status_cell = document.getElementById('status_cell');
          status_cell.innerHTML = 'New';
       }
    }
    else if ((status > 201) && (status < 300)) ajax_request.display_error();
}

