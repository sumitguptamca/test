/*
   Inroads Shopping Cart - Amazon Reports JavaScript Functions

              Written 2018-2019 by Randall Severy
               Copyright 2018-2019 Inroads, LLC
*/

var amazon_rows_visible = false;

function amazon_select_report(report)
{
    if (report == 'Amazon') {
       set_row_visible('amazon_row','');
       set_row_visible('amazon_options_row','');
       amazon_rows_visible = true;
    }
    else {
       set_row_visible('amazon_row','none');
       set_row_visible('amazon_options_row','none');
       amazon_rows_visible = false;
    }
}

function amazon_run_report(report,url)
{
    if (report == 'Amazon') {
       var list_field = document.getElementById('amazon_report');
       var amazon_report = list_field.options[list_field.selectedIndex].value;
       var title = list_field.options[list_field.selectedIndex].text;
       var amazon_report = get_selected_list_value('amazon_report');
       url += '&AmazonReport=' + amazon_report + '&Title=' +
              encodeURIComponent(title);
       if (document.Reports.use_last_amazon_report.checked)
          url += '&UseLast=Yes';
    }
    return url;
}

function edit_product(id)
{
    if (top.skin) var dialog_width = 1100;
    else var dialog_width = 900;
    var dialog_name = 'edit_product_' + (new Date()).getTime();
    var url = script_prefix + 'products.php?cmd=editproduct&id='+id;
    var window_width = top.get_document_window_width();
    if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
    else window_width -= top.default_dialog_frame_width;
    url += '&window_width=' + window_width;
    top.create_dialog(dialog_name,null,null,dialog_width,
                      600,false,url,null);
    return false;
}

