/*
  Inroads Shopping Cart - Google Shopping Reports JavaScript Functions

                    Written 2018 by Randall Severy
                     Copyright 2018 Inroads, LLC
*/

var google_row_visible = false;

function google_select_report(report)
{
    if (report == 'Google') {
       set_row_visible('google_row','');
       google_row_visible = true;
    }
    else {
       set_row_visible('google_row','none');
       google_row_visible = false;
    }
}

function google_run_report(report,url)
{
    if (report == 'Google') {
       var list_field = document.getElementById('google_report');
       var google_report = list_field.options[list_field.selectedIndex].value;
       var title = list_field.options[list_field.selectedIndex].text;
       var google_report = get_selected_list_value('google_report');
       url += '&GoogleReport=' + google_report + '&Title=' +
              encodeURIComponent(title);
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

