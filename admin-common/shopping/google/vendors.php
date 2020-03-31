<?php
/*
              Inroads Shopping Cart - Google Shopping Vendor Functions

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

function add_google_import_fields($db,&$dialog,$edit_type,$row)
{
    global $amazon_category_types;

    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->start_row('Google Shopping','middle','fieldprompt shopping_section');
    add_vendor_shopping_flag($dialog,GOOGLE_SHOPPING_FLAG,$shopping_flags);
    $dialog->end_row();
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "google_shopping_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'google_shopping_cat'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Category\" class=\"select_shopping_" .
                   "button\" onClick=\"select_google_shopping_field('" .
                   "google_shopping_cat','Google Product Category');\">\n");
    $dialog->end_row();
    $dialog->start_row('Product Type:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "google_shopping_type\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'google_shopping_type'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Type\" class=\"select_shopping_button\" " .
                   "onClick=\"select_google_shopping_field('google_shopping_type'," .
                   "'Google Product Type');\">\n");
    $dialog->end_row();
    $dialog->add_edit_row('AdWords Labels:','google_adwords',$row,55);
}

function update_google_import(&$product_data)
{
    if ($product_data->product_id) return true;

    if (! empty($product_data->import['google_shopping_cat']))
       $product_data->product_record['google_shopping_cat']['value'] =
          $product_data->import['google_shopping_cat'];
    if (! empty($product_data->import['google_shopping_type']))
       $product_data->product_record['google_shopping_type']['value'] =
          $product_data->import['google_shopping_type'];
    if (! empty($product_data->import['google_adwords']))
       $product_data->product_record['google_adwords']['value'] =
          $product_data->import['google_adwords'];
    return true;
}

?>
