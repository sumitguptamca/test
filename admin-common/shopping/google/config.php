<?php
/*
           Inroads Shopping Cart - Google Shopping Cart Config Functions

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

function google_update_config_fields(&$cart_config_fields)
{
    $cart_config_fields[] = 'google_shopping_use_api';
    $cart_config_fields[] = 'google_shopping_merchant_id';
    $cart_config_fields[] = 'google_shopping_client_id';
    $cart_config_fields[] = 'google_shopping_email';
    $cart_config_fields[] = 'google_shopping_key_file';
    $cart_config_fields[] = 'google_shopping_last_refresh';
    $cart_config_fields[] = 'google_shopping_last_full_refresh';
}

function google_config_head($dialog,$db)
{
    $dialog->add_script_file('../admin/shopping/google/config.js');
}

function google_config_section($db,$dialog,$config_values)
{
    add_shopping_section($dialog,'Google Shopping');

    $dialog->start_row('Use API:','middle');
    $dialog->add_checkbox_field('google_shopping_use_api','',$config_values);
    $dialog->end_row();
    $dialog->add_edit_row('Merchant ID:','google_shopping_merchant_id',
                          $config_values,20);
    $dialog->add_edit_row('Key ID:','google_shopping_client_id',
                          $config_values,90);
    $dialog->add_edit_row('E-Mail Address:','google_shopping_email',
                          $config_values,90);
    $dialog->add_edit_row('Key File:','google_shopping_key_file',
                          $config_values,40);
    $last_refresh = get_row_value($config_values,
                                  'google_shopping_last_refresh');
    $dialog->add_hidden_field('google_shopping_last_refresh',$last_refresh);
    if ($last_refresh)
       $dialog->add_text_row('Last Refresh:',date('F j, Y g:i:s a',
                             $last_refresh));
    $last_refresh = get_row_value($config_values,
                                  'google_shopping_last_full_refresh');
    $dialog->add_hidden_field('google_shopping_last_full_refresh',
                              $last_refresh);
    if ($last_refresh)
       $dialog->add_text_row('Last Full Refresh:',date('F j, Y g:i:s a',
                             $last_refresh));
    $dialog->write("<tr><td></td><td style=\"padding:5px;\">" .
                   "<div class=\"buttonwrapper\">" .
                   "<a class=\"ovalbutton\" style=\"width: 180px;\" " .
                   "onClick=\"resubmit_google_products(); " .
                   "return false;\" href=\"#\"><span>" .
                   'Resubmit All Products</span></a></div></td></tr>');
}

function google_update_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'google_shopping_use_api') {
       if (get_form_field('google_shopping_use_api') == 'on')
          $new_field_value = 1;
       else $new_field_value = 0;
       return true;
    }
    return false;
}

?>
