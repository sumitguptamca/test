<?php
/*
                 Inroads Shopping Cart - Vendor Category Mapping Tab

                         Written 2014-2019 by Randall Severy
                          Copyright 2014-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'sublist.php';
require_once 'utility.php';

function mapping_record_definition()
{
    $mapping_record = array();
    $mapping_record['id'] = array('type' => INT_TYPE);
    $mapping_record['id']['key'] = true;
    $mapping_record['vendor_id'] = array('type' => INT_TYPE);
    $mapping_record['vendor_category'] = array('type' => CHAR_TYPE);
    $mapping_record['category_id'] = array('type' => INT_TYPE);
    $mapping_record['num_products'] = array('type' => INT_TYPE);
    return $mapping_record;
}

function add_mapping_filters($screen)
{
    global $status_values;

    $db = new DB;
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Vendor:');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    $screen->write("<select name=\"vendor\" id=\"vendor\" " .
                   "onChange=\"select_vendor();\" class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    $screen->add_list_item('','',false);
    $screen->add_list_item('-1','Manual Import',false);
    $query = 'select * from vendors order by id';
    $vendors = $db->get_records($query);
    foreach ($vendors as $vendor_row)
       $screen->add_list_item($vendor_row['id'],$vendor_row['name'],false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');
    else $screen->write("</td></tr>\n");
    if ($screen->skin) $screen->write("<div class=\"filter\">");
    else $screen->write("<tr><td colspan=\"2\" align=\"center\">\n");
    $screen->add_checkbox_field('unmapped_only','Unmapped only',false,
                                'update_grid_where();');
    if ($screen->skin) $screen->write('</div>');
    else $screen->write("</td></tr>\n");
}

function display_mapping_screen()
{
    $db = new DB;
    $query = 'update category_mapping set category_id=NULL where id in ' .
             '(select id from (select m.id from category_mapping m left ' .
             'join categories c on c.id=m.category_id where isnull(c.id)) x)';
    if (! $db->query($query)) {
       process_error('Database Error: '.$db->error,0);   return;
    }
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('mapping.css');
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('mapping.js');
    $screen->set_body_id('Mapping');
    $screen->set_help('Mapping');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Category Mapping');
       $screen->start_title_filters();
       add_mapping_filters($screen);
       add_search_box($screen,'search_mapping','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->set_button_width(148);
    $screen->start_button_column();
    $screen->add_button('Add Mapping','../cartengine/images/AddOrder.png',
                        'add_mapping();','add_mapping',false);
    $screen->add_button('Edit Mapping','../cartengine/images/EditOrder.png',
                        'edit_mapping();');
    $screen->add_button('Delete Mapping','../cartengine/images/DeleteOrder.png',
                        'delete_mapping();');
    $screen->add_button('Add Category','../cartengine/images/AddCategory.png',
                        'add_category();');
    $screen->add_button_separator('import_button_row',20,false);
    $screen->add_button('Import Products','../cartengine/images/AddOrder.png',
                        'import_products();','import_products',false);
    if (! $screen->skin) {
       add_mapping_filters($screen);
       add_search_box($screen,'search_mapping','reset_search');
    }
    $screen->end_button_column();
    $screen->write("          <script>load_grid();</script>\n");
    $screen->end_body();
}

function display_mapping_fields($db,$dialog,$edit_type,$row)
{
    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field('id',get_row_value($row,'id'));
       if (get_row_value($row,'vendor_id') == -1)
          $dialog->add_edit_row('Vendor Category:','vendor_category',$row,100);
       else $dialog->add_text_row('Vendor Category:',
                                  get_row_value($row,'vendor_category'));
    }
    else {
       $dialog->add_hidden_field('vendor_id','-1');
       $dialog->add_edit_row('Vendor Category:','vendor_category','',100);
    }
    $category = get_row_value($row,'category_id');
    $dialog->start_row('Mapped Category:','middle');
    $dialog->start_choicelist('category_id',null);
    $dialog->add_list_item('','',false);
    $query = 'select id,name from categories order by name';
    $categories = $db->get_records($query);
    foreach ($categories as $cat_row)
       $dialog->add_list_item($cat_row['id'],$cat_row['name'],
                              $category == $cat_row['id']);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->write('<tr><td></td><td class="map_search_cell">');
    add_small_search_box($dialog,'query','map_search','map_reset_search');
}

function add_mapping()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('utility.css');
    $dialog->add_style_sheet('mapping.css');
    $dialog->add_script_file('mapping.js');
    $dialog->set_body_id('AddMapping');
    $dialog->set_help('AddMapping');
    $dialog->start_body('Add Mapping');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Mapping','../cartengine/images/AddOrder.png',
                        'process_add_mapping();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('mapping.php','AddMapping');
    $dialog->start_field_table();
    display_mapping_fields($db,$dialog,ADDRECORD,array());
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_field_table();
    $dialog->end_body();
}

function process_add_mapping()
{
    $db = new DB;
    $mapping_record = mapping_record_definition();
    $db->parse_form_fields($mapping_record);
    if (! $db->insert('category_mapping',$mapping_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Mapping Added');
    $log_string = 'Added Category Mapping #'.$db->insert_id();
    log_activity($log_string);
}

function edit_mapping()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from category_mapping where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error))
          process_error('Database Error: '.$db->error,0);
       else process_error('Category Mapping not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('utility.css');
    $dialog->add_style_sheet('mapping.css');
    $dialog->add_script_file('mapping.js');
    $dialog->set_body_id('EditMapping');
    $dialog->set_help('EditMapping');
    $dialog_title = 'Edit Mapping (#'.$id.')';
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','../cartengine/images/Update.png',
                        'update_mapping();');
    $dialog->add_button('Cancel','../cartengine/images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('mapping.php','EditMapping');
    $dialog->start_field_table();
    display_mapping_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_field_table();
    $dialog->end_body();
}

function update_mapping()
{
    $db = new DB;
    $mapping_record = mapping_record_definition();
    $db->parse_form_fields($mapping_record);
    if (! $db->update('category_mapping',$mapping_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Mapping Updated');
    log_activity('Updated Category Mapping #'.$mapping_record['id']['value']);
}

function delete_mapping()
{
    $id = get_form_field('id');
    $db = new DB;
    $mapping_record = mapping_record_definition();
    $mapping_record['id']['value'] = $id;
    if (! $db->delete('category_mapping',$mapping_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Mapping Deleted');
    log_activity('Deleted Category Mapping #'.$id);
}

function import_products()
{
    $dialog = new Dialog;
    $dialog->add_script_file('mapping.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('import_products');
    $dialog->set_help('import_products');
    $dialog->start_body('Import Products');
    $dialog->start_button_column();
    $dialog->add_button('Import','images/Import.png',
                        'process_import_products();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_field_table();
    $dialog->write('<form method="POST" action="mapping.php" name="' .
                   'ImportProducts" encType="multipart/form-data">'."\n");
    $dialog->add_hidden_field('cmd','processimportproducts');
    $dialog->start_row('Import File:','middle');
    $dialog->write("<input type=\"file\" name=\"Filename\" size=\"35\" " .
                   "class=\"browse_button\">\n");
    $dialog->end_row();
    $dialog->start_row('','middle');
    $dialog->add_checkbox_field('FieldNames','First line contains field names',
                                true);
    $dialog->end_row();
    $dialog->start_row('Find Products in:','middle');
    $dialog->start_choicelist('column1');
    $dialog->add_list_item('0','Column A',true);
    $dialog->add_list_item('1','Column B',false);
    $dialog->add_list_item('2','Column C',false);
    $dialog->add_list_item('3','Column D',false);
    $dialog->add_list_item('4','Column E',false);
    $dialog->add_list_item('5','Column F',false);
    $dialog->end_choicelist();
    $dialog->write(' using ');
    $dialog->start_choicelist('match1');
    $dialog->add_list_item('part_number','Part Number',true);
    $dialog->add_list_item('shopping_mpn','MPN',false);
    $dialog->add_list_item('id','Product ID',false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Assign to Category in:','middle');
    $dialog->start_choicelist('column2');
    $dialog->add_list_item('0','Column A',false);
    $dialog->add_list_item('1','Column B',true);
    $dialog->add_list_item('2','Column C',false);
    $dialog->add_list_item('3','Column D',false);
    $dialog->add_list_item('4','Column E',false);
    $dialog->add_list_item('5','Column F',false);
    $dialog->end_choicelist();
    $dialog->write(' using ');
    $dialog->start_choicelist('match2');
    $dialog->add_list_item('vendor_category','Vendor Category',true);
    $dialog->add_list_item('category_name','Category Name',false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->end_form();
    $dialog->end_field_table();
    $dialog->end_body();
}

function process_import_products()
{
    set_time_limit(0);
    ini_set('memory_limit','2048M');
    ignore_user_abort(true);
    $filename = $_FILES['Filename']['name'];
    $temp_name = $_FILES['Filename']['tmp_name'];
    $file_type = $_FILES['Filename']['type'];
    $temp_filename = tempnam('import','import');
    if ($temp_name) {
       if (! move_uploaded_file($temp_name,$temp_filename)) {
          log_error('Attempted to move '.$temp_name.' to '.$temp_filename);
          process_error('Unable to save uploaded file',-1);   return;
       }
    }
    else {
       process_error('You must select an Import File',-1);   return;
    }

    $extension = strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    if ($extension == 'xls') $format = 'Excel5';
    else if ($extension == 'xlsx') $format = 'Excel2007';
    else if ($extension == 'csv') $format = 'CSV';
    else {
       unlink($temp_filename);
       process_error('Only XLS, XLSX, or CSV files can be imported',-1);
       return;
    }
    require_once '../engine/PHPExcel/IOFactory.php';
    $reader = PHPExcel_IOFactory::createReader($format);
    $import_file = $reader->load($temp_filename);
    $data = $import_file->getActiveSheet()->toArray(null,false,false,false);
    if (! $data) {
       unlink($temp_filename);
       process_error('Unable to load data from '.$filename,-1);   return;
    }
    $field_names = get_form_field('FieldNames');
    $column1 = intval(get_form_field('column1'));
    if (! array_key_exists($column1,$data[0])) {
       process_error('Import File does not have a Column '.chr($column1 + 65));
       unlink($temp_filename);   return;
    }
    $match1 = get_form_field('match1');
    $column2 = intval(get_form_field('column2'));
    if (! array_key_exists($column2,$data[0])) {
       process_error('Import File does not have a Column '.chr($column2 + 65));
       unlink($temp_filename);   return;
    }
    $match2 = get_form_field('match2');

    $db = new DB;
    $db->enable_log_query(false);
    $category_product_record = sublist_record_definition();

    switch ($match1) {
       case 'part_number':
          $query = 'select parent,lower(trim(part_number)) as part_number ' .
                   'from product_inventory';
          $key_field = 'part_number';   $return_field = 'parent';   break;
       case 'shopping_mpn':
          $query = 'select id,lower(trim(shopping_mpn)) as shopping_mpn ' .
                   'from products';
          $key_field = 'shopping_mpn';   $return_field = 'id';   break;
       case 'id':
          $query = null;
    }
    if ($query) {
       $products = $db->get_records($query,$key_field,$return_field);
       if (! $products) {
          if (isset($db->error)) process_error($db->error,-1);
          else process_error('No Products Found',-1);
          unlink($temp_filename);   return;
       }
    }
    else $products = null;

    switch ($match2) {
       case 'vendor_category':
          $query = 'select lower(trim(vendor_category)) as vendor_category,' .
                   'category_id from category_mapping';
          $key_field = 'vendor_category';   $return_field = 'vendor_category';
          break;
       case 'category_name':
          $query = 'select id,lower(trim(name)) as name from categories';
          $key_field = 'name';   $return_field = 'id';   break;
    }
    $categories = $db->get_records($query,$key_field,$return_field);
    if (! $categories) {
       if (isset($db->error)) process_error($db->error,-1);
       else process_error('No Categories Found',-1);
       unlink($temp_filename);   return;
    }

    $query = 'select parent,related_id from category_products';
    $category_products = $db->get_records($query);
    if (! $category_products) {
       if (isset($db->error)) {
          process_error($db->error,-1);   unlink($temp_filename);   return;
       }
       $category_products = array();
    }

    $dialog = new Dialog;
    $dialog->add_script_file('mapping.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_onload_function('finish_import_products();');
    $dialog->set_body_id('finish_import_products');
    $dialog->start_body('Import Product Results');

    foreach ($data as $row_index => $row) {
       if (($row_index == 0) && $field_names) continue;
       $product = trim($row[$column1]);
       $match_product = strtolower($product);
       $category = trim($row[$column2]);
       $match_category = strtolower($category);
       if (! $products) $product_id = $product;
       else if (! isset($products[$match_product])) {
          $dialog->write('Product '.$match1.' '.$product.' not found in ' .
                         'Product Category Import, skipping<br>'."\n");
          continue;
       }
       else $product_id = $products[$match_product];
       if (! isset($categories[$match_category])) {
          $dialog->write('Category '.$match2.' '.$category.' not found in ' .
                         'Product Category Import, skipping<br>'."\n");
          continue;
       }
       else $category_id = $categories[$match_category];
       foreach ($category_products as $category_row)
          if (($category_row['parent'] == $category_id) &&
              ($category_row['related_id'] == $product_id)) {
          $dialog->write('Product '.$product.' already in Category ' .
                         $category.', skipping<br>'."\n");
          continue 2;
       }
       $category_product_record['parent']['value'] = $category_id;
       $category_product_record['related_id']['value'] = $product_id;
       if (! $db->insert('category_products',$category_product_record)) {
          unlink($temp_filename);   process_error($db->error,-1);   return;
       }
       $dialog->write('Product '.$product.' added to Category ' .
                      $category.'<br>'."\n");
    }
    $dialog->write('<br>Category Mapping Import Completed'."\n");

    unlink($temp_filename);

    $dialog->end_body();

    log_activity('Imported Product Category Mapping');
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'addmapping') add_mapping();
else if ($cmd == 'processaddmapping') process_add_mapping();
else if ($cmd == 'editmapping') edit_mapping();
else if ($cmd == 'updatemapping') update_mapping();
else if ($cmd == 'deletemapping') delete_mapping();
else if ($cmd == 'importproducts') import_products();
else if ($cmd == 'processimportproducts') process_import_products();
else display_mapping_screen();

?>
