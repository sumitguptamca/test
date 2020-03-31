<?php
/*
                   Inroads Shopping Cart - Template Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

if (file_exists(__DIR__.'/../admin/custom-config.php'))
   require_once __DIR__.'/../admin/custom-config.php';
require_once __DIR__.'/orders-common.php';
require_once __DIR__.'/currency.php';
require_once __DIR__.'/cartconfig-common.php';

function template_scan_cart_variables(&$email,$prefix,$content)
{
    if (strpos($content,'{'.$prefix.':') !== false)
       $email->tables[$prefix] = true;
}

function template_load_cart_table(&$email,$prefix)
{
    if (($prefix == 'customer') && isset($email->tables['customer'])) {
       $query = null;
       if (isset($email->data['customer'])) {
          if ($email->data['customer'] != 'obj') {
             if (! $email->data['customer']) {
                $email->error = 'Customer ID is required for the ' .
                                $email->name.' template';
                return false;
             }
             $query = 'select * from customers where id=?';
             $query = $email->db->prepare_query($query,$email->data['customer']);
          }
       }
       else if (isset($email->data['order']) &&
                ($email->data['order'] == 'obj')) {}
       else if (isset($email->data['rma']) &&
                ($email->data['rma'] == 'obj')) {}
       else {
          $email->error = 'Customer ID is required for the ' .
                          $email->name.' template';
          return false;
       }
       if (isset($email->data['customer']) &&
           ($email->data['customer'] == 'obj')) {
          if (isset($email->data['customer_obj']->info))
             $customer = $email->data['customer_obj']->info;
          else $customer = null;
       }
       else if (isset($email->data['order']) &&
                ($email->data['order'] == 'obj')) {
          if (isset($email->data['order_obj']->info))
             $customer = $email->data['order_obj']->info;
          else {
             $email->error = 'Order Information is required for the ' .
                             $email->name.' template';
             return false;
          }
       }
       else if (isset($email->data['rma']) &&
                ($email->data['rma'] == 'obj')) {
          if (isset($email->data['rma_obj']->info))
             $customer = $email->data['rma_obj']->info;
          else {
             $email->error = 'RMA Information is required for the ' .
                             $email->name.' template';
             return false;
          }
       }
       else $customer = $email->db->get_record($query);
       if (! $customer) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else if ($query)
             $email->error = 'Customer #'.$email->data['customer'] .
                             ' not found for the '.$email->name.' template';
          else $email->error = 'Customer Data not supplied for the ' .
                               $email->name.' template';
          return false;
       }
       if ($query) $email->db->decrypt_record('customers',$customer);
       $email->tables['customer'] = $customer;
    }

    if (($prefix == 'billing') && isset($email->tables['billing'])) {
       $query = null;
       if (isset($email->data['billing'])) {
          $query = 'select * from billing_information where id=?';
          $query = $email->db->prepare_query($query,$email->data['billing']);
       }
       else if (isset($email->data['customer'])) {
          if ($email->data['customer'] != 'obj') {
             $query = 'select * from billing_information where parent=?';
             $query = $email->db->prepare_query($query,$email->data['customer']);
          }
       }
       else if (isset($email->data['order']) &&
                ($email->data['order'] == 'obj')) {}
       else {
          $email->error = 'Billing or Customer ID is required for the ' .
                          $email->name.' template';
          return false;
       }
       if (isset($email->data['customer']) && ($email->data['customer'] == 'obj'))
          $billing = $email->data['customer_obj']->billing;
       else if (isset($email->data['order']) && ($email->data['order'] == 'obj'))
          $billing = $email->data['order_obj']->billing;
       else $billing = $email->db->get_record($query);
       if (! $billing) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Billing Record Not Found';
          return false;
       }
       if ($query) $email->db->decrypt_record('billing_information',$billing);
       $email->tables['billing'] = $billing;
    }

    if (($prefix == 'shipping') && isset($email->tables['shipping'])) {
       $query = null;
       if (isset($email->data['shipping'])) {
          $query = 'select * from shipping_information where id=?';
          $query = $email->db->prepare_query($query,$email->data['shipping']);
       }
       else if (isset($email->data['customer'])) {
          if ($email->data['customer'] != 'obj') {
             $query = 'select * from shipping_information where parent=? ' .
                      'and default_flag=1';
             $query = $email->db->prepare_query($query,$email->data['customer']);
          }
       }
       else if (isset($email->data['order']) &&
                ($email->data['order'] == 'obj')) {}
       else {
          $email->error = 'Billing or Customer ID is required for the ' .
                          $email->name.' template';
          return false;
       }
       if (isset($email->data['customer']) && ($email->data['customer'] == 'obj'))
          $shipping = $email->data['customer_obj']->shipping;
       else if (isset($email->data['order']) && ($email->data['order'] == 'obj'))
          $shipping = $email->data['order_obj']->shipping;
       else $shipping = $email->db->get_record($query);
       if (! $shipping) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Shipping Record Not Found';
          return false;
       }
       if ($query) $email->db->decrypt_record('shipping_information',$shipping);
       $email->tables['shipping'] = $shipping;
    }

    if (($prefix == 'order') && isset($email->tables['order'])) {
       if (! isset($email->data['order'])) {
          $email->error = 'Order ID is required for the '.$email->name.' template';
          return false;
       }
       if ($email->data['order'] == 'obj') $order = $email->data['order_obj'];
       else {
          $query = 'select * from orders where id=?';
          $query = $email->db->prepare_query($query,$email->data['order']);
          $order = $email->db->get_record($query);
          if ($order) $email->db->decrypt_record('orders',$order);
       }
       if (! $order) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Order Record Not Found';
          return false;
       }
       $email->tables['order'] = $order;
       unset($email->tables['order']->curr_item);
    }

    if (($prefix == 'product') && isset($email->tables['product'])) {
       if (! isset($email->data['product'])) {
          $email->error = 'Product ID is required for the '.$email->name.' template';
          return false;
       }
       if ($email->data['product'] == 'obj') $product = $email->data['product_obj'];
       else {
          $query = 'select * from products where id=?';
          $query = $email->db->prepare_query($query,$email->data['product']);
          $product = $email->db->get_record($query);
       }
       if (! $product) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Product Record Not Found';
          return false;
       }
       $email->tables['product'] = $product;
    }

    if (($prefix == 'inventory') && isset($email->tables['inventory'])) {
       if (! isset($email->data['inventory'])) {
          $email->error = 'Inventory ID is required for the ' .
                          $email->name.' template';
          return false;
       }
       if ($email->data['inventory'] == 'obj')
          $inventory = $email->data['inventory_obj'];
       else {
          $query = 'select * from product_inventory where id=?';
          $query = $email->db->prepare_query($query,$email->data['inventory']);
          $inventory = $email->db->get_record($query);
       }
       if (! $inventory) {
          if (isset($email->db->error))
             $email->error = 'Database Error: ' .$email->db->error;
          else $email->error = 'Inventory Record Not Found';
          return false;
       }
       $email->tables['inventory'] = $inventory;
    }

    if (($prefix == 'cartconfig') && isset($email->tables['cartconfig'])) {
       $cartconfig_values = load_cart_config_values($email->db,false);
       if (! $cartconfig_values) {
          if (isset($email->db->error)) {
             $email->error = 'Database Error: ' . $email->db->error;
             return false;
          }
          return true;
       }
       $email->tables['cartconfig'] = $cartconfig_values;
    }

    if (($prefix == 'rma') && isset($email->tables['rma'])) {
       if (! isset($email->data['rma'])) {
          $email->error = 'RMA ID is required for the '.$email->name.' template';
          return false;
       }
       if ($email->data['rma'] == 'obj') $rma = $email->data['rma_obj'];
       else {
          require_once 'rmas-common.php';
          $rma = load_rma($email->db,$email->data['rma'],$error_msg);
          if (! $rma) {
             $email->error = $error_msg;   return false;
          }
       }
       $email->tables['rma'] = $rma;
    }

    if (($prefix == 'review') && isset($email->tables['review'])) {
       if (! isset($email->data['review'])) {
          $email->error = 'Review ID is required for the '.$email->name.' template';
          return false;
       }
       if ($email->data['review'] == 'obj') $review = $email->data['review_obj'];
       else {
          $query = 'select * from reviews where id=?';
          $query = $email->db->prepare_query($query,$email->data['review']);
          $review = $email->db->get_record($query);
       }
       if (! $review) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Review Record Not Found';
          return false;
       }
       $email->tables['review'] = $review;
    }

    if (($prefix == 'vendor') && isset($email->tables['vendor'])) {
       if (! isset($email->data['vendor'])) {
          $email->error = 'Vendor ID is required for the '.$email->name.' template';
          return false;
       }
       if ($email->data['vendor'] == 'obj') $vendor = $email->data['vendor_obj'];
       else {
          $query = 'select * from vendors where id=?';
          $query = $email->db->prepare_query($query,$email->data['vendor']);
          $vendor = $email->db->get_record($query);
       }
       if (! $vendor) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Vendor Record Not Found';
          return false;
       }
       $email->tables['vendor'] = $vendor;
    }

    if (($prefix == 'registry') && isset($email->tables['registry'])) {
       if (! isset($email->data['registry'])) {
          $email->error = 'Registry ID is required for the '.$email->name .
                          ' template';
          return false;
       }
       if ($email->data['registry'] == 'obj')
          $registry = $email->data['registry_obj'];
       else {
          $query = 'select * from registry where id=?';
          $query = $email->db->prepare_query($query,$email->data['registry']);
          $registry = $email->db->get_record($query);
       }
       if (! $registry) {
          if (isset($email->db->error))
             $email->error = 'Database Error: '.$email->db->error;
          else $email->error = 'Registry Record Not Found';
          return false;
       }
       $email->tables['registry'] = $registry;
    }

    return true;
}

function format_order_amount_value($amount,$currency,$use_cents_flag=false)
{
    global $amount_cents_flag;

    if (isset($use_cents_flag) && $use_cents_flag) {
       if (isset($amount_cents_flag) && (! $amount_cents_flag)) $precision = 0;
       else $precision = 2;
    }
    else $precision = 2;
    return format_amount($amount,$currency,null,$precision,true);
}

function get_order_field_value(&$email,$field_name)
{
    $order = $email->tables['order'];
    if (($field_name == 'shipped_date') || ($field_name == 'weight') ||
        (substr($field_name,0,8) == 'shipping') ||
        (substr($field_name,0,8) == 'tracking')) {
       if (! isset($order->shipments))
          $email->tables['order']->shipments = load_order_shipments($order);
       $order = $email->tables['order'];
       if (! empty($order->shipments)) {
          $shipment_info = reset($order->shipments);
          if (isset($shipment_info[$field_name]))
             return $shipment_info[$field_name];
       }
    }
    else if ((substr($field_name,0,7) == 'payment') ||
             (substr($field_name,0,4) == 'card') ||
             (substr($field_name,0,5) == 'check')) {
       if (! isset($order->payments))
          $email->tables['order']->payments = load_order_payments($order);
       $order = $email->tables['order'];
       if (! empty($order->payments)) {
          $payment_info = reset($order->payments);
          if (isset($payment_info[$field_name]))
             return $payment_info[$field_name];
       }
    }
    if (isset($order->info[$field_name])) return $order->info[$field_name];
    return null;
}

function format_order_amount(&$email,$field_name)
{
    $currency = $email->tables['order']->info['currency'];
    $amount = get_order_field_value($email,$field_name);
    if ($amount === null) return '';
    if ((! $amount) &&
        (($field_name == 'subtotal') || ($field_name == 'total')))
       $amount = 0;
    else if ($amount == '') return '';
    return format_order_amount_value($amount,$currency);
}

if (! function_exists('get_text_attributes')) {
   function get_text_attributes($attribute_array,$location,$email,$order_item)
   {
       if (! isset($attribute_array)) return '';
       $num_attributes = count($attribute_array);
       if ($num_attributes == 0) return '';
       $text = '';
       foreach ($attribute_array as $index => $attribute) {
          if ($index > 0) $text .= ', ';
          $text .= $attribute['attr'].': '.$attribute['option'];
          if ($attribute['price'])
             $text .= ' - $'.number_format($attribute['price'],2);
       }
       if ($text != '') $text .= "\n";
       return $text;
   }
}

if (! function_exists('get_text_product_name')) {
   function get_text_product_name($product_name,$location,$email,$order_item)
   {
       return $product_name;
   }
}

function template_lookup_cart_variable(&$email,$prefix,$field_name)
{
    global $part_number_prompt,$rma_status_list,$rma_reasons_list,$base_url;
    global $enable_reorders,$auto_reorder_label,$ssl_url;

    if (! isset($part_number_prompt)) $part_number_prompt = 'Part #';
    if (! isset($auto_reorder_label)) $auto_reorder_label = 'Reorder';
    if (isset($email->template_info['format']))
       $template_format = $email->template_info['format'];
    else $template_format = 1;

    if ($prefix == 'order') {
       if (isset($email->data['order']) && ($email->data['order'] == 'obj')) {
          if ((substr($field_name,0,6) == 'short_') &&
              (substr($field_name,-5) == '_date')) {
             $field_name = substr($field_name,6);
             if ($field_name == 'placed_date') $field_name = 'order_date';
             $date_value = get_order_field_value($email,$field_name);
             if ($date_value === null) $date_value = time();
             $field_value = date('m/d/Y',$date_value);
          }
          else if (substr($field_name,-5) == '_date') {
             if ($field_name == 'placed_date') $field_name = 'order_date';
             $date_value = get_order_field_value($email,$field_name);
             if ($date_value === null) $date_value = time();
             $field_value = date('F j, Y g:i a',$date_value);
          }
          else if ($field_name == 'items') {
             if ((! $email->tables['order']->items) ||
                 (count($email->tables['order']->items) == 0)) return false;
             if (! isset($email->tables['order']->curr_item)) {
                if ($email->tables['order']->features & USE_PART_NUMBERS)
                   load_order_part_numbers($email->tables['order']);
                load_order_item_images($email->tables['order']);
                load_order_item_urls($email->tables['order']);
                $email->tables['order']->curr_item = reset($email->tables['order']->items);
             }
             else $email->tables['order']->curr_item = next($email->tables['order']->items);
             if ($email->tables['order']->curr_item) return true;
             else return false;
          }
          else if (substr($field_name,0,5) == 'item_') {
             if (! isset($email->tables['order']->curr_item)) return null;
             $order_item = $email->tables['order']->curr_item;
             if ($field_name == 'item_product_id')
                $field_value = $order_item['product_id'];
             else if ($field_name == 'item_product_url')
                $field_value = $order_item['url'];
             else if ($field_name == 'item_product_image') {
                if (isset($image_base_url))
                   $field_value = $image_base_url.$order_item['image'];
                else $field_value = $base_url.$order_item['image'];
             }
             else if ($field_name == 'item_product_name') {
                if ((! empty($order_item['product_flags'])) &&
                    ($order_item['product_flags'] & HIDE_NAME_IN_ORDERS))
                   $field_value = '';
                else if ($template_format == HTML_FORMAT)
                   $field_value = get_html_product_name($order_item['product_name'],
                      GET_PROD_EMAIL_HTML,$email->tables['order'],$order_item);
                else $field_value = get_text_product_name($order_item['product_name'],
                                                          GET_PROD_EMAIL_TEXT,$email,
                                                          $order_item);
                $attribute_array = build_attribute_array($email->db,'order',$order_item);
                if ($template_format == HTML_FORMAT)
                   $attributes = get_html_attributes($attribute_array,GET_ATTR_EMAIL_HTML,
                                                     $email->tables['order'],$order_item);
                else $attributes = get_text_attributes($attribute_array,
                                                       GET_ATTR_EMAIL_TEXT,$email,$order_item);
                if ($attributes != '') $field_value .= $attributes;
                if ((! empty($enable_reorders)) &&
                    (! empty($order_item['reorder_frequency']))) {
                   if ($template_format == HTML_FORMAT) $field_value .= '<br>';
                   else $field_value .= ' (';
                   $field_value .= $auto_reorder_label.' Every '.$order_item['reorder_frequency'] .
                                   ' Months';
                   if ($template_format != HTML_FORMAT) $field_value .= ')';
                }
             }
             else if ($field_name == 'item_product_name_only') {
                if ((! empty($order_item['product_flags'])) &&
                    ($order_item['product_flags'] & HIDE_NAME_IN_ORDERS))
                   $field_value = '';
                else if ($template_format == HTML_FORMAT)
                   $field_value = get_html_product_name($order_item['product_name'],
                      GET_PROD_EMAIL_HTML,$email->tables['order'],$order_item);
                else $field_value = get_text_product_name($order_item['product_name'],
                                                          GET_PROD_EMAIL_TEXT,$email,
                                                          $order_item);
             }
             else if ($field_name == 'item_attributes') {
                $attribute_array = build_attribute_array($email->db,'order',$order_item);
                if ($template_format == HTML_FORMAT)
                   $field_value = get_html_attributes($attribute_array,GET_ATTR_EMAIL_HTML,
                                                      $email->tables['order'],$order_item);
                else $field_value = get_text_attributes($attribute_array,GET_ATTR_EMAIL_TEXT,
                                                        $email,$order_item);
             }
             else if ($field_name == 'item_part_number')
                $field_value = $order_item['part_number'];
             else if ($field_name == 'item_price') {
                $currency = $email->tables['order']->info['currency'];
                $unit_price = get_item_total($order_item,false);
                $field_value = format_order_amount_value($unit_price,$currency,true);
             }
             else if ($field_name == 'item_qty') $field_value = $order_item['qty'];
             else if ($field_name == 'item_total') {
                $currency = $email->tables['order']->info['currency'];
                $item_total = get_item_total($order_item);
                $field_value = format_order_amount_value($item_total,$currency,true);
             }
             else if ($field_name == 'item_download_url') {
                if (empty($order_item['product_id'])) $field_value = null;
                else {
                   $product_id = $order_item['product_id'];
                   $query = 'select download_file from products where id=?';
                   $query = $email->db->prepare_query($query,$product_id);
                   $download_row = $email->db->get_record($query);
                   if (empty($download_row['download_file']))
                      $field_value = null;
                   else $field_value = $ssl_url.'cart/downloads.php?id=' .
                                       $product_id;
                }
             }
          }
          else if ($field_name == 'items_text') {
             if ($email->tables['order']->features & USE_PART_NUMBERS)
                load_order_part_numbers($email->tables['order']);
             $field_value = '';
             $currency = $email->tables['order']->info['currency'];
             $index = 0;
             foreach ($email->tables['order']->items as $item_id => $order_item) {
                if ($index == 0) $field_value .= "--------------------------------------------\n";
                $field_value .= 'Product Name: ' .
                                get_text_product_name($order_item['product_name'],
                                                      GET_PROD_EMAIL_TEXT,$email,
                                                      $order_item)."\n";
                $attribute_array = build_attribute_array($email->db,'order',$order_item);
                $attributes = get_text_attributes($attribute_array,GET_ATTR_EMAIL_TEXT,
                                                  $email,$order_item);
                if ($attributes != '') $field_value .= $attributes;
                if ((! empty($enable_reorders)) &&
                    (! empty($order_item['reorder_frequency'])))
                   $field_value .= ' ('.$auto_reorder_label.' Every '.$order_item['reorder_frequency'] .
                                   ' Months)';
                if ($email->tables['order']->features & USE_PART_NUMBERS)
                   $field_value .= $part_number_prompt.': '.$order_item['part_number']."\n";
                $unit_price = get_item_total($order_item,false);
                $field_value .= 'Unit Price: '.format_order_amount_value($unit_price,
                                $currency,true)."\n";
                $field_value .= 'Qty: '.$order_item['qty']."\n";
                $item_total = get_item_total($order_item);
                $field_value .= 'Total: '.format_order_amount_value($item_total,
                                $currency,true)."\n";
                $field_value .= "--------------------------------------------\n";
                $index++;
             }
          }
          else if (($field_name == 'items_html') ||
                   ($field_name == 'items_html_with_mpn')) { 
             if ($email->tables['order']->features & USE_PART_NUMBERS)
                load_order_part_numbers($email->tables['order']);
             if ($field_name == 'items_html_with_mpn')
                load_order_mpns($email->tables['order']);
             $currency = $email->tables['order']->info['currency'];
             $field_value = "<table cellspacing=\"0\" cellpadding=\"0\" " .
                            "class=\"cart_font items_html\">\n";
             $field_value .= "<tr class=\"items_html_header\"><th nowrap " .
                             "align=\"left\" class=\"cart_header\">" .
                             "Product Name</th>";
             $field_value .= "<th class=\"cart_header\">Attributes</th>";
             if ($email->tables['order']->features & USE_PART_NUMBERS)
                $field_value .= "<th class=\"cart_header\">" .
                                $part_number_prompt.'</th>';
             if ($field_name == 'items_html_with_mpn')
                $field_value .= "<th class=\"cart_header\">MPN</th>";
             $field_value .= "<th align=\"center\" class=\"cart_header\" " .
                             "width=\"75px\">Unit Price</th>" .
                             "<th align=\"center\" class=\"cart_header\" " .
                             "width=\"30px;\">Qty</th>" .
                             "<th align=\"right\" class=\"cart_header\" " .
                             "width=\"75px\">Total</th></tr>\n";
             foreach ($email->tables['order']->items as $id => $order_item) {
                $field_value .= "<tr valign=\"top\" class=\"items_html_data\">";
                $field_value .= '<td>'.get_html_product_name($order_item['product_name'],
                   GET_PROD_EMAIL_HTML,$email->tables['order'],$order_item).'</td><td>';
                $attribute_array = build_attribute_array($email->db,'order',$order_item);
                $field_value .= get_html_attributes($attribute_array,GET_ATTR_EMAIL_HTML,
                                                    $email->tables['order'],$order_item);
                if ((! empty($enable_reorders)) &&
                    (! empty($order_item['reorder_frequency'])))
                   $field_value .= '<br>'.$auto_reorder_label.' Every '.$order_item['reorder_frequency'] .
                                   ' Months';
                if ($email->tables['order']->features & USE_PART_NUMBERS)
                   $field_value .= '</td><td>'.$order_item['part_number'];
                if ($field_name == 'items_html_with_mpn')
                   $field_value .= '</td><td>'.$order_item['mpn'];
                $unit_price = get_item_total($order_item,false);
                $field_value .= "</td><td align=\"right\">" .
                                format_order_amount_value($unit_price,
                                                          $currency,true)."</td>\n";
                $field_value .= "<td align=\"center\">".$order_item['qty'].'</td>';
                $item_total = get_item_total($order_item);
                $field_value .= "<td align=\"right\">".format_order_amount_value($item_total,
                                $currency,true)."</td></tr>\n";
             }
             $field_value .= '</table>';
          }
          else if (($field_name == 'subtotal') || ($field_name == 'tax') ||
                   ($field_name == 'coupon_amount') ||
                   ($field_name == 'total') ||
                   ($field_name == 'gift amount') ||
                   ($field_name == 'balance_due') ||
                   ($field_name == 'shipping') ||
                   ($field_name == 'payment_amount'))
             $field_value = format_order_amount($email,$field_name);
          else if ((substr($field_name,0,8) == 'shipping') ||
                   (substr($field_name,0,8) == 'tracking')) {
             $shipping_carrier = get_order_field_value($email,
                                                       'shipping_carrier');
             if ($shipping_carrier) {
                if ($field_name == 'trackinglink') {
                   $tracking = get_order_field_value($email,'tracking');
                   if ($tracking &&
                       shipping_module_event_exists('get_tracking_url',
                                                    $shipping_carrier)) {
                      $get_tracking_url = $shipping_carrier.'_get_tracking_url';
                      $field_value = '<a href="'.$get_tracking_url($tracking) .
                                     '" target="_blank">'.$tracking.'</a>';
                   }
                   else if ($tracking) $field_value = $tracking;
                   else $field_value = null;
                }
                else if (($field_name == 'shipping_carrier') ||
                         ($field_name == 'shipping_method')) {
                   if (shipping_module_event_exists('format_shipping_field',
                                                    $shipping_carrier)) {
                      $format_shipping_field = $shipping_carrier .
                                               '_format_shipping_field';
                      $order = $email->tables['order'];
                      if (! empty($order->shipments))
                         $shipment_info = reset($order->shipments);
                      else $shipment_info = $order->info;
                      $field_value = $format_shipping_field($shipment_info,
                                                            $field_name);
                   }
                   else $field_value = null;
                }
                else $field_value = get_order_field_value($email,$field_name);
             }
             else if ($field_name == 'trackinglink')
                $field_value = get_order_field_value($email,'tracking');
             else $field_value = get_order_field_value($email,$field_name);
          }
          else if ($field_name == 'status') {
             $status = $email->tables['order']->info['status'];
             $status_values = load_cart_options(ORDER_STATUS,$email->db);
             if (isset($status_values[$status]))
                $field_value = $status_values[$status];
             else $field_value = $status;
          }
          else if ($field_name == 'partial_ship') {
             $partial_ship = $email->tables['order']->info['partial_ship'];
             if ($partial_ship) $field_value = 'Yes';
             else $field_value = 'No';
          }
          else if ($field_name == 'downloads') {
             $order_id = $email->tables['order']->info['id'];
             $query = 'select count(i.id) as num_items from order_items i ' .
                      'join products p on p.id=i.product_id where (i.parent=?) ' .
                      'and (not isnull(p.download_file))';
             $query = $email->db->prepare_query($query,$order_id);
             $row = $email->db->get_record($query);
             if (! empty($row['num_items'])) $field_value = $row['num_items'];
             else $field_value = null;
          }
          else if (($field_name == 'account_id') ||
                   ($field_name == 'account_name')) {
             $customer_id = $email->tables['order']->info['customer_id'];
             if ($customer_id) {
                $query = 'select account_id from customers where id=?';
                $query = $email->db->prepare_query($query,$customer_id);
                $customer_info = $email->db->get_record($query);
                if (! empty($customer_info['account_id'])) {
                   if ($field_name == 'account_id')
                      $field_value = $customer_info['account_id'];
                   else {
                      $query = 'select name from accounts where id=?';
                      $query = $email->db->prepare_query($query,
                                  $customer_info['account_id']);
                      $account_info = $email->db->get_record($query);
                      if (! empty($account_info['name']))
                         $field_value = $account_info['name'];
                      else $field_value = null;
                   }
                }
                else $field_value = null;
             }
             else $field_value = null;
          }
          else if (isset($email->tables['order']->info[$field_name]))
             $field_value = $email->tables['order']->info[$field_name];
          else $field_value = null;
       }
       else $field_value = null;
    }
    else if ($prefix == 'inventory') {
       if ($field_name == 'attributes') {
          $attributes = $email->tables['inventory']['attributes'];
          if (strpos($attributes,'-') !== false)
             $attributes = explode('-',$attributes);
          else $attributes = explode('|',$attributes);
          $lookup_attributes = array();
          foreach ($attributes as $option) {
             if (is_numeric($option)) $lookup_attributes[] = $option;
          }
          if (count($lookup_attributes) > 0) {
             $query = 'select o.id,a.name,o.name as option_name from ' .
                      'attributes a join attribute_options o on o.parent=' .
                      'a.id where o.id in (?)';
             $query = $email->db->prepare_query($query,$lookup_attributes);
             $options = $email->db->get_records($query,'id');
             if (! $options) $options = array();
          }
          else $options = array();
          $field_value = '';
          foreach ($attributes as $option) {
             if ($field_value) $field_value .= ', ';
             if (isset($options[$option]))
                $field_value .= $options[$option]['name'].': ' .
                                $options[$option]['option_name'];
             else $field_value .= $option;
          }
       }
       else if (isset($email->tables['inventory'][$field_name]))
          $field_value = $email->tables['inventory'][$field_name];
       else $field_value = null;
    }
    else if ($prefix == 'customer') {
       if (isset($email->data['customer'])) {
          if ($field_name == 'encoded_email')
             $field_value = urlencode($email->tables[$prefix]['email']);
          else if ($field_name == 'encoded_password')
             $field_value = urlencode($email->tables[$prefix]['password']);
          else if ($field_name == 'crc_password')
             $field_value = crc32($email->tables[$prefix]['password']);
          else if ($field_name == 'create_date') {
             if (isset($email->tables[$prefix][$field_name]))
                $date_value = $email->tables[$prefix][$field_name];
             else $date_value = time();
             $field_value = date('F j, Y g:i a',$date_value);
          }
          else if (isset($email->tables[$prefix][$field_name]))
             $field_value = $email->tables[$prefix][$field_name];
          else if (isset($email->data[$field_name]))
             $field_value = $email->data[$field_name];
          else $field_value = null;
       }
       else if ($field_name == 'create_date')
          $field_value = date('F j, Y g:i a',
                              $email->tables[$prefix][$field_name]);
       else if (isset($email->tables[$prefix][$field_name]))
          $field_value = $email->tables[$prefix][$field_name];
       else if (isset($email->data[$field_name]))
          $field_value = $email->data[$field_name];
       else $field_value = null;
    }
    else if ($prefix == 'billing') {
       if ($field_name == 'country')
          $field_value = get_country_name($email->tables[$prefix]['country'],$email->db);
       else $field_value = null;
    }
    else if ($prefix == 'shipping') {
       if (($field_name == 'shipto') &&
           ((! isset($email->tables[$prefix]['shipto'])) ||
            (! $email->tables[$prefix]['shipto']))) {
          $fname = $email->tables['order']->info['fname'];
          $mname = $email->tables['order']->info['mname'];
          $lname = $email->tables['order']->info['lname'];
          $field_value = $fname;
          if ($mname) {
             if ($field_value) $field_value .= ' ';
             $field_value .= $mname;
          }
          if ($lname) {
             if ($field_value) $field_value .= ' ';
             $field_value .= $lname;
          }
       }
       else if ($field_name == 'country')
          $field_value = get_country_name($email->tables[$prefix]['country'],
                                          $email->db);
       else $field_value = null;
    }
    else if ($prefix == 'product') {
       if ($field_name == 'image') {
          require_once 'shopping-common.php';
          $field_value = build_image_url($email->db,$email->tables['product'],
                                         'medium');
       }
       else if ($field_name == 'url') {
          require_once 'shopping-common.php';
          $field_value = build_product_url($email->db,$email->tables['product']);
       }
       else if (isset($email->tables['product'][$field_name]))
          $field_value = $email->tables['product'][$field_name];
       else $field_value = '';
    }
    else if ($prefix == 'cartconfig') {
       if (isset($email->tables['cartconfig'][$field_name]))
          $field_value = $email->tables['cartconfig'][$field_name];
       else $field_value = '';
    }
    else if ($prefix == 'sage') {
       if (isset($email->data[$field_name]))
          $field_value = $email->data[$field_name];
       else $field_value = '';
    }
    else if ($prefix == 'rma') {
       if (isset($email->data['rma']) && ($email->data['rma'] == 'obj')) {
          if (($field_name == 'request_date') ||
              ($field_name == 'completed_date')) {
             if (isset($email->tables[$prefix]->info[$field_name]))
                $date_value = $email->tables[$prefix]->info[$field_name];
             else $date_value = time();
             $field_value = date('F j, Y g:i a',$date_value);
          }
          else if ($field_name == 'status') {
             $status = $email->tables['rma']->info['status'];
             if (! isset($rma_status_list)) $rma_status_list = RMA_STATUS;
             $status_values = load_cart_options($rma_status_list,$email->db);
             if (isset($status_values[$status]))
                $field_value = $status_values[$status];
             else $field_value = $status;
          }
          else if ($field_name == 'request_type') {
             if (isset($email->tables['rma']->info[$field_name])) {
                if ($email->tables['rma']->info[$field_name] == 1)
                   $field_value = 'Refund';
                else $field_value = 'Replace';
             }
             else $field_value = 'Unknown';
          }
          else if ($field_name == 'reason') {
             $reason = $email->tables['rma']->info['reason'];
             if (! isset($rma_reasons_list)) $rma_reasons_list = RMA_REASONS;
             $reason_values = load_cart_options($rma_reasons_list,$email->db);
             if (isset($reason_values[$reason]))
                $field_value = $reason_values[$reason];
             else $field_value = $reason;
          }
          else if ($field_name == 'opened') {
             if (isset($email->tables['rma']->info[$field_name])) {
                if ($email->tables['rma']->info[$field_name] == 1)
                   $field_value = 'Yes';
                else $field_value = 'No';
             }
             else $field_value = 'Unknown';
          }
          else if ($field_name == 'vendor') {
             $vendor = $email->tables['rma']->info['vendor'];
             if ($vendor) {
                $query = 'select name from vendors where id=?';
                $query = $email->db->prepare_query($query,$vendor);
                $vendor_info = $email->db->get_record($query);
                if ($vendor_info) $field_value = $vendor_info['name'];
                else $field_value = '';
             }
             else $field_value = '';
          }
          else if (($field_name == 'return_address') ||
                   ($field_name == 'return_address_html')) {
             $field_value = $email->tables['rma']->info['return_address'];
             if ($field_name == 'return_address_html')
                $field_value = str_replace("\n",'<br>',$field_value);
          }
          else if ($field_name == 'items_text') {
             $field_value = '';
             $index = 0;
             foreach ($email->tables['rma']->order_items as $item_id => $order_item) {
                $return_flag = false;
                if ($email->tables['rma']->items) {
                   foreach ($email->tables['rma']->items as $rma_item) {
                      if ($rma_item['item_id'] == $item_id) {
                         $return_flag = true;   break;
                      }
                   }
                }
                if (! $return_flag) continue;
                if ($index == 0)
                   $field_value .= "--------------------------------------------\n";
                $field_value .= 'Product Name: '.$order_item['product_name']."\n";
                $field_value .= 'Unit Price: $'.number_format($order_item['price'],2)."\n";
                $field_value .= 'Qty: '.$order_item['qty']."\n";
                $field_value .= "--------------------------------------------\n";
                $index++;
             }
          }
          else if ($field_name == 'items_html') {
             $field_value = "<table cellspacing=\"0\" cellpadding=\"1\" " .
                            "class=\"cart_font\">\n";
             $field_value .= "<tr><th nowrap align=\"left\" class=\"" .
                             "cart_header\">Product Name</th>";
             $field_value .= "<th align=\"right\" class=\"cart_header\" " .
                             "width=\"75px\">Unit Price</th>" .
                             "<th align=\"center\" class=\"cart_header\" " .
                             "width=\"30px;\">Qty</th></tr>\n";
             foreach ($email->tables['rma']->order_items as $item_id => $order_item) {
                $return_flag = false;
                if ($email->tables['rma']->items) {
                   foreach ($email->tables['rma']->items as $rma_item) {
                      if ($rma_item['item_id'] == $item_id) {
                         $return_flag = true;   break;
                      }
                   }
                }
                if (! $return_flag) continue;
                $field_value .= "<tr valign=\"top\">";
                $field_value .= '<td>'.$order_item['product_name'] .
                                "</td><td align=\"right\">$" .
                                number_format($order_item['price'],2)."</td>\n";
                $field_value .= "<td align=\"center\">".$order_item['qty'] .
                                "</td></tr>\n";
             }
             $field_value .= '</table>';
          }
          else if (isset($email->tables['rma']->info[$field_name]))
             $field_value = $email->tables['rma']->info[$field_name];
          else $field_value = null;
       }
       else $field_value = null;
    }
    else if ($prefix == 'review') {
       if ($field_name == 'create_date') {
          if (isset($email->tables['review'][$field_name]))
             $date_value = $email->tables['review'][$field_name];
          else $date_value = time();
          $field_value = date('F j, Y g:i a',$date_value);
       }
       else if (isset($email->tables['review'][$field_name]))
          $field_value = $email->tables['review'][$field_name];
       else $field_value = '';
    }
    else if ($prefix == 'vendor') {
       if (isset($email->tables['vendor'][$field_name]))
          $field_value = $email->tables['vendor'][$field_name];
       else $field_value = '';
    }
    else if ($prefix == 'registry') {
       if (isset($email->data['registry']) &&
           ($email->data['registry'] == 'obj')) {
          if ($field_name == 'event_date') {
             if (isset($email->tables[$prefix][$field_name]))
                $date_value = $email->tables[$prefix][$field_name];
             else $date_value = time();
             $field_value = date('F j, Y g:i a',$date_value);
          }
          else if ($field_name == 'items_text') {
             $field_value = '';
             $index = 0;
             foreach ($email->tables['rma']->order_items as $item_id => $order_item) {
                $return_flag = false;
                if ($email->tables['rma']->items) {
                   foreach ($email->tables['rma']->items as $rma_item) {
                      if ($rma_item['item_id'] == $item_id) {
                         $return_flag = true;   break;
                      }
                   }
                }
                if (! $return_flag) continue;
                if ($index == 0)
                   $field_value .= "--------------------------------------------\n";
                $field_value .= 'Product Name: '.$order_item['product_name']."\n";
                $field_value .= 'Unit Price: $'.number_format($order_item['price'],2)."\n";
                $field_value .= 'Qty: '.$order_item['qty']."\n";
                $field_value .= "--------------------------------------------\n";
                $index++;
             }
          }
          else if ($field_name == 'items_html') {
             $field_value = "<table cellspacing=\"0\" cellpadding=\"1\" " .
                            "class=\"cart_font\">\n";
             $field_value .= "<tr><th nowrap align=\"left\" class=\"" .
                             "cart_header\">Product Name</th>";
             $field_value .= "<th align=\"right\" class=\"cart_header\" " .
                             "width=\"75px\">Unit Price</th>" .
                             "<th align=\"center\" class=\"cart_header\" " .
                             "width=\"30px;\">Qty</th></tr>\n";
             foreach ($email->tables['rma']->order_items as $item_id => $order_item) {
                $return_flag = false;
                if ($email->tables['rma']->items) {
                   foreach ($email->tables['rma']->items as $rma_item) {
                      if ($rma_item['item_id'] == $item_id) {
                         $return_flag = true;   break;
                      }
                   }
                }
                if (! $return_flag) continue;
                $field_value .= "<tr valign=\"top\">";
                $field_value .= '<td>'.$order_item['product_name'] .
                                "</td><td align=\"right\">$" .
                                number_format($order_item['price'],2)."</td>\n";
                $field_value .= "<td align=\"center\">".$order_item['qty'] .
                                "</td></tr>\n";
             }
             $field_value .= '</table>';
          }
          else if (isset($email->tables['registry'][$field_name]))
             $field_value = $email->tables['registry'][$field_name];
          else $field_value = null;
       }
       else $field_value = null;
    }
    else if ($prefix == 'certificate') {
       if (isset($email->data[$field_name]))
          $field_value = $email->data[$field_name];
       else $field_value = '';
    }
    else if ((require_once 'shopping-common.php') &&
             call_shopping_event('lookup_template_variable',
                array(&$email,$prefix,$field_name,&$field_value),
                true,true)) {}
    else $field_value = null;

    return $field_value;
}

?>
