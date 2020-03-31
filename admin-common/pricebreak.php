<?php
/*
                    Inroads Shopping Cart - Price Break Processing

                           Written 2008-2017 by Randall Severy
                            Copyright 2008-2017 Inroads, LLC
*/

function display_price_breaks($dialog,$form_name,$type,$breaks)
{
    $dialog->add_hidden_field('price_breaks',$breaks);
    $dialog->start_field_table('pricebreaks_table');
    $dialog->start_row('Type:','middle');
    $dialog->add_radio_field('price_break_type','0','Per Item',$type == 0);
    $dialog->add_radio_field('price_break_type','1','Per Quantity',$type == 1);
    $dialog->end_row();
    $dialog->write("        <tr><td colspan=\"2\">\n");
    $dialog->write("          <script>create_price_breaks_grid('".$form_name .
                   "','".$breaks."');</script>\n");
    $dialog->write("        </td></tr>\n");
    $dialog->end_field_table();
}

function add_price_break_buttons($dialog,$enabled=false,$add_separator=false)
{
    if ($add_separator)
       $dialog->add_button_separator('price_buttons_row',20,$enabled);
    $dialog->add_button('Add Price','images/AddInventory.png',
                        'add_price();','add_price',$enabled);
    $dialog->add_button('Delete Price','images/DeleteInventory.png',
                        'delete_price();','delete_price',$enabled);
}

function set_price_breaks()
{
    global $default_price_break_type;

    if (! isset($default_price_break_type)) $default_price_break_type = 0;
    $id = get_form_field('id');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('pricebreak.js');
    $dialog->set_body_id('set_price_breaks');
    $dialog->set_help('set_price_breaks');
    $dialog->start_body('Set Price Breaks');
    $dialog->set_button_width(145);
    $dialog->start_button_column();
    $dialog->add_button('Set Price Breaks','images/AddInventory.png',
                        'apply_price_breaks();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_price_break_buttons($dialog,true,true);
    $dialog->end_button_column();
    $dialog->start_form('categories.php','SetPriceBreaks');
    $dialog->add_hidden_field('id',$id);
    display_price_breaks($dialog,'SetPriceBreaks',
                         $default_price_break_type,'');
    $dialog->end_form();
    $dialog->end_body();
}

function apply_price_breaks()
{
    $id = get_form_field('id');
    $price_break_type = get_form_field('price_break_type');
    $price_breaks = get_form_field('price_breaks');
    $db = new DB;
    $query = 'select related_id from category_products where parent=?';
    $query = $db->prepare_query($query,$id);
    $ids = $db->get_records($query,null,'related_id');
    if (! $ids) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(201,'No Products Found');
       return;
    }
    $query = 'update products set price_break_type=?,price_breaks=? ' .
             'where id in (?)';
    $query = $db->prepare_query($query,$price_break_type,$price_breaks,$ids);
    $db->log_query($query);
    $update_result = $db->query($query);
    if (! $update_result) {
       http_response(422,'Database Error: '.$db->error);   return;
    }
    http_response(201,'Price Breaks Applied');
    log_activity('Applied Price Breaks to Category #'.$id);
}

?>
