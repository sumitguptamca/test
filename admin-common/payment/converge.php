<?php
/*
                Inroads Shopping Cart - Elavon Converge API Module

                        Written 2019 by Randall Severy
                         Copyright 2019 Inroads, LLC
*/

function converge_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'Elavon Converge','converge',$values);

    $dialog->add_edit_row('Merchant ID:','converge_merchantid',$values,30);
    $dialog->add_edit_row('User ID:','converge_userid',$values,30);
    $dialog->add_edit_row('PIN:','converge_pin',$values,30);
    $dialog->add_edit_row('Hostname:','converge_hostname',$values,30);
    $dialog->add_edit_row('URL Path:','converge_path',$values,30);
    $dialog->start_row('Transaction Type:','middle');
    $dialog->add_radio_field('converge_type','ccsale',
                             'Authorize and Capture',$values);
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('converge_type','ccauthonly','Authorize Only',
                             $values);
    $dialog->end_row();
    $dialog->start_row('Test Mode:','middle');
    $dialog->add_checkbox_field('converge_test','',$values);
    $dialog->end_row();
    if (! empty($enable_saved_cards)) {
       $dialog->start_row('Enable Saved Credit Cards:','middle');
       $dialog->add_checkbox_field('converge_saved_cards','',$values);
       $dialog->end_row();
    }
}

function converge_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('converge_merchantid','converge_userid','converge_pin',
       'converge_hostname','converge_path','converge_type','converge_test',
       'converge_saved_cards');
    if (! empty($website_settings)) $fields[] = 'converge_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function converge_payment_update_cart_config_field($field_name,
                                                   &$new_field_value,$db)
{
    if ($field_name == 'converge_test') {
       if (get_form_field('converge_test') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'converge_saved_cards') {
       if (get_form_field('converge_saved_cards') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'converge_active') {
       if (get_form_field('converge_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function converge_active($db)
{
    return get_cart_config_value('converge_active',$db);
}

function converge_get_primary_module($db)
{
    return 'converge';
}

function converge_saved_cards_enabled($db)
{
    $saved_flag = get_cart_config_value('converge_saved_cards',$db);
    if ($saved_flag == '1') return true;
    return false;
}

function append_converge_xml_data($xml_array,&$xml_string,&$log_string)
{
    foreach ($xml_array as $index => $value) {
       $xml_string .= '<'.$index.'>';   $log_string .= '<'.$index.'>';
       if (is_array($value))
          append_converge_xml_data($value,$xml_string,$log_string);
       else {
          $xml_string .= encode_xml_data($value);
          if (($index == 'ssl_card_number') || ($index == 'ssl_exp_date') ||
              ($index == 'ssl_cvv2cvc2') || ($index == 'ssl_merchant_id') ||
              /*($index == 'ssl_user_id') || */($index == 'ssl_pin'))
             $value = str_repeat('X',strlen($value));
          $log_string .= encode_xml_data($value);
       }
       $xml_string .= '</'.$index.'>';   $log_string .= '</'.$index.'>';
    }
}

function call_converge($db,$xml_array,&$error)
{
    $auth_array = array(
       'ssl_merchant_id' => get_cart_config_value('converge_merchantid',$db),
       'ssl_user_id' => get_cart_config_value('converge_userid',$db),
       'ssl_pin' => get_cart_config_value('converge_pin',$db)
    );
    $xml_array = array_merge($auth_array,$xml_array);
    $xml_string = '<txn>';
    $log_string = $xml_string;
    append_converge_xml_data($xml_array,$xml_string,$log_string);
    $xml_string .= '</txn>';   $log_string .= '</txn>';

    if (class_exists('Order'))
       @Order::log_payment('Sent: '.$log_string);
    else log_payment('Sent: '.$log_string);

    $host = get_cart_config_value('converge_hostname',$db);
    $path = get_cart_config_value('converge_path',$db);
    $url = 'https://'.$host.$path;
    require_once '../engine/http.php';

    $http = new HTTP($url);
    $response_data = $http->call('xmldata='.urlencode($xml_string));
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('Elavon Converge Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Elavon Converge Error: '.$error);
       else log_payment('Elavon Converge Error: '.$error);
       return null;
    }

    $response_data = str_replace("\n",'',$response_data);
    if (class_exists('Order'))
       @Order::log_payment('Response: '.$response_data);
    else log_payment('Response: '.$response_data);

    return $response_data;
}

function parse_ssl_result($response,&$error_message)
{
    $ssl_result = parse_xml_tag($response,'ssl_result');
    if ($ssl_result != '0') {
       $error_message = parse_xml_tag($response,'ssl_result_message');
       if (! $error_message)
          $error_message = parse_xml_tag($response,'errorMessage');
       if (! $error_message)
          $error_message = 'Unknown Elavon Converge Error';
       $error_name = parse_xml_tag($response,'errorName');
       if ($error_name) $error_message = $error_name.': '.$error_message;
       $error_code = parse_xml_tag($response,'errorCode');
       if ($error_code) $error_message .= ' ('.$error_code.')';
       return false;
    }
    return true;
}

function converge_process_payment(&$order)
{
    $site_name = get_cart_config_value('companyname');
    $billing_country_info = get_country_info($order->billing_country,$order->db);
    $shipping_country_info = get_country_info($order->shipping_country,$order->db);
if ($billing_country_info['code'] == 'US') $billing_country_info['code'] = 'USA';
else if ($billing_country_info['code'] == 'CA') $billing_country_info['code'] = 'CAN';
if ($shipping_country_info['code'] == 'US') $shipping_country_info['code'] = 'USA';
else if ($shipping_country_info['code'] == 'CA') $shipping_country_info['code'] = 'CAN';
    if (isset($order->info['tax']) && ($order->info['tax'] != ''))
       $tax = $order->info['tax'];
    else $tax = 0;
    $trans_type = get_cart_config_value('converge_type',$order->db);
    $test_mode = get_cart_config_value('converge_test',$order->db);
    if (isset($order->saved_card)) {
       $query = 'select * from saved_cards where profile_id=?';
       $query = $order->db->prepare_query($query,$order->saved_card);
       $card_info = $order->db->get_record($query);
       if (! $card_info) {
          if (isset($order->db->error)) $order->error = $order->db->error;
          else $order->error = 'Saved Credit Card not found';
          return false;
       }
       $exp_date = $card_info['card_month'].$card_info['card_year'];
    }
    else $exp_date = $order->credit_card['month'].$order->credit_card['year'];

    $xml_array = array('ssl_transaction_type' => $trans_type,
                       'ssl_amount' => $order->payment['payment_amount'],
                       'ssl_salestax' => $tax,
                       'ssl_exp_date' => $exp_date,
                       'ssl_description' => $site_name.' Shopping Cart Order',
                       'ssl_invoice_number' => $order->info['order_number'],
                       'ssl_customer_code' => $order->customer->info['id'],
                       'ssl_company' => substr($order->customer->info['company'],0,50),
                       'ssl_first_name' => substr($order->customer->info['fname'],0,20),
                       'ssl_last_name' => substr($order->customer->info['lname'],0,30),
                       'ssl_avs_address' => substr($order->customer->billing['address1'],0,30),
                       'ssl_address2' => substr($order->customer->billing['address2'],0,30),
                       'ssl_city' => substr($order->customer->billing['city'],0,30),
                       'ssl_state' => $order->customer->billing['state'],
                       'ssl_avs_zip' => str_replace('-','',$order->customer->billing['zipcode']),
                       'ssl_country' => $billing_country_info['code'],
                       'ssl_phone' => substr($order->customer->billing['phone'],0,20),
                       'ssl_email' => substr($order->customer->info['email'],0,100),
                       'ssl_ship_to_company' => substr($order->customer->shipping['shipto'],0,50),
                       'ssl_ship_to_first_name' => substr($order->customer->info['fname'],0,20),
                       'ssl_ship_to_last_name' => substr($order->customer->info['lname'],0,30),
                       'ssl_ship_to_address1' => substr($order->customer->shipping['address1'],0,30),
                       'ssl_ship_to_saddress2' => substr($order->customer->shipping['address2'],0,30),
                       'ssl_ship_to_city' => substr($order->customer->shipping['city'],0,30),
                       'ssl_ship_to_state' => $order->customer->shipping['state'],
                       'ssl_ship_to_zip' => $order->customer->shipping['zipcode'],
                       'ssl_ship_to_country' => $shipping_country_info['code']);
    if (isset($order->saved_card)) $xml_array['ssl_token'] = $order->saved_card;
    else {
       $xml_array['ssl_card_number'] = $order->credit_card['number'];
       $xml_array['ssl_cvv2cvc2_indicator'] = '1';
       $xml_array['ssl_cvv2cvc2'] = $order->credit_card['cvv'];
    }
    if (($order->credit_card['number'] == '4111111111111111') ||
        ($order->credit_card['number'] == '5000300020003003') ||
        ($order->credit_card['number'] == '4124939999999990')) {
       $xml_array['ssl_test_mode'] = 'true';
       $xml_array['ssl_amount'] = .01;
    }
    else if ($test_mode) $xml_array['ssl_test_mode'] = 'true';

    if (function_exists('update_converge_data'))
       update_converge_data($order,$xml_array);

    $response = call_converge($order->db,$xml_array,$error);
    if (! $response) {
       $order->error = $error_message;   return false;
    }

    if (! parse_ssl_result($response,$error_message)) {
       log_error('Elavon Converge Declined: '.$error_message);
       $order->error = $error_message;   return false;
    }
    $order->payment['payment_id'] = parse_xml_tag($response,'ssl_txn_id');
    $order->payment['payment_code'] =
       parse_xml_tag($response,'ssl_approval_code');
    if ($trans_type == 'ccauthonly') {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('Elavon Converge Authorization Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Approval Code ' .
                    $order->payment['payment_code']);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('Elavon Converge Payment Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Approval Code ' .
                    $order->payment['payment_code']);
    }
    return true;
}

function converge_capture_payment($db,$payment_info,&$error)
{
    global $order_label;

    $payment_id = $payment_info['payment_id'];
    $xml_array = array('ssl_transaction_type' => 'cccomplete',
                       'ssl_txn_id' => $payment_id,
                       'ssl_amount' => $payment_info['payment_amount']);
    $response = call_converge($db,$xml_array,$error);
    if (! $response) return false;
    if (! parse_ssl_result($response,$error_message)) {
       $error = 'Elavon Converge Capture Declined: '.$error_message;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       log_error($error);   return false;
    }
    $capture_id = parse_xml_tag($response,'ssl_txn_id');
    $approval_code = parse_xml_tag($response,'ssl_approval_code');
    $order_id = $payment_info['parent'];
    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_info['id'];
    $payment_record['payment_status']['value'] = PAYMENT_CAPTURED;
    $payment_record['payment_id']['value'] = $capture_id;
    $payment_record['payment_code']['value'] = $approval_code;
    $payment_record['payment_date']['value'] = time();
    if (! $db->update('order_payments',$payment_record)) {
       $error = $db->error;   return false;
    }
    log_activity('Updated Payment #'.$payment_info['id'].' for ' .
                 $order_label.' #'.$order_id);

    log_activity('Captured Elavon Converge Transaction #'.$payment_id .
                 ' with Transaction ID #'.$capture_id.' and Approval Code ' .
                 $approval_code.' for Order #'.$order_id);
    return true;
}

function converge_cancel_payment($db,$payment_info,$refund_amount,
                                  &$cancel_info,&$error)
{
    $order_id = $payment_info['parent'];
    $payment_status = $payment_info['payment_status'];
    if ($payment_status == PAYMENT_AUTHORIZED) {
       $trans_type = 'ccvoid';   $description = 'Void';
    }
    else {
       $trans_type = 'ccreturn';   $description = 'Refund';
    }
    $description .= ' for Order #'.$order_id;
    $payment_id = $payment_info['payment_id'];
    $xml_array = array('ssl_transaction_type' => $trans_type,
                       'ssl_txn_id' => $payment_id);

    if ($trans_type == 'cccredit') $xml_array['ssl_amount'] = $refund_amount;
    $response = call_converge($db,$xml_array,$error);
    if (! $response) return false;
    if (! parse_ssl_result($response,$error_message)) {
       $error = 'Elavon Converge '.$description.' Error: '.$error_message;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       log_error($error);   return false;
    }
    $cancel_info['payment_id'] = parse_xml_tag($response,'ssl_txn_id');
    $cancel_info['payment_code'] = parse_xml_tag($response,'ssl_approval_code');
    $cancel_info['payment_data'] = $response;
    if ($payment_status == PAYMENT_AUTHORIZED)
       log_activity('Voided Elavon Converge Transaction #'.$payment_id .
                    ' for Order #'.$order_id);
    else log_activity('Refunded Elavon Converge Transaction #'.$payment_id .
                      ' for Order #'.$order_id);
    return true;
}

function converge_create_saved_profile($db,$customer_id,&$error)
{
/*  Converge doesn't use Profiles, so use Customer ID as the Profile ID */
    return $customer_id;
}

function converge_delete_saved_profile($db,$profile_id,&$error)
{
/*  Converge doesn't use Profiles, so just pretend to delete the profile */
    return true;
}

function converge_create_saved_card($db,$profile_id,$payment_info,&$error)
{
    $exp_date = $payment_info['card_month'].$payment_info['card_year'];
    $xml_array = array('ssl_transaction_type' => 'ccgettoken',
                       'ssl_card_number' => $payment_info['card_number'],
                       'ssl_exp_date' => $exp_date,
                       'ssl_cvv2cvc2' => $payment_info['card_cvv'],
                       'ssl_first_name' => substr($payment_info['fname'],0,20),
                       'ssl_last_name' => substr($payment_info['lname'],0,30),
                       'ssl_avs_address' => substr($payment_info['address1'],0,30),
                       'ssl_avs_zip' => str_replace('-','',$payment_info['zipcode']),
                       'ssl_add_token' => 'Y');
    $test_mode = get_cart_config_value('converge_test',$db);
    if ($test_mode) $xml_array['ssl_test_mode'] = 'true';

    $response = call_converge($db,$xml_array,$error);
    if (! $response) return false;
    if (! parse_ssl_result($response,$error_message)) {
       $error = 'Elavon Converge Get Token Error: '.$error_message;
       return null;
    }
    $token_response = parse_xml_tag($response,'ssl_token_response');
    if ($token_response != 'SUCCESS') {
       $error = 'Elavon Converge Get Token Error: '.$token_response.': ' .
                parse_xml_tag($response,'ssl_add_token_response');
       return null;
    }
    $payment_id = parse_xml_tag($response,'ssl_token');
    if (! $payment_id) $error = 'Missing Payment Profile ID from Response';
    return $payment_id;
}

function converge_update_saved_card($db,$profile_id,$payment_id,$payment_info,
                                     &$error)
{
    $exp_date = $payment_info['card_month'].$payment_info['card_year'];
    $xml_array = array('ssl_transaction_type' => 'ccupdatetoken',
                       'ssl_token' => $profile_id,
                       'ssl_card_number' => $payment_info['card_number'],
                       'ssl_exp_date' => $exp_date,
                       'ssl_cvv2cvc2' => $payment_info['card_cvv'],
                       'ssl_first_name' => substr($payment_info['fname'],0,20),
                       'ssl_last_name' => substr($payment_info['lname'],0,30),
                       'ssl_avs_address' => substr($payment_info['address1'],0,30),
                       'ssl_avs_zip' => str_replace('-','',$payment_info['zipcode']));

    $response = call_converge($db,$xml_array,$error);
    if (! $response) return false;
    if (! parse_ssl_result($response,$error_message)) {
       $error = 'Elavon Converge Update Token Error: '.$error_message;
       return null;
    }
    $token_response = parse_xml_tag($response,'ssl_token_response');
    if ($token_response != 'SUCCESS') {
       $error = 'Elavon Converge Update Token Error: '.$token_response;
       return null;
    }
    $payment_id = parse_xml_tag($response,'ssl_token');
    if (! $payment_id) $error = 'Missing Payment Profile ID from Response';
    return $payment_id;
}

function converge_delete_saved_card($db,$profile_id,$payment_id,&$error)
{
    $xml_array = array('ssl_transaction_type' => 'ccdeletetoken',
                       'ssl_token' => $profile_id);

    $response = call_converge($db,$xml_array,$error);
    if (! $response) return false;
    if (! parse_ssl_result($response,$error_message)) {
       $error = 'Elavon Converge Delete Token Error: '.$error_message;
       return false;
    }
    $token_response = parse_xml_tag($response,'ssl_token_response');
    if ($token_response != 'SUCCESS') {
       $error = 'Elavon Converge Delete Token Error: '.$token_response;
       return false;
    }
    return true;
}

?>
