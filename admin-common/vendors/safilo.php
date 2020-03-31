<?php
/*
                 Inroads Shopping Cart - Safilo Group Vendor Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

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

5) Upload Safilo vendor module file to admin/vendors and glasses-common.php
   file to admin

6) Edit Safilo Vendor Settings and set:

Account Username
Account Password

7) Edit Safilo Vendor Settings and select brands to import

8) Edit Safilo Product Data Import and set:

Auto Update Frequency
Attribute Set for New Products

8) Run Safilo Product Data Import

*/

require_once '../engine/http.php';

global $vendor_fields;
$vendor_fields = array(array('name'=>'brands','type'=>TEXT_TYPE));

global $import_mapping;
$import_mapping = array(
   array('vendor_field'=>'Org Name','update_field'=>'images|*','convert_funct'=>'safiloimage'),
   array('vendor_field'=>'Coll Name','update_field'=>'product|shopping_brand','convert_funct'=>'safilobrand'),
   array('vendor_field'=>'Style Name','update_field'=>'product|model','convert_funct'=>'safiloname'),
   array('vendor_field'=>'Color Name','update_field'=>'product|shopping_color'),
   array('vendor_field'=>'Style Number','update_field'=>'product|model_number'),
   array('vendor_field'=>'Color Number','update_field'=>'product|color_code'),
   array('vendor_field'=>'Front Size','convert_funct'=>'sizelabel'),
   array('vendor_field'=>'Temple Size','update_field'=>'product|temple_length'),
   array('vendor_field'=>'UPC Code','update_field'=>'product|shopping_gtin'),
   array('vendor_field'=>'List Price','update_field'=>'product|cost','convert_funct'=>'setmarkupprice'),
   array('vendor_field'=>'Lens Material','update_field'=>'product|prescription','convert_funct'=>'alwaystrue'),
   array('vendor_field'=>'Lens Material Desc','update_field'=>'product|lens_material'),
   array('vendor_field'=>'Box A Measure','update_field'=>'product|lens_width','required'=>1),
   array('vendor_field'=>'Box B Measure','update_field'=>'product|lens_height'),
   array('vendor_field'=>'Box DBL Measure','update_field'=>'product|bridge_size')
);

global $brand_matches;
$brand_matches = array(
   'CARRERA HELMET' => 'Carrera',
   'CLAIBORNE' => 'Liz Claiborne',
   'LIBRARY' => 'Safilo',
   'MARCEL WANDERS' => 'Safilo',
   'PIERRE CARDIN' => 'Safilo',
   'POLAROID ANCILL' => 'Polaroid Core',
   'POLAROID KIDS' => 'Polaroid Core',
   'POLAROID PREMIU' => 'Polaroid Core',
   'POLAROID SPORT' => 'Polaroid Core',
   'SAFILO DESIGN' => 'Safilo',
   'SAFILO KIDS' => 'Safilo Safilo Kids',
   'SMITH RX' => 'Smith',
   'SMITH SUNCLOUD' => 'Smith',
   'TEAM' => 'Safilo'
);

global $display_brands;
$display_brands = array(
   'ADENSCO' => 'Adensco',
   'BANANA REPUBLIC' => 'Banana Republic',
   'BOBBI BROWN' => 'Bobbi Brown',
   'BOSS (HUB)' => 'Hugo Boss',
   'BOSS ORANGE' => 'Hugo Boss',
   'CARRERA' => 'Carrera',
   'CARRERA HELMET' => 'Carrera',
   'CHESTERFIELD' => 'Chesterfield',
   'CLAIBORNE' => 'Liz Claiborne',
   'DENIM' => 'Denim',
   'DIOR' => 'Dior',
   'DIOR HOMME' => 'Dior',
   'ELASTA' => 'Elasta',
   'EMOZIONI' => 'Emozioni',
   'FENDI' => 'Fendi',
   'FENDI MEN' => 'Fendi',
   'FOSSIL' => 'Fossil',
   'GIVENCHY' => 'Givenchy',
   'HAVAIANAS' => 'Havaianas',
   'JACK SPADE' => 'Jack Spade',
   'JIMMY CHOO' => 'Jimmy Choo',
   'JUICY COUTURE' => 'Juicy Couture',
   'KATE SPADE' => 'Kate Spade',
   'LIBRARY' => 'Library',
   'LIZ CLAIBORNE' => 'Liz Claiborne',
   'MARC JACOBS' => 'Marc Jacobs',
   'MARCEL WANDERS' => 'Marcel Wanders',
   'MAX & CO' => 'Max & Co',
   'MAX MARA' => 'Max Mara',
   'New SAFILO' => 'Safilo',
   'OXYDO' => 'Oxydo',
   'PIERRE CARDIN' => 'Pierre Cardin',
   'POLAROID ANCILL' => 'Polaroid',
   'POLAROID CORE' => 'Polaroid',
   'POLAROID KIDS' => 'Polaroid',
   'POLAROID PREMIU' => 'Polaroid',
   'POLAROID SPORT' => 'Polaroid',
   'SAFILO' => 'Safilo',
   'SAFILO DESIGN' => 'Safilo',
   'SAFILO KIDS' => 'Safilo Kids',
   'SAKS FIFTH AVE' => 'Saks Fifth Avenue',
   'SMITH' => 'Smith',
   'SMITH RX' => 'Smith',
   'SMITH SUNCLOUD' => 'Smith',
   'TEAM' => 'Team',
   'TOMMY HILFIGER' => 'Tommy Hilfiger',
);

class Safilo {

function __construct($db,$vendor_id)
{
    $this->url = 'https://mysafilo.com';
    $this->db = $db;
    $this->vendor_id = $vendor_id;
    $query = 'select * from vendors where id=?';
    $query = $this->db->prepare_query($query,$this->vendor_id);
    $this->vendor_info = $this->db->get_record($query);
    $this->cookie_header = null;
}

function Safilo($db,$vendor_id)
{
    self::__construct($db,$vendor_id);
}

function login()
{
    if ($this->cookie_header) return true;
    if (empty($this->vendor_info['username']) ||
        empty($this->vendor_info['password'])) return false;
    $url = $this->url.'/ssl/processLogin.php';
    $http = new HTTP($url);
    $post_data = 'username='.urlencode($this->vendor_info['username']) .
                 '&password='.urlencode($this->vendor_info['password']) .
                 '&jsEnabled=true';
    $http->call($post_data);
    if ($http->status != 302) {
       $error = 'Unable to login to '.$this->url.': '.$http->error;
       log_error($error);   log_vendor_activity($error);   return false;
    }
    if (! isset($http->cookies['PHPSESSID'])) {
       $error = 'Session cookie not found in '.$this->url.' response';
       log_error($error);   log_vendor_activity($error);   return false;
    }
    $this->cookie_header = 'Cookie: PHPSESSID='.$http->cookies['PHPSESSID'];
    return true;
}

function download_catalog($import_filename,$manual_flag)
{
    $url = $this->url.'/cFiles/upcFiles/SAFUPC.CSV';
    $last_modified = get_last_modified($url);
    if ($last_modified == -1) return $manual_flag;
    if (file_exists($import_filename) &&
        (filemtime($import_filename) == $last_modified)) return $manual_flag;
    $product_data = @file_get_contents($url);
    if (! $product_data) {
       $error = 'Unable to download Safilo Product Data File '.$url;
       log_error($error);   log_vendor_activity($error);
       return false;
    }
    file_put_contents($import_filename,$product_data);
    touch($import_filename,$last_modified);
    log_vendor_activity('Downloaded Safilo Product Data File from ' .
                        $url);
    return true;
}

function get_brands()
{
    if (! $this->login()) return null;
    $url = $this->url.'/?p=collections&ctt=menuBar&ctd=orderFrames';
    $http = new HTTP($url);
    $http->set_method('GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $error = 'Unable to retrieve product details from '.$this->url.': ' .
                $http->error;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $cells = explode('selectCollection',$page_data);
    unset($cells[0]);
    $brands = array();
    foreach ($cells as $cell) {
       $start_pos = strpos($cell,'\'');
       if ($start_pos === false) continue;
       $start_pos++;
       $end_pos = strpos($cell,'\'',$start_pos);
       if ($end_pos === false) continue;
       $brand = substr($cell,$start_pos,$end_pos - $start_pos);
       $brands[] = $brand;
    }
    sort($brands);
    return $brands;
}

function convert_brand($brand)
{
    global $brand_matches;

    if (isset($brand_matches[$brand])) $brand = $brand_matches[$brand];
    $brand = strtolower($brand);
    return $brand;
}

function parse_attribute($page_data,$attribute,&$pos)
{
    if ($pos === false) return null;
    $start_pos = strpos($page_data,$attribute.'="',$pos);
    if ($start_pos === false) {
       $pos = false;   return null;
    }
    $start_pos += strlen($attribute) + 2;
    $end_pos = strpos($page_data,'"',$start_pos);
    if ($end_pos === false) {
       $pos = false;   return null;
    }
    $value = substr($page_data,$start_pos,$end_pos - $start_pos);
    $pos = $end_pos;
    return $value;
}

function parse_images($page_data)
{
    $images = array();
    $pos = strpos($page_data,'class="chipPicture"');
    while ($pos !== false) {
       $color_code = $this->parse_attribute($page_data,'colorCode',$pos);
       $lens_code = $this->parse_attribute($page_data,'lensCode',$pos);
       $image = $this->parse_attribute($page_data,'img src',$pos);
       if ($pos !== false)
          $images[] = array('color_code'=>$color_code,'lens_code'=>$lens_code,
                            'image'=>$image);
    }
    return $images;
}

function parse_skus($page_data)
{
    $skus = array();
    $pos = strpos($page_data,'title="UPC:');
    while ($pos !== false) {
       $upc = $this->parse_attribute($page_data,'title',$pos);
       $color_code = $this->parse_attribute($page_data,'colorCode',$pos);
       $color_name = $this->parse_attribute($page_data,'colorName',$pos);
       if ($pos !== false) $pos = strpos($page_data,'<td ',$pos);
       if ($pos !== false) $pos = strpos($page_data,'<td>',$pos + 3);
       if ($pos !== false) $pos = strpos($page_data,'<td>',$pos + 3);
       if ($pos !== false) {
          $pos += 4;
          $end_pos = strpos($page_data,'</td>',$pos);
          if ($end_pos !== false)
             $lens_material = substr($page_data,$pos,$end_pos - $pos);
          if ($end_pos !== false)
             $skus[] = array('upc'=>substr($upc,5),'color_code'=>$color_code,
                             'color_name'=>$color_name,
                             'lens_material'=>$lens_material);
       }
       $pos = strpos($page_data,'title="UPC:',$pos);
    }
    return $skus;
}

function parse_criteria($page_data)
{
    $criteria = array();
    $pos = strpos($page_data,'criterionValue="');
    while ($pos !== false) {
       $value = $this->parse_attribute($page_data,'criterionValue',$pos);
       $name = $this->parse_attribute($page_data,'id',$pos);
       if ($pos !== false) $criteria[] = array('name'=>$name,'value'=>$value);
    }
    return $criteria;
}

function get_product_details($style_number)
{
    if (! $this->login()) return null;
    $url = $this->url.'/?p=product&styleNumber='.$style_number;
    $http = new HTTP($url);
    $http->set_method('GET');
    $http->set_headers(array($this->cookie_header));
    $page_data = $http->call();
    if (! $page_data) {
       $error = 'Unable to retrieve product details from '.$this->url.': ' .
                $http->error;
       log_error($error);   log_vendor_activity($error);   return null;
    }
    $safilo_data = array();
    $safilo_data['images'] = $this->parse_images($page_data);
    $safilo_data['skus'] = $this->parse_skus($page_data);
    $safilo_data['criteria'] = $this->parse_criteria($page_data);
    return $safilo_data;
}

function build_product_name($product_data)
{
    $model = $product_data->product_record['model']['value'];
    $color = $product_data->product_record['shopping_color']['value'];
    $width = $product_data->product_record['lens_width']['value'];
    $bridge = $product_data->product_record['bridge_size']['value'];
    $length = $product_data->product_record['temple_length']['value'];
    $product_name = $model.' '.$color.' '.$width.'-'.$bridge.'-'.$length;
    return $product_name;
}

};

function add_safilo_import($db,$vendor_id)
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

function safilo_install($db)
{
    global $vendor_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    $vendor_info = array('module'=>'safilo','name'=>'Safilo Group',
       'company'=>'Safilo USA','address1'=>'300 Lighting Way',
       'address2'=>'Suite 400','city'=>'Secaucus','state'=>'NJ',
       'zipcode'=>'07094','country'=>1,'phone'=>'1-800-631-1188',
       'email'=>'mySafilo@safilo.com','num_markups'=>0);

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
    $vendor_id = add_module_vendor($db,$vendor_info);
    if (! $vendor_id) return;
    add_safilo_import($db,$vendor_id);
}

function safilo_upgrade($db)
{
    global $vendor_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/glasses-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_vendor_fields($db,$vendor_fields)) return;
}

function safilo_vendor_fields(&$vendor_record)
{
    $vendor_record['brands'] = array('type'=>CHAR_TYPE);
}

function safilo_add_vendor_fields($dialog,$edit_type,$row,$db,$tab_name)
{
    if ($tab_name != 'settings') return;
    $current_brands = explode(',',$row['brands']);
    $safilo = new Safilo($db,$row['id']);
    $brands = $safilo->get_brands();
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

function safilo_parse_vendor_fields($db,&$vendor_record)
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

function safilo_download_catalog($db,$import)
{
    $vendor_id = $import['parent'];
    $safilo = new Safilo($db,$vendor_id);
    if (! $import['import_file']) {
       $import['import_file'] = 'import-'.$import['id'].'.csv';
       $new_import_file = true;
    }
    else $new_import_file = false;
    $import_filename = '../admin/vendors/'.$import['import_file'];
    if (! $safilo->download_catalog($import_filename,$import['manual']))
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

function safilo_load_custom_data($db,&$product_data,&$custom_data)
{
    $product_data->safilo = new Safilo($db,$product_data->vendor_id);
    $product_data->safilo_products = array();
    $query = 'select brands from vendors where id=?';
    $query = $db->prepare_query($query,$product_data->vendor_id);
    $row = $db->get_record($query);
    if ($row) {
       $brands = explode(',',$row['brands']);
       foreach ($brands as $index => $brand)
          $brands[$index] = strtolower($brand);
    }
    else $brands = array();
    $product_data->safilo_brands = $brands;
}

function safilo_update_conversions($db,&$conversions)
{
    $conversions['sizelabel'] = 'Build Size Label';
    $conversions['safilobrand'] = 'Convert Safilo Brand';
    $conversions['safiloimage'] = 'Download Safilo Image';
    $conversions['safiloname'] = 'Build Safilo Product Name';
    $conversions['alwaystrue'] = 'Always True';
}

function safilo_check_vendor_data($product_data,$row)
{
    $safilo = $product_data->safilo;
    $brand = $safilo->convert_brand($row[2]);
    if (in_array($brand,$product_data->safilo_brands)) return true;
    return false;
}

function safilo_process_image_conversion($map_info,&$product_data,
   &$image_filename,&$image_modified)
{
    if ($map_info['convert_funct'] != 'safiloimage') return false;
    $image_filename = '';   $image_modified = false;
    $safilo = $product_data->safilo;
    $style_number = $product_data->product_record['model_number']['value'];
    $style_number = str_pad($style_number,6,'0',STR_PAD_LEFT);
    if (! array_key_exists($style_number,$product_data->safilo_products)) {
       $product_data->safilo_products[$style_number] =
          $safilo->get_product_details($style_number);
       if ($product_data->safilo_products[$style_number])
          log_vendor_activity('Downloaded Safilo Product Details for Style ' .
                              $style_number);
       else {
          $error = 'Safilo Product Details for Style '.$style_number .
                   ' not found';
          log_error($error);   log_vendor_activity($error);
          return true;
       }
    }
    if (! isset($product_data->product_record['color_code']['value'])) {
       $error = 'Safilo Color Code not found for product ' .
                $safilo->build_product_name($product_data);
       log_error($error);   log_vendor_activity($error);
       return true;
    }

    if ((! empty($product_data->product_record['shopping_gtin']['value'])) &&
        isset($product_data->safilo_products[$style_number]['skus'])) {
       $upc = $product_data->product_record['shopping_gtin']['value'];
       foreach ($product_data->safilo_products[$style_number]['skus'] as
                $sku) {
          if ($sku['upc'] == $upc) {
             $product_data->product_record['shopping_color']['value'] =
                $sku['color_name'];
             if (empty($product_data->product_record['lens_material']['value']))
                $product_data->product_record['lens_material']['value'] =
                   $sku['lens_material'];
          }
       }
    }
    else {
       $error = 'Safilo SKU Data or UPC not found for product ' .
                $safilo->build_product_name($product_data);
       log_error($error);   log_vendor_activity($error);
    }

    if (isset($product_data->safilo_products[$style_number]['criteria'])) {
       $safilo_criteria = array('frameType'=>'frame_style',
          'frontMaterial'=>'front_material',
          'templeMaterial'=>'temple_material',
          'frameMaterial'=>'frame_material','rx'=>'prescription',
          'eyeWire'=>'eye_wire','eyeShape'=>'shape',
          'thickness'=>'thickness','bridgeShape'=>'bridge_shape',
          'rimType'=>'rim_type','hinge'=>'flex',
          'genderGroup'=>'shopping_gender');
       foreach ($product_data->safilo_products[$style_number]['criteria'] as
                $criteria) {
          if (! isset($safilo_criteria[$criteria['name']])) {
             $error = 'Unknown Safilo Criteria '.$criteria['name'] .
                      ' for Style '.$style_number;
             log_error($error);   log_vendor_activity($error);   break;
          }
          $field_name = $safilo_criteria[$criteria['name']];
          $value = $criteria['value'];
          switch ($field_name) {
             case 'frame_style':
                require_once '../admin/glasses-common.php';
                $value = convert_frame_style($value);   break;
             case 'flex':
                if ($value == 'Flex') $value = 1;
                else $value = 0;
                break;
             case 'shopping_gender':
                if (strpos($value,'Adult') !== false) $age = 'adult';
                else $age = 'kids';
                $product_data->product_record['shopping_age']['value'] = $age;
                if (strpos($value,'Female') !== false) $value = 'female';
                else if (strpos($value,'Male') !== false) $value = 'male';
                else $value = 'unisex';
                break;
             case 'shape':
                require_once '../admin/glasses-common.php';
                $value = convert_shape($value);   break;
          }
          $product_data->product_record[$field_name]['value'] = $value;
       }
    }
    else {
       $error = 'Safilo Criteria Data not found for product ' .
                $safilo->build_product_name($product_data);
       log_error($error);   log_vendor_activity($error);
    }

    $color_code = $product_data->product_record['color_code']['value'];
    $lens_code = trim($product_data->row[9]);
    if (! $lens_code) $lens_code = '00';
    if (! isset($product_data->safilo_products[$style_number]['images'])) {
       $error = 'Safilo Image Data not found for product ' .
                $product_data->product_record['name']['value'];
       log_error($error);   log_vendor_activity($error);
       return true;
    }
    $image = null;
    foreach ($product_data->safilo_products[$style_number]['images'] as
             $image_info) {
       if (($image_info['color_code'] == $color_code) &&
           ($image_info['lens_code'] == $lens_code)) {
          $image = $image_info['image'];   break;
       }
    }
    if (! $image) {
       $error = 'Safilo Image not found for product ' .
                $safilo->build_product_name($product_data).', Style: ' .
                $style_number.', Color: '.$color_code.', Lens: ' .
                $lens_code;
       log_vendor_activity($error);   return true;
    }

    $image_options = $product_data->import['image_options'];
    $image_url = $safilo->url.'/'.$image;
    $image_filename = basename($image);
    $local_filename = '../images/original/'.$image_filename;
    if (($image_options & DOWNLOAD_NEW_IMAGES_ONLY) &&
        file_exists($local_filename))
       $last_modified = filemtime($local_filename);
    else $last_modified = get_last_modified($image_url,5);
    if ($last_modified == -1) {
       $error = 'Safilo image '.$image_url.' not found';
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    if (file_exists($local_filename) &&
        (filemtime($local_filename) == $last_modified)) return true;
    $image_data = @file_get_contents($image_url);
    if (! $image_data) {
       $error = 'Unable to download Safilo image '.$image_url;
       log_error($error);   log_vendor_activity($error);
       $image_filename = '';   return true;
    }
    file_put_contents($local_filename,$image_data);
    touch($local_filename,$last_modified);
    $image_modified = true;
    log_vendor_activity('Downloaded Safilo Product Image ' .
                        $image_filename);
    return true;
}

function safilo_process_conversion(&$map_info,&$product_data)
{
    global $display_brands;

    $safilo = $product_data->safilo;
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
       case 'safilobrand':
          $field_value = get_update_field($map_info,$product_data);
          if ($field_value == 'K.SPADE READERS') $frame_style = 'Reader';
          else if ($field_value == 'POLAROID Reader') $frame_style = 'Reader';
          else $frame_style = null;
          if (isset($display_brands[$field_value]))
             $field_value = $display_brands[$field_value];
          set_update_field($map_info,$product_data,$field_value);
          if ($frame_style) {
             $map_info['update_field'] = 'product|frame_style';
             set_update_field($map_info,$product_data,$frame_style);
          }
          return null;
       case 'safiloimage': return null;
       case 'safiloname':
          $model = $product_data->product_record['model']['value'];
          $product_name = $safilo->build_product_name($product_data);
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

function safilo_check_import(&$product_data)
{
    if (empty($product_data->product_record['prescription']['value']))
       $product_data->product_attributes = null;
    return true;
}

?>
