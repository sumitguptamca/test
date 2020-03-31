/*
        Inroads Shopping Cart - Amazon Admin JavaScript Functions

                     Written 2018 by Randall Severy
                      Copyright 2018 Inroads, LLC
*/

function amazon_set_fields(product_data)
{
    var error_row = document.getElementById('amazon_error_row');
    var error_field = document.getElementById('amazon_error');
    if (product_data.amazon_error) {
       if (error_row) google_row.style.display = '';
       if (error_field) error_field.innerHTML = product_data.amazon_error;
    }
    else {
       if (error_row) error_row.style.display = 'none';
       if (error_field) error_field.innerHTML = '&nbsp;';
    }
    var warning_row = document.getElementById('amazon_warning_row');
    var warning_field = document.getElementById('amazon_warning');
    if (product_data.amazon_warning) {
       if (warning_row) google_row.style.display = '';
       if (warning_field) warning_field.innerHTML = product_data.amazon_warning;
    }
    else {
       if (warning_row) warning_row.style.display = 'none';
       if (warning_field) warning_field.innerHTML = '&nbsp;';
    }
}

