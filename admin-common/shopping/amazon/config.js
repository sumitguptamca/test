/*
   Inroads Shopping Cart - Amazon Cart Config JavaScript Functions

                   Written 2016-2019 by Randall Severy
                    Copyright 2016-2019 Inroads, LLC
*/

function select_amazon_carrier(list_field,level)
{
    var method_list = document.getElementById('amazon_method_'+level);
    var carrier = list_field.options[list_field.selectedIndex].value;
    var options = window[carrier+'_options'];
    if (typeof(options) == 'undefined') {
       method_list.innerHTML = '';   return;
    }
    var html = '<option value=""></option>';
    for (var id in options)
       html += '<option value="' + id + '">' + options[id] + '</option>';
    method_list.innerHTML = html;
}

function download_amazon_products()
{
    var response = confirm('All products will be downloaded from Amazon.' +
                           '  Do you wish to continue?');
    if (! response) return;
    top.display_status('Downloading Products',
                       'Starting Background Download, Please Wait...',
                       600,100,null);
    call_ajax('../admin/shopping/amazon/cmd.php','cmd=startdownload',true,
              finish_download_amazon_products);
}

function finish_download_amazon_products(ajax_request)
{
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 202) alert('Products will be Downloaded from Amazon');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function update_fba_flags()
{
    var response = confirm('All product FBA flags will be updated from ' +
                           'Amazon.  Do you wish to continue?');
    if (! response) return;
    top.display_status('Updating FBA Flags',
                       'Updating FBA Flags, Please Wait...',
                       600,100,null);
    call_ajax('../admin/shopping/amazon/cmd.php','cmd=updatefba',true,
              finish_update_fba_flags);
}

function finish_update_fba_flags(ajax_request)
{
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) alert('Product FBA flags updated from Amazon');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

