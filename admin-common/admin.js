/*
           Inroads Control Panel/Shopping Cart - Admin Tab JavaScript Functions

                         Written 2007-2018 by Randall Severy
                          Copyright 2007-2018 Inroads, LLC
*/

var config_fields = [];
var login_cookie;
var script_prefix = '';
var cms_url;
var sysconfig_dialog_width;
var sysconfig_dialog_height;
var inside_cms = false;
var use_dynamic_images = false;
var image_subdir_prefix = 0;

function system_config_onclose(user_close)
{
    top.num_dialogs--;
}

function system_config_onload()
{
    if (inside_cms && (cms_top() != top)) {
       var cms = cms_top();
       cms.dialog_onload(document,window);
       var dialog_window = cms.dialog_windows[cms.num_dialogs - 1];
       var dialog_name = cms.dialog_names[cms.num_dialogs - 1];
       top.dialog_windows[top.num_dialogs] = dialog_window;
       top.dialog_names[top.num_dialogs] = dialog_name;
       top.num_dialogs++;
       top.set_dialog_title(dialog_name,'System Config');
       top.dialog_onload(document,window);
       top.set_current_dialog_onclose(system_config_onclose);
    }
}

function system_config()
{
    top.create_dialog('system_config',null,null,sysconfig_dialog_width,
                      sysconfig_dialog_height,false,
                      script_prefix + 'admin.php?cmd=systemconfig',null);
}

function close_system_config()
{
    if (inside_cms && (cms_top() != top))
       cms_top().close_current_dialog();
    else top.close_current_dialog();
}

function enable_image_buttons(enable_flag)
{
    var image_buttons_row = document.getElementById('image_buttons_row');
    if (! image_buttons_row) return;
    var manage_images_button = document.getElementById('manage_images');
    var update_images_button = document.getElementById('update_images');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    image_buttons_row.style.display = display_style;
    if (manage_images_button)
       manage_images_button.style.display = display_style;
    if (update_images_button)
       update_images_button.style.display = display_style;

    var attr_image_buttons_row =
       document.getElementById('attr_image_buttons_row');
    if (attr_image_buttons_row) {
       var manage_attr_images_button =
          document.getElementById('manage_attr_images');
       var update_attr_images_button =
          document.getElementById('update_attr_images');
       if (enable_flag) var display_style = '';
       else var display_style = 'none';
       attr_image_buttons_row.style.display = display_style;
       if (manage_attr_images_button)
          manage_attr_images_button.style.display = display_style;
       if (update_attr_images_button)
          update_attr_images_button.style.display = display_style;
    }
    if (typeof(enable_callout_image_buttons) != 'undefined')
       enable_callout_image_buttons(enable_flag);
}

function change_tab(tab,content_id)
{
    if (content_id == 'images_content') enable_image_buttons(true);
    else enable_image_buttons(false);
    if (typeof(enable_country_buttons) != "undefined") {
       if (content_id == 'countries_content') enable_country_buttons(true);
       else enable_country_buttons(false);
    }
    if (typeof(enable_schedule_buttons) != "undefined") {
       if (content_id == 'schedule_content') enable_schedule_buttons(true);
       else enable_schedule_buttons(false);
    }
    if (typeof(change_config_tab) != "undefined")
       change_config_tab(tab,content_id);
    tab_click(tab,content_id);
    resize_tabs();
    top.grow_current_dialog();
}

function toggle_street_view()
{
    var street_view = document.SystemConfig.map_streetview;
    var sv_row_0 = document.getElementById('sv_row_0');
    var sv_row_1 = document.getElementById('sv_row_1');
    if (street_view.checked) {
       sv_row_0.style.display = '';   sv_row_1.style.display = '';
    }
    else {
       sv_row_0.style.display = 'none';   sv_row_1.style.display = 'none';
    }
}

function lookup_address()
{
    top.enable_current_dialog_progress(true);
    submit_form_data(script_prefix + 'admin.php',"cmd=lookupaddress",
                     document.SystemConfig,finish_lookup_address);
}

function finish_lookup_address(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 200) {
       var latitude = 0;
       var longitude = 0;
       eval(ajax_request.request.responseText);
       var form = document.SystemConfig;
       form.map_latitude.value = latitude;
       form.map_longitude.value = longitude;
       if (form.map_sv_latitude.value == '')
          form.map_sv_latitude.value = latitude;
       if (form.map_sv_longitude.value == '')
          form.map_sv_longitude.value = longitude;
    }
    else ajax_request.display_error();
}

function find_address(link)
{
    var form = document.SystemConfig;
    var address = form.map_address1.value;
    if (form.map_address2.value) address += ' ' + form.map_address2.value;
    address += ' ' + form.map_city.value + ', ' + form.map_state.value + ' ' +
               form.map_zip.value;
    var country_list = form.map_country;
    if (country_list.selectedIndex != -1)
       address += ' ' + country_list.options[country_list.selectedIndex].text;
    var url = 'http://maps.google.com/maps?f=q&source=s_q&hl=en&geocode=&q=' +
              encodeURIComponent(address) + '&ie=UTF8&hq=&hnear=' +
              encodeURIComponent(address);
    link.href = url;
    return true;
}

function update_config()
{
    if (! validate_form_field(document.SystemConfig.admin_email,
                              "Admin E-Mail Address")) return;
 
    if ((! inside_cms) || (cms_top() == top))
       top.enable_current_dialog_progress(true);
    submit_form_data("admin.php","cmd=updateconfig",document.SystemConfig,
                     finish_update_config,60);
}

function finish_update_config(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {
       if ((typeof(countries_grid) != "undefined") && countries_grid)
          countries_grid.table.process_updates(false);
       if ((typeof(states_grid) != "undefined") && states_grid)
          states_grid.table.process_updates(false);
       if (typeof(custom_update_config) != "undefined")
          custom_update_config(ajax_request);
    }
    if ((! inside_cms) || (cms_top() == top))
       top.enable_current_dialog_progress(false);
    if (status == 201) close_system_config();
    else ajax_request.display_error();
}

function close_manage_images(user_close)
{
    top.using_admin_top = false;
}

var images_parent_type;

function manage_image_files(image_dir)
{
    top.using_admin_top = true;
    var url = cms_url + '?BrowseFiles=Go&Dir=' + image_dir +
              '&Mime=image/*&Frame=system_config&_JavaScript=Yes' +
              '&_iFrameDialog=Yes&NoDelete=true&NoCreateDir=true';
    if (use_dynamic_images) url += '&View=List&NoThumbs=Yes';
    if (image_subdir_prefix > 0)
       url += '&WorkingDir=' + image_dir;
    else url += '&NoDirs=true&HideCurrDir=true';
    url += '&UploadFinishFunction=top.get_dialog_frame(\'system_config\').' +
           'contentWindow.finish_upload_image&iFrameTime=' +
           '&CopyFinishFunction=top.get_dialog_frame(\'system_config\').' +
           'contentWindow.finish_upload_image&iFrameTime=' +
           '&UpdateFinishFunction=top.get_dialog_frame(\'system_config\').' +
           'contentWindow.update_image_file&iFrameTime=' +
           (new Date()).getTime();
    if (images_parent_type == 2) {
       if (document.SystemConfig.attrimage_crop_ratio &&
           (document.SystemConfig.attrimage_crop_ratio.value != ''))
          url += '&CropRatio=' + document.SystemConfig.attrimage_crop_ratio.value;
    }
    else if (images_parent_type == 3) {
       if (document.SystemConfig.callout_crop_ratio &&
           (document.SystemConfig.callout_crop_ratio.value != ''))
          url += '&CropRatio=' + document.SystemConfig.callout_crop_ratio.value;
    }
    else if (document.SystemConfig.image_crop_ratio &&
        (document.SystemConfig.image_crop_ratio.value != ''))
       url += '&CropRatio=' + document.SystemConfig.image_crop_ratio.value;
    top.create_dialog('browse_files',null,null,'80%','80%',false,url,null);
    top.set_dialog_onclose('browse_files',close_manage_images);
}

function manage_images()
{
    images_parent_type = 99;   manage_image_files('/images/original/');
}

function manage_attr_images()
{
    images_parent_type = 2;   manage_image_files('/attrimages/original/');
}

function finish_upload_image(filename)
{
    var slash_pos = filename.lastIndexOf('/');
    if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
    var slash_pos = filename.lastIndexOf('\\');
    if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
    var fields = "cmd=processnewimage&Filename=" + encodeURIComponent(filename) +
                 "&ParentType=" + images_parent_type;
    call_ajax(script_prefix + 'admin.php',fields,true,finish_process_new_image,60);
}

function finish_process_new_image(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {}
    else ajax_request.display_error();
}

function update_image_file(filename)
{
    var slash_pos = filename.lastIndexOf('/');
    if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
    var slash_pos = filename.lastIndexOf('\\');
    if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
    var fields = "cmd=updateimagefile&Filename=" + encodeURIComponent(filename) +
                 "&ParentType=" + images_parent_type;
    call_ajax(script_prefix + 'admin.php',fields,true,finish_update_image_file,60);
}

function finish_update_image_file(ajax_request)
{
    var status = ajax_request.get_status();
    if (status == 201) {}
    else ajax_request.display_error();
}

function update_image_files(parent_type)
{
    top.enable_current_dialog_progress(true);
    top.display_status("Update","Updating Images...",300,100,null);
    var fields = "cmd=updateimages&ParentType=" + parent_type;
    submit_form_data(script_prefix + 'admin.php',fields,
                     document.SystemConfig,finish_update_images,0);
}

function update_images()
{
    update_image_files(99);
}

function update_attr_images()
{
    update_image_files(2);
}

function finish_update_images(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) alert('Images Updated');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function web_sites()
{
    top.create_dialog('web_sites',null,null,800,500,false,
                      script_prefix + 'websites.php',null);
}

function open_cpanel()
{
    top.create_dialog('cpanel',null,null,795,null,false,
                      script_prefix + 'admin.php?cmd=opencpanel',null);
}

function open_google(use_new_window)
{
    if (use_new_window) window.open('http://www.google.com/analytics/');
    else top.create_dialog('google',null,null,'95%','80%',false,
                           script_prefix + 'admin.php?cmd=opengoogle',null);
}

function open_tickets()
{
    top.create_dialog('tickets',null,null,1100,700,false,
                      script_prefix + 'tickets.php',null);
}

function view_ticket(id)
{
    var url = script_prefix + 'tickets.php?onload=view_ticket('+id+');';
    top.create_dialog('tickets',null,null,1100,700,false,url,null);
}

function launch_cms()
{
    var popup_win = window.open(cms_url,'CMS');
    if (! popup_win) alert('Unable to open window, please enable popups for this domain');
}

var expires_date;

function logout()
{
    var today = new Date();
    expires_date = new Date(today.getTime() - 3600000);
    top.set_cookie(login_cookie,'',expires_date,top.admin_cookie_domain);
    logout_modules();
    top.location.reload(true);
}

function updatedb_onload()
{
    var cover_div = top.document.getElementById('cover_div');
    if (cover_div) cover_div.style.display = 'none';
    if (cms_top() != window) top.dialog_onload(document,window);
    document.UpdateDB.Query.focus();
}

function update_database()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('index.php',"cmd=processupdatedb",document.UpdateDB,
                     finish_update_database);
}

function finish_update_database(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) alert('Database Update Complete');
}

function checkphp_onload()
{
    var cover_div = top.document.getElementById('cover_div');
    if (cover_div) cover_div.style.display = 'none';
    if (cms_top() != window) top.dialog_onload(document,window);
}

