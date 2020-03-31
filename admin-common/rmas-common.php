<?php
/*
                 Inroads Shopping Cart - Common RMA Functions

                     Written 2013-2016 by Randall Severy
                     Copyright 2013-2016 Inroads, LLC
*/

function rma_record_definition()
{
    $rma_record = array();
    $rma_record['id'] = array('type' => INT_TYPE);
    $rma_record['id']['key'] = true;
    $rma_record['order_id'] = array('type' => INT_TYPE);
    $rma_record['status'] = array('type' => INT_TYPE);
    $rma_record['request_type'] = array('type' => INT_TYPE);
    $rma_record['restocking_fee'] = array('type' => FLOAT_TYPE);
    $rma_record['refund_amount'] = array('type' => FLOAT_TYPE);
    $rma_record['opened'] = array('type' => INT_TYPE);
    $rma_record['opened']['fieldtype'] = CHECKBOX_FIELD;
    $rma_record['reason'] = array('type' => CHAR_TYPE);
    $rma_record['reason_details'] = array('type' => CHAR_TYPE);
    $rma_record['vendor'] = array('type' => INT_TYPE);
    $rma_record['vendor_rma'] = array('type' => CHAR_TYPE);
    $rma_record['return_address'] = array('type' => CHAR_TYPE);
    $rma_record['email'] = array('type' => CHAR_TYPE);
    $rma_record['fname'] = array('type' => CHAR_TYPE);
    $rma_record['mname'] = array('type' => CHAR_TYPE);
    $rma_record['lname'] = array('type' => CHAR_TYPE);
    $rma_record['company'] = array('type' => CHAR_TYPE);
    $rma_record['address1'] = array('type' => CHAR_TYPE);
    $rma_record['address2'] = array('type' => CHAR_TYPE);
    $rma_record['city'] = array('type' => CHAR_TYPE);
    $rma_record['state'] = array('type' => CHAR_TYPE);
    $rma_record['zipcode'] = array('type' => CHAR_TYPE);
    $rma_record['country'] = array('type' => INT_TYPE);
    $rma_record['address_type'] = array('type' => INT_TYPE);
    $rma_record['phone'] = array('type' => CHAR_TYPE);
    $rma_record['fax'] = array('type' => CHAR_TYPE);
    $rma_record['mobile'] = array('type' => CHAR_TYPE);
    $rma_record['request_date'] = array('type' => INT_TYPE);
    $rma_record['refund_date'] = array('type' => INT_TYPE);
    $rma_record['completed_date'] = array('type' => INT_TYPE);
    $rma_record['comments'] = array('type' => CHAR_TYPE);
    $rma_record['notes'] = array('type' => CHAR_TYPE);
    $rma_record['website'] = array('type' => INT_TYPE);
    $rma_record['flags'] = array('type' => INT_TYPE);
    return $rma_record;
}

function rma_item_record_definition()
{
    $rma_item_record = array();
    $rma_item_record['id'] = array('type' => INT_TYPE);
    $rma_item_record['id']['key'] = true;
    $rma_item_record['parent'] = array('type' => INT_TYPE);
    $rma_item_record['item_id'] = array('type' => INT_TYPE);
    $rma_item_record['qty'] = array('type' => INT_TYPE);
    return $rma_item_record;
}

class RMAInfo {
};

function load_rma(&$db,$id,&$error_msg)
{
    $rma = new RMAInfo();
    $rma->db = $db;
    $query = "select * from rmas where id=".$id;
    $rma->info = $db->get_record($query);
    if (! $rma->info) {
       if (isset($db->error)) $error_msg = $db->error;
       else $error_msg = "RMA not found";
       return null;
    }
    $error_msg = null;
    $rma->id = $id;
    $db->decrypt_record('rmas',$rma->info);

    $query = 'select order_number from orders where id=' .
             $rma->info['order_id'];
    $order_info = $db->get_record($query);
    if ($order_info) $rma->info['order_number'] = $order_info['order_number'];
    else $rma->info['order_number'] = null;

    $query = "select * from order_items where parent=".$rma->info['order_id'] .
             " order by id";
    $rma->order_items = $db->get_records($query,'id');
    if (! $rma->order_items) {
       if (isset($db->error)) $error_msg = $db->error;
       else $error_msg = "Unable to load rma order items";
       return null;
    }
    if (count($rma->order_items) == 0) $rma->order_items = null;

    $query = "select * from rma_items where parent=".$id." order by id";
    $rma->items = $db->get_records($query,'id');
    if (! $rma->items) {
       if (isset($db->error)) {
          $error_msg = $db->error;   return null;
       }
    }
    else if (count($rma->items) == 0) $rma->items = null;

    return $rma;
}

function delete_rma_record($db,$rma_id,&$error,$module=null)
{
    $query = "delete from rma_items where parent=".$rma_id;
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $rma_record = rma_record_definition();
    $rma_record['id']['value'] = $rma_id;
    if (! $db->delete("rmas",$rma_record)) {
       $error = $db->error;   return false;
    }
    return true;
}

?>
