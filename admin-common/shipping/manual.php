<?php
/*
                   Inroads Shopping Cart - Manual Shipping Module

                        Written 2008-2018 by Randall Severy
                         Copyright 2008-2018 Inroads, LLC
*/

function manual_module_labels(&$module_labels)
{
    $module_labels['manual'] = 'Manual';
}

function manual_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['manual_rates'] = 'Manual Rates';
}

function manual_shipping_cart_config_section($db,$dialog,$values)
{
    $dialog->start_subtab_content('manual_rates_content',
                                  $dialog->current_subtab == 'manual_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('manual_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('Default Shipping Cost:','manual_handling',$values,10,'$');
    $dialog->add_edit_row('Shipping Method Label:','manual_label',$values,30);
    $dialog->add_edit_row('Free Shipping for Order Totals Above:',
                          'manual_free_cutoff',$values,10,'$');
    $dialog->start_row('Free Shipping only for U.S. Orders:','middle');
    $dialog->add_checkbox_field('manual_free_us_only','',$values);
    $dialog->end_row();
    $dialog->add_edit_row('Free Shipping Label:','manual_free_label',$values,30);
    $dialog->write('<tr><td colspan="2" align="center" style="padding-top:20px;"><span ' .
                   'class="fieldprompt">Shipping Cost By Weight</span><br>');
    $dialog->start_table(null,null,0,4);
    $dialog->write('<tr><th>Row</th><th>&nbsp;&nbsp;&nbsp;Min. Weight (lbs)' .
                   '&nbsp;&nbsp;&nbsp;</th><th>Max. Weight (lbs)</th><th>' .
                   '&nbsp;&nbsp;&nbsp;Shipping Cost&nbsp;&nbsp;&nbsp;</th></tr>' .
                   "\n");
    for ($loop = 1;  $loop < 6;  $loop++) {
       $shipping_info = explode('|',get_row_value($values,
                                                  'manual_row_'.$loop));
       $dialog->write('<tr><td align="center">'.$loop."</td>\n");
       $dialog->write('<td align="center">');
       $value = $shipping_info[0];
       $dialog->add_input_field('manual_row_min_'.$loop,$value,5);
       $dialog->write("</td>\n<td align=\"center\">");
       if (! isset($shipping_info[1])) $value = '';
       else $value = $shipping_info[1];
       $dialog->add_input_field('manual_row_max_'.$loop,$value,5);
       $dialog->write("</td>\n<td align=\"center\">");
       if (! isset($shipping_info[2])) $value = '';
       else $value = $shipping_info[2];
       $dialog->add_input_field('manual_row_cost_'.$loop,$value,5);
       $dialog->end_row();
    }
    $dialog->end_table();
    $dialog->end_row();
    if (function_exists('custom_manual_shipping_config'))
       custom_manual_shipping_config($dialog,'manual',$values);

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function manual_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('manual_handling','manual_label','manual_free_cutoff',
       'manual_free_label','manual_free_us_only','manual_row_1','manual_row_2',
       'manual_row_3','manual_row_4','manual_row_5');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function manual_shipping_update_cart_config_field($field_name,
                                                  &$new_field_value,$db)
{
    if (substr($field_name,0,11) == 'manual_row_') {
       $index = intval(substr($field_name,11));
       $min = get_form_field('manual_row_min_'.$index);
       $max = get_form_field('manual_row_max_'.$index);
       $cost = get_form_field('manual_row_cost_'.$index);
       if ((! $min) && (! $max) && (! $cost)) $new_field_value = '';
       else $new_field_value = $min.'|'.$max.'|'.$cost;
    }
    else if ($field_name == 'manual_free_us_only') {
       if (get_form_field('manual_free_us_only') == 'on')
          $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function manual_load_shipping_options(&$cart,$customer)
{
    $origin_info = $cart->get_origin_info(null,0,$customer);
    $total_weight = 0;
    foreach ($origin_info as $origin_zip => $weight)
       $total_weight += $origin_info[$origin_zip];
    $shipping_country_info = get_country_info($customer->shipping_country,
                                              $cart->db);
    $handling = $cart->get_handling($shipping_country_info,$customer,
                                    'manual_handling');
    if (function_exists('custom_add_manual_shipping_option')) {
       custom_add_manual_shipping_option($cart,$customer,$handling);
       return;
    }
    $manual_label = get_cart_config_value('manual_label',$cart->db);
    $manual_free_cutoff = get_cart_config_value('manual_free_cutoff',
                                                $cart->db);
    if ($manual_free_cutoff) {
       $manual_free_us_only = get_cart_config_value('manual_free_us_only',
                                                    $cart->db);
       $cart_total = floatval($cart->get('total')) -
                     floatval($cart->get('shipping'));
       if (($cart_total > floatval($manual_free_cutoff)) &&
           (($customer->shipping_country == 1) ||
            ($manual_free_us_only != 1))) {
          $manual_free_label = get_cart_config_value('manual_free_label',
                                                     $cart->db);
          if (! $manual_free_label) $manual_free_label = 'Free Shipping';
          $cart->unset_default_shipping();
          $cart->add_shipping_option('manual',-1,0,$manual_free_label,true);
          return;
       }
    }
    for ($loop = 1;  $loop < 6;  $loop++) {
       $shipping_info = explode('|',get_cart_config_value(
                                       'manual_row_'.$loop,$cart->db));
       if (is_numeric($shipping_info[0])) $min = floatval($shipping_info[0]);
       else $min = '';
       if (! isset($shipping_info[1])) $max = '';
       else if (is_numeric($shipping_info[1]))
          $max = floatval($shipping_info[1]);
       else $max = '';
       if (! isset($shipping_info[2])) $cost = '';
       else $cost = floatval($shipping_info[2]);
       if ((! $min) && (! $max) && (! $cost)) continue;
       if (($min === '') && ($total_weight < $max)) {
          $handling = $cost;   break;
       }
       if (($max === '') && ($total_weight >= $min)) {
          $handling = $cost;   break;
       }
       if (($min !== '') && ($max !== '') && ($total_weight >= $min) &&
           ($total_weight < $max)) {
          $handling = $cost;   break;
       }
       if (($min === '') && ($max === '')) {
          $handling = $cost;   break;
       }
    }
    if ($handling !== '')
       $cart->add_shipping_option('manual',0,$handling,$manual_label,true);
    if (function_exists('add_custom_manual_shipping_options'))
       add_custom_manual_shipping_options($cart);
}

function manual_process_shipping(&$order,$shipping_method)
{
    $shipping_info = explode('|',$shipping_method);
    if (isset($shipping_info[2])) 
       $order->set('shipping',$shipping_info[2]);
    else $order->set('shipping',0);
    $order->set('shipping_carrier',$shipping_info[0]);
    if (isset($shipping_info[1])) $shipping_method = $shipping_info[1];
    else $shipping_method = '';
    if (isset($shipping_info[3])) $shipping_method .= '|'.$shipping_info[3];
    $order->set('shipping_method',$shipping_method);
}

function manual_display_shipping_info($dialog,$order)
{
    global $shipping_title;

    if (! isset($shipping_title)) $shipping_title = 'Shipping';
    $shipping_method = get_row_value($order->info,'shipping_method');
    if ($shipping_method) {
       $shipping_info = explode('|',$shipping_method);
       if (isset($shipping_info[1])) $shipping_method = $shipping_info[1];
       $dialog->add_text_row($shipping_title.' Method:',$shipping_method);
    }
}

function manual_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return '';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       if ($shipping_method == '') return 'Unknown';
       else {
          $shipping_info = explode('|',$shipping_method);
          if (isset($shipping_info[1])) $shipping_method = $shipping_info[1];
          return $shipping_method;
       }
    }
    return null;
}

function manual_get_tracking_url($tracking)
{
    return null;
}

function manual_available_methods()
{
    $methods = array();
    $handling = get_cart_config_value('manual_handling');
    if ($handling !== '') {
       $manual_label = get_cart_config_value('manual_label');
       if (! $manual_label) $manual_label = 'Shipping';
       $methods[0] = $manual_label;
    }
    $manual_free_cutoff = get_cart_config_value('manual_free_cutoff');
    if ($manual_free_cutoff) {
       $manual_free_label = get_cart_config_value('manual_free_label');
       if (! $manual_free_label) $manual_free_label = 'Free Shipping';
       $methods[-1] = $manual_free_label;
    }
    if (function_exists('add_custom_manual_available_methods'))
       add_custom_manual_available_methods($methods);
    return $methods;
}

function manual_all_methods()
{
    return manual_available_methods();
}

function load_manual_shipping_options($db)
{
    global $manual_options;

    $manual_options = array();   $services = 1;
    $manual_label = get_cart_config_value('manual_label',$db);
    if (! $manual_label) $manual_label = 'Shipping';
    $manual_options['0'] = $manual_label;
    $manual_free_cutoff = get_cart_config_value('manual_free_cutoff',$db);
    if ($manual_free_cutoff) {
       $manual_free_label = get_cart_config_value('manual_free_label',$db);
       if (! $manual_free_label) $manual_free_label = 'Free Shipping';
       $manual_options[-1] = $manual_free_label;
       $services = 3;
    }
    return $services;
}

?>
