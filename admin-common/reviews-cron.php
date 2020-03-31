<?php
/*
                 Inroads Shopping Cart - Product Review Cron Job

                        Written 2019 by Randall Severy
                         Copyright 2019 Inroads, LLC
*/

require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once 'cart-public.php';

function send_review_request_emails()
{
    global $review_request_days;

    if (empty($review_request_days)) $review_request_days = 4;
    $end_range  = mktime(0,0,0,date('m'),
                         date('d') - $review_request_days,date('Y'));
    $start_range  = mktime(0,0,0,date('m'),
                           date('d') - $review_request_days - 1,date('Y'));
    $db = new DB;
    $query = 'select id,customer_id from orders where (order_date>=?) and ' .
             '(order_date<=?)';
    $query = $db->prepare_query($query,$start_range,$end_range);
    $orders = $db->get_records($query);
    if (! $orders) {
       if (isset($db->error)) exit(1);
       return;
    }
    foreach ($orders as $row) {
       $order_id = $row['id'];
       $customer_id = $row['customer_id'];
       $customer = new Customer($db,$customer_id,true);
       if ($customer_id) $customer->load();
       $order = new Order($customer,$db,true);
       $order->load($order_id);
       if (! $customer_id) $customer->info = $order->info;
       require_once '../engine/email.php';
       $email = new Email(FEEDBACK_REQUEST_EMAIL,array('order' => 'obj',
                          'order_obj' => $order,'customer' => 'obj',
                          'customer_obj' => $customer));
       if (! $email->send()) log_error($email->error);
    }
}

set_remote_user('reviews');
send_review_request_emails();

?>
