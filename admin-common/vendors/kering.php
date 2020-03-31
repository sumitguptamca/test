<?php
/*
                  Inroads Shopping Cart - Kering Eyewear Vendor Module

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

5) Upload Kering vendor module file to admin/vendors and glasses-common.php
   file to admin

6) Edit Kering Vendor Settings and set:

Account Username
Account Password
Default Auto Update Frequency
Default Attribute Set

7) Edit Kering Vendor Settings and select brands to import

8) Run Kering Imports

*/

require_once '../engine/http.php';

function debug_kering($str)
{
    log_activity($str);
}

global $vendor_fields;
$vendor_fields = array(array('name'=>'brands','type'=>TEXT_TYPE),
   array('name'=>'default_set','type'=>INT_TYPE),
   array('name'=>'default_auto_update','type'=>INT_TYPE));
global $vendor_import_fields;
$vendor_import_fields = array(array('name'=>'brand','type'=>CHAR_TYPE,
                                    'size'=>40),
                              array('name'=>'markup_source','type'=>INT_TYPE));
global $import_mapping;
$import_mapping = array(
   array('vendor_field'=>'Brand','update_field'=>'product|shopping_brand'),
   array('vendor_field'=>'Brand Line','update_field'=>'product|collection'),
   array('vendor_field'=>'Product Description','update_field'=>'product|name','convert_funct'=>'buildkerringname'),
   array('vendor_field'=>'Type / Material Group','update_field'=>'product|frame_style','convert_funct'=>'convertframestyle'),
   array('vendor_field'=>'Style Name','update_field'=>'product|model_number'),
   array('vendor_field'=>'EAN Code','update_field'=>'images|*','convert_funct'=>'keringimage'),
   array('vendor_field'=>'UPC Code','update_field'=>'product|shopping_gtin'),
   array('vendor_field'=>'Front Main Material Description','update_field'=>'product|front_material'),
   array('vendor_field'=>'Template Main Material Description','update_field'=>'product|temple_material'),
   array('vendor_field'=>'Lens Material Description','update_field'=>'product|lens_material'),
   array('vendor_field'=>'Lens Base','update_field'=>'product|lens_base'),
   array('vendor_field'=>'Lens Width','update_field'=>'product|lens_width','convert_funct'=>'sizelabel'),
   array('vendor_field'=>'Bridge Length','update_field'=>'product|bridge_size'),
   array('vendor_field'=>'Temple Length','update_field'=>'product|temple_length'),
   array('vendor_field'=>'Gender','update_field'=>'product|shopping_gender','convert_funct'=>'convertgender'),
   array('vendor_field'=>'Front Main Color','update_field'=>'product|front_color'),
   array('vendor_field'=>'Temple Main Color','update_field'=>'product|shopping_color'),
   array('vendor_field'=>'Lens Main Color','update_field'=>'product|lens_color'),
   array('vendor_field'=>'Shape','update_field'=>'product|shape','convert_funct'=>'convertshape'),
   array('vendor_field'=>'Polarized Lens','update_field'=>'product|polarized'),
   array('vendor_field'=>'Convertible in optical frame','update_field'=>'product|prescription','convert_funct'=>'alwaystrue'),
   array('vendor_field'=>'Hinge','update_field'=>'product|flex')
);

class Kering {

function __construct($db,$vendor_id)
{
    $this->url = 'https://my.keringeyewear.com';
    $this->db = $db;
    $this->vendor_id = $vendor_id;
    $query = 'select * from vendors where id=?';
    $query = $this->db->prepare_query($query,$this->vendor_id);
    $this->vendor_info = $this->db->get_record($query);
    $this->cookie_header = null;
    $this->error = null;
}

function Kering($db,$vendor_id)
{
    self::__construct($db,$vendor_id);
}

function set_cookies($http)
{
    $cookies = array('JSESSIONID','JSESSIONID-B2BACC','acceleratorSecureGUID',
                     'SERVERID','keringb2bstorefrontRememberMe');

print 'set_cookies, raw_cookies = '.print_r($http->raw_cookies,true)."\n";
    $this->cookie_header = '';
    if (! isset($http->cookies['JSESSIONID'])) {
       $error = 'Session cookie not found in '.$this->url.' response';
       log_error($error);   log_vendor_activity($error);
       return false;
    }
    foreach ($cookies as $cookie) {
       if (isset($http->cookies[$cookie])) {
          if ($this->cookie_header) $this->cookie_header .= '; ';
          else $this->cookie_header = 'Cookie: ';
          $this->cookie_header .= $cookie . '=' . $http->cookies[$cookie];
       }
    }
print 'Cookie Header = '.$this->cookie_header."\n";
    return true;
}

function login()
{
    if ($this->cookie_header) return true;

    if (empty($this->vendor_info['username']) ||
        empty($this->vendor_info['password'])) {
       $this->error = 'Missing Vendor Username or Password';   return false;
    }

    $url = $this->url.'/keringeyewear/en/login/customer';
    $http = new HTTP($url);
    $http->set_timeout(120);
    $post_data = 'customerEmail='.urlencode($this->vendor_info['username']);
print 'Calling '.$url.' with Post Data '.$post_data."\n";
    $uid_data = $http->call($post_data);
    if ($http->status != 200) {
       $error = 'Unable to login to '.$url.': '.$http->error.' (' .
                $http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if (! $this->set_cookies($http)) return false;

    $url = $this->url.'/keringeyewear/en/j_spring_security_check';
    $http = new HTTP($url);
    $http->set_timeout(120);
    $http->set_headers(array($this->cookie_header));
    $uid_data = substr($uid_data,1,-1);
    $post_data = 'j_username='.urlencode($uid_data) .
                 '&j_email='.urlencode($this->vendor_info['username']) .
                 '&j_password='.urlencode($this->vendor_info['password']);
print 'Calling '.$url.' with Post Data '.$post_data."\n";
    $http->call($post_data);
    if ($http->status != 302) {
       $error = 'Unable to login to '.$url.': '.$http->error.' (' .
                $http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if (! $this->set_cookies($http)) return false;

    return true;
}

function get_brands()
{
    if (! $this->login()) return null;
    $url = $this->url.'/keringeyewear/en/';
    $http = new HTTP($url);
    $http->set_timeout(120);
    $http->set_headers(array($this->cookie_header));
    $http->set_method('GET');
print 'Calling '.$url."\n";
    $page_data = $http->call();
    if (! $page_data) {
       $error = 'Unable to retrieve home page from '.$url.': ' .
                $http->error.' ('.$http->status.')';
       if (! empty($http->response_location))
          $error .= ' ['.$http->response_location.']';
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $start_pos = strpos($page_data,'menu-2 menu-category');
    if ($start_pos === false) {
       $error = 'Unable to retrieve brand list from '.$url;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $end_pos = strpos($page_data,'</div>',$start_pos);
    if ($end_pos === false) {
       $error = 'Unable to retrieve brand list from '.$url;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $menu = substr($page_data,$start_pos,$end_pos - $start_pos);
    $menu_items = explode('<li>',$menu);
    unset($menu_items[0]);
    $brands = array();
    foreach ($menu_items as $menu_item) {
       $start_pos = strpos($menu_item,'">');
       if ($start_pos === false) continue;
       $start_pos += 2;
       $end_pos = strpos($menu_item,'</a>',$start_pos);
       if ($end_pos === false) continue;
       $brand = substr($menu_item,$start_pos,$end_pos - $start_pos);
       $brands[] = $brand;
    }
    sort($brands);
    return $brands;
}

function get_brand_url($brand)
{
    $url = $this->url.'/keringeyewear/en/';
    $http = new HTTP($url);
    $http->set_timeout(120);
    $http->set_headers(array($this->cookie_header));
    $http->set_method('GET');
print 'Calling '.$url."\n";
    $page_data = $http->call();
    if (! $page_data) {
       $error = 'Unable to retrieve home page from '.$url.': ' .
                $http->error.' ('.$http->status.')';
       if (! empty($http->response_location))
          $error .= ' ['.$http->response_location.']';
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $start_pos = strpos($page_data,'menu-2 menu-category');
    if ($start_pos !== false)
       $start_pos = strpos($page_data,$brand,$start_pos);
    if ($start_pos !== false)
       $start_pos = strrpos($page_data,'<a href="',
                            -(strlen($page_data)-$start_pos));
    if ($start_pos === false) {
       $error = 'Unable to find brand url for '.$brand.' on '.$url;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $start_pos += 9;
    $end_pos = strpos($page_data,'"',$start_pos);
    if ($end_pos === false) {
       $error = 'Unable to find brand url for '.$brand.' on '.$url;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $brand_url = substr($page_data,$start_pos,$end_pos - $start_pos);
    return $brand_url;
}

function get_product_list($brand_url)
{
    $current_page = 0;   $num_pages = 999;   $product_list = array();

    while ($current_page < $num_pages) {
       $url = $this->url.$brand_url.'/results?q=&isBrandPage=true&page=' .
              $current_page;
       $http = new HTTP($url);
       $http->set_timeout(120);
       $http->set_headers(array($this->cookie_header));
       $http->set_method('GET');
       $js_data = $http->call();
       if (! $js_data) {
          $error = 'Unable to retrieve product list from ' .
                   $url.': '.$http->error.' ('.$http->status.')';
          if (! empty($http->response_location))
             $error .= ' ['.$http->response_location.']';
          log_error($error);   log_vendor_activity($error);   return null;
       }
       if ($num_pages == 999) {
          $start_pos = strpos($js_data,'numberOfPages');
          if (! $start_pos) {
             $error = 'Unable to retrieve product list from '.$url;
             log_error($error);   log_vendor_activity($error);   return null;
          }
          $start_pos += 17;
          $end_pos = strpos($js_data,'"',$start_pos);
          $num_pages = intval(substr($js_data,$start_pos,
                                     $end_pos - $start_pos));
       }
       $parts = explode('input type=\\"hidden\\" id=\\"isTransparent\\"',
                        $js_data);
       foreach ($parts as $section) {
          $start_pos = strpos($section,'a href=');
          if ($start_pos === false) continue;
          $start_pos += 9;
          $end_pos = strpos($section,'"',$start_pos);
          if ($end_pos === false) continue;
          $url = substr($section,$start_pos,$end_pos - $start_pos);
          $url = str_replace('\\','',$url);
          $start_pos = strpos($section,'div class=\\"sku\\"');
          if ($start_pos === false) continue;
          $start_pos += 23;
          $end_pos = strpos($section,'\\u003C',$start_pos);
          if ($end_pos === false) continue;
          $model = substr($section,$start_pos,$end_pos - $start_pos);
          $model = str_replace('\\n','',$model);
          $model = str_replace('\\t','',$model);
          $model = str_replace('\\','',$model);
          $product_list[$model] = array('url' => $url);
       }
       $current_page++;
    }
    return $product_list;
}

function get_product_details(&$product_list,$model)
{
    $url = $this->url.$product_list[$model]['url'];
    $http = new HTTP($url);
    $http->set_timeout(120);
    $http->set_headers(array($this->cookie_header));
    $http->set_method('GET');
    $page_data = $http->call();
    if ($http->status == 404) {
       $error = 'Product Details for Model '.$model.' Not Found';
       log_error($error);   log_vendor_activity($error);   return true;
    }
    if (! $page_data) {
       $error = 'Unable to retrieve product details (no data) from ' .
                $url.': '.$http->error.' ('.$http->status.')';
       if (! empty($http->response_location))
          $error .= ' ['.$http->response_location.']';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $start_pos = strpos($page_data,'<div class="descriptionul">');
    if ($start_pos) {
       $start_pos += 27;   $end_pos = strpos($page_data,'</div>',$start_pos);
    }
    if (($start_pos === false) || ($end_pos === false)) {
       $error = 'Unable to retrieve product details (no descriptionul) from ' .
                $url;
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $description = trim(substr($page_data,$start_pos,$end_pos - $start_pos));
    $product_list[$model]['description'] = $description;
    $items = array();
    $parts = explode('class="pdp-products"',$page_data);
    foreach ($parts as $index => $section) {
       if ($index == 0) continue;
       $start_pos = strpos($section,'<div class="title">');
       if ($start_pos === false) continue;
       $start_pos += 19;
       $end_pos = strpos($section,'</div>',$start_pos);
       if ($end_pos === false) continue;
       $item_sku = substr($section,$start_pos,$end_pos - $start_pos);

       $start_pos = strpos($section,' src="',$end_pos);
       if ($start_pos === false) continue;
       $start_pos += 6;
       $end_pos = strpos($section,'"',$start_pos);
       if ($end_pos === false) continue;
       $image = substr($section,$start_pos,$end_pos - $start_pos);
       if (! $image) {
          $start_pos = strpos($section,'data-zoomproductsrc="',$end_pos);
          if ($start_pos !== false) {
             $start_pos += 21;   $end_pos = strpos($section,'"',$start_pos);
             $image = substr($section,$start_pos,$end_pos - $start_pos);
          }
       }

       $start_pos = strpos($section,'path="priceSRPValue"',$end_pos);
       if ($start_pos === false) continue;
       $start_pos += 28;
       $end_pos = strpos($section,'"',$start_pos);
       if ($end_pos === false) continue;
       $list_price = floatval(substr($section,$start_pos,
                                     $end_pos - $start_pos));

       $start_pos = strpos($section,'<span class="prezzo-whs">',
                           $end_pos);
       if ($start_pos === false) continue;
       $start_pos += 25;
       $end_pos = strpos($section,'</span>',$start_pos);
       if ($end_pos === false) continue;
       $cost = trim(substr($section,$start_pos,$end_pos - $start_pos));
       $cost = floatval(str_replace('$','',$cost));

       $items[$item_sku] = array('image' => $image,'list_price' => $list_price,
                                 'cost' => $cost);
    }
    $product_list[$model]['items'] = $items;
    return true;
}

function get_brand_details($brand,$test_model=null)
{
    if (! $this->login()) return null;
    $brand_url = $this->get_brand_url($brand);
    if (! $brand_url) return null;
    $product_list = $this->get_product_list($brand_url);
    if (! $product_list) return null;
    foreach ($product_list as $model => $product_info) {
       if ($test_model && ($model != $test_model)) {
          unset($product_list[$model]);   continue;
       }
       if (! $this->get_product_details($product_list,$model))
          return null;
    }
    return $product_list;
}

function parse_model($product_data)
{
    $model = trim($product_data->row[5]);
    $model_parts = explode(' ',$model);
    $brand = $product_data->row[1];
    if ($brand == 'Saint Laurent') {
       $skip = false;
       foreach ($model_parts as $index => $part) {
          $part = strtolower($part);
          if (($part == 'sunglass') || ($part == 'optical'))
             $skip = true;
          if ($skip) unset($model_parts[$index]);
       }
       $model = implode(' ',$model_parts);
    }
    else $model = $model_parts[0];
    return $model;
}

function parse_item_id($product_data)
{
    $item_id = $product_data->row[3];
    $item_parts = explode(' ',$item_id);
    $brand = $product_data->row[1];
    if ($brand == 'Saint Laurent') {
       $skip = false;
       foreach ($item_parts as $index => $part) {
          $part = strtolower($part);
          if (($part == 'sunglass') || ($part == 'optical')) {
             $skip = true;   unset($item_parts[$index - 1]);
          }
          if ($skip) unset($item_parts[$index]);
       }
       $item_id = implode(' ',$item_parts);
    }
    else $item_id = $item_parts[0];
    return $item_id;
}

function download_catalog($brand_code,$import_filename)
{
    $url = $this->url.'/keringeyewear/en/**/c/createFileExcel?categoryCode=' .
           $brand_code.'&q=%3Arelevance%3AitemtypeName%3AStyle';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $http->set_method('GET');
    $http->set_timeout(300);
    $response = $http->call();
    if (! $response) {
       $error = 'Unable to retrieve download catalog from ' .
                $url.': '.$http->error.' ('.$http->status.')';
       if (! empty($http->response_location))
          $error .= ' ['.$http->response_location.']';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if ($http->status == 404) {
       $error = 'Catalog is not available from '.$url;
       log_error($error);   log_vendor_activity($error);
       if (file_exists($import_filename)) return true;
       return false;
    }

    $json_data = json_decode($response);
    if (empty($json_data->redirectUrl)) {
       $error = 'Invalid response to download request from ' .
                $url.': '.substr($response,0,1000);
       log_error($error);   log_vendor_activity($error);   return false;
    }

    $url = $this->url.'/keringeyewear/en/**/c/downloadFileExcel';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $http->set_timeout(300);
    $post_data = 'customerName='.urlencode($json_data->redirectUrl);
    $data = $http->call($post_data);
    if ($http->status != 200) {
       $error = 'Unable to download catalog from '.$url.': ' .
                $http->error.' ('.$http->status.')';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if (! $data) {
       $error = 'Empty download catalog returned from '.$url;
       log_error($error);   log_vendor_activity($error);   return false;
    }
    file_put_contents($import_filename,$data);
    log_vendor_activity('Downloaded Kering Product Data File for Brand ' .
                        $brand_code);
    return true;
}

};

function kering_install($db)
{
    global $vendor_fields,$vendor_import_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    $vendor_info = array('module'=>'kering','name'=>'Kering EyeWear USA',
       'company'=>'Kering EyeWear','address1'=>'65 Bleecker St.',
       'city'=>'New York','state'=>'NY','zipcode'=>'10023','country'=>1,
       'phone'=>'212-302-2920','num_markups'=>0);

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
    if (! add_module_vendor($db,$vendor_info)) return;
    if (! add_import_fields($db,$vendor_import_fields)) return;
}

function kering_upgrade($db)
{
    global $vendor_fields,$vendor_import_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
    if (! add_import_fields($db,$vendor_import_fields)) return;
}

function kering_vendor_fields(&$vendor_record)
{
    $vendor_record['brands'] = array('type'=>CHAR_TYPE);
    $vendor_record['default_set'] = array('type'=>INT_TYPE);
    $vendor_record['default_auto_update'] = array('type'=>INT_TYPE);
}

function kering_import_fields(&$vendor_import_record)
{
    $vendor_import_record['brand'] = array('type'=>CHAR_TYPE);
    $vendor_import_record['markup_source'] = array('type'=>INT_TYPE);
}

function kering_add_vendor_fields($dialog,$edit_type,$row,$db,$tab_name)
{
    if ($tab_name != 'settings') return;

    $kering = new Kering($db,$row['id']);
    $dialog->start_row('Go To Kering Eyewear:');
    $dialog->write('<a href="'.$kering->url .
                   '" target="_blank">Kering Eyewear</a>');
    $dialog->end_row();

    $auto_update = get_row_value($row,'default_auto_update');
    $dialog->start_row('Default Auto Update Frequency:','middle');
    $dialog->add_radio_field('default_auto_update','0','None',
                             ($auto_update == AUTO_UPDATE_NONE));
    $dialog->add_radio_field('default_auto_update','1','Hourly',
                             ($auto_update == AUTO_UPDATE_HOURLY));
    $dialog->add_radio_field('default_auto_update','2','Daily',
                             ($auto_update == AUTO_UPDATE_DAILY));
    $dialog->add_radio_field('default_auto_update','3','Weekly',
                             ($auto_update == AUTO_UPDATE_WEEKLY));
    $dialog->add_radio_field('default_auto_update','4','Monthly',
                             ($auto_update == AUTO_UPDATE_MONTHLY));
    $dialog->end_row();

    $query = 'select * from attribute_sets';
    $sets = $db->get_records($query,'id','name');
    if (! empty($sets)) {
       $attribute_set = get_row_value($row,'default_set');
       $dialog->start_row('Default Attribute Set:');
       $dialog->start_choicelist('default_set');
       $dialog->add_list_item('','',(! $attribute_set));
       foreach ($sets as $set_id => $set_name) {
          $dialog->add_list_item($set_id,$set_name,$attribute_set == $set_id);
       }
       $dialog->end_choicelist();
       $dialog->end_row();
    }

    $current_brands = explode(',',$row['brands']);
    $kering = new Kering($db,$row['id']);
    $brands = $kering->get_brands();
    $dialog->start_row('Brands to Import:','top');
    $dialog->add_hidden_field('Brands',json_encode($brands));
    $dialog->add_hidden_field('OldBrands',$row['brands']);
    $dialog->start_table();
    if ($brands) foreach ($brands as $index => $brand) {
       if ($index % 2) $dialog->write('</td><td>');
       else {
          if ($index != 0) $dialog->write("</td></tr>\n");
          $dialog->write('<tr><td>');
       }
       if (in_array($brand,$current_brands)) $checked = true;
       else $checked = false;
       $dialog->add_checkbox_field('brand_'.$index,$brand,$checked);
    }
    $dialog->end_row();
    $dialog->end_table();
    $dialog->end_row();
}

function add_kering_import($db,$vendor_id,$brand)
{
    global $import_mapping;

    $vendor_info = load_vendor_info($db,$vendor_id);
    $import_info = array('brand'=>$brand,'parent'=>$vendor_id,
       'name'=>$brand.' Product Data','import_type'=>1,'import_source'=>5,
       'auto_update'=>$vendor_info['default_auto_update'],'new_status'=>0,
       'load_existing'=>1,'match_existing'=>3,
       'attribute_set'=>$vendor_info['default_set'],'image_options'=>1,
       'non_match_action'=>1,'noimage_status'=>1,'flags'=>2,
       'mapping'=>$import_mapping);
    add_module_import($db,$vendor_info,$import_info);
}

function delete_kering_import($db,$vendor_id,$brand)
{
    $query = 'select * from vendor_imports where (parent=?) and (brand=?)';
    $query = $db->prepare_query($query,$vendor_id,$brand);
    $row = $db->get_record($query);
    if ($row) delete_module_import($db,$row);
}

function kering_parse_vendor_fields($db,&$vendor_record)
{
    $brands = json_decode(get_form_field('Brands'));
    $old_brands = explode(',',get_form_field('OldBrands'));
    $new_brands = array();
    $vendor_id = $vendor_record['id']['value'];
    if ($brands) foreach ($brands as $index => $brand) {
       $checked = get_form_field('brand_'.$index);
       if ($checked) {
          if (! in_array($brand,$old_brands))
             add_kering_import($db,$vendor_id,$brand);
          $new_brands[] = $brand;
       }
       else if (in_array($brand,$old_brands))
          delete_kering_import($db,$vendor_id,$brand);
    }
    $vendor_record['brands']['value'] = implode(',',$new_brands);
}

function kering_add_import_fields($db,$dialog,$edit_type,$row,$section)
{
    global $status_values,$num_status_values;

    if ($section != 'import_options') return;

    $markup_source = get_row_value($row,'markup_source');
    $dialog->start_row('Markup Price From:','middle');
    $dialog->add_radio_field('markup_source',0,'None',$row);
    $dialog->add_radio_field('markup_source',1,'List Price',$row);
    $dialog->add_radio_field('markup_source',2,'Price',$row);
    $dialog->add_radio_field('markup_source',3,'Cost',$row);
    $dialog->end_row();
}

function kering_download_catalog($db,$import)
{
    $vendor_id = $import['parent'];
    $brand = $import['brand'];
    $kering = new Kering($db,$vendor_id);
    if (! $kering->login()) return false;
    $brand_url = $kering->get_brand_url($brand);
    if (! $brand_url) return false;
    $url_parts = explode('/',$brand_url);
    $brand_code = $url_parts[count($url_parts) - 1];
    if (! $import['import_file']) {
       $import['import_file'] = 'import-'.$import['id'].'.xlsx';
       $new_import_file = true;
    }
    else $new_import_file = false;
    $import_filename = '../admin/vendors/'.$import['import_file'];
    if (! $kering->download_catalog($brand_code,$import_filename))
       return false;
    if ($new_import_file) {
       $query = 'update vendor_imports set import_file=? where id=?';
       $query = $db->prepare_query($query,$import['import_file'],
                                   $import['id']);
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    return true;
}

function kering_load_custom_data($db,&$product_data,&$custom_data)
{
    $product_data->kering = new Kering($db,$product_data->vendor_id);
}

function kering_update_conversions($db,&$conversions)
{
    $conversions['sizelabel'] = 'Build Size Label';
    $conversions['keringimage'] = 'Download Kering Image';
    $conversions['buildkerringname'] = 'Build Kerring Name & Model';
    $conversions['convertgender'] = 'Convert Gender';
    $conversions['alwaystrue'] = 'Always True';
    $conversions['convertframestyle'] = 'Convert Frame Style';
    $conversions['convertshape'] = 'Convert Shape';
}

function kering_process_image_conversion($map_info,&$product_data,
   &$image_filename,&$image_modified)
{
    if ($map_info['convert_funct'] != 'keringimage') return false;
    $kering = $product_data->kering;
    $image_filename = '';   $image_modified = false;
    if (! isset($product_data->product_record['model_number']['value']))
       return true;
    if (! isset($product_data->product_record['shopping_brand']['value']))
       return true;
    $model = $kering->parse_model($product_data);
    $brand = $product_data->product_record['shopping_brand']['value'];
    $item_id = $kering->parse_item_id($product_data);
    if ((! $model) || (! $brand) || (! $item_id)) return true;
    if (! isset($product_data->kering_products))
       $product_data->kering_products = array();
    if (! isset($product_data->kering_products[$brand])) {
       $product_data->kering_products[$brand] =
          $kering->get_brand_details($brand);
       if ($product_data->kering_products[$brand])
          log_vendor_activity('Downloaded Kering Product Details for ' .
                              'Brand '.$brand);
       else {
          $error = 'Kering Product Details for Brand '.$brand .
                   ' not found';
          log_error($error);   log_vendor_activity($error);
          return true;
       }
    }
    if (! isset($product_data->kering_products[$brand][$model])) {
       $error = 'Kering Product Details for Brand '.$brand .
                ' and Model '.$model.' not found';
       log_error($error);   log_vendor_activity($error);
       return true;
    }
    $kering_info = $product_data->kering_products[$brand][$model];
    $product_data->product_record['short_description']['value'] =
       $kering_info['description'];
    if (! isset($kering_info['items'][$item_id])) {
       $error = 'Kering Product Details for Brand '.$brand .
                ' and Item '.$item_id.' not found';
       log_error($error);   log_vendor_activity($error);
       return true;
    }
    $item_info = $kering_info['items'][$item_id];
    $product_data->product_record['list_price']['value'] =
       $item_info['list_price'];
    $product_data->product_record['price']['value'] =
       $item_info['list_price'];
    $product_data->product_record['cost']['value'] = $item_info['cost'];
    $markup_source = $product_data->import['markup_source'];
    switch ($markup_source) {
       case 1: $markup_update_field = 'product|list_price';   break;
       case 2: $markup_update_field = 'product|price';   break;
       case 3: $markup_update_field = 'product|cost';   break;
       default: $markup_update_field = null;
    }
    if ($markup_update_field) {
       $cost_map = array('convert_funct' => 'setmarkupprice',
                         'update_field' => $markup_update_field);
       process_conversion($cost_map,$product_data);
    }
    $image = $item_info['image'];
    if (! $image) {
       $error = 'Kering Image not found for Brand '.$brand .
                ' and Item '.$item_id;
       log_vendor_activity($error);   return true;
    }
    $image_options = $product_data->import['image_options'];
    $image_url = $image;
    $image_info = explode('?',$image);
    $image_filename = basename($image_info[0]);
    $local_filename = '../images/original/'.$image_filename;
    if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
        file_exists($local_filename))
       $last_modified = filemtime($local_filename);
    else $last_modified = get_last_modified($image_url,5);
    if ($last_modified == -1) {
       $error = 'Kering image '.$image_url.' not found';
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    if (file_exists($local_filename) &&
        (filemtime($local_filename) == $last_modified)) return true;
    $image_data = @file_get_contents($image_url);
    if (! $image_data) {
       $error = 'Unable to download Kering image '.$image_url;
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    file_put_contents($local_filename,$image_data);
    touch($local_filename,$last_modified);
    $image_modified = true;
    log_vendor_activity('Downloaded Kering Product Image ' .
                        $image_filename.' for Item '.$item_id);
    return true;
}

function kering_process_conversion(&$map_info,&$product_data)
{
    $kering = $product_data->kering;
    $convert_function = $map_info['convert_funct'];
    switch ($convert_function) {
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
       case 'keringimage': return null;
       case 'buildkerringname': 
          $name = $product_data->product_record['name']['value'];
          $name_parts = explode(' ',$name);
          $display_name = $name_parts[0];
          $map_info['update_field'] = 'product|display_name';
          set_update_field($map_info,$product_data,$display_name);
          $map_info['update_field'] = 'product|name';
          set_update_field($map_info,$product_data,$name);
          $model = $kering->parse_model($product_data);
          $map_info['update_field'] = 'product|model';
          set_update_field($map_info,$product_data,$model);
          $map_info['update_field'] = 'product|model_number';
          set_update_field($map_info,$product_data,$model);
          return null;
       case 'convertgender':
          $shopping_age = 'adult';
          $field_value = strtolower(get_update_field($map_info,$product_data));
          if ($field_value == 'man') $field_value = 'male';
          else if ($field_value == 'woman') $field_value = 'female';
          else if ($field_value == 'kid') {
             $field_value = '';   $shopping_age = 'kids';
          }
          $field_value = ucfirst($field_value);
          set_update_field($map_info,$product_data,$field_value);
          $map_info['update_field'] = 'product|shopping_age';
          set_update_field($map_info,$product_data,$shopping_age);
          return null;
        case 'alwaystrue':
          $field_value = 1;
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
    }
    return true;
}

function kering_check_import(&$product_data)
{
    if (empty($product_data->product_record['prescription']['value']))
       $product_data->product_attributes = null;
    return true;
}

?>
