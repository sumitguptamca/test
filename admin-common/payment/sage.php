<?php
/*
                     Inroads Shopping Cart - Sage API Module

                       Written 2010-2018 by Randall Severy
                        Copyright 2010-2018 Inroads, LLC
*/

define('SAGE_HOSTNAME','https://api-cert.sagepayments.com');
define('SAGE_CLIENT_ID','bSOKTzYLYqSnjtqajjuGdthqP1KM8fOU');
define('SAGE_CLIENT_SECRET','WwSSp41NIvEvRefw');

function sage_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'Sage','sage',$values);

    $dialog->add_edit_row('Merchant ID:','sage_merchant_id',$values,30);
    $dialog->add_edit_row('Merchant Key:','sage_merchant_key',$values,30);
    $dialog->start_row('Transaction Type:','middle');
    $sage_type = get_row_value($values,'sage_type');
    if (! $sage_type) $sage_type = 'Sale';
    $dialog->add_radio_field('sage_type','Sale','Authorize and Capture',
                             $sage_type == 'Sale');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('sage_type','Authorization','Authorize Only',
                             $sage_type == 'Authorization');
    $dialog->end_row();
    if (! empty($enable_saved_cards)) {
       $dialog->start_row('Enable Saved Credit Cards:','middle');
       $dialog->add_checkbox_field('sage_saved_cards','',$values);
       $dialog->end_row();
    }
}

function sage_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('sage_merchant_id','sage_merchant_key','sage_type',
       'sage_saved_cards');
    if (! empty($website_settings)) $fields[] = 'sage_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function sage_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'sage_saved_cards') {
       if (get_form_field('sage_saved_cards') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'sage_active') {
       if (get_form_field('sage_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function sage_active($db)
{
    return get_cart_config_value('sage_active',$db);
}

function sage_get_primary_module($db)
{
    return 'sage';
}

function sage_saved_cards_enabled($db)
{
    $saved_flag = get_cart_config_value('sage_saved_cards',$db);
    if ($saved_flag == '1') return true;
    return false;
}

function log_sage_activity($msg)
{
    if (class_exists('Order')) @Order::log_payment($msg);
    else if (function_exists('log_payment')) log_payment($msg);
}

function log_sage_error($msg)
{
    log_sage_activity($msg);
    log_error($msg);
}

function call_sage($request_method,$path,$query_string,$request_data,&$error)
{
    require_once '../engine/http.php';

    $url = SAGE_HOSTNAME.$path;
    if ($query_string) $url .= '?'.$query_string;
    $merchant_id = get_cart_config_value('sage_merchant_id');
$merchant_id = '173859436515';
    $merchant_key = get_cart_config_value('sage_merchant_key');
$merchant_key = 'P1J2V8P2Q3D8';
    $nonce = uniqid();
    $timestamp = (string) time();
    if ($request_data) $json_data = json_encode($request_data);
    else $json_data = '';
    $hash_data = $request_method.$url.$json_data.$merchant_id.$nonce .
                 $timestamp;
    $hmac = hash_hmac('sha512',$hash_data,SAGE_CLIENT_SECRET,true);
    $hmac_b64 = base64_encode($hmac);
    log_sage_activity('Sent Request: '.$request_method.' '.$url);
    if ($json_data) log_sage_activity('Sent Data: '.$json_data);

    $http = new HTTP($url);
    $headers = array('clientId: '.SAGE_CLIENT_ID,
                     'merchantId: '.$merchant_id,
                     'merchantKey: '.$merchant_key,
                     'nonce: '.$nonce,
                     'timestamp: '.$timestamp,
                     'Authorization: '.$hmac_b64);
    $http->set_headers($headers);
    $http->set_content_type('application/json');
    $http->set_charset(null);
    $http->set_method($request_method);
    $json_data = $http->call($json_data);
    if (! $json_data) {
       if ($http->status == 200) {
          log_sage_activity('Response: '.$http->status);
          $data = new StdClass;
          $data->http_status = $http->status;
          return $data;
       }
       if ($http->error) $error_string = $http->error;
       else $error_string = 'Empty Response';
       $error = $error_string.' ('.$http->status.')';
       log_sage_error('Sage Error: '.$error);   return null;
    }
    $data = json_decode($json_data);
    if ($data === null) {
       if (strlen($json_data) > 500)
          $json_data = substr($json_data,0,500).'...';
       $error = 'Invalid Data Received ('.json_last_error_msg().') [' .
                json_last_error().']: '.$json_data;
       log_sage_error('Sage Error: '.$error);
       log_sage_activity('Response Headers: ' .
                         print_r($http->response_headers,true));
       return null;
    }
    log_sage_activity('Response: '.$http->status.' '.json_encode($json_data));
    if (isset($data->vaultResponse)) $data = $data->vaultResponse;
    if (! isset($data->message)) {
       $error = 'Invalid Sage Response';
       log_sage_error('Sage Error: '.$error);   return null;
    }
    if ($http->status > 299) {
       $error = $data->message;
       if (isset($data->code)) $error .= ' ('.$data->code.')';
       log_sage_error('Sage Error: '.$error);   return null;
    }
    $data->http_status = $http->status;
    return $data;
}

function sage_process_payment(&$order)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($order->db);
    if (empty($order->saved_card))
       $exp_date = $order->credit_card['month'].$order->credit_card['year'];
    $billing_country_info = get_country_info($order->billing_country,
                                             $order->db);
    $shipping_country_info = get_country_info($order->shipping_country,
                                              $order->db);
    if (! empty($order->info['tax'])) $tax = $order->info['tax'];
    else $tax = 0;
    if (! empty($order->info['shipping']))
       $shipping = $order->info['shipping'];
    else $shipping = 0;
    $bill_name = $order->customer->info['fname'].' ' .
                 $order->customer->info['lname'];
    if (isset($order->customer->shipping['shipto']) &&
        ($order->customer->shipping['shipto'] != ''))
       $ship_name = $order->customer->shipping['shipto'];
    else $ship_name = $bill_name;
    $phone = str_replace('(','',str_replace(')','',str_replace(' ','-',
                         $order->customer->billing['phone'])));
    $fax = str_replace('(','',str_replace(')','',str_replace(' ','-',
                       $order->customer->billing['fax'])));

    $request_data = array(
       'eCommerce' => array(
          'amounts' => array(
             'total' => number_format($order->payment['payment_amount'],2,'.',''),
             'tax' => $tax,
             'shipping' => $shipping
          ),
          'orderNumber' => $order->info['order_number']
       )
    );
    if (empty($order->saved_card)) {
       $request_data['eCommerce']['cardData'] = array(
          'number' => $order->credit_card['number'],
          'expiration' => $exp_date,
          'cvv' => $order->credit_card['cvv']
       );
    }
    $request_data['eCommerce']['customer'] = array(
       'email' => $order->customer->info['email'],
       'telephone' => $phone,
       'fax' => $fax
    );
    $request_data['eCommerce']['billing'] = array(
       'name' => $bill_name,
       'address' => $order->customer->billing['address1'],
       'city' => $order->customer->billing['city'],
       'state' => $order->customer->billing['state'],
       'postalCode' => $order->customer->billing['zipcode'],
       'country' => $billing_country_info['code']
    );
    $request_data['eCommerce']['shipping'] = array(
       'name' => $ship_name,
       'address' => $order->customer->shipping['address1'],
       'city' => $order->customer->shipping['city'],
       'state' => $order->customer->shipping['state'],
       'postalCode' => $order->customer->shipping['zipcode'],
       'country' => $shipping_country_info['code']
    );
    if (! empty($order->saved_card)) {
       $query = 'select * from saved_cards where profile_id=?';
       $query = $order->db->prepare_query($query,$order->saved_card);
       $card_info = $order->db->get_record($query);
       if (! $card_info) {
          if (isset($order->db->error)) $order->error = $order->db->error;
          else $order->error = 'Saved Credit Card not found';
          return false;
       }
       $request_data['vault'] = array(
          'token' => $order->saved_card,
          'operation' => 'READ'
       );
    }

    $transaction_type = get_cart_config_value('sage_type');
    if (! $transaction_type) $transaction_type = 'Sale';
    $data = call_sage('POST','/bankcard/v1/charges','type='.$transaction_type,
                      $request_data,$error);
    if (! $data) {
       $order->error = $error;   return false;
    }
    if ($data->http_status != 201) {
       $order->error = 'Card Declined: '.$data->message.' ('.$data->code.')';
       log_sage_error('Sage Error: '.$order->error);   return false;
    }
    $order->payment['payment_code'] = $data->code;
    $order->payment['payment_id'] = $data->reference;
    if (! empty($order->saved_card)) {
       $order->payment['card_type'] = $card_info['card_type'];
       $order->payment['card_name'] = $card_info['card_name'];
       $order->payment['card_number'] = $card_info['card_number'];
       $order->payment['card_month'] = $card_info['card_month'];
       $order->payment['card_year'] = $card_info['card_year'];
       $order->payment['card_cvv'] = $card_info['card_cvv'];
    }
    if ($transaction_type == 'Authorization') {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('Sage Authorization Accepted with Reference #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('Sage Payment Accepted with Reference #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    return true;
}

function sage_capture_payment($db,$payment_info,&$error)
{
    global $cart_config_values,$order_label;

    $order_id = $payment_info['parent'];

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $payment_id = $payment_info['payment_id'];
    $path = '/bankcard/v1/charges/'.$payment_id;

    $request_data = array(
       'amounts' => array(
          'total' => number_format($payment_info['payment_amount'],2,'.','')
       )
    );

    $data = call_sage('PUT',$path,null,$request_data,$error);
    if (! $data) return false;
    if ($data->http_status != 200) {
       $error = 'Capture Error: '.$data->message.' ('.$data->code.')';
       log_sage_error('Sage Error: '.$error);   return false;
    }
    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_info['id'];
    $payment_record['payment_status']['value'] = PAYMENT_CAPTURED;
    $payment_record['payment_date']['value'] = time();
    if (! $db->update('order_payments',$payment_record)) {
       $error = $db->error;   return false;
    }
    log_activity('Updated Payment #'.$payment_info['id'].' for ' .
                 $order_label.' #'.$order_id);

    log_activity('Captured Sage Transaction #'.$payment_id .
                 ' for '.$order_label.' #'.$order_id);
    return true;
}

function sage_cancel_payment($db,$payment_info,$refund_amount,&$cancel_info,
                             &$error)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $order_id = $payment_info['parent'];
    $payment_status = $payment_info['payment_status'];
    $payment_id = $payment_info['payment_id'];
    if ($payment_status == PAYMENT_AUTHORIZED) {
       $path = '/bankcard/v1/charges/'.$payment_id;
       $request_method = 'DELETE';   $type = 'Void';
       $request_data = null;   $success_status = 200;
    }
    else {
       $path = '/bankcard/v1/credits/'.$payment_id;
       $request_method = 'POST';   $type = 'Refund';
       $request_data = array(
          'amount' => number_format($refund_amount,2,'.','')
       );
       $success_status = 201;
    }
    $data = call_sage($request_method,$path,null,$request_data,$error);
    if (! $data) return false;
    if ($data->http_status != $success_status) {
       $error = $type.' Error: '.$data->message.' ('.$data->code.')';
       log_sage_error('Sage Error: '.$error);   return false;
    }
    if ($type == 'Void')
       log_activity('Voided Sage Transaction #'.$payment_id .
                    ' for Order #'.$order_id);
    else log_activity('Refunded Sage Transaction #'.$payment_id .
                      ' for Order #'.$order_id);
    return true;
}

function sage_create_saved_profile($db,$customer_id,&$error)
{
/*  Sage doesn't use Profiles, so use Customer ID as the Profile ID */
    return $customer_id;
}

function sage_delete_saved_profile($db,$profile_id,&$error)
{
/*  Sage doesn't use Profiles, so just pretend to delete the profile */
    return true;
}

function sage_create_saved_card($db,$profile_id,$payment_info,&$error)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $exp_date = $payment_info['card_month'].$payment_info['card_year'];

    $request_data = array(
       'cardData' => array(
          'number' => $payment_info['card_number'],
          'expiration' => $exp_date,
       )
    );

    $data = call_sage('POST','/token/v1/tokens',null,$request_data,$error);
    if (! $data) return false;
    if ($data->http_status != 200) {
       $error = 'Saved Card Error: '.$data->message.' ('.$data->code.')';
       log_sage_error('Sage Error: '.$error);   return false;
    }
    if (isset($data->data))
       $payment_id = $data->data;
    else {
       $error = 'Missing Vault Response Token in Response Data';
       $payment_id = null;
    }
    return $payment_id;
}

function sage_update_saved_card($db,&$profile_id,$payment_id,$payment_info,
                                &$error)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $exp_date = $payment_info['card_month'].$payment_info['card_year'];
    $path = '/token/v1/tokens/'.$payment_id;

    $request_data = array(
       'cardData' => array(
          'number' => $payment_info['card_number'],
          'expiration' => $exp_date,
       )
    );

    $data = call_sage('PUT',$path,null,$request_data,$error);
    if (! $data) return false;
    if ($data->http_status != 200) {
       $error = 'Saved Card Error: '.$data->message.' ('.$data->code.')';
       log_sage_error('Sage Error: '.$error);   return false;
    }
    return true;
}

function sage_delete_saved_card($db,$profile_id,$payment_id,&$error)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $exp_date = $payment_info['card_month'].$payment_info['card_year'];
    $path = '/token/v1/tokens/'.$payment_id;

    $data = call_sage('DELETE',$path,null,null,$error);
    if (! $data) return false;
    if ($data->http_status != 200) {
       $error = 'Saved Card Error: '.$data->message.' ('.$data->code.')';
       log_sage_error('Sage Error: '.$error);   return false;
    }
    return true;
}

?>
