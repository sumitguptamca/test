<?php
/*
                 Inroads Shopping Cart - Saved Credit Card Functions

                        Written 2015-2019 by Randall Severy
                         Copyright 2015-2019 Inroads, LLC
*/

function add_saved_cards_tab($dialog)
{
    $dialog->add_tab('cards_tab','Credit Cards','cards_tab',
                     'cards_content','change_tab');
}

function add_saved_cards_tab_section($dialog,$row,$db)
{
    $id = get_row_value($row,'id');

    $dialog->start_tab_content('cards_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script>create_saved_cards_grid(".$id.");</script>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();
}

function add_saved_cards_buttons($dialog)
{
    $dialog->add_button_separator('cards_buttons_row',20);
    $dialog->add_button('Add Card','images/AddImage.png',
                        'add_card();','add_card',null,false);
    $dialog->add_button('Edit Card','images/EditImage.png',
                        'edit_card();','edit_card',null,false);
    $dialog->add_button('Delete Card','images/DeleteImage.png',
                        'delete_card();','delete_card',null,false);
}

function card_record_definition()
{
    $card_record = array();
    $card_record['id'] = array('type' => INT_TYPE);
    $card_record['id']['key'] = true;
    $card_record['parent'] = array('type' => INT_TYPE);
    $card_record['profile_id'] = array('type' => CHAR_TYPE);
    $card_record['fname'] = array('type' => CHAR_TYPE);
    $card_record['mname'] = array('type' => CHAR_TYPE);
    $card_record['lname'] = array('type' => CHAR_TYPE);
    $card_record['company'] = array('type' => CHAR_TYPE);
    $card_record['address1'] = array('type' => CHAR_TYPE);
    $card_record['address2'] = array('type' => CHAR_TYPE);
    $card_record['city'] = array('type' => CHAR_TYPE);
    $card_record['state'] = array('type' => CHAR_TYPE);
    $card_record['zipcode'] = array('type' => CHAR_TYPE);
    $card_record['country'] = array('type' => INT_TYPE);
    $card_record['phone'] = array('type' => CHAR_TYPE);
    $card_record['fax'] = array('type' => CHAR_TYPE);
    $card_record['mobile'] = array('type' => CHAR_TYPE);
    $card_record['card_type'] = array('type' => CHAR_TYPE);
    $card_record['card_name'] = array('type' => CHAR_TYPE);
    $card_record['card_number'] = array('type' => CHAR_TYPE);
    $card_record['card_month'] = array('type' => CHAR_TYPE);
    $card_record['card_year'] = array('type' => CHAR_TYPE);
    $card_record['card_cvv'] = array('type' => CHAR_TYPE);
    return $card_record;
}

function display_card_fields($dialog,$edit_type,$row,$db)
{
    global $available_card_types;

    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field('id',get_row_value($row,'id'));
       $dialog->add_hidden_field('profile_id',
                                 get_row_value($row,'profile_id'));
    }
    $parent = get_row_value($row,'parent');
    $dialog->add_hidden_field('parent',$parent);

    $dialog->add_edit_row('First Name:','fname',$row,30);
    $dialog->add_edit_row('Middle Name:','mname',$row,30);
    $dialog->add_edit_row('Last Name:','lname',$row,30);
    $dialog->add_edit_row('Company:','company',$row,50);
    $country = get_row_value($row,'country');
    $dialog->add_edit_row('Address Line 1:','address1',$row,50);
    $dialog->add_edit_row('Address Line 2:','address2',$row,50);
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"city_prompt\">");
    if ($country == 29) print 'Parish';
    else print 'City';
    $dialog->write(":</td>\n");
    $dialog->write("<td><input type=\"text\" class=\"text\" name=\"city\" " .
                   "size=\"30\" value=\"");
    write_form_value(get_row_value($row,'city'));
    $dialog->write("\"></td></tr>\n");
    $state = get_row_value($row,'state');
    $dialog->start_hidden_row('State:','state_row',($country != 1),'middle');
    $dialog->start_choicelist('state',null);
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
    $dialog->start_choicelist('canada_province',null);
    $dialog->add_list_item('','',false);
    load_canada_province_list($state);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap " .
                   "id=\"zip_cell\">");
    if ($country == 1) $dialog->write('Zip Code:');
    else $dialog->write('Postal Code:');
    $dialog->write("</td><td>\n");
    $dialog->add_input_field('zipcode',$row,20);
    $dialog->end_row();
    $dialog->start_row('Country:','middle');
    $dialog->start_choicelist('country','select_country(this);');
    load_country_list($country,true,$db);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Telephone:','phone',$row,20);
    $dialog->add_edit_row('Fax:','fax',$row,20);
    $dialog->add_edit_row('Mobile:','mobile',$row,20);
    $dialog->start_row('Card Type:','middle');
    $card_type = get_row_value($row,'card_type');
    $dialog->start_choicelist('card_type');
    $dialog->add_list_item('','',(! $card_type));
    foreach ($available_card_types as $type => $card_name)
       $dialog->add_list_item($type,$card_name,($card_type == $type));
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Card Number:','card_number',$row,30);
    $dialog->start_hidden_row('CVV Number:','cc_row_2',true);
    $dialog->add_edit_row('CVV Number:','card_cvv',$row,10);
    $dialog->start_row('Expiration Date:','middle');
    $card_month = get_row_value($row,'card_month');
    if ($card_month === '') $card_month = date('m');
    $dialog->start_choicelist('card_month');
    for ($month = 1;  $month <= 12;  $month++) {
       $month_string = sprintf('%02d',$month);
       $dialog->add_list_item($month_string,$month_string,
                              $month == $card_month);
    }
    $dialog->end_choicelist();
    $dialog->start_choicelist('card_year');
    $card_year = get_row_value($row,'card_year');
    if ($card_year === '') $card_year = date('y');
    $start_year = date('y') - 10;
    for ($year = $start_year;  $year < $start_year + 20;  $year++) {
       $year_string = sprintf('%02d',$year);
       $dialog->add_list_item($year_string,'20'.$year_string,
                              $year == $card_year);
    }
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Name on Card:','card_name',
                          get_row_value($row,'card_name'),30);
}

function parse_card_fields($db,&$card_record,$edit_type)
{
    $db->parse_form_fields($card_record);
    if ($edit_type == ADDRECORD) unset($card_record['id']['value']);
    if (empty($card_record['country']['value']))
       $card_record['country']['value'] = 1;
    if ($card_record['country']['value'] == 43)
       $card_record['state']['value'] = get_form_field('canada_province');
    else if ($card_record['country']['value'] != 1)
       $card_record['state']['value'] = get_form_field('province');
    if (! empty($card_record['card_type']['value']))
       $card_record['card_type']['value'] =
          trim($card_record['card_type']['value']);
    if (! empty($card_record['card_name']['value']))
       $card_record['card_name']['value'] =
          trim($card_record['card_name']['value']);
    if (! empty($card_record['card_number']['value']))
       $card_record['card_number']['value'] =
          trim(preg_replace('/[^0-9]/','',
               $card_record['card_number']['value']));
    if (! empty($card_record['card_cvv']['value']))
       $card_record['card_cvv']['value'] =
          trim($card_record['card_cvv']['value']);
    if (! empty($card_record['card_month']['value']))
       $card_record['card_month']['value'] =
          trim($card_record['card_month']['value']);
    if (! empty($card_record['card_year']['value']))
       $card_record['card_year']['value'] =
          trim($card_record['card_year']['value']);
}

function get_customer_profile_id($db,$customer_id,&$error)
{
    $query = 'select profile_id from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $customer_info = $db->get_record($query);
    if (! $customer_info) {
       if (isset($db->error)) {
          $error = $db->error;   return false;
       }
       return null;
    }
    $profile_id = $customer_info['profile_id'];
    return $profile_id;
}

function mask_card_number(&$card_record)
{
    $card_number = $card_record['card_number']['value'];
    $card_number = substr($card_number,0,6) .
                   str_pad('',strlen($card_number) - 10,'X') .
                   substr($card_number,-4);
    $card_record['card_number']['value'] = $card_number;
}

function add_card()
{
    $db = new DB;
    $parent = get_form_field('parent');
    $query = 'select * from customers where id=?';
    $query = $db->prepare_query($query,$parent);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Customer #'.$parent.' Not Found',0);
       return;
    }
    $query = 'select * from billing_information where parent=?';
    $query = $db->prepare_query($query,$parent);
    $billing_row = $db->get_record($query);
    if (! $billing_row) {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,0);   return;
       }
    }
    else $row = array_merge($row,$billing_row);
    $row['parent'] = get_form_field('parent');

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('savedcards.css');
    $dialog->add_script_file('savedcards.js');
    $dialog->start_body('Add Saved Credit Card');
    $dialog->set_button_width(135);
    $dialog->start_button_column();
    $dialog->add_button('Add Card','images/AddCustomer.png',
                        'process_add_card();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('customers.php','AddCard');
    $dialog->start_field_table();
    display_card_fields($dialog,ADDRECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function add_card_record($db,$card_record,&$error)
{
    $customer_id = $card_record['parent']['value'];
    if ($customer_id) {
       $profile_id = get_customer_profile_id($db,$customer_id,$error);
       if ($profile_id === false) return null;
    }
    else $profile_id = null;
    $payment_info = $db->convert_record_to_array($card_record);

    if (! $profile_id) {
       if ($customer_id) $profile_customer_id = $customer_id;
       else $profile_customer_id = '999'.time();
       $profile_id = call_payment_event('create_saved_profile',
                        array($db,$profile_customer_id,&$error),true,true);
       if (! $profile_id) return null;
       if ($customer_id) {
          $query = 'update customers set profile_id=? where id=?';
          $query = $db->prepare_query($query,$profile_id,$customer_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             $error = $db->error;   return null;
          }
       }
       else $card_record['parent']['value'] = -$profile_id;
    }
    $payment_id = call_payment_event('create_saved_card',
                     array($db,$profile_id,$payment_info,&$error),true,true);
    if (! $payment_id) return null;
    $card_record['profile_id']['value'] = $payment_id;
    mask_card_number($card_record);
    if (! $db->insert('saved_cards',$card_record)) {
       $error = $db->error;   return null;
    }
    return $db->insert_id();
}

function process_add_card()
{
    $db = new DB;
    $card_record = card_record_definition();
    parse_card_fields($db,$card_record,ADDRECORD);
    $id = add_card_record($db,$card_record,$error);
    if (! $id) {
       http_response(422,$error);   return;
    }
    http_response(201,'Saved Credit Card Added');
    $activity = 'Added Saved Credit Card #'.$id;
    $customer_id = $card_record['parent']['value'];
    log_activity($activity.' to Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function edit_card()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from saved_cards where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Saved Credit Card not found',0);
       return;
    }

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('savedcards.css');
    $dialog->add_script_file('savedcards.js');
    $dialog_title = 'Edit Saved Credit Card (#'.$id.')';
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_card();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('customers.php','EditCard');
    $dialog->add_hidden_field('old_card_number',$row['card_number']);
    $dialog->start_field_table();
    display_card_fields($dialog,UPDATERECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_card_record($db,$card_record,&$error)
{
    $customer_id = $card_record['parent']['value'];
    $profile_id = get_customer_profile_id($db,$customer_id,$error);
    if ($profile_id === false) return false;
    if (! $profile_id) {
       $error = 'Customer Profile Missing';   return false;
    }
    $payment_info = $db->convert_record_to_array($card_record);
    $payment_id = $payment_info['profile_id'];

    if (! call_payment_event('update_saved_card',
             array($db,$profile_id,&$payment_id,$payment_info,&$error),false))
       return false;
    $card_record['profile_id']['value'] = $payment_id;
    mask_card_number($card_record);
    if (! $db->update('saved_cards',$card_record)) {
       $error = $db->error;   return false;
    }
    return true;
}

function update_card()
{
    $db = new DB;
    $card_record = card_record_definition();
    $db->parse_form_fields($card_record);
    if (! update_card_record($db,$card_record,$error)) {
       http_response(422,$error);   return;
    }
    http_response(201,'Saved Credit Card Updated');
    $activity = 'Updated Saved Credit Card #'.$card_record['id']['value'];
    $customer_id = $card_record['parent']['value'];
    log_activity($activity.' for Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function delete_card_record($db,$card_id,&$customer_id,&$error)
{
    $query = 'select parent,profile_id from saved_cards where id=?';
    $query = $db->prepare_query($query,$card_id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) $error = $db->error;
       else $error = 'Saved Credit Card Missing';
       return false;
    }
    $customer_id = $row['parent'];
    $payment_id = $row['profile_id'];
    $profile_id = get_customer_profile_id($db,$customer_id,$error);
    if ($profile_id === false) return false;
    if (! $profile_id) {
       $error = 'Customer Profile Missing';   return false;
    }

    if (! call_payment_event('delete_saved_card',
             array($db,$profile_id,$payment_id,&$error),false)) return false;
    $card_record = card_record_definition();
    $card_record['id']['value'] = $card_id;
    if (! $db->delete('saved_cards',$card_record)) {
       $error = $db->error;   return false;
    }
    return true;
}

function delete_card()
{
    $id = get_form_field('id');
    $db = new DB;
    $query = 'select parent from saved_cards where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(410,'Saved Credit Card Not Found');
       return;
    }
    $customer_id = $row['parent'];
    if (! delete_card_record($db,$id,$customer_id,$error)) {
       http_response(422,$error);   return;
    }
    http_response(201,'Saved Credit Card Deleted');
    $activity = 'Deleted Saved Credit Card #'.$id;
    log_activity($activity.' for Customer #'.$customer_id);
    write_customer_activity($activity.' by '.get_customer_activity_user($db),
                            $customer_id,$db);
}

function delete_customer_cards($db,$id)
{
    $profile_id = get_customer_profile_id($db,$id,$error);
    if ($profile_id === false) {
       http_response(422,$error);   return false;
    }
    if (! $profile_id) return true;

    if (! call_payment_event('delete_saved_profile',
                             array($db,$profile_id,&$error),false)) {
       http_response(422,$error);   return false;
    }
    $query = 'delete from saved_cards where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }
    $activity = 'Deleted Saved Credit Cards';
    log_activity($activity.' for Customer #'.$id);
    return true;
}

function delete_saved_cards()
{
    $id = get_form_field('id');
    if (! $id) {
       print "You must specify a Customer ID\n";   return;
    }
    $db = new DB();
    if (! delete_customer_cards($db,$id)) return;
    $query = 'update customers set profile_id=null where id=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    print 'Saved Cards Deleted for Customer #'.$id."\n";
}

function process_saved_card_command($cmd)
{
    if ($cmd == 'addsavedcard') add_card();
    else if ($cmd == 'processaddsavedcard') process_add_card();
    else if ($cmd == 'editsavedcard') edit_card();
    else if ($cmd == 'updatesavedcard') update_card();
    else if ($cmd == 'deletesavedcard') delete_card();
    else if ($cmd == 'deleteallsavedcard') delete_saved_cards();
}

if (! function_exists('log_payment')) {
   function log_payment($msg)
   {
       global $payment_log,$login_cookie;
   
       $payment_file = fopen($payment_log,"at");
       if ($payment_file) {
          $remote_user = getenv('REMOTE_USER');
          if (! $remote_user) $remote_user = get_cookie($login_cookie);
          if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
             $remote_user = $_SERVER['REMOTE_ADDR'];
          fwrite($payment_file,$remote_user." [".date("D M d Y H:i:s")."] ".$msg."\n");
          fclose($payment_file);
       }
   }
}

?>
