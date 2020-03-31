<?php
/*
                     Inroads Shopping Cart - PayPal API Module

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

global $paypal_button;
if (! isset($paypal_button))
   $paypal_button = 'https://www.paypal.com/en_US/i/btn/btn_xpressCheckout.gif';
global $using_paypal_express,$show_paypal_express;
$using_paypal_express = false;
$show_paypal_express = false;

function paypal_payment_cart_config_section($db,$dialog,$values)
{
    global $payment_modules;

    $primary_module = call_payment_event('get_primary_module',array($db),
                                         true,true);

    add_payment_section($dialog,'PayPal','paypal',$values);

    $dialog->add_edit_row('API Username:','paypal_username',$values,50);
    $dialog->add_edit_row('API Password:','paypal_password',$values,30);
    $dialog->add_edit_row('Signature:','paypal_signature',$values,70);
    $dialog->add_edit_row('API Hostname:','paypal_api_hostname',$values,30);
    $dialog->add_edit_row('Checkout Hostname:','paypal_checkout_hostname',
                          $values,30);
    if (empty($values['paypal_action']))
       $values['paypal_action'] = 'Sale';
    $dialog->start_row('Payment Action:','middle');
    $dialog->add_radio_field('paypal_action','Sale',
                             'Authorize and Capture (Sale)',$values);
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('paypal_action','Authorization','Authorize Only',
                             $values);
    $dialog->end_row();
    $dialog->add_edit_row('Default Shipping Amount','paypal_shipping',
                          $values,10,'$','(for Express Checkout only)');
    $paypal_options = get_row_value($values,'paypal_options');
    $dialog->start_row('Payment Options:','middle');
    if ((! $primary_module) || ($primary_module == 'paypal')) {
       $dialog->add_checkbox_field('paypal_option_1','Direct Payment',
                                   $paypal_options & 1);
       $dialog->write('&nbsp;&nbsp;&nbsp;');
    }
    $dialog->add_checkbox_field('paypal_option_2','Express Checkout',
                                $paypal_options & 2);
    $dialog->end_row();
}

function paypal_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('paypal_username','paypal_password','paypal_signature',
       'paypal_api_hostname','paypal_checkout_hostname','paypal_action',
       'paypal_shipping','paypal_options');
    if (! empty($website_settings)) $fields[] = 'paypal_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function paypal_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'paypal_options') {
       $new_field_value = 0;
       if (get_form_field('paypal_option_1') == 'on') $new_field_value |= 1;
       if (get_form_field('paypal_option_2') == 'on') $new_field_value |= 2;
    }
    else if ($field_name == 'paypal_active') {
       if (get_form_field('paypal_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function paypal_active($db)
{
    return get_cart_config_value('paypal_active',$db);
}

function paypal_get_primary_module($db)
{
    $paypal_options = get_cart_config_value('paypal_options',$db);
    if (! ($paypal_options & 1)) return null;
    return 'paypal';
}

function call_paypal($post_array,&$error,$prefix='')
{
    if ($prefix) $post_array['Version'] = '121';
    else $post_array['Version'] = '3.2';
    $payment_action = get_cart_config_value('paypal_action');
    if (! $payment_action) $payment_action = 'Sale';
    $post_array[$prefix.'PaymentAction'] = $payment_action;
    $post_array['User'] = get_cart_config_value('paypal_username');
    $post_array['Pwd'] = get_cart_config_value('paypal_password');
    $post_array['Signature'] = get_cart_config_value('paypal_signature');

    $post_string = '';
    foreach ($post_array as $key => $value) {
       if ($post_string != '') $post_string .= '&';
       $post_string .= $key.'='.urlencode($value);
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if (($key == 'acct') || ($key == 'expdate') || ($key == 'cvv2') ||
           ($key == 'user') || ($key == 'pwd') || ($key == 'signature') ||
           ($key == 'Acct') || ($key == 'ExpDate') || ($key == 'Cvv2'))
          continue;
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order'))
       @Order::log_payment('PayPal Sent: '.$log_string);
    else log_payment('PayPal Sent: '.$log_string);

    $host = get_cart_config_value('paypal_api_hostname');
    if (! $host) {
       $error = 'Missing PayPal API Hostname';
       if (class_exists('Order'))
          @Order::log_payment('PayPal Error: '.$error);
       else log_payment('PayPal Error: '.$error);
       log_activity('PayPal Error: '.$error);   return null;
    }
    $url = 'https://'.$host.'/nvp';
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('PayPal Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('PayPal Error: '.$error);
       else log_payment('PayPal Error: '.$error);
       return null;
    }
    $response_data = str_replace("\n",'',$response_data);
    $response_data = str_replace("\r",'',$response_data);

    if (class_exists('Order'))
       @Order::log_payment('PayPal Response: '.$response_data);
    else log_payment('PayPal Response: '.$response_data);
    $paypal_result = array();
    $result_array = explode('&',$response_data);
    foreach ($result_array as $field_data) {
       $field_pair = explode('=',$field_data);
       $paypal_result[$field_pair[0]] = urldecode($field_pair[1]);
    }

    return $paypal_result;
}

function set_order_info(&$post_array,$order,$express_flag)
{
    $currency = $order->info['currency'];
    if ($currency == 'HUF') $precision = 0;
    else $precision = 2;
    if ($express_flag) $prefix = 'PAYMENTREQUEST_0_';
    else $prefix = '';
    $billing_country_info = get_country_info($order->billing_country,$order->db);
    $shipping_country_info = get_country_info($order->shipping_country,$order->db);
    if (isset($order->customer->shipping,$order->customer->shipping['shipto'])) {
       $shipto = $order->customer->shipping['shipto'];
       if ((! $shipto) && isset($order->customer->info,$order->customer->info['lname']))
          $shipto = $order->customer->info['fname'].' '.$order->customer->info['lname'];
    }
    else $shipto = '';

    if (! empty($order->payment['payment_amount']))
       $payment_amount = $order->payment['payment_amount'];
    else if (! empty($order->cart->info['total']))
       $payment_amount = $order->cart->info['total'];
    else $payment_amount = 0;

    if ($order->partial_payment || $order->balance_payment) {
       $item_amount = $payment_amount;   $tax = 0;   $shipping_amount = 0;
    }
    else {
       if (isset($order->info['tax'])) $tax = $order->info['tax'];
       else $tax = 0;
       if ($tax != 0)
          $tax_rate = load_state_tax($order->customer->get('bill_state'),$order->db);
       if (isset($order->info['subtotal'])) $item_amount = $order->info['subtotal'];
       else if (isset($order->cart->info['subtotal']))
          $item_amount = $order->cart->info['subtotal'];
       else $item_amount = 0;
       if (isset($order->info['coupon_amount']) && $order->info['coupon_amount']) {
          $coupon_amount = $order->info['coupon_amount'];
          $item_amount -= $coupon_amount;
       }
       else $coupon_amount = 0;
       if (isset($order->info['gift_amount']) && $order->info['gift_amount']) {
          $gift_amount = $order->info['gift_amount'];
          $item_amount -= $gift_amount;
       }
       else $gift_amount = 0;
       if (isset($order->info['discount_amount']) &&
                 $order->info['discount_amount']) {
          $discount_amount = $order->info['discount_amount'];
          $item_amount -= $discount_amount;
       }
       else $discount_amount = 0;
       if (isset($order->info['fee_amount']) && $order->info['fee_amount']) {
          $fee_amount = $order->info['fee_amount'];
          $item_amount += $fee_amount;
       }
       else $fee_amount = 0;
       if (isset($order->info['shipping']))
          $shipping_amount = $order->info['shipping'];
       else $shipping_amount = 0;
       if ($tax != 0) {
          if (! $coupon_amount) $coupon_tax = 0;
          else $coupon_tax = -round($coupon_amount * ($tax_rate / 100),$precision);
          if (! $discount_amount) $discount_tax = 0;
          else $discount_tax = -round($discount_amount * ($tax_rate / 100),$precision);
          $total_tax = $coupon_tax + $discount_tax;
       }
       else {
          $coupon_tax = 0;   $discount_tax = 0;
       }
    }

    if ($currency == 'HUF') {
       $payment_amount = round($payment_amount,0);
       $item_amount = round($item_amount,0);
       if ((! $order->partial_payment) && (! $order->balance_payment)) {
          $tax = round($tax,0);
          $shipping_amount = round($shipping_amount,0);
          $coupon_amount = round($coupon_amount,0);
          $gift_amount = round($gift_amount,0);
          $discount_amount = round($discount_amount,0);
          $fee_amount = round($fee_amount,0);
       }
    }

    $post_array['IpAddress'] = $_SERVER['REMOTE_ADDR'];
    $post_array[$prefix.'ItemAmt'] =
       number_format($item_amount,$precision,'.','');
    if ($shipping_amount)
       $post_array[$prefix.'ShippingAmt'] = $shipping_amount;
    $post_array[$prefix.'TaxAmt'] = $tax;
    $post_array[$prefix.'Amt'] = number_format($payment_amount,$precision,'.','');
    $post_array[$prefix.'CurrencyCode'] = $currency;
    if (isset($order->info['order_number']))
       $post_array['InvNum'] = $order->info['order_number'];
    if (isset($order->customer->info,$order->customer->info['lname'])) {
       $post_array[$prefix.'FirstName'] = $order->customer->info['fname'];
       $post_array[$prefix.'LastName'] = $order->customer->info['lname'];
       $post_array[$prefix.'Street'] = $order->customer->billing['address1'];
       $post_array[$prefix.'Street2'] = $order->customer->billing['address2'];
       $post_array[$prefix.'City'] = $order->customer->billing['city'];
       $post_array[$prefix.'State'] = $order->customer->get('bill_state');
       $post_array[$prefix.'Zip'] = $order->customer->billing['zipcode'];
       $post_array[$prefix.'CountryCode'] = $billing_country_info['code'];
       $post_array[$prefix.'Country'] = $billing_country_info['country'];
       $post_array[$prefix.'Email'] = $order->customer->info['email'];
       $post_array[$prefix.'PhoneNum'] = $order->customer->billing['phone'];
       $post_array[$prefix.'ShipToName'] = $shipto;
       $post_array[$prefix.'ShipToStreet'] = $order->customer->shipping['address1'];
       $post_array[$prefix.'ShipToStreet2'] = $order->customer->shipping['address2'];
       $post_array[$prefix.'ShipToCity'] = $order->customer->shipping['city'];
       $post_array[$prefix.'ShipToState'] = $order->customer->get('ship_state');
       $post_array[$prefix.'ShipToZip'] = $order->customer->shipping['zipcode'];
       $post_array[$prefix.'ShipToCountryCode'] = $shipping_country_info['code'];
       $post_array[$prefix.'ShipToPhoneNum'] = $order->customer->billing['phone'];
    }

    if (($order->partial_payment || $order->balance_payment)) {
       if ($order->partial_payment) {
          if (isset($order->id)) $name = 'Partial Payment for Order #'.$order->id;
          else $name = 'Partial Payment';
       }
       else if (isset($order->id)) $name = 'Balance Payment for Order #'.$order->id;
       else $name = 'Balance Payment';
       $post_array['L_'.$prefix.'Qty0'] = 1;
       $post_array['L_'.$prefix.'Name0'] = $name;
       $post_array['L_'.$prefix.'Amt0'] = $payment_amount;
       $post_array['L_'.$prefix.'TaxAmt0'] = 0;
       return;
    }

    foreach ($order->items as $id => $cart_item) {
       $item_total = get_item_total($cart_item,false);
       $order->items[$id]['item_total'] = $item_total;
       if ($tax != 0) {
          $item_tax = round($item_total * ($tax_rate / 100),2);
          if ($currency == 'HUF') $item_tax = round($item_tax,0);
          $total_tax += $item_tax;
       }
       else $item_tax = 0;
       $order->items[$id]['item_tax'] = $item_tax;
    }
    if (($tax != 0) && ($total_tax != $tax)) $tax_diff = $tax - $total_tax;
    else $tax_diff = 0;

    $index = 0;
    foreach ($order->items as $id => $cart_item) {
       $item_description = get_html_product_name($cart_item['product_name'],
                                                 GET_PROD_PAYMENT_GATEWAY,
                                                 $order,$cart_item);
       $attr_array = $cart_item['attribute_array'];
       if (isset($attr_array) && (count($attr_array) > 0)) {
          $item_description .= ' - ';
          foreach ($attr_array as $attr_index => $attribute) {
             if ($attr_index > 0) $item_description .= ', ';
             $item_description .= $attribute['attr'].': '.$attribute['option'];
          }
       }
       if ($tax_diff) $cart_item['item_tax'] = 0;
       $item_total = $cart_item['item_total'];
       if ($currency == 'HUF') $item_total = round($item_total,0);
       $post_array['L_'.$prefix.'Number'.$index] = $cart_item['product_id'];
       $post_array['L_'.$prefix.'Qty'.$index] = $cart_item['qty'];
       $post_array['L_'.$prefix.'Name'.$index] = strip_tags($item_description);
       $post_array['L_'.$prefix.'Amt'.$index] = $item_total;
       $post_array['L_'.$prefix.'TaxAmt'.$index] = $cart_item['item_tax'];
       $index++;
    }
    if ($coupon_amount) {
       if ($tax_diff) $coupon_tax = 0;
       $post_array['L_'.$prefix.'Number'.$index] = $order->info['coupon_id'];
       $post_array['L_'.$prefix.'Qty'.$index] = 1;
       $post_array['L_'.$prefix.'Name'.$index] = 'Coupon ' .
                                                 $order->info['coupon_code'];
       $post_array['L_'.$prefix.'Amt'.$index] = -$coupon_amount;
       $post_array['L_'.$prefix.'TaxAmt'.$index] = $coupon_tax;
       $index++;
    }
    if ($gift_amount) {
       $post_array['L_'.$prefix.'Number'.$index] = $order->info['gift_id'];
       $post_array['L_'.$prefix.'Qty'.$index] = 1;
       $post_array['L_'.$prefix.'Name'.$index] = 'Gift Certificate ' .
                                      $order->info['gift_code'];
       $post_array['L_'.$prefix.'Amt'.$index] = -$gift_amount;
       $post_array['L_'.$prefix.'TaxAmt'.$index] = 0;
       $index++;
    }
    if ($discount_amount) {
       if ($tax_diff) $discount_tax = 0;
       $post_array['L_'.$prefix.'Qty'.$index] = 1;
       $post_array['L_'.$prefix.'Name'.$index] =
          strip_tags($order->info['discount_name']);
       $post_array['L_'.$prefix.'Amt'.$index] = -$discount_amount;
       $post_array['L_'.$prefix.'TaxAmt'.$index] = $discount_tax;
       $index++;
    }
    if ($fee_amount) {
       $post_array['L_'.$prefix.'Qty'.$index] = 1;
       $post_array['L_'.$prefix.'Name'.$index] =
          strip_tags($order->info['fee_name']);
       $post_array['L_'.$prefix.'Amt'.$index] = $fee_amount;
       $post_array['L_'.$prefix.'TaxAmt'.$index] = 0;
       $index++;
    }
    if ($tax_diff) {
       $post_array['L_'.$prefix.'Qty'.$index] = 1;
       $post_array['L_'.$prefix.'Name'.$index] = 'Tax';
       $post_array['L_'.$prefix.'Amt'.$index] = 0;
       $post_array['L_'.$prefix.'TaxAmt'.$index] = $tax;
       $index++;
    }
}

function load_paypal_shipping_modules(&$order)
{
    global $shipping_modules;

    if ($shipping_modules === null) load_shipping_modules();
    if (function_exists('check_shipping_module')) {
       if ($order instanceof Cart) {
          $cart = $order;   $customer = $cart->customer;
       }
       else {
          $cart = $order->cart;   $customer = $order->customer;
       }
       if (! empty($shipping_modules))
          $cart->shipping_module = reset($shipping_modules);
       $null_order = null;
       check_shipping_module($cart,$customer,$null_order);
    }
}

function build_paypal_shipping_method($shipping_option,&$shipping_carrier,
                                      $combine_flag=false)
{
    static $shipping_module_labels = null;

    if ($shipping_module_labels === null) {
       $shipping_module_labels = array();
       call_shipping_event('module_labels',array(&$shipping_module_labels));
    }
    $shipping_carrier = $shipping_module_labels[$shipping_option[0]];
    if ($shipping_carrier == 'Manual') $shipping_carrier = 'Standard';
    else if ($shipping_carrier == 'Endicia') $shipping_carrier = 'USPS';
    $shipping_method = $shipping_option[3];
    $carrier_prefix = $shipping_carrier.' ';
    $prefix_length = strlen($carrier_prefix);
    if (substr($shipping_method,0,$prefix_length) == $carrier_prefix)
       $shipping_method = substr($shipping_method,$prefix_length);
    if (! $shipping_method) $shipping_method = 'Shipping';
    if ($combine_flag) $shipping_method = $carrier_prefix.$shipping_method;
    return $shipping_method;
}

function process_paypal_checkout(&$order)
{
    global $ssl_url,$prefix;

    if (isset($order->customer->billing['country']))
       $order->billing_country = $order->customer->billing['country'];
    else $order->billing_country = 1;
    if (isset($order->customer->shipping['country']))
       $order->shipping_country = $order->customer->shipping['country'];
    else $order->shipping_country = 1;
    if (function_exists('custom_process_paypal_checkout')) {
       if (! custom_process_paypal_checkout($order)) return;
    }

    if (function_exists('get_custom_cart_prefix'))
       $cart_prefix = get_custom_cart_prefix();
    else $cart_prefix = '';
    $returnurl = $ssl_url.$cart_prefix.'cart/'.$order->checkout_module .
                 '?ContinuePayPal=';
    if (isset($order->cart_paypal_express)) {
       $returnurl .= 'Cart';   $cancel_module = 'index.php';
    }
    else {
       $returnurl .= 'Go';   $cancel_module = $order->checkout_module;
    }
    $cancelurl = $ssl_url.$cart_prefix.'cart/'.$cancel_module .
                 '?CancelPayPal=Go';
    $shipping_method = get_form_field('shipping_method');
    if ($shipping_method)
       $shipping_method = str_replace('|','~',$shipping_method);
    if (isset($order->id)) $order_id = $order->id;
    else $order_id = '';
    if (isset($order->cart) && (! button_pressed('PayPalExpress'))) {
       if (! $order->cart->save_cart_data()) {
          $order->error = $order->db->error;
          $order->errors['CardFailed'] = true;
          return;
       }
       $custom_data = $order->cart->id;
    }
    else $custom_data = $order_id;
    $site_name = get_cart_config_value('companyname');
    $shipping_country_info = get_country_info($order->shipping_country,$order->db);
    if (! empty($order->payment['payment_amount']))
       $payment_amount = $order->payment['payment_amount'];
    else if (isset($order->cart->info['total']))
       $payment_amount = $order->cart->info['total'];
    else $payment_amount = 0;
    $payment_amount += 100;
    if ($order->info['currency'] == 'HUF') {
       $payment_amount = round($payment_amount,0);   $precision = 0;
    }
    else $precision = 2;
    $total = number_format($payment_amount,$precision,'.','');
    if (isset($order->customer->info['email']))
       $order_email = $order->customer->info['email'];
    else $order_email = '';
    $logo = get_cart_config_value('companylogo');
    if ($logo) {
       if (substr($logo,0,1) == '/') $logo = substr($logo,1);
       $logo = $ssl_url.$logo;
    }
    if (isset($order->cart_paypal_express)) {
       $order->cart->calculate_tax($order->customer);
       if (! empty($order->cart->info['tax']))
          $order->info['tax'] = $order->cart->info['tax'];
       load_paypal_shipping_modules($order);
       $order->cart->load_shipping_options($order->customer);
       $lowest_index = -1;
       foreach ($order->cart->shipping_options as $index => $shipping_option) {
          if ($lowest_index == -1) {
             $lowest_index = $index;   $lowest_amount = $shipping_option[2];
          }
          else if ($shipping_option[2] < $lowest_amount) {
             $lowest_index = $index;   $lowest_amount = $shipping_option[2];
          }
       }
       if ($lowest_index != -1) {
          $shipping_option = $order->cart->shipping_options[$lowest_index];
          $shipping_option_name = build_paypal_shipping_method($shipping_option,
             $shipping_carrier,true);
          $shipping_option_amount = $lowest_amount;
       }
       else {
          $shipping_option_name = 'Default Shipping';
          $paypal_shipping = get_cart_config_value('paypal_shipping',
                                                   $order->db);
          if ($paypal_shipping)
             $shipping_option_amount = floatval($paypal_shipping);
          else $shipping_option_amount = 0;
       }
       if ($shipping_option_amount) {
          $order->cart->info['total'] += $shipping_option_amount;
          $order->info['shipping'] = $shipping_option_amount;
          $payment_amount += $shipping_option_amount;
       }
/*
       if ((! empty($shipping_modules)) &&
           in_array('manual',$shipping_modules)) {
          $shipping_option_name = get_cart_config_value('manual_label',
                                                        $order->db);
          $shipping_option_amount = get_cart_config_value('manual_handling',
                                                          $order->db);
          if ($shipping_option_amount) {
             $order->cart->info['total'] += $shipping_option_amount;
             $order->info['shipping'] = $shipping_option_amount;
             $payment_amount += $shipping_option_amount;
          }
          else {
             $shipping_option_name = 'Default Shipping';
             $shipping_option_amount = 0;
          }
       }
       else {
          $shipping_option_name = 'Default Shipping';
          $shipping_option_amount = 0;
       }
*/
    }
    $post_array = array('Method' => 'SetExpressCheckout',
                        'MaxAmt' => $total,
                        'LocaleCode' => $shipping_country_info['code'],
                        'Desc' => $site_name.' Shopping Cart Order',
                        'ReturnURL' => $returnurl,
                        'CancelURL' => $cancelurl,
                        'SolutionType' => 'Sole',
                        'PAYMENTREQUEST_0_CUSTOM' => $custom_data);
    if ($logo) $post_array['LogoImg'] = $logo;
    if ($order_email) $post_array['Email'] = $order_email;
    if (isset($order->cart_paypal_express)) {
       $callbackurl = $ssl_url.$cart_prefix.'cart/'.$order->checkout_module .
                     '/paypalcallback/'.$order->cart->id;
       $post_array['Callback'] = $callbackurl;
       $post_array['CallbackVersion'] = 61;
       $post_array['CallbackTimeout'] = 6;
       $post_array['L_SHIPPINGOPTIONISDEFAULT0'] = 'true';
       $post_array['L_SHIPPINGOPTIONNAME0'] = $shipping_option_name;
       $post_array['L_SHIPPINGOPTIONAMOUNT0'] = $shipping_option_amount;
       $post_array['PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED'] = 'false';
       $post_array['ALLOWNOTE'] = 0;
    }

    set_order_info($post_array,$order,true);

    $paypal_result = call_paypal($post_array,$error,'PAYMENTREQUEST_0');
    if (! $paypal_result) {
       $order->error = $error;   $order->errors['CardFailed'] = true;   return;
    }
    if ((! isset($paypal_result['ACK'])) || ($paypal_result['ACK'] != 'Success')) {
       $order->error = 'PayPal Error: '.$paypal_result['ACK'].' - ' .
                       $paypal_result['L_SHORTMESSAGE0'].' ('.
                       $paypal_result['L_LONGMESSAGE0'].')';
       log_error($order->error);   $order->errors['CardFailed'] = true;
       return;
    }
    $token = $paypal_result['TOKEN'];
    $host = get_cart_config_value('paypal_checkout_hostname');
    header('Location: https://'.$host.'/webscr&cmd=_express-checkout&token='.$token);
    exit;
}

function process_paypal_callback(&$order)
{
    if (isset($order->customer->billing['country']))
       $order->billing_country = $order->customer->billing['country'];
    else $order->billing_country = 1;
    if (isset($order->customer->shipping['country']))
       $order->shipping_country = $order->customer->shipping['country'];
    else $order->shipping_country = 1;
    putenv('PATH_INFO');
    set_remote_user('paypal');
    $log_string = '';   $form_fields = get_form_fields();
    foreach ($form_fields as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       if (is_array($value)) $log_string .= $key.'=Array';
       else $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order'))
       @Order::log_payment('PayPal Received: '.$log_string);
    else log_payment('PayPal Received: '.$log_string);

    $field_map = array('SHIPTOSTREET'=>'ship_address1',
       'SHIPTOSTREET2'=>'ship_address2','SHIPTOCITY'=>'ship_city',
       'SHIPTOSTATE'=>'ship_state','SHIPTOZIP'=>'ship_zipcode');
    foreach ($field_map as $paypal_name => $field_name)
       if (isset($form_fields[$paypal_name]))
          $order->customer->set($field_name,$form_fields[$paypal_name]);
    if (isset($form_fields['SHIPTOCOUNTRYCODE'])) {
       $country_info = find_country_info($form_fields['SHIPTOCOUNTRYCODE'],
                                         $order->db);
       if ($country_info) {
          $order->customer->set('ship_country',$country_info['id']);
          $available = $country_info['available'];
       }
       else $available = 0;
    }
    else $available = 2;

    $order->cart->calculate_tax($order->customer);
    load_paypal_shipping_modules($order);
    $order->cart->load_shipping_options($order->customer);
    $tax = $order->cart->get('tax');
    if (($tax === null) || ($tax === '')) $tax = 0;

    $response = 'METHOD=CallbackResponse&OFFERINSURANCEOPTION=false';

    if (isset($cart->error)) {
       log_error('Shipping Error during PayPal Callback: '.$cart->error);
       $response .= '&NO_SHIPPING_OPTION_DETAILS=1';
    }
    else if (count($order->cart->shipping_options) == 0) {
       $response .= '&L_SHIPPINGOPTIONNAME0=Standard';
       $response .= '&L_SHIPPINGOPTIONLABEL0=Shipping';
       $response .= '&L_SHIPPINGOPTIONAMOUNT0=0.00';
       $response .= '&L_SHIPPINGOPTIONISDEFAULT0=true';
       $response .= '&L_TAXAMT0='.number_format($tax,2,'.','');
       $response .= '&L_INSURANCEAMOUNT0=0.00';
    }
    else if (! ($available & 2))
       $response .= '&NO_SHIPPING_OPTION_DETAILS=1';
    else {
       $index = 0;
       foreach ($order->cart->shipping_options as $shipping_option) {
          $shipping_method = build_paypal_shipping_method($shipping_option,
                                                          $shipping_carrier);
          $response .= '&L_SHIPPINGOPTIONNAME'.$index.'=' .
                       urlencode($shipping_carrier);
          $response .= '&L_SHIPPINGOPTIONLABEL'.$index.'=' .
                       urlencode($shipping_method);
          $response .= '&L_SHIPPINGOPTIONAMOUNT'.$index.'=' .
                       number_format(floatval($shipping_option[2]),2,'.','');
          $response .= '&L_SHIPPINGOPTIONISDEFAULT'.$index.'=';
          if ($shipping_option[4]) $response .= 'true';
          else $response .= 'false';
          $response .= '&L_TAXAMT'.$index.'='.number_format($tax,2,'.','');
          $response .= '&L_INSURANCEAMOUNT'.$index.'=0.00';
          $index++;
       }
    }
    if (class_exists('Order'))
       @Order::log_payment('PayPal Sent: '.$response);
    else log_payment('PayPal Sent: '.$response);
    print $response;
}

function continue_paypal_checkout(&$cart,&$customer)
{
    global $update_shipping_from_paypal,$guest_checkout;
    global $using_paypal_express;

    if ($cart instanceof Order) $update_shipping_from_paypal = false;
    else if (! isset($update_shipping_from_paypal))
       $update_shipping_from_paypal = true;
    $token = get_form_field('token');
    $post_array = array('method' => 'GetExpressCheckoutDetails',
                        'token' => $token);
    $paypal_result = call_paypal($post_array,$error,'PAYMENTREQUEST_0');
    if (! $paypal_result) {
       $cart->error = $error;   return false;
    }
    if ((! isset($paypal_result['ACK'])) ||
        ($paypal_result['ACK'] != 'Success')) {
       log_activity('PayPal Error: '.$paypal_result['ACK'].' - ' .
                    $paypal_result['L_SHORTMESSAGE0'].' ('.
                    $paypal_result['L_LONGMESSAGE0'].')');
       $cart->error = 'PayPal Error';   return false;
    }
    $cart->set('token',$token);
    if ($cart instanceof Order)
       $cart->id = $paypal_result['PAYMENTREQUEST_0_CUSTOM'];

    if (isset($paypal_result['PAYERID']))
       $cart->set('payerid',$paypal_result['PAYERID']);
    if ($update_shipping_from_paypal || $using_paypal_express) {
       if (isset($paypal_result['SHIPTONAME']))
          $customer->set('ship_shipto',$paypal_result['SHIPTONAME']);
       if (isset($paypal_result['SHIPTOSTREET'])) {
          $customer->set('ship_address1',$paypal_result['SHIPTOSTREET']);
          if ($guest_checkout || $using_paypal_express)
             $customer->set('bill_address1',$paypal_result['SHIPTOSTREET']);
       }
       if (isset($paypal_result['SHIPTOSTREET2'])) {
          $customer->set('ship_address2',$paypal_result['SHIPTOSTREET2']);
          if ($guest_checkout || $using_paypal_express)
             $customer->set('bill_address2',$paypal_result['SHIPTOSTREET2']);
       }
       if (isset($paypal_result['SHIPTOCITY'])) {
          $customer->set('ship_city',$paypal_result['SHIPTOCITY']);
          if ($guest_checkout || $using_paypal_express)
             $customer->set('bill_city',$paypal_result['SHIPTOCITY']);
       }
       if (isset($paypal_result['SHIPTOSTATE'])) {
          $customer->set('ship_state',$paypal_result['SHIPTOSTATE']);
          if ($guest_checkout || $using_paypal_express)
             $customer->set('bill_state',$paypal_result['SHIPTOSTATE']);
       }
       if (isset($paypal_result['SHIPTOZIP'])) {
          $customer->set('ship_zipcode',$paypal_result['SHIPTOZIP']);
          if ($guest_checkout || $using_paypal_express)
             $customer->set('bill_zipcode',$paypal_result['SHIPTOZIP']);
       }
       if (isset($paypal_result['SHIPTOCOUNTRYCODE'])) {
          $country_info = find_country_info($paypal_result['SHIPTOCOUNTRYCODE'],$cart->db);
          if ($country_info) {
             $customer->set('ship_country',$country_info['id']);
             if ($guest_checkout || $using_paypal_express)
                $customer->set('bill_country',$country_info['id']);
          }
       }
       if (isset($paypal_result['SHIPTOPHONENUM']))
          $customer->set('bill_phone',$paypal_result['SHIPTOPHONENUM']);
    }
    if ($using_paypal_express) {
       if (isset($paypal_result['EMAIL']))
          $customer->set('cust_email',$paypal_result['EMAIL']);
       if (isset($paypal_result['FIRSTNAME']))
          $customer->set('cust_fname',$paypal_result['FIRSTNAME']);
       if (isset($paypal_result['LASTNAME']))
          $customer->set('cust_lname',$paypal_result['LASTNAME']);
       if (isset($paypal_result['PHONENUM']))
          $customer->set('bill_phone',$paypal_result['PHONENUM']);
       if (isset($paypal_result['COUNTRYCODE'])) {
          $country_info = find_country_info($paypal_result['COUNTRYCODE'],$cart->db);
          if ($country_info) $customer->set('bill_country',$country_info['id']);
       }
       $paypal_shipping_option = null;   $shipping_amount = null;
       if (! empty($paypal_result['SHIPPINGOPTIONNAME']))
          $paypal_shipping_option = $paypal_result['SHIPPINGOPTIONNAME'];
       if (! empty($paypal_result['SHIPPINGAMT']))
          $shipping_amount = $paypal_result['SHIPPINGAMT'];
       if ($paypal_shipping_option || $shipping_amount) {
          if (isset($paypal_result['SHIPPINGOPTIONISDEFAULT']) &&
              ($paypal_result['SHIPPINGOPTIONISDEFAULT'] == 'true'))
             $default_shipping = true;
          else $default_shipping = false;
          load_paypal_shipping_modules($cart);
          $cart->load_shipping_options($cart->customer);
          foreach ($cart->shipping_options as $index => $shipping_option) {
             if ($paypal_shipping_option) {
                $shipping_method = build_paypal_shipping_method($shipping_option,
                   $shipping_carrier,true);
                if (($paypal_shipping_option == $shipping_method) ||
                    ($paypal_shipping_option == $shipping_option[3]) ||
                    ($default_shipping &&
                     ($cart->shipping_options[$index][4] == 1)))
                   $cart->shipping_options[$index][4] = 1;
                else unset($cart->shipping_options[$index]);
             }
             else if ($cart->shipping_options[$index][4] == 1)
                $cart->shipping_options[$index][2] = $shipping_amount;
             else unset($cart->shipping_options[$index]);
          }
          if (count($cart->shipping_options) > 1) $cart->shipping_columns--;
          else if ((count($cart->shipping_options) == 0) && $shipping_amount) {
             if ($paypal_shipping_option) $label = $paypal_shipping_option;
             else $label = 'Default Shipping';
             $cart->add_shipping_option('manual',0,$shipping_amount,
                                        $label,true);
          }
       }
       if (empty($customer->id)) {
          if (! isset($customer->id)) $customer->id = 0;
          if (! empty($customer->info['email'])) {
             $query = 'select id from customers where email=?';
             $query = $cart->db->prepare_query($query,
                                               $customer->info['email']);
             $row = $cart->db->get_record($query);
             if ($row && $row['id']) {
                $customer->id = $row['id'];
                $cart->info['customer_id'] = $customer->id;
                $query = 'update '.$cart->main_table .
                         ' set customer_id=? where id=?';
                $query = $cart->db->prepare_query($query,$customer->id,
                                                  $cart->id);
                $cart->db->log_query($query);
                if (! $cart->db->query($query)) {
                   $cart->error = $this->db->error;
                   $cart->errors['dberror'] = true;
                   return false;
                }
                $guest_checkout = false;
             }
             else $guest_checkout = true;
          }
       }
    }
    return true;
}

function process_paypal_result(&$order,$paypal_result)
{
    if (! isset($paypal_result['ACK'])) {
       $order->error = 'Card Declined';   return false;
    }
    else if ($paypal_result['ACK'] == 'SuccessWithWarning') {
       $order->log_payment('PayPal Warning: '.$paypal_result['L_SHORTMESSAGE0'].' ('.
                           $paypal_result['L_LONGMESSAGE0'].')');
    }
    else if ($paypal_result['ACK'] != 'Success') {
       log_activity('PayPal Declined: '.$paypal_result['ACK'].' - ' .
                    $paypal_result['L_SHORTMESSAGE0'].' ('.
                    $paypal_result['L_LONGMESSAGE0'].')');
       $error_code = $paypal_result['L_ERRORCODE0'];
       if ($error_code == 10486) {  // Funding Failure at PayPal
          $host = get_cart_config_value('paypal_checkout_hostname');
          $token = get_form_field('token');
          header('Location: https://'.$host .
                 '/webscr&cmd=_express-checkout&token='.$token);
       }
       $order->error = $paypal_result['L_LONGMESSAGE0'];   return false;
    }
    if (isset($paypal_result['TRANSACTIONID']))
       $payment_id = $paypal_result['TRANSACTIONID'];
    else if (isset($paypal_result['PAYMENTINFO_0_TRANSACTIONID']))
       $payment_id = $paypal_result['PAYMENTINFO_0_TRANSACTIONID'];
    else $payment_id = '';
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = $payment_id;
    $order->payment['payment_method'] = 'PayPal';
    log_activity('PayPal Payment Accepted with Transaction ID #' .
                 $order->payment['payment_id']);
    return true;
}

function finish_paypal_checkout(&$order)
{
    if (empty($order->info['order_number'])) {
       if (! $order->set_order_number()) return false;
    }
    if (isset($order->customer->billing['country']))
       $order->billing_country = $order->customer->billing['country'];
    else $order->billing_country = 1;
    if (isset($order->customer->shipping['country']))
       $order->shipping_country = $order->customer->shipping['country'];
    else $order->shipping_country = 1;

    $token = get_form_field('token');
    $payerid = get_form_field('payerid');
    $post_array = array('Method' => 'DoExpressCheckoutPayment',
                        'Token' => $token,
                        'PayerId' => $payerid);

    $order->customer->shipping['shipto'] = get_form_field('paypal_shipto');
    $order->customer->shipping['address1'] = get_form_field('paypal_address1');
    $order->customer->shipping['address2'] = get_form_field('paypal_address2');
    $order->customer->shipping['city'] = get_form_field('paypal_city');
    $order->customer->shipping['state'] = get_form_field('paypal_state');
    $order->customer->set('ship_state',get_form_field('paypal_state'));
    $order->customer->shipping['zipcode'] = get_form_field('paypal_zipcode');
    $order->shipping_country = get_form_field('paypal_country');

    set_order_info($post_array,$order,true);

    $paypal_result = call_paypal($post_array,$error,'PAYMENTREQUEST_0');
    if (! $paypal_result) {
       $order->error = $error;   $order->errors['CardFailed'] = true;
       $order->errors['error'] = true;   $order->cleanup_order();
       return false;
    }
    if (! process_paypal_result($order,$paypal_result)) {
       $order->errors['CardFailed'] = true;   $order->errors['error'] = true;
       $order->cleanup_order();   return false;
    }
    $order->payment['payment_amount'] = $order->info['total'];
    $order->payment['payment_date'] = time();
    return true;
}

function paypal_process_payment(&$order)
{
    switch ($order->credit_card['type']) {
       case 'amex': $credit_card_type = 'Amex';   break;
       case 'visa': $credit_card_type = 'Visa';   break;
       case 'master': $credit_card_type = 'MasterCard';   break;
       case 'discover': $credit_card_type = 'Discover';   break;
    }
    $exp_date = $order->credit_card['month'].'20'.$order->credit_card['year'];

    $post_array = array('Method' => 'DoDirectPayment',
                        'CreditCardType' => $credit_card_type,
                        'Acct' => $order->credit_card['number'],
                        'ExpDate' => $exp_date,
                        'Cvv2' => $order->credit_card['cvv']);

    set_order_info($post_array,$order,false);

    $paypal_result = call_paypal($post_array,$error);
    if (! $paypal_result) {
       $order->error = $error;   return false;
    }
    return process_paypal_result($order,$paypal_result);
}

function paypal_cancel_payment($db,$payment_info,$refund_amount,&$cancel_info,
                               &$error)
{
    $payment_id = $payment_info['payment_id'];
    $order_id = $payment_info['parent'];
    $post_array = array('Method' => 'RefundTransaction',
                        'TRANSACTIONID' => $payment_id);
    if ($refund_amount == $payment_info['payment_amount'])
       $post_array['REFUNDTYPE'] = 'Full';
    else {
       $post_array['REFUNDTYPE'] = 'Partial';
       $post_array['AMT'] = number_format($refund_amount,2,'.','');
       $post_array['CURRENCYCODE'] = $payment_info['currency'];
       $post_array['NOTE'] = 'Refund for Order #'.$order_id;
    }

    $paypal_result = call_paypal($post_array,$error);
    if (! $paypal_result) return false;

    if (! isset($paypal_result['ACK'])) {
       $error = 'Refund Failed';   return false;
    }
    else if ($paypal_result['ACK'] == 'SuccessWithWarning') {
       $order->log_payment('PayPal Warning: '.$paypal_result['L_SHORTMESSAGE0'].' ('.
                           $paypal_result['L_LONGMESSAGE0'].')');
    }
    else if ($paypal_result['ACK'] != 'Success') {
       log_activity('PayPal Declined: '.$paypal_result['ACK'].' - ' .
                    $paypal_result['L_SHORTMESSAGE0'].' ('.
                    $paypal_result['L_LONGMESSAGE0'].')');
       $error_code = $paypal_result['L_ERRORCODE0'];
       $error = $paypal_result['L_LONGMESSAGE0'];   return false;
    }

    log_activity('Refunded PayPal Transaction #'.$payment_id .
                 ' for Order #'.$order_id);
    return true;
}

function paypal_write_cart_payment_button(&$cart,$coupon_error=false)
{
    global $mobile_cart,$paypal_button;

    if ($cart === null) return;
    if ($cart && method_exists($cart,'check_auto_reorders') &&
        $cart->check_auto_reorders()) return;
    $paypal_options = get_cart_config_value('paypal_options',$cart->db);
    if (! ($paypal_options & 2)) return;

    if ($coupon_error) {
         print "<td align=\"right\" class=\"paypal_button_cell\">\n";
         print "<a href=\"cart/checkout.php?coupon_code=&PayPalExpress=Go\">";
         print "      <img style=\"vertical-align: middle;\" " .
               "src=\"".$paypal_button."\">";
         print '</a>';
         print "</td>\n      <td class=\"or_cell\">";
         print '<strong>&nbsp;&nbsp;'.T('OR').'&nbsp;&nbsp;&nbsp;</strong>';
         print "</td>\n";
    }
    else if ($mobile_cart) {
?>
    <div class="paypal_button_div">
      <a href="cart/checkout.php?PayPalExpress=Go"><img style="vertical-align: middle;" 
       src="<?= $paypal_button; ?>"></a>
    </div>
<?
    }
    else {
       print "<td align=\"right\" class=\"paypal_button_cell\">\n";
       print "<a href=\"cart/checkout.php?PayPalExpress=Go\">";
       print "      <img style=\"vertical-align: middle;\" " .
             "src=\"".$paypal_button."\">";
       print '</a>';
       print "</td>\n      <td class=\"or_cell\">";
       print '<strong>&nbsp;&nbsp;'.T('OR').'&nbsp;&nbsp;&nbsp;</strong>';
       print "</td>\n";
    }
}

function paypal_write_login_payment_button()
{
    global $paypal_button;

    $paypal_options = get_cart_config_value('paypal_options');
    if (! ($paypal_options & 2)) return;
?>
    <div class="paypal_button_div">
      <a href="cart/checkout.php?PayPalExpress=Go" class="enhancedMobileLoginPayPal"><img style="vertical-align: middle;" 
       src="<?= $paypal_button; ?>"></a>
    </div>
<?
}

function paypal_start_checkout()
{
    if (button_pressed('PayPalExpress')) {
       $order = new Order;
       if (! $order->load_cart()) {
          require 'index.php';   exit;
       }
       $coupon_code = get_form_field('coupon_code');
       if ($coupon_code === '') {
          if (isset($order->cart,$order->cart->info,
                    $order->cart->info['coupon_code']))
             $cart_coupon_code = $order->cart->info['coupon_code'];
          else $cart_coupon_code = null;
          if ($cart_coupon_code) $order->cart->delete_coupon();
       }
       $order->payment_module = 'paypal';
       $order->cart_paypal_express = true;
       process_paypal_checkout($order);
       require 'index.php';   exit;
    }
    $path_info = getenv('PATH_INFO');
    if (substr($path_info,0,15) == '/paypalcallback') {
       if (isset($system_disabled)) $system_disabled = false;
       $order = new Order;
       $cart_id = substr($path_info,16);
       if (! $order->load_cart($cart_id)) {
          log_error($order->error);   exit;
       }
       $order->payment_module = 'paypal';
       $order->cart_paypal_express = true;
       process_paypal_callback($order);
       exit;
    }
}

function paypal_setup_checkout(&$cart)
{
    global $order,$coupon_code,$using_paypal_express;
    global $require_paypal_express_account;

    if (isset($order)) {
       if (get_form_field('FinishPayPal') == 'Cart')
          $using_paypal_express = true;
    }
    else {
       $using_paypal_express = false;
       if (button_pressed('ContinuePayPal') || button_pressed('CancelPayPal') ||
           button_pressed('FinishPayPal')) {
          if (isset($cart->info['coupon_code']))
             $coupon_code = $cart->info['coupon_code'];
          else $coupon_code = null;
          if (! $cart->load_cart_data()) $cart->errors['error'] = true;
          $cart->read_hidden_fields();
          if (get_form_field('ContinuePayPal') == 'Cart')
             $using_paypal_express = true;
          else if (get_form_field('FinishPayPal') == 'Cart')
             $using_paypal_express = true;
          if ($coupon_code) {
             if ((! empty($cart->info['coupon_code'])) &&
                 (! empty($cart->info['coupon_amount'])))
             $cart->info['total'] += $cart->info['coupon_amount'];
             if (! $cart->process_coupon($coupon_code)) {
                require 'index.php';   exit;
             }
          }
          else $cart->process_special_offers();
       }
    }
    if ($using_paypal_express) {
       $cart->enable_shipping_options = false;
       $cart->enable_edit_addresses = false;
       if (! empty($require_paypal_express_account))
          $cart->account_required = true;
       $cart->payment_customer_info = true;
    }
}

function paypal_process_checkout_buttons(&$cart,$paying_balance)
{
    global $order_id,$using_paypal_express;

    if (button_pressed('ContinuePayPal') || button_pressed('CancelPayPal')) {
       if ($paying_balance) {
          $customer = array();
          $using_paypal_express = false;
          if (! continue_paypal_checkout($cart,$customer))
             $cart->errors['dberror'] = true;
          else $cart->paypal_info = $cart->info;
          if (isset($cart->id)) $order_id = $cart->id;
          else $order_id = null;
       }
       else if (! continue_paypal_checkout($cart,$cart->customer))
          $cart->errors['error'] = true;
       $cart->single_payment_button = 'paypal';
    }
}

function paypal_configure_checkout(&$cart)
{
    global $show_paypal_express,$include_direct_payment;

    if (button_pressed('ContinuePayPal') || button_pressed('FinishPayPal')) {
       $cart->payment_module = '';   $show_paypal_express = false;
       $include_direct_payment = true;   return;
    }
    else {
       $paypal_options = get_cart_config_value('paypal_options',$cart->db);
       if ($paypal_options & 2) $show_paypal_express = true;
       else $show_paypal_express = false;
       if (method_exists($cart,'check_auto_reorders') &&
           $cart->check_auto_reorders()) $show_paypal_express = false;
    }
    if ((! $cart->payment_module) || ($cart->payment_module == 'paypal')) {
       $paypal_options = get_cart_config_value('paypal_options');
       if (! ($paypal_options & 1)) $include_direct_payment = false;
    }
}

function paypal_write_checkout_hidden_fields(&$cart)
{
    global $using_paypal_express;

    if ($using_paypal_express) {
       print "<input type=\"hidden\" name=\"ContinuePayPal\" id=\"ContinuePayPal\" value=\"" .
             get_form_field('ContinuePayPal')."\">\n";
       print "<input type=\"hidden\" name=\"token\" id=\"token\" value=\"" .
             get_form_field('token')."\">\n";
       $payerid = get_form_field('PayerID');
       if (! $payerid) $payerid = get_form_field('payerid');
       print "<input type=\"hidden\" name=\"payerid\" id=\"payerid\" value=\"" .
             $payerid."\">\n";
    }
    if (button_pressed('ContinuePayPal') || button_pressed('FinishPayPal')) {
       if (! $using_paypal_express) {
          print "<input type=\"hidden\" name=\"token\" value=\"";
          if (isset($cart->info['token'])) print $cart->info['token'];
          print "\">\n";
          print "<input type=\"hidden\" name=\"payerid\" value=\"";
          if (isset($cart->info['payerid'])) print $cart->info['payerid'];
          print "\">\n";
       }
       print "<input type=\"hidden\" name=\"FinishPayPal\" value=\"";
       if ($using_paypal_express) print 'Cart';
       else print 'Go';
       print "\">\n";
       $customer = $cart->customer;
       print "<input type=\"hidden\" name=\"paypal_shipto\" value=\"". 
             $customer->get('ship_shipto')."\">\n";
       print "<input type=\"hidden\" name=\"paypal_address1\" value=\"". 
             $customer->get('ship_address1')."\">\n";
       print "<input type=\"hidden\" name=\"paypal_address2\" value=\"". 
             $customer->get('ship_address2')."\">\n";
       print "<input type=\"hidden\" name=\"paypal_city\" value=\"". 
             $customer->get('ship_city')."\">\n";
       print "<input type=\"hidden\" name=\"paypal_state\" value=\"". 
             $customer->get('ship_state')."\">\n";
       print "<input type=\"hidden\" name=\"paypal_zipcode\" value=\"". 
             $customer->get('ship_zipcode')."\">\n";
       print "<input type=\"hidden\" name=\"paypal_country\" value=\"". 
             $customer->get('ship_country')."\">\n";
    }

}

function paypal_write_payment_logos(&$cart)
{
    global $show_paypal_express;

    if ($show_paypal_express)
       print '<img src="../cartimages/paypal-logo.png" alt="PayPal" ' .
             'class="avail_card_logo">'."\n";
}

function paypal_write_continue_checkout(&$cart)
{
    global $checkout_table_cellspacing;

    if (button_pressed('ContinuePayPal') || button_pressed('FinishPayPal')) {
       $paypal_action = get_cart_config_value('paypal_action');
?>
   <table cellpadding="0" cellspacing="<? print $checkout_table_cellspacing;
?>" id="payment_table" class="checkout_table" style="margin-right: 20px;">
    <tr><td class="checkout_header"><?= T('PAYMENT INFORMATION'); ?></td></tr>
    <tr>
      <td class="checkout_prompt"><?= T('Payment Method'); ?>: PayPal</td>
    </tr>
    <tr><td>Note: PayPal will debit funds <?
       if ($paypal_action == 'Authorization')
          print 'as soon as the order is fulfilled.';
       else print 'immediately upon completion of checkout.'; ?>
    </td></tr>
    <tr><td class="checkout_prompt">Review the information above,
     then click "Process Payment" to complete the order</td></tr>
   </table>
<?
       return true;
    }
    return false;
}

function paypal_write_checkout_payment_button(&$cart)
{
    global $show_paypal_express,$continue_no_payment,$paypal_button;
    global $include_direct_payment;

    if ($show_paypal_express) {
       $cart->num_payment_divs++;
       print "    <div style=\"float: right; line-height: 43px;";
       if ($continue_no_payment) print ' display:none;';
       print "\" id=\"payment_div_".$cart->num_payment_divs."\">";
       if ($include_direct_payment || ($cart->num_payment_divs > 1))
          print '<strong>&nbsp;&nbsp;'.T('OR')."&nbsp;&nbsp;&nbsp;</strong>\n";
       print "      <input type=\"image\" name=\"PayPalCheckout\" id=\"PayPalCheckout\" " .
             "style=\"vertical-align: middle;\" " .
             "src=\"".$paypal_button."\">";
       print "</div>\n";
    }
}

function paypal_setup_process_order(&$order)
{
    if ($order->payment_module == 'paypal')
       $order->save_card_available = false;
}

function paypal_process_order_button(&$order,$paying_balance)
{
    if (button_pressed('PayPalCheckout')) {
       $order->payment_module = 'paypal';
       process_paypal_checkout($order);
       $order->errors['error'] = true;
       if ($paying_balance) require 'pay-balance.php';
       else require 'checkout.php';
       exit;
    }
    if (button_pressed('FinishPayPal')) {
       $order->payment_module = 'paypal';
       if (! finish_paypal_checkout($order)) {
          if ($paying_balance) require 'pay-balance.php';
          else require 'checkout.php';
          exit;
       }
       return true;
    }
    return false;
}

function paypal_write_balance_hidden_fields(&$order)
{
    if (button_pressed('ContinuePayPal') || button_pressed('FinishPayPal')) {
       print "<input type=\"hidden\" name=\"token\" value=\"";
       if (isset($order->paypal_info['token']))
          print $order->paypal_info['token'];
       print "\">\n";
       print "<input type=\"hidden\" name=\"payerid\" value=\"";
       if (isset($order->paypal_info['payerid']))
          print $order->paypal_info['payerid'];
       print "\">\n";
       print "<input type=\"hidden\" name=\"FinishPayPal\" value=\"Go\">\n";
    }
}

?>
