<?php
/*
       Inroads Shopping Cart - Facebook Commerce Shopping Cart Config Functions

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC

*/

function facebook_update_config_fields(&$cart_config_fields)
{
    $cart_config_fields[] = 'facebook_feed_type';
}

function facebook_config_section($db,$dialog,$config_values)
{
    global $ssl_url;

    add_shopping_section($dialog,'Facebook Commerce');

    $dialog->start_row('Feed Type:','middle');
    $dialog->add_radio_field('facebook_feed_type','Commerce',
                             'Commerce&nbsp;&nbsp;&nbsp;',$config_values);
    $dialog->add_radio_field('facebook_feed_type','Dynamic Ads','Dynamic Ads',
                             $config_values);
    $dialog->end_row();
    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
    $url = $ssl_url.'admin/shopping/facebook/feed.php';
    $dialog->add_text_row('Data Feed URL:','<a href="'.$url .
                          '" target="_blank">'.$url.'</a>');
}

?>
