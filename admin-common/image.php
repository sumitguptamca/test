<?php
/*
                Inroads Control Panel/Shopping Cart - Image Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

if (! class_exists('UI')) {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   if (file_exists('../cartengine/adminperms.php')) {
      $shopping_cart = true;
      require_once 'cartconfig-common.php';
   }
   else {
      $shopping_cart = false;
      require_once 'catalog-common.php';
   }
   if (file_exists('../admin/custom-config.php'))
      require_once '../admin/custom-config.php';
   $dynamic_images = true;
}
else $dynamic_images = false;

define('IMAGE_ALIGN_TOP',0);
define('IMAGE_ALIGN_MIDDLE',1);
define('IMAGE_ALIGN_BOTTOM',2);

global $image_subdir_prefix,$use_dynamic_images;

$php_url = '';
$script_url = '';
$images_parent_type = -1;
$images_parent_types = array('Category','Product','Attribute Option',
                             'Callout');
if (! isset($image_subdir_prefix)) $image_subdir_prefix = null;
if (! isset($use_dynamic_images)) $use_dynamic_images = false;

function init_images($php_url_param,$script_url_param,$parent_type_param)
{
    global $php_url;
    global $script_url;
    global $images_parent_type;

    $php_url = $php_url_param;
    $script_url = $script_url_param;
    $images_parent_type = $parent_type_param;
}

function image_type_name($parent_type)
{
    global $images_parent_types;

    if (isset($images_parent_types[$parent_type]))
       $image_type_name = $images_parent_types[$parent_type];
    else if ($parent_type == 99) $image_type_name = 'Category/Product';
    else $image_type_name = 'Unknown Type';
    return $image_type_name;
}

function add_image_buttons($dialog,$enabled=true)
{
    global $image_dir,$shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog->add_button('Add Image',$prefix.'images/AddImage.png',
                        'add_image();','add_image',$enabled);
    if (file_exists($image_dir.'/original/')) {
       $dialog->add_button('Upload Images',$prefix.'images/UploadImages.png',
                           'upload_images();','upload_images',$enabled);
       $dialog->add_button('Edit Image',$prefix.'images/EditImage.png',
                           'edit_image_file();','edit_image_file',$enabled);
    }
    $dialog->add_button('Edit Image Info',$prefix.'images/EditImage.png',
                        'edit_image();','edit_image_info',$enabled);
    $dialog->add_button('Delete Image',$prefix.'images/DeleteImage.png',
                        'delete_image();','delete_image',$enabled);
}

function add_image_sequence_button($icon,$funct,$alt,$margin)
{
?>
            <table cellspacing=0 cellpadding=0 class="dialog_button"<?
   if ($margin) print " style=\"margin-bottom:20px;\""; ?>>
              <tr onMouseOver="button_mouseover(this);" onMouseOut="button_mouseout(this);"
                onClick="<? print $funct; ?>" class="button_out" valign="middle">
                <td><img src="<? print $icon ?>" alt="<?
   print $alt; ?>" title="<? print $alt; ?>"></td>
              </tr>
            </table>
<?
}

function add_image_sequence_buttons($dialog)
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog->write("          <td id=\"image_sequence_buttons\" width=\"50\" " .
                   "nowrap align=\"center\" style=\"display:none;\">\n");
    add_image_sequence_button($prefix.'images/MoveTop.png','move_image_top();',
                              'Top',true);
    add_image_sequence_button($prefix.'images/MoveUp.png','move_image_up();',
                              'Up',true);
    add_image_sequence_button($prefix.'images/MoveDown.png','move_image_down();',
                              'Down',true);
    add_image_sequence_button($prefix.'images/MoveBottom.png',
                              'move_image_bottom();','Bottom',false);
    $dialog->write("          </td>\n");
}

function add_image_sample($screen)
{
    $screen->write("        <p>\n");
    $screen->write("        <div id=\"sample_image_div\"></div>\n");
}

function image_record_definition()
{
    global $shopping_cart;

    $image_record = array();
    $image_record['id'] = array('type' => INT_TYPE);
    $image_record['id']['key'] = true;
    $image_record['parent_type'] = array('type' => INT_TYPE);
    $image_record['parent'] = array('type' => INT_TYPE);
    $image_record['sequence'] = array('type' => INT_TYPE);
    $image_record['filename'] = array('type' => CHAR_TYPE);
    $image_record['caption'] = array('type' => CHAR_TYPE);
    $image_record['title'] = array('type' => CHAR_TYPE);
    $image_record['alt_text'] = array('type' => CHAR_TYPE);
    $image_record['orig_width'] = array('type' => INT_TYPE);
    $image_record['orig_height'] = array('type' => INT_TYPE);
    if ($shopping_cart)
       $image_record['callout_group'] = array('type' => INT_TYPE);
    return $image_record;
}

function get_largest_image_size_info($config_values)
{
    global $images_parent_type;

    if ($images_parent_type == 2) $prefix = 'attrimage_size_';
    else $prefix = 'image_size_';
    if (isset($config_values[$prefix.'zoom']) &&
        ($config_values[$prefix.'zoom'] != '')) {
       $size_info = explode('|',$config_values[$prefix.'zoom']);
       if ((count($size_info) == 2) && $size_info[1]) return $size_info;
    }
    if (isset($config_values[$prefix.'large']) &&
        ($config_values[$prefix.'large'] != '')) {
       $size_info = explode('|',$config_values[$prefix.'large']);
       if ((count($size_info) == 2) && $size_info[1]) return $size_info;
    }
    if (isset($config_values[$prefix.'medium']) &&
        ($config_values[$prefix.'medium'] != '')) {
       $size_info = explode('|',$config_values[$prefix.'medium']);
       if ((count($size_info) == 2) && $size_info[1]) return $size_info;
    }
    if (isset($config_values[$prefix.'small']) &&
        ($config_values[$prefix.'small'] != '')) {
       $size_info = explode('|',$config_values[$prefix.'small']);
       if ((count($size_info) == 2) && $size_info[1]) return $size_info;
    }
    return null;
}

function build_image_filename($basedir,$filename,&$error_msg)
{
    global $image_subdir_prefix,$new_dir_perms;

    if (! $image_subdir_prefix) return $basedir.$filename;

    $image_subdir = $basedir.substr($filename,0,$image_subdir_prefix);
    if (! file_exists($image_subdir)) {
       mkdir($image_subdir);
       if (isset($new_dir_perms) &&
           (! chmod($image_subdir,$new_dir_perms))) {
          $error_msg = 'Unable to set permissions on '.$image_subdir;
          return null;
       }
    }
    return $image_subdir.'/'.$filename;
}

function add_image()
{
    global $php_url,$script_url,$cms_base_url,$image_dir,$attr_image_dir;
    global $images_parent_type,$use_dynamic_images,$image_subdir_prefix;
    global $shopping_cart;

    $parent = get_form_field('Parent');
    $frame = get_form_field('Frame');
    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';

    $db = new DB;
    $config_values = load_config_values($db);
    if ($config_values === null) return;
    else if (! $config_values) {
       process_error('Image Size Information has not been configured',0);
       $db->close();   return;
    }
    $size_info = get_largest_image_size_info($config_values);
    if (! $size_info) {
       process_error('No Image Size information is available',0);
       $db->close();   return;
    }

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file($script_url);
    $dialog->add_style_sheet($prefix.'image.css');
    $dialog->add_script_file($prefix.'image.js');
    $head_block = "<script type=\"text/javascript\">\n";
    if (isset($cms_base_url))
       $head_block .= "       cms_url = '".$cms_base_url."';\n";
    if (isset($config_values['image_crop_ratio']))
       $head_block .= "       crop_ratio = '".$config_values['image_crop_ratio'] .
                      "';\n";
    $head_block .= "       image_dir = '";
    if ($images_parent_type == 2) $head_block .= '/attrimages';
    else $head_block .= '/images';
    $head_block .= "';\n";
    if ($use_dynamic_images)
       $head_block .= "       use_dynamic_images = true;\n";
    if ($image_subdir_prefix)
       $head_block .= '       image_subdir_prefix = ' .
                      $image_subdir_prefix.";\n";
    $head_block .= '    </script>';
    $dialog->add_head_line($head_block);
    $dialog->set_body_id('add_image');
    $dialog->set_help('add_image');
    $dialog->start_body('Add Image');
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Add Image',$prefix.'images/AddImage.png',
                        'process_add_image();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->write("<form method=\"POST\" action=\"".$php_url .
                   "\" name=\"AddImage\" ");
    $dialog->write("encType=\"multipart/form-data\">\n");
    $dialog->add_hidden_field('cmd','processaddimage');
    $dialog->add_hidden_field('Parent',$parent);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_field_table();
    $dialog->start_row('Filename:');
    $dialog->write("<input type=\"file\" name=\"Filename\" size=\"35\" ");
    $dialog->write("class=\"browse_button\" onBlur=\"update_server_filename();\">\n");
    $dialog->end_row();
    $dialog->start_row('Server Filename:','bottom','fieldprompt',null,true);
    if ($images_parent_type == 0) $input_size = 20;
    else $input_size = 35;
    $dialog->write("<input type=\"text\" class=\"text\" name=\"ServerFilename\" ");
    $dialog->write("size=\"".$input_size."\" value=\"\" onFocus=\"update_server_filename();\">");
    if (file_exists($image_dir.'/original/')) {
       $dialog->write("<input type=\"button\" value=\"Browse...\" ");
       $dialog->write("onClick=\"browse_server();\" style=\"margin-left: 2px;\">\n");
    }
    if ($images_parent_type == 0) {
       $dialog->write("<input type=\"button\" value=\"Product Image...\" ");
       $dialog->write("onClick=\"select_product_image();\" style=\"margin-left: 2px;\">\n");
    }
    $dialog->end_row();
    $dialog->write("<tr valign=top><td class=\"fieldprompt\" nowrap></td><td width=\"300\">\n");
    if (function_exists('custom_image_note')) custom_image_note($db,$dialog);
    else if ($size_info[0] || $size_info[1]) {
       $dialog->write('Note: for best results, uploaded images should be at least ');
       if ($size_info[0]) {
          $dialog->write($size_info[0].' pixels wide');
          if ($size_info[1]) $dialog->write(' and ');
       }
       if ($size_info[1]) $dialog->write($size_info[1].' pixels high');
    }
    $dialog->write("</td></tr>\n");
    $dialog->add_edit_row('Caption:','caption','',50);
    $dialog->add_edit_row('Title:','title','',50);
    $dialog->add_edit_row('Alt Text:','alt_text','',50);
    $dialog->start_row('Options:','top');
    $dialog->add_checkbox_field('ReplaceExisting','Replace Existing Image',false);
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
    $db->close();
}

function dwordize($str)
{
    $a = ord($str[0]);
    $b = ord($str[1]);
    $c = ord($str[2]);
    return $c*256*256 + $b*256 + $a;
}

function byte3($n)
{
    return chr($n & 255) . chr(($n >> 8) & 255) . chr(($n >> 16) & 255);    
}

function dword($n)
{
    return pack('V', $n);
}

function word($n)
{
    return pack('v', $n);
}

function imagebmp(&$img, $filename = false)
{
    $wid = imagesx($img);
    $hei = imagesy($img);
    $wid_pad = str_pad('', $wid % 4, "\0");
    $size = 54 + ($wid + $wid_pad) * $hei * 3; //fixed
    $header['identifier']       = 'BM';
    $header['file_size']        = dword($size);
    $header['reserved']         = dword(0);
    $header['bitmap_data']      = dword(54);
    $header['header_size']      = dword(40);
    $header['width']            = dword($wid);
    $header['height']           = dword($hei);
    $header['planes']           = word(1);
    $header['bits_per_pixel']   = word(24);
    $header['compression']      = dword(0);
    $header['data_size']        = dword(0);
    $header['h_resolution']     = dword(0);
    $header['v_resolution']     = dword(0);
    $header['colors']           = dword(0);
    $header['important_colors'] = dword(0);

    if ($filename) {
       $f = fopen($filename, 'wb');
       foreach ($header AS $h) fwrite($f, $h);
       for ($y=$hei-1; $y>=0; $y--) {
          for ($x=0; $x<$wid; $x++) {
             $rgb = imagecolorat($img, $x, $y);
             fwrite($f, byte3($rgb));
          }
          fwrite($f, $wid_pad);
       }
       fclose($f);
    }
    else {
       foreach ($header AS $h) echo $h;
       for ($y=$hei-1; $y>=0; $y--) {
          for ($x=0; $x<$wid; $x++) {
             $rgb = imagecolorat($img, $x, $y);
             echo byte3($rgb);
          }
          echo $wid_pad;
       }
    }
    return true;
}

function imagecreatefrombmp($filename)
{
    $f = fopen($filename, 'rb');
    $header = fread($f, 54);
    $header = unpack('c2identifier/Vfile_size/Vreserved/Vbitmap_data/Vheader_size/' .
                     'Vwidth/Vheight/vplanes/vbits_per_pixel/Vcompression/Vdata_size/'.
                     'Vh_resolution/Vv_resolution/Vcolors/Vimportant_colors', $header);
    if ($header['identifier1'] != 66 or $header['identifier2'] != 77)
       return false;
    if ($header['bits_per_pixel'] != 24) return false;
    $wid2 = ceil((3*$header['width']) / 4) * 4;
    $wid = $header['width'];
    $hei = $header['height'];
    $img = imagecreatetruecolor($header['width'], $header['height']);
    for ($y=$hei-1; $y>=0; $y--) {
       $row = fread($f, $wid2);
       $pixels = str_split($row, 3);
       for ($x=0; $x<$wid; $x++)
          imagesetpixel($img, $x, $y, dwordize($pixels[$x]));
    }
    fclose($f);            
    return $img;
}    

function resize_image($src_filename,$dest_filename,$width,$height,
                      $image_color,$pad_width,$image_align,$large_height)
{
    global $image_watermark,$jpeg_image_quality,$new_file_perms;
    global $image_scaling_factor,$image_watermark_top;

    if (! isset($jpeg_image_quality)) $jpeg_image_quality = 75;
    $memory_limit = intval(ini_get('memory_limit'));
    if (($memory_limit != -1) && ($memory_limit < 256))
       ini_set('memory_limit','256M');
    if ($dest_filename && (get_server_type() == WINDOWS)) {
       if ($src_filename == $dest_filename) return true;
       else if (copy($src_filename,$dest_filename)) return true;
       else {
          log_error('Unable to copy '.$src_filename.' to '.$dest_filename);
          return false;
       }
    }
    if (isset($image_scaling_factor)) $scaling_factor = $image_scaling_factor;
    else $scaling_factor = 1.25;
    if (! file_exists($src_filename)) $image_size = null;
    else $image_size = @getimagesize($src_filename);
    if (! $image_size) {
       log_error('Unable to get size information for image '.$src_filename);
       return false;
    }
    $image_width = $image_size[0];
    $image_height = $image_size[1];
    if (! function_exists('image_type_to_extension')) {
       $types = array(1=>'gif','jpeg','png','swf','psd','bmp','tiff','tiff',
                      'jpc','jp2','jpf','jb2','swc','aiff','wbmp','xbm','ico');
       $image_type = $types[$image_size[2]];
    }
    else $image_type = image_type_to_extension($image_size[2],false);
    $max_width = $max_height = null;
    if ($width === null) $width = $image_width;
    else if (! is_numeric($width)) {
       if ($width[0] == '<') $max_width = intval(substr($width,1));
       $width = null;
    }
    if ($height === null) $height = $image_height;
    else if (! is_numeric($height)) {
       if ($height[0] == '<') $max_height = intval(substr($height,1));
       $height = null;
    }

    if (($image_color != '') || ($image_type != 'png')) {
       if ($image_color == '') $image_color = 'FFFFFF';
       if ($image_color[0] == '#') $bg_color = hexdec(substr($image_color,1,6));
       else $bg_color = hexdec($image_color);
       $bg_colors = array('red' => 0xFF & ($bg_color >> 0x10),
                          'green' => 0xFF & ($bg_color >> 0x8),
                          'blue' => 0xFF & $bg_color);
    }

    $image_ratio = $image_width / $image_height;
    if ($width && ((! $height) || (($height * $image_ratio) > $width))) {
       /*   resize based on width   */
       if ($width < ($image_width * $scaling_factor)) {
          $conversion_factor = $width / $image_width;
          $dest_width = $width;
          $dest_height = $image_height * $conversion_factor;
       }
       else {
          $dest_width = ($image_width * $scaling_factor);
          $dest_height = ($image_height * $scaling_factor);
       }
       if ($max_height && ($dest_height > $max_height)) {
          $dest_width *= ($max_height / $dest_height);
          $dest_height = $max_height;
       }
       if (! $height) $height = $dest_height;
    }
    else {
       /*   resize based on height   */
       if ($height < ($image_height * $scaling_factor)) {
          $conversion_factor = $height / $image_height;
          $dest_height = $height;
          $dest_width = $image_width * $conversion_factor;
       }
       else {
          $dest_width = ($image_width * $scaling_factor);
          $dest_height = ($image_height * $scaling_factor);
       }
       if ($max_width && ($dest_width > $max_width)) {
          $dest_height *= ($max_width / $dest_width);
          $dest_width = $max_width;
       }
       if (! $width) $width = $dest_width;
    }
    if ($pad_width) $dest_x = round($width - $dest_width) / 2;
    else $dest_x = 0;
    switch ($image_align) {
       case IMAGE_ALIGN_TOP: $dest_y = 0;   break;
       case IMAGE_ALIGN_MIDDLE: $dest_y = round($height - $dest_height) / 2;
                                break;
       case IMAGE_ALIGN_BOTTOM: $dest_y = $height - $dest_height;   break;
    }
    if (isset($image_watermark)) {
       if (! file_exists($image_watermark)) $watermark_size = null;
       else $watermark_size = @getimagesize($image_watermark);
       if (! $watermark_size) {
          log_error('Unable to get size information for watermark '.$image_watermark);
          return false;
       }
       $watermark_width = $watermark_size[0];
       $watermark_height = $watermark_size[1];
       if (! function_exists('image_type_to_extension'))
          $watermark_type = $types[$watermark_size[2]];
       else $watermark_type = image_type_to_extension($watermark_size[2],false);
       $watermark_image = @call_user_func('imagecreatefrom'.$watermark_type,
                                          $image_watermark);
       if ($watermark_image === false) {
          log_error('Unable to load watermark image '.$image_watermark);
          return false;
       }
       if ($large_height === null) $large_height = 0;
       else if (! is_numeric($large_height) && ($large_height[0] == '<'))
          $large_height = intval(substr($large_height,1));
       if (! $large_height) $watermark_factor = 0;
       else $watermark_factor = $height / $large_height;
       $dest_watermark_height = $watermark_height * $watermark_factor;
       $dest_watermark_width = $watermark_width * $watermark_factor;
       $watermark_x = (10 * $watermark_factor) + $dest_x;
       if (isset($image_watermark_top) && $image_watermark_top)
          $watermark_y = (10 * $watermark_factor) + $dest_y;
       else $watermark_y = $height - $dest_watermark_height -
                           (10 * $watermark_factor) - $dest_y;
    }

    $src_image = @call_user_func('imagecreatefrom'.$image_type,$src_filename);
    if ($src_image === false) {
       log_error('Unable to load image '.$src_filename);
       return false;
    }
    if (function_exists('imagecreatetruecolor')) {
       $dest_image = imagecreatetruecolor($width,$height);
       if ($dest_image === false) {
          log_error('Unable to create image for '.$dest_filename);
          return false;
       }
       if ($image_color == '') {
          imagealphablending($dest_image,false);
          imagesavealpha($dest_image,true);
          $background_color = imagecolorallocatealpha($dest_image,1,1,1,127);
       }
       else $background_color = imagecolorallocate($dest_image,
               $bg_colors['red'],$bg_colors['green'],$bg_colors['blue']);
       if ($background_color === false) {
          log_error('Unable to allocate color for '.$dest_filename);
          return false;
       }
       if (! imagefilledrectangle($dest_image,0,0,$width,$height,
                                  $background_color)) {
          log_error('Unable to fill rectangle for '.$dest_filename);
          return false;
       }
       if (! imagecopyresampled($dest_image,$src_image,$dest_x,$dest_y,0,0,
                                $dest_width,$dest_height,$image_width,
                                $image_height)) {
          log_error('Unable to resample image for '.$dest_filename);
          return false;
       }
       if (isset($image_watermark)) {
          if (! imagecopyresampled($dest_image,$watermark_image,$watermark_x,
                   $watermark_y,0,0,$dest_watermark_width,$dest_watermark_height,
                   $watermark_width,$watermark_height)) {
             log_error('Unable to resample watermark for '.$dest_filename);
             return false;
          }
       }
    }
    else {
       $dest_image = imagecreate($width,$height);
       if ($dest_image === false) {
          log_error('Unable to create image for '.$dest_filename);
          return false;
       }
       if ($image_color == '') {
          imagealphablending($dest_image,false);
          imagesavealpha($dest_image,true);
          $background_color = imagecolorallocatealpha($dest_image,1,1,1,127);
       }
       else $background_color = imagecolorallocate($dest_image,
               $bg_colors['red'],$bg_colors['green'],$bg_colors['blue']);
       if ($background_color === false) {
          log_error('Unable to allocate color for '.$dest_filename);
          return false;
       }
       if (! imagefilledrectangle($dest_image,0,0,$width,$height,
                                  $background_color)) {
          log_error('Unable to fill rectangle for '.$dest_filename);
          return false;
       }
       if (! imagecopyresized($dest_image,$src_image,$dest_x,$dest_y,0,0,
                              $dest_width,$dest_height,$image_width,
                              $image_height)) {
          log_error('Unable to resize image for '.$dest_filename);
          return false;
       }
       if (isset($image_watermark)) {
          if (! imagecopyresized($dest_image,$watermark_image,$watermark_x,
                   $watermark_y,0,0,$dest_watermark_width,$dest_watermark_height,
                   $watermark_width,$watermark_height)) {
             log_error('Unable to resample watermark for '.$dest_filename);
             return false;
          }
       }
    }
    if (! $dest_filename) header('Content-Type: image/'.$image_type);
    $image_function = 'image'.$image_type;
    if ($image_type == 'jpeg') {
       imageinterlace($dest_image,true);
       if (! imagejpeg($dest_image,$dest_filename,$jpeg_image_quality)) {
          if ($dest_filename)
             log_error('Unable to create jpeg image '.$dest_filename);
          else log_error('Unable to create jpeg image');
          return false;
       }
    }
    else if (! $image_function($dest_image,$dest_filename)) {
       if ($dest_filename)
          log_error('Unable to create '.$image_type.' image '.$dest_filename);
       else log_error('Unable to create '.$image_type.' image');
       return false;
    }
    if (isset($new_file_perms) && $dest_filename &&
       (! chmod($dest_filename,$new_file_perms))) {
       log_error('Unable to set permissions on '.$dest_filename);
       return false;
    }
    imagedestroy($src_image);
    imagedestroy($dest_image);
    return true;
}

function add_image_record($filename,$parent,$caption,$title,$alt_text,
                          $orig_width,$orig_height,$ajax=false)
{
    global $images_parent_type;

    $db = new DB;

    $query = 'select sequence from images where parent_type=? and parent=? ' .
             'order by sequence desc limit 1';
    $query = $db->prepare_query($query,$images_parent_type,$parent);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) {
          $error_msg = 'Database Error: '.$db->error;
          if ($ajax) http_response(422,$error_msg);
          else if ($ajax !== null) process_error($error_msg,0);
          $db->close();   return false;
       }
       $sequence = 0;
    }
    $sequence = $row['sequence'];
    $sequence++;

    $image_record = image_record_definition();
    $image_record['parent_type']['value'] = (string) $images_parent_type;
    $image_record['parent']['value'] = $parent;
    $image_record['sequence']['value'] = $sequence;
    $image_record['filename']['value'] = $filename;
    $image_record['caption']['value'] = $caption;
    $image_record['title']['value'] = $title;
    $image_record['alt_text']['value'] = $alt_text;
    $image_record['orig_width']['value'] = $orig_width;
    $image_record['orig_height']['value'] = $orig_height;
    if (! $db->insert('images',$image_record)) {
       $error_msg = 'Database Error: '.$db->error;
       if ($ajax) http_response(422,$error_msg);
       else if ($ajax !== null) process_error($error_msg,0);
       $db->close();   return false;
    }
    $db->close();
    return true;
}

function update_image_record($filename,$orig_width,$orig_height,$ajax=false)
{
    $db = new DB;

    $image_record = image_record_definition();
    $image_record['filename']['value'] = $filename;
    $image_record['filename']['key'] = true;
    $image_record['orig_width']['value'] = $orig_width;
    $image_record['orig_height']['value'] = $orig_height;
    if (! $db->update('images',$image_record)) {
       $error_msg = 'Database Error: '.$db->error;
       if ($ajax) http_response(422,$error_msg);
       else if ($ajax !== null) process_error($error_msg,0);
       $db->close();   return false;
    }
    $db->close();
    return true;
}

function process_image($filename,$original_filename,$parent,$caption,$title,
                       $alt_text,$config_values,$insert_db_flag,$ajax=false,
                       $single_size=null,&$image_record=null)
{
    global $image_dir,$attr_image_dir,$images_parent_type;
    global $pad_image_width,$pad_zoom_image_width,$pad_large_image_width;
    global $pad_medium_image_width,$pad_small_image_width;
    global $pad_image_height,$pad_zoom_image_height,$pad_large_image_height;
    global $pad_medium_image_height,$pad_small_image_height;
    global $image_align,$zoom_image_align,$large_image_align;
    global $medium_image_align,$small_image_align,$image_align_bottom;
    global $enable_product_callouts;

    if (! isset($enable_product_callouts)) $enable_product_callouts = false;
    if (! file_exists($original_filename)) {
       $error_msg = 'Original Image '.$original_filename .
                    ' does not exist on the server';
       if ($ajax) {
          log_error($error_msg);   http_response(404,$error_msg);
       }
       else if ($ajax === null) log_error($error_msg);
       else process_error($error_msg,0);
       return false;
    }
    if ($images_parent_type == 2) {
       $image_color = $config_values['attrimage_color'];
       $prefix = 'attrimage_size_';
       $image_dir = $attr_image_dir;
    }
    else if ($enable_product_callouts && ($images_parent_type == 3)) {
       $image_color = $config_values['callout_color'];
    }
    else {
       $image_color = $config_values['image_color'];   $prefix = 'image_size_';
    }
    if (isset($pad_image_width)) {
       $pad_zoom_image_width = $pad_image_width;
       $pad_large_image_width = $pad_image_width;
       $pad_medium_image_width = $pad_image_width;
       $pad_small_image_width = $pad_image_width;
    }
    else {
       if (! isset($pad_zoom_image_width)) $pad_zoom_image_width = true;
       if (! isset($pad_large_image_width)) $pad_large_image_width = true;
       if (! isset($pad_medium_image_width)) $pad_medium_image_width = true;
       if (! isset($pad_small_image_width)) $pad_small_image_width = true;
    }
    $zoom_align = $large_align = $medium_align = $small_align = IMAGE_ALIGN_MIDDLE;
    if (isset($zoom_image_align)) $zoom_align = $zoom_image_align;
    if (isset($large_image_align)) $large_align = $large_image_align;
    if (isset($medium_image_align)) $medium_align = $medium_image_align;
    if (isset($small_image_align)) $small_align = $small_image_align;
    if (isset($pad_image_height)) {
       if (! $pad_image_height) {
          $zoom_align = $large_align = IMAGE_ALIGN_TOP;
          $medium_align = $small_align = IMAGE_ALIGN_TOP;
       }
    }
    else if (isset($image_align)) {
       $zoom_align = $large_align = $image_align;
       $medium_align = $small_align = $image_align;
    }
    else if (isset($image_align_bottom) && $image_align_bottom) {
       $zoom_align = $large_align = IMAGE_ALIGN_BOTTOM;
       $medium_align = $small_align = IMAGE_ALIGN_BOTTOM;
    }
    else {
       if (isset($pad_zoom_image_height) && (! $pad_zoom_image_height))
          $zoom_align = IMAGE_ALIGN_TOP;
       if (isset($pad_large_image_height) && (! $pad_large_image_height))
          $large_align = IMAGE_ALIGN_TOP;
       if (isset($pad_medium_image_height) && (! $pad_medium_image_height))
          $medium_align = IMAGE_ALIGN_TOP;
       if (isset($pad_small_image_height) && (! $pad_small_image_height))
          $small_align = IMAGE_ALIGN_TOP;
    }
    $largest_size_info = get_largest_image_size_info($config_values);
    if (! $largest_size_info) {
       $error_msg = 'No Image Size information is available';
       if ($ajax) {
          log_error($error_msg);   http_response(406,$error_msg);
       }
       else if ($ajax === null) log_error($error_msg);
       else process_error($error_msg,0);
       return false;
    }

    if (isset($config_values[$prefix.'small']) &&
        ($config_values[$prefix.'small'] != '') &&
        ((! $single_size) || ($single_size == 'small'))) {
       if (! $filename) $small_filename = null;
       else {
          $small_filename = build_image_filename($image_dir.'/small/',
                                                 $filename,$error_msg);
          if (! $small_filename) {
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       $size_info = explode('|',$config_values[$prefix.'small']);
       if ((count($size_info) == 2) && ($size_info[0] || $size_info[1])) {
          if (! resize_image($original_filename,$small_filename,$size_info[0],
                             $size_info[1],$image_color,$pad_small_image_width,
                             $small_align,$largest_size_info[1])) {
             $error_msg = 'Unable to convert small image '.$small_filename;
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       else if ($single_size) {
          $error_msg = 'No Small Image Size information is available';
          if ($ajax) {
             log_error($error_msg);   http_response(406,$error_msg);
          }
          else if ($ajax === null) log_error($error_msg);
          else process_error($error_msg,0);
          return false;
       }
    }

    if (isset($config_values[$prefix.'medium']) &&
        ($config_values[$prefix.'medium'] != '') &&
        ((! $single_size) || ($single_size == 'medium'))) {
       if (! $filename) $medium_filename = null;
       else {
          $medium_filename = build_image_filename($image_dir.'/medium/',
                                                 $filename,$error_msg);
          if (! $medium_filename) {
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       $size_info = explode('|',$config_values[$prefix.'medium']);
       if ((count($size_info) == 2) && ($size_info[0] || $size_info[1])) {
          if (! resize_image($original_filename,$medium_filename,$size_info[0],
                             $size_info[1],$image_color,$pad_medium_image_width,
                             $medium_align,$largest_size_info[1])) {
             $error_msg = 'Unable to convert medium image '.$medium_filename;
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       else if ($single_size) {
          $error_msg = 'No Medium Image Size information is available';
          if ($ajax) {
             log_error($error_msg);   http_response(406,$error_msg);
          }
          else if ($ajax === null) log_error($error_msg);
          else process_error($error_msg,0);
          return false;
       }
    }

    if (isset($config_values[$prefix.'large']) &&
        ($config_values[$prefix.'large'] != '') &&
        ((! $single_size) || ($single_size == 'large'))) {
       if (! $filename) $large_filename = null;
       else {
          $large_filename = build_image_filename($image_dir.'/large/',
                                                 $filename,$error_msg);
          if (! $large_filename) {
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       $size_info = explode('|',$config_values[$prefix.'large']);
       if ((count($size_info) == 2) && ($size_info[0] || $size_info[1])) {
          if (! resize_image($original_filename,$large_filename,$size_info[0],
                             $size_info[1],$image_color,$pad_large_image_width,
                             $large_align,$largest_size_info[1])) {
             $error_msg = 'Unable to convert large image '.$large_filename;
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       else if ($single_size) {
          $error_msg = 'No Large Image Size information is available';
          if ($ajax) {
             log_error($error_msg);   http_response(406,$error_msg);
          }
          else if ($ajax === null) log_error($error_msg);
          else process_error($error_msg,0);
          return false;
       }
    }

    if (isset($config_values[$prefix.'zoom']) &&
        ($config_values[$prefix.'zoom'] != '') &&
        ((! $single_size) || ($single_size == 'zoom'))) {
       if (! $filename) $zoom_filename = null;
       else {
          $zoom_filename = build_image_filename($image_dir.'/zoom/',
                                                $filename,$error_msg);
          if (! $zoom_filename) {
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       $size_info = explode('|',$config_values[$prefix.'zoom']);
       if ((count($size_info) == 2) && ($size_info[0] || $size_info[1])) {
          if (! resize_image($original_filename,$zoom_filename,$size_info[0],
                             $size_info[1],$image_color,$pad_zoom_image_width,
                             $zoom_align,$largest_size_info[1])) {
             $error_msg = 'Unable to convert zoom image '.$zoom_filename;
             if ($ajax) {
                log_error($error_msg);   http_response(422,$error_msg);
             }
             else if ($ajax === null) log_error($error_msg);
             else process_error($error_msg,0);
             return false;
          }
       }
       else if ($single_size) {
          $error_msg = 'No Small Zoom Size information is available';
          if ($ajax) {
             log_error($error_msg);   http_response(406,$error_msg);
          }
          else if ($ajax === null) log_error($error_msg);
          else process_error($error_msg,0);
          return false;
       }
    }

    if ($single_size == 'original') {
       if (! resize_image($original_filename,null,null,null,$image_color,false,
                          IMAGE_ALIGN_MIDDLE,$largest_size_info[1])) {
          $error_msg = 'Unable to resize original image '.$original_filename;
          log_error($error_msg);   http_response(422,$error_msg);
          return false;
       }
    }

    if ($single_size == 'callout') {
       if (! isset($pad_image_width)) $pad_image_width = true;
       if (isset($pad_image_height) && (! $pad_image_height))
          $image_align = IMAGE_ALIGN_TOP;
       else if (! isset($image_align)) {
          if (isset($image_align_bottom) && $image_align_bottom)
             $image_align = IMAGE_ALIGN_BOTTOM;
          else $image_align = IMAGE_ALIGN_MIDDLE;
       }
       $size_info = explode('|',$config_values['callout_size']);
       if ((count($size_info) == 2) && ($size_info[0] || $size_info[1])) {
          if (! resize_image($original_filename,$original_filename,
                   $size_info[0],$size_info[1],$image_color,$pad_image_width,
                   $image_align,$largest_size_info[1])) {
             $error_msg = 'Unable to resize callout image '.$original_filename;
             log_error($error_msg);   http_response(422,$error_msg);
             return false;
          }
       }
    }

    if ($single_size) return true;

    if (! file_exists($original_filename)) $image_size = null;
    else $image_size = @getimagesize($original_filename);
    if (! $image_size) {
       $error_msg = 'Unable to get size information for image ' .
                    $original_filename;
       if ($ajax) {
          log_error($error_msg);   http_response(422,$error_msg);
       }
       else if ($ajax === null) log_error($error_msg);
       else process_error($error_msg,0);
       return false;
    }
    $orig_width = $image_size[0];
    $orig_height = $image_size[1];
    if ($insert_db_flag) {
       if (! add_image_record($filename,$parent,$caption,$title,$alt_text,
                              $orig_width,$orig_height,$ajax))
          return false;
    }
    else if ($image_record) {
       $image_record['orig_width']['value'] = $orig_width;
       $image_record['orig_height']['value'] = $orig_height;
    }
    else if (! update_image_record($filename,$orig_width,$orig_height,$ajax))
       return false;

    return true;
}

function process_add_image()
{
    global $image_dir,$attr_image_dir,$images_parent_type;
    global $use_dynamic_images,$new_file_perms;

    $filename = $_FILES['Filename']['name'];
    $temp_name = $_FILES['Filename']['tmp_name'];
    $file_type = $_FILES['Filename']['type'];

    if ($temp_name) $new_image = true;
    else $new_image = false;
    $server_filename = get_form_field('ServerFilename');
    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    if (file_exists($image_dir.'/original/'))
       $original_filename = $image_dir.'/original/';
    else $original_filename = $image_dir.'/large/';
    $original_filename = build_image_filename($original_filename,
                            $server_filename,$error_msg);
    if (! $original_filename) {
       process_error($error_msg);   return false;
    }
    if (file_exists($original_filename)) {
       if ($new_image) {
          $replace_existing = get_form_field('ReplaceExisting');
          if ($replace_existing != 'on') {
             process_error('That Image ('.$server_filename .
                           ') already exists on the server',0);
             return;
          }
       }
    }
    else if (! $new_image) {
       process_error('That Image ('.$server_filename .
                     ') does not exist on the server',0);
       return;
    }

    if ($new_image && (! move_uploaded_file($temp_name,$original_filename))) {
       log_error('Attempted to move '.$temp_name.' to '.$original_filename);
       process_error('Unable to save uploaded file',0);   return;
    }
    if (isset($new_file_perms) &&
       (! chmod($original_filename,$new_file_perms))) {
       log_error('Unable to set permissions on '.$original_filename);
       process_error('Unable to set uploaded file permissions',0);   return;
    }

    $parent = get_form_field('Parent');
    $caption = get_form_field('caption');
    $title = get_form_field('title');
    $alt_text = get_form_field('alt_text');

    $config_values = load_config_values();
    if ($config_values === null) return;
    else if (! $config_values) {
       process_error('Image Size Information has not been configured',0);
       return;
    }

    if ($new_image && (! $use_dynamic_images)) {
       if (! process_image($server_filename,$original_filename,$parent,
                           $caption,$title,$alt_text,$config_values,true))
          return;
    }
    else {
       if (! file_exists($original_filename)) $image_size = null;
       else $image_size = @getimagesize($original_filename);
       if (! $image_size) {
          $error_msg = 'Unable to get size information for image ' .
                       $original_filename;
          process_error($error_msg,0);   return;
       }
       $orig_width = $image_size[0];
       $orig_height = $image_size[1];
       if (! add_image_record($server_filename,$parent,$caption,$title,
                              $alt_text,$orig_width,$orig_height,true)) return;
    }

    $frame = get_form_field('Frame');
    log_activity('Added Image '.$server_filename.' to ' .
                 image_type_name($images_parent_type).' #'.$parent);
    if ($images_parent_type == 1) {
       $db = new DB;
       write_product_activity('Added Image '.$server_filename.' '.
                              get_product_activity_user($db),$parent,$db);
    }

    print "<html><head><script>top.get_dialog_frame(\"".$frame .
          "\").contentWindow.finish_add_image();";
    print '</script></head><body></body></html>';
}

function process_uploaded_image()
{
    global $image_dir,$attr_image_dir,$images_parent_type,$image_subdir_prefix;
    global $use_dynamic_images;

    $parent = get_form_field('Parent');
    $filename = get_form_field('Filename');
    $caption = get_form_field('Caption');
    $title = get_form_field('Title');
    $alt_text = get_form_field('AltText');
    $no_update = get_form_field('NoUpdate');
    if ($no_update == 'true') $insert_db_flag = false;
    else $insert_db_flag = true;
    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    $basedir = $image_dir.'/original/';
    $original_filename = build_image_filename($basedir,$filename,$error_msg);
    if (! $original_filename) {
       log_error($error_msg);   http_response(422,$error_msg);   return;
    }
    if ($image_subdir_prefix) {
       $old_filename = $basedir.$filename;
       if (! rename($old_filename,$original_filename)) {
          $error_msg = 'Unable to rename '.$old_filename.' to ' .
                       $original_filename;
          log_error($error_msg);   http_response(422,$error_msg);   return;
       }
       log_activity('Renamed Uploaded Image '.$old_filename.' to ' .
                    $original_filename);
    }
    $config_values = load_config_values();
    if ($config_values === null) return;
    else if (! $config_values) {
       http_response(422,'Image Size Information has not been configured');
       return;
    }
    if ($use_dynamic_images) {
       if (! file_exists($original_filename)) $image_size = null;
       else $image_size = @getimagesize($original_filename);
       if (! $image_size) {
          $error_msg = 'Unable to get size information for image ' .
                       $original_filename;
          process_error($error_msg,0);   return;
       }
       $orig_width = $image_size[0];
       $orig_height = $image_size[1];
       if ($insert_db_flag) {
          if (! add_image_record($filename,$parent,$caption,$title,$alt_text,
                                 $orig_width,$orig_height,true)) return;
       }
       else if (! update_image_record($filename,$orig_width,$orig_height,true))
          return;
    }
    else if (! process_image($filename,$original_filename,$parent,$caption,
                             $title,$alt_text,$config_values,$insert_db_flag,
                             true))
       return;

    http_response(201,'Processed Uploaded Image');
    log_activity('Processed Uploaded Image '.$filename.' for ' .
                 image_type_name($images_parent_type).' #'.$parent);
    if ($images_parent_type == 1) {
       $db = new DB;
       write_product_activity('Uploaded Image '.$filename .
                              get_product_activity_user($db),$parent,$db);
    }
}

function update_image_file()
{
    global $image_dir,$attr_image_dir,$images_parent_type,$image_subdir_prefix;
    global $use_dynamic_images;

    $filename = get_form_field('Filename');

    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    $original_filename = $image_dir.'/original/';
    if ($image_subdir_prefix)
       $original_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $original_filename .= $filename;
    $config_values = load_config_values();
    if ($config_values === null) return;
    else if (! $config_values) {
       process_error('Image Size Information has not been configured',0);
       return;
    }
    if ($use_dynamic_images) {
       if (! file_exists($original_filename)) $image_size = null;
       else $image_size = @getimagesize($original_filename);
       if (! $image_size) {
          $error_msg = 'Unable to get size information for image ' .
                       $original_filename;
          process_error($error_msg,0);   return;
       }
       $orig_width = $image_size[0];
       $orig_height = $image_size[1];
       if (! update_image_record($server_filename,$orig_width,
                                 $orig_height,true))
          return;
    }
    else if (! process_image($filename,$original_filename,null,null,null,null,
                             $config_values,false,true))
       return;

    http_response(201,'Updated Image');
    log_activity('Updated '.image_type_name($images_parent_type) .
                 ' Image '.$filename);
}

function edit_image()
{
    global $php_url,$script_url,$images_parent_type,$shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $db = new DB;
    $id = get_form_field('id');
    if ($images_parent_type == 2)
       $query = 'select * from images where parent_type=2 and parent=?';
    else $query = 'select * from images where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Image not found',0);
       $db->close();   return;
    }
    $frame = get_form_field('Frame');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file($script_url);
    $dialog->add_style_sheet('image.css');
    $dialog->add_script_file('image.js');
    $dialog_title = 'Edit Image (#'.$row['id'].')';
    $dialog->set_body_id('edit_image');
    $dialog->set_help('edit_image');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update',$prefix.'images/Update.png',
                        'update_image();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form($php_url,'EditImage');
    $dialog->start_field_table();
    $dialog->add_hidden_field('id',get_row_value($row,'id'));
    $dialog->add_hidden_field('filename',get_row_value($row,'filename'));
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_text_row('Filename:',get_row_value($row,'filename'));
    $dialog->add_edit_row('Caption:','caption',$row,50);
    $dialog->add_edit_row('Title:','title',$row,50);
    $dialog->add_edit_row('Alt Text:','alt_text',$row,50);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
    $db->close();
}

function update_image()
{
    global $images_parent_type;

    $db = new DB;
    $image_record = image_record_definition();
    $db->parse_form_fields($image_record);
    if (! $db->update('images',$image_record)) {
       http_response(422,$db->error);   $db->close();   return;
    }
    http_response(201,'Image Updated');
    log_activity('Updated '.image_type_name($images_parent_type).' Image ' .
                 $image_record['filename']['value']);
    $db->close();
}

function get_image_info()
{
    global $image_dir,$attr_image_dir,$images_parent_type,$docroot;
    global $image_subdir_prefix;

    if ($images_parent_type == 2) {
       $image_dir = $attr_image_dir;   $config_name = 'attrimage_crop_ratio';
    }
    else $config_name = 'image_crop_ratio';
    $filename = get_form_field('Filename');
    $original_filename = $image_dir.'/original/';
    if ($image_subdir_prefix)
       $original_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $original_filename .= $filename;
    $image_filename = substr($original_filename,strlen($docroot));
    print "image_filename = '".$image_filename."';";
    if (! file_exists($original_filename)) $image_size = null;
    else $image_size = @getimagesize($original_filename);
    if (! $image_size) return;
    print 'image_width = '.$image_size[0].';';
    print 'image_height = '.$image_size[1].';';
    $db = new DB;
    $query = 'select config_value from config where config_name=?';
    $query = $db->prepare_query($query,$config_name);
    $row = $db->get_record($query);
    if ($row) print "crop_ratio = '".$row['config_value']."';";
    $db->close();
}

function delete_image_files($filename,&$error)
{
    global $image_dir,$attr_image_dir,$images_parent_type,$image_subdir_prefix;
    global $use_dynamic_images;

    if ($images_parent_type == 2) $image_dir = $attr_image_dir;
    $original_filename = $image_dir.'/original/';
    if ($image_subdir_prefix)
       $original_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $original_filename .= $filename;
    if (file_exists($original_filename) && (! unlink($original_filename))) {
       $error = 'Unable to delete image '.$original_filename;   return false;
    }
    if ($use_dynamic_images) return true;

    $zoom_filename = $image_dir.'/zoom/';
    if ($image_subdir_prefix)
       $zoom_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $zoom_filename .= $filename;
    if (file_exists($zoom_filename) && (! unlink($zoom_filename))) {
       $error = 'Unable to delete image '.$zoom_filename;   return false;
    }
    $large_filename = $image_dir.'/large/';
    if ($image_subdir_prefix)
       $large_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $large_filename .= $filename;
    if (file_exists($large_filename) && (! unlink($large_filename))) {
       $error = 'Unable to delete image '.$large_filename;   return false;
    }
    $medium_filename = $image_dir.'/medium/';
    if ($image_subdir_prefix)
       $medium_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $medium_filename .= $filename;
    if (file_exists($medium_filename) && (! unlink($medium_filename))) {
       $error = 'Unable to delete image '.$medium_filename;   return false;
    }
    $small_filename = $image_dir.'/small/';
    if ($image_subdir_prefix)
       $small_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $small_filename .= $filename;
    if (file_exists($small_filename) && (! unlink($small_filename))) {
       $error = 'Unable to delete image '.$small_filename;   return false;
    }

    return true;
}

function delete_image()
{
    global $images_parent_type,$remove_image_files,$shopping_cart;

    $db = new DB;
    if (! isset($remove_image_files)) $remote_image_files = false;

    $id = get_form_field('id');
    if ($images_parent_type == 2) {
       $query = 'select id from images where parent_type=2 and parent=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! $row) {
          if (isset($db->error)) http_response(422,$db->error);
          else http_response(409,'Image not found');
          $db->close();   return;
       }
       $id = $row['id'];
    }
    $filename = get_form_field('Filename');

    if ($remove_image_files) {
       $query = 'select count(id) as num_images from images where ' .
                '(filename=?) and (parent_type=?)';
       $query = $db->prepare_query($query,$filename,$images_parent_type);
       $row = $db->get_record($query);
       if (! $row) {
          if (isset($db->error)) http_response(422,$db->error);
          else http_response(409,'Image not found');
          $db->close();   return;
       }
       $num_images = $row['num_images'];
       if (($num_images == 1) && (! delete_image_files($filename,$error))) {
          $db->close();   http_response(422,$error);   return;
       }
    }

    if ($images_parent_type == 1) {
       $query = 'select parent from images where id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! empty($row['parent'])) $product_id = $row['parent'];
       else $product_id = -1;
    }

    $image_record = image_record_definition();
    $image_record['id']['value'] = $id;
    if (! $db->delete('images',$image_record)) {
       http_response(422,$db->error);   $db->close();   return;
    }
    http_response(201,'Image Deleted');
    if ($remote_image_files && ($num_images == 1))
       $activity = 'Deleted '.image_type_name($images_parent_type) .
                   ' Image '.$filename;
    else $activity = 'Deleted Image Record for '.$filename;
    if (($images_parent_type == 1) && ($product_id != -1))
       $activity .= ' for Product #'.$product_id;
    log_activity($activity);
    if ($images_parent_type == 1) {
       if ($shopping_cart && ($product_id != -1)) {
          require_once 'shopping-common.php';
          call_shopping_event('delete_image',array($db,$product_id,$filename));
       }
       write_product_activity('Deleted Product Image '.$filename.' by ' .
                              get_product_activity_user($db),$product_id,$db);
    }
    $db->close();
}

function copy_image_records($parent_type,$old_parent,$new_parent,$db=null)
{
    global $product_label;

    if (! $db) $db = new DB;
    $query = 'select * from images where parent_type=? and parent=?';
    $query = $db->prepare_query($query,$parent_type,$old_parent);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return false;
       }
       return true;
    }
    $image_record = image_record_definition();
    while ($row = $db->fetch_assoc($result)) {
       reset($image_record);
       while (list($field_name,$field_info) = each($image_record))
          $image_record[$field_name]['value'] = $row[$field_name];
       unset($image_record['id']['value']);
       $image_record['parent']['value'] = $new_parent;
       if (! $db->insert('images',$image_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    $db->free_result($result);
    log_activity('Copied Images for '.image_type_name($parent_type).' #' .
                 $old_parent.' to '.image_type_name($parent_type).' #' .
                 $new_parent);
    return true;
}

function delete_images($parent_type,$parent,&$error,$db)
{
    $query = 'select i.filename,(select count(id) from images ni where ' .
             'ni.filename=i.filename) as num_images from images i where ' .
             'i.parent_type=? and i.parent=?';
    $query = $db->prepare_query($query,$parent_type,$parent);
    $result = $db->query($query);
    if (! $result) {
       $error = $db->error;   return false;
    }
    while ($row = $db->fetch_assoc($result)) {
       if ($row['num_images'] == 1) {
          if (! delete_image_files($row['filename'],$error)) return false;
          log_activity('Deleted '.image_type_name($parent_type) .
                       ' Image '.$row['filename']);
       }
    }
    $db->free_result($result);

    $query = 'delete from images where parent_type=? and parent=?';
    $query = $db->prepare_query($query,$parent_type,$parent);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    return true;
}

function resequence_images()
{
    $parent_type = get_form_field('ParentType');
    $parent = get_form_field('Parent');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');
    if ((! $parent_type) && (! $parent)) return sequence_images();

    $db = new DB;

    $query = 'select id,sequence from images where parent_type=? and ' .
             'parent=? order by sequence,id';
    $query = $db->prepare_query($query,$parent_type,$parent);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       $db->close();   return false;
    }
    while ($row = $db->fetch_assoc($result)) {
       $current_sequence = $row['sequence'];   $updated_sequence = $current_sequence;
       if ($current_sequence == $old_sequence) $updated_sequence = $new_sequence;
       else if ($old_sequence > $new_sequence) {
          if (($current_sequence >= $new_sequence) && ($current_sequence < $old_sequence))
             $updated_sequence = $current_sequence + 1;
       }
       else {
          if (($current_sequence > $old_sequence) && ($current_sequence <= $new_sequence))
             $updated_sequence = $current_sequence - 1;
       }
       if ($updated_sequence != $current_sequence) {
          $query = 'update images set sequence=? where id=?';
          $query = $db->prepare_query($query,$updated_sequence,$row['id']);
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             http_response(422,'Database Error: '.$db->error);
             $db->close();   return;
          }
       }
    }
    $db->free_result($result);

    http_response(201,'Images Resequenced');
    log_activity('Resequenced Image #'.$old_sequence.' to #'.$new_sequence.' for ' .
                 image_type_name($parent_type).' #'.$parent);
    $db->close();
}

function sequence_images()
{
    $db = new DB;

    $query = 'select * from images order by parent_type,parent,sequence,id';
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) print 'Database Error: '.$db->error."<br>\n";
       else print "No Images Found<br>\n";
       $db->close();   return;
    }
    $current_type = -1;
    while ($row = $db->fetch_assoc($result)) {
       if ($current_type != $row['parent_type']) {
          $current_type = $row['parent_type'];   $current_parent = -1;
       }
       if ($current_parent != $row['parent']) {
          $current_parent = $row['parent'];   $sequence = 1;
       }
       else $sequence++;
       $query = 'update images set sequence=? where id=?';
       $query = $db->prepare_query($query,$sequence,$row['id']);
       $update_result = $db->query($query);
       if (! $update_result) print 'Database Error: '.$db->error."<br>\n";
    }
    $db->free_result($result);

    print 'Images Sequenced<br>';
    log_activity('Sequenced Images');
    $db->close();
}

function load_image()
{
    global $image_dir,$image_subdir_prefix;

    $filename = get_form_field('filename');
    if ((strpos($filename,'..') !== false) ||
        (strpos($filename,'/') !== false)) exit;
    if (! $filename) $filename = get_form_field('amp;filename');
    if (! $filename) $filename = 'no-image-found.jpg';
    $size = get_form_field('size');
    if (! $size) $size = get_form_field('amp;size');
    if (! $size) {
       $error_msg = 'No size specified in load_image';
       log_error($error_msg);   http_response(406,$error_msg);   return;
    }
    $db = new DB;
    $config_values = load_config_values($db);
    if ($config_values === null) return;
    else if (! $config_values) {
       http_response(410,'Image Size Information has not been configured');
       $db->close();   return;
    }
    $original_filename = $image_dir.'/original/';
    if ($image_subdir_prefix)
       $original_filename .= substr($filename,0,$image_subdir_prefix).'/';
    $original_filename .= $filename;
    if (! file_exists($original_filename)) {
       if ($filename == 'no-image-found.jpg') $filename = null;
       else {
          $filename = 'no-image-found.jpg';
          $original_filename = $image_dir.'/original/';
          if ($image_subdir_prefix)
             $original_filename .= substr($filename,0,$image_subdir_prefix).'/';
          $original_filename .= $filename;
          if (! file_exists($original_filename)) $filename = null;
       }
       if (! $filename) {
          $error_msg = "No \"No Image Found\" Image found in load_image";
          log_error($error_msg);   http_response(406,$error_msg);
          $db->close();   return;
       }
    }
    process_image(null,$original_filename,null,null,null,null,$config_values,
                 false,true,$size);
    $db->close();
}

if ($dynamic_images) {
   $cmd = get_form_field('cmd');
   if ($cmd == 'loadimage') load_image();
}

?>
