<?php
/*
           Inroads Shopping Cart - Google Shopping Product Reviews Feed

                         Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC
*/

chdir(dirname(__FILE__));   chdir('../..');
require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../cartengine/shopping-common.php';
require_once '../cartengine/utility.php';
require_once 'shopping/google/googleshopping.php';
if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;
   require_once '../cartengine/cartconfig-common.php';
}
else $shopping_cart = false;
require_once '../cartengine/products-common.php';
if (file_exists('custom-config.php')) require_once 'custom-config.php';

define('GOOGLE_SHOPPING_FLAG',1);

function export_reviews_feed()
{
    global $company_name;

    set_remote_user('googleshopping');
    set_time_limit(0);
    $db = new DB;
    $query = 'select * from reviews where status=1 order by create_date';
    $reviews = $db->get_records($query);
    if (! $reviews) {
       if (! isset($db->error)) http_response(204,'No Reviews Found');
       else http_response(422,$db->error);
       return;
    }
    $product_ids = array();
    foreach ($reviews as $review) {
       $id = $review['parent'];
       if (! in_array($id,$product_ids)) $product_ids[] = $id;
    }

    $query = 'select * from products where '.get_shopping_status_where() .
             ' and (shopping_flags & 2) and id in (?) order by name';
    $query = $db->prepare_query($query,$product_ids);
    $products = $db->get_records($query,'id');
    if (! $products) {
       if (! isset($db->error)) http_response(204,'No Products Found');
       else http_response(422,$db->error);
       return;
    }
    $query = 'select parent,part_number from product_inventory ' .
             'where parent in (?)';
    $query = $db->prepare_query($query,$product_ids);
    $part_numbers = $db->get_records($query,'parent','part_number');
    if (! $part_numbers) {
       if (! isset($db->error)) http_response(204,'No Part Numbers Found');
       else http_response(422,$db->error);
       return;
    }

    write_xml_header();
    print '<feed xmlns:vc="http://www.w3.org/2007/XMLSchema-versioning"' .
          ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
          ' xsi:noNamespaceSchemaLocation=' .
          ' "http://www.google.com/shopping/reviews/schema/product/2.2/product_reviews.xsd">' .
          '<version>2.2</version><publisher><name>';
    write_xml_data($company_name);
    print '</name></publisher><reviews>';

    foreach ($reviews as $review) {
       $product_id = $review['parent'];
       if (! isset($products[$product_id])) continue;
       $product = $products[$product_id];
       if (function_exists('update_shopping_product_row'))
          update_shopping_product_row(GOOGLE_SHOPPING_FLAG,$db,$product);
       print '<review><review_id>'.$review['id'].'</review_id><reviewer><name>';
       if (! empty($review['firstname'])) write_xml_data($review['firstname']);
       if (! empty($review['lastname'])) {
          if (! empty($review['firstname'])) print ' ';
          write_xml_data($review['lastname']);
       }
       print '</name></reviewer><review_timestamp>' .
             date('c',$review['create_date']).'</review_timestamp>';
       if (! empty($review['subject'])) {
          print '<title>';   write_xml_data($review['subject']);
          print '</title>';
       }
       print '<content>';
       write_xml_data($review['review']);
       print '</content>';
       $product_url = build_product_url($db,$product);
       print '<review_url type="group">';
       write_xml_data($product_url);
       print '</review_url><ratings><overall min="1" max="5">' .
             $review['rating'].'</overall></ratings>';
       if ($product['display_name']) $product_name = $product['display_name'];
       else $product_name = $product['name'];
       print '<products><product><product_ids>';
       if (! empty($product['shopping_gtin'])) {
          print '<gtins><gtin>';   write_xml_data($product['shopping_gtin']);
          print '</gtin></gtins>';
       }
       if (! empty($product['shopping_mpn'])) {
          print '<mpns><mpn>';   write_xml_data($product['shopping_mpn']);
          print '</mpn></mpns>';
       }
       if (! empty($part_numbers[$product_id])) {
          print '<skus><sku>';   write_xml_data($part_numbers[$product_id]);
          print '</sku></skus>';
       }
       if (! empty($product['shopping_brand'])) {
          print '<brands><brand>';
          write_xml_data($product['shopping_brand']);
          print '</brand></brands>';
       }
       print '</product_ids><product_name>';
       write_xml_data($product_name);
       print '</product_name><product_url>';
       write_xml_data($product_url);
       print '</product_url></product></products></review>';
    }

    print '</reviews></feed>'."\n";

    log_activity('Generated Google Shopping Reviews Datafeed');
}

export_reviews_feed();

?>

