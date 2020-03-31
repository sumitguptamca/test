<?php
/*
                Inroads Shopping Cart - Stone Edge Order Manager API Module

                        Written 2011-2018 by Randall Severy
                         Copyright 2011-2018 Inroads, LLC
*/

/*
     Notes: Does not use code or omversion form variables
*/

require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once 'cartconfig-common.php';
require_once 'orders-common.php';

define ("SETI_API_VERSION","1.000");

function parse_xml_value($response_string,$tag)
{
    $start_pos = strpos($response_string,"<".$tag.">");
    if ($start_pos === false) return null;
    $end_pos = strpos($response_string,"</".$tag.">");
    if ($end_pos === false) return null;
    $tag_length = strlen($tag);
    $value = substr($response_string,$start_pos + $tag_length + 2,
                    $end_pos - $start_pos - $tag_length - 2);
    return $value;
}

function print_field($tag_name,$field_name,$row)
{
    print "<".$tag_name.">".encode_xml_data(get_row_value($row,$field_name)) .
          "</".$tag_name.">";
}

function start_response($module)
{
    print "<?xml version='1.0'?><SETI".$module.">\n";
}

function seti_response($code,$description)
{
    print "<Response><ResponseCode>".$code."</ResponseCode>";
    print "<ResponseDescription>".encode_xml_data($description) .
          "</ResponseDescription></Response>\n";
}

function finish_response($module)
{
    print "</SETI".$module.">\n";
}

function seti_error($module,$error_msg)
{
    start_response($module);
    seti_response(3,$error_msg);
    finish_response($module);
    exit;
}

function check_query_limit(&$query)
{
    $startnum = get_form_field("startnum");
    $batchsize = get_form_field("batchsize");
    if (($startnum > 0) && $batchsize)
       $query .= " limit ".(intval($startnum) - 1).",".$batchsize;
}

function validate_user($db,$module)
{
    $username = get_form_field("setiuser");
    $password = get_form_field("password");
    if ((! $db) || isset($db->error)) {
       if ($db) seti_error($module,$db->error);
       else seti_error($module,"Unable to open database");
    }
    $query = 'select username,password from users where username=';
    if ($db->check_encrypted_field('users','username'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $db->prepare_query($query,$username);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error))
          seti_error($module,$db->error." (Username=".$username.")");
       else seti_error($module,"User Not Found");
    }
    $db->decrypt_record('users',$row);
    if ($password != $row['password']) seti_error($module,"Incorrect Password");
}

function send_version()
{
    print "SETIResponse: version=".SETI_API_VERSION."\n";
}

function order_count()
{
    $db = new DB;
    validate_user($db,"Orders");
    $lastorder = get_form_field("lastorder");
    if ($lastorder === null) seti_error("Orders","lastorder not specified");
    if (strtolower($lastorder) == 'all')
       $query = "select count(id) as num_orders from orders";
    else $query = "select count(id) as num_orders from orders where id>" .
                  $lastorder;
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) seti_error("Orders","Database Error: ".$db->error);
       else seti_error("Orders","Unable to load order information");
       return;
    }
    $num_orders = $row['num_orders'];
    print "SETIResponse: ordercount=".$num_orders."\n";
}

function print_order_data($db,$features,$order_id)
{
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) return;
    if ($features & USE_PART_NUMBERS) load_order_part_numbers($order);
    print "<Order>";
    print_field('OrderNumber','order_number',$order->info);
    print "<OrderDate>".date("Y-m-d H:i:s",get_row_value($order->info,'order_date')) .
          "</OrderDate>\n";
    $country_info = get_country_info(get_row_value($order->billing,'country'),$db);
    $full_name = get_row_value($order->info,'fname');
    $mname = get_row_value($order->info,'mname');
    if ($mname) $full_name .= " ".$mname;
    $full_name .= " ".get_row_value($order->info,'lname');
    print "<Billing><FullName>".encode_xml_data($full_name)."</FullName>";
    print_field('Company','company',$order->info);
    print_field('Phone','phone',$order->billing);
    print_field('Email','email',$order->info);   print "\n";
    print "<Address>";
    print_field('Street1','address1',$order->billing);
    print_field('Street2','address2',$order->billing);
    print_field('City','city',$order->billing);
    print_field('State','state',$order->billing);
    print_field('Code','zipcode',$order->billing);
    print "<Country>".encode_xml_data($country_info['code'])."</Country></Address>\n";
    print "</Billing>\n";
    $country_info = get_country_info(get_row_value($order->shipping,'country'),$db);
    print "<Shipping><FullName>";
    $shipto = get_row_value($order->shipping,'shipto');
    if ($shipto) print encode_xml_data($shipto);
    else print encode_xml_data($full_name);
    print "</FullName>";
    print_field('Company','company',$order->info);
    print_field('Phone','phone',$order->billing);
    print_field('Email','email',$order->info);   print "\n";
    print "<Address>";
    print_field('Street1','address1',$order->shipping);
    print_field('Street2','address2',$order->shipping);
    print_field('City','city',$order->shipping);
    print_field('State','state',$order->shipping);
    print_field('Code','zipcode',$order->shipping);
    print "<Country>".encode_xml_data($country_info['code'])."</Country></Address>\n";
    if ($order->items) {
       foreach ($order->items as $item_id => $order_item) {
          print "<Product>";
          if ($features & USE_PART_NUMBERS)
             print_field('SKU','part_number',$order_item);
          print "<Name>".encode_xml_data(get_html_product_name($order_item['product_name'],
                                            GET_PROD_ADMIN_VIEW_ORDER,
                                            $order,$order_item))."</Name>";
          print_field('Quantity','qty',$order_item);
          print_field('ItemPrice','price',$order_item);
          $attributes = $order_item['attribute_array'];
          if (count($attributes) > 0) {
             foreach ($attributes as $index => $attribute) {
                if (! $attribute['id']) continue;
                print "\n<OrderOption>";
                print_field('OptionName','attr',$attribute);
                print_field('SelectedOption','option',$attribute);
                print_field('OptionPrice','price',$attribute);
                print "</OrderOption>";
             }
          }
          print "</Product>\n";
       }
    }
    print "</Shipping>\n";
    print "<Payment><CreditCard>";
    print_field('Issuer','card_type',$order->info);
    print_field('TransID','payment_id',$order->info);
    print_field('AuthCode','payment_code',$order->info);
    print "</Payment>\n";
    print "<Totals>";
    print_field('ProductTotal','subtotal',$order->info);
    $coupon_id = get_row_value($order->info,'coupon_id');
    if (isset($coupon_id) && ($coupon_id != "")) {
       $coupon_amount = get_row_value($order->info,'coupon_amount');
       print "<Discount><Type>Flat</Type><Amount>".$coupon_amount."</Amount></Discount>";
    }
    $discount_name = get_row_value($order->info,'discount_name');
    if (isset($discount_name) && ($discount_name != "")) {
       $discount_amount = get_row_value($order->info,'discount_amount');
       print "<Discount><Type>Flat</Type><Amount>".$discount_amount."</Amount></Discount>";
    }

    print "<Subtotal>".get_row_value($order->info,'subtotal')."</Subtotal>";
    print "<Tax><TaxAmount>".get_row_value($order->info,'tax')."</TaxAmount></Tax>";
    print "<GrandTotal>".get_row_value($order->info,'total')."</GrandTotal>";
    $shipping_carrier = get_row_value($order->info,'shipping_carrier');
    if ($shipping_carrier != '') {
       require_once $shipping_carrier.".php";
       $format_shipping_field = $shipping_carrier."_format_shipping_field";
       $shipping_method = $format_shipping_field($order->info,'shipping_method');
       print "<ShippingTotal><Total>".get_row_value($order->info,'shipping') .
             "</Total><Description>".encode_xml_data($shipping_method) .
             "</Description></ShippingTotal>";
    }
    print "</Totals>\n";
    print "<Other>";
    print_field('Comments','comments',$order->info);
    print "</Other>\n";
    print "</Order>\n";
}

function download_orders()
{
/*
    Notes: Does not provide detailed order item or option data
           Does not provide detailed payment data
*/
    $db = new DB;
    validate_user($db,"Orders");
    $lastorder = get_form_field("lastorder");
    if ($lastorder === null) seti_error("Orders","lastorder not specified");
    if (strtolower($lastorder) == 'all')
       $query = "select id from orders";
    else $query = "select id from orders where id>".$lastorder;
    check_query_limit($query);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) {
          log_error($query);   seti_error("Orders","Database Error: ".$db->error);
       }
       start_response("Orders");
       seti_response(2,"Success");
       finish_response("Orders");
       return;
    }
    $features = get_cart_config_value('features',$db);

    start_response("Orders");
    seti_response(1,"Success");
    while ($row = $db->fetch_assoc($result))
       print_order_data($db,$features,$row['id']);
    $db->free_result($result);
    finish_response("Orders");
    log_activity("Processed StoneEdge downloadorders API function");
}

function get_customers_count()
{
    $db = new DB;
    validate_user($db,"Customers");
    $query = "select count(id) as num_customers from customers";
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) seti_error("Customers","Database Error: ".$db->error);
       else seti_error("Customers","Unable to load customer information");
       return;
    }
    $num_customers = $row['num_customers'];
    print "SETIResponse: itemcount=".$num_customers."\n";
}

function print_customer_data($db,$customer_info)
{
    $customer_id = get_row_value($customer_info,'id');
    $query = "select * from billing_information where parent=".$customer_id;
    $billing_info = $db->get_record($query);
    if (! $billing_info) return;
    $query = "select * from shipping_information where parent=".$customer_id .
             " order by default_flag desc limit 1";
    $shipping_info = $db->get_record($query);
    if (! $shipping_info) return;

    print "<Customer>";
    print_field('WebID','id',$customer_info);
    print_field('UserName','email',$customer_info);
    print_field('Password','password',$customer_info);
    $country_info = get_country_info(get_row_value($billing_info,'country'),$db);
    print "\n<BillAddr>";
    print_field('FirstName','fname',$customer_info);
    print_field('MiddleName','mname',$customer_info);
    print_field('LastName','lname',$customer_info);
    print_field('Company','company',$customer_info);
    print_field('Phone','phone',$billing_info);
    print_field('Fax','fax',$billing_info);
    print_field('Email','email',$customer_info);
    print "\n<Address>";
    print_field('Addr1','address1',$billing_info);
    print_field('Addr2','address2',$billing_info);
    print_field('City','city',$billing_info);
    print_field('State','state',$billing_info);
    print_field('Zip','zipcode',$billing_info);
    print "<Country>".encode_xml_data($country_info['code'])."</Country></Address>\n";
    print "</BillAddr>\n";
    $country_info = get_country_info(get_row_value($shipping_info,'country'),$db);
    print "<ShipAddr>";
    print_field('FirstName','fname',$customer_info);
    print_field('MiddleName','mname',$customer_info);
    print_field('LastName','lname',$customer_info);
    print_field('Company','company',$customer_info);
    print_field('Phone','phone',$billing_info);
    print_field('Fax','fax',$billing_info);
    print_field('Email','email',$customer_info);
    print "\n<Address>";
    print_field('Addr1','address1',$shipping_info);
    print_field('Addr2','address2',$shipping_info);
    print_field('City','city',$shipping_info);
    print_field('State','state',$shipping_info);
    print_field('Zip','zipcode',$shipping_info);
    print "<Country>".encode_xml_data($country_info['code'])."</Country></Address>\n";
    print "</ShipAddr>\n";
    print "</Customer>\n";
}

function download_customers()
{
    $db = new DB;
    validate_user($db,"Customers");
    $query = "select * from customers";
    check_query_limit($query);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) {
          log_error($query);   seti_error("Customers","Database Error: ".$db->error);
       }
       start_response("Customers");
       seti_response(2,"Success");
       finish_response("Customers");
       return;
    }

    start_response("Customers");
    seti_response(1,"Success");
    while ($row = $db->fetch_assoc($result)) print_customer_data($db,$row);
    $db->free_result($result);
    finish_response("Customers");
    log_activity("Processed StoneEdge downloadcustomers API function");
}

function get_products_count()
{
    $db = new DB;
    validate_user($db,"Products");
    $query = "select count(id) as num_products from products";
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) seti_error("Products","Database Error: ".$db->error);
       else seti_error("Products","Unable to load product information");
       return;
    }
    $num_products = $row['num_products'];
    print "SETIResponse: itemcount=".$num_products."\n";
}

function print_product_data($db,$product_info)
{
    $product_id = get_row_value($product_info,'id');
    $query = "select * from product_inventory where parent=".$product_id;
    $result = $db->query($query);
    if (! $result) return;

    while ($inventory_info = $db->fetch_assoc($result)) {
       print "<Product>";
       print_field('Code','part_number',$inventory_info);
       print_field('WebID','id',$product_info);
       print_field('Name','name',$product_info);
       print_field('Price','price',$inventory_info);
       print_field('Cost','cost',$inventory_info);
       print_field('Description','long_description',$product_info);
       print_field('Weight','weight',$inventory_info);
       print_field('QOH','qty',$inventory_info);
       print "</Product>\n";
    }
    $db->free_result($result);
}

function download_products()
{
/*
    Notes: Does not provide product option information
*/
    $db = new DB;
    validate_user($db,"Products");
    $query = "select * from products";
    check_query_limit($query);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) {
          log_error($query);   seti_error("Products","Database Error: ".$db->error);
       }
       start_response("Products");
       seti_response(2,"Success");
       finish_response("Products");
       return;
    }

    start_response("Products");
    seti_response(1,"Success");
    while ($row = $db->fetch_assoc($result)) print_product_data($db,$row);
    $db->free_result($result);
    finish_response("Products");
    log_activity("Processed StoneEdge downloadprods API function");
}

function print_inventory_data($db,$inventory_info)
{
    print "<Product>";
    print_field('Code','part_number',$inventory_info);
    print_field('WebID','parent',$inventory_info);
    print_field('QOH','qty',$inventory_info);
    print "</Product>\n";
}

function download_quantities()
{
    $db = new DB;
    validate_user($db,"Products");
    $query = "select * from product_inventory";
    check_query_limit($query);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) {
          log_error($query);   seti_error("Products","Database Error: ".$db->error);
       }
       start_response("Products");
       seti_response(2,"Success");
       finish_response("Products");
       return;
    }

    start_response("Products");
    seti_response(1,"Success");
    while ($row = $db->fetch_assoc($result)) print_inventory_data($db,$row);
    $db->free_result($result);
    finish_response("Products");
    log_activity("Processed StoneEdge downloadqoh API function");
}

function inventory_record_definition()
{
    $inventory_record = array();
    $inventory_record['part_number'] = array('type' => CHAR_TYPE);
    $inventory_record['part_number']['key'] = true;
    $inventory_record['qty'] = array('type' => INT_TYPE);
    return $inventory_record;
}

function upload_quantities($increment)
{
    $db = new DB;
    validate_user($db,"Products");
    $num_items = get_form_field("count");
    $product_data = get_form_field("update");
    if (substr($product_data,0,14) == '<SETIProducts>') {
       $product_fields = explode('<Product>',$product_data);
       $inventory_data = array();
       foreach ($product_fields as $index => $product_info) {
          if ($index == 0) continue;
          $sku = parse_xml_value($product_info,"SKU");
          if ($sku === null) continue;
          $qty = parse_xml_value($product_info,"QOH");
          if ($qty === null) continue;
          $inventory_data[$sku] = $qty;
       }
    }
    else {
       $product_fields = explode('|',$product_data);
       $inventory_data = array();
       foreach ($product_fields as $index => $inventory_info) {
          $inventory_fields = explode('~',$inventory_info);
          if (count($inventory_fields) != 2) continue;
          $inventory_data[$inventory_fields[0]] = $inventory_fields[1];
       }
    }

    if (! $increment) print "SETIResponse\n";
    $inventory_record = inventory_record_definition();
    $notify_flags = get_cart_config_value('notifications',$db);
    foreach ($inventory_data as $sku => $qty) {
       $inventory_record['part_number']['value'] = $sku;
       if ($increment) {
          $query = "select qty from product_inventory where part_number='" .
                   $db->escape($sku)."'";
          $inventory_info = $db->get_record($query);
          if (! $inventory_info) {
             print "SETIRESPONSE=False;SKU=".$sku.";QOH=NF;NOTE=NotFound\n";
             if (isset($db->error)) log_error($db->error);
             continue;
          }
          $inventory_record['qty']['value'] = intval($inventory_info['qty']) +
                                              intval($qty);
       }
       else $inventory_record['qty']['value'] = $qty;
       if (! $db->update("product_inventory",$inventory_record)) {
          log_error($db->error);
          if ($increment) 
             print "SETIRESPONSE=False;SKU=".$sku.";QOH=NA;NOTE=Error\n";
          else print $sku."=".$db->error."\n";
       }
       else if ($increment)
          print "SETIRESPONSE=OK;SKU=".$sku.";QOH=".$inventory_record['qty']['value'] .
                ";NOTE=\n";
       else print $sku."=OK\n";
       if ($notify_flags & NOTIFY_LOW_QUANTITY) check_low_quantity(null,$sku);
    }
    if (! $increment) print "SETIEndOfData\n";
    if ($increment) log_activity("Processed StoneEdge invupdate API function");
    else log_activity("Processed StoneEdge qohreplace API function");
}

function order_record_definition()
{
    $order_record = array();
    $order_record['id'] = array('type' => INT_TYPE);
    $order_record['id']['key'] = true;
    $order_record['status'] = array('type' => INT_TYPE);
    $order_record['tracking'] = array('type' => CHAR_TYPE);
    $order_record['shipped_date'] = array('type' => INT_TYPE);
    $order_record['notes'] = array('type' => CHAR_TYPE);
    return $order_record;
}

function update_order_status()
{
/*
    Notes: Needs to handle multiple tracknum,trackcarrier,trackpickupdate
           variables based on trackcount variable
           Does not use refnumber, trackcarrier, trackpickupdate variables
           Does not use Items block from XML data
*/
    $db = new DB;
    validate_user($db,"Orders");
    $status_data = get_form_field("update");
    if ($status_data) {
       $order_id = parse_xml_value($status_data,"OrderNumber");
       $status = parse_xml_value($status_data,"Status");
       $refnumber = parse_xml_value($status_data,"ReferenceNumber");
       $notes = parse_xml_value($status_data,"Notes");
       $tracking = parse_xml_value($status_data,"TrackingID");
       $tracking_carrier = parse_xml_value($status_data,"Shipper");
       $tracking_date = parse_xml_value($status_data,"PickupDate");
       $tracking_count = 1;

    }
    else {
       $order_id = get_form_field("ordernumber");
       $status = get_form_field("orderstatus");
       $refnumber = get_form_field("refnumber");
       $notes = get_form_field("orderdetail");
       $tracking = get_form_field("tracknum");
       $tracking_carrier = get_form_field("trackcarrier");
       $tracking_date = get_form_field("trackpickupdate");
       $tracking_count = get_form_field("trackcount");
    }
    $query = "select status,tracking,notes from orders where id=".$order_id;
    $order_info = $db->get_record($query);
    if (! $order_info) {
       if (isset($db->error)) {
          log_error($db->error);
          print "SETIRESPONSE: update=False;Notes=".$db->error."\n";
       }
       else print "SETIRESPONSE: update=False;Notes=Unable to find order #".$order_id."\n";
       return;
    }
    $order_record = order_record_definition();
    $order_record['id']['value'] = $order_id;
    if ($status == 'Shipped') $order_record['status']['value'] = 1;
    if ($order_info['tracking'])
       $tracking = $order_info['tracking'].", ".$tracking;
    if ($order_info['notes'])
       $notes = $order_info['notes'].", ".$notes;
    $order_record['tracking']['value'] = $tracking;
    $order_record['notes']['value'] = $notes;
    if (! $db->update("orders",$order_record)) {
       log_error($db->error);
       print "SETIRESPONSE: update=False;Notes=".$db->error."\n";
       return;
    }
    if ($status == 'Shipped') {
       $order = load_order($db,$order_id,$error_msg);
       if (! $order) {
          if (isset($db->error)) log_error($db->error);
       }
       else change_order_status($order_info['status'],1,$db,$order);
    }
    print "SETIRESPONSE: update=OK;Notes=\n";
    log_activity("Processed StoneEdge updatestatus API function");
}

$function = get_form_field("setifunction");
if ($function == "sendversion") send_version();
else if ($function == "ordercount") order_count();
else if ($function == "downloadorders") download_orders();
else if ($function == "getcustomerscount") get_customers_count();
else if ($function == "downloadcustomers") download_customers();
else if ($function == "getproductscount") get_products_count();
else if ($function == "downloadprods") download_products();
else if ($function == "downloadqoh") download_quantities();
else if ($function == "qohreplace") upload_quantities(false);
else if ($function == "invupdate") upload_quantities(true);
else if ($function == "updatestatus") update_order_status();
else seti_error("Orders","No setifunction specified");

DB::close_all();

?>
