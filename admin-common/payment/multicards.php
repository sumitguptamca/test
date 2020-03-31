<?php
/*
                 Inroads Shopping Cart - MultiCards Order Page/API Module

                           Written 2015-2018 by Randall Severy
                            Copyright 2015-2018 Inroads, LLC
*/

define ('MULTICARDS_ORDERPAGE_URL','https://secure.multicards.com/cgi-bin/order2/processorder1.pl');

global $multicards_order_page;
$multicards_order_page = false;

function multicards_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'MultiCards','multicards',$values);

    $dialog->add_edit_row('Merchant Number:','multicards_merchant',$values,20);
    $dialog->add_edit_row('Page ID:','multicards_page_id',$values,5);
    $multicards_interface = get_row_value($values,'multicards_interface');
    $dialog->start_row('Interface:','middle');
    $dialog->add_radio_field('multicards_interface','0','Order Page',$multicards_interface == 0);
    $dialog->add_radio_field('multicards_interface','1','API',$multicards_interface == 1);
    $dialog->end_row();
    $dialog->add_edit_row('Silent Password:','multicards_password',$values,20);
}

function multicards_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('multicards_merchant','multicards_page_id',
       'multicards_interface','multicards_password');
    if (! empty($website_settings)) $fields[] = 'multicards_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function multicards_payment_update_cart_config_field($field_name,
                                                     &$new_field_value,$db)
{
    if ($field_name == 'multicards_active') {
       if (get_form_field('multicards_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function multicards_active($db)
{
    return get_cart_config_value('multicards_active',$db);
}

function multicards_get_primary_module($db)
{
    return 'multicards';
}

function create_multicards_request_data($order,$cart)
{
    if ($order) {
       if (! isset($order->items)) return array();
       $db = $order->db;
       $customer = $order->customer;
       $info = $order->info;
       $items = $order->items;
       $billing_country = $order->billing_country;
    }
    else {
       if (! isset($cart->items)) return array();
       $db = $cart->db;
       $customer = $cart->customer;
       $info = $cart->info;
       $items = $cart->items;
       $billing_country = $customer->billing_country;
    }

    $address = $customer->billing['address1'];
    $address2 = $customer->billing['address2'];
    if (isset($address2) && ($address2 != ''))
       $address .= ' '.$address2;
    $billing_country_info = get_country_info($billing_country,$db);

    $post_array = array('mer_id' => get_cart_config_value('multicards_merchant'),
                        'mer_url_idx' => get_cart_config_value('multicards_page_id'),
                        'cust_country' => $billing_country_info['country'],
                        'cust_name' => trim($customer->info['fname']).' '.
                                       trim($customer->info['lname']),
                        'cust_company' => trim($customer->info['company']),
                        'cust_phone' => trim($customer->billing['phone']),
                        'cust_fax' => trim($customer->billing['fax']),
                        'cust_email' => trim($customer->info['email']),
                        'cust_address1' => trim($address),
                        'cust_zip' => trim($customer->billing['zipcode']),
                        'cust_city' => trim($customer->billing['city']),
                        'cust_state' => trim($customer->billing['state']),
                        'user1' => $cart->id);

    if ($order) {
       $exp_date = $order->credit_card['month'].'/'.$order->credit_card['year'];
       switch ($order->credit_card['type']) {
          case 'amex': $card_type = 'creditcard_americanexpress';   break;
          case 'Visa': $card_type = 'Visa';   break;
          case 'master': $card_type = 'creditcard_mastercard';   break;
          case 'discover': $card_type = 'creditcard_discover';   break;
          case 'diners': $card_type = 'creditcard_dinersclub';   break;
          case 'JCB':  $card_type = 'creditcard_jcb';   break;
          default: $card_type = '';
       }
       $post_array['pay_method_type'] = $card_type;
       $post_array['pay_type'] = $card_type;
       $post_array['card_num'] = $order->credit_card['number'];
       $post_array['card_name'] = $order->credit_card['name'];
       $post_array['card_code'] = $order->credit_card['cvv'];
       $post_array['card_exp'] = $exp_date;
    }
    else $post_array['next_phase'] = 'paydata';

    $item_num = 1;
    foreach ($items as $id => $cart_item) {
       if ($order) $cart_obj = $order;
       else $cart_obj = $cart;
       $item_name = get_html_product_name($cart_item['product_name'],
                                          GET_PROD_PAYMENT_GATEWAY,
                                          $cart_obj,$cart_item);
       if (isset($cart_item['attribute_array']))
          $attr_array = $cart_item['attribute_array'];
       else $attr_array = null;
       if (isset($attr_array) && (count($attr_array) > 0)) {
          foreach ($attr_array as $index => $attribute)
             $item_name .= ', '.$attribute['attr'].': '.$attribute['option'];
       }
       $post_array['item'.$item_num.'_desc'] = $item_name;
       $post_array['item'.$item_num.'_price'] = get_item_total($cart_item,false);
       $post_array['item'.$item_num.'_qty'] = $cart_item['qty'];
       $item_num++;
    }
    if (isset($info['coupon_amount']) && $info['coupon_amount']) {
       $item_name = 'Coupon Code '.$info['coupon_code'].': -$' .
                    number_format($info['coupon_amount'],2,'.','');
       $post_array['item'.$item_num.'_desc'] = $item_name;
       $post_array['item'.$item_num.'_price'] = -$info['coupon_amount'];
       $post_array['item'.$item_num.'_qty'] = 1;
       $item_num++;
    }
    if (isset($info['gift_amount']) && $info['gift_amount']) {
       $item_name = 'Gift Certificate '.$info['gift_code'].': -$' .
                     number_format($info['gift_amount'],2,'.','');
       $post_array['item'.$item_num.'_desc'] = $item_name;
       $post_array['item'.$item_num.'_price'] = -$info['gift_amount'];
       $post_array['item'.$item_num.'_qty'] = 1;
       $item_num++;
    }
    if (isset($info['discount_amount']) && $info['discount_amount']) {
       $item_name = $info['discount_name'].': -$' .
                    number_format($info['discount_amount'],2,'.','');
       $post_array['item'.$item_num.'_desc'] = $item_name;
       $post_array['item'.$item_num.'_price'] = -$info['discount_amount'];
       $post_array['item'.$item_num.'_qty'] = 1;
       $item_num++;
    }
    if (isset($info['fee_amount']) && $info['fee_amount']) {
       $post_array['item'.$item_num.'_desc'] = $info['fee_name'];
       $post_array['item'.$item_num.'_price'] = $info['fee_amount'];
       $post_array['item'.$item_num.'_qty'] = 1;
       $item_num++;
    }

    return $post_array;
}

function multicards_process_payment(&$order)
{
    $post_array = create_multicards_request_data($order,null);

    $post_string = '';
    foreach ($post_array as $key => $value) {
       if ($post_string != '') $post_string .= '&';
       $post_string .= $key.'='.urlencode($value);
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if (($key == 'card_num') || ($key == 'card_exp') ||
           ($key == 'card_code')) continue;
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }

    $order->log_payment('Sent: '.$log_string);
    $url = get_cart_config_value('multicards_hostname');
    if (substr($url,0,4) != 'http')
       $url = 'https://'.$url.'/gateway/transact.dll';
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('MultiCards Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('MultiCards Error: '.$error);
       else log_payment('MultiCards Error: '.$error);
       return null;
    }
    $response_data = str_replace("\n",'',$response_data);
    $response_data = str_replace("\r",'',$response_data);
    $order->log_payment('Response: '.$response_data);
    $separator = $response_data[1];
    $result_array = explode($separator,$response_data);
    if ($result_array[0] != '1') {
       log_activity('MultiCards Declined: '.$result_array[3]);
       if (isset($order->echeck))
          $order->error = 'Check Declined: '.$result_array[3];
       else $order->error = 'Card Declined: '.$result_array[3];
       return false;
    }
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = $result_array[6];
    $order->payment['payment_code'] = $result_array[4];
    $order->payment['payment_data'] = $response_data;
    log_activity('MultiCards Payment Accepted with Transaction ID #' .
                 $order->payment['payment_id'].' and Authorization Code ' .
                 $order->payment['payment_code']);
    return true;
}

function process_multicards_return()
{
    global $base_url,$ssl_url,$process_order_continue_url;

    putenv('PATH_INFO');
    set_remote_user('multicards');
    $log_string = '';   $form_fields = get_form_fields();
    foreach ($form_fields as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order'))
       @Order::log_payment('Received: '.$log_string);
    else log_payment('Received: '.$log_string);

    $multicards_order = get_form_field('order_num');
    $multicards_total = get_form_field('total_amount');
    $silent_password = get_form_field('SilentPostPassword');
    $cart_id = get_form_field('user1');
    if (! $cart_id) {
       print "Cart ID not returned from MultiCards\n";
       log_error('Cart ID not returned from MultiCards');   return true;
    }
    $comments = get_form_field('user2');
    $db = new DB;
    load_cart_config_values($db);
    $multicards_password = get_cart_config_value('multicards_password');
    if ($silent_password != $multicards_password) {
       print "Invalid Password returned from MultiCards\n";
       log_error('Invalid Password returned from MultiCards');
       return true;
    }
    $query = 'select cart_data from cart where id=?';
    $query = $db->prepare_query($query,$cart_id);
    $row = $db->get_record($query);
    if ((! $row) || (! $row['cart_data'])) {
       print "Invalid Cart ID returned from MultiCards\n";
       log_error('Invalid Cart ID ('.$cart_id.') returned from MultiCards');
       return true;
    }
    $_GET = array_merge($_GET,unserialize($row['cart_data']));
    $_GET['comments'] = $comments;

    $order = new Order(null,$db,true);
    $guest_checkout = get_form_field('GuestCheckout');
    if ($guest_checkout) {
       $order->customer_id = 0;
       set_remote_user(get_form_field('cust_email'));
    }
    if (! $order->load_cart($cart_id)) {
       print $order->error;   log_error($order->error);   return true;
    }
    if (isset($order->customer))
       set_remote_user($order->customer->get('cust_email'));
    $order->payment_module = get_cart_config_value('payment_module');
    $order->shipping_module = get_cart_config_value('shipping_module');
    if (function_exists('check_shipping_module'))
       check_shipping_module($order->cart,$order->customer,$order);
    $order->process_shipping();
    if (! $order->read_hidden_fields()) {
       print $order->error;   log_error($order->error);   return true;
    }
    if (function_exists('custom_validate_order_fields') &&
        (! custom_validate_order_fields($order))) {
       print $order->error;   log_error($order->error);   return true;
    }
    $order->payment['payment_amount'] = $multicards_total;
    $order->payment['payment_method'] = 'MultiCards';
    $order->payment['payment_id'] = $multicards_order;
    if (! $order->create()) {
       print $order->error;   log_error($order->error);   return true;
    }

    if (function_exists('custom_order_notifications'))
       custom_order_notifications($order);
    else {
       $notify_flags = get_cart_config_value('notifications',$order->db);
       if (($notify_flags & NOTIFY_NEW_ORDER_CUST) ||
           ($notify_flags & NOTIFY_NEW_ORDER_ADMIN) ||
           ($notify_flags & NOTIFY_LOW_QUANTITY)) {
          require '../engine/email.php';
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
          if ($notify_flags & NOTIFY_LOW_QUANTITY)
             check_low_quantity($order,null);
       }
    }
    log_activity('Processed Order #'.$order->id.' from Cart #' .
                 $cart_id.' by MultiCards with MultiCards Order Number ' .
                 $multicards_order);

    $contact_email = get_cart_config_value('contactemail');
    $contact_phone = get_cart_config_value('contactphone');
    $contact_hours = get_cart_config_value('contacthours');
    print "<!--success-->\n";
    print "<p>\n";
    print str_replace('%1',$order->get('order_order_number'),
             T('Your new order # is %1. We appreciate your patronage. You will receive a confirmation email shortly.'));
    if ($contact_email || $contact_phone) {
       print ' '.T('Should you have any questions or difficulty, feel free to').' ';
       if ($contact_email) {
          print T('contact us by e-mail at')."\n  <a href='mailto:".$contact_email .
                "' class='cartLink'>".$contact_email.'</a>';
          if ($contact_phone) print "\n  ".T('or').' ';
       }
       if ($contact_phone) print T('call').' '.$contact_phone;
       if ($contact_hours) print ' '.T('during store hours between').' '.$contact_hours;
       print '.';
    }
    print T('We are glad to help').".</p>\n";
    print "<p><a href='".$base_url."cart/view-order.php?id=".$order->id."&amp;print=true'\n";
    print " class='cartLink' target='new'><b>".T('Click here')."</b></a> ".T('for a printer friendly invoice').".\n";
    print T('You can always reprint the invoice by logging in to the system in the My Account section').".\n";
    print "<br><br></p>\n";

    print "<p align=\"center\"><a href=\"".$ssl_url;
    if (isset($process_order_continue_url)) print $process_order_continue_url;
    else print 'index.html';
    print "\">Continue Shopping</a></p>\n";

    return true;
}

function multicards_configure_checkout(&$cart)
{
    global $multicards_order_page;

    if ($cart->payment_module == 'multicards') {
       $multicards_interface = get_cart_config_value('multicards_interface',
                                                     $cart->db);
       if ($multicards_interface == 0) {
          $multicards_order_page = true;   $include_direct_payment = false;
       }
    }
}

function write_multicards_checkout_form(&$cart)
{
    $post_array = create_multicards_request_data(null,$cart);

    $form_data = array_merge($_POST,$cart->info);
    unset($form_data['cart_data']);
    $cart_data = serialize($form_data);
    $query = 'update cart set cart_data=? where id=?';
    $query = $cart->db->prepare_query($query,$cart_data,$cart->id);
    $cart->db->log_query($query);
    if (! $cart->db->query($query)) {
       $cart->error = $cart->db->error;   return;
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order'))
       @Order::log_payment('Payment Button: '.$log_string);
    else log_payment('Payment Button: '.$log_string);

    print "<form method=\"POST\" name=\"MultiCardsCart\" action=\"" .
          MULTICARDS_ORDERPAGE_URL."\"\n" .
          " class=\"cart_form\" style=\"display:inline; margin:0px;\">\n";
    foreach ($post_array as $field_name => $field_value) {
       print "<input type=\"hidden\" name=\"".$field_name."\" value=\"";
       write_form_value($field_value);
       print "\">\n";
    }
}

function multicards_write_checkout_form(&$cart)
{
    global $multicards_order_page;

    if ($multicards_order_page) {
       write_multicards_checkout_form($cart);   return true;
    }
    return false;
}

function write_multicards_payment_options($cart,$customer)
{
    global $checkout_table_cellspacing,$available_card_types;

    if ($customer->billing_country != 155) unset($available_card_types['iDEAL']);
?>
   <script type="text/javascript">
     function select_pay_type(select_list) {
        if (select_list.selectedIndex == -1) var pay_type = '';
        var pay_type = select_list.options[select_list.selectedIndex].value;
        var your_bank_row = document.getElementById('your_bank_row');
        if (pay_type == 'iDEAL') your_bank_row.style.display = '';
        else your_bank_row.style.display = 'none';
     }
   </script>
   <table cellpadding="0" cellspacing="<? print $checkout_table_cellspacing;
?>" id="payment_table" class="cart_font" style="margin-right: 20px;">
    <tr><td class="checkout_header"><?= T('PAYMENT INFORMATION'); ?></td></tr>
    <tr>
       <td class="checkout_prompt" style="padding-right:20px;"><?= T('Credit Card Type'); ?></td>
       <td>
<?
      $selected_card_type = get_form_field('pay_type');
      if (! $selected_card_type) $selected_card_type = 'visa';
?>
          <select name="pay_type" class="cart_field card_type" onChange="select_pay_type(this);">
<?
      foreach ($available_card_types as $card_type => $card_name) {
         print "             <option value=\"".$card_type."\"";
         if ($selected_card_type == $card_type) print ' selected';
         print '>'.$card_name."</option>\n";
      }
?>
          </select>
       </td>
    </tr>
<?
   if ($customer->billing_country == 155) {
      $ideal_banks = array('ABNANL2A'=>'ABN Amro Bank','ASNBNL21'=>'ASN Bank',
         'INGBNL2A'=>'ING','KNABNL2H'=>'Knab','RABONL2U'=>'Rabobank',
         'RBRBNL21'=>'RegioBank','SNSBNL2A'=>'SNS Bank','TRIONL2U'=>'Triodos Bank',
         'FVLBNL22'=>'Van Lanschot Bankiers');
      $issuer_id = get_form_field('issuer_id');

      print "    <tr id=\"your_bank_row\"";
      if ($selected_card_type != 'iDEAL') print " style=\"display:none;\"";
      print ">\n";
      print "       <td class=\"checkout_prompt\" style=\"" .
            "padding-right:20px;\">".T('Your Bank')."</td>\n";
      print "       <td>\n";
      print "          <select name=\"issuerID\" size=\"1\" class=\"" .
            "cart_field card_type\">\n";
      print "             <option value=\"\"></option>\n";
      print "             <optgroup label=\"Nederland\">\n";
      foreach ($ideal_banks as $value => $label) {
         print '             <option value="'.$value.'"';
         if ($value == $issuer_id) print ' selected';
         print '>'.$label."</option>\n";
      }
      print "          </select>\n";
      print "       </td>\n";
      print "    </tr>\n";
   }
?>
   </table>
<?
}

function multicards_write_continue_checkout(&$cart)
{
    global $multicards_order_page,$include_direct_payment;

    if ($multicards_order_page) {
       write_multicards_payment_options($cart,$cart->customer);
       $cart->comments_field_name = 'user2';
       $include_direct_payment = true;
       return true;
    }
    return false;
}

function multicards_start_process_order($paying_balance)
{
    if (getenv('PATH_INFO') == '/multicards') {
       if (process_multicards_return()) exit;
    }
}

?>
