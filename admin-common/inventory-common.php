<?php
/*
                 Inroads Shopping Cart - Common Inventory Functions

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC
*/

function using_linked_inventory($db,$features=null)
{
    global $enable_linked_inventory;
    static $using_links = -1;

    if ($using_links != -1) return $using_links;
    if (empty($enable_linked_inventory)) $using_links = false;
    else {
       if ($features === null)
          $features = get_cart_config_value('features',$db);
       if ($features & MAINTAIN_INVENTORY) $using_links = true;
       else $using_links = false;
    }
    return $using_links;
}

function update_linked_inventory($db,$inv_id,$qty,$product_id=null,
                                 $attributes=null)
{
    if (empty($inv_id)) {
       $query = 'select id,qty from product_inventory where (parent=?)';
       if (empty($attributes)) {
          $query .= ' and ((attributes="") or isnull(attributes))';
          $query = $db->prepare_query($query,$product_id);
       }
       else {
          $query .= ' and (attributes=?)';
          $query = $db->prepare_query($query,$product_id,$attributes);
       }
       $row = $db->get_record($query);
       if (! $row) return false;
       $inv_id = $row['id'];
       if ($qty === null) $qty = $row['qty'];
    }
    else if ($qty === null) {
       $query = 'select qty from product_inventory where id=?';
       $query = $db->prepare_query($query,$inv_id);
       $row = $db->get_record($query);
       if (! $row) return false;
       $qty = $row['qty'];
    }

    $query = 'select linked_id from inventory_link where (primary_id=?) ' .
             'union select primary_id from inventory_link where (linked_id=?)';
    $query = $db->prepare_query($query,$inv_id,$inv_id);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) return false;
       return true;
    }

    require_once '../engine/modules.php';
    foreach ($rows as $row) {
       $linked_id = $row['linked_id'];
       if ($linked_id == $inv_id) continue;
       $query = 'update product_inventory set qty=? where id=?';
       $query = $db->prepare_query($query,$qty,$linked_id);
       $db->log_query($query);
       if (! $db->query($query)) return false;

       $query = 'select * from product_inventory where id=?';
       $query = $db->prepare_query($query,$linked_id);
       $inventory_info = $db->get_record($query);
       if (! $inventory_info) return false;
       $product_id = $inventory_info['parent'];
       $attributes = $inventory_info['attributes'];
       $activity = 'Updated Linked Product Inventory';
       if ($attributes) $activity .= ' with Attributes '.$attributes;
       write_product_activity($activity.' by '.get_product_activity_user($db),
                              $product_id,$db);
       if ($attributes)
          log_activity('Updated Linked Product Inventory for Product ID #' .
                       $product_id.' with Attributes '.$attributes);
       else log_activity('Updated Product Inventory for Product ID #' .
                         $product_id);

       if (module_attached('update_inventory')) {
          $product_info = load_product_info($db,$inventory_info['parent']);
          set_product_category_info($db,$product_info);
          update_inventory_records($db,$product_info,$inventory_info);
          if (! call_module_event('update_inventory',
                                  array($db,$product_info,$inventory_info),
                                  null,true)) return false;
       }
    }

    return true;
}

function delete_linked_inventory($db,$inv_id)
{
    $query = 'delete from inventory_link where (primary_id=?) or ' .
             '(linked_id=?)';
    $query = $db->prepare_query($query,$inv_id,$inv_id);
    $db->log_query($query);
    if (! $db->query($query)) return false;
    return true;
}

?>
