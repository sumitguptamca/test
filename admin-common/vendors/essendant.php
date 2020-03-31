<?php
/*
                 Inroads Shopping Cart - Essendant Vendor Module

                         Written 2019 by Randall Severy
                          Copyright 2019 Inroads, LLC

Configuration Steps:

1) Add to admin/custom-config.php:

require_once 'office-common.php';

2) Upload Essendant vendor module file to admin/vendors and office-common.php
   file to admin

3) Edit Essendant Vendor Settings and set:

Account Username
Account Password

4) Edit ECDB Product Data Import and set FTP User and Password for ECDB SFTP Access

5) Create an FTP account with the Username of "essendant@domain.com", password of
   "essendantftp" and Directory of "/essendant"

6) Generate and Schedule ICAPS Pricing Data Export:
   a) Go to https://solutionscentral.ussco.com
   b) Navigate to Applications > ICAPS2 > Customer or Supplier and log in
   c) Click on "New Report" button
   d) Enter a report name of "AxiumPro Pricing File"
   e) Select the appropriate account
   f) Go to "Pricing" tab and select the next day as the Effective Date and
      "Best price across selected plans" as the Pricing Option
   g) Go to "Fields" tab and select the following fields:
      Item Number, Item Stock Number-Butted, List Price, Cost Column 1 Price,
      Consumer 1 Price, Facility Total OnHand Qty, Price Plan, UPC Retail
   h) Go to "Schedule" tab and select "Daily" and the next day as the Next Run Date,
      enter a filename of "EssendantPrices", No Zip Compression, and File Type of .XLS
   i) Select "Deliver to an FTP/SFTP" and add the FTP information created in step #5
   j) Click "Save"
   k) Temporarily turn off Schedule and Click "Run Now"

7) Run Essendant Imports

*/

global $vendor_import_fields;
$vendor_import_fields = array(array('name'=>'related_map','type'=>CHAR_TYPE,
                                    'size'=>25));
global $essendant_relationships;
$essendant_relationships = array('Companion For/Companion','Product Family',
   'Accessory For/Accessories','Downsell/Upsell',
   'Consumable Supply For/Consumable Supply');

function essendant_install($db)
{
    global $vendor_import_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_import_fields($db,$vendor_import_fields)) return;
}

function essendant_upgrade($db)
{
    global $vendor_import_fields,$vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
    if (! add_import_fields($db,$vendor_import_fields)) return;
}

function essendant_import_fields(&$vendor_import_record)
{
    $vendor_import_record['related_map'] = array('type'=>CHAR_TYPE);
}

function parse_essendant_related_map($row)
{
    $related_map = get_row_value($row,'related_map');
    if ($related_map) {
       $related_info = explode(',',$related_map);
       $related_map = array();
       foreach ($related_info as $related_parts) {
          $related_parts = explode('=',$related_parts);
          if (count($related_parts) == 2)
             $related_map[$related_parts[0]] = $related_parts[1];
       }
    }
    else $related_map = array();
    return $related_map;
}

function essendant_add_import_fields($db,$dialog,$edit_type,$row,$section)
{
    global $related_types,$essendant_relationships;

    if (empty($related_types)) return;
    if ($section != 'group_options') return;
    if (empty($row['ftp_filename'])) return;
    if (strpos($row['ftp_filename'],'SyncProductRelationship') === false)
       return;

    $related_map = parse_essendant_related_map($row);

    $dialog->add_section_row('Relationship Mapping');
    $dialog->write('<tr><td colspan="2" align="center">');
    $dialog->start_field_table(null,'mappingtable',1);
    $dialog->write('<tr>');
    $dialog->write('<th>Vendor Relationship</th>');
    $dialog->write("<th>Related Type</th>\n");
    $dialog->write("</tr>\n");

    foreach ($essendant_relationships as $rel_index => $rel_label) {
       if (isset($related_map[$rel_index]))
          $rel_value = $related_map[$rel_index];
       else $rel_value = -1;
       $dialog->write('<tr><td style="padding:0px 10px 0px 5px;" nowrap>');
       $dialog->write($rel_label."</td>\n");
       $dialog->write('<td align="center">');
       $dialog->start_choicelist('related_map_'.$rel_index);
       $dialog->add_list_item('','',$rel_value == -1);
       foreach ($related_types as $type_index => $type_label)
          $dialog->add_list_item($type_index,$type_label,
                                 ($type_index == $rel_value));
       $dialog->end_choicelist();
       $dialog->write("</td></tr>\n");
    }
    $dialog->end_table();
    $dialog->end_row();
}

function essendant_parse_import_fields($db,&$import_record)
{
    $related_map = '';
    for ($index = 0;  $index < 5;  $index++) {
       $map_value = get_form_field('related_map_'.$index);
       if ($map_value !== '') {
          if ($related_map) $related_map .= ',';
          $related_map .= $index.'='.$map_value;
       }
    }
    $import_record['related_map']['value'] = $related_map;
}

function essendant_update_db_fields($db,&$db_fields)
{
    if (defined('FEATURES_DATA_TYPE'))
       $db_fields['product_data|'.FEATURES_DATA_TYPE] = 'Features';
    if (defined('SPECIFICATIONS_DATA_TYPE'))
       $db_fields['product_data|'.SPECIFICATIONS_DATA_TYPE] = 'Specifications';
}

function parse_essendant_item_master($item,$ns)
{
    $data = array();   $images = '';

    $header = $item->children($ns['us'])->ItemMasterHeader;
    $oa_nodes = $header->children($ns['oa']);
    $us_nodes = $header->children($ns['us']);
    $header_items = $oa_nodes->ItemID;
    foreach ($header_items as $header_item) {
       $role = (string) $header_item->attributes()['agencyRole'];
       $value = (string) $header_item->children($ns['oa'])->ID;
       $data[$role] = $value;
    }
    $data['Item Number'] = $data['Prefix_Number'].$data['Stock_Number_Butted'];
    $node = $oa_nodes->ManufacturerItemID->children($ns['oa'])->ID;
    $attributes = $node->attributes();
    foreach ($attributes as $name => $value) $data[$name] = (string) $value;

    $classifications = $us_nodes->Classification;
    foreach ($classifications as $class) {
       $type = (string) $class->attributes()['type'];
       $code_sets = $class->children($ns['us'])->Codes;
       foreach ($code_sets as $code_set) {
          $codes = $code_set->children($ns['oa'])->Code;
          foreach ($codes as $code) {
             $attributes = $code->attributes();
             if (! empty($attributes['name']))
                $name = (string) $attributes['name'];
             else if (! empty($attributes['listName']))
                $name = (string) $attributes['listName'];
             else if (! empty($attributes['type']))
                $name = (string) $attributes['type'];
             else $name = $type;
             $data[$name] = (string) $code;
          }
       }
       if ($type == 'SKU_Group') {
          $features = '';
          $notes = $class->children($ns['oa'])->Note;
          foreach ($notes as $note) {
             $name = (string) $note->attributes()['status'];
             $value = (string) $note;
             if (substr($name,0,14) == 'Selling_Point_') {
                if ($features) $features .= '|';
                $features .= $value;
             }
             else $data[$name] = $value;
          }
          $data['Features'] = $features;
          $elements = $class->children($ns['us']);
          $sku_images = '';
          $image = (string) $elements->SkuGroupImage;
          if ($image && ($image != 'NOA.JPG')) {
             if ($sku_images) $sku_images .= '|';
             $sku_images .= $image;
          }
          $alt_images = (string) $elements->SkuGroupAlternateImage;
          $alt_images = explode(';',$alt_images);
          foreach ($alt_images as $image) {
             $image = trim($image);
             if ($image && ($image != 'NOA.JPG')) {
                if ($sku_images) $sku_images .= '|';
                $sku_images .= $image;
             }
          }
          $data['SkuImages'] = $sku_images;
          $image = (string) $elements->SkuGroupIcons;
          if ($image && ($image != 'NOA.JPG'))
             $data['SkuGroupIcons'] = $image;
       }
    }

    $specs = '';
    $specifications = $oa_nodes->Specification;
    foreach ($specifications as $spec_set) {
       $properties = $spec_set->children($ns['oa'])->Property;
       foreach ($properties as $property) {
          $namevalue = $property->children($ns['oa'])->NameValue;
          if ($namevalue) {
             $name = (string) $namevalue->attributes()['name'];
             $value = (string) $namevalue;
             if ($specs) $specs .= '|';
             $specs .= $name.'^'.$value;
          }
          $descriptions = $property->children($ns['oa'])->Description;
          if ($descriptions) foreach ($descriptions as $description) {
             $name = (string) $description->attributes()['type'];
             $value = (string) $description;
             $data[$name] = $value;
          }
       }
    }
    $data['Specifications'] = $specs;

    $packaging = $us_nodes->Packaging->children($ns['oa'])->ID;
    $data['Packaging'] = (string) $packaging;
    $status = $oa_nodes->ItemStatus->children($ns['oa'])->Code;
    $data['ItemStatus'] = (string) $status;
    $image = (string) $oa_nodes->DrawingAttachment->children($ns['oa'])->
                      FileName;
    if ($image && ($image != 'NOA.JPG')) {
       if ($images) $images .= '|';
       $images .= $image;
    }
    $attachment = (string) $oa_nodes->Attachment->children($ns['oa'])->FileName;
    $attachments = explode(';',$attachment);
    foreach ($attachments as $attachment) {
       $image = trim($attachment);
       if ($image && ($image != 'NOA.JPG')) {
          if ($images) $images .= '|';
          $images .= $image;
       }
    }
    $code_sets = $us_nodes->FreightClassification->children($ns['oa'])->Codes;
    foreach ($code_sets as $code_set) {
       $codes = $code_set->children($ns['oa'])->Code;
       foreach ($codes as $code) {
          $attributes = $code->attributes();
          $name = (string) $attributes['name'];
          $data[$name] = (string) $code;
       }
    }
    $data['Keywords'] = (string) $us_nodes->Keywords;
    $data['BrandId'] = (string) $us_nodes->BrandId;

    $location = $item->children($ns['us'])->ItemLocation;
    $us_nodes = $location->children($ns['us']);
    $classifications = $us_nodes->Classification;
    foreach ($classifications as $class) {
       $type = (string) $class->attributes()['type'];
       $code_sets = $class->children($ns['us'])->Codes;
       foreach ($code_sets as $code_set) {
          $codes = $code_set->children($ns['oa'])->Code;
          foreach ($codes as $code) {
             $attributes = $code->attributes();
             if (! empty($attributes['name']))
                $name = (string) $attributes['name'];
             else if (! empty($attributes['type']))
                $name = (string) $attributes['type'];
             else $name = $type;
             $data[$name] = (string) $code;
          }
       }
    }

    $packaging = $us_nodes->Packaging;
    $type = (string) $packaging->children($ns['oa'])->ID;
    $dimensions = $packaging->children($ns['us'])->Dimensions;
    $oa_children = $dimensions->children($ns['oa']);
    foreach ($oa_children as $child)
       $data[$type.$child->getName()] = (string) $child;
    $us_children = $dimensions->children($ns['us']);
    foreach ($us_children as $child)
       $data[$type.$child->getName()] = (string) $child;
    $qty = $packaging->children($ns['oa'])->PerPackageQuantity;
    $data[$type.'PerPackageQuantity'] = (string) $qty;

    $packaging = $us_nodes->UnitPackaging;
    $type = (string) $packaging->children($ns['oa'])->ID;
    $dimensions = $packaging->children($ns['us'])->Dimensions;
    $oa_children = $dimensions->children($ns['oa']);
    foreach ($oa_children as $child)
       $data[$type.$child->getName()] = (string) $child;
    $us_children = $dimensions->children($ns['us']);
    foreach ($us_children as $child)
       $data[$type.$child->getName()] = (string) $child;
    $qty = $packaging->children($ns['oa'])->PerPackageQuantity;
    $data[$type.'PerPackageQuantity'] = (string) $qty;

    $global_item = $item->children($ns['us'])->GlobalItem;
    $children = $global_item->children($ns['us']);
    foreach ($children as $child) {
       $name = $child->getName();
       if ($name == 'ItemDimensions') {
          $oa_children = $child->children($ns['oa']);
          foreach ($oa_children as $oa_child)
             $data['Item'.$oa_child->getName()] = (string) $oa_child;
          $us_children = $child->children($ns['us']);
          foreach ($us_children as $us_child)
             $data['Item'.$us_child->getName()] = (string) $us_child;
       }
       else $data[$name] = (string) $child;
    }

    $item_list = $item->children($ns['us'])->ItemList;
    $children = $item_list->children($ns['us']);
    foreach ($children as $child)
       $data[$child->getName()] = (string) $child;

    $warranty_info = $item->children($ns['us'])->WarrantyInfo;
    $children = $warranty_info->children($ns['us']);
    foreach ($children as $child)
       $data[$child->getName()] = (string) $child;

    if (isset($item->children($ns['us'])->LineDrawing)) {
       $line_drawing = $item->children($ns['us'])->LineDrawing;
       $image = $line_drawing->children($ns['us'])->FileName;
       if ($image && ($image != 'NOA.JPG')) {
          if ($images) $images .= '|';
          $images .= (string) $image;
       }
    }

    $data['Images'] = $images;

    return $data;
}

function parse_essendant_product_relationships($product,$ns,$map,&$data)
{
    $row = array();
    $nodes = $product->children($ns['us']);
    $row['MPN'] = (string) $nodes->MPN;
    $row['PrefixNumber'] = (string) $nodes->PrefixNumber;
    $row['StockNumberButted'] = (string) $nodes->StockNumberButted;
    $row['Item Number'] = $row['PrefixNumber'].$row['StockNumberButted'];
    $relationships = $nodes->Relationship;
    foreach ($relationships as $relationship) {
       $nodes = $relationship->children($ns['us']);
       $name = trim((string) $nodes->Name);
       if (isset($map[$name])) $row['Type'] = $map[$name];
       $members = $nodes->RelationshipMember;
       foreach ($members as $member) {
          $nodes = $member->children($ns['us']);
          $row['Position'] = (string) $nodes->Position;
          $row['RelatedMPN'] = (string) $nodes->MPN;
          $row['RelatedPrefix'] = (string) $nodes->PrefixNumber;
          $row['RelatedStock'] = (string) $nodes->StockNumberButted;
          $row['Related Item Number'] = $row['RelatedPrefix'] .
                                        $row['RelatedStock'];
          $data[] = $row;
       }
    }
}

function format_csv_data($buffer)
{
    $buffer = str_replace('"','""',$buffer);
    $buffer = str_replace("\r",' ',$buffer);
    $buffer = str_replace("\n",' ',$buffer);
    return '"'.$buffer.'"';
}

function essendant_update_catalog_filename(&$catalog_file,&$extension,
                                           $import_file,$files)
{
    $extension = 'csv';
    if (empty($import_file)) return;
    $local_filename = '../admin/vendors/'.$import_file;
    if (file_exists($local_filename) &&
        ($files[$catalog_file]['mtime'] == filemtime($local_filename)))
       return 'skip';
}

function essendant_convert_catalog_file(&$import_file,$local_filename,
                                        &$import,$ftp)
{
    global $essendant_relationships;

    $mtime = filemtime($local_filename);
    libxml_use_internal_errors();
    $xml = simplexml_load_file('compress.zlib://'.$local_filename);
    if (! $xml) {
       $xml_errors = libxml_get_errors();
       $error = 'Unable to parse '.$local_filename.': ' .
                str_replace("\n",' ',print_r($xml_errors,true));
       log_error($error);   log_vendor_activity($error);   return;
    }
    $ns = $xml->getNamespaces(true);
    $header = array();   $data = array();
    if (strpos($import['ftp_filename'],'SyncItemMaster') !== false) {
       $items = $xml->children($ns['us'])->DataArea->children($ns['us'])->
                ItemMaster;
       if (empty($items)) {
          $error = $local_filename.' is missing ItemMasters';
          log_error($error);   log_vendor_activity($error);   return;
       }
       foreach ($items as $item) {
          $row = parse_essendant_item_master($item,$ns);
          foreach ($row as $name => $value) {
             if (! isset($header[$name])) $header[$name] = true;
          }
          $data[] = $row;
       }
    }
    else if (strpos($import['ftp_filename'],'SyncProductRelationship')
             !== false) {
       $products = $xml->children($ns['us'])->DataArea->children($ns['us'])->
                   ProductRelationship;
       if (empty($products)) {
          $error = $local_filename.' is missing ProductRelationships';
          log_error($error);   log_vendor_activity($error);   return;
       }
       $columns = array('MPN','PrefixNumber','StockNumberButted','Item Number',
          'Type','Position','RelatedMPN','RelatedPrefix','RelatedStock',
          'Related Item Number');
       foreach ($columns as $column) $header[$column] = true;
       $related_map = parse_essendant_related_map($import);
       $map = array();
       foreach ($essendant_relationships as $index => $label) {
          if (isset($related_map[$index]))
             $map[$label] = $related_map[$index];
       }
       $data = array();
       foreach ($products as $product)
          parse_essendant_product_relationships($product,$ns,$map,$data);
    }
    else {
       $error = 'Unknown FTP Filename '.$import['ftp_filename'];
       log_error($error);   log_vendor_activity($error);   return;
    }

    unlink($local_filename);
    $output_file = fopen($local_filename,'w');
    $line = '';
    foreach ($header as $column_name => $flag) {
       if ($line) $line .= ',';
       $line .= format_csv_data($column_name);
    }
    fwrite($output_file,$line."\n");
    foreach ($data as $data_row) {
       $line = '';
       foreach ($header as $field => $flag) {
          if ($line) $line .= ',';
          if (! isset($data_row[$field])) $line .= '""';
          else $line .= format_csv_data(trim($data_row[$field]));
       }
       fwrite($output_file,$line."\n");
    }
    fclose($output_file);
    touch($local_filename,$mtime);
}

function essendant_download_catalog($db,&$import)
{
    if ($import['import_type'] != PRICES_IMPORT_TYPE) {
       process_import_error('Essendant Download Get Data is only available ' .
                            'for Prices Import Type');
       return false;
    }
    $import_id = $import['id'];   $import_file = 'import-'.$import_id.'.xls';
    $import_filename = '../admin/vendors/'.$import_file;

    $ftp_dirname = '../../essendant/';
    $ftp_dir = @opendir($ftp_dirname);
    if (! $ftp_dir) {
       process_import_error('Unable to access Essendant FTP Directory');
       return false;
    }
    $latest_file = null;   $latest_mtime = -1;
    while (($filename = readdir($ftp_dir)) !== false) {
       if ($filename[0] == '.') continue;
       $mtime = filemtime($ftp_dirname.$filename);
       if ($mtime > $latest_mtime) {
          $latest_file = $filename;   $latest_mtime = $mtime;
       }
    }
    if (! $latest_file) {
       if (file_exists($import_filename)) return $import['manual'];
       process_import_error('No Import File found in Essendant FTP Directory');
       return false;
    }
    if (! rename($ftp_dirname.$latest_file,$import_filename)) {
       process_import_error('Unable to rename '.$ftp_dirname.$latest_file .
                            ' to '.$import_filename);
       return false;
    }
    log_vendor_activity('Downloaded New Catalog File '.$latest_file.' to ' .
                        $import_file);
    rewinddir($ftp_dir);
    while (($filename = readdir($ftp_dir)) !== false) {
       if ($filename[0] == '.') continue;
       unlink($ftp_dirname.$filename);
    }
    closedir($ftp_dir);
    if (! update_import_file($db,$import_file,$import)) return false;
    return true;
}

function essendant_display_custom_match($dialog,$match_existing)
{
    require_once '../admin/office-common.php';
    display_vendor_number_match($dialog,$match_existing);
}

function essendant_load_custom_data($db,&$product_data,&$custom_data)
{
    require_once '../admin/office-common.php';
    if (! load_vendor_numbers($product_data)) return;
}

function essendant_find_matching_product(&$part_number,$mpn,$upc,
   $product_data,$addl_match,&$product_id,&$inv_id,&$match_name,&$match_value)
{
    require_once '../admin/office-common.php';
    find_matching_vendor_number($part_number,$product_data,$addl_match,
       $product_id,$inv_id,$match_name,$match_value);
}

?>
