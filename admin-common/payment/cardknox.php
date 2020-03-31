<?php
/*
                 Inroads Shopping Cart - Cardknox API Module

                      Written 2019 by Randall Severy
                       Copyright 2019 Inroads, LLC
*/

define ('CARDKNOX_API_URL','https://x1.cardknox.com/gateway');
define ('CARDKNOX_RECURRING_URL','https://x1.cardknox.com/recurring');
define ('CARDKNOX_IFIELDS_VERSION','2.4.1812.1101');

global $log_cart_errors_enabled;
if (! function_exists('log_cart_error')) $log_cart_errors_enabled = false;

function cardknox_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'Cardknox','cardknox',$values);

    $dialog->add_edit_row('API Key:','cardknox_key',$values,50);
    $dialog->add_edit_row('iFields Key:','cardknox_ifields_key',$values,50);
    if (empty($values['cardknox_type']))
       $values['cardknox_type'] = 'Sale';
    $dialog->start_row('Transaction Type:','middle');
    $dialog->add_radio_field('cardknox_type','Sale',
                             'Authorize and Capture',$values);
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('cardknox_type','AuthOnly','Authorize Only',
                             $values);
    $dialog->end_row();
    $dialog->start_row('Customer Notifications:','middle');
    $dialog->add_checkbox_field('cardknox_notify','',$values);
    $dialog->end_row();
    $dialog->start_row('Include eChecks:','middle');
    $dialog->add_checkbox_field('cardknox_echeck','',$values);
    $dialog->end_row();
    if (! empty($enable_saved_cards)) {
       $dialog->start_row('Enable Saved Credit Cards:','middle');
       $dialog->add_checkbox_field('cardknox_saved_cards','',$values);
       $dialog->end_row();
    }
}

function cardknox_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('cardknox_key','cardknox_ifields_key',
                    'cardknox_type','cardknox_notify','cardknox_echeck',
                    'cardknox_saved_cards');
    if (! empty($website_settings)) $fields[] = 'cardknox_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function cardknox_payment_update_cart_config_field($field_name,
                                                    &$new_field_value,$db)
{
    $check_fields = array('cardknox_notify','authorize_echeck',
                          'cardknox_saved_cards','cardknox_active');

    foreach ($check_fields as $field) {
       if ($field_name == $field) {
          if (get_form_field($field) == 'on') $new_field_value = '1';
          else $new_field_value = '0';
          return true;
       }
    }
    return false;
}

function cardknox_active($db)
{
    return get_cart_config_value('cardknox_active',$db);
}

function cardknox_get_primary_module($db)
{
    return 'cardknox';
}

function cardknox_saved_cards_enabled($db)
{
    $saved_flag = get_cart_config_value('cardknox_saved_cards',$db);
    if ($saved_flag == '1') return true;
    return false;
}

function cardknox_echecks_enabled($db)
{
    $echeck_flag = get_cart_config_value('cardknox_echeck',$db);
    if ($echeck_flag) return true;
    return false;
}

function call_cardknox($post_array,&$response_data,&$error)
{
    $post_string = '';
    foreach ($post_array as $key => $value) {
       if ($post_string) $post_string .= '&';
       $post_string .= $key.'='.urlencode($value);
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if ($log_string) $log_string .= '&';
       if (($key == 'xCardNum') || ($key == 'xExp') || ($key == 'xKey') ||
           ($key == 'xCVV') || ($key == 'xToken')) $log_string .= $key.'=XXXX';
       else $log_string .= $key.'='.urlencode($value);
    }

    if (class_exists('Order'))
       @Order::log_payment('Sent: '.$log_string);
    else log_payment('Sent: '.$log_string);

    $host = get_cart_config_value('cardknox_hostname');

    require_once '../engine/http.php';
    $http = new HTTP(CARDKNOX_API_URL);
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('Cardknox Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Cardknox Error: '.$error);
       else log_payment('Cardknox Error: '.$error);
       return null;
    }

    if (class_exists('Order'))
       @Order::log_payment('Response: '.$response_data);
    else log_payment('Response: '.$response_data);
    $response = explode('&',$response_data);
    $result = array();
    foreach ($response as $name_value) {
       $parts = explode('=',$name_value);
       if (count($parts) != 2) continue;
       $result[urldecode($parts[0])] = urldecode($parts[1]);
    }
    return $result;
}

function set_cardknox_api_fields(&$post_array)
{
    $api_fields = array('xKey' => get_cart_config_value('cardknox_key'),
                        'xVersion' => '4.5.8',
                        'xSoftwareName' => 'AxiumPro',
                        'xSoftwareVersion' => '3.0');
    $post_array = array_merge($api_fields,$post_array);
}

function cardknox_process_payment(&$order)
{
    global $use_state_tax_table,$log_cart_errors_enabled,$cardknox_token;

    if (! isset($order->customer->info)) {
       $order->error = 'Missing Customer Information';
       log_error($order->error.' for Cardknox');
       return false;
    }
    if (! isset($use_state_tax_table)) $use_state_tax_table = true;
    $address = $order->customer->billing['address1'];
    $address2 = $order->customer->billing['address2'];
    if (! empty($address2)) $address .= ' '.$address2;
    if (get_cart_config_value('cardknox_notify') == '1')
       $email_customer = 'True';
    else $email_customer = 'False';
    $site_name = get_cart_config_value('companyname');
    if (isset($order->info['tax'])) $tax = $order->info['tax'];
    else $tax = 0;
    $bill_state = $order->customer->get('bill_state');
    $ship_state = $order->customer->get('ship_state');
    $billing_phone = $order->customer->billing['phone'];
    $billing_phone = str_replace('+','',$billing_phone);
    $billing_phone = str_replace(' ','',$billing_phone);
    $billing_phone = str_replace('-','',$billing_phone);
    $billing_phone = str_replace(',','',$billing_phone);
    $billing_mobile = $order->customer->billing['mobile'];
    $billing_mobile = str_replace('+','',$billing_mobile);
    $billing_mobile = str_replace(' ','',$billing_mobile);
    $billing_mobile = str_replace('-','',$billing_mobile);
    $billing_mobile = str_replace(',','',$billing_mobile);
    $billing_fax = $order->customer->billing['fax'];
    $billing_fax = str_replace('+','',$billing_fax);
    $billing_fax = str_replace(' ','',$billing_fax);
    $billing_fax = str_replace('-','',$billing_fax);
    $billing_fax = str_replace(',','',$billing_fax);
    $auth_type = get_cart_config_value('cardknox_type');
    if (isset($order->echeck)) $auth_type = 'check:'.$auth_type;
    else $auth_type = 'cc:'.$auth_type;
    if (empty($_SERVER['REMOTE_ADDR'])) $remote_addr = '';
    else $remote_addr = $_SERVER['REMOTE_ADDR'];
    if (! empty($order->info['comments']))
       $comments = trim($order->info['comments']);
    else $comments = '';

    $post_array = array('xCommand' => $auth_type,
                        'xAmount' => $order->payment['payment_amount'],
                        'xStreet' => trim($address),
                        'xZip' => trim($order->customer->billing['zipcode']),
                        'xTax' => $tax,
                        'xComments' => $comments,
                        'xIP' => $remote_addr,
                        'xEmail' => trim($order->customer->info['email']),
                        'xFax' => $billing_fax,
                        'xBillFirstName' => trim($order->customer->info['fname']),
                        'xBillMiddleName' => trim($order->customer->info['mname']),
                        'xBillLastName' => trim($order->customer->info['lname']),
                        'xBillCompany' => trim($order->customer->info['company']),
                        'xBillStreet' => trim($address),
                        'xBillCity' => trim($order->customer->billing['city']),
                        'xBillState' => trim($bill_state),
                        'xBillZip' => trim($order->customer->billing['zipcode']),
                        'xBillCountry' => $order->get('bill_country_code'),
                        'xBillPhone' => $billing_phone,
                        'xBillMobile' => $billing_mobile,
                        'xShipFirstName' => trim($order->customer->info['fname']),
                        'xShipMiddleName' => trim($order->customer->info['mname']),
                        'xShipLastName' => trim($order->customer->info['lname']),
                        'xShipCompany' => trim($order->customer->shipping['shipto']),
                        'xShipStreet' => trim($order->customer->shipping['address1']),
                        'xShipStreet2' => trim($order->customer->shipping['address2']),
                        'xShipCity' => trim($order->customer->shipping['city']),
                        'xShipState' => trim($ship_state),
                        'xShipZip' => trim($order->customer->shipping['zipcode']),
                        'xShipCountry' => $order->get('ship_country_code'),
                        'xShipPhone' => $billing_phone,
                        'xShipMobile' => $billing_mobile,
                        'xOrderID' => $order->info['order_number'],
                        'xCustReceipt' => $email_customer,
                        'xDescription' => $site_name.' Shopping Cart Order',
                        'xCurrency' => $order->info['currency']);
    set_cardknox_api_fields($post_array);
    if (! empty($order->info['purchase_order']))
       $post_array['xPONum'] = $order->info['purchase_order'];

    if (isset($order->echeck)) {
       $post_array['xRouting'] = $order->echeck['routing_number'];
       $post_array['xAccount'] = $order->echeck['account_number'];
       $post_array['xName'] = $order->echeck['account_name'];
    }
    else if (isset($order->saved_card))
       $post_array['xToken'] = $order->saved_card;
    else {
       $exp_date = $order->credit_card['month'].$order->credit_card['year'];
       $post_array['xCardNum'] = $order->credit_card['number'];
       $post_array['xExp'] = $exp_date;
       $post_array['xCVV'] = $order->credit_card['cvv'];
       $post_array['xName'] = $order->credit_card['name'];
       if ($post_array['xCardNum'] == '4111111111111111') 
          $post_array['xAmount'] = .01;
    }
    if (function_exists('update_cardknox_post_array'))
       update_cardknox_post_array($order,$post_array);

    $result = call_cardknox($post_array,$response_data,$error);
    if (! $result) {
       $order->error = $error;   return false;
    }

    if (empty($result['xResult']) || ($result['xResult'] != 'A')) {
       if (empty($result['xResult'])) $error = $response_data;
       else $error = $result['xError'].' ('.$result['xErrorCode'].')';
       if (empty($result['xStatus'])) $status = 'Error';
       else $status = $result['xStatus'];
       if (! empty($log_cart_errors_enabled))
          log_cart_error('Cardknox '.$status.': '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Cardknox '.$status.': '.$error);
       else log_payment('Cardknox '.$status.': '.$error);
       if (isset($order->echeck)) $order->error = 'Check '.$status.': '.$error;
       else $order->error = 'Card '.$status.': '.$error;
       return false;
    }

    $cardknox_token = $result['xToken'];
    $order->credit_card['number'] = $result['xMaskedCardNumber'];
    $order->credit_card['type'] = $result['xCardType'];
    $order->payment['payment_id'] = $result['xRefNum'];
    $order->payment['payment_code'] = $result['xAuthCode'];
    $order->payment['payment_data'] = $response_data;
    if (($auth_type == 'cc:AuthOnly') || ($auth_type == 'check:AuthOnly')) {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('Cardknox Authorization Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('Cardknox Payment Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    return true;
}

function cardknox_capture_payment($db,$payment_info,&$error)
{
    global $order_label;

    $payment_id = $payment_info['payment_id'];
    $post_array = array('xCommand' => 'cc:Capture',
                        'xRefNum' => $payment_id);
    set_cardknox_api_fields($post_array);

    $result = call_cardknox($post_array,$response_data,$error);
    if (! $result) return false;

    if (empty($result['xResult']) || ($result['xResult'] != 'A')) {
       if (empty($result['xResult'])) $error = $response_data;
       else $error = $result['xError'].' ('.$result['xErrorCode'].')';
       $error = 'Cardknox Capture Declined: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return false;
    }

    $capture_id = $result['xRefNum'];
    $payment_code = $result['xAuthCode'];

    $order_id = $payment_info['parent'];
    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_info['id'];
    $payment_record['payment_status']['value'] = PAYMENT_CAPTURED;
    $payment_record['payment_id']['value'] = $capture_id;
    $payment_record['payment_date']['value'] = time();
    if (! $db->update('order_payments',$payment_record)) {
       $error = $db->error;   return false;
    }
    log_activity('Updated Payment #'.$payment_info['id'].' for ' .
                 $order_label.' #'.$order_id);

    log_activity('Captured Cardknox Transaction #'.$payment_id .
                 ' with Transaction ID #'.$capture_id.' and Authorization Code ' .
                 $payment_code.' for Order #'.$order_id);
    return true;
}

function cardknox_cancel_payment($db,$payment_info,$refund_amount,
                                  &$cancel_info,&$error)
{
    $order_id = $payment_info['parent'];
    $payment_status = $payment_info['payment_status'];
    if ($payment_status == PAYMENT_AUTHORIZED) $trans_type = 'cc:Void';
    else $trans_type = 'cc:Refund';
    $payment_id = $payment_info['payment_id'];
    $post_array = array('xCommand' => $trans_type,
                        'xRefNum' => $payment_id,
                        'xAmount' => $refund_amount);
    set_cardknox_api_fields($post_array);
    if ($trans_type == 'cc:Refund')
       $post_array['xDescription'] = 'Refund for Order #'.$order_id;

    $result = call_cardknox($post_array,$response_data,$error);
    if (! $result) return false;

    if (empty($result['xResult']) || ($result['xResult'] != 'A')) {
       if (empty($result['xResult'])) $error = $response_data;
       else $error = $result['xError'].' ('.$result['xErrorCode'].')';
       $error = 'Cardknox '.$trans_type.' Error: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return false;
    }

    $cancel_info['payment_id'] = $result['xRefNum'];
    $cancel_info['payment_code'] = $result['xAuthCode'];
    $cancel_info['payment_data'] = $response_data;
    if ($trans_type == 'cc:Void')
       log_activity('Voided Cardknox Transaction #'.$payment_id .
                    ' for Order #'.$order_id);
    else log_activity('Refunded Cardknox Transaction #'.$payment_id .
                      ' for Order #'.$order_id);
    return true;
}

function cardknox_create_saved_profile($db,$customer_id,&$error)
{
/*  Cardknox doesn't use Profiles, so use Customer ID as the Profile ID */
    return $customer_id;
}

function cardknox_delete_saved_profile($db,$profile_id,&$error)
{
/*  Cardknox doesn't use Profiles, so just pretend to delete the profile */
    return true;
}

function cardknox_create_saved_card($db,$profile_id,$payment_info,&$error)
{
    global $cardknox_token;

    if (! empty($cardknox_token)) return $cardknox_token;

    $expiration_date = $payment_info['card_month'].$payment_info['card_year'];
    if (empty($_SERVER['REMOTE_ADDR'])) $remote_addr = '';
    else $remote_addr = $_SERVER['REMOTE_ADDR'];
    $post_array = array('xCommand' => 'cc:Save',
                        'xCardNum' => $payment_info['card_number'],
                        'xExp' => $expiration_date,
                        'xStreet' => $payment_info['address1'],
                        'xZip' => $payment_info['zipcode'],
                        'xName' => $payment_info['card_name'],
                        'xIP' => $remote_addr);
    set_cardknox_api_fields($post_array);

    $result = call_cardknox($post_array,$response_data,$error);
    if (! $result) return null;

    if (empty($result['xResult']) || ($result['xResult'] != 'A')) {
       if (empty($result['xResult'])) $error = $response_data;
       else $error = $result['xError'].' ('.$result['xErrorCode'].')';
       $error = 'Cardknox Save Card Error: '.$error;
       log_error($error);   return null;
    }

    $payment_id = $result['xToken'];
    if (! $payment_id) $error = 'Missing Cardknox Token from Response';
    return $payment_id;
}

function cardknox_update_saved_card($db,$profile_id,&$payment_id,$payment_info,
                                     &$error)
{
    $expiration_date = $payment_info['card_month'].$payment_info['card_year'];
    if (empty($_SERVER['REMOTE_ADDR'])) $remote_addr = '';
    else $remote_addr = $_SERVER['REMOTE_ADDR'];
    $post_array = array('xCommand' => 'cc:Save',
                        'xExp' => $expiration_date,
                        'xStreet' => $payment_info['address1'],
                        'xZip' => $payment_info['zipcode'],
                        'xName' => $payment_info['card_name'],
                        'xIP' => $remote_addr);
    $old_card_number = get_form_field('old_card_number');
    if ($old_card_number == $payment_info['card_number'])
       $post_array['xToken'] = $payment_id;
    else $post_array['xCardNum'] = $payment_info['card_number'];
    set_cardknox_api_fields($post_array);

    $result = call_cardknox($post_array,$response_data,$error);
    if (! $result) return null;

    if (empty($result['xResult']) || ($result['xResult'] != 'A')) {
       if (empty($result['xResult'])) $error = $response_data;
       else $error = $result['xError'].' ('.$result['xErrorCode'].')';
       $error = 'Cardknox Save Card Error: '.$error;
       log_error($error);   return false;
    }

    $payment_id = $result['xToken'];
    if (! $payment_id) {
       $error = 'Missing Cardknox Token from Response';   return false;
    }
    return true;
}

function cardknox_delete_saved_card($db,$profile_id,$payment_id,&$error)
{
/*  Cardknox doesn't support deleting saved cards, so just pretend to
    delete the saved card */
    return true;
}

function cardknox_setup_order_dialog($db,&$dialog,$edit_type)
{
    $ifields_key = get_cart_config_value('cardknox_ifields_key',$db);
    $dialog->ifields_key = $ifields_key;
    if (! $ifields_key) return;
    $head_line = '<script>var ifields_key = \''.$ifields_key.'\';</script>';
    $dialog->add_head_line($head_line);
    $dialog->add_script_file('../admin/payment/cardknox-ifields.js');
    $dialog->add_script_file('https://cdn.cardknox.com/ifields/' .
                             CARDKNOX_IFIELDS_VERSION.'/ifields.min.js',null);
    if ($dialog->onload_function)
       $onload_function = $dialog->onload_function .
                          ' cardknox_ifields_onload();';
    else $onload_function = 'cardknox_ifields_onload();';
    $dialog->set_onload_function($onload_function);
}

function cardknox_write_card_dialog_field(&$dialog,&$order,$field_name)
{
    if (! $dialog->ifields_key) return false;
    if (($field_name != 'card_number') && ($field_name != 'card_cvv'))
       return false;
    ob_start();
    cardknox_write_card_field($dialog,$field_name);
    $field = ob_get_contents();
    ob_end_clean();
    $dialog->write($field);
    return true;
}

function cardknox_configure_checkout(&$cart)
{
    if ($cart->payment_module == 'cardknox')
       $cart->enable_echecks = get_cart_config_value('cardknox_echeck',
                                                     $cart->db);
}

function cardknox_write_checkout_form(&$cart)
{
    $cart->ifields_key = get_cart_config_value('cardknox_ifields_key',
                                               $cart->db);
    if (! $cart->ifields_key) return false;
    print '<script>var ifields_key = \''.$cart->ifields_key."';</script>\n";
    print '<script src="admin/payment/cardknox-ifields.js?v=' .
          filemtime('../admin/payment/cardknox-ifields.js').'"></script>'."\n";
    print '<script src="https://cdn.cardknox.com/ifields/' .
          CARDKNOX_IFIELDS_VERSION.'/ifields.min.js">' .
          '</script>'."\n";
    print '<script>add_onload(cardknox_ifields_onload);</script>'."\n";
    return false;
}

function cardknox_write_card_field(&$cart,$field_name)
{
    if (! $cart->ifields_key) return false;
    if ($field_name == 'card_number') {
       $mobile_cart = get_cookie('mobile_cart');
       if ($mobile_cart) $width = 100;
       else $width = 300;
       print '<iframe data-ifields-id="card-number" data-ifields-placeholder="" ' .
             'frameborder="0" height="20" id="ifields_card_number_iframe" ' .
             'scrolling="no" width="'.$width.'" src="' .
             'https://cdn.cardknox.com/ifields/ifield.htm"></iframe>'."\n";
       print '<input data-ifields-id="card-number-token" name="card_number" ' .
             'type="hidden"></input>'."\n";
       print '<input type="hidden" name="card_type" id="card_type" ' .
             'value="Cardknox">'."\n";
       print '<label class="cart_error" id="ifields_error" style="display: block;"' .
             'data-ifields-id="card-data-error"></label>'."\n";
       return true;
    }
    else if ($field_name == 'card_cvv') {
       print '<iframe data-ifields-id="cvv" data-ifields-placeholder="" ' .
             'frameborder="0" height="20" style="display: inline; width: 65px;" ' .
             'id="ifields_cvv_iframe" scrolling="no" src="' .
             'https://cdn.cardknox.com/ifields/ifield.htm"></iframe>'."\n";
       print '<input data-ifields-id="cvv-token" name="card_cvv" ' .
             'type="hidden"></input>'."\n";
       return true;
    }
    return false;
}

function cardknox_validate_credit_card(&$cart)
{
    $cart->credit_card['number'] = trim(get_form_field('card_number'));
    if (isset($cart->errors['card_number']))
       unset($cart->errors['card_number']);
}

?>
