<?php
/*
            Inroads Shopping Cart - Facebook Commerce Vendor Functions

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC

*/

function add_facebook_import_fields($db,&$dialog,$edit_type,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->start_row('Facebook Commerce','middle','fieldprompt shopping_section');
    add_vendor_shopping_flag($dialog,FACEBOOK_SHOPPING_FLAG,$shopping_flags);
    $dialog->end_row();
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "google_shopping_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'google_shopping_cat'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Category\" class=\"select_shopping_" .
                   "button\" onClick=\"select_facebook_shopping_field('" .
                   "google_shopping_cat','Google Product Category');\">\n");
    $dialog->end_row();
}

function update_facebook_import(&$product_data)
{
    if ($product_data->product_id) return true;

    if (! empty($product_data->import['google_shopping_cat']))
       $product_data->product_record['google_shopping_cat']['value'] =
          $product_data->import['google_shopping_cat'];
    return true;
}

?>
