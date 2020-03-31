<?php
/*
               Inroads Shopping Cart - Internet Secure API Module

                        Written 2010-2018 by Randall Severy
                         Copyright 2010-2018 Inroads, LLC
*/

define ('ISECURE_DIRECT_HOSTNAME','direct.internetsecure.com');
define ('ISECURE_LINK_HOSTNAME','secure.internetsecure.com');
define ('ISECURE_PATH','/process.cgi');

global $isecure_link_flag;
$isecure_link_flag = false;

function isecure_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'InternetSecure','isecure',$values);

    $dialog->add_edit_row('Gateway ID:','isecure_gateway_id',$values,30);
    $dialog->start_row('Customer Notifications:','middle');
    $dialog->add_checkbox_field('isecure_notify','',$values);
    $dialog->end_row();
    $isecure_options = get_row_value($values,'isecure_options');
    $dialog->start_row('Payment Options:','middle');
    $dialog->add_checkbox_field('isecure_option_1','Merchant Direct',$isecure_options & 1);
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_checkbox_field('isecure_option_2','Merchant Link',$isecure_options & 2);
    $dialog->end_row();
}

function isecure_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('isecure_gateway_id','isecure_notify','isecure_options');
    if (! empty($website_settings)) $fields[] = 'isecure_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function isecure_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'isecure_notify') {
       if (get_form_field('isecure_notify') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'isecure_options') {
       $new_field_value = 0;
       if (get_form_field('isecure_option_1') == 'on') $new_field_value |= 1;
       if (get_form_field('isecure_option_2') == 'on') $new_field_value |= 2;
    }
    else if ($field_name == 'isecure_active') {
       if (get_form_field('isecure_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function isecure_active($db)
{
    return get_cart_config_value('isecure_active',$db);
}

function isecure_get_primary_module($db)
{
    return 'isecure';
}

function create_isecure_request_data($order,$cart)
{
    if ($order) {
       $db = $order->db;
       $customer = $order->customer;
       $info = $order->info;
       $items = $order->items;
       $billing_country = $order->billing_country;
       $shipping_country = $order->shipping_country;
       $features = $order->features;
    }
    else {
       $db = $cart->db;
       $customer = $cart->customer;
       $info = $cart->info;
       $items = $cart->items;
       $billing_country = $customer->billing_country;
       $shipping_country = $customer->shipping_country;
       $features = $cart->features;
    }
    $address = $customer->billing['address1'];
    $address2 = $customer->billing['address2'];
    if (isset($address2) && ($address2 != ''))
       $address .= ' '.$address2;
    $shipto = $customer->shipping['shipto'];
    if ((! isset($shipto)) || ($shipto == ''))
       $shipto = $customer->info['fname'].' '.$customer->info['lname'];
    $ship_address = $customer->shipping['address1'];
    $ship_address2 = $customer->shipping['address2'];
    if (isset($ship_address2) && ($ship_address2 != ''))
       $ship_address .= ' '.$ship_address2;
    $billing_country_info = get_country_info($billing_country,$db);
    $shipping_country_info = get_country_info($shipping_country,$db);
    if (isset($info['tax']) && ($info['tax'] != '')) $tax = $info['tax'];
    else $tax = 0;
    if ($order) {
       if (isset($info['shipping'])) $shipping = $info['shipping'];
       else $shipping = 0;
       $shipping_method = get_form_field('shipping_method');
       if ($shipping_method) {
          $shipping_method_info = explode('|',$shipping_method);
          $shipping_option = $shipping_method_info[3];
       }
       else {
          $shipping_method = '';   $shipping_option = '';
       }
    }
    else {
       $shipping = 0;   $shipping_method = '';   $shipping_option = '';
       $first_option = true;
       foreach ($cart->shipping_options as $shipping_method_info) {
          if ($first_option) {
             $first_option = false;
             $shipping_method = implode('|',$shipping_method_info);
             $shipping = $shipping_method_info[2];
             $shipping_option = $shipping_method_info[3];
          }
          if ($shipping_method_info[4]) {
             $shipping_method = implode('|',$shipping_method_info);
             $shipping = $shipping_method_info[2];
             $shipping_option = $shipping_method_info[3];
             break;
          }
       }
    }
    if (isset($info['coupon_amount'])) $coupon_amount = $info['coupon_amount'];
    else $coupon_amount = 0;
    if ($features & GIFT_CERTIFICATES) {
       if (isset($info['gift_amount'])) $gift_amount = $info['gift_amount'];
       else $gift_amount = 0;
    }
    else $gift_amount = 0;
    if (get_cart_config_value('isecure_notify') == '1') $email_customer = true;
    else $email_customer = false;
    $test_mode = false;

    $post_array = array('GatewayID' => get_cart_config_value('isecure_gateway_id'),
                        'xxxName' => $customer->info['fname'].' '.
                                     $customer->info['lname'],
                        'xxxCompany' => $customer->info['company'],
                        'xxxAddress' => $address,
                        'xxxCity' => $customer->billing['city'],
                        'xxxState' => $customer->billing['state'],
                        'xxxZipCode' => $customer->billing['zipcode'],
                        'xxxCountry' => $billing_country_info['code'],
                        'xxxPhone' => $customer->billing['phone'],
                        'xxxEmail' => $customer->info['email'],
                        'xxxShippingName' => $shipto,
                        'xxxShippingCompany' => $customer->shipping['company'],
                        'xxxShippingAddress' => $ship_address,
                        'xxxShippingCity' => $customer->shipping['city'],
                        'xxxShippingState' => $customer->shipping['state'],
                        'xxxShippingZipCode' => $customer->shipping['zipcode'],
                        'xxxShippingCountry' => $shipping_country_info['code'],
                        'xxxShippingPhone' => $customer->billing['phone'],
                        'xxxShippingEmail' => $customer->info['email']);

    if ($cart) $post_array['xxxVar1'] = $cart->id;
    else $post_array['xxxVar1'] = $order->cart->id;
    $post_array['xxxVar2'] = $info['subtotal'].'|'.$tax;
    if ($shipping != 0) $post_array['xxxVar3'] = $shipping_method;
    if ($coupon_amount != 0)
       $post_array['xxxVar4'] = $info['coupon_id'].'|'.$info['coupon_code'] .
                                '|'.$coupon_amount;
    if ($gift_amount != 0)
       $post_array['xxxVar5'] = $info['gift_id'].'|'.$info['gift_code'].'|' .
                                $gift_amount.'|'.$info['gift_balance'];
    if (! $email_customer)
       $post_array['xxxSendCustomerEmailReceipt'] = 'N';

    if ($order) {
       $post_array['xxxCard_Number'] = $order->credit_card['number'];
       $post_array['xxxCCMonth'] = $order->credit_card['month'];
       $post_array['xxxCCYear'] = $order->credit_card['year'];
       $post_array['CVV2'] = $order->credit_card['cvv'];
       $post_array['CVV2Indicator'] = 1;
       $post_array['xxxTransType'] = '00';
       if ($post_array['xxxCard_Number'] == '4111111111111111')
          $test_mode = true;
    }

    $item_string = '';
    foreach ($items as $id => $cart_item) {
       if ($item_string != '') $item_string .= '|';
       if ($order) $cart_obj = $order;
       else $cart_obj = $cart;
       $item_name = get_html_product_name($cart_item['product_name'],
                                          GET_PROD_PAYMENT_GATEWAY,
                                          $cart_obj,$cart_item);
       $attr_array = $cart_item['attribute_array'];
       if (isset($attr_array) && (count($attr_array) > 0)) {
          foreach ($attr_array as $index => $attribute)
             $item_name .= ', '.$attribute['attr'].'='.$attribute['option'];
       }
       $item_name = str_replace("\"",' ',$item_name);
       $item_name = str_replace("'",' ',$item_name);
       $item_name = str_replace('|',' ',$item_name);
       $item_name = str_replace('`',' ',$item_name);
       $item_name = str_replace(':',' ',$item_name);
       if (strlen($item_name) > 150) $item_name = substr($item_name,0,150);
       $item_string .= get_item_total($cart_item,false).'::'.$cart_item['qty'] .
                       '::'.$cart_item['product_id'].'::'.$item_name.'::';
       if ($test_mode) $item_string .= '{TEST}';
    }
    if ($tax != 0) {
       if ($item_string != '') $item_string .= '|';
       $item_string .= $tax.'::1::Tax::Tax::';
       if ($test_mode) $item_string .= '{TEST}';
    }
    if ($shipping != 0) {
       if ($item_string != '') $item_string .= '|';
       $item_string .= $shipping.'::1::Shipping::'.$shipping_option.'::';
       if ($test_mode) $item_string .= '{TEST}';
    }
    if ($coupon_amount != 0) {
       if ($item_string != '') $item_string .= '|';
       $item_string .= (-$coupon_amount).'::1::Promotion Code::'.$info['coupon_code'].'::';
       if ($test_mode) $item_string .= '{TEST}';
    }
    if ($gift_amount != 0) {
       if ($item_string != '') $item_string .= '|';
       $item_string .= (-$gift_amount).'::1::Gift Certificate::'.$info['gift_code'].'::';
       if ($test_mode) $item_string .= '{TEST}';
    }
    $post_array['Products'] = $item_string;

    return $post_array;
}

function isecure_process_payment(&$order)
{
    $post_array = create_isecure_request_data($order,null);

    $post_string = 'xxxRequestMode=X&xxxRequestData=';
    $xml_string = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><TranxRequest>";
    foreach ($post_array as $key => $value)
       $xml_string .= '<'.$key.'>'.encode_xml_data($value).'</'.$key.'>';
    $xml_string .= '</TranxRequest>';
    $post_string .= urlencode($xml_string);

    $log_string = '<TranxRequest>';
    foreach ($post_array as $key => $value) {
       if (($key == 'xxxCard_Number') || ($key == 'xxxCCMonth') ||
           ($key == 'xxxCCYear') || ($key == 'CVV2')) continue;
       $log_string .= '<'.$key.'>'.encode_xml_data($value).'</'.$key.'>';
    }
    $log_string .= '</TranxRequest>';

    $order->log_payment('Sent: '.$log_string);

    require_once '../engine/http.php';
    $url = 'https://'.ISECURE_DIRECT_HOSTNAME.ISECURE_PATH;
    $http = new HTTP($url);
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('InternetSecure: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('InternetSecure Error: '.$error);
       else log_payment('InternetSecure Error: '.$error);
       return null;
    }
    $response_data = str_replace("\n",'',$response_data);
    $response_data = str_replace("\r",'',$response_data);
    $order->log_payment('Response: '.$response_data);
    $error = parse_xml_tag($response_data,'Error');
    if ($error) {
       log_error('InternetSecure Error: '.$error);
       $order->log_payment('InternetSecure Error: '.$error);
       $order->error = $error;
       return false;
    }
    $approval_code = parse_xml_tag($response_data,'ApprovalCode');
    $page_number = parse_xml_tag($response_data,'Page');
    $verbage = parse_xml_tag($response_data,'Verbiage');
    if (($page_number != '90000') && ($page_number != '02000')) {
       log_error('InternetSecure Error: '.$verbage.' ('.$page_number.')');
       $order->log_payment('InternetSecure Error: '.$verbage.' (' .
                           $page_number.')');
       $order->error = $verbage;
       return false;
    }
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = parse_xml_tag($response_data,'ReceiptNumber');
    $order->payment['payment_code'] = $approval_code;
    log_activity('InternetSecure Payment Accepted with Receipt #' .
                 $order->payment['payment_id'].' and Approval Code ' .
                 $order->payment['payment_code']);
    return true;
}

function write_isecure_field($field_name,$field_value)
{
    print "      <input type=\"hidden\" name=\"".$field_name."\" value=\"";
    write_form_value($field_value);
    print "\">\n";
}

function process_isecure_export()
{
    set_remote_user('isecure');
    $log_string = '';   $form_fields = get_form_fields();
    foreach ($form_fields as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order'))
       @Order::log_payment('Received: '.$log_string);
    else log_payment('Received: '.$log_string);

    $approval_code = get_form_field('ApprovalCode');
    $page_number = get_form_field('PageNumber');
    $verbage = get_form_field('Verbage');
//    if (($approval_code == '') || ($approval_code == 'VOIDED') ||
//        ($verbage == 'Declined') || (substr($verbage,0,8) == 'Rejected')) {
    if (($page_number != '90000') && ($page_number != '02000')) {
       log_error('InternetSecure Error: '.$verbage.' ('.$page_number.')');
       print 'InternetSecure Error: '.$verbage.' ('.$page_number.")\n";
       if (class_exists('Order'))
          @Order::log_payment('InternetSecure Error: '.$verbage.' (' .
                              $page_number.')');
       else log_payment('InternetSecure Error: '.$verbage.' (' .
                        $page_number.')');
       return;
    }
    $cart_id = get_form_field('xxxVar1');
    if (! $cart_id) {
       print "Invalid Export Response from InternetSecure\n";
       log_error('Invalid Export Response from InternetSecure');   return;
    }
    $db = new DB;
    $query = 'select customer_id from cart where id=?';
    $query = $db->prepare_query($query,$cart_id);
    $row = $db->get_record($query);
    if ((! $row) && isset($db->error)) {
       print 'Database Error: '.$db->error."\n";
       log_error('Database Error: '.$db->error);   return;
    }
    if ($row) {
       $customer_id = $row['customer_id'];
       if ((! isset($customer_id)) || ($customer_id == '')) {
          print 'No Customer information found for Cart #'.$cart_id."\n";
          log_error('No Customer information found for Cart #'.$cart_id);
          return;
       }
       $customer = new Customer($db,$customer_id);
       $customer->cart_id = $cart_id;
       $order = new Order($customer);
       if ((! $order->load_cart($cart_id)) && isset($db->error)) {
          print 'Database Error: '.$db->error."\n";
          log_error('Database Error: '.$order->error);   return;
       }
       $order->payment['payment_method'] = 'InternetSecure';
       $order->credit_card['type'] = get_form_field('xxxCCType');
       $order->credit_card['number'] = get_form_field('xxxCard_Number');
       $order->credit_card['name'] = get_form_field('CardHolder');
       $order->payment['payment_id'] = get_form_field('SalesOrderNumber');
       $order->payment['payment_code'] = $approval_code;
       $order->payment['payment_ref'] = get_form_field('receiptnumber');
       $total_data = get_form_field('xxxVar2');
       $total_info = explode('|',$total_data);
       $subtotal = $total_info[0];
       $tax = $total_info[1];
       if ($tax) $order->set('tax',$tax);
       $shipping_method = get_form_field('xxxVar3');
       if ($shipping_method) {
          $shipping_info = explode('|',$shipping_method);
          $shipping_module = $shipping_info[0];
          require_once $shipping_module.'.php';
          $process_shipping = $shipping_module.'_process_shipping';
          $process_shipping($order,$shipping_method);
          $shipping = $order->info['shipping'];
       }
       else {
          $shipping = 0;   $order->set('shipping',0);
       }
       $coupon_data = get_form_field('xxxVar4');
       if ($coupon_data) {
          $coupon_info = explode('|',$coupon_data);
          $order->set('coupon_id',$coupon_info[0]);
          $order->set('coupon_code',$coupon_info[1]);
          $order->set('coupon_amount',$coupon_info[2]);
          $coupon_amount = $coupon_info[2];
       }
       else $coupon_amount = 0;
       $gift_data = get_form_field('xxxVar5');
       if ($gift_data) {
          $gift_info = explode('|',$gift_data);
          $order->set('gift_id',$gift_info[0]);
          $order->set('gift_code',$gift_info[1]);
          $order->set('gift_amount',$gift_info[2]);
          $order->set('gift_balance',$gift_info[3]);
          $gift_amount = $gift_info[2];
       }
       else $gift_amount = 0;
       $total = $subtotal + $tax + $shipping - $coupon_amount - $gift_amount;
       $order->set('subtotal',$subtotal);
       $order->set('total',$total);
       $order->payment['payment_amount'] = $total;
       if (! $order->create()) {
          print 'Database Error: '.$db->error."\n";
          log_error('Database Error: '.$order->error);   return;
       }
//       if (! update_order_totals($db,$order->id,true)) return;
       if (function_exists('custom_order_notifications'))
          custom_order_notifications($order);
       else {
          $notify_flags = get_cart_config_value('notifications',$order->db);
          if (($notify_flags & NOTIFY_NEW_ORDER_CUST) || ($notify_flags & NOTIFY_NEW_ORDER_ADMIN)) {
             require '../engine/email.php';
             if ($notify_flags & NOTIFY_NEW_ORDER_CUST) {
                $email = new Email(NEW_ORDER_CUST_EMAIL,array('order' => 'obj','order_obj' => $order));
                if (! $email->send()) log_error($email->error);
             }
             if ($notify_flags & NOTIFY_NEW_ORDER_ADMIN) {
                $email = new Email(NEW_ORDER_ADMIN_EMAIL,array('order' => 'obj','order_obj' => $order));
                if (! $email->send()) log_error($email->error);
             }
          }
       }
       log_activity('Processed Order #'.$order->id.' from Cart #' .
                    $cart_id.' by InternetSecure with Approval Code ' .
                    $approval_code);
       print 'Processed Order #'.$order->id.' from Cart #' .
             $cart_id.' by InternetSecure with Approval Code ' .
             $approval_code."\n";
    }
    else {
       log_error('Cart #'.$cart_id.' not found');
       print 'Cart #'.$cart_id." not found\n";
    }
}

function isecure_configure_checkout(&$cart)
{
    global $include_direct_payment,$isecure_link_flag;

    if ($cart->payment_module == 'isecure') {
       $isecure_options = get_cart_config_value('isecure_options');
       if (! ($isecure_options & 1)) $include_direct_payment = false;
       if ($isecure_options & 2) $isecure_link_flag = true;
    }
}

function write_isecure_checkout_button($cart)
{
    global $ssl_url;

    $post_array = create_isecure_request_data(null,$cart);
    $returnurl = $ssl_url.'cart/'.$cart->process_module.'?ContinueISecure=Go';
    $cancelurl = $ssl_url.'cart/'.$cart->checkout_module.'?CancelISecure=Go';

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order'))
       @Order::log_payment('Payment Button: '.$log_string);
    else log_payment('Payment Button: '.$log_string);

    print "    <form method=\"POST\" name=\"ISecureCart\" action=\"https://" .
          ISECURE_LINK_HOSTNAME.ISECURE_PATH."\"\n" .
          "     class=\"cart_form\" style=\"display:inline; margin:0px;\">\n";
    write_isecure_field('GatewayID',$post_array['GatewayID']);
    write_isecure_field('language','English');
    if (isset($post_array['xxxSendCustomerEmailReceipt']))
       write_isecure_field('xxxSendCustomerEmailReceipt',
                           $post_array['xxxSendCustomerEmailReceipt']);
    write_isecure_field('ReturnURL',$returnurl);
    write_isecure_field('xxxCancelURL',$cancelurl);
    write_isecure_field('Products',$post_array['Products']);
    write_isecure_field('xxxName',$post_array['xxxName']);
    write_isecure_field('xxxCompany',$post_array['xxxCompany']);
    write_isecure_field('xxxAddress',$post_array['xxxAddress']);
    write_isecure_field('xxxCity',$post_array['xxxCity']);
    write_isecure_field('xxxState',$post_array['xxxState']);
    write_isecure_field('xxxCountry',$post_array['xxxCountry']);
    write_isecure_field('xxxZipCode',$post_array['xxxZipCode']);
    write_isecure_field('xxxEmail',$post_array['xxxEmail']);
    write_isecure_field('xxxPhone',$post_array['xxxPhone']);
    if (isset($post_array['xxxVar1']))
       write_isecure_field('xxxVar1',$post_array['xxxVar1']);
    if (isset($post_array['xxxVar2']))
       write_isecure_field('xxxVar2',$post_array['xxxVar2']);
    if (isset($post_array['xxxVar3']))
       write_isecure_field('xxxVar3',$post_array['xxxVar3']);
    else write_isecure_field('xxxVar3','');
    if (isset($post_array['xxxVar4']))
       write_isecure_field('xxxVar4',$post_array['xxxVar4']);
    if (isset($post_array['xxxVar5']))
       write_isecure_field('xxxVar5',$post_array['xxxVar5']);
    print "      <input type=\"image\" name=\"ISecureCheckout\" " .
          "style=\"vertical-align: middle;\" src=\"cartimages/process-payment.gif\">\n";
    print "    </form>\n";
}

function isecure_write_checkout_payment_button(&$cart)
{
    global $isecure_link_flag,$continue_no_payment,$include_direct_payment;

    if ($isecure_link_flag) {
       print "\n  </form>\n";
       print "  <div style=\"float: right; line-height: 43px; padding-right: 20px;";
       if ($continue_no_payment) print ' display:none;';
       print "\" id=\"payment_div_2\">";
       if ($include_direct_payment || ($cart->num_payment_divs > 0))
          print '<strong>&nbsp;&nbsp;'.T('OR')."&nbsp;&nbsp;&nbsp;</strong>\n";
       write_isecure_checkout_button($cart);
       print "  </div>\n";
    }
}

function isecure_start_process_order($paying_balance)
{
    if (getenv('PATH_INFO') == '/isecure') {
       process_isecure_export();   exit;
    }
}

function process_order_start_button(&$order,$paying_balance)
{
    if (button_pressed('ContinueISecure')) {
       if (isset($order->customer_id)) set_remote_user($order->customer_id);
       if (! $order->load_last_order()) {
          require 'pay-balance.php';   exit;
       }
       return true;
    }
    return false;
}

?>
