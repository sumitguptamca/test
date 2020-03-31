<?php
/*
                  Inroads Shopping Cart - TheFind Shopping Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

define('THEFIND_FLAG',5);

function thefind_module_info(&$modules)
{
    $modules[] = array('modulename'=>'thefind','name'=>'TheFind',
                       'flag'=>THEFIND_FLAG);
}

function thefind_add_shopping_fields($db,&$dialog,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">TheFind\n");
    add_shopping_flag($dialog,THEFIND_FLAG,$shopping_flags);
    $dialog->write("</div></td></tr>\n");
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "thefind_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'thefind_cat'));
    $dialog->write("\">\n");
    $dialog->end_row();
}

function thefind_setup_select_field($field,&$field_info)
{
    if ($field != 'amazon_item_type') return;
    $field_info['table'] = 'amazon_item_types';
    $field_info['id_field'] = 'item_type';
    $field_info['label_field'] = 'description';
    $field_info['use_listbox'] = false;
    $field_info['label_width'] = 810;
}

function thefind_add_product_fields(&$product_record)
{
    $product_record['thefind_cat'] = array('type' => CHAR_TYPE);
}

function thefind_update_export_fields(&$query,&$field_defs)
{
    $query .= ',p.thefind_cat';
}

?>
