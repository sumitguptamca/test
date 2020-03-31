<?php
/*
              Inroads Shopping Cart - Catalog Config Dialog Functions

                       Written 2015-2019 by Randall Severy
                        Copyright 2015-2019 Inroads, LLC
*/

require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'catalogconfig-common.php';
if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/importdata.php')) $shopping_cart = true;
   else $shopping_cart = false;
}
if ($shopping_cart) require_once 'cartconfig-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

function add_tab_buttons($dialog)
{
    $dialog->add_button_separator('extra_buttons_row',20);
    $dialog->add_button('Add Field','images/AddUser.png',
                        'add_field();','add_field',false);
    $dialog->add_button('Edit Field','images/EditUser.png',
                        'edit_field();','edit_field',false);
    $dialog->add_button('Delete Field','images/DeleteUser.png',
                        'delete_field();','delete_field',false);
    $dialog->add_button('Add Backup','images/AddUser.png',
                        'add_backup();','add_backup',false);
    $dialog->add_button('Restore Backup','images/EditUser.png',
                        'restore_backup();','restore_backup',false);
    $dialog->add_button('Delete Backup','images/DeleteUser.png',
                        'delete_backup();','delete_backup',false);
}

function parse_backup_date($backup)
{
    $time_info = strptime($backup,'catalog-%m-%d-%y-%H-%M.sql.gz');
    $backup_date = mktime($time_info['tm_hour'],$time_info['tm_min'],
                         $time_info['tm_sec'],$time_info['tm_mon']+1,
                         $time_info['tm_mday'],($time_info['tm_year']+1900));
    return $backup_date;
}

function add_product_tab($new_tab,$label,$before_order=null)
{
    global $product_tab_labels;

    $product_tab_labels[$new_tab] = $label;
}

function catalog_config()
{
    global $category_types,$product_types,$shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $db = new DB;
    $config_values = load_catalog_config_values($db);

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet($prefix.'catalogconfig.css');
    $dialog->add_script_file($prefix.'catalogconfig.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    if ($shopping_cart) {
       $head_block = "<script type=\"text/javascript\">\n" .
                     "      script_prefix = '../cartengine/';\n" .
                     "    </script>";
       $dialog->add_head_line($head_block);
    }
    $dialog->set_body_id('catalog_config');
    $dialog->set_help('catalog_config');
    $dialog->start_body('Catalog Config');
    $dialog->set_button_width(140);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Update',$prefix.'Update.png',
                        'update_catalog_config();');
    $dialog->add_button('Cancel',$prefix.'Update.png',
                        'top.close_current_dialog();');
    add_tab_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('admin.php','CatalogConfig');

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('templates_tab','templates_content','change_tab');
    $dialog->add_tab('templates_tab','Templates','templates_tab',
                     'templates_content','change_tab',true,null,FIRST_TAB);
    $dialog->add_tab('fields_tab','Fields','fields_tab','fields_content',
                     'change_tab');
    $dialog->add_tab('backups_tab','Backups','backups_tab','backups_content',
                     'change_tab');
    $dialog->end_tab_row('tab_row_middle');

    $dialog->start_tab_content('templates_content',true);
    $dialog->start_field_table('templates_table');
    $templates_dir = get_row_value($config_values,'templates_directory');
    if (! $templates_dir) $templates_dir = 'templates';
    $dialog->add_edit_row('Templates Directory:','templates_directory',
                          $templates_dir,30);
    add_catalog_config_rows($db,$dialog,$config_values);
    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->start_tab_content('fields_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script>create_fields_grid();</script>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $backups = array();
    $backup_dir = @opendir('../admin/backups/');
    if ($backup_dir) {
       while (($backup = readdir($backup_dir)) !== false) {
          if (substr($backup,0,7) != 'catalog') continue;
          $backup_date = parse_backup_date($backup);
          $backups[$backup] = $backup_date;
       }
    }
    asort($backups);
    $dialog->start_tab_content('backups_content',false);
    $dialog->start_field_table('backups_table');
    $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: left;\">" .
                   "Category and Product Data Backups:<br>\n");
    $list_size = count($backups);
    if ($list_size < 5) $list_size = 5;
    $dialog->start_listbox('Backup',$list_size);
    foreach ($backups as $backup => $backup_date) {
       $backup_date = date('F j, Y g:i a',$backup_date);
       $dialog->add_list_item($backup,$backup_date,false);
    }
    $dialog->end_listbox();
    $dialog->end_row();
    $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: left;\">" .
                   "<strong><i>Warning: Restoring Category and Product Data " .
                   "from a backup will delete all category and " .
                   "product data that was entered or imported after the " .
                   "date of the backup</i></strong></td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->end_tab_section();

    $dialog->end_form();
    $dialog->end_body();
}

function update_catalog_config()
{
    $db = new DB;
    $config_values = load_catalog_config_values($db);
    if (! isset($config_values)) return;
    $catalog_config_fields = get_catalog_config_fields();
    $config_record = config_record_definition();
    foreach ($catalog_config_fields as $field_name) {
       if (isset($config_values[$field_name]))
          $old_field_value = $config_values[$field_name];
       else $old_field_value = '';
       $new_field_value = get_form_field($field_name);
       if ($old_field_value == $new_field_value) continue;
       $config_record['config_name']['value'] = $field_name;
       if (! isset($new_field_value))
          $config_record['config_value']['value'] = '';
       else $config_record['config_value']['value'] = $new_field_value;
       if (isset($config_values[$field_name])) {
          if (! $db->update('catalog_config',$config_record)) {
             http_response(422,$db->error);   return;
          }
       }
       else if (! $db->insert('catalog_config',$config_record)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,'Catalog Config Updated');
    log_activity('Updated Catalog Config');
}

function add_select_row($dialog,$prompt,$field_name,$row,$select_array,
                        $onchange_event=null,$class=null)
{
    if ($prompt) $dialog->start_row($prompt,'middle');
    $dialog->start_choicelist($field_name,$onchange_event,$class);
    $field_value = get_row_value($row,$field_name);
    $dialog->add_list_item('','',$field_value === '');
    foreach ($select_array as $value => $label) {
       if (! $label) continue;
       if ($field_value === '') $selected = false;
       else if ($value == $field_value) $selected = true;
       else $selected = false;
       $dialog->add_list_item($value,$label,$selected);
    }
    $dialog->end_choicelist();
    if ($prompt) $dialog->end_row();
}

function display_field_fields($dialog,$edit_type,$db,$row)
{
    global $shopping_cart,$product_tabs,$product_tab_labels;

    $product_tab_labels = array();
    if (! isset($product_tabs))
       $product_tabs = array('product' => true,'seo' => true);
    if ($product_tabs['product']) add_product_tab('product','Product');
    if (isset($product_tabs['specs']) && $product_tabs['specs'])
       add_product_tab('specs','Specs');
    if ($shopping_cart) add_product_tab('shopping','Shopping');
    if ($product_tabs['inventory']) add_product_tab('inventory','Inventory');
    if ($product_tabs['seo']) add_product_tab('seo','SEO');
    if (function_exists('setup_product_tabs')) setup_product_tabs(array());
    $query = 'select id,name from categories';
    $categories = $db->get_records($query,'id','name');

    $dialog->set_table_columns(4);
    $dialog->add_section_row('Field Options');
    if ($edit_type == UPDATERECORD) $dialog->add_hidden_field('id',$row);
    $dialog->add_edit_row('Field Name:','field_name',$row,30);
    $dialog->add_edit_row('Field Label:','field_label',$row,30);
    $dialog->add_edit_row('Sequence:','field_sequence',$row,1);
    add_select_row($dialog,'Admin Tab:','admin_tab',$row,$product_tab_labels);
    $tables = array(0 => 'Category',1 => 'Product');
    add_select_row($dialog,'Table:','table_id',$row,$tables);
    $data_types = array(INT_TYPE => 'INT_TYPE - Integer',
                        CHAR_TYPE => 'CHAR_TYPE - Character ',
                        REAL_TYPE => 'REAL_TYPE - Float',
                        TIMESTAMP_TYPE => 'TIMESTAMP_TYPE - Timestamp',
                        FLOAT_TYPE => 'FLOAT_TYPE - Float',
                        TEXT_TYPE => 'TEXT_TYPE - Text',
                        DATE_TYPE => 'DATE_TYPE - Date',
                        TIME_TYPE => 'TIME_TYPE - Time',
                        DATETIME_TYPE => 'DATETIME_TYPE - Date/Time',
                        BLOB_TYPE => 'BLOB_TYPE - Blob',
                        ENUM_TYPE => 'ENUM_TYPE - Enumeration');
    add_select_row($dialog,'Data Type:','data_type',$row,$data_types);
    $field_types = array(EDIT_FIELD => 'Edit Field',
                         TEXTAREA_FIELD => 'TextArea Field',
                         HTMLEDIT_FIELD => 'HTML Edit Field',
                         CHECKBOX_FIELD => 'Checkbox Field',
                         RADIO_FIELD => 'Radio Field',
                         CUSTOM_FIELD => 'Custom Field',
                         CUSTOM_ROW => 'Custom Row',
                         BROWSE_ROW => 'Browse Row',
                         SELECT_FIELD => 'Select Field',
                         BITFLAGS_FIELD => 'BitFlags Field',
                         TABLE_FIELD => 'Table Field',
                         11 => 'Inventory Field');
    add_select_row($dialog,'Field Type:','field_type',$row,$field_types);
    $dialog->add_edit_row('Field Values:','field_values',$row,30);
    if ($shopping_cart) {
       $option_labels = get_cart_option_labels();
       add_select_row($dialog,'Option Table:','option_table',$row,
                      $option_labels);
    }
    $dialog->start_row('Sub Product Attribute:','middle');
    $dialog->add_checkbox_field('subproduct_select','',$row);
    $dialog->end_row();

    $dialog->add_section_row('Display Options');
    $dialog->add_edit_row('Field Group:','field_group',$row,1);
    $dialog->add_edit_row('Field Flags:','field_flags',$row,10);
    $dialog->add_edit_row('Field Width:','field_width',$row,1);
    $dialog->add_edit_row('Field Height:','field_height',$row,1);
    $dialog->add_edit_row('Image/File Dir:','field_dir',$row,30);
    $dialog->start_row('Image Field:','middle');
    $dialog->add_checkbox_field('field_is_image','',$row);
    $dialog->end_row();
    $dialog->add_edit_row('Field Title:','field_title',$row,30);
    $wrap_options = array(WRAP_OFF => 'No Wrap',WRAP_HARD => 'Hard Wrap',
                          WRAP_SOFT => 'Soft Wrap');
    add_select_row($dialog,'Field Wrap:','field_wrap',$row,$wrap_options);

    $dialog->add_section_row('Search Options');
    $dialog->start_row('Searchable:','middle');
    $dialog->add_checkbox_field('search','',$row);
    $dialog->end_row();
    $dialog->add_edit_row('Search Label:','search_label',$row,30);
    $search_type_options = array(1 => 'Keyword (Text Field)',
                                 2 => 'Checkbox Set',
                                 4 => 'Radio Set',
                                 8 => 'Select List',
                                 16 => 'Hidden Input',
                                 32 => 'Custom Function');
    add_select_row($dialog,'Search Type:','search_type',$row,
                   $search_type_options);
    $dialog->add_edit_row('Search Group:','search_group',$row,1);
    $dialog->add_edit_row('Search Values:','search_values',$row,30);
    if ($shopping_cart)
       add_select_row($dialog,'Search Option Table:','search_option_table',
                      $row,$option_labels);
    add_select_row($dialog,'Search Category:','search_category_id',$row,
                   $categories,null,'search_category');
    $dialog->add_edit_row('Search Sequence:','search_sequence',$row,1);
    $dialog->start_row('Search AutoComplete:','middle');
    $dialog->add_checkbox_field('search_autocomplete','',$row);
    $dialog->end_row();

    $dialog->add_section_row('Filter Options');
    $dialog->start_row('Filterable:','middle');
    $dialog->add_checkbox_field('filter','',$row);
    $dialog->end_row();
    $dialog->add_edit_row('Filter Label:','filter_label',$row,30);
    $filter_type_options = array(1 => 'Text Button (checkbox)',
                                 2 => 'Text Button (radio)',
                                 4 => 'Solid Button (checkbox)',
                                 8 => 'Solid Button (radio)',
                                 16 => 'Checkbox Rows',
                                 32 => 'Radio Rows',
                                 64 => 'Select List',
                                 128 => 'Hidden Field',
                                 256 => 'Custom Function',
                                 512 => 'Slider');
    add_select_row($dialog,'Filter Type:','filter_type',$row,
                   $filter_type_options);
    $dialog->add_edit_row('Filter Group:','filter_group',$row,1);
    $filter_source_options = array(1 => 'Filter Values',
                                   2 => 'DB Field Values',
                                   4 => 'Custom Function',
                                   8 => 'Option Table',
                                   16 => 'Category');
    add_select_row($dialog,'Filter Value Source:','filter_value_source',$row,
                   $filter_source_options,'change_filter_value_source();');
    $dialog->add_edit_row('Filter Sequence:','filter_sequence',$row,1);
    $source = get_row_value($row,'filter_value_source');
    $values = get_row_value($row,'filter_values');
    $row['filter_values_1'] = $values;
    $dialog->start_hidden_row('Filter Values:','values_row1',
                              ($source != 1));
    $dialog->add_input_field('filter_values_1',$row,30);
    $dialog->end_row();
    $row['filter_values_4'] = $values;
    $dialog->start_hidden_row('Value Function:','values_row4',
                              ($source != 4));
    $dialog->add_input_field('filter_values_4',$row,30);
    $dialog->end_row();
    $row['filter_values_8'] = $values;
    $dialog->start_hidden_row('Value Option Table:','values_row8',
                              ($source != 8));
    if ($shopping_cart)
       add_select_row($dialog,null,'filter_values_8',$row,$option_labels);
    $dialog->end_row();
    $row['filter_values_16'] = $values;
    $dialog->start_hidden_row('Parent Category:','values_row16',
                              ($source != 16));
    add_select_row($dialog,null,'filter_values_16',$row,$categories);
    $dialog->end_row();

    $dialog->add_section_row('Compare Options');
    $compare_options = array(2 => 'Main',1 => 'Other');
    add_select_row($dialog,'In Compare:','compare',$row,$compare_options);
    $dialog->add_edit_row('Compare Row Label:','compare_row_label',$row,30);
    $dialog->add_edit_row('Compare Field Label:','compare_label',$row,30);
    $dialog->start_row('Link to Product:','middle');
    $dialog->add_checkbox_field('compare_link','',$row);
    $dialog->end_row();
    $dialog->add_edit_row('Compare Sequence:','compare_sequence',$row,1);
}

function parse_field_fields($db,&$field_record)
{
    $db->parse_form_fields($field_record);
    $value_source = $field_record['filter_value_source']['value'];
    if ($value_source && ($value_source != 2)) {
       $values = get_form_field('filter_values_'.$value_source);
       $field_record['filter_values']['value'] = $values;
    }
    else $field_record['filter_values']['value'] = '';
}

function add_field()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('catalogconfig.css');
    $dialog->add_script_file('catalogconfig.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('add_field');
    $dialog->set_help('add_field');
    $dialog->start_body('Add Catalog Field');
    $dialog->set_button_width(105);
    $dialog->start_button_column();
    $dialog->add_button('Add Field','images/AddUser.png',
                        'process_add_field();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('catalogconfig.php','AddField');
    $dialog->start_field_table();
    $row = array('table_id' => 1);
    display_field_fields($dialog,ADDRECORD,$db,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_field()
{
    $db = new DB;
    $field_record = catalog_fields_record_definition();
    parse_field_fields($db,$field_record);
    if (! $db->insert('catalog_fields',$field_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    http_response(201,'Field Added');
    log_activity('Added Catalog Field '.$field_record['field_name']['value'] .
                 ' (#'.$id.')');
}

function edit_field()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from catalog_fields where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Catalog Field not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('catalogconfig.css');
    $dialog->add_script_file('catalogconfig.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog_title = 'Edit Catalog Field (#'.$id.')';
    $dialog->set_body_id('edit_field');
    $dialog->set_help('edit_field');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_field();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('catalogconfig.php','EditField');
    $dialog->start_field_table();
    display_field_fields($dialog,UPDATERECORD,$db,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_field()
{
    $db = new DB;
    $field_record = catalog_fields_record_definition();
    parse_field_fields($db,$field_record);
    if (! $db->update('catalog_fields',$field_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Field Updated');
    log_activity('Updated Catalog Field '.$field_record['field_name']['value'] .
                 ' (#'.$field_record['id']['value'].')');
}

function delete_field()
{
    $db = new DB;
    $id = get_form_field('id');
    $field_record = catalog_fields_record_definition();
    $field_record['id']['value'] = $id;
    if (! $db->delete('catalog_fields',$field_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Field Deleted');
    log_activity('Deleted Catalog Field #'.$id);
}

function add_backup()
{
    global $db_host,$db_user,$db_name,$db_pass,$enable_product_flags;
    global $shopping_cart;

    if (! isset($enable_product_flags)) $enable_product_flags = false;
    $backup_file = '../admin/backups/catalog-'.date('m-d-y-H-i').'.sql';
    if ($shopping_cart) 
       $tables = 'products product_inventory product_data product_attributes ' .
                 'related_products product_discounts popular_products ' .
                 'categories subcategories category_products category_filters ' .
                 'images account_products account_inventory account_categories ' .
                 'attributes attribute_options attribute_conditions ' .
                 'attribute_sets attribute_set_attributes catalog_config ' .
                 'catalog_fields reviews coupon_products coupon_inventory ' .
                 'coupon_excluded_products';
    else $tables = 'products product_data related_products categories ' .
                   'subcategories category_products images catalog_config ' .
                   'catalog_fields';
    if ($enable_product_flags) $tables .= ' product_flags';
    $cmd = 'mysqldump -h '.$db_host.' -u '.$db_user.' -p'.$db_pass.' ' .
           $db_name.' '.$tables.' > '.$backup_file;
    $output = array();
    $result = exec($cmd,$output,$return_var);
    if ($return_var != 0) {
       @unlink($backup_file);
       log_error('Unable to Create Backup using '.$cmd);
       http_response(422,'Unable to add Backup');   return;
    }
    $cmd = 'gzip '.$backup_file;
    $result = exec($cmd,$output,$return_var);
    if ($return_var != 0) {
       @unlink($backup_file);
       log_error('Unable to Compress Backup using '.$cmd);
       http_response(422,'Unable to add Backup');   return;
    }
    $backup_file .= '.gz';

    http_response(201,'Added Backup');
    log_activity('Created Backup '.substr($backup_file,17));
}

function restore_backup()
{
    global $db_host,$db_user,$db_name,$db_pass;

    $backup = get_form_field('backup');
    $backup_file = '../admin/backups/'.$backup;
    $cmd = 'gzip -d '.$backup_file;
    $output = array();
    $result = exec($cmd,$output,$return_var);
    if ($return_var != 0) {
       log_error('Unable to Uncompress Backup using '.$cmd);
       http_response(422,'Unable to restore Backup');   return;
    }
    $backup_file = substr($backup_file,0,-3);
    $cmd = 'mysql -h '.$db_host.' -u '.$db_user.' -p'.$db_pass.' ' .
           $db_name.' < '.$backup_file;
    $result = exec($cmd,$output,$return_var);
    if ($return_var != 0) {
       exec('gzip '.$backup_file);
       log_error('Unable to Restore Backup using '.$cmd);
       http_response(422,'Unable to restore Backup');   return;
    }
    $cmd = 'gzip '.$backup_file;
    $result = exec($cmd,$output,$return_var);
    if ($return_var != 0)
       log_error('Unable to Recompress Backup using '.$cmd);

    http_response(201,'Backup Restored');
    log_activity('Restored Backup '.$backup);
}

function delete_backup()
{
    $backup = get_form_field('backup');
    $backup_file = '../admin/backups/'.$backup;
    if (! file_exists($backup_file)) {
       http_response(409,'Backup Not Found');   return;
    }
    if (! @unlink($backup_file)) {
       $error = 'Unable to delete backup file '.$backup;
       log_error($error);   http_response(422,$error);   return;
    }
    http_response(201,'Backup Deleted');
    log_activity('Deleted Backup '.$backup);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'updatecatalogconfig') update_catalog_config();
else if ($cmd == 'addfield') add_field();
else if ($cmd == 'processaddfield') process_add_field();
else if ($cmd == 'editfield') edit_field();
else if ($cmd == 'updatefield') update_field();
else if ($cmd == 'deletefield') delete_field();
else if ($cmd == 'addbackup') add_backup();
else if ($cmd == 'restorebackup') restore_backup();
else if ($cmd == 'deletebackup') delete_backup();
else catalog_config();

DB::close_all();

?>
