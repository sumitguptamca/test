/*
        Inroads Control Panel/Shopping Cart - Reports Tab JavaScript Functions

                       Written 2007-2019 by Randall Severy
                         Copyright 2007-2019 Inroads, LLC
*/

var HTML_OUTPUT = 'html';
var EMAIL_OUTPUT = 'email';

var ONSCREEN_DESTINATION = '0';
var NEWTAB_DESTINATION = '1';
var DIALOG_DESTINATION = '2';
var POPUP_DESTINATION = '3';

var website_row_visible = false;
var range_row_visible = false;
var summary_row_visible = false;
var sales_summary_options_row_visible = false;
var product_options_row_visible = false;
var inventory_options_row_visible = false;
var order_source_row_visible = false;
var customer_status_row_visible = false;
var logfiles_rows_visible = false;
var script_prefix = '';
var script_name;
var reports_url = '';
var dialog_name = 'reports';
var use_dialog = false;
var enable_reorders = false;
var shopping_modules = null;
var modules = null;

function resize_report_iframe()
{
   if (use_dialog) {
      var dialog_iframe = top.get_dialog_frame(dialog_name);
      var body = top.get_dialog_document(dialog_name).body;
      var body_height = dialog_iframe.offsetHeight -
          top.get_style(body,'padding-top',true) -
          top.get_style(body,'padding-bottom',true);
      body.style.height = body_height + 'px';
      var form = document.forms.Reports;
      var rect = form.getBoundingClientRect();
      var bottom = rect.bottom;
      var iframe = document.getElementById('report_iframe');
      iframe.style.height = (body_height - bottom) + 'px';
   }
   else {
      var reports_screen = top.document.getElementById('reports_iframe');
      var screen_height = parseInt(reports_screen.style.height);
      var form = document.forms.Reports;
      var rect = form.getBoundingClientRect();
      var bottom = rect.bottom;
      var iframe = document.getElementById('report_iframe');
      iframe.style.height = (screen_height - bottom - 25) + 'px';
   }
}

function resize_screen(new_width,new_height)
{
    resize_report_iframe();
}

function resize_dialog(new_width,new_height)
{
    resize_report_iframe();
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
    if (output_type == EMAIL_OUTPUT) set_row_visible('email_address','');
    else set_row_visible('email_address','none');
    set_row_visible('other_email_span','none');
}

function select_email()
{
    var email = get_selected_list_value('email_address');
    if (email == '*') set_row_visible('other_email_span','');
    else set_row_visible('other_email_span','none');
}

function select_range(suffix)
{
    if (typeof(suffix) == 'undefined') suffix = '';
    var range_option = get_selected_list_value('range' + suffix);
    if (range_option == 'All') {
       set_row_visible('summary_week_cell','');
       set_row_visible('summary_month_cell','');
       set_row_visible('summary_year_cell','');
    }
    else if (range_option.slice(-4) == 'Week') {
       set_row_visible('summary_week_cell','none');
       set_row_visible('summary_month_cell','none');
       set_row_visible('summary_year_cell','none');
    }
    else if (range_option.slice(-5) == 'Month') {
       set_row_visible('summary_week_cell','');
       set_row_visible('summary_month_cell','none');
       set_row_visible('summary_year_cell','none');
    }
    else if (range_option.slice(-7) == 'Quarter') {
       set_row_visible('summary_week_cell','');
       set_row_visible('summary_month_cell','');
       set_row_visible('summary_year_cell','none');
    }
    else if (range_option.slice(-4) == 'Year') {
       set_row_visible('summary_week_cell','');
       set_row_visible('summary_month_cell','');
       set_row_visible('summary_year_cell','none');
    }
    else if (range_option == 'Range') {
       set_row_visible('summary_week_cell','');
       set_row_visible('summary_month_cell','');
       set_row_visible('summary_year_cell','');
    }
    if (range_option == 'Range')
       set_row_visible('range' + suffix + '_table','inline-block');
    else set_row_visible('range' + suffix + '_table','none');
    if (range_option == 'Month')
       set_row_visible('month' + suffix + '_table','inline-block');
    else set_row_visible('month' + suffix + '_table','none');
    if (range_option == 'Quarter')
       set_row_visible('quarter' + suffix + '_table','inline-block');
    else set_row_visible('quarter' + suffix + '_table','none');
    set_radio_button('summary','All');
}

function append_range_fields(suffix)
{
    if (typeof(suffix) == 'undefined') suffix = '';
    var field_name = 'range' + suffix;
    var range_option = get_selected_list_value(field_name);
    url = '&Range' + suffix + '=' + range_option;
    if (range_option == 'Range') {
       var start_date = document.Reports['range' + suffix +
                                         '_start_date'].value;
       var end_date = document.Reports['range' + suffix + '_end_date'].value;
       url += '&StartDate' + suffix + '=' + start_date + '&EndDate' + suffix +
              '=' + end_date;
    }
    if (range_option == 'Month') {
       var month = get_selected_list_value('month' + suffix + '_month');
       var year = get_selected_list_value('month' + suffix + '_year');
       url += '&Month' + suffix + '=' + month + '-' + year;
    }
    if (range_option == 'Quarter') {
       var quarter = get_selected_list_value('quarter' + suffix + '_quarter');
       var year = get_selected_list_value('quarter' + suffix + '_year');
       url += '&Quarter' + suffix + '=' + quarter + '-' + year;
    }
    return url;
}

function select_report()
{
    var report = get_selected_list_value('Report');
    if ((report == 'Sales') || (report == 'SalesSummary')) {
       set_row_visible('website_row','');
       website_row_visible = true;
    }
    else {
       set_row_visible('website_row','none');
       website_row_visible = false;
    }
    if ((report == 'Sales') || (report == 'SalesSummary') ||
        (report == 'NewCustomers') || (report == 'Products') ||
        (report == 'AllCustomers')) {
       set_row_visible('range_row','');
       range_row_visible = true;
       if ((report == 'SalesSummary') || (report == 'AllCustomers')) {
          set_row_visible('summary_row','none');
          summary_row_visible = false;
       }
       else {
          set_row_visible('summary_row','');
          summary_row_visible = true;
       }
       set_radio_button('range','All');
       set_radio_button('summary','All');
       select_range();
    }
    else if (range_row_visible) {
       set_row_visible('range_row','none');
       range_row_visible = false;
       set_row_visible('summary_row','none');
       summary_row_visible = false;
    }
    if ((report == 'Sales') || (report == 'SalesSummary') ||
        (report == 'Products')) {
       set_row_visible('order_source_row','');
       order_source_row_visible = true;
    }
    else {
       set_row_visible('order_source_row','none');
       order_source_row_visible = false;
    }
    if (report == 'SalesSummary') {
       set_row_visible('sales_summary_options_row','');
       sales_summary_options_row_visible = true;
    }
    else if (sales_summary_options_row_visible) {
       set_row_visible('sales_summary_options_row','none');
       sales_summary_options_row_visible = false;
    }
    if ((report == 'NewCustomers') || (report == 'AllCustomers')) {
       set_row_visible('customer_status_row','');
       customer_status_row_visible = true;
    }
    else if (customer_status_row_visible) {
       set_row_visible('customer_status_row','none');
       customer_status_row_visible = false;
    }
    if (report == 'Products') {
       set_row_visible('product_options_row','');
       product_options_row_visible = true;
    }
    else if (product_options_row_visible) {
       set_row_visible('product_options_row','none');
       product_options_row_visible = false;
    }
    if (report == 'Inventory') {
       set_row_visible('inventory_options_row','');
       inventory_options_row_visible = true;
    }
    else if (inventory_options_row_visible) {
       set_row_visible('inventory_options_row','none');
       inventory_options_row_visible = false;
    }
    if (report == 'LogFiles') {
       set_row_visible('logfiles_type_row','');
       set_row_visible('logfiles_date_row','');
       set_row_visible('output_row','none');
       set_row_visible('dest_row','');
       logfiles_rows_visible = true;
    }
    else if (logfiles_rows_visible) {
       set_row_visible('logfiles_type_row','none');
       set_row_visible('logfiles_date_row','none');
       set_row_visible('output_row','');   select_output();
       logfiles_rows_visible = false;
    }
    else if (report) {
       set_row_visible('output_row','');   select_output();
    }
    if (enable_reorders) select_reorders_report(report);
    if (typeof(select_custom_report) != 'undefined')
       select_custom_report(report);
    if (modules) {
       for (var index in modules) {
          if (typeof(window[modules[index] +
                     '_select_report']) != 'undefined')
          window[modules[index] + '_select_report'](report);
       }
    }
    if (shopping_modules) {
       for (var index in shopping_modules) {
          if (typeof(window[shopping_modules[index] +
                     '_select_report']) != 'undefined')
          window[shopping_modules[index] + '_select_report'](report);
       }
    }
    var iframe = document.getElementById('report_iframe');
    iframe.style.display = 'none';
}

function run_report()
{
    if (document.Reports.Report.tagName == 'INPUT')
       var report = document.Reports.Report.value;
    else var report = get_selected_list_value('Report');
    if (! report) {
       alert('You must select a Report');   return false;
    }
    var output_type = get_selected_list_value('Output');
    if (output_type == HTML_OUTPUT)
       var destination = get_selected_radio_button('Destination');
    if (output_type == EMAIL_OUTPUT) {
       var email_address = get_selected_list_value('email_address');
       if (email_address == '*')
          email_address = document.Reports.other_email.value;
       if (! email_address) {
          alert('You must select an E-Mail address');   return false;
       }
    }

    if ((! reports_url) ||
        ((output_type == HTML_OUTPUT) &&
          (destination == DIALOG_DESTINATION)))
       var url = script_prefix + script_name;
    else var url = reports_url;
    if (output_type == EMAIL_OUTPUT) {
       var script_url = url;   url = '';
    }
    else url += '?';
    url += 'cmd=runreport&Report=' + report + '&Output=' + output_type;
    if (document.Reports.WebSite) {
       var web_site = get_selected_list_value('WebSite');
       if (web_site) url += '&WebSite=' + web_site;
    }
    if ((report == 'Sales') || (report == 'SalesSummary') ||
        (report == 'NewCustomers') || (report == 'Products') ||
        (report == 'AllCustomers')) {
       url += append_range_fields();
       if ((report != 'SalesSummary') && (report != 'AllCustomers')) {
          var summary_option = get_selected_radio_button('summary');
          url += '&Summary=' + summary_option;
       }
       if (report == 'SalesSummary') {
          if (document.Reports.order_item_details.checked)
             url += '&ItemDetails=Yes';
          if (document.Reports.customer_details.checked)
             url += '&CustomerDetails=Yes';
          if (document.Reports.payment_details.checked)
             url += '&PaymentDetails=Yes';
       }
       if (report == 'Products') {
          if (document.Reports.include_attributes.checked)
             url += '&Attributes=Yes';
          else url += '&Attributes=No';
          if (document.Reports.include_ids.checked)
             url += '&ProductIDs=Yes';
          else url += '&ProductIDs=No';
       }
       if ((report == 'Sales') || (report == 'SalesSummary') ||
           (report == 'Products')) {
          if (document.Reports.source) {
             var source = get_selected_list_value('source');
             url += '&Source=' + source;
          }
       }
       if ((report == 'NewCustomers') || (report == 'AllCustomers')) {
          var status = get_selected_list_value('status');
          url += '&Status=' + status;
       }
    }
    else if (report == 'Inventory') {
       if (document.Reports.offsale_products.checked)
          url += '&OffSale=Yes';
       else url += '&OffSale=No';
    }
    else if (report == 'LogFiles') {
       var type = get_selected_list_value('LogFileType');
       url += '&Type=' + type;
       var start_date = document.Reports.logfiles_start_date.value;
       var end_date = document.Reports.logfiles_end_date.value;
       url += '&StartDate=' + start_date + '&EndDate=' + end_date;
    }
    else if (enable_reorders && reorders_report_selected(report)) {
       url = run_reorders_report(report,url);
       if (! url) return false;
    }
    else if (typeof(run_custom_report) != 'undefined') {
       url = run_custom_report(report,url);
       if (! url) return false;
    }
    if (modules) {
       for (var index in modules) {
          if (typeof(window[modules[index] +
                     '_run_report']) != 'undefined')
          url = window[modules[index] + '_run_report'](report,url);
          if (! url) return false;
       }
    }
    if (shopping_modules) {
       for (var index in shopping_modules) {
          if (typeof(window[shopping_modules[index] +
                     '_run_report']) != 'undefined')
          url = window[shopping_modules[index] + '_run_report'](report,url);
          if (! url) return false;
       }
    }
    if (typeof(custom_update_report_url) != 'undefined') {
       url = custom_update_report_url(report,url);
       if (! url) return false;
    }

    if (output_type == HTML_OUTPUT) {
       switch (destination) {
          case ONSCREEN_DESTINATION:
             url += '&Destination=onscreen';
             var run_button = document.getElementById('run_report');
             run_button.href = url;
             run_button.setAttribute('target','report_iframe');
             resize_report_iframe();
             var iframe = document.getElementById('report_iframe');
             if (! reports_url) {
                var loading = '<p style="text-align: center; font-family: ' +
                   'Arial,Helvetica,sans-serif; font-size: 24px; font-weight: ' +
                   'bold; font-style: italic; padding-top: 40px;">' +
                   'Loading Report...</p>';
                iframe.contentWindow.document.open();
                iframe.contentWindow.document.write(loading);
                iframe.contentWindow.document.close();
             }
             iframe.style.display = '';
             return true;
          case NEWTAB_DESTINATION:
             var run_button = document.getElementById('run_report');
             run_button.href = url;
             run_button.setAttribute('target','_blank');
             return true;
          case DIALOG_DESTINATION:
             url += '&Destination=dialog';
             top.create_dialog('Report',null,null,null,null,false,url,null);
             break;
          case POPUP_DESTINATION:
             var window_options = 'toolbar=yes,directories=no,menubar=yes,' +
                'status=no,scrollbars=yes,resizable=yes';
             var report_window = window.open(url,'Report',window_options);
             if (! report_window)
                alert('Unable to open report window, please enable popups ' +
                      'for this domain');
             break;
       }
    }
    else if (output_type == EMAIL_OUTPUT) {
       url += '&EmailAddress=' + encodeURIComponent(email_address);
       top.display_status('Sending E-Mail',
                          'Sending Report by E-Mail, Please Wait...',
                          550,100,null);
       call_ajax(script_url,url,true,finish_email_report,0);
    }
    else location.href = url;

    return false;
}

function finish_email_report(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) alert('Report Sent by E-Mail');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function reports_onload()
{
    if (document.Reports.Report.tagName == 'SELECT') select_report();
/*
    if (typeof(top.website) != 'undefined') {
       var list = document.Reports.WebSite;
       if (list) {
          for (var loop = 0;  loop < list.options.length;  loop++) {
             if (list.options[loop].value == top.website) {
                list.selectedIndex = loop;   break;
             }
          }
       }
    }
*/
}

