/*
        Inroads Control Panel/Shopping Cart - WebSocket JavaScript Functions

                       Written 2017-2018 by Randall Severy
                        Copyright 2017-2018 Inroads, LLC
*/

var notifications_enabled = false;
var notification_icon = null;
var websocket = null;
var pending_match = null;

var WS_RESPONSE = 0;
var WS_AUTH = 1;
var WS_MESSAGE = 2;
var WS_FUNCTION = 3;
var WS_LISTUSERS = 4;
var WS_CLOSESESSION = 5;

function display_notification(title,body)
{
    if (! notifications_enabled) return;
    if (! ('Notification' in window)) return;
    if (window.Notification.permission !== 'granted') return;
    var options = {body: body};
    if (notification_icon) options.icon = notification_icon;
    new Notification(title,options);
}

function websocket_response(status,message,user,leave_open)
{
    var msg = {'action':WS_RESPONSE,'status':status,'message':message};
    if ((typeof(user) != 'undefined') && user) msg.user = user;
    if (typeof(leave_open) != 'undefined') msg.leave_open = leave_open;
    websocket.send(JSON.stringify(msg));
console.log('Sent: ' + JSON.stringify(msg));
}

function Match(full_string,match_string,command)
{
    this.full_string = full_string;
    this.match_string = match_string;
    this.command = command;
    this.match_code = this.get_dm_code(match_string);
    this.matches = new Array();
    this.num_matches = 0;
}

Match.prototype.get_dm_code = function(string)
{
    var dm_codes = doubleMetaphone(string);
    return dm_codes[0];
}

Match.prototype.calc_distance = function(string1,string2)
{
    return damerauLevenshtein(string1,string2);
}

Match.prototype.add = function(full_string,match_string,obj)
{
    var code = this.get_dm_code(match_string);
    var distance = this.calc_distance(this.match_string,match_string);
    var dm_distance = this.calc_distance(this.match_code,code);
    this.matches.push({full_string:full_string, match_string:match_string,
                       obj:obj, code:code, distance:distance,
                       dm_distance:dm_distance});
}

Match.prototype.sort_score = function(a,b)
{
    if (a.dm_distance < b.dm_distance) return -1;
    if (a.dm_distance > b.dm_distance) return 1;
    if (a.distance < b.distance) return -1;
    if (a.distance > b.distance) return 1;
    return 0;
}

Match.prototype.get_matches = function()
{
    this.matches.sort(this.sort_score);
    this.num_matches = 0;
    for (var index in this.matches) {
       if (this.matches[index].dm_distance == 0) this.num_matches++;
    }
    return this.matches;
}

function process_response(message,user)
{
    if (pending_match.matches.length == 0) {
       websocket_response(410,'There are no more choices left',user);   return;
    }
    if (message == 'yes') {
       var match = pending_match.matches[0];
       if ((pending_match.command == 'menu') ||
           (pending_match.command == 'click')) {
          match.obj.click();   websocket_response(200,'Ok',user);   return;
       }
    }
    pending_match.matches.shift();
    if (pending_match.command == 'menu') var verb = 'open';
    else var verb = 'click';
    websocket_response(300,'Do you want me to ' + verb + ' ' +
                       pending_match.matches[0].full_string + '?',user,true);
}

function process_call_command(number,user)
{
    var body = document.getElementsByTagName('body')[0];
    var div = document.createElement('div');
    div.id = 'call_div';
    div.style.display = 'none';
    var anchor = document.createElement('a');
    anchor.id = 'call_link';
    anchor.href = number;
    div.appendChild(anchor);
    body.appendChild(div);
    anchor.click();
    body.removeChild(div);
    websocket_response(200,'Ok',user);
}

function process_menu_command(menu,user)
{
    var trimmed_menu = menu.toLowerCase().replace(/ /g,'');
    var match = new Match(menu,trimmed_menu,'menu');
    var labels = document.querySelectorAll('[data-label]');
    for (var index in labels) {
       if (! labels.hasOwnProperty(index)) continue;
       var label = labels[index].getAttribute('data-label');
       var trimmed_label = label.toLowerCase().replace(/ /g,'');
       match.add(label,trimmed_label,labels[index]);
       if (trimmed_label == trimmed_menu) {
          labels[index].click();
          websocket_response(200,'Ok',user);
          return;
       }
    }
    var matches = match.get_matches();
    if (match.num_matches == 1) {
       matches[0].obj.click();
       websocket_response(200,'Ok',user);
       return;
    }
    pending_match = match;
    websocket_response(300,'Do you want me to open ' +
                       matches[0].full_string + '?',user,true);
}

function trigger_keyboard_event(doc,element,event_type,key_code)
{
    var event_obj = doc.createEventObject ? doc.createEventObject() :
                                            doc.createEvent('Events');
    if (event_obj.initEvent) event_obj.initEvent(event_type,true,true);
    event_obj.keyCode = key_code;
    event_obj.which = key_code;
    element.dispatchEvent ? element.dispatchEvent(event_obj) :
                            element.fireEvent('on' + event_type,event_obj);
}

function process_search_command(query,user)
{
    if (num_dialogs == 0) var frame = get_content_frame();
    else var frame = get_current_dialog();
    var search_fields = frame.document.getElementsByClassName('search_input');
    if (search_fields && (search_fields.length > 0)) {
       var search = search_fields[0];
       search.value = query;   search.focus();
       trigger_keyboard_event(frame.document,search,'keypress',13);
       websocket_response(200,'Ok',user);
    }
    websocket_response(400,'I couldn\'t find a search box',user);
}

function process_reset_command(user)
{
    if (num_dialogs == 0) var frame = get_content_frame();
    else var frame = get_current_dialog();
    var reset_links = frame.document.getElementsByClassName('search_reset');
    if (reset_links && (reset_links.length > 0)) {
       var reset = reset_links[0];   reset.click();
       websocket_response(200,'Ok',user);
    }
    websocket_response(400,'I couldn\'t find a reset link',user);
}

function process_click_command(button,user)
{
    var trimmed_button = button.toLowerCase().replace(/ /g,'');
    var match = new Match(button,trimmed_button,'click');
    if (num_dialogs == 0) var frame = get_content_frame();
    else var frame = get_current_dialog();
    var buttons = frame.document.querySelectorAll('[data-label]');
    for (var index in buttons) {
       if (! buttons.hasOwnProperty(index)) continue;
       var label = buttons[index].getAttribute('data-label');
       var trimmed_label = label.toLowerCase().replace(/ /g,'');
       match.add(label,trimmed_label,buttons[index]);
       if (trimmed_label == trimmed_button) {
          buttons[index].click();
          websocket_response(200,'Ok',user);
          return;
       }
    }
    var matches = match.get_matches();
    if (match.num_matches == 1) {
       matches[0].obj.click();
       websocket_response(200,'Ok',user);
       return;
    }
    pending_match = match;
    websocket_response(300,'Do you want me to click ' +
                       matches[0].full_string + '?',user,true);
}

function find_scrollable_element(frame)
{
    var grids = frame.document.getElementsByClassName('aw-system-control');
    if ((! grids) || (grids.length == 0)) return null;
    return grids[0];
}

function process_arrow_command(key_code,user)
{
    if (num_dialogs == 0) var frame = get_content_frame();
    else var frame = get_current_dialog();
    var element = find_scrollable_element(frame);
    if (element) {
       trigger_keyboard_event(frame.document,element,'keydown',key_code);
       websocket_response(200,'Ok',user);
    }
    websocket_response(400,'I couldn\'t find anything to scroll',user);
}

function run_websocket_report(report)
{
    var doc = window.frames['reports_iframe'].document;
    if (typeof(doc.Reports) != 'undefined')
       var iframe = doc.getElementById('report_iframe');
    if ((typeof(doc.Reports) == 'undefined') || (! iframe)) {
       window.setTimeout(function() {
          run_websocket_report(report);
       },100);
    }
    else {
       doc.Reports.Report.selectedIndex = report;
       doc.getElementById('run_report').click();
    }
}

function run_websocket_order_button(button_id)
{
    var doc = window.frames['orders_iframe'].document;
    var button = doc.getElementById(button_id);
    if (! button) {
       window.setTimeout(function() {
          run_websocket_order_button(button_id);
       },100);
    }
    else button.click();
}

function process_websocket_message(msg)
{
console.log('Received: ' + msg.data);
    var message = JSON.parse(msg.data);
    if (! message) return;
    if (typeof(message.user) == 'undefined') var user = 0;
    else var user = message.user;
    if (message.action == WS_RESPONSE) {
       if (message.status != 200)
          display_notification('Error from WebSocket',message.message);
       if (pending_match) process_response(message.message,user);
       return;
    }
    else if (message.action == WS_MESSAGE)
       display_notification(message.title,message.body);
    else if (message.action == WS_FUNCTION) {
       if ((typeof(message.command) != 'undefined') &&
           (message.command == 'call')) {
          if ((typeof(message.number) == 'undefined') || (! message.number)) {
             websocket_response(406,'You must specify a number to call',user);
             return;
          }
          process_call_command(message.number,user);   return;
       }
       if (document.visibilityState == 'prerender') {
          websocket_response(406,'Axium Pro has not finished loading',user);
          return;
       }
       if (document.visibilityState != 'visible') {
          websocket_response(406,'Axium Pro is not the active browser tab, ' +
                             'please switch to it before using this command',
                             user);
          return;
       }
       if (typeof(message.command) != 'undefined') {
          switch (message.command) {
             case 'menu': process_menu_command(message.menu,user);   return;
             case 'search': process_search_command(message.query,user);
                            return;
             case 'reset': process_reset_command(user);   return;
             case 'click': process_click_command(message.button,user);
                           return;
             case 'down': process_arrow_command(40,user);   return;
             case 'up': process_arrow_command(38,user);   return;
          }
       }
       if (message.tab == 'reports') {
          if (current_tab != 'reports') {
             var tab = document.getElementById('reports');
             tab_click(tab.firstElementChild,'../cartengine/reports.php',
                       false,true);
          }
          run_websocket_report(message.report);
       }
       else if (message.tab == 'orders') {
          if (current_tab != 'orders') {
             var tab = document.getElementById('orders');
             tab_click(tab.firstElementChild,'../cartengine/orders.php',
                       false,true);
          }
          run_websocket_order_button(message.button);
       }
       else if (typeof(message.tab) != 'undefined') {
          var previous_tab = current_tab;
          var tab = document.getElementById(message.tab);
          tab_click(tab.firstElementChild,message.url,false,true);
          if ((previous_tab != message.tab) &&
              (array_index(loaded_tabs,message.tab) != -1)) {
             var iframe = document.getElementById(message.tab + '_iframe');
             iframe.src = message.url;
          }
       }
    }
    else if (message.action == WS_CLOSESESSION) pending_match = null;
    websocket_response(200,'Ok',user);
}

function init_websocket(port)
{
    if (location.protocol == 'https:') var url = 'wss';
    else var url = 'ws';
    if (admin_base_url) {
       var url_parts = admin_base_url.split('/');
       var hostname = url_parts[2];
    }
    else var hostname = location.hostname;
    if (location.protocol == 'https:') url += '://' + hostname + '/_ws_/';
    else url += '://' + hostname + ':' + port;
    try {
       websocket = new WebSocket(url);
       websocket.onopen = function(msg) { 
          if (this.readyState == 1) {
             var admin_user = get_cookie(login_cookie);
             var msg = {'action':WS_AUTH,'username':admin_user};
             websocket.send(JSON.stringify(msg));
          }
       };
       websocket.onmessage = process_websocket_message;
       websocket.onclose = function(msg) { websocket = null; };
       websocket.onerror = function() { websocket = null; };
    }
    catch(ex) {}
}

