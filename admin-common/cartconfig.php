<?php
/*
               Inroads Shopping Cart - Admin Tab - Cart Config Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

require_once 'cartconfig-common.php';
require_once 'analytics.php';
require_once 'shopping-common.php';
require_once 'utility.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

if (! isset($cart_config_label)) $cart_config_label = 'Cart Config';

$cart_config_fields = array('orderprefix','contactemail','contactphone',
   'contacthours','companyname','companylogo','fiscalyear','features',
   'notifications');

$config_flags = array('hide_off_sale_inventory' => 'Hide Off Sale Inventory',
   'enable_inventory_available' => 'Enable Inventory Available Column',
   'enable_checkout_comments' => 'Enable Checkout Comments Field',
   'show_pay_balance' => 'Show Pay Balance Button on My Account',
   'hide_catalog_prices' => 'Hide Prices on Catalog Pages',
   'hide_cart_prices' => 'Hide Prices on Cart/Checkout Pages',
   'disable_shipping_calc' => 'Disable Shipping Calculator',
   'log_cart_errors_enabled' => 'Enable Cart Error Logging',
   'enable_guest_checkout' => 'Enable Guest Checkout',
   'disable_join' => 'Disable Join Page',
   'include_purchase_order' => 'Include Purchase Order Field',
   'qty_pricing_by_product' => 'Quantity Pricing all inventory by product'
);
$config_flag_defaults = array('hide_off_sale_inventory' => false,
   'enable_inventory_available' => false,
   'enable_checkout_comments' => true,
   'show_pay_balance' => true,
   'hide_catalog_prices' => false,
   'hide_cart_prices' => false,
   'disable_shipping_calc' => false,
   'log_cart_errors_enabled' => false,
   'enable_guest_checkout' => false,
   'disable_join' => false,
   'include_purchase_order' => false,
   'qty_pricing_by_product' => false
);

setup_analytics_config_fields($cart_config_fields);
if (isset($enable_rewards) && $enable_rewards)
   $cart_config_fields[] = 'rewards_factor';
foreach ($config_flags as $flag => $value) $cart_config_fields[] = $flag;

if (function_exists('update_cart_config_fields'))
   update_cart_config_fields($cart_config_fields);

$notifications = array('New Order (Admin)','New Order (Customer)',
                       'Back Order','Order Shipment',
                       'Order Cancelled','Customer Registration (Customer)',
                       'Customer Registration (Admin)','Low Quantity Alert');
if (isset($enable_rmas) && $enable_rmas) {
   $notifications[] = 'New RMA (Admin)';
   $notifications[] = 'New RMA (Customer)';
   $notifications[] = 'RMA Approved';
   $notifications[] = 'RMA Denied';
   $notifications[] = 'RMA Completed';
}

function add_yes_no_feature_row($dialog,$prompt,$bitvalue,$features,
                                $labels=null)
{
    $dialog->start_row($prompt,'middle','features_prompt');
    if ($labels) $label = $labels[0];
    else $label = 'Yes';
    $dialog->add_radio_field('features_'.$bitvalue,'Yes',$label .
                             '&nbsp;&nbsp;&nbsp;',$features & $bitvalue);
    if ($labels) $label = $labels[1];
    else $label = 'No';
    $dialog->add_radio_field('features_'.$bitvalue,'',$label,
                             ! ($features & $bitvalue));
    $dialog->end_row();
}

function add_options_feature_row($dialog,$prompt,$field_name,$options,
                                 $features,$align='middle',$wrap_count=null)
{
    $dialog->start_row($prompt,$align,'features_prompt');
    $num_options = count($options);   $option_num = 1;
    foreach ($options as $option) {
       $bitvalue = $option[2];
       if (! $option[0]) $option_flag = (! ($features & $bitvalue));
       else if ($features & $bitvalue) $option_flag = true;
       else $option_flag = false;
       $label = $option[1];
       if ($wrap_count && ($option_num == $wrap_count)) $label .= '<br>';
       else $label .= '&nbsp;&nbsp;&nbsp;';
       $dialog->add_radio_field($field_name,$option[0],$label,$option_flag);
       $option_num++;
    }
    $dialog->end_row();
}

function add_flag_update_row($dialog,$prompt,$table,$field_name,
                             $field_label,$table_row=false)
{
    if ($table_row)
       $dialog->write('<tr><td></td><td class="update_table_flags">');
    else $dialog->write('<div class="update_table_flags">'."\n");
    $dialog->write('<span class="prompt">'.$prompt.':</span><div class="' .
                   'perms_link"><a href="#" onClick="' .
                   'update_table_flags(\''.$table.'\',\''.$field_name .
                   '\',\''.$field_label.'\',true); return false;">' .
                   'Check All</a>');
    $dialog->write("&nbsp;&nbsp;&nbsp;\n");
    $dialog->write('<a href="#" onClick="update_table_flags(\''.$table .
                   '\',\''.$field_name.'\',\''.$field_label.'\',false); ' .
                   'return false;">Uncheck All</a>');
    $dialog->write("</div>\n");
    if ($table_row) $dialog->end_row();
    else $dialog->write("</div>\n");
}

function add_feature_fields($dialog,$features)
{
    add_yes_no_feature_row($dialog,'Maintain Inventory:',MAINTAIN_INVENTORY,
                           $features);
    add_yes_no_feature_row($dialog,'Drop Shipping:',DROP_SHIPPING,
                           $features);
    add_yes_no_feature_row($dialog,'Use Part Numbers:',USE_PART_NUMBERS,
                           $features);
    add_yes_no_feature_row($dialog,'Use Coupons:',USE_COUPONS,
                           $features);
    add_yes_no_feature_row($dialog,'Allow Reorders:',ALLOW_REORDERS,
                           $features);
    add_yes_no_feature_row($dialog,'Hide Out Of Stock:',HIDE_OUT_OF_STOCK,
                           $features);
    add_yes_no_feature_row($dialog,'Gift Certificates:',GIFT_CERTIFICATES,
                           $features);
    $options = array(array('Yes','Yes',QTY_DISCOUNTS),
                     array('','No',QTY_DISCOUNTS|QTY_PRICING),
                     array('Pricing','Quantity Pricing',QTY_PRICING));
    add_options_feature_row($dialog,'Quantity Discounts:',
                            'features_qty_discounts',$options,$features);
    $options = array(array('Yes','Yes',ALLOW_BACKORDERS),
                     array('','No',ALLOW_BACKORDERS|INVENTORY_BACKORDERS),
                     array('Inventory','Inventory',INVENTORY_BACKORDERS));
    add_options_feature_row($dialog,'Allow Back Orders:',
                            'features_back_orders',$options,$features);
    add_flag_update_row($dialog,'Backorderable Inventory','inventory',
                        'backorder','Backorderable',true);
    $options = array(array('Product','Product',MIN_ORDER_QTY_PRODUCT),
                     array('Inventory','Inventory',MIN_ORDER_QTY),
                     array('Both','Both',MIN_ORDER_QTY_BOTH),
                     array('','None',MIN_ORDER_QTY_PRODUCT|MIN_ORDER_QTY|
                                     MIN_ORDER_QTY_BOTH));
    add_options_feature_row($dialog,'Minimum Order Qty:',
                            'features_min_order_qty',$options,$features);
    $options = array(array('Product','Product',REGULAR_PRICE_PRODUCT),
                     array('Inventory','Inventory',REGULAR_PRICE_INVENTORY),
                     array('PriceBreaks','Price Breaks',
                           REGULAR_PRICE_BREAKS));
    add_options_feature_row($dialog,'Regular Price:','features_reg_price',
                            $options,$features);
    $options = array(array('Product','Product',LIST_PRICE_PRODUCT),
                     array('Inventory','Inventory',LIST_PRICE_INVENTORY),
                     array('','None',
                           LIST_PRICE_PRODUCT|LIST_PRICE_INVENTORY));
    add_options_feature_row($dialog,'List Price:','features_list_price',
                            $options,$features);
    $options = array(array('Product','Product',SALE_PRICE_PRODUCT),
                     array('Inventory','Inventory',SALE_PRICE_INVENTORY),
                     array('','None',
                           SALE_PRICE_PRODUCT|SALE_PRICE_INVENTORY));
    add_options_feature_row($dialog,'Sale Price:','features_sale_price',
                            $options,$features);
    $options = array(array('Product','Product',PRODUCT_COST_PRODUCT),
                     array('Inventory','Inventory',PRODUCT_COST_INVENTORY),
                     array('','None',
                           PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY));
    add_options_feature_row($dialog,'Product Cost:','features_product_cost',
                            $options,$features);
    $options = array(array('Item','Item Weight',WEIGHT_ITEM),
                     array('Default','Default Weight',WEIGHT_DEFAULT),
                     array('','Custom',WEIGHT_ITEM|WEIGHT_DEFAULT));
    add_options_feature_row($dialog,'Weight Calculation:','features_weight',
                            $options,$features);
    if (empty($base_order_number)) {
       $options = array(array('','Internal Sequential ID',
                              ORDER_PREFIX|ORDER_PREFIX_ID|ORDER_BASE_ID),
                        array('PrefixTimestamp','Prefix + Timestamp',
                              ORDER_PREFIX),
                        array('PrefixID','Prefix + ID',ORDER_PREFIX_ID),
                        array('BaseID','Base + ID',ORDER_BASE_ID));
       add_options_feature_row($dialog,'Order Number:','features_ordernum',
                               $options,$features,'top',2);
    }
    $options = array(array('Collection','Collection',
                           SUB_PRODUCT_COLLECTION),
                     array('Related','Related',SUB_PRODUCT_RELATED),
                     array('','None',
                           SUB_PRODUCT_COLLECTION|SUB_PRODUCT_RELATED));
    add_options_feature_row($dialog,'Sub Products:','features_sub_products',
                            $options,$features);
}

function add_config_flags($dialog)
{
    global $config_flags,$config_flag_defaults;

    foreach ($config_flags as $var_name => $label) {
       global $$var_name;
       if (isset($$var_name)) $checked = $$var_name;
       else if (isset($config_flag_defaults[$var_name]))
          $checked = $config_flag_defaults[$var_name];
       else $checked = false;
       $dialog->add_checkbox_field($var_name,$label,$checked);
       $dialog->write("<br>\n");
       if ($var_name == 'enable_inventory_available')
          add_flag_update_row($dialog,'In Stock Inventory','inventory',
                              'available','In Stock');
    }
}

function add_check_link($dialog,$label,$function)
{
    $dialog->write('        <a href="#" class="perms_link" onClick="' .
                   $function.' return false;">'.$label."</a>\n");
}

function add_shopping_section($dialog,$label)
{
    $dialog->write('<tr><td colspan="2" class="fieldprompt shopping_section">' .
                   '<div class="shopping_title">'.$label."</div></td></tr>\n");
}

function add_handling_field($dialog,$field_name,$values)
{
    $value = get_row_value($values,$field_name);
    if ($value && (substr($value,-1) == '%')) {
       $value = substr($value,0,-1);   $percent = true;
    }
    else $percent = false;
    $dialog->start_row('Default Handling Cost:','middle');
    $dialog->add_radio_field($field_name.'_percent',0,'$',(! $percent));
    $dialog->write(' or ');
    $dialog->add_radio_field($field_name.'_percent',1,'%',$percent);
    $dialog->add_input_field($field_name,$value,10);
    $dialog->end_row();
}

function parse_handling_field($field_name)
{
    $value = get_form_field($field_name);
    $percent = get_form_field($field_name.'_percent');
    if ($percent) $value .= '%';
    return $value;
}

function cart_config()
{
    global $notifications,$cart_config_label,$cart_config_tabs,$enable_rewards;
    global $enable_multisite,$base_order_number;
    global $shipping_modules,$payment_modules,$shopping_modules;

    $db = new DB;
    $config_values = load_cart_config_values($db);
    if (! isset($config_values)) return;
    $features = get_row_value($config_values,'features');
    if (! empty($enable_multisite))
       $website_settings = get_website_settings($db);
    else $website_settings = 0;
    $option_labels = get_cart_option_labels();
    $months = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep',
                    'Oct','Nov','Dec');

    require_once '../engine/modules.php';
    call_module_event('add_cart_config_tabs',array(&$cart_config_tabs,$db));
    load_shopping_modules();
    call_shopping_event('add_cart_config_tabs',array(&$cart_config_tabs,$db));

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('../cartengine/cartconfig.css');
    $dialog->add_script_file('../cartengine/cartconfig.js');
    load_shipping_modules();
    if (count($shipping_modules) == 0) unset($cart_config_tabs['shipping']);
    else call_shipping_event('shipping_cart_config_head',array(&$dialog,$db));
    if ($website_settings & WEBSITE_SEPARATE_PAYMENT)
       unset($cart_config_tabs['payment']);
    else {
       load_payment_modules($db);
       if (count($payment_modules) == 0) unset($cart_config_tabs['payment']);
       else call_payment_event('payment_cart_config_head',array(&$dialog,$db));
    }
    if (count($shopping_modules) == 0) unset($cart_config_tabs['shopping']);
    else call_shopping_event('cart_config_head',array(&$dialog,$db));
    if (! empty($enable_multisite)) unset($cart_config_tabs['analytics']);
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $first_tab = '';   $last_tab = '';
    if (! $dialog->skin) $middle_width = 570;
    foreach ($cart_config_tabs as $tab_name => $tab_label) {
       if ($tab_label) {
          if (! $first_tab) $first_tab = $tab_name;
          $last_tab = $tab_name;
          if (! $dialog->skin) $middle_width -= 80;
       }
    }
    if (! $dialog->skin) {
       $style = "<style type=\"text/css\">\n";
       $style .= '      .cartconfig_tab_row_middle { width: '.$middle_width .
                 "px; }\n";
       $style .= '    </style>';
       $dialog->add_head_line($style);
    }
    $dialog->set_onload_function('cart_config_onload();');
    $dialog->set_body_id('cart_config');
    $dialog->set_help('cart_config');
    if (function_exists('custom_cart_config_head'))
       custom_cart_config_head($dialog,$db);
    call_module_event('cart_config_head',array(&$dialog,$db)); /* remove when no longer used */
    call_module_event('update_head',array('cartconfig',&$dialog,$db));
    $dialog->start_body($cart_config_label);
    $dialog->set_button_width(135);
    $dialog->start_button_column(false,false,true);
    $dialog->start_bottom_buttons(false);
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_cart_config();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_bottom_buttons();
    $dialog->add_button_separator('country_buttons_row',20);
    $dialog->add_button('Add Country','../cartengine/images/AddUser.png',
                        'add_country();','add_country',false,false,ADD_BUTTON);
    $dialog->add_button('Edit Country','../cartengine/images/EditUser.png',
                        'edit_country();','edit_country',false,false,
                        EDIT_BUTTON);
    $dialog->add_button('Delete Country','../cartengine/images/DeleteUser.png',
                        'delete_country();','delete_country',false,
                        false,DELETE_BUTTON);
    $dialog->add_button('Add Value','../cartengine/images/AddUser.png',
                        'add_cart_option();','add_cart_option',false,
                        false,ADD_BUTTON);
    $dialog->add_button('Edit Value','../cartengine/images/EditUser.png',
                        'edit_cart_option();','edit_cart_option',false,
                        false,EDIT_BUTTON);
    $dialog->add_button('Delete Value','../cartengine/images/DeleteUser.png',
                        'delete_cart_option();','delete_cart_option',false,
                        false,DELETE_BUTTON);
    $dialog->end_button_column();
    $dialog->start_form('admin.php','CartConfig');
    if (! $dialog->skin) $dialog->start_field_table('cartconfig_table');
    $dialog->add_hidden_field('Start','$Start$');

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row($first_tab.'_tab',$first_tab.'_content',
                           'change_tab');
    $using_more = false;
    $avail_space = intval(get_form_field('window_width')) - 240;
    $tab_space = 0;
    foreach ($cart_config_tabs as $tab_name => $tab_label) {
       if ($tab_label) {
          $tab_sequence = 0;
          if ($first_tab == $tab_name) $tab_sequence |= FIRST_TAB;
          if ($last_tab == $tab_name) $tab_sequence |= LAST_TAB;
          $tab_width = (8 * strlen($tab_label)) + 32;
          if ($tab_name == $last_tab) $compare_width = $tab_width;
          else $compare_width = $tab_width + 73;
          if ($dialog->skin && (! $using_more) &&
              ($tab_space + $compare_width > $avail_space)) {
             $dialog->add_tab('more_tab','More',null,null,null,true,null,
                              LAST_TAB,true);
             $dialog->start_tab_menu();
             $using_more = true;
          }
          $dialog->add_tab($tab_name.'_tab',$tab_label,$tab_name.'_tab',
                           $tab_name.'_content','change_tab',true,null,
                           $tab_sequence);
          $tab_space += $tab_width;
       }
    }
    if ($using_more) {
       $dialog->end_tab_menu();   $dialog->end_tab();
    }
    $dialog->end_tab_row('cartconfig_tab_row_middle');

    if (! empty($cart_config_tabs['settings'])) {
       $dialog->start_tab_content('settings_content',true);
       $dialog->set_field_padding(2);
       $dialog->start_field_table('settings_table');

       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
       if (empty($enable_multisite)) {
          $dialog->add_edit_row('Contact Email:','contactemail',
                                $config_values,80);
          $dialog->add_edit_row('Contact Phone:','contactphone',
                                $config_values,80);
          $dialog->add_edit_row('Contact Hours:','contacthours',
                                $config_values,80);
          $dialog->add_edit_row('Company Name:','companyname',
                                $config_values,80);
          $dialog->add_browse_row('Invoice Logo:','companylogo',$config_values,
                                  50,'cart_config','',false,true,true,true);
       }
       if ($features & ORDER_PREFIX)
          $dialog->add_edit_row('Order Number Prefix:','orderprefix',
             $config_values,20,null,'(String to prepend to Timestamp)');
       else if ($features & ORDER_PREFIX_ID)
          $dialog->add_edit_row('Order Number Prefix:','orderprefix',
             $config_values,20,null,'(String to prepend to Order ID)');
       else if ($features & ORDER_BASE_ID)
          $dialog->add_edit_row('Order Number Base:','orderprefix',
             $config_values,20,null,'(Number to add to Order ID)');
       else $dialog->add_hidden_field('orderprefix',$config_values);

       $dialog->start_row('Fiscal Year Start:','middle');
       $dialog->start_choicelist('fiscalyear');
       $fiscal_year = get_row_value($config_values,'fiscalyear');
       for ($loop = 0;  $loop < 12;  $loop++)
          $dialog->add_list_item($loop,$months[$loop],($loop == $fiscal_year));
       $dialog->end_listbox();
       $dialog->end_row();
       if (isset($enable_rewards) && $enable_rewards)
          $dialog->add_edit_row('Rewards Percentage:','rewards_factor',
                                $config_values,5,null,'%');
       if (function_exists('display_custom_cart_config_fields'))
          display_custom_cart_config_fields($dialog,$config_values);

       $notify_flags = get_row_value($config_values,'notifications');
       $dialog->start_row('Notifications:','top');
       $dialog->start_table();
       $dialog->write("<tr valign=\"top\"><td nowrap>\n");
       $end_loop = count($notifications);
       $half_loop = (int) ceil($end_loop / 2);
       for ($loop = 0;  $loop < $end_loop;  $loop++) {
          if ($loop == $half_loop) $dialog->write("</td><td nowrap>\n");
          else if (($loop > 0) && ($loop < $end_loop)) $dialog->write("<br>\n");
          $dialog->add_checkbox_field('notify_'.$loop,$notifications[$loop],
                                      $notify_flags & (1 << $loop));
       }
       $dialog->end_row();
       $dialog->end_table();
       $dialog->end_row();

       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['features'])) {
       $dialog->start_tab_content('features_content',false);
       $dialog->set_field_padding(2);
       $dialog->start_field_table('features_table');

       $dialog->write('<tr><td width="50%" align="top">'."\n");
       $dialog->start_table(null,null,0,4);
       add_feature_fields($dialog,$features);
       $dialog->end_table();
       $dialog->write('</td><td width="50%" class="config_flag_cell">'."\n");
       add_config_flags($dialog);
       $dialog->end_row();

       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['options'])) {
       $dialog->start_tab_content('options_content',false);
       $dialog->start_field_table('options_table');

       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
       $dialog->start_row('Option List:','top');
       $num_tables = 0;
       foreach ($option_labels as $option_name)
          if ($option_name) $num_tables++;
       if ($num_tables > 10) $num_tables = 10;
       $dialog->start_listbox('OptionTable',$num_tables,false,
                              'change_option_table();');
       foreach ($option_labels as $option_index => $option_name)
          if ($option_name)
             $dialog->add_list_item($option_index,$option_name,
                                    ($option_index == 0));
       $dialog->end_listbox();
       $dialog->end_row();
       $query = 'select max(length(label)) as longest,max(c) as max_options ' .
                'from (select count(id) as c from cart_options group by ' .
                'table_id) as max_count join cart_options';
       $option_info = $db->get_record($query);
       $dialog->start_row('Values:','top');
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write('        <script>create_options_grid(' .
                      $num_tables.','.$option_info['longest'].',' .
                      $option_info['max_options'].");</script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_row();
       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['countries'])) {
       $dialog->start_tab_content('countries_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>create_countries_grid();</script>\n");
       $dialog->write("        </div>\n");
       $dialog->write("        <div class=\"country_links\">\n");
       add_check_link($dialog,'Check All Billing','check_all_countries(5);');
       add_check_link($dialog,'Uncheck All Billing',
                              'uncheck_all_countries(5);');
       add_check_link($dialog,'Check All Shipping','check_all_countries(6);');
       add_check_link($dialog,'Uncheck All Shipping',
                              'uncheck_all_countries(6);');
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['states'])) {
       $dialog->start_tab_content('states_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>create_states_grid();</script>\n");
       $dialog->write("        </div>\n");
       $dialog->write("        <div class=\"state_links\">\n");
       add_check_link($dialog,'Check All Billing','check_all_states(5);');
       add_check_link($dialog,'Uncheck All Billing','uncheck_all_states(5);');
       add_check_link($dialog,'Check All Shipping','check_all_states(6);');
       add_check_link($dialog,'Uncheck All Shipping','uncheck_all_states(6);');
       add_check_link($dialog,'Contiguous U.S. Only','contiguous_us_only();');
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['shipping'])) {
       $dialog->start_tab_content('shipping_content',false);

       $shipping_tabs = array();
       call_shipping_event('shipping_tabs',array(&$shipping_tabs));
       reset($shipping_tabs);   $first_tab = key($shipping_tabs);
       end($shipping_tabs);   $last_tab = key($shipping_tabs);

       $dialog->set_field_padding(2);
       if (! $dialog->skin) $dialog->start_field_table('shipping_table');
       $dialog->start_subtab_section('subtab_section');

       $dialog->start_subtab_row($first_tab.'_tab',$first_tab.'_content',
                                 'change_subtab');
       foreach ($shipping_tabs as $tab => $label) {
          if ($tab == $first_tab) $tab_sequence = FIRST_TAB;
          else if ($tab == $last_tab) $tab_sequence = LAST_TAB;
          else $tab_sequence = 0;
          $dialog->add_subtab($tab.'_tab',$label,$tab.'_tab',$tab.'_content',
                              'change_subtab',$tab_sequence);
       }
       $dialog->end_subtab_row('shipping_tab_row_middle');

       call_shipping_event('shipping_cart_config_section',
                           array($db,&$dialog,$config_values));

       $dialog->end_subtab_section();
       if (! $dialog->skin) $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['payment'])) {
       $dialog->start_tab_content("payment_content",false);
       $dialog->set_field_padding(2);
       $dialog->start_field_table('payment_table');
       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
       call_payment_event('payment_cart_config_section',
                           array($db,&$dialog,$config_values));
       $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['seo'])) {
       $dialog->start_tab_content('seo_content',false);
       $dialog->start_field_table('seo_table');
       $dialog->write("<tr><td colspan=\"2\"><table cellspacing=\"0\" " .
                      "cellpadding=\"50\" align=\"center\">\n");
       $dialog->write("<tr><td><div class=\"buttonwrapper\"><a class=\"ovalbutton\" " .
                      "style=\"width: 180px;\" onClick=\"init_seo_urls(); " .
                      "return false;\" href=\"#\"><span>" .
                      "Initialize SEO URLs</span></a></div></td>\n");
       $dialog->write("<td><div class=\"buttonwrapper\"><a class=\"ovalbutton\" " .
                      "style=\"width: 180px;\" onClick=\"rebuild_htaccess(); " .
                      "return false;\" href=\"#\"><span>" .
                      "Rebuild SEO Rewrite Rules</span></a></div></td></tr>\n");
       $dialog->write("</td></tr></table>\n</td></tr>");
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['shopping'])) {
       $dialog->start_tab_content('shopping_content',false);
       $dialog->start_field_table('shopping_table');
       call_shopping_event('cart_config_section',
                           array($db,&$dialog,$config_values));
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (! empty($cart_config_tabs['analytics']))
       load_analytics_config_tab($db,$dialog,$config_values);
    call_module_event('cart_config_tabs',array($config_values,
                      $cart_config_tabs,&$dialog,$db));
    call_shopping_event('cart_config_tabs',array($config_values,
                        $cart_config_tabs,&$dialog,$db));

    $dialog->end_tab_section();

    $dialog->add_hidden_field('End','$End$');
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_cart_features()
{
    $new_field_value = 0;
    for ($loop = 0;  $loop < NUM_FEATURES;  $loop++) {
       $bit_value = (1 << $loop);
       switch ($bit_value) {
          case QTY_DISCOUNTS:
             $bitfield_name = 'features_qty_discounts';   $set_value = 'Yes';   break;
          case QTY_PRICING:
             $bitfield_name = 'features_qty_discounts';   $set_value = 'Pricing';   break;
          case ALLOW_BACKORDERS:
             $bitfield_name = 'features_back_orders';   $set_value = 'Yes';   break;
          case INVENTORY_BACKORDERS:
             $bitfield_name = 'features_back_orders';   $set_value = 'Inventory';   break;
          case MIN_ORDER_QTY_PRODUCT:
             $bitfield_name = 'features_min_order_qty';   $set_value = 'Product';   break;
          case MIN_ORDER_QTY:
             $bitfield_name = 'features_min_order_qty';   $set_value = 'Inventory';   break;
          case MIN_ORDER_QTY_BOTH:
             $bitfield_name = 'features_min_order_qty';   $set_value = 'Both';   break;
          case REGULAR_PRICE_PRODUCT:
             $bitfield_name = 'features_reg_price';   $set_value = 'Product';   break;
          case REGULAR_PRICE_INVENTORY:
             $bitfield_name = 'features_reg_price';   $set_value = 'Inventory';   break;
          case REGULAR_PRICE_BREAKS:
             $bitfield_name = 'features_reg_price';   $set_value = 'PriceBreaks';   break;
          case LIST_PRICE_PRODUCT:
             $bitfield_name = 'features_list_price';   $set_value = 'Product';   break;
          case LIST_PRICE_INVENTORY:
             $bitfield_name = 'features_list_price';   $set_value = 'Inventory';   break;
          case SALE_PRICE_PRODUCT:
             $bitfield_name = 'features_sale_price';   $set_value = 'Product';   break;
          case SALE_PRICE_INVENTORY:
             $bitfield_name = 'features_sale_price';   $set_value = 'Inventory';   break;
          case PRODUCT_COST_PRODUCT:
             $bitfield_name = 'features_product_cost';   $set_value = 'Product';   break;
          case PRODUCT_COST_INVENTORY:
             $bitfield_name = 'features_product_cost';   $set_value = 'Inventory';   break;
          case WEIGHT_ITEM:
             $bitfield_name = 'features_weight';   $set_value = 'Item';   break;
          case WEIGHT_DEFAULT:
             $bitfield_name = 'features_weight';   $set_value = 'Default';   break;
          case ORDER_PREFIX:
             $bitfield_name = 'features_ordernum';   $set_value = 'PrefixTimestamp';   break;
          case ORDER_PREFIX_ID:
             $bitfield_name = 'features_ordernum';   $set_value = 'PrefixID';   break;
          case ORDER_BASE_ID:
             $bitfield_name = 'features_ordernum';   $set_value = 'BaseID';   break;
          case SUB_PRODUCT_COLLECTION:
             $bitfield_name = 'features_sub_products';   $set_value = 'Collection';   break;
          case SUB_PRODUCT_RELATED:
             $bitfield_name = 'features_sub_products';   $set_value = 'Related';   break;
          default: $bitfield_name = 'features_'.$bit_value;   $set_value = 'Yes';
       }
       if (get_form_field($bitfield_name) == $set_value)
          $new_field_value |= $bit_value;
    }
    return $new_field_value;
}

function load_config_content()
{
    $config_content = file('../admin/config.php');
    if ($config_content === false) {
       log_error('Unable to open admin/config.php');   return null;
    }
    return $config_content;
}

function update_config_variable($var_name,$var_value,&$config_content,
                                $section_name=null)
{
    if (! $config_content) return;
    $num_lines = sizeof($config_content);
    $last_variable = -1;   $variable_found = false;  $section_found = false;
    for ($index = 0;  $index < $num_lines;  $index++) {
       $start_pos = strpos($config_content[$index],'$'.$var_name);
       if ($start_pos !== false) {
          $config_content[$index] = '$'.$var_name.' = '.$var_value.";\n";
          $variable_found = true;   break;
       }
       if (! $section_name) {
          if (strpos($config_content[$index],'$') !== false)
             $last_variable = $index;
       }
       else if ($section_found) {
          if (strpos($config_content[$index],'$') !== false)
             $last_variable = $index;
          else if ($last_variable != -1) break;
       }
       else if (strpos($config_content[$index],$section_name) !== false)
          $section_found = true;
    }
    if (! $variable_found) {
       if ($last_variable == -1)
          $config_content[$num_lines] = '$'.$var_name.' = '.$var_value.";\n";
       else {
          for ($index = $num_lines - 1;  $index > $last_variable;  $index--)
             $config_content[$index + 1] = $config_content[$index];
          $config_content[$index + 1] = '$'.$var_name.' = '.$var_value.";\n";
       }
    }
}

function write_config_content($config_content)
{
    if (! $config_content) return;
    if (get_server_type() == WINDOWS) {
       $num_lines = sizeof($config_content);
       for ($index = 0;  $index < $num_lines - 1;  $index++)
          $config_content[$index] = rtrim($config_content[$index],"\r\n")."\n";
    }
    $config_file = fopen('../admin/config.php','wt');
    if (! $config_file) {
       log_error('Unable to open admin/config.php');   return;
    }
    if (! fwrite($config_file,implode('',$config_content))) {
       log_error('Unable to update admin/config.php');   return;
    }
    fclose($config_file);
}

function update_config_flags($field_name,&$config_content)
{
    global $config_flags,$config_flag_defaults;

    if (! isset($config_flags[$field_name])) return false;
    $checked = get_form_field($field_name);
    if ($checked) $checked = true;
    else $checked = false;
    global $$field_name;
    if (! isset($$field_name)) {
       if (isset($config_flag_defaults[$field_name]) &&
           ($checked == $config_flag_defaults[$field_name])) return true;
    }
    else if ($$field_name == $checked) return true;
    if ($checked) $checked = 'true';
    else $checked = 'false';
    update_config_variable($field_name,$checked,$config_content,
                           'Shopping Cart Configuration Settings');
    return true;
}

function update_cart_config()
{
    global $cart_config_fields,$notifications,$enable_multisite;
    global $cart_config_label,$cart_config_tabs;

    $db = new DB;
    if (! empty($enable_multisite))
       $website_settings = get_website_settings($db);
    else $website_settings = 0;
    call_shipping_event('shipping_update_cart_config_fields',
                        array(&$cart_config_fields));
    if (! ($website_settings & WEBSITE_SEPARATE_PAYMENT))
       call_payment_event('payment_update_cart_config_fields',
                          array(&$cart_config_fields));
    call_shopping_event('update_cart_config_fields',
                        array(&$cart_config_fields));
    $config_values = load_cart_config_values($db);
    if (! isset($config_values)) return;
    $config_content = load_config_content();
    $old_config_content = $config_content;

    $config_record = config_record_definition();
    foreach ($cart_config_fields as $field_name) {
       if (isset($config_values[$field_name]))
          $old_field_value = $config_values[$field_name];
       else $old_field_value = '';
       if ($field_name == 'features')
          $new_field_value = update_cart_features();
       else if (update_config_flags($field_name,$config_content)) {}
       else if ($field_name == 'notifications') {
          $new_field_value = 0;
          for ($loop = 0;  $loop < count($notifications);  $loop++)
             if (get_form_field('notify_'.$loop) == 'on')
                $new_field_value |= (1 << $loop);
       }
       else if (call_shipping_event('shipping_update_cart_config_field',
                                    array($field_name,&$new_field_value,$db),
                                    true,true)) {}
       else if ((! ($website_settings & WEBSITE_SEPARATE_PAYMENT)) &&
                call_payment_event('payment_update_cart_config_field',
                                   array($field_name,&$new_field_value,$db),
                                   true,true)) {
          if ($new_field_value === null) continue;
       }
       else if (call_shopping_event('update_cart_config_field',
                                    array($field_name,&$new_field_value,$db),
                                    true,true)) {}
       else $new_field_value = get_form_field($field_name);
       if ($old_field_value == $new_field_value) continue;
       $config_record['config_name']['value'] = $field_name;
       if (! isset($new_field_value))
          $config_record['config_value']['value'] = '';
       else $config_record['config_value']['value'] = $new_field_value;
       if (isset($config_values[$field_name])) {
          if (! $db->update('cart_config',$config_record)) {
             http_response(422,$db->error);   return;
          }
       }
       else if (! $db->insert('cart_config',$config_record)) {
          http_response(422,$db->error);   return;
       }
    }
    if ($old_config_content != $config_content)
       write_config_content($config_content);
    if (function_exists('custom_update_cart_config'))
       custom_update_cart_config($config_values,$config_record,$db);
    call_shipping_event('shipping_update_cart_config',
                        array($config_values,$config_record,$db));
    if (! ($website_settings & WEBSITE_SEPARATE_PAYMENT))
       call_payment_event('payment_update_cart_config',
                           array($config_values,$config_record,$db));
    call_shopping_event('update_cart_config',
                        array($config_values,$config_record,$db));
    require_once '../engine/modules.php';
    if (! call_module_event('update_cart_config',
                            array($config_values,$config_record,$db))) {
       http_response(422,get_module_errors());   return;
    }

    http_response(201,$cart_config_label.' Updated');
    log_activity('Updated '.$cart_config_label);
}

function update_table_flags()
{
    $table = get_form_field('Table');
    $field_name = get_form_field('Field');
    if (! in_array($field_name,array('available','backorder'))) {
       http_response(406,'Invalid Field Name');   return;
    }
    $flag = get_form_field('Flag');
    if ($table == 'product') $query = 'update products set ';
    else if ($table == 'inventory') $query = 'update product_inventory set ';
    else {
       http_response(406,'Invalid Table Name');   return;
    }
    $query .= $field_name.'=';
    if ($flag == 'true') $query .= '1';
    else $query .= '0';
    $db = new DB;
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Table Flags Updated');
    if ($flag == 'true') $logstr = 'Checked all ';
    else $logstr = 'Unchecked all ';
    $logstr .= $field_name.' fields in '.$table.' Table';
    log_activity($logstr);
}

function cart_options_record_definition()
{
    $cart_options_record = array();
    $cart_options_record['table_id'] = array('type' => INT_TYPE,'key' => true);
    $cart_options_record['id'] = array('type' => INT_TYPE,'key' => true);
    $cart_options_record['label'] = array('type' => CHAR_TYPE);
    $cart_options_record['sequence'] = array('type' => INT_TYPE);
    return $cart_options_record;
}

function display_cart_option_fields($dialog,$edit_type,$row)
{
    $dialog->add_hidden_field('table_id',$row);
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('oldid',get_row_value($row,'id'));
    $dialog->add_edit_row('Id:','id',$row,5);
    $dialog->add_edit_row('Label:','label',$row,30);
    $dialog->add_edit_row('Sequence:','sequence',$row,2);
}

function add_cart_option()
{
    $table_id = get_form_field('table');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file('../cartengine/cartconfig.js');
    $dialog->set_body_id('add_cart_option');
    $dialog->set_help('add_cart_option');
    $dialog->start_body('Add Cart Option');
    $dialog->set_button_width(150);
    $dialog->start_button_column();
    $dialog->add_button('Add Cart Option','../cartengine/images/AddOption.png',
                        'process_add_cart_option();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('admin.php','AddCartOption');
    $dialog->start_field_table();
    display_cart_option_fields($dialog,ADDRECORD,array('table_id' => $table_id));
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_cart_option()
{
    $db = new DB;

    $cart_option_record = cart_options_record_definition();
    $db->parse_form_fields($cart_option_record);
    if (! $db->insert('cart_options',$cart_option_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    http_response(201,'Cart Option Added');
    log_activity('Added Cart Option '.$cart_option_record['label']['value'] .
                 ' to Table #'.$cart_option_record['table_id']['value']);
}

function edit_cart_option()
{
    $db = new DB;
    $table_id = get_form_field('table');
    $id = get_form_field('id');
    $query = 'select * from cart_options where (table_id=?) and (id=?)';
    $query = $db->prepare_query($query,$table_id,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Cart Option not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file('../cartengine/cartconfig.js');
    $dialog_title = 'Edit Cart Option (#'.$id.')';
    $dialog->set_body_id('edit_cart_option');
    $dialog->set_help('edit_cart_option');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_cart_option();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('admin.php','EditCartOption');
    $dialog->start_field_table();
    display_cart_option_fields($dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_cart_option()
{
    $db = new DB;
    $cart_option_record = cart_options_record_definition();
    $db->parse_form_fields($cart_option_record);
    $old_id = get_form_field('oldid');
    if ($old_id != $cart_option_record['id']['value']) {
       $query = 'update cart_options set id=?,label=?,sequence=? where ' .
                '(table_id=?) and (id=?)';
       $query = $db->prepare_query($query,$cart_option_record['id']['value'],
                                   $cart_option_record['label']['value'],
                                   $cart_option_record['sequence']['value'],
                                   $cart_option_record['table_id']['value'],
                                   $old_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    else if (! $db->update('cart_options',$cart_option_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Cart Option Updated');
    log_activity('Updated Cart Option '.$cart_option_record['label']['value'] .
                 ' in Table #'.$cart_option_record['table_id']['value']);
}

function delete_cart_option()
{
    $table_id = get_form_field('table');
    $id = get_form_field('id');

    $db = new DB;
    $cart_option_record = cart_options_record_definition();
    $cart_option_record['table_id']['value'] = $table_id;
    $cart_option_record['id']['value'] = $id;
    if (! $db->delete('cart_options',$cart_option_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Cart Option Deleted');
    log_activity('Deleted Cart Option #'.$id.' from Table #' .
                 $cart_option_record['table_id']['value']);
}

function add_order_source_option()
{
    global $order_source_table_id;

    $label = get_form_field('external_source');
    $db = new DB;
    $query = 'select max(id) as last_id from cart_options where table_id=?';
    $query = $db->prepare_query($query,$order_source_table_id);
    $row = $db->get_record($query);
    if ($row) $id = intval($row['last_id']) + 1;
    else $id = 0;
    $query = 'select max(sequence) as last_sequence from cart_options where ' .
             'table_id=?';
    $query = $db->prepare_query($query,$order_source_table_id);
    $row = $db->get_record($query);
    if ($row) $sequence = intval($row['last_sequence']) + 1;
    else $sequence = 0;
    $cart_option_record = cart_options_record_definition();
    $cart_option_record['table_id']['value'] = $order_source_table_id;
    $cart_option_record['id']['value'] = $id;
    $cart_option_record['label']['value'] = $label;
    $cart_option_record['sequence']['value'] = $sequence;
    if (! $db->insert('cart_options',$cart_option_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    http_response(201,'Order Source Option Added');
    log_activity('Added Order Source Option '.$label);
}

function countries_record_definition()
{
    $countries_record = array();
    $countries_record['id'] = array('type' => INT_TYPE);
    $countries_record['id']['key'] = true;
    $countries_record['country'] = array('type' => CHAR_TYPE);
    $countries_record['code'] = array('type' => CHAR_TYPE);
    $countries_record['handling'] = array('type' => FLOAT_TYPE);
    $countries_record['available'] = array('type' => INT_TYPE);
    $countries_record['available']['fieldtype'] = CHECKBOX_FIELD;
    return $countries_record;
}

function display_country_fields($dialog,$edit_type,$row)
{
    $available = get_row_value($row,'available');
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field("id",get_row_value($row,'id'));
    $dialog->add_edit_row("Country:","country",get_row_value($row,'country'),30);
    $dialog->add_edit_row("Code:","code",get_row_value($row,'code'),5);
    $dialog->add_edit_row("Handling Cost:","handling",get_row_value($row,'handling'),10);
    $dialog->write("<tr valign=middle><td class=\"fieldprompt\" nowrap>Billing Available:</td><td>\n");
    $dialog->add_checkbox_field("billing_available","",$available & 1);
    $dialog->write("</td></tr>\n");
    $dialog->write("<tr valign=middle><td class=\"fieldprompt\" nowrap>Shipping Available:</td><td>\n");
    $dialog->add_checkbox_field("shipping_available","",$available & 2);
    $dialog->write("</td></tr>\n");
}

function add_country()
{
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file("../cartengine/cartconfig.js");
    $dialog->set_body_id('add_country');
    $dialog->set_help('add_country');
    $dialog->start_body("Add Country");
    $dialog->start_button_column();
    $dialog->add_button("Add Country","../cartengine/images/AddOption.png","process_add_country();");
    $dialog->add_button("Cancel","../cartengine/images/Update.png","top.close_current_dialog();");
    $dialog->end_button_column();
    $dialog->start_form("admin.php","AddCountry");
    $dialog->start_field_table();
    display_country_fields($dialog,ADDRECORD,array());
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_country()
{
    $db = new DB;

    $country_record = countries_record_definition();
    $db->parse_form_fields($country_record);
    $available = 0;
    if (get_form_field("billing_available") == 'on') $available |= 1;
    if (get_form_field("shipping_available") == 'on') $available |= 2;
    $country_record['available']['value'] = $available;
    if (! $db->insert("countries",$country_record)) {
       http_response(422,"Database Error: ".$db->error);   return;
    }

    http_response(201,"Country Added");
    log_activity("Added Country ".$country_record['country']['value']);
}

function edit_country()
{
    $db = new DB;
    $id = get_form_field("id");
    $query = 'select * from countries where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,0);
       else process_error("Country not found",0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file("../cartengine/cartconfig.js");
    $dialog_title = "Edit Country (#".$id.")";
    $dialog->set_body_id('edit_country');
    $dialog->set_help('edit_country');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button("Update","../cartengine/images/Update.png",
                        "update_country();");
    $dialog->add_button("Cancel","../cartengine/images/Update.png",
                        "top.close_current_dialog();");
    $dialog->end_button_column();
    $dialog->start_form("admin.php","EditCountry");
    $dialog->start_field_table();
    display_country_fields($dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_country()
{
    $db = new DB;
    $country_record = countries_record_definition();
    if (get_form_field("Command") == "UpdateRecord") {
       unset($country_record['available']['fieldtype']);
       $db->parse_form_fields($country_record);
    }
    else {
       $db->parse_form_fields($country_record);
       $available = 0;
       if (get_form_field("billing_available") == 'on') $available |= 1;
       if (get_form_field("shipping_available") == 'on') $available |= 2;
       $country_record['available']['value'] = $available;
    }
    if (! $db->update("countries",$country_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(200,"Country Updated");
    log_activity("Updated Country ".$country_record['country']['value']." (".
                 $country_record['id']['value'].")");
}

function delete_country()
{
    $id = get_form_field("id");

    $db = new DB;
    $country_record = countries_record_definition();
    $country_record['id']['value'] = $id;
    if (! $db->delete("countries",$country_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,"Country Deleted");
    log_activity("Deleted Country #".$id);
}

function states_record_definition()
{
    $states_record = array();
    $states_record['code'] = array('type' => CHAR_TYPE);
    $states_record['code']['key'] = true;
    $states_record['name'] = array('type' => CHAR_TYPE);
    $states_record['tax'] = array('type' => FLOAT_TYPE);
    $states_record['handling'] = array('type' => FLOAT_TYPE);
    $states_record['available'] = array('type' => INT_TYPE);
    return $states_record;
}

function update_state()
{
    $db = new DB;
    $state_record = states_record_definition();
    $db->parse_form_fields($state_record);
    if (! $db->update("states",$state_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(200,"State Updated");
    log_activity("Updated State ".$state_record['name']['value']." (".
                 $state_record['code']['value'].")");
}

function ajax_init_seo_urls()
{
    require_once 'seo.php';
    set_time_limit(0);
    ini_set('memory_limit',-1);
    ini_set('max_execution_time',0);
    if (! initialize_seo_urls(true)) return;
    if (function_exists('custom_initalize_seo_urls') &&
        (! custom_initalize_seo_urls(true))) return;
    http_response(201,'Initialized SEO URLs');
}

function ajax_rebuild_htaccess()
{
    require_once 'seo.php';
    if (! reset_htaccess(true)) return;
    if (! rebuild_htaccess(true)) return;
    if (function_exists('custom_rebuild_htaccess') &&
        (! custom_rebuild_htaccess(true))) return;
    http_response(201,'Rebuilt .htaccess files');
}

function process_cartconfig_function($cmd)
{
    if ($cmd == 'cartconfig') cart_config();
    else if ($cmd == 'updatecartconfig') update_cart_config();
    else if ($cmd == 'updatetableflags') update_table_flags();
    else if ($cmd == 'addcartoption') add_cart_option();
    else if ($cmd == 'processaddcartoption') process_add_cart_option();
    else if ($cmd == 'editcartoption') edit_cart_option();
    else if ($cmd == 'updatecartoption') update_cart_option();
    else if ($cmd == 'deletecartoption') delete_cart_option();
    else if ($cmd == 'addordersource') add_order_source_option();
    else if ($cmd == 'addcountry') add_country();
    else if ($cmd == 'processaddcountry') process_add_country();
    else if ($cmd == 'editcountry') edit_country();
    else if ($cmd == 'updatecountry') update_country();
    else if ($cmd == 'deletecountry') delete_country();
    else if ($cmd == 'updatestate') update_state();
    else if ($cmd == 'initseo') ajax_init_seo_urls();
    else if ($cmd == 'rebuildhtaccess') ajax_rebuild_htaccess();
    else return false;
    return true;
}

?>
