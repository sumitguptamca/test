/*
        Inroads Control Panel/Shopping Cart - Login Screen JavaScript Functions

                        Written 2008-2017 by Randall Severy
                         Copyright 2008-2017 Inroads, LLC
*/

var login_cookie;
var admin_cookie_domain = null;

function login_onload()
{
    var cover_div = top.document.getElementById('cover_div');
    if (cover_div) cover_div.style.display = 'none';
//    if (cms_top() != window) top.dialog_onload(document,window);
    document.Login.username.focus();
}

var login_request;

function process_login()
{
    if (! validate_form_field(document.Login.username,"Username")) return;
    if (! validate_form_field(document.Login.password,"Password")) return;
    top.display_status("Status","Processing Login...",350,100,cancel_login);
    var username = document.Login.username.value.trim();
    var query = "cmd=validateuser&username=" + encodeURIComponent(username) +
       "&password=" + encodeURIComponent(document.Login.password.value);
    login_request = new Ajax("index.php",query,true);
    login_request.enable_parse_response();
    login_request.set_callback_function(finish_login,username);
    login_request.set_timeout(30);
    login_request.send();
}

function cancel_login(user_close)
{
    if (user_close) login_request.abort();
}

function build_home_url()
{
    var form = document.Login;
    var fields = '';
    for (loop=0;  loop < form.elements.length;  loop++) {
       if (form.elements[loop].name == "") continue;
       if ((form.elements[loop].tagName == 'INPUT') &&
           (form.elements[loop].type == 'hidden')) {
            if (fields != '') fields += '&';
            fields += encodeURIComponent(form.elements[loop].name) + '=' +
               encodeURIComponent(form.elements[loop].value);
       }
    }
    var url = 'index.php';
    if (fields) url += '?'+fields;
    return url;
}

function finish_login(login_request,username)
{
    if (login_request.state != 4) return;
    if (login_request.aborted) return;

    top.remove_status();
    if (login_request.timeout) {
       alert('Timeout while processing login');   return;
    }

    var status = login_request.get_status();
    if (status == 201) {
       var today = new Date();
       var expires_date = new Date(today.getTime() + 86400000);
       var cookie_string = login_cookie + '=' + escape(username);
       if (admin_cookie_domain)
          cookie_string += ';domain=' + admin_cookie_domain;
       cookie_string += ';path=/;expires=' + expires_date.toGMTString();
       document.cookie = cookie_string;
       location.href = build_home_url();
    }
    else if ((status == 404) || (status == 409)) {
       alert('Invalid Username or Password');
       document.Login.username.focus();
       return;
    }
    else login_request.display_error();
}

function msie_event_handler()
{
    if (event.keyCode == 13) {
       process_login();   return false;
    }
    return true;
}
function netscape_event_handler(event)
{
    if (event.which == 13) {
       process_login();   return false;
    }
    return true;
}
function capture_events()
{
    if (! document.all) {
       window.captureEvents(Event.KEYDOWN);
       window.onkeydown = netscape_event_handler;
    }
    else document.onkeydown = msie_event_handler;
}
capture_events();

