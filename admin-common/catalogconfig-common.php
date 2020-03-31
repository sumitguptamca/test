<?php
/*
                Inroads Shopping Cart - Common Catalog Config Functions

                        Written 2015-2018 by Randall Severy
                          Copyright 2015-2018 Inroads, LLC
*/

$catalog_config_values = null;

function config_record_definition()
{
    $config_record = array();
    $config_record['config_name'] = array('type' => CHAR_TYPE);
    $config_record['config_name']['key'] = true;
    $config_record['config_value'] = array('type' => CHAR_TYPE);
    return $config_record;
}

function load_catalog_config_values($db=null)
{
    global $catalog_config_values;

    if (! $db) $db = new DB;
    $catalog_config_values = $db->get_records('select * from catalog_config',
                                              'config_name','config_value');
    if ((! $catalog_config_values) && isset($db->error)) {
       process_error('Database Error: '.$db->error,-1);   return null;
    }
    return $catalog_config_values;
}

function get_catalog_config_value($config_name,$db=null)
{
    global $catalog_config_values;

    if ($catalog_config_values) {
       if (isset($catalog_config_values[$config_name]))
          return $catalog_config_values[$config_name];
       else return '';
    }

    if (! $db) $db = new DB;
    $row = $db->get_record("select * from catalog_config where config_name='" .
                           $config_name."'");
    if ((! $row) && isset($db->error)) {
       process_error("Database Error: ".$db->error,-1);   return null;
    }
    if ($row) return $row['config_value'];
    else return "";
}

function load_catalog_templates($db=null,$website=null)
{
    global $docroot;

    if ($website !== null) {
       if (! $db) $db = new DB;
       $query = 'select rootdir from web_sites where id=?';
       $query = $db->prepare_query($query,$website);
       $row = $db->get_record($query);
       if (! empty($row['rootdir'])) {
          $rootdir = $row['rootdir'];
          if ((substr($docroot,-1) != '/') && ($rootdir[0] != '/'))
             $rootdir = '/'.$rootdir;
          $rootdir = $docroot.$rootdir;
          if (substr($rootdir,-1) == '/') $rootdir = substr($rootdir,0,-1);
       }
       else $rootdir = $docroot;
    }
    else $rootdir = $docroot;

    $templates_directory = get_catalog_config_value('templates_directory',$db);
    if (! $templates_directory) $templates_directory = '/templates/';
    if ($templates_directory[0] != '/')
       $templates_directory = '/'.$templates_directory;
    if (substr($templates_directory,-1) != '/') $templates_directory .= '/';

    $templates = array();
    $template_dir = @opendir($rootdir.$templates_directory);
    if (! $template_dir) return null;
    while (($template = readdir($template_dir)) !== false) {
       if ($template[0] == '.') continue;
       $templates[] = $template;
    }
    closedir($template_dir);
    sort($templates);
    return $templates;
}

function catalog_fields_record_definition()
{
    $catalog_fields_record = array();
    $catalog_fields_record['id'] = array('type' => INT_TYPE);
    $catalog_fields_record['id']['key'] = true;
    $catalog_fields_record['table_id'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_sequence'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_label'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['field_name'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['data_type'] = array('type' => FLOAT_TYPE);
    $catalog_fields_record['field_type'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_values'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['option_table'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_group'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_flags'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_width'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_height'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_dir'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['field_is_image'] = array('type' => INT_TYPE);
    $catalog_fields_record['field_is_image']['fieldtype'] = CHECKBOX_FIELD;
    $catalog_fields_record['field_title'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['field_wrap'] = array('type' => INT_TYPE);
    $catalog_fields_record['search'] = array('type' => INT_TYPE);
    $catalog_fields_record['search']['fieldtype'] = CHECKBOX_FIELD;
    $catalog_fields_record['search_label'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['search_type'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_group'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_values'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_option_table'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_category_id'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_sequence'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_autocomplete'] = array('type' => INT_TYPE);
    $catalog_fields_record['search_autocomplete']['fieldtype'] = CHECKBOX_FIELD;
    $catalog_fields_record['filter'] = array('type' => INT_TYPE);
    $catalog_fields_record['filter']['fieldtype'] = CHECKBOX_FIELD;
    $catalog_fields_record['filter_label'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['filter_type'] = array('type' => INT_TYPE);
    $catalog_fields_record['filter_group'] = array('type' => INT_TYPE);
    $catalog_fields_record['filter_value_source'] = array('type' => INT_TYPE);
    $catalog_fields_record['filter_values'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['filter_sequence'] = array('type' => INT_TYPE);
    $catalog_fields_record['admin_tab'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['compare'] = array('type' => INT_TYPE);
    $catalog_fields_record['compare_row_label'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['compare_label'] = array('type' => CHAR_TYPE);
    $catalog_fields_record['compare_link'] = array('type' => INT_TYPE);
    $catalog_fields_record['compare_link']['fieldtype'] = CHECKBOX_FIELD;
    $catalog_fields_record['compare_sequence'] = array('type' => INT_TYPE);
    $catalog_fields_record['subproduct_select'] = array('type' => INT_TYPE);
    $catalog_fields_record['subproduct_select']['fieldtype'] = CHECKBOX_FIELD;
    return $catalog_fields_record;
}

function get_catalog_config_fields($website_flag=false)
{
    global $category_types,$product_types;

    $catalog_config_fields = array('category_list_template',
       'product_list_template','no_products_template',
       'compare_template','no_compare_template','product_template');
    if (! $website_flag)
       $catalog_config_fields = array_merge(array('templates_directory'),
                                            $catalog_config_fields);
    if (isset($category_types)) {
       foreach ($category_types as $index => $label) {
          $catalog_config_fields[] = 'category_list_'.$index.'_template';
          $catalog_config_fields[] = 'product_list_'.$index.'_template';
       }
    }
    if (isset($product_types)) {
       foreach ($product_types as $index => $label)
          $catalog_config_fields[] = 'product_'.$index.'_template';
    }
    return $catalog_config_fields;
}

function add_catalog_template_row($dialog,$prompt,$field_name,$config_values,
                                  $templates,$website_flag)
{
    $template = get_row_value($config_values,$field_name);
    $dialog->start_row($prompt,'middle');
    $dialog->start_choicelist($field_name);
    if ($website_flag) $label = 'Default Template';
    else $label = '';
    $dialog->add_list_item('',$label,(! $template));
    if ($templates) foreach ($templates as $filename)
       $dialog->add_list_item($filename,$filename,$template == $filename);
    $dialog->end_choicelist();
    $dialog->end_row();
}

function add_catalog_config_rows($db,$dialog,$config_values,
                                 $website_flag=false,$website=null)
{
    global $category_types,$product_types;

    $templates = load_catalog_templates($db,$website);
    add_catalog_template_row($dialog,
                             'Default Category (Subcategory List) Template:',
                             'category_list_template',$config_values,
                             $templates,$website_flag);
    if (isset($category_types)) {
       foreach ($category_types as $index => $label)
          add_catalog_template_row($dialog,$label .
                             ' Category (Subcategory List) Template:',
                             'category_list_'.$index.'_template',
                             $config_values,$templates,$website_flag);
    }
    add_catalog_template_row($dialog,
                             'Default Category (Product List) Template:',
                             'product_list_template',$config_values,
                             $templates,$website_flag);
    if (isset($category_types)) {
       foreach ($category_types as $index => $label)
          add_catalog_template_row($dialog,$label .
                             ' Category (Product List) Template:',
                             'product_list_'.$index.'_template',
                             $config_values,$templates,$website_flag);
    }
    add_catalog_template_row($dialog,'No Products Found Template:',
                             'no_products_template',$config_values,
                             $templates,$website_flag);
    add_catalog_template_row($dialog,'Compare Template:','compare_template',
                             $config_values,$templates,$website_flag);
    add_catalog_template_row($dialog,'No Items to Compare Template:',
                             'no_compare_template',$config_values,
                             $templates,$website_flag);
    add_catalog_template_row($dialog,'Default Product Template:',
                             'product_template',$config_values,$templates,
                             $website_flag);
    if (isset($product_types)) {
       foreach ($product_types as $index => $label)
          add_catalog_template_row($dialog,$label.' Product Template:',
                             'product_'.$index.'_template',
                             $config_values,$templates,$website_flag);
    }
}

function add_catalog_fields($db,$table,$admin_tab,$fields)
{
    switch ($table) {
       case 'categories': $table_id = 0;   break;
       case 'products': $table_id = 1;   break;
    }
    $field_defs = $db->get_field_defs($table);

    $query = 'select * from catalog_fields where (table_id=?) and ' .
             '(admin_tab=?)';
    $query = $db->prepare_query($query,$table_id,$admin_tab);
    $catalog_fields = $db->get_records($query,'field_name');
    $field_sequence = 0;
    if ($catalog_fields) {
       foreach ($catalog_fields as $field) {
          if ($field['field_sequence'] > $field_sequence)
             $field_sequence = $field['field_sequence'];
       }
    }

    $query = 'select max(filter_group) as filter_group from catalog_fields';
    $row = $db->query($query);
    if (empty($row['filter_group'])) $filter_group = 0;
    else $filter_group = $row['filter_group'];

    $query = 'select max(filter_sequence) as filter_sequence ' .
             'from catalog_fields';
    $row = $db->query($query);
    if (empty($row['filter_sequence'])) $filter_sequence = 0;
    else $filter_sequence = $row['filter_sequence'];

    $query = 'select max(compare_sequence) as compare_sequence ' .
             'from catalog_fields';
    $row = $db->query($query);
    if (empty($row['compare_sequence'])) $compare_sequence = 0;
    else $compare_sequence = $row['compare_sequence'];

    foreach ($fields as $field_def) {
       $field_name = $field_def['name'];
       if (! isset($field_defs[$field_name])) {
          $field_column = $db->build_field_column($field_def);
          $query = 'alter table '.$table.' add '.$field_column;
          $db->log_query($query);
          if (! $db->query($query)) return false;
       }
       if (! isset($catalog_fields[$field_name])) {
          $record = catalog_fields_record_definition();
          $record['table_id']['value'] = $table_id;
          $record['field_sequence']['value'] = ++$field_sequence;
          if (! empty($field_def['label']))
             $record['field_label']['value'] = $field_def['label'];
          $record['field_name']['value'] = $field_name;
          $record['data_type']['value'] = $field_def['type'];
          if (isset($field_def['field_type']))
             $record['field_type']['value'] = $field_def['field_type'];
          else $record['field_type']['value'] = 0;
          if (! empty($field_def['filter_type'])) {
             $record['filter']['value'] = 1;
             if (! empty($field_def['label']))
                $record['filter_label']['value'] = $field_def['label'];
             $record['filter_type']['value'] = $field_def['filter_type'];
             $record['filter_group']['value'] = ++$filter_group;
             if (isset($field_def['filter_source']))
                $record['filter_value_source']['value'] =
                   $field_def['filter_source'];
             else $record['filter_value_source']['value'] = 2;
             $record['filter_sequence']['value'] = ++$filter_sequence;
          }
          $record['admin_tab']['value'] = $admin_tab;
          if (! empty($field_def['compare'])) {
             $record['compare']['value'] = $field_def['compare'];
             if (! empty($field_def['label']))
                $record['compare_row_label']['value'] = $field_def['label'];
             $record['compare_sequence']['value'] = ++$compare_sequence;
          }
          if (! empty($field_def['subproduct_select']))
             $record['subproduct_select']['value'] =
                $field_def['subproduct_select'];
          if (! $db->insert('catalog_fields',$record)) return false;
       }
    }
    return true;
}

?>
