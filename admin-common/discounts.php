<?php
/*
                  Inroads Shopping Cart - Quantity Discount Processing

                          Written 2012-2019 by Randall Severy
                           Copyright 2012-2019 Inroads, LLC
*/

if ($use_discounts) $discount_label = 'Discount';
else $discount_label = 'Price';

function discount_record_definition()
{
    $discount_record = array();
    $discount_record['id'] = array('type' => INT_TYPE);
    $discount_record['id']['key'] = true;
    $discount_record['parent'] = array('type' => INT_TYPE);
    $discount_record['discount_type'] = array('type' => INT_TYPE);
    $discount_record['start_qty'] = array('type' => INT_TYPE);
    $discount_record['end_qty'] = array('type' => INT_TYPE);
    $discount_record['discount'] = array('type' => FLOAT_TYPE);
    return $discount_record;
}

function display_discounts($dialog,$product_id)
{
    global $enable_wholesale,$discount_label,$use_discounts;

    if (! isset($enable_wholesale)) $enable_wholesale = false;
    $dialog->write("<table id=\"fieldtable\" class=\"fieldtable\" " .
                   "border=\"0\" cellpadding=\"4\" cellspacing=\"0\" " .
                   "width=\"100%\">\n");
    $dialog->write('        <tr><td');
    if ($enable_wholesale)
       $dialog->write(" class=\"fieldprompt\" style=\"text-align: left; " .
                      "font-weight: bold;\">Standard ".$discount_label."s:<br>\n");
    else $dialog->write(" colspan=\"2\">\n");
    $dialog->write("          <script>\n");
    if ($use_discounts) {
       $dialog->write("            discount_header = 'Discount (% off)';\n");
       $dialog->write("            discount_label = 'discount';\n");
    }
    else {
       $dialog->write("            discount_header = 'Price';\n");
       $dialog->write("            discount_label = 'price';\n");
    }
    $dialog->write("            create_discounts_grid(0,".$product_id.");\n");
    $dialog->write("          </script>\n");
    if ($enable_wholesale) {
       $dialog->write("        </td><td class=\"fieldprompt\" " .
                      "style=\"text-align: left; font-weight: bold;\">" .
                      "Wholesale ".$discount_label."s:<br>\n");
       $dialog->write("          <script>create_discounts_grid(1,".$product_id .
                      ");</script>\n");
    }
    $dialog->write("        </td></tr>\n");
    $dialog->end_field_table();
}

function add_discount_buttons($dialog,$enabled)
{
    global $enable_wholesale,$discount_label;

    if (! isset($enable_wholesale)) $enable_wholesale = false;
    if ($enable_wholesale) {
       if ($dialog->skin) {
          $dialog->write("<div class=\"filter\" id=\"standard_discount_row\"");
          if (! $enabled) $dialog->write(" style=\"display:none;\"");
          $dialog->write("><span>Standard ".$discount_label."s:</span></div>");
       }
       else {
          $dialog->write("<tr id=\"standard_discount_row\"");
          if (! $enabled) $dialog->write(" style=\"display:none;\"");
          $dialog->write("><td colspan=\"2\" class=\"fieldprompt\" style=\"" .
                         "text-align: left; font-size: 12px; font-weight: bold;\">" .
                         "Standard ".$discount_label."s:</td></tr>\n");
       }
    }
    $dialog->add_button('Add '.$discount_label,'images/AddInventory.png',
                        'add_discount(0);','add_standard_discount',$enabled);
    $dialog->add_button('Delete '.$discount_label,'images/DeleteInventory.png',
                        'delete_discount(0);','delete_standard_discount',
                        $enabled);
    if ($enable_wholesale) {
       $dialog->add_button_separator('discount_row_sep',20);
       if ($dialog->skin) {
          $dialog->write("<div class=\"filter\" id=\"wholesale_discount_row\"");
          if (! $enabled) $dialog->write(" style=\"display:none;\"");
          $dialog->write("><span>Wholesale ".$discount_label."s:</span></div>");
       }
       else {
          $dialog->write("<tr id=\"wholesale_discount_row\"");
          if (! $enabled) $dialog->write(" style=\"display:none;\"");
          $dialog->write("><td colspan=\"2\" class=\"fieldprompt\" style=\"" .
                         "text-align: left; font-size: 12px; font-weight: bold;\">" .
                         "Wholesale ".$discount_label."s:</td></tr>\n");
       }
       $dialog->add_button('Add '.$discount_label,'images/AddInventory.png',
                           'add_discount(1);','add_wholesale_discount',$enabled);
       $dialog->add_button('Delete '.$discount_label,'images/DeleteInventory.png',
                           'delete_discount(1);','delete_wholesale_discount',
                           $enabled);
    }
}

function update_discount()
{
    global $discount_label;

    $db = new DB;
    $discount_record = discount_record_definition();
    $db->parse_form_fields($discount_record);
    if ($discount_record['discount_type']['value'] == 0) $label = 'Standard';
    else $label = 'Wholesale';
    $cmd = get_form_field('Command');
    if ($cmd == 'DeleteRecord') {
       if (! $db->delete('product_discounts',$discount_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,$discount_label.' Deleted');
       log_activity('Deleted Qty '.$label.' '.$discount_label.' ' . 
                    $discount_record['start_qty']['value'] .
                    '-'.$discount_record['end_qty']['value'].' for Product #' .
                    $discount_record['parent']['value']);
    }
    else if (! $discount_record['id']['value']) {
       if (! $db->insert('product_discounts',$discount_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,$discount_label.' Added');
       log_activity('Added Qty '.$label.' '.$discount_label.' ' . 
                    $discount_record['start_qty']['value'] .
                    '-'.$discount_record['end_qty']['value'].' for Product #' .
                    $discount_record['parent']['value']);
    }
    else {
       if (! $db->update('product_discounts',$discount_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,$discount_label.' Updated');
       log_activity('Updated Qty '.$label.' '.$discount_label.' ' .
                    $discount_record['start_qty']['value'] .
                    '-'.$discount_record['end_qty']['value'].' for Product #' .
                    $discount_record['parent']['value']);
    }
}

function copy_discounts($old_parent,$new_parent,$db=null)
{
    global $discount_label;

    if (! $db) $db = new DB;
    $query = 'select * from product_discounts where parent=?';
    $query = $db->prepare_query($query,$old_parent);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return false;
       }
       return true;
    }
    $discount_record = discount_record_definition();
    while ($row = $db->fetch_assoc($result)) {
       $discount_record['parent']['value'] = $new_parent;
       $discount_record['discount_type']['value'] = $row['discount_type'];
       $discount_record['start_qty']['value'] = $row['start_qty'];
       $discount_record['end_qty']['value'] = $row['end_qty'];
       $discount_record['discount']['value'] = $row['discount'];
       if (! $db->insert('product_discounts',$discount_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    $db->free_result($result);
    log_activity('Copied Qty '.$discount_label.'s for Product #'.$old_parent .
                 ' to Product #'.$new_parent);
    return true;
}

function process_discount_command($cmd)
{
    if ($cmd == 'updatediscount') update_discount();
    else return false;
    return true;
}

?>
