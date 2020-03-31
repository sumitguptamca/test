<?php
/*
                         Inroads Shopping Cart - RMAs Tab

                        Written 2013-2018 by Randall Severy
                         Copyright 2013-2018 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
require_once 'rmas-common.php';
require_once 'cartconfig-common.php';
require_once 'utility.php';
require_once 'currency.php';
require_once '../cartengine/adminperms.php';

function amount_format($rma,$amount)
{
    return format_amount($amount,get_row_value($rma->info,'currency'),false);
}

function display_rmas_screen()
{
    global $rma_status_list;

    $db = new DB;
    if (! isset($rma_status_list)) $rma_status_list = RMA_STATUS;
    $status_values = load_cart_options($rma_status_list,$db);
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('rmas.css');
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('rmas.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    add_encrypted_fields($screen,'rmas');
    $screen->set_body_id('rmas');
    $screen->set_help('rmas');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('RMAs');
       $screen->start_title_filters();
       add_search_box($screen,'search_rmas','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }

    $screen->set_button_width(148);
    $screen->start_button_column();
    $screen->add_button('New RMA','images/AddOrder.png',
                        'add_rma();',null,true,false,ADD_BUTTON);
    $screen->add_button('Edit RMA','images/EditOrder.png',
                        'edit_rma();',null,true,false,EDIT_BUTTON);
    $screen->add_button('Delete RMA','images/DeleteOrder.png',
                        'delete_rma();',null,true,false,DELETE_BUTTON);
    $screen->add_button('View RMA','images/ViewOrder.png',
                        'view_rma();',null,true,false,VIEW_BUTTON);
    if (! $screen->skin)
       add_search_box($screen,'search_rmas','reset_search');
    $screen->end_button_column();
    $screen->write("\n          <script type=\"text/javascript\">\n");
    $screen->write('             var rma_status_values = [');
    $max_status = max(array_keys($status_values));
    for ($loop = 0;  $loop <= $max_status;  $loop++) {
       if ($loop > 0) $screen->write(',');
       if (isset($status_values[$loop]))
          $screen->write("\"".$status_values[$loop]."\"");
       else $screen->write("\"\"");
    }
    $screen->write("];\n");
    $screen->write("             load_grid();\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function view_rma()
{
    global $rma_status_list,$rma_reasons_list,$rma_replace_label;
    global $enable_vendors;

    $db = new DB;
    $id = get_form_field('id');
    $rma = load_rma($db,$id,$error_msg);
    if (! $rma) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    if (! isset($rma_status_list)) $rma_status_list = RMA_STATUS;
    $status_values = load_cart_options($rma_status_list,$db);
    if (isset($db->error)) {
       process_error('Database Error: '.$db->error,0);   return;
    }
    if (! isset($rma_reasons_list)) $rma_reasons_list = RMA_REASONS;
    $reason_values = load_cart_options($rma_reasons_list,$db);
    if (! isset($rma_replace_label)) $rma_replace_label = 'Replace';
    if (! isset($enable_vendors)) $enable_vendors = false;

    $dialog = new Dialog;
    $dialog->set_doctype("<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Strict//EN\">");
    $dialog->add_style_sheet('rmas.css');
    $dialog->add_script_file('rmas.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    if ($dialog->skin)
       $head_block = '<style>.fieldtable { width: 100%; }</style>';
    else $head_block = '<style>.fieldtable { margin-left: 20px; }</style>';
    $dialog->add_head_line($head_block);
    $dialog_title = 'View RMA (#'.$id.')';
    $dialog->set_body_id('view_rma');
    $dialog->set_help('view_rma');
    $dialog->start_body($dialog_title);
    $dialog->start_content_area(true);
    $dialog->set_field_padding(1);

    $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" " .
                   "class=\"fieldtable\" width=\"620px\">\n");
    $dialog->write("<tr valign=\"top\"><td width=\"50%\">\n");
    $dialog->write("<table cellpadding=\"1\" cellspacing=\"0\">\n");
    $dialog->write("<tr><td colspan=2 class=\"section_title\">" .
                   "RMA Information</td></tr>\n");
    $dialog->write("<tr height=\"10px\"><td colspan=2></td></tr>\n");
    $dialog->add_text_row('RMA Status:',
                          $status_values[get_row_value($rma->info,'status')]);
    $dialog->add_text_row('RMA Number:',$id);
    $dialog->add_text_row('Request Date:',date('F j, Y g:i:s a',
       get_row_value($rma->info,'request_date')));
    $completed_date = get_row_value($rma->info,'completed_date');
    if ($completed_date)
       $dialog->add_text_row('Completed Date:',date('F j, Y g:i:s a',
                                                    $completed_date));
    $dialog->add_text_row('Order Number:',
                          get_row_value($rma->info,'order_number'));
    $request_type = get_row_value($rma->info,'request_type');
    if ($request_type == 0) $request_type = $rma_replace_label;
    else $request_type = 'Refund';
    $dialog->add_text_row('Request Type:',$request_type);
    $restocking_fee = get_row_value($rma->info,'restocking_fee');
    if ($restocking_fee)
       $dialog->add_text_row('Restocking Fee:',
                             amount_format($rma,$restocking_fee));
    $refund_amount = get_row_value($rma->info,'refund_amount');
    if ($refund_amount)
       $dialog->add_text_row('Refund Amount:',
                             amount_format($rma,$refund_amount));
    $refund_date = get_row_value($rma->info,'refund_date');
    if ($refund_date)
       $dialog->add_text_row('Refund Date:',date('F j, Y g:i:s a',
                                                 $refund_date));
    $opened = get_row_value($rma->info,'opened');
    if ($opened == 1) $opened = 'Yes';
    else $opened = 'No';
    $dialog->add_text_row('Package Opened:',$opened);
    $reason = get_row_value($rma->info,'reason');
    if (isset($reason_values[$reason])) $reason = $reason_values[$reason];
    $dialog->add_text_row('Reason:',$reason,'top');
    $reason_details = get_row_value($rma->info,'reason_details');
    if ($reason_details) {
       $dialog->start_row('Reason Details:','top');
       $dialog->write("<div class=\"reason_details_div\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$reason_details));
       $dialog->write("</tt></div>\n");
       $dialog->end_row();
    }
    if ($enable_vendors) {
       $vendor = get_row_value($rma->info,'vendor');
       if ($vendor) {
          $query = 'select name from vendors where id=?';
          $query = $db->prepare_query($query,$vendor);
          $vendor_info = $db->get_record($query);
          if ($vendor_info) $dialog->add_text_row('Vendor:',$vendor_info['name']);
       }
       $vendor_rma = get_row_value($rma->info,'vendor_rma');
       if ($vendor_rma) $dialog->add_text_row('Vendor RMA #:',$vendor_rma);
       $return_address = get_row_value($rma->info,'return_address');
       if ($return_address) {
          $dialog->start_row('Return Address:','top');
          $dialog->write("<div class=\"return_address_div\"><tt>");
          $dialog->write(str_replace("\n",'<br>',$return_address));
          $dialog->write("</tt></div>\n");
          $dialog->end_row();
       }
    }
    $comments = get_row_value($rma->info,'comments');
    if ($comments) {
       $dialog->start_row('Comments:','top');
       $dialog->write("<div class=\"short_comments_div\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$comments));
       $dialog->write("</tt></div>\n");
    }
    $notes = get_row_value($rma->info,'notes');
    if ($notes) {
       $dialog->start_row('Notes:','top');
       $dialog->write("<div class=\"short_notes_div\"><tt>");
       $dialog->write(str_replace("\n",'<br>',$notes));
       $dialog->write("</tt></div>\n");
       $dialog->end_row();
    }
    $dialog->write("<tr height=\"20px\"><td colspan=2></td></tr>\n");
    $dialog->end_field_table();

    $dialog->write("</td><td width=\"50%\" style=\"padding-left:20px;\">\n");
    $dialog->write("<table cellpadding=\"1\" cellspacing=\"0\">\n");
    $dialog->write("<tr><td colspan=2 class=\"section_title\">" .
                   "Shipping Address</td></tr>\n");
    $dialog->write("<tr height=\"10px\"><td colspan=\"2\"></td></tr>\n");
    $dialog->write("<tr valign=\"top\"><td>".get_row_value($rma->info,'fname'));
    $mname = get_row_value($rma->info,'mname');
    if ($mname) $dialog->write(' '.$mname);
    $dialog->write(' '.get_row_value($rma->info,'lname'));
    $dialog->write("<br>\n");
    $company = get_row_value($rma->info,'company');
    if ($company) $dialog->write($company."<br>\n");
    $dialog->write(get_row_value($rma->info,'address1')."<br>\n");
    $address2 = get_row_value($rma->info,'address2');
    if ($address2) $dialog->write($address2."<br>\n");
    $dialog->write(get_row_value($rma->info,'city').', ' .
                   get_row_value($rma->info,'state') .
                   '  '.get_row_value($rma->info,'zipcode').' ' .
                   get_country_name(get_row_value($rma->info,'country'),$db) .
                   "<br><br>\n");
    $address_type = get_row_value($rma->info,'address_type');
    if ($address_type === '') $address_type = get_address_type();
    if ($address_type == 2) $dialog->write('Address Type: Residential');
    else $dialog->write('Address Type: Business');
    $dialog->write("<br>\n");
    $phone = get_row_value($rma->info,'phone');
    if ($phone) $dialog->write('Telephone: '.$phone."<br>\n");
    $fax = get_row_value($rma->info,'fax');
    if ($fax) $dialog->write('Fax: '.$fax."<br>\n");
    $mobile = get_row_value($rma->info,'mobile');
    if ($mobile) $dialog->write('Mobile: '.$mobile."<br>\n");
    $email = get_row_value($rma->info,'email');
    if ($email) $dialog->write('E-Mail Address: '.$email."<br>\n");
    $dialog->write("</td></tr>\n");

    $dialog->end_field_table();
    $dialog->write("</td></tr></table>\n");

    $dialog->write("<tr height=\"20px\"><td colspan=\"2\"></td></tr>\n");
    if ($rma->items) {
       $dialog->write("</table>\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"1\" " .
                      "class=\"fieldtable\" width=\"530px\">\n");
       $dialog->write("<tr><td class=\"rma_item_title\" nowrap>" .
                      "Product Name</td>\n");
       $dialog->write("<td class=\"rma_item_title\" align=\"right\" " .
                      "width=\"75px\">Unit Price</td>\n");
       $dialog->write("<td class=\"rma_item_title\" align=\"center\" " .
                      "width=\"75px;\">Order Qty</td>\n");
       $dialog->write("<td class=\"rma_item_title\" align=\"center\" " .
                      "width=\"75px;\">Return Qty</td></tr>\n");
       $index = 0;
       foreach ($rma->order_items as $item_id => $order_item) {
          $return_flag = false;   $return_qty = '';
          if ($rma->items) {
             foreach ($rma->items as $rma_item) {
                if ($rma_item['item_id'] == $item_id) {
                   $return_qty = $rma_item['qty'];
                   if ($return_qty === null) $return_qty = $order_item['qty'];
                   $return_flag = true;   break;
                }
             }
          }
          if (! $return_flag) continue;
          if ($index > 0)
             $dialog->write("<tr height=\"10px\"><td colspan=\"4\">" .
                            "</td></tr>\n");
          $dialog->write("<tr valign=\"bottom\"><td>" .
             $order_item['product_name']."</td>\n");
          $dialog->write("<td align=\"right\">$" .
                         number_format($order_item['price'],2)."</td>\n");
          $dialog->write("<td align=\"center\">".$order_item['qty'] .
                         "</td>\n");
          $dialog->write("<td align=\"center\">".$return_qty .
                         "</td></tr>\n");
          $index++;
       }
       $dialog->write("<tr height=\"10px\"><td colspan=3></td></tr>\n");
       $num_cols = 3;
    }
    else $num_cols = 2;

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<tr class=\"bottom_buttons\"><td colspan=".$num_cols .
                      " align=\"center\">\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr>");
       $dialog->write("  <td style=\"padding-right: 10px;\">\n");
    }
    $dialog->add_dialog_button('Print','images/Update.png',
                               'window.print(); return false;');
    if (! $dialog->skin)
       $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    $dialog->add_dialog_button('Close','images/Update.png',
                               'top.close_current_dialog();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr></table>\n</td></tr>\n");
    $dialog->write("</table>\n");
    $dialog->end_content_area(true);
    $dialog->end_body();
    log_activity('Viewed RMA #'.$id);
}

function display_rma_fields($dialog,$edit_type,$rma,$db)
{
    global $rma_status_list,$rma_reasons_list,$rma_replace_label;
    global $enable_vendors;

    if (! isset($rma->db)) $rma->db = $db;
    if (! isset($rma_status_list)) $rma_status_list = RMA_STATUS;
    $status_values = load_cart_options($rma_status_list,$db);
    if (! isset($rma_reasons_list)) $rma_reasons_list = RMA_REASONS;
    $reason_values = load_cart_options($rma_reasons_list,$db);
    $dialog->set_field_padding(1);
    $status = get_row_value($rma->info,'status');
    if (! isset($rma_replace_label)) $rma_replace_label = 'Replace';
    if (! isset($enable_vendors)) $enable_vendors = false;

    $dialog->start_table(null,'rma_layout_table');
    $dialog->write("<tr valign=\"top\"><td align=\"left\">");

    $dialog->write("<div class=\"add_edit_rma_box add_edit_rma_info_box\">\n");
    $dialog->write("<div class=\"add_edit_rma_legend\">RMA Information" .
                   "</div>\n");
    if ($edit_type == UPDATERECORD) {
       $id = get_row_value($rma->info,'id');
       $dialog->add_hidden_field('id',$id);
       $dialog->add_hidden_field('OldStatus',$status);
       $order_id = get_row_value($rma->info,'order_id');
       $dialog->add_hidden_field('order_id',$order_id);
       if ($rma->order_items) $num_items = count($rma->order_items);
       else $num_items = 0;
       $query = 'select * from orders where id=?';
       $query = $db->prepare_query($query,$order_id);
       $order_info = $db->get_record($query);
       if (! $order_info) $order_info = array();
       else if ($order_info['coupon_id']) {
          $query = 'select coupon_code from coupons where id=?';
          $query = $db->prepare_query($query,$order_info['coupon_id']);
          $row = $db->get_record($query);
          if ($row) $order_info['coupon_code'] = $row['coupon_code'];
       }
       $currency = get_row_value($order_info,'currency');
       $coupon_code = get_row_value($order_info,'coupon_code');
       $coupon_amount = get_row_value($order_info,'coupon_amount');
       $gift_amount = get_row_value($order_info,'gift_amount');
       $discount_amount = get_row_value($order_info,'discount_amount');
       $dialog->write("<script type=\"text/javascript\">\n");
       if ($coupon_amount && (floatval($coupon_amount) != 0))
          $dialog->write("  order_coupon_amount = ".$coupon_amount.";\n");
       if ($gift_amount && (floatval($gift_amount) != 0))
          $dialog->write("  order_gift_amount = ".$gift_amount.";\n");
       if ($discount_amount && (floatval($discount_amount) != 0))
          $dialog->write("  order_discount_amount = ".$discount_amount.";\n");
       $dialog->write("</script>\n");
    }
    else {
       $dialog->add_hidden_field('order_id','');
       $num_items = 0;
    }
    $dialog->add_hidden_field('NumItems',$num_items);
    $dialog->start_field_table('rma_info_table');
    $dialog->set_table_columns(4);
    $dialog->start_row('Status:','middle');
    $dialog->start_choicelist('status',null);
    foreach ($status_values as $index => $status_label)
       $dialog->add_list_item($index,$status_label,$status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    if ($edit_type == ADDRECORD) $request_date = time();
    else $request_date = get_row_value($rma->info,'request_date');
    $dialog->add_text_row('Request Date:',date('F j, Y g:i:s a',
                                               $request_date),'middle',false);
    $dialog->add_hidden_field('request_date',$request_date);

    if ($edit_type == ADDRECORD) {
       $dialog->write("<tr valign=\"middle\"><td class=\"fieldprompt\" nowrap>");
       $dialog->write("Order Number:</td><td nowrap id=\"order_number_cell\">");
       $dialog->write("<a href=\"#\" class=\"find_order_link\" " .
                      "onClick=\"return find_order();\">Find Order</a>\n");
       $dialog->end_row();
       $dialog->curr_table_col = 2;
    }
    else {
       $order_number = get_row_value($rma->info,'order_number');
       $dialog->add_text_row('Order Number:',$order_number,'middle');
    }
    $completed_date = get_row_value($rma->info,'completed_date');
    if ($completed_date) {
       $dialog->add_text_row('Completed Date:',date('F j, Y g:i:s a',
                                                    $completed_date),
                             'bottom',false);
       $dialog->add_hidden_field('completed_date',$completed_date);
       $dialog->set_row_colspan(4);
    }
    $request_type = get_row_value($rma->info,'request_type');
    $dialog->start_row('Request Type:','middle');
    $dialog->add_radio_field('request_type','0',$rma_replace_label,
                             $request_type === '0','change_request_type();');
    $dialog->write('&nbsp;&nbsp;');
    $dialog->add_radio_field('request_type','1','Refund',$request_type == 1,
                             'change_request_type();');
    $dialog->end_row();
    $refund_date = get_row_value($rma->info,'refund_date');
    $restocking_fee = get_row_value($rma->info,'restocking_fee');
    if (! $refund_date) {
       $dialog->start_row('Restocking Fee:');
       $dialog->add_input_field('restocking_fee',$rma->info,10,
                                'update_refund();');
       $dialog->end_row();
    }
    else if ($restocking_fee)
       $dialog->add_text_row('Restocking Fee:',
                             amount_format($rma,$restocking_fee));
    $refund_amount = get_row_value($rma->info,'refund_amount');
    if (! $refund_date) {
       if (payment_module_event_exists('cancel_payment')) {
          $include_process_refund = true;
          $dialog->end_current_row();
       }
       else $include_process_refund = false;
       $dialog->start_hidden_row('Refund Amount:','refund_amount_row',
                                 ($request_type != 1),'bottom','fieldprompt',
                                 null,true,3);
       $dialog->add_input_field('refund_amount',$refund_amount,10);
       if ($include_process_refund) {
          $dialog->write('&nbsp;&nbsp;&nbsp;');
          $dialog->add_checkbox_field('process_refund','Process Refund',false);
       }
       $dialog->end_row();
    }
    else if ($refund_amount)
       $dialog->add_text_row('Refund Amount:',
                             amount_format($rma,$refund_amount));
    if ($refund_date)
       $dialog->add_text_row('Refund Date:',date('F j, Y g:i:s a',
                                                 $refund_date),'bottom',false);
    $dialog->start_row('Package Opened:','middle');
    $opened = get_row_value($rma->info,'opened');
    $dialog->add_checkbox_field('opened','',$opened);
    $dialog->end_row();
    $reason = get_row_value($rma->info,'reason');
    if (count($reason_values) == 0) {
       if ($completed_date && $refund_date) {
          $num_cols = 3;   $field_width = 73;
       }
       else {
          $num_cols = 1;   $field_width = 24;
       }
       $dialog->start_row('Reason:','bottom','fieldprompt',null,
                          false,$num_cols);
       $dialog->add_input_field('reason',$reason,$field_width);
       $dialog->end_row();
    }
    else {
       $dialog->start_row('Reason:','middle');
       $dialog->start_choicelist('reason');   $reason_found = false;
       if (is_numeric($reason)) $reason = intval($reason);
       $dialog->add_list_item('','',! $reason);
       for ($loop = 0;  $loop < count($reason_values);  $loop++) {
          if (isset($reason_values[$loop])) {
             if ($reason === $loop) {
                $reason_found = true;   $selected = true;
             }
             else $selected = false;
             $dialog->add_list_item($loop,$reason_values[$loop],$selected);
          }
       }
       if ($reason && (! $reason_found))
          $dialog->add_list_item($reason,$reason,true);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    $dialog->end_current_row();
    $dialog->start_row('Reason Details:','top','fieldprompt',null,false,3);
    $dialog->start_textarea_field('reason_details',6,72,WRAP_SOFT);
    write_form_value(get_row_value($rma->info,'reason_details'));
    $dialog->end_textarea_field();
    $dialog->end_row();
    if ($enable_vendors) {
       $vendor = get_row_value($rma->info,'vendor');
       $dialog->start_row('Vendor:','middle');
       $query = 'select id,name from vendors order by name';
       $vendors = $db->get_records($query);
       if ($vendors) {
          $dialog->start_choicelist('vendor','select_vendor();');
          $dialog->add_list_item('','',(! $vendor));
          foreach ($vendors as $vendor_info) {
             $dialog->add_list_item($vendor_info['id'],
                $vendor_info['name'],($vendor_info['id'] == $vendor));
          }
          $dialog->end_choicelist();
       }
       $dialog->end_row();
       $dialog->add_edit_row('Vendor RMA #:','vendor_rma',$rma->info,25);
    }
    $dialog->start_row('Return Address:','top','fieldprompt',null,false,3);
    $dialog->start_textarea_field('return_address',6,72,WRAP_SOFT);
    write_form_value(get_row_value($rma->info,'return_address'));
    $dialog->end_textarea_field();
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    $dialog->write("</td><td align=\"right\">\n");

    $dialog->write("<div class=\"add_edit_rma_box add_edit_rma_address_box\">\n");
    $dialog->write("<div class=\"add_edit_rma_legend\">Shipping Address</div>\n");
    $dialog->start_field_table('shipping_table');
    $country = get_row_value($rma->info,'country');
    if ($country === '') $country = 1;
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('country','select_country(this,"");');
    load_country_list($country,true);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('First Name:','fname',
                          get_row_value($rma->info,'fname'),30);
    $dialog->add_edit_row('Middle Name:','mname',
                          get_row_value($rma->info,'mname'),30);
    $dialog->add_edit_row('Last Name:','lname',
                          get_row_value($rma->info,'lname'),30);
    $dialog->add_edit_row('Company:','company',
                          get_row_value($rma->info,'company'),30);
    $dialog->add_edit_row('Address Line 1:','address1',
                          get_row_value($rma->info,'address1'),30);
    $dialog->add_edit_row('Address Line 2:','address2',
                          get_row_value($rma->info,'address2'),30);
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" " .
                   "id=\"city_prompt\" nowrap>");
    if ($country == 29) $dialog->write('Parish');
    else $dialog->write('City');
    $dialog->write(":</td>\n");
    $dialog->write("<td><input type=\"text\" class=\"text\" name=\"city\" " .
                   "size=\"30\" value=\"");
    write_form_value(get_row_value($rma->info,'city'));
    $dialog->write("\"></td></tr>\n");
    $state = get_row_value($rma->info,'state');
    $dialog->start_hidden_row('State:','state_row',($country != 1),'middle');
    $dialog->start_choicelist('state');
    $dialog->add_list_item('','',false);
    load_state_list($state,true,$db);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','province_row',
       (($country == 1) || ($country == 29) || ($country == 43)));
    $dialog->add_input_field('province',$state,20);
    $dialog->end_row();
    $dialog->start_hidden_row('Province:','canada_province_row',
                              ($country != 43),'middle');
    $dialog->write("<select name=\"canada_province\" " .
                   "class=\"select add_edit_rma_state\">\n");
    $dialog->add_list_item('','',false);
    load_canada_province_list($state);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"zip_cell\">");
    if ($country == 1) $dialog->write('Zip Code:');
    else $dialog->write('Postal Code:');
    $dialog->write("</td><td>\n");
    $dialog->write("<input type=\"text\" class=\"text\" name=\"zipcode\" " .
                   "size=\"30\" value=\"");
    write_form_value(get_row_value($rma->info,'zipcode'));
    $dialog->write("\"></td></tr>\n");
    $address_type = get_row_value($rma->info,'address_type');
    if ($address_type === '') $address_type = get_address_type();
    $dialog->start_row('Address Type:','middle');
    $dialog->add_radio_field('address_type','1','Business',$address_type == 1,null);
    $dialog->write('&nbsp;&nbsp;');
    $dialog->add_radio_field('address_type','2','Residential',$address_type == 2,null);
    $dialog->end_row();
    $dialog->add_edit_row('Telephone:','phone',
                          get_row_value($rma->info,'phone'),30);
    $dialog->add_edit_row('Fax:','fax',
                          get_row_value($rma->info,'fax'),30);
    $dialog->add_edit_row('Mobile:','mobile',
                          get_row_value($rma->info,'mobile'),30);
    $dialog->add_edit_row('E-Mail Address:','email',
                          get_row_value($rma->info,'email'),30);
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    $dialog->end_row();
    $dialog->end_table();

    $dialog->start_table(null,'rma_layout_table');
    $dialog->write("<tr valign=\"top\"><td align=\"left\">");

    $dialog->write("<div class=\"add_edit_rma_box add_edit_rma_items_box\">\n");
    $dialog->write("<div class=\"add_edit_rma_legend\">Items</div>\n");
    $dialog->write("<table cellspacing=\"2\" cellpadding=\"0\" " .
                   "id=\"items_table\" class=\"add_edit_rma_item_table " .
                   "fieldtable\" width=\"577\">\n");
    $dialog->write("<tr><th class=\"fieldprompt\" width=\"50\" " .
                   "style=\"text-align: center;\">Return</th><th " .
                   "class=\"fieldprompt\" width=\"272\" " .
                   "style=\"text-align: left;\" nowrap>Product Name</th>" .
                   "<th class=\"fieldprompt\" width=\"100\" nowrap " .
                   "style=\"text-align: right;\">Unit Price</th>" .
                   "<th class=\"fieldprompt\" width=\"75\">Order Qty</th>" .
                   "<th class=\"fieldprompt\" width=\"75\">Return Qty</th>" .
                   "</tr>\n");

    if (($edit_type == UPDATERECORD) && $rma->order_items) {
       $index = 0;
       foreach ($rma->order_items as $item_id => $order_item) {
          $return_flag = false;   $return_qty = '';
          if ($rma->items) {
             foreach ($rma->items as $rma_item) {
                if ($rma_item['item_id'] == $item_id) {
                   $return_qty = $rma_item['qty'];
                   if ($return_qty === null) $return_qty = $order_item['qty'];
                   $return_flag = true;   break;
                }
             }
          }
          $price = $order_item['price'];
          $dialog->write("<tr valign=\"middle\">\n<td align=\"center\">");
          $dialog->add_hidden_field('item_id_'.$index,$item_id);
          $dialog->add_hidden_field('item_price_'.$index,$price);
          $dialog->add_hidden_field('item_qty_'.$index,$order_item['qty']);
          $dialog->add_checkbox_field('return_'.$index,'',$return_flag,
                                      'check_return('.$index.');');
          $dialog->write("</td><td>");
          write_form_value($order_item['product_name']);
          $dialog->write("</td>\n");
          $dialog->write("<td align=\"right\">$");
          $dialog->write(number_format($price,2));
          $dialog->write("</td>\n");
          $dialog->write("<td align=\"center\">".$order_item['qty'] .
                         "</td>\n");
          $dialog->write("<td align=\"center\">");
          $dialog->write('<input type="text" class="text" name="return_qty_' .
                         $index.'" id="return_qty_'.$index.'" size="1" value="');
          write_form_value($return_qty);
          $dialog->write('" onBlur="update_refund();">');
          $dialog->write("</td></tr>\n");
          $index++;
       }
    }

    $dialog->end_table();
    $dialog->write("</div>\n");

    $dialog->write("</td><td align=\"right\">\n");

    $dialog->write("<div class=\"add_edit_rma_box add_edit_rma_order_info_box\">\n");
    $dialog->write("<div class=\"add_edit_rma_legend\">Order Information</div>\n");
    $dialog->start_field_table('order_info_table');
    if ($edit_type == UPDATERECORD) {
       $sub_total = get_row_value($order_info,'subtotal');
       if ($sub_total > 0)
          $dialog->add_text_row('Sub Total:',amount_format($rma,$sub_total));
       $tax = get_row_value($order_info,'tax');
       if ($tax != 0)
          $dialog->add_text_row('Tax:',amount_format($rma,$tax));
       $shipping = get_row_value($order_info,'shipping');
       if ($shipping && (floatval($shipping) != 0))
          $dialog->add_text_row('Shipping:',amount_format($rma,$shipping));
       if ($coupon_code) $dialog->add_text_row('Coupon:',$coupon_code);
       if ($coupon_amount && (floatval($coupon_amount) != 0))
          $dialog->add_text_row('Coupon Amount:','-' .
                                amount_format($rma,$coupon_amount));
       if ($gift_amount && (floatval($gift_amount) != 0))
          $dialog->add_text_row('Gift Certificate:','-' .
                                amount_format($rma,$gift_amount));
       $fee_name = get_row_value($order_info,'fee_name');
       $fee_amount = get_row_value($order_info,'fee_amount');
       if ($fee_name || $fee_amount) {
          if (! $fee_name) $fee_name = 'Fee';
          $dialog->add_text_row($fee_name.':',amount_format($rma,$fee_amount));
       }
       $discount_name = get_row_value($order_info,'discount_name');
       if ($discount_name || $discount_amount) {
          if (! $discount_name) $discount_name = 'Discount';
          $dialog->add_text_row($discount_name.':','-' .
                                amount_format($rma,$discount_amount));
       }
       $total = get_row_value($order_info,'total');
       if ($total > 0)
          $dialog->add_text_row('Total:',amount_format($rma,$total));
       $balance_due = get_row_value($order_info,'balance_due');
       if ($balance_due && (floatval($balance_due) != 0))
          $dialog->add_text_row('Balance Due:',amount_format($rma,$balance_due));
    }
    $dialog->end_field_table();
    $dialog->write("</div>\n");

    $dialog->end_row();
    $dialog->end_table();

    $dialog->start_table(null,'rma_layout_table');
    $dialog->write("<tr><td align=\"right\">\n");

    $dialog->write("<div style=\"width:131px;\">");
    if ($edit_type == UPDATERECORD)
       $dialog->add_oval_button('Update RMA','update_rma();',120);
    else $dialog->add_oval_button('Create RMA','process_add_rma();',120);
    $dialog->write("</div>\n");

    $dialog->end_row();
    $dialog->end_table();

    $dialog->start_table(null,'rma_layout_table add_edit_rma_comments');
    $dialog->write("<tr valign=\"top\"><td align=\"left\">");

    $dialog->write("<div class=\"add_edit_rma_box add_edit_rma_comments_box\">\n");
    $dialog->write("<div class=\"add_edit_rma_legend\">Comments</div>\n");
    $dialog->start_textarea_field('comments',8,45,WRAP_SOFT);
    write_form_value(get_row_value($rma->info,'comments'));
    $dialog->end_textarea_field();
    $dialog->write("</div>\n");

    $dialog->write("</td><td align=\"right\">\n");

    $dialog->write("<div class=\"add_edit_rma_box add_edit_rma_notes_box\">\n");
    $dialog->write("<div class=\"add_edit_rma_legend\">Notes</div>\n");
    $dialog->start_textarea_field('notes',8,45,WRAP_SOFT);
    write_form_value(get_row_value($rma->info,'notes'));
    $dialog->end_textarea_field();
    $dialog->write("</div>\n");

    $dialog->end_row();
    $dialog->end_table();
}

function parse_rma_fields($db,&$rma_record)
{
    $db->parse_form_fields($rma_record);
    if (isset($rma_record['restocking_fee']['value']))
       $rma_record['restocking_fee']['value'] =
          parse_amount($rma_record['restocking_fee']['value']);
    if (isset($rma_record['refund_amount']['value']))
       $rma_record['refund_amount']['value'] =
          parse_amount($rma_record['refund_amount']['value']);
}

function add_rma()
{
    $rma = new RMAInfo();
    $rma->info = array();
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('rmas.css');
    $dialog->add_script_file('rmas.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_field_padding(2);
    $dialog->set_body_id('add_rma');
    $dialog->set_help('add_rma');
    $dialog->start_body('New RMA');
    $dialog->set_button_width(90);
    $dialog->start_button_column();
    $dialog->add_button('Add','images/AddOrder.png','process_add_rma();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('rmas.php','AddRMA');
    display_rma_fields($dialog,ADDRECORD,$rma,$db);
    $dialog->end_form();
    $dialog->end_body();
}

function save_rma_items($db,$rma_id)
{
    $item_record = rma_item_record_definition();
    $item_record['parent']['value'] = $rma_id;
    $index = 0;
    $return_flag = get_form_field('return_'.$index);
    while ($return_flag !== null) {
       if ($return_flag == 'on') {
          $item_record['item_id']['value'] = get_form_field('item_id_'.$index);
          $item_record['qty']['value'] = get_form_field('return_qty_'.$index);
          if (! $db->insert('rma_items',$item_record)) {
             http_response(422,$db->error);   return false;
          }
       }
       $index++;
       $return_flag = get_form_field('return_'.$index);
    }
    return true;
}

function process_rma_refund($db,$rma_id,$order_id)
{
    $process_refund = get_form_field('process_refund');
    if ($process_refund != 'on') return true;
    $refund_amount = get_form_field('refund_amount');
    if (! $refund_amount) return true;

    require_once 'orders-common.php';
    $query = 'select * from order_payments where (parent=?) and ' .
             '(parent_type=0) order by payment_date limit 1';
    $query = $db->prepare_query($query,$order_id);
    $payment_info = $db->get_record($query);
    if (! $payment_info) return true;

    $cancel_info = array();
    if (! cancel_order_payment($db,$payment_info,$refund_amount,
                               $cancel_info,$error)) {
       http_response(420,'Unable to process refund: '.$error);   return false;
    }
    if (! add_cancelled_payment($db,$payment_info,$refund_amount,
                                $cancel_info,$error)) {
       http_response(420,'Unable to process refund: '.$error);   return false;
    }
    $query = 'update rmas set refund_date=? where id=?';
    $query = $db->prepare_query($query,time(),$rma_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(420,$db->error);   return false;
    }
    return true;
}

function process_add_rma()
{
    $db = new DB;
    $rma_record = rma_record_definition();
    parse_rma_fields($db,$rma_record);
    $status = $rma_record['status']['value'];
    if ($status == 3) $rma_record['completed_date']['value'] = time();
    if (! $db->insert('rmas',$rma_record)) {
       http_response(422,$db->error);   return;
    }
    $rma_id = $db->insert_id();
    if (! save_rma_items($db,$rma_id)) return;
    log_activity('Created New RMA #'.$rma_id.' for Order #' .
                 $rma_record['order_id']['value'] .
                 $rma_record['email']['value'] .
                 ' ('.$rma_record['fname']['value'].' ' .
                 $rma_record['lname']['value'].')');

    $notify_flags = get_cart_config_value('notifications',$db);
    if (($notify_flags & NOTIFY_NEW_RMA_CUST) ||
        ($notify_flags & NOTIFY_NEW_RMA_ADMIN) ||
        (($status == 1) && ($notify_flags & NOTIFY_RMA_APPROVED)) ||
        (($status == 2) && ($notify_flags & NOTIFY_RMA_DENIED))) {
       $rma = load_rma($db,$rma_id,$error_msg);
       if (! $rma) {
          http_response(422,$error_msg);   return;
       }
       require_once '../engine/email.php';
       if ($notify_flags & NOTIFY_NEW_RMA_CUST) {
          $email = new Email(NEW_RMA_CUST_EMAIL,
                             array('rma' => 'obj','rma_obj' => $rma));
          if (! $email->send()) log_error($email->error);
       }
       if ($notify_flags & NOTIFY_NEW_RMA_ADMIN) {
          $email = new Email(NEW_RMA_ADMIN_EMAIL,
                             array('rma' => 'obj','rma_obj' => $rma));
          if (! $email->send()) log_error($email->error);
       }
       if (($status == 1) && ($notify_flags & NOTIFY_RMA_APPROVED)) {
          $email = new Email(RMA_APPROVED_EMAIL,
                             array('rma' => 'obj','rma_obj' => $rma));
          if (! $email->send()) log_error($email->error);
       }
       if (($status == 2) && ($notify_flags & NOTIFY_RMA_DENIED)) {
          $email = new Email(RMA_DENIED_EMAIL,
                             array('rma' => 'obj','rma_obj' => $rma));
          if (! $email->send()) log_error($email->error);
       }
    }

    $order_id = $rma_record['order_id']['value'];
    if (! process_rma_refund($db,$rma_id,$order_id)) return;

    http_response(201,'RMA #'.$rma_id.' Added');
}

function edit_rma()
{
    $db = new DB;
    $id = get_form_field('id');
    $rma = load_rma($db,$id,$error_msg);
    if (! $rma) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($error_msg,0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('rmas.css');
    $dialog->add_script_file('rmas.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_field_padding(2);
    $dialog_title = 'Edit RMA (#'.$id.')';
    $dialog->set_body_id('edit_rma');
    $dialog->set_help('edit_rma');
    $dialog->set_onload_function('update_refund();');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(90);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_rma();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('rmas.php','EditRMA');
    display_rma_fields($dialog,UPDATERECORD,$rma,$db);
    $dialog->end_form();
    $dialog->end_body();
}

function update_rma()
{
    $db = new DB;
    $rma_record = rma_record_definition();
    parse_rma_fields($db,$rma_record);
    $rma_id = $rma_record['id']['value'];
    $old_status = get_form_field('OldStatus');
    $new_status = $rma_record['status']['value'];
    if ($new_status == 3) $rma_record['completed_date']['value'] = time();
    if (! $db->update('rmas',$rma_record)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from rma_items where parent=?';
    $query = $db->prepare_query($query,$rma_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    if (! save_rma_items($db,$rma_id)) return;

    if (($new_status != $old_status) && ($new_status != 0)) {
       $notify_flags = get_cart_config_value('notifications',$db);
       if (($notify_flags & NOTIFY_RMA_APPROVED) ||
           ($notify_flags & NOTIFY_RMA_DENIED) ||
           ($notify_flags & NOTIFY_RMA_COMPLETED)) {
          $rma = load_rma($db,$rma_id,$error_msg);
          if (! $rma) {
             http_response(422,$error_msg);   return;
          }
          require_once '../engine/email.php';
          if (($new_status == 1) && ($notify_flags & NOTIFY_RMA_APPROVED)) {
             $email = new Email(RMA_APPROVED_EMAIL,
                                array('rma' => 'obj','rma_obj' => $rma));
             if (! $email->send()) log_error($email->error);
          }
          if (($new_status == 2) && ($notify_flags & NOTIFY_RMA_DENIED)) {
             $email = new Email(RMA_DENIED_EMAIL,
                                array('rma' => 'obj','rma_obj' => $rma));
             if (! $email->send()) log_error($email->error);
          }
          if (($new_status == 3) && ($notify_flags & NOTIFY_RMA_COMPLETED)) {
             $email = new Email(RMA_COMPLETED_EMAIL,
                                array('rma' => 'obj','rma_obj' => $rma));
             if (! $email->send()) log_error($email->error);
          }
       }
    }

    $order_id = $rma_record['order_id']['value'];
    if (! process_rma_refund($db,$rma_id,$order_id)) return;

    log_activity('Updated RMA #'.$rma_id);
    http_response(201,'RMA Updated');
}

function delete_rma()
{
    $id = get_form_field('id');
    $db = new DB;
    if (! delete_rma_record($db,$id,$error)) {
       http_response(422,$error);   return;
    }
    http_response(201,'RMA Deleted');
    log_activity('Deleted RMA #'.$id);
}

function load_order_items()
{
    require_once 'orders-common.php';

    $order_id = get_form_field('id');
    $db = new DB;
    $order = load_order($db,$order_id,$error_msg);
    if ((! $order) || (! $order->items)) return;
    if ($order->info['coupon_id']) {
       $query = 'select coupon_code from coupons where id=?';
       $query = $db->prepare_query($query,$order->info['coupon_id']);
       $row = $db->get_record($query);
       if ($row) $order->info['coupon_code'] = $row['coupon_code'];
       else $order->info['coupon_code'] = '';
    }
    else $order->info['coupon_code'] = '';
    $order_info = str_replace("'","\\'",json_encode($order->info));
    $order_info = str_replace("\\n",' ',$order_info);
    print "order_info = '".$order_info."';\n";
    foreach ($order->items as $item_id => $item) {
       print 'order_items['.$item_id.'] = {';
       print ' id: '.$item_id.',';
       print ' product_id: '.$item['product_id'].',';
       $item['product_name'] = str_replace("'","\\'",$item['product_name']);
       print " name: '".$item['product_name']."',";
       print ' price: '.$item['price'].',';
       print ' qty: '.$item['qty'];
       print " };\n";
    }
}

$cmd = get_form_field('cmd');

if (! check_login_cookie()) exit;

if ($cmd == 'addrma') add_rma();
else if ($cmd == 'processaddrma') process_add_rma();
else if ($cmd == 'viewrma') view_rma();
else if ($cmd == 'editrma') edit_rma();
else if ($cmd == 'updaterma') update_rma();
else if ($cmd == 'deleterma') delete_rma();
else if ($cmd == 'loadorderitems') load_order_items();
else display_rmas_screen();

DB::close_all();

?>
