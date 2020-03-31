<?php
/*
               Inroads Control Panel/Shopping Cart - Admin Common Functions

                          Written 2013-2015 by Randall Severy
                           Copyright 2013-2015 Inroads, LLC
*/

function process_resize_admin_image($src_filename,$dest_filename,$resize,
                                    $config_values,$config_prefix,
                                    $show_response=true)
{
    global $pad_image_width,$pad_zoom_image_width,$pad_large_image_width;
    global $pad_medium_image_width,$pad_small_image_width;
    global $pad_image_height,$pad_zoom_image_height,$pad_large_image_height;
    global $pad_medium_image_height,$pad_small_image_height;
    global $image_align,$zoom_image_align,$large_image_align;
    global $medium_image_align,$small_image_align,$image_align_bottom;
    global $docroot;

    if ($resize == 'callout') $field_name = 'callout_color';
    else $field_name = 'image_color';
    $image_color = $config_values[$field_name];
    if (! $image_color) {
       if ($show_response) http_response(406,'No image fill color specified');
       return false;
    }
    $largest_size_info = get_largest_image_size_info($config_values);
    if (! $largest_size_info) {
       if ($show_response)
          http_response(406,'Missing Large Image Size Information');
       return false;
    }
    if (in_array($resize,array('small','medium','large','zoom','callout'))) {
       if ($resize == 'callout') $field_name = 'callout_size';
       else $field_name = $config_prefix.$resize;
       if (! (isset($config_values[$field_name])) ||
           ($config_values[$field_name] == '')) {
          if ($show_response)
             http_response(406,'Missing Image Size Information for '.$resize);
          return false;
       }
       $size_info = explode('|',$config_values[$field_name]);
       if ((count($size_info) != 2) || (! $size_info[0]) || (! $size_info[1])) {
          if ($show_response)
             http_response(406,'Invalid Image Size Information (' .
                          $config_values[$field_name].')');
          return false;
       }
       if (! isset($pad_image_width)) {
          $var_name = 'pad_'.$resize.'_image_width';
          if (isset($$var_name)) $pad_image_width = $$var_name;
          else $pad_image_width = true;
       }
       if (isset($image_align)) {
          if (isset($pad_image_height) && (! $pad_image_height))
             $image_align = IMAGE_ALIGN_TOP;
       }
       else if (isset($image_align_bottom) && $image_align_bottom)
          $image_align = IMAGE_ALIGN_BOTTOM;
       else {
          $var_name = 'pad_'.$resize.'_image_height';
          if (isset($$var_name) && (! $var_name))
             $image_align = IMAGE_ALIGN_TOP;
          else {
             $var_name = $resize.'_image_align';
             if (isset($$var_name)) $image_align = $$var_name;
             else $image_align = IMAGE_ALIGN_MIDDLE;
          }
       }
    }
    else {
       $size_info = explode('x',$resize);
       if (count($size_info) != 2) {
          if ($show_response)
             http_response(406,'Invalid Resize Value '.$resize);
          return false;
       }
       if (! isset($pad_image_width)) $pad_image_width = true;
       if (! isset($image_align)) $image_align = IMAGE_ALIGN_MIDDLE;
    }
    if (! resize_image($docroot.$src_filename,$docroot.$dest_filename,
                       $size_info[0],$size_info[1],$image_color,
                       $pad_image_width,$image_align,$largest_size_info[1])) {
       if ($src_filename != $dest_filename) {
          if ($show_response)
             http_response(406,'Unable to convert image '.$src_filename .
                           ' to '.$dest_filename);
       }
       else if ($show_response)
          http_response(406,'Unable to convert image '.$src_filename);
       return false;
    }
    return true;
}

?>
