<?php
/*
                 Inroads Shopping Cart - Common Order Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

/* Order Types */

define('ORDER_TYPE',0);
define('QUOTE_TYPE',1);
define('INVOICE_TYPE',2);
define('SALESORDER_TYPE',3);

/* Order and Cart Item Flags */

define('QTY_PRICE',1);
define('SALE_PRICE',2);
define('ON_ACCOUNT_ITEM',4);

/* Location values for get_html_attributes and get_text_attributes */

define('GET_ATTR_CART',1);
define('GET_ATTR_CHECKOUT',2);
define('GET_ATTR_CUST_VIEW_ORDER',3);
define('GET_ATTR_ADMIN_VIEW_ORDER',4);
define('GET_ATTR_EMAIL_HTML',5);
define('GET_ATTR_REPORT',6);
define('GET_ATTR_EMAIL_TEXT',1);

/* Location values for get_html_product_name and get_text_product_name */

define('GET_PROD_CART',1);
define('GET_PROD_CHECKOUT',2);
define('GET_PROD_CUST_VIEW_ORDER',3);
define('GET_PROD_ADMIN_VIEW_ORDER',4);
define('GET_PROD_EMAIL_HTML',5);
define('GET_PROD_REPORT',6);
define('GET_PROD_ADMIN',7);
define('GET_PROD_PAYMENT_GATEWAY',8);
define('GET_PROD_EMAIL_TEXT',1);

/* Payment Status Values */

define('PAYMENT_NONE',0);
define('PAYMENT_AUTHORIZED',1);
define('PAYMENT_VOIDED',2);
define('PAYMENT_CAPTURED',3);
define('PAYMENT_REFUNDED',4);

global $include_purchase_order,$enable_partial_shipments;
global $disable_partial_ship_option,$payment_status_values,$order_type;
global $orders_table,$order_label;

if (! isset($include_purchase_order)) $include_purchase_order = false;
if (! isset($enable_partial_shipments)) $enable_partial_shipments = false;
if ($enable_partial_shipments && (! isset($disable_partial_ship_option)))
   $disable_partial_ship_option = false;
$payment_status_values = array('None','Authorized','Voided','Captured',
                               'Refunded');
if (! isset($order_type)) $order_type = ORDER_TYPE;
if (! isset($orders_table)) $orders_table = 'orders';
if (! isset($order_label)) $order_label = 'Order';

function cart_record_definition()
{
    $cart_record = array();
    $cart_record['id'] = array('type' => INT_TYPE,'key' => true);
    $cart_record['customer_id'] = array('type' => INT_TYPE);
    $cart_record['create_date'] = array('type' => INT_TYPE);
    $cart_record['ip_address'] = array('type' => CHAR_TYPE);
    $cart_record['user_agent'] = array('type' => CHAR_TYPE);
    $cart_record['device'] = array('type' => INT_TYPE);
    $cart_record['email'] = array('type' => CHAR_TYPE);
    $cart_record['currency'] = array('type' => CHAR_TYPE);
    $cart_record['coupon_code'] = array('type' => CHAR_TYPE);
    $cart_record['gift_code'] = array('type' => CHAR_TYPE);
    $cart_record['discount_name'] = array('type' => CHAR_TYPE);
    $cart_record['discount_amount'] = array('type' => FLOAT_TYPE);
    $cart_record['shipping_method'] = array('type' => CHAR_TYPE);
    $cart_record['shipping'] = array('type' => FLOAT_TYPE);
    $cart_record['reorder_id'] = array('type' => INT_TYPE);
    $cart_record['comments'] = array('type' => CHAR_TYPE);
    $cart_record['registry_id'] = array('type' => INT_TYPE);
    $cart_record['gift_message'] = array('type' => CHAR_TYPE);
    $cart_record['cart_data'] = array('type' => CHAR_TYPE);
    $cart_record['payment_data'] = array('type' => CHAR_TYPE);
    $cart_record['website'] = array('type' => INT_TYPE);
    $cart_record['flags'] = array('type' => INT_TYPE);
    if (function_exists('custom_cart_fields'))
       custom_cart_fields($cart_record);
    return $cart_record;
}

function item_record_definition()
{
    global $item_fields;

    $item_record = array();
    $item_record['id'] = array('type' => INT_TYPE,'key' => true);
    $item_record['parent_type'] = array('type' => INT_TYPE);
    $item_record['parent'] = array('type' => INT_TYPE);
    $item_record['product_id'] = array('type' => INT_TYPE);
    $item_record['product_name'] = array('type' => CHAR_TYPE);
    $item_record['attributes'] = array('type' => CHAR_TYPE);
    $item_record['attribute_names'] = array('type' => CHAR_TYPE);
    $item_record['attribute_prices'] = array('type' => CHAR_TYPE);
    $item_record['part_number'] = array('type' => CHAR_TYPE);
    $item_record['qty'] = array('type' => FLOAT_TYPE);
    $item_record['price'] = array('type' => FLOAT_TYPE);
    $item_record['cost'] = array('type' => FLOAT_TYPE);
    $item_record['registry_item'] = array('type' => INT_TYPE);
    $item_record['related_id'] = array('type' => INT_TYPE);
    $item_record['reorder_frequency'] = array('type' => INT_TYPE);
    $item_record['reorder_qty'] = array('type' => INT_TYPE);
    $item_record['reorder_date'] = array('type' => INT_TYPE);
    $item_record['account_id'] = array('type' => INT_TYPE);
    $item_record['website'] = array('type' => INT_TYPE);
    $item_record['flags'] = array('type' => INT_TYPE);
    if (isset($item_fields)) {
       foreach ($item_fields as $field_name => $field) {
          if ($field['datatype']) {
             $item_record[$field_name] = array('type' => $field['datatype']);
             if (isset($field['fieldtype']) &&
                 ($field['fieldtype'] == CHECKBOX_FIELD))
                $item_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
             else if (isset($field['datafieldtype']) &&
                 ($field['datafieldtype'] == CHECKBOX_FIELD))
                $item_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
          }
       }
    }
    return $item_record;
}

function orders_record_definition()
{
    $orders_record = array();
    $orders_record['id'] = array('type' => INT_TYPE,'key' => true);
    $orders_record['status'] = array('type' => INT_TYPE);
    $orders_record['order_number'] = array('type' => CHAR_TYPE);
    $orders_record['order_date'] = array('type' => INT_TYPE);
    $orders_record['updated_date'] = array('type' => INT_TYPE);
    $orders_record['quote_id'] = array('type' => INT_TYPE);
    $orders_record['reorder_id'] = array('type' => INT_TYPE);
    $orders_record['external_source'] = array('type' => CHAR_TYPE);
    $orders_record['external_id'] = array('type' => CHAR_TYPE);
    $orders_record['customer_id'] = array('type' => INT_TYPE);
    $orders_record['ip_address'] = array('type' => CHAR_TYPE);
    $orders_record['user_agent'] = array('type' => CHAR_TYPE);
    $orders_record['device'] = array('type' => INT_TYPE);
    $orders_record['email'] = array('type' => CHAR_TYPE);
    $orders_record['fname'] = array('type' => CHAR_TYPE);
    $orders_record['mname'] = array('type' => CHAR_TYPE);
    $orders_record['lname'] = array('type' => CHAR_TYPE);
    $orders_record['company'] = array('type' => CHAR_TYPE);
    $orders_record['currency'] = array('type' => CHAR_TYPE);
    $orders_record['sales_rep'] = array('type' => CHAR_TYPE);
    $orders_record['subtotal'] = array('type' => FLOAT_TYPE);
    $orders_record['tax'] = array('type' => FLOAT_TYPE);
    $orders_record['tax_zone'] = array('type' => CHAR_TYPE);
    $orders_record['tax_rate'] = array('type' => FLOAT_TYPE);
    $orders_record['shipping'] = array('type' => FLOAT_TYPE);
    $orders_record['coupon_id'] = array('type' => INT_TYPE);
    $orders_record['coupon_amount'] = array('type' => FLOAT_TYPE);
    $orders_record['gift_id'] = array('type' => INT_TYPE);
    $orders_record['gift_amount'] = array('type' => FLOAT_TYPE);
    $orders_record['fee_name'] = array('type' => CHAR_TYPE);
    $orders_record['fee_amount'] = array('type' => FLOAT_TYPE);
    $orders_record['discount_name'] = array('type' => CHAR_TYPE);
    $orders_record['discount_amount'] = array('type' => FLOAT_TYPE);
    $orders_record['total'] = array('type' => FLOAT_TYPE);
    $orders_record['balance_due'] = array('type' => FLOAT_TYPE);
    $orders_record['purchase_order'] = array('type' => CHAR_TYPE);
    $orders_record['shipping_carrier'] = array('type' => CHAR_TYPE);
    $orders_record['shipping_method'] = array('type' => CHAR_TYPE);
    $orders_record['shipping_flags'] = array('type' => INT_TYPE);
    $orders_record['weight'] = array('type' => FLOAT_TYPE);
    $orders_record['comments'] = array('type' => CHAR_TYPE);
    $orders_record['notes'] = array('type' => CHAR_TYPE);
    $orders_record['terms'] = array('type' => CHAR_TYPE);
    $orders_record['registry_id'] = array('type' => INT_TYPE);
    $orders_record['gift_message'] = array('type' => CHAR_TYPE);
    $orders_record['website'] = array('type' => INT_TYPE);
    $orders_record['mailing'] = array('type' => INT_TYPE);
    $orders_record['mailing']['fieldtype'] = CHECKBOX_FIELD;
    $orders_record['partial_ship'] = array('type' => INT_TYPE);
    $orders_record['partial_ship']['fieldtype'] = CHECKBOX_FIELD;
    $orders_record['phone_order'] = array('type' => INT_TYPE);
    $orders_record['phone_order']['fieldtype'] = CHECKBOX_FIELD;
    $orders_record['payment_data'] = array('type' => CHAR_TYPE);
    $orders_record['flags'] = array('type' => INT_TYPE);
    if (function_exists('custom_order_fields'))
       custom_order_fields($orders_record);
    return $orders_record;
}

function quotes_record_definition()
{
    $quotes_record = array();
    $quotes_record['id'] = array('type' => INT_TYPE,'key' => true);
    $quotes_record['status'] = array('type' => INT_TYPE);
    $quotes_record['quote_date'] = array('type' => INT_TYPE);
    $quotes_record['updated_date'] = array('type' => INT_TYPE);
    $quotes_record['order_id'] = array('type' => INT_TYPE);
    $quotes_record['customer_id'] = array('type' => INT_TYPE);
    $quotes_record['email'] = array('type' => CHAR_TYPE);
    $quotes_record['fname'] = array('type' => CHAR_TYPE);
    $quotes_record['mname'] = array('type' => CHAR_TYPE);
    $quotes_record['lname'] = array('type' => CHAR_TYPE);
    $quotes_record['company'] = array('type' => CHAR_TYPE);
    $quotes_record['currency'] = array('type' => CHAR_TYPE);
    $quotes_record['subtotal'] = array('type' => FLOAT_TYPE);
    $quotes_record['tax'] = array('type' => FLOAT_TYPE);
    $quotes_record['tax_zone'] = array('type' => CHAR_TYPE);
    $quotes_record['tax_rate'] = array('type' => FLOAT_TYPE);
    $quotes_record['shipping'] = array('type' => FLOAT_TYPE);
    $quotes_record['coupon_id'] = array('type' => INT_TYPE);
    $quotes_record['coupon_amount'] = array('type' => FLOAT_TYPE);
    $quotes_record['gift_id'] = array('type' => INT_TYPE);
    $quotes_record['gift_amount'] = array('type' => FLOAT_TYPE);
    $quotes_record['fee_name'] = array('type' => CHAR_TYPE);
    $quotes_record['fee_amount'] = array('type' => FLOAT_TYPE);
    $quotes_record['discount_name'] = array('type' => CHAR_TYPE);
    $quotes_record['discount_amount'] = array('type' => FLOAT_TYPE);
    $quotes_record['total'] = array('type' => FLOAT_TYPE);
    $quotes_record['purchase_order'] = array('type' => CHAR_TYPE);
    $quotes_record['shipping_carrier'] = array('type' => CHAR_TYPE);
    $quotes_record['shipping_method'] = array('type' => CHAR_TYPE);
    $quotes_record['comments'] = array('type' => CHAR_TYPE);
    $quotes_record['notes'] = array('type' => CHAR_TYPE);
    $quotes_record['terms'] = array('type' => CHAR_TYPE);
    $quotes_record['website'] = array('type' => INT_TYPE);
    $quotes_record['flags'] = array('type' => INT_TYPE);
    if (function_exists('custom_quote_fields'))
       custom_quote_fields($quotes_record);
    return $quotes_record;
}

function salesorders_record_definition()
{
    $salesorders_record = array();
    $salesorders_record['id'] = array('type' => INT_TYPE,'key' => true);
    $salesorders_record['status'] = array('type' => INT_TYPE);
    $salesorders_record['order_date'] = array('type' => INT_TYPE);
    $salesorders_record['updated_date'] = array('type' => INT_TYPE);
    $salesorders_record['quote_id'] = array('type' => INT_TYPE);
    $salesorders_record['customer_id'] = array('type' => INT_TYPE);
    $salesorders_record['email'] = array('type' => CHAR_TYPE);
    $salesorders_record['fname'] = array('type' => CHAR_TYPE);
    $salesorders_record['mname'] = array('type' => CHAR_TYPE);
    $salesorders_record['lname'] = array('type' => CHAR_TYPE);
    $salesorders_record['company'] = array('type' => CHAR_TYPE);
    $salesorders_record['currency'] = array('type' => CHAR_TYPE);
    $salesorders_record['sales_rep'] = array('type' => CHAR_TYPE);
    $salesorders_record['subtotal'] = array('type' => FLOAT_TYPE);
    $salesorders_record['tax'] = array('type' => FLOAT_TYPE);
    $salesorders_record['tax_zone'] = array('type' => CHAR_TYPE);
    $salesorders_record['tax_rate'] = array('type' => FLOAT_TYPE);
    $salesorders_record['shipping'] = array('type' => FLOAT_TYPE);
    $salesorders_record['coupon_id'] = array('type' => INT_TYPE);
    $salesorders_record['coupon_amount'] = array('type' => FLOAT_TYPE);
    $salesorders_record['gift_id'] = array('type' => INT_TYPE);
    $salesorders_record['gift_amount'] = array('type' => FLOAT_TYPE);
    $salesorders_record['fee_name'] = array('type' => CHAR_TYPE);
    $salesorders_record['fee_amount'] = array('type' => FLOAT_TYPE);
    $salesorders_record['discount_name'] = array('type' => CHAR_TYPE);
    $salesorders_record['discount_amount'] = array('type' => FLOAT_TYPE);
    $salesorders_record['total'] = array('type' => FLOAT_TYPE);
    $salesorders_record['balance_due'] = array('type' => FLOAT_TYPE);
    $salesorders_record['purchase_order'] = array('type' => CHAR_TYPE);
    $salesorders_record['shipping_carrier'] = array('type' => CHAR_TYPE);
    $salesorders_record['shipping_method'] = array('type' => CHAR_TYPE);
    $salesorders_record['shipping_flags'] = array('type' => INT_TYPE);
    $salesorders_record['weight'] = array('type' => FLOAT_TYPE);
    $salesorders_record['comments'] = array('type' => CHAR_TYPE);
    $salesorders_record['notes'] = array('type' => CHAR_TYPE);
    $salesorders_record['terms'] = array('type' => CHAR_TYPE);
    $salesorders_record['website'] = array('type' => INT_TYPE);
    $salesorders_record['partial_ship'] = array('type' => INT_TYPE);
    $salesorders_record['partial_ship']['fieldtype'] = CHECKBOX_FIELD;
    $salesorders_record['flags'] = array('type' => INT_TYPE);
    if (function_exists('custom_salesorder_fields'))
       custom_salesorder_fields($salesorders_record);
    return $salesorders_record;
}

function invoices_record_definition()
{
    $invoices_record = array();
    $invoices_record['id'] = array('type' => INT_TYPE,'key' => true);
    $invoices_record['status'] = array('type' => INT_TYPE);
    $invoices_record['invoice_date'] = array('type' => INT_TYPE);
    $invoices_record['updated_date'] = array('type' => INT_TYPE);
    $invoices_record['quote_id'] = array('type' => INT_TYPE);
    $invoices_record['order_id'] = array('type' => INT_TYPE);
    $invoices_record['customer_id'] = array('type' => INT_TYPE);
    $invoices_record['email'] = array('type' => CHAR_TYPE);
    $invoices_record['fname'] = array('type' => CHAR_TYPE);
    $invoices_record['mname'] = array('type' => CHAR_TYPE);
    $invoices_record['lname'] = array('type' => CHAR_TYPE);
    $invoices_record['company'] = array('type' => CHAR_TYPE);
    $invoices_record['currency'] = array('type' => CHAR_TYPE);
    $invoices_record['subtotal'] = array('type' => FLOAT_TYPE);
    $invoices_record['tax'] = array('type' => FLOAT_TYPE);
    $invoices_record['shipping'] = array('type' => FLOAT_TYPE);
    $invoices_record['coupon_id'] = array('type' => INT_TYPE);
    $invoices_record['coupon_amount'] = array('type' => FLOAT_TYPE);
    $invoices_record['gift_id'] = array('type' => INT_TYPE);
    $invoices_record['gift_amount'] = array('type' => FLOAT_TYPE);
    $invoices_record['fee_name'] = array('type' => CHAR_TYPE);
    $invoices_record['fee_amount'] = array('type' => FLOAT_TYPE);
    $invoices_record['discount_name'] = array('type' => CHAR_TYPE);
    $invoices_record['discount_amount'] = array('type' => FLOAT_TYPE);
    $invoices_record['total'] = array('type' => FLOAT_TYPE);
    $invoices_record['purchase_order'] = array('type' => CHAR_TYPE);
    $invoices_record['comments'] = array('type' => CHAR_TYPE);
    $invoices_record['notes'] = array('type' => CHAR_TYPE);
    $invoices_record['terms'] = array('type' => CHAR_TYPE);
    $invoices_record['website'] = array('type' => INT_TYPE);
    $invoices_record['flags'] = array('type' => INT_TYPE);
    if (function_exists('custom_invoice_fields'))
       custom_invoice_fields($invoices_record);
    return $invoices_record;
}

function item_attributes_record_definition()
{
    $item_attributes_record = array();
    $item_attributes_record['id'] = array('type' => INT_TYPE,'key' => true);
    $item_attributes_record['parent'] = array('type' => INT_TYPE);
    $item_attributes_record['attribute_id'] = array('type' => INT_TYPE);
    $item_attributes_record['attribute_type'] = array('type' => INT_TYPE);
    $item_attributes_record['price'] = array('type' => FLOAT_TYPE);
    $item_attributes_record['data'] = array('type' => CHAR_TYPE);
    return $item_attributes_record;
}

function payment_record_definition()
{
    $payment_record = array();
    $payment_record['id'] = array('type' => INT_TYPE,'key' => true);
    $payment_record['parent_type'] = array('type' => INT_TYPE);
    $payment_record['parent'] = array('type' => INT_TYPE);
    $payment_record['payment_status'] = array('type' => INT_TYPE);
    $payment_record['payment_date'] = array('type' => INT_TYPE);
    $payment_record['payment_user'] = array('type' => CHAR_TYPE);
    $payment_record['payment_type'] = array('type' => CHAR_TYPE);
    $payment_record['payment_method'] = array('type' => CHAR_TYPE);
    $payment_record['payment_amount'] = array('type' => FLOAT_TYPE);
    $payment_record['card_type'] = array('type' => CHAR_TYPE);
    $payment_record['card_name'] = array('type' => CHAR_TYPE);
    $payment_record['card_number'] = array('type' => CHAR_TYPE);
    $payment_record['card_month'] = array('type' => CHAR_TYPE);
    $payment_record['card_year'] = array('type' => CHAR_TYPE);
    $payment_record['card_cvv'] = array('type' => CHAR_TYPE);
    $payment_record['check_number'] = array('type' => CHAR_TYPE);
    $payment_record['payment_id'] = array('type' => CHAR_TYPE);
    $payment_record['payment_code'] = array('type' => CHAR_TYPE);
    $payment_record['payment_ref'] = array('type' => CHAR_TYPE);
    $payment_record['payment_data'] = array('type' => CHAR_TYPE);
    $payment_record['saved_card_id'] = array('type' => INT_TYPE);
    return $payment_record;
}

function shipment_record_definition()
{
    $shipment_record = array();
    $shipment_record['id'] = array('type' => INT_TYPE,'key' => true);
    $shipment_record['parent_type'] = array('type' => INT_TYPE);
    $shipment_record['parent'] = array('type' => INT_TYPE);
    $shipment_record['shipping'] = array('type' => FLOAT_TYPE);
    $shipment_record['shipped_date'] = array('type' => INT_TYPE);
    $shipment_record['shipping_carrier'] = array('type' => CHAR_TYPE);
    $shipment_record['shipping_method'] = array('type' => CHAR_TYPE);
    $shipment_record['shipping_trans'] = array('type' => CHAR_TYPE);
    $shipment_record['shipping_flags'] = array('type' => INT_TYPE);
    $shipment_record['weight'] = array('type' => FLOAT_TYPE);
    $shipment_record['tracking'] = array('type' => CHAR_TYPE);
    return $shipment_record;
}

function shipment_item_record_definition()
{
    $shipment_item_record = array();
    $shipment_item_record['id'] = array('type' => INT_TYPE,'key' => true);
    $shipment_item_record['parent'] = array('type' => INT_TYPE);
    $shipment_item_record['item_id'] = array('type' => INT_TYPE);
    $shipment_item_record['qty'] = array('type' => INT_TYPE);
    return $shipment_item_record;
}

class OrderInfo {

function set($field_name,$field_value)
{
    $this->info[$field_name] = $field_value;
}

function get($field_name)
{
    if (! strncmp($field_name,'order_',6)) {
       $field_name = substr($field_name,6);
       if (($field_name == 'shipped_date') || ($field_name == 'weight') ||
           (substr($field_name,0,8) == 'shipping') ||
           (substr($field_name,0,8) == 'tracking')) {
          if (! isset($this->shipments))
             $this->shipments = load_order_shipments($this);
          if (! empty($this->shipments)) {
             $shipment_info = reset($this->shipments);
             if (isset($shipment_info[$field_name]))
                return $shipment_info[$field_name];
          }
       }
       else if ((substr($field_name,0,7) == 'payment') ||
                (substr($field_name,0,4) == 'card') ||
                (substr($field_name,0,5) == 'check')) {
          if (! isset($this->payments))
             $this->payments = load_order_payments($this);
          if (! empty($this->payments)) {
             $payment_info = reset($this->payments);
             if (isset($payment_info[$field_name]))
                return $payment_info[$field_name];
          }
       }
       if (isset($this->info[$field_name])) return $this->info[$field_name];
    }
    else if (! strncmp($field_name,'bill_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->billing_country == 1) || ($this->billing_country == 29) ||
              ($this->billing_country == 43)) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->billing_country != 43) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'country_name') {
          $country_name = get_country_name($this->billing_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_name;
       }
       else if ($field_name == 'country_code') {
          $country_info = get_country_info($this->billing_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_info['code'];
       }
       if (isset($this->billing) && isset($this->billing[$field_name]))
          return $this->billing[$field_name];
    }
    else if (! strncmp($field_name,'ship_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->shipping_country == 1) || ($this->shipping_country == 29) ||
              ($this->shipping_country == 43)) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->shipping_country != 43) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'country_name') {
          $country_name = get_country_name($this->shipping_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_name;
       }
       else if ($field_name == 'country_code') {
          $country_info = get_country_info($this->shipping_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_info['code'];
       }
       if (isset($this->shipping[$field_name]))
          return $this->shipping[$field_name];
    }
    return '';
}

function log_shipping($msg)
{
    global $shipping_log;
    global $login_cookie;

    $shipping_file = fopen($shipping_log,'at');
    if ($shipping_file) {
       $remote_user = getenv('REMOTE_USER');
       if (($remote_user == '') && isset($_COOKIE[$login_cookie]))
          $remote_user = $_COOKIE[$login_cookie];
       if (($remote_user == '') && isset($_SERVER['REMOTE_ADDR']))
          $remote_user = $_SERVER['REMOTE_ADDR'];
       fwrite($shipping_file,$remote_user.' ['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($shipping_file);
    }
}

function format_shipping_field($field_name)
{
    $shipping_module = $this->info['shipping_carrier'];
    if (! shipping_module_event_exists('format_shipping_field',
                                       $shipping_module))
       return $this->info[$field_name];
    $format_shipping_field = $shipping_module.'_format_shipping_field';
    return $format_shipping_field($this->info,$field_name);
}

function write_amount($amount,$use_cents_flag=false,$write_output=true)
{
    global $amount_cents_flag;

    if (isset($use_cents_flag) && $use_cents_flag) {
       if (isset($amount_cents_flag) && (! $amount_cents_flag)) $precision = 0;
       else $precision = 2;
    }
    else $precision = 2;
    if ((! isset($this->currency)) && (! $this->exchange_rate)) return;
    $output = format_amount($amount,$this->currency,$this->exchange_rate,
                            $precision);
    if ($write_output) print $output;
    return $output;
}

};

function format_qty($qty)
{
    return rtrim(rtrim(number_format($qty,2,'.',''),'0'),'.');
}

function load_order(&$db,$id,&$error_msg)
{
    global $order_type,$order_label,$orders_table,$default_currency;

    if (! isset($order_type)) $order_type = ORDER_TYPE;
    switch ($order_type) {
       case ORDER_TYPE:
          if (! isset($order_label)) $order_label = 'Order';
          $orders_table = 'orders';   break;
       case QUOTE_TYPE:
          $order_label = 'Quote';   $orders_table = 'quotes';   break;
       case INVOICE_TYPE:
          $order_label = 'Invoice';   $orders_table = 'invoices';   break;
       case SALESORDER_TYPE:
          $order_label = 'Sales Order';   $orders_table = 'sales_orders';
          break;
    }

    $order = new OrderInfo();
    $order->db = $db;
    if (! is_numeric($id)) {
       log_error('Invalid '.$order_label.' ID ('.$id.')');
       log_request_data();
       $error_msg = 'Invalid '.$order_label.' ID';   return null;
    }
    $query = 'select * from '.$orders_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $order->info = $db->get_record($query);
    if (! $order->info) {
       if (isset($db->error)) $error_msg = $db->error;
       else {
          $error_msg = $order_label.' not found';   log_error($error_msg);
       }
       return null;
    }
    $error_msg = null;
    $order->id = $id;
    $order->order_type = $order_type;
    $order->table = $orders_table;
    $order->label = $order_label;
    $db->decrypt_record($orders_table,$order->info);
    if (! isset($default_currency)) $default_currency = 'USD';
    $order->currency = $order->info['currency'];
    $order->customer_id = $order->info['customer_id'];
    $order->exchange_rate = null;

    $query = 'select * from order_items where (parent=?) and ' .
             '(parent_type=?) order by id';
    $query = $db->prepare_query($query,$id,$order_type);
    $order->items = $db->get_records($query,'id');
    if ((! $order->items) && isset($db->error)) {
       $error_msg = $db->error;   return null;
    }
    if (! empty($order->items)) foreach ($order->items as $item_id => $row) {
       $order->items[$item_id]['qty'] = format_qty($row['qty']);
       $attribute_array = build_attribute_array($db,'order',$row);
       $order->items[$item_id]['attribute_array'] = $attribute_array;
       if ($attribute_array && ($row['attribute_prices'] == '')) {
          $attribute_prices = '';
          foreach ($attribute_array as $index => $attribute_info) {
             if ($index > 0) $attribute_prices .= '|';
             $attribute_prices .= $attribute_info['price'];
          }
          $order->items[$item_id]['attribute_prices'] = $attribute_prices;
       }
    }

    $query = 'select * from order_billing where (parent=?) and ' .
             '(parent_type=?)';
    $query = $db->prepare_query($query,$id,$order_type);
    $order->billing = $db->get_record($query);
    if (! $order->billing) {
       if (isset($db->error)) {
          $error_msg = $db->error;   return null;
       }
       $order->billing = array();
    }
    else $db->decrypt_record('order_billing',$order->billing);

    $query = 'select * from order_shipping where (parent=?) and ' .
             '(parent_type=?)';
    $query = $db->prepare_query($query,$id,$order_type);
    $order->shipping = $db->get_record($query);
    if (! $order->shipping) {
       if (isset($db->error)) {
          $error_msg = $db->error;   return null;
       }
       $order->shipping = array();
    }
    else $db->decrypt_record('order_shipping',$order->shipping);

    if (isset($order->info['reorder_id'])) {
       $query = 'select order_number from '.$orders_table.' where id=?';
       $query = $db->prepare_query($query,$order->info['reorder_id']);
       $row = $db->get_record($query);
       if ($row) $order->info['reorder'] = $row['order_number'];
       else if (isset($db->error)) $error_msg = $db->error;
    }

    $order->features = get_cart_config_value('features',$db);

    return $order;
}

function load_order_part_numbers(&$order)
{
    if (! isset($order->items)) return;
    foreach ($order->items as $item_id => $order_item) {
       if (isset($order_item['part_number']) && $order_item['part_number'])
          continue;
       if (empty($order_item['product_id'])) {
          $order->items[$item_id]['part_number'] = '';   continue;
       }
       $query = 'select part_number from product_inventory where parent=?';
       if ((! isset($order_item['attributes'])) ||
           ($order_item['attributes'] == ''))
          $lookup_attributes = null;
       else {
          $no_options = check_no_option_attributes($order->db,$order_item['product_id']);
          if ($no_options) $attributes = explode('|',$order_item['attributes']);
          else $attributes = explode('-',$order_item['attributes']);
          $lookup_attributes = build_lookup_attributes($order->db,
             $order_item['product_id'],$attributes,true,$no_options);
       }
       if ($lookup_attributes) {
          $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
             $order_item['product_id'],$no_options,$order->db);
          $query .= ' and attributes=?';
          $query = $order->db->prepare_query($query,$order_item['product_id'],
                                             $lookup_attributes);
       }
       else {
          $query .= ' and ((attributes="") or isnull(attributes))';
          $query = $order->db->prepare_query($query,$order_item['product_id']);
       }
       $row = $order->db->get_record($query);
       if ($row && $row['part_number'])
          $order->items[$item_id]['part_number'] = $row['part_number'];
       else $order->items[$item_id]['part_number'] = '';
    }
}

function load_order_accounts(&$order)
{
    if (! isset($order->items)) return;
    $account_ids = array();
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['account_id'] &&
          (! in_array($order_item['account_id'],$account_ids)))
          $account_ids[] = $order_item['account_id'];
       else $order->items[$item_id]['account_name'] = '';
    }
    if (count($account_ids) == 0) return;
    $query = 'select id,name from accounts where id in (?)';
    $query = $order->db->prepare_query($query,$account_ids);
    $accounts = $order->db->get_records($query,'id');
    if (! $accounts) return;
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['account_id'])
          $order->items[$item_id]['account_name'] =
             $accounts[$order_item['account_id']]['name'];
    }
}

function load_order_weight(&$order,$default_weight)
{
    if (! empty($order->info['weight'])) return $order->info['weight'];
    $order_weight = 0;
    if (empty($order->items)) return $default_weight;
    foreach ($order->items as $item_id => $order_item) {
       if (! $order_item['product_id']) {
          $order->items[$item_id]['weight'] = $default_weight;
          continue;
       }
       $query = 'select weight from product_inventory where parent=?';
       if ((! isset($order_item['attributes'])) ||
           ($order_item['attributes'] == ''))
          $lookup_attributes = null;
       else {
          $no_options = check_no_option_attributes($order->db,$order_item['product_id']);
          if ($no_options) $attributes = explode('|',$order_item['attributes']);
          else $attributes = explode('-',$order_item['attributes']);
          $lookup_attributes = build_lookup_attributes($order->db,
             $order_item['product_id'],$attributes,true,$no_options);
       }
       if ($lookup_attributes) {
          $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
             $order_item['product_id'],$no_options,$order->db);
          $query .= ' and attributes=?';
          $query = $order->db->prepare_query($query,$order_item['product_id'],
                                             $lookup_attributes);
       }
       else {
          $query .= ' and ((attributes="") or isnull(attributes))';
          $query = $order->db->prepare_query($query,$order_item['product_id']);
       }
       $row = $order->db->get_record($query);
       if ($row) {
          $order_item['weight'] = floatval($row['weight']);
          $order->items[$item_id]['weight'] = $order_item['weight'];
       }
       else {
          $order_item['weight'] = 0;
          $order->items[$item_id]['weight'] = $default_weight;
       }
       if (function_exists('calculate_item_weight'))
          $weight = calculate_item_weight($order_item);
       else {
          $weight = $order_item['weight'];
          if ((! $weight) || ($weight == '')) $weight = $default_weight;
          if (! ($order_item['flags'] & QTY_PRICE)) $weight *= $order_item['qty'];
       }
       $order_weight += $weight;
    }
    return $order_weight;
}

function load_order_taxable_flags(&$order)
{
    global $custom_taxable_default;

    if (! isset($custom_taxable_default)) $custom_taxable_default = false;
    if (! isset($order->items)) return;
    $item_ids = array();
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id']) $item_ids[] = $order_item['product_id'];
       else if ($custom_taxable_default)
          $order->items[$item_id]['taxable'] = '1';
       else $order->items[$item_id]['taxable'] = '0';
    }
    if (count($item_ids) == 0) return;
    $query = 'select id,taxable from products where id in (?)';
    $query = $order->db->prepare_query($query,$item_ids);
    $taxable_flags = $order->db->get_records($query,'id','taxable');
    if (! $taxable_flags) return;
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id'])
          $order->items[$item_id]['taxable'] =
             $taxable_flags[$order_item['product_id']];
    }
}

function load_order_mpns(&$order)
{
    if (! isset($order->items)) return;
    $item_ids = array();
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id']) $item_ids[] = $order_item['product_id'];
       else $order->items[$item_id]['mpn'] = '';
    }
    if (count($item_ids) == 0) return;
    $query = 'select id,shopping_mpn from products where id in (?)';
    $query = $order->db->prepare_query($query,$item_ids);
    $mpns = $order->db->get_records($query,'id');
    if (! $mpns) return;
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id'])
          $order->items[$item_id]['mpn'] =
             $mpns[$order_item['product_id']]['shopping_mpn'];
    }
}

function load_order_item_images(&$order,$size='medium')
{
    global $use_dynamic_images,$image_subdir_prefix,$dynamic_image_url;

    if (! isset($order->items)) return;
    foreach ($order->items as $item_id => $order_item) {
       if (! $order_item['product_id']) {
          $order->items[$item_id]['image'] = '';
          $order->items[$item_id]['image_filename'] = '';
          continue;
       }
       $query = 'select image from product_inventory where parent=?';
       if ((! isset($order_item['attributes'])) ||
           ($order_item['attributes'] == ''))
          $lookup_attributes = null;
       else {
          $no_options = check_no_option_attributes($order->db,
                                                   $order_item['product_id']);
          if ($no_options) $attributes = explode('|',$order_item['attributes']);
          else $attributes = explode('-',$order_item['attributes']);
          $lookup_attributes = build_lookup_attributes($order->db,
             $order_item['product_id'],$attributes,true,$no_options);
       }
       if ($lookup_attributes) {
          $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
             $order_item['product_id'],$no_options,$order->db);
          $query .= ' and attributes=?';
          $query = $order->db->prepare_query($query,$order_item['product_id'],
                                             $lookup_attributes);
       }
       else {
          $query .= ' and ((attributes="") or isnull(attributes))';
          $query = $order->db->prepare_query($query,$order_item['product_id']);
       }
       $row = $order->db->get_record($query);
       if ($row && $row['image']) $image_filename = $row['image'];
       else {
          $query = 'select filename from images where parent_type=1 and ' .
                   'parent=? order by sequence limit 1';
          $query = $order->db->prepare_query($query,$order_item['product_id']);
          $image_row = $order->db->get_record($query);
          if (! $image_row) $image_filename = '';
          else $image_filename = $image_row['filename'];
       }
       if (! $image_filename) $image_url = '';
       else if ($use_dynamic_images) {
          if (isset($dynamic_image_url))
             $image_url = $dynamic_image_url.'?cmd=loadimage&filename=' .
                          rawurlencode($image_filename).'&size='.$size;
          else $image_url = 'cartengine/image.php?cmd=loadimage&filename=' .
                            rawurlencode($image_filename).'&size='.$size;
       }
       else if ($image_subdir_prefix)
          $image_url = 'images/medium/' .
             substr($image_filename,0,$image_subdir_prefix).'/' .
             rawurlencode($image_filename);
       else $image_url = 'images/'.$size.'/'.rawurlencode($image_filename);
       $order->items[$item_id]['image'] = $image_url;
       $order->items[$item_id]['image_filename'] = $image_filename;
    }
}

function load_order_item_urls(&$order)
{
    if (! isset($order->items)) return;
    $item_ids = array();
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id']) $item_ids[] = $order_item['product_id'];
       else $order->items[$item_id]['url'] = '';
    }
    if (count($item_ids) == 0) return;
    require_once 'shopping-common.php';
    $query = 'select id,flags,seo_url,seo_category from products ' .
             'where id in (?)';
    $query = $order->db->prepare_query($query,$item_ids);
    $products = $order->db->get_records($query,'id');
    if (! $products) return;
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id'])
          $order->items[$item_id]['url'] =
             build_product_url($order->db,$products[$order_item['product_id']]);
    }
}

function load_order_item_product_info(&$order)
{
    if (! isset($order->items)) return;
    $prod_ids = array();
    foreach ($order->items as $item_id => $order_item) {
       $order->items[$item_id]['product_flags'] = 0;
       $order->items[$item_id]['price_break_type'] = 0;
       $order->items[$item_id]['price_breaks'] = '';
       if ($order_item['product_id']) $prod_ids[] = $order_item['product_id'];
    }
    if (count($prod_ids) == 0) return;
    $query = 'select id,flags,price_break_type,price_breaks from products ' .
             'where id in (?)';
    $query = $order->db->prepare_query($query,$prod_ids);
    $rows = $order->db->get_records($query,'id');
    if (! $rows) return;
    foreach ($order->items as $item_id => $order_item) {
       if ($order_item['product_id'] &&
           isset($rows[$order_item['product_id']])) {
          $product_id = $order_item['product_id'];
          $order->items[$item_id]['product_flags'] =
             $rows[$product_id]['flags'];
          $order->items[$item_id]['price_break_type'] =
             $rows[$product_id]['price_break_type'];
          $order->items[$item_id]['price_breaks'] =
             $rows[$product_id]['price_breaks'];
       }
    }
}

function load_order_payments($order)
{
    if (empty($order->id)) return array();
    $query = 'select * from order_payments where (parent=?) and ' .
             '(parent_type=?) order by payment_date';
    $query = $order->db->prepare_query($query,$order->id,$order->order_type);
    $payments = $order->db->get_records($query);
    if (! $payments) return array();
    $order->db->decrypt_records('order_payments',$payments);
    return $payments;
}

function load_order_shipments($order)
{
    if (empty($order->id)) return array();
    $query = 'select * from order_shipments where (parent=?) and ' .
             '(parent_type=?) order by id';
    $query = $order->db->prepare_query($query,$order->id,$order->order_type);
    $shipments = $order->db->get_records($query,'id');
    if (! $shipments) return array();
    $order->db->decrypt_records('order_shipments',$shipments);
    $query = 'select * from order_shipment_items where parent in ' .
             '(select id from order_shipments where (parent=?) and ' .
             '(parent_type=?)) order by parent,item_id';
    $query = $order->db->prepare_query($query,$order->id,$order->order_type);
    $shipment_items = $order->db->get_records($query);
    if (! $shipment_items) return $shipments;
    $order->db->decrypt_records('order_shipment_items',$shipment_items);
    foreach ($shipment_items as $item) {
       if (! isset($shipments[$item['parent']]['items']))
          $shipments[$item['parent']]['items'] = array();
       $shipments[$item['parent']]['items'][] = $item;
    }
    return $shipments;
}

function delete_order_record($db,$order_info,&$error,$module=null,
                             $order_type=ORDER_TYPE)
{
    global $file_dir;

    $order_id = $order_info['id'];
    switch ($order_type) {
       case ORDER_TYPE: $table = 'orders';   $event = 'delete_order';   break;
       case QUOTE_TYPE: $table = 'quotes';   $event = 'delete_quote';   break;
       case INVOICE_TYPE:
          $table = 'invoices';   $event = 'delete_invoice';   break;
       case SALESORDER_TYPE:
          $table = 'sales_orders';   $event = 'delete_salesorder';   break;
    }
    require_once '../engine/modules.php';
    if (module_attached($event)) {
       $query = 'select * from order_billing where (parent=?) and ' .
                '(parent_type=?)';
       $query = $db->prepare_query($query,$order_id,$order_type);
       $billing_info = $db->get_record($query);
       $query = 'select * from order_shipping where (parent=?) and ' .
                '(parent_type=?)';
       $query = $db->prepare_query($query,$order_id,$order_type);
       $shipping_info = $db->get_record($query);
    }
    $query = 'delete from order_items where (parent=?) and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $query = 'delete from order_billing where (parent=?) and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $query = 'delete from order_shipping where (parent=?) and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $query = 'delete from order_payments where (parent=?) and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $query = 'delete from order_shipment_items where parent in ' .
             '(select id from order_shipments where (parent=?) and ' .
             '(parent_type=?))';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $query = 'delete from order_shipments where (parent=?) and ' .
             '(parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    $order_record = orders_record_definition();
    $order_record['id']['value'] = $order_id;
    if (! $db->delete($table,$order_record)) {
       $error = $db->error;   return false;
    }
    $label_filename = $file_dir.'/labels/'.$order_id.'.gif';
    if (file_exists($label_filename)) unlink($label_filename);
    if (function_exists('custom_delete_order')) custom_delete_order($order_id);
    if (module_attached($event)) {
       if (! call_module_event($event,
                array($db,$order_info,$billing_info,$shipping_info),
                $module,true)) {
          $error = get_module_errors();   return false;
       }
    }
    return true;
}

class CartInfo {

function write_amount($amount,$use_cents_flag=false,$write_output=true)
{
    global $amount_cents_flag;

    if (isset($use_cents_flag) && $use_cents_flag) {
       if (isset($amount_cents_flag) && (! $amount_cents_flag)) $precision = 0;
       else $precision = 2;
    }
    else $precision = 2;
    if ((! isset($this->currency)) && (! $this->exchange_rate)) return;
    $output = format_amount($amount,$this->currency,$this->exchange_rate,
                            $precision);
    if ($write_output) print $output;
    return $output;
}

};

function load_cart(&$db,$id,&$error_msg)
{
    global $wish_lists;

    $cart = new CartInfo();
    $cart->db = $db;
    if (! empty($wish_lists)) $table = 'wishlist';
    else $table = 'cart';
    $query = 'select * from '.$table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $cart->info = $db->get_record($query);
    if (! $cart->info) {
       if (isset($db->error)) $error_msg = $db->error;
       else $error_msg = 'Cart not found';
       return null;
    }
    $error_msg = null;
    $cart->currency = $cart->info['currency'];
    $cart->exchange_rate = null;

    $query = 'select * from '.$table.'_items where parent=? order by id';
    $query = $db->prepare_query($query,$id);
    $cart->items = $db->get_records($query,'id');
    if (! $cart->items) {
       if (isset($db->error)) $error_msg = $db->error;
    }
    else foreach ($cart->items as $item_id => $row) {
       $attribute_array = build_attribute_array($db,'cart',$row);
       $cart->items[$item_id]['attribute_array'] = $attribute_array;
    }

    if (isset($cart->info['reorder_id'])) {
       $query = 'select order_number from orders where id=?';
       $query = $db->prepare_query($query,$cart->info['reorder_id']);
       $row = $db->get_record($query);
       if ($row) $cart->info['reorder'] = $row['order_number'];
       else if (isset($db->error)) $error_msg = $db->error;   
    }

    return $cart;
}

function build_attribute_array($db,$prefix,$item_record)
{
    if (! empty($item_record['attributes'])) {
       $no_options = check_no_option_attributes($db,$item_record['product_id']);
       if ($no_options && (strpos($item_record['attributes'],'|') !== false))
          $attribute_values = explode('|',$item_record['attributes']);
       else $attribute_values = explode('-',$item_record['attributes']);
    }
    if (! empty($item_record['attribute_names']))
       $attribute_names = explode('|',$item_record['attribute_names']);
    if (! empty($item_record['attribute_prices']))
       $attribute_prices = explode('|',$item_record['attribute_prices']);
    if (isset($attribute_values) && isset($attribute_names)) {
       if (count($attribute_names) != (count($attribute_values) * 2))
          $attributes = null;
       else {
          $attributes = array();
          foreach ($attribute_values as $index => $id) {
             $attributes[$index] = array('id' => $id,'attr' => $attribute_names[$index * 2],
                                         'option' => $attribute_names[($index * 2) + 1]);
             if (isset($id[0]) && ($id[0] == '*')) {
                $attributes[$index]['price'] = 0;
                $attributes[$index]['data'] = '';
                $attr_info = explode('*',$id);
                if (count($attr_info) != 3) continue;
                $query = 'select * from '.$prefix.'_attributes where ' .
                         'parent=? and attribute_id=?';
                $query = $db->prepare_query($query,$item_record['id'],
                                            $attr_info[1]);
                $row = $db->get_record($query);
                if ($row) {
                   $attributes[$index]['price'] = $row['price'];
                   $attributes[$index]['data'] = $row['data'];
                }
                else if (isset($db->error)) continue;
                else if (isset($attribute_prices) && isset($attribute_prices[$index]))
                   $attributes[$index]['price'] = floatval($attribute_prices[$index]);
             }
             else if (isset($attribute_prices) && isset($attribute_prices[$index]))
                $attributes[$index]['price'] = floatval($attribute_prices[$index]);
             else $attributes[$index]['price'] = 0;
          }
       }
    }
    else $attributes = null;
    return $attributes;
}

function get_item_total($item_record,$multiply_qty=true)
{
    $item_total = $item_record['price'];

/* removed because attribute price is already added in load_item_price
   and then put back in because it broke econoenvelope.  I don't know why
   it needed to be removed --  Randall 1/27/12 */

    if (isset($item_record['attribute_array'])) {
       $attribute_array = $item_record['attribute_array'];
       foreach ($attribute_array as $attribute)
          $item_total += $attribute['price'];
    }
    if ($multiply_qty && (! ($item_record['flags'] & QTY_PRICE)))
       $item_total *= $item_record['qty'];
    return $item_total;
}

function has_attribute_prices($attribute_array)
{
    global $hide_attribute_prices;

    if (isset($hide_attribute_prices) && $hide_attribute_prices) return false;
    if (! isset($attribute_array)) return false;
    $num_attributes = count($attribute_array);
    if ($num_attributes == 0) return false;
    foreach ($attribute_array as $attribute) {
       if ($attribute['price']) return true;
    }
    return false;
}

function get_card_type($card_type)
{
    switch ($card_type) {
       case 'amex': return 'American Express';
       case 'visa': return 'Visa';
       case 'master': return 'MasterCard';
       case 'discover': return 'Discover';
       case 'diners': return 'Diners Club';
       case 'JCB':  return 'JCB';
       default: return $card_type;
    }
}

function create_order_shipment($db,$order_info,$order_items,&$error,
                               $order_type=ORDER_TYPE)
{
    $shipment_record = shipment_record_definition();
    $shipment_record['parent']['value'] = $order_info['id'];
    $shipment_record['parent_type']['value'] = $order_type;
    $shipment_record['shipping']['value'] = $order_info['shipping'];
    if (array_key_exists('shipped_date',$order_info)) {
       if (! empty($order_info['shipped_date']))
          $shipment_record['shipped_date']['value'] =
             $order_info['shipped_date'];
    }
    else $shipment_record['shipped_date']['value'] = time();
    $shipment_record['shipping_carrier']['value'] = $order_info['shipping_carrier'];
    $shipment_record['shipping_method']['value'] = $order_info['shipping_method'];
    $shipment_record['shipping_flags']['value'] = $order_info['shipping_flags'];
    $shipment_record['weight']['value'] = $order_info['weight'];
    if (isset($order_info['tracking']))
       $shipment_record['tracking']['value'] = $order_info['tracking'];
    else $shipment_record['tracking']['value'] = get_form_field('tracking');
    if (! $db->insert('order_shipments',$shipment_record)) {
       $error = $db->error;   return null;
    }
    $order_shipment = $db->convert_record_to_array($shipment_record);
    $order_shipment['items'] = array();
    $shipment_id = $db->insert_id();
    $item_record = shipment_item_record_definition();
    $item_record['parent']['value'] = $shipment_id;
    foreach ($order_items as $item) {
       $item_record['item_id']['value'] = $item['id'];
       $item_record['qty']['value'] = $item['qty'];
       if (! $db->insert('order_shipment_items',$item_record)) {
          $error = $db->error;   return null;
       }
       $item = $db->convert_record_to_array($item_record);
       $order_shipment['items'][] = $item;
    }
    return $order_shipment;
}

function update_order_shipment($db,$shipment_info,&$error)
{
    $shipment_record = shipment_record_definition();
    foreach ($shipment_info as $field_name => $field_value) {
       if (isset($shipment_record[$field_name]))
          $shipment_record[$field_name]['value'] = $field_value;
    }
    if (! $db->update('order_shipments',$shipment_record)) {
       $error = $db->error;   return false;
    }
    return true;
}

if (! function_exists('get_html_attributes')) {
   function get_html_attributes($attribute_array,$location,$cart,$cart_item)
   {
       global $file_url;

       if (empty($attribute_array)) return '';
       if ($location == GET_ATTR_EMAIL_HTML) $html = '';
       else $html = "<br>\n";   $first_attr = true;
       foreach ($attribute_array as $attribute) {
          if (empty($attribute['option'])) continue;
          $option = str_replace("\n",'<br>',$attribute['option']);
          if (substr($option,0,2) == '{#') {
             $files = explode('^',$option);   $option = '';
             foreach ($files as $file) {
                $file_parts = explode('#}',substr($file,2));
                $filename = $file_parts[1];
                $url = $file_url.'/cart/'.$file_parts[0].'/' .
                       str_replace('#','%23',$filename);
                if ($option) $option .= ', ';
                $option .= '<a target="_blank" href="'.$url.'">' .
                           $filename.'</a>';
             }
          }
          else $option = str_replace('^',', ',$option);
          if (! $option) continue;
          if ($first_attr) $first_attr = false;
          else $html .= ', ';
          if ($attribute['attr']) $html .= $attribute['attr'].': ';
          $html .= $option;
          if ($attribute['price'])
             $html .= ' - $'.number_format($attribute['price'],2);
       }
       return $html;
   }
}

if (! function_exists('get_html_product_name')) {
   function get_html_product_name($product_name,$location=null,$cart=null,
                                  $cart_item=null)
   {
       return $product_name;
   }
}

function update_order_totals($db,$order_id,$new_order)
{
    global $order_type,$order_label,$orders_table;

    $query = 'select subtotal,shipping,coupon_amount,gift_amount,' .
             'discount_amount,fee_amount,total from '.$orders_table .
             ' where id=?';
    $query = $db->prepare_query($query,$order_id);
    $order_info = $db->get_record($query);
    if (! $order_info) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(409,$order_label.' not found');
       return false;
    }

    $query = 'select state,country from order_shipping where (parent=?) ' .
             'and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $shipping_info = $db->get_record($query);
    if (! $shipping_info) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(409,$order_label.' Shipping Information not found');
       return false;
    }

    $query = 'select qty,price,flags from order_items where (parent=?) ' .
             'and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $rows = $db->get_records($query);
    if ((! $rows) && isset($db->error)) {
       http_response(422,$db->error);   return false;
    }
    $new_subtotal = 0.0;
    foreach ($rows as $row) {
       if ($row['flags'] & QTY_PRICE) $new_subtotal += $row['price'];
       else $new_subtotal += ($row['qty'] * $row['price']);
    }

    $old_total = $order_info['total'];
    $new_total = $new_subtotal;
    if ($order_info['shipping'])
       $new_total += floatval($order_info['shipping']);
    if ($order_info['coupon_amount'])
       $new_total -= floatval($order_info['coupon_amount']);
    if ($order_info['gift_amount'])
       $new_total -= floatval($order_info['gift_amount']);
    if ($order_info['discount_amount'])
       $new_total -= floatval($order_info['discount_amount']);
    if ($order_info['fee_amount'])
       $new_total -= floatval($order_info['fee_amount']);

    $tax = 0;
    if ($shipping_info['country'] == 1) {
       $tax_rate = load_state_tax($shipping_info['state'],$db);
       if ($tax_rate != 0) {
          $taxable_total = $new_subtotal;
          if ($order_info['coupon_amount'])
             $taxable_total -= floatval($order_info['coupon_amount']);
          if ($order_info['discount_amount'])
             $taxable_total -= floatval($order_info['discount_amount']);
          $tax = round($taxable_total * ($tax_rate / 100),2);
          $new_total += $tax;
       }
    }

    $query = 'update '.$orders_table.' set subtotal=?,tax=?,total=? where id=?';
    $query = $db->prepare_query($query,$new_subtotal,$tax,$new_total,$order_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }

    if (function_exists('custom_update_order_totals')) {
       if (($old_total === null) || ($old_total == ''))
          $old_total = 0;
       custom_update_order_totals($db,$order_id,$old_total,$new_total,
                                  $new_order);
    }

    return true;
}

function check_no_option_attributes($db,$product_id)
{
    if (! $product_id) return false;
    $query = 'select a.related_id,(select type from attributes where id=' .
             'a.related_id) as type,(select dynamic from attributes where id=' .
             'a.related_id) as dynamic,(select count(id) from ' .
             'attribute_options where parent=a.related_id) as num_options ' .
             'from product_attributes a where parent=?';
    $query = $db->prepare_query($query,$product_id);
    $result = $db->query($query);
    $rows = $db->get_records($query);
    if (empty($rows)) return false;
    $no_options = false;
    foreach ($rows as $row) {
       if ($row['num_options'] == 0) {
          switch ($row['type']) {
             case 0:
             case 1:
             case 2: $no_options = true;   break;
             case 3:
             case 4:
             case 5:
             case 8: /* if ($row['dynamic'] == 1) */ $no_options = true;
                     break;
          }
       }
    }
    return $no_options;
}

function build_lookup_attributes($db,$product_id,$attributes,$inventory_lookup,
   $no_options,$check_sub_product=true,&$sub_product_flags=null)
{
    if (! isset($attributes)) return '';
    if (count($attributes) == 0) return '';
    if ($no_options) {
       $query = 'select a.* from product_attributes p join attributes a on ' .
                'a.id=p.related_id where p.parent=? order by p.sequence';
       $query = $db->prepare_query($query,$product_id);
       $rows = $db->get_records($query);
       if (! empty($rows)) {
          $attr_array = array();
          foreach ($rows as $index => $row) {
             if ($sub_product_flags !== null)
                $sub_product_flags[$index] = $row['sub_product'];
             if ($check_sub_product &&
                 (($row['sub_product'] != 1) && ($row['dynamic'] != 1)))
                continue;
             if (isset($attributes[$index]))
                $attr_array[] = $attributes[$index];
             else $attr_array[] = '';
          }
          $attributes = implode('|',$attr_array);
       }
       return $attributes;
    }
    if (! $inventory_lookup) return implode('-',$attributes);

    $option_ids = array();
    foreach ($attributes as $id) {
       if ((! isset($id[0])) || ($id[0] != '*')) {
          if (! is_numeric($id)) continue;
          $option_ids[] = $id;
       }
    }
    if (count($option_ids) == 0) return '';

    $query = 'select o.id,a.sub_product,a.dynamic from attributes a left ' .
             'join attribute_options o on a.id=o.parent where o.id in (?)';
    $query = $db->prepare_query($query,$option_ids);
    $attribute_info = $db->get_records($query,'id');
    if (! $attribute_info) {
       unset($db->error);   return '';
    }
    foreach ($attributes as $index => $id) {
       if ($sub_product_flags !== null) {
          if (! empty($attribute_info[$id]['sub_product']))
             $sub_product_flags[$index] = 1;
          else if (! empty($attribute_info[$id]['dynamic']))
             $sub_product_flags[$index] = 1;
          else $sub_product_flags[$index] = 0;
       }
       if (isset($id[0]) && ($id[0] == '*')) unset($attributes[$index]);
       else if ($check_sub_product &&
                empty($attribute_info[$id]['sub_product']) &&
                empty($attribute_info[$id]['dynamic']))
          unset($attributes[$index]);
    }
    return implode('-',$attributes);
}

function update_shipped_order($db,&$order,$shipped_date,$tracking_number)
{
    global $shipped_option;

    if (! isset($shipped_option)) $shipped_option = 1;
    $query = 'update orders set status=?,updated_date=?';
    $query = $db->prepare_query($query,$shipped_option,time());
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }

    if ($shipped_date) {
       $order->info['shipped_date'] = $shipped_date;
       $order->info['tracking'] = $tracking_number;
       if (! create_order_shipment($db,$order->info,$order->items,$error)) {
          http_response(422,$error);   return false;
       }
    }

    $payments = load_order_payments($order);
    if (count($payments) == 0) return true;
    $error = null;
    foreach ($payments as $payment_info) {
       if ($payment_info['payment_status'] == PAYMENT_AUTHORIZED) {
          $payment_module = $payment_info['payment_type'];
          if (! payment_module_event_exists('capture_payment',$payment_module))
             continue;
          $capture_function = $payment_module.'_capture_payment';
          if (! $capture_function($db,$payment_info,$error)) {
             log_error($error);   return false;
          }
       }
    }

    return true;
}

function send_eye4fraud_request($old_status,$db,$order)
{
    global $eye4fraud_login,$eye4fraud_key,$eye4fraud_product_info_query;
    global $eye4fraud_sitename;

    require_once 'eye4fraud.class.php';

    $payments = load_order_payments($order);
    if (empty($payments)) $payment = null;
    else $payment = reset($payments);
    $eye4fraud = new Eye4Fraud('https://www.eye4fraud.com/api/',
                               $eye4fraud_login,$eye4fraud_key);
    $billing_country_info = get_country_info($order->billing['country'],$db);
    $shipping_country_info = get_country_info($order->shipping['country'],$db);
    if ($order->customer_id) {
       $query = 'select * from customers where id=?';
       $query = $db->prepare_query($query,$order->customer_id);
       $customer_info = $db->get_record($query);
       if ($customer_info) $ip_address = $customer_info['ip_address'];
       else if (isset($db->error)) {
          log_error($db->error);   http_response(422,$db->error);   return false;
       }
       else if ($order->info['ip_address'])
          $ip_address = $order->info['ip_address'];
       else $ip_address = '0.0.0.0';
    }
    else if ($order->info['ip_address'])
       $ip_address = $order->info['ip_address'];
    else $ip_address = '0.0.0.0';
    if (empty($payment['card_type'])) $card_type = 'OTHER';
    else switch ($payment['card_type']) {
       case 'amex': $card_type = 'AMEX';   break;
       case 'visa': $card_type = 'VISA';   break;
       case 'master': $card_type = 'MC';   break;
       case 'discover': $card_type = 'DISC';   break;
       default: $card_type = 'OTHER';
    }
    if (! empty($payment['payment_data']))
       $payment_data = $payment['payment_data'];
    else $payment_data = null;
    if ($payment_data) {
       $separator = $payment_data[1];
       $payment_array = explode($separator,$payment_data);
       $avs_code = $payment_array[5];
       $cid_response = $payment_array[38];
       if (! $cid_response) $cid_response = 'P';
    }
    else {
       $avs_code = 'P';   $cid_response = 'P';
    }
    if (! empty($payment['card_number']))
       $card_number = $payment['card_number'];
    if ($card_number) {
       $first6 = substr($card_number,0,6);   $last4 = substr($card_number,-4);
    }
    else {
       $first6 = '111111';   $last4 = '1111';
    }
    if (! empty($payment['payment_ref']))
       $referring_code = $payment['payment_ref'];
    else $referring_code = '';
    $order_info = array(
       'SiteName'               => $eye4fraud_sitename,
       'OrderDate'              => date('Y-m-d g:i:s a',$order->info['order_date']),
       'OrderNumber'            => $order->id,
       'CustomerID'             => $order->customer_id,
       'BillingFirstName'       => $order->info['fname'],
       'BillingMiddleName'      => $order->info['mname'], 
       'BillingLastName'        => $order->info['lname'],
       'BillingCompany'         => $order->info['company'],
       'BillingAddress1'        => $order->billing['address1'],
       'BillingAddress2'        => $order->billing['address2'],
       'BillingCity'            => $order->billing['city'],
       'BillingState'           => $order->billing['state'],
       'BillingZip'             => $order->billing['zipcode'],
       'BillingCountry'         => $billing_country_info['code'],
       'BillingEveningPhone'    => $order->billing['phone'],
       'BillingEmail'           => $order->info['email'],
       'IPAddress'              => $ip_address,
       'ShippingFirstName'      => $order->info['fname'],
       'ShippingMiddleName'     => $order->info['mname'],
       'ShippingLastName'       => $order->info['lname'],
       'ShippingCompany'        => $order->shipping['company'],
       'ShippingAddress1'       => $order->shipping['address1'],
       'ShippingAddress2'       => $order->shipping['address2'],
       'ShippingCity'           => $order->shipping['city'],
       'ShippingState'          => $order->shipping['state'],
       'ShippingZip'            => $order->shipping['zipcode'],
       'ShippingCountry'        => $shipping_country_info['code'],
       'ShippingEveningPhone'   => $order->billing['phone'],
       'ShippingEmail'          => $order->info['email'],
       'ShippingCost'           => $order->info['shipping'],
       'GrandTotal'             => $order->info['total'],
       'CCFirst6'               => $first6,
       'CCLast4'                => $last4,
       'CCType'                 => $card_type,
       'CIDResponse'            => $cid_response,
       'AVSCode'                => $avs_code,
       'ReferringCode'          => $referring_code,
       'PromoCode'              => $order->info['coupon_id']
    );
    if (! $order_info['BillingState']) $order_info['BillingState'] = '0';
    if (! $order_info['ShippingState']) $order_info['ShippingState'] = '0';
    if ($order->shipping['shipto']) {
       $order_info['ShippingFirstName'] = $order->shipping['shipto'];
       $order_info['ShippingLastName'] = $order->shipping['shipto'];
    }
    $eye4fraud->setBin($first6);
    $eye4fraud->setOrderInfo($order_info);
    foreach ($order->items as $item_id => $order_item) {
       if (isset($eye4fraud_product_info_query))
          $product_info = $db->get_record($eye4fraud_product_info_query);
       else $product_info = null;
       if (! $product_info) $product_info = array('sku'=>'','brand'=>'');
       $order_item = array(
          'SKU'                 => $product_info['sku'],
          'ProductName'         => $order_item['product_name'],
          'ProductDescription'  => $order_item['product_name'],
          'ProductSellingPrice' => get_item_total($order_item,false),
          'ProductQty'          => $order_item['qty'],
          'ProductBrandname'    => $product_info['brand']
       );
       $eye4fraud->addLineItem($order_item);
    }
    if (! $eye4fraud->send())
       log_error('Eye4Fraud Error: '.$eye4fraud->getError());
    else log_activity('Sent Order #'.$order->id.' to Eye4Fraud for Review');
    return true;
}

if (! function_exists('change_order_status')) {
   function change_order_status($old_status,$new_status,$db,$order)
   {
       global $template_prefixes,$eye4fraud_status;
       global $shipped_option,$backorder_option,$cancelled_option;

       if (function_exists('custom_change_order_status') &&
           custom_change_order_status($old_status,$new_status,$db,$order))
          return true;

       if (! isset($shipped_option)) $shipped_option = 1;
       if (! isset($backorder_option)) $backorder_option = 2;
       if (! isset($cancelled_option)) $cancelled_option = 3;
       if (isset($eye4fraud_status) && ($new_status == $eye4fraud_status))
          return send_eye4fraud_request($old_status,$db,$order);

       $send_emails = get_form_field('send_emails');
       if ($send_emails === '') return true;

       if (($new_status != $shipped_option) &&
           ($new_status != $backorder_option) &&
           ($new_status != $cancelled_option)) return true;
       if (file_exists('../engine/email.php'))
          require_once '../engine/email.php';
       else require_once 'engine/email.php';
       $notify_flags = get_cart_config_value('notifications',$db);
       $email_template = 0;
       if ($new_status == $shipped_option) {
          if (($notify_flags & NOTIFY_SHIPPED) &&
              ((! isset($order->info['external_source'])) ||
               (substr($order->info['external_source'],0,6) != 'Amazon')))
             $email_template = SHIP_NOTIFY_EMAIL;
       }
       else if ($new_status == $cancelled_option) {
          if (! restore_inventory($order->id,$db)) return;
          if ($notify_flags & NOTIFY_ORDER_DECLINED)
             $email_template = ORDER_DECLINED_EMAIL;
       }
       else if ($new_status == $backorder_option) {
          if ($notify_flags & NOTIFY_BACK_ORDER)
             $email_template = BACK_ORDER_EMAIL;
       }
       if ($email_template != 0) {
          $email = new Email($email_template,array('order' => 'obj',
                                                   'order_obj' => $order));
          if (! $email->send()) {
             log_error($email->error);   http_response(422,$email->error);
             return false;
          }
          if (! empty($order->customer_id))
             write_customer_activity($email->activity,$order->customer_id,$db);
       }
       return true;
   }
}

function reorder_attributes_by_id($attributes,$product_id,$no_options,$db=null)
{
    if (! $db) $db = new DB;
    if ($no_options) {
       $query = 'select a.* from product_attributes p join attributes a on ' .
                'a.id=p.related_id where p.parent=? order by p.sequence';
       $query = $db->prepare_query($query,$product_id);
       $rows = $db->get_records($query);
       if (! empty($rows)) {
          $attr_array = array();
          $attributes = explode('|',$attributes);
          foreach ($rows as $index => $row) {
             if (($row['sub_product'] != 1) && ($row['dynamic'] != 1))
                continue;
             if (isset($attributes[$index]))
                $attr_array[$row['id']] = $attributes[$index];
             else $attr_array[$row['id']] = '';
          }
          ksort($attr_array);
          $attributes = implode('|',$attr_array);
       }
       return $attributes;
    }
    while (strpos($attributes,'--') !== false)
       $attributes = str_replace('--','-',$attributes);
    $attributes = str_replace('-',',',trim($attributes,'-'));
    $query = 'select o.id,o.parent,a.sub_product,a.dynamic from ' .
             'attribute_options o join attributes a on a.id=o.parent ' .
             'where o.id in (?) order by parent';
    $query = $db->prepare_query($query,explode(',',$attributes));
    $rows = $db->get_records($query);
    if (! empty($rows)) {
       $attr_array = array();
       foreach ($rows as $row) {
          if (($row['sub_product'] != 1) && ($row['dynamic'] != 1)) continue;
          $attr_array[] = $row['id'];
       }
       $attributes = implode('-',$attr_array);
    }
    return $attributes;
}

function check_low_quantity($order,$part_number)
{
    if ($order) {
       $products = array();
       foreach ($order->items as $id => $order_item) {
          if (! $order_item['product_id']) continue;
          $product_info = $order_item['product_id'].'|'.$order_item['attributes'];
          if (! isset($products[$product_info]))
             $products[$product_info] = true;
       }
       if (count($products) == 0) return;
       foreach ($products as $product_info => $flag) {
          $product_data = explode('|',$product_info);
          $query = 'select * from product_inventory where parent=?';
          if ($product_data[1]) {
             $query .= ' and attributes=?';
             $query = $order->db->prepare_query($query,$product_data[0],
                                                $product_data[1]);
          }
          else $query = $order->db->prepare_query($query,$product_data[0]);
          $rows = $order->db->get_records($query);
          if (! $rows) return;
          foreach ($rows as $row) {
             if ($row['qty'] < $row['min_qty']) {
                if (! class_exists('Email')) require_once '../engine/email.php';
                $email = new Email(LOW_QTY_ALERT_EMAIL,
                                   array('order' => 'obj','order_obj' => $order,
                                         'product' => $row['parent'],
                                         'inventory' => $row['id']));
                if (! $email->send()) log_error($email->error);
             }
          }
       }
    }
    else {
       $db = new DB;
       $query = 'select * from product_inventory where part_number=?';
       $query = $db->prepare_query($query,$part_number);
       $rows = $db->get_records($query);
       if (! $rows) return;
       foreach ($rows as $row) {
          if ($row['qty'] < $row['min_qty']) {
             if (! class_exists('Email')) require_once '../engine/email.php';
             $email = new Email(LOW_QTY_ALERT_EMAIL,
                                array('product' => $row['parent'],
                                      'inventory' => $row['id']));
             if (! $email->send()) log_error($email->error);
          }
       }
    }
}

function cancel_order_payment($db,$payment_info,$refund_amount,&$cancel_info,
                              &$error)
{
    if (empty($payment_info['payment_type'])) return true;
    $payment_module = $payment_info['payment_type'];
    if (! payment_module_event_exists('cancel_payment',$payment_module))
       return true;
    $cancel_function = $payment_module.'_cancel_payment';
    return $cancel_function($db,$payment_info,$refund_amount,$cancel_info,
                            $error);
}

function capture_order_payment($db,$payment_info,&$error)
{
    $payment_module = $payment_info['payment_type'];
    if (! payment_module_event_exists('capture_payment',$payment_module))
       return true;
    $capture_function = $payment_module.'_capture_payment';
    return $capture_function($db,$payment_info,$error);
}

function add_cancelled_payment($db,$payment_info,$refund_amount,$cancel_info,
                               &$error)
{
    global $login_cookie,$order_type;

    if (empty($payment_info['parent'])) {
       $error = 'No Order Found';   return false;
    }
    $order_id = $payment_info['parent'];
    $cancel_record = payment_record_definition();
    $cancel_record['parent']['value'] = $order_id;
    $cancel_record['parent_type']['value'] = $order_type;
    if ($payment_info['payment_status'] == PAYMENT_AUTHORIZED)
       $cancel_record['payment_status']['value'] = PAYMENT_VOIDED;
    else $cancel_record['payment_status']['value'] = PAYMENT_REFUNDED;
    $cancel_record['payment_date']['value'] = time();
    $admin_user = get_cookie($login_cookie);
    if ($admin_user) $cancel_record['payment_user']['value'] = $admin_user;
    $cancel_record['payment_type']['value'] = $payment_info['payment_type'];
    $cancel_record['payment_method']['value'] = $payment_info['payment_method'];
    $cancel_record['payment_amount']['value'] = -$refund_amount;
    $cancel_record['card_type']['value'] = $payment_info['card_type'];
    $cancel_record['card_number']['value'] = $payment_info['card_number'];
    foreach ($cancel_info as $cancel_field => $cancel_value) {
       if (isset($cancel_record[$cancel_field]))
          $cancel_record[$cancel_field]['value'] = $cancel_value;
    }
    if (! $db->insert('order_payments',$cancel_record)) {
       $error = $db->error;   return false;
    }
    return true;
}

if (! function_exists('log_payment')) {
   function log_payment($msg)
   {
       global $payment_log;
       global $login_cookie;
   
       $payment_file = fopen($payment_log,'at');
       if ($payment_file) {
          $remote_user = getenv('REMOTE_USER');
          if (! $remote_user) $remote_user = get_cookie($login_cookie);
          if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
             $remote_user = $_SERVER['REMOTE_ADDR'];
          fwrite($payment_file,$remote_user.' ['.date('D M d Y H:i:s').'] '.$msg."\n");
          fclose($payment_file);
       }
   }
}

function get_order_shipment_description($shipment_info)
{
    static $shipping_descriptions = array();

    if (! empty($shipment_info['shipping_carrier'])) {
       $shipping_carrier = $shipment_info['shipping_carrier'];
       if (empty($shipment_info['shipping_method'])) $shipping_method = '';
       else $shipping_method = $shipment_info['shipping_method'];
       $key = $shipping_carrier.'|'.$shipping_method;
       if (! isset($shipping_descriptions[$key])) {
          if (! shipping_module_event_exists('format_shipping_field',
                                            $shipping_carrier))
             $description = 'Shipping: Invalid Shipping Carrier (' .
                            $shipping_carrier.')';
          else {
             $format_shipping_field = $shipping_carrier .
                                      '_format_shipping_field';
             $description = 'Shipping: ' .
                $format_shipping_field($shipment_info,'shipping_method');
          }
          $shipping_descriptions[$key] = $description;
       }
       else $description = $shipping_descriptions[$key];
    }
    else if (! empty($shipment_info['shipping_method']))
       $description = 'Shipping: '.$shipment_info['shipping_method'];
    else $description = 'Shipping Charge';
    return $description;
}

function update_order_info($db,&$order_info,&$billing_info,&$shipping_info,
   &$order_items,&$order_payments,&$order_shipments)
{
    global $unit_field,$use_dynamic_images,$image_subdir_prefix;
    global $dynamic_image_url,$prefix;

    static $tax_rates = array();

    $features = get_cart_config_value('features',$db);
    if ((! empty($billing_info)) &&
        (! isset($billing_info['country'],$billing_info['country_name'],
                 $billing_info['state_name']))) {
       if (empty($billing_info['country'])) $billing_info['country'] = 1;
       $country_id = $billing_info['country'];
       $country_info = get_country_info($country_id,$db);
       if ($country_info) {
          $billing_info['country_code'] = $country_info['code'];
          $billing_info['country_name'] = $country_info['country'];
       }
       else {
          $billing_info['country_code'] = '';
          $billing_info['country_name'] = '';
       }
       if (($billing_info['country'] == 1) &&
           (! empty($billing_info['state']))) {
          $state = $billing_info['state'];
          $state_info = get_state_info($state,$db);
          if ($state_info) {
             $billing_info['state_name'] = $state_info['name'];
             $billing_info['state_tax'] = $state_info['tax'];
          }
          else $billing_info['state_name'] = '';
       }
       else {
          $state = null;   $state_info = null;
       }
    }
    else {
       $country_id = null;   $state = null;   $state_info = null;
    }
   
    if ((! empty($shipping_info)) &&
        (! isset($shipping_info['country'],$shipping_info['country_name'],
                 $shipping_info['state_name']))) {
       if (empty($shipping_info['country'])) $shipping_info['country'] = 1;
       if ($shipping_info['country'] != $country_id)
          $country_info = get_country_info($shipping_info['country'],$db);
       if ($country_info) {
          $shipping_info['country_code'] = $country_info['code'];
          $shipping_info['country_name'] = $country_info['country'];
       }
       else {
          $shipping_info['country_code'] = '';
          $shipping_info['country_name'] = '';
       }
       if ($shipping_info['country'] == 1) {
          if ((! empty($shipping_info['state'])) &&
              ($shipping_info['state'] != $state))
             $state_info = get_state_info($shipping_info['state'],$db);
          if ($state_info) {
             $shipping_info['state_name'] = $state_info['name'];
             $shipping_info['state_tax'] = $state_info['tax'];
          }
          else $shipping_info['state_name'] = '';
       }
    }

    if (! empty($shipping_info)) {
       if (! isset($shipping_info['profilename']))
          $shipping_info['profilename'] = 'Default';
       if (! isset($shipping_info['default_flag']))
          $shipping_info['default_flag'] = 1;
    }

    if (count($order_info) > 1) {
       if (empty($order_info['currency'])) $order_info['currency'] = 'USD';
       if (! isset($order_info['tax_rate'])) {
          if (isset($shipping_info['state_tax']))
             $order_info['tax_rate'] = $shipping_info['state_tax'];
          else {
             $tax_rate = 0;
             if (empty($shipping_info['country'])) $country = 1;
             else $country = $shipping_info['country'];
             if ($country == 1) {
                $state = get_row_value($shipping_info,'state');
                if ($state) {
                   if (! isset($tax_rates[$state])) {
                      $query = 'select tax from states where code=?';
                      $query = $db->prepare_query($query,$state);
                      $row = $db->get_record($query);
                      if ($row) $tax_rates[$state] = $row['tax'];
                      else $tax_rates[$state] = 0;
                   }
                   $tax_rate = $tax_rates[$state];
                }
             }
             $order_info['tax_rate'] = $tax_rate;
          }
       }
    }

    if (! empty($order_payments)) {
       $payment_info = reset($order_payments);
       foreach ($payment_info as $payment_field => $payment_value) {
          if (($payment_field == 'id') || ($payment_field == 'parent_type') ||
              ($payment_field == 'parent')) continue;
          $order_info[$payment_field] = $payment_value;
       }
    }

    if (! empty($order_shipments)) {
       foreach ($order_shipments as $index => $shipment_info) {
          $order_shipments[$index]['shipping_description'] =
             get_order_shipment_description($shipment_info);
       }
/* If the API does not include shipping info, this will overwrite the existing
   info from the first shipment, which may not be what is desired.  This should
   retrieving the existing order info and check for empty values there instead
   of what is passed in the order_info array.
       if (count($order_info) > 1) {
          $shipment_info = reset($order_shipments);
          foreach ($shipment_info as $shipment_field => $shipment_value) {
             if (($shipment_field == 'id') ||
                 ($shipment_field == 'parent_type') ||
                 ($shipment_field == 'parent')) continue;
             if (empty($order_info[$shipment_field]) && $shipment_value)
                $order_info[$shipment_field] = $shipment_value;
          }
       }
*/
    }
    else if (count($order_info) > 1)
       $order_info['shipping_description'] =
          get_order_shipment_description($order_info);

    if (count($order_info) > 1) {
       if (! empty($order_info['coupon_id'])) {
          $query = 'select coupon_code from coupons where id=?';
          $query = $db->prepare_query($query,$order_info['coupon_id']);
          $row = $db->get_record($query);
          if ($row) $order_info['coupon_code'] = $row['coupon_code'];
          else $order_info['coupon_code'] = '';
       }
       else $order_info['coupon_code'] = '';
       if (! empty($order_info['gift_id'])) {
          $query = 'select coupon_code from coupons where id=?';
          $query = $db->prepare_query($query,$order_info['gift_id']);
          $row = $db->get_record($query);
          if ($row) $order_info['gift_code'] = $row['coupon_code'];
          else $order_info['gift_code'] = '';
       }
       else $order_info['gift_code'] = '';
    }

    if ((! $order_items) || (count($order_items) == 0)) {
       if (function_exists('custom_update_order_info'))
          custom_update_order_info($db,$order_info,$billing_info,
             $shipping_info,$order_items,$order_payments,$order_shipments);
       return;
    }

    foreach ($order_items as $item_id => $order_item) {
       if (! isset($order_item['attributes'])) {
          $order_items[$item_id]['attributes'] = '';   $attributes = '';
       }
       else $attributes = $order_item['attributes'];
       if (! isset($order_item['part_number']))
          $order_items[$item_id]['part_number'] = '';
       $order_items[$item_id]['inventory_name'] = '';
       $order_items[$item_id]['full_inventory_name'] = '';
       $order_items[$item_id]['option_price'] = 0;
       $order_items[$item_id]['options'] = null;
       if (empty($order_item['product_id'])) {
          $order_items[$item_id]['vendor'] = null;
          $order_items[$item_id]['taxable'] = 0;
          $order_items[$item_id]['product_type'] = 0;
          $order_items[$item_id]['unit'] = 'EA';
          $order_items[$item_id]['inventory_id'] = null;
          if (! isset($order_item['cost']))
             $order_items[$item_id]['cost'] = $order_item['price'];
          continue;
       }
       $product_id = $order_item['product_id'];
       $query = 'select p.name,p.vendor,p.taxable,p.product_type';
       if (isset($unit_field)) $query .= ',p.'.$unit_field;
       if ($features & PRODUCT_COST_PRODUCT) $query .= ',p.cost';
       else if ($features & PRODUCT_COST_INVENTORY) $query .= ',pi.cost';
       $query .= ',pi.id,pi.part_number,pi.image from product_inventory pi ' .
                 'join products p on p.id=pi.parent where p.id=?';
       if (! $attributes) $lookup_attributes = null;
       else {
          $no_options = check_no_option_attributes($db,$product_id);
          if ($no_options) $attributes = explode('|',$attributes);
          else $attributes = explode('-',$attributes);
          $lookup_attributes = build_lookup_attributes($db,$product_id,
                                  $attributes,true,$no_options);
          $attribute_array = build_attribute_array($db,'order',$order_item);
          if ($no_options) $lookup_attr_array = explode('|',$lookup_attributes);
          else $lookup_attr_array = explode('-',$lookup_attributes);
          $inv_name = '';   $full_inv_name = '';   $option_price = 0;
          if ($attribute_array) {
             $option_ids = array();
             $attribute_names = '';   $attribute_prices = '';
             foreach ($attribute_array as $attr_info) {
                $attr_name = trim($attr_info['attr']);
                $option_name = trim($attr_info['option']);
                if ($attr_name || $option_name) {
                   if ($inv_name) {
                      $inv_name .= ', ';   $full_inv_name .= ', ';
                   }
                   if ($attr_name) {
                      $full_inv_name .= $attr_name;
                      if ($option_name) $full_inv_name .= ': ';
                   }
                   if ($option_name) {
                      $inv_name .= $option_name;
                      $full_inv_name .= $option_name;
                   }
                }
                if (in_array($attr_info['id'],$lookup_attr_array)) {
                   if ($attribute_names != '') $attribute_names .= '|';
                   if ($attribute_prices != '') $attribute_prices .= '|';
                   $attribute_names .= $attr_name;
                   $attribute_names .= '|'.$option_name;
                   $attribute_prices .= $attr_info['price'];
                }
                else {
                   $option_id = $attr_info['id'];
                   if (isset($option_id[0]) && ($option_id[0] == '*'))
                      continue;
                   if (! is_numeric($option_id)) continue;
                   $option_ids[] = $option_id;
                }
                $option_price += $attr_info['price'];
             }
             if (empty($order_item['attribute_names']) && $attribute_names) {
                $order_items[$item_id]['attribute_names'] = $attribute_names;
                $order_items[$item_id]['attribute_prices'] = $attribute_prices;
             }
             if (count($option_ids) > 0) {
                $option_query = 'select o.id,a.taxable from attributes a ' .
                   'left join attribute_options o on a.id=o.parent where ' .
                   'o.id in (?)';
                $option_query = $db->prepare_query($option_query,$option_ids);
                $option_rows = $db->get_records($option_query,'id','taxable');
                $options = array();
                foreach ($attribute_array as $attr_info) {
                   if (! in_array($attr_info['id'],$lookup_attr_array)) {
                      if ($option_rows &&
                          isset($option_rows[$attr_info['id']]))
                         $taxable = $option_rows[$attr_info['id']];
                      else $taxable = 1;
                      if (($taxable === '') || ($taxable === null))
                         $taxable = 1;
                   }
                   $attr_info['name'] = $attr_info['option'];
                   $attr_info['full_name'] = $attr_info['attr'].': ' .
                                             $attr_info['option'];
                   $options[] = $attr_info;
                }
                $order_items[$item_id]['options'] = $options;
             }
          }
          $order_items[$item_id]['inventory_name'] = $inv_name;
          $order_items[$item_id]['full_inventory_name'] = $full_inv_name;
          $order_items[$item_id]['option_price'] = $option_price;
       }
       if ($lookup_attributes) {
          $query .= ' and pi.attributes=?';
          $query = $db->prepare_query($query,$product_id,$lookup_attributes);
       }
       else {
          $query .= ' and ((pi.attributes="") or isnull(pi.attributes))';
          $query = $db->prepare_query($query,$order_item['product_id']);
       }
       $row = $db->get_record($query);
       if (! $row) {
          $order_items[$item_id]['vendor'] = null;
          $order_items[$item_id]['taxable'] = 0;
          $order_items[$item_id]['product_type'] = 0;
          $order_items[$item_id]['unit'] = 'EA';
          $order_items[$item_id]['inventory_id'] = null;
          if (! isset($order_item['cost']))
             $order_items[$item_id]['cost'] = $order_item['price'];
          continue;
       }
       if (empty($order_item['product_name']))
          $order_items[$item_id]['product_name'] = $row['name'];
       $order_items[$item_id]['vendor'] = $row['vendor'];
       if (! $order_items[$item_id]['part_number'])
          $order_items[$item_id]['part_number'] = $row['part_number'];
       $order_items[$item_id]['taxable'] = $row['taxable'];
       $order_items[$item_id]['product_type'] = $row['product_type'];
       if (isset($unit_field))
          $order_items[$item_id]['unit'] = $row['unit'];
       else $order_items[$item_id]['unit'] = 'EA';
       $order_items[$item_id]['inventory_id'] = $row['id'];
       if ((! isset($order_item['cost'])) && isset($row['cost']))
          $order_items[$item_id]['cost'] = $row['cost'];
       if (! empty($row['image'])) $image_filename = $row['image'];
       else {
          $query = 'select filename from images where (parent_type=1) and ' .
                   '(parent=?) order by sequence limit 1';
          $query = $db->prepare_query($query,$product_id);
          $image_row = $db->get_record($query);
          if (! $image_row) $image_filename = null;
          else $image_filename = $image_row['filename'];
       }
       if (! $image_filename) $image_url = null;
       else if ($use_dynamic_images) {
          if (isset($dynamic_image_url))
             $image_url = $dynamic_image_url.'?cmd=loadimage&filename=' .
                          rawurlencode($image_filename).'&size=small';
          else $image_url = 'cartengine/image.php?cmd=loadimage&filename=' .
                            rawurlencode($image_filename).'&size=small';
       }
       else if ($image_subdir_prefix)
          $image_url = $prefix.'/images/small/' .
             substr($image_filename,0,$image_subdir_prefix).'/' .
             rawurlencode($image_filename);
       else $image_url = $prefix.'/images/small/' .
                         rawurlencode($image_filename);
       if ($image_url) $order_items[$item_id]['image'] = $image_url;
       if ($image_filename)
          $order_items[$item_id]['image_filename'] = $image_filename;
    }

    if (function_exists('custom_update_order_info'))
       custom_update_order_info($db,$order_info,$billing_info,$shipping_info,
          $order_items,$order_payments,$order_shipments);
}

function update_vendor_order_info(&$order,$vendor_id)
{
    if (! isset($order->items)) return;

    $vendor_payments = array();   $vendor_shipments = array();
    update_order_info($order->db,$order->info,$order->billing,$order->shipping,
                      $order->items,$vendor_payments,$vendor_shipments);
    $tax_rate = $order->info['tax_rate'];

    $subtotal = 0;   $taxable_subtotal = 0;
    foreach ($order->items as $item_id => $order_item) {
       if (! $order_item['product_id']) {
          unset($order->items[$item_id]);   continue;
       }
       if ($order_item['vendor'] != $vendor_id) {
          unset($order->items[$item_id]);   continue;
       }
       if ($order_item['cost']) {
          $order->items[$item_id]['price'] = $order_item['cost'];
          $order_item['price'] = $order_item['cost'];
       }
       $line_item_price = $order_item['price'] * $order_item['qty'];
       $subtotal += $line_item_price;
       if ($order_item['taxable']) $taxable_subtotal += $line_item_price;
    }
    $order->info['subtotal'] = $subtotal;
    if ($tax_rate) {
       $tax = round($taxable_subtotal * ($tax_rate / 100),2);
       $order->info['tax'] = $tax;
       $order->info['total'] = $subtotal + $tax;
    }
    else {
       $order->info['tax'] = 0;
       $order->info['total'] = $subtotal;
    }
    if (function_exists('custom_update_vendor_order_info'))
       custom_update_vendor_order_info($order,$vendor_id);
}

function send_vendor_emails($db,$vendor_ids,$order_items,&$error)
{
    global $template_prefixes;

    $query = 'select * from vendors where id in (?)';
    $query = $db->prepare_query($query,$vendor_ids);
    $vendors = $db->get_records($query);
    if (! $vendors) {
       if (isset($db->error)) $error = 'Database Error: '.$db->error;
       else $error = 'Vendors not found';
       return false;
    }

    if (file_exists('../engine/email.php'))
       require_once '../engine/email.php';
    else require_once 'engine/email.php';

    foreach ($vendors as $vendor_info) {
       if (! $vendor_info['submit_email']) continue;
       $vendor_id = $vendor_info['id'];
       $vendor_orders = array();
       foreach ($order_items as $row) {
          if (($row['vendor'] == $vendor_id) &&
              (! in_array($row['id'],$vendor_orders)))
             $vendor_orders[] = $row['id'];
       }
       if (count($vendor_orders) == 0) continue;

       foreach ($vendor_orders as $order_id) {
          $order = load_order($db,$order_id,$error_msg);
          if (! $order) {
             if (isset($db->error)) $error = 'Database Error: '.$db->error;
             else $error = $error_msg;
             return false;
          }
          update_vendor_order_info($order,$vendor_id);
          $email = new Email(VENDOR_ORDER_EMAIL,array('order' => 'obj',
                             'order_obj' => $order,'vendor' => 'obj',
                             'vendor_obj' => $vendor_info));
          if (! $email->send()) {
             log_error($email->error);   $error = $email->error;
             return false;
          }
          if ($vendor_info['sent_status']) {
             $new_status = $vendor_info['sent_status'];
             $query = 'update orders set status=?,updated_date=? where id=?';
             $query = $db->prepare_query($query,$new_status,time(),$order_id);
             $db->log_query($query);
             if (! $db->query($query)) return;
          }
       }
    }

    return true;
}

function upload_vendor_orders($db,$vendor_ids,$order_items,&$error)
{
    $query = 'select * from vendors where id in (?)';
    $query = $db->prepare_query($query,$vendor_ids);
    $vendors = $db->get_records($query);
    if (! $vendors) {
       if (isset($db->error)) $error = 'Database Error: '.$db->error;
       else $error = 'Vendors not found';
       return false;
    }

    foreach ($vendors as $vendor_info) {
       if (empty($vendor_info['module'])) continue;
       $vendor_id = $vendor_info['id'];
       $vendor_orders = array();
       foreach ($order_items as $row) {
          if (($row['vendor'] == $vendor_id) &&
              (! in_array($row['id'],$vendor_orders)))
             $vendor_orders[] = $row['id'];
       }
       if (count($vendor_orders) == 0) continue;

       $orders = array();
       foreach ($vendor_orders as $order_id) {
          $order = load_order($db,$order_id,$error_msg);
          if (! $order) {
             if (isset($db->error)) $error = 'Database Error: '.$db->error;
             else $error = $error_msg;
             return false;
          }
          update_vendor_order_info($order,$vendor_id);
          $orders[] = $order;
       }
       if (! call_vendor_event($vendor_info,'upload_orders',
                               array($db,$vendor_info,&$orders))) return;
       if ($vendor_info['sent_status']) {
          $new_status = $vendor_info['sent_status'];
          $query = 'update orders set status=?,updated_date=? where id in (?)';
          $query = $db->prepare_query($query,$new_status,time(),$vendor_orders);
          $db->log_query($query);
          if (! $db->query($query)) return;
       }
    }

    return true;
}

function send_to_vendors($order_id=null)
{
    require_once 'vendors-common.php';

    $db = new DB;
    if ($order_id) {
       $order_ids = $order_id;   $manual = false;
    }
    else {
       $order_ids = get_form_field('ids');   $manual = true;
    }
    $log_orders = $order_ids;
    $order_ids = explode(',',$order_ids);
    if (count($order_ids) == 1) $log_orders = 'Order #'.$log_orders;
    else $log_orders = 'Order #s '.$log_orders;
    $query = 'select o.id,p.id as product_id,p.vendor,v.new_order_flag,' .
             'v.send_order_flag from orders o left join order_items i on ' .
             'i.parent=o.id left join products p on p.id=i.product_id left ' .
             'join vendors v on v.id=p.vendor where o.id in (?)';
    $query = $db->prepare_query($query,$order_ids);
    $order_items = $db->get_records($query);
    if (! $order_items) {
       if (isset($db->error)) {
          $error = 'Database Error: '.$db->error;
          if ($manual) http_response(422,$error);
          return $error;
       }
       else if ($manual) {
          http_response(410,'No Products found in '.$log_orders);
          return;
       }
       return null;
    }

    $edi_orders = array();   $email_vendors = array();
    $edi_vendors = array();   $upload_vendors = array();
    foreach ($order_items as $row) {
       if (! $row['new_order_flag']) continue;
       if (! $row['vendor']) continue;
       if ($manual && ($row['send_order_flag'] == SEND_ORDER_AUTO)) continue;
       if ((! $manual) && ($row['send_order_flag'] != SEND_ORDER_AUTO))
          continue;
       if ($row['new_order_flag'] == SEND_ORDER_BY_EMAIL) {
          if (! in_array($row['vendor'],$email_vendors))
             $email_vendors[] = $row['vendor'];
       }
       else if ($row['new_order_flag'] == SEND_ORDER_BY_EDI) {
          if (! in_array($row['id'],$edi_orders))
             $edi_orders[] = $row['id'];
          if (! in_array($row['vendor'],$edi_vendors))
             $edi_vendors[] = $row['vendor'];
       }
       else if ($row['new_order_flag'] == SEND_ORDER_BY_UPLOAD) {
          if (! in_array($row['vendor'],$email_vendors))
             $upload_vendors[] = $row['vendor'];
       }
    }
    if ((count($email_vendors) == 0) && (count($edi_orders) == 0) &&
        (count($upload_vendors) == 0)) {
       if ($manual)
          http_response(410,'No Products found in '.$log_orders .
                        ' to send to vendors');
       return null;
    }

    if (count($email_vendors) > 0) {
       if (! send_vendor_emails($db,$email_vendors,$order_items,$error)) {
          if ($manual) http_response(422,$error);
          return $error;
       }
    }

    if (count($upload_vendors) > 0) {
       if (! upload_vendor_orders($db,$upload_vendors,$order_items,$error)) {
          if ($manual) http_response(422,$error);
          return $error;
       }
    }

    if (count($edi_orders) > 0) {
       $command = 'edi.php sendorders '.implode(',',$edi_orders).' ' .
                  implode(',',$edi_vendors);
       $process = new Process($command);
       if ($process->return != 0) {
          $error = 'Unable to start EDI module';   log_error($error);
          if ($manual) http_response(422,$error);
          return $error;
       }
       $counter = 0;
       while ($process->status()) {
          if ($counter == 900) {
             $process->stop();
             $error = 'EDI Send Orders took too long';   log_error($error);
             if ($manual) http_response(422,$error);
             return $error;
          }
          sleep(1);   $counter++;
       }
    }

    if ($manual) http_response(201,'Send Orders to Vendors Completed');
    log_activity('Sent '.$log_orders.' to Vendors');
    return null;
}

function taxable_shipping($obj)
{
    global $taxable_shipping;

    if (! isset($taxable_shipping)) {
       if (file_exists('../admin/modules/avalara.php')) {
          require_once '../admin/modules/avalara.php';
          $avalara_api_type = get_avalara_api_type($obj);
          if ($avalara_api_type == AVATAX_TRANSACTION)
             $taxable_shipping = true;
       }
    }
    return $taxable_shipping;
}

?>
