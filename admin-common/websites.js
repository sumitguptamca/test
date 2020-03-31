/*

   Inroads Control Panel/Shopping Cart - Web Sites Dialog JavaScript Functions

                        Written 2012-2019 by Randall Severy
                         Copyright 2012-2019 Inroads, LLC
*/

var script_prefix = '';
var websites_grid = null;
var website_settings;

var WEBSITE_SHARED_CART = 1;
var WEBSITE_SEPARATE_CMS = 2;
var WEBSITE_SEPARATE_PAYMENT = 4;

function resize_dialog(new_width,new_height)
{
    if (! websites_grid) return;
    if (top.skin)
       resize_grid(websites_grid,-1,new_height - get_grid_offset(websites_grid));
    else resize_grid(websites_grid,new_width,new_height)
}

function load_grid()
{
   var grid_size = get_default_grid_size();
   websites_grid = new Grid('web_sites',grid_size.width,grid_size.height);
   websites_grid.set_columns(['ID','Web Site','Hostname','Root Directory',
                              'Base Href']);
   websites_grid.set_column_widths([0,120,150,160,180]);
   var query = 'select id,name,domain,rootdir,base_href from web_sites';
   websites_grid.set_query(query);
   websites_grid.set_order_by('name,domain');
   websites_grid.table.set_convert_cell_data(convert_website_data);
   websites_grid.set_id('websites_grid');
   websites_grid.load(false);
   websites_grid.set_double_click_function(edit_website);
   websites_grid.display();
}

function reload_grid()
{
   websites_grid.table.reset_data(false);
   websites_grid.grid.refresh();
   window.setTimeout(function() { websites_grid.table.restore_position(); },0);
}

function get_website_dialog_width()
{
    var width = 720;
    if (website_settings & WEBSITE_SEPARATE_PAYMENT) width += 105;
    return width;
}

function add_website()
{
   websites_grid.table.save_position();
   var width = get_website_dialog_width();
   top.create_dialog('add_website',null,null,width,340,false,
                     script_prefix + 'websites.php?cmd=addwebsite',null);
}

function process_add_website()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('websites.php','cmd=processaddwebsite',document.AddWebSite,
                    finish_add_website);
}

function finish_add_website(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_dialog_frame('web_sites').contentWindow.reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function edit_website()
{
   if (websites_grid.table._num_rows < 1) {
      alert('There are no web sites to edit');   return;
   }
   var grid_row = websites_grid.grid.getCurrentRow();
   var id = websites_grid.grid.getCellText(0,grid_row);
   websites_grid.table.save_position();
   var width = get_website_dialog_width();
   top.create_dialog('edit_website',null,null,width,340,false,
                     script_prefix + 'websites.php?cmd=editwebsite&id=' + id,
                     null);
}

function update_website()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('websites.php','cmd=updatewebsite',document.EditWebSite,
                    finish_update_website);
}

function finish_update_website(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_dialog_frame('web_sites').contentWindow.reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function delete_website()
{
   if (websites_grid.table._num_rows < 1) {
      alert('There are no web sites to delete');   return;
   }
   var grid_row = websites_grid.grid.getCurrentRow();
   var id = websites_grid.grid.getCellText(0,grid_row);
   var name = websites_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete the "' + name +
                          '" web site?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   websites_grid.table.save_position();
   call_ajax('websites.php','cmd=deletewebsite&id=' + id,true,
             finish_delete_website);
}

function finish_delete_website(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function convert_website_data(col,row,text)
{
   return text;
}

function change_tab(tab,content_id)
{
   tab_click(tab,content_id);
   top.grow_current_dialog();
   resize_tabs();
}

function edit_website_settings()
{
   top.create_dialog('website_settings',null,null,460,85,false,
                     script_prefix + 'websites.php?cmd=settings',null);
}

function update_settings()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('websites.php','cmd=updatesettings',
                    document.Settings,finish_update_settings);
}

function finish_update_settings(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) top.close_current_dialog();
   else if ((status >= 200) && (status < 300))
      ajax_request.process_error(ajax_request.request.responseText);
}

function toggle_street_view()
{
    if (document.AddWebSite) var form = document.AddWebSite;
    else var form = document.EditWebSite;
    var street_view = form.map_streetview;
    var sv_row_0 = document.getElementById('sv_row_0');
    var sv_row_1 = document.getElementById('sv_row_1');
    if (street_view.checked) {
       sv_row_0.style.display = '';   sv_row_1.style.display = '';
       top.grow_current_dialog();
    }
    else {
       sv_row_0.style.display = 'none';   sv_row_1.style.display = 'none';
    }
}

function lookup_address()
{
    if (document.AddWebSite) var form = document.AddWebSite;
    else var form = document.EditWebSite;
    top.enable_current_dialog_progress(true);
    submit_form_data(script_prefix + 'admin.php',"cmd=lookupaddress",
                     form,finish_lookup_address);
}

function finish_lookup_address(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 200) {
       var latitude = 0;
       var longitude = 0;
       eval(ajax_request.request.responseText);
       if (document.AddWebSite) var form = document.AddWebSite;
       else var form = document.EditWebSite;
       form.map_latitude.value = latitude;
       form.map_longitude.value = longitude;
       if (form.map_sv_latitude.value == '')
          form.map_sv_latitude.value = latitude;
       if (form.map_sv_longitude.value == '')
          form.map_sv_longitude.value = longitude;
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function find_address(link)
{
    if (document.AddWebSite) var form = document.AddWebSite;
    else var form = document.EditWebSite;
    var address = form.config_map_address1.value;
    if (form.config_map_address2.value)
       address += ' ' + form.config_map_address2.value;
    address += ' ' + form.config_map_city.value + ', ' +
       form.config_map_state.value + ' ' + form.config_map_zip.value;
    var country_list = form.config_map_country;
    if (country_list.selectedIndex != -1)
       address += ' ' + country_list.options[country_list.selectedIndex].text;
    var url = 'http://maps.google.com/maps?f=q&source=s_q&hl=en&geocode=&q=' +
              encodeURIComponent(address) + '&ie=UTF8&hq=&hnear=' +
              encodeURIComponent(address);
    link.href = url;
    return true;
}

