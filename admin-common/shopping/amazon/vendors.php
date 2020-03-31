<?php
/*
                 Inroads Shopping Cart - Amazon Vendor Functions

                      Written 2018-2019 by Randall Severy
                       Copyright 2018-2019 Inroads, LLC

*/

require_once 'amazon-common.php';

function add_amazon_import_fields($db,&$dialog,$edit_type,$row)
{
    global $amazon_category_types;

    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->start_row('Amazon','middle','fieldprompt shopping_section');
    add_vendor_shopping_flag($dialog,AMAZON_FLAG,$shopping_flags);
    $dialog->end_row();
    $amazon_type = get_row_value($row,'amazon_type');
    $dialog->start_row('Category Type:','middle');
    $dialog->start_choicelist('amazon_type');
    $dialog->add_list_item('','',! $amazon_type);
    $category_found = false;
    foreach ($amazon_category_types as $category_type => $category_label) {
       if ($category_type == $amazon_type) {
          $selected = true;   $category_found = true;
       }
       else $selected = false;
       $dialog->add_list_item($category_type,$category_label,$selected);
    }
    if ($amazon_type && (! $category_found))
       $dialog->add_list_item($amazon_type,$amazon_type,true);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Item Type:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "amazon_item_type\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'amazon_item_type'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Item Type\" class=\"select_shopping_button\" " .
                   "onClick=\"select_amazon_shopping_field();\">\n");
    $dialog->end_row();
}

function get_amazon_flags(&$product_data)
{
    if (isset($product_data->amazon_flags)) return $product_data->amazon_flags;
    $query = 'select config_value from cart_config where ' .
             'config_name="amazon_flags"';
    $row = $product_data->db->get_record($query);
    if (empty($row['config_value'])) $product_data->amazon_flags = 0;
    else $product_data->amazon_flags = intval($row['config_value']);
    return $product_data->amazon_flags;
}

function get_vendor_amazon(&$product_data)
{
    if (! empty($product_data->amazon)) {
       $product_data->amazon->db = $product_data->db;
       return $product_data->amazon;
    }

    require_once 'amazon.php';
    require_once '../engine/http.php';
    $amazon = new Amazon($product_data->db);
    $amazon->debug = true;
    $product_data->amazon = $amazon;
    return $amazon;
}

function find_amazon_matching_vendor_product(&$part_number,$mpn,$upc,
   $product_data,$addl_match,&$product_id,&$inv_id)
{
    if ($product_id) return;
    $amazon_flags = get_amazon_flags($product_data);
    if (! ($amazon_flags & MATCH_IMPORT_BY_ASIN)) return;
    $match_existing = $product_data->import['match_existing'];
    if ($match_existing == MATCH_BY_MPN) return;

    $amazon = get_vendor_amazon($product_data);
    $match_existing = $product_data->import['match_existing'];
    if ($match_existing == MATCH_BY_PART_NUMBER) {
       $id = $part_number;   $cache_type = AMAZON_ASIN_PART_NUMBER;
    }
    else if ($match_existing == MATCH_BY_UPC) {
       $id = $upc;   $cache_type = AMAZON_ASIN_UPC;
    }
    else return;
    $asin = $amazon->get_cached_asin($cache_type,$id);
    if ($asin) $amazon_asins = array($asin);
    else {
       if ($asin === '') return;
       if ($match_existing == MATCH_BY_PART_NUMBER)
          $product_details = $amazon->get_products('SellerSKU',
                                                   array($part_number));
       else if ($match_existing == MATCH_BY_UPC)
          $product_details = $amazon->get_products('UPC',array($upc));
       $amazon_asins = array();
       if (! empty($product_details[$id]['asin']))
          $amazon_asins[] = $product_details[$id]['asin'];
       $products = $product_data->amazon->list_matching_products($id);
       if (! empty($products)) foreach ($products as $product) {
          if ((! empty($product['asin'])) &&
              (! in_array($product['asin'],$amazon_asins)))
             $amazon_asins[] = $product['asin'];
       }
       if (empty($amazon_asins)) {
          $amazon->add_cached_asin($cache_type,$id,'');   return;
       }
       $amazon->add_cached_asin($cache_type,$id,$amazon_asins[0]);
    }
    $db = $product_data->db;
    $product_data->current_asin = $amazon_asins[0];
    $query = 'select * from products where amazon_asin in (?) limit 1';
    $query = $db->prepare_query($query,$amazon_asins);
    $row = $db->get_record($query);
    if (empty($row['id'])) return;
    $product_data->current_asin = $row['amazon_asin'];
    if (! empty($row['vendor'])) return;
    $product_id = $row['id'];
    $product_data->products[$row['id']] = $row;
}

function lookup_amazon_asin(&$product_data)
{
    $amazon = get_vendor_amazon($product_data);
    $upc = $product_data->product_record['shopping_gtin']['value'];

    $asin = $amazon->get_cached_asin(AMAZON_ASIN_UPC,$upc);
    if ($asin) {
       $product_data->product_record['amazon_asin']['value'] = $asin;
       return;
    }
    if ($asin === '') return;

    $product_details = $amazon->get_products('UPC',array($upc));
    if ((! empty($product_details[$upc])) &&
        (! empty($product_details[$upc]['asin'])))
       $asin = $product_details[$upc]['asin'];
    else {
       $products = $product_data->amazon->list_matching_products($upc);
       if (! empty($products)) foreach ($products as $product) {
          if (! empty($product['asin'])) {
             $asin = $product['asin'];   break;
          }
       }
    }
    if ($asin) {
       $product_data->product_record['amazon_asin']['value'] = $asin;
       $amazon->add_cached_asin(AMAZON_ASIN_UPC,$upc,$asin);
    }
    else $amazon->add_cached_asin(AMAZON_ASIN_UPC,$upc,'');
}

function update_amazon_import(&$product_data)
{
    $shopping_flags = get_import_shopping_flags($product_data);
    if ($product_data->product_id) {
       $amazon_flags = get_amazon_flags($product_data);
       if (($amazon_flags & SKIP_MATCHING_FBA) &&
           (! empty($product_data->product_info['amazon_fba_flag'])))
          return null;
       if (! empty($product_data->current_asin)) {
          $product_data->product_record['amazon_asin']['value'] =
             $product_data->current_asin;
          $product_data->product_record['vendor']['value'] =
             $product_data->vendor_id;
          if ($product_data->import['load_existing'] ==
              LOAD_OTHER_IMPORT_PRODUCTS)
             $product_data->product_record['import_id']['value'] =
                -$product_data->import['import_source'];
          else $product_data->product_record['import_id']['value'] =
                  $product_data->import_id;
       }
       else if (($shopping_flags & 1) &&
                empty($product_data->product_info['amazon_asin']) &&
                (! empty($product_data->product_record['shopping_gtin']
                                                      ['value'])))
          lookup_amazon_asin($product_data);
       return true;
    }

    if (! empty($product_data->import['amazon_type']))
       $product_data->product_record['amazon_type']['value'] =
          $product_data->import['amazon_type'];
    if (! empty($product_data->import['amazon_item_type']))
       $product_data->product_record['amazon_item_type']['value'] =
          $product_data->import['amazon_item_type'];
    if (($shopping_flags & 1) &&
        (! empty($product_data->product_record['shopping_gtin']['value'])) &&
        empty($product_data->product_record['amazon_asin']['value'])) {
       if (! empty($product_data->current_asin)) {
          $product_data->product_record['amazon_asin']['value'] =
             $product_data->current_asin;
          return true;
       }
       lookup_amazon_asin($product_data);
    }
    return true;
}

?>
