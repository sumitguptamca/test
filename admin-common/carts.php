<?php
/*
                       Inroads Shopping Cart - Carts Screen

                        Written 2009-2019 by Randall Severy
                         Copyright 2009-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
require_once 'utility.php';
require_once 'orders-common.php';
require_once 'customers-common.php';
require_once 'cartconfig-common.php';
require_once 'currency.php';

if (! isset($order_label)) $order_label = 'Order';
if (! isset($show_mpns_in_pending_cart)) $show_mpns_in_pending_cart = false;
if (get_form_field('wishlists')) $wish_lists = true;
else $wish_lists = false;

function amount_format($cart,$amount)
{
    return format_amount($amount,get_row_value($cart->info,'currency'),false);
}

function display_carts_screen()
{
    global $order_label,$show_mpns_in_pending_cart,$wish_lists;
    global $enable_multisite;

    $db = new DB;
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('carts.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    if ($show_mpns_in_pending_cart || $wish_lists ||
        (! empty($enable_multisite))) {
       $head_block = "<script type=\"text/javascript\">\n";
       if ($show_mpns_in_pending_cart)
          $head_block .= "      show_mpns = true;\n";
       if ($wish_lists) $head_block .= "      wish_lists = true;\n";
       if (! empty($enable_multisite)) {
          $website_settings = get_website_settings($db);
          $head_block .= '      website_settings = '.$website_settings.";\n";
       }
       $head_block .= '    </script>';
       $screen->add_head_line($head_block);
    }
    if (function_exists('custom_init_carts_screen'))
       custom_init_carts_screen($screen);
    $screen->set_body_id('carts');
    $screen->set_help('carts');
    $screen->start_body();
    if ($screen->skin) {
       if ($wish_lists) $screen->start_title_bar('Wish Lists');
       else $screen->start_title_bar('Pending Carts');
       $screen->start_title_filters();
       add_search_box($screen,'search_cart','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    if ($wish_lists) {
       $suffix = 'Wish List';
       $screen->set_button_width(148);
    }
    else {
       $suffix = 'Cart';
       $screen->set_button_width(148);
    }
    $screen->start_button_column();
    $screen->add_button('View '.$suffix,'images/ViewOrder.png',
                        'view_cart();');
    $screen->add_button('Submit '.$order_label,'images/AdminUsers.png',
                        'submit_order();');
    $screen->add_button('Edit '.$suffix,'images/EditOrder.png','edit_cart();');
    $screen->add_button('Delete '.$suffix,'images/DeleteOrder.png',
                        'delete_cart();');
    if (! $screen->skin) add_search_box($screen,'search_cart','reset_search');
    $screen->end_button_column();
    $screen->write("\n          <script>\n");
    if (function_exists('write_custom_cart_variables'))
       write_custom_cart_variables($screen,$db);
    $screen->write("             load_grid();\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function view_cart()
{
    global $show_mpns_in_pending_cart,$wish_lists,$enable_multisite;

    $db = new DB;
    $id = get_form_field('id');
    $cart = load_cart($db,$id,$error_msg);
    if (! $cart) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    if ($show_mpns_in_pending_cart) load_order_mpns($cart);
    $customer_id = get_row_value($cart->info,'customer_id');
    if ($customer_id) {
       $query = 'select * from customers where id=?';
       $query = $db->prepare_query($query,$customer_id);
       $customer_info = $db->get_record($query);
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,0);   return;
       }
       $db->decrypt_record('customers',$customer_info);
    }

    if (isset($cart->items)) {
       $sub_total = 0;
       foreach ($cart->items as $cart_item)
          $sub_total += get_item_total($cart_item);
    }
    else $sub_total = 0;

    $dialog = new Dialog;
    $dialog->set_doctype("<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Strict//EN\">");
    $dialog->add_style_sheet('carts.css');
    $dialog->add_script_file('carts.js');
    $head_block = '<style>.fieldtable { margin-left: 20px;';
    if ($dialog->skin) $head_block .= ' width: 100%;';
    $head_block .= ' }</style>';
    $dialog->add_head_line($head_block);
    if ($wish_lists) $dialog_title = 'View Wish List (#'.$id.')';
    else $dialog_title = 'View Pending Cart (#'.$id.')';
    $dialog->set_body_id('view_cart');
    $dialog->set_help('view_cart');
    $dialog->start_body($dialog_title);
    $dialog->start_content_area(true);
    $dialog->set_field_padding(1);

    $dialog->start_field_table();
    if ($wish_lists) $label = 'Wish List';
    else $label = 'Cart';
    $dialog->write('<tr><td colspan="2" class="section_title">'.$label .
                   ' Information</td></tr>'."\n");
    $dialog->write("<tr height=\"10px\"><td colspan=2></td></tr>\n");

    $ip_address = get_row_value($cart->info,'ip_address');
    if ($ip_address) $dialog->add_text_row('IP Address:',$ip_address);
    $user_agent = get_row_value($cart->info,'user_agent');
    if ($user_agent) $dialog->add_text_row('User Agent:',$user_agent,'top');
    if ($customer_id) {
       $customer_name = get_row_value($customer_info,'fname');
       $mname = get_row_value($customer_info,'mname');
       if ($mname != '') $customer_name .= ' '.$mname;
       $customer_name .= ' '.get_row_value($customer_info,'lname');
       $dialog->add_text_row('Customer:',$customer_name);
       $company = get_row_value($cart->info,'company');
       if ($company != '') $dialog->add_text_row('Company:',$company);
    }

    $dialog->write("<tr height=\"10px\"><td colspan=2></td></tr>\n");

    if (! empty($enable_multisite)) {
       $website = get_row_value($cart->info,'website');
       if ($website) {
          $query = 'select * from web_sites where id=?';
          $query = $db->prepare_query($query,$website);
          $website_info = $db->get_record($query);
          if ($website_info)
             $dialog->add_text_row('Web Site:',$website_info['name']);
       }
    }

    if (isset($cart->info['reorder'])) {
       $reorder_num = get_row_value($cart->info,'reorder_id');
       $reorder_info = "<a href=\"\" onClick=\"top.get_content_frame().view_reorder(" .
                       $reorder_num."); return false;\">".$reorder_num."</a>";
       $dialog->add_text_row('Reorder of:',$reorder_info);
    }
    if (isset($cart->items))
       $dialog->add_text_row('Sub Total:',amount_format($cart,$sub_total));
    $dialog->add_text_row('Created On:',
                          date('F j, Y g:i:s a',
                               get_row_value($cart->info,'create_date')));
    $comments = get_row_value($cart->info,'comments');
    if ($comments && ($comments != '')) {
       $dialog->start_row('Comments:','top');
       $dialog->write("<div class=\"");
       $dialog->write('comments_div');
       $dialog->write("\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$comments));
       $dialog->write("</tt>\n");
       $dialog->end_row();
    }
    $dialog->write("<tr height=\"20px\"><td colspan=2></td></tr>\n");

    if (isset($cart->items)) {
       $dialog->write("<tr height=\"20px\"><td colspan=2></td></tr>\n");
       $dialog->write("</table>\n");
       $dialog->write("<table cellspacing=0 cellpadding=1 class=\"fieldtable\" width=\"580px\">\n");
       $dialog->write("<tr><td class=\"cart_item_title\" nowrap>Product Name</td>");
       if ($show_mpns_in_pending_cart)
          $dialog->write('<td class="cart_item_title" align="center" width="75px">MPN</td>');
       $dialog->write("<td class=\"cart_item_title\" align=\"center\" width=\"75px\">Unit Price</td>" .
                      "<td class=\"cart_item_title\" align=\"center\" width=\"30px;\">Qty</td>" .
                      "<td class=\"cart_item_title\" align=\"right\" width=\"75px\">Total</td></tr>\n");
       $index = 0;
       foreach ($cart->items as $item_id => $cart_item) {
          if ($index > 0)
             $dialog->write("<tr height=\"10px\"><td colspan=4></td></tr>\n");
          $dialog->write("<tr valign=\"top\"><td>" .
                        get_html_product_name($cart_item['product_name'],
                                              GET_PROD_ADMIN_VIEW_ORDER,
                                              $cart,$cart_item));
          $dialog->write(get_html_attributes($cart_item['attribute_array'],
                                             GET_ATTR_ADMIN_VIEW_ORDER,
                                             $cart,$cart_item));
          if ($show_mpns_in_pending_cart)
             $dialog->write("<td align=\"center\">".$cart_item['mpn']."</td>\n");
          $dialog->write("</td>\n<td align=\"center\">" .
                         amount_format($cart,$price = $cart_item['price'])."</td>\n");
          $dialog->write("<td align=\"center\">".format_qty($cart_item['qty']) .
                         "</td>\n");
          $item_total = get_item_total($cart_item);
          $dialog->write("<td align=\"right\">".amount_format($cart,$item_total) .
                         "</td></tr>\n");
          $index++;
       }
       $num_cols = 4;
       if ($show_mpns_in_pending_cart) $num_cols++;
       $dialog->write("<tr height=\"20px\"><td colspan=".$num_cols."></td></tr>\n");
       $dialog->write("<tr><td colspan=".($num_cols - 1)." class=\"fieldprompt\">" .
                      "Sub Total:</td><td align=\"right\">" .
                      amount_format($cart,$sub_total)."</td></tr>\n");
    }
    else $num_cols = 2;

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<tr height=\"10px\"><td colspan=".$num_cols."></td></tr>\n");
       $dialog->write("<tr><td colspan=".$num_cols." align=\"center\" width=\"580px\">\n");
    }
    $dialog->add_dialog_button('Close','images/Update.png','top.close_current_dialog();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_content_area(true);
    $dialog->end_body();
}

function submit_order()
{
    global $order_label,$wish_lists;

    require_once 'cart-public.php';

    if ($wish_lists) $label = 'Wish List';
    else $label = 'Cart';
    $db = new DB;
    $ids = get_form_field('ids');
    $ids = explode(',',$ids);
    $notify_flags = get_cart_config_value('notifications',$db);
    foreach ($ids as $id) {
       $query = 'select customer_id from cart where id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if ((! $row) || (! $row['customer_id'])) {
          http_response(409,'No Customer information found for '.$label);
          return;
       }
       $customer_id = $row['customer_id'];
       $customer = new Customer($db,$customer_id,true);
       $customer->cart_id = $id;
       $order = new Order($customer);
       if (! $order->load_cart($id)) {
          http_response(422,'Database Error: '.$order->error);   return;
       }
       $sub_total = 0;
       foreach ($order->items as $cart_item)
          $sub_total += get_item_total($cart_item);
       $order->set('subtotal',$sub_total);
       $order->set('total',$sub_total);
       $order->calculate_tax($customer);
       if (! $order->create()) {
          if ($order->status == DB_ERROR)
             http_response(422,'Database Error: '.$order->error);
          else http_response(422,$order->error);
          return;
       }
       if (! update_order_totals($db,$order->id,true)) return;
       if (function_exists('custom_order_notifications'))
          custom_order_notifications($order);
       else {
          if (($notify_flags & NOTIFY_NEW_ORDER_CUST) ||
              ($notify_flags & NOTIFY_NEW_ORDER_ADMIN)) {
             require_once '../engine/email.php';
             $customer_template = NEW_ORDER_CUST_EMAIL;
             $admin_template = NEW_ORDER_ADMIN_EMAIL;
             if (($notify_flags & NOTIFY_NEW_ORDER_CUST) &&
                 $customer->info['email']) {
                $email = new Email($customer_template,
                                   array('order' => 'obj','order_obj' => $order));
                if (! $email->send()) log_error($email->error);
                if (! empty($customer_id))
                   write_customer_activity($email->activity,$customer_id,$db);
             }
             if ($notify_flags & NOTIFY_NEW_ORDER_ADMIN) {
                $email = new Email($admin_template,
                                   array('order' => 'obj','order_obj' => $order));
                if (! $email->send()) log_error($email->error);
             }
          }
          if ($notify_flags & NOTIFY_LOW_QUANTITY)
             check_low_quantity($order,null);
       }
       log_activity('Submitted '.$order_label.' #'.$order->id.' from ' .
                    $label.' #'.$id);
    }
    http_response(201,$order_label.'s Submitted');
}

function display_cart_fields($dialog,$edit_type,$cart)
{
    global $wish_lists;

    if ($wish_lists) $label = 'Wish List';
    else $label = 'Cart';
    if (isset($cart->items)) {
       $sub_total = 0;
       foreach ($cart->items as $cart_item)
          $sub_total += get_item_total($cart_item);
    }
    else $sub_total = 0;
    if ($edit_type == UPDATERECORD) $id = get_row_value($cart->info,'id');
    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('cart_tab','cart_content','change_tab');
    $dialog->add_tab('cart_tab',$label,'cart_tab','cart_content',
                     'change_tab',true,null,FIRST_TAB);
    if ($edit_type == UPDATERECORD) {
       $dialog->add_tab('items_tab','Items','items_tab',
                        'items_content','change_tab');
       $dialog->add_tab('comments_tab','Comments','comments_tab',
                        'comments_content','change_tab',true,null,LAST_TAB);
       $dialog->end_tab_row('update_tab_row_middle');
    }
    else $dialog->end_tab_row('add_tab_row_middle');

    $dialog->start_tab_content('cart_content',true);
    $dialog->start_field_table('cart_table');
    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field('id',$id);
    }
    if (isset($cart->info['reorder']))
       $dialog->add_text_row('Reorder of:',get_row_value($cart->info,'reorder'));
    if (isset($cart->items))
       $dialog->add_text_row('Sub Total:',amount_format($cart,$sub_total));

    $dialog->add_text_row('Created On:',date('F j, Y g:i:s a',
                          get_row_value($cart->info,'create_date')));
    $dialog->end_field_table();
    $dialog->end_tab_content();

    if ($edit_type == UPDATERECORD) {
       $dialog->start_tab_content('items_content',false);
       if (isset($cart->items)) {
          $dialog->write("<table cellspacing=0 cellpadding=0 class=\"" .
                         "fieldtable item_table\" width=\"515\">\n");
          $dialog->write("<tr><th nowrap>Product Name</td>" .
                         "<th align=\"center\" width=\"75px\">Unit Price</td>" .
                         "<th align=\"center\" width=\"30px;\">Qty</td>" .
                         "<th align=\"right\" width=\"75px\">Total</td></tr>\n");
          $index = 0;
          foreach ($cart->items as $item_id => $cart_item) {
             $dialog->write("<tr valign=\"middle\" id=\"".$cart_item['id']."\"");
             $dialog->write(" onMouseOver=\"item_row_mouseover(this);\" ");
             $dialog->write("onMouseOut=\"item_row_mouseout(this);\" onClick=\"item_row_click(this);\"");
             $dialog->write(">\n");
             $dialog->write("<td id=\"item_name_".$cart_item['id']."\">" .
                            get_html_product_name($cart_item['product_name'],
                                                  GET_PROD_ADMIN_VIEW_ORDER,
                                                  $cart,$cart_item));
             $dialog->write(get_html_attributes($cart_item['attribute_array'],
                                                GET_ATTR_ADMIN_VIEW_ORDER,
                                                $cart,$cart_item));
             $dialog->write("</td>\n<td align=\"center\">" .
                            amount_format($cart,$price = $cart_item['price'])."</td>\n");
             $dialog->write("<td align=\"center\">".format_qty($cart_item['qty']) .
                            "</td>\n");
             $item_total = get_item_total($cart_item);
             $dialog->write("<td align=\"right\">".amount_format($cart,$item_total) .
                            "</td></tr>\n");
             $index++;
          }
          $dialog->write("</table>\n");
       }
       $dialog->end_tab_content();

       $dialog->start_tab_content('comments_content',false);
       $dialog->start_field_table('comments_table');
       $dialog->write("<tr><td>\n");
       $dialog->start_textarea_field('comments',19,83,WRAP_SOFT);
       write_form_value(get_row_value($cart->info,'comments'));
       $dialog->end_textarea_field();
       $dialog->end_row();
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }
}

function add_cart_item_buttons($dialog,$enabled=true)
{
    $dialog->write("            <tr height=\"20\"><td colspan=2>&nbsp;</td></tr>\n");
/*
    $dialog->add_button('Add Item','images/AddProduct.png',
                        'add_cart_item();','add_cart_item',$enabled);
*/
    $dialog->add_button('Edit Item','images/EditProduct.png',
                        'edit_cart_item();','edit_cart_item',$enabled);
    $dialog->add_button('Delete Item','images/DeleteProduct.png',
                        'delete_cart_item();','delete_cart_item',$enabled);
}

function add_cart()
{
    global $enable_edit_order_items,$wish_lists;

    $cart = new Object();
    $cart->info = array();
    $cart->billing = array();
    $cart->shipping = array();
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('carts.css');
    $dialog->add_script_file('carts.js');
    $dialog->set_field_padding(2);
    $dialog->set_body_id('add_cart');
    $dialog->set_help('add_cart');
    if ($wish_lists) $dialog->start_body('Add Wish List');
    else $dialog->start_body('Add Pending Cart');
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Add','images/AddOrder.png','process_add_cart();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_cart_item_buttons($dialog,false);
    $dialog->end_button_column();
    $dialog->start_form('carts.php','AddCart');
    if (! $dialog->skin) $dialog->start_field_table();
    display_cart_fields($dialog,ADDRECORD,$cart);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_cart()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $db = new DB;
    $cart_record = cart_record_definition();
    $db->parse_form_fields($cart_record);
    if (! $db->insert($table,$cart_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.' Added');
    log_activity('Added '.$label.' '.$cart_record['id']['value']);
}

function edit_cart()
{
    global $wish_lists;

    $db = new DB;
    $id = get_form_field('id');
    $cart = load_cart($db,$id,$error_msg);
    if (! $cart) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('carts.css');
    $dialog->add_script_file('carts.js');
    $dialog->set_field_padding(2);
    if ($wish_lists) $dialog_title = 'Edit Wish List (#'.$id.')';
    else $dialog_title = 'Edit Pending Cart (#'.$id.')';
    $dialog->set_body_id('edit_cart');
    $dialog->set_help('edit_cart');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(115);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_cart();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_cart_item_buttons($dialog,false);
    $dialog->end_button_column();
    $dialog->start_form('carts.php','EditCart');
    if (! $dialog->skin) $dialog->start_field_table();
    display_cart_fields($dialog,UPDATERECORD,$cart);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_cart()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $db = new DB;
    $cart_record = cart_record_definition();
    $db->parse_form_fields($cart_record);
    if (! $db->update($table,$cart_record)) {
       http_response(422,$db->error);   return;
    }
    log_activity('Updated '.$label.' '.$cart_record['id']['value']);
    http_response(201,$label.' Updated');
}

function delete_cart()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $ids = get_form_field('ids');
    $db = new DB;
    $query = 'delete from '.$table.'_items where parent in (?)';
    $query = $db->prepare_query($query,explode(',',$ids));
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from '.$table.' where id in (?)';
    $query = $db->prepare_query($query,explode(',',$ids));
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.'s Deleted');
    log_activity('Deleted '.$label.' IDs '.$ids);
}

function add_cart_item()
{
    global $wish_lists;

    $parent = get_form_field('Cart');

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file('carts.js');
    $dialog->set_body_id('add_cart_item');
    $dialog->set_help('add_cart_item');
    if ($wish_lists) $dialog->start_body('Add Wish List Item');
    else $dialog->start_body('Add Cart Item');
    $dialog->start_button_column();
    $dialog->add_button('Add Item','images/AddProduct.png',
                        'process_add_cart_item();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('carts.php','EditCartItem');
    $dialog->start_field_table();
    $dialog->add_hidden_field('parent',$parent);
    $dialog->add_edit_row('Description:','description',
                          get_row_value($row,'description'),35);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_cart_item()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $db = new DB;
    $item_record = item_record_definition();
    $db->parse_form_fields($item_record);
    if (! $db->insert($table.'_items',$item_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.' Item Added');
    log_activity('Added '.$label.' Item to '.$label.' #' .
                 $item_record['parent']['value']);
}

function edit_cart_item()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from '.$table.'_items where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($label.' Item not found',0);
       return;
    }
    $price = get_row_value($row,'price');
    $qty = get_row_value($row,'qty');
    if ($row['flags'] & QTY_PRICE) $total = $price;
    else $total = $price * $qty;
    $cart_id = get_form_field('Cart');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file('carts.js');
    $dialog_title = 'Edit '.$label.' Item (#'.$row['id'].')';
    $dialog->set_body_id('edit_cart_item');
    $dialog->set_help('edit_cart_item');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_cart_item();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('carts.php','EditCartItem');
    $dialog->start_field_table();
    $dialog->add_hidden_field('Cart',$cart_id);
    $dialog->add_hidden_field('id',$row);
    $product_name = get_row_value($row,'product_name');
    $dialog->add_text_row('Product Name:',
       get_html_product_name($product_name,GET_PROD_ADMIN,null,$row));
    $dialog->add_text_row('Unit Price:','$'.number_format($price,2));
    $dialog->add_edit_row('Quantity:','qty',format_qty($qty),3);
    $dialog->add_text_row('Total:','$'.number_format($total,2));
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_cart_item()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $cart_id = get_form_field('Cart');
    $db = new DB;
    $item_record = item_record_definition();
    $db->parse_form_fields($item_record);
    if (! $db->update($table.'_items',$item_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.' Item Updated');
    log_activity('Updated '.$label.' Item '.$item_record['id']['value']);
}

function delete_cart_item()
{
    global $wish_lists;

    if ($wish_lists) {
       $label = 'Wish List';   $table = 'wishlist';
    }
    else {
      $label = 'Cart';   $table = 'cart';
    }
    $cart_id = get_form_field('Cart');
    $db = new DB;
    $id = get_form_field('id');
    $item_record = item_record_definition();
    $item_record['id']['value'] = $id;
    if (! $db->delete($table.'_items',$item_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.' Item Deleted');
    log_activity('Deleted '.$label.' Item '.$id);
}

$cmd = get_form_field('cmd');

if (! check_login_cookie()) exit;

if ($cmd == 'addcart') add_cart();
else if ($cmd == 'processaddcart') process_add_cart();
else if ($cmd == 'viewcart') view_cart();
else if ($cmd == 'submitorder') submit_order();
else if ($cmd == 'editcart') edit_cart();
else if ($cmd == 'updatecart') update_cart();
else if ($cmd == 'deletecart') delete_cart();
else if ($cmd == 'addcartitem') add_cart_item();
else if ($cmd == 'processaddcartitem') process_add_cart_item();
else if ($cmd == 'editcartitem') edit_cart_item();
else if ($cmd == 'updatecartitem') update_cart_item();
else if ($cmd == 'deletecartitem') delete_cart_item();
else display_carts_screen();

DB::close_all();

?>
