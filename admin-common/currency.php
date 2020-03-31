<?php
/*
                    Inroads Shopping Cart - Currency Functions

                        Written 2008-2018 by Randall Severy
                         Copyright 2008-2018 Inroads, LLC
*/

function get_exchange_rate($from_currency,$to_currency)
{
    $host = 'download.finance.yahoo.com';
    $port = 80;
    $currency_param = $from_currency.$to_currency."=X";
    $path = "/d/quotes.csv?s=".$currency_param."&f=sl1";
    $rate = 0.0;

    $fp = @fsockopen($host,$port,$errno,$error_string,60);
    if (! $fp) {
       log_error($error_string." (".$errno.")");   return $rate;
    }

    fputs($fp,"GET ".$path." HTTP/1.1\r\n");
    fputs($fp,"Host: ".$host."\r\n");
    fputs($fp,"Cache-control: no-cache\r\n");
    fputs($fp,"Connection: close\r\n\r\n");

    $currency_param = "\"".$currency_param."\"";
    $status_code = 0;   $response_string = "";
    while (! feof($fp)) {
       $response = @fgets($fp,8124);
       $response = str_replace("\n","",$response);
       $response = str_replace("\r","",$response);
       if (substr($response,0,5) == "HTTP/") {
          $status_code = substr($response,9,3);   continue;
       }
       if (($status_code == 100) || ($status_code == 200)) {
          if (substr($response,0,6) == "Date: ") continue;
          if (substr($response,0,12) == "Connection: ") continue;
          if (substr($response,0,14) == "Content-Type: ") continue;
          if (substr($response,0,15) == "Cache-Control: ") continue;
          if (substr($response,0,5) == "P3P: ") continue;
          if (substr($response,0,19) == "Transfer-Encoding: ") continue;
          if ($response == "") continue;
          if (substr($response,0,10) == $currency_param)
             $rate = floatval(substr($response,11));
       }
       $response_string .= $response;
    }
    fclose($fp);
    if ($rate == 0.0) log_error("Invalid Rate Lookup Response: ".$response_string);
    return $rate;
}

function format_amount($amount,$currency="USD",$exchange_rate=null,
                       $precision=2,$html_symbol=true)
{
    $amount = floatval($amount);
    if (($exchange_rate !== null) && ($exchange_rate != 0.0))
       $amount * $exchange_rate;

    $sign = '';
    if ($currency == 'HUF') $precision = 0;
    if ($currency == 'EUR') {
       $dec_point = ',';   $thousands_sep = '.';
    }
    else {
       $dec_point = '.';   $thousands_sep = ',';
    }
    if (round($amount,2) == 0.0) {
       if ($precision == 2) $number_value = '0.00';
       else if ($precision == 1) $number_value = '0.0';
       else if ($precision == 0) $number_value = '0';
    }
    else if ($amount < 0) {
       $sign = '-';   $amount = -$amount;
    }
    $number_value = number_format($amount,$precision,$dec_point,
                                  $thousands_sep);

    if ($currency == 'EUR') {
       if ($html_symbol) return $sign.'&#8364;'.$number_value;
       else return $sign."€".$number_value;
    }
    else if ($currency == 'CAD') return $sign.'Can$'.$number_value;
    else if ($currency == 'HUF') return $sign.'Ft'.$number_value;
    else return $sign.'$'.$number_value;
}

function setup_exchange_rate(&$obj)
{
    global $currency_cookie;

    if ((! isset($currency_cookie)) || (! isset($_COOKIE[$currency_cookie]))) {
       $obj->exchange_rate = null;   return;
    }
    $currency = $_COOKIE[$currency_cookie];
    if ($currency == $obj->currency) {
       $obj->exchange_rate = null;   return;
    }
    if (isset($obj->info['currency'])) $obj->info['currency'] = $currency;
    $rate_cookie = $currency_cookie."Rate";
    if (isset($_COOKIE[$rate_cookie])) {
       $obj->currency = $currency;
       $obj->exchange_rate = $_COOKIE[$rate_cookie];   return;
    }
    $exchange_rate = get_exchange_rate($obj->currency,$currency);
    $obj->currency = $currency;
    $obj->exchange_rate = $exchange_rate;
    setcookie($rate_cookie,$exchange_rate,0,'/');
}

function change_currency(&$obj,$currency)
{
    global $default_currency;

    if ($currency == $obj->currency) return;
    if (! isset($default_currency)) $default_currency = "USD";
    $obj->currency = $currency;
    if (isset($obj->info['currency'])) $obj->info['currency'] = $currency;
    if ($obj->currency == $default_currency) $obj->exchange_rate = null;
    else $obj->exchange_rate = get_exchange_rate($default_currency,$currency);
}

?>
