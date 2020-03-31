/*
                Inroads Shopping Cart - Callouts JavaScript Functions

                        Written 2015-2016 by Randall Severy
                         Copyright 2015-2016 Inroads, LLC
*/

var groups_grid = null;
var callouts_grid = null;
var callouts_script_url;
var callouts_frame_name;
var callouts_form_name;
var callouts_grid_width;
var delayed_callouts_grid_load = false;
var script_prefix = '';
var drag_callout = false;
var drag_target = null;

function enable_callout_image_buttons(enable_flag)
{
    var buttons_row = document.getElementById('callout_image_buttons_row');
    if (! buttons_row) return;
    var manage_callout_images_button =
       document.getElementById('manage_callout_images');
    var update_callout_images_button =
       document.getElementById('update_callout_images');
    var callout_groups_button =
       document.getElementById('callout_groups');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    buttons_row.style.display = display_style;
    if (manage_callout_images_button)
       manage_callout_images_button.style.display = display_style;
    if (update_callout_images_button)
       update_callout_images_button.style.display = display_style;
    if (callout_groups_button)
       callout_groups_button.style.display = display_style;
}

function manage_callout_images()
{
    images_parent_type = 3;   manage_image_files('/images/callouts/');
}

function update_callout_images()
{
    update_image_files(3);
}

function init_callouts(url,frame,name,width)
{
    callouts_script_url = url;
    callouts_frame_name = frame;
    callouts_form_name = name;
    callouts_grid_width = width;
}

function enable_callout_buttons(enable_flag)
{
    var callout_buttons_row = document.getElementById('callout_buttons_row');
    var add_callout_button = document.getElementById('add_callout');
    var edit_callout_button = document.getElementById('edit_callout');
    var delete_callout_button = document.getElementById('delete_callout');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    callout_buttons_row.style.display = display_style;
    add_callout_button.style.display = display_style;
    edit_callout_button.style.display = display_style;
    delete_callout_button.style.display = display_style;
}

function set_callouts_parent(product_id)
{
    var where = 'parent=(select id from images where (parent=' + product_id +
                ') and (parent_type=1) order by sequence,id limit 1)';
    callouts_grid.set_where(where);
}

function create_callouts_grid(product_id,container)
{
    callouts_grid = new Grid('callouts',grid_width,200);
    callouts_grid.set_columns(['Id','Seq','Title','X %','Y %','Image',
                               'Description']);
    if (grid_width > 0) var desc_width = grid_width - 325;
    else var desc_width = 390;
    callouts_grid.set_column_widths([0,30,150,45,45,150,desc_width]);
    callouts_grid.set_field_names(['id','sequence','title','xpos','ypos',
                                   'image','description']);
    var query = 'select id,sequence,title,xpos,ypos,image,description ' +
                'from callouts';
    callouts_grid.set_query(query);
    set_callouts_parent(product_id);
    callouts_grid.set_order_by('sequence,id');
    callouts_grid.set_id('callouts_grid');
    callouts_grid.load(true);
    callouts_grid.set_double_click_function(edit_callout);
    if (typeof(container) != 'undefined') {
       delayed_callouts_grid_load = true;
       add_onload_function(function() {
          callouts_grid.insert(container);
       },0);
    }
    else callouts_grid.display();
    add_onload_function(callouts_onload);
}

function callouts_onload()
{
    if (callouts_grid.table._num_rows > 1) {
       var button_cell = document.getElementById('callout_sequence_buttons');
       if (button_cell) button_cell.style.display = '';
    }
}

function select_image_callouts(image_row)
{
    var image_id = images_grid.grid.getCellText(0,image_row);
    var where = 'parent=' + image_id;
    callouts_grid.set_where(where);
    reload_callouts_grid(0);
}

function reload_callouts_grid(current_callout)
{
    callouts_grid.table.reset_data(true);
    callouts_grid.grid.refresh();
    if ((current_callout > 0) &&
        (callouts_grid.table._num_rows > current_callout)) {
       callouts_grid.grid.setSelectedRows([current_callout]);
       callouts_grid.grid.setCurrentRow(current_callout);
    }
    else window.setTimeout(function() {
       callouts_grid.table.restore_position();
    },0);
}

function resequence_callouts(update_data,old_row,new_row)
{
    if (old_row == new_row) return true;
    if (groups_grid) {
       var url = script_prefix + 'admin.php';
       var grid_row = groups_grid.grid.getCurrentRow();
       var parent = groups_grid.grid.getCellText(0,grid_row);
    }
    else {
       var url = callouts_script_url;
       var image_row = images_grid.grid.getCurrentRow();
       var parent = images_grid.grid.getCellText(0,image_row);
    }
    var old_sequence = callouts_grid.grid.getCellText(1,old_row);
    var new_sequence = callouts_grid.grid.getCellText(1,new_row);
    call_ajax(url,'cmd=resequencecallout&Parent=' + parent +
              '&OldSequence=' + old_sequence + '&NewSequence=' + new_sequence,
              true,function (ajax_request) {
                 finish_resequence_callouts(ajax_request,new_row);
              });
}

function finish_resequence_callouts(ajax_request,new_row)
{
    var status = ajax_request.get_status();
    if (status != 201) {
       ajax_request.display_error();
       return false;
    }
    if (groups_grid) reload_group_callouts_grid();
    else reload_callouts_grid(new_row);
    return true;
}

function move_callout_top()
{
    if (callouts_grid.table._num_rows < 1) return;
    var grid_row = parse_int(callouts_grid.grid.getCurrentRow());
    if (grid_row == 0) return;
    resequence_callouts(null,grid_row,0);
}

function move_callout_up()
{
    if (callouts_grid.table._num_rows < 1) return;
    var grid_row = parse_int(callouts_grid.grid.getCurrentRow());
    if (grid_row == 0) return;
    resequence_callouts(null,grid_row,grid_row - 1);
}

function move_callout_down()
{
    var num_rows = callouts_grid.table._num_rows;
    if (num_rows < 1) return;
    var grid_row = parse_int(callouts_grid.grid.getCurrentRow());
    if (grid_row == num_rows - 1) return;
    resequence_callouts(null,grid_row,grid_row + 1);
}

function move_callout_bottom()
{
    var num_rows = callouts_grid.table._num_rows;
    if (num_rows < 1) return;
    var grid_row = parse_int(callouts_grid.grid.getCurrentRow());
    if (grid_row == num_rows - 1) return;
    resequence_callouts(null,grid_row,num_rows - 1);
}

function set_position()
{
    if (document.AddCallout) {
       var form = document.AddCallout;   var frame = 'add_callout';
    }
    else {
       var form = document.EditCallout;   var frame = 'edit_callout';
    }
    var image = form.Image.value;
    var xpos = form.xpos.value;
    if (xpos == '') xpos = 0;
    var ypos = form.ypos.value;
    if (ypos == '') ypos = 0;
    var size = form.Size.value;
    var image_width = form.ImageWidth.value;
    var image_height = form.ImageHeight.value;
    var dialog_width = form.DialogWidth.value;
    var dialog_height = form.DialogHeight.value;
    if ((typeof(top.current_tab) == 'undefined') &&
       (typeof(admin_path) != 'undefined')) var url = admin_path;
    else var url = '';
    url += callouts_script_url + '?cmd=positioncallout&Frame=' + frame +
           '&Image=' + encodeURIComponent(image) + '&xpos=' + xpos +
           '&ypos=' + ypos + '&Size=' + size + '&ImageWidth=' + image_width +
           '&ImageHeight=' + image_height;
    top.create_dialog('set_position',null,null,dialog_width,dialog_height,
                      false,url,null);
}

function set_position_onload()
{
    document.onmousedown = start_callout_drag;
    document.onmouseup = stop_callout_drag;
}

function start_callout_drag(e)
{
    if (!e) var e = window.event;
    if (e.preventDefault) e.preventDefault();
    drag_target = e.target ? e.target : e.srcElement;
    if (drag_target.className != 'dragme') return;
    offsetX = e.clientX;
    offsetY = e.clientY;
    if (! drag_target.style.left) drag_target.style.left = '0px';
    if (! drag_target.style.top) drag_target.style.top = '0px';
    coordX = parseInt(drag_target.style.left);
    coordY = parseInt(drag_target.style.top);
    drag_callout = true;
    document.onmousemove = drag_callout_div;
    return false;
}

function drag_callout_div(e)
{
    if (! drag_callout) return;
    if (! e) var e = window.event;
    drag_target.style.left = coordX + e.clientX - offsetX + 'px';
    drag_target.style.top = coordY + e.clientY - offsetY + 'px';
    return false;
}

function stop_callout_drag()
{
    drag_callout = false;
}

function update_position()
{
    var target = document.getElementById('target');
    var xpos = parseInt(target.style.left);
    var ypos = parseInt(target.style.top);
    var frame = document.PositionCallout.Frame.value;
    var image_width = parseInt(document.PositionCallout.ImageWidth.value);
    var image_height = parseInt(document.PositionCallout.ImageHeight.value);
    xpos = ((xpos + 14) / image_width) * 100;
    xpos = +(Math.round(xpos + 'e+2') + 'e-2');
    ypos = ((ypos + 14) / image_height) * 100;
    ypos = +(Math.round(ypos + 'e+2') + 'e-2');
    var iframe = top.get_dialog_frame(frame).contentWindow;
    iframe.update_position_fields(xpos,ypos);
    top.close_current_dialog();
}

function update_position_fields(xpos,ypos)
{
    if (document.AddCallout) var form = document.AddCallout;
    else var form = document.EditCallout;
    form.xpos.value = xpos;
    form.ypos.value = ypos;
}

function add_callout()
{
    if (groups_grid) {
       if (groups_grid.table._num_rows < 1) {
          alert('There are no callout groups to add a callout for');   return;
       }
       var grid_row = groups_grid.grid.getCurrentRow();
       var parent = groups_grid.grid.getCellText(0,grid_row);
       var url = script_prefix + 'admin.php?cmd=addcallout&Parent=' + parent;
    }
    else {
       var image_row = images_grid.grid.getCurrentRow();
       var parent = images_grid.grid.getCellText(0,image_row);
       var filename = images_grid.grid.getCellText(3,image_row);
       if ((typeof(top.current_tab) == 'undefined') &&
          (typeof(admin_path) != 'undefined')) var url = admin_path;
       else var url = '';
       url += callouts_script_url + '?cmd=addcallout&Parent=' + parent +
              '&Frame=' + callouts_frame_name + '&Image=' +
              encodeURIComponent(filename);
    }
    callouts_grid.table.save_position();
    top.create_dialog('add_callout',null,null,620,375,false,url,null);
}

function process_add_callout()
{
    if (! validate_form_field(document.AddCallout.title,'Title')) return;
    if (! validate_form_field(document.AddCallout.xpos,'X Pos')) return;
    if (! validate_form_field(document.AddCallout.ypos,'Y Pos')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('products.php','cmd=processaddcallout',document.AddCallout,
                     finish_add_callout);
}

function finish_add_callout(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var frame = document.AddCallout.Frame.value;
       if (frame) {
          var iframe = top.get_dialog_frame(frame).contentWindow;
          iframe.reload_callouts_grid(0);
       }
       else top.get_content_frame().reload_group_callouts_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_callout()
{
    if (callouts_grid.table._num_rows < 1) {
       alert('There are no callouts to edit');   return;
    }
    var grid_row = callouts_grid.grid.getCurrentRow();
    var id = callouts_grid.grid.getCellText(0,grid_row);
    if (groups_grid)
       var url = script_prefix + 'admin.php?cmd=editcallout&id=' + id;
    else {
       var image_row = images_grid.grid.getCurrentRow();
       var filename = images_grid.grid.getCellText(3,image_row);
       if ((typeof(top.current_tab) == 'undefined') &&
          (typeof(admin_path) != 'undefined')) var url = admin_path;
       else var url = '';
       url += callouts_script_url + '?cmd=editcallout&id=' + id + '&Frame=' +
              callouts_frame_name + '&Image=' + encodeURIComponent(filename);
    }
    callouts_grid.table.save_position();
    top.create_dialog('edit_callout',null,null,590,375,false,url,null);
}

function update_callout()
{
    if (! validate_form_field(document.EditCallout.title,'Title')) return;
    if (! validate_form_field(document.EditCallout.xpos,'X Pos')) return;
    if (! validate_form_field(document.EditCallout.ypos,'Y Pos')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('products.php','cmd=updatecallout',document.EditCallout,
                     finish_update_callout);
}

function finish_update_callout(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var frame = document.EditCallout.Frame.value;
       if (frame) {
          var iframe = top.get_dialog_frame(frame).contentWindow;
          iframe.reload_callouts_grid(0);
       }
       else top.get_content_frame().reload_group_callouts_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_callout()
{
    if (callouts_grid.table._num_rows < 1) {
       alert('There are no callouts to delete');   return;
    }
    var grid_row = callouts_grid.grid.getCurrentRow();
    var id = callouts_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete the ' +
                           'selected callout?');
    if (! response) return;
    if (groups_grid) var url = script_prefix + 'admin.php';
    else {
       if ((typeof(top.current_tab) == 'undefined') &&
          (typeof(admin_path) != 'undefined')) var url = admin_path;
       else var url = '';
       url += callouts_script_url;
    }
    call_ajax(url,'cmd=deletecallout&id=' + id,true,finish_delete_callout);
}

function finish_delete_callout(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {
       if (groups_grid) reload_group_callouts_grid();
       else reload_callouts_grid(0);
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function resize_screen(new_width,new_height)
{
    if (top.skin) {
       if (top.buttons == 'left') {
          var button_column1 = document.getElementById('buttonColumn1');
          var button_column2 = document.getElementById('buttonColumn2');
          var offset = get_body_height() -
                       Math.max(groups_grid.height,button_column1.offsetHeight) -
                       Math.max(callouts_grid.height,button_column2.offsetHeight);
       }
       else var offset = get_body_height() - groups_grid.height -
                         callouts_grid.height;
       var grid_height = Math.floor((new_height - offset) / 2);
       if (grid_height < 0) grid_height = 0;
       if (top.buttons == 'left') {
          resize_grid(groups_grid,groups_grid.width,
                      Math.max(grid_height,button_column1.offsetHeight));
          resize_grid(callouts_grid,callouts_grid.width,
                      Math.max(grid_height,button_column2.offsetHeight));
       }
       else {
          resize_grid(groups_grid,groups_grid.width,grid_height);
          resize_grid(callouts_grid,callouts_grid.width,grid_height);
       }
    }
    else {
       new_height = ((new_height + 20) / 2) - 15;
       if (top.buttons == 'top') new_height -= 40;
       resize_grid(groups_grid,new_width,new_height)
       resize_grid(callouts_grid,new_width,new_height);
       var sep_row = document.getElementById('imports_sep_row');
       var row_height = new_height - 125;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function load_groups_grid()
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2) - 15;
    if (top.buttons == 'top') grid_height -= 40;
    groups_grid = new Grid('callout_groups',grid_size.width,grid_height);
    groups_grid.set_columns(['ID','Name']);
    groups_grid.set_column_widths([0,800]);
    var query = 'select id,name from callout_groups';
    groups_grid.set_query(query);
    groups_grid.set_order_by('name');
    groups_grid.set_id('groups_grid');
    groups_grid.load(false);
    groups_grid.set_double_click_function(edit_group);
    groups_grid.grid.onCurrentRowChanged = select_group;
    groups_grid.display();
    if (! top.skin) {
       var sep_row = document.getElementById('groups_sep_row');
       var row_height = grid_height - 105;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function reload_groups_grid()
{
    groups_grid.table.reset_data(false);
    groups_grid.grid.refresh();
    window.setTimeout(function() {
       groups_grid.table.restore_position();
       var grid_row = groups_grid.grid.getCurrentRow();
       select_group(grid_row);
    },0);
}

function select_group(row)
{
    var group_id = groups_grid.grid.getCellText(0,row);
    var name = groups_grid.grid.getCellText(1,row);
    var group_label = document.getElementById('group_label');
    group_label.innerHTML = name;
    callouts_grid.table.set_where('parent=' + group_id);
    callouts_grid.table.reset_data(true);
    callouts_grid.grid.refresh();
}

function add_group()
{
    groups_grid.table.save_position();
    top.create_dialog('add_group',null,null,550,80,false,
                      script_prefix + 'admin.php?cmd=addcalloutgroup',null);
}

function process_add_group()
{
    top.enable_current_dialog_progress(true);
    submit_form_data(script_prefix + 'admin.php','cmd=processaddcalloutgroup',
                     document.AddGroup,finish_add_group);
}

function finish_add_group(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_groups_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_group()
{
    if (groups_grid.table._num_rows < 1) {
       alert('There are no callout groups to edit');   return;
    }
    var grid_row = groups_grid.grid.getCurrentRow();
    var id = groups_grid.grid.getCellText(0,grid_row);
    groups_grid.table.save_position();
    top.create_dialog('edit_group',null,null,550,80,false,
                      script_prefix + 'admin.php?cmd=editcalloutgroup&id=' +
                      id,null);
}

function update_group()
{
    top.enable_current_dialog_progress(true);
    submit_form_data(script_prefix + 'admin.php','cmd=updatecalloutgroup',
                     document.EditGroup,finish_update_group);
}

function finish_update_group(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_groups_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_group()
{
    if (groups_grid.table._num_rows < 1) {
       alert('There are no callout groups to delete');   return;
    }
    var grid_row = groups_grid.grid.getCurrentRow();
    var id = groups_grid.grid.getCellText(0,grid_row);
    var name = groups_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the "' + name +
                           '" callout group?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    groups_grid.table.save_position();
    call_ajax(script_prefix + 'admin.php','cmd=deletecalloutgroup&id=' + id,true,
              finish_delete_group);
}

function finish_delete_group(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_groups_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function search_groups()
{
    var query = document.SearchForm.query.value;
    if (query == '') {
       reset_search();   return;
    }
    top.display_status('Search','Searching Callout Groups...',350,100,null);
    window.setTimeout(function() {
       var where = '(name like "%' + query + '%")';
       groups_grid.set_where(where);
       groups_grid.table.reset_data(false);
       groups_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_search()
{
    top.display_status('Search','Loading All Callout Groups...',350,100,null);
    window.setTimeout(function() {
       document.SearchForm.query.value = '';
       groups_grid.set_where('');
       groups_grid.table.reset_data(false);
       groups_grid.grid.refresh();
       top.remove_status();
    },0);
}

function load_callouts_grid(group_id)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2) - 15;
    if (top.buttons == 'top') grid_height -= 40;
    callouts_grid = new Grid('callouts',grid_size.width,grid_height);
    callouts_grid.set_columns(['Id','Seq','Title','X %','Y %','Image',
                               'Description']);
    callouts_grid.set_column_widths([0,30,150,45,45,150,390]);
    callouts_grid.set_field_names(['id','sequence','title','xpos','ypos',
                                   'image','description']);
    var query = 'select id,sequence,title,xpos,ypos,image,description ' +
                'from callouts';
    callouts_grid.table.set_query(query);
    callouts_grid.set_where('parent=' + group_id);
    callouts_grid.set_order_by('sequence,id');
    callouts_grid.set_id('callouts_grid');
    callouts_grid.load(true);
    callouts_grid.set_double_click_function(edit_callout);
    callouts_grid.display();
}

function reload_group_callouts_grid()
{
    callouts_grid.table.reset_data(true);
    callouts_grid.grid.refresh();
    window.setTimeout(function() {
       callouts_grid.table.restore_position();
    },0);
}

