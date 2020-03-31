<?
/*
                  Inroads Shopping Cart - Checkout - Checkout Page

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

   require_once '../cartengine/cart-public.php';

   if (is_crawler()) { crawler_noindex(); exit; }

   if (! isset($cart_globals)) {
      global $cart_globals;
      foreach ($cart_globals as $variable => $value) global $$variable;
   }

   process_start_checkout();

   $errors = array();   $error = null;
   if (isset($order)) {
      if (isset($order->cart)) {
         $cart = $order->cart;
         if (isset($cart->items)) $cart_items = $cart->items;
         else if (! empty($cart->id)) $cart_items = $cart->load();
         else $cart_items = null;
      }
      else {
         $cart = new Cart;
         if (! empty($cart->id)) $cart_items = $cart->load();
         else $cart_items = null;
      }
      $guest_checkout = get_form_field('GuestCheckout');
      if ($guest_checkout) $cart->save_guest_info();
      else if (($cart->flags & GUEST_CHECKOUT) &&
               ($cart->flags & SAVED_GUEST_INFO)) {
         $cart->load_guest_info();   $guest_checkout = true;
      }
      $cart->read_hidden_fields();
      if (isset($order->customer)) $customer = $order->customer;
      else {
         $customer = new Customer($order->db);
         if ($guest_checkout) {
            $customer->parse();
            $customer->setup_guest();
         }
         else {
            $customer->load();
            $customer->check_required_fields($join_required_fields);
            if (count($customer->errors) > 0) {
               require 'join.php';   exit;
            }
         }
      }
      $cart->customer = $customer;
      $errors = $order->errors;
      if (isset($order->error)) $error = $order->error;
      call_payment_event('setup_checkout',array(&$cart));
   }
   else {
<$if liveupdate>
      $cart = new Cart(null,null,null,true,true);
      $coupon_code = get_form_field('coupon_code');
      $cart_items = null;
      $customer = new Customer(null,null,true);
      $guest_checkout = false;
<$else>
      $cart = new Cart;   $coupon_code = null;
      call_payment_event('setup_checkout',array(&$cart));
      $guest_checkout = get_form_field('GuestCheckout');
      if (! $coupon_code) $coupon_code = get_form_field('coupon_code');
      if ((! $coupon_code) && button_pressed('Checkout')) $cart->delete_coupon();
      if (! empty($cart->id)) $cart_items = $cart->load();
      else $cart_items = null;
      if (! isset($customer)) $customer = new Customer($cart->db);
      if ($guest_checkout) $cart->save_guest_info();
      else if ($cart->flags & GUEST_CHECKOUT) {
         if ($cart->flags & SAVED_GUEST_INFO) {
            $cart->load_guest_info();   $guest_checkout = true;
         }
         else $cart->set_flags($cart->flags & ~GUEST_CHECKOUT);
      }
      if ($guest_checkout) {
         $customer->parse();
         $customer->setup_guest();
      }
      else if ((! isset($customer->id)) && (! $cart->payment_customer_info)) {
         $goto_checkout = true;
         require 'login.php';   exit;
      }
<$endif>
      if ((! $cart_items) && isset($cart->error)) {
         $customer->errors['dberror'] = true;
         $customer->error = T('Unable to load cart items').': '.$cart->error;
      }
      else if (! empty($customer->id)) {
         $customer->load();
         $customer->check_required_fields($join_required_fields);
         if (count($customer->errors) > 0) {
            require 'join.php';   exit;
         }
      }
      $cart->customer = $customer;
      if ($cart_items && (! $cart->check_cart_quantities())) {
         require 'index.php';   exit;
      }
      if ($cart_items) $cart_items = $cart->update_cart_items();
      $errors = $cart->errors;
      call_payment_event('process_checkout_buttons',array(&$cart,false));
      if (isset($customer->error)) $error = $customer->error;
      else {
         $errors = $customer->errors;
         if (isset($customer->error)) $error = $customer->error;
         if ($enable_rewards) {
            $rewards = get_form_field('rewards');
            if ($rewards != '') {
               if (! $cart->process_rewards($rewards)) {
                  require 'index.php';   exit;
               }
            }
         }
         if ($coupon_code) {
            if ((! empty($cart->info['coupon_code'])) &&
                (! empty($cart->info['coupon_amount'])))
            $cart->info['total'] += $cart->info['coupon_amount'];
            if (! $cart->process_coupon($coupon_code)) {
               require 'index.php';   exit;
            }
         }
         else $cart->process_special_offers();
         if ($cart->features & GIFT_CERTIFICATES) {
            $gift_code = get_form_field('gift_code');
            if ($gift_code != '') {
               if (! $cart->process_gift_certificate($gift_code)) {
                  require 'index.php';   exit;
               }
            }
         }
         if ((! isset($cart->error)) &&
             (function_exists('custom_update_cart')))
            custom_update_cart($cart);
      }
   }
   set_remote_user($customer->get('info_email'));
   if ($cart_items) {
      if (! empty($show_part_number_in_cart))
         $cart_items = $cart->load_part_numbers();
      if ($mobile_cart) $size = 'medium';
      else $size = 'small';
      $cart_items = $cart->load_images(null,$size);
      if (! empty($on_account_products))
         $cart_items = $cart->load_on_account_products();
   }
   load_cart_config_values($customer->db);
   if ($cart->enable_shipping_options) {
      $null_order = null;
      if (function_exists("check_shipping_module"))
         check_shipping_module($cart,$customer,$null_order);
      $cart->load_shipping_options($customer);
   }
   $tax_shipping = false;
   if (! isset($order)) {
      $tax_shipping = taxable_shipping($cart);
      $cart->set_shipping_amount();
      $cart->calculate_tax($customer);
   }
   if (function_exists('update_checkout_cart')) update_checkout_cart($cart);
   if (function_exists('get_custom_payment_module'))
      $cart->payment_module = get_custom_payment_module($cart,$customer);
   else $cart->payment_module = call_payment_event('get_primary_module',
                                                   array($cart->db),true,true);
   $include_direct_payment = true;

   if ($cart->registry) {
      require_once '../cartengine/registry-public.php';
      $registry = new Registry($cart->db,$cart->info['registry_id']);
      $registry->copy_address($customer);
   }

   if (isset($cart->errors['dberror']) ||
       isset($cart->errors['shippingerror']) ||
       isset($cart->errors['error'])) {
      $errors = $cart->errors;
      if (isset($cart->error)) $error = $cart->error;
   }
   if (! isset($checkout_table_cellspacing)) $checkout_table_cellspacing = 2;

   if ($guest_checkout) $saved_cards_flag = false;
   else $saved_cards_flag = get_saved_cards_flag();
   if ($saved_cards_flag) $saved_cards = $customer->load_saved_cards();
   else $saved_cards = null;
   $payment_types = $customer->load_payment_types();
   $cart->payment_types = $payment_types;
   $payment_type = get_form_field('payment_type');
   if (($payment_type === null) || (! isset($payment_types[$payment_type]))) {
      reset($payment_types);   $payment_type = key($payment_types);
   }
   $credit_balance = $customer->get_credit_balance();

   $cart_total = $cart->get('total');
   $fee_name = $cart->get('fee_name');
   $fee_amount = $cart->get('fee_amount');
   $discount_name = $cart->get('discount_name');
   $discount_amount = $cart->get('discount_amount');
   $round_total = round($cart_total,2);
   if (($round_total == 0) || ($round_total == 0.00))
      $continue_no_payment = true;
   else $continue_no_payment = false;
   if ($enhanced_mobile_cart) $include_checkout_cart_details = true;
   else if (! isset($include_checkout_cart_details))
      $include_checkout_cart_details = true;
   if ($enhanced_mobile_cart) $checkout_change_links = false;
   else if (! isset($checkout_change_links)) $checkout_change_links = false;
   if (! isset($disable_card_name)) $disable_card_name = false;
   if ($disable_card_name) {
      $name_on_card = $customer->get('cust_fname');
      $mname = $customer->get('cust_mname');
      if ($mname) $name_on_card .= ' '.$mname;
      $name_on_card .= ' '.$customer->get('cust_lname');
   }
   if ($guest_checkout) {
      $billing_change_url = 'cart/join.php?GuestCheckout=Yes';
      $shipping_change_url = 'cart/join.php?GuestCheckout=Yes';
   }
   else {
      $billing_change_url = 'cart/billing.php?Checkout=true';
      $shipping_change_url = 'cart/shipping.php?Checkout=true';
   }
   if (! isset($enable_checkout_comments)) $enable_checkout_comments = true;
   global $checkout_continue_button_label,$checkout_process_button_label;
   if (empty($checkout_continue_button_label))
      $checkout_continue_button_label = 'Continue';
   if (empty($checkout_process_button_label))
      $checkout_process_button_label = 'Process Payment';
   call_payment_event('configure_checkout',array(&$cart));
   $cart->init_checkout();

   log_cart_errors($errors,$error);

   init_cart_page('checkout');

?><$include {cart_header.html}><div class="<?= $cart_body_class; ?> checkout_page">
<?
   $cart->set_multisite_cookies($cart->multisite_cookies,$cart->db);
   if ($tax_shipping) $cart->set_tax_shipping_flag();
   if (! call_payment_event('write_checkout_form',array(&$cart),true,true)) { ?>
  <form method="POST" name="Cart" action="cart/process-order.php" class="cart_form"<?
if (! $enhanced_mobile_cart) print " style=\"display:inline; margin:0px;\""; ?>>
<?
   }
   if (! empty($guest_checkout))
      print "<input type=\"hidden\" name=\"GuestCheckout\" id=\"GuestCheckout\" value=\"" .
            $guest_checkout."\">\n";
   if ($cart->account_required)
      print "<input type=\"hidden\" name=\"guest_password_required\" id=\"guest_password_required\" value=\"Yes\">\n";
   call_payment_event('write_checkout_hidden_fields',array(&$cart));
   $cart->write_hidden_fields($customer);
?>
    <table cellpadding="0" cellspacing="0" <?
   if ($mobile_cart)
      print "class=\"checkout_table mobile_checkout_table mobile_billing_info_table\"";
   else print "width=\"580px\" class=\"checkout_table\" style=\"margin-top: 20px;\""; ?>>
<?
if (isset($errors['dberror']))
   print "<tr><td colspan=\"2\" class=\"cart_error\">".T('Database Error') .
         ": ".$error."<br><br></td></tr>\n";
else if (isset($errors['shippingerror']))
   print "<tr><td colspan=\"2\" class=\"cart_error\">".T('Shipping Error') .
         ": ".$error."<br><br></td></tr>\n";
else if (isset($errors['error']))
   print "<tr><td colspan=\"2\" class=\"cart_error\">".T($error) .
         "<br><br></td></tr>\n";
else if (isset($checkout_warning))
   print "<tr><td colspan=\"2\" class=\"cart_error\">".T($checkout_warning) .
         "<br><br></td></tr>\n";
else if (isset($error,$order))
   print "<tr><td colspan=\"2\" class=\"cart_error\">".T('Payment Error') .
         ": ".$error."<br><br></td></tr>\n";
if (isset($customer)) {
?>
    <tr valign="top"><? if (empty($hide_checkout_billing_info)) {
?><td<? if (! $mobile_cart) print " width=\"50%\""; ?>>
      <table class="billing_info_table" cellpadding="0" cellspacing="0" width="100%">
        <tr><td class="checkout_header"><?
if ($enhanced_mobile_cart) print T('Billing Address');
else print T('BILLING INFORMATION'); ?></td>
        <tr style="height:5px;"><td></td></tr>
        <tr><td class="checkout_data" id="bill_name_cell"><?
   print $customer->get('cust_fname');
   $mname = $customer->get('cust_mname');
   if ($mname) print " ".$mname;
   print " ".$customer->get('cust_lname');
        ?></td></tr>
<?
   $company = $customer->get('cust_company');
?>
        <tr id="bill_company_row"<? if (! $company) print ' style="display:none;"';
   ?>><td class="checkout_data" id="bill_company_cell"><? if ($company) print $company; ?></td></tr>
        <tr><td class="checkout_data" id="bill_address1_cell"><?
print $customer->get('bill_address1'); ?></td></tr>
<?
   $address2 = $customer->get('bill_address2');
?>
        <tr id="bill_address2_row"<? if (! $address2) print ' style="display:none;"';
   ?>><td class="checkout_data" id="bill_address2_cell"><?
   if ($address2) print $address2; ?></td></tr>
        <tr><td class="checkout_data" id="bill_location_cell"><?
   if (isset($customer->id) || $cart->payment_customer_info) {
      print $customer->get('bill_city');
      if ($customer->billing_country != 29) print ", ";
      if ($customer->billing_country == 1) print $customer->get('bill_state');
      else if ($customer->billing_country == 43)
         print $customer->get('bill_canada_province');
      else print $customer->get('bill_province');
      print "&nbsp;&nbsp;".$customer->get('bill_zipcode')." ";
      print $customer->get('bill_country_name');
   }
        ?></td></tr>
        <tr><td class="checkout_data" id="bill_phone_cell"><?
   print $customer->get('bill_phone'); ?></td></tr>
        <tr style="height:10px;"><td></td></tr>
      </table>
    </td>
<? }
   if ($mobile_cart) { ?>
    </tr>
<? if ($cart->enable_edit_addresses && empty($hide_checkout_billing_info)) { ?>
    <tr style="height:20px;">
      <td><a class="<? write_cart_anchor_classes('change_link');
?>" onClick="return change_billing();" href="<?= $billing_change_url; ?>"><?
    if ($enhanced_mobile_cart)
       write_cart_button(null,'change','Edit Billing Address');
    else write_cart_button(null,'change','Change');
?></a></td>
    </tr>
<? } ?>
    <tr style="height:20px;">
    </table>
    <table cellpadding="0" cellspacing="0" class="checkout_table mobile_checkout_table mobile_shipping_info_table">
    <tr>
<? }
   if (empty($hide_checkout_shipping_info)) {
?>
    <td<? if (! $mobile_cart) print " width=\"50%\""; ?>>
      <table class="shipping_info_table" cellpadding="0" cellspacing="0" width="100%">
        <tr><td class="checkout_header"><?
if ($enhanced_mobile_cart) print T($shipping_title.' Address');
else print T(strtoupper($shipping_title).' INFORMATION'); ?></td></tr>
        <tr style="height:5px;"><td></td></tr>
        <tr><td class="checkout_data"><?
   $shipto = $customer->get('ship_shipto');
   if (isset($shipto) && ($shipto != "")) print $shipto;
   else {
      print $customer->get('cust_fname');
      $mname = $customer->get('cust_mname');
      if ($mname) print " ".$mname;
      print " ".$customer->get('cust_lname');
   }
        ?></td></tr>
<?
   $ship_company = $customer->get('ship_company');
   if ($ship_company && ($ship_company != "")) {
?>
        <tr><td class="checkout_data"><? print $ship_company; ?></td></tr>
<? } ?>
        <tr><td class="checkout_data"><? print $customer->get('ship_address1'); ?></td></tr>
<?
   $address2 = $customer->get('ship_address2');
   if ($address2 && ($address2 != "")) {
?>
        <tr><td class="checkout_data"><? print $address2; ?></td></tr>
<? } ?>
        <tr><td class="checkout_data"><?
   if (isset($customer->id) || $cart->payment_customer_info) {
      print $customer->get('ship_city');
      if ($customer->shipping_country != 29) print ", ";
      if ($customer->shipping_country == 1) print $customer->get('ship_state');
      else if ($customer->shipping_country == 43)
         print $customer->get('ship_canada_province');
      else print $customer->get('ship_province');
      print "&nbsp;&nbsp;".$customer->get('ship_zipcode')." ";
      print $customer->get('ship_country_name');
   }
        ?></td></tr>
<?
   if (function_exists("write_custom_checkout_shipping_fields"))
      write_custom_checkout_shipping_fields($customer);
?>
        <tr style="height:10px;"><td></td></tr>
      </table>
    </td><? } ?></tr>
<? if ($cart->enable_edit_addresses) { ?>
    <tr style="height:20px;">
<? if ((! $mobile_cart) && empty($hide_checkout_billing_info)) { ?>
      <td><a class="<? write_cart_anchor_classes('change_link');
?>" onClick="return change_billing();" href="<?= $billing_change_url; ?>"><?
    if ($checkout_change_links) print "Edit";
    else write_cart_button(null,'change','Change');
?></a></td>
<? }
   if (empty($hide_checkout_shipping_info)) {
?>
      <td><?
      if ($cart->registry && (! $mobile_cart)) print "&nbsp;";
      else {
?>
<a onClick="return change_shipping();" href="<?= $shipping_change_url; ?>" class="<? write_cart_anchor_classes('change_link'); ?>"><?
    if ($checkout_change_links) print 'Edit';
    else if ($enhanced_mobile_cart)
       write_cart_button(null,'change','Edit '.$shipping_title.' Address');
    else write_cart_button(null,'change','Change');
?></a><?
      } ?></td>
<? } ?>
    </tr>
<? } ?>
    <tr style="height:20px;"><td colspan="2"></td></tr>
<? } ?>
    </table>
<? if ($include_checkout_cart_details || $guest_checkout) { ?>
    <table cellpadding="0" <?
     if ($mobile_cart) print "cellspacing=\"0\" class=\"checkout_table mobile_cart_table\"";
     else print "cellspacing=\"".$checkout_table_cellspacing .
              "\" class=\"checkout_table\" width=\"580px\"";
     ?>>
<?
   if ($include_checkout_cart_details) {
   if ($enhanced_mobile_cart) {
      print "<tr><td class=\"cart_summary_row\" colspan=\"2\">Order Summary (";
      if (isset($cart_items) && is_array($cart_items))
         $num_items = count($cart_items);
      else $num_items = 0;
      print $num_items;
      if ($num_items == 1) print " Item) ";
      else print " Items) ";
      print "<a class=\"".write_cart_anchor_classes('edit_cart',true)."\" href=\"cart/index.php\">";
      write_cart_button(null,'edit','Edit');
      print "</a></td></tr>\n";
   }
   else if (! $mobile_cart) {
?>
      <tr>
<? if (isset($show_part_number_in_cart)) {
      if (! isset($part_number_prompt)) $part_number_prompt = T('Part #');
?>
        <th class="cart_header" colspan="2" align="left" style="text-align: left;"><?= T('Product Name'); ?></td>
        <th class="cart_header" width="60px" nowrap align="center" style="text-align: center;"><?
      print strtoupper($part_number_prompt); ?></td>
<? } else { ?>
        <th class="cart_header" colspan="2" nowrap align="left" style="text-align: left;"><?= T('Product Name'); ?></td>
<? }
   if (empty($hide_cart_prices)) {
?>
        <th class="cart_header" width="80px" nowrap align="right" style="text-align: right;"><?= T('Unit Price'); ?></td>
<? } ?>
        <th class="cart_header" width="40px" align="center" style="text-align: center;"><?= T('Qty'); ?></td>
<? if (empty($hide_cart_prices)) { ?>
        <th class="cart_header" width="60px" align="right" style="text-align: right;"><?= T('Total'); ?></td>
<? } ?>
      </tr>
<?
   }
   if (! empty($on_account_products)) {
      $on_account_total = 0;   $all_products_on_account = true;
      $num_on_account_items = 0;
   }
   if (isset($cart_items) && is_array($cart_items)) {
      $index = 1;
      foreach ($cart_items as $id => $cart_item) {
         $qty = $cart_item['qty'];
         if ((! empty($on_account_products)) &&
             (! empty($cart_item['on_account']))) $on_account_product = true;
         else $on_account_product = false;
         if ($mobile_cart) {
            print "<tr><td class=\"cart_item_image\">";
            if (isset($cart_item['image']))
               print "<img src=\"".$cart_item['image']."\">";
            print "</td><td><table class=\"checkout_table mobile_cart_item_table\" " .
                  "cellpadding=\"0\" cellspacing=\"0\">\n";
            print "    <tr>\n";
            print "      <td><span class=\"cart_product_name\">" .
                  get_html_product_name($cart_item['product_name'],GET_PROD_CART,
                                        $cart,$cart_item);
            if (has_attribute_prices($cart_item['attribute_array']) &&
                empty($hide_cart_prices) && floatval($cart_item['price'])) {
               print ' - <span class="cart_price"';
               $cart->write_amount($cart_item['price'],true);
               print '</span>';
            }
            print "</span>";
            print get_html_attributes($cart_item['attribute_array'],GET_ATTR_CART,
                                      $cart,$cart_item);
            $cart->write_reorder_info($cart_item);
            if ((! isset($show_part_number_in_cart)) || (! $enhanced_mobile_cart))
               print "</td></tr>\n";
            if (isset($show_part_number_in_cart)) {
               if (! $enhanced_mobile_cart) print "    <tr><td>";
               print "<span class=\"cart_prompt\">Part #:</span>";
               if (isset($cart_item['part_number'])) print $cart_item['part_number'];
               print "</td>\n";
               print "    </tr>\n";
            }
            $unit_price = get_item_total($cart_item,false);
            $item_total = get_item_total($cart_item,true);
            if (! empty($hide_cart_prices)) print "</table></td></tr>\n";
            else if ($enhanced_mobile_cart) {
               print "</table></td></tr>\n";
               print "    <tr class=\"cart_item_price_row\"><td colspan=\"2\" " .
                     "align=\"right\">";
               if ($qty > 1) print '<span class="cart_item_qty">x'.$qty .
                                   '</span>&nbsp;';
               print '<span class="cart_item_price cart_price">';
               $cart->write_amount($unit_price,true);
               print "</span></td></tr>\n";
            }
            else {
               print "    <tr><td><span class=\"cart_prompt\">Unit Price:</span>";
               if ($cart_item['flags'] & SALE_PRICE)
                  print "<span class=\"cart_sale_price\">SALE</span>&nbsp;\n";
               print '<span class="cart_price">';
               $cart->write_amount($unit_price,true);
               print "</span></td>\n";
               print "    </tr>\n    <tr><td><span class=\"cart_prompt\">Qty:</span>";
               print $qty;
               print "</td>\n";
               print "    </tr>\n";
               print "    <tr><td><span class=\"cart_prompt\">Total:</span>";
               print '<span class="cart_price">';
               $cart->write_amount($item_total,true);
               print "</span></td>\n";
               print "    </tr>\n";
               print "</table></td></tr>\n";
            }
            if ($on_account_product) {
               print '<tr><td><span class="cart_on_account"><input type="checkbox" name="' .
                     'on_account_'.$id.'" id="on_account_'.$id.'"';
               if (isset($cart_item['flags']) &&
                   ($cart_item['flags'] & ON_ACCOUNT_ITEM)) {
                  print ' checked';   $on_account_total += $item_total;
                  $num_on_account_items++;
               }
               else $all_products_on_account = false;
               print ' onClick="check_on_account_product(this,'.$id.','.$item_total .
                     ');"><label for="on_account_'.$id .
                     '">Pay On Account</label></span></td></tr>' .
                     "\n";
            }
            $index++;   continue;
         }

         print "      <tr class=\"";
         if (($index % 2) == 0) print "cart_row_even";
         else print "cart_row_odd";
         print "\" valign=\"top\">\n";
         print "      <td class=\"cart_item_image\" align=\"center\">";
         if (isset($cart_item['image']))
            print "<img src=\"".$cart_item['image']."\" style=\"max-width:75px;\"><br>\n";
         print "</td>\n";
         print "        <td";
         if ($cart_item['related_id']) print " style=\"padding-left: 20px;\"";
         print ">".get_html_product_name($cart_item['product_name'],GET_PROD_CHECKOUT,
                                         $cart,$cart_item);
         if (has_attribute_prices($cart_item['attribute_array']) &&
             empty($hide_cart_prices) && floatval($cart_item['price'])) {
            print ' - <span class="cart_price">';
            $cart->write_amount($cart_item['price'],true);
            print '</span>';
         }
         print get_html_attributes($cart_item['attribute_array'],GET_ATTR_CHECKOUT,
                                   $cart,$cart_item);
         $item_total = get_item_total($cart_item,true);
         $cart->write_reorder_info($cart_item);
         if ($on_account_product) {
            print "<br>\n".'<span class="cart_on_account"><input type="checkbox" name="' .
                  'on_account_'.$id.'" id="on_account_'.$id.'"';
            if (isset($cart_item['flags']) &&
                ($cart_item['flags'] & ON_ACCOUNT_ITEM)) {
               print ' checked';   $on_account_total += $item_total;
               $num_on_account_items++;
            }
            else $all_products_on_account = false;
            print ' onClick="check_on_account_product(this,'.$id.','.$item_total .
                  ');"><label for="on_account_'.$id.'">Pay On Account</label></span>' .
                  "\n";
         }
         print "</td>\n";
         if (isset($show_part_number_in_cart)) {
            print "        <td align=\"center\" nowrap>";
            if (isset($cart_item['part_number'])) print $cart_item['part_number'];
            print "</td>\n";
         }
         if (empty($hide_cart_prices)) {
            print "        <td align=\"right\" class=\"cart_price\">";
            $unit_price = get_item_total($cart_item,false);
            $cart->write_amount($unit_price,true);
            print "</td>\n";
         }
         print "        <td align=\"center\">".$qty."</td>\n";
         if (empty($hide_cart_prices)) {
            print "        <td align=\"right\" class=\"cart_price\">";
            $cart->write_amount($item_total,true);
            print "</td>\n";
         }
         print "      </tr>\n";
         if ($checkout_table_cellspacing > 0)
            print "      <tr style=\"height:5px;\"><td colspan=\"5\"></td></tr>\n";
         $index++;
      }
   }
   if ($credit_balance) {
      print "      <tr id=\"apply_credit_row\">\n";
      print "        <td colspan=\"5\" align=\"right\"><span class=\"cart_button " .
            "apply_credit\" onClick=\"apply_store_credit(".$credit_balance.");\">" .
            "Apply Store Credit ($".number_format($credit_balance,2) .
            ")</span></td>\n";
      print "      </tr>\n";
   }
    if (function_exists("write_checkout_post_items_content"))
       write_checkout_post_items_content($cart);
/*
       Mobile Cart
*/
    if ($mobile_cart) {
       $num_columns = 1;
       if (empty($hide_cart_prices)) {
?>
      <tr id="subtotal_row">
        <td class="cart_prompt" nowrap><?
if ($enhanced_mobile_cart) print T('Subtotal');
else print T('SUB-TOTAL').':';
?></td>
        <td align="right" class="cart_price"><? $cart->write_amount($cart->get('subtotal')); ?></td>
      </tr>
<?
          $tax = $cart->get('tax');
          if ($tax != 0) {
?>
      <tr id="tax_row">
        <td class="cart_prompt"><?
if ($enhanced_mobile_cart) print T('Tax');
else print T('TAX').':'; ?></td>
        <td align="right" id="tax_amount_cell" class="cart_price"><? $cart->write_amount($tax); ?></td>
      </tr>
<?        }
       }
    if (isset($cart->shipping_options) && (count($cart->shipping_options) > 0)) { ?>
      <tr id="shipping_row">
        <td class="cart_prompt" nowrap><span class="shipping_method_title"><?
    if ((! $cart->only_manual_shipping()) && (count($cart->shipping_options) != 1)) {
       if ($enhanced_mobile_cart) print 'Shipping';
       else print T('SHIPPING METHOD').':';
    }
    print "</span></td>\n<td align=\"right\"";
    if (! empty($hide_cart_prices)) print ' colspan="2"';
    print ">";
    $cart->display_shipping_options($customer); ?>
        </td>
      </tr>
<?
    if ((! empty($on_account_products)) && $all_products_on_account)
       $on_account_total += $cart->info['shipping'];
   }
   if (empty($hide_cart_prices)) {
   if ($enable_rewards) {
      $rewards = $cart->get('rewards');
      if ($rewards) {
         print "      <input type=\"hidden\" name=\"rewards\" id=\"rewards\" value=\"";
         print $rewards;
         print "\">\n";
?>
      <tr id="rewards_row">
        <td class="cart_prompt"><?
if ($enhanced_mobile_cart) print T('Rewards');
else print T('REWARDS').':';
?></td>
        <td align="right" class="cart_price">-<? $cart->write_amount($rewards); ?></td>
      </tr>
<?    }
   }
   $coupon_code = $cart->get('coupon_code');
   $coupon_type = $cart->get('coupon_type');
   if ($coupon_code && ($coupon_type != 3)) {
?>
      <tr id="coupon_row">
        <td class="cart_prompt"><?
if ($enhanced_mobile_cart) print T('Promotion Discount');
else print T('PROMOTION DISCOUNT').':'; ?></td>
        <td align="right" class="cart_price">-<? $cart->write_amount($cart->get('coupon_amount')); ?></td>
      </tr>
<? }
   else if (($cart->features & USE_COUPONS) && (! $hide_checkout_coupon_link)) { ?>
      <tr id="coupon_row">
        <td colspan="2" align="right"><i><a href="cart/index.php">Click Here</a> to enter a Promo Code</i></td>
      </tr>
<? }
   if ($cart->features & GIFT_CERTIFICATES) {
      $gift_code = $cart->get('gift_code');
      if (! empty($gift_code)) {
         print "      <input type=\"hidden\" name=\"gift_amount\" id=\"gift_amount\" value=\"";
         if (isset($cart->info['gift_amount'])) print $cart->info['gift_amount'];
         print "\">\n";
?>
      <tr id="gift_row">
        <td class="cart_prompt"><?
if ($enhanced_mobile_cart) print T('Gift Certificate');
else print T('GIFT CERTIFICATE').':';
?></td>
        <td align="right" id="gift_cell" class="cart_price">-<? $cart->write_amount($cart->get('gift_amount')); ?></td>
      </tr>
<?    }
   }
   else $gift_code = null;
?>
      <tr id="fee_row"<?
if ((! $fee_name) && (! $fee_amount)) print " style=\"display:none;\""; ?>>
        <td id="fee_name_cell" class="cart_prompt"><?
   if ($fee_name) print $fee_name;
   if (! $enhanced_mobile_cart) print ':';
?></td>
        <td align="right" id="fee_amount_cell" class="cart_price"><?
   if ($fee_amount) $cart->write_amount($fee_amount); ?></td>
      </tr>
      <tr id="discount_row"<?
if ((! $discount_name) && (! $discount_amount)) print " style=\"display:none;\""; ?>>
        <td id="discount_name_cell" class="cart_prompt"><?
   if ($discount_name) print $discount_name;
   if (! $enhanced_mobile_cart) print ':';
?></td>
        <td align="right" id="discount_amount_cell" class="cart_price"><?
   if ($discount_amount) $cart->write_amount(-$discount_amount); ?></td>
      </tr>
<?    if (empty($hide_cart_prices)) { ?>
      <tr id="total_row">
        <td class="cart_prompt"><?
if ($enhanced_mobile_cart) print T('Total');
else print T('TOTAL').':';
?></td>
        <td align="right" id="total_cell" class="cart_price"><? $cart->write_amount($cart->get('total')); ?></td>
      </tr>
<?
      if (! empty($on_account_products)) {
         $payment_total = $cart->get('total') - $on_account_total;
         print '      <script type="text/javascript">'."\n";
         print '         num_cart_items = ';
         if (empty($cart_items)) print '0';
         else print count($cart_items);
         print ";\n";
         print '         num_on_account_items = '.$num_on_account_items.";\n";
         print '      </script>'."\n";
?>
      <tr id="payment_total_row">
        <td class="cart_prompt"><?
if ($enhanced_mobile_cart) print T('Payment Total');
else print T('PAYMENT TOTAL').':';
?></td>
        <td align="right" id="payment_total_cell" class="cart_price"><? $cart->write_amount($payment_total); ?></td>
      </tr>
<?
      }
      }
      }
   }
   else {
      $coupon_code = $cart->get('coupon_code');
      $coupon_type = $cart->get('coupon_type');
      $num_columns = 4;
      if (isset($show_part_number_in_cart)) {
         $num_columns++;   $cart->shipping_columns++;
      }
      if (! empty($hide_cart_prices)) $num_columns -= 2;
      $tax = $cart->get('tax');
      if ($cart->features & GIFT_CERTIFICATES)
         $gift_code = $cart->get('gift_code');
      else $gift_code = null;
      if ($checkout_table_cellspacing > 0) {
?>      <tr style="height:5px;"><td colspan="<? print $num_columns + 1; ?>"></td></tr>
<?  }
    }
/*
       Desktop Cart
*/
    if ((! $mobile_cart) && $include_checkout_cart_details) {
       if (empty($hide_cart_prices)) {
?>
      <tr id="subtotal_row" valign="top">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('SUB-TOTAL'); ?></td>
        <td align="right" class="cart_price"><? $cart->write_amount($cart->get('subtotal')); ?></td>
      </tr>
<?
          if ($tax != 0) {
?>
      <tr id="tax_row">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('TAX'); ?></td>
        <td align="right" id="tax_amount_cell" class="cart_price"><? $cart->write_amount($tax); ?></td>
      </tr>
<?        }
       }
       if (isset($cart->shipping_options) && (count($cart->shipping_options) > 0)) { ?>
      <tr id="shipping_row">
        <td class="cart_header" colspan="<? print $cart->shipping_columns; ?>" nowrap align="right"><span class="shipping_method_title"><?
          if ((! $cart->only_manual_shipping()) && (count($cart->shipping_options) != 1))
             print ' '.T('SHIPPING METHOD');
          print "</span>";
          $cart->display_shipping_options($customer); ?></td>
      </tr>
<?
          if ((! empty($on_account_products)) && $all_products_on_account)
             $on_account_total += $cart->info['shipping'];
       }
       if (empty($hide_cart_prices)) {
          if ($enable_rewards) {
             $rewards = $cart->get('rewards');
             if ($rewards) {
                print "      <input type=\"hidden\" name=\"rewards\" id=\"rewards\" value=\"";
                print $rewards;
                print "\">\n";
?>
      <tr id="rewards_row">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('REWARDS'); ?></td>
        <td align="right" class="cart_price">-<? $cart->write_amount($rewards); ?></td>
      </tr>
<?           }
          }
          if ($coupon_code && ($coupon_type != 3)) {
?>
      <tr id="coupon_row">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('PROMOTION DISCOUNT'); ?></td>
        <td align="right" class="cart_price">-<? $cart->write_amount($cart->get('coupon_amount')); ?></td>
      </tr>
<?        }
          else if (($coupon_type != 3) && ($cart->features & USE_COUPONS) && (! $hide_checkout_coupon_link)) { ?>
      <tr id="coupon_row">
        <td colspan="<? print $num_columns + 1; ?>" align="right"><i><a href="cart/index.php">Click Here</a> to enter a Promo Code</i></td>
      </tr>
<?        }
          if ($gift_code) {
             print "      <input type=\"hidden\" name=\"gift_amount\" id=\"gift_amount\" value=\"";
             if (isset($cart->info['gift_amount'])) print $cart->info['gift_amount'];
             print "\">\n";
?>
      <tr id="gift_row">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('GIFT CERTIFICATE'); ?></td>
        <td align="right" id="gift_cell" class="cart_price">-<? $cart->write_amount($cart->get('gift_amount')); ?></td>
      </tr>
<?        } ?>
      <tr id="fee_row"<?
          if ((! $fee_name) && (! $fee_amount)) print " style=\"display:none;\""; ?>>
        <td id="fee_name_cell" class="cart_header" colspan="<? print $num_columns; ?>" align="right"
         style="text-transform: uppercase;"><?
          if ($fee_name) print $fee_name; ?></td>
        <td align="right" id="fee_amount_cell" class="cart_price"><?
          if ($fee_amount) $cart->write_amount($fee_amount); ?></td>
      </tr>
      <tr id="discount_row"<?
          if ((! $discount_name) && (! $discount_amount)) print " style=\"display:none;\""; ?>>
        <td id="discount_name_cell" class="cart_header" colspan="<? print $num_columns; ?>" align="right"
         style="text-transform: uppercase;"><?
          if ($discount_name) print $discount_name; ?></td>
        <td align="right" id="discount_amount_cell" class="cart_price"><?
          if ($discount_amount) $cart->write_amount(-$discount_amount); ?></td>
      </tr>
<?        if (empty($hide_cart_prices)) { ?>
      <tr id="total_row">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('TOTAL'); ?></td>
        <td align="right" id="total_cell" class="cart_price"><? $cart->write_amount($cart->get('total')); ?></td>
      </tr>
<?
          }
          if (! empty($on_account_products)) {
             $payment_total = $cart->get('total') - $on_account_total;
             print '      <script type="text/javascript">'."\n";
             print '         num_cart_items = ';
             if (empty($cart_items)) print '0';
             else print count($cart_items);
             print ";\n";
             print '         num_on_account_items = '.$num_on_account_items.";\n";
             print '      </script>'."\n";
?>
      <tr id="payment_total_row">
        <td class="cart_header" colspan="<? print $num_columns; ?>" align="right"><?= T('PAYMENT TOTAL'); ?></td>
        <td align="right" id="payment_total_cell" class="cart_price"><? $cart->write_amount($payment_total); ?></td>
      </tr>
<?
          }
       }
    }
    }
?>
    </table>
<?
   }
   if ((! $include_checkout_cart_details) && isset($cart->shipping_options) &&
       (count($cart->shipping_options) > 0)) { ?>
    <table cellpadding="0" <?
     if ($mobile_cart) print "cellspacing=\"0\" class=\"checkout_table mobile_cart_table\"";
     else print "cellspacing=\"".$checkout_table_cellspacing .
              "\" class=\"checkout_table\" width=\"580px\"";
     if (count($cart->shipping_options) == 1) print " style=\"display:none;\"";
     ?>>
      <tr id="shipping_row">
        <td class="cart_header" colspan="<? print $cart->shipping_columns; ?>" nowrap align="right"><span class="shipping_method_title"><?
    if ((! $cart->only_manual_shipping()) && (count($cart->shipping_options) != 1))
       print ' '.T('SHIPPING METHOD');
    print "</span>";
    $cart->display_shipping_options($customer); ?></td>
      </tr>
    </table>
<?
   }
   if ((! $include_checkout_cart_details) &&
       function_exists("write_checkout_post_items_content"))
      write_checkout_post_items_content($cart);
   if (function_exists("write_checkout_text")) write_checkout_text($cart);
?>
   <table cellpadding="0" <?
    if ($mobile_cart) print "cellspacing=\"0\" class=\"mobile_checkout_table\"";
    else print "cellspacing=\"".$checkout_table_cellspacing .
               "\" width=\"580px\"";
    ?>><tr valign="top"><td>
<?
   if ((! empty($cart->payment_module)) && $include_direct_payment) {
      if ($mobile_cart) $num_cols = 2;
      else $num_cols = 4;
   if (! empty($on_account_products)) {
      $round_payment_total = round($payment_total,2);
      if (($round_payment_total == 0) || ($round_payment_total == 0.00))
         $continue_no_payment = true;
   }
?>
   <table cellpadding="0" cellspacing="<? print $checkout_table_cellspacing;
?>" id="payment_table" class="checkout_table"<?
      if ($continue_no_payment) print " style=\"display:none;\""; ?>>
    <tr><td colspan="<?= $num_cols; ?>" class="checkout_header payment_header"><?= T('PAYMENT INFORMATION'); ?></td></tr>
    <tr style="height:5px;"><td></td></tr>
<?
      if (isset($errors['CardFailed'])) {
         print "<tr><td colspan=\"".$num_cols."\" class=\"cart_error\">".$error."</td></tr>\n";
      }
      if (function_exists("write_custom_payment_options") &&
          write_custom_payment_options($cart,$customer)) {}
      else if ((count($payment_types) != 1) || ($payment_type != 0)) {
?>    <tr valign="top" class="payment_type_row">
       <td class="checkout_prompt" nowrap><?= T('Payment Type'); ?></td>
       <td colspan="<?= $num_cols-1; ?>"><?
         if (count($payment_types) == 1) {
            print '<input type="hidden" name="payment_type" value="' .
                  $payment_type.'">';
            print $payment_types[$payment_type];
         }
         else foreach ($payment_types as $index => $label) {
?>
           <label style="white-space: nowrap;"><input type="radio" class="cart_field" style="vertical-align: middle;"
            name="payment_type" id="payment_type_<?= $index; ?>" onClick="change_payment_type(<?= $index; ?>);" value="<?= $index; ?>"<?
         if ($payment_type == $index) print " checked"; ?>> <?= T($label); ?></label>&nbsp;&nbsp;&nbsp;
<?       } ?>
         <script type="text/javascript">add_onload_function(function() { change_payment_type(<?= $payment_type; ?>); });</script>
       </td>
    </tr>
<?
      }
      if (isset($payment_types[CREDIT_CARD_TYPE])) {
         if ($payment_type == CREDIT_CARD_TYPE) $style = '';
         else $style = ' style="display:none;"';
         $selected_card_type = get_form_field('card_type');
         if ($selected_card_type)
            $card_logo = '../cartimages/'.$selected_card_type.'-logo.png';
         else $card_logo = '../cartengine/images/blank.gif';
         if (! empty($saved_cards)) {
            $selected_card = get_form_field('SavedCard');
?>
    <tr id="cc_row_0"<? print $style; ?>>
       <td class="checkout_prompt" colspan="<?= $num_cols; ?>">
<?
   $customer->write_saved_card_addresses($saved_cards,$selected_card);
   print T('Use Saved Credit Card');
   if ($enhanced_mobile_cart) print "         <br>\n";
   else print ': ';
?><select name="SavedCard" id="SavedCard" class="cart_field" onChange="change_saved_card(this);">
<? $customer->display_saved_cards($saved_cards,$selected_card); ?>
          </select>
       </td>
    </tr>
<?
            if ($selected_card)
               print "<script type=\"text/javascript\">change_saved_card(document.Card.SavedCard);</script>\n";
         }
         if (! $enhanced_mobile_cart) {
?>
    <tr id="cc_row_1"<? print $style; ?>>
       <td class="checkout_prompt" colspan="<?= $num_cols; ?>"><p><?= T('We accept...'); ?></p>
<?
      foreach ($available_card_types as $card_type => $card_name) {
         $logo = '../cartimages/'.$card_type.'-logo.png';
         print '<img src="'.$logo.'" alt="'.$card_name.'" class="avail_card_logo">'."\n";
      }
      call_payment_event('write_payment_logos',array(&$cart));
?>
       </td>
    </tr>
<?    } ?>
    <tr id="cc_row_2"<? print $style; ?>>
       <td class="checkout_prompt" nowrap><?= T('Card Number'); ?>
<? if ($enhanced_mobile_cart) print "         <br>\n";
   else { ?>
       </td>
       <td nowrap>
<? }
   if (call_payment_event('write_card_field',array(&$cart,'card_number'),
                          true,true)) {}
   else {
?>
         <script type="text/javascript">
            var available_card_types = [<?
    $first_card = true;
    foreach ($available_card_types as $card_type => $card_name) {
       if ($first_card) $first_card = false;
       else print ',';
       print '"'.$card_type.'"';
    }
            ?>];
         </script>
         <input type="hidden" name="card_type" id="card_type" value="<?= $selected_card_type; ?>">
         <input type="text" name="card_number" id="card_number" value="<? write_form_value(get_form_field('card_number')); ?>"
          class="cart_field" onKeyUp="card_number_keyup(this.value);">
         <img id="card_logo" src="<?= $card_logo; ?>">
<? }
   if ($enhanced_mobile_cart) {
      print "         <br>\n";
      foreach ($available_card_types as $card_type => $card_name) {
         $logo = '../cartimages/'.$card_type.'-logo.png';
         print '<img src="'.$logo.'" alt="'.$card_name.'" class="avail_card_logo">'."\n";
      }
      call_payment_event('write_payment_logos',array(&$cart));
   }
?>
       </td>
<?
   if ($enhanced_mobile_cart) { ?>
       <td class="checkout_prompt" nowrap><?= T('CVV Number'); ?><a class="display_cvv_link" href="javascript:display_cvv();"><img border=0 style="vertical-align: bottom;"
        src="cartimages/question.gif" class="cvv_image" alt="Display CVV Example"
        title="Display CVV Example"></a><br>
<?
   if (call_payment_event('write_card_field',array(&$cart,'card_cvv'),
                          true,true)) {}
   else {
?>
         <input type="text" name="card_cvv" id="card_cvv" value="<? write_form_value(get_form_field('card_cvv')); ?>" class="cart_field" style="width:50px;">
           </td>
<?
   }
   }
   if ($mobile_cart) { ?>
    </tr>
<?
      if (isset($errors['card_number']))
         print "<tr id=\"cc_row_3\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
               T('Invalid Card Number')."</td></tr>\n";
      if ($enhanced_mobile_cart && isset($errors['card_cvv']))
         print "<tr id=\"cc_row_8\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
               T('You must specify a CVV Number')."</td></tr>\n";
?>
    <tr id="cc_row_4"<? print $style; ?>>
<? } ?>
       <td class="checkout_prompt"<? if ($enhanced_mobile_cart) print " colspan=\"2\""; ?> nowrap><?= T('Expiration Date'); ?>
<? if ($enhanced_mobile_cart) print "         <br>\n"; else { ?></td>
       <td>
<? }
   if (call_payment_event('write_card_field',array(&$cart,'card_month'),
                          true,true)) {}
   else {
   $selected_card_month = get_form_field("card_month");
            ?><select name="card_month" id="card_month" class="cart_field">
             <option value="01"<? if ($selected_card_month == "01") print " selected"; ?>>01 - January</option>
             <option value="02"<? if ($selected_card_month == "02") print " selected"; ?>>02 - February</option>
             <option value="03"<? if ($selected_card_month == "03") print " selected"; ?>>03 - March</option>
             <option value="04"<? if ($selected_card_month == "04") print " selected"; ?>>04 - April</option>
             <option value="05"<? if ($selected_card_month == "05") print " selected"; ?>>05 - May</option>
             <option value="06"<? if ($selected_card_month == "06") print " selected"; ?>>06 - June</option>
             <option value="07"<? if ($selected_card_month == "07") print " selected"; ?>>07 - July</option>
             <option value="08"<? if ($selected_card_month == "08") print " selected"; ?>>08 - August</option>
             <option value="09"<? if ($selected_card_month == "09") print " selected"; ?>>09 - September</option>
             <option value="10"<? if ($selected_card_month == "10") print " selected"; ?>>10 - October</option>
             <option value="11"<? if ($selected_card_month == "11") print " selected"; ?>>11 - November</option>
             <option value="12"<? if ($selected_card_month == "12") print " selected"; ?>>12 - December</option>
          </select>
<? }
   if (call_payment_event('write_card_field',array(&$cart,'card_year'),
                          true,true)) {}
   else {
?>
          <select name="card_year" id="card_year" class="cart_field">
          <?
             $start_year = date('y');
             $selected_year = get_form_field("card_year");
             for ($year = $start_year;  $year < $start_year + 20;  $year++) {
                if ($year == $selected_year) $select_string = " selected";
                else $select_string = "";
                if (strlen($year) == 1)
                   print "<option value='0".$year."'".$select_string.">200".$year."</option>\n";
                else print "<option value='".$year."'".$select_string.">20".$year."</option>\n";
             }
          ?>
          </select>
<? } ?>
    </tr>
<?
   if ((! $mobile_cart) && isset($errors['card_number']))
      print "<tr id=\"cc_row_5\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
            T('Invalid Card Number')."</td></tr>\n";

   if (isset($errors['card_expired']))
      print "<tr id=\"cc_row_6\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
            T('Card has Expired')."</td></tr>\n";

   if (! $enhanced_mobile_cart) {
?>
    <tr id="cc_row_7"<? print $style; ?>>
       <td class="checkout_prompt" nowrap><?= T('CVV Number'); ?></td>
         <td><?
   if (call_payment_event('write_card_field',array(&$cart,'card_cvv'),
                          true,true)) {}
   else {
           ?><input type="text" name="card_cvv" id="card_cvv" value="<? write_form_value(get_form_field('card_cvv')); ?>" class="cart_field" style="width:50px;">
<? } ?>
           <a class="display_cvv_link" href="javascript:display_cvv();"><img border=0 style="vertical-align: bottom;"
            src="cartimages/question.gif" class="cvv_image" alt="Display CVV Example"
            title="Display CVV Example"></a></td>
<? }
   if ($mobile_cart) {
   if (! $enhanced_mobile_cart) {
?>
    </tr>
<?
   if (isset($errors['card_cvv']))
      print "<tr id=\"cc_row_8\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
            T('You must specify a CVV Number')."</td></tr>\n";
   }
?>
    <tr id="cc_row_9"<? print $style; ?>>
<? } ?>
       <td class="checkout_prompt"<? if ($enhanced_mobile_cart) print " colspan=\"2\""; ?> nowrap><?= T('Name on Card'); ?>
<? if ($enhanced_mobile_cart) print "         <br>\n"; else { ?></td>
       <td><?
   }
   if ($disable_card_name) { ?>
<input type="hidden" name="card_name" id="card_name" value="<?= $name_on_card; ?>"><?= $name_on_card; ?>
<? } else if (call_payment_event('write_card_field',array(&$cart,'card_name'),
                                 true,true)) {}
   else {
?>
<input type="text" name="card_name" id="card_name" value="<? write_form_value(get_form_field('card_name')); ?>"
            class="cart_field"><?
  } ?></td>
    </tr>
<?
      if ((! $mobile_cart) && isset($errors['card_cvv']))
         print "<tr id=\"cc_row_10\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
               T('You must specify a CVV Number')."</td></tr>\n";
      if (isset($errors['card_name']))
         print "<tr id=\"cc_row_11\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
               T('You must specify a Name')."</td></tr>\n";
      if ($cart->include_purchase_order) {
?>
    <tr id="cc_row_12"<? print $style; ?>>
       <td class="checkout_prompt"><?= T('Purchase Order'); ?></td>
       <td><input type="text" name="purchase_order" id="purchase_order" value="<? write_form_value(get_form_field('purchase_order')); ?>"
            class="cart_field"></td>
    </tr>
<?       }
         if ($saved_cards_flag) {
?>
    <tr id="cc_row_13"<? print $style; ?>>
       <td class="checkout_prompt" colspan="<?= $num_cols; ?>">
<?
            if (! empty($force_save_card)) {
               print '<input type="hidden" id="SaveCard" name="SaveCard" value="on">'."\n";
               print $force_save_card;
            }
            else if ($cart->check_auto_reorders() && empty($saved_cards))
               print T('Credit card details will be saved automatically for future re-orders').'.';
            else {
?>      <input type="checkbox" id="SaveCard" name="SaveCard"<?
   if (get_form_field('SaveCard') == 'on') print " checked";
    ?>><label for="SaveCard"><?= T('Save credit card details for future orders'); ?>.</label>
<?          } ?></td></tr>
<?
         }
      }
      if (isset($payment_types[ECHECK_TYPE])) {
         if ($payment_type == ECHECK_TYPE) $style = '';
         else $style = ' style="display:none;"';
?>
    <tr id="echeck_row_0"<? print $style; ?>>
       <td class="checkout_prompt" nowrap><?= T('Bank Name'); ?></td>
       <td><input type="text" name="bank_name" id="bank_name" value="<? write_form_value(get_form_field('bank_name')); ?>"
            class="cart_field"></td>
<? if ($mobile_cart) { ?>
    </tr>
<?
         if (isset($errors['bank_name']))
            print "<tr id=\"echeck_row_1\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must specify a Bank Name')."</td></tr>\n";
?>
    <tr id="echeck_row_2"<? print $style; ?>>
<? } ?>
       <td class="checkout_prompt" nowrap><?= T('Routing Number'); ?></td>
       <td nowrap><input type="text" name="routing_number" id="routing_number" value="<? write_form_value(get_form_field('routing_number')); ?>"
            class="cart_field">
           <a href="javascript:display_echeck();"><img border=0 style="vertical-align: bottom;"
            src="cartimages/question.gif" class="cvv_image" alt="Display Check Example"
            title="Display Check Example"></a></td>
    </tr>
<?
         if ((! $mobile_cart) && isset($errors['bank_name']))
            print "<tr id=\"echeck_row_1\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must specify a Bank Name')."</td></tr>\n";
         if (isset($errors['routing_number'])) {
            print "<tr id=\"echeck_row_3\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must specify a Routing Number')."</td></tr>\n";
         }
?>
    <tr id="echeck_row_4"<? print $style; ?>>
       <td class="checkout_prompt" nowrap><?= T('Account Number'); ?></td>
       <td nowrap><input type="text" name="account_number" id="account_number" value="<? write_form_value(get_form_field('account_number')); ?>"
            class="cart_field">
           <a href="javascript:display_echeck();"><img border=0 style="vertical-align: bottom;"
            src="cartimages/question.gif" class="cvv_image" alt="Display Check Example"
            title="Display Check Example"></a></td>
<? if ($mobile_cart) { ?>
    </tr>
<?
         if (isset($errors['account_number']))
            print "<tr id=\"echeck_row_5\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must specify an Account Number')."</td></tr>\n";
?>
    <tr id="echeck_row_6"<? print $style; ?>>
<? } ?>
       <td class="checkout_prompt" nowrap><?= T('Name on Account'); ?></td>
       <td><input type="text" name="account_name" id="account_name" value="<? write_form_value(get_form_field('account_name')); ?>"
            class="cart_field"></td>
    </tr>
<?
         if ((! $mobile_cart) && isset($errors['account_number']))
            print "<tr id=\"echeck_row_5\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must specify an Account Number')."</td></tr>\n";
         if (isset($errors['account_name']))
            print "<tr id=\"echeck_row_7\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must specify an Account Name')."</td></tr>\n";
         $account_type = get_form_field("account_type");
?>
    <tr id="echeck_row_8"<? print $style; ?>>
       <td class="checkout_prompt"><?= T('Account Type'); ?></td>
       <td colspan="<?= $num_cols-1; ?>"><input type="radio" class="cart_field" name="account_type" id="account_type_0"
            style="vertical-align: middle; margin-top:0px; margin-bottom:0px;"
            value="0"<?
         if ($account_type == 0) print " checked"; ?>> <?= T('Checking'); ?>&nbsp;&nbsp;&nbsp;
           <input type="radio" class="cart_field" name="account_type" id="account_type_1"
            style="vertical-align: middle; margin-top:0px; margin-bottom:0px;"
            value="1"<?
         if ($account_type == 1) print " checked"; ?>> <?= T('Savings'); ?>&nbsp;&nbsp;&nbsp;
           <input type="radio" class="cart_field" name="account_type" id="account_type_2"
            style="vertical-align: middle; margin-top:2px;"
            value="2"<?
         if ($account_type == 2) print " checked"; ?>> <?= T('Business Checking'); ?>
       </td>
    </tr>
<?
         if (isset($errors['account_type']))
            print "<tr id=\"echeck_row_9\"".$style."><td colspan=\"".$num_cols."\" class=\"cart_error\">" .
                  T('You must select an Account Type')."</td></tr>\n";
         $company_name = get_cart_config_value('companyname');
?>
    <tr id="echeck_row_10"<? print $style; ?>><td colspan="<?= $num_cols; ?>">By clicking the button below, I
     authorize <? print $company_name; ?> to charge my <?
         if ($account_type == 1) print "savings"; else print "checking"; ?> account on <?
         print date("F j, Y"); ?> for the amount of
     <span id="echeck_total" class="cart_price"><? $cart->write_amount($cart->get('total')); ?></span> for this purchase.
    </td></tr>
<?
   }
   if (function_exists('write_custom_payment_fields'))
      write_custom_payment_fields($cart,$customer);
?>
  </table>
  <div id="cvvImage" style="position:absolute;left:250px;top:400px;background-color:#FFFFFF;border:1px solid #8C8C8C;display:none;" onClick="remove_cvv();">
    <table cellpadding="0" cellspacing="0">
      <tr><td style="padding:5px;">
      <img style="border:1px solid #636565;filter:alpha(opacity=95);padding:10px"
       src="cartimages/cvv.gif" alt="CVV Example" title="CVV Example">
      </td></tr>
      <tr><td class="popupAd" align='center'><img src="cartimages/close.gif"
       style="vertical-align: bottom;" alt="Close" title="Close"> <?= T('Click to Close'); ?></td></tr>
      <tr style="height:5px;"><td></td></tr>
    </table>
  </div>
<?
      if ($cart->enable_echecks) {
?>
  <div id="eCheckImage" style="position:absolute;left:250px;top:400px;background-color:#FFFFFF;border:1px solid #8C8C8C;display:none;" onClick="remove_echeck();">
    <table cellpadding="0" cellspacing="0">
      <tr><td style="padding:5px;">
      <img style="border:1px solid #636565;filter:alpha(opacity=95);padding:10px"
       src="cartimages/echeck.gif" alt="Check Example" title="Check Example">
      </td></tr>
      <tr><td class="popupAd" align='center'><img src="cartimages/close.gif"
       style="vertical-align: bottom;" alt="Close" title="Close"> <?= T('Click to Close'); ?></td></tr>
      <tr style="height:5px;"><td></td></tr>
    </table>
  </div>
<?
      }
   }
   else if (call_payment_event('write_continue_checkout',
                               array(&$cart),true,true)) {}
   else if (function_exists("write_custom_payment_options"))
      write_custom_payment_options($cart,$customer);
?>
  </td>
   </tr>
   <tr style="height:20px;"><td></td></tr>
  <tr>
  <td>
    <table cellpadding="0" cellspacing="<? print $checkout_table_cellspacing; ?>">
<? if ($cart->registry) { ?>
      <tr><td class="checkout_header"><?= T('GIFT MESSAGE'); ?></td></tr>
      <tr><td><textarea name="gift_message" id="gift_message" class="registry_message_field" rows="5" cols="80"><?
      if (isset($cart->info['gift_message'])) print $cart->info['gift_message'];
      else write_form_value(get_form_field('gift_message')); ?></textarea></td></tr>
<?
      if (isset($errors['gift_message']))
         print "<tr><td class=\"cart_error\">" .
               T('You must specify a Gift Message')."</td></tr>\n";
   }
   if ($enable_checkout_comments) {
      if ($cart->registry) {
?>
      <tr><td class="checkout_header"><?= T(strtoupper($comments_title)); ?></td></tr>
      <tr><td><textarea name="<?= $cart->comments_field_name; ?>" id="<?= $cart->comments_field_name; ?>" class="registry_comments_field" rows="5" cols="80"><?
         if (isset($cart->info['comments']) && $cart->info['comments']) write_form_value($cart->info['comments']);
         else write_form_value(get_form_field($cart->comments_field_name)); ?></textarea></td></tr>
<?
         if (isset($errors['comments']))
            print "<tr><td class=\"cart_error\">" .
                  T('You must specify a '.$comments_title)."</td></tr>\n";
      } else { ?>
      <tr><td class="checkout_header"><?= T(strtoupper($comments_title)); ?></td></tr>
      <tr><td><textarea name="<?= $cart->comments_field_name; ?>" id="<?= $cart->comments_field_name; ?>" class="comments_field" rows="10" cols="80"><?
         if (isset($cart->info['comments']) && $cart->info['comments']) write_form_value($cart->info['comments']);
         else write_form_value(get_form_field($cart->comments_field_name)); ?></textarea></td></tr>
<?
         if (isset($errors['comments']))
            print "<tr><td class=\"cart_error\">" .
                  T('You must specify a '.$comments_title)."</td></tr>\n";
      }
   }
?>
    </table>
  </td></tr>
<?
    if ($enable_partial_shipments && (! $disable_partial_ship_option)) {
       print "    <table cellpadding=0 cellspacing=2 class=\"cart_font " .
             "checkout_options_table\" width=\"580px\">\n";
       print "      <tr>\n";
       print "        <td><input type=\"checkbox\" name=\"partial_ship\" " .
             "id=\"partial_ship\"";
       if (get_form_field('partial_ship') == 'on') print " checked";
       print ">\n";
       print "            <label for=\"partial_ship\">Yes, please Partial Ship " .
             "if an item is not currently in stock.</label>\n";
       print "            <p>I understand that my order is subject to an " .
             "additional shipping and handling charge.</p>\n";
       print "      </td></tr>\n";
   }
   if (function_exists("write_checkout_post_comments_content"))
      write_checkout_post_comments_content($cart);
   if ($guest_checkout) {
      if (! isset($create_account_text))
         $create_account_text = str_replace('%1',get_cart_config_value('companyname'),
            T('By creating an account at %1 you will be able to shop faster, ' .
              'be up to date on an orders status, and keep track of the orders ' .
              'you have previously placed.'));

?>
      <tr style="height:20px;"><td></td></tr>
      <tr>
        <td>
          <table cellpadding="0" cellspacing="<? print $checkout_table_cellspacing;
?>" id="account_table">
            <tr><td class="checkout_header" style="padding-top: 0px;" colspan="2"><?
   if ($cart->account_required) print T('CREATE ACCOUNT');
   else print T('CREATE ACCOUNT (optional)'); ?></td></tr>
            <tr>
              <td colspan="2" class="account_text"><?= $create_account_text; ?></td>
            </tr>
            <tr><td class="checkout_prompt"><?= T('Password'); ?></td>
              <td class="account_password_cell"><input type="password" class="cart_field" name="cust_password" id="cust_password" size="20"
                   value="<? print $customer->get("cust_password"); ?>"></td></tr>
           </table>
        </td>
      </tr>
<?
      if (isset($errors['cust_password']))
         print "<tr><td class=\"cart_error\">".T('You must specify a password') .
               "</td></tr>\n";
   } ?>
      <tr style="height:20px;"><td></td></tr>
  </table>
<?
   if (isset($cart_items) && empty($errors['dberror']) &&
       empty($errors['shippingerror']) && empty($errors['error'])) {
      if ($enable_continue_no_payment) {
         if ($mobile_cart)
            print "  <table cellpadding=\"0\" cellspacing=\"0\" class=\"" .
                  "mobile_checkout_table checkout_continue_table button_table\">\n";
         else print "  <table cellpadding=\"0\" cellspacing=\"" .
                    $checkout_table_cellspacing."\" width=\"580px\" " .
                    "class=\"checkout_continue_table\">\n";
         print "    <tr id=\"continue_no_payment_row\"";
         if ($continue_no_payment)
            log_activity("Displaying ContinueNoPayment button since cart_total = " .
                         $cart_total);
         else print " style=\"display:none;\"";
         print "><td width=\"100%\" align=\"right\" class=\"checkout_continue_cell\">";
         if ($continue_no_payment)
            write_cart_button('ContinueNoPayment','continue',$checkout_continue_button_label);
         else write_cart_button('Continue','continue',$checkout_continue_button_label);
         print "</td></tr>\n";
         print "  </table>\n";
      }
      if ($include_direct_payment) {
         print "  <div style=\"float: left; line-height: 43px;";
         if ($continue_no_payment) print " display:none;";
         print "\" id=\"payment_div_0\">\n    ";

          if (file_exists("engine/ui.php")) {
            require_once 'engine/ui.php';
            require_once 'engine/db.php';
            if (file_exists("admin/custom-config.php"))
                require_once 'admin/custom-config.php';
            require_once 'cartengine/customers-common.php';
          }else {
            require_once '../engine/ui.php';
            require_once '../engine/db.php';
            if (file_exists("../admin/custom-config.php"))
                require_once '../admin/custom-config.php';
            require_once '../cartengine/customers-common.php';
          }

          if(isset($_COOKIE['HiltonHonorsUser'])){
            $db = new DB;
            $query = 'select credit_balance from customers where id=?';
            $query = $db->prepare_query($query,$_COOKIE['HiltonHonorsUser']);
            $row = $db->get_record($query);
            if($row) $credit_balance = $row['credit_balance'];
            else $credit_balance = 0;
          } else {
            $credit_balance = 0;
          }
          if($cart->get('total') > 50){
            if($credit_balance > $cart->get('total')){
              write_cart_button('ContinuePayment','process-payment',
                           $checkout_process_button_label,
                           'return process_payment();',
                           'vertical-align: middle;');
            }else{
              echo "<h5>WARNING</g5>
                    <span>You must have a minimum of $50.00 order in the shopping cart and the credit balance must be equal to or greater than the shopping cart total.</span>";
          }
            }
          }else{
             echo "<h5>WARNING</g5>
                    <span>You must have a minimum of $50.00 order in the shopping cart and the credit balance must be equal to or greater than the shopping cart total.</span>";
          }
         print "&nbsp;</div>\n";
      }
      call_payment_event('write_checkout_payment_button',array(&$cart));
      print "  <div style=\"clear: both;";
      if ($continue_no_payment) print " display:none;";
      print "\" id=\"payment_div_".($cart->num_payment_divs + 1)."\"><!-- --></div>\n";
   }
   print "  </form>\n";
?>
</div>
<$include {cart_footer.html}><?
DB::close_all(); ?>
