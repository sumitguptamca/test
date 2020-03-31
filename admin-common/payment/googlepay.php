<?php
/*
                 Inroads Shopping Cart - Google Checkout API Module

                        Written 2010-2018 by Randall Severy
                         Copyright 2010-2018 Inroads, LLC
*/

define('GOOGLE_SANDBOX_BUTTON_URL',
       'sandbox.google.com/checkout/buttons/checkout.gif');
define('GOOGLE_BUTTON_URL','checkout.google.com/buttons/checkout.gif');
define ('GOOGLE_SANDBOX_URL',
        'https://sandbox.google.com/checkout/api/checkout/v2/merchantCheckout/Merchant/');
define ('GOOGLE_CHECKOUT_URL',
        'https://checkout.google.com/api/checkout/v2/merchantCheckout/Merchant/');
define ('GOOGLE_SANDBOX_NOTIFICATION_URL',
        'https://sandbox.google.com/checkout/api/checkout/v2/reports/Merchant/');
define ('GOOGLE_NOTIFICATION_URL',
        'https://checkout.google.com/api/checkout/v2/reports/Merchant/');

function google_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'Google Checkout','googlepay',$values);

    $dialog->add_edit_row('Merchant ID:','google_merchant_id',$values,50);
    $dialog->add_edit_row('Merchant Key:','google_merchant_key',$values,50);
    $dialog->start_row('Use Sandbox:','middle');
    $dialog->add_checkbox_field('google_sandbox','',$values);
    $dialog->end_row();
}

function google_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('google_merchant_id','google_merchant_key','google_sandbox');
    if (! empty($website_settings)) $fields[] = 'google_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function google_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'google_sandbox') {
       if (get_form_field('google_sandbox') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'google_active') {
       if (get_form_field('google_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function google_active($db)
{
    return get_cart_config_value('google_active',$db);
}

function google_get_primary_module($db)
{
    return 'google';
}

function call_google($url,$xml_data,&$error)
{
    $merchant_id = get_cart_config_value('google_merchant_id');
    $merchant_key = get_cart_config_value('google_merchant_key');
    $url .= $merchant_id;
    @Order::log_payment('Sent: '.$xml_data);
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $http->set_content_type('application/xml');
    $http->set_charset('UTF-8');
    $http->set_accept('application/xml');
    $http->set_headers(array('Authorization: Basic ' .
                             base64_encode($merchant_id.':'.$merchant_key)));
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('Google Checkout Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Google Checkout Error: '.$error);
       else log_payment('Google Checkout Error: '.$error);
       return null;
    }
    $log_response = $response_data;
    $log_response = str_replace("\n",'',$log_response);
    $log_response = str_replace("\r",'',$log_response);
    @Order::log_payment('Response: '.$log_response);
    return $response_data;
}

function process_google_checkout(&$order)
{
    if (isset($order->customer->billing['country']))
       $order->billing_country = $order->customer->billing['country'];
    else $order->billing_country = 1;
    if (isset($order->customer->shipping['country']))
       $order->shipping_country = $order->customer->shipping['country'];
    else $order->shipping_country = 1;

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
           "<checkout-shopping-cart xmlns=\"http://checkout.google.com/schema/2\">" .
           '<shopping-cart><items>';
    foreach ($order->items as $id => $cart_item) {
       $item_name = get_html_product_name($cart_item['product_name'],
                                          GET_PROD_PAYMENT_GATEWAY,
                                          $order,$cart_item);
       $attr_array = $cart_item['attribute_array'];
       $item_description = '';
       if (isset($attr_array) && (count($attr_array) > 0)) {
          foreach ($attr_array as $index => $attribute) {
             if ($index > 0) $item_description .= ', ';
             $item_description .= $attribute['attr'].': '.$attribute['option'];
          }
       }
       $xml .= '<item><merchant-item-id>'.$id.'</merchant-item-id>' .
               '<item-name>'.encode_xml_data($item_name).'</item-name>' .
               '<item-description>'.encode_xml_data($item_description) .
               '</item-description><unit-price currency=\'' .
               $order->info['currency'].'\'>'.get_item_total($cart_item,false) .
               '</unit-price><quantity>'.$cart_item['qty'].'</quantity></item>';
    }
    if (isset($order->info['tax']) && ($order->info['tax'] > 0))
       $xml .= '<item><item-name>Tax</item-name><item-description>' .
               "</item-description><unit-price currency=\"" .
               $order->info['currency']."\">".$order->info['tax'] .
               '</unit-price><quantity>1</quantity></item>';
    if (isset($order->info['coupon_amount']) && $order->info['coupon_amount'])
       $xml .= '<item><item-name>Promotion Discount</item-name><item-description>' .
               $order->info['coupon_code']."</item-description><unit-price currency=\"" .
               $order->info['currency']."\">-".$order->info['coupon_amount'] .
               '</unit-price><quantity>1</quantity></item>';
    if (isset($order->info['fee_amount']) && $order->info['fee_amount'])
       $xml .= '<item><item-name>'.$order->info['fee_name'].'</item-name><item-description>' .
               $order->info['fee_name']."</item-description><unit-price currency=\"" .
               $order->info['currency']."\">".$order->info['fee_amount'] .
               '</unit-price><quantity>1</quantity></item>';
    if (isset($order->info['discount_amount']) && $order->info['discount_amount'])
       $xml .= '<item><item-name>'.$order->info['discount_name'].'</item-name><item-description>' .
               $order->info['discount_name']."</item-description><unit-price currency=\"" .
               $order->info['currency']."\">-".$order->info['discount_amount'] .
               '</unit-price><quantity>1</quantity></item>';

    $private_data = $order->cart->id.'|'.$order->info['subtotal'].'|';
    if (isset($order->info['tax']) && ($order->info['tax'] > 0))
       $private_data .= $order->info['tax'];
    if (isset($order->info['shipping']) && $order->info['shipping']) {
       $private_data .= '|'.$order->info['shipping'].'|';
       $shipping_method = get_form_field('shipping_method');
       if ($shipping_method)
          $private_data .= str_replace('|','^',$shipping_method);
    }
    else $private_data .= '||';
    if (isset($order->info['coupon_amount']) && $order->info['coupon_amount'])
       $private_data .= '|'.$order->info['coupon_id'].'|'.$order->info['coupon_code'] .
                        '|'.$order->info['coupon_amount'];
    else $private_data .= '|||';
    if (isset($order->info['gift_amount']) && $order->info['gift_amount'])
       $private_data .= '|'.$order->info['gift_id'].'|'.$order->info['gift_code'].'|' .
                        $order->info['gift_amount'].'|'.$order->info['gift_balance'];
    else $private_data .= '||||';
    if (isset($order->info['fee_amount']) && $order->info['fee_amount'])
       $private_data .= '|'.$order->info['fee_name'].'|'.$order->info['fee_amount'];
    else $private_data .= '||';
    if (isset($order->info['discount_amount']) && $order->info['discount_amount'])
       $private_data .= '|'.$order->info['discount_name'].'|'.$order->info['discount_amount'];
    else $private_data .= '||';

    $xml .= '</items><merchant-private-data>'.$private_data .
            '</merchant-private-data></shopping-cart>';
    if (isset($order->info['shipping']) && ($order->info['shipping'] > 0)) {
       $xml .= '<checkout-flow-support><merchant-checkout-flow-support>' .
               "<shipping-methods><flat-rate-shipping name=\"";
       if ($shipping_method) {
          $shipping_method_info = explode('|',$shipping_method);
          $xml .= encode_xml_data($shipping_method_info[1]);
       }
       else $xml .= 'Shipping';
       $xml .= "\"><price currency=\"".$order->info['currency']."\">" .
               $order->info['shipping'].'</price></flat-rate-shipping>' .
               '</shipping-methods></merchant-checkout-flow-support>' .
               '</checkout-flow-support>';
    }
    $xml .= '</checkout-shopping-cart>';
    $google_sandbox = get_cart_config_value('google_sandbox');
    if ($google_sandbox == 1) $url = GOOGLE_SANDBOX_URL;
    else $url = GOOGLE_CHECKOUT_URL;
    $google_result = call_google($url,$xml,$error);
    if (! $google_result) {
       $order->error = $error;
       $order->errors['CardFailed'] = true;   $order->errors['error'] = true;
       return false;
    }
    $redirect_url = parse_xml_tag($google_result,'redirect-url',true);
    if (! $redirect_url) {
       $error = parse_xml_tag($google_result,'error-message',true);
       if (! $error) {
          $error = $google_result;
          $error = str_replace("\n",'',$error);
          $error = str_replace("\r",'',$error);
       }
       @Order::log_payment('Google Checkout Error: '.$error);
       log_error('Google Checkout Error: '.$error);
       $order->error = $error;
       $order->errors['CardFailed'] = true;   $order->errors['error'] = true;
       return false;
    }
    $order->payment['payment_amount'] = $order->info['total'];
    $redirect_url = str_replace('&amp;','&',$redirect_url);
    header('Location: '.$redirect_url);
    exit;
}

function process_google_api()
{
    set_remote_user('googlecheckout');
    $log_string = '';   $form_fields = get_form_fields();
    foreach ($form_fields as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    @Order::log_payment('Received: '.$log_string);
    $serial_number = get_form_field('serial-number');
    if (! $serial_number) {
       log_error('Invalid Request from Google Checkout');   return;
    }
    $db = new DB;
    load_cart_config_values($db);
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
           "<notification-history-request xmlns=\"http://checkout.google.com/schema/2\">" .
           '<serial-number>'.$serial_number.'</serial-number>' .
           '</notification-history-request>';
    $google_sandbox = get_cart_config_value('google_sandbox');
    if ($google_sandbox == 1) $url = GOOGLE_SANDBOX_NOTIFICATION_URL;
    else $url = GOOGLE_NOTIFICATION_URL;
    $google_result = call_google($url,$xml,$error);
    if (! $google_result) {
       log_error('Google Notification Error: '.$error);   return;
    }
    $private_data = parse_xml_tag($google_result,'merchant-private-data',true);
    $private_info = explode('|',$private_data);
    if (isset($private_info[0])) $cart_id = $private_info[0];
    else $cart_id = null;
    if (! $cart_id) {
       $error = parse_xml_tag($google_result,'error-message',true);
       if (! $error) {
          $error = $google_result;
          $error = str_replace("\n",'',$error);
          $error = str_replace("\r",'',$error);
       }
       @Order::log_payment('Google Notification Error: '.$error);
       log_error('Google Notification Error: '.$error);
       return;
    }

    $query = 'select customer_id from cart where id=?';
    $query = $db->prepare_query($query,$cart_id);
    $row = $db->get_record($query);
    if ((! $row) && isset($db->error)) {
       log_error('Database Error: '.$db->error);   return;
    }
    if ($row) {
       $customer_id = $row['customer_id'];
       if (empty($customer_id)) {
          log_error('No Customer information found for Cart #'.$cart_id);
          return;
       }
       $customer = new Customer($db,$customer_id);
       $customer->cart_id = $cart_id;
       $order = new Order($customer);
       $order->payment_module = 'google';
       if (! $order->load_cart($cart_id)) {
          log_error('Database Error: '.$order->error);   return;
       }
       $order->payment['payment_method'] = 'Google Checkout';

       if (isset($private_info[1])) $subtotal = $private_info[1];
       else $subtotal = 0;
       if (isset($private_info[2])) $tax = $private_info[2];
       else $tax = 0;
       if ($tax) $order->set('tax',$tax);
       if (isset($private_info[3])) $shipping = $private_info[3];
       else $shipping = 0;
       if (isset($private_info[4]))
          $shipping_method = str_replace('^','|',$private_info[4]);
       else $shipping_method = null;
       if ($shipping_method) {
          $shipping_info = explode('|',$shipping_method);
          $shipping_module = $shipping_info[0];
          require_once $shipping_module.'.php';
          $process_shipping = $shipping_module.'_process_shipping';
          $process_shipping($order,$shipping_method);
          $shipping = $order->info['shipping'];
       }
       $order->set('shipping',$shipping);
       if (isset($private_info[5]) && $private_info[5])
          $order->set('coupon_id',$private_info[5]);
       if (isset($private_info[6]) && $private_info[6])
          $order->set('coupon_code',$private_info[6]);
       if (isset($private_info[7]) && $private_info[7]) {
          $coupon_amount = $private_info[7];
          $order->set('coupon_amount',$private_info[7]);
       }
       else $coupon_amount = 0;
       if (isset($private_info[8]) && $private_info[8])
          $order->set('gift_id',$private_info[8]);
       if (isset($private_info[9]) && $private_info[9])
          $order->set('gift_code',$private_info[9]);
       if (isset($private_info[10]) && $private_info[10]) {
          $gift_amount = $private_info[10];
          $order->set('gift_amount',$private_info[10]);
       }
       else $gift_amount = 0;
       if (isset($private_info[11]) && $private_info[11])
          $order->set('gift_balance',$private_info[11]);
       if (isset($private_info[12]) && $private_info[12])
          $order->set('fee_name',$private_info[12]);
       if (isset($private_info[13]) && $private_info[13]) {
          $fee_amount = $private_info[13];
          $order->set('fee_amount',$private_info[13]);
       }
       else $fee_amount = 0;
       if (isset($private_info[14]) && $private_info[14])
          $order->set('discount_name',$private_info[14]);
       if (isset($private_info[15]) && $private_info[15]) {
          $discount_amount = $private_info[15];
          $order->set('discount_amount',$private_info[15]);
       }
       else $discount_amount = 0;
       $total = $subtotal + $tax + $shipping - $coupon_amount -
                $gift_amount + $fee_amount - $discount_amount;
       $order->set('subtotal',$subtotal);
       $order->set('total',$total);

       if (! $order->create()) {
          log_error('Database Error: '.$order->error);   return;
       }
//       if (! update_order_totals($db,$order->id,true)) return;
       if (function_exists('custom_order_notifications'))
          custom_order_notifications($order);
       else {
          $notify_flags = get_cart_config_value('notifications',$order->db);
          if (($notify_flags & NOTIFY_NEW_ORDER_CUST) ||
              ($notify_flags & NOTIFY_NEW_ORDER_ADMIN)) {
             require_once '../engine/email.php';
             if ($notify_flags & NOTIFY_NEW_ORDER_CUST) {
                $email = new Email(NEW_ORDER_CUST_EMAIL,
                                   array('order' => 'obj','order_obj' => $order));
                if (! $email->send()) log_error($email->error);
             }
             if ($notify_flags & NOTIFY_NEW_ORDER_ADMIN) {
                $email = new Email(NEW_ORDER_ADMIN_EMAIL,
                                   array('order' => 'obj','order_obj' => $order));
                if (! $email->send()) log_error($email->error);
             }
          }
       }
       log_activity('Processed Order #'.$order->id.' from Cart #' .
                    $cart_id.' by Google Checkout');
    }

    write_xml_header();
    $xml = "<notification-acknowledgment xmlns=\"http://checkout.google.com/schema/2\" " .
           "serial-number=\"".$serial_number."\" />";
    print $xml;
    @Order::log_payment('Sent: '.$xml);
}

function write_google_checkout_button()
{
    $google_sandbox = get_cart_config_value('google_sandbox');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on'))
       $button_url = 'https://';
    else $button_url = 'http://';
    if ($google_sandbox == 1) $button_url .= GOOGLE_SANDBOX_BUTTON_URL;
    else $button_url .= GOOGLE_BUTTON_URL;
    $google_merchant_id = get_cart_config_value('google_merchant_id');
    $button_url .= '?merchant_id='.$google_merchant_id;
    $button_url .= '&w=160&h=43&style=white&variant=text&loc=en_US';
    print "      <input type=\"image\" name=\"GoogleCheckout\" " .
          "alt=\"Fast checkout through Google\" style=\"vertical-align: middle;\" " .
          "src=\"".$button_url."\" height=\"43\" width=\"160\">";
}

function google_write_checkout_payment_button(&$cart)
{
    global $continue_no_payment;

      if ($google_checkout_flag) {
         $primary_module = call_payment_event('get_primary_module',
                                              null,true,true);
         $cart->num_payment_divs++;
         print "    <div style=\"float: right; line-height: 43px;";
         if ($continue_no_payment) print ' display:none;';
         print "\" id=\"payment_div_".$cart->num_payment_divs."\">";
         if ($primary_module || ($cart->num_payment_divs > 1))
            print '<strong>&nbsp;&nbsp;'.T('OR')."&nbsp;&nbsp;&nbsp;</strong>\n";
         write_google_checkout_button();
         print "</div>\n";
      }
}

function google_start_process_order($paying_balance)
{
    if (getenv('PATH_INFO') == '/googleapi') {
       process_google_api();   exit;
    }
}

function google_process_order_button(&$order,$paying_balance)
{
    if (button_pressed('GoogleCheckout')) {
       $order->payment_module = 'google';
       process_google_checkout($order);
       if ($paying_balance) require 'pay-balance.php';
       else require 'checkout.php';
       exit;
    }
}

?>
