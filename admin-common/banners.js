/*

            Inroads Shopping Cart - Banner Ads Tab JavaScript Functions

                         Written 2015 by Randall Severy
                          Copyright 2015 Inroads, LLC
*/

var slots_grid = null;
var ads_grid = null;
var cancel_add_slot = true;
var cancel_add_ad = true;

function resize_preview_image()
{
    var grid_height = slots_grid.height - 4;
    var grid_width = slots_grid.width;
    var content_area = document.getElementById('contentArea1');
    if (typeof(window.getComputedStyle) == 'undefined')
       var comp_style = content_area.currentStyle;
    else var comp_style = window.getComputedStyle(content_area);
    var content_width = parseInt(comp_style.width);

    var preview_image = document.getElementById('preview_image');
    if (! preview_image) image_width = 0;
    else {
       preview_image.style.width = 'auto';
       preview_image.style.height = 'auto';
       var image_width = preview_image.width;
       var image_height = preview_image.height;
       var ratio = image_width / image_height;
       image_height = grid_height;
       image_width = Math.floor(image_height * ratio);
       preview_image.style.width = '' + image_width + 'px';
       preview_image.style.height = '' + image_height + 'px';
       var preview_div = document.getElementById('preview_div');
       preview_div.style.width = '' + image_width + 'px';
       preview_image.style.display = '';
    }

    grid_width = content_width - image_width;
    resize_grid(slots_grid,grid_width,slots_grid.height);
    slots_grid.grid.setColumnWidth(grid_width - 155,1);
}

function resize_screen(new_width,new_height)
{
    var preview_image = document.getElementById('preview_image');
    preview_image.style.display = 'none';
    if (top.skin) {
       if (top.buttons == 'left') {
          var button_column1 = document.getElementById('buttonColumn1');
          var button_column2 = document.getElementById('buttonColumn2');
          var offset = get_body_height() -
                       Math.max(slots_grid.height,button_column1.offsetHeight) -
                       Math.max(ads_grid.height,button_column2.offsetHeight);
       }
       else var offset = get_body_height() - slots_grid.height -
                         ads_grid.height;
       var grid_height = Math.floor((new_height - offset) / 3);
       if (grid_height < 0) grid_height = 0;
       if (top.buttons == 'left') {
          resize_grid(slots_grid,slots_grid.width,
                      Math.max(grid_height * 2,button_column1.offsetHeight));
          resize_grid(ads_grid,ads_grid.width,
                      Math.max(grid_height,button_column2.offsetHeight));
       }
       else {
          resize_grid(slots_grid,slots_grid.width,grid_height * 2);
          resize_grid(ads_grid,ads_grid.width,grid_height);
       }
    }
    else {
       new_height = ((new_height + 20) / 3) - 15;
       if (top.buttons == 'top') new_height -= 40;
       resize_grid(slots_grid,new_width,new_height * 2)
       resize_grid(ads_grid,new_width,new_height);
       var sep_row = document.getElementById('ads_sep_row');
       var row_height = new_height - 125;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
    resize_preview_image();
}

function load_slots_grid()
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 3) * 2 - 15;
    if (top.buttons == 'top') grid_height -= 40;
    slots_grid = new Grid('banner_slots',grid_size.width,grid_height);
    slots_grid.table.set_field_names([]);
    slots_grid.set_columns(['ID','Name','Width','Height','Preview']);
    slots_grid.set_column_widths([30,450,50,50,0]);
    var query = 'select id,name,width,height,preview_image from banner_slots';
    slots_grid.set_query(query);
    slots_grid.set_order_by('id');
    slots_grid.table.set_convert_cell_data(convert_slot_data);
    slots_grid.set_id('slots_grid');
    slots_grid.load(true);
    slots_grid.set_double_click_function(edit_slot);
    slots_grid.grid.onCurrentRowChanged = select_slot;
    slots_grid.display();
    if (! top.skin) {
       var sep_row = document.getElementById('ads_sep_row');
       var row_height = grid_height - 105;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function reload_slots_grid()
{
    slots_grid.table.reset_data(true);
    slots_grid.grid.refresh();
    window.setTimeout(function() {
       slots_grid.table.restore_position();
       var grid_row = slots_grid.grid.getCurrentRow();
       select_slot(grid_row);
    },0);
}

function select_slot(row)
{
    var slot_id = slots_grid.grid.getCellText(0,row);
    var name = slots_grid.grid.getCellText(1,row);
    var preview_image = slots_grid.grid.getCellText(4,row);
    var slot_label = document.getElementById('slot_label');
    slot_label.innerHTML = name;
    ads_grid.table.set_where('parent=' + slot_id);
    ads_grid.table.reset_data(true);
    ads_grid.grid.refresh();
    var preview_div = document.getElementById('preview_div');
    if (preview_image)
       var html = '<img id="preview_image" src="../images/' +
          'banner-ads-preview/' + preview_image + '" style="display:none;">';
    else var html = '';
    preview_div.innerHTML = html;
    resize_preview_image();
}

function add_slot()
{
    cancel_add_slot = true;
    var ajax_request = new Ajax('banners.php','cmd=createslot',true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_slot,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function continue_add_slot(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    var status = ajax_request.get_status();
    if (status != 200) return;

    var slot_id = -1;
    eval(ajax_request.request.responseText);
    top.create_dialog('add_slot',null,null,575,165,false,
                      '../cartengine/banners.php?cmd=addslot&id='+slot_id,
                      null);
}

function add_slot_onclose(user_close)
{
    if (cancel_add_slot) {
       var slot_id = document.AddSlot.id.value;
       call_ajax('banners.php','cmd=deleteslot&id='+slot_id,true);
    }
}

function add_slot_onload()
{
    top.set_current_dialog_onclose(add_slot_onclose);
}

function process_add_slot()
{
    if (! validate_form_field(document.AddSlot.name,'Name')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('banners.php','cmd=processaddslot',document.AddSlot,
                     finish_add_slot);
}

function finish_add_slot(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_slot = false;
       top.get_content_frame().reload_slots_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_slot()
{
    if (slots_grid.table._num_rows < 1) {
       alert('There are no banner slots to edit');   return;
    }
    var grid_row = slots_grid.grid.getCurrentRow();
    var id = slots_grid.grid.getCellText(0,grid_row);
    slots_grid.table.save_position();
    top.create_dialog('edit_slot',null,null,575,165,false,
                      '../cartengine/banners.php?cmd=editslot&id='+id,null);
}

function update_slot()
{
    if (! validate_form_field(document.EditSlot.name,'Name')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('banners.php','cmd=updateslot',document.EditSlot,
                     finish_update_slot);
}

function finish_update_slot(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_slots_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_slot()
{
    if (slots_grid.table._num_rows < 1) {
       alert('There are no banner slots to delete');   return;
    }
    var grid_row = slots_grid.grid.getCurrentRow();
    var id = slots_grid.grid.getCellText(0,grid_row);
    var name = slots_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the '+name +
                           ' banner slot?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    slots_grid.table.save_position();
    call_ajax('banners.php','cmd=deleteslot&id='+id,true,finish_delete_slot);
}

function finish_delete_slot(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_slots_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function convert_slot_data(col,row,text)
{
    return text;
}

function load_ads_grid(slot_id)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 3) - 15;
    if (top.buttons == 'top') grid_height -= 40;
    ads_grid = new Grid('banner_ads',grid_size.width,grid_height);
    ads_grid.table.set_field_names([]);
    ads_grid.set_columns(['ID','Name','Image','URL','# Clicks','# Views']);
    ads_grid.set_column_widths([0,250,150,600,60,60]);
    var query = 'select id,name,image,url,clicks,views from banner_ads';
    ads_grid.table.set_query(query);
    ads_grid.set_where('parent=' + slot_id);
    ads_grid.table.set_order_by('name');
    ads_grid.table.set_convert_cell_data(convert_ad_data);
    ads_grid.set_id('ads_grid');
    ads_grid.load(true);
    ads_grid.set_double_click_function(edit_ad);
    ads_grid.display();
}

function reload_ads_grid()
{
    ads_grid.table.reset_data(false);
    ads_grid.grid.refresh();
    window.setTimeout(function() {
       ads_grid.table.restore_position();
    },0);
}

function add_ad()
{
    if (slots_grid.table._num_rows < 1) {
       alert('There are no banner slots to add a banner ad to');   return;
    }
    var grid_row = slots_grid.grid.getCurrentRow();
    var id = slots_grid.grid.getCellText(0,grid_row);
    cancel_add_ad = true;
    var ajax_request = new Ajax('banners.php','cmd=createad&parent='+id,true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_ad,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function continue_add_ad(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    var status = ajax_request.get_status();
    if (status != 200) return;

    var ad_id = -1;
    eval(ajax_request.request.responseText);
    var grid_row = slots_grid.grid.getCurrentRow();
    var width = slots_grid.grid.getCellText(2,grid_row);
    var height = slots_grid.grid.getCellText(3,grid_row);
    var url = '../cartengine/banners.php?cmd=addad&id=' + ad_id + '&width=' +
              width + '&height=' + height;
    top.create_dialog('add_ad',null,null,545,200,false,url,null);
}

function add_ad_onclose(user_close)
{
    if (cancel_add_ad) {
       var ad_id = document.AddAd.id.value;
       call_ajax('banners.php','cmd=deletead&id='+ad_id,true);
    }
}

function add_ad_onload()
{
    top.set_current_dialog_onclose(add_ad_onclose);
}

function process_add_ad()
{
    if (! validate_form_field(document.AddAd.name,'Name')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('banners.php','cmd=processaddad',document.AddAd,
                     finish_add_ad);
}

function finish_add_ad(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_ad = false;
       top.get_content_frame().reload_ads_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_ad()
{
    if (slots_grid.table._num_rows < 1) {
       alert('There are no banner ads to edit');   return;
    }
    var grid_row = ads_grid.grid.getCurrentRow();
    var ad_id = ads_grid.grid.getCellText(0,grid_row);
    ads_grid.table.save_position();
    var slots_grid_row = slots_grid.grid.getCurrentRow();
    var width = slots_grid.grid.getCellText(2,slots_grid_row);
    var height = slots_grid.grid.getCellText(3,slots_grid_row);
    var url = '../cartengine/banners.php?cmd=editad&id=' + ad_id + '&width=' +
              width + '&height=' + height;
    top.create_dialog('edit_ad',null,null,545,200,false,url,null);
}

function update_ad()
{
    if (! validate_form_field(document.EditAd.name,'Name')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('banners.php','cmd=updatead',document.EditAd,
                     finish_update_ad);
}

function finish_update_ad(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_content_frame().reload_ads_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_ad()
{
    if (slots_grid.table._num_rows < 1) {
       alert('There are no banner ads to delete');   return;
    }
    var grid_row = ads_grid.grid.getCurrentRow();
    var id = ads_grid.grid.getCellText(0,grid_row);
    var name = ads_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the '+name +
                           ' banner ad?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    ads_grid.table.save_position();
    call_ajax('banners.php','cmd=deletead&id='+id,true,finish_delete_ad);
}

function finish_delete_ad(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_ads_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function convert_ad_data(col,row,text)
{
    return text;
}

