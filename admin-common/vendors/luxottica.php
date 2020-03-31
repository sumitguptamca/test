<?php
/*
              Inroads Shopping Cart - Luxottica Group Vendor Module

                       Written 2018-2019 by Randall Severy
                        Copyright 2018-2019 Inroads, LLC

Configuration Steps:

1) Add to admin/config.php:

$product_group_field = 'model_number';

2) Add to admin/custom-config.php:

require_once 'glasses-common.php';

$related_types = array(0 => 'Related Products',1 => 'Accessories',
                       2 => 'Sub Products');
$subproduct_type = 2;

3) Edit Catalog Config Fields and set Sub Product Attribute flag on
   shopping_color field

4) Create Prescription Attribute Set

5) Upload Luxottica vendor module file to admin/vendors and glasses-common.php
   file to admin

6) Edit Luxottica Vendor Settings and set:

Account Username
Account Password

7) Set up E-Mail Forwarder:

ln -s /usr/local/bin/php /home/{account}/public_html/admin/php
luxottica@domain.com => |public_html/admin/php /home/{account}/public_html/admin/vendors/luxottica.php importcatalog <vendor id>

8) Edit Luxottica Vendor Settings and set Download E-Mail Alias to e-mail address set up
   in step #7 and select brands to import

9) Update web site Unix user with a valid login shell, such as "/bin/bash" (if necessary)

10) Edit Luxottica Product Data Import and set:

Auto Update Frequency
Attribute Set for New Products

11) Run Luxottica Product Data Import

*/

if (isset($argv[1],$argv[2]) && ($argv[1] == 'importcatalog')) {
   chdir(dirname(__DIR__));
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   require_once '../cartengine/utility.php';
   require_once '../cartengine/vendors-common.php';
   $luxottica_import = true;
}
require_once '../engine/http.php';

global $vendor_fields;
$vendor_fields = array(array('name'=>'downloademail','type'=>CHAR_TYPE,
                             'size'=>255));
global $vendor_import_fields;
$vendor_import_fields = array(array('name'=>'minqty_value','type'=>INT_TYPE),
                              array('name'=>'minqty_status','type'=>INT_TYPE),
                              array('name'=>'gtqty_value','type'=>INT_TYPE),
                              array('name'=>'gtqty_status','type'=>INT_TYPE),
                              array('name'=>'brands','type'=>TEXT_TYPE));

global $import_mapping;
$import_mapping = array(
   array('vendor_field'=>'Door','update_field'=>'images|*','convert_funct'=>'luxotticaimage'),
   array('vendor_field'=>'UPC','update_field'=>'product|shopping_gtin'),
   array('vendor_field'=>'Model Code','update_field'=>'product|model_number','convert_funct'=>'copytomodel'),
   array('vendor_field'=>'Color Code','update_field'=>'product|color_code'),
   array('vendor_field'=>'Size','convert_funct'=>'sizelabel'),
   array('vendor_field'=>'Lens Color','update_field'=>'product|lens_color'),
   array('vendor_field'=>'Color Name Description','update_field'=>'product|shopping_color','convert_funct'=>'luxotticaname'),
   array('vendor_field'=>'Suggested Retail Price','update_field'=>'product|list_price'),
   array('vendor_field'=>'Wholesale Price','update_field'=>'product|cost','convert_funct'=>'setmarkupprice'),
   array('vendor_field'=>'Lens Base','update_field'=>'product|lens_base'),
   array('vendor_field'=>'Lens Material','update_field'=>'product|lens_material'),
   array('vendor_field'=>'Photochromic','update_field'=>'product|photochromic'),
   array('vendor_field'=>'Polarized','update_field'=>'product|polarized'),
   array('vendor_field'=>'Standard','update_field'=>'product|standard'),
   array('vendor_field'=>'Temple Length','update_field'=>'product|temple_length'),
   array('vendor_field'=>'Bridge Size','update_field'=>'product|bridge_size'),
   array('vendor_field'=>'Lens Witdth','update_field'=>'product|lens_width'),
   array('vendor_field'=>'Lens Height','update_field'=>'product|lens_height'),
   array('vendor_field'=>'Folding','update_field'=>'product|folding'),
   array('vendor_field'=>'Crystals','update_field'=>'product|crystals'),
   array('vendor_field'=>'Rx-Service','update_field'=>'product|rx_service'),
   array('vendor_field'=>'Rx-Able','update_field'=>'product|prescription'),
   array('vendor_field'=>'Flex','update_field'=>'product|flex'),
   array('vendor_field'=>'Front Colour Family','update_field'=>'product|front_color'),
   array('vendor_field'=>'Shape','update_field'=>'product|shape','convert_funct'=>'convertshape'),
   array('vendor_field'=>'Type','update_field'=>'product|rim_type'),
   array('vendor_field'=>'Temple Material','update_field'=>'product|temple_material'),
   array('vendor_field'=>'Front Material','update_field'=>'product|front_material'),
   array('vendor_field'=>'Best Seller','update_field'=>'product|best_seller'),
   array('vendor_field'=>'Gender','update_field'=>'product|shopping_gender','convert_funct'=>'convertgender'),
   array('vendor_field'=>'Collection','update_field'=>'product|frame_style','convert_funct'=>'convertframestyle'),
   array('vendor_field'=>'Theme','update_field'=>'product|collection'),
   array('vendor_field'=>'Brand Name','update_field'=>'product|shopping_brand')
);
global $default_import_info;
$default_import_info = array('name'=>'Product Data','import_type'=>1,
   'import_source'=>5,'start_row'=>2,'txt_delim'=>';','new_status'=>'0',
   'load_existing'=>1,'match_existing'=>3,'image_options'=>1,
   'non_match_action'=>1,'noimage_status'=>'1','flags'=>2);

function reset_import_start($db,$import_id)
{
    $query = 'update vendor_imports set import_started=null where id=?';
    $query = $db->prepare_query($query,$import_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       log_import_error($import_id,$db->error);   return false;
    }
    return true;
}

class Luxottica {

function __construct($db,$vendor_id,$import_id=null)
{
    $this->url = 'https://my.luxottica.com';
    $this->db = $db;
    $this->vendor_id = $vendor_id;
    $this->import_id = $import_id;
    $query = 'select * from vendors where id=?';
    $query = $this->db->prepare_query($query,$this->vendor_id);
    $this->vendor_info = $this->db->get_record($query);
    $query = 'select * from vendor_imports where id=?';
    $query = $this->db->prepare_query($query,$this->import_id);
    $this->import_info = $this->db->get_record($query);
    $this->cookie_header = null;
    $this->error = null;
    $this->brand_map = array('D&G' => 'Dolce & Gabbana',
       'Oakley Frame' => 'Oakley','Polo Prep' => 'Polo',
       'Ray-Ban Junior' => 'Ray-Ban','Ray-Ban Junior Vista' => 'Ray-Ban',
       'Ray-Ban Optical' => 'Ray-Ban');
    $this->brand_urls = null;
    $this->brand_models = array();
    $this->backorder_items = array();
}

function Luxottica($db,$vendor_id,$import_id=null)
{
    self::__construct($db,$vendor_id,$import_id);
}

function login()
{
    if ($this->cookie_header) return true;

    if (empty($this->vendor_info['username']) ||
        empty($this->vendor_info['password'])) {
       $this->error = 'Missing Vendor Username or Password';   return false;
    }

    $http = new HTTP($this->url,'GET');
    $page_data = $http->call();
    if ($http->status != 302) {
       if (($http->status == 200) &&
           (strpos($page_data,'<title>Under Maintenance</title>') !== false))
          $this->error = $this->url.' is under maintenance';
       else $this->error = 'Unable to login to '.$this->url.': '.$http->error .
                           ' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return false;
    }
    $url = $http->response_location;

    $http = new HTTP($url,'GET');
    $http->call();
    if ($http->status != 302) {
       $this->error = 'Unable to login to '.$this->url.': '.$http->error .
                      ' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return false;
    }

    $url = $http->response_location;
    $question_pos = strpos($url,'?');
    if ($question_pos === false) {
       $this->error = 'Store fields not found in '.$url.' response';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return false;
    }
    $fields = substr($url,$question_pos + 1);
    $this->catalog_fields = $this->store_fields = $fields;
    $end_pos = strpos($this->store_fields,'&krypto');
    if ($end_pos !== false)
       $this->store_fields = substr($this->store_fields,0,$end_pos);

    $url = $this->url.'/webapp/wcs/stores/servlet/Logon';
    $http = new HTTP($url);
    $post_data = 'logonId='.urlencode($this->vendor_info['username']) .
                 '&logonPassword='.urlencode($this->vendor_info['password']) .
                 '&reLogonURL=LogonForm&URL=TopCategoriesDisplay?' .
                 $this->store_fields;
    $http->call($post_data);
    if ($http->status != 302) {
       $this->error = 'Unable to login to '.$this->url.': '.$http->error .
                      ' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return false;
    }
    $cookies = '';
    foreach ($http->cookies as $cookie_name => $cookie_value) {
       if ($cookies) $cookies .= '; ';
       $cookies .= $cookie_name.'='.$cookie_value;
    }
    $this->cookie_header = 'Cookie: '.$cookies;
    return true;
}

function parse_json($json_response)
{
    $start_pos = strpos($json_response,'/*');
    if ($start_pos !== false)
       $json_response = substr($json_response,$start_pos + 2);
    $end_pos = strrpos($json_response,'*/');
    if ($end_pos !== false) $json_response = substr($json_response,0,$end_pos);
    $json_response = trim($json_response);
    return $json_response;
}

function check_cookie($response)
{
    if (empty($response->errorCode)) return;
    if ($response->errorCode != 'CMN1039E') return;
    log_debug('check_cookie setting cookie_header to null');
    $this->cookie_header = null;
}

function check_relogin_needed($page_data)
{
    if (strpos($page_data,'An invalid cookie was received for the user')
               !== false) {
       $this->cookie_header = null;
       log_debug('check_relogin_needed setting cookie_header to null');
       return true;
    }
    return false;
}

function get_brands()
{
    if (! $this->login()) return null;

    $url = $this->url.'/webapp/wcs/stores/servlet/AjaxDownloadCsvDataFileForm?' .
           $this->store_fields;
    $http = new HTTP($url,'GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $this->error = 'Unable to retrieve brand list from '.$url.': ' .
                      $http->error.' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return null;
    }
    $brands = array();
    $start_pos = strpos($page_data,'<ul class="brand-content ');
    if ($start_pos === false) {
       if ($this->check_relogin_needed($page_data))
          return $this->get_brands();
       return $brands;
    }
    $end_pos = strpos($page_data,'</div>',$start_pos);
    if ($end_pos === false) return $brands;
    $brand_data = substr($page_data,$start_pos,$end_pos - $start_pos);
    $brand_items = explode('<li ',$brand_data);
    foreach ($brand_items as $index => $brand_line) {
       if ($index == 0) continue;
       $start_pos = strpos($brand_line,'<a title="" class=');
       if ($start_pos === false) continue;
       $start_pos = strpos($brand_line,'>',$start_pos);
       if ($start_pos === false) continue;
       $start_pos += 1;
       $end_pos = strpos($brand_line,'</a>',$start_pos);
       if ($end_pos === false) continue;
       $brand = substr($brand_line,$start_pos,$end_pos - $start_pos);
       $start_pos = strpos($brand_line,'value="',$end_pos);
       if ($start_pos === false) continue;
       $start_pos += 7;
       $end_pos = strpos($brand_line,'"',$start_pos);
       if ($end_pos === false) continue;
       $code = substr($brand_line,$start_pos,$end_pos - $start_pos);
       $brands[$code] = $brand;
    }
    asort($brands);
    return $brands;
}

function get_brand_urls()
{
    if (! empty($this->brand_urls)) return $this->brand_urls;
    if (! $this->login()) return null;

    $url = $this->url.'/webapp/wcs/stores/servlet/TopCategoriesDisplayView?' .
           $this->catalog_fields;
    $http = new HTTP($url,'GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $this->error = 'Unable to retrieve brand list from '.$url.': ' .
                      $http->error.' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return null;
    }
    $brands = array();
    $start_pos = strpos($page_data,'<ul class="brand-element ');
    if ($start_pos === false) {
       if ($this->check_relogin_needed($page_data))
          return $this->get_brand_urls();
       return $brands;
    }
    $end_pos = strpos($page_data,'</ul>',$start_pos);
    if ($end_pos === false) return $brands;
    $brand_data = substr($page_data,$start_pos,$end_pos - $start_pos);
    $brand_items = explode('<li ',$brand_data);
    foreach ($brand_items as $index => $brand_line) {
       if ($index == 0) continue;
       $start_pos = strpos($brand_line,'<a href=');
       if ($start_pos === false) continue;
       $start_pos += 9;
       $end_pos = strpos($brand_line,"'",$start_pos);
       if ($end_pos === false) continue;
       $url = substr($brand_line,$start_pos,$end_pos - $start_pos);
       $start_pos = strpos($brand_line,'data-analytics=',$end_pos);
       if ($start_pos === false) continue;
       $start_pos += 16;
       $end_pos = strpos($brand_line,'"',$start_pos);
       if ($end_pos === false) continue;
       $brand = substr($brand_line,$start_pos,$end_pos - $start_pos);
       $url = str_replace('&amp;','&',$url);
       $brands[$brand] = $url;
    }
    ksort($brands);
    $this->brand_urls = $brands;
    return $brands;
}

function get_brand_models($brand)
{
    if (isset($this->brand_map[$brand])) $brand = $this->brand_map[$brand];
    if (isset($this->brand_models[$brand])) return $this->brand_models[$brand];
    $brand_urls = $this->get_brand_urls();

    if (! $this->login()) return null;
    $url = $brand_urls[$brand].'&pageSize=1000';
    $http = new HTTP($url,'GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $this->error = 'Unable to retrieve brand model list from ' .
                      $url.': '.$http->error.' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return null;
    }
    $models = array();
    $start_pos = strpos($page_data,'<section class="product-grid">');
    if ($start_pos === false) {
       if ($this->check_relogin_needed($page_data))
          return $this->get_brand_models($brand);
       return $brands;
    }
    $end_pos = strpos($page_data,'</section>',$start_pos);
    if ($end_pos === false) return $models;
    $model_data = substr($page_data,$start_pos,$end_pos - $start_pos);
    $model_items = explode('<li class="item',$model_data);
    foreach ($model_items as $index => $model_info) {
       if ($index == 0) continue;
       $start_pos = strpos($model_info,'href="');
       if ($start_pos === false) continue;
       $start_pos += 6;
       $end_pos = strpos($model_info,'"',$start_pos);
       if ($end_pos === false) continue;
       $url = substr($model_info,$start_pos,$end_pos - $start_pos);
       $start_pos = strpos($model_info,'model-code" data-analytics="',$end_pos);
       if ($start_pos === false) continue;
       $start_pos += 28;
       $end_pos = strpos($model_info,'"',$start_pos);
       if ($end_pos === false) continue;
       $model = trim(substr($model_info,$start_pos,$end_pos - $start_pos));
       $models[$model]['url'] = $url;
    }
    $this->brand_models[$brand] = $models;
    return $models;
}

function get_backorder_status()
{
    if (empty($this->backorder_items)) {
       $this->error = 'Back Order Items Not Found';   return array();
    }

    if (! $this->login()) return null;
    $url = $this->url.'/webapp/wcs/stores/servlet/AjaxOrderChangeServiceItemAdd';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $post_data = $this->store_fields;
    foreach ($this->backorder_items as $index => $item_info) {
       $id = $item_info['id'];
       $suffix = $index + 1;
       $post_data .= '&catEntryId_'.$suffix.'='.$id.'&quantity_'.$suffix.'=1';
    }
    $json_response = $http->call($post_data);
    if (! $json_response) {
       $this->error = 'Unable to add items to cart at '.$this->url.': ' .
                      $http->error.' ('.$http->status.')';
       return null;
    }
    $json_response = $this->parse_json($json_response);
    $response = json_decode($json_response);
    $this->check_cookie($response);
    if (empty($response->orderItemId)) {
       $this->error = 'Unable to add items to cart at '.$this->url.': ' .
                      $json_response;
       return null;
    }
    $avail_post_data = '';   $delete_post_data = '';
    foreach ($response->orderItemId as $index => $order_item) {
       $this->backorder_items[$index]['order_item'] = $order_item;
       if ($avail_post_data) $avail_post_data .= '&';
       $avail_post_data .= 'orderItemIDList='.$order_item;
       $suffix = $index + 1;
       $delete_post_data .= '&orderItemId_'.$suffix.'='.$order_item;
    }

    $url = $this->url.'/webapp/wcs/stores/servlet/GetSKUAvailability';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $post_data = $avail_post_data.'&'.$this->store_fields.'&requesttype=ajax';
    $json_response = $http->call($post_data);
    if (! $json_response) {
       $this->error = 'Unable to get cart item status at '.$this->url.': ' .
                      $http->error.' ('.$http->status.')';
       return null;
    }
    $avail_response = $this->parse_json($json_response);

    $url = $this->url.'/webapp/wcs/stores/servlet/AjaxOrderChangeServiceItemDelete';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $post_data = $this->store_fields.$delete_post_data.
                 '&simulation=true&requesttype=ajax';
    $json_response = $http->call($post_data);
    if (! $json_response) {
       $this->error = 'Unable to delete cart items at '.$this->url.': ' .
                      $http->error.' ('.$http->status.')';
       return null;
    }
    $delete_response = $this->parse_json($json_response);
    $response = json_decode($delete_response);
    $this->check_cookie($response);
    if (empty($response->orderId)) {
       $this->error = 'Unable to get delete cart items at '.$this->url.': ' .
                      $delete_response;
       return null;
    }

    $response = json_decode($avail_response);
    $this->check_cookie($response);
    if (empty($response->splitAvailableQuantities)) {
       $this->error = 'Unable to get cart item status at '.$this->url.': ' .
                      $avail_response;
       return null;
    }
    foreach ($response->splitAvailableQuantities as $order_item => $info) {
       foreach ($this->backorder_items as $index => $item_info) {
          if ($item_info['order_item'] == $order_item) {
             $this->backorder_items[$index]['info'] = $info[0];   break;
          }
       }
    }

    return $this->backorder_items;
}

function get_model_info($brand,$model)
{
    $models = $this->get_brand_models($brand);
    if (! isset($models[$model])) {
       $this->error = 'Model '.$model.' Not Found';   return array();
    }
    if (isset($this->brand_models[$brand][$model]['items']))
       return $this->brand_models[$brand][$model]['items'];

    if (! $this->login()) return null;
    $url = $models[$model]['url'];
    $http = new HTTP($url,'GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $this->error = 'Unable to retrieve model info from '.$url.': ' .
                      $http->error.' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);   return null;
    }
    $items = array();
    $start_pos = strpos($page_data,'<table id="brand-products-info"');
    if ($start_pos === false) {
       if (strpos($page_data,'<div class="coming-soon-product">') !== false) {
          log_debug('Brand '.$brand.' and Model '.$model.' is Coming Soon');
          return $items;
       }
       if ($this->check_relogin_needed($page_data))
          return $this->get_model_info($brand,$model);
       log_debug('get_model_info, url = '.$url);
       log_debug('unable to find brand-products-info table in '.$page_data);
       return $items;
    }
    $end_pos = strpos($page_data,'<div class="btn-container',$start_pos);
    if ($end_pos === false) {
       log_debug('get_model_info, url = '.$url);
       log_debug('unable to find btn-container table in '.$page_data);
       return $items;
    }
    $model_data = substr($page_data,$start_pos,$end_pos - $start_pos);
    $model_items = explode('class="sortable-element',$model_data);
    foreach ($model_items as $index => $model_info) {
       if ($index == 0) continue;
       $start_pos = strpos($model_info,'data-color="');
       if ($start_pos === false) continue;
       $start_pos += 12;
       $end_pos = strpos($model_info,'"',$start_pos);
       if ($end_pos === false) continue;
       $color = substr($model_info,$start_pos,$end_pos - $start_pos);
       $start_pos = strpos($model_info,'<ul class="quantity-cb"',$end_pos);
       if ($start_pos === false) continue;
       $end_pos = strpos($model_info,'</article>',$start_pos);
       if ($end_pos === false) continue;
       $avail_data = substr($model_info,$start_pos,$end_pos - $start_pos);
       $avail_items = explode('data-catentryid',$avail_data);
       $sizes = array();
       foreach ($avail_items as $avail_index => $avail_info) {
          if ($index == 0) continue;
          $start_pos = strpos($avail_info,'"');
          if ($start_pos === false) continue;
          $start_pos += 1;
          $end_pos = strpos($avail_info,'"',$start_pos);
          if ($end_pos === false) continue;
          $id = substr($avail_info,$start_pos,$end_pos - $start_pos);
          $start_pos = strpos($avail_info,'data-size="',$end_pos);
          if ($start_pos === false) continue;
          $start_pos += 11;
          $end_pos = strpos($avail_info,'"',$start_pos);
          if ($end_pos === false) continue;
          $size = substr($avail_info,$start_pos,$end_pos - $start_pos);
          $start_pos = strpos($avail_info,'<!-- in production:',$end_pos);
          if ($start_pos === false) continue;
          $start_pos += 5;
          $end_pos = strpos($avail_info,' -->',$start_pos);
          if ($end_pos === false) continue;
          $status = substr($avail_info,$start_pos,$end_pos - $start_pos);
          $status_info = explode(',',$status);
          $size_info = array('id' => $id);
          foreach ($status_info as $status_item) {
             $status_parts = explode(':',$status_item);
             if (count($status_parts) != 2) continue;
             $size_info[trim($status_parts[0])] = trim($status_parts[1]);
          }
          $sizes[$size] = $size_info;
          if ($size_info['availabilityClass'] != 'GREEN')
             $this->backorder_items[] = array('id'=>$id);
       }
       $items[$color] = $sizes;
    }

    if (count($this->backorder_items) > 0) {
       $backorder_status = $this->get_backorder_status();
       if (! $backorder_status) {
          $this->error = 'Unable to get backorder status: '.$this->error;
          log_error($this->error);   log_vendor_activity($this->error);
          log_import_error($this->import_id,$this->error);
       }
       else foreach ($backorder_status as $backorder) {
          foreach ($items as $color => $sizes) {
             foreach ($sizes as $size => $size_info) {
                if ($backorder['id'] == $size_info['id']) {
                   $items[$color][$size]['backorder'] = $backorder['info'];
                   break 2;
                }
             }
          }
       }
       $this->backorder_items = array();
    }

    $this->brand_models[$brand][$model]['items'] = $items;
    return $items;
}

function request_catalog()
{
    if (! $this->login()) return null;

    $url = $this->url.'/webapp/wcs/stores/servlet/AjaxDownloadCsvDataFileForm?' .
           $this->store_fields;
    $http = new HTTP($url,'GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $this->error = 'Unable to retrieve catalog data from '.$this->url.': ' .
                      $http->error.' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       log_import_error($this->import_id,$this->error);
       reset_import_start($db,$this->import_id);   return null;
    }
    $bukrs = '0';   $stars_door = '0';
    $start_pos = strpos($page_data,'name: "bukrs"');
    if ($start_pos !== false) {
       $start_pos += 23;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos != false)
          $bukrs = substr($page_data,$start_pos,$end_pos - $start_pos);
    }
    $start_pos = strpos($page_data,'name="starsDoor"');
    if ($start_pos !== false) {
       $start_pos = strpos($page_data,'value="',$start_pos);
       if ($start_pos !== false) {
          $start_pos += 7;
          $end_pos = strpos($page_data,'"',$start_pos);
          if ($end_pos != false)
             $stars_door = substr($page_data,$start_pos,$end_pos - $start_pos);
       }
    }
    $url = $this->url.'/webapp/wcs/stores/servlet/ScheduleStarsCsvCatalogFileCreation?' .
           'starsDoor='.$stars_door;
    $brands = explode(',',$this->import_info['brands']);
    foreach ($brands as $code) $url .= '&brand='.$code;
    $url .= '&joinedFields=UPC%2CModel+code%2CColor+code%2CSize%2CCategory%2C' .
            'Lenses+Color%2CColor+Name+Description%2CModel+Name+Description%2C' .
            'Suggested+Retail+Price%2CWholesale+Price%2CLens+Base%2CLens+Material%2C' .
            'Photochromic%2CPolarized%2CStandard%2CTemple+Length%2CBridge+Size%2C' .
            'Lens+Width%2CLens+Height%2CFolding%2CCrystals%2CRx-Service%2CRx-Able%2C' .
            'Flex%2CGeofit%2CFront+Colour+Family%2CShape%2CType%2CTemple+Material%2C' .
            'Front+Material%2CBest+Seller%2CAge+Range%2CGender%2CCollection%2C' .
            'Limited+Edition%2CNew%2CAdvertising%2CTheme%2CItems%2CBrand+name%2C' .
            'Brand+code%2C';
    $url .= '&mailTo='.urlencode($this->vendor_info['downloademail']) .
            '&'.$this->store_fields.'&bukrs='.$bukrs;
    $http = new HTTP($url,'GET');
    $http->set_headers(array($this->cookie_header));
    $json_response = $http->call();
    $json_response = $this->parse_json($json_response);
    $response = json_decode($json_response);
    $this->check_cookie($response);
    if ((! empty($response->schedulingResponse->response)) &&
        ($response->schedulingResponse->response == 'OK')) {
       log_vendor_activity('Requested Catalog from Luxottica for Brands ' .
                           $this->import_info['brands']);
       return true;
    }
    if (! empty($response->errorMessage)) $this->error = $response->errorMessage;
    else $this->error = 'Unknown Response: '.$json_response;
    if (! empty($response->errorCode))
       $this->error .= ' ('.$response->errorCode.')';
    log_error($this->error);   log_vendor_activity($this->error);
    log_import_error($this->import_id,$this->error);
    reset_import_start($db,$this->import_id);
    return false;
}

function generate_upload_spreadsheet($db,$orders)
{
    require_once '../engine/excel.php';
    $reader = PHPExcel_IOFactory::createReader('Excel2007');
    $excel = $reader->load('vendors/luxottica-upload.xlsx');
    $worksheet = $excel->setActiveSheetIndex(0);
    $parts = explode('.',$this->vendor_info['username']);
    $door = ltrim($parts[1],'0');

    $row_num = 8;
    foreach ($orders as $order) {
       $customer = $order->info['fname'].' '.$order->info['lname'];
       foreach ($order->items as $order_item) {
          if (empty($order_item['product_id'])) continue;
          $query = 'select shopping_gtin,model,color_code,size from ' .
                   'products where id=?';
          $query = $db->prepare_query($query,$order_item['product_id']);
          $row = $db->get_record($query);
          if (! $row) {
             if (! isset($db->error))
                log_error('Unable to find Product #'.$order_item['product_id'] .
                          ' in Luxottica Generate Spreadsheet');
             continue;
          }
          if ($row['shopping_gtin'])
             $worksheet->setCellValue('A'.$row_num,$row['shopping_gtin']);
          else {
             $worksheet->setCellValue('B'.$row_num,$row['model']);
             $worksheet->setCellValue('C'.$row_num,$row['color_code']);
             $worksheet->setCellValue('D'.$row_num,$row['size']);
          }
          $worksheet->setCellValue('E'.$row_num,$order_item['qty']);
          $worksheet->setCellValue('F'.$row_num,$customer);
          $worksheet->setCellValue('G'.$row_num,$door);
          $worksheet->setCellValue('H'.$row_num,$door);
          $row_num++;
       }
    }

    $writer = PHPExcel_IOFactory::createWriter($excel,'Excel2007');
    ob_start();
    $writer->save('php://output');
    $spreadsheet = ob_get_clean();
    return $spreadsheet;
}

function upload_orders($spreadsheet)
{
    if (! $this->login()) return null;

    $url = $this->url.'/webapp/wcs/stores/servlet/AjaxSubmitOrderMassiveNew?' .
           $this->store_fields;
    $http = new HTTP($url);
$http->set_debug_function('print_debug');
    $http->set_accept('text/plain, */*; q=0.01');
    $http->set_accept_encoding('gzip, deflate, br');
    $headers = array($this->cookie_header,'Accept-Language: en-US,en;q=0.9',
                     'Origin: '.$this->url,
                     'Referer: '.$this->url.'/webapp/wcs/stores/servlet/AjaxLogonForm?' .
                        'catalogId=10001&langId=-1&storeId=10001&page=orderMassiveNew',
                     'X-Requested-With: XMLHttpRequest');
    $http->set_headers($headers);
    $http->add_field('order-massive-ajaxurl',$url);
    $success_url = $this->url.'/webapp/wcs/stores/servlet/AjaxOrderMassiveCheckPageNew?' .
                   $this->store_fields.'&fileType=%23FILE_TYPE%23&page=orderMassive';
    $http->add_field('order-massive-succesurl',$success_url);
    $http->add_field('isOAK','false');
    $http->add_field('splitItems','true');
    $http->add_field('frames','true');
    $http->add_field('accessories','true');
    $http->add_field('afaAtOnce','false');
    $http->add_field('afaPreBook','false');
    $http->add_field('sngAtOnce','false');
    $http->add_field('sngPreBook','false');
    $http->add_file_field('luxottica-upload.xlsx','massiveFile',$spreadsheet,
       'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $page_data = $http->call();
    if (! $page_data) {
       $this->error = 'Unable to upload order data to '.$this->url.': ' .
                      $http->error.' ('.$http->status.')';
       log_error($this->error);   log_vendor_activity($this->error);
       return false;
    }
print "Page Data = ".$page_data."\n";

// file_put_contents('luxottica-test.xlsx',$spreadsheet);
// log_activity('Saved uploaded orders in luxottica-test.xlsx');
    return true;
}

};

function print_debug($str)
{
    print $str."\n";
}

function add_luxottica_import($db,$vendor_id)
{
    global $import_mapping,$default_import_info;

    $vendor_info = load_vendor_info($db,$vendor_id);
    $import_info = $default_import_info;
    $import_info['parent'] = $vendor_id;
    $import_info['mapping'] = $import_mapping;
    add_module_import($db,$vendor_info,$import_info);
}

function luxottica_install($db)
{
    global $vendor_fields,$vendor_import_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    $vendor_info = array('module'=>'luxottica','name'=>'Luxottica Group',
       'contact'=>'Jonathan Giacalone','company'=>'Luxottica USA LLC',
       'address1'=>'12 Harbor Park Drive','city'=>'Port Washington','state'=>'NY',
       'zipcode'=>'11050','country'=>1,'phone'=>'1-800-422-2020',
       'email'=>'customerservice@us.luxottica.com','num_markups'=>-1);

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
    if (! add_import_fields($db,$vendor_import_fields)) return;
    $vendor_id = add_module_vendor($db,$vendor_info);
    if (! $vendor_id) return;
    add_luxottica_import($db,$vendor_id);
}

function luxottica_upgrade($db)
{
    global $vendor_fields,$vendor_import_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
    if (! add_import_fields($db,$vendor_import_fields)) return;
}

function luxottica_vendor_fields(&$vendor_record)
{
    $vendor_record['downloademail'] = array('type'=>CHAR_TYPE);
}

function luxottica_import_fields(&$vendor_import_record)
{
    $vendor_import_record['minqty_value'] = array('type'=>INT_TYPE);
    $vendor_import_record['minqty_status'] = array('type'=>INT_TYPE);
    $vendor_import_record['gtqty_value'] = array('type'=>INT_TYPE);
    $vendor_import_record['gtqty_status'] = array('type'=>INT_TYPE);
    $vendor_import_record['brands'] = array('type'=>CHAR_TYPE);
}

function luxottica_add_vendor_fields($dialog,$edit_type,$row,$db,$tab_name)
{
    if ($tab_name != 'settings') return;

    $luxottica = new Luxottica($db,$row['id']);
    $dialog->start_row('Go To My Luxottica:');
    $dialog->write('<a href="'.$luxottica->url .
                   '" target="_blank">My Luxottica</a>');
    $dialog->end_row();
    $dialog->add_edit_row('Download E-Mail Alias:','downloademail',$row,40);
}

function luxottica_init_add_import($db,&$row)
{
    global $default_import_info;
    $skip_fields = array('id','parent','name','import_file','brands',
                         'import_started','import_finished');

    foreach ($default_import_info as $field_name => $field_value)
       $row[$field_name] = $field_value;
    $query = 'select * from vendor_imports where parent=? order by id limit 1';
    $query = $db->prepare_query($query,$row['parent']);
    $first_row = $db->get_record($query);
    if (! $first_row) return;
    foreach ($first_row as $field_name => $field_value) {
       if (array_key_exists($field_name,$default_import_info)) continue;
       if (in_array($field_name,$skip_fields)) continue;
       $row[$field_name] = $field_value;
    }
}

function luxottica_add_import_tabs(&$import_tabs,$vendor_info,$db)
{
    $import_tabs['brands'] = 'Brands';
}

function luxottica_add_import_fields($db,$dialog,$edit_type,$row,$section)
{
    global $status_values,$num_status_values;

    if ($section != 'status_options') return;

    $dialog->start_row('Inventory Quantity Less Than:','middle');
    $dialog->add_input_field('minqty_value',$row,1);
    $dialog->add_inner_prompt('Status:');
    $minqty_status = get_row_value($row,'minqty_status');
    $dialog->start_choicelist('minqty_status');
    $dialog->add_list_item('','',($minqty_status === ''));
    for ($loop = 0;  $loop < $num_status_values;  $loop++)
       if (isset($status_values[$loop]))
          $dialog->add_list_item($loop,$status_values[$loop],
                                 (string) $loop === $minqty_status);
    $dialog->end_choicelist();
    $dialog->end_row();

    $dialog->start_row('Inventory Quantity Greater Than:','middle');
    $dialog->add_input_field('gtqty_value',$row,1);
    $dialog->add_inner_prompt('Status:');
    $gtqty_status = get_row_value($row,'gtqty_status');
    $dialog->start_choicelist('gtqty_status');
    $dialog->add_list_item('','',($gtqty_status === ''));
    for ($loop = 0;  $loop < $num_status_values;  $loop++)
       if (isset($status_values[$loop]))
          $dialog->add_list_item($loop,$status_values[$loop],
                                 (string) $loop === $gtqty_status);
    $dialog->end_choicelist();
    $dialog->end_row();
}

function luxottica_import_tabs($db,$row,&$dialog,$tab_name,$vendor_info,
                               $import_tabs)
{
    if ($tab_name != 'brands') return;

    $dialog->start_tab_content('brands_content',false);
    $dialog->start_field_table(null,'fieldtable add_edit_import_table');
    $old_brands = get_row_value($row,'brands');
    $current_brands = explode(',',$old_brands);
    $luxottica = new Luxottica($db,$vendor_info['id']);
    $brands = $luxottica->get_brands();
    $brand_array = array();
    $dialog->start_row('Brands to Import:','top');
    $dialog->add_hidden_field('OldBrands',$old_brands);
    $dialog->start_table(null,null,0,4);   $index = 0;
    if ($brands) foreach ($brands as $code => $brand) {
       $brand_array[] = $code;
       if ($index % 2) $dialog->write('</td><td>');
       else {
          if ($index != 0) $dialog->write("</td></tr>\n");
          $dialog->write('<tr><td>');
       }
       if (in_array($code,$current_brands)) $checked = true;
       else $checked = false;
       $dialog->add_checkbox_field('brand_'.$index,$brand,$checked);
       $index++;
    }
    $dialog->add_hidden_field('Brands',json_encode($brand_array));
    $dialog->end_row();
    $dialog->end_table();
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_tab_content();
}

function luxottica_parse_import_fields($db,&$import_record)
{
    $brands = json_decode(get_form_field('Brands'));
    $old_brands = explode(',',get_form_field('OldBrands'));
    $new_brands = array();
    if ($brands) foreach ($brands as $index => $code) {
       $checked = get_form_field('brand_'.$index);
       if ($checked) $new_brands[] = $code;
    }
    $import_record['brands']['value'] = implode(',',$new_brands);
}

function luxottica_finish_add_import($db,$import_id,$vendor_info,
                                     $import_record)
{
    global $import_mapping;

    foreach ($import_mapping as $map_info) {
       $mapping_record = vendor_mapping_record_definition();
       $mapping_record['sep_char']['value'] = '';
       $mapping_record['required']['value'] = 0;
       $mapping_record['convert_funct']['value'] = '';
       foreach ($map_info as $field_name => $field_value)
          $mapping_record[$field_name]['value'] = $field_value;
       $mapping_record['parent']['value'] = $import_id;
       if (! $db->insert('vendor_mapping',$mapping_record)) return false;
    }
}

function luxottica_start_import(&$db,&$import)
{
    $query = 'select id,import_started,import_finished from vendor_imports ' .
             'where parent=?';
    $query = $db->prepare_query($query,$import['parent']);
    $rows = $db->get_records($query);
    if (! $rows) {
       process_import_error($db->error);   return false;
    }
    $running_import = 0;
    foreach ($rows as $row) {
       if ((! empty($row['import_started'])) &&
           empty($row['import_finished'])) {
          $running_import = $row['id'];   break;
       }
    }
    if (! $running_import) return true;
    $db->close();
    log_vendor_activity('Vendor Import #'.$running_import .
                        ' is still running, waiting 10 minutes');
    sleep(600);
    $db = new DB;
    return luxottica_start_import($db,$import);
}

function luxottica_download_catalog($db,&$import)
{
    global $import_log_filename;

    $import_id = $import['id'];
    $import_filename = '../admin/vendors/import-'.$import_id.'.csv';
    $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
    $cmd = get_form_field('cmd');
    if ($cmd == 'getimport') {   /* Get Data Button Clicked */
       if (file_exists($import_log_filename)) unlink($import_log_filename);
       $query = 'update vendor_imports set import_started=null,' .
                'import_finished=null,import_file=null where id=?';
       $query = $db->prepare_query($query,$import_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          process_import_error($db->error);   $db->close();   return false;
       }
       if (file_exists($import_filename)) unlink($import_filename);
       $import['pending'] = true;
    }
    else { /* Background Import or Incoming E-Mail */
       if (file_exists($import_filename)) {
          if (time() - filemtime($import_filename) < 60) {
             /* Processing incoming e-mail, continue import process */
             return true;
          }
          /* Processing Background Import */
          unlink($import_filename);
       }
       if (! empty($import['import_file'])) {
          $query = 'update vendor_imports set import_file=null where id=?';
          $query = $db->prepare_query($query,$import_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             process_import_error($db->error);   $db->close();   return false;
          }
       }
    }
    $vendor_id = $import['parent'];
    $luxottica = new Luxottica($db,$vendor_id,$import_id);
    if (! $luxottica->request_catalog())
       log_import_error($import_id,$luxottica->error);
    /* Skip remaining import processing */
    return false;
}

function luxottica_upload_orders($db,$vendor_info,&$orders)
{
    $luxottica = new Luxottica($db,$vendor_info['id']);
    $spreadsheet = $luxottica->generate_upload_spreadsheet($db,$orders);
    if (! $spreadsheet) return false;
    if (! $luxottica->upload_orders($spreadsheet)) return false;
    return true;
}

function luxottica_load_custom_data($db,&$product_data,&$custom_data)
{
    $product_data->luxottica = new Luxottica($db,$product_data->vendor_id);
    $product_data->tables['inventory'] = true;
}

function luxottica_update_conversions($db,&$conversions)
{
    $conversions['copytomodel'] = 'Copy to Model';
    $conversions['sizelabel'] = 'Build Size Label';
    $conversions['luxotticaimage'] = 'Download Luxottica Image';
    $conversions['luxotticaname'] = 'Build Luxottica Product Name';
    $conversions['convertgender'] = 'Convert Gender';
    $conversions['convertframestyle'] = 'Convert Frame Style';
    $conversions['convertshape'] = 'Convert Shape';
    $conversions['alwaystrue'] = 'Always True';
}

function luxottica_process_image_conversion($map_info,&$product_data,
   &$image_filename,&$image_modified)
{
    if ($map_info['convert_funct'] != 'luxotticaimage') return false;

    $image_filename = '';   $image_modified = false;
    if (empty($product_data->product_record['model_number']['value']))
       return true;
    if (empty($product_data->product_record['color_code']['value']))
       return true;
    $model = $product_data->product_record['model_number']['value'];
    $color_code = $product_data->product_record['color_code']['value'];

    if ((! $model) || (! $color_code) || (strlen($model) < 3)) return true;
    $prefix = substr($model,1,2);
    $color_code = str_replace('/','_',$color_code);
    $color_code = str_pad($color_code,3,'0',STR_PAD_LEFT);
    $image_url = 'https://my.luxottica.com/myLuxotticaImages/'.$prefix .
                 '/'.$model.'__'.$color_code.'_890x445.jpg';
    $image_filename = $model.'_'.$color_code.'.jpg';
    $local_filename = '../images/original/'.$image_filename;
    $image_options = $product_data->import['image_options'];
    if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
        file_exists($local_filename))
       $last_modified = filemtime($local_filename);
    else $last_modified = get_last_modified($image_url);
    if ($last_modified == -1) {
       $error = 'Luxottica image '.$image_url.' not found';
       log_vendor_activity($error);   $image_filename = '';   return true;
    }
    if (file_exists($local_filename) &&
        (filemtime($local_filename) == $last_modified)) return true;
    $image_data = @file_get_contents($image_url);
    if (! $image_data) {
       $error = 'Unable to download Luxottica image '.$image_url;
       log_vendor_activity($error);   $image_filename = '';   return true;
    }
    file_put_contents($local_filename,$image_data);
    touch($local_filename,$last_modified);
    $image_modified = true;
    log_vendor_activity('Downloaded Luxottica Product Image '.$image_filename);

    return true;
}

function luxottica_process_conversion(&$map_info,&$product_data)
{
    $convert_function = $map_info['convert_funct'];
    switch ($convert_function) {
       case 'copytomodel':
          $field_value = get_update_field($map_info,$product_data);
          $map_info['update_field'] = 'product|model';
          set_update_field($map_info,$product_data,$field_value);
          return null;
       case 'sizelabel':
          $width = $product_data->product_record['lens_width']['value'];
          if (substr($width,-2) == '.0') $width = substr($width,0,-2);
          $bridge = $product_data->product_record['bridge_size']['value'];
          if (substr($bridge,-2) == '.0') $bridge = substr($bridge,0,-2);
          $length = $product_data->product_record['temple_length']['value'];
          if (substr($length,-2) == '.0') $length = substr($length,0,-2);
          $field_value = $width.'-'.$bridge.'-'.$length;
          $map_info['update_field'] = 'product|size';
          set_update_field($map_info,$product_data,$field_value);
          return null;
       case 'luxotticaimage': return null;
       case 'luxotticaname':
          $model = $product_data->product_record['model']['value'];
          $color = $product_data->product_record['shopping_color']['value'];
          $width = $product_data->product_record['lens_width']['value'];
          $bridge = $product_data->product_record['bridge_size']['value'];
          $length = $product_data->product_record['temple_length']['value'];
          $product_name = $model.' '.$color.' '.$width.'-'.$bridge.'-'.$length;
          $map_info['update_field'] = 'product|name';
          set_update_field($map_info,$product_data,$product_name);
          $map_info['update_field'] = 'product|display_name';
          set_update_field($map_info,$product_data,$model);
          return null;
       case 'convertgender':
          $field_value = strtolower(get_update_field($map_info,$product_data));
          if ($field_value == 'man') $field_value = 'male';
          else if ($field_value == 'woman') $field_value = 'female';
          $field_value = ucfirst($field_value);
          set_update_field($map_info,$product_data,$field_value);
          return null;
       case 'convertframestyle':
          require_once '../admin/glasses-common.php';
          $field_value = get_update_field($map_info,$product_data);
          $field_value = convert_frame_style($field_value);
          set_update_field($map_info,$product_data,$field_value);
          return null;
       case 'convertshape':
          require_once '../admin/glasses-common.php';
          $field_value = get_update_field($map_info,$product_data);
          $field_value = convert_shape($field_value);
          set_update_field($map_info,$product_data,$field_value);
          return null;
        case 'alwaystrue':
          $field_value = 1;
          set_update_field($map_info,$product_data,$field_value);
          return null;
    }
    return true;
}

function luxottica_check_import(&$product_data)
{
    if (empty($product_data->product_record['prescription']['value']))
       $product_data->product_attributes = null;
    return true;
}

function luxottica_update_import(&$product_data)
{
    $luxottica = $product_data->luxottica;
    if (! empty($product_data->product_record['shopping_brand']['value']))
       $brand_name = $product_data->product_record['shopping_brand']['value'];
    else if (! empty($product_data->product_info['shopping_brand']))
       $brand_name = $product_data->product_info['shopping_brand'];
    else return true;
    if (! empty($product_data->product_record['model_number']['value']))
       $model = $product_data->product_record['model_number']['value'];
    else if (! empty($product_data->product_info['model_number']))
       $model = $product_data->product_info['model_number'];
    else return true;
    if (! empty($product_data->product_record['color_code']['value']))
       $color_code = $product_data->product_record['color_code']['value'];
    else if (! empty($product_data->product_info['color_code']))
       $color_code = $product_data->product_info['color_code'];
    else return true;
    $color_code = str_pad($color_code,3,'0',STR_PAD_LEFT);
    $size = trim($product_data->row[4]);

    $qty = 0;   $product_found = false;
    $model_info = $luxottica->get_model_info($brand_name,$model);
    if (empty($model_info)) {
       if ($model_info === null) return true;
       $error = 'Luxottica Model Details for Brand '.$brand_name .
                ' and Model '.$model.' not found';
       log_debug($error);
    }
    else if (! isset($model_info[$color_code])) {
       $error = 'Luxottica Model Details for Brand '.$brand_name .
                ' and Model '.$model.' and Color '.$color_code .
                ' not found';
       log_debug($error);
    }
    else if (! isset($model_info[$color_code][$size])) {
       $error = 'Luxottica Model Details for Brand '.$brand_name .
                ' and Model '.$model.' and Color '.$color_code .
                ' and Size '.$size.' not found';
       log_debug($error);
    }
    else {
       $product_found = true;
       $size_info = $model_info[$color_code][$size];
       $qty = $size_info['availability'];
       if (! empty($size_info['backorder'])) {
          $backorder = $size_info['backorder'];
          if (! empty($backorder->wh)) {
             switch ($backorder->wh) {
                case 1: $backorder_status = '3 - 5 Business Days USA';   break;
                case 2: $backorder_status = '10 - 15 Business Days OVERSEAS';
                        break;
                case 3: $backorder_status = 'Approximately 15 Business Days';
                        break;
                default: $backorder_status = 'Unknown wh'.$backorder->wh;
                         break;
             }
          }
          else {
             log_error('Unknown Luxottica BackOrder: ' .
                       print_r($backorder,true));
             $backorder_status = 'Unknown';
          }
       }
       else $backorder_status = '';
       $product_data->product_record['backorder_status']['value'] =
          $backorder_status;
    }
    $import = $product_data->import;
    if (! $product_found) {
       $action = $import['non_match_action'];
       if ($action == NON_MATCH_DELETE) {
          if (! empty($product_data->product_record['id']['value'])) {
             $db = $product_data->db;
             $product_id = $product_data->product_record['id']['value'];
             if (! delete_vendor_product($db,$product_id)) return false;
          }
       }
       else if ($action != NON_MATCH_SKIP)
          $product_data->product_record['status']['value'] = $action;
    }
    else {
       $product_data->inventory_record['qty']['value'] = $qty;
       if ((! empty($import['minqty_value'])) &&
           ($qty < $import['minqty_value']))
          $product_data->product_record['status']['value'] =
             $import['minqty_status'];
       else if ((! empty($import['gtqty_value'])) &&
           ($qty > $import['gtqty_value']))
          $product_data->product_record['status']['value'] =
             $import['gtqty_status'];
    }

    return true;
}

function start_luxottica_import($vendor_id)
{
    set_remote_user('Luxottica Vendor Import');

    $db = new DB;
    $query = 'select id,import_started,import_finished,import_file,brands ' .
             'from vendor_imports where parent=?';
    $query = $db->prepare_query($query,$vendor_id);
    $imports = $db->get_records($query);
    if (! $imports) {
       if (! isset($db->error)) {
          $error = 'No Imports found for Vendor #'.$vendor_id;
          log_error($error);   log_vendor_activity($error);
       }
       else $error = $db->error;
       log_vendor_activity($error);   return;
    }
    if (count($imports) == 1) $import_id = $imports[0]['id'];
    else {
       $import_id = null;
       foreach ($imports as $row) {
          if ((! empty($row['import_started'])) &&
              empty($row['import_finished'])) {
             $import_id = $row['id'];   break;
          }
       }
    }
    if ($import_id) set_remote_user('Vendor Import #'.$import_id);

    $fd = fopen('php://stdin','r');
    $email = '';
    while (! feof($fd)) $email .= fread($fd,1024);
    fclose($fd);
    $email = str_replace("=\n",'',$email);

    $start_pos = strpos($email,'<a href=');
    if ($start_pos !== false) {
       $start_pos = strpos($email,'http',$start_pos);
       if ($start_pos !== false) {
          $end_pos1 = strpos($email,'"',$start_pos);
          $end_pos2 = strpos($email,"'",$start_pos);
          if (($end_pos1 !== false) && ($end_pos2 !== false) &&
              ($end_pos1 < $end_pos2))
             $end_pos = $end_pos1;
          else $end_pos = $end_pos2;
       }
    }
    if (($start_pos === false) || ($end_pos === false)) {
       $error = 'No Download URL found in Luxottica CSV Catalog E-Mail';
       if ($import_id) {
          log_import_error($import_id,$error);
          reset_import_start($db,$import_id);
       }
       log_error($error);   log_vendor_activity($error);   return;
    }
    $download_url = substr($email,$start_pos,$end_pos - $start_pos);

    $catalog = @file_get_contents($download_url);
    if (! $catalog) {
       $error = 'Unable to download Luxottica Catalog File '.$download_url;
       if ($import_id) {
          log_import_error($import_id,$error);
          reset_import_start($db,$import_id);
       }
       log_error($error);   log_vendor_activity($error);   return;
    }
    if (count($imports) == 1) $import = $imports[0];
    else {
       $brand_code = null;
       $end_pos = strpos($catalog,"\n");
       if ($end_pos !== false) {
          $end_pos = strpos($catalog,"\n",$end_pos + 1);
          if ($end_pos !== false) {
             $end_pos = strpos($catalog,"\n",$end_pos + 1);
             if ($end_pos !== false) {
                $first_rows = substr($catalog,0,$end_pos);
                $parts = explode(';',$first_rows);
                $num_parts = count($parts);
                if ($num_parts > 1) $brand_code = $parts[$num_parts - 2];
             }
          }
       }
       if (! $brand_code) {
          $error = 'No Brand found in Luxottica Catalog File '.$download_url;
          if ($import_id) {
             log_import_error($import_id,$error);
             reset_import_start($db,$import_id);
          }
          log_error($error);   log_vendor_activity($error);   return;
       }
       $import = null;
       foreach ($imports as $row) {
          $brands = explode(',',$row['brands']);
          if (in_array($brand_code,$brands)) {
             $import = $row;   break;
          }
       }
       if (! $import) {
          $error = 'Unable to find import for Brand '.$brand_code .
                   ' in Luxottica Catalog File '.$download_url;
          if ($import_id) {
             log_import_error($import_id,$error);
             reset_import_start($db,$import_id);
          }
          log_error($error);   log_vendor_activity($error);   return;
       }
    }
    $import_id = $import['id'];

    $import_filename = 'vendors/import-'.$import_id.'.csv';
    file_put_contents($import_filename,$catalog);
    log_vendor_activity('Downloaded Luxottica Catalog File from ' .
                        $download_url.' for Import #'.$import_id);
    if (empty($import['import_file'])) {
       $import_file = 'import-'.$import_id.'.csv';
       $query = 'update vendor_imports set import_file=? where id=?';
       $query = $db->prepare_query($query,$import_file,$import_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          log_import_error($import_id,$db->error);   return;
       }
    }
    if (! empty($import['import_started'])) {
       if (! reset_import_start($db,$import_id)) return;
       chdir('../cartengine');
       spawn_program('vendors-import.php import '.$import_id);
       log_activity('Started Vendor Import #'.$import_id);
    }
}

if (! empty($luxottica_import)) start_luxottica_import($argv[2]);

?>
