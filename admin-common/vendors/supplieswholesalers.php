<?php
/*
            Inroads Shopping Cart - Supplies Wholesalers Vendor Module

                         Written 2019 by Randall Severy
                          Copyright 2019 Inroads, LLC
*/

require_once '../engine/http.php';

class Query {
   public $CustomerNumber;
   public $Password;
}

class GetInventoryItems {
  public $query;
}

class GetAllStockInformationByCustomer {
  public $query;
}

class SuppliesWholesalers {

function __construct($db,$vendor_id)
{
    ini_set('default_socket_timeout',600);
    $this->wsdl_url =
       'https://services.supplieswholesalers.com:83/StockService.asmx?wsdl';
    $this->db = $db;
    $this->vendor_id = $vendor_id;
    $query = 'select * from vendors where id=?';
    $query = $this->db->prepare_query($query,$this->vendor_id);
    $this->vendor_info = $this->db->get_record($query);
    $this->cookie_header = null;
    $this->error = null;
    try {
       $this->ws = new SoapClient($this->wsdl_url);
    } catch (SoapFault $exception) {
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return;
    }

}

function SuppliesWholesalers($db,$vendor_id)
{
    self::__construct($db,$vendor_id);
}

function call($method,$params)
{
    $query = new Query();
    $query->CustomerNumber = $this->vendor_info['username'];
    $query->Password = $this->vendor_info['password'];
    $params->query = $query;
    try {
       $result = $this->ws->$method($params);
    } catch (SoapFault $exception) {
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return null;
    }
    return $result;
}

function get_inventory_items()
{
    $params = new GetInventoryItems();
    $result = $this->call('GetInventoryItems',$params);
    if (! $result) return null;
    if (empty($result->GetInventoryItemsResult) || 
        (! isset($result->GetInventoryItemsResult->WasSuccessful))) {
       $this->error = 'Invalid response from GetInventoryItems';
       return null;
    }
    if (! $result->GetInventoryItemsResult->WasSuccessful) {
       if (! isset($result->GetInventoryItemsResult->ExceptionMessage))
          $this->error = 'Unknown error in GetInventoryItems';
       else $this->error = $result->GetInventoryItemsResult->ExceptionMessage;
       return null;
    }
    if (empty($result->GetInventoryItemsResult->InventoryItems->
              StockServiceInventoryItem)) {
       $this->error = 'No inventory items returned by GetInventoryItems';
       return null;
    }
    return $result->GetInventoryItemsResult->InventoryItems->
           StockServiceInventoryItem;
}

function get_stock_information()
{
    $params = new GetAllStockInformationByCustomer();
    $result = $this->call('GetAllStockInformationByCustomer',$params);
    if (! $result) return null;
    if (empty($result->GetAllStockInformationByCustomerResult) || 
        (! isset($result->GetAllStockInformationByCustomerResult->
                 WasSuccessful))) {
       $this->error = 'Invalid response from GetAllStockInformationByCustomer';
       return null;
    }
    if (! $result->GetAllStockInformationByCustomerResult->WasSuccessful) {
       if (! isset($result->GetAllStockInformationByCustomerResult->
                   ExceptionMessage))
          $this->error = 'Unknown error in GetAllStockInformationByCustomer';
       else $this->error = $result->GetAllStockInformationByCustomerResult->
                           ExceptionMessage;
       return null;
    }
    if (empty($result->GetAllStockInformationByCustomerResult->StockEntries->
              StockAll)) {
       $this->error = 'No inventory items returned by ' .
                      'GetAllStockInformationByCustomer';
       return null;
    }
    return $result->GetAllStockInformationByCustomerResult->StockEntries->
           StockAll;
}

};

function supplieswholesalers_install($db)
{
    global $vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
}

function supplieswholesalers_upgrade($db)
{
    global $vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
}

function supplieswholesalers_update_conversions($db,&$conversions)
{
    $conversions['swimage'] = 'Download SW Image';
}

function supplieswholesalers_import_field_names($db,$import,&$field_names)
{
    switch ($import['import_type']) {
       case PRODUCTS_IMPORT_TYPE:
          $field_names = array('OemNumbers','ProductCode','Description',
             'Description2','Weight','NormalDealerCost','SuggestedSellPrice',
             'ProductClassificationCode','BatteryTypeOrPageYields','AlsoFits',
             'Units','ProdUrl');
          break;
       case INVENTORY_IMPORT_TYPE:
          $field_names = array('StockItem','Price','UnitsOnBackOrder',
                               'UnitsOnHand');
          break;
    }
}

function supplieswholesalers_get_data($db,&$import,&$data)
{
    $vendor_id = $import['parent'];
    $sw = new SuppliesWholesalers($db,$vendor_id);
    $data = array();
    supplieswholesalers_import_field_names($db,$import,$field_names);
    if ($import['import_type'] == PRODUCTS_IMPORT_TYPE) {
       $inventory = $sw->get_inventory_items();
       if (! $inventory) {
          process_import_error('Unable to retrieve Supplies Wholesalers ' .
                               'Inventory Data: '.$sw->error);
          return false;
       }
       foreach ($inventory as $item) {
          $row = array();
          foreach ($field_names as $index => $field_name) {
             if (! isset($item->$field_name)) $row[$index] = '';
             else $row[$index] = $item->$field_name;
          }
          $data[] = $row;
       }
    }
    else if ($import['import_type'] == INVENTORY_IMPORT_TYPE) {
       $stock = $sw->get_stock_information();
       if (! $stock) {
          process_import_error('Unable to retrieve Supplies Wholesalers ' .
                               'Stock Data: '.$sw->error);
          return false;
       }
       foreach ($stock as $item) {
          $row = array();
          foreach ($field_names as $index => $field_name) {
             if ($field_name == 'UnitsOnHand') {
                $qty = 0;
                if (! empty($item->BinEntries->StockByBin)) {
                   foreach ($item->BinEntries->StockByBin as $bin) {
                      if (! isset($bin->UnitsOnHand)) continue;
                      $qty += $bin->UnitsOnHand;
                   }
                }
                $row['index'] = $qty;
             }
             else if (! isset($item->$field_name)) $row[$index] = '';
             else $row[$index] = $item->$field_name;
          }
          $data[] = $row;
       }
    }
    return true;
}

function supplieswholesalers_process_image_conversion($map_info,&$product_data,
   &$image_filename,&$image_modified)
{
    if ($map_info['convert_funct'] != 'swimage') return false;
    $image_filename = '';   $image_modified = false;
    $product_code = trim($product_data->row[1]);
    $image_url = trim($product_data->row[$map_info['index']]);
    $image_options = $product_data->import['image_options'];
    $image_filename = basename($image_url);
    $local_filename = '../images/original/'.$image_filename;
    if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
        file_exists($local_filename))
       $last_modified = filemtime($local_filename);
    else $last_modified = get_last_modified($image_url,5);
    if ($last_modified == -1) {
       $error = 'Supplies Wholesalers image '.$image_url.' not found';
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    if (file_exists($local_filename) &&
        (filemtime($local_filename) == $last_modified)) return true;
    $image_data = @file_get_contents($image_url);
    if (! $image_data) {
       $error = 'Unable to download Supplies Wholesalers image '.$image_url;
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    file_put_contents($local_filename,$image_data);
    touch($local_filename,$last_modified);
    $image_modified = true;
    log_vendor_activity('Downloaded Supplies Wholesalers Product Image ' .
                        $image_filename.' for Product Code '.$product_code);
    return true;
}

function supplieswholesalers_process_conversion(&$map_info,&$product_data)
{
    $convert_function = $map_info['convert_funct'];
    switch ($convert_function) {
       case 'swimage': return null;
    }
    return true;
}

?>
