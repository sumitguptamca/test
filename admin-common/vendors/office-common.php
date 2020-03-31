<?php
/*
            Inroads Shopping Cart - Office Supplies Product Data and Functions

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC

*/

$product_fields['vendor_number'] = array('datatype' => CHAR_TYPE,
                                         'fieldtype' => EDIT_FIELD,
                                         'fieldwidth' => 50,
                                         'prompt' => 'Vendor Number:');
$product_fields['sku_type'] = array('datatype' => CHAR_TYPE);
$product_fields['catalog_sku'] = array('datatype' => CHAR_TYPE);
$product_fields['vendor_product_type'] = array('datatype' => CHAR_TYPE);
$product_fields['product_line'] = array('datatype' => CHAR_TYPE);
$product_fields['product_series'] = array('datatype' => CHAR_TYPE);
$product_fields['unspsc'] = array('datatype' => CHAR_TYPE);
$product_fields['manufacturer'] = array('datatype' => CHAR_TYPE);
$product_fields['origin_country'] = array('datatype' => CHAR_TYPE);
$product_fields['recycled'] = array('datatype' => CHAR_TYPE);
$product_fields['recycled_pcw'] = array('datatype' => CHAR_TYPE);
$product_fields['recycled_total'] = array('datatype' => CHAR_TYPE);
$product_fields['assembly_required'] = array('datatype' => CHAR_TYPE);
$product_fields['product_specs'] = array('datatype' => CHAR_TYPE);
$product_fields['oem_numbers'] = array('datatype' => CHAR_TYPE);
$product_fields['type_or_yields'] = array('datatype' => CHAR_TYPE);
$product_fields['also_fits'] = array('datatype' => CHAR_TYPE);
$product_fields['brand_logo'] = array('datatype' => CHAR_TYPE);
$product_fields['overview'] = array('datatype' => CHAR_TYPE);
$product_fields['units'] = array('datatype' => CHAR_TYPE);
$product_fields['lead_time'] = array('datatype' => CHAR_TYPE);

$vendor_product_fields = array(
   array('name'=>'vendor_number','label'=>'Vendor Number','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'sku_type','label'=>'SKU Type','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'catalog_sku','label'=>'Catalog SKU','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'vendor_product_type','label'=>'Product Type','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'product_line','label'=>'Product Line','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'product_series','label'=>'Product Series','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'unspsc','label'=>'UNSPSC Code','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'manufacturer','label'=>'Manufacturer','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'origin_country','label'=>'Country of Origin','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'recycled','label'=>'Recycled','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'recycled_pcw','label'=>'Recycled Post Consumer Waste','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'recycled_total','label'=>'Recycled Content','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'assembly_required','label'=>'Assembly Required','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'brand_logo','label'=>'Brand Logo','type'=>CHAR_TYPE,'size'=>255),
   array('name'=>'product_specs','label'=>'SKU Type','type'=>TEXT_TYPE),
   array('name'=>'oem_numbers','label'=>'OEM Numbers','type'=>TEXT_TYPE),
   array('name'=>'type_or_yields','label'=>'Battery Type or Page Yields','type'=>TEXT_TYPE),
   array('name'=>'also_fits','label'=>'Also Fits','type'=>TEXT_TYPE),
   array('name'=>'overview','label'=>'Overview','type'=>TEXT_TYPE),
   array('name'=>'units','label'=>'Units','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'lead_time','label'=>'Lead Time','type'=>CHAR_TYPE,'size'=>80)
);

function add_office_product_dialog_styles($styles)
{
    $styles .= '      #specs_table .innerprompt { ' .
               "display: inline-block; min-width: 100px; }\n";
    return $styles;
}

function display_office_specs_fields($dialog,$row)
{
    $dialog->start_row('SKU Type:');
    $dialog->add_input_field('sku_type',$row,20);
    $dialog->add_inner_prompt('Catalog SKU:');
    $dialog->add_input_field('catalog_sku',$row,20);
    $dialog->add_inner_prompt('Units:');
    $dialog->add_input_field('units',$row,20);
    $dialog->end_row();

    $dialog->start_row('Product Type:');
    $dialog->add_input_field('vendor_product_type',$row,20);
    $dialog->add_inner_prompt('Product Line:');
    $dialog->add_input_field('product_line',$row,20);
    $dialog->add_inner_prompt('Product Series:');
    $dialog->add_input_field('product_series',$row,20);
    $dialog->end_row();

    $dialog->start_row('UNSPSC Code:');
    $dialog->add_input_field('unspsc',$row,20);
    $dialog->add_inner_prompt('Manufacturer:');
    $dialog->add_input_field('manufacturer',$row,20);
    $dialog->add_inner_prompt('Country of Origin:');
    $dialog->add_input_field('origin_country',$row,20);
    $dialog->end_row();
    $dialog->start_row('Recycled:');
    $dialog->add_input_field('recycled',$row,20);
    $dialog->add_inner_prompt('Recycled PCW:');
    $dialog->add_input_field('recycled_pcw',$row,20);
    $dialog->add_inner_prompt('Recycled Content:');
    $dialog->add_input_field('recycled_total',$row,20);
    $dialog->end_row();

    $dialog->start_row('Assembly Required:');
    $dialog->add_input_field('assembly_required',$row,20);
    $dialog->add_inner_prompt('Brand Logo:');
    $dialog->add_input_field('brand_logo',$row,65);
    $dialog->end_row();

    $dialog->start_row('Lead Time:');
    $dialog->add_input_field('lead_time',$row,20);
    $dialog->end_row();

    $dialog->write("      <tr><td colspan=\"2\" class=\"fieldprompt\" " .
                   "style=\"text-align: left;\">Product Specs:<br>\n");
    $dialog->add_htmleditor_popup_field('product_specs',$row,
       'Product Specs',900,200,null,null,null,false);
    $dialog->end_row();
    $dialog->write("      <tr><td colspan=\"2\" class=\"fieldprompt\" " .
                   "style=\"text-align: left;\">Overview:<br>\n");
    $dialog->add_htmleditor_popup_field('overview',$row,
       'Overview',900,50,null,null,null,false);
    $dialog->end_row();
    $dialog->write("      <tr><td colspan=\"2\" class=\"fieldprompt\" " .
                   "style=\"text-align: left;\">OEM Numbers:<br>\n");
    $dialog->add_htmleditor_popup_field('oem_numbers',$row,
       'OEM Numbers',900,50,null,null,null,false);
    $dialog->end_row();
    $dialog->write("      <tr><td colspan=\"2\" class=\"fieldprompt\" " .
                   "style=\"text-align: left;\">Battery Type or Page Yields:" .
                   "<br>\n");
    $dialog->add_htmleditor_popup_field('type_or_yields',$row,
       'Battery Type or Page Yields',900,50,null,null,null,false);
    $dialog->end_row();
    $dialog->write("      <tr><td colspan=\"2\" class=\"fieldprompt\" " .
                   "style=\"text-align: left;\">Also Fits:<br>\n");
    $dialog->add_htmleditor_popup_field('also_fits',$row,
       'Also Fits',900,50,null,null,null,false);
    $dialog->end_row();


}

function display_vendor_number_match($dialog,$match_existing)
{
    $dialog->add_radio_field('match_existing','-1','Vendor Number',
                             $match_existing == -1);
}

function load_vendor_numbers(&$product_data)
{
    if ($product_data->import['match_existing'] != -1) return false;

    $index = -1;   $map = $product_data->map;
    foreach ($map as $index => $map_info) {
       if ($map_info['update_field'] == 'product|vendor_number') {
          $index = $index;   break;
       }
    }
    if ($index == -1) {
       process_import_error('Vendor Number must be mapped');   $db->close();
       exit;
    }
    $product_data->vendor_number_index = $index;
    $inventory_ids = extract_inventory_ids($product_data->inventory);
    $product_data->inventory_ids = $inventory_ids;
    $vendor_numbers = array();
    foreach ($product_data->products as $product) {
       if (! $product['vendor_number']) continue;
       $vendor_numbers[$product['vendor_number']] = $product['id'];
    }
    $product_data->vendor_numbers = $vendor_numbers;
    $product_data->product_ids = $vendor_numbers;
    return true;
}

function find_matching_vendor_number(&$part_number,$product_data,$addl_match,
   &$product_id,&$inv_id,&$match_name,&$match_value)
{
    if ($product_data->import['match_existing'] != -1) return;

    $vendor_number =
       trim($product_data->row[$product_data->vendor_number_index]);
    $match_name = 'Vendor Number';   $match_value = $vendor_number;
    if (! $vendor_number) {
       if (DEBUG_LOGGING) log_import('No Vendor Number found in Product Match');
       return;
    }

    if (isset($product_data->vendor_numbers[$vendor_number])) {
       $vendor_product_id = $product_data->vendor_numbers[$vendor_number];
       if ($product_data->match_values === null)
          $product_id = $vendor_product_id;
       else if ($product_data->addl_match_table == 'product') {
          if (isset($product_data->match_values[$vendor_number]) &&
              ($product_data->match_values[$vendor_number] == $addl_match))
             $product_id = $vendor_product_id;
       }
       else if (isset($product_data->inventory_ids[$vendor_product_id])) {
          $parent_inv_id = $product_data->inventory_ids[$vendor_product_id];
          $inv_record = $product_data->inventory[$parent_inv_id];
          $compare_id = $inv_record['id'];
          if (isset($product_data->match_values[$compare_id]) &&
              ($product_data->match_values[$compare_id] ==
               $inv_record['index'])) {
             $product_id = $vendor_product_id;   $inv_id = $compare_id;
          }
       }
    }
    if ($product_id) {
       if ((! $inv_id) && isset($product_data->inventory_ids[$product_id]))
          $inv_id = $product_data->inventory_ids[$product_id];
       if (isset($product_data->inventory[$inv_id]['index']))
          $part_number = $product_data->inventory[$inv_id]['index'];
       else $part_number = null;
    }
}

?>
