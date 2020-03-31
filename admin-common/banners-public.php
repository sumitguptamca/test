<?php
/*
             Inroads Shopping Cart - Public Banner Ads Functions

                      Written 2016 by Randall Severy
                       Copyright 2016 Inroads, LLC
*/

if (! class_exists('UI')) {
   if (file_exists('engine/ui.php')) {
      require_once 'engine/ui.php';
      require_once 'engine/db.php';
   }
   else {
      require_once '../engine/ui.php';
      require_once '../engine/db.php';
   }
}
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

function banner_ad_exists($db,$slot)
{
    if (! $db) $db = new DB;
    if (function_exists('custom_banner_ad_exists')) {
       $retval = custom_banner_ad_exists($db,$slot);
       if ($retval !== null) return $retval;
    }
    $query = 'select count(id) as num_ads from banner_ads where parent=?';
    $query = $db->prepare_query($query,$slot);
    $row = $db->get_record($query);
    if ($row && ($row['num_ads'] > 0)) return true;
    return false;
}

function load_ad()
{
    $db = new DB;
    $slot_id = get_form_field('slot');
    if (! $slot_id) {
       log_error('Missing Banner Slot ID in load_ad');
       http_response(404,'Missing Banner Slot ID');   return null;
    }
    $query = 'select * from banner_ads where parent=?';
    $query = $db->prepare_query($query,$slot_id);
    $rows = $db->get_records($query);
    if ((! $rows) || (count($rows) == 0)) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else {
          log_error('Banner Ads for Slot #'.$slot_id.' not found');
          http_response(404,'Banner Ads not found');
       }
       return null;
    }
    $num_rows = count($rows);
    if ($num_rows == 1) $row = $rows[0];
    else $row = $rows[mt_rand(0,$num_rows - 1)];
    $ad_id = $row['id'];
    $image = $row['image'];
    if (! $image) {
       log_error('No Image Found for Banner Ad #'.$ad_id);
       http_response(404,'Banner Ad Image not found');   return;
    }
    $image_filename = '../images/banner-ads/'.$image;
    if (! file_exists($image_filename)) {
       log_error('Image '.$image_filename.' Not Found for Banner Ad #'.$ad_id);
       http_response(404,'Banner Ad Image not found');   return;
    }
    $image_size = @getimagesize($image_filename);
    if (! $image_size) {
       log_error('Unable to get size information for Banner Ad Image ' .
                 $image_filename);
       http_response(422,'Unable to get Banner Ad Image Size Info');
       return;
    }

    $query = 'update banner_ads set views=ifnull(views+1,1) where id=?';
    $query = $db->prepare_query($query,$ad_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    if (! function_exists('image_type_to_extension')) {
       $types = array(1=>'gif','jpeg','png','swf','psd','bmp','tiff','tiff',
                      'jpc','jp2','jpf','jb2','swc','aiff','wbmp','xbm','ico');
       $image_type = $types[$image_size[2]];
    }
    else $image_type = image_type_to_extension($image_size[2],false);
    $image = file_get_contents($image_filename);
    setcookie('Banner_'.$slot_id,$ad_id);
    header('Content-Type: image/'.$image_type);
    header('Cache-Control: no-cache');
    header("Expires: -1441");
    print $image;
}

function click_ad()
{
    $db = new DB;
    $slot_id = get_form_field('slot');
    if (! $slot_id) {
       log_error('Missing Banner Slot ID in load_ad');
       http_response(404,'Missing Banner Slot ID');   return;
    }
    $ad_id = get_cookie('Banner_'.$slot_id);
    if (! $ad_id) {
       log_error('Missing Banner Ad ID in load_ad');
       http_response(404,'Missing Banner Ad ID');   return;
    }
    $query = 'select * from banner_ads where id=?';
    $query = $db->prepare_query($query,$ad_id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else {
          log_error('Banner Ad #'.$ad_id.' not found');
          http_response(404,'Banner Ad not found');
       }
       return;
    }
    $url = $row['url'];
    if (! $url) {
       log_error('No URL Found for Banner Ad #'.$ad_id);
       http_response(404,'Banner Ad URL not found');   return;
    }

    $query = 'update banner_ads set clicks=ifnull(clicks+1,1) where id=?';
    $query = $db->prepare_query($query,$ad_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    header('Cache-Control: no-cache');
    header("Expires: -1441");
    header('Location: '.$url);
}

$cmd = get_form_field('cmd');
if ($cmd == 'loadad') load_ad();
else if ($cmd == 'clickad') click_ad();

?>
