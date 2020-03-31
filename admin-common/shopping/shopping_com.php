<?php
/*
                Inroads Shopping Cart - Shopping.com Shopping Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

define('SHOPPING_COM_FLAG',2);

function shopping_com_module_info(&$modules)
{
    $modules[] = array('modulename'=>'shopping_com','name'=>'Shopping.com',
                       'flag'=>SHOPPING_COM_FLAG);
}

function shopping_com_add_shopping_fields($db,&$dialog,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">Shopping.com\n");
    add_shopping_flag($dialog,SHOPPING_COM_FLAG,$shopping_flags);
    $dialog->write("</div>\n");
    $dialog->end_row();
    $dialog->start_row('Category:');
    $shopping_cat = get_row_value($row,'shopping_cat');
    $dialog->add_hidden_field('shopping_cat',$shopping_cat);
    $dialog->write('<script type="text/javascript">'."\n" .
                   '  function update_shopping_cat(value,text) {'."\n" .
                   '     var field = document.getElementById(\'' .
                   'shopping_cat_string\');'."\n" .
                   '     field.innerHTML = text;'."\n" .
                   '  }'."\n" .
                   '</script>'."\n");
    $dialog->write("<span id=\"shopping_cat_string\" " .
                   "class=\"select_shopping_input\">");
    if ($shopping_cat) {
       $query = 'select category from shopping_categories where id=?';
       $query = $db->prepare_query($query,$shopping_cat);
       $cat_row = $db->get_record($query);
       if ($cat_row) $dialog->write($cat_row['category']);
    }
    $dialog->write("</span>&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Category\" class=\"select_shopping_" .
                   "button\" onClick=\"select_shopping_field('" .
                   "shopping_cat','Shopping.com Product Category',500);\">\n");
    $dialog->end_row();
    $dialog->start_row('Product Type:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "shopping_type\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'shopping_type'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Type\" class=\"select_shopping_button\" " .
                   "onClick=\"select_shopping_field('shopping_type'," .
                   "'Shopping.com Product Type',350);\">\n");
    $dialog->end_row();
}

function shopping_com_setup_select_field($field,&$field_info)
{
    if (($field != 'shopping_cat') && ($field != 'shopping_type'))
       return;
    if ($field == 'shopping_cat') {
       $field_info['table'] = 'shopping_categories';
       $field_info['id_field'] = 'id';
       $field_info['label_field'] = 'category';
       $field_info['use_listbox'] = false;
       $field_info['label_width'] = 310;
    }
    else {
       $field_info['table'] = 'shopping_product_types';
       $field_info['id_field'] = 'product_type';
       $field_info['label_field'] = 'product_type';
       $field_info['use_listbox'] = false;
       $field_info['label_width'] = 160;
    }
}

function shopping_com_add_product_fields(&$product_record)
{
    $product_record['shopping_cat'] = array('type' => CHAR_TYPE);
    $product_record['shopping_type'] = array('type' => CHAR_TYPE);
}

function shopping_com_update_export_fields(&$query,&$field_defs)
{
    $query .= ',p.shopping_cat,p.shopping_type';
}

?>
