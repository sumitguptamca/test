/*
        Inroads Control Panel/Shopping Cart - Dashboard Tab JavaScript Functions

                          Written 2015 by Randall Severy
                            Copyright 2015 Inroads, LLC
*/

var COLUMN_CHART = 0;
var LINE_CHART = 1;
var PIE_CHART = 2;
var BAR_CHART = 3;
var AREA_CHART = 4;
var GROUP_CHART = 10;
var TABLE_CHART = 11;

var DASHBOARD_REFRESH = 0;

var db_tabs = [];
var db_widgets = [];
var refresh_timeout = null;

function activate()
{
    if (refresh_timeout) window.clearTimeout(refresh_timeout);
    window.setTimeout(refresh_dashboard,0);
}

function set_widget_ids()
{
    var containers = document.getElementsByClassName('rfComponentContainer');
    var max_id = 0;
    for (var loop = 0;  loop < containers.length;  loop++) {
       if (containers[loop].id) {
          var widget_num = parseInt(containers[loop].id.substring(6));
          if (widget_num > max_id) max_id = widget_num;
       }
    }
    var widget_num = max_id + 1;
    for (var loop = 0;  loop < containers.length;  loop++) {
       if (containers[loop].id) continue;
       containers[loop].id = 'widget' + widget_num;
       widget_num++;
    }
}

function dashboard_tab_onclick()
{
    window.setTimeout(set_widget_ids,1000);
}

function add_object_event(obj,event_name,funct)
{
    if (typeof(obj.addEventListener) != 'undefined')
       obj.addEventListener(event_name,funct,false);
    else if (typeof(obj.attachEvent) != 'undefined')
       obj.attachEvent(event_name,funct);
}

function set_tab_onlick_functions()
{
    var tab_bar = document.getElementsByClassName('tabLinks');
    for (index in tab_bar[0].children)
       add_object_event(tab_bar[0].children[index],'click',
                        dashboard_tab_onclick);
}

function load_dashboard(tdb)
{
    var range_list = document.getElementById('range');
    var range = range_list.options[range_list.selectedIndex].value;
    var summary_list = document.getElementById('summary');
    var summary = summary_list.options[summary_list.selectedIndex].value;

    for (var tab in db_tabs) {

       if (tdb) {
          var db = new Dashboard();
          db.setDashboardTitle(db_tabs[tab]);
       }

       for (var id in db_widgets) {
          var widget_info = db_widgets[id];
          if (widget_info.tab != tab) continue;
          if (typeof(widget_info.widget) != 'undefined')
             var widget = widget_info.widget;
          else {
             if (widget_info.type == GROUP_CHART)
                var widget = new KPIGroupComponent();
             else if (widget_info.type == TABLE_CHART)
                var widget = new TableComponent();
             else var widget = new ChartComponent();
             widget.widget_id = id;
             widget.setDimensions(widget_info.width,widget_info.height);
             if (widget_info.name) widget.setCaption(widget_info.name);
             db_widgets[id].widget = widget;
             widget.lock();
             db.addComponent(widget);
          }
          var type = widget_info.type;
          var fields = 'cmd=loadwidget&id='+id+'&Type='+type+'&Range='+range +
                       '&Summary='+summary;
          var ajax_request = new Ajax('dashboard.php',fields,true);
          ajax_request.enable_alert();
          ajax_request.enable_parse_response();
          ajax_request.set_callback_function(load_widget,widget);
          ajax_request.set_timeout(30);
          ajax_request.send();
       }

       if (tdb) tdb.addDashboardTab(db,{ title: db_tabs[tab] });
    }
    window.setTimeout(function() {
       set_tab_onlick_functions();   set_widget_ids();
    },0);
}

function load_widget(ajax_request,widget)
{
    if (ajax_request.state != 4) return;
    var status = ajax_request.get_status();
    if (status != 200) return;

    var widget_id = widget.widget_id;
    var widget_data = JSON.parse(ajax_request.request.responseText);
    if (widget.isLocked()) var reload_widget = true;
    else if (widget_data.type == PIE_CHART)  {
       widget.lock();   var reload_widget = true;
    }
    else if (widget_data.type == GROUP_CHART) var reload_widget = false;
    else if ((typeof(widget_data.labels) != 'undefined') &&
             (widget.num_labels != widget_data.labels.length)) {
       widget.lock();   var reload_widget = true;
    }
    else var reload_widget = false;
    if (reload_widget) {
       if (typeof(widget.clearChart) != 'undefined') widget.clearChart();
       if (typeof(widget_data.labels) != 'undefined') {
          db_widgets[widget_id].widget.num_labels = widget_data.labels.length;
          if (widget_data.labels.length > 0)
             widget.setLabels(widget_data.labels);
       }
    }
    if (widget_data.type == PIE_CHART) {
       var series = widget_data.series[0];
       widget.setPieValues(series.data,series.options);
    }
    else if (widget_data.type == TABLE_CHART) {
       if (reload_widget) {
          if (widget_data.num_rows)
             widget.setRowsPerPage(widget_data.num_rows);
          for (var index in widget_data.columns) {
             var column = widget_data.columns[index];
             widget.addColumn(column.name,column.label,column.options);
          }
       }
       widget.clearRows();
       widget.addMultipleRows(widget_data.rows);
    }
    else for (var index in widget_data.series) {
       var series = widget_data.series[index];
       var series_id = 'series' + index;
       if (reload_widget) {
          var options = series.options;
          if (index > 0) {
             widget.addYAxis(series_id,series.name);
             options.yAxis = series_id;
          }
          widget.addSeries(series_id,series.name,series.data,options);
       }
       else widget.updateSeries(series_id,series.data);
    }
    for (var id in widget_data.kpis) {
       var kpi_options = widget_data.kpis[id];
       if (widget_data.type == GROUP_CHART) {
          if (reload_widget) widget.addKPI(id,kpi_options);
          else widget.updateKPI(id,kpi_options);
       }
       else if (reload_widget) widget.addComponentKPI(id,kpi_options);
       else widget.updateComponentKPI(id,kpi_options);
    }
    if (reload_widget) widget.unlock();
}

function dashboard_onload()
{
    StandaloneDashboard(load_dashboard,{tabbed: true});
    if (DASHBOARD_REFRESH)
       refresh_timeout = window.setTimeout(refresh_dashboard,
                                           DASHBOARD_REFRESH * 1000);
}

function update_dashboard()
{
    load_dashboard(null);
}

function refresh_dashboard()
{
    if (top.current_tab == 'dashboard') load_dashboard(null);
    if (DASHBOARD_REFRESH)
       refresh_timeout = window.setTimeout(refresh_dashboard,
                                           DASHBOARD_REFRESH * 1000);
}

