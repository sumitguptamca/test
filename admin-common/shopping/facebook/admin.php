<?php
/*
               Inroads Shopping Cart - Facebook Commerce Admin Functions

                         Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC
*/

define('FACEBOOK_GOOGLE_PRODUCT_TYPE_URL',
       'http://www.google.com/basepages/producttype/taxonomy.en-US.txt');

function add_facebook_product_fields(&$product_record)
{
    $product_record['google_shopping_cat'] = array('type' => CHAR_TYPE);
}

function add_facebook_shopping_fields($db,$dialog,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">Facebook Commerce\n");
    add_shopping_flag($dialog,FACEBOOK_SHOPPING_FLAG,$shopping_flags);
    $dialog->write("</div></td></tr>\n");
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "google_shopping_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'google_shopping_cat'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Category\" class=\"select_shopping_" .
                   "button\" onClick=\"select_shopping_field('" .
                   "google_shopping_cat','Facebook Product Category',1100);\">\n");
    $dialog->end_row();
}

function load_facebook_product_types($dialog,$value)
{
    $google_shopping_taxonomy =
       file_get_contents(FACEBOOK_GOOGLE_PRODUCT_TYPE_URL);
    if ($google_shopping_taxonomy) {
       $google_shopping_types = explode("\n",$google_shopping_taxonomy);
       foreach ($google_shopping_types as $shopping_type) {
          if ((! isset($shopping_type[0])) || ($shopping_type[0] == '#'))
             continue;
          trim($shopping_type);
          $dialog->add_list_item($shopping_type,$shopping_type,
                                 $value == $shopping_type);
       }
    }
}

?>
