<?php
/*
                  Inroads Shopping Cart - Payflow Pro API Module

                       Written 2011-2018 by Randall Severy
                        Copyright 2011-2018 Inroads, LLC
*/

define ('PFP_LIVE_HOSTNAME','payflowpro.paypal.com');
define ('PFP_TEST_HOSTNAME','pilot-payflowpro.paypal.com');
define ('PFP_PATH','/transaction');

function payflowpro_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'Payflow Pro','payflowpro',$values);

    $dialog->add_edit_row('Partner ID:','payflowpro_partner_id',$values,30);
    $dialog->add_edit_row('Vendor ID:','payflowpro_vendorid',$values,30);
    $dialog->add_edit_row('User ID:','payflowpro_userid',$values,30);
    $dialog->add_edit_row('Password:','payflowpro_password',$values,30);
    $dialog->add_edit_row('Transaction Type:','payflowpro_type',$values,1);
    $dialog->start_row('Test Mode:','middle');
    $dialog->add_checkbox_field('payflowpro_test','',$values);
    $dialog->end_row();
}

function payflowpro_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('payflowpro_partner_id','payflowpro_vendorid',
       'payflowpro_userid','payflowpro_password','payflowpro_type',
       'payflowpro_test');
    if (! empty($website_settings)) $fields[] = 'payflowpro_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function payflowpro_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'payflowpro_test') {
       if (get_form_field('payflowpro_test') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'payflowpro_active') {
       if (get_form_field('payflowpro_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function payflowpro_active($db)
{
    return get_cart_config_value('payflowpro_active',$db);
}

function payflowpro_get_primary_module($db)
{
    return 'payflowpro';
}

function call_payflowpro($post_array,$order_id,&$error)
{
    $vendorid = get_cart_config_value('payflowpro_vendorid');
    $userid = get_cart_config_value('payflowpro_userid');
    if (! $vendorid) $vendorid = $userid;

    $auth_array = array('PARTNER' => get_cart_config_value('payflowpro_partner_id'),
                        'VENDOR' => $vendorid,
                        'USER' => $userid,
                        'PWD' => get_cart_config_value('payflowpro_password'));
    $post_array = array_merge($auth_array,$post_array);

    $post_string = '';
    foreach ($post_array as $key => $value) {
       if ($post_string != '') $post_string .= '&';
       $value = str_replace("\"",'',$value);
       $post_string .= $key.'['.strlen($value).']='.$value;
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if (($key == 'ACCT') || ($key == 'EXPDATE') ||
           ($key == 'CVV2') || ($key == 'PWD')) continue;
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.$value;
    }

    if (class_exists('Order'))
       @Order::log_payment('Sent: '.$log_string);
    else log_payment('Sent: '.$log_string);

    $test_mode = get_cart_config_value('payflowpro_test');
    if ($test_mode) $url = 'https://'.PFP_TEST_HOSTNAME.PFP_PATH;
    else $url = 'https://'.PFP_LIVE_HOSTNAME.PFP_PATH;
    $request_id = $order_id.time();
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $http->set_content_type('text/namevalue');
    $http->set_headers(array('X-VPS-REQUEST-ID: '.$request_id,
       'X-VPS-CLIENT-TIMEOUT: 60',
       'X-VPS-VIT-INTEGRATION-PRODUCT: Inroads Shopping Cart',
       'X-VPS-VIT-INTEGRATION-VERSION: 2.0'));
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('PayFlowPro Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('PayFlowPro Error: '.$error);
       else log_payment('PayFlowPro Error: '.$error);
       return null;
    }
    if (class_exists('Order'))
       @Order::log_payment('Response: '.$response_data);
    else log_payment('Response: '.$response_data);
    $response_array = array();
    $result_array = explode('&',$response_data);
    foreach ($result_array as $index => $field_data) {
       $field_pair = explode('=',$field_data);
       $response_array[$field_pair[0]] = $field_pair[1];
    }
    return $response_array;
}

function payflowpro_process_payment(&$order)
{
    $exp_date = $order->credit_card['month'].$order->credit_card['year'];
    $billing_country_info = get_country_info($order->billing_country,$order->db);
    $trans_type = get_cart_config_value('payflowpro_type');
    if (! $trans_type) $trans_type = 'S';
    $order_id = $order->info['id'];
    if (isset($order->customer->billing['state']))
       $state = $order->customer->billing['state'];
    else $state = '';

    $post_array = array('TRXTYPE' => $trans_type,
                        'TENDER' => 'C',
                        'FIRSTNAME' => $order->customer->info['fname'],
                        'LASTNAME' => $order->customer->info['lname'],
                        'STREET' => $order->customer->billing['address1'],
                        'CITY' => $order->customer->billing['city'],
                        'STATE' => $state,
                        'ZIP' => $order->customer->billing['zipcode'],
                        'BILLTOCOUNTRY' => $billing_country_info['code'],
                        'ACCT' => $order->credit_card['number'],
                        'EXPDATE' => $exp_date,
                        'AMT' => number_format($order->payment['payment_amount'],2,'.',''),
                        'CVV2' => $order->credit_card['cvv'],
                        'CUSTIP' => $_SERVER['REMOTE_ADDR'],
                        'COMMENT1' => $order_id,
                        'COMMENT2' => $order->customer->info['email']);

    if (function_exists('update_payflowpro_data'))
       update_payflowpro_data($order,$post_array);

    $response_array = call_payflowpro($post_array,$order_id,$error);
    if (! $response_array) {
       $order->log_payment('Payflow Pro Error: '.$error);
       log_error('Payflow Pro Error: '.$error);
       $order->error = $error;   return false;
    }

    if (! isset($response_array['RESULT'])) {
       $error_message = 'Invalid Payflow Pro Response';
       log_error('Payflow Pro Declined: '.$error_message);
       $order->error = 'Card Declined';   return false;
    }
    $result_code = $response_array['RESULT'];
    if (isset($response_array['PNREF'])) $reference = $response_array['PNREF'];
    else $reference = '';
    if (isset($response_array['AUTHCODE']))
       $auth_code = $response_array['AUTHCODE'];
    else $auth_code = '';
    if (isset($response_array['RESPMSG']))
       $response_message = $response_array['RESPMSG'];
    else $response_message = '';
    if (isset($response_array['PPREF']))
       $paypal_reference = $response_array['PPREF'];
    else $paypal_reference = '';

    if ($result_code != '0') {
       log_error('Payflow Pro Declined: '.$response_message.' (' .
                 $result_code.')');
       $order->error = 'Card Declined';   return false;
    }
    $order->payment['payment_id'] = $reference;
    $order->payment['payment_code'] = $auth_code;
    $order->payment['payment_ref'] = $paypal_reference;
    if ($trans_type == 'S') {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('Payflow Pro Payment Accepted with Reference #' .
                    $reference.' and Authorization Code '.$auth_code);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('Payflow Pro Authorization Accepted with Reference #' .
                    $reference.' and Authorization Code '.$auth_code);
    }
    return true;
}

function payflowpro_capture_payment($db,$payment_info,&$error)
{
    global $order_label;

    $payment_id = $payment_info['payment_id'];
    $post_array = array('TRXTYPE' => 'D',
                        'ORIGID' => $payment_id);
    $order_id = $payment_info['parent'];

    $response_array = call_payflowpro($post_array,$order_id,$error);
    if (! $response_array) {
       $error = 'Payflow Pro Error: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return false;
    }

    if (! isset($response_array['RESULT'])) {
       $error = 'Payflow Pro Error: Invalid Payflow Pro Response';
       return false;
    }
    $result_code = $response_array['RESULT'];
    if (isset($response_array['PNREF'])) $reference = $response_array['PNREF'];
    else $reference = '';
    if (isset($response_array['RESPMSG']))
       $response_message = $response_array['RESPMSG'];
    else $response_message = '';
    if (isset($response_array['PPREF']))
       $paypal_reference = $response_array['PPREF'];
    else $paypal_reference = '';

    if ($result_code != '0') {
       $error = 'Payflow Pro Error: '.$response_message.' (' .
                 $result_code.')';
       return false;
    }
    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_info['id'];
    $payment_record['payment_status']['value'] = PAYMENT_CAPTURED;
    $payment_record['payment_id']['value'] = $reference;
    $payment_record['payment_ref']['value'] = $paypal_reference;
    $payment_record['payment_date']['value'] = time();
    if (! $db->update('order_payments',$payment_record)) {
       $error = $db->error;   return false;
    }
    log_activity('Updated Payment #'.$payment_info['id'].' for ' .
                 $order_label.' #'.$order_id);

    log_activity('Captured Payflow Pro Transaction #'.$payment_id .
                 ' with Reference #'.$reference.' and PayPal Reference #' .
                 $paypal_reference.' for Order #'.$order_id);
    return true;
}

function payflowpro_cancel_payment($db,$payment_info,$refund_amount,
                                   &$cancel_info,&$error)
{
    $payment_status = $payment_info['payment_status'];
    if ($payment_status == PAYMENT_AUTHORIZED) $trans_type = 'V';
    else $trans_type = 'C';
    $payment_id = $payment_info['payment_id'];
    $post_array = array('TRXTYPE' => $trans_type,
                        'ORIGID' => $payment_id,
                        'AMT' => number_format($refund_amount,2,'.',''));
    $order_id = $payment_info['parent'];

    $response_array = call_payflowpro($post_array,$order_id,$error);
    if (! $response_array) {
       $error = 'Payflow Pro Error: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return false;
    }

    if (! isset($response_array['RESULT'])) {
       $error = 'Payflow Pro Error: Invalid Payflow Pro Response';
       return false;
    }
    $result_code = $response_array['RESULT'];
    if (isset($response_array['RESPMSG']))
       $response_message = $response_array['RESPMSG'];
    else $response_message = '';

    if ($result_code != '0') {
       $error = 'Payflow Pro Error: '.$response_message.' (' .
                 $result_code.')';
       return false;
    }
    if ($trans_type == 'V')
       log_activity('Voided Payflow Pro Transaction #'.$payment_id .
                    ' for Order #'.$order_id);
    else log_activity('Refunded Payflow Pro Transaction #'.$payment_id .
                      ' for Order #'.$order_id);
    return true;
}

?>
