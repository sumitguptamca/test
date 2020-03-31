<?php
/*
                    Inroads Shopping Cart - Manual Payment Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC
*/

global $log_cart_errors_enabled;
if (! function_exists('log_cart_error')) $log_cart_errors_enabled = false;

function manual_get_primary_module($db)
{
    return 'manual';
}

function manual_configure_checkout(&$cart)
{
    global $include_purchase_order;

    if (($cart->payment_module == 'manual') &&
        (! empty($include_purchase_order)))
       $cart->include_purchase_order = true;
}

function manual_process_payment(&$order)
{
    global $log_cart_errors_enabled;

    if ($log_cart_errors_enabled)
       log_cart_error('Manual Payment Module, skipping payment processing');
    return true;
}

?>
