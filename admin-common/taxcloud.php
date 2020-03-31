<?php
/*
                    Inroads Shopping Cart - TaxCloud API Module

                        Written 2015-2018 by Randall Severy
                         Copyright 2015-2018 Inroads, LLC
*/

define ('TAXCLOUD_WSDL_URL','https://api.taxcloud.net/1.0/?wsdl');

class Ping {
  public $apiLoginID;
  public $apiKey;
}

class CartItem {
  public $Index;
  public $ItemID;
  public $TIC;
  public $Price;
  public $Qty;
}

class TaxCloudAddress {
  public $Address1;
  public $Address2;
  public $City;
  public $State;
  public $Zip5;
  public $Zip4;
}

class ExemptionCertificate {
  public $CertificateID;
  public $Detail;
}

class Lookup {
  public $apiLoginID;
  public $apiKey;
  public $customerID;
  public $cartID;
  public $cartItems;
  public $origin;
  public $destination;
  public $deliveredBySeller;
  public $exemptCert;
}

class Authorized {
  public $apiLoginID;
  public $apiKey;
  public $customerID;
  public $cartID;
  public $cartItems;
  public $orderID;
  public $dateAuthorized;
}

class AuthorizedWithCapture {
  public $apiLoginID;
  public $apiKey;
  public $customerID;
  public $cartID;
  public $cartItems;
  public $orderID;
  public $dateAuthorized;
  public $dateCaptured;
}

class Captured {
  public $apiLoginID;
  public $apiKey;
  public $orderID;
}

class Returned {
  public $apiLoginID;
  public $apiKey;
  public $orderID;
  public $retCartItems;
  public $retDate;
}

class TaxCloud {

function TaxCloud()
{
    global $taxcloud_api_id,$taxcloud_api_key;

    $this->exception = null;   $this->error = null;
    $this->api_id = $taxcloud_api_id;   $this->api_key = $taxcloud_api_key;
    try {
       $this->ws = new SoapClient(TAXCLOUD_WSDL_URL);
    } catch (SoapFault $exception) {
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return;
    }
}

function log_activity($activity_msg)
{
    global $activity_log;

    $path_parts = pathinfo($activity_log);
    $taxcloud_activity_log = $path_parts['dirname'].'/taxcloud.log';
    $activity_file = @fopen($taxcloud_activity_log,"at");
    if ($activity_file) {
       fwrite($activity_file,'['.date('D M d Y H:i:s').'] ' .
              $activity_msg."\n");
       fclose($activity_file);
    }
}

function log_format($obj)
{
    if (! $obj) return '';
    $text = print_r($obj,true);
    $text = str_replace("\n",'',$text);
    $text = str_replace("\t",'',$text);
    $text = str_replace('  ','',$text);
    return $text;
}

function call($method,$params)
{
    $this->log_activity('Sent: '.$method.': '.$this->log_format($params));
    $params->apiLoginID = $this->api_id;
    $params->apiKey = $this->api_key;
    try {
       $result = $this->ws->$method($params);
    } catch (SoapFault $exception) {
       $this->log_activity('Exception: '.$this->log_format($exception));
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return null;
    }
    $this->log_activity('Response: '.$this->log_format($result));
    return $result;
}

function ping()
{
    $params = new Ping();
    $result = $this->call('Ping',$params);
    if ((! $result) && $this->error) return false;
    if (! isset($result->PingResult,$result->PingResult->ResponseType)) {
       $this->error = 'No ResponseType found in Result';   return false;
    }
    $this->response_type = $result->PingResult->ResponseType;
    if ($this->response_type == 'Error') {
       $this->error = $result->PingResult->Messages->ResponseMessage->Message;
       return false;
    }
    else if ($this->response_type == 'OK') return true;
    $this->error = 'Unknown Ping Error: '.$this->log_format($result);
    return false;
}

function lookup($customer_id,$cart_id,$cart_items,$origin,$destination)
{
    $params = new Lookup();
    $params->customerID = $customer_id;
    $params->cartID = $cart_id;
    $params->cartItems = $cart_items;
    $params->origin = $origin;
    $params->destination = $destination;
    $params->deliveredBySeller = false;
    $result = $this->call('Lookup',$params);
    if ((! $result) && $this->error) return false;
    if (! isset($result->LookupResult,$result->LookupResult->ResponseType)) {
       $this->error = 'No ResponseType found in Result';   return null;
    }
    $this->response_type = $result->LookupResult->ResponseType;
    if ($this->response_type == 'Error') {
       $this->error = $result->LookupResult->Messages->ResponseMessage->
                         Message;
       return null;
    }
    else if ($this->response_type != 'OK') {
       $this->error = 'Unknown Lookup Error: '.$this->log_format($result);
       return null;
    }
    $cart_item_response = $result->LookupResult->CartItemsResponse->
                          CartItemResponse;
    if (is_array($cart_item_response)) {
        $tax = 0;
        foreach ($cart_item_response as $element)
           $tax += $element->TaxAmount;
    }
    else $tax = $cart_item_response->TaxAmount;
    return $tax;
}

function authorized($customer_id,$cart_id,$cart_items,$order_id,$order_date)
{
    $params = new Authorized();
    $params->customerID = $customer_id;
    $params->cartID = $cart_id;
    $params->cartItems = $cart_items;
    $params->orderID = $order_id;
    $params->dateAuthorized = gmdate(DATE_ATOM,$order_date);
    $result = $this->call('Authorized',$params);
    if ((! $result) && $this->error) return false;
    if (! isset($result->AuthorizedResult,
                $result->AuthorizedResult->ResponseType)) {
       $this->error = 'No ResponseType found in Result';   return null;
    }
    $this->response_type = $result->AuthorizedResult->ResponseType;
    if ($this->response_type == 'Error') {
       $this->error = $result->AuthorizedResult->Messages->ResponseMessage->
                         Message;
       return null;
    }
    else if ($this->response_type != 'OK') {
       $this->error = 'Unknown Authorized Error: '.$this->log_format($result);
       return null;
    }
    return $result;
}

function authorized_with_capture($customer_id,$cart_id,$cart_items,$order_id,
                                 $order_date)
{
    $params = new AuthorizedWithCapture();
    $params->customerID = $customer_id;
    $params->cartID = $cart_id;
    $params->cartItems = $cart_items;
    $params->orderID = $order_id;
    $params->dateAuthorized = gmdate(DATE_ATOM,$order_date);
    $params->dateCaptured = gmdate(DATE_ATOM,$order_date);
    $result = $this->call('AuthorizedWithCapture',$params);
    if ((! $result) && $this->error) return false;
    if (! isset($result->AuthorizedWithCaptureResult,
                $result->AuthorizedWithCaptureResult->ResponseType)) {
       $this->error = 'No ResponseType found in Result';   return null;
    }
    $this->response_type = $result->AuthorizedWithCaptureResult->ResponseType;
    if ($this->response_type == 'Error') {
       $this->error = $result->AuthorizedWithCaptureResult->Messages->
                         ResponseMessage->Message;
       return null;
    }
    else if ($this->response_type != 'OK') {
       $this->error = 'Unknown AuthorizedWithCapture Error: ' .
                      $this->log_format($result);
       return null;
    }
    return $result;
}

function captured($order_id)
{
    $params = new Captured();
    $params->orderID = $order_id;
    $result = $this->call('Captured',$params);
    if ((! $result) && $this->error) return false;
    if (! isset($result->CapturedResult,
                $result->CapturedResult->ResponseType)) {
       $this->error = 'No ResponseType found in Result';   return null;
    }
    $this->response_type = $result->CapturedResult->ResponseType;
    if ($this->response_type == 'Error') {
       $this->error = $result->CapturedResult->Messages->ResponseMessage->
                         Message;
       return null;
    }
    else if ($this->response_type != 'OK') {
       $this->error = 'Unknown Captured Error: '.$this->log_format($result);
       return null;
    }
    return $result;
}

function returned($order_id,$cart_items,$return_date)
{
    $params = new Returned();
    $params->orderID = $order_id;
    $params->retCartItems = $cart_items;
    $params->retDate = $return_date;
    $result = $this->call('Returned',$params);
    if ((! $result) && $this->error) return false;
    if (! isset($result->ReturnedResult,
                $result->ReturnedResult->ResponseType)) {
       $this->error = 'No ResponseType found in Result';   return null;
    }
    $this->response_type = $result->ReturnedResult->ResponseType;
    if ($this->response_type == 'Error') {
       $this->error = $result->ReturnedResult->Messages->ResponseMessage->
                         Message;
       return null;
    }
    else if ($this->response_type != 'OK') {
       $this->error = 'Unknown Returned Error: '.$this->log_format($result);
       return null;
    }
    return $result;
}

};

function get_taxcloud_sales_tax($obj,$customer)
{
    if (! isset($customer->shipping)) return 0;
    if ((! isset($obj->items)) || (count($obj->items) == 0)) return 0;
    $cart_items = array();   $index = 1;
    foreach ($obj->items as $id => $obj_item) {
       $cart_item = new CartItem();
       $cart_item->Index = $index++;
       if (! $obj_item['product_id']) $cart_item->ItemID = 0;
       else $cart_item->ItemID = $obj_item['product_id'];
       $item_total = get_item_total($obj_item,false);
       $cart_item->Price = $item_total;
       $cart_item->Qty = $obj_item['qty'];
       $cart_items[] = $cart_item;
    }
    $query = 'select * from config where config_name like "map_%"';
    $config = $obj->db->get_records($query,'config_name');
    if (! $config) return 0;
    $origin = new TaxCloudAddress();
    $origin->Address1 = $config['map_address1']['config_value'];
    $origin->Address2 = $config['map_address2']['config_value'];
    $origin->City = $config['map_city']['config_value'];
    $origin->State = $config['map_state']['config_value'];
    $origin->Zip5 = $config['map_zip']['config_value'];
    if (strlen($origin->Zip5) > 5) $origin->Zip5 = substr($origin->Zip5,0,5);
    $destination = new TaxCloudAddress();
    $destination->Address1 = $customer->get('ship_address1');
    $destination->Address2 = $customer->get('ship_address2');
    $destination->City = $customer->get('ship_city');
    $destination->State = $customer->get('ship_state');
    $destination->Zip5 = $customer->get('ship_zipcode');
    if (strlen($destination->Zip5) > 5)
       $destination->Zip5 = substr($destination->Zip5,0,5);
    $customer_id = $customer->id;
    if ($customer_id === null) $customer_id = 0;
    $taxcloud = new TaxCloud;
    $tax_amount = $taxcloud->lookup($customer_id,$obj->id,$cart_items,
                                    $origin,$destination);
    if ($tax_amount === null) {
       log_error('TaxCloud Lookup Error: '.$taxcloud->error);   return 0;
    }
    return round($tax_amount,2);
}

function send_taxcloud_order($order)
{
    $customer_id = $order->info['customer_id'];
    if (isset($order->cart)) $cart_id = $order->cart->id;
    else $cart_id = 0;
    $cart_items = array();
    if (isset($order->items)) {
       $index = 1;
       foreach ($order->items as $id => $order_item) {
          $cart_item = new CartItem();
          $cart_item->Index = $index++;
          if (! $order_item['product_id']) $cart_item->ItemID = 0;
          else $cart_item->ItemID = $order_item['product_id'];
          $item_total = get_item_total($order_item,false);
          $cart_item->Price = $item_total;
          $cart_item->Qty = $order_item['qty'];
          $cart_items[] = $cart_item;
       }
    }
    $order_id = $order->id;
    $order_date = $order->info['order_date'];
    if (isset($order->payment['payment_status']))
       $payment_status = $order->payment['payment_status'];
    else $payment_status = PAYMENT_CAPTURED;

    $taxcloud = new TaxCloud;
    if ($payment_status == PAYMENT_AUTHORIZED)
       $result = $taxcloud->authorized($customer_id,$cart_id,$cart_items,
                                       $order_id,$order_date);
    else $result = $taxcloud->authorized_with_capture($customer_id,$cart_id,
                      $cart_items,$order_id,$order_date);
    if (! $result) log_error('TaxCloud Capture Error: '.$taxcloud->error);
}

function capture_taxcloud_order($order_id)
{
    $taxcloud = new TaxCloud;
    $result = $taxcloud->captured($order_id);
    if (! $result) log_error('TaxCloud Capture Error: '.$taxcloud->error);
}

?>
