/*
       Inroads Shopping Cart - Google Shopping Admin JavaScript Functions

                         Written 2018 by Randall Severy
                          Copyright 2018 Inroads, LLC
*/

function google_set_fields(product_data)
{
    var google_field = document.getElementById('google_shopping_id_string');
    if (google_field) {
       if (product_data.google_shopping_id)
          google_field.innerHTML = product_data.google_shopping_id;
       else google_field.innerHTML = '&nbsp;';
    }

    var google_row = document.getElementById('google_updated_row');
    var google_field = document.getElementById('google_shopping_updated');
    if (product_data.google_shopping_updated &&
        (! product_data.google_shopping_error)) {
       if (google_row) google_row.style.display = '';
       if (google_field) {
          var updated_date = new Date(parse_int(product_data.
                                      google_shopping_updated) * 1000);
          google_field.innerHTML =
             updated_date.format('mmmm d, yyyy h:MM:ss tt');
       }
    }
    else {
       if (google_row) google_row.style.display = 'none';
       if (google_field) google_field.innerHTML = '&nbsp;';
    }


    var google_row = document.getElementById('google_error_row');
    var google_field = document.getElementById('google_shopping_error');
    if (product_data.google_shopping_error) {
       if (google_row) google_row.style.display = '';
       if (google_field)
          google_field.innerHTML = product_data.google_shopping_error;
    }
    else {
       if (google_row) google_row.style.display = 'none';
       if (google_field) google_field.innerHTML = '&nbsp;';
    }

    var google_row = document.getElementById('google_warnings_row');
    var google_field = document.getElementById('google_shopping_warnings');
    if (product_data.google_shopping_warnings) {
       if (google_row) google_row.style.display = '';
       var warnings = product_data.google_shopping_warnings.replace(/|/g,'<br>');
       if (google_field) google_field.innerHTML = warnings;
    }
    else {
       if (google_row) google_row.style.display = 'none';
       if (google_field) google_field.innerHTML = '&nbsp;';
    }
}

