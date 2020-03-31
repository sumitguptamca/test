/*
        Inroads Control Panel/Shopping Cart - Main Screen JavaScript Functions

                         Written 2012-2018 by Randall Severy
                          Copyright 2012-2018 Inroads, LLC
*/

var login_cookie = null;
var prefs_cookie = null;
var cms_cookie = null;
var admin_cookie_domain = null;
var buttons = 'left';
var script_prefix = '';
var website_cookie = '';
var website = 0;
var cms_base_url = '';
var admin_base_url = null;
var sysconfig_dialog_width;
var sysconfig_dialog_height;
var cartconfig_dialog_width;
var website_hostnames = [];

function user_preferences()
{
    top.create_dialog('user_preferences',null,null,400,89,false,
                      script_prefix + 'adminusers.php?cmd=userprefs',null);
    return false;
}

function logout()
{
    if ((typeof(websocket) != 'undefined') && websocket) {
       websocket.close();   websocket = null;
    }
    location.href = script_prefix + 'main.php?cmd=logout';
    return false;
}

function select_website(list)
{
    website = list.options[list.selectedIndex].value;
    var today = new Date();
    var expires_date = new Date(today.getTime() + (60*60*24*365*1000));
    set_cookie(website_cookie,website,expires_date,top.admin_cookie_domain);
    top.loaded_tabs = [];
    top.loaded_tabs.push(top.current_tab);
    if (top.current_tab == 'cms') {
       var tab = document.getElementById(current_tab);
       tab.firstElementChild.click();
    }
    else {
       var screen = top.get_content_frame();
       screen.location.reload(true);
    }
}

function switch_layout()
{
    if (document.body.className.indexOf('maximized') != -1) {
       var image = 'expand.png';   var title = 'Switch to Maximized Layout';
       var pref_value = 'normal';
       document.body.className =
          document.body.className.replace(/ maximized/g,'');
    }
    else {
       var image = 'collapse.png';   var title = 'Switch to Normal Layout';
       var pref_value = 'maximized';
       document.body.className += ' maximized';
    }
    var layout_icon = document.getElementById('layout_icon');
    layout_icon.src = '../engine/images/' + image;
    layout_icon.title = title;
    adjust_iframe_height(false);
    if (skin) adjust_masthead();
    var fields = 'cmd=setuserpref&name=layout&value=' + pref_value;
    call_ajax(script_prefix + 'adminusers.php',fields,false);
}

function admin_users()
{
    top.create_dialog('admin_users',null,null,400,220,false,
                      script_prefix + 'adminusers.php?cmd=adminusers',null);
}

function import_data()
{
/*   if (admin_base_url) var url = admin_base_url + 'admin.php?cmd=importdata';
   else */ var url = script_prefix + 'admin.php?cmd=importdata';
   top.create_dialog('import_data',null,null,485,195,false,url,null);
}

function export_data()
{
/*   if (admin_base_url) var url = admin_base_url + 'admin.php?cmd=exportdata';
   else */ var url = script_prefix + 'admin.php?cmd=exportdata';
   top.create_dialog('export_data',null,null,460,90,false,url,null);
}

function system_config()
{
    top.create_dialog('system_config',null,null,sysconfig_dialog_width,
                      sysconfig_dialog_height,false,
                      script_prefix + 'admin.php?cmd=systemconfig',null);
}

function cart_config()
{
   var url = '../cartengine/admin.php?cmd=cartconfig';
   var window_width = top.get_document_window_width();
   if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
   else window_width -= top.default_dialog_frame_width;
   url += '&window_width=' + window_width;
   top.create_dialog('cart_config',null,null,cartconfig_dialog_width,680,
                     false,url,null);
}

function catalog_config()
{
   var url = script_prefix + 'catalogconfig.php';
   top.create_dialog('catalog_config',null,null,800,400,false,url,null);
}

function product_flags()
{
   top.create_dialog('product_flags',null,null,755,200,false,
                     'productflags.php',null);
}

function web_sites()
{
    top.create_dialog('web_sites',null,null,800,500,false,
                      script_prefix + 'websites.php',null);
}

function media_libraries()
{
    top.create_dialog('libraries',null,null,800,400,false,
                      script_prefix + 'media.php?cmd=libraries',null);
}

/*
function open_cpanel()
{
    top.create_dialog('cpanel',null,null,795,null,false,
                      script_prefix + 'admin.php?cmd=opencpanel',null);
}
*/
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

function open_wp_plugins()
{
    top.create_dialog('wp_plugins',null,null,1100,null,false,
                      script_prefix + 'admin.php?cmd=openwpplugins',null);
}

function publish_live_site()
{
    var response = confirm('Are you sure you want to publish all updates ' +
                           'from the development site to the live site?');
    if (! response) return;
    top.display_status("Publish","Publishing Updates to Live Site...",
                       450,100,null);
    var url = script_prefix + 'publish.php?cmd=publish';
    var ajax_request = new Ajax(url,"",true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_publish_site,null);
    ajax_request.set_timeout(300);
    ajax_request.send();
}

function finish_publish_site(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) alert('Updates Published to Live Site');
    else if (status == 202) alert('Updates Submitted for Publishing to Live Site');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function copy_live_data()
{
    top.display_status("CopyData","Copying Live Data to Development Site...",
                       550,100,null);
    var url = script_prefix + 'publish.php?cmd=copydata';
    var ajax_request = new Ajax(url,"",true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(finish_copy_live_data,null);
    ajax_request.set_timeout(60);
    ajax_request.send();
}

function finish_copy_live_data(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) alert('Live Data Copied to Development Site');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function manage_order_terms(frame)
{
    var url = '../cartengine/orders.php?cmd=manageterms';
    if (frame) url += '&Frame=' + frame;
    top.create_dialog('manage_terms',null,null,570,300,false,url,null);
}

function edit_packing_slip()
{
    top.create_dialog('edit_template',null,null,900,600,false,
                      script_prefix + 'templates.php?cmd=editpackingslip',
                      null);
}

function cms_edit(filename)
{
    var cms_tab = top.document.getElementById('cms');
    if (top.skin) cms_tab = cms_tab.firstChild;
    var url = cms_base_url + '?SmartUpdate=' + filename + '&BarStyle=2';
    top.cms_frame_name='cms_iframe';
    top.tab_click(cms_tab,url,false);
    if (array_index(top.loaded_tabs,'cms') != -1) {
       var iframe = top.document.getElementById('cms_iframe');
       if (iframe) iframe.src = url;
    }
}

function display_about()
{
   var url = script_prefix + 'main.php?cmd=about';
   top.create_dialog('about',null,null,500,370,false,url,null);
}

function display_credits()
{
   var url = top.script_prefix + 'main.php?cmd=credits';
   top.create_dialog('credits',null,null,550,165,false,url,null);
}

function display_license(product_name,license_url,license_format)
{
   var url = top.script_prefix + 'main.php?cmd=license&url=' +
             encodeURIComponent(license_url) + '&format=' +
             encodeURIComponent(license_format);
   if (license_format == 'text') var width = 600;
   else var width = '100%';
   top.create_dialog('license',null,null,width,'100%',false,url,null);
   top.set_dialog_title('license',product_name + ' License');
   top.enable_current_dialog_progress(false);
}

