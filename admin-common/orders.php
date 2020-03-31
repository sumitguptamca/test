<?php
/*
                        Inroads Shopping Cart - Orders Tab

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
require_once 'orders-common.php';
require_once 'customers-common.php';
require_once 'cartconfig-common.php';
require_once 'products-common.php';
require_once 'inventory-common.php';
require_once 'currency.php';
require_once 'utility.php';
require_once 'adminperms.php';

if (! isset($enable_edit_order_items)) $enable_edit_order_items = false;
if (! isset($multiple_customer_accounts)) $multiple_customer_accounts = false;
if (! isset($enable_vendors)) $enable_vendors = false;
if (! isset($products_script_name)) $products_script_name = 'products.php';

function init_order_type()
{
    global $order_type,$order_label,$orders_table,$order_status_table;
    global $custom_order_label;

    switch ($order_type) {
       case QUOTE_TYPE:
          $order_label = 'Quote';   $orders_table = 'quotes';
          $order_status_table = QUOTE_STATUS;   break;
       case INVOICE_TYPE:
          $order_label = 'Invoice';   $orders_table = 'invoices';
          $order_status_table = INVOICE_STATUS;   break;
       case SALESORDER_TYPE:
          $order_label = 'Sales Order';   $orders_table = 'sales_orders';
          $order_status_table = SALESORDER_STATUS;   break;
       default:
          if (isset($custom_order_label)) $order_label = $custom_order_label;
          else $order_label = 'Order';
          $order_type = ORDER_TYPE;   $orders_table = 'orders';
          $order_status_table = ORDER_STATUS;   break;
    }
}
$order_type = get_form_field('ordertype');
init_order_type();

function amount_format($order,$amount)
{
    return format_amount($amount,get_row_value($order->info,'currency'),false);
}

function get_registry_name($db,$registry_id)
{
    $query = 'select * from registry where id=?';
    $query = $db->prepare_query($query,$registry_id);
    $row = $db->get_record($query);
    if (! $row) return '';
    $name = $row['event_name'].' ('.$row['fname'].' '.$row['lname'];
    if ($row['co_fname'] || $row['co_lname'])
       $name .= ' and '.$row['co_fname'].' '.$row['co_lname'];
    $name .= ')';
    return $name;
}

function add_order_filters($screen,$status_values,$db)
{
    global $order_type;

    if (function_exists('add_custom_order_filters'))
       add_custom_order_filters($screen,$db);

    if ($screen->skin && ($order_type == ORDER_TYPE)) {
       $screen->write('<div class="filter"><span>');
       $screen->add_checkbox_field('balance_due_only','Balance Due Only',
                                   false,'filter_orders();');
       $screen->write('</span></div>');
    }

    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Status:');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    if (! $screen->skin) $class = 'select filter_select';
    else $class = null;
    $screen->start_choicelist('status','filter_orders();',$class);
    $screen->add_list_item('','All',true);
    foreach ($status_values as $index => $status)
       $screen->add_list_item($index,$status,false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');

    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Refresh:');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    if (! $screen->skin) $class = 'select filter_select';
    else $class = null;
    $screen->start_choicelist('refresh','update_refresh();',$class);
    $refresh = 60;
    $screen->add_list_item('0','None',! $refresh);
    $refresh_options = array(30,60,90,120);
    foreach ($refresh_options as $refresh_option)
       $screen->add_list_item($refresh_option,$refresh_option,
                              $refresh == $refresh_option);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');

    if (! $screen->skin) $screen->write("</td></tr>\n");
}

function shipping_labels_enabled($db)
{
    global $shipping_labels_enabled,$shipping_modules;

    if (isset($shipping_labels_enabled)) return $shipping_labels_enabled;
    $shipping_labels_enabled = call_shipping_event('verify_shipping_label',
                                                   array($db),true,true);
    return $shipping_labels_enabled;
}

function get_order_templates($db,$main_screen)
{
    global $order_type,$order_templates,$enable_invoices,$include_packing_slip;

    $templates = array();
    if (! empty($order_templates)) {
       foreach ($order_templates as $filename => $template_info) {
          if ($template_info['type'] == $order_type)
             $templates[$filename] = $template_info['name'];
       }
    }
    if ((! isset($order_templates['invoice'])) &&
        (($order_type == INVOICE_TYPE) ||
         (($order_type == ORDER_TYPE) && empty($enable_invoices))))
       $templates['~invoice'] = 'Invoice';
    if ((($order_type == ORDER_TYPE) || ($order_type == SALESORDER_TYPE)) &&
        (! isset($order_templates['packingslip'])) &&
        (! empty($include_packing_slip)))
       $templates['~packingslip'] = 'Packing Slip';
    if ((! $main_screen) && shipping_labels_enabled($db))
       $templates['shipping_label'] = 'Shipping Label';
    return $templates;
}

function display_orders_screen()
{
    global $order_type,$orders_table,$order_status_table,$order_label;
    global $enable_add_order,$enable_sales_reps,$enable_vendors;
    global $enable_multisite,$enable_invoices,$enable_partial_shipments;
    global $enable_salesorders;

    $db = new DB;
    get_user_perms($user_perms,$module_perms,$custom_perms,$db);
    $features = get_cart_config_value('features',$db);
    if ($order_type == ORDER_TYPE) {
       if ($features & ORDER_PREFIX) $order_number_is_id = false;
       else $order_number_is_id = true;
    }
    else $order_number_is_id = true;
    $status_values = load_cart_options($order_status_table,$db);
    if ($order_type == ORDER_TYPE) {
       $query = 'select max(length(order_number)) as number_length from orders';
       $row = $db->get_record($query);
       if ($row) {
          $order_number_width = ($row['number_length'] * 6) + 8;
          if ($order_number_width < 50) $order_number_width = 50;
       }
       else if ($order_number_is_id) $order_number_width = 55;
       else $order_number_width = 110;
       if ($features & ALLOW_REORDERS) $order_number_width += 30;
    }
    else $order_number_width = 75;
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('orders.css');
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('orders.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    add_encrypted_fields($screen,$orders_table);
    if (function_exists('custom_init_orders_screen'))
       custom_init_orders_screen($screen);
    $screen->set_body_id($orders_table);
    $screen->set_help($orders_table);
    require_once '../engine/modules.php';
    call_module_event('update_head',array($orders_table,&$screen,$db));
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar($order_label.'s');
       $screen->start_title_filters();
       add_order_filters($screen,$status_values,$db);
       add_search_box($screen,'search_orders','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }

    switch ($order_type) {
       case ORDER_TYPE: $button_width = 148;   break;
       case QUOTE_TYPE: $button_width = 180;   break;
       case INVOICE_TYPE: $button_width = 135;   break;
       case SALESORDER_TYPE: $button_width = 160;   break;
    }
    $screen->set_button_width($button_width);
    if (function_exists('custom_start_order_buttons'))
       custom_start_order_buttons($screen);
    $screen->start_button_column();
    if (isset($enable_add_order) && $enable_add_order &&
        ($user_perms & ADD_ORDER_BUTTON_PERM))
       $screen->add_button('New '.$order_label,'images/AddOrder.png',
                           'add_order();','add_order',true,false,ADD_BUTTON);
    if ($user_perms & EDIT_ORDER_BUTTON_PERM)
       $screen->add_button('Edit '.$order_label,'images/EditOrder.png',
                           'edit_order();','edit_order',true,false,
                           EDIT_BUTTON);
    if ($user_perms & DELETE_ORDER_BUTTON_PERM)
       $screen->add_button('Delete '.$order_label,'images/DeleteOrder.png',
                           'delete_order();','delete_order',true,false,
                           DELETE_BUTTON);
    $screen->add_button('View '.$order_label,'images/ViewOrder.png',
                        'view_order();','view_order',true,false,VIEW_BUTTON);
    if ($enable_partial_shipments && ($user_perms & EDIT_ORDER_BUTTON_PERM))
       $screen->add_button('Partial Ship','images/PartialShip.png',
                           'add_partial_shipment();','add_partial_shipment');
    if ((! empty($enable_add_order)) && ($user_perms & ADD_ORDER_BUTTON_PERM))
       $screen->add_button('Copy '.$order_label,'images/CopyOrder.png',
                           'copy_order();','copy_order');
    if ($order_type == ORDER_TYPE) {
       if (($features & ALLOW_REORDERS) && $enable_add_order &&
           ($user_perms & ADD_ORDER_BUTTON_PERM))
          $screen->add_button('Add Reorder','images/AddOrder.png',
                              'add_reorder();','add_reorder');
    }
    $templates = get_order_templates($db,true);
    if (! empty($templates)) {
       if (count($templates) == 1) {
          $label = reset($templates);   $template = key($templates);
          $onclick = 'print_order(null,null,\''.$template.'\',\''.$label .
                     '\');';
       }
       else $onclick = 'print_order(null,null);';
       $screen->add_button('Print','images/ViewOrder.png',$onclick,
                           'print_order');
       if (count($templates) > 1) {
          $screen->write('<div id="print_options" style="display:none;">'."\n");
          $screen->start_choicelist('print_option','print_order(this,null);');
          $screen->add_list_item('','Select',true);
          foreach ($templates as $filename => $template_name)
             $screen->add_list_item($filename,$template_name,false);
          $screen->end_choicelist();
          $screen->write("</div>\n");
       }
    }
    if ($order_type == ORDER_TYPE) {
       if (shipping_labels_enabled($db)) {
          $screen->add_button('Shipping Label','images/ViewOrder.png',
                              'shipping_label(null);','shipping_label');
          $screen->add_button('Cancel Shipment','images/ViewOrder.png',
                              'cancel_shipment();','cancel_shipment');
       }
       if ($enable_vendors)
          $screen->add_button('Send To Vendors','images/ViewOrder.png',
                              'send_to_vendors();');
    }
    if ($user_perms & EDIT_ORDER_BUTTON_PERM) {
       if ($order_type == QUOTE_TYPE) {
          if (! empty($enable_salesorders))
             $screen->add_button('Convert to Sales Order',
                'images/CopyOrder.png','convert_quote();','convert_quote');
          else $screen->add_button('Convert to Order',
                  'images/CopyOrder.png','convert_quote();','convert_quote');
       }
       if ((! empty($enable_invoices)) && ($order_type != INVOICE_TYPE) &&
           ($order_type != ORDER_TYPE))
          $screen->add_button('Generate Invoice','images/CopyOrder.png',
                              'generate_invoice();','generate_invoice');
    }
    if (function_exists('display_custom_order_buttons'))
       display_custom_order_buttons($screen,$db);
    call_module_event('display_custom_buttons',
                      array($orders_table,&$screen,$db));
    if (! $screen->skin) {
       add_order_filters($screen,$status_values,$db);
       add_search_box($screen,'search_orders','reset_search');
    }
    $screen->end_button_column();
    $screen->write("\n          <script type=\"text/javascript\">\n");
    $screen->write('             order_type = '.$order_type.";\n");
    if ($order_number_is_id)
       $screen->write("             order_number_is_id = true;\n");
    if (! empty($enable_sales_reps))
       $screen->write("             enable_sales_reps = true;\n");
    $screen->write('             var order_status_values = [');
    $status_width = 100;
    if (empty($status_values)) $max_status = -1;
    else $max_status = max(array_keys($status_values));
    for ($loop = 0;  $loop <= $max_status;  $loop++) {
       if ($loop > 0) $screen->write(',');
       if (isset($status_values[$loop])) {
          $screen->write("\"".$status_values[$loop]."\"");
          $new_width = strlen($status_values[$loop]) * 6;
          if ($new_width > $status_width) $status_width = $new_width;
       }
       else $screen->write("\"\"");
    }
    $screen->write("];\n");
    $screen->write("             status_width = ".$status_width.";\n");
    $screen->write('             order_number_width = ' .
                   $order_number_width.";\n");
    if (! empty($enable_multisite)) {
       $website_settings = get_website_settings($db);
       $screen->write('             website_settings = '.$website_settings .
                      ";\n");
    }
    if (! empty($enable_salesorders)) 
       $screen->write("             enable_salesorders = true;\n");
    if (function_exists('write_custom_order_variables'))
       write_custom_order_variables($screen,$db);
    $screen->write("             load_grid(true);\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function display_payment_info($db,$dialog,$order,$payment_info)
{
    global $payment_status_values;

    $payment_status = get_row_value($payment_info,'payment_status');
    if ($payment_status !== '')
       $dialog->add_text_row('Payment Status:',
                             $payment_status_values[$payment_status]);
    $payment_method = get_row_value($payment_info,'payment_method');
    if ($payment_method != '')
       $dialog->add_text_row('Payment Method:',$payment_method,'top');
    $payment_date = get_row_value($payment_info,'payment_date');
    if ($payment_date)
       $dialog->add_text_row('Payment Date:',
                             date('F j, Y g:i:s a',$payment_date),
                             'bottom',true);
    $payment_user = get_row_value($payment_info,'payment_user');
    if ($payment_user) {
       $full_name = get_user_name($db,$payment_user);
       if (! $full_name) $full_name = $payment_user;
       $dialog->add_text_row('Entered By:',$full_name);
    }
    $payment_amount = get_row_value($payment_info,'payment_amount');
    if ($payment_amount)
       $dialog->add_text_row('Payment Amount:',
          amount_format($order,$payment_amount));
    $card_type = get_row_value($payment_info,'card_type');
    if ($card_type)
       $dialog->add_text_row('Card Type:',get_card_type($card_type));
    $card_name = get_row_value($payment_info,'card_name');
    if ($card_name) $dialog->add_text_row('Name on Card:',$card_name,'top');
    $card_number = get_row_value($payment_info,'card_number');
    if ($card_number) $dialog->add_text_row('Card Number:',$card_number);
    $card_year = get_row_value($payment_info,'card_year');
    if (strlen($card_year) == 2) $card_year = '20'.$card_year;
    $card_month = get_row_value($payment_info,'card_month');
    if ($card_year && $card_month)
       $dialog->add_text_row('Expiration Date:',$card_month.'/'.$card_year);
    $card_cvv = get_row_value($payment_info,'card_cvv');
    if ($card_cvv) $dialog->add_text_row('CVV2 Number:',$card_cvv);
    $check_number = get_row_value($payment_info,'check_number');
    if ($check_number) $dialog->add_text_row('Check Number:',$check_number);
    $payment_id = get_row_value($payment_info,'payment_id');
    if ($payment_id)$dialog->add_text_row('Payment ID:',$payment_id,'top');
    $payment_code = get_row_value($payment_info,'payment_code');
    if ($payment_code)$dialog->add_text_row('Payment Code:',$payment_code);
    $payment_ref = get_row_value($payment_info,'payment_ref');
    if ($payment_ref) $dialog->add_text_row('Payment Ref:',$payment_ref);
}

function display_shipment_info($db,$dialog,$order,$shipment_info,
                               $editable=false)
{
    $shipping_carrier = get_row_value($shipment_info,'shipping_carrier');
    if ($shipping_carrier) {
       if (! shipping_module_event_exists('display_shipping_info',
                                          $shipping_carrier))
          $dialog->add_text_row('Shipping Carrier:','Invalid Shipping Carrier (' .
                                $shipping_carrier.')');
       else {
          $display_shipping_info = $shipping_carrier.'_display_shipping_info';
          $shipment_obj = new StdClass();
          $shipment_obj->info = $shipment_info;
          $display_shipping_info($dialog,$shipment_obj);
       }
    }
    else {
       $shipping_method = get_row_value($shipment_info,'shipping_method');
       if ($shipping_method) 
          $dialog->add_text_row('Shipping Method:',$shipping_method,'top');
    }
    $shipping = get_row_value($shipment_info,'shipping');
    if ($shipping)
       $dialog->add_text_row('Shipping Cost:',amount_format($order,$shipping));
    $shipped_date = get_row_value($shipment_info,'shipped_date');
    if ($shipped_date)
       $dialog->add_text_row('Shipped On:',date('F j, Y g:i:s a',$shipped_date),
                             'bottom',false);
    $weight = get_row_value($shipment_info,'weight');
    if ($weight) $dialog->add_text_row('Shipping Weight:',$weight.' (Lbs)');
    $tracking = get_row_value($shipment_info,'tracking');
    if ($editable)
       $dialog->add_edit_row('Tracking #s:','tracking_'.$shipment_info['id'],
                             $tracking,35);
    else if (! empty($tracking)) {
       if ($shipping_carrier) {
          if (! shipping_module_event_exists('get_tracking_url',
                                             $shipping_carrier))
             $tracking_url = null;
          else {
             $get_tracking_url = $shipping_carrier.'_get_tracking_url';
             $tracking_url = $get_tracking_url($tracking);
          }
       }
       else $tracking_url = null;
       if ($tracking_url)
          $tracking_info = '<a href="'.$tracking_url.'" target="_blank">';
       else $tracking_info = '';
       $tracking_info .= $tracking;
       if ($tracking_url) $tracking_info .= '</a>';
       $dialog->add_text_row('Tracking #s:',$tracking_info,'top');
    }
    $dialog->start_row('Items Shipped:','top');
    if (! empty($shipment_info['items'])) {
       foreach ($shipment_info['items'] as $index => $shipment_item) {
          if ($index > 0) $dialog->write("<br>\n");
          $order_item = null;
          foreach ($order->items as $item_id => $item) {
             if ($shipment_item['item_id'] == $item_id) {
                $order_item = $item;   break;
             }
          }
          if (! $order_item)
             $order_item = array('product_name'=>
                                 'Order Item #'.$shipment_item['item_id'],
                                 'attribute_array'=>array());
          $dialog->write(get_html_product_name($order_item['product_name'],
                                               GET_PROD_ADMIN_VIEW_ORDER,
                                               $order,$order_item));
          $dialog->write(get_html_attributes($order_item['attribute_array'],
                                             GET_ATTR_ADMIN_VIEW_ORDER,
                                             $order,$order_item));
          $dialog->write(' (Qty:'.$shipment_item['qty'].')'."\n");
       }
    }
    $dialog->end_row();
}

function load_rma_info($db,$id,&$rma_status_values,&$rma_reason_values)
{
    global $rma_status_list,$rma_reasons_list;

    $query = 'select r.id,r.status,r.reason,ri.item_id,ri.qty from rmas ' .
             'r join rma_items ri on ri.parent=r.id where r.order_id=?';
    $query = $db->prepare_query($query,$id);
    $rma_items = $db->get_records($query,'item_id');
    if (! $rma_items) return null;
    if (count($rma_items) == 0) return $rma_items;
    if (! isset($rma_status_list)) $rma_status_list = RMA_STATUS;
    $rma_status_values = load_cart_options($rma_status_list,$db);
    if (! isset($rma_reasons_list)) $rma_reasons_list = RMA_REASONS;
    $rma_reason_values = load_cart_options($rma_reasons_list,$db);
    return $rma_items;
}

function set_order_website_id($row,$edit_type)
{
    global $enable_multisite,$website_cookie,$website_id;

    if (empty($enable_multisite)) return;

    if ($edit_type == ADDRECORD) {
       if (isset($_COOKIE[$website_cookie]))
          $website = $_COOKIE[$website_cookie];
       else $website = 0;
       if ($website == '') $website = 0;
    }
    else $website = get_row_value($row,'website');
    $website_id = $website;
}

function add_order_website_info($db,$dialog,$row,$edit_type)
{
    global $enable_multisite,$website_cookie;

    if (empty($enable_multisite)) return;

    if ($edit_type == ADDRECORD) {
       if (isset($_COOKIE[$website_cookie]))
          $website = $_COOKIE[$website_cookie];
       else $website = 0;
       if ($website == '') $website = 0;
    }
    else $website = get_row_value($row,'website');

    if ($edit_type == -1) {
       if (! $website) return;
       $query = 'select * from web_sites where id=?';
       $query = $db->prepare_query($query,$website);
       $row = $db->get_record($query);
       if (! $row) return;
       $dialog->add_text_row('Web Site:',$row['name']);
       return;
    }

    $query = 'select * from web_sites order by domain';
    $web_sites = $db->get_records($query);
    if ($web_sites) {
       $dialog->start_row('Web Site:','middle');
       $dialog->start_choicelist('website');
       foreach ($web_sites as $row)
          $dialog->add_list_item($row['id'],$row['name'],
                                 $website == $row['id']);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
}

function write_item_field_header($dialog,$edit_type,$before_field)
{
    global $item_fields;

    if (! isset($item_fields)) return;
    if ($edit_type == -1) $class = 'order_item_title';
    else $class = 'fieldprompt';
    foreach ($item_fields as $field) {
       if ($field['before'] == $before_field) {
          $dialog->write('<th class="'.$class.'" width="'.$field['width'].'"');
          if (! empty($field['align']))
             $dialog->write(' align="'.$field['align'].'" style="text-align: ' .
                            $field['align'].';"');
          $dialog->write('>'.$field['prompt']."</th>\n");
       }
    }
}

function write_item_field($dialog,$edit_type,$order,$order_item,$index,
                          $before_field)
{
    global $item_fields;

    if (! isset($item_fields)) return;
    foreach ($item_fields as $field_name => $field) {
       if ($field['before'] == $before_field) {
          $dialog->write('<td');
          if (! empty($field['align']))
             $dialog->write(' align="'.$field['align'].'"');
          $dialog->write('>');
          if ($edit_type == -1) {
             if ($field['datatype'] == FLOAT_TYPE)
                $dialog->write(amount_format($order,$order_item[$field_name]));
             else $dialog->write($order_item[$field_name]);
          }
          else {
             $dialog->write('<input type="text" class="text" style="' .
                            'border: 0px;');
             if (! empty($field['align']))
                $dialog->write(' text-align: '.$field['align'].';');
             $dialog->write('" name="'.$field_name.'_'.$index.'" id="' .
                            $field_name.'_'.$index.'" size="' .
                            $field['fieldwidth'].'" value="');
             write_form_value($order_item[$field_name]);
             $dialog->write('">');
          }
          $dialog->write("</td>\n");
       }
    }
}

function view_order()
{
    global $order_type,$order_status_table,$orders_table,$order_label;
    global $name_prompt,$part_number_prompt,$enable_sales_reps;
    global $shipping_title,$comments_title,$view_order_comments_title;
    global $enable_rmas,$enable_partial_shipments,$disable_partial_ship_option;
    global $multiple_customer_accounts,$account_label,$enable_vendors;
    global $enable_reorders,$auto_reorder_label,$show_cost_column,$item_fields;
    global $enable_salesorders,$on_account_products,$enable_auto_reorders;

    if (! isset($part_number_prompt)) $part_number_prompt = 'Part #';
    if (! isset($enable_sales_reps)) $enable_sales_reps = false;
    if (! isset($shipping_title)) $shipping_title = 'Shipping';
    if (! isset($comments_title)) $comments_title = 'Comments';
    else if (isset($view_order_comments_title))
       $comments_title = $view_order_comments_title;
    if (! isset($auto_reorder_label)) $auto_reorder_label = 'Auto-Reorder';
    if (! isset($enable_rmas)) $enable_rmas = false;
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    if (isset($show_cost_column)) $show_cost = $show_cost_column;
    else if ($features & (PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY))
       $show_cost = true;
    else $show_cost = false;
    $id = get_form_field('id');
    $order = load_order($db,$id,$error_msg);
    if (! $order) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    set_order_website_id($order->info,-1);
    if ($features & USE_PART_NUMBERS) load_order_part_numbers($order);
    if ($multiple_customer_accounts) {
       if (! isset($account_label)) $account_label = 'Account';
       load_order_accounts($order);
    }
    $status_values = load_cart_options($order_status_table,$db);
    if (isset($db->error)) {
       process_error('Database Error: '.$db->error,0);   return;
    }
    if (! isset($name_prompt)) $name_prompt = 'Product Name';
    $payments = load_order_payments($order);
    $num_payments = count($payments);
    $shipments = load_order_shipments($order);
    $num_shipments = count($shipments);
    if ($enable_rmas)
       $rma_items = load_rma_info($db,$id,$rma_status_values,$rma_reason_values);
    else $rma_items = null;
    $customer_id = get_row_value($order->info,'customer_id');
    if ($customer_id) {
       $query = 'select id from customers where id=?';
       $query = $db->prepare_query($query,$customer_id);
       if (! $db->get_record($query))
          $customer_name = 'Deleted #'.$customer_id;
       else $customer_name = '#'.$customer_id;
    }
    else $customer_name = 'Guest Customer';

    $dialog = new Dialog;
    $dialog->set_doctype("<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Strict//EN\">");
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $head_block = '<style>.fieldtable { margin-left: 20px;';
    if ($dialog->skin) $head_block .= ' width: 99%;';
    $head_block .= ' }</style>';
    $dialog->add_head_line($head_block);
    $dialog_title = 'View '.$order_label.' (#'.$id.')';
    $dialog->set_body_id('view_order');
    $dialog->set_help('view_order');
    $dialog->start_body($dialog_title);
    $dialog->start_content_area(true);
    $dialog->set_field_padding(1);

    if ($num_payments > 0) {
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" " .
                      "class=\"fieldtable\" width=\"620px\">\n");
       $dialog->write("<tr valign=\"top\"><td width=\"50%\">\n");
       $dialog->write("<table cellpadding=\"1\" cellspacing=\"0\">\n");
    }
    else $dialog->start_field_table('order_information');
    $dialog->write("<tr><td colspan=2 class=\"section_title\">".$order_label .
                   " Information</td></tr>\n");
    $dialog->write("<tr height=\"10px\"><td colspan=2></td></tr>\n");
    $status = get_row_value($order->info,'status');
    if (isset($status_values[$status])) $status = $status_values[$status];
    else $status = '';
    $dialog->add_text_row($order_label.' Status:',$status,'top');
    if ($order_type == ORDER_TYPE)
       $number = get_row_value($order->info,'order_number');
    else $number = $order->id;
    $dialog->add_text_row($order_label.' Number:',$number);
    if ($order_type != QUOTE_TYPE) {
       $quote_id = get_row_value($order->info,'quote_id');
       if ($quote_id) {
          $quote_info = '<a href="" onClick="top.get_content_frame().' .
             'view_order_link('.$quote_id.',1); return false;">' .
             $quote_id.'</a>';
          $dialog->add_text_row('Quote Number:',$quote_info);
       }
    }
    if (($order_type == ORDER_TYPE) || ($order_type == SALESORDER_TYPE)) {
       $query = 'select id from invoices where order_id=?';
       $query = $db->prepare_query($query,$id);
       $invoices = $db->get_records($query,null,'id');
       if ($invoices) {
          if (count($invoices) > 1) $prompt = 'Invoice Numbers:';
          else $prompt = 'Invoice Number:';
          $invoice_info = '';
          foreach ($invoices as $invoice_id) {
             if ($invoice_info) $invoice_info .= ', ';
             $invoice_info .= '<a href="" onClick="top.get_content_frame().' .
                'view_order_link('.$invoice_id.',2); return false;">' .
                $invoice_id.'</a>';
          }
          $dialog->add_text_row($prompt,$invoice_info);
       }
    }
    else {
       $order_id = get_row_value($order->info,'order_id');
       if ($order_id) {
          if (! empty($enable_salesorders)) {
             $prompt = 'Sales Order Number:';   $dest_type = SALESORDER_TYPE;
          }
          else {
             $prompt = 'Order Number:';   $dest_type = ORDER_TYPE;
          }
          $view_info = '<a href="" onClick="top.get_content_frame().' .
             'view_order_link('.$order_id.','.$dest_type.'); return false;">' .
             $order_id.'</a>';
          $dialog->add_text_row($prompt,$view_info);
       }
    }
    if (isset($order->info['reorder'])) {
       $reorder_num = get_row_value($order->info,'reorder');
       $reorder_info = '<a href="" onClick="top.get_content_frame().' .
          'view_order_link('.$reorder_num.',0); return false;">' .
          $reorder_num.'</a>';
       if ($order->info['flags'] & AUTO_REORDER_FLAG)
          $label = $auto_reorder_label;
       else $label = 'Reorder';
       $dialog->add_text_row($label.' of:',$reorder_info);
    }
    if (isset($order->info['external_id']) && $order->info['external_id']) {
       $external_source = get_row_value($order->info,'external_source');
       $dialog->add_text_row($external_source.' Order #:',
          get_row_value($order->info,'external_id'),'bottom',false);
    }
    add_order_website_info($db,$dialog,$order->info,-1);
    $sub_total = get_row_value($order->info,'subtotal');
    if ($order->items || ($sub_total > 0))
       $dialog->add_text_row('Sub Total:',amount_format($order,$sub_total));
    $tax = get_row_value($order->info,'tax');
    if ($tax != 0)
       $dialog->add_text_row('Tax:',amount_format($order,$tax));
    $shipping_carrier = get_row_value($order->info,'shipping_carrier');
    if ($shipping_carrier) {
       if (! shipping_module_event_exists('display_shipping_info',
                                          $shipping_carrier))
          $dialog->add_text_row('Shipping Carrier:','Invalid Shipping Carrier (' .
                                $shipping_carrier.')');
       else {
          $display_shipping_info = $shipping_carrier.'_display_shipping_info';
          $display_shipping_info($dialog,$order);
       }
    }
    else {
       $shipping_method = get_row_value($order->info,'shipping_method');
       if ($shipping_method) 
          $dialog->add_text_row('Shipping Method:',$shipping_method,'top');
    }
    $shipping = floatval(get_row_value($order->info,'shipping'));
    if ($shipping)
       $dialog->add_text_row('Shipping Cost:',amount_format($order,$shipping));
    $coupon_id = get_row_value($order->info,'coupon_id');
    $coupon_amount = get_row_value($order->info,'coupon_amount');
    if ($coupon_id || $coupon_amount) {
       $coupon_label = 'Coupon';
       if ($coupon_id) {
          if ($coupon_id < 0) $coupon_id = abs($coupon_id);
          $query = 'select coupon_code,flags,description from coupons ' .
                   'where id=?';
          $query = $db->prepare_query($query,$coupon_id);
          $coupon_row = $db->get_record($query);
          if ($coupon_row) {
             $coupon_flags = $coupon_row['flags'];
             if ($coupon_flags & 32) {
                $dialog->add_text_row('Special Offer:',
                                      $coupon_row['description'],'top');
                $coupon_label = 'Special Offer';
             }
             else $dialog->add_text_row('Coupon:',$coupon_row['coupon_code']);
          }
       }
       if ($coupon_amount)
          $dialog->add_text_row($coupon_label.' Amount:','-' .
                                amount_format($order,$coupon_amount));
    }
    $gift_id = get_row_value($order->info,'gift_id');
    $gift_amount = get_row_value($order->info,'gift_amount');
    if ($gift_id || $gift_amount) {
       if ($gift_id) {
          $query = 'select coupon_code from coupons where id=?';
          $query = $db->prepare_query($query,$gift_id);
          $gift_row = $db->get_record($query);
          $dialog->add_text_row('Gift Certificate:',$gift_row['coupon_code']);
       }
       if ($gift_amount)
          $dialog->add_text_row('Gift Certificate Amount:','-' .
                                amount_format($order,$gift_amount));
    }
    $fee_name = get_row_value($order->info,'fee_name');
    $fee_amount = get_row_value($order->info,'fee_amount');
    if ($fee_name || $fee_amount) {
       if (! $fee_name) $fee_name = 'Fee';
       $dialog->add_text_row($fee_name.':',amount_format($order,$fee_amount));
    }
    $discount_name = get_row_value($order->info,'discount_name');
    $discount_amount = get_row_value($order->info,'discount_amount');
    if ($discount_name || $discount_amount) {
       if (! $discount_name) $discount_name = 'Discount';
       $dialog->add_text_row($discount_name.':','-' .
                             amount_format($order,$discount_amount));
    }
    $total = get_row_value($order->info,'total');
    if ($order->items || ($total > 0))
       $dialog->add_text_row('Total:',amount_format($order,$total));
    $balance_due = get_row_value($order->info,'balance_due');
    if ($balance_due && (floatval($balance_due) != 0))
       $dialog->add_text_row('Balance Due:',amount_format($order,$balance_due));
    if ($customer_id) {
       $customer_info = '<a href="" onClick="top.get_content_frame().' .
                        'edit_customer('.$customer_id.'); return false;">' .
                        $customer_name.'</a>';
    }
    else $customer_info = $customer_name;
    $dialog->add_text_row('Customer:',$customer_info);
    $ip_address = get_row_value($order->info,'ip_address');
    if ($ip_address) $dialog->add_text_row('IP Address:',$ip_address);
    $order_date = get_row_value($order->info,'order_date');
    if ($order_date)
       $dialog->add_text_row('Order Date:',date('F j, Y g:i:s a',$order_date),
                             'bottom',false);
    if ($enable_partial_shipments && (! $disable_partial_ship_option)) {
       $partial_ship = get_row_value($order->info,'partial_ship');
       if ($partial_ship)
          $dialog->add_text_row('Req Partial Ship:','Yes');
    }
    if ($enable_sales_reps) {
       $sales_rep = get_row_value($order->info,'sales_rep');
       if ($sales_rep) {
          $full_name = get_user_name($db,$sales_rep);
          if ($full_name) $dialog->add_text_row('Sales Rep:',$full_name);
       }
    }
    $purchase_order = get_row_value($order->info,'purchase_order');
    if ($purchase_order && ($purchase_order != ''))
       $dialog->add_text_row('Purchase Order:',$purchase_order);
    $comments = get_row_value($order->info,'comments');
    if ($comments) {
       if ($num_payments > 0) $data_class = 'short_comments_div';
       else $data_class = 'comments_div';
       $dialog->start_row($comments_title.':','top','fieldprompt',$data_class);
       $dialog->write('<tt>');
       $dialog->write(str_replace("\n",'<br>',$comments));
       $dialog->write("</tt>\n");
       $dialog->end_row();
    }
    $registry_id = get_row_value($order->info,'registry_id');
    if ($registry_id)
       $dialog->add_text_row('Registry:',get_registry_name($db,$registry_id));
    $gift_message = get_row_value($order->info,'gift_message');
    if ($gift_message) {
       $dialog->start_row('Gift Message:','top');
       $dialog->write("<div class=\"");
       if ($num_payments > 0) $dialog->write('short_gift_message_div');
       else $dialog->write('gift_message_div');
       $dialog->write("\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$gift_message));
       $dialog->write("</tt></div>\n");
       $dialog->end_row();
    }
    $notes = get_row_value($order->info,'notes');
    if ($notes) {
       $dialog->start_row('Notes:','top');
       $dialog->write("<div class=\"");
       if ($num_payments > 0) $dialog->write('short_notes_div');
       else $dialog->write('notes_div');
       $dialog->write("\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$notes));
       $dialog->write("</tt></div>\n");
       $dialog->end_row();
    }
    $terms = get_row_value($order->info,'terms');
    if ($terms) {
       $dialog->start_row('Terms:','top');
       $dialog->write("<div class=\"");
       if ($num_payments > 0) $dialog->write('short_terms_div');
       else $dialog->write('terms_div');
       $dialog->write("\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$terms));
       $dialog->write("</tt></div>\n");
       $dialog->end_row();
    }

    if (function_exists('display_custom_order_fields'))
       display_custom_order_fields($dialog,3,$order);
    require_once '../engine/modules.php';
    call_module_event('display_custom_fields',
       array($orders_table,$db,&$dialog,3,$order->info));
    $dialog->write("<tr height=\"20px\"><td colspan=2></td></tr>\n");
    $dialog->end_field_table();

    if ($num_payments > 0) {
       $dialog->write("</td><td width=\"50%\">\n");
       $dialog->write("<table cellpadding=\"1\" cellspacing=\"0\">\n");
       if ($num_payments > 0) {
          $dialog->write("<tr><td colspan=2 class=\"section_title\">" .
                         "Payment Information</td></tr>\n");
          $dialog->write("<tr height=\"10px\"><td colspan=\"2\"></td></tr>\n");
          $payment_total = 0;
          foreach ($payments as $payment_info) {
             display_payment_info($db,$dialog,$order,$payment_info);
             $payment_total += $payment_info['payment_amount'];
             $dialog->write("<tr height=\"20px\"><td colspan=\"2\"></td></tr>\n");
          }
          if ($num_payments > 1)
             $dialog->add_text_row('Total Payments:',
                                   amount_format($order,$payment_total));
       }
       $dialog->end_field_table();
       $dialog->write("</td></tr></table>\n");
    }

    if ($num_shipments > 0) {
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"1\" " .
                      "class=\"fieldtable\" width=\"620px\">\n");
       $dialog->write("<tr><td colspan=2 class=\"section_title\">" .
                      "Shipment Information</td></tr>\n");
       $dialog->write("<tr height=\"10px\"><td colspan=\"2\"></td></tr>\n");
       foreach ($shipments as $shipment_info) {
          display_shipment_info($db,$dialog,$order,$shipment_info);
          $dialog->write("<tr height=\"20px\"><td colspan=\"2\"></td></tr>\n");
       }
       $dialog->write("</table>\n");
    }

    $dialog->write("<table cellspacing=\"0\" cellpadding=\"1\" " .
                   "class=\"fieldtable\" width=\"620px\">\n");
    $dialog->write("<tr><td class=\"section_title\" width=\"50%\">" .
                   "Billing Information</td>\n");
    $dialog->write("<td class=\"section_title\" width=\"50%\">" .
                   $shipping_title." Information</td></tr>\n");
    $dialog->write("<tr height=\"10px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->write("<tr valign=\"top\"><td>" .
                   get_row_value($order->info,'fname'));
    $mname = get_row_value($order->info,'mname');
    if ($mname != '') $dialog->write(' '.$mname);
    $dialog->write(' '.get_row_value($order->info,'lname')."<br>\n");
    $company = get_row_value($order->info,'company');
    if ($company != '') $dialog->write($company."<br>\n");
    $dialog->write(get_row_value($order->billing,'address1')."<br>\n");
    $address2 = get_row_value($order->billing,'address2');
    if ($address2 != '') $dialog->write($address2."<br>\n");
    $dialog->write(get_row_value($order->billing,'city').', ' .
                   get_row_value($order->billing,'state') .
                   '  '.get_row_value($order->billing,'zipcode').' ' .
                   get_country_name(get_row_value($order->billing,'country'),
                                    $db)."<br>\n" .
                   get_row_value($order->billing,'phone')."<br>\n" .
                   get_row_value($order->info,'email')."</td>\n");

    $dialog->write('<td>');
    $shipto = get_row_value($order->shipping,'shipto');
    if (isset($shipto) && ($shipto != '')) $dialog->write($shipto);
    else {
       $dialog->write(get_row_value($order->info,'fname'));
       $mname = get_row_value($order->info,'mname');
       if ($mname != '') $dialog->write(' '.$mname);
       $dialog->write(' '.get_row_value($order->info,'lname'));
    }
    $dialog->write("<br>\n");
    $ship_company = get_row_value($order->shipping,'company');
    if ($ship_company != '') $dialog->write($ship_company."<br>\n");
    $dialog->write(get_row_value($order->shipping,'address1')."<br>\n");
    $address2 = get_row_value($order->shipping,'address2');
    if ($address2 != '') $dialog->write($address2."<br>\n");
    $dialog->write(get_row_value($order->shipping,'city').', ' .
                   get_row_value($order->shipping,'state') .
                   '  '.get_row_value($order->shipping,'zipcode').' ' .
                   get_country_name(get_row_value($order->shipping,'country'),
                                    $db)."<br>\n");
    $address_type = get_address_type($order);
    if ($address_type == 2) $dialog->write('Address Type: Residential');
    else $dialog->write('Address Type: Business');
    if (function_exists('display_custom_order_shipping_fields'))
       display_custom_order_shipping_fields($dialog,3,$order);
    $dialog->write("</td></tr>\n");

    $dialog->write("<tr height=\"20px\"><td colspan=\"2\"></td></tr>\n");
    if ($order->items) {
       load_order_item_product_info($order);
       $dialog->write("</table>\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"1\" " .
                      "class=\"fieldtable\" width=\"620px\">\n");
       if (function_exists('display_custom_view_item_titles'))
          display_custom_view_item_titles($dialog);
       else {
          $dialog->write('<tr>');
          write_item_field_header($dialog,-1,'name');
          $dialog->write('<th class="order_item_title" align="left" nowrap>' .
                         $name_prompt."</td>\n");
          write_item_field_header($dialog,-1,'account');
          if ($multiple_customer_accounts)
             $dialog->write('<th class="order_item_title" ' .
                            'width="75px" align="left">' .
                            $account_label."</td>\n");
          write_item_field_header($dialog,-1,'part_number');
          if ($features & USE_PART_NUMBERS)
             $dialog->write('<th class="order_item_title" ' .
                            'width="75px">'.$part_number_prompt."</td>\n");
          write_item_field_header($dialog,-1,'cost');
          if ($show_cost)
             $dialog->write('<th class="order_item_title" align="right" ' .
                            'width="75">Cost'."</th>\n");
          write_item_field_header($dialog,-1,'price');
          $dialog->write('<th class="order_item_title" align="right" ' .
                         'width="75px">Unit Price'."</td>\n");
          write_item_field_header($dialog,-1,'qty');
          $dialog->write('<th class="order_item_title" ' .
                         'width="30px;">Qty'."</td>\n");
          write_item_field_header($dialog,-1,'total');
          $dialog->write('<th class="order_item_title" align="right" ' .
                         'width="75px">Total'."</td>\n");
          write_item_field_header($dialog,-1,'');
          $dialog->write("</tr>\n");
       }
       if (function_exists('display_custom_view_items'))
          $num_cols = display_custom_view_items($dialog,$order,$rma_items);
       else {
          $num_cols = 4;
          if ($multiple_customer_accounts) $num_cols++;
          if ($features & USE_PART_NUMBERS) $num_cols++;
          if ($show_cost) $num_cols++;
          if (isset($item_fields)) $num_cols += count($item_fields);
          $index = 0;
          foreach ($order->items as $item_id => $order_item) {
             if ($index > 0)
                $dialog->write('<tr height="10px"><td colspan="4">' .
                               "</td></tr>\n");
             $dialog->write('<tr valign="top">');
             write_item_field($dialog,-1,$order,$order_item,0,'name');
             $dialog->write('<td>');
             if (empty($order_item['product_flags']) ||
                 (! ($order_item['product_flags'] & HIDE_NAME_IN_ORDERS)))
                $dialog->write(get_html_product_name($order_item['product_name'],
                                                     GET_PROD_ADMIN_VIEW_ORDER,
                                                     $order,$order_item));
             if (has_attribute_prices($order_item['attribute_array']) &&
                 floatval($order_item['price']))
                $dialog->write(' - '.amount_format($order,$order_item['price']));
             $dialog->write(get_html_attributes($order_item['attribute_array'],
                                                GET_ATTR_ADMIN_VIEW_ORDER,
                                                $order,$order_item));
             if ((! empty($enable_reorders)) &&
                 (! empty($order_item['reorder_frequency']))) {
                if (! empty($enable_auto_reorders))
                   $label = $auto_reorder_label;
                else $label = 'Reorder';
                $dialog->write('<br>'.$label.' Every ' .
                               $order_item['reorder_frequency'].' Months');
             }
             if ((! empty($on_account_products)) &&
                 ($order_item['flags'] & ON_ACCOUNT_ITEM))
                $dialog->write('<br>Paid On Account');
             $dialog->write("</td>\n");
             write_item_field($dialog,-1,$order,$order_item,0,'account');
             if ($multiple_customer_accounts)
                $dialog->write('<td>'.$order_item['account_name']."</td>\n");
             write_item_field($dialog,-1,$order,$order_item,0,'part_number');
             if ($features & USE_PART_NUMBERS)
                $dialog->write('<td align="center">' .
                               $order_item['part_number']."</td>\n");
             write_item_field($dialog,-1,$order,$order_item,$index,'cost');
             if ($show_cost)
                $dialog->write('<td align="right">' .
                               amount_format($order,$order_item['cost']) .
                               "</td>\n");
             write_item_field($dialog,-1,$order,$order_item,0,'price');
             $unit_price = get_item_total($order_item,false);
             $dialog->write('<td align="right">' .
                            amount_format($order,$unit_price)."</td>\n");
             write_item_field($dialog,-1,$order,$order_item,0,'qty');
             $dialog->write('<td align="center">'.$order_item['qty']."</td>\n");
             write_item_field($dialog,-1,$order,$order_item,0,'total');
             $item_total = get_item_total($order_item);
             $dialog->write('<td align="right">' .
                            amount_format($order,$item_total)."</td>");
             write_item_field($dialog,-1,$order,$order_item,0,'');
             $dialog->write("</tr>\n");
             if ($enable_rmas && isset($rma_items[$item_id])) {
                $rma_item = $rma_items[$item_id];
                $rma_status = $rma_status_values[$rma_item['status']];
                $rma_reason = $rma_item['reason'];
                if (isset($rma_reason_values[$rma_reason]))
                   $rma_reason = $rma_reason_values[$rma_reason];
                $dialog->write('<tr><td colspan="'.$num_cols.'" class="' .
                               'rma_item">');
                $dialog->write('<a href="#" onClick="return view_rma(' .
                               $rma_item['id'].');">RMA #'.$rma_item['id'].'</a>');
                $dialog->write(' ('.$rma_status.'): '.$rma_reason);
                $dialog->write("</td></tr>\n");
             }
             $index++;
          }
       }
       $dialog->write("<tr height=\"20px\"><td colspan=".$num_cols."></td></tr>\n");
       $dialog->write("<tr><td colspan=".($num_cols - 1) .
                      " class=\"fieldprompt\">Sub Total:</td><td align=\"right\">" .
                      amount_format($order,get_row_value($order->info,'subtotal')) .
                      "</td></tr>\n");
       $tax = floatval(get_row_value($order->info,'tax'));
       if (! empty($tax))
          $dialog->write("<tr><td colspan=".($num_cols - 1) .
                         " class=\"fieldprompt\">Tax:</td><td align=\"right\">" .
                         amount_format($order,$tax)."</td></tr>\n");
       if (! empty($shipping))
          $dialog->write("<tr><td colspan=".($num_cols - 1) .
                         " class=\"fieldprompt\">Shipping:</td><td align=\"right\">" .
                         amount_format($order,$shipping)."</td></tr>\n");
       $coupon_id = get_row_value($order->info,'coupon_id');
       $coupon_amount = get_row_value($order->info,'coupon_amount');
       if ($coupon_amount)
          $dialog->write('<tr><td colspan='.($num_cols - 1) .
                         " class=\"fieldprompt\">".$coupon_label .
                         " Amount:</td><td align=\"right\">" .
                         "-".amount_format($order,$coupon_amount)."</td></tr>\n");
       $gift_id = get_row_value($order->info,'gift_id');
       $gift_amount = get_row_value($order->info,'gift_amount');
       if ((! empty($gift_id)) || $gift_amount)
          $dialog->write("<tr><td colspan=".($num_cols - 1) .
                         " class=\"fieldprompt\">Gift Certificate:</td><td align=\"right\">" .
                         '-'.amount_format($order,$gift_amount)."</td></tr>\n");
       $fee_name = get_row_value($order->info,'fee_name');
       $fee_amount = get_row_value($order->info,'fee_amount');
       if ((! empty($fee_name)) || $fee_amount)
          $dialog->write("<tr><td colspan=".($num_cols - 1)." class=\"fieldprompt\">" .
                         $fee_name.":</td><td align=\"right\">" .
                         amount_format($order,$fee_amount)."</td></tr>\n");
       $discount_name = get_row_value($order->info,'discount_name');
       $discount_amount = get_row_value($order->info,'discount_amount');
       if ((! empty($discount_name)) || $discount_amount) {
          if (! $discount_name) $discount_name = 'Discount';
          $dialog->write("<tr><td colspan=".($num_cols - 1)." class=\"fieldprompt\">" .
                         $discount_name.":</td><td align=\"right\">" .
                         '-'.amount_format($order,$discount_amount)."</td></tr>\n");
       }
       $dialog->write('<tr><td colspan='.($num_cols - 1) .
                      " class=\"fieldprompt\">Total:</td><td align=\"right\">" .
                      amount_format($order,get_row_value($order->info,'total'))."</td></tr>\n");
       $balance_due = get_row_value($order->info,'balance_due');
       if ($balance_due)
          $dialog->write('<tr><td colspan='.($num_cols - 1) .
                         " class=\"fieldprompt\">Balance Due:</td>" .
                         "<td align=\"right\">".amount_format($order,$balance_due) .
                         "</td></tr>\n");
       $dialog->write("<tr height=\"10px\"><td colspan=".$num_cols .
                      "></td></tr>\n");
    }
    else $num_cols = 2;

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<tr class=\"bottom_buttons\"><td colspan=".$num_cols .
                      " align=\"center\">\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr>");
       $dialog->write("  <td style=\"padding-right: 10px;\">\n");
    }
    if ($enable_vendors) {
       $dialog->add_dialog_button('Send To Vendors','images/Update.png',
                                  'send_order_to_vendors('.$id.'); return false;');
       if (! $dialog->skin)
          $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    }
    $dialog->add_dialog_button('Print','images/Update.png',
                               'window.print(); return false;');
    if (! $dialog->skin)
       $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    $dialog->add_dialog_button('Close','images/Update.png',
                               'top.close_current_dialog(); return false;');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr></table>\n</td></tr>\n");
    $dialog->write("</table>\n");
    $dialog->end_content_area(true);
    $dialog->end_body();
    log_activity('Viewed '.$order_label.' #'.$id);
}

function display_order_fields($dialog,$edit_type,$order,$db,$num_products)
{
    global $order_type,$orders_table,$order_status_table,$order_label;
    global $name_prompt,$available_card_types,$available_payment_methods;
    global $include_purchase_order,$enable_wholesale;
    global $enable_sales_reps,$enable_edit_order_source,$default_currency;
    global $shipping_title,$shipto_title,$comments_title,$login_cookie;
    global $enable_rmas,$admin_use_saved_cards,$enable_partial_shipments;
    global $shipped_option,$backorder_option,$cancelled_option;
    global $disable_partial_ship_option,$multiple_customer_accounts;
    global $account_label,$account_product_prices,$enable_reorders;
    global $order_source_table_id,$edit_order_item_flags;
    global $new_order_add_custom_product,$part_number_prompt;
    global $new_order_add_new_product,$file_url,$enable_order_terms;
    global $show_cost_column,$item_fields;

    if (! empty($enable_reorders)) require_once '../admin/reorders-admin.php';

    set_order_website_id($order->info,$edit_type);
    if (! isset($order->db)) $order->db = $db;
    if (function_exists('custom_start_display_order_fields'))
       custom_start_display_order_fields($dialog,$edit_type,$order,$db,
                                         $num_products);
    if (! isset($name_prompt)) $name_prompt = 'Product Name';
    if (! isset($part_number_prompt)) $part_number_prompt = 'Part #';
    if (! isset($file_url)) $file_url = '';
    $payment_module = call_payment_event('get_primary_module',array($db),
                                         true,true);
    if (! isset($available_payment_methods)) {
       $available_payment_methods = array();
       if ($payment_module) {
          $available_payment_methods['cc'] = 'Credit Card (Online)';
          $available_payment_methods['Credit Card'] = 'Credit Card (Offline)';
       }
       else $available_payment_methods['Credit Card'] = 'Credit Card';
       $available_payment_methods['Cash'] = 'Cash';
       $available_payment_methods['Check'] = 'Check';
    }
    else if ((! $payment_module) && isset($available_payment_methods['cc']))
       unset($available_payment_methods['cc']);
    if (! isset($enable_wholesale)) $enable_wholesale = false;
    if (! isset($enable_sales_reps)) $enable_sales_reps = false;
    if (! isset($enable_edit_order_source)) $enable_edit_order_source = false;
    if (! isset($shipping_title)) $shipping_title = 'Shipping';
    if (! isset($shipto_title)) $shipto_title = 'Ship To';
    if (! isset($comments_title)) $comments_title = 'Comments';
    if (! isset($shipped_option)) $shipped_option = 1;
    if (! isset($backorder_option)) $backorder_option = 2;
    if (! isset($cancelled_option)) $cancelled_option = 3;
    if (! isset($admin_use_saved_cards)) $admin_use_saved_cards = false;
    if (! isset($edit_order_item_flags)) $edit_order_item_flags = 0;
    if (! isset($new_order_add_custom_product))
       $new_order_add_custom_product = true;
    if (! isset($new_order_add_new_product))
       $new_order_add_new_product = false;

    $features = get_cart_config_value('features',$db);
    $notify_flags = get_cart_config_value('notifications',$db);
    if (! $notify_flags) $notify_flags = 0;
    if (isset($show_cost_column)) $show_cost = $show_cost_column;
    else if ($features & (PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY))
       $show_cost = true;
    else $show_cost = false;
    $tax_shipping = taxable_shipping($order);

    $dialog->write("<script type=\"text/javascript\">\n" .
                   '  order_type = '.$order_type.";\n" .
                   '  features = '.$features.";\n" .
                   '  notify_flags = '.$notify_flags.";\n" .
                   '  shipped_option = '.$shipped_option.";\n" .
                   '  backorder_option = '.$backorder_option.";\n" .
                   '  cancelled_option = '.$cancelled_option.";\n");
    if ($multiple_customer_accounts)
       $dialog->write("  multiple_customer_accounts = true;\n");
    if ($account_product_prices) {
       $dialog->write('  account_product_prices = ');
       if ($account_product_prices === 'both') $dialog->write("'both';\n");
       else $dialog->write("true;\n");
    }
    if ($admin_use_saved_cards)
       $dialog->write("  admin_use_saved_cards = true;\n");
    if (isset($show_cost_column) && (! $show_cost_column))
       $dialog->write("  show_cost_column = false;\n");
    $dialog->write('  edit_order_item_flags = '.$edit_order_item_flags.";\n");
    $dialog->write('  file_url = \''.$file_url."';\n");
    if ($tax_shipping) $dialog->write("  tax_shipping = true;\n");
    $dialog->write("</script>\n");
    $status_values = load_cart_options($order_status_table,$db);
    if ($features & USE_PART_NUMBERS) load_order_part_numbers($order);
    $customer_id = get_row_value($order->info,'customer_id');
    if ($edit_type == UPDATERECORD) {
       $order_id = get_row_value($order->info,'id');
       $payments = load_order_payments($order);
       $num_payments = count($payments);
       $shipments = load_order_shipments($order);
       $num_shipments = count($shipments);
       if (! isset($enable_rmas)) $enable_rmas = false;
       if ($enable_rmas)
          $rma_items = load_rma_info($db,$order_id,$rma_status_values,
                                     $rma_reason_values);
       if ($admin_use_saved_cards) {
          $saved_cards = array();
          foreach ($payments as $payment_info) {
             if ($payment_info['saved_card_id'])
                $saved_cards[] = $payment_info['saved_card_id'];
          }
          if ($customer_id) {
             $query = 'select id from saved_cards where parent=?';
             $query = $db->prepare_query($query,$customer_id);
             $saved_card_ids = $db->get_records($query,null,'id');
             if ($saved_card_ids) foreach ($saved_card_ids as $card_id) {
                if (! in_array($card_id,$saved_cards))
                   $saved_cards[] = $card_id;
             }
          }
          if (count($saved_cards) > 0) {
             $query = 'select * from saved_cards where id in (?)';
             $query = $db->prepare_query($query,$saved_cards);
             $saved_cards = $db->get_records($query,'id');
             if ($saved_cards) foreach ($saved_cards as $saved_card) {
                $value = 'saved-'.$saved_card['id'].'|' .
                         $saved_card['profile_id'];
                $label = 'Saved: '.$saved_card['card_number'].' (Exp ' .
                   $saved_card['card_month'].'/'.$saved_card['card_year'].')';
                $available_payment_methods[$value] = $label;
             }
          }
       }
       if ($customer_id) {
          $query = 'select id from customers where id=?';
          $query = $db->prepare_query($query,$customer_id);
          if (! $db->get_record($query))
             $customer_name = 'Deleted Customer #'.$customer_id;
          else $customer_name = '<a href="" onClick="top.get_content_frame().' .
                  'edit_customer('.$customer_id.'); return false;">' .
                  'Customer #'.$customer_id.'</a>';
       }
       else $customer_name = 'Guest Customer';
    }
    else {
       $num_payments = 0;   $num_shipments = 0;   $order_id = null;
       $customer_name = '';
    }
    $dialog->add_hidden_field('ordertype',$order_type);
    $dialog->add_hidden_field('NumPayments',$num_payments);
    $dialog->add_hidden_field('NumShipments',$num_shipments);
    $dialog->add_hidden_field('SkipBillValidate','');
    $dialog->add_hidden_field('SkipShipValidate','');
    $dialog->add_hidden_field('UseOwnAddress','');

    $dialog->set_field_padding(1);
    $status = get_row_value($order->info,'status');
    $dialog->add_hidden_field('NumIds',$num_products);
    $dialog->add_hidden_field('customer_id',$customer_id);
    $account_id = 0;
    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field('id',$order_id);
       $dialog->add_hidden_field('OldStatus',$status);
       $registry_id = get_row_value($order->info,'registry_id');
       if ($registry_id)
          $dialog->add_hidden_field('registry_id',$registry_id);
       $gift_message = get_row_value($order->info,'gift_message');
       if ($gift_message)
          $dialog->add_hidden_field('gift_message',$gift_message);
       $order_flags = get_row_value($order->info,'order_flags');
       if ($order_flags)
          $dialog->add_hidden_field('order_flags',$order_flags);
       if ($enable_wholesale && $customer_id) {
          $query = 'select account_id from customers where id=?';
          $query = $db->prepare_query($query,$customer_id);
          $customer_row = $db->get_record($query);
          if ((! $customer_row) && isset($db->error)) {
             process_error('Database Error: '.$db->error,0,true);
             return;
          }
          $account_id = get_row_value($customer_row,'account_id');
       }
    }
    $dialog->write("<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" " .
                   "width=\"850\">\n");

    $max_length = 0;
    foreach ($status_values as $status_label) {
       if (strlen($status_label) > $max_length)
          $max_length = strlen($status_label);
    }
    $div_width = round($max_length * 7.5) + 240;
    $dialog->write("<tr><td nowrap>\n");
    $dialog->write("<div class=\"add_edit_order_box status_box\"");
    if ($div_width > 390)
       $dialog->write(" style=\"width: ".$div_width."px;\"");
    $dialog->write(">\n");
    $dialog->start_field_table('status_table');
    $dialog->start_row('Status:','middle');
    $dialog->start_choicelist('status','select_order_status(this);');
    foreach ($status_values as $index => $status_label)
       $dialog->add_list_item($index,$status_label,$status == $index);
    $dialog->end_choicelist();
    if ($order_type == ORDER_TYPE)
       $dialog->add_checkbox_field('send_emails',
                                   'Send E-Mail Notifications',false);
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    if ($multiple_customer_accounts) {
       if (! isset($account_label)) $account_label = 'Account';
       if ($edit_type == UPDATERECORD) {
          load_order_accounts($order);
          if (isset($order->items) && is_array($order->items)) {
             $last_item = end($order->items);
             $dialog->add_hidden_field('current_account',
                                       $last_item['account_id']);
          }
       }
       $dialog->write("<div id=\"account_div\" class=\"add_edit_order_box account_box\" " .
                      "style=\"display: none;\">\n");
       $dialog->start_field_table('account_table');
       $dialog->start_row($account_label.':','middle');
       $dialog->start_choicelist('account');
       $dialog->end_choicelist();
       $dialog->end_row();
       $dialog->write('</table>');
       $dialog->write("</div>\n");
    }

    $dialog->end_row();

    $dialog->write("<tr><td><table border=\"0\" cellspacing=\"0\" " .
                   "cellpadding=\"0\" width=\"850\">\n");
    $dialog->write("<tr valign=\"top\"><td align=\"left\">");

    $dialog->write("<div class=\"add_edit_order_box add_edit_order_customer_box\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">Billing Information</div>\n");
    $dialog->start_field_table('billing_table');
    $billing_country = get_row_value($order->billing,'country');
    if ($billing_country === '') $billing_country = 1;
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('billing_id',get_row_value($order->billing,'id'));
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('country','select_country(this,\'\');',
                              'select add_edit_order_country');
    load_country_list($billing_country,true);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('First Name:');
    $dialog->add_input_field('fname',$order->info,30,'update_card_name();');
    $dialog->end_row();
    $dialog->add_edit_row('Middle Name:','mname',$order->info,30);
    $dialog->start_row('Last Name:');
    $dialog->add_input_field('lname',$order->info,30,'update_card_name();');
    $dialog->end_row();
    $dialog->add_edit_row('Company:','company',$order->info,30);
    $dialog->add_edit_row('Address Line 1:','address1',$order->billing,30);
    $dialog->add_edit_row('Address Line 2:','address2',$order->billing,30);
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" " .
                   "id=\"city_prompt\" nowrap>");
    if ($billing_country == 29) $dialog->write('Parish');
    else $dialog->write('City');
    $dialog->write(":</td>\n<td>");
    $dialog->add_input_field('city',$order->billing,30);
    $dialog->end_row();
    $billing_state = get_row_value($order->billing,'state');
    $dialog->start_hidden_row('State:','state_row',($billing_country != 1),
                              'middle');
    $dialog->start_choicelist('state',null,'select add_edit_order_state');
    $dialog->add_list_item('','',false);
    load_state_list($billing_state,true,$db);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','province_row',
       (($billing_country == 1) || ($billing_country == 29) ||
        ($billing_country == 43)));
    $dialog->add_input_field('province',$billing_state,20);
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','canada_province_row',
                              ($billing_country != 43),'middle');
    $dialog->start_choicelist('canada_province',null,
                              'select add_edit_order_state');
    $dialog->add_list_item('','',false);
    load_canada_province_list($billing_state);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"zip_cell\">");
    if ($billing_country == 1) $dialog->write('Zip Code:');
    else $dialog->write('Postal Code:');
    $dialog->write("</td><td>\n");
    $dialog->add_input_field('zipcode',$order->billing,30);
    $dialog->end_row();
    $dialog->add_edit_row('E-Mail Address:','email',$order->info,30);
    $dialog->add_edit_row('Telephone:','phone',$order->billing,30);
    $dialog->add_edit_row('Fax:','fax',$order->billing,30);
    $dialog->add_edit_row('Mobile:','mobile',$order->billing,30);
    $dialog->add_hidden_field('account_id',$account_id);
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    $dialog->write("</td><td align=\"center\">\n");
    $dialog->write('<div id="customer_name_div" class="customer_name_div">' .
                   $customer_name.'</div>'."\n");
    $dialog->write("<div class=\"add_edit_order_customer_buttons\">\n");
    $dialog->add_oval_button('>> Copy >>','copy_customer_info();',120);
    $dialog->write("<br><br><br>\n");
    $dialog->add_oval_button('Find Customer','find_customer();',120);
    $dialog->write("<br><br><br>\n");
    $dialog->add_oval_button('New Customer','new_customer();',120);
    $dialog->write("</div>\n");
    $dialog->write("</td><td align=\"right\">\n");

    $dialog->write("<div class=\"add_edit_order_box add_edit_order_customer_box\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">" .
                   $shipping_title." Information</div>\n");
    $dialog->start_field_table('shipping_table');
    if (($edit_type == UPDATERECORD) && $customer_id) {
       $current_profile = get_row_value($order->shipping,'profilename');
       $query = 'select profilename,default_flag from shipping_information ' .
                'where parent=? order by profilename';
       $query = $db->prepare_query($query,$customer_id);
       $profiles = $db->get_records($query);
       if (count($profiles) > 1) $hide_profile = false;
       else $hide_profile = true;
       $found_current = false;   $default_profile = null;
       foreach ($profiles as $profile_info) {
          if ($profile_info['profilename'] == $current_profile)
             $found_current = true;
          else if ($profile_info['default_flag'])
             $default_profile = $profile_info['profilename'];
       }
       if ((! $found_current) && $default_profile)
          $current_profile = $default_profile;
    }
    else {
       $hide_profile = true;   $profiles = null;
       $current_profile = 'Default';
    }
    $dialog->start_hidden_row('Profile:','ship_profile_row',$hide_profile,
                              'middle');
    $dialog->start_choicelist('ship_profilename',
                              'select_shipping_profile(this);');
    if ($profiles) foreach ($profiles as $profile_info) {
       $name = $profile_info['profilename'];
       if (! $name) $name = 'Default';
       $dialog->add_list_item($name,$name,($name == $current_profile));
    }
    else $dialog->add_list_item('Default','Default',
                                ($current_profile == 'Default'));
    $dialog->end_choicelist();
    $dialog->end_row();
    $ship_country = get_row_value($order->shipping,'country');
    if ($ship_country === '') $ship_country = 1;
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('shipping_id',get_row_value($order->shipping,'id'));
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('ship_country','select_shipping_country(this);',
                              'select add_edit_order_country');
    load_country_list($ship_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row($shipto_title.':','shipto',$order->shipping,30);
    $dialog->add_edit_row('Company:','ship_company',
                          get_row_value($order->shipping,'company'),30);
    $dialog->add_edit_row('Address Line 1:','ship_address1',
                          get_row_value($order->shipping,'address1'),30);
    $dialog->add_edit_row('Address Line 2:','ship_address2',
                          get_row_value($order->shipping,'address2'),30);
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" " .
                   "id=\"ship_city_prompt\" nowrap>");
    if ($ship_country == 29) $dialog->write('Parish');
    else $dialog->write('City');
    $dialog->write(":</td>\n<td>");
    $dialog->add_input_field('ship_city',get_row_value($order->shipping,'city'),
                             30);
    $dialog->end_row();
    $ship_state = get_row_value($order->shipping,'state');
    $dialog->start_hidden_row('State:','ship_state_row',($ship_country != 1),
                              'middle');
    $dialog->start_choicelist('ship_state','select_shipping_state(this);',
                              'select add_edit_order_state');
    $dialog->add_list_item('','',false);
    load_state_list($ship_state,true,$db);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','ship_province_row',
       (($ship_country == 1) || ($ship_country == 29) || ($ship_country == 43)));
    $dialog->add_input_field('ship_province',$ship_state,20);
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','ship_canada_province_row',
                              ($ship_country != 43),'middle');
    $dialog->start_choicelist('ship_canada_province',
                              'select_shipping_state(this);',
                              'select add_edit_order_state');
    $dialog->add_list_item('','',false);
    load_canada_province_list($ship_state);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"ship_zip_cell\">");
    if ($ship_country == 1) $dialog->write('Zip Code:');
    else $dialog->write('Postal Code:');
    $dialog->write("</td><td>\n");
    $dialog->add_input_field('ship_zipcode',
                             get_row_value($order->shipping,'zipcode'),30,
                             'shipping_zip_onblur(this);');
    $dialog->end_row();
    $address_type = get_address_type($order);
    $dialog->start_row('Address Type:');
    $dialog->add_radio_field('address_type','1','Business',$address_type == 1,null);
    $dialog->write('&nbsp;&nbsp;');
    $dialog->add_radio_field('address_type','2','Residential',$address_type == 2,null);
    $dialog->end_row();
    if (function_exists('display_custom_order_shipping_fields'))
       display_custom_order_shipping_fields($dialog,$edit_type,$order);
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    $dialog->write("<div id=\"orders_div\" class=\"add_edit_order_box\" " .
                   "style=\"margin-top: 20px; display: none; width: 320px;\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">");
    if ($edit_type == UPDATERECORD) $dialog->write('Other Orders');
    else $dialog->write('Previous Orders');
    $dialog->write("</div>\n<div class=\"customer_orders_div\">");
    $dialog->write("<table id=\"orders_table\" class=\"add_edit_order_product_table fieldtable\" " .
                   "border=\"0\" cellpadding=\"4\" cellspacing=\"0\" width=\"95%\">\n");
    $dialog->write("<tr><th class=\"fieldprompt\" style=\"text-align: left;\">Number</th>" .
                   "<th class=\"fieldprompt\">Status</th>" .
                   "<th class=\"fieldprompt\" style=\"text-align: right;\">Total</th>" .
                   "<th class=\"fieldprompt\">Received</th></tr>\n");
    $dialog->write("</table>\n</div>");
    $dialog->write("</div>\n");

    $dialog->write("</td></tr></table></td></tr>\n");

    $dialog->write("<tr><td>");
    $dialog->write("<div class=\"add_edit_order_box\" style=\"width: 838px;\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">Items</div>\n");
    $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" " .
                   "id=\"add_edit_order_product_table\" class=\"add_edit_order_product_table " .
                   "fieldtable\" width=\"825\">\n");
    $name_width = 515;
    if ($multiple_customer_accounts) $name_width -= 75;
    if ($features & USE_PART_NUMBERS) $name_width -= 75;
    if (isset($item_fields))
       foreach ($item_fields as $field) $name_width -= $field['width'];
    $dialog->write('<tr>');
    write_item_field_header($dialog,$edit_type,'name');
    $dialog->write('<th class="fieldprompt" width="'.$name_width .
                   '" style="text-align: left;" nowrap>'.$name_prompt .
                   "</th>\n");
    write_item_field_header($dialog,$edit_type,'account');
    if ($multiple_customer_accounts)
       $dialog->write('<th class="fieldprompt" width="75px" ' .
                      'style="text-align: left;">'.$account_label."</th>\n");
    write_item_field_header($dialog,$edit_type,'part_number');
    if ($features & USE_PART_NUMBERS)
       $dialog->write('<th class="fieldprompt" width="75px">' .
                      $part_number_prompt."</th>\n");
    write_item_field_header($dialog,$edit_type,'cost');
    if ($show_cost)
       $dialog->write('<th class="fieldprompt" width="75" ' .
                      'style="text-align: right;">Cost'."</th>\n");
    write_item_field_header($dialog,$edit_type,'price');
    $dialog->write('<th class="fieldprompt" width="75" ' .
                   'style="text-align: right;" nowrap>Unit Price'."</th>\n");
    write_item_field_header($dialog,$edit_type,'qty');
    $dialog->write('<th class="fieldprompt" width="30">Qty'."</th>\n");
    write_item_field_header($dialog,$edit_type,'total');
    $dialog->write('<th class="fieldprompt" width="75" ' .
                   'style="text-align: right;">Total'."</th>\n");
    write_item_field_header($dialog,$edit_type,'');
    $dialog->write('<th width="50"></th></tr>'."\n");

    if (($edit_type == UPDATERECORD) && $order->items) {
       load_order_item_product_info($order);
       $index = 0;
       foreach ($order->items as $item_id => $order_item) {
          if ($order_item['product_id']) $custom_product = false;
          else $custom_product = true;
          $dialog->write("<tr valign=\"top\" id=\"product_row_".$index."\">\n");

          write_item_field($dialog,$edit_type,$order,$order_item,$index,'name');
          $dialog->write("<td class=\"left_product_cell\" id=\"product_name_cell_" .
                         $index."\">");
          $dialog->write("<input type=\"hidden\"\n name=\"item_id_".$index .
                         "\" id=\"item_id_".$index."\" value=\"".$item_id."\">");
          if ($custom_product) {
             $dialog->write("<input type=\"text\"\n"." class=\"text\" style=\"" .
                            "border: 0px; width: 100%;\" name=\"product_name_".$index .
                            "\" id=\"product_name_".$index."\" " .
                            "value=\"");
             write_form_value($order_item['product_name']);
             $dialog->write("\">");
             $dialog->write("<input type=\"hidden\"\n name=\"product_custom_attrs_" .
                            $index."\" id=\"product_custom_attrs_".$index .
                            "\" value=\"\">");
          }
          else {
             $dialog->write("<input type=\"hidden\"\n name=\"product_id_".$index .
                            "\" id=\"product_id_".$index .
                            "\" value=\"".$order_item['product_id']."\">");
             if (($edit_order_item_flags & EDIT_ORDER_ITEM_NAME) &&
                 (! ($order_item['product_flags'] & HIDE_NAME_IN_ORDERS)))
                $dialog->write("<input type=\"text\"\n"." class=\"text\" style=\"" .
                               "border: 0px; width: 100%;\" name=\"product_name_".$index .
                               "\" id=\"product_name_".$index."\" " .
                               "value=\"");
             else $dialog->write("<input type=\"hidden\" name=\"product_name_".$index .
                                 "\" value=\"");
             write_form_value($order_item['product_name']);
             $dialog->write("\">");
             if (! ($edit_order_item_flags & EDIT_ORDER_ITEM_NAME))
                $dialog->write("<input type=\"hidden\" name=\"product_price_".$index .
                               "\" id=\"product_price_".$index .
                               "\" value=\"".$order_item['price']."\">");
             $dialog->write("<input type=\"hidden\" name=\"product_attributes_".$index .
                            "\" id=\"product_attributes_".$index .
                            "\" value=\"");
             write_form_value($order_item['attributes']);
             $dialog->write("\"><input type=\"hidden\" name=\"product_attribute_names_" .
                            $index."\" id=\"product_attribute_names_".$index .
                            "\" value=\"");
             write_form_value($order_item['attribute_names']);
             $dialog->write("\"><input type=\"hidden\" name=\"product_attribute_prices_".$index .
                            "\" id=\"product_attribute_prices_".$index .
                            "\" value=\"".$order_item['attribute_prices']."\">" .
                            "<input type=\"hidden\" name=\"item_flags_".$index .
                            "\" id=\"item_flags_".$index."\" value=\"" .
                            $order_item['flags']."\">");
             $dialog->write("<input type=\"hidden\"\n name=\"product_custom_attrs_" .
                            $index."\" id=\"product_custom_attrs_".$index .
                            "\" value=\"\">");
             if (! ($edit_order_item_flags & EDIT_ORDER_ITEM_NAME))
                $dialog->write(get_html_product_name($order_item['product_name'],
                                                     GET_PROD_ADMIN_VIEW_ORDER,
                                                     $order,$order_item));
          }
          if (! empty($enable_reorders))
             add_order_item_reorder_option($db,$dialog,$index,$order_item);
          $dialog->write("</td>\n");

          write_item_field($dialog,$edit_type,$order,$order_item,$index,'account');
          if ($multiple_customer_accounts)
             $dialog->write("<td class=\"account_id_cell\" id=\"" .
                            "account_id_cell_".$index."\"><input type=\"" .
                            "hidden\" name=\"account_id_".$index .
                            "\" id=\"account_id_".$index."\" value=\"" .
                            $order_item['account_id']."\">" .
                            $order_item['account_name']."</td>\n");

          write_item_field($dialog,$edit_type,$order,$order_item,$index,
                           'part_number');
          if ($features & USE_PART_NUMBERS) {
             $dialog->write("<td class=\"part_number_cell\" id=\"" .
                            "part_number_cell_".$index."\" align=\"center\">");
             if ($custom_product || ($edit_order_item_flags & EDIT_ORDER_ITEM_PART_NUMBER)) {
                $dialog->write("<input type=\"text\"\n"." class=\"text\" style=\"" .
                               "border: 0px; width: 100%;\" name=\"part_number_" .
                               $index."\" id=\"part_number_".$index."\" value=\"");
                write_form_value($order_item['part_number']);
                $dialog->write("\">");
             }
             else $dialog->write($order_item['part_number']);
             $dialog->write("</td>\n");
          }

          write_item_field($dialog,$edit_type,$order,$order_item,$index,'cost');
          if ($show_cost) {
             $dialog->write("<td class=\"product_cost_cell\" id=\"product_cost_cell_" .
                            $index."\" align=\"right\">");
             if ($custom_product || ($edit_order_item_flags & EDIT_ORDER_ITEM_COST)) {
                $dialog->write("<input type=\"text\" class=\"text\" style=\"border: 0px; " .
                               "text-align: right;\" name=\"product_cost_".$index .
                               "\" id=\"product_cost_".$index."\" size=\"5\" " .
                               "value=\"");
                write_form_value($order_item['cost']);
                $dialog->write("\">");
             }
             else $dialog->write(amount_format($order,$order_item['cost']));
             $dialog->write("</td>\n");
          }

          write_item_field($dialog,$edit_type,$order,$order_item,$index,'price');
          $dialog->write("<td class=\"product_price_cell\" id=\"product_price_cell_" .
                         $index."\" align=\"right\">");
          if ($custom_product || ($edit_order_item_flags & EDIT_ORDER_ITEM_PRICE)) {
             $dialog->write("<input type=\"text\" class=\"text\" style=\"border: 0px; " .
                            "text-align: right;\" name=\"product_price_".$index .
                            "\" id=\"product_price_".$index."\" onBlur=\"" .
                            "update_line_item_total(".$index.",true,true);\" size=\"5\" " .
                            "value=\"");
             write_form_value($order_item['price']);
             $dialog->write("\">");
          }
          else $dialog->write(amount_format($order,$order_item['price']));
          $dialog->write("</td>\n");

          write_item_field($dialog,$edit_type,$order,$order_item,$index,'qty');
          $dialog->write('<td class="product_qty_cell" align="center">');
          if (($features & REGULAR_PRICE_BREAKS) &&
              ($order_item['price_break_type'] == 1)) {
             $onchange = 'update_line_item_total('.$index.',';
             if ($custom_product) $onchange .= 'true';
             else $onchange .= 'false';
             $onchange .= ',true);';
             $dialog->start_choicelist('product_qty_'.$index,$onchange);
             $price_breaks = explode('|',$order_item['price_breaks']);
             foreach ($price_breaks as $price_break) {
                $break_info = explode('-',$price_break);
                $qty = $break_info[1];
                $dialog->add_list_item($qty,$qty,$qty == $order_item['qty']);
             }
             $dialog->end_choicelist();
          }
          else {
             $dialog->add_hidden_field('old_product_qty_'.$index,
                                       $order_item['qty']);
             $dialog->write('<input type="text" class="text" style="border: ' .
                            '0px; text-align: center;" name="product_qty_' .
                            $index.'" id="product_qty_'.$index.'" onBlur="' .
                            'update_line_item_total('.$index.',');
             if ($custom_product) $dialog->write('true');
             else $dialog->write('false');
             $dialog->write(',true);" value="'.$order_item['qty'].'" size="2">');
          }
          $dialog->write("</td>\n");
          write_item_field($dialog,$edit_type,$order,$order_item,$index,'total');
          $item_total = get_item_total($order_item);
          $dialog->write("<td class=\"product_total_cell\" id=\"product_total_" .
                         $index."\" align=\"right\">".amount_format($order,$item_total) .
                         "</td>\n");

          write_item_field($dialog,$edit_type,$order,$order_item,$index,'');
          $dialog->write("<td class=\"product_delete_cell\" align=\"center\">" .
                         "<a href=\"#\" class=\"perms_link\" " .
                         "onClick=\"delete_line_item(".$index."); " .
                         "return false;\">Delete</a>" .
                         "</td></tr>\n");
          $index++;
       }
    }

    $dialog->write('</table>');
    $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\"><tr><td>\n");
    $dialog->add_oval_button('Find Product','find_product();',140);
    if ($new_order_add_custom_product) {
       $dialog->write("</td><td style=\"padding-left: 20px;\">\n");
       $dialog->add_oval_button('Add Custom Product','add_custom_product();',
                                140);
    }
    if ($new_order_add_new_product) {
       $dialog->write("</td><td style=\"padding-left: 20px;\">\n");
       $dialog->add_oval_button('Add New Product','add_new_product();',140);
    }
    if (($features & REGULAR_PRICE_PRODUCT) && (! $enable_wholesale)) {
       $dialog->write("</td><td style=\"padding-left: 20px;\">\n");
       $dialog->add_oval_button('Update Prices','update_product_prices();',140);
    }
    $dialog->write("</td></tr></table>\n");
    $dialog->write("</div>\n");
    $dialog->write("</td></tr>\n");

    $dialog->write("<tr><td><table border=\"0\" cellspacing=\"0\" " .
                   "cellpadding=\"0\" width=\"850\">\n");
    $dialog->write("<tr valign=\"top\"><td align=\"left\">");

    $payment_total = 0;
    if ($num_payments > 0) {
       $dialog->write("<div class=\"add_edit_order_box\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">" .
                      "Payment Information</div>\n");
       foreach ($payments as $payment_info)
          $payment_total += $payment_info['payment_amount'];
       foreach ($payments as $payment_info) {
          $payment_status = $payment_info['payment_status'];
          $dialog->write("<div class=\"payment_div\">\n");
          $dialog->write("<div class=\"update_payment_div\">");
          if (($payment_status == PAYMENT_AUTHORIZED) &&
              payment_module_event_exists('capture_payment',
                                          $payment_info['payment_type'])) {
             $field_name = 'capture_payment_'.$payment_info['id'];
             $dialog->add_checkbox_field($field_name,'Capture',false);
             $capture_available = true;
          }
          else $capture_available = false;
          $cancel_onclick = null;
          if ($payment_total <= 0.0) $cancel_label = null;
          else if (payment_module_event_exists('cancel_payment',
                      $payment_info['payment_type'])) {
             if ($payment_status == PAYMENT_AUTHORIZED) $cancel_label = 'Void';
             else if ($payment_status == PAYMENT_CAPTURED) {
                $cancel_label = 'Refund';
                $payment_row = 0;
                if (get_row_value($payment_info,'payment_status') !== '')
                   $payment_row++;
                if (get_row_value($payment_info,'payment_method') != '')
                   $payment_row++;
                if (get_row_value($payment_info,'payment_date'))
                   $payment_row++;
                if (get_row_value($payment_info,'payment_user'))
                   $payment_row++;
                if (! get_row_value($payment_info,'payment_amount'))
                   $payment_row = -1;
                if ($payment_row > 0) {
                   if (isset($payment_info['id']))
                      $cancel_suffix = $payment_info['id'];
                   else $cancel_suffix = 'order';
                   $cancel_onclick = "refund_onclick(this,".$payment_row.",'" .
                                     $cancel_suffix."');";
                }
             }
             else $cancel_label = null;
          }
          else $cancel_label = null;
          if ($cancel_label) {
             if ($capture_available) $dialog->write("<br><br>\n");
             if (isset($payment_info['id']))
                $field_name = 'cancel_payment_'.$payment_info['id'];
             else $field_name = 'cancel_payment_order';
             $dialog->add_checkbox_field($field_name,$cancel_label,false,
                                         $cancel_onclick);
             $dialog->write("<br>\n");
          }
          $field_name = 'delete_payment_'.$payment_info['id'];
          $dialog->add_checkbox_field($field_name,'Delete',false);
          $dialog->write("</div>\n");
          if ($cancel_onclick) {
             $dialog->write("<div id=\"refund_amount_div_".$cancel_suffix .
                            "\" class=\"refund_amount_div\" " .
                            "style=\"display:none;\">");
             $dialog->write("<span class=\"fieldprompt\">Refund Amount:" .
                            "</span>\n");
             $field_name = 'refund_amount_'.$cancel_suffix;
             $payment_amount = get_row_value($payment_info,'payment_amount');
             $dialog->add_input_field($field_name,$payment_amount,7);
             $dialog->write("</div>\n");
          }
          $dialog->start_field_table('payment_table');
          display_payment_info($db,$dialog,$order,$payment_info);
          $dialog->end_field_table();
          $dialog->write("</div>\n");
       }
       if ($num_payments > 1) {
          $dialog->start_field_table('total_payments_table');
          $dialog->add_text_row('Total Payments:',
                                amount_format($order,$payment_total));
          $dialog->end_field_table();
       }
       $dialog->write("</div>\n");
    }
    $dialog->add_hidden_field('payment_total',$payment_total);
    $dialog->add_hidden_field('shipping_flags',
                              get_row_value($order->info,'shipping_flags'));
    if ($edit_type == ADDRECORD) {
       if (! isset($default_currency)) $default_currency = 'USD';
       $dialog->add_hidden_field('currency',$default_currency);
    }

    if (($order_type != QUOTE_TYPE) && ($order_type != SALESORDER_TYPE) &&
        (count($available_payment_methods) > 0)) {
       $dialog->write("<div class=\"add_edit_order_box\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">" .
                      "Add Payment</div>\n");
       $dialog->add_hidden_field('NoPayment','');
       $dialog->add_hidden_field('payment_method','');
       $dialog->start_field_table('add_payment_table');
       $dialog->start_row('Payment Method:','middle');
       $dialog->start_choicelist('payment_type','change_payment_type();');
       $dialog->add_list_item('','',true);
       foreach ($available_payment_methods as $method => $label)
          $dialog->add_list_item($method,$label,false);
       $dialog->end_choicelist();
       $dialog->end_row();
       $dialog->add_edit_row('Payment Amount:','payment_amount','',10);
       if (isset($available_payment_methods['cc'])) {
          $dialog->start_hidden_row('Credit Card Type:','cc_row_0',true,
                                    'middle');
          $dialog->start_choicelist('card_type');
          foreach ($available_card_types as $card_type => $card_name)
             $dialog->add_list_item($card_type,$card_name,($card_type=='visa'));
          $dialog->end_choicelist();
          $dialog->end_row();
          $dialog->start_hidden_row('Card Number:','cc_row_1',true);
          if (call_payment_event('write_card_dialog_field',
                                 array(&$dialog,&$order,'card_number'),
                                 true,true)) {}
          else {
             $size = '30" onKeyUp="card_number_keyup(this.value);';
             $dialog->add_input_field('card_number','',$size);
          }
          $dialog->end_row();
          $dialog->start_hidden_row('Expiration Date:','cc_row_3',true,
                                    'middle');
          if (call_payment_event('write_card_dialog_field',
                                 array(&$dialog,&$order,'card_month'),
                                 true,true)) {}
          else {
             $dialog->start_choicelist('card_month');
             for ($month = 1;  $month <= 12;  $month++) {
                $month_string = sprintf('%02d',$month);
                $dialog->add_list_item($month_string,$month_string,false);
             }
             $dialog->end_choicelist();
          }
          if (call_payment_event('write_card_dialog_field',
                                 array(&$dialog,&$order,'card_year'),
                                 true,true)) {}
          else {
             $dialog->start_choicelist('card_year');
             $start_year = date('y');
             for ($year = $start_year;  $year < $start_year + 20;  $year++) {
                $year_string = sprintf('%02d',$year);
                $dialog->add_list_item($year_string,'20'.$year_string,false);
             }
             $dialog->end_choicelist();
          }
          $dialog->end_row();
          $dialog->start_hidden_row('CVV Number:','cc_row_2',true);
          if (call_payment_event('write_card_dialog_field',
                                 array(&$dialog,&$order,'card_cvv'),
                                 true,true)) {}
          else $dialog->add_input_field('card_cvv','',10);
          $dialog->end_row();
          $dialog->start_hidden_row('Name on Card:','cc_row_4',true);
          if ($edit_type == UPDATERECORD)
             $card_name = get_row_value($order->info,'fname').' ' .
                          get_row_value($order->info,'lname');
          else $card_name = '';
          if (call_payment_event('write_card_dialog_field',
                                 array(&$dialog,&$order,'card_name'),
                                 true,true)) {}
          else $dialog->add_input_field('card_name',$card_name,30);
          $dialog->end_row();
          if ($admin_use_saved_cards) {
             $dialog->start_hidden_row('Save Credit Card:','cc_row_5',true,
                                       'middle');
             $dialog->add_checkbox_field('SaveCard',null,false);
             $dialog->end_row();
          }
       }
       if (isset($available_payment_methods['Check'])) {
          $dialog->start_hidden_row('Check Number:','check_row_0',true);
          $dialog->add_input_field('check_number','',10);
          $dialog->end_row();
       }
       $dialog->end_field_table();
       $dialog->write("</div>\n");
    }

    if (($order_type == ORDER_TYPE) && ($num_shipments == 0)) {
       if ($edit_type == UPDATERECORD) {
          $dialog->write('<div id="add_shipment_button_div">');
          $dialog->add_oval_button('Add Shipment','add_shipment();',155);
          $dialog->write("</div>");
          $dialog->add_hidden_field('NewShipment','');
       }
       $dialog->write("<div class=\"add_edit_order_box\" style=\"" .
                      "display:none;\" id=\"add_shipment_div\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">" .
                      "Add Shipment</div>\n");
       $dialog->write('<div id="new_shipment_div" class="shipment_div"'.">\n");
       $dialog->start_field_table('shipment_table_new');
       $dialog->end_field_table();
       $dialog->write("</div></div>\n");
    }

    if (($edit_type == UPDATERECORD) && $enable_rmas &&
       (count($rma_items) > 0)) {
       $dialog->write("<div class=\"add_edit_order_box add_edit_order_rma_box\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">" .
                      "RMAs</div>\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" class=\"" .
                      "fieldtable add_edit_order_rma_table\" width=\"100%\">\n");
       $dialog->write("<tr><th class=\"fieldprompt\" width=\"40\" nowrap>RMA #</th>");
       $dialog->write("<th class=\"fieldprompt\" width=\"75\">Status</th>\n");
       $dialog->write("<th class=\"fieldprompt\" style=\"text-align: " .
                      "left;\" nowrap>Reason</th></tr>\n");
       foreach ($rma_items as $rma_item) {
          $rma_status = $rma_status_values[$rma_item['status']];
          $rma_reason = $rma_item['reason'];
          if (isset($rma_reason_values[$rma_reason]))
             $rma_reason = $rma_reason_values[$rma_reason];
          $dialog->write('<tr valign="top"><td align="center">');
          $dialog->write('<a href="#" onClick="return view_rma(' .
                         $rma_item['id'].');">'.$rma_item['id']."</a></td>\n");
          $dialog->write('<td align="center">'.$rma_status."</td>\n");
          $dialog->write('<td>'.$rma_reason."</td></tr>\n");
       }
       $dialog->end_field_table();
       $dialog->write("</div>\n");
    }

    if (! empty($enable_order_terms)) {
       $dialog->write("<div class=\"add_edit_order_box\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">Terms</div>\n");
       if ($edit_type == UPDATERECORD) $frame = 'edit_order';
       else $frame = 'add_order';
       $button_code = 'top.get_dialog_frame(\\\''.$frame .
                      '\\\').contentWindow.insert_terms_list(document,window,' .
                      '\\\''.$frame.'\\\');';
       $dialog->add_htmleditor_popup_field('terms',$order->info,
          'Terms',378,140,null,null,null,false,'catalogtemplates.xml',
          $button_code);
       $dialog->write("</div>\n");
    }

    $dialog->write("</td><td align=\"right\">\n");

    if ($edit_type == UPDATERECORD) {
       $dialog->write("<div class=\"add_edit_order_box\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">".$order_label .
                      " Information</div>\n");
       if ($num_shipments > 0) $table_name = 'shipped_order_info_table';
       else $table_name = 'order_info_table';
       $dialog->start_field_table($table_name);
       if ($order_type == ORDER_TYPE) {
          $order_number = get_row_value($order->info,'order_number');
          $dialog->add_text_row($order_label.' Number:',$order_number);
          $dialog->add_hidden_field('order_number',$order_number);
       }
       else $dialog->add_text_row($order_label.' Number:',$order->id);
       if (isset($order->info['reorder'])) {
          $reorder_num = get_row_value($order->info,'reorder');
          $reorder_info = "<a href=\"\" onClick=\"top.get_content_frame().view_reorder(" .
                          $reorder_num."); return false;\">".$reorder_num."</a>";
          $dialog->add_text_row('Reorder of:',$reorder_info);
          $dialog->add_hidden_field('reorder',$reorder_num);
       }
       add_order_website_info($db,$dialog,$order->info,UPDATERECORD);
       if ($enable_edit_order_source) {
          $order_sources = load_cart_options($order_source_table_id,$db);
          if (count($order_sources) == 0)
             $dialog->add_edit_row('Order Source:','external_source',
                                   $order->info,35);
          else {
             $external_source = get_row_value($order->info,'external_source');
             $dialog->start_row('Order Source:','middle');
             $dialog->start_choicelist('external_source','select_order_source();');
             $source_found = false;
             foreach ($order_sources as $source_id => $label) {
                if ($external_source && ($external_source == $label)) {
                   $selected = true;   $source_found = true;
                }
                else if ((! $external_source) && ($source_id == 0)) {
                   $selected = true;   $source_found = true;
                }
                else $selected = false;
                if ($source_id == 0) $source_value = null;
                else $source_value = $label;
                $dialog->add_list_item($source_value,$label,$selected);
             }
             if ($external_source && (! $source_found)) $selected = true;
             else $selected = false;
             $dialog->add_list_item('custom','Custom:',$selected);
             $dialog->add_list_item('new','Add New Source',false);
             $dialog->end_choicelist();
             $dialog->write('<span id="custom_source_span"');
             if (! $selected) $dialog->write(' style="display: none;"');
             $dialog->write('>');
             if ($selected) $custom_source = $external_source;
             else $custom_source = '';
             $dialog->add_input_field('custom_source',$custom_source,15);
             $dialog->write('</span>');
             $dialog->end_row();
          }
          $dialog->add_edit_row('External Order #:','external_id',
                                $order->info,35);
       }
       else if (isset($order->info['external_id']) &&
                $order->info['external_id']) {
          $external_source = get_row_value($order->info,'external_source');
          $dialog->add_text_row($external_source.' Order #:',
                             get_row_value($order->info,'external_id'));
       }
       $phone_order = get_row_value($order->info,'phone_order');
       $dialog->start_row('Phone '.$order_label.':','middle');
       $dialog->add_checkbox_field('phone_order','',$phone_order);
       $dialog->end_row();
       $currency = get_row_value($order->info,'currency');
       if ($currency != 'USD') $dialog->add_text_row('Currency:',$currency);
       $dialog->add_hidden_field('currency',$currency);
       $shipping_carrier = get_row_value($order->info,'shipping_carrier');
       $shipping_method = get_row_value($order->info,'shipping_method');
       $shipping = get_row_value($order->info,'shipping');
       if ($num_shipments > 0) {
          $dialog->add_hidden_field('shipping_carrier',$shipping_carrier);
          $dialog->add_hidden_field('shipping_method',$shipping_method);
          if ($shipping_carrier && 
              shipping_module_event_exists('display_shipping_info',
                                           $shipping_carrier)) {
             $display_shipping_info = $shipping_carrier.'_display_shipping_info';
             $display_shipping_info($dialog,$order);
          }
          else if ($shipping_carrier) {
             $dialog->add_text_row('Shipping Carrier:',$shipping_carrier);
             if ($shipping_method)
                $dialog->add_text_row('Shipping Method:',$shipping_method);
          }
          if ($shipping) $dialog->add_text_row('Shipping Cost:',$shipping);
          $dialog->add_hidden_field('shipping',$shipping);
       }
       else {
          $dialog->write("<tr id=\"shipping_carrier_row\" style=\"display:none;\"" .
                         " valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                         "Shipping Carrier:</td><td id=\"shipping_carrier_cell\">\n");
          $dialog->write("<input type=\"text\" class=\"text\" name=\"" .
                         "shipping_carrier\" size=\"25\" value=\"");
          write_form_value($shipping_carrier);
          $dialog->write("\"></td></tr>\n");
          $dialog->write("<tr id=\"shipping_method_row\" style=\"display:none;\"" .
                         " valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                         "Shipping Method:</td><td id=\"shipping_method_cell\">\n");
          $dialog->write("<input type=\"text\" class=\"text\" name=\"" .
                         "shipping_method\" size=\"25\" value=\"");
          write_form_value($shipping_method);
          $dialog->write("\"></td></tr>\n");
          $dialog->start_hidden_row('Shipping Cost:','shipping_cost_row');
          $dialog->write("<input type=\"text\" class=\"text\" name=\"shipping\" " .
                         "size=\"10\" onBlur=\"shipping_amount_onblur(this);\" value=\"");
          write_form_value($shipping);
          $dialog->write("\">\n");
          $dialog->end_row();
       }
       switch ($order_type) {
          case ORDER_TYPE: $date_field = 'order_date';   break;
          case QUOTE_TYPE: $date_field = 'quote_date';   break;
          case INVOICE_TYPE: $date_field = 'invoice_date';   break;
          case SALESORDER_TYPE: $date_field = 'order_date';   break;
       }
       $order_date = get_row_value($order->info,$date_field);
       if ($order_date)
          $dialog->add_text_row($order_label.' Date:',
                                date('F j, Y g:i:s a',$order_date),
                                'bottom',true);
       $dialog->add_hidden_field('order_date',$order_date);
       $updated_date = get_row_value($order->info,'updated_date');
       if ($updated_date)
          $dialog->add_hidden_field('updated_date',$updated_date);
       if ($include_purchase_order)
          $dialog->add_edit_row('Purchase Order:','purchase_order',
                                get_row_value($order->info,'purchase_order'),25);
       if ($enable_partial_shipments && (! $disable_partial_ship_option)) {
          $partial_ship = get_row_value($order->info,'partial_ship','middle');
          $dialog->start_row('Req Partial Ship:');
          $dialog->add_checkbox_field('partial_ship','',$partial_ship);
          $dialog->end_row();
       }
       $weight = get_row_value($order->info,'weight');
       if ($num_shipments > 0) {
          if ($weight)
             $dialog->add_text_row('Shipping Weight:',$weight.' (Lbs)');
          $dialog->add_hidden_field('weight',$weight);
       }
       else if ($order_type == ORDER_TYPE) {
          $dialog->start_hidden_row('Shipping Weight:','shipping_weight_row');
          $dialog->add_input_field('weight',$weight,10);
          $dialog->write(' (Lbs)');
          $dialog->end_row();
       }
       if ($enable_sales_reps) {
          $sales_rep = get_row_value($order->info,'sales_rep');
          $user_list = load_user_list($db,true);
          $dialog->start_row('Sales Rep:','middle');
          $dialog->start_choicelist('sales_rep',null);
          $dialog->add_list_item('','',! $sales_rep);
          display_user_list($dialog,$user_list,$sales_rep);
          $dialog->end_choicelist();
          $dialog->end_row();
       }
       if (function_exists('display_custom_order_fields'))
          display_custom_order_fields($dialog,$edit_type,$order);
       require_once '../engine/modules.php';
       call_module_event('display_custom_fields',
          array($orders_table,$db,&$dialog,$edit_type,$order->info));
       $dialog->end_field_table();
       $dialog->write("</div>\n");
       $templates = get_order_templates($db,false);
       if (! empty($templates)) {
          $dialog->write("<div class=\"update_print_div\">");
          if (count($templates) == 1) {
             $label = reset($templates);   $template = key($templates);
             $onclick = 'update_order(null,\''.$template.'\',\''.$label.'\');';
          }
          else $onclick = 'update_order(\'print\');';
          $dialog->add_oval_button('Update and Print',$onclick,155);
          if (count($templates) > 1) {
             $dialog->write('<div id="print_options" style="display:none;">'."\n");
             $dialog->start_choicelist('print_option','update_order(this);');
             $dialog->add_list_item('','Select',true);
             foreach ($templates as $filename => $template_name)
                $dialog->add_list_item($filename,$template_name,false);
             $dialog->end_choicelist();
             $dialog->write("</div>\n");
          }
          $dialog->write("</div>");
       }
       $dialog->write("</div>\n");
    }
    else {
       $dialog->write("<div class=\"add_edit_order_box\">\n");
       $dialog->write('<div class="add_edit_order_legend">'.$order_label .
                      " Information</div>\n");
       $dialog->set_field_padding(1);
       $dialog->start_field_table('order_info_table');
       add_order_website_info($db,$dialog,$order->info,ADDRECORD);
       if (($order_type == ORDER_TYPE) && ($enable_edit_order_source)) {
          $dialog->add_edit_row('Order Source:','external_source',
                                get_row_value($order->info,'external_source'),35);
          $dialog->add_edit_row('External Order Number:','external_id',
                                get_row_value($order->info,'external_id'),35);
       }
       $phone_order = get_row_value($order->info,'phone_order');
       $dialog->start_row('Phone '.$order_label.':','middle');
       $dialog->add_checkbox_field('phone_order','',$phone_order);
       $dialog->end_row();
       if ($include_purchase_order)
          $dialog->add_edit_row('Purchase Order:','purchase_order',
                                get_row_value($order->info,'purchase_order'),25);
       if ($enable_sales_reps) {
          $admin_user = get_cookie($login_cookie);
          $user_list = load_user_list($db,true);
          $dialog->start_row('Sales Rep:','middle');
          $dialog->start_choicelist('sales_rep',null);
          $dialog->add_list_item('','',false);
          display_user_list($dialog,$user_list,$admin_user);
          $dialog->end_choicelist();
          $dialog->end_row();
       }
       if (function_exists('display_custom_order_fields'))
          display_custom_order_fields($dialog,$edit_type,$order);
       require_once '../engine/modules.php';
       call_module_event('display_custom_fields',
          array($orders_table,$db,&$dialog,$edit_type,$order->info));
       $dialog->end_field_table();
       $dialog->write("</div>\n");
    }

    $dialog->write("<div class=\"add_edit_order_box\">\n");
    $dialog->start_field_table('total_fields_table');
    $subtotal = get_row_value($order->info,'subtotal');
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                   "Sub Total:</td><td align=\"right\" id=\"subtotal\" " .
                   "style=\"width: 80px;\">".amount_format($order,$subtotal) .
                   "</td></tr>\n");
    $dialog->add_hidden_field('subtotal',$subtotal);
    $tax = get_row_value($order->info,'tax');
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                   "Tax:</td><td align=\"right\" " .
                   "id=\"tax_cell\">".amount_format($order,$tax)."</td></tr>\n");
    $dialog->add_hidden_field('tax',$tax);
    $dialog->write("<tr valign=\"bottom\" id=\"shipping_row\" style=\"" .
                   "display:none;\"><td class=\"fieldprompt\" style=\"padding: 0px;\" nowrap " .
                   "colspan=\"2\">Shipping Method: <span id=\"shipping_method_span\">");
    $dialog->write("</span></td></tr>\n");
    if ($edit_type == ADDRECORD) {
       $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                      "Shipping Cost:</td><td align=\"right\">\n");
       $dialog->write("<input type=\"text\" class=\"text\" name=\"shipping\" " .
                      "style=\"width: 55px; text-align: right;\" " .
                      "onBlur=\"shipping_amount_onblur(this);\" value=\"");
       $dialog->write("\">\n</td></tr>\n");
    }
    $dialog->write("<tr><td class=\"fieldprompt\" colspan=\"2\" onClick=\"" .
                   "toggle_adjustments();\" style=\"cursor: pointer; padding: 3px 0px;\">" .
                   "Adjustments&nbsp;<img src=\"images/add-order-down-arrow.gif\" " .
                   "style=\"margin-bottom: -3px;\"></td></tr>\n");
    if ($features & USE_COUPONS) {
       $coupon_id = get_row_value($order->info,'coupon_id');
       if ($coupon_id) {
          $query = 'select coupon_code from coupons where id=?';
          $query = $db->prepare_query($query,$coupon_id);
          $coupon_row = $db->get_record($query);
          if ($coupon_row && ($coupon_row['coupon_code']))
             $coupon_code = $coupon_row['coupon_code'];
          else $coupon_code = '';
       }
       else $coupon_code = '';
       $coupon_amount = get_row_value($order->info,'coupon_amount');
       $dialog->write("<tr valign=\"bottom\" id=\"coupon_row\" style=\"display: " .
                      "none;\"><td class=\"fieldprompt\" nowrap>" .
                      "Promotion Code:&nbsp;<input type=\"hidden\" name=\"coupon_id\" " .
                      "value=\"".$coupon_id."\"><input type=\"hidden\" name=\"coupon_type\" " .
                      "value=\"\"><input type=\"hidden\" name=\"coupon_amount\" " .
                      "value=\"".$coupon_amount."\"><input type=\"text\" class=\"text\" " .
                      "name=\"coupon_code\" style=\"width: 100px;\" onBlur=\"" .
                      "coupon_onblur(this);\" value=\"");
       write_form_value($coupon_code);
       $dialog->write("\"></td><td align=\"right\" id=\"coupon_amount_cell\">");
       if ($coupon_amount) $dialog->write('-'.amount_format($order,$coupon_amount));
       $dialog->write("</td></tr>\n");
    }
    else $dialog->add_hidden_field('coupon_id','');
    if ($features & GIFT_CERTIFICATES) {
       $gift_id = get_row_value($order->info,'gift_id');
       $gift_amount = get_row_value($order->info,'gift_amount');
       $dialog->write("<tr valign=\"bottom\" id=\"gift_row\" style=\"display: " .
                      "none;\"><td class=\"fieldprompt\" nowrap>" .
                      "Gift Certificate:&nbsp;<input type=\"hidden\" name=\"gift_id\" " .
                      "value=\"".$gift_id."\"><input type=\"hidden\" name=\"gift_amount\" " .
                      "value=\"".$gift_amount."\"><input type=\"hidden\" name=\"gift_balance\" " .
                      "value=\"\"><input type=\"text\" class=\"text\" " .
                      "name=\"gift_code\" style=\"width: 100px;\" onBlur=\"gift_onblur(this);\">" .
                      "</td><td align=\"right\" id=\"gift_amount_cell\">");
       if ($gift_amount) $dialog->write('-'.amount_format($order,$gift_amount));
       $dialog->write("</td></tr>\n");
    }
    else $dialog->add_hidden_field('gift_id','');
    $fee_name = get_row_value($order->info,'fee_name');
    $fee_amount = get_row_value($order->info,'fee_amount');
    $dialog->write("<tr valign=\"bottom\" id=\"fee_row\" style=\"display: " .
                   "none;\"><td class=\"fieldprompt\" nowrap>" .
                   "Fee:&nbsp;<input type=\"text\" class=\"text\" " .
                   "name=\"fee_name\" style=\"width: 150px;\" value=\"");
    write_form_value($fee_name);
    $dialog->write("\"></td><td align=\"right\" nowrap>+ <input type=\"text\" class=\"text\" " .
                   "name=\"fee_amount\" style=\"width: 75px; text-align: right;\" " .
                   "onBlur=\"fee_onblur(this);\" value=\"".$fee_amount."\"></td></tr>\n");
    $discount_name = get_row_value($order->info,'discount_name');
    $discount_amount = get_row_value($order->info,'discount_amount');
    $dialog->write("<tr valign=\"bottom\" id=\"discount_row\" style=\"display: " .
                   "none;\"><td class=\"fieldprompt\" nowrap>" .
                   "Discount:&nbsp;<input type=\"text\" class=\"text\" " .
                   "name=\"discount_name\" style=\"width: 150px;\" value=\"");
    write_form_value($discount_name);
    $dialog->write("\"></td><td align=\"right\" nowrap>- <input type=\"text\" class=\"text\" " .
                   "name=\"discount_amount\" style=\"width: 75px; text-align: right;\" " .
                   "onBlur=\"discount_onblur(this);\" value=\"".$discount_amount .
                   "\"></td></tr>\n");
    $total = get_row_value($order->info,'total');
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                   "Total:</td><td align=\"right\" id=\"total\">" .
                   amount_format($order,$total)."</td></tr>\n");
    if (($edit_type == UPDATERECORD) && ($order_type == ORDER_TYPE)) {
       $dialog->add_hidden_field('old_total',$total);
       $balance_due = get_row_value($order->info,'balance_due');
       $dialog->add_hidden_field('old_balance_due',$balance_due);
       $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                      "Balance Due:</td><td align=\"right\"><input type=\"text\" " .
                      "class=\"text\" name=\"new_balance_due\" style=\"width: 75px; " .
                      "text-align: right;\" value=\"".$balance_due."\"></td></tr>\n");
    }
    $dialog->end_field_table();
    if ($order_type == SALESORDER_TYPE) $width = 140;
    else $width = 120;
    $dialog->write("</div>\n<div style=\"width:".($width + 11)."px;\">");
    if ($edit_type == UPDATERECORD)
       $dialog->add_oval_button('Update '.$order_label,
                                'update_order(null);',$width);
    else $dialog->add_oval_button('Create '.$order_label,
                                  'process_add_order();',$width);
    $dialog->write("</div></td></tr></table></td></tr>\n");

    if ($num_shipments > 0) {
       $dialog->write('<tr><td><table border="0" cellspacing="0" cellpadding="0" ' .
                      "width=\"850\">\n");
       $dialog->write("<tr valign=\"top\"><td align=\"left\">");
       $dialog->write("<div class=\"add_edit_order_box\" style=\"width: 838px;\">\n");
       $dialog->write("<div class=\"add_edit_order_legend\">" .
                      "Shipment Information</div>\n");
       $index = 0;
       foreach ($shipments as $shipment_info) {
          $dialog->write("<div class=\"shipment_div\">\n");
          $dialog->write("<div class=\"update_shipment_div\">");
          $field_name = 'delete_shipment_'.$shipment_info['id'];
          $dialog->add_checkbox_field($field_name,'Delete',false);
          $dialog->write("</div>\n");
          $dialog->start_field_table('shipment_table_'.$index);
          display_shipment_info($db,$dialog,$order,$shipment_info,true);
          $dialog->end_field_table();
          $dialog->write("</div>\n");
          $index++;
       }
       $dialog->write("</div></td></tr></table></td></tr>\n");
    }

    $dialog->write("<tr><td><table cellspacing=\"0\" cellpadding=\"0\" " .
                   "width=\"850\" class=\"add_edit_order_comments\">\n");
    $dialog->write("<tr><td width=\"100%\">\n");
    $dialog->write("<div class=\"add_edit_order_box\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">".$comments_title."</div>\n");
    $dialog->start_textarea_field("comments",8,45,WRAP_SOFT);
    write_form_value(get_row_value($order->info,'comments'));
    $dialog->end_textarea_field();
    $dialog->write("</div>\n");
    $dialog->write("</td><td>\n");
    $dialog->write("<div class=\"add_edit_order_box\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">Notes</div>\n");
    $dialog->start_textarea_field('notes',8,45,WRAP_SOFT);
    write_form_value(get_row_value($order->info,'notes'));
    $dialog->end_textarea_field();
    $dialog->write("</div>\n");
    $dialog->write("</td></tr></table></td></tr>\n");
    $dialog->end_field_table();
}

function load_shipping_profiles()
{
    $customer_id = get_form_field('id');
    $db = new DB;
    $query = 'select profilename,default_flag from shipping_information ' .
             'where parent=? order by profilename';
    $query = $db->prepare_query($query,$customer_id);
    $profiles = $db->get_records($query);
    print json_encode($profiles);
}

function load_shipping_profile()
{
    $customer_id = get_form_field('id');
    $profile = get_form_field('Profile');
    $db = new DB;
    $query = 'select * from shipping_information where (parent=?) and ' .
             '(profilename=?)';
    $query = $db->prepare_query($query,$customer_id,$profile);
    $shipping_info = $db->get_record($query);
    if (! $shipping_info) {
       if (isset($db->error))
          http_response(422,'Database Error: '.$db->error);
       else http_response(410,'Shipping Profile not found');
       return;
    }
    print json_encode($shipping_info);
}

function load_order_customer_accounts()
{
    global $account_label;

    if (! isset($account_label)) $account_label = 'Account';
    $customer_id = get_form_field('id');
    $db = new DB;
    $query = 'select a.id,a.name,a.company from accounts a join ' .
             'customer_accounts ca on ca.account_id=a.id where ' .
             'ca.customer_id=? order by a.name';
    $query = $db->prepare_query($query,$customer_id);
    $accounts = $db->get_records($query);
    $no_account = array('id'=>0,'name'=>'No '.$account_label,'company'=>'');
    $accounts = array_merge(array($no_account),$accounts);
    print json_encode($accounts);
}

function load_customer_orders()
{
    global $order_status_table;

    require_once 'cart-public.php';

    $customer_id = get_form_field('id');
    $order = new Order();
    $order->customer_id = $customer_id;
    $orders = $order->load_all();
    if (! $orders) return;
    $skip = get_form_field('skip');
    $status_values = load_cart_options($order_status_table,$order->db);
    foreach ($orders as $id => $order) {
       if ($id == $skip) continue;
       if (empty($order['order_date'])) continue;
       if (! $order['total']) $order['total'] = '0';
       print 'customer_orders['.$id.'] = {';
       print ' id: '.$id.',';
       print " number: '".trim($order['order_number'])."',";
       print " status: '".$status_values[$order['status']]."',";
       print " email: '".trim($order['email'])."',";
       print " total: ".$order['total'].",";
       print " order_date: ".$order['order_date'];
       print " };\n";
    }
}

function load_customer_saved_cards()
{
    $db = new DB;
    $customer_id = get_form_field('id');
    $query = 'select * from saved_cards where parent=?';
    $query = $db->prepare_query($query,$customer_id);
    $saved_cards = $db->get_records($query,'id');
    if (! $saved_cards) return;
    print json_encode($saved_cards);
}

function load_account_info()
{
    global $account_product_prices;

    $id = get_form_field('id');
    $db = new DB;
    $query = 'select * from accounts where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error))
          http_response(422,'Database Error: '.$db->error);
       else http_response(410,'Account not found');
       return;
    }
    print 'account_info = { ';   $first_field = true;
    foreach ($row as $field_name => $field_value) {
       if ($first_field) $first_field = false;
       else print ', ';
       $field_value = str_replace("'","\\'",$field_value);
       print $field_name.":'".$field_value."'";
    }
    print ' };';
    if ($row['discount_rate'])
       print ' account_discount = '.$row['discount_rate'].';';
    $query = 'select a.*,p.price as product_price,p.sale_price from ' .
             'account_products a join products p on p.id=a.related_id ' .
             'where a.parent=?';
    $query = $db->prepare_query($query,$id);
    $products = $db->get_records($query);
    foreach ($products as $product) {
       if ($product['sale_price']) $price = $product['sale_price'];
       else $price = $product['product_price'];
       if (is_numeric($product['price']))
          $account_price = floatval($product['price']);
       else $account_price = null;
       if (is_numeric($product['discount']))
          $discount = floatval($product['discount']);
       else $discount = null;
       if ((! $account_price) && (! $discount)) continue;
       if ($account_product_prices) {
          if ($account_product_prices === 'both') {
             if ($account_price) $price = $account_price;
          }
          else {
             $price = $discount;   $discount = null;
          }
       }
       if ($discount) {
          $factor = (100 - $discount) / 100;
          $price = round($price * $factor,2);
       }
       if (! $price) continue;
       print ' account_products['.$product['related_id'].'] = '.$price.';';
    }
    $query = 'select id from products where flags&'.NO_ACCOUNT_DISCOUNTS;
    $products = $db->get_records($query,null,'id');
    if ($products) {
       print ' no_account_products = [';   $first_product = true;
       foreach ($products as $product_id) {
          if ($first_product) $first_product = false;
          else print ',';
          print $product_id;
       }
       print '];';
    }
}

function update_cart_total(&$cart)
{
    $sub_total = $cart->get('subtotal');
    $total = $sub_total;
    $tax = $cart->get('tax');
    if (($tax === null) || ($tax === '')) $tax = 0;
    $total += $tax;
    $shipping = $cart->get('shipping');
    $total += $shipping;
    $coupon_amount = $cart->get('coupon_amount');
    if (($coupon_amount === null) || ($coupon_amount === '')) $coupon_amount = 0;
    $total -= $coupon_amount;
    if ($cart->features & GIFT_CERTIFICATES) {
       $gift_amount = $cart->get('gift_amount');
       if (($gift_amount === null) || ($gift_amount === '')) $gift_amount = 0;
       $total -= $gift_amount;
    }
    $fee_amount = $cart->get('fee_amount');
    if (($fee_amount === null) || ($fee_amount === '')) $fee_amount = 0;
    $total += $fee_amount;
    $cart->set('total',$total);
}

function parse_new_cart_data($db,&$customer,&$cart,$edit_type,
                             $adding_order=false,$lookup_fees=false)
{
    global $amount_cents_flag,$multiple_customer_accounts,$enable_multisite;

    $customer_id = get_form_field('customer_id');
    if ($customer_id == '') $customer_id = -1;
    $customer = new Customer($db,$customer_id,true);
    $customer->info_changed = false;
    $customer->billing_changed = false;
    $customer->shipping_changed = false;
    $customer->customers_record = customers_record_definition();
    $customer->info = array();
    $customer->billing_record = billing_record_definition();
    $customer->billing = array();
    $customer->shipping_record = shipping_record_definition();
    $customer->shipping = array();
    $customer->info['id'] = $customer_id;
    $account_id = 0;
    if ($customer_id > 0) {
       $query = 'select tax_exempt,account_id from customers where id=?';
       $query = $db->prepare_query($query,$customer_id);
       $row = $db->get_record($query);
       if ($row) {
          $customer->set('cust_tax_exempt',$row['tax_exempt']);
          $account_id = $row['account_id'];
          $customer->set('cust_account_id',$account_id);
       }
    }
    $customer->set('cust_email',get_form_field('email'));
    $customer->set('cust_fname',get_form_field('fname'));
    $customer->set('cust_mname',get_form_field('mname'));
    $customer->set('cust_lname',get_form_field('lname'));
    $customer->set('cust_company',get_form_field('company'));
    $customer->set('bill_address1',get_form_field('address1'));
    $customer->set('bill_address2',get_form_field('address2'));
    $customer->set('bill_city',get_form_field('city'));
    $bill_country = get_form_field('country');
    $customer->set('bill_country',$bill_country);
    $customer->billing_country = $bill_country;
    switch ($bill_country) {
       case 1: $customer->set('bill_state',get_form_field('state'));   break;
       case 29: break;
       case 43: $customer->set('bill_canada_province',
                               get_form_field('canada_province'));
                break;
       default: $customer->set('bill_province',get_form_field('province'));
                break;
    }
    $customer->set('bill_zipcode',get_form_field('zipcode'));
    $customer->set('bill_phone',get_form_field('phone'));
    $customer->set('bill_fax',get_form_field('fax'));
    $customer->set('bill_mobile',get_form_field('mobile'));
    $profile_name = get_form_field('ship_profilename');
    if (! $profile_name) $profile_name = 'Default';
    $customer->set('ship_profilename',$profile_name);
    $customer->set('ship_shipto',get_form_field('shipto'));
    $customer->set('ship_company',get_form_field('ship_company'));
    $customer->set('ship_address1',get_form_field('ship_address1'));
    $customer->set('ship_address2',get_form_field('ship_address2'));
    $customer->set('ship_city',get_form_field('ship_city'));
    $ship_country = get_form_field('ship_country');
    $customer->set('ship_country',$ship_country);
    $customer->shipping_country = $ship_country;
    switch ($ship_country) {
       case 1: $customer->set('ship_state',get_form_field('ship_state'));
               break;
       case 29: break;
       case 43: $customer->set('ship_canada_province',
                               get_form_field('ship_canada_province'));
                break;
       default: $customer->set('ship_province',
                               get_form_field('ship_province'));
                break;
    }
    $customer->set('ship_zipcode',get_form_field('ship_zipcode'));
    $customer->set('ship_address_type',get_form_field('address_type'));

    $cart = new Cart($db,-1,$customer_id,true);
    if ($customer_id > 0) $cart->info['customer_id'] = $customer_id;
    $cart->customer = $customer;
    if (isset($amount_cents_flag) && (! $amount_cents_flag))
       $round_cents = true;
    else $round_cents = false;
    $sub_total = 0;
    if (! isset($enable_multisite)) $enable_multisite = false;
    if ($enable_multisite) $website = get_form_field('website');
    $cart_items = array();
    $num_ids = get_form_field('NumIds');
    for ($loop = 0;  $loop < $num_ids;  $loop++) {
       $cart_item = array();
       $product_id = get_form_field('product_id_'.$loop);
       $product_name = get_form_field('product_name_'.$loop);
       $custom_attrs = get_form_field('product_custom_attrs_'.$loop);
       if ($product_id || $product_name) {
          $cart_item['id'] = $loop;
          $cart_item['product_id'] = $product_id;
          $cart_item['product_name'] = $product_name;
          $qty = get_form_field('product_qty_'.$loop);
          $cart_item['qty'] = $qty;
          $part_number = get_form_field('part_number_'.$loop);
          if ($part_number !== null)
             $cart_item['part_number'] = $part_number;
          $cost = get_form_field('product_cost_'.$loop);
          if ($cost !== null) $cart_item['cost'] = $cost;
          if ($multiple_customer_accounts)
             $cart_item['account_id'] = get_form_field('account_id_'.$loop);
          if ($product_id && (! $custom_attrs)) {
             $attributes = $cart->get_attributes($loop);
             if ($adding_order)
                $inv_info = $cart->get_product_inv_info($product_id,$attributes);
             if ($adding_order && ($cart->features & MAINTAIN_INVENTORY) &&
                 (! $cart->backorderable($inv_info))) {
                if ($inv_info) $item_qty = $inv_info['qty'];
                else $item_qty = 0;
                $cart->check_quantity($loop,$item_qty,$qty);
                if (isset($cart->errors['overqty_'.$loop]))
                   $cart->error = 'QTYERR|'.$loop.'|' .
                                  $cart->errors['overqty_'.$loop];
             }
             $no_options = check_no_option_attributes($db,$product_id);
             if (isset($attributes)) {
                if ($no_options)
                   $cart_item['attributes'] = implode('|',$attributes);
                else $cart_item['attributes'] = implode('-',$attributes);
             }
             else $cart_item['attributes'] = '';
             $cart->load_item_details($product_id,$qty,$attributes,$no_options,
                                      $cart_item['attribute_names'],
                                      $cart_item['attribute_prices'],
                                      $cart_item['price'],
                                      $cart_item['part_number'],
                                      $cart_item['flags']);
             if ($enable_multisite)
                $query = 'select status,websites from products where id=?';
             else $query = 'select status from products where id=?';
             $query = $cart->db->prepare_query($query,$product_id);
             $row = $cart->db->get_record($query);
             if ($row) {
                $cart_item['status'] = $row['status'];
                if ($enable_multisite) {
                   $websites = explode(',',$row['websites']);
                   if (in_array($website,$websites))
                      $cart_item['website'] = $website;
                   else $cart_item['website'] = $websites[0];
                }
             }
             else {
                $row['status'] = 0;
                if ($enable_multisite) $cart_item['website'] = $website;
             }
          }
          else {
             $cart_item['attributes'] = '';
             $cart_item['flags'] = 0;
             if ($enable_multisite) $cart_item['website'] = $website;
          }
          if ((! isset($cart_item['attributes'])) ||
              (! $cart_item['attributes'])) {
             $field_value = get_form_field('product_attributes_'.$loop);
             if ($field_value) $cart_item['attributes'] = $field_value;
          }
          if ((! isset($cart_item['attribute_names'])) ||
              (! $cart_item['attribute_names'])) {
             $field_value = get_form_field('product_attribute_names_'.$loop);
             if ($field_value) $cart_item['attribute_names'] = $field_value;
          }
          if ((! isset($cart_item['attribute_prices'])) ||
              (! $cart_item['attribute_prices'])) {
             $field_value = get_form_field('product_attribute_prices_'.$loop);
             if ($field_value) $cart_item['attribute_prices'] = $field_value;
          }
          if ((! isset($cart_item['flags'])) || (! $cart_item['flags'])) {
             $field_value = get_form_field('item_flags_'.$loop);
             if ($field_value) $cart_item['flags'] = $field_value;
          }
          $price = get_form_field('product_price_'.$loop);
          $price = floatval(preg_replace("/([^-0-9\\.])/i",'',$price));
          if ($round_cents) {
             if (($cart->currency != 'USD') && ($cart->exchange_rate !== null) &&
                 ($cart->exchange_rate != 0.0)) {
                $price = floatval($price) * $cart->exchange_rate;
                $price = floor($price / $this->exchange_rate);
             }
             else $price = floor($price);
          }
          $cart_item['price'] = $price;
          if (function_exists('update_add_edit_order_cart_item'))
             update_add_edit_order_cart_item($cart_item);
          $cart_items[$loop] = $cart_item;
          if ($cart_item['flags'] & QTY_PRICE) $sub_total += $price;
          else $sub_total += ($price * $qty);
          if (isset($cart_item['attribute_prices'])) {
             $attribute_prices = explode('|',$cart_item['attribute_prices']);
             foreach ($attribute_prices as $attr_index => $attr_price)
                if ($attr_price) {
                   if ($cart_item['flags'] & QTY_PRICE)
                      $sub_total += floatval($attr_price);
                   else $sub_total += (floatval($attr_price) * $qty);
                }
          }
       }
    }
    $cart->items = $cart_items;
    $cart->num_items = count($cart_items);
    $cart->set('subtotal',$sub_total);
    $cart->set('total',$sub_total);

    $cart->set('coupon_id',get_form_field('coupon_id'));
    $cart->set('coupon_code',get_form_field('coupon_code'));
    $cart->set('coupon_type',get_form_field('coupon_type'));
    $cart->set('coupon_amount',get_form_field('coupon_amount'));
    if ($cart->features & GIFT_CERTIFICATES) {
       $cart->set('gift_id',get_form_field('gift_id'));
       $cart->set('gift_code',get_form_field('gift_code'));
       $cart->set('gift_amount',get_form_field('gift_amount'));
       $cart->set('gift_balance',get_form_field('gift_balance'));
    }
    $fee_name = get_form_field('fee_name');
    $fee_amount = get_form_field('fee_amount');
    if ($fee_amount && (! $fee_name)) $fee_name = 'Fee';
    if ($fee_name) {
       $fee_amount = floatval(preg_replace("/([^-0-9\\.])/i",'',$fee_amount));
       $cart->set('fee_name',$fee_name);
       $cart->set('fee_amount',$fee_amount);
    }
    $discount_name = get_form_field('discount_name');
    $discount_amount = get_form_field('discount_amount');
    if ($discount_amount && (! $discount_name)) $discount_name = 'Discount';
    if ($discount_name) {
       $discount_amount = floatval(preg_replace("/([^-0-9\\.])/i",'',$discount_amount));
       $cart->set('discount_name',$discount_name);
       $cart->set('discount_amount',$discount_amount);
    }

    if ($edit_type == ADDRECORD) {
       if (function_exists('check_shipping_module'))
          check_shipping_module($cart,$customer,$cart);
       $shipping_method = get_form_field('shipping_method');
       if (! $shipping_method) $cart->set('shipping',0);
       else {
          $shipping_info = explode('|',$shipping_method);
          $shipping_module = $shipping_info[0];
          if (shipping_module_event_exists('display_shipping_info',
                                           $shipping_module)) {
             $process_shipping = $shipping_module.'_process_shipping';
             $process_shipping($cart,$shipping_method);
             $cart->update_free_shipping_label($cart->info['shipping_method']);
          }
       }
       $cart->set('shipping',get_form_field('shipping'));
    }
    else {
       $cart->set('shipping',get_form_field('shipping'));
       $cart->set('shipping_carrier',get_form_field('shipping_carrier'));
       $cart->set('shipping_method',get_form_field('shipping_method'));
    }

    if ((! $lookup_fees) || get_form_field('shipping_method') ||
        (! taxable_shipping($cart)))
       $cart->calculate_tax($customer);

    update_cart_total($cart);
}

function get_product_prices()
{
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    $form_fields = get_form_fields();   $ids = array();
    foreach ($form_fields as $field_name => $field_value) {
       if (substr($field_name,0,3) == 'id_') {
          $index = intval(substr($field_name,3));
          $ids[$index] = $field_value;
       }
    }
    $query = 'select id,price from products where id in (?)';
    $query = $db->prepare_query($query,$ids);
    $prices = $db->get_records($query,'id');
    if (! $prices) return;
    foreach ($ids as $index => $id) {
       if (! isset($prices[$id])) continue;
       $price = $prices[$id]['price'];
       print 'form.product_price_'.$index.'.value='.$price.";\n";
    }
}

function lookup_fees()
{
    require_once 'cart-public.php';

    $shipping_module_labels = array();
    call_shipping_event('module_labels',array(&$shipping_module_labels));
    $db = new DB;
    if (get_form_field('Action') == 'Add') $edit_type = ADDRECORD;
    else $edit_type = UPDATERECORD;
    parse_new_cart_data($db,$customer,$cart,$edit_type,false,true);
    $null_order = null;
    $cart->internal_cart = true;
    if (function_exists('check_shipping_module'))
       check_shipping_module($cart,$customer,$null_order);
    $cart->load_shipping_options($customer);
    if (! get_form_field('shipping_method')) {
       $cart->set_shipping_amount();
       if (taxable_shipping($cart)) $cart->calculate_tax($customer);
    }

    $coupon_code = $cart->get('coupon_code');
    if (! $coupon_code) {
       $cart->process_special_offers();
       $coupon_code = $cart->get('coupon_code');
       if ($coupon_code) {
          print 'coupon_id = \''.$coupon_code."';\n";
          print 'coupon_amount = '.$cart->get('coupon_amount').";\n";
       }
    }
    $tax = $cart->get('tax');
    if (($tax === null) || ($tax === '')) print "tax = null;\n";
    else print 'tax = '.$tax.";\n";
    print 'shipping_options = [';
    $first_option = true;
    foreach ($cart->shipping_options as $shipping_option) {
       if ($first_option) $first_option = false;
       else print ',';
       $shipping_label = $shipping_option[3];
       if ($edit_type == UPDATERECORD) {
          $label_prefix = $shipping_module_labels[$shipping_option[0]].' ';
          $prefix_length = strlen($label_prefix);
          if (substr($shipping_label,0,$prefix_length) == $label_prefix)
             $shipping_label = substr($shipping_label,$prefix_length);
       }
       $option_string = $shipping_option[0].'|'.$shipping_option[1].'|' .
             $shipping_option[2].'|'.$shipping_label.'|' .
             $shipping_option[4];
       $option_string = str_replace("'","\\'",$option_string);
       print "\n  '".$option_string."'";
    }
    print "\n];\n";
    if (isset($cart->error))
       print "shipping_error = '".str_replace("'","\\'",$cart->error)."';\n";
    foreach ($shipping_module_labels as $module_id => $module_label)
       print "shipping_modules['".$module_id."'] = '".$module_label."';\n";
}

function get_cart_error($cart)
{
    if (isset($cart->error)) return $cart->error;

    $error_messages = array(
       'InvalidCoupon' => 'Invalid Promotion Code',
       'CouponNotYet' => 'Promotion Code is not available yet',
       'ExpiredCoupon' => 'Promotion Code has expired',
       'NoMoreCoupons' => 'No more promotion codes of this type are available',
       'CouponMinAmount' => 'This Promotion Code is only valid for orders over ',
       'CouponNoProduct' => 'Promotion Code is not available for ',
       'CouponNoWebSite' => 'Promotion Code is not available for this web site',
       'CouponNoCustomer' => 'Promotion Code is not available for the selected customer',
       'InvalidGiftCert' => 'Invalid Gift Certficate',
       'ExpiredGiftCert' => 'Gift Certificate has expired',
       'GiftCertNotYet' => 'Gift Certificate is not available yet',
       'UsedGiftCert' => 'This Gift Certificate has already been used',
       'GiftNoProduct' => 'Gift Certificate is not available for ',
       'GiftNoCustomer' => 'Gift Certificate is not available for the selected customer'
    );

    $error = null;
    foreach ($error_messages as $code => $label) {
       if (isset($cart->errors[$code])) {
          if ($code == 'CouponMinAmount')
             $label .= $cart->get('coupon_min_amount');
          else if (($code == 'CouponNoProduct') || ($code == 'GiftNoProduct')) {
             if (count($cart->items) > 1) $label .= 'these products';
             else $label .= 'this product';
          }
          return $label;
       }
    }
    $value = reset($cart->errors);
    $code = key($cart->errors);
    
    return 'Unknown Error: '.$code.' ('.$value.')';
}

function process_coupon()
{
    require_once 'cart-public.php';

    $db = new DB;
    if (get_form_field('Action') == 'Add') $edit_type = ADDRECORD;
    else $edit_type = UPDATERECORD;
    parse_new_cart_data($db,$customer,$cart,$edit_type);
    $coupon_code = get_form_field('coupon_code');
    if (! $cart->process_coupon($coupon_code)) {
       print "coupon_error = '".str_replace("'","\\'",get_cart_error($cart)) .
             "';\n";
       return;
    }
    print 'coupon_id = '.$cart->info['coupon_id'].";\n";
    print 'coupon_amount = '.$cart->info['coupon_amount'].";\n";
    print 'coupon_type = '.$cart->info['coupon_type'].";\n";
}

function process_gift()
{
    require_once 'cart-public.php';

    $db = new DB;
    if (get_form_field('Action') == 'Add') $edit_type = ADDRECORD;
    else $edit_type = UPDATERECORD;
    parse_new_cart_data($db,$customer,$cart,$edit_type);
    $cart->set('gift_amount',0);
    update_cart_total($cart);
    $gift_code = get_form_field('gift_code');
    if (! $cart->process_gift_certificate($gift_code)) {
       print "gift_error = '".str_replace("'","\\'",get_cart_error($cart)) .
             "';\n";
       return;
    }
    print 'gift_id = '.$cart->info['gift_id'].";\n";
    print 'gift_amount = '.$cart->info['gift_amount'].";\n";
    print 'gift_balance = '.$cart->info['gift_balance'].";\n";
}

function delete_new_customer($db,$customer_id)
{
    $query = 'delete from billing_information where parent=?';
    $query = $db->prepare_query($query,$customer_id);
    $db->log_query($query);
    if (! $db->query($query)) return;
    $query = 'delete from shipping_information where parent=?';
    $query = $db->prepare_query($query,$customer_id);
    $db->log_query($query);
    if (! $db->query($query)) return;
    $query = 'delete from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $db->log_query($query);
    $db->query($query);
    log_activity('Deleted Customer #'.$customer_id);
}

function add_item_fields(&$head_block)
{
    global $item_fields;

    if (! isset($item_fields)) return;
    foreach ($item_fields as $field_name => $field)
       $head_block .= '      item_fields[\''.$field_name.'\'] = ' .
                      json_encode($field).";\n";
}

function add_order()
{
    global $order_label,$products_script_name,$vertical_attribute_prompt;
    global $enable_reorders;

    $order = new OrderInfo();
    $order->info = array();
    $order->billing = array();
    $order->shipping = array();
    $head_block = "<script type=\"text/javascript\">\n";
    $head_block .= "      products_script_name = '".$products_script_name .
                   "';\n";
    $product_dialog_height = get_product_screen_height();
    $head_block .= "      product_dialog_height = " .
                   $product_dialog_height.";\n";
    if (! empty($vertical_attribute_prompt))
       $head_block .= "      vertical_attribute_prompt = true;\n";
    add_item_fields($head_block);
    $head_block .= '    </script>';
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('fileuploader.css');
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('fileuploader.js');
    $dialog->add_script_file('orders.js');
    $dialog->add_script_file('../engine/he.js');
    if (! empty($enable_reorders))
       $dialog->add_script_file('../admin/reorders-admin.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->add_head_line($head_block);
    $dialog->set_onload_function('add_edit_order_onload();');
    $dialog->set_field_padding(2);
    $dialog->set_body_id('add_order');
    $dialog->set_help('add_order');
    call_payment_event('setup_order_dialog',array($db,&$dialog,ADDRECORD));
    $dialog->start_body('New '.$order_label);
    $dialog->set_button_width(90);
    $dialog->start_button_column();
    $dialog->add_button('Add','images/AddOrder.png','process_add_order();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','AddOrder');
    display_order_fields($dialog,ADDRECORD,$order,$db,0);
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_order_errors($customer)
{
    $errors = $customer->errors;
    $error_messages = array(
       'InvalidEmail' => 'Invalid Billing E-Mail Address',
       'InvalidBillingZipCode' => 'Invalid Billing Zip Code',
       'InvalidShippingZipCode' => 'Invalid Shipping Zip Code',
       'cust_email' => 'Missing Billing E-Mail Address',
       'cust_fname' => 'Missing Billing First Name',
       'cust_lname' => 'Missing Billing Last Name',
       'bill_address1' => 'Missing Billing Address Line 1',
       'bill_city' => 'Missing Billing City',
       'bill_country' => 'Missing Billing Country',
       'bill_state' => 'Missing Billing State',
       'bill_province' => 'Missing Billing Province',
       'bill_zipcode' => 'Missing Billing Zip Code',
       'bill_phone' => 'Missing Billing Telephone',
       'ship_address1' => 'Missing Shipping Address Line 1',
       'ship_city' => 'Missing Shipping City',
       'ship_country' => 'Missing Shipping Country',
       'ship_state' => 'Missing Shipping State',
       'ship_province' => 'Missing Shipping Province',
       'ship_zipcode' => 'Missing Shipping Zip Code',
       'card_type' => 'You must select a Credit Card Type',
       'card_number' => 'Invalid Card Number',
       'card_cvv' => 'Missing CVV Number',
       'card_expired' => 'Card has Expired',
       'card_name' => 'Missing Name on Card',
       'bank_name' => 'Missing Bank Name',
       'routing_number' => 'Missing Account Number',
       'account_name' => 'Missing Account Name',
       'account_type' => 'Missing Account Type'
    );

    foreach ($error_messages as $code => $label) {
       if (isset($errors[$code])) {
          http_response(406,$label);   return;
       }
    }
    $value = reset($errors);
    $code = key($errors);
    if (($code == 'BillAddress') || ($code == 'ShipAddress')) {
       $data = array('code'=>$code);   http_response(409,json_encode($data));
    }
    else if ($code == 'SuggestedBillAddress') {
       $data = $customer->match_bill_address;   $data['code'] = $code;
       http_response(409,json_encode($data));
    }
    else if ($code == 'SuggestedShipAddress') {
       $data = $customer->match_ship_address;   $data['code'] = $code;
       http_response(409,json_encode($data));
    }
    else http_response(406,'Unknown Error: '.$code.' ('.$value.')');
}

function send_new_order_notifications($db,$customer_id,$order,$customer)
{
    $notify_flags = get_cart_config_value('notifications',$db);
    if (($customer_id == -1) &&
        (($notify_flags & NOTIFY_NEW_CUSTOMER) ||
         ($notify_flags & NOTIFY_NEW_CUSTOMER_ADMIN))) {
       load_order_item_product_info($order);
       require_once '../engine/email.php';
       if (($notify_flags & NOTIFY_NEW_CUSTOMER) && $customer->info['email']) {
          $email = new Email(NEW_CUSTOMER_EMAIL,
                             array('customer' => 'obj',
                                   'customer_obj' => $customer));
          if (! $email->send()) log_error($email->error);
          if (! empty($customer->id))
             write_customer_activity($email->activity,$customer->id,$db);
       }
       if ($notify_flags & NOTIFY_NEW_CUSTOMER_ADMIN) {
          $email = new Email(NEW_CUSTOMER_ADMIN_EMAIL,
                             array('customer' => 'obj',
                                   'customer_obj' => $customer));
          if (! $email->send()) log_error($email->error);
       }
    }

    if (function_exists('custom_order_notifications'))
       custom_order_notifications($order);
    else {
       require_once '../engine/email.php';
       if ($order->order_type == ORDER_TYPE) {
          $customer_template = NEW_ORDER_CUST_EMAIL;
          $admin_template = NEW_ORDER_ADMIN_EMAIL;
       }
       else if (($order->order_type == QUOTE_TYPE) &&
                defined('NEW_QUOTE_ADMIN_EMAIL')) {
          $customer_template = NEW_QUOTE_CUST_EMAIL;
          $admin_template = NEW_QUOTE_ADMIN_EMAIL;
       }
       else $customer_template = $admin_template = 0;
       if (($customer_template && ($notify_flags & NOTIFY_NEW_ORDER_CUST)) ||
           ($admin_template && ($notify_flags & NOTIFY_NEW_ORDER_ADMIN))) {
          if ($customer_template && ($notify_flags & NOTIFY_NEW_ORDER_CUST) &&
              $order->info['email']) {
             $email = new Email($customer_template,
                                array('order' => 'obj','order_obj' => $order));
             if (! $email->send()) log_error($email->error);
             if (! empty($order->customer_id))
                write_customer_activity($email->activity,$order->customer_id,
                                        $db);
          }
          if ($admin_template && ($notify_flags & NOTIFY_NEW_ORDER_ADMIN)) {
             $email = new Email($admin_template,
                                array('order' => 'obj','order_obj' => $order));
             if (! $email->send()) log_error($email->error);
          }
       }
       if (($order->order_type == ORDER_TYPE) &&
           ($notify_flags & NOTIFY_LOW_QUANTITY))
          check_low_quantity($order,null);
    }
}

function process_add_order()
{
    global $order_type,$order_label,$save_card_in_order,$enable_multisite;

    require_once 'cart-public.php';

    if (! isset($save_card_in_order)) $save_card_in_order = false;
    $db = new DB;
    load_cart_config_values($db);
    $order_status = get_form_field('status');
    parse_new_cart_data($db,$customer,$cart,ADDRECORD,true);
    if ($order_type == ORDER_TYPE) {
       $customer->validate_email();
       $customer->validate_address();
       $customer->check_required_fields(array('cust_email','cust_fname','cust_lname',
                                              'bill_address1','bill_city','bill_country',
                                              'bill_state','bill_zipcode','bill_phone',
                                              'ship_address1','ship_city','ship_country',
                                              'ship_state','ship_zipcode'));
    }
    if (count($customer->errors) > 0) {
       process_add_order_errors($customer);   return;
    }
    if (isset($cart->error)) {
       http_response(406,$cart->error);   return;
    }

    $customer_id = $customer->id;
    if ($customer_id == -1) {
       if (! $customer->create()) {
          if ($customer->status == MISSING_EMAIL) {
             http_response(406,'Invalid Billing E-Mail Address');   return;
          }
          else if ($customer->status == ALREADY_REGISTERED) {
             http_response(406,'A Customer with that e-mail address already ' .
                               'exists in the system, ' .
                               'please use the Find Customer option to ' .
                               'select the existing customer record');
             return;
          }
          else if (count($customer->errors) == 0) {
             if (isset($customer->error)) http_response(406,$customer->error);
             else http_response(406,'Unable to add customer');
          }
       }
       if (count($customer->errors) > 0) {
          if (isset($customer->error)) http_response(406,$customer->error);
          else process_add_order_errors($customer);
          return;
       }
       $cart->info['customer_id'] = $customer->id;
       $customer->info['id'] = $customer->id;
    }
    else if (($customer_id > 0) && get_form_field('UseOwnAddress'))
       write_customer_activity('Customer Used Entered Address',$customer_id,
                               $db);

    $order = new Order($customer);
    $order->cart = $cart;
    $order->items = $cart->items;
    if ((count($order->items) == 0) && ($order_type == ORDER_TYPE))  {
       if ($customer_id == -1) delete_new_customer($db,$customer->id);
       http_response(406,'There are no items in the ' .
                     strtolower($order_label));
       return;
    }
    $order->info['status'] = $order_status;
    $order->info['currency'] = $cart->info['currency'];
    change_currency($order,$order->info['currency']);
    if (! empty($enable_multisite)) {
       $order->info['website'] = get_form_field('website');
       set_order_website_id($order->info,ADDRECORD);
    }
    if (isset($cart->info['reorder_id']))
       $order->info['reorder_id'] = $cart->info['reorder_id'];

    $order->set('shipping',$cart->get('shipping'));
    $order->set('shipping_carrier',$cart->get('shipping_carrier'));
    $order->set('shipping_method',$cart->get('shipping_method'));
    $order->set('subtotal',$cart->get('subtotal'));
    $order->set('tax',$cart->get('tax'));
    $order->set('coupon_id',$cart->get('coupon_id'));
    $order->set('coupon_code',$cart->get('coupon_code'));
    $order->set('coupon_amount',$cart->get('coupon_amount'));
    if ($cart->features & GIFT_CERTIFICATES) {
       $order->set('gift_id',$cart->get('gift_id'));
       $order->set('gift_code',$cart->get('gift_code'));
       $order->set('gift_amount',$cart->get('gift_amount'));
       $order->set('gift_balance',$cart->get('gift_balance'));
    }
    $fee_name = $cart->get('fee_name');
    if ($fee_name) {
       $order->set('fee_name',$fee_name);
       $order->set('fee_amount',$cart->get('fee_amount'));
    }
    $discount_name = $cart->get('discount_name');
    if ($discount_name) {
       $order->set('discount_name',$discount_name);
       $order->set('discount_amount',$cart->get('discount_amount'));
    }
    if (isset($order->info['shipping'])) $shipping = $order->info['shipping'];
    else $shipping = 0;
    $total = $order->info['subtotal'] + $order->info['tax'] + $shipping -
             $order->info['coupon_amount'];
    if ($cart->features & GIFT_CERTIFICATES)
       $total -= $order->info['gift_amount'];
    if ($fee_name) $total += $order->info['fee_amount'];
    if ($discount_name) $total -= $order->info['discount_amount'];
    $order->set('total',$total);
    $order->info['notes'] = get_form_field('notes');
    $external_source = get_form_field('external_source');
    if ($external_source == 'custom')
       $external_source = get_form_field('custom_source');
    else if ($external_source == 'new') $external_source = null;
    if ($external_source) $order->info['external_source'] = $external_source;
    $external_id = get_form_field('external_id');
    if ($external_id) $order->info['external_id'] = $external_id;
    $phone_order = get_form_field('phone_order');
    if ($phone_order == 'on') $order->info['phone_order'] = true;
    $payment_type = get_form_field('payment_type');
    if ($payment_type) {
       $payment_amount = floatval(round(get_form_field('payment_amount'),2));
       if ($payment_amount) {
          $order->payment['payment_amount'] = $payment_amount;
          if ($payment_amount != $total) $order->partial_payment = true;
       }
       if (substr($payment_type,0,6) == 'saved-') {
          $saved_info = explode('|',substr($payment_type,6));
          $saved_card_id = $saved_info[0];
          $saved_profile_id = $saved_info[1];
       }
       else $saved_card_id = null;
       if (($payment_type == 'cc') || $saved_card_id) {
          $order->payment_module = call_payment_event('get_primary_module',
                                                      array($db),true,true);
          if ($order->payment_module) {
             if ($saved_card_id) $order->saved_card = $saved_profile_id;
             else $order->parse_card_fields();
             if ((! $order->validate_credit_card()) || (! $order->process_payment())) {
                if ($customer_id == -1) delete_new_customer($db,$customer->id);
                if (isset($order->error)) http_response(406,$order->error);
                else process_add_order_errors($order);
                $order->cleanup_order();   return;
             }
             if ((! $saved_card_id) &&
                 ($save_card_in_order || (get_form_field('SaveCard') == 'on'))) {
                if (! $order->save_credit_card()) {
                   http_response(422,'Database Error: '.$order->error);
                   $order->cleanup_order();   return;
                }
             }
          }
       }
       else {
          $payment_method = get_form_field('payment_method');
          if (! $payment_method) $payment_method = $payment_type;
          $order->payment['payment_method'] = $payment_method;
          $check_number = get_form_field('check_number');
          if ($check_number) $order->set('check_number',$check_number);
       }
    }
    if (function_exists('custom_process_add_order'))
       custom_process_add_order($order);
    if (! $order->create()) {
       http_response(422,'Database Error: '.$order->error);   return;
    }
    if (get_form_field('send_emails') == 'on')
       send_new_order_notifications($db,$customer_id,$order,$customer);

    if ($order_type == ORDER_TYPE) $order_number = $order->info['order_number'];
    else $order_number = $order->id;
    http_response(201,$order_label.' #'.$order_number.' Added');
}

function edit_order()
{
    global $enable_edit_order_items,$order_label,$enable_reorders;
    global $products_script_name,$vertical_attribute_prompt;

    $db = new DB;
    $id = get_form_field('id');
    $order = load_order($db,$id,$error_msg);
    if (! $order) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    $head_block = "<script type=\"text/javascript\">\n";
    $num_products = 0;
    if ($order->items) {
       foreach ($order->items as $item_id => $order_item) {
          $head_block .= "      product_quantities[".$num_products."] = " .
                         $order_item['qty'].";\n";
          $head_block .= '      product_prices['.$num_products.'] = ' .
                         $order_item['price'].";\n";
          $num_products++;
       }
    }
    $head_block .= '      num_product_rows = '.($num_products + 1).";\n";
    $head_block .= '      num_product_ids = '.$num_products.";\n";
    $head_block .= "      products_script_name = '".$products_script_name .
                   "';\n";
    $product_dialog_height = get_product_screen_height($db);
    $head_block .= "      product_dialog_height = " .
                   $product_dialog_height.";\n";
    if (! empty($vertical_attribute_prompt))
       $head_block .= "      vertical_attribute_prompt = true;\n";
    add_item_fields($head_block);
    $head_block .= '    </script>';
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('fileuploader.css');
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('fileuploader.js');
    $dialog->add_script_file('orders.js');
    $dialog->add_script_file('../engine/he.js');
    if (! empty($enable_reorders))
       $dialog->add_script_file('../admin/reorders-admin.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->add_head_line($head_block);
    $dialog->set_onload_function('add_edit_order_onload();');
    $dialog->set_field_padding(2);
    $dialog_title = 'Edit '.$order_label.' (#'.$id.')';
    $dialog->set_body_id('edit_order');
    $dialog->set_help('edit_order');
    call_payment_event('setup_order_dialog',array($db,&$dialog,UPDATERECORD));
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(90);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_order(null);');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','EditOrder');
    if (get_form_field('copying') == 'true')
       $dialog->add_hidden_field('copying','true');
    display_order_fields($dialog,UPDATERECORD,$order,$db,$num_products);
    $dialog->end_form();
    $dialog->end_body();
}

function remove_payment_fields(&$order_record)
{
    foreach ($order_record as $field_name => $field_info) {
       if ((substr($field_name,0,8) == 'payment_') ||
           (substr($field_name,0,5) == 'card_') ||
           (substr($field_name,0,6) == 'check_'))
          unset($order_record[$field_name]);
    }
}

function get_order_shipped_status($db,$order_id)
{
    global $new_order_option,$shipped_option,$partial_shipped_option;

    if (! isset($new_order_option)) $new_order_option = 0;
    if (! isset($shipped_option)) $shipped_option = 1;
    if (! isset($partial_shipped_option)) $partial_shipped_option = 4;
    $query = 'select sum(qty) as num_items from order_items where parent=?';
    $query = $db->prepare_query($query,$order_id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return -1;
    }
    $total_items = $row['num_items'];
    $query = 'select sum(qty) as num_items from order_shipment_items where ' .
             'parent in (select id from order_shipments where parent=?)';
    $query = $db->prepare_query($query,$order_id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return -1;
    }
    $shipped_items = $row['num_items'];
    if ($total_items == $shipped_items) return $shipped_option;
    else if ($shipped_items == 0) return $new_order_option;
    return $partial_shipped_option;
}

function update_attribute_prices(&$item_record)
{
    $form_fields = get_form_fields();
    if (empty($item_record['attribute_prices']['value'])) $prices = '';
    else $prices = $item_record['attribute_prices']['value'];
    $prices = explode('|',$prices);
    $updated_prices = false;
    foreach ($form_fields as $field_name => $field_value) {
       if (substr($field_name,0,10) == 'attr_price') {
          $name_parts = explode('_',$field_name);
          $attr_index = intval($name_parts[count($name_parts) - 1]);
          if ($field_value && empty($prices[$attr_index])) {
             $prices[$attr_index] = $field_value;   $updated_prices = true;
          }
       }
    }
    if ($updated_prices)
       $item_record['attribute_prices']['value'] = implode('|',$prices);
}

function update_order_inventory_record($db,$product_id,$attributes,$qty,
                                       $increment_flag)
{
    $query = 'update product_inventory set qty=ifnull(';
    if ($increment_flag) $query .= 'qty+?';
    else $query .= 'qty-?';
    $query .= ',?) where (parent=?)';
    if (! $attributes) $lookup_attributes = null;
    else {
       $no_options = check_no_option_attributes($db,$product_id);
       if ($no_options) $attributes = explode('|',$attributes);
       else $attributes = explode('-',$attributes);
       $lookup_attributes = build_lookup_attributes($db,
          $product_id,$attributes,true,$no_options);
    }
    if ($lookup_attributes) {
       $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
          $product_id,$no_options,$db);
       $query .= ' and (attributes=?)';
       $query = $db->prepare_query($query,$qty,$qty,$product_id,
                   $lookup_attributes);
    }
    else {
       $query .= ' and ((attributes="") or isnull(attributes))';
       $query = $db->prepare_query($query,$qty,$qty,$product_id);
    }
    $db->log_query($query);
    $result = $db->query($query);
    if (! $result) {
       http_response(422,$db->error);   return false;
    }
    if (using_linked_inventory($db))
       update_linked_inventory($db,null,null,$product_id,$lookup_attributes);
    return true;
}

function update_order_inventory($db,$product_id,$old_attributes,$old_qty,
                                $new_attributes,$new_qty)
{
    if (! $product_id) return true;
    if (($old_attributes == $new_attributes) && ($old_qty == $new_qty))
       return true;

    if ($new_attributes === null) {  // deleted item - increment inventory
       if (! update_order_inventory_record($db,$product_id,$old_attributes,
                                           $old_qty,true)) return false;
    }
    else if ($old_attributes === null) {  // new item - decrement inventory
       if (! update_order_inventory_record($db,$product_id,$new_attributes,
                                           $new_qty,false)) return false;
    }
    else if ($old_attributes == $new_attributes) {  // quantity change
       if ($new_qty > $old_qty) {
          $qty = $new_qty - $old_qty;   $increment_flag = false;
       }
       else {
          $qty = $old_qty - $new_qty;   $increment_flag = true;
       }
       if (! update_order_inventory_record($db,$product_id,$new_attributes,
                                           $qty,$increment_flag)) return false;
    }
    else {  // attribute change - increment old and decrement new
       if (! update_order_inventory_record($db,$product_id,$old_attributes,
                                           $old_qty,true)) return false;
       if (! update_order_inventory_record($db,$product_id,$new_attributes,
                                           $new_qty,false)) return false;
    }

    return true;
}

function update_order()
{
    global $order_type,$orders_table,$order_label,$amount_cents_flag;
    global $shipped_option,$taxcloud_api_id,$item_fields,$enable_multisite;
    global $save_card_in_order,$skip_update_attributes,$auto_reorder_label;
    global $multiple_customer_accounts,$enable_reorders,$enable_multisite;

    require_once 'cart-public.php';

    if (isset($amount_cents_flag) && (! $amount_cents_flag))
       $round_cents = true;
    else $round_cents = false;
    if (! isset($shipped_option)) $shipped_option = 1;
    if (! isset($save_card_in_order)) $save_card_in_order = false;
    $customer = null;
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    switch ($order_type) {
       case ORDER_TYPE: $order_record = orders_record_definition();   break;
       case QUOTE_TYPE: $order_record = quotes_record_definition();   break;
       case INVOICE_TYPE: $order_record = invoices_record_definition();
                          break;
       case SALESORDER_TYPE: $order_record = salesorders_record_definition();
                             break;
    }
    $db->parse_form_fields($order_record);
    if (! empty($enable_multisite)) {
       $order_info = array('website' => get_form_field('website'));
       set_order_website_id($order_info,UPDATERECORD);
    }
    if ($order_type == ORDER_TYPE) {
       if (! isset($order_record['external_source']['value']))
          $order_record['external_source']['value'] = null;
       else if ($order_record['external_source']['value'] == 'custom')
          $order_record['external_source']['value'] =
             get_form_field('custom_source');
       else if ($order_record['external_source']['value'] == 'new')
          $order_record['external_source']['value'] = null;
    }
    $order_status = $order_record['status']['value'];
    $order_id = $order_record['id']['value'];
    if (! empty($order_record['fee_amount']['value']))
       $order_record['fee_amount']['value'] =
          floatval(preg_replace("/([^-0-9\\.])/i",'',
                   $order_record['fee_amount']['value']));
    if (! empty($order_record['discount_amount']['value']))
       $order_record['discount_amount']['value'] =
          floatval(preg_replace("/([^-0-9\\.])/i",'',
                   $order_record['discount_amount']['value']));
    $shipping = floatval($order_record['shipping']['value']);
    $total = floatval($order_record['subtotal']['value']) +
             floatval($order_record['tax']['value']) + $shipping;
    if (! empty($order_record['coupon_amount']['value']))
       $total -= floatval($order_record['coupon_amount']['value']);
    if (! empty($order_record['gift_amount']['value']))
       $total -= floatval($order_record['gift_amount']['value']);
    if (! empty($order_record['fee_amount']['value']))
       $total += floatval($order_record['fee_amount']['value']);
    if (! empty($order_record['discount_amount']['value']))
       $total -= floatval($order_record['discount_amount']['value']);
    $order_record['total']['value'] = $total;
    $customer_id = $order_record['customer_id']['value'];
    $num_payments = get_form_field('NumPayments');
    $payment_type = get_form_field('payment_type');
    if ($payment_type) {
       $payment_amount = preg_replace("/([^-0-9\\.])/i",'',
                            get_form_field('payment_amount'));
       $payment_amount = floatval(round($payment_amount,2));
       if (! $payment_amount)  {
          http_response(406,'No Payment Amount Specified');   return;
       }
       if (substr($payment_type,0,6) == 'saved-') {
          $saved_info = explode('|',substr($payment_type,6));
          $saved_card_id = $saved_info[0];
          $saved_profile_id = $saved_info[1];
       }
       else $saved_card_id = null;
       if (($payment_type == 'cc') || $saved_card_id) {
          if ($customer_id) {
             $customer = new Customer($db,$customer_id,true);
             $customer->load();
          }
          else $customer = new Customer($db,null,true);
          $payment_order = new Order($customer);
          $payment_order->load($order_id);
          $payment_order->payment['payment_method'] = '';
          if (! $customer_id) {
             $payment_order->customer->info = $payment_order->info;
             $payment_order->customer->billing = $payment_order->billing;
             $payment_order->customer->shipping = $payment_order->shipping;
          }
          $payment_order->payment['payment_amount'] = $payment_amount;
          $payment_order->payment_module =
             call_payment_event('get_primary_module',array($db),true,true);
          if ($payment_order->payment_module) {
             if ($payment_amount != $total) $payment_order->partial_payment = true;
             if ($saved_card_id) $payment_order->saved_card = $saved_profile_id;
             else $payment_order->parse_card_fields();
             if ((! $payment_order->validate_credit_card()) ||
                 (! $payment_order->process_payment())) {
                if (isset($payment_order->error))
                   http_response(406,$payment_order->error);
                else process_add_order_errors($payment_order);
                return;
             }
             if ((! $saved_card_id) &&
                 ($save_card_in_order || (get_form_field('SaveCard') == 'on'))) {
                if (! $payment_order->save_credit_card()) {
                   http_response(422,'Database Error: '.$payment_order->error);
                   return;
                }
             }
          }
       }
       else {
          $payment_order = new Order(null,$db);
          $payment_method = get_form_field('payment_method');
          if (! $payment_method) $payment_method = $payment_type;
          $payment_order->payment['payment_method'] = $payment_method;
          $payment_order->payment['payment_amount'] = $payment_amount;
          $check_number = get_form_field('check_number');
          if ($check_number)
             $payment_order->payment['check_number'] = $check_number;
       }
       $payment_record = payment_record_definition();
       $payment_record['parent']['value'] = $order_id;
       $payment_record['parent_type']['value'] = $order_type;
       $payment_order->copy_payment_data($payment_record,
                                         $payment_order->payment);
       if ($saved_card_id && $save_card_in_order)
          $payment_record['saved_card_id']['value'] = $saved_card_id;
       if (! $db->insert('order_payments',$payment_record)) {
          http_response(422,$db->error);   return;
       }
       $activity = 'Added Payment for '.$order_label.' #'.$order_id;
       log_activity($activity);
       if ($customer_id)
          write_customer_activity($activity.' by ' .
                                  get_customer_activity_user($db),
                                  $customer_id,$db);
    }
    else $payment_amount = 0;
    if ($order_type == ORDER_TYPE) {
       $old_balance_due = floatval(get_form_field('old_balance_due'));
       $new_balance_due = floatval(preg_replace("/([^-0-9\\.])/i",'',
                                   get_form_field('new_balance_due')));
    }

    $query = 'select * from order_payments where (parent=?) and ' .
             '(parent_type=?) order by payment_date';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $order_payments = $db->get_records($query);
    if ($order_payments) foreach ($order_payments as $row) {
       if (get_form_field('cancel_payment_'.$row['id'])) {
          $cancel_info = array();
          $refund_amount = get_form_field('refund_amount_'.$row['id']);
          if ($refund_amount) $refund_amount = floatval($refund_amount);
          else $refund_amount = floatval($row['payment_amount']);
          $row['currency'] = $order_record['currency']['value'];
          if (! cancel_order_payment($db,$row,$refund_amount,$cancel_info,
                                     $error)) {
             http_response(422,$error);   return;
          }
          if (! add_cancelled_payment($db,$row,$refund_amount,$cancel_info,
                                      $error)) {
             http_response(422,$error);   return;
          }
          $new_balance_due += $refund_amount;
       }
       else if (get_form_field('capture_payment_'.$row['id'])) {
          if (! capture_order_payment($db,$row,$error)) {
             http_response(422,$error);   return;
          }
          else if (isset($taxcloud_api_id) && (count($order_payments) == 1)) {
             require_once 'taxcloud.php';
             capture_taxcloud_order($order_id);
          }
       }
       else if (get_form_field('delete_payment_'.$row['id'])) {
          $query = 'delete from order_payments where id=?';
          $query = $db->prepare_query($query,$row['id']);
          $db->log_query($query);
          if (! $db->query($query)) {
             http_response(422,$db->error);   return;
          }
          $new_balance_due += floatval($row['payment_amount']);
       }
    }

    $query = 'select id,tracking,shipped_date from order_shipments where ' .
             '(parent=?) and (parent_type=?)';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $shipments = $db->get_records($query);
    $deleted_shipments = false;
    if ($shipments) foreach ($shipments as $shipment) {
       if (get_form_field('delete_shipment_'.$shipment['id'])) {
          $query = 'delete from order_shipment_items where parent=?';
          $query = $db->prepare_query($query,$shipment['id']);
          $db->log_query($query);
          if (! $db->query($query)) {
             http_response(422,$db->error);   return;
          }
          $query = 'delete from order_shipments where id=?';
          $query = $db->prepare_query($query,$shipment['id']);
          $db->log_query($query);
          if (! $db->query($query)) {
             http_response(422,$db->error);   return;
          }
          $deleted_shipments = true;
       }
       else {
          $tracking = get_form_field('tracking_'.$shipment['id']);
          if (($tracking != $shipment['tracking']) ||
              (($order_status == $shipped_option) &&
               empty($shipment['shipped_date']))) {
             $shipment_record = shipment_record_definition();
             $shipment_record['id']['value'] = $shipment['id'];
             if ($tracking != $shipment['tracking'])
                $shipment_record['tracking']['value'] = $tracking;
             if (($order_status == $shipped_option) &&
                 empty($shipment['shipped_date']))
             $shipment_record['shipped_date']['value'] = time();
             if (! $db->update('order_shipments',$shipment_record)) {
                http_response(422,$db->error);   return;
             }
          }
       }
    }
    if ($deleted_shipments) {
       $new_status = get_order_shipped_status($db,$order_id);
       if ($new_status == -1) return;
       $order_record['status']['value'] = $new_status;
    }
    if ($order_type == ORDER_TYPE) {
       if (($new_balance_due > 0) && ($payment_amount > 0)) {
          $new_balance_due -= $payment_amount;
          if ($new_balance_due < 0) $new_balance_due = 0;
          $order_record['balance_due']['value'] = $new_balance_due;
       }
       else if ($old_balance_due != $new_balance_due)
          $order_record['balance_due']['value'] = $new_balance_due;
       if (isset($order_record['external_source']['value']) &&
           (! trim($order_record['external_source']['value'])))
          $order_record['external_source']['value'] = null;
       if (isset($order_record['external_id']['value']) &&
           (! trim($order_record['external_id']['value'])))
          $order_record['external_id']['value'] = null;
    }
    $order_record['updated_date']['value'] = time();
    if ((! empty($order_record['coupon_id']['value'])) &&
        ($order_record['coupon_id']['value'][0] == '~'))
       $order_record['coupon_id']['value'] =
          -intval(substr($order_record['coupon_id']['value'],1));
    if (function_exists('custom_update_order_record'))
       custom_update_order_record($db,$order_record);
    if (! $db->update($orders_table,$order_record)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from order_attributes where parent in (select id from ' .
             'order_items where (parent=?) and (parent_type=?))';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }

    $query = 'select id,product_id,attributes,qty from order_items where ' .
             '(parent=?) and (parent_type=?) order by id';
    $query = $db->prepare_query($query,$order_id,$order_type);
    $item_ids = $db->get_records($query,'id');
    $num_ids = get_form_field('NumIds');
    for ($loop = 0;  $loop < $num_ids;  $loop++) {
       $item_id = get_form_field('item_id_'.$loop);
       $product_id = get_form_field('product_id_'.$loop);
       $product_name = get_form_field('product_name_'.$loop);
       if ((! $product_id) && (! $product_name)) continue;
       $item_record = item_record_definition();
       $item_record['parent_type']['value'] = $order_type;
       if ($item_id) $item_record['id']['value'] = $item_id;
       $item_record['parent']['value'] = $order_id;
       $item_record['parent_type']['value'] = $order_type;
       $custom_attrs = get_form_field('product_custom_attrs_'.$loop);
       if (isset($skip_update_attributes) && $skip_update_attributes)
          $custom_attrs = true;
       $item_record['product_id']['value'] = $product_id;
       $item_record['product_name']['value'] = $product_name;
       $cost = get_form_field('product_cost_'.$loop);
       if ($cost !== null)
          $item_record['cost']['value'] = $cost;
       else unset($item_record['cost']['value']);
       $old_qty = get_form_field('old_product_qty_'.$loop);
       $qty = get_form_field('product_qty_'.$loop);
       $item_record['qty']['value'] = $qty;
       if (! empty($enable_reorders)) {
          $new_frequency = get_form_field('reorder_frequency_'.$loop);
          $item_record['reorder_frequency']['value'] = $new_frequency;
          $old_frequency = get_form_field('old_reorder_frequency_'.$loop);
          if ($old_frequency != $new_frequency) {
             if (! $new_frequency) $new_frequency = 'null';
             $activity = 'Frequency ('.$old_frequency.'=>'.$new_frequency.')';
          }
          else $activity = null;
       }
       if ($multiple_customer_accounts)
          $item_record['account_id']['value'] =
             get_form_field('account_id_'.$loop);
       $old_attributes = get_form_field('product_attributes_'.$loop);
       if ($product_id && (! $custom_attrs)) {
          $no_options = check_no_option_attributes($db,$product_id);
          $attributes = @Cart::get_attributes($loop);
          if (isset($attributes)) {
             if ($no_options)
                $item_record['attributes']['value'] = implode('|',$attributes);
             else $item_record['attributes']['value'] = implode('-',$attributes);
          }
          else $item_record['attributes']['value'] = '';
          @Cart::load_item_details($product_id,$qty,$attributes,$no_options,
                                   $item_record['attribute_names']['value'],
                                   $item_record['attribute_prices']['value'],
                                   $item_record['price']['value'],
                                   $item_record['part_number']['value'],
                                   $item_record['flags']['value']);
       }
       else {
          $item_record['attributes']['value'] = '';
          $item_record['flags']['value'] = 0;
          unset($item_record['part_number']['value']);
       }
       if (empty($item_record['attributes']['value'])) {
          if ($old_attributes)
             $item_record['attributes']['value'] = $old_attributes;
       }
       if (empty($item_record['attribute_names']['value'])) {
          $field_value = get_form_field('product_attribute_names_'.$loop);
          if ($field_value)
             $item_record['attribute_names']['value'] = $field_value;
       }
       if (empty($item_record['attribute_prices']['value'])) {
          $field_value = get_form_field('product_attribute_prices_'.$loop);
          if ($field_value)
             $item_record['attribute_prices']['value'] = $field_value;
       }
       update_attribute_prices($item_record);
       if (empty($item_record['flags']['value'])) {
          $field_value = get_form_field('item_flags_'.$loop);
          if ($field_value)
             $item_record['flags']['value'] = $field_value;
       }
       $part_number = get_form_field('part_number_'.$loop);
       if ($part_number !== null)
          $item_record['part_number']['value'] = $part_number;
       $price = parse_amount(get_form_field('product_price_'.$loop));
       if ($round_cents) {
          if (($cart->currency != 'USD') && ($cart->exchange_rate !== null) &&
              ($cart->exchange_rate != 0.0)) {
             $price = $price * $cart->exchange_rate;
             $price = floor($price / $this->exchange_rate);
          }
          else $price = floor($price);
       }
       $item_record['price']['value'] = $price;
       if (isset($item_fields)) {
          foreach ($item_fields as $field_name => $field)
             $item_record[$field_name]['value'] =
                get_form_field($field_name.'_'.$loop);
       }
       if (function_exists('update_add_edit_order_item'))
          update_add_edit_order_item($item_record);
       if ($item_id) {
          if (! $db->update('order_items',$item_record)) {
             http_response(422,$db->error);   return;
          }
          unset($item_ids[$item_id]);
          if ($features & MAINTAIN_INVENTORY) {
             if (! update_order_inventory($db,$product_id,$old_attributes,
                      $old_qty,$item_record['attributes']['value'],
                      $item_record['qty']['value'])) return;
          }
       }
       else {
          if (! $db->insert('order_items',$item_record)) {
             http_response(422,$db->error);   return;
          }
          if ($features & MAINTAIN_INVENTORY) {
             if (! update_order_inventory($db,$product_id,null,null,
                      $item_record['attributes']['value'],
                      $item_record['qty']['value'])) return;
          }
       }
       if ((! empty($enable_reorders)) && $activity) {
          if (! isset($auto_reorder_label)) $auto_reorder_label = 'Reorder';
          if ($new_frequency == 'null')
             $activity = 'Cancelled '.$auto_reorder_label.' for Order #' .
                         $order_id;
          else $activity = 'Updated '.$auto_reorder_label.' '.$activity .
                           ' for Order #'.$order_id;
          log_activity($activity.' for Customer #'.$customer_id);
          write_customer_activity($activity.' by ' .
                                  get_customer_activity_user($db),
                                  $customer_id,$db);
       }
    }
    if (count($item_ids) > 0) {
       $ids = array();
       foreach ($item_ids as $item_id => $row) {
          $ids[] = $item_id;
          if (($order_type == ORDER_TYPE) && ($features & MAINTAIN_INVENTORY)) {
             if (! update_order_inventory($db,$row['product_id'],
                      $row['attributes'],$row['qty'],null,null)) return;
          }
       }
       $query = 'delete from order_items where id in (?)';
       $query = $db->prepare_query($query,$ids);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }

    $billing_record = billing_record_definition();
    $db->parse_form_fields($billing_record);
    $billing_record['id']['value'] = get_form_field('billing_id');
    if ($billing_record['country']['value'] == 43)
       $billing_record['state']['value'] = get_form_field('canada_province');
    else if ($billing_record['country']['value'] != 1)
       $billing_record['state']['value'] = get_form_field('province');
    if (! $billing_record['id']['value']) {
       unset($billing_record['id']['value']);
       $billing_record['parent']['value'] = $order_id;
       $billing_record['parent_type']['value'] = $order_type;
       if (! $db->insert('order_billing',$billing_record)) {
          http_response(422,$db->error);   return;
       }
    }
    else if (! $db->update('order_billing',$billing_record)) {
       http_response(422,$db->error);   return;
    }
    $shipping_record = shipping_record_definition();
    $db->parse_form_fields($shipping_record);
    $shipping_record['id']['value'] = get_form_field('shipping_id');
    $shipping_record['profilename']['value'] = get_form_field('ship_profilename');
    $shipping_record['company']['value'] = get_form_field('ship_company');
    $shipping_record['address1']['value'] = get_form_field('ship_address1');
    $shipping_record['address2']['value'] = get_form_field('ship_address2');
    $shipping_record['city']['value'] = get_form_field('ship_city');
    $shipping_record['country']['value'] = get_form_field('ship_country');
    if ($shipping_record['country']['value'] == 43)
       $shipping_record['state']['value'] = get_form_field('ship_canada_province');
    else if ($shipping_record['country']['value'] != 1)
       $shipping_record['state']['value'] = get_form_field('ship_province');
    else $shipping_record['state']['value'] = get_form_field('ship_state');
    $shipping_record['zipcode']['value'] = get_form_field('ship_zipcode');
    if (function_exists('custom_update_order_shipping_record'))
       custom_update_order_shipping_record($db,$shipping_record);
    if (! $shipping_record['id']['value']) {
       unset($shipping_record['id']['value']);
       $shipping_record['parent']['value'] = $order_id;
       $shipping_record['parent_type']['value'] = $order_type;
       if (! $db->insert('order_shipping',$shipping_record)) {
          http_response(422,$db->error);   return;
       }
    }
    else if (! $db->update('order_shipping',$shipping_record)) {
       http_response(422,$db->error);   return;
    }
    log_activity('Updated '.$order_label.' #'.$order_id);

    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) http_response(428,$db->error);
       else http_response(428,$error_msg);
       return;
    }
    if (get_form_field('send_emails') == 'on') $send_emails = true;
    else $send_emails = false;
    $copying = get_form_field('copying');
    if ($copying && $send_emails) {
       if ($customer) {}
       else if ($order->customer_id)
          $customer = load_customer($db,$order->customer_id,$error_msg);
       else $customer = new Customer($db,null,true);
       send_new_order_notifications($db,$order->customer_id,$order,$customer);
    }
    $old_status = get_form_field('OldStatus');
    $new_status = $order->info['status'];
    $new_shipment = get_form_field('NewShipment');
    if ($order_type != ORDER_TYPE) {}
    else if (($new_status != $old_status) || $new_shipment) {
       if (($new_status == $shipped_option) || $new_shipment) {
          $query = 'select count(id) as num_shipments from order_shipments ' .
                   'where (parent=?) and (parent_type=?)';
          $query = $db->prepare_query($query,$order_id,$order_type);
          $row = $db->get_record($query);
          if (empty($row['num_shipments'])) {
             if ($new_shipment) $order->info['shipped_date'] = null;
             if (! create_order_shipment($db,$order->info,
                                         $order->items,$error)) {
                http_response(428,$db->error);   return;
             }
          }
       }
       if (! change_order_status($old_status,$new_status,$db,$order)) return;
    }
    else if ($send_emails) {
       if (($new_status == 0) && (! $copying)) {
          if ($customer) {}
          else if ($order->customer_id)
             $customer = load_customer($db,$order->customer_id,$error_msg);
          else $customer = new Customer($db,null,true);
          send_new_order_notifications($db,$order->customer_id,$order,$customer);
       }
       else if (! change_order_status($old_status,$new_status,$db,$order))
          return;
    }
    require_once '../engine/modules.php';
    switch ($order_type) {
       case ORDER_TYPE: $event = 'update_order';    break;
       case QUOTE_TYPE: $event = 'update_quote';   break;
       case INVOICE_TYPE: $event = 'update_invoice';   break;
       case SALESORDER_TYPE: $event = 'update_salesorder';   break;
    }
    if (module_attached($event)) {
       $order_payments = load_order_payments($order);
       $order_shipments = load_order_shipments($order);
       update_order_info($db,$order->info,$order->billing,$order->shipping,
                         $order->items,$order_payments,$order_shipments);
       if (! call_module_event($event,array($db,$order->info,
                $order->billing,$order->shipping,$order->items,
                $order_payments,$order_shipments),null,true)) {
          http_response(428,get_module_errors());   return;
       }
    }
    http_response(201,$order_label.' Updated');
}

function restore_inventory($order_id,$db)
{
    global $order_label;

    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(422,$error_msg);
       return false;
    }
    $coupon_amount = get_row_value($order->info,'coupon_amount');
    if (isset($order->info['coupon_id']) && ($order->info['coupon_id'] != '')) {
       $query = 'update coupons set qty_used=ifnull(qty_used-1,0) where id=?';
       $query = $db->prepare_query($query,$order->info['coupon_id']);
       $db->log_query($query);
       $result = $db->query($query);
       if (! $result) {
          http_response(422,$db->error);   return false;
       }
    }

    $gift_amount = get_row_value($order->info,'gift_amount');
    if (isset($order->info['gift_id']) && ($order->info['gift_id'] != '')) {
       $query = 'update coupons set balance=balance+? where id=?';
       $query = $db->prepare_query($query,$gift_amount,$order->info['gift_id']);
       $db->log_query($query);
       $result = $db->query($query);
       if (! $result) {
          http_response(422,$db->error);   return false;
       }
    }

    $features = get_cart_config_value('features',$db);
    if ($features & MAINTAIN_INVENTORY) {
       foreach ($order->items as $id => $order_item) {
          if (! update_order_inventory_record($db,$order_item['product_id'],
                   $order_item['attributes'],$order_item['qty'],true))
             return false;
       }
    }
    log_activity('Restored Inventory for '.$order_label.' #'.$order_id);
    return true;
}

function delete_order()
{
    global $order_type,$orders_table,$order_label;

    $id = get_form_field('id');
    $db = new DB;
    $query = 'select * from '.$orders_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $order_info = $db->get_record($query);
    if (! delete_order_record($db,$order_info,$error,null,$order_type)) {
       http_response(422,$error);   return;
    }
    http_response(201,$order_label.' Deleted');
    log_activity('Deleted '.$order_label.' #'.$id);
}

function add_partial_shipment()
{
    $order_id = get_form_field('id');
    $db = new DB;
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    if (! $order->items) {
       process_error('Order has no items to ship',0);   return;
    }
    $query = 'select * from order_shipment_items where parent in ' .
             '(select id from order_shipments where parent=?)';
    $query = $db->prepare_query($query,$order_id);
    $shipment_items = $db->get_records($query);
    if ((! $shipment_items) && isset($db->error)) {
       process_error('Database Error: '.$db->error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $head_block = '<style>table.fieldtable tr { height: 21px; }</style>';
    $dialog->add_head_line($head_block);
    $dialog->set_onload_function('add_partial_shipment_onload();');
    $dialog->set_field_padding(2);
    $dialog->set_body_id('add_partial_shipment');
    $dialog->set_help('add_partial_shipment');
    $dialog->start_body('Add Partial Shipment');
    $dialog->set_button_width(90);
    $dialog->start_button_column();
    $dialog->add_button('Add','images/AddPartialShipment.png',
                        'process_add_partial_shipment();');
    $dialog->add_button('Cancel','images/Update.png',
                        'cancel_partial_shipment();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','AddPartialShipment');
    $dialog->add_hidden_field('id',$order_id);

    $dialog->write("<div class=\"add_edit_order_box\" style=\"width: 688px;\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">Items</div>\n");
    $dialog->write("<table cellspacing=\"2\" cellpadding=\"0\" " .
                   "id=\"ship_items_table\" class=\"add_edit_order_product_table " .
                   "fieldtable\" width=\"675\">\n");
    $dialog->write("<tr><th class=\"fieldprompt\" width=\"445\" " .
                   "style=\"text-align: left;\" nowrap>Product Name</th>" .
                   "<th class=\"fieldprompt\" width=\"75\">Order Qty</th>" .
                   "<th class=\"fieldprompt\" width=\"75\">Shipped Qty</th>" .
                   "<th class=\"fieldprompt\" width=\"75\">Qty to Ship</th>" .
                   "</tr>\n");

    $index = 0;
    foreach ($order->items as $item_id => $order_item) {
       $shipped_qty = 0;
       if ($shipment_items) foreach ($shipment_items as $shipped_item) {
          if ($shipped_item['item_id'] == $item_id)
             $shipped_qty += $shipped_item['qty'];
       }
       $dialog->write("<tr valign=\"middle\">\n<td>");
       write_form_value($order_item['product_name']);
       $dialog->write("</td>\n");
       $dialog->write("<td align=\"center\">".$order_item['qty'] .
                      "</td>\n");
       $dialog->write("<td align=\"center\">".$shipped_qty .
                      "</td>\n");
       $dialog->write("<td align=\"center\">");
       $max_qty = $order_item['qty'] - $shipped_qty;
       $dialog->add_hidden_field('maxqty_'.$item_id,$max_qty);
       $dialog->write('<input type="text" class="text" name="qty_'.$item_id .
                      '" id="qty_'.$item_id.'" size="1" value="" ' .
                      'onBlur="update_partial_shipping_info();">'."\n");
       $dialog->write("</td></tr>\n");
       $index++;
    }

    $dialog->write("</table>");
    $dialog->write("</div>\n");

    $dialog->write("<div class=\"add_edit_order_box\" style=\"width: 688px;\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">Shipping Information" .
                   "</div>\n");
    $dialog->set_field_padding(4);
    $dialog->start_field_table();
    $dialog->write("<tr><td class=\"fieldprompt\" nowrap>" .
                   "Shipping_Carrier:</td>\n");
    $dialog->write("<td id=\"shipping_carrier_cell\">\n");
    $dialog->add_hidden_field('shipping_carrier',$order->info);
    $dialog->write("</td>\n");
    $dialog->write("<td class=\"fieldprompt\" style=\"padding-left: 20px;\" nowrap>" .
                   "Shipping Method:</td>\n");
    $dialog->write("<td id=\"shipping_method_cell\">\n");
    $dialog->add_hidden_field('shipping_method',$order->info);
    $dialog->write("</td>\n");
    $dialog->write("<td class=\"fieldprompt\" style=\"padding-left: 20px;\" nowrap>" .
                   "Shipping Cost:</td>\n");
    $dialog->write("<td><input type=\"text\" class=\"text\" name=\"shipping\" " .
                   "size=\"5\" value=\"\"></td></tr>\n");
    $dialog->write("<tr><td class=\"fieldprompt\" nowrap>" .
                   "Tracking #:</td>\n");
    $dialog->write("<td colspan=\"5\"><input type=\"text\" class=\"text\" " .
                   "name=\"tracking\" size=\"40\" value=\"");
    write_form_value(get_row_value($order->info,'tracking'));
    $dialog->write("\"></td></tr>\n");
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    $dialog->end_form();
    $dialog->end_body();
}

function process_add_partial_shipment()
{
    global $order_label,$new_order_option,$shipped_option;
    global $partial_shipped_option;

    $db = new DB;
    $order_id = get_form_field('id');
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(410,'Order Not Found');
       return;
    }
    if (! $order->items) {
       http_response(406,'Order has no items to ship');   return;
    }
    $shipment_record = shipment_record_definition();
    $shipment_record['parent']['value'] = $order_id;
    $shipment_record['shipping']['value'] = get_form_field('shipping');
    $shipment_record['shipped_date']['value'] = time();
    $shipping_carrier = get_form_field('shipping_carrier');
    $shipment_record['shipping_carrier']['value'] = $shipping_carrier;
    $order->info['shipping_carrier'] = $shipping_carrier;
    $shipping_method = get_form_field('shipping_method');
    $shipment_record['shipping_method']['value'] = $shipping_method;
    $order->info['shipping_method'] = $shipping_method;
    $tracking = get_form_field('tracking');
    $shipment_record['tracking']['value'] = $tracking;
    $order->info['tracking'] = $tracking;
    if (! $db->insert('order_shipments',$shipment_record)) {
       http_response(422,$db->error);   return;
    }
    $shipment_id = $db->insert_id();

    $item_record = shipment_item_record_definition();
    $item_record['parent']['value'] = $shipment_id;
    foreach ($order->items as $index => $item_info) {
       $qty_to_ship = get_form_field('qty_'.$item_info['id']);
       if ($qty_to_ship) {
          $item_record['item_id']['value'] = $item_info['id'];
          $item_record['qty']['value'] = $qty_to_ship;
          if (! $db->insert('order_shipment_items',$item_record)) {
             http_response(422,$db->error);   return;
          }
       }
       else unset($order->items[$index]);
    }

    $new_status = get_order_shipped_status($db,$order_id);
    if ($new_status == -1) return;
    $query = 'update orders set status=? where id=?';
    $query = $db->prepare_query($query,$new_status,$order_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }

    require_once '../engine/email.php';
    if (! isset($shipped_option)) $shipped_option = 1;
    if (! isset($partial_shipped_option)) $partial_shipped_option = 4;
    if ($new_status == $shipped_option) $email_template = SHIP_NOTIFY_EMAIL;
    else if (($new_status == $partial_shipped_option) &&
             defined('PARTIAL_SHIP_NOTIFY_EMAIL'))
       $email_template = PARTIAL_SHIP_NOTIFY_EMAIL;
    else $email_template = 0;

    if ($email_template != 0) {
       $notify_flags = get_cart_config_value('notifications',$db);
       if ((! ($notify_flags & NOTIFY_SHIPPED)) ||
           (isset($order->info['external_source']) &&
            (substr($order->info['external_source'],0,6) == 'Amazon')))
          $email_template = 0;
    }
    if ($email_template != 0) {
       $email = new Email($email_template,array('order' => 'obj',
                                                'order_obj' => $order));
       if (! $email->send()) {
          log_error($email->error);   http_response(422,$email->error);
          return false;
       }
       if (! empty($order->customer_id))
          write_customer_activity($email->activity,$order->customer_id,$db);
    }

    http_response(201,'Partial Shipment Added');
    log_activity('Added Partial Shipment #'.$shipment_id.' to '.$order_label .
                 ' #'.$order_id);
}

function lookup_partial_shipping()
{
    require_once 'cart-public.php';

    $shipping_module_labels = array();
    call_shipping_event('module_labels',array(&$shipping_module_labels));
    $db = new DB;
    $order_id = get_form_field('id');
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) $error_msg = $db->error;
       print "shipping_error = '".str_replace("'","\\'",$error_msg)."';\n";
       return;
    }
    $customer_id = get_row_value($order->info,'customer_id');
    $customer = new Customer($db,$customer_id,true);
    $customer->shipping = $order->shipping;
    $customer->shipping_country = $order->shipping['country'];
    $cart = new Cart($db,-1,$customer_id,true);
    $cart->info = $order->info;
    $cart->customer = $customer;
    $cart->items = $order->items;
    foreach ($cart->items as $index => $item_info) {
       $qty_to_ship = get_form_field('qty_'.$item_info['id']);
       if (! $qty_to_ship) unset($cart->items[$index]);
       else $cart->items[$index]['qty'] = $qty_to_ship;
    }
    print 'shipping_options = [';
    $null_order = null;
    $cart->internal_cart = true;
    if (function_exists('check_shipping_module'))
       check_shipping_module($cart,$customer,$null_order);
    $cart->load_shipping_options($customer);
    $first_option = true;
    foreach ($cart->shipping_options as $shipping_option) {
       if ($first_option) $first_option = false;
       else print ',';
       $shipping_label = $shipping_option[3];
       $label_prefix = $shipping_module_labels[$shipping_option[0]].' ';
       $prefix_length = strlen($label_prefix);
       if (substr($shipping_label,0,$prefix_length) == $label_prefix)
          $shipping_label = substr($shipping_label,$prefix_length);
       $option_string = $shipping_option[0].'|'.$shipping_option[1].'|' .
             $shipping_option[2].'|'.$shipping_label.'|' .
             $shipping_option[4];
       $option_string = str_replace("'","\\'",$option_string);
       print "\n  '".$option_string."'";
    }
    print "\n];\n";
    if (isset($cart->error))
       print "shipping_error = '".str_replace("'","\\'",$cart->error)."';\n";
    foreach ($shipping_module_labels as $module_id => $module_label)
       print "shipping_modules['".$module_id."'] = '".$module_label."';\n";
}

function copy_order_record($id,$from_order_type,$to_order_type,$partial=false)
{
    global $order_type,$orders_table,$order_label;
    global $custom_order_label,$copied_order_status,$base_order_number;
   
    $order_type = $from_order_type;
    switch ($from_order_type) {
       case ORDER_TYPE:
          if (isset($custom_order_label)) $order_label = $custom_order_label;
          else $order_label = 'Order';
          break;
       case QUOTE_TYPE:
          $order_label = 'Quote';   break;
       case INVOICE_TYPE:
          $order_label = 'Invoice';   break;
       case SALESORDER_TYPE:
          $order_label = 'Sales Order';   break;
    }
    switch ($to_order_type) {
       case ORDER_TYPE:
          if (isset($custom_order_label))
             $to_order_label = $custom_order_label;
          else $to_order_label = 'Order';
          $to_orders_table = 'orders';   $date_field = 'order_date';
          $order_record = orders_record_definition();
          if ($from_order_type == QUOTE_TYPE)
             $order_record['quote_id']['value'] = $id;
          break;
       case QUOTE_TYPE:
          $to_order_label = 'Quote';   $to_orders_table = 'quotes';
          $date_field = 'quote_date';
          $order_record = quotes_record_definition();   break;
       case INVOICE_TYPE:
          $to_order_label = 'Invoice';   $to_orders_table = 'invoices';
          $date_field = 'invoice_date';
          $order_record = invoices_record_definition();
          if ($from_order_type == QUOTE_TYPE)
             $order_record['quote_id']['value'] = $id;
          else if (($from_order_type == ORDER_TYPE) ||
                   ($from_order_type == SALESORDER_TYPE))
             $order_record['order_id']['value'] = $id;
          break;
       case SALESORDER_TYPE:
          $to_order_label = 'Sales Order';   $to_orders_table = 'sales_orders';
          $date_field = 'order_date';
          $order_record = salesorders_record_definition();
          if ($from_order_type == QUOTE_TYPE)
             $order_record['quote_id']['value'] = $id;
          break;
    }

    $new_status = 0;
    if (($to_order_type == ORDER_TYPE) && isset($copied_order_status))
       $new_status = $copied_order_status;
    $db = new DB;
    $order = load_order($db,$id,$error_msg);
    if (! $order) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(410,$order_label.' Not Found');
       return null;
    }
    $order_number_is_id = true;   $maintain_inventory = false;
    if ($to_order_type == ORDER_TYPE) {
       $features = get_cart_config_value('features',$db);
       if ($features & ORDER_PREFIX) {
          $order_number_is_id = false;
          $order_prefix = get_cart_config_value('orderprefix',$db);
       }
       else if ($features & (ORDER_PREFIX_ID|ORDER_BASE_ID))
          $order_prefix = get_cart_config_value('orderprefix',$db);
       if ($features & MAINTAIN_INVENTORY) $maintain_inventory = true;
    }
    else $features = null;
    $current_time = time();
    foreach ($order->info as $field_name => $field_value) {
       if ($field_name == 'id') continue;
       if (! isset($order_record[$field_name])) continue;
       if (substr($field_name,0,7) == 'coupon_') continue;
       if (substr($field_name,0,4) == 'fee_') continue;
       if (substr($field_name,0,9) == 'discount_') continue;
       if (substr($field_name,0,5) == 'gift_') continue;
       if (substr($field_name,-5) == '_date') continue;
       if ($field_name == 'check_number') continue;
       if ($field_name == 'purchase_order') continue;
       if ($field_name == 'comments') continue;
       if ($field_name == 'notes') continue;
       if ($field_name == 'balance_due') continue;
       $order_record[$field_name]['value'] = $field_value;
    }
    $order_record['status']['value'] = $new_status;
    if (! $order_number_is_id)
       $order_record['order_number']['value'] =
          $order_prefix.$order->info['customer_id'].'-'.$current_time;
    $order_record[$date_field]['value'] = $current_time;
    if ($to_order_type == ORDER_TYPE)
       $order_record['balance_due']['value'] = $order_record['total']['value'];
    if (! $db->insert($to_orders_table,$order_record)) {
       http_response(422,$db->error);   return false;
    }
    $order_id = $db->insert_id();
    if (($to_order_type == ORDER_TYPE) && $order_number_is_id) {
       if ($features & ORDER_BASE_ID)
          $order_number = intval($order_prefix) + $order_id;
       else if (empty($base_order_number)) $order_number = $order_id;
       else $order_number = $base_order_number + $order_id;
       if ($features & ORDER_PREFIX_ID)
          $order_number = $order_prefix.$order_number;
       $query = 'update orders set order_number=? where id=?';
       $query = $db->prepare_query($query,$order_number,$order_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return null;
       }
    }
    $billing_record = billing_record_definition();
    foreach ($order->billing as $field_name => $field_value)
       $billing_record[$field_name]['value'] = $field_value;
    unset($billing_record['id']['value']);
    $billing_record['parent']['value'] = $order_id;
    $billing_record['parent_type']['value'] = $to_order_type;
    if (! $db->insert('order_billing',$billing_record)) {
       http_response(422,$db->error);   return null;
    }
    $shipping_record = shipping_record_definition();
    foreach ($order->shipping as $field_name => $field_value)
       $shipping_record[$field_name]['value'] = $field_value;
    unset($shipping_record['id']['value']);
    $shipping_record['parent']['value'] = $order_id;
    $shipping_record['parent_type']['value'] = $to_order_type;
    if (! $db->insert('order_shipping',$shipping_record)) {
       http_response(422,$db->error);   return null;
    }
    $item_record = item_record_definition();
    $item_record['parent']['value'] = $order_id;
    $item_record['parent_type']['value'] = $to_order_type;
    foreach ($order->items as $item_id => $order_item) {
       foreach ($order_item as $field_name => $field_value) {
          if (($field_name == 'id') || ($field_name == 'parent') ||
              ($field_name == 'parent_type')) continue;
          if ($field_name == 'attribute_array') continue;
          $item_record[$field_name]['value'] = $field_value;
       }
       if ($partial) {
          $qty = get_form_field('qty_'.$item_id);
          if (! $qty) {
             unset($item_record['qty']['value']);   continue;
          }
          $item_record['qty']['value'] = $qty;
       }
       if ($to_order_type == INVOICE_TYPE)
          $item_record['related_id']['value'] = $item_id;
       if (! $db->insert('order_items',$item_record)) {
          http_response(422,$db->error);   return null;
       }
    }
    if ($to_order_type == INVOICE_TYPE) {
       $order_type = $to_order_type;   init_order_type();
       if (! update_order_totals($db,$order_id,true)) return null;
       $order_type = $from_order_type;   init_order_type();
    }
    if ($maintain_inventory) {
       foreach ($order->items as $index => $order_item) {
          if ((! isset($order_item['product_id'])) ||
              (! $order_item['product_id'])) continue;
          $qty = intval($order_item['qty']);
          $query = 'update product_inventory set qty=ifnull(qty-?,-?) ' .
                   'where (parent=?)';
          if (! empty($order_item['attributes'])) {
             $query .= ' and (attributes=?)';
             $query = $db->prepare_query($query,$qty,$qty,
                         $order_item['product_id'],$order_item['attributes']);
          }
          else {
             $query .= ' and ((attributes="") or isnull(attributes))';
             $query = $db->prepare_query($query,$qty,$qty,
                                         $order_item['product_id']);
          }
          $db->log_query($query);
          if (! $db->query($query)) {
             http_response(422,$db->error);   return null;
          }
          if (using_linked_inventory($db,$features))
             update_linked_inventory($db,null,null,$order_item['product_id'],
                                     $order_item['attributes']);
       }
    }
    if (($from_order_type == QUOTE_TYPE) &&
        (($to_order_type == ORDER_TYPE) ||
         ($to_order_type == SALESORDER_TYPE))) {
       $query = 'update quotes set order_id=? where id=?';
       $query = $db->prepare_query($query,$order_id,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return null;
       }
    }
    return $order_id;
}

function copy_order()
{
    global $order_label,$order_type;

    $id = get_form_field('id');
    $order_id = copy_order_record($id,$order_type,$order_type);
    if (! $order_id) return;
    print 'order_id = '.$order_id.';';
    log_activity('Copied '.$order_label.' #'.$id.' to '.$order_label.' #' .
                 $order_id);
}

function convert_quote()
{
    global $custom_order_label,$enable_salesorders;

    $id = get_form_field('id');
    if (! empty($enable_salesorders)) {
       $to_order_type = SALESORDER_TYPE;   $label = 'Sales Order';
    }
    else {
       $to_order_type = ORDER_TYPE;
       if (isset($custom_order_label)) $label = $custom_order_label;
       else $label = 'Order';
    }
    $order_id = copy_order_record($id,QUOTE_TYPE,$to_order_type);
    if (! $order_id) return;
    print 'order_id = '.$order_id.';';
    log_activity('Converted Quote #'.$id.' to '.$label.' #'.$order_id);
}

function generate_partial_invoice()
{
    global $order_type;

    $order_id = get_form_field('id');
    $db = new DB;
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    if (! $order->items) {
       process_error('Order has no items to generate an invoice for',0);   return;
    }
    foreach ($order->items as $item_id => $order_item)
       $order->items[$item_id]['invoiced'] = 0;
    $query = 'select * from order_items where (parent_type=?) and ' .
             '(parent in (select id from invoices where ';
    if ($order_type == QUOTE_TYPE) $query .= 'quote_id=?))';
    else $query .= 'order_id=?))';
    $query = $db->prepare_query($query,INVOICE_TYPE,$order_id);
    $invoice_items = $db->get_records($query);
    if (! $invoice_items) {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,0);   return;
       }
    }
    else {
       foreach ($invoice_items as $invoice_item) {
          $item_id = $invoice_item['related_id'];
          if ((! $item_id) || (! isset($order->items[$item_id]))) continue;
          $order->items[$item_id]['invoiced'] += $invoice_item['qty'];
       }
       $all_invoiced = true;
       foreach ($order->items as $item_id => $order_item) {
          if ($order_item['invoiced'] < $order_item['qty']) {
             $all_invoiced = false;   break;
          }
       }
       if ($all_invoiced) {
          process_error('All Items have been invoiced',0);   return;
       }
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $head_block = '<style>table.fieldtable tr { height: 21px; }</style>';
    $dialog->add_head_line($head_block);
    $dialog->set_field_padding(2);
    $dialog->set_body_id('generate_invoice');
    $dialog->set_help('generate_invoice');
    $dialog->start_body('Generate Invoice');
    $dialog->set_button_width(90);
    $dialog->start_button_column();
    $dialog->add_button('Generate','images/Update.png',
                        'process_generate_invoice();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','GenerateInvoice');
    $dialog->add_hidden_field('id',$order_id);
    $dialog->add_hidden_field('ordertype',$order_type);

    $dialog->write("<div class=\"add_edit_order_box\" style=\"width: 688px;\">\n");
    $dialog->write("<div class=\"add_edit_order_legend\">Items</div>\n");
    $dialog->write("<table cellspacing=\"2\" cellpadding=\"0\" " .
                   "id=\"ship_items_table\" class=\"add_edit_order_product_table " .
                   "fieldtable\" width=\"675\">\n");
                  $dialog->write("<tr><th class=\"fieldprompt\" width=\"435\" " .
                   "style=\"text-align: left;\" nowrap>Product Name</th>" .
                   "<th class=\"fieldprompt\" width=\"75\">Order Qty</th>" .
                   "<th class=\"fieldprompt\" width=\"80\">Invoiced Qty</th>" .
                   "<th class=\"fieldprompt\" width=\"80\">Qty to Invoice</th>" .
                   "</tr>\n");

    $index = 0;
    foreach ($order->items as $item_id => $order_item) {
       $qty_to_invoice = $order_item['qty'] - $order_item['invoiced'];
       if ($qty_to_invoice < 0) $qty_to_invoice = 0;
       $dialog->write("<tr valign=\"middle\">\n<td>");
       write_form_value($order_item['product_name']);
       $dialog->write("</td>\n");
       $dialog->write("<td align=\"center\">".$order_item['qty'] .
                      "</td>\n");
       $dialog->write("<td align=\"center\">".$order_item['invoiced'] .
                      "</td>\n");
       $dialog->write("<td align=\"center\">");
       if ($qty_to_invoice == 0) $dialog->write('&nbsp;');
       else {
          $dialog->add_hidden_field('maxqty_'.$item_id,$qty_to_invoice);
          $dialog->write('<input type="text" class="text" name="qty_'.$item_id .
                         '" id="qty_'.$item_id.'" size="1" value="' .
                         $qty_to_invoice.'">'."\n");
       }
       $dialog->write("</td></tr>\n");
       $index++;
    }

    $dialog->write("</table>");
    $dialog->write("</div>\n");

    $dialog->end_form();
    $dialog->end_body();
}

function process_generate_partial_invoice()
{
    global $order_type,$order_label;

    $id = get_form_field('id');
    $invoice_id = copy_order_record($id,$order_type,INVOICE_TYPE,true);
    if (! $invoice_id) return;
    print 'invoice_id = '.$invoice_id.';';
    log_activity('Generated Invoice #'.$invoice_id.' from '.$order_label .
                 ' #'.$id);
}

function generate_invoice()
{
    global $order_type,$order_label;

    $id = get_form_field('id');
    $invoice_id = copy_order_record($id,$order_type,INVOICE_TYPE);
    if (! $invoice_id) return;
    print 'invoice_id = '.$invoice_id.';';
    log_activity('Generated Invoice #'.$invoice_id.' from '.$order_label .
                 ' #'.$id);
}

function add_reorder()
{
    global $order_type,$orders_table,$order_label,$base_order_number;

    $id = get_form_field('id');
    $db = new DB;

    if ($order_type == ORDER_TYPE) {
       $features = get_cart_config_value('features',$db);
       if ($features & ORDER_PREFIX) $order_number_is_id = false;
       else $order_number_is_id = true;
    }
    else $order_number_is_id = true;
    $query = 'select * from '.$orders_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,$order_label.' not found');
       return;
    }
    $db->decrypt_record($orders_table,$row);
    $orders_record = orders_record_definition();
    $current_time = time();
    foreach ($orders_record as $field_name => $field_def) {
       switch ($field_name) {
          case 'id': break;
          case 'order_number': 
             if ($order_number_is_id)
                $orders_record[$field_name]['value'] = '';
             else $orders_record[$field_name]['value'] =
                     get_cart_config_value('orderprefix',$db) .
                     $row['customer_id'].'-'.$current_time;
             break;
          case 'reorder_id':
             $orders_record[$field_name]['value'] = $id;   break;
          case 'order_date':
             $orders_record[$field_name]['value'] = $current_time;   break;
          case 'purchase_order':
          case 'updated_date':
             $orders_record[$field_name]['value'] = '';   break;
          default: $orders_record[$field_name]['value'] = $row[$field_name];
       }
    }
    if (! $db->insert($orders_table,$orders_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }
    $new_id = $db->insert_id();
    if (($order_type == ORDER_TYPE) && $order_number_is_id) {
       if ($features & ORDER_BASE_ID)
          $order_number = intval(get_cart_config_value('orderprefix')) +
                          $new_id;
       else if (empty($base_order_number)) $order_number = $new_id;
       else $order_number = $base_order_number + $new_id;
       if ($features & ORDER_PREFIX_ID)
          $order_number = get_cart_config_value('orderprefix').$order_number;
       $query = 'update orders set order_number=? where id=?';
       $query = $db->prepare_query($query,$order_number,$new_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
    }

    $query = 'select * from order_billing where (parent=?) and ' .
             '(parent_type=?)';
    $query = $db->prepare_query($query,$id,$order_type);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,$order_label.' Billing Record not found');
       return;
    }
    $db->decrypt_record('order_billing',$row);
    $billing_record = billing_record_definition();
    foreach ($billing_record as $field_name => $field_def) {
       if ($field_name == 'id') continue;
       if ($field_name == 'parent') {
          $billing_record[$field_name]['value'] = $new_id;   continue;
       }
       $billing_record[$field_name]['value'] = $row[$field_name];
    }
    if (! $db->insert('order_billing',$billing_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    $query = 'select * from order_shipping where (parent=?) and ' .
             '(parent_type=?)';
    $query = $db->prepare_query($query,$id,$order_type);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,$order_label.' Shipping Record not found');
       return;
    }
    $db->decrypt_record('order_shipping',$row);
    $shipping_record = shipping_record_definition();
    foreach ($shipping_record as $field_name => $field_def) {
       if ($field_name == 'id') continue;
       if ($field_name == 'parent') {
          $shipping_record[$field_name]['value'] = $new_id;   continue;
       }
       $shipping_record[$field_name]['value'] = $row[$field_name];
    }
    if (! $db->insert('order_shipping',$shipping_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    $query = 'select * from order_items where (parent=?) and ' .
             '(parent_type=?) order by id';
    $query = $db->prepare_query($query,$id,$order_type);
    $rows = $db->get_records($query);
    if ((! $rows) && isset($db->error)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }
    if (! empty($rows)) {
       foreach ($rows as $row) {
          $db->decrypt_record('order_items',$row);
          $item_record = item_record_definition();
          foreach ($item_record as $field_name => $field_def) {
             if ($field_name == 'id') continue;
             if ($field_name == 'parent') {
                $item_record[$field_name]['value'] = $new_id;   continue;
             }
             $item_record[$field_name]['value'] = $row[$field_name];
          }
          if (! $db->insert('order_items',$item_record)) {
             http_response(422,'Database Error: '.$db->error);   return;
          }
       }
    }

    http_response(201,'Reorder Added');
    log_activity('Added Reorder #'.$new_id.' from '.$order_label.' #'.$id);
}

function verify_shipping_carrier($shipping_carrier,$db,$use_http_response=false)
{
    if ($shipping_carrier == '') {
       $error = 'No Shipping Carrier assigned to this order';
       if ($use_http_response) http_response(410,$error);
       else print '<h1 align="center">'.$error.'</h1>';
       return false;
    }
    if (! shipping_module_event_exists('verify_shipping_label',
                                       $shipping_carrier)) {
       $error = 'Labels are not available for the Shipping Carrier assigned to ' .
                'this order';
       if ($use_http_response) http_response(410,$error);
       else print '<h1 align="center">'.$error.'</h1>';
       return false;
    }
    $verify_shipping_label = $shipping_carrier.'_verify_shipping_label';
    if (! $verify_shipping_label($db)) {
       $error = 'Labels are not configured for the Shipping Carrier ' .
                'assigned to this order';
       if ($use_http_response) http_response(410,$error);
       else print '<h1 align="center">'.$error.'</h1>';
       return false;
    }
    return true;
}

function shipping_label()
{
    global $order_label,$shipped_option,$order_type;

    if (! isset($shipped_option)) $shipped_option = 1;
    $order_id = get_form_field('id');
    $db = new DB;
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) $error_msg = $db->error;
       print '<h1 align="center">'.$error_msg.'</h1>';
       return;
    }
    $order_shipments = load_order_shipments($order);
    if ($order_shipments) $order_shipment = reset($order_shipments);
    else $order_shipment = null;

    if ($order_shipment)  $shipment_info = $order_shipment;
    else $shipment_info = $order->info;
    $shipping_carrier = get_row_value($shipment_info,'shipping_carrier');
    if (! verify_shipping_carrier($shipping_carrier,$db)) return;

    load_shipping_modules();
    $get_filename = $shipping_carrier.'_get_shipping_label_filename';
    $label_filename = $get_filename($order_id);
    if (! file_exists($label_filename)) {
       if (! $order_shipment) {
          $shipment_info = create_order_shipment($db,$order->info,
                                                 $order->items,$error);
          if (! $shipment_info) {
             print '<h1 align="center">'.$error.'</h1>';   return;
          }
       }
       $generate_shipping_label = $shipping_carrier.'_generate_shipping_label';
       if (! $generate_shipping_label($order,$shipment_info,true,
                                      $label_filename)) {
          log_error($order->error);
          print '<h1 align="center">Unable to Generate Shipping Label: ' .
                $order->error.'</h1>';
          return;
       }
       log_activity('Generated Shipping Label for '.$order_label.' #'.$order_id);
       if (! $order_shipment) {
          if (! update_order_shipment($db,$shipment_info,$error)) {
             print '<h1 align="center">'.$error.'</h1>';   return;
          }
          $query = 'update orders set status=? where id=?';
          $query = $db->prepare_query($query,$shipped_option,$order_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             print '<h1 align="center">'.$db->error.'</h1>';   return;
          }
          $shipment_info['tracking'] = $tracking_num;
          if (! change_order_status($order->info['status'],$shipped_option,$db,
                                    $order)) return;
       }
       require_once '../engine/modules.php';
       switch ($order_type) {
          case ORDER_TYPE: $event = 'update_order';   break;
          case QUOTE_TYPE: $event = 'update_quote';   break;
          case INVOICE_TYPE: $event = 'update_invoice';   break;
          case SALESORDER_TYPE: $event = 'update_salesorder';   break;
       }
       if (module_attached($event)) {
          $order_payments = load_order_payments($order);
          $order_shipments = load_order_shipments($order);
          update_order_info($db,$order->info,$order->billing,$order->shipping,
                            $order->items,$order_payments,$order_shipments);
          if (! call_module_event($event,array($db,$order->info,
                   $order->billing,$order->shipping,$order->items,
                   $order_payments,$order_shipments),null,true)) {
             http_response(422,get_module_errors());   return;
          }
       }
    }
    $print_shipping_label = $shipping_carrier.'_print_shipping_label';
    $print_shipping_label($db,$order,$label_filename);

    log_activity('Viewed Shipping Label for '.$order_label.' #'.$order_id);
}

function cancel_order_shipment()
{
    global $order_label,$file_dir,$order_type;

    $order_id = get_form_field('id');
    $db = new DB;
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(409,$error_msg);
       return;
    }
    $order_shipments = load_order_shipments($order);
    if (! $order_shipments) {
       http_response(409,'This Order has not been shipped');   return;
    }
    $shipment_info = reset($order_shipments);
    $shipping_carrier = get_row_value($shipment_info,'shipping_carrier');
    if (! verify_shipping_carrier($shipping_carrier,$db,true)) return;
    $tracking = get_row_value($shipment_info,'tracking');
    if (empty($tracking)) {
       if (function_exists('custom_cancel_order_shipment') &&
           custom_cancel_order_shipment($order)) {}
       else http_response(406,'No Tracking Information has been generated for this ' .
                          $order_label);
       return;
    }
    if (! shipping_module_event_exists('cancel_shipment',$shipping_carrier)) {
       http_response(410,'Cancel Shipment is not available for that Shipping Carrier');
       return;
    }
    $cancel_shipment = $shipping_carrier.'_cancel_shipment';
    if (! $cancel_shipment($order,$shipment_info)) {
       http_response(422,$order->error);   return;
    }

    $query = 'delete from order_shipments where id=?';
    $query = $db->prepare_query($query,$shipment_info['id']);
    $db->log_query($query);
    if (! $db->query($query)) {
       log_error($query);   http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    switch ($order_type) {
       case ORDER_TYPE: $event = 'update_order';   break;
       case QUOTE_TYPE: $event = 'update_quote';   break;
       case INVOICE_TYPE: $event = 'update_invoice';   break;
       case SALESORDER_TYPE: $event = 'update_salesorder';   break;
    }
    if (module_attached($event)) {
       $order_payments = load_order_payments($order);
       $order_shipments = load_order_shipments($order);
       update_order_info($db,$order->info,$order->billing,$order->shipping,
                         $order->items,$order_payments,$order_shipments);
       if (! call_module_event($event,array($db,$order->info,
                $order->billing,$order->shipping,$order->items,
                $order_payments,$order_shipments),null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }
    $get_filename = $shipping_carrier.'_get_shipping_label_filename';
    $label_filename = $get_filename($order_id);
    if (file_exists($label_filename)) unlink($label_filename);
    if (function_exists('custom_cancel_order_shipment'))
       custom_cancel_order_shipment($order);
    http_response(201,'Shipment Cancelled');
    log_activity('Cancelled Shipment for '.$order_label.' #'.$order_id);
}

function add_order_item()
{
    global $order_label;

    $parent = get_form_field('Order');

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file('orders.js');
    $dialog->set_body_id('add_order_item');
    $dialog->set_help('add_order_item');
    $dialog->start_body('Add '.$order_label.' Item');
    $dialog->start_button_column();
    $dialog->add_button('Add Item','images/AddProduct.png','process_add_order_item();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','EditOrderItem');
    $dialog->start_field_table();
    $dialog->add_hidden_field('parent',$parent);
    $dialog->add_edit_row('Description:','description',get_row_value($row,'description'),35);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_order_item()
{
    global $order_label;

    $db = new DB;
    $item_record = item_record_definition();
    $item_record['parent_type']['value'] = 0;
    $db->parse_form_fields($item_record);
    if (! $db->insert('order_items',$item_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$order_label.' Item Added');
    log_activity('Updated '.$order_label.' Item to '.$order_label .
                 ' #'.$item_record['parent']['value']);
}

function edit_order_item()
{
    global $order_label;
    global $order_label;
    global $name_prompt;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from order_items where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($order_label.' Item not found',0);
       return;
    }
    if (! isset($name_prompt)) $name_prompt = 'Product Name';
    $order_id = get_form_field('Order');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file('orders.js');
    $dialog_title = 'Edit '.$order_label.' Item (#'.$row['id'].')';
    $dialog->set_body_id('edit_order_item');
    $dialog->set_help('edit_order_item');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_order_item();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','EditOrderItem');
    $dialog->start_field_table();
    $dialog->add_hidden_field('Order',$order_id);
    $dialog->add_hidden_field('id',get_row_value($row,'id'));
    $price = get_row_value($row,'price');
    $qty = get_row_value($row,'qty');
    $dialog->add_text_row($name_prompt.':',
                          get_html_product_name(get_row_value($row,'product_name'),
                                                GET_PROD_ADMIN,null,null));
    $dialog->add_text_row('Unit Price:','$'.number_format($price,2));
    $dialog->add_edit_row('Quantity:','qty',$qty,3);
    $dialog->add_text_row('Total:','$'.number_format($price * $qty,2));
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_order_item()
{
    global $order_label;

    $order_id = get_form_field('Order');
    $db = new DB;
    $item_record = item_record_definition();
    $db->parse_form_fields($item_record);
    if (! $db->update('order_items',$item_record)) {
       http_response(422,$db->error);   return;
    }
    if (! update_order_totals($db,$order_id,false)) return;
    http_response(201,$order_label.' Item Updated');
    log_activity('Updated '.$order_label.' Item #'.$item_record['id']['value']);
}

function delete_order_item()
{
    global $order_label;

    $order_id = get_form_field('Order');
    $db = new DB;
    $id = get_form_field('id');
    $item_record = item_record_definition();
    $item_record['id']['value'] = $id;
    if (! $db->delete('order_items',$item_record)) {
       http_response(422,$db->error);   return;
    }
    if (! update_order_totals($db,$id,false)) return;
    http_response(201,$order_label.' Item Deleted');
    log_activity('Deleted '.$order_label.' Item #'.$id);
}

function select_order()
{
    global $order_type,$order_label,$order_status_table;

    $status_values = load_cart_options($order_status_table);
    $frame = get_form_field('frame');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('utility.css');
    $dialog->add_script_file('orders.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('select_'.strtolower($order_label));
    $dialog->set_help('select_'.strtolower($order_label));
    $dialog->start_body('Select '.$order_label);
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button('Select','images/Update.png','select_order();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    add_search_box($dialog,'search_orders','reset_search');
    $dialog->end_button_column();
    $dialog->write("\n          <script>\n");
    $dialog->write('             order_type = '.$order_type.";\n");
    $dialog->write("             var select_frame = '".$frame."';\n");
    $dialog->write('             var order_status_values = [');
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $dialog->write(',');
       if (isset($status_values[$loop]))
          $dialog->write("\"".$status_values[$loop]."\"");
       else $dialog->write("\"\"");
    }
    $dialog->write("];\n");
    $dialog->write("             load_grid(false);\n");
    $dialog->write("          </script>\n");
    $dialog->end_body();
}

function print_order()
{
    global $order_label,$base_url,$docroot,$order_type;

    require_once '../cartengine/cart-public.php';
    require_once '../engine/email.php';

    $template = get_form_field('Template');
    $template = $docroot.'/admin/templates/'.$template.'.html';
    $label = get_form_field('Label');
    if (! file_exists($template)) {
       process_error($label.' Template does not exist',0);
       return;
    }

    $order = new Order;
    $order_id = get_form_field('id');
    if ($order_id) $order_ids = array($order_id);
    else {
       $id_values = get_form_field('ids');
       $order_ids = explode(',',$id_values);
    }
    $template_content = file_get_contents($template);
    if (! $template_content) {
       process_error($label.' Template does not exist',0);
       return;
    }
    $email = new Email(null);
    $email->template_info = array('format'=>HTML_FORMAT);
    $email->scan_variables($template_content);
    $first_order = true;
    $charset = ini_get('default_charset');
    if (! $charset) $charset = 'iso-8859-1';

    print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"" .
          "http://www.w3.org/TR/html4/loose.dtd\">\n";
    print "<html moznomarginboxes mozdisallowselectionprint>\n<head>\n  <title>".$label."s</title>\n";
    print "  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=\"" .
          $charset."\">\n";
    print "  <base href=\"".$base_url."\">\n";
    print "  <link href=\"cart/cart.css?v=".filemtime('../cart/cart.css') .
          "\" rel=\"stylesheet\" type=\"text/css\">\n";
    print "  <style type=\"text/css\">@page{ size: auto; margin: 3mm; }</style>\n";
    print "</head>\n<body>\n";
    print "<table cellpadding=\"0\" cellspacing=\"0\" class=\"invoice_link_table\" width=\"100%\">\n";
    print "  <tbody>\n";
    print "    <tr>\n";
    print "      <td width=\"100%\">&nbsp;</td>\n";
    print "      <td><a href=\"\" onclick=\"window.print(); return false;\">" .
          "<img alt=\"Print This Page\" border=\"0\" src=\"cartimages/print-page.jpg\" " .
          "title=\"Print This Page\" /></a></td>\n";
    print "      <td nowrap=\"nowrap\" style=\"padding-left:5px\" valign=\"middle\">" .
          "<a class=\"invoice_links\" href=\"\" onclick=\"window.print(); return false;\">" .
          "Print Page</a></td>\n";
    print "      <td style=\"padding-left:10px;\"><a href=\"\" onclick=\"window.close(); " .
          "return false;\"><img alt=\"Close This Window\" border=\"0\" src=\"cartimages/close.png\" " .
          "title=\"Close This Window\" /></a></td>\n";
    print "      <td nowrap=\"nowrap\" style=\"padding-left:5px\" valign=\"middle\">" .
          "<a class=\"invoice_links\" href=\"\" onclick=\"window.close(); return false;\">" .
          "Close Window</a></td>\n";
    print "    </tr>\n";
    print "  </tbody>\n";
    print "</table>\n";

    foreach ($order_ids as $order_id) {
       $order->load($order_id);
       load_order_item_product_info($order);
       if (! $first_order)
          print "<div style=\"page-break-before: always;\"></div>\n";
       $packing_slip = $template_content;
       $email->data = array('order' => 'obj','order_obj' => $order);
       if (! $email->load_tables()) {
          process_error('Unable to process '.$label.': '.$email->error,0);
          return;
       }
       $email->replace_variables($packing_slip);
       print $packing_slip;
       $first_order = false;
       log_activity('Generated '.$label.' for '.$order_label.' #'.$order_id);
    }

    print "</body>\n</html>\n";
}

function reset_item_attribute_names()
{
    $db = new DB;
    $query = 'select id,name,display_name from attributes';
    $attrs = $db->get_records($query,'id');
    if (! $attrs) {
       print "Database Error: ".$db->error."<br>\n";   return;
    }
    $query = 'select id,parent,name from attribute_options';
    $options = $db->get_records($query,'id');
    if (! $options) {
       print "Database Error: ".$db->error."<br>\n";   return;
    }
    $query = 'select id,attributes from order_items';
    $order_items = $db->get_records($query,'id','attributes');
    if (! $order_items) {
       print "Database Error: ".$db->error."<br>\n";   return;
    }

    $db->enable_log_query(false);
    foreach ($order_items as $item_id => $attributes) {
       $attributes = explode('-',$attributes);
       $names = '';
       foreach ($attributes as $attribute) {
          if (! isset($options[$attribute])) continue;
          $option = $options[$attribute];
          if (! isset($attrs[$option['parent']])) continue;
          $attr = $attrs[$option['parent']];
          if ($names) $names .= '|';
          if ($attr['display_name']) $names .= $attr['display_name'];
          else $names .= $attr['name'];
          $names .= '|'.$option['name'];
       }
       if (! $names) continue;
       $query = 'update order_items set attribute_names=? where id=?';
       $query = $db->prepare_query($query,$names,$item_id);
       if (! $db->query($query)) {
          print "Database Error: ".$db->error."<br>\n";   return;
       }
    }

    log_activity('Reset Order Item Attribute Names');
    print "Reset Order Item Attribute Names<br>\n";
}

function add_order_source()
{
    $frame = get_form_field('Frame');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    $head_block = '    <style> .fieldtable { width: 100%; } </style>';
    $dialog->add_head_line($head_block);
    $dialog->set_body_id('add_order_source');
    $dialog->set_help('add_order_source');
    $dialog->start_body('Add New Order Source');
    $dialog->start_button_column();
    $dialog->add_button('Add','images/Update.png','add_order_source();');
    $dialog->add_button('Cancel','images/Update.png',
                        'cancel_add_order_source();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','AddOrderSource');
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_field_table();
    $dialog->add_edit_row('Order Source:','external_source','',30);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function term_record_definition()
{
    $term_record = array();
    $term_record['id'] = array('type' => INT_TYPE);
    $term_record['id']['key'] = true;
    $term_record['name'] = array('type' => CHAR_TYPE);
    $term_record['content'] = array('type' => CHAR_TYPE);
    return $term_record;
}

function manage_terms()
{
    $frame = get_form_field('Frame');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_onload_function('manage_terms_onload();');
    $dialog->set_body_id('manage_terms');
    $dialog->set_help('manage_terms');
    $dialog->start_body('Manage Order Terms');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Term','images/AddCategory.png',
                        'add_term(null);');
    $dialog->add_button('Edit Term','images/EditCategory.png',
                        'edit_term();');
    $dialog->add_button('Delete Term','images/DeleteCategory.png',
                        'delete_term();');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','ManageTerms');
    if ($frame) $dialog->add_hidden_field('Frame',$frame);
    $dialog->write("        <script>create_terms_grid();</script>\n");
    $dialog->end_form();
    $dialog->end_body();
}

function display_term_fields($dialog,$edit_type,$row,$db)
{
    if ($edit_type == UPDATERECORD) {
       $id = get_row_value($row,'id');
       $dialog->add_hidden_field('id',$id);
    }
    $dialog->add_edit_row('Name:','name',$row,64);
    $dialog->start_row('Content:','top');
    $dialog->add_htmleditor_popup_field('content',$row,'Content',400,200,null,
       null,null,false,'catalogtemplates.xml');
    $dialog->end_row();
}

function add_term()
{
    $frame = get_form_field('Frame');
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    $dialog->set_body_id('add_term');
    $dialog->set_help('add_term');
    $dialog->set_onload_function('document.AddTerm.name.focus();');
    $dialog->start_body('Add Order Term');
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Add Term','images/AddCategory.png',
                        'process_add_term();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','AddTerm');
    if ($frame) $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_field_table();
    display_term_fields($dialog,ADDRECORD,array(),$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_term()
{
    $db = new DB;
    $term_record = term_record_definition();
    $db->parse_form_fields($term_record);
    if (! $db->insert('order_terms',$term_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Order Term Added');
    log_activity('Added Order Term '.$term_record['name']['value'] .
                 ' (#'.$db->insert_id().')');
}

function edit_term()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from order_terms where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error($db->error,0);
       else process_error('Term not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('orders.css');
    $dialog->add_script_file('orders.js');
    $dialog_title = 'Edit Order Term (#'.$id.')';
    $dialog->set_body_id('edit_term');
    $dialog->set_help('edit_term');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_term();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('orders.php','EditTerm');
    $dialog->start_field_table();
    display_term_fields($dialog,UPDATERECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_term()
{
    $db = new DB;
    $term_record = term_record_definition();
    $db->parse_form_fields($term_record);
    if (! $db->update('order_terms',$term_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Order Term Updated');
    log_activity('Updated Order Term '.$term_record['name']['value'] .
                 ' (#'.$term_record['id']['value'].')');
}

function delete_term()
{
    $id = get_form_field('id');
    $db = new DB;
    $term_record = term_record_definition();
    $term_record['id']['value'] = $id;
    if (! $db->delete('order_terms',$term_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Order Term Deleted');
    log_activity('Deleted Order Term #'.$id);
}

function load_terms()
{
    $db = new DB;
    $query = 'select * from order_terms order by name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) print 'var error = \''.$db->error.'\'';
       else header('Status: 204 No Terms Found');
       return;
    }
    foreach ($rows as $index => $row) {
       $name = str_replace("'","\\'",$row['name']);
       $name = str_replace("\n","\\n",$row['name']);
       $content = str_replace("'","\\'",$row['content']);
       $content = str_replace("\n","\\n",$row['content']);
       print 'terms['.$index.'] = {id:'.$row['id'].',name:\''.$name .
             '\',content:\''.$content.'\'};';
    }
}

$cmd = get_form_field('cmd');

if (function_exists('check_custom_edit_access') &&
    (($cmd == 'editorder') || ($cmd == 'updateorder'))) {
   if (! check_custom_edit_access()) exit;
}
else if (! check_login_cookie()) exit;

if ($cmd == 'addorder') add_order();
else if ($cmd == 'processaddorder') process_add_order();
else if ($cmd == 'vieworder') view_order();
else if ($cmd == 'editorder') edit_order();
else if ($cmd == 'updateorder') update_order();
else if ($cmd == 'deleteorder') delete_order();
else if ($cmd == 'addpartialshipment') add_partial_shipment();
else if ($cmd == 'processaddpartialshipment') process_add_partial_shipment();
else if ($cmd == 'lookuppartialshipping') lookup_partial_shipping();
else if ($cmd == 'copyorder') copy_order();
else if ($cmd == 'convertquote') convert_quote();
else if ($cmd == 'generatepartialinvoice') generate_partial_invoice();
else if ($cmd == 'processgenerateinvoice') process_generate_partial_invoice();
else if ($cmd == 'generateinvoice') generate_invoice();
else if ($cmd == 'reorder') add_reorder();
else if ($cmd == 'shippinglabel') shipping_label();
else if ($cmd == 'cancelshipment') cancel_order_shipment();
else if ($cmd == 'addorderitem') add_order_item();
else if ($cmd == 'loadshippingprofiles') load_shipping_profiles();
else if ($cmd == 'loadshippingprofile') load_shipping_profile();
else if ($cmd == 'loadcustomeraccounts') load_order_customer_accounts();
else if ($cmd == 'loadcustomerorders') load_customer_orders();
else if ($cmd == 'loadcustomersavedcards') load_customer_saved_cards();
else if ($cmd == 'loadaccountinfo') load_account_info();
else if ($cmd == 'getprices') get_product_prices();
else if ($cmd == 'lookupfees') lookup_fees();
else if ($cmd == 'processcoupon') process_coupon();
else if ($cmd == 'processgift') process_gift();
else if ($cmd == 'processaddorderitem') process_add_order_item();
else if ($cmd == 'editorderitem') edit_order_item();
else if ($cmd == 'updateorderitem') update_order_item();
else if ($cmd == 'deleteorderitem') delete_order_item();
else if ($cmd == 'selectorder') select_order();
else if ($cmd == 'printorder') print_order();
else if ($cmd == 'sendtovendors') send_to_vendors();
else if ($cmd == 'resetitemattrnames') reset_item_attribute_names();
else if ($cmd == 'addsource') add_order_source();
else if ($cmd == 'manageterms') manage_terms();
else if ($cmd == 'addterm') add_term();
else if ($cmd == 'processaddterm') process_add_term();
else if ($cmd == 'editterm') edit_term();
else if ($cmd == 'updateterm') update_term();
else if ($cmd == 'deleteterm') delete_term();
else if ($cmd == 'loadterms') load_terms();
else if (function_exists('custom_order_command') &&
         custom_order_command($cmd)) {}
else {
   require_once '../engine/modules.php';
   if (call_module_event('custom_command',array('orders',$cmd),
                         null,true,true)) {}
   else display_orders_screen();
}

DB::close_all();

?>
