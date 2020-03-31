/*
      Inroads Shopping Cart - Google Shopping Cart Config JavaScript Functions

                         Written 2016-2018 by Randall Severy
                          Copyright 2016-2018 Inroads, LLC
*/

function resubmit_google_products()
{
    var response = confirm('All products will be resubmitted to Google.' +
                           '  Do you wish to continue?');
    if (! response) return;
    top.display_status('Resubmitting Products',
                       'Starting Background Resubmission, Please Wait...',
                       600,100,null);
    call_ajax('../admin/shopping/google/cmd.php','cmd=resubmit',true,
              finish_resubmit_google_products);
}

function finish_resubmit_google_products(ajax_request)
{
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 202) alert('Products will be Resubmitted to Google');
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

