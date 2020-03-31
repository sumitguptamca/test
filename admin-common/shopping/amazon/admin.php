<?php
/*
                     Inroads Shopping Cart - Amazon Admin Functions

                         Written 2014-2019 by Randall Severy
                           Copyright 2014-2019 Inroads, LLC
*/

require_once 'amazon-common.php';

function add_amazon_product_fields(&$product_record)
{
    $product_record['amazon_asin'] = array('type' => CHAR_TYPE);
    $product_record['amazon_sku'] = array('type' => CHAR_TYPE);
    $product_record['amazon_type'] = array('type' => CHAR_TYPE);
    $product_record['amazon_item_type'] = array('type' => CHAR_TYPE);
    $product_record['amazon_price'] = array('type' => FLOAT_TYPE);
    $product_record['amazon_fba_flag'] = array('type' => INT_TYPE,
                                               'fieldtype' => CHECKBOX_FIELD);
    $product_record['amazon_downloaded'] = array('type' => INT_TYPE);
    $product_record['amazon_updated'] = array('type' => INT_TYPE);
    $product_record['amazon_error'] = array('type' => CHAR_TYPE);
    $product_record['amazon_warning'] = array('type' => CHAR_TYPE);
}

function add_amazon_price_fields($dialog,$row)
{
    $dialog->add_edit_row('Amazon Price:','amazon_price',$row,10);
}

function add_amazon_shopping_fields($db,$dialog,$row)
{
    global $amazon_category_types;

    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">Amazon\n");
    add_shopping_flag($dialog,AMAZON_FLAG,$shopping_flags);
    $dialog->write('<span class="shopping_flag" style="left: 250px;">');
    $dialog->add_checkbox_field('amazon_fba_flag','Fulfillment By Amazon',
                                $row);
    $dialog->write("</span></div></td></tr>\n");
    $asin = get_row_value($row,'amazon_asin');
    if ($asin)
       $suffix = '&nbsp<a target="_blank" href="https://www.amazon.com/dp/' .
                 $asin.'/">View on Amazon</a>';
    else $suffix = null;
    $dialog->add_edit_row('ASIN:','amazon_asin',$asin,40,null,$suffix);
    $dialog->add_edit_row('Seller SKU:','amazon_sku',$row,40);
    $amazon_type = get_row_value($row,'amazon_type');
    $dialog->start_row('Category Type:','middle');
    $dialog->start_choicelist('amazon_type');
    $dialog->add_list_item('','',! $amazon_type);
    $category_found = false;
    foreach ($amazon_category_types as $category_type => $category_label) {
       if ($category_type == $amazon_type) {
          $selected = true;   $category_found = true;
       }
       else $selected = false;
       $dialog->add_list_item($category_type,$category_label,$selected);
    }
    if ($amazon_type && (! $category_found))
       $dialog->add_list_item($amazon_type,$amazon_type,true);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Item Type:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\"" .
                   " name=\"amazon_item_type\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'amazon_item_type'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Item Type\" class=\"select_shopping_button\" " .
                   "onClick=\"select_shopping_field('amazon_item_type'," .
                   "'Amazon Item Type',1000);\">\n");
    $dialog->end_row();
    $amazon_downloaded = get_row_value($row,'amazon_downloaded');
    if ($amazon_downloaded)
       $dialog->add_text_row('Last Downloaded:',
                             date('F j, Y g:i:s a',$amazon_downloaded),
                             'bottom',true);
    $amazon_updated = get_row_value($row,'amazon_updated');
    if ($amazon_updated && empty($row['amazon_error']))
       $dialog->add_text_row('Last Uploaded:',
                             date('F j, Y g:i:s a',$amazon_updated),
                             'bottom',true);
    $dialog->start_hidden_row('Error:','amazon_error_row',
                              empty($row['amazon_error']),'top');
    $dialog->write('<tt id="amazon_error">');
    if (! empty($row['amazon_error'])) {
       $error = str_replace("\n","<br>\n",$row['amazon_error']);
       $dialog->write($error);
    }
    else $dialog->write('&nbsp;');
    $dialog->write('</tt>');
    $dialog->end_row();
    $dialog->start_hidden_row('Warning:','amazon_warning_row',
                              empty($row['amazon_warning']),'top');
    $dialog->write('<tt id="amazon_warning">');
    if (! empty($row['amazon_warning'])) {
       $warning = str_replace("\n","<br>\n",$row['amazon_warning']);
       $dialog->write($warning);
    }
    else $dialog->write('&nbsp;');
    $dialog->write('</tt>');
    $dialog->end_row();
}

function load_amazon_item_types($dialog,$value)
{
    $db = new DB;
    $query = 'select * from amazon_item_types';
    $rows = $db->get_records($query);
    if (! $rows) return;
    foreach ($rows as $row)
       $dialog->add_list_item($row['item_type'],$row['description'],
                              $value == $row['item_type']);
}

function add_amazon_delete_product($db,$product_row,&$error)
{
    if (! empty($product_row['amazon_sku'])) {
       $query = 'insert into amazon_pending_deletes (part_number) values(?)';
       $query = $db->prepare_query($query,$product_row['amazon_sku']);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = $db->error;   return false;
       }
       return true;
    }
    $product_id = $product_row['id'];
    $query = 'select part_number from product_inventory where parent=?';
    $query = $db->prepare_query($query,$product_id);
    $num_inventory = 0;
    $rows = $db->get_records($query);
    if ($rows) foreach ($rows as $row) {
       $query = 'insert into amazon_pending_deletes (product_id,' .
                'part_number) values(?,?)';
       $query = $db->prepare_query($query,$product_id,$row['part_number']);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = $db->error;   return false;
       }
       $num_inventory++;
    }
    if ($num_inventory == 0) {
       $query = 'insert into amazon_pending_deletes (product_id) values(?)';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = $db->error;   return false;
       }
    }
    return true;
}

function add_amazon_delete_image($db,$product_id,$row,$filename)
{
    $query = 'insert into amazon_pending_image_deletes (product_id,' .
             'amazon_sku,filename) values(?,?,?)';
    $query = $db->prepare_query($query,$product_id,$row['amazon_sku'],
                                $filename);
    $db->log_query($query);
    $db->query($query);
}

function remove_amazon_delete($db,$product_row,&$error)
{
    if (! empty($product_row['amazon_sku'])) {
       $query = 'delete from amazon_pending_deletes where part_number=?';
       $query = $db->prepare_query($query,$product_row['amazon_sku']);
    }
    else {
       $query = 'delete from amazon_pending_deletes where product_id=?';
       $query = $db->prepare_query($query,$product_row['id']);
    }
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    return true;
}

?>
