<?php
/*
            Inroads Control Panel/Shopping Cart - Admin Tab - Import Data Processing

                           Written 2008-2019 by Randall Severy
                            Copyright 2008-2019 Inroads, LLC
*/

if (isset($argc) && ($argc > 1)) {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
}
require_once 'image.php';
require_once 'utility.php';
require_once 'eximport-common.php';
if (file_exists('custom-config.php')) require_once 'custom-config.php';
else if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

define('MAX_IMAGE_COUNT',200);
define('MAX_RECORD_COUNT',1000000);

if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/importdata.php')) $shopping_cart = true;
   else $shopping_cart = false;
}
if (isset($argc) && ($argc > 1)) {
   if ($shopping_cart) require_once 'cartconfig-common.php';
   else require_once 'catalog-common.php';
}

function import_data()
{
    global $cart_cookie,$shopping_cart;

    $db = new DB;
    $db_tables = $db->list_db_tables();
    if (! $db_tables) return;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog = new Dialog;
    $dialog->add_script_file($prefix.'importdata.js');
    $dialog->add_script_file($prefix.'eximport-common.js');
    $dialog->set_body_id('import_data');
    $dialog->set_help('import_data');
    $dialog->set_onload_function('import_data_onload();');
    $dialog->start_body('Import Data');
    $dialog->start_button_column(false,true);
    $dialog->add_button('Import',$prefix.'images/Import.png',
                        'process_import();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_field_table();
    $dialog->write("<form method=\"POST\" action=\"admin.php\" " .
                   "name=\"ImportData\" encType=\"multipart/form-data\">\n");
    $dialog->add_hidden_field('cmd','processimport');

    $dialog->start_row('Options:','top');
    $dialog->add_checkbox_field('FieldNames',
                                '&nbsp;First line contains field names',true);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('ConvertDates',
                                '&nbsp;Convert date fields to timestamps',true);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('DeleteRecords',
                                '&nbsp;Delete existing table records',false);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('ImportImages','&nbsp;Import Images',
                                false,'enable_import_images(this);');
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('DeleteMatching',
                                '&nbsp;Delete matching table records',false);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('DoNotAdd',
                                '&nbsp;Do not add non-matching table records',
                                false);
    $dialog->end_row();

    $dialog->start_hidden_row('Image Directory:','imagedir_row',true);
    $dialog->add_input_field('ImageDir','',35);
    $dialog->end_row();

    $dialog->start_hidden_row('Image Type:','imagetype_row',true,'middle');
    $dialog->add_radio_field('ImageType','0','&nbsp;Category',true,null);
    $dialog->write('&nbsp;');
    $dialog->add_radio_field('ImageType','1','&nbsp;Product',false,null);
    $dialog->write('&nbsp;');
    $dialog->add_radio_field('ImageType','2','&nbsp;Attribute',false,null);
    $dialog->write('&nbsp;');
    $dialog->add_radio_field('ImageType','-1','&nbsp;Other:',false,null);
    $dialog->write("<input type=\"text\" class=\"text\" name=\"OtherImageType\" " .
                   "size=\"2\">\n");
    $dialog->end_row();

    $dialog->start_hidden_row('Image Options:','imageoptions_row',true,
                              'middle');
    $dialog->add_checkbox_field('ReplaceImages',
                                '&nbsp;Replace Existing Images',false);
    $dialog->end_row();

    add_module_row($dialog);

    $dialog->start_row('Database Table:','middle');
    $dialog->start_choicelist('Table');
    $dialog->add_list_item('','',true);
    if (isset($cart_cookie)) {
//       $dialog->add_list_item('*catsub','categories AND subcategories',false);
//       $dialog->add_list_item('*prodinv','products AND inventory',false);
    }
    while (list($index,$table_name) = each($db_tables))
       $dialog->add_list_item($table_name,$table_name,false);
    $dialog->end_choicelist();
    $dialog->end_row();

    $dialog->start_row('Upload Import File:','middle');
    $dialog->write("<input type=\"file\" name=\"Filename\" size=\"35\" " .
                   "class=\"browse_button\">\n");
    $dialog->end_row();

    $dialog->end_form();
    $dialog->end_field_table();
    $dialog->end_body();
}

function process_import()
{
    global $shopping_cart;

    set_time_limit(0);
    ini_set('memory_limit',-1);
    $upload_error = 0;
    $filename = $_FILES['Filename']['name'];
    $temp_name = $_FILES['Filename']['tmp_name'];
    $file_type = $_FILES['Filename']['type'];
    if (isset($_FILES['Filename']['error']))
       $upload_error = $_FILES['Filename']['error'];
    if ($upload_error) {
       switch ($upload_error) {
          case 1:
             $error = 'The uploaded file '.$filename .
                      ' exceeds the upload_max_filesize directive';
             break;
          case 3:
             $error = 'The uploaded file '.$filename .
                      ' was only partially uploaded';
             break;
          case 4:
             $error = 'No file was uploaded in Upload request';   break;
          case 7:
             $error = 'The uploaded file '.$filename .
                      ' could not be written to disk';
             break;
          default:
             $error = 'Unknown Upload Error #'.$upload_error.' for file ' .
                      $filename;
       }
       process_error($error,-1);   return;
    }
    if (! $temp_name) {
       process_error('No Upload File Found',-1);   return;
    }
    $field_names = get_form_field('FieldNames');
    $convert_dates = get_form_field('ConvertDates');
    $delete_records = get_form_field('DeleteRecords');
    $delete_matching = get_form_field('DeleteMatching');
    $do_not_add = get_form_field('DoNotAdd');
    $table = get_form_field('Table');
    $import_images = get_form_field('ImportImages');
    if ($import_images) {
       $image_source_dir = get_form_field('ImageDir');
       $image_type = get_form_field('ImageType');
       if ($image_type == -1) $image_type = get_form_field('OtherImageType');
       $replace_images = get_form_field('ReplaceImages');
       if ($table != 'images') {
          process_error('Images can only be imported into the images table',-1);
          return;
       }
    }
    $temp_filename = tempnam('import','import');
    if (! $temp_filename) {
       process_error('Unable to create temporary Import File',-1);   return;
    }
    if (! move_uploaded_file($temp_name,$temp_filename)) {
       log_error('Attempted to move '.$temp_name.' to '.$temp_filename);
       process_error('Unable to save uploaded file',-1);   return;
    }
    if (! file_exists($temp_filename)) {
       process_error('Import File '.$temp_filename.' does not exist',-1);
       return;
    }
    $extension = pathinfo($filename,PATHINFO_EXTENSION);
    if ($extension == 'xls') $format = 'Excel5';
    else if ($extension == 'xlsx') $format = 'Excel2007';
    else if ($extension == 'xlsm') $format = 'Excel2007';
    else if ($extension == 'csv') $format = 'CSV';
    else if ($extension == 'txt') $format = 'CSV';
    else {
       process_error('Unsupported Spreadsheet Format in '.$filename,-1);
       return;
    }

    require_once '../engine/excel.php';
    $reader = PHPExcel_IOFactory::createReader($format);
    if ($format == 'CSV')
       PHPExcel_Cell::setValueBinder(new BindValueAsString());
    if ($extension == 'txt') $reader->setDelimiter("\t");
    if ($field_names) $stop_row = 4;
    else $stop_row = 3;
    $row_filter = new RowFilter($stop_row);
    $reader->setReadFilter($row_filter);
    $excel = $reader->load($temp_filename);
    $worksheet = $excel->setActiveSheetIndex(0);
    $data = $worksheet->toArray(null,true,false,false);
    if (! $data) {
       process_error('Unable to load data from '.$filename,-1);
       return;
    }

    if ($field_names) {
       $import_field_info = $data[0];   $sample_start = 1;
    }
    else $sample_start = 2;
    if (isset($data[$sample_start])) $first_sample = $data[$sample_start];
    else $first_sample = null;
    if ($field_names) $num_fields = count($import_field_info);
    else $num_fields = count($first_sample);
    if (isset($data[$sample_start + 1])) $second_sample = $data[$sample_start + 1];
    else $second_sample = null;

    $module = get_form_field('Module');
    if ($module) set_module_db_info($module);
    $db = new DB;

    if ($table == '*catsub') {
       $db_fields = $db->get_field_defs('categories');
       $sub_field_defs = $db->get_field_defs('subcategories');
       unset($sub_field_defs['id']);
       unset($sub_field_defs['related_id']);
       $sub_db_fields = array();
       while (list($db_field_name,$db_field_info) = each($sub_field_defs))
          $sub_db_fields['*'.$db_field_name] = $db_field_info;
       $db_fields = array_merge($db_fields,$sub_db_fields);
    }
    else if ($table == '*prodinv') {
       $features = get_cart_config_value('features',$db);
       $db_fields = $db->get_field_defs('products');
       if (! ($features & LIST_PRICE_PRODUCT))
          unset($db_fields['list_price']);
       if (! ($features & REGULAR_PRICE_PRODUCT))
          unset($db_fields['price']);
       if (! ($features & SALE_PRICE_PRODUCT))
          unset($db_fields['sale_price']);
       if (! ($features & PRODUCT_COST_PRODUCT))
          unset($db_fields['cost']);
       if (! ($features & REGULAR_PRICE_BREAKS)) {
          unset($db_fields['price_break_type']);
          unset($db_fields['price_breaks']);
       }
       $inv_field_defs = $db->get_field_defs('product_inventory');
       unset($inv_field_defs['id']);
       unset($inv_field_defs['sequence']);
       unset($inv_field_defs['parent']);
       if (! ($features & USE_PART_NUMBERS))
          unset($inv_field_defs['part_number']);
       if (! ($features & MAINTAIN_INVENTORY)) {
          unset($inv_field_defs['qty']);
          unset($inv_field_defs['min_qty']);
       }
       if (! ($features & WEIGHT_ITEM))
          unset($inv_field_defs['weight']);
       if (! ($features & LIST_PRICE_INVENTORY))
          unset($inv_field_defs['list_price']);
       if (! ($features & REGULAR_PRICE_INVENTORY))
          unset($inv_field_defs['price']);
       if (! ($features & SALE_PRICE_INVENTORY))
          unset($inv_field_defs['sale_price']);
       if (! ($features & PRODUCT_COST_INVENTORY))
          unset($inv_field_defs['cost']);
       if (! ($features & DROP_SHIPPING))
          unset($inv_field_defs['origin_zip']);
       $inv_db_fields = array();
       while (list($db_field_name,$db_field_info) = each($inv_field_defs))
          $inv_db_fields['*'.$db_field_name] = $db_field_info;
       $db_fields = array_merge($db_fields,$inv_db_fields);
    }
    else $db_fields = $db->get_field_defs($table);

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet($prefix.'importdata.css');
    $dialog->add_script_file($prefix.'importdata.js');
    if ($shopping_cart) {
       $head_block = "<script type=\"text/javascript\">\n" .
                     "      script_prefix = '../cartengine/';\n" .
                     "    </script>";
       $dialog->add_head_line($head_block);
    }
    $dialog->set_onload_function('process_import_data_onload();');
    $dialog->set_body_id('process_import');
    $dialog->set_help('process_import');
    $dialog->start_body('Import Data');
    $dialog->start_content_area(true);
    $dialog->start_form('admin.php','ImportData');
    $dialog->add_hidden_field('ImportFilename',$temp_filename);
    $dialog->add_hidden_field('Extension',$extension);
    $dialog->add_hidden_field('Format',$format);
    if ($module) $dialog->add_hidden_field('Module',$module);
    $dialog->add_hidden_field('Table',$table);
    $dialog->add_hidden_field('NumFields',$num_fields);
    if ($field_names) $dialog->add_hidden_field('FieldNames',$field_names);
    if ($convert_dates)
       $dialog->add_hidden_field('ConvertDates',$convert_dates);
    if ($delete_records)
       $dialog->add_hidden_field('DeleteRecords',$delete_records);
    if ($delete_matching)
       $dialog->add_hidden_field('DeleteMatching',$delete_matching);
    if ($do_not_add) $dialog->add_hidden_field('DoNotAdd',$do_not_add);
    if ($import_images) {
       $dialog->add_hidden_field('ImportImages',$import_images);
       $dialog->add_hidden_field('ImageDir',$image_source_dir);
       $dialog->add_hidden_field('ImageType',$image_type);
       $dialog->add_hidden_field('ReplaceImages',$replace_images);
    }

    if (! $dialog->skin)
       $dialog->write("<div id=\"import_div\" class=\"import_div\">\n");
    $dialog->write("<table border=\"1\" cellpadding=\"2\" cellspacing=\"0\" ");
    $dialog->write("class=\"");
    if ($dialog->skin) $dialog->write('fieldtable ');
    $dialog->write("import_table\">\n");
    $dialog->write('<tr><td colspan=');
    $num_columns = 4;
    if ($field_names) $num_columns++;
    $dialog->write($num_columns);
    $dialog->write(" align=center class=\"import_header\">");
    $dialog->write('Import Field Assignments');
    $dialog->write("<div class=\"unmap_link\"><a href=\"#\" onClick=\"" .
                   "unmap_all_fields(); return false;\">" .
                   "Unmap All Fields</a></div>\n");
    $dialog->write("</td></tr>\n");
    $dialog->write('<tr>');
    if ($field_names) $dialog->write('<th>Import Field</th>');
    $dialog->write("<th>Update Field</th>\n");
    $dialog->write("<th>Key</th>\n");
    $dialog->write("<th>Sample</th>\n");
    $dialog->write("<th>Sample</th></tr>\n");

    for ($loop = 0;  $loop < $num_fields;  $loop++) {
       $dialog->write('<tr>');
       $field_name = $import_field_info[$loop];
       if (strlen($field_name) > 30) {
          $display_name = substr($field_name,0,30).'...';
          $title = str_replace('"','&quot;',$field_name);
       }
       else {
          $display_name = $field_name;   $title = null;
       }
       if ($field_names) {
          $dialog->write("<td style=\"padding-left:5px;\" nowrap");
          if ($title) $dialog->write(" title=\"".$title."\"");
          $dialog->write(">".$display_name."</td>\n");
       }
       $dialog->write("<td align=\"center\">");
       $dialog->start_choicelist('Update_'.$loop,null);
       $dialog->add_list_item('','',true);
       reset($db_fields);   $key_field = false;
       while (list($db_field_name,$db_field_info) = each($db_fields)) {
          if ($db_field_name[0] == '*') $display_field_name = substr($db_field_name,1);
          else $display_field_name = $db_field_name;
          $dialog->add_list_item($db_field_name,$display_field_name,
                                 $db_field_name == $import_field_info[$loop]);
          if (($db_field_name == $import_field_info[$loop]) &&
              isset($db_field_info['key'])) $key_field = true;
       }
       $dialog->end_choicelist();
       $dialog->write("</td>\n<td align=center>");
       $dialog->add_checkbox_field('Key_'.$loop,'',$key_field);
       $dialog->write("</td>\n<td style=\"padding-left:5px;\">");
       if ($first_sample && isset($first_sample[$loop]) &&
           ($first_sample[$loop] != ''))
          $dialog->write(htmlspecialchars(substr($first_sample[$loop],0,30)));
       else $dialog->write('&nbsp;');
       $dialog->write("</td>\n<td style=\"padding-left:5px;\">");
       if ($second_sample && isset($second_sample[$loop]) &&
           ($second_sample[$loop] != ''))
          $dialog->write(htmlspecialchars(substr($second_sample[$loop],0,30)));
       else $dialog->write('&nbsp;');
       $dialog->write("</td></tr>\n");
    }

    $dialog->write('</table>');
    if (! $dialog->skin) $dialog->write("</div>\n");
    $dialog->end_content_area(true);

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write('<table cellspacing=0 cellpadding=0 border=0 ');
       $dialog->write("align=\"center\" style=\"margin-top: 10px;\"><tr>");
       $dialog->write("  <td style=\"padding-right: 10px;\">\n");
    }
    $dialog->add_dialog_button('Import',$prefix.'images/Import.png',
                               'finish_import();');
    if (! $dialog->skin)
       $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    $dialog->add_dialog_button('Cancel',$prefix.'images/Update.png',
                               'cancel_import();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr></table>\n");

    $dialog->end_form();
    $dialog->end_body();
    if ($module) restore_db_info();
}

function delete_table_records($db,$table,$db_fields)
{
    $query = 'delete from '.$table;
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }
    log_activity('Deleted All Records from Table '.$table);
    reset($db_fields);
    list($first_field_name,$first_field_def) = each($db_fields);
    if (isset($first_field_def['auto'])) {
       $query = 'alter table '.$table.' auto_increment=0';
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return false;
       }
       log_activity('Reset Auto Increment for Table '.$table);
    }
    return true;
}

function delete_product_info($db,$product_id)
{
    require_once 'sublist.php';
    require_once 'inventory.php';
    require_once 'seo.php';

    if (! delete_images(1,$product_id,$error,$db)) {
       http_response(422,$error);   return false;
    }
    if (! delete_sublist_items('product_attributes',$product_id,$db)) {
       http_response(422,$db->error);   return false;
    }
    if (! delete_inventory_records($product_id,$error,$db)) {
       http_response(422,$error);   return false;
    }
    if (! delete_sublist_items('related_products',$product_id)) {
       http_response(422,$db->error);   return false;
    }
    if (! delete_related_items('category_products',$product_id)) {
       http_response(422,$db->error);   return false;
    }
    if (! delete_related_items('related_products',$product_id)) {
       http_response(422,$db->error);   return false;
    }
    $query = 'delete from category_products where related_id='.$product_id;
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }
    log_activity('Deleted Product Info for Product ID #'.$product_id);
    return true;
}

function parse_import_date($date_string)
{
    $space_pos = strpos($date_string,' ');
    if ($space_pos === false) {
       $date_part = $date_string;   $time_part = '';
    }
    else {
       $date_part = substr($date_string,0,$space_pos);
       $time_part = substr($date_string,$space_pos + 1);
    }
    $date_info = explode('/',$date_part);
    if (count($date_info) != 3) return $date_string;
    $time_info = explode(':',$time_part);
    if (count($time_info) == 3) {
       $hours = intval($time_info[0]);   $minutes = intval($time_info[1]);
       $seconds = intval($time_info[2]);
    }
    else if (count($time_info) == 2) {
       $hours = intval($time_info[0]);   $minutes = intval($time_info[1]);
       $seconds = 0;
    }
    else {
       $hours = 12;   $minutes = 0;   $seconds = 0;
    }
    $date_value = mktime($hours,$minutes,$seconds,intval($date_info[0]),
                         intval($date_info[1]),intval($date_info[2]));
    return $date_value;
}

function finish_import($bg_data)
{
    global $image_dir,$images_parent_type,$shopping_cart;
    global $max_image_count,$max_record_count,$convert_date_fields;

    set_time_limit(0);
    ignore_user_abort(true);
    ini_set('memory_limit',-1);
    if ($bg_data) {
       $data_fields = explode('|',rawurldecode($bg_data));
       $import_page = $data_fields[0];
       $num_pages = $data_fields[1];
       $import_filename = $data_fields[2];
       $extension = $data_fields[3];
       $format = $data_fields[4];
       $field_names = $data_fields[5];
       $convert_dates = $data_fields[6];
       $delete_records = $data_fields[7];
       $delete_matching = $data_fields[8];
       $import_images = $data_fields[9];
       $image_source_dir = $data_fields[10];
       $image_type = $data_fields[11];
       $replace_images = $data_fields[12];
       $table = $data_fields[13];
       $num_fields = $data_fields[14];
       $do_not_add = $data_fields[15];
       $data_field_offset = 16;
    }
    else {
       $import_filename = get_form_field('ImportFilename');
       $extension = get_form_field('Extension');
       $format = get_form_field('Format');
       $field_names = get_form_field('FieldNames');
       $convert_dates = get_form_field('ConvertDates');
       $delete_records = get_form_field('DeleteRecords');
       $delete_matching = get_form_field('DeleteMatching');
       $do_not_add = get_form_field('DoNotAdd');
       $import_images = get_form_field('ImportImages');
       if ($import_images) {
          $image_source_dir = get_form_field('ImageDir');
          $image_type = get_form_field('ImageType');
          $replace_images = get_form_field('ReplaceImages');
       }
       $table = get_form_field('Table');
       $num_fields = get_form_field('NumFields');
    }
    $update_fields = array();
    $key_fields = array();
    if ($import_images) $image_fields = array();
    $num_update_fields = 0;
    if ($import_images) {
       $parent_index = -1;
       $filename_index = -1;
       $description_index = -1;
    }
    if ($table == '*prodinv') {
       $include_inventory = true;   $table = 'products';
    }
    else $include_inventory = false;
    for ($loop = 0;  $loop < $num_fields;  $loop++) {
       if ($bg_data) {
          $update_fields[$loop] = $data_fields[$data_field_offset +
                                               ($loop * 2)];
          $key_fields[$loop] = $data_fields[$data_field_offset + 1 +
                                            ($loop * 2)];
       }
       else {
          $update_fields[$loop] = get_form_field('Update_'.$loop);
          $key_fields[$loop] = get_form_field('Key_'.$loop);
       }
       if ($update_fields[$loop]) {
          $num_update_fields++;
          if ($import_images) {
            if ($update_fields[$loop] == 'parent') $parent_index = $loop;
            else if ($update_fields[$loop] == 'filename')
               $filename_index = $loop;
            else if ($update_fields[$loop] == 'description')
               $description_index = $loop;
          }
       }
    }

    if ($num_update_fields == 0) {
       if ($bg_data) log_error('You must select at least one Update Field');
       else http_response(406,'You must select at least one Update Field');
       return;
    }

    if (! file_exists($import_filename)) {
       $error_msg = 'Import File '.$import_filename.' not found';
       if ($bg_data) log_error($error_msg);
       else http_response(410,$error_msg);
       return;
    }
    require_once '../engine/excel.php';
    $reader = PHPExcel_IOFactory::createReader($format);
    if ($format == 'CSV')
       PHPExcel_Cell::setValueBinder(new BindValueAsString());
    if ($extension == 'txt') $reader->setDelimiter("\t");
    $excel = $reader->load($import_filename);
    $worksheet = $excel->setActiveSheetIndex(0);
    $data = $worksheet->toArray(null,true,false,false);
    if (! $data) {
       $error_msg = 'Unable to load data from '.$import_filename;
       if ($bg_data) log_error($error_msg);
       else http_response(422,$error_msg);
       return;
    }
    $num_import_lines = count($data);
    if ($field_names) {
       $import_field_info = $data[0];   $num_import_lines--;
    }

    $module = get_form_field('Module');
    if ($module) set_module_db_info($module);

    if ($import_images) {
       if (! isset($max_image_count)) $max_image_count = MAX_IMAGE_COUNT;
       if ($parent_index == -1) {
          http_response(406,'You must map an Import Field to the "parent" Update Field');
          return;
       }
       if ($filename_index == -1) {
          http_response(406,'You must map an Import Field to the "filename" Update Field');
          return;
       }
       if (! $bg_data) {
          if ($num_import_lines > $max_image_count) {
             if ($delete_records) {
                $db = new DB;
                $db_fields = $db->get_field_defs($table);
                if (! delete_table_records($db,$table,$db_fields)) {
                   @unlink($import_filename);   return;
                }
             }
             $num_pages = ceil($num_import_lines / $max_image_count);
             $data = '|'.$num_pages.'|'.$import_filename.'|'.$extension .
                     '|'.$format.'|'.$field_names.'|'.$convert_dates.'|' .
                     $delete_records.'|'.$delete_matching.'|'.$import_images .
                     '|'.$image_source_dir.'|'.$image_type.'|'.$replace_images .
                     '|'.$table.'|'.$num_fields.'|'.$do_not_add;
             for ($loop = 0;  $loop < $num_fields;  $loop++)
                $data .= '|'.$update_fields[$loop].'|'.$key_fields[$loop];
             if ($shopping_cart) $prefix = '../cartengine/';
             else $prefix = '';
             for ($import_page = 0;  $import_page < $num_pages;  $import_page++) {
                $command = $prefix.'importdata.php importdata ' .
                            rawurlencode($import_page.$data);
                $process = new Process($command);
                if ($process->return != 0) {
                   http_response(422,'Unable to start image import ('.$process->return.')');
                   return;
                }
                $counter = 0;
                while ($process->status()) {
                   if ($counter == 500) {
                      $process->stop();
                      http_response(422,'Image Import took too long');   return;
                   }
                   sleep(1);
                   $counter++;
                }
             }
             @unlink($import_filename);
             http_response(201,'Image Import Completed');
             log_activity('Imported Image Data into Table '.$table);
             return;
          }
       }

       init_images(null,null,$image_type);
       $config_values = load_config_values();
       if ($config_values === null) return;
       else if (! $config_values) {
          http_response(422,'Image Size Information has not been configured');
          return;
       }
    }
    else {
       if (! isset($max_record_count)) $max_record_count = MAX_RECORD_COUNT;
       if (! $bg_data) {
          if ($num_import_lines > $max_record_count) {
             if ($delete_records) {
                $db = new DB;
                $db_fields = $db->get_field_defs($table);
                if (! delete_table_records($db,$table,$db_fields)) {
                   @unlink($import_filename);   return;
                }
             }
             $num_pages = ceil($num_import_lines / $max_record_count);
             $data = '|'.$num_pages.'|'.$import_filename.'|'.$extension .
                     '|'.$format.'|'.$field_names.'|'.$convert_dates.'|' .
                     $delete_records.'|'.$delete_matching.'|||||'.$table.'|' .
                     $num_fields.'|'.$do_not_add;
             for ($loop = 0;  $loop < $num_fields;  $loop++)
                $data .= '|'.$update_fields[$loop].'|'.$key_fields[$loop];
             if ($shopping_cart) $prefix = '../cartengine/';
             else $prefix = '';
             for ($import_page = 0;  $import_page < $num_pages;  $import_page++) {
                $command = $prefix.'importdata.php importdata ' .
                            rawurlencode($import_page.$data);
                $process = new Process($command);
                if ($process->return != 0) {
                   http_response(422,'Unable to start data import ('.$process->return.')');
                   return;
                }
                $counter = 0;
                while ($process->status()) {
                   if ($counter == 500) {
                      $process->stop();
                      http_response(422,'Data Import took too long');   return;
                   }
                   sleep(1);
                   $counter++;
                }
             }
             @unlink($import_filename);
             http_response(201,'Data Import Completed');
             log_activity('Imported Data into Table '.$table);
             return;
          }
       }
    }

    $db = new DB;
    $db_fields = $db->get_field_defs($table);
    while (list($field_name,$field_def) = each($db_fields))
       unset($db_fields[$field_name]['key']);
    if ($include_inventory) {
       $inv_db_fields = $db->get_field_defs('product_inventory');
       while (list($field_name,$field_def) = each($inv_db_fields))
          unset($inv_db_fields[$field_name]['key']);
    }

    if ($delete_records && (! isset($import_page))) {
       if (! delete_table_records($db,$table,$db_fields)) {
          @unlink($import_filename);   return;
       }
       if ($include_inventory &&
           (! delete_table_records($db,'product_inventory',$inv_db_fields))) {
          @unlink($import_filename);   return;
       }
    }

    $check_update = false;
    if ($include_inventory) {
       $check_update_inventory = false;   $num_product_updates = 0;
    }
    if (! $delete_records) {
       for ($loop = 0;  $loop < $num_fields;  $loop++)
          if ($update_fields[$loop] && ($key_fields[$loop] == 'on')) {
             if ($include_inventory && ($update_fields[$loop][0] == '*')) {
                $inv_db_fields[substr($update_fields[$loop],1)]['key'] = true;
                $check_update_inventory = true;
             }
             else $db_fields[$update_fields[$loop]]['key'] = true;
             $check_update = true;
          }
          else if ($include_inventory && $update_fields[$loop] &&
                   ($update_fields[$loop][0] != '*')) $num_product_updates++;
    }

    if ($delete_matching && (! $check_update)) {
       http_response(406,'You must select at least one Key Field to delete matching records');
       return;
    }

    if ($bg_data) {
       if ($import_images) {
          $start_line = ($import_page * $max_image_count) + 1;
          $end_line = ($start_line + $max_image_count);
       }
       else {
          $start_line = ($import_page * $max_record_count) + 1;
          $end_line = ($start_line + $max_record_count);
       }
       log_activity('Starting Data Import ('.$start_line.'-' .
                    ($end_line - 1).') into Table '.$table);
    }
    else if ($field_names) $start_line = 2;
    else $start_line = 1;
    $import_line = 1;
    foreach ($data as $import_data) {
       if ((count($import_data) == 1) && ($import_data[0] == '')) {
          $import_line++;   continue;
       }
       if ($import_line < $start_line) {
          $import_line++;   continue;
       }
       if ($import_images) {
          $filename = $import_data[$filename_index];
          if ($filename != '') {
             $parent = $import_data[$parent_index];
             if ($description_index != -1)
                $description = $import_data[$description_index];
             else $description = '';
             $src_filename = $image_source_dir.'/'.$filename;
             $original_filename = $image_dir.'/original/'.$filename;
             if (file_exists($original_filename)) $image_exists = true;
             else $image_exists = false;
             if (($replace_images) && $image_exists) {
                $query = 'select id from images where parent='.$parent .
                         ' and parent_type='.$images_parent_type .
                         " and filename='".$filename."'";
                $row = $db->get_record($query);
                if ($row) $update_db_flag = false;
                else $update_db_flag = true;
             }
             else $update_db_flag = true;
             if ((! $replace_images) && $image_exists)
                log_error('Image file '.$original_filename .
                          ' already exists on the server');
             else if (! file_exists($src_filename))
                log_error('Source Image file '.$src_filename .
                          ' does not exist on the server');
             else if (! copy($src_filename,$original_filename))
                log_error('Unable to copy image '.$src_filename.' to ' .
                          $original_filename);
             else if (! process_image($filename,$original_filename,$parent,
                                      $description,$config_values,$update_db_flag))
                return;
             else if ($image_exists)
                log_activity('Updated Image '.$filename.' for ' .
                             image_type_name($images_parent_type).' #'.$parent);
             else log_activity('Added Image '.$filename.' to ' .
                               image_type_name($images_parent_type).' #'.$parent);
          }
       }
       else {
          for ($loop = 0;  $loop < $num_fields;  $loop++)
             if ($update_fields[$loop]) {
                if (! isset($import_data[$loop])) {
                   if ($db_fields[$update_fields[$loop]]['null'] == 'NO')
                      $db_fields[$update_fields[$loop]]['value'] = '';
                   else $db_fields[$update_fields[$loop]]['value'] = null;
                }
                else if ($include_inventory && ($update_fields[$loop][0] == '*'))
                   $inv_db_fields[substr($update_fields[$loop],1)]['value'] =
                      $import_data[$loop];
                else if ($convert_dates && isset($convert_date_fields[$table]) &&
                         in_array($update_fields[$loop],$convert_date_fields[$table]) &&
                         $import_data[$loop])
                   $db_fields[$update_fields[$loop]]['value'] =
                      parse_import_date($import_data[$loop]);
                else $db_fields[$update_fields[$loop]]['value'] = $import_data[$loop];
             }
          if ($delete_matching) {
             $row = $db->read($table,$db_fields);
             if ($row) {
                if (! $db->delete($table,$db_fields)) {
                   $error_msg = $db->error.' (import row '.$import_line.')';
                   if ($bg_data) log_error($error_msg);
                   else http_response(422,$error_msg);
                   @unlink($import_filename);   return;
                }
                if ($include_inventory && (! delete_product_info($db,$row['id']))) {
                   @unlink($import_filename);   return;
                }
             }
          }
          else if ($check_update && ($row = $db->read($table,$db_fields))) {
             if (((! $include_inventory) || ($num_product_updates > 0)) &&
                 (! $db->update($table,$db_fields))) {
                $error_msg = $db->error.' (import row '.$import_line.')';
                if ($bg_data) log_error($error_msg);
                else http_response(422,$error_msg);
                @unlink($import_filename);   return;
             }
             if ($include_inventory) {
                if (! $check_update_inventory) {
                   $inv_db_fields['parent']['value'] = $row['id'];
                   $inv_db_fields['parent']['key'] = true;
                }
                if ($db->read('product_inventory',$inv_db_fields)) {
                   if (! $db->update('product_inventory',$inv_db_fields)) {
                      $error_msg = $db->error.' (import row '.$import_line.')';
                      if ($bg_data) log_error($error_msg);
                      else http_response(422,$error_msg);
                      @unlink($import_filename);   return;
                   }
                }
                else if (! $do_not_add) {
                   if ($check_update_inventory)
                      $inv_db_fields['parent']['value'] = $row['id'];
                   if (! $db->insert('product_inventory',$inv_db_fields)) {
                      $error_msg = $db->error.' (import row '.$import_line.')';
                      if ($bg_data) log_error($error_msg);
                      else http_response(422,$error_msg);
                      @unlink($import_filename);   return;
                   }
                }
             }
          }
          else if (! $do_not_add) {
             if (isset($db->error)) {
                $error_msg = $db->error.' (import row '.$import_line.')';
                if ($bg_data) log_error($error_msg);
                else http_response(422,$error_msg);
                @unlink($import_filename);   return;
             }
             if (! $db->insert($table,$db_fields)) {
                $error_msg = $db->error.' (import row '.$import_line.')';
                if ($bg_data) log_error($error_msg);
                else http_response(422,$error_msg);
                @unlink($import_filename);   return;
             }
             if ($include_inventory) {
                $product_id = $db->insert_id();
                $inv_db_fields['parent']['value'] = $product_id;
                if (! $db->insert('product_inventory',$inv_db_fields)) {
                   $error_msg = $db->error.' (import row '.$import_line.')';
                   if ($bg_data) log_error($error_msg);
                   else http_response(422,$error_msg);
                   @unlink($import_filename);   return;
                }
             }
          }
       }
       $import_line++;
       if ($bg_data && ($import_line == $end_line)) break;
    }

    if (! $bg_data) @unlink($import_filename);

    if ($bg_data) {
       if ($import_line != $end_line) $end_line = $import_line;
       log_activity('Imported Data ('.$start_line.'-'.($end_line - 1) .
                    ') into Table '.$table);
    }
    else {
       http_response(201,'Import Completed');
       log_activity('Imported Data into Table '.$table);
    }

    if ($module) restore_db_info();
}

function cancel_import()
{
    $import_filename = get_form_field('Filename');
    if (file_exists($import_filename)) @unlink($import_filename);
    http_response(205,'Import Cancelled');
}

function process_import_function($cmd)
{
    if ($cmd == 'importdata') import_data();
    else if ($cmd == 'processimport') process_import();
    else if ($cmd == 'finishimport') finish_import(null);
    else if ($cmd == 'cancelimport') cancel_import();
    else return false;
    return true;
}

function convert_import_data($field_names)
{
    $field_names = explode(',',$field_names);
    $fp = fopen('php://stdin','r');
    while (! feof($fp)) {
       $buffer = trim(fgets($fp));
       $start_pos = strpos($buffer,'] ');
       if ($start_pos !== false)
          $buffer = trim(substr($buffer,$start_pos + 2));
       if (substr($buffer,-1) != ';') $buffer .= ';';
       $start_pos = strpos($buffer,' set ');
       if ($start_pos === false) continue;
       $end_pos = strrpos($buffer,' where ');
       if ($end_pos === false) continue;
       $prefix = substr($buffer,0,$start_pos + 5);
       $suffix = substr($buffer,$end_pos);
       $fields = substr($buffer,$start_pos + 5,$end_pos - $start_pos - 5);
       preg_match_all('/[^, ?].*?=(?:\'.*?\'|.*?)(?=,|$)/',$fields,$fields); 
       $new_fields = array();
       foreach ($fields[0] as $field) {
          $field_parts = explode('=',$field);
          if (in_array($field_parts[0],$field_names)) $new_fields[] = $field;
       }
       if (count($new_fields) == 0) continue;
       $buffer = $prefix.implode(',',$new_fields).$suffix;
       print $buffer."\n";
    }
    fclose($fp);
}

if (isset($argc) && ($argc == 3)) {
   if ($argv[1] == 'importdata') {
      finish_import($argv[2]);   DB::close_all();   exit(0);
   }
   else if ($argv[1] == 'convert') {
      convert_import_data($argv[2]);   DB::close_all();   exit(0);
   }
}

?>
