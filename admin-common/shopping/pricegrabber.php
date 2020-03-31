<?php
/*
                 Inroads Shopping Cart - PriceGrabber Shopping Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

define('PRICEGRABBER_FLAG',3);
define('PRICEGRABBER_CATEGORIES_URL',
       'https://partner.pricegrabber.com/partner.php/cpc_rates/export/country_domain:us/public:1');

function pricegrabber_module_info(&$modules)
{
    $modules[] = array('modulename'=>'pricegrabber','name'=>'PriceGrabber',
                       'flag'=>PRICEGRABBER_FLAG);
}

function pricegrabber_add_shopping_fields($db,&$dialog,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">PriceGrabber\n");
    add_shopping_flag($dialog,PRICEGRABBER_FLAG,$shopping_flags);
    $dialog->write("</div></td></tr>\n");
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "pricegrabber_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'pricegrabber_cat'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Category\" class=\"select_shopping_" .
                   "button\" onClick=\"select_shopping_field('" .
                   "pricegrabber_cat','PriceGrabber Product Category',600);\">\n");
    $dialog->end_row();
}

function pricegrabber_setup_select_field($field,&$field_info)
{
    if ($field != 'pricegrabber_cat') return;
    $field_info['use_listbox'] = true;
}

function pricegrabber_load_select_field_list(&$dialog,$field,$value)
{
    if ($field != 'pricegrabber_cat') return;
    $category_data = file_get_contents(PRICEGRABBER_CATEGORIES_URL);
    if ($category_data) {
       $categories = explode("\n",$category_data);
       foreach ($categories as $category) {
          $end_pos = strpos($category,"\",");
          if ($end_pos === false) continue;
          $category = substr($category,1,$end_pos - 1);
          $dialog->add_list_item($category,$category,
                                 $value == $category);
       }
    }
}

function pricegrabber_add_product_fields(&$product_record)
{
    $product_record['pricegrabber_cat'] = array('type' => CHAR_TYPE);
}

function pricegrabber_update_export_fields(&$query,&$field_defs)
{
    $query .= ',p.pricegrabber_cat';
}

?>
