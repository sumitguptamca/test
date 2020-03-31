<?php
/*
               Inroads Shopping Cart - Eye4Fraud Callback Interface

                        Written 2013-2014 by Randall Severy
                         Copyright 2013-2014 Inroads, LLC
*/

require_once '../engine/ui.php';
require_once '../engine/db.php';

define ("EYE4FRAUD_PENDING",$eye4fraud_status);
define ("EYE4FRAUD_FRAUD",($eye4fraud_status + 1));
define ("EYE4FRAUD_INSURED",($eye4fraud_status + 2));
define ("EYE4FRAUD_DECLINED",($eye4fraud_status + 3));
define ("EYE4FRAUD_APPROVED",($eye4fraud_status + 4));

$log_file = fopen('../admin/eye4fraud.log','at');
if ($log_file) {
   $msg = 'Received: '.str_replace("\n",' ',print_r(get_form_fields(),true));
   fwrite($log_file,'['.date('D M d Y H:i:s').'] '.$msg."\n");
   fclose($log_file);
}

$merchant_name = get_form_field('MerchantName');
$site_name = get_form_field('SiteName');
$order_number = get_form_field('OrderNumber');
$order_date = get_form_field('OrderDate');
$verification_result = get_form_field('VerificationResult');
if (! $verification_result)
   $verification_result = get_form_field('E4FResponse');
if ((! $order_number) || (! $verification_result)) {
   log_form_fields();   $error = "Invalid Form Data from Eye4Fraud";
   log_error($error);   print $error;   exit;
}
if ($verification_result == 'Review') exit;

$db = new DB;
$query = 'select status from orders where id='.$order_number;
$order_info = $db->get_record($query);
if (! $order_info) {
   if (isset($db->error)) print $db->error;
   else print "Order Not Found";
   $db->close();   exit;
}
if ($order_info['status'] != EYE4FRAUD_PENDING) {
   $error = "Order #".$order_number." not pending for Eye4Fraud";
   log_error($error);   print $error;   $db->close();   exit;
}
if ($verification_result == 'Fraud') $status = EYE4FRAUD_FRAUD;
else if ($verification_result == 'Insured') $status = EYE4FRAUD_INSURED;
else if ($verification_result == 'Declined') $status = EYE4FRAUD_DECLINED;
else if ($verification_result == 'Approved') $status = EYE4FRAUD_APPROVED;
else if ($verification_result == 'Allowed') {
   $db->close();   exit;
}
else {
   $error = "Invalid Eye4Fraud Verification Result: ".$verification_result;
   log_error($error);   print $error;   $db->close();   exit;
}
$query = 'update orders set status='.$status.' where id='.$order_number;
$db->log_query($query);
if (! $db->query($query)) {
   print $db->error;   $db->close();   exit;
}
log_activity("Updated Order #".$order_number." with Eye4Fraud Result " .
             $verification_result);
print "Order Updated";

DB::close_all();

?>
