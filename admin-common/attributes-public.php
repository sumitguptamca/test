<?php
/*
                Inroads Shopping Cart - Public Attribute Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

if (! class_exists('UI')) {
   require_once __DIR__.'/../engine/ui.php';
   require_once __DIR__.'/../engine/db.php';
   $using_ajax = true;
}
else {
   require_once __DIR__.'/../engine/ui.php';
   require_once __DIR__.'/../engine/db.php';
   $using_ajax = false;
}
if (file_exists(__DIR__.'/../admin/custom-config.php'))
   require_once __DIR__.'/../admin/custom-config.php';

function load_attribute($id,$db = null)
{
    if (! $db) $db = new DB;
    $query = 'select * from attributes where id=?';
    $query = $db->prepare_query($query,$id);
    $attribute = $db->get_record($query);
    if (! $attribute) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
       return null;
    }
    $attribute['options'] = array();
    $query = 'select * from attribute_options where parent=? ' .
             'order by sequence';
    $query = $db->prepare_query($query,$id);
    $attribute['options'] = $db->get_records($query,'id');
    if ((! $attribute['options']) && isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    return $attribute;
}

function load_all_attributes($db = null)
{
    if (! $db) $db = new DB;
    $query = 'select * from attributes';
    $attributes = $db->get_records($query,'id');
    if (! $attributes) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
       return null;
    }
    $attributes['options'] = array();
    $query = 'select * from attribute_options order by parent,sequence';
    $attributes['options'] = $db->get_records($query,'id');
    if ((! $attributes['options']) && isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    return $attributes;
}

function ajax_load_all_attributes()
{
    $db = new DB;
    write_javascript_header();
    print "if (typeof(all_attribute_data)==\"undefined\") " .
          "var all_attribute_data=new Array();\n";
    print "if (typeof(all_attribute_options)==\"undefined\") " .
          "var all_attribute_options=new Array();\n";
    $query = 'select * from attributes order by id';
    $result = $db->query($query);
    if ($result) {
       if ($db->num_rows($result) > 0)
          print "var all_attribute_fields = ['id','name','display_name','type'," .
                "'url','description','select_function','sub_product','dynamic'," .
                "'required','width','height','wrap'];\n";
       while ($row = $db->fetch_assoc($result)) {
          print 'all_attribute_data['.$row['id'].']=[';
          $first_field = true;
          foreach ($row as $field_name => $field_value) {
             if ($first_field) $first_field = false;
             else print ',';
             print "'";
             write_js_data($field_value);
             print "'";
          }
          print "];\n";
          print 'all_attribute_options['.$row['id']."]=new Array();\n";
       }
       $db->free_result($result);
    }
    else if (isset($db->error)) return;

    $query = 'select * from attribute_options order by parent,sequence';
    $result = $db->query($query);
    if ($result) {
       if ($db->num_rows($result) > 0)
          print "var all_option_fields = ['id','sequence','parent','name'," .
                "'adjust_type','adjustment','default_value','overlay_image'];\n";
       while ($row = $db->fetch_assoc($result)) {
          print 'all_attribute_options['.$row['parent'].']['.$row['id'].']=[';
          $first_field = true;
          foreach ($row as $field_name => $field_value) {
             if ($first_field) $first_field = false;
             else print ',';
             print "'";
             write_js_data($field_value);
             print "'";
          }
          print "];\n";
       }
       $db->free_result($result);
    }
    if (function_exists('custom_ajax_load_all_attributes'))
       custom_ajax_load_all_attributes($db);
}

if ($using_ajax) {
   $jscmd = get_form_field('jscmd');
   if ($jscmd == 'loadall') ajax_load_all_attributes();
   DB::close_all();
}

?>
