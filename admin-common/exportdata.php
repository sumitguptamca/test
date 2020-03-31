<?php
/*
           Inroads Control Panel/Shopping Cart - Admin Tab - Export Data Processing

                           Written 2009-2019 by Randall Severy
                            Copyright 2009-2019 Inroads, LLC
*/

require_once 'eximport-common.php';
if (file_exists('custom-config.php')) require_once 'custom-config.php';
else if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/exportdata.php')) $shopping_cart = true;
   else $shopping_cart = false;
}
if ($shopping_cart) $catalog_site = false;
else {
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
}

define('DECRYPT_DATA',false);

function export_data()
{
    global $shopping_cart,$catalog_site,$admin_base_url;

    $db = new DB;
    $db_tables = $db->list_db_tables();
    if (! $db_tables) return;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog = new Dialog;
    $dialog->add_script_file($prefix.'exportdata.js');
    $dialog->add_script_file($prefix.'eximport-common.js');
    $dialog->set_body_id('export_data');
    $dialog->set_help('export_data');
    $dialog->start_body('Export Data');
    $dialog->start_button_column(false,true);
    $dialog->add_button('Export',$prefix.'images/Import.png',
                        'process_export();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    if (isset($admin_base_url)) {
       if ($shopping_cart)
          $form_url = $admin_base_url.'cartengine/admin.php';
       else $form_url = $admin_base_url.'admin/admin.php';
    }
    else $form_url = 'admin.php';
    $dialog->start_form($form_url,'ExportData');
    $dialog->start_field_table();
    $dialog->add_hidden_field('cmd','processexport');

    $dialog->start_row('File Format:');
    $dialog->start_choicelist('Format','select_format();');
    $dialog->add_list_item('','',true);
    $dialog->add_list_item('xlsx','Excel Workbook (*.xlsx)',false);
    $dialog->add_list_item('xls','Excel 97-2003 Workbook (*.xls)',false);
    $dialog->add_list_item('csv','CSV (Comma delimited) (*.csv)',false);
    $dialog->add_list_item('txt','Text (Tab delimited) (*.txt)',false);
    $dialog->add_list_item('sql','SQL',false);
    $dialog->end_choicelist();
    $dialog->end_row();

    $dialog->start_hidden_row('Options:','options_row',true,'top');
    $dialog->add_checkbox_field('FieldNames',
                                '&nbsp;First line contains field names',true);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('ConvertDates',
                                '&nbsp;Convert date fields from timestamps',true);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('ExportEncrypted',
                                '&nbsp;Export encrypted fields',false);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('SaveOnServer',
                                '&nbsp;Save Export on Server',false);
    $dialog->end_row();

    add_module_row($dialog);

    $dialog->start_row('Database Table:','middle');
    $dialog->start_choicelist('Table');
    $dialog->add_list_item('','',true);
    if ($shopping_cart)
       $dialog->add_list_item('*allcart','All Cart Data Tables',false);
    if ($shopping_cart || $catalog_site) {
       $dialog->add_list_item('*allcat','All Catalog Tables',false);
/*
       $dialog->add_list_item('*catsub','categories AND subcategories',false);
       $dialog->add_list_item('*prodinv','products AND inventory',false);
*/
    }
    foreach ($db_tables as $index => $table_name)
       $dialog->add_list_item($table_name,$table_name,false);
    $dialog->end_choicelist();
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function output_data($buffer,$delim)
{
    $buffer = str_replace("\"","\"\"",$buffer);
    print $buffer;
}

function process_export()
{
    global $product_fields,$inventory_fields,$convert_date_fields;
    global $shopping_cart,$encrypted_fields,$shopping_feeds_enabled;

    if ($shopping_cart) {
       require_once 'shopping-common.php';
       if (empty($shopping_feeds_enabled))
          $shopping_feeds_enabled = shopping_modules_installed();
    }

    set_time_limit(0);
    ini_set('memory_limit',-1);
    ini_set('max_execution_time',0);
    $module = get_form_field('Module');
    if ($module) set_module_db_info($module);
    $db = new DB;
    $table = get_form_field('Table');
    $field_names = get_form_field('FieldNames');
    $export_encrypted = get_form_field('ExportEncrypted');
    $save_on_server = get_form_field('SaveOnServer');
    $format = get_form_field('Format');
    if ($table == '*allcart') {
       $filename = 'cartdata.'.$format;   $multiple_tables = true;
       $table_name = 'All Cart Data Tables';
       $tables = array('cart','cart_items','cart_attributes','wishlist',
          'wishlist_items','wishlist_attributes','accounts','account_products',
          'account_inventory','account_categories','customers',
          'customer_accounts','customer_notices','billing_information',
          'shipping_information','saved_cards','orders','order_items',
          'order_attributes','order_billing','order_shipping','order_payments',
          'order_shipments','order_shipment_items','rmas','rma_items',
          'vendors','vendor_imports','vendor_mapping','vendor_markups',
          'coupons','coupon_products','coupon_inventory','coupon_customers',
          'coupon_discounts','registry','registry_items','reviews',
          'testimonials','searches','search_synonyms',
          'api_objects');
    }
    else if ($table == '*allcat') {
       $filename = 'catalog.'.$format;   $multiple_tables = true;
       $table_name = 'All Catalog Tables';
       $tables = array('categories','subcategories','category_products',
          'category_filters','products','related_products',
          'product_attributes','product_inventory','product_data',
          'product_discounts','popular_products','catalog_fields','attributes',
          'attribute_options','attribute_conditions','attribute_sets',
          'attribute_set_attributes','category_mapping','images',
          'callout_groups','callouts','banner_slots','banner_ads',
          'media_libraries','media_library_pages','media_sections',
          'media_subsections','media_documents','media_section_docs',
          'media_users','media_downloads');
    }
    else {
       $filename = $table.'.'.$format;   $tables = array($table);
       $multiple_tables = false;   $table_name = $table;
    }
    if ($shopping_cart)
       call_shopping_event('update_export_tables',array($table,&$tables));

    if ($format == 'xls') {
       $output_format = 'Excel5';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($format == 'xlsx') {
       $output_format = 'Excel2007';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($format == 'csv') {
       $output_format = 'CSV';   $mime_type = 'text/csv';
    }
    else if ($format == 'txt') {
       $output_format = 'CSV';   $mime_type = 'text/csv';
    }
    else if ($format == 'sql') {
       $mime_type = 'application/octet-stream';
       $field_names = false;
       $output_format = 'sql';
       $export_encrypted = true;
    }

    if ($save_on_server) {
       $dialog = new Dialog;
       $dialog->set_body_id('export_data_submitted');
       $dialog->start_body('Submitted Export Request');
       $dialog->write('<br><h2 align="center">Submitted Export Request</h2>' .
                      "\n");
       $dialog->end_body();
       flush();
    }
    else if ($multiple_tables) {
       header('Content-Type: '.$mime_type);
       header('Content-Disposition: attachment; filename="'.$filename.'"');
       header("Cache-Control: no-cache");
    }

    if ($format != 'sql') {
       ob_start();
       require_once '../engine/excel.php';
       $excel = new PHPExcel();
       $worksheet = $excel->getActiveSheet();
       $first_table = true;
    }

    foreach ($tables as $table) {
       if ($format == 'sql') $convert_dates = false;
       else {
          $convert_dates = get_form_field('ConvertDates');
          if (! isset($convert_date_fields[$table])) $convert_dates = false;
       }
       if (DECRYPT_DATA) {
          if (isset($encrypted_fields,$encrypted_fields[$table]))
             $decrypt_table = true;
          else $decrypt_table = false;
       }
       else $decrypt_table = false;
       if ($table == '*catsub') {
          $field_defs = $db->get_field_defs('categories');
          $sub_field_defs = $db->get_field_defs('subcategories');
          unset($sub_field_defs['id']);
          unset($sub_field_defs['related_id']);
          $field_defs = array_merge($field_defs,$sub_field_defs);
          $query = 'select c.*,s.parent,s.sequence from categories c ' .
                   'left join subcategories s on c.id=s.related_id order by c.id';
       }
       else if ($table == '*prodinv') {
          $features = get_cart_config_value('features',$db);
          $field_defs = $db->get_field_defs('products');
          $query = 'select p.id,p.status,p.product_type,p.vendor,p.name,' .
                   'p.display_name,p.menu_name';
          if ($features & LIST_PRICE_PRODUCT) $query .= ',p.list_price';
          else unset($field_defs['list_price']);
          if ($features & REGULAR_PRICE_PRODUCT) $query .= ',p.price';
          else unset($field_defs['price']);
          if ($features & SALE_PRICE_PRODUCT) $query .= ',p.sale_price';
          else unset($field_defs['sale_price']);
          if ($features & PRODUCT_COST_PRODUCT) $query .= ',p.cost';
          else unset($field_defs['cost']);
          $query .= ',p.flags,p.last_modified';
          if ($shopping_cart) $query .= ',p.taxable';
          else unset($field_defs['taxable']);
          if ($shopping_cart && $shopping_feeds_enabled)
             $query .= ',p.shopping_gtin,p.shopping_brand,p.shopping_mpn' .
                       ',p.shopping_gender,p.shopping_color,p.shopping_age' .
                       ',p.shopping_flags';
          else {
             unset($field_defs['shopping_gtin']);
             unset($field_defs['shopping_brand']);
             unset($field_defs['shopping_mpn']);
             unset($field_defs['shopping_gender']);
             unset($field_defs['shopping_color']);
             unset($field_defs['shopping_age']);
             unset($field_defs['shopping_flags']);
          }
          if ($shopping_cart)
             call_shopping_event('update_export_fields',
                                 array(&$query,&$field_defs));
          if ($features & REGULAR_PRICE_BREAKS)
             $query .= ',p.price_break_type,p.price_breaks';
          else {
             unset($field_defs['price_break_type']);
             unset($field_defs['price_breaks']);
          }
          $query .= ',p.short_description,p.long_description,p.websites,p.video,' .
                    'p.audio,p.seo_title,p.seo_description,p.seo_keywords,' .
                    'p.seo_header,p.seo_footer,p.seo_url,seo_category';
          if (isset($product_fields)) {
             foreach ($product_fields as $field_name => $field)
                if ($field['datatype']) $query .= ','.$field_name;
          }

          $inv_field_defs = $db->get_field_defs('product_inventory');
          unset($inv_field_defs['id']);
          unset($inv_field_defs['sequence']);
          unset($inv_field_defs['parent']);

          $query .= ',i.attributes';
          if ($features & USE_PART_NUMBERS) $query .= ',i.part_number';
          else unset($inv_field_defs['part_number']);
          if ($features & MAINTAIN_INVENTORY) $query .= ',i.qty,i.min_qty';
          else {
             unset($inv_field_defs['qty']);
             unset($inv_field_defs['min_qty']);
          }
          if ($features & WEIGHT_ITEM) $query .= ',i.weight';
          else unset($inv_field_defs['weight']);
          if ($features & LIST_PRICE_INVENTORY) $query .= ',i.list_price';
          else unset($inv_field_defs['list_price']);
          if ($features & REGULAR_PRICE_INVENTORY) $query .= ',i.price';
          else unset($inv_field_defs['price']);
          if ($features & SALE_PRICE_INVENTORY) $query .= ',i.sale_price';
          else unset($inv_field_defs['sale_price']);
          if ($features & PRODUCT_COST_INVENTORY) $query .= ',i.cost';
          else unset($inv_field_defs['cost']);
          if ($features & DROP_SHIPPING) $query .= ',i.origin_zip';
          else unset($inv_field_defs['origin_zip']);
          $query .= ',i.image';
          if (isset($inventory_fields)) {
             foreach ($inventory_fields as $field_name => $field)
                if ($field['datatype']) $query .= ','.$field_name;
          }

          $field_defs = array_merge($field_defs,$inv_field_defs);
          $query .= ' from products p left join product_inventory i on p.id=i.parent';
       }
       else {
          $field_defs = $db->get_field_defs($table);
          if (isset($encrypted_fields,$encrypted_fields[$table]) &&
              (! $export_encrypted)) {
             $first_field = true;   $query = 'select ';
             foreach ($field_defs as $field_name => $field_def) {
                if ($db->check_encrypted_field($table,$field_name))
                   unset($field_defs[$field_name]);
                else {
                   if ($first_field) $first_field = false;
                   else $query .= ',';
                   $query .= $field_name;
                }
             }
             $query .= ' from '.$table;
          }
          else $query = 'select * from '.$table;
       }
       $rows = $db->get_records($query);
       if (! $rows) {
          if ($multiple_tables) continue;
          if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
          else process_error('No Records Found to Export',-1);
          return;
       }

       if ($save_on_server) {}
       else if (! $multiple_tables) {
          header('Content-Type: '.$mime_type);
          header('Content-Disposition: attachment; filename="'.$filename.'"');
          header("Cache-Control: no-cache");
       }
       else if ($format != 'sql') {
          if ($first_table) $first_table = false;
          else $worksheet = $excel->createSheet();
          $worksheet->setTitle($table);
       }

       if ($field_names) {
          $field_row = array();
          foreach ($field_defs as $field_name => $field_def)
             $field_row[] = $field_name;
          $worksheet->fromArray($field_row,NULL,'A1');
          $starting_row = 'A2';
       }
       else $starting_row = 'A1';

       if ($convert_dates || $decrypt_table) {
          foreach ($rows as $index => $row) {
             if ($decrypt_table) $db->decrypt_record($table,$rows[$index]);
             if ($convert_dates) {
                foreach ($field_defs as $field_name => $field_def) {
                   if (in_array($field_name,$convert_date_fields[$table]) &&
                       $row[$field_name])
                      $rows[$index][$field_name] = date('n/j/y H:i:s',
                                                        $row[$field_name]);
                }
             }
          }
       }

       if ($format == 'sql') {
          $old_encrypted = $db->encrypted;   $db->encrypted = false;
          print 'DELETE FROM '.$table.";\n";
          foreach ($rows as $row) {
             print 'INSERT INTO '.$table.' VALUES (';
             $first_field = true;
             foreach ($field_defs as $field_name => $field_def) {
                $field_value = $row[$field_name];
                if ($first_field) $first_field = false;
                else print ',';
                if ($db->char_type($field_def))
                   print $db->get_char_value($table,$field_name,$field_value);
                else if (trim($field_value) === '') print 'NULL';
                else print $field_value;
             }
             print ");\n";
          }
          $db->encrypted = $old_encrypted;
       }
       else $worksheet->fromArray($rows,NULL,$starting_row);
    }

    if ($format != 'sql') {
       $excel->setActiveSheetIndex(0);
       $writer = PHPExcel_IOFactory::createWriter($excel,$output_format);
       $writer->setPreCalculateFormulas(false);
       if ($format == 'txt') $writer->setDelimiter("\t");
       $error = ob_get_contents();
       if ($error) log_error('Error generating export spreadsheet: '.$error);
       ob_end_clean();
       if ($save_on_server) $writer->save('../admin/'.$filename);
       else $writer->save('php://output');
    }

    if ($save_on_server)
       log_activity('Exported Data from Table '.$table_name.' to file ' .
                    $filename);
    else log_activity('Exported Data from Table '.$table_name);
    if ($module) restore_db_info();
}

function load_tables()
{
    global $shopping_cart,$catalog_site;

    $module = get_form_field('module');

    if ($module) set_module_db_info($module);
    $db = new DB;
    $db_tables = $db->list_db_tables();
    if (! $db_tables) {
       http_response(410,'No Tables Found');   return;
    }
    if (! $module) {
       $special_tables = array();
       if ($shopping_cart)
          $special_tables[] = '*allcart|All Cart Data Tables';
       if ($shopping_cart || $catalog_site)
          $special_tables[] = '*allcat|All Catalog Tables';
       $db_tables = array_merge($special_tables,$db_tables);
    }
    print json_encode($db_tables);
    if ($module) restore_db_info();
}

function process_export_function($cmd)
{
    if ($cmd == 'exportdata') export_data();
    else if ($cmd == 'processexport') process_export();
    else if ($cmd == 'loadtables') load_tables();
    else return false;
    return true;
}

?>
