<?php
/*
                  Inroads Shopping Cart - Shopzilla Shopping Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

define('SHOPZILLA_FLAG',4);
define('SHOPZILLA_CATEGORIES_URL',
       'http://merchant.shopzilla.com/oa/general/taxonomy.xpml');

function shopzilla_module_info(&$modules)
{
    $modules[] = array('modulename'=>'shopzilla','name'=>'Shopzilla',
                       'flag'=>SHOPZILLA_FLAG);
}

function shopzilla_add_shopping_fields($db,&$dialog,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">Shopzilla\n");
    add_shopping_flag($dialog,SHOPZILLA_FLAG,$shopping_flags);
    $dialog->write("</div></td></tr>\n");
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "shopzilla_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'shopzilla_cat'));
    $dialog->write("\">&nbsp;<a href=\"".SHOPZILLA_CATEGORIES_URL .
                   "\" target=\"_blank\" class=\"shopzilla_link\">" .
                   "View Categories</a>\n");
    $dialog->end_row();
}

function shopzilla_setup_select_field($field,&$field_info)
{
    if ($field != 'amazon_item_type') return;
    $field_info['table'] = 'amazon_item_types';
    $field_info['id_field'] = 'item_type';
    $field_info['label_field'] = 'description';
    $field_info['use_listbox'] = false;
    $field_info['label_width'] = 810;
}

function shopzilla_add_product_fields(&$product_record)
{
    $product_record['shopzilla_cat'] = array('type' => CHAR_TYPE);
}

function shopzilla_update_export_fields(&$query,&$field_defs)
{
    $query .= ',p.shopzilla_cat';
}

?>
