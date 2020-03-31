<?php
/*
               Inroads Shopping Cart - Marchon Eyewear Vendor Module

                       Written 2018-2019 by Randall Severy
                        Copyright 2018-2019 Inroads, LLC

*/

require_once '../engine/http.php';

global $vendor_fields;
$vendor_fields = array(array('name'=>'brands','type'=>TEXT_TYPE));

global $import_mapping;
$import_mapping = array(
   array('vendor_field'=>'FRC#12','update_field'=>'product|shopping_gtin'),
   array('vendor_field'=>'FRSDSC','update_field'=>'product|model_number','convert_funct'=>'copytomodel'),
/*   array('vendor_field'=>'FRSTY','update_field'=>'images|*','convert_funct'=>'marchonimage'), */
   array('vendor_field'=>'FRCDSC','update_field'=>'product|shopping_color','convert_funct'=>'marchonname'),
   array('vendor_field'=>'FRCOL','update_field'=>'product|color_code'),
   array('vendor_field'=>'FRESZE','update_field'=>'product|lens_width','convert_funct'=>'sizelabel'),
   array('vendor_field'=>'FRBSZE','update_field'=>'product|bridge_size'),
   array('vendor_field'=>'FRTLEN','update_field'=>'product|temple_length'),
   array('vendor_field'=>'FRB','update_field'=>'product|lens_height'),
   array('vendor_field'=>'FRCPRC','update_field'=>'product|cost','convert_funct'=>'setmarkupprice'),
   array('vendor_field'=>'FRDIV','update_field'=>'product|shopping_brand','convert_funct'=>'marchonbrand'),
   array('vendor_field'=>'FRBNDC','update_field'=>'product|collection','convert_funct'=>'setframestyle'),
   array('vendor_field'=>'FRSEX','update_field'=>'product|shopping_gender','convert_funct'=>'convertgender'),
   array('vendor_field'=>'FRUSAG','update_field'=>'product|prescription','convert_funct'=>'alwaystrue')
);

global $brand_replace;
$brand_replace = array(
   'CALVIN KLEIN' => 'Calvin Klein',
   'CHLOE' => 'Chloé',
   'CK COLLECTION' => 'Calvin Klein',
   'COLUMBIA' => 'Columbia',
   'DRAGON' => 'Dragon',
   'DRAGONA' => 'Dragon',
   'ETRO' => 'Etro',
   'FERRAGAMO' => 'Salvatore Ferragamo',
   'FLEXON' => 'Flexon',
   'LACOSTE' => 'Lacoste',
   'LIU JO' => 'Liu Jo',
   'LONGCHAMP' => 'Longchamp',
   'MARCHON' => 'Marchon NYC',
   'MARCHON AIRLOCK' => 'Airlock',
   'NAUTICA' => 'Nautica',
   'NIKE' => 'Nike',
   'NIKEON' => 'Nike',
   'NINE WEST' => 'Nine West',
   'SKAGA' => 'Skaga'
);

function debug_marchon($str)
{
//    log_activity($str);
    print $str."\n";
}

class Marchon {

function __construct($db,$vendor_id)
{
    $this->url = 'https://account.mymarchon.com';
    $this->db = $db;
    $this->vendor_id = $vendor_id;
    $query = 'select * from vendors where id=?';
    $query = $this->db->prepare_query($query,$this->vendor_id);
    $this->vendor_info = $this->db->get_record($query);
    $this->cookie_header = null;
    $this->identity = null;
}

function Marchon($db,$vendor_id)
{
    self::__construct($db,$vendor_id);
}

function login()
{
    if ($this->cookie_header) return true;
    if (empty($this->vendor_info['username']) ||
        empty($this->vendor_info['password'])) return false;

    $url = $this->url.'/pkmslogin.form';
    $http = new HTTP($url);
    $post_data = 'username='.urlencode($this->vendor_info['username']) .
                 '&password='.urlencode($this->vendor_info['password']) .
                 '&login-form-type=pwd';
// $http->set_debug_function('debug_marchon');
    $http->call($post_data);
    if ($http->status != 200) {
       $error = 'Unable to login to '.$this->url.': '.$http->error;
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if (! isset($http->cookies['PD-S-SESSION-ID'])) {
       $error = 'Session cookie not found in '.$this->url.' response';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $this->cookie_header = 'Cookie: PD-S-SESSION-ID='.$http->cookies['PD-S-SESSION-ID'];
    if (isset($http->cookies['BIGipServerSAM_mvp_PR_https_pool']))
       $this->cookie_header .= '; BIGipServerSAM_mvp_PR_https_pool=' .
                               $http->cookies['BIGipServerSAM_mvp_PR_https_pool'];
    return true;
}

function get_credentials()
{
    $url = $this->url.'/bpm/AccountHome/';
    $http = new HTTP($url);
    $http->set_method('GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $error = 'Unable to retrieve home page from '.$this->url.': ' .
                $http->error;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $start_pos = strpos($page_data,'window.identity=');
    if ($start_pos === false) {
       $error = 'Unable to find credentials start in '.$this->url;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $start_pos += 16;
    $end_pos = strpos($page_data,';</script>',$start_pos);
    if ($end_pos === false) {
       $error = 'Unable to find credentials end in '.$this->url;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $this->credentials = substr($page_data,$start_pos,$end_pos - $start_pos);
    $credentials = str_replace("'",'"',$this->credentials);
    $credentials = str_replace(',}','}',$credentials);
    $this->identity = json_decode($credentials);
}

function get_brands()
{
    if (! $this->login()) return null;
    if (! $this->identity) $this->get_credentials();

    $post_data = '{"userCredential":'.$this->credentials .
                 ',"accountNumber":"'.$this->identity->accountNumber.'"}';
    $url = $this->url.'/bpm/Module_GETBRANDSFORUSRWeb/brandList2/invoke';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $response = $http->call($post_data);
    if (! $response) {
       $error = 'Unable to retrieve brand list from '.$this->url.': ' .
                $http->error;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $json_data = json_decode($response);
    $this->brands = $json_data->brands;
    $brands = array();
    foreach ($json_data->brands as $brand_info)
       $brands[] = $brand_info->brandName;
    sort($brands);
    return $brands;
}

function find_brand_code($brand)
{
    foreach ($this->brands as $brand_info) {
       if ($brand_info->brandName == $brand) return $brand_info->brandCode;
    }
    return null;
}

function get_product_details($style_number)
{
    if (! $this->login()) return null;
    if (! $this->identity) $this->get_credentials();

    $credentials = $this->identity;
    $credentials->isGreenGrassAccount = false;
    $credentials->isFirstTimeGreenGrass = false;
    $url = $this->url .
           '/bpm/ProductCatologWebWeb/GetSpecSheetInfoJson/getSpecSheetByStyle';
    $post_data = '{"style":"'.$style_number.'","itemType":"F","orderType":"ZRX",' .
       '"currencyCode":"USD","salesOrg":"'.$this->identity->salesOrg.'",' .
       '"distChannel":"'.$this->identity->custSalesArea[0]->sapDistribution .
       '","userCredential":'.json_encode($credentials).'}';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $response = $http->call($post_data);
    if (! $response) {
       $error = 'Unable to retrieve product details from '.$this->url.': ' .
                $http->error;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $data = json_decode($response);
    if (! isset($data->serviceStatus->resultCode)) {
       $error = 'Invalid product details retrieved from '.$this->url .
                ': '.substr($response,0,100).'...';
       log_error($error);   log_vendor_activity($error);   return null;
    }
    if ($data->serviceStatus->resultCode) {
       $error = 'Error retrieving product details from '.$this->url.': ' .
                $data->serviceStatus->message;
       log_error($error);   log_vendor_activity($error);   return null;
    }

    $url = $this->url .
           '/bpm/ControlDataServicesWeb/getControlInfoExport/invoke';
    $post_data = '{"tag":"HIRESIMG'.$style_number.'"}';
    $http = new HTTP($url);
    $http->set_headers(array($this->cookie_header));
    $response = $http->call($post_data);
    if (! $response) {
       $error = 'Unable to retrieve image details from '.$this->url.': ' .
                $http->error;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $json_data = json_decode($response);
    if (isset($json_data->serviceStatus->resultCode) &&
        (! $json_data->serviceStatus->resultCode)) {
       $images = array();
       foreach ($json_data->controlData as $image_data) {
          $image = $image_data->description;
          if (substr($image,0,2) == '..') $image = substr($image,2);
          $images[$image_data->code] = $image;
       }
       foreach ($data->skuDetail as $index => $sku) {
          if (isset($images[$sku->color]))
             $data->skuDetail[$index]->hiResImage = $images[$sku->color];
       }
    }

    return $data;
}

function build_product_name($product_data)
{
    $model = $product_data->product_record['model_number']['value'];
    $color = $product_data->product_record['shopping_color']['value'];
    $width = $product_data->product_record['lens_width']['value'];
    $bridge = $product_data->product_record['bridge_size']['value'];
    $length = $product_data->product_record['temple_length']['value'];
    $product_name = $model.' '.$color.' '.$width.'-'.$bridge.'-'.$length;
    return $product_name;
}

};

function add_marchon_import($db,$vendor_id)
{
    global $import_mapping;

    $vendor_info = load_vendor_info($db,$vendor_id);
    $import_info = array('parent'=>$vendor_id,
       'name'=>'Product Data','import_type'=>1,'import_source'=>5,
       'new_status'=>0,'load_existing'=>0,'match_existing'=>3,
       'image_options'=>1,'non_match_action'=>1,'noimage_status'=>1,
       'flags'=>2,'mapping'=>$import_mapping);
    add_module_import($db,$vendor_info,$import_info);
}

function marchon_install($db)
{
    global $vendor_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    $vendor_info = array('module'=>'marchon','name'=>'Marchon Eyewear',
       'company'=>'Marchon Eyewear, Inc',
       'address1'=>'201 Old Country Road','city'=>'Melville','state'=>'NY',
       'zipcode'=>'11747','country'=>1,'phone'=>'631-629-3200',
       'email'=>'cs@marchon.com','num_markups'=>0);

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
    $vendor_id = add_module_vendor($db,$vendor_info);
    if (! $vendor_id) return;
    add_marchon_import($db,$vendor_id);
}

function marchon_upgrade($db)
{
    global $vendor_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
}

function marchon_vendor_fields(&$vendor_record)
{
    $vendor_record['brands'] = array('type'=>CHAR_TYPE);
}

function marchon_add_vendor_fields($dialog,$edit_type,$row,$db,$tab_name)
{
    if ($tab_name != 'settings') return;
    $current_brands = explode(',',$row['brands']);
    $marchon = new Marchon($db,$row['id']);
    $brands = $marchon->get_brands();
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

function marchon_parse_vendor_fields($db,&$vendor_record)
{
    $brands = json_decode(get_form_field('Brands'));
    $old_brands = explode(',',get_form_field('OldBrands'));
    $new_brands = array();
    $vendor_id = $vendor_record['id']['value'];
    if ($brands) foreach ($brands as $index => $brand) {
       $checked = get_form_field('brand_'.$index);
       if ($checked) $new_brands[] = $brand;
    }
    $vendor_record['brands']['value'] = implode(',',$new_brands);
}

function marchon_download_catalog($db,$import)
{
    $vendor_id = $import['parent'];
    if (! $import['import_file']) {
       $import['import_file'] = 'import-'.$import['id'].'.csv';
       $new_import_file = true;
    }
    else $new_import_file = false;
    $import_filename = '../admin/vendors/'.$import['import_file'];
    $catalog_url = 'https://account.mymarchon.com/Images/FrameFile/frames.csv';
    $last_modified = get_last_modified($catalog_url);
    if ($last_modified == -1) return true;
    if (file_exists($import_filename) &&
        (filemtime($import_filename) == $last_modified)) return true;
    $catalog_data = @file_get_contents($catalog_url);
    if (! $catalog_data) {
       $error = 'Unable to download Marchon catalog from '.$catalog_url;
       log_error($error);   log_vendor_activity($error);
       return true;
    }
    file_put_contents($import_filename,$catalog_data);
    touch($import_filename,$last_modified);
    if ($new_import_file) {
       $query = 'update vendor_imports set import_file=? where id=?';
       $query = $db->prepare_query($query,$import['import_file'],
                                   $import['id']);
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    log_vendor_activity('Downloaded Marchon Product Data File from ' .
                        $catalog_url);
    return true;
}

function marchon_load_custom_data($db,&$product_data,&$custom_data)
{
    $product_data->marchon = new Marchon($db,$product_data->vendor_id);
    $product_data->marchon_products = array();
    $query = 'select brands from vendors where id=?';
    $query = $db->prepare_query($query,$product_data->vendor_id);
    $row = $db->get_record($query);
    if ($row) $brands = explode(',',$row['brands']);
    else $brands = array();
    $product_data->marchon_brands = $brands;
}

function marchon_update_conversions($db,&$conversions)
{
    $conversions['sizelabel'] = 'Build Size Label';
    $conversions['copytomodel'] = 'Copy to Model';
    $conversions['convertgender'] = 'Convert Gender';
    $conversions['marchonbrand'] = 'Convert Marchon Brand';
    $conversions['marchonimage'] = 'Download Marchon Image';
    $conversions['marchonname'] = 'Build Marchon Product Name';
    $conversions['setframestyle'] = 'Set Frame Style/Prescription';
    $conversions['alwaystrue'] = 'Always True';
}

function marchon_check_vendor_data($product_data,$row)
{
    global $brand_replace;

    $brand = trim($row[15]);
    if (isset($brand_replace[$brand])) $brand = $brand_replace[$brand];
    if (in_array($brand,$product_data->marchon_brands)) return true;
    return false;
}

function marchon_process_image_conversion($map_info,&$product_data,
   &$image_filename,&$image_modified)
{
    if ($map_info['convert_funct'] != 'marchonimage') return false;
    $image_filename = '';   $image_modified = false;
    if (! isset($product_data->row[3])) return true;
    $style_number = trim($product_data->row[3]);
    $marchon = $product_data->marchon;
    if (! array_key_exists($style_number,$product_data->marchon_products)) {
       $product_data->marchon_products[$style_number] =
          $marchon->get_product_details($style_number);
       if ($product_data->marchon_products[$style_number])
          log_vendor_activity('Downloaded Marchon Product Details for Style ' .
                              $style_number);
       else {
          $error = 'Marchon Product Details for Style '.$style_number .
                   ' not found';
          log_error($error);   log_vendor_activity($error);
          return true;
       }
    }

    if (isset($product_data->marchon_products[$style_number]->
              techSpecs->field_styles)) {
       $field_styles = $product_data->marchon_products[$style_number]->
                       techSpecs->field_styles;
/* Generate product data (Specifications) records - trim *everything*! */
    }

    if (! isset($product_data->product_record['shopping_gtin']['value'])) {
       $error = 'Marchon UPC Code not found for product ' .
                $marchon->build_product_name($product_data);
       log_error($error);   log_vendor_activity($error);
       return true;
    }
    $upc = trim($product_data->product_record['shopping_gtin']['value']);
    $sku_info = null;
    if (isset($product_data->marchon_products[$style_number]->skuDetail)) {
       foreach ($product_data->marchon_products[$style_number]->skuDetail as
                $sku_detail) {
          if (trim($sku_detail->upcNumber) == $upc) {
             $sku_info = $sku_detail;   break;
          }
       }
    }
    if (! $sku_info) {
       $error = 'Marchon SKU Data not found for product ' .
                $marchon->build_product_name($product_data);
       log_error($error);   log_vendor_activity($error);   return true;
    }
    if (isset($sku_info->hiResImage))
       $image_url = $marchon->url.'/Images'.trim($sku_info->hiResImage);
    else {
       $image = trim($sku_info->colorImage);
       if ($image) $image_url = $marchon->url.'/Images/jpg_L/'.$image;
       else {
          $error = 'Marchon Image not found for product ' .
                   $marchon->build_product_name($product_data);
          log_vendor_activity($error);   return true;
       }
    }
    $image_options = $product_data->import['image_options'];
    $image_filename = basename($image_url);
    $local_filename = '../images/original/'.$image_filename;
    if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
        file_exists($local_filename))
       $last_modified = filemtime($local_filename);
    else $last_modified = get_last_modified($image_url,5);
    if ($last_modified == -1) {
       $error = 'Marchon image '.$image_url.' not found';
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    if (file_exists($local_filename) &&
        (filemtime($local_filename) == $last_modified)) return true;
    $image_data = @file_get_contents($image_url);
    if (! $image_data) {
       $error = 'Unable to download Marchon image '.$image_url;
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    file_put_contents($local_filename,$image_data);
    touch($local_filename,$last_modified);
    $image_modified = true;
    log_vendor_activity('Downloaded Marchon Product Image ' .
                        $image_filename);
    return true;
}

function marchon_process_conversion(&$map_info,&$product_data)
{
    global $brand_replace;

    $marchon = $product_data->marchon;
    $convert_function = $map_info['convert_funct'];
    switch ($convert_function) {
       case 'copytomodel':
          $field_value = get_update_field($map_info,$product_data);
          $map_info['update_field'] = 'product|model';
          set_update_field($map_info,$product_data,$field_value);
          return null;
       case 'convertgender':
          $field_value = get_update_field($map_info,$product_data);
          switch ($field_value) {
             case 1: $shopping_age = 'adult';   $field_value = 'Unisex';
                     break;
             case 2: $shopping_age = 'adult';   $field_value = 'Female';
                     break;
             case 3: $shopping_age = 'adult';   $field_value = 'Male';   break;
             case 4: $shopping_age = 'kids';   $field_value = 'Unisex';
                     break;
             case 5: $shopping_age = 'kids';   $field_value = 'Female';
                     break;
             case 6: $shopping_age = 'kids';   $field_value = 'Male';   break;
          }
          set_update_field($map_info,$product_data,$field_value);
          $map_info['update_field'] = 'product|shopping_age';
          set_update_field($map_info,$product_data,$shopping_age);
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
       case 'marchonbrand':
          $field_value = get_update_field($map_info,$product_data);
          if (isset($brand_replace[$field_value]))
             $field_value = $brand_replace[$field_value];
          set_update_field($map_info,$product_data,$field_value);
          return null;
       case 'setframestyle':
          $field_value = get_update_field($map_info,$product_data);
          $parts = explode(' ',$field_value);
          $last_part = $parts[count($parts) - 1];
          $frame_style = null;   $prescription = null;
          switch ($last_part) {
             case 'OPT':
             case 'OPTICAL':
                $prescription = 1;   $frame_style = 'Optical';   break;
             case 'SUN':
             case 'SUNS':
                $prescription = 0;   $frame_style = 'Sunglass';   break;
             case 'READERS':
                $prescription = 1;   $frame_style = 'Reader';   break;
          }
          if ($frame_style) {
             $map_info['update_field'] = 'product|frame_style';
             set_update_field($map_info,$product_data,$frame_style);
          }
          if ($prescription !== null) {
             $map_info['update_field'] = 'product|prescription';
             set_update_field($map_info,$product_data,$prescription);
          }
          return null;
       case 'marchonimage': return null;
       case 'marchonname':
          $model = $product_data->product_record['model']['value'];
          $product_name = $marchon->build_product_name($product_data);
          $map_info['update_field'] = 'product|name';
          set_update_field($map_info,$product_data,$product_name);
          $map_info['update_field'] = 'product|display_name';
          set_update_field($map_info,$product_data,$model);
          return null;
        case 'alwaystrue':
          $field_value = 1;
          set_update_field($map_info,$product_data,$field_value);
          return null;
    }
    return true;
}

function marchon_check_import(&$product_data)
{
    if (empty($product_data->product_record['prescription']['value']))
       $product_data->product_attributes = null;
    return true;
}

?>
