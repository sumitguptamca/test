<?php
/*
                Inroads Shopping Cart - Google Content API for Shopping Module

                        Written 2010-2019 by Randall Severy
                         Copyright 2010-2019 Inroads, LLC
*/

require_once '../engine/Google/autoload.php';

class GoogleShopping {

function GoogleShopping($db=null)
{
    global $google_shopping_debug,$products_table;

    $this->error = null;
    $this->warnings = null;
    $this->use_image_link = true;
    if (! $db) $this->db = new DB;
    else $this->db = $db;
    if (! isset($products_table)) $this->products_table = 'products';
    else $this->products_table = $products_table;
    if (isset($google_shopping_debug) && $google_shopping_debug)
       $this->debug = true;
    else $this->debug = false;
    $query = 'select * from cart_config where config_name ' .
             'like "google_shopping_%"';
    $this->config = $this->db->get_records($query,'config_name',
                                           'config_value');
    if (! empty($this->config['google_shopping_use_api']))
       $this->use_api = true;
    else $this->use_api = false;
    if (! empty($this->config['google_shopping_merchant_id']))
       $this->merchant_id = $this->config['google_shopping_merchant_id'];
    else $this->merchant_id = null;
    if (! empty($this->config['google_shopping_client_id']))
       $this->client_id = $this->config['google_shopping_client_id'];
    else $this->client_id = null;
    if (! empty($this->config['google_shopping_email']))
       $this->email = $this->config['google_shopping_email'];
    else $this->email = null;
    if (! empty($this->config['google_shopping_key_file']))
       $this->key_file = $this->config['google_shopping_key_file'];
    else $this->key_file = null;

    if (empty($this->merchant_id) || empty($this->key_file)) {
       $this->error = 'Google Shopping is not configured';
       log_error($this->error);   $this->service = null;   return null;
    }
    if (! file_exists('../admin/'.$this->key_file)) {
       $this->error = 'Google Shopping Key File '.$this->key_file.' not found';
       log_error($this->error);   $this->service = null;   return null;
    }
    $this->private_key = file_get_contents('../admin/'.$this->key_file);
    $this->scopes = 'https://www.googleapis.com/auth/content';
    $this->credentials = new Google_Auth_AssertionCredentials($this->email,
                            $this->scopes,$this->private_key);
    $this->client = new Google_Client();
    $this->client->setApplicationName('QuikWeb Shopping Cart');
    $this->client->setAssertionCredentials($this->credentials);
    if ($this->client->getAuth()->isAccessTokenExpired())
       $this->client->getAuth()->refreshTokenWithAssertion();
    $this->service = new Google_Service_ShoppingContent($this->client);
}

function log_activity($activity_msg)
{
    global $activity_log;

    $path_parts = pathinfo($activity_log);
    $google_activity_log = $path_parts['dirname'].'/google.log';
    $activity_file = @fopen($google_activity_log,'at');
    if ($activity_file) {
       fwrite($activity_file,'['.date('D M d Y H:i:s').'] ' .
              $activity_msg."\n");
       fclose($activity_file);
    }
}

function write_error($product_id,$error)
{
    if (! $error) {
       $query = 'update '.$this->products_table .
                ' set google_shopping_error=null where id=?';
       $query = $this->db->prepare_query($query,$product_id);
    }
    else {
       $query = 'update '.$this->products_table .
                ' set google_shopping_error=? where id=?';
       $query = $this->db->prepare_query($query,$error,$product_id);
    }
    $this->db->log_query($query);
    if (! $this->db->query($query)) return false;
    return true;
}

function write_warnings($product_id,$warnings)
{
    if (! $warnings) {
       $query = 'update '.$this->products_table .
                ' set google_shopping_warnings=null where id=?';
       $query = $this->db->prepare_query($query,$product_id);
    }
    else {
       $query = 'update '.$this->products_table .
                ' set google_shopping_warnings=? where id=?';
       $query = $this->db->prepare_query($query,$warnings,$product_id);
    }
    $this->db->log_query($query);
    if (! $this->db->query($query)) return false;
    return true;
}

function list_items()
{
    if (! $this->service) return null;
    try {
       $product_info = $this->service->products->
                              listProducts($this->merchant_id);
       if (! $product_info) return array();
       $products = array();   $parameters = array();
       $product_list = $product_info->getResources();
       while ($product_list) {
          $products = array_merge($products,$product_list);
          if (! $product_info->getNextPagetoken()) break;
          $parameters['pageToken'] = $product_info->nextPageToken;
          $product_info = $this->service->products->
                           listProducts($this->merchant_id,$parameters);
          if (! $product_info) break;
          $product_list = $product_info->getResources();
       }
    } catch (Exception $e) {
       if ($e instanceof Google_Service_Exception) {
          $errors = $e->getErrors();
          if (isset($errors[0])) $error = $errors[0]['message'];
          else $error = 'Unknown Error';
       }
       else $error = $e->getMessage();
       $this->error = 'Google Shopping Error: '.$error;
       $this->log_activity($this->error);
       return null;
    }
    return $products;
}

function get_product_status()
{
    if (! $this->service) return null;
    try {
       $product_info = $this->service->productstatuses->
                              listProductstatuses($this->merchant_id);
       if (! $product_info) return array();
       $products = array();   $parameters = array();
       $product_list = $product_info->getResources();
       while ($product_list) {
          $products = array_merge($products,$product_list);
          if (! $product_info->getNextPagetoken()) break;
          $parameters['pageToken'] = $product_info->nextPageToken;
          $product_info = $this->service->productstatuses->
                           listProductstatuses($this->merchant_id,$parameters);
          if (! $product_info) break;
          $product_list = $product_info->getResources();
       }
    } catch (Exception $e) {
       if ($e instanceof Google_Service_Exception) {
          $errors = $e->getErrors();
          if (isset($errors[0])) $error = $errors[0]['message'];
          else $error = 'Unknown Error';
       }
       else $error = $e->getMessage();
       $this->error = 'Google Shopping Error: '.$error;
       $this->log_activity($this->error);
       return null;
    }
    return $products;
}

function get_image_file($row)
{
    global $image_dir,$use_dynamic_images,$image_subdir_prefix;

    $query = 'select filename from images where (parent_type=1) and ' .
             '(parent=?) order by sequence limit 1';
    $query = $this->db->prepare_query($query,$row['id']);
    $image_row = $this->db->get_record($query);
    if (! $image_row) return null;
    $image_filename = $image_row['filename'];
    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    if (! isset($image_subdir_prefix)) $image_subdir_prefix = null;
    if ($use_dynamic_images) {
       $image = $image_dir.'/original/';
       if ($image_subdir_prefix) {
          $prefix = substr($image_filename,0,$image_subdir_prefix);
          $image .= $prefix.'/';
       }
       $image .= $image_filename;
    }
    else if ($image_subdir_prefix) {
       $prefix = substr($image_filename,0,$image_subdir_prefix);
       $image = $image_dir.'/large/'.$prefix.'/'.$image_filename;
    }
    else $image = $image_dir.'/large/'.$image_filename;
    return $image;
}

function build_item_array($id,$features=null,$row=null)
{
    if (! $features) $features = get_cart_config_value('features',$this->db);
    if (! $row) {
       $query = 'select * from products where id=?';
       $query = $this->db->prepare_query($query,$id);
       $row = $this->db->get_record($query);
       if (! $row) {
          if (isset($this->db->error))
             $this->error = 'Google Shopping Database Error: '.$this->db->error;
          else $this->error = 'Google Shopping: Product #'.$id.' not found';
          $this->log_activity($this->error);
          return null;
       }
    }
    if (function_exists('update_shopping_product_row'))
       update_shopping_product_row(GOOGLE_SHOPPING_FLAG,$this->db,$row);
    if ($row['display_name']) $product_name = $row['display_name'];
    else $product_name = $row['name'];
    if ($row['short_description'])
       $description = $row['short_description'];
    else $description = $row['long_description'];
    $shopping_item = array();
    $shopping_item['item_id'] = $row['google_shopping_id'];
    $shopping_item['gtin'] = $row['shopping_gtin'];
    $shopping_item['mpn'] = $row['shopping_mpn'];
    $shopping_item['brand'] = $row['shopping_brand'];
    $shopping_item['product_type'] = $row['google_shopping_type'];
    $shopping_item['product_category'] = $row['google_shopping_cat'];
    if (isset($row['google_adwords']))
       $shopping_item['adwords_labels'] = $row['google_adwords'];
    else $shopping_item['adwords_labels'] = '';
    $shopping_item['gender'] = strtolower(trim($row['shopping_gender']));
    $shopping_item['color'] = $row['shopping_color'];
    $shopping_item['age'] = strtolower(trim($row['shopping_age']));
    if ($shopping_item['age'] == 'child') $shopping_item['age'] = 'kids';
    $shopping_item['id'] = $id;
    $shopping_item['url'] = build_product_url($this->db,$row);
    $shopping_item['title'] = $product_name;
    $shopping_item['description'] = make_utf8($description);
    if (($row['status'] == 0) || ($row['status'] == 3))
       $shopping_item['availability'] = 'in stock';
    else $shopping_item['availability'] = 'out of stock';
    $shopping_item['condition'] = strtolower(trim($row['shopping_condition']));
    if (empty($shopping_item['condition']))
       $shopping_item['condition'] = 'new';
    $shopping_item['price'] = get_product_price($this->db,$row,$features);
    if ($this->use_image_link)
       $shopping_item['image_url'] = build_image_url($this->db,$row);
    else $shopping_item['image'] = $this->get_image_file($row);
    $shopping_item['weight'] = get_product_weight($this->db,$row,$features);
    if (isset($row['shipping'])) $shopping_item['shipping'] = $row['shipping'];
    if (isset($row['custom_label_0']))
       $shopping_item['custom_label_0'] = $row['custom_label_0'];
    if (isset($row['custom_label_1']))
       $shopping_item['custom_label_1'] = $row['custom_label_1'];
    if (isset($row['custom_label_2']))
       $shopping_item['custom_label_2'] = $row['custom_label_2'];
    if (isset($row['custom_label_3']))
       $shopping_item['custom_label_3'] = $row['custom_label_3'];
    if (isset($row['custom_label_4']))
       $shopping_item['custom_label_4'] = $row['custom_label_4'];

    return $shopping_item;
}

function build_item_product($item_info)
{
    $product = new Google_Service_ShoppingContent_Product();
    $product->setOfferId($item_info['id']);
    if ($item_info['title']) {
       if (strlen($item_info['title']) > 150)
          $item_info['title'] = substr($item_info['title'],0,150);
       $product->setTitle($item_info['title']);
    }
    if ($item_info['description']) {
       if (strlen($item_info['description']) > 5000)
          $item_info['description'] = substr($item_info['description'],0,5000);
       $product->setDescription($item_info['description']);
    }
    $product->setLink($item_info['url']);
    if ($this->use_image_link) $product->setImageLink($item_info['image_url']);
    $product->setContentLanguage('en');
    $product->setTargetCountry('US');
    $product->setChannel('online');
    $product->setAvailability($item_info['availability']);
    $product->setCondition($item_info['condition']);
    if ($item_info['mpn']) $product->setMpn($item_info['mpn']);
    if ($item_info['brand']) $product->setBrand($item_info['brand']);
    if ($item_info['product_type'])
       $product->setProductType($item_info['product_type']);
    if ($item_info['product_category'])
       $product->setGoogleProductCategory($item_info['product_category']);
    if ($item_info['gtin']) $product->setGtin($item_info['gtin']);
    $price = new Google_Service_ShoppingContent_Price();
    $price->setValue(number_format($item_info['price'],2,'.',''));
    $price->setCurrency('USD');
    $product->setPrice($price);
//    if ($item_info['adwords_labels'])
//       $product->setAdwordsLabels($item_info['adwords_labels']);
    if ($item_info['gender']) $product->setGender($item_info['gender']);
    if ($item_info['color']) $product->setColor($item_info['color']);
    if ($item_info['age']) $product->setAgeGroup($item_info['age']);
    if (isset($item_info['custom_label_0']))
       $product->setCustomLabel0($item_info['custom_label_0']);
    if (isset($item_info['custom_label_1']))
       $product->setCustomLabel1($item_info['custom_label_1']);
    if (isset($item_info['custom_label_2']))
       $product->setCustomLabel2($item_info['custom_label_2']);
    if (isset($item_info['custom_label_3']))
       $product->setCustomLabel3($item_info['custom_label_3']);
    if (isset($item_info['custom_label_4']))
       $product->setCustomLabel4($item_info['custom_label_4']);
    if (isset($item_info['shipping'])) {
       $shipping_price = new Google_Service_ShoppingContent_Price();
       $shipping_price->setValue(number_format($item_info['shipping'],2,'.',''));
       $shipping_price->setCurrency('USD');
       $shipping = new Google_Service_ShoppingContent_ProductShipping();
       $shipping->setPrice($shipping_price);
       $shipping->setCountry('US');
       $shipping->setService('Standard shipping');
       $product->setShipping(array($shipping));
    }
    if (! empty($item_info['weight'])) {
       $shipping_weight =
           new Google_Service_ShoppingContent_ProductShippingWeight();
       $shipping_weight->setValue($item_info['weight']);
       $shipping_weight->setUnit('lb');
       $product->setShippingWeight($shipping_weight);
    }
    return $product;
}

function add_item($item_info,$update=false)
{
    if (! $this->service) return null;
    $this->warnings = null;
    $item_product = $this->build_item_product($item_info);
    if ($this->debug) {
       if ($update)
          $this->log_activity('Updating Google Shopping Item: ' .
                           str_replace("\n",' ',print_r($item_product,true)));
       else $this->log_activity('Adding Google Shopping Item: ' .
                           str_replace("\n",' ',print_r($item_product,true)));
    }
    try {
       $response = $this->service->products->insert($this->merchant_id,
                                                    $item_product);
    } catch (Exception $e) {
       if ($e instanceof Google_Service_Exception) {
          $errors = $e->getErrors();
          if (isset($errors[0])) $error = $errors[0]['message'];
          else $error = 'Unknown Error';
       }
       else $error = $e->getMessage();
       $this->error = 'Google Shopping Error (Product #'.$item_info['id'].'): ' .
                      $error;
       $this->log_activity($this->error);
       $this->write_error($item_info['id'],$error);
       return null;
    }
    $this->write_error($item_info['id'],null);
    if ($this->debug)
       $this->log_activity('Response: ' .
                           str_replace("\n",' ',print_r($response,true)));
    $warnings = $response->getWarnings();   $this->warnings = '';
    foreach ($warnings as $warning) {
       $warning = 'Google Shopping Warning (Product #'.$item_info['id'] .
                  '): ['.$warning->getReason().'] '.$warning->getMessage();
       $this->log_activity($warning);
       if ($this->warnings) $this->warnings .= '|';
       $this->warnings .= $warning->getReason().': '.$warning->getMessage();
    }
    $this->write_warnings($item_info['id'],$this->warnings);
    $item_id = $response->getId();
    if ($update) {
       $this->log_activity('Updated Google Shopping Item '.$item_id);
       write_product_activity('Updated Item '.$item_id.' in Google Shopping',
                              $item_info['id'],$this->db);
    }
    else {
       $this->log_activity('Added Google Shopping Item '.$item_id);
       write_product_activity('Added Item '.$item_id.' to Google Shopping',
                              $item_info['id'],$this->db);
    }
    return $item_id;
}

function update_item($item_id,$item_info)
{
    if (! $this->add_item($item_info,true)) return false;
    return true;
}

function delete_item($item_id,$product_id)
{
    if (! $this->service) return true;
    if (substr($item_id,0,8) == 'generic/')
       $item_id = urldecode(substr($item_id,8));
    else if (substr($item_id,0,7) != 'online:')
       $item_id = 'online:en:US:'.$item_id;
    if ($this->debug)
       $this->log_activity('Deleting Google Shopping Item '.$item_id);
    try {
       $this->service->products->delete($this->merchant_id,$item_id);
    } catch (Exception $e) {
       if ($e instanceof Google_Service_Exception) {
          $errors = $e->getErrors();
          if (isset($errors[0])) $error = $errors[0]['message'];
          else $error = 'Unknown Error';
       }
       else $error = $e->getMessage();
       $this->error = 'Google Shopping Error (Item ID '.$item_id.', Product #' .
                      $product_id.'): '.$error;
       $this->log_activity($this->error);
       $this->write_error($item_id,$error);
       return false;
    }
    $this->write_error($item_id,null);
    $this->log_activity('Deleted Google Shopping Item '.$item_id);
    write_product_activity('Deleted Item '.$item_id.' from Google Shopping',
                           $product_id,$this->db);
    return true;
}

};

?>
