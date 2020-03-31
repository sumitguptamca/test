<?php
/*
          Inroads Control Panel/Shopping Cart - Media Common Functions

                       Written 2018 by Randall Severy
                        Copyright 2018 Inroads, LLC
*/

function user_record_definition()
{
    $user_record = array();
    $user_record['id'] = array('type' => INT_TYPE);
    $user_record['id']['key'] = true;
    $user_record['library'] = array('type' => INT_TYPE);
    $user_record['username'] = array('type' => CHAR_TYPE);
    $user_record['password'] = array('type' => CHAR_TYPE);
    $user_record['firstname'] = array('type' => CHAR_TYPE);
    $user_record['lastname'] = array('type' => CHAR_TYPE);
    $user_record['email'] = array('type' => CHAR_TYPE);
    return $user_record;
}

?>
