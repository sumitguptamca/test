<?php
/*
              Inroads Shopping Cart - Products Tab - Product Data Dialogs

                          Written 2011-2019 by Randall Severy
                           Copyright 2011-2019 Inroads, LLC
*/

require_once '../engine/dialog.php';
require_once '../engine/db.php';
if (file_exists('../cartengine/adminperms.php')) $shopping_cart = true;
else $shopping_cart = false;
require_once 'products-common.php';
require_once 'utility.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

define('DOWNLOADS_DATA_TYPE',1);

function data_record_definition()
{
    $data_record = array();
    $data_record['id'] = array('type' => INT_TYPE);
    $data_record['id']['key'] = true;
    $data_record['sequence'] = array('type' => INT_TYPE);
    $data_record['parent'] = array('type' => INT_TYPE);
    $data_record['data_type'] = array('type' => INT_TYPE);
    $data_record['label'] = array('type' => CHAR_TYPE);
    $data_record['data_value'] = array('type' => CHAR_TYPE);
    return $data_record;
}

function add_data_head_block($dialog)
{
    global $cms_base_url,$prefix,$shopping_cart;

    $head_block = "<script type=\"text/javascript\">\n       var cms_url = '";
    if (isset($cms_base_url)) $head_block .= $cms_base_url;
    $head_block .= "';\n       var prefix = '";
    if (isset($prefix)) $head_block .= $prefix;
    $head_block .= "';\n";
    if ($shopping_cart)
       $head_block .= "       script_prefix = '../cartengine/';\n";
    $head_block .= '    </script>';
    $dialog->add_head_line($head_block);
}

function display_data_fields($dialog,$edit_type,$row,$data_type)
{
    global $file_url,$prefix;

    $dialog->add_edit_row('Label:','label',$row,60);
    if ($data_type == DOWNLOADS_DATA_TYPE) {
       if ($edit_type == ADDRECORD) $frame = 'add_data';
       else $frame = 'edit_data';
       $dialog->add_browse_row('Filename:','data_value',$row,
                               60,$frame,$file_url,true,false,
                               false,false);
    }
    else $dialog->add_edit_row('Value:','data_value',$row,60);
}

function add_data()
{
    $data_type = get_form_field('DataType');
    $parent = get_form_field('Parent');
    $frame = get_form_field('Frame');
    $label = get_form_field('Label');

    $db = new DB;

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('productdata.css');
    $dialog->add_script_file('productdata.js');
    add_data_head_block($dialog);
    $dialog->set_body_id('add_product_data');
    $dialog->set_help('add_product_data');
    $dialog->start_body('Add '.$label);
    $dialog->set_button_width(((strlen($label) + 4) * 7) + 45);
    $dialog->start_button_column();
    $dialog->add_button('Add '.$label,'images/AddImage.png',
                        'process_add_data();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('productdata.php','AddData');
    $dialog->add_hidden_field('data_type',$data_type);
    $dialog->add_hidden_field('parent',$parent);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_hidden_field('Label',$label);
    $dialog->start_field_table();
    if ((! function_exists('display_custom_data_fields')) ||
        (! display_custom_data_fields($dialog,ADDRECORD,array(),$data_type)))
       display_data_fields($dialog,ADDRECORD,array(),$data_type);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_data()
{
    $parent = get_form_field('parent');
    $data_type = get_form_field('data_type');
    $label = get_form_field('Label');

    $db = new DB;

    $query = 'select sequence from product_data where data_type=? and ' .
             'parent=? order by sequence desc limit 1';
    $query = $db->prepare_query($query,$data_type,$parent);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);
          return false;
       }
       $sequence = 0;
    }
    $sequence = $row['sequence'];
    $sequence++;

    $data_record = data_record_definition();
    $db->parse_form_fields($data_record);
    $data_record['sequence']['value'] = $sequence;
    if (! $db->insert('product_data',$data_record)) {
       http_response(422,'Database Error: '.$db->error);
       return false;
    }

    http_response(201,$label.' Added');
    $product_id = $data_record['parent']['value'];
    log_activity('Added Product Data '.$label.' #' .
                 $db->insert_id().' to Product #'.$product_id);
    write_product_activity('Added Product Data '.$label .
                           get_product_activity_user($db),$product_id,$db);
}

function edit_data()
{
    $db = new DB;
    $id = get_form_field('id');
    $label = get_form_field('Label');
    $query = 'select * from product_data where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($label.' not found',0);
       return;
    }
    $frame = get_form_field('Frame');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('productdata.css');
    $dialog->add_script_file('productdata.js');
    add_data_head_block($dialog);
    $dialog_title = 'Edit '.$label.' (#'.$id.')';
    $dialog->set_body_id('edit_product_data');
    $dialog->set_help('edit_product_data');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_data();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('productdata.php','EditData');
    $dialog->add_hidden_field('id',$id);
    $data_type = get_row_value($row,'data_type');
    $dialog->add_hidden_field('DataType',$data_type);
    $dialog->add_hidden_field('Label',$label);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_field_table();
    if ((! function_exists('display_custom_data_fields')) ||
        (! display_custom_data_fields($dialog,UPDATERECORD,$row,$data_type)))
       display_data_fields($dialog,UPDATERECORD,$row,$data_type);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_data()
{
    $label = get_form_field('Label');
    $db = new DB;
    $data_record = data_record_definition();
    $db->parse_form_fields($data_record);
    if (! $db->update('product_data',$data_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.' Updated');
    $product_id = $data_record['parent']['value'];
    log_activity('Updated Product Data '.$label.' #' .
                 $data_record['id']['value']);
    write_product_activity('Updated Product Data '.$label .
                           get_product_activity_user($db),$product_id,$db);
}

function delete_data()
{
    $db = new DB;
    $id = get_form_field('id');
    $label = get_form_field('Label');
    $data_record = data_record_definition();
    $data_record['id']['value'] = $id;
    if (! $db->delete('product_data',$data_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$label.' Deleted');
    log_activity('Deleted '.$label.' #'.$id);
}

function resequence_data()
{
    $data_type = get_form_field('DataType');
    $parent = get_form_field('Parent');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');

    $db = new DB;

    $query = 'select id,sequence from product_data where data_type=? and ' .
             'parent=? order by sequence';
    $query = $db->prepare_query($query,$data_type,$parent);
    $product_data = $db->get_records($query,'id');
    if (! $product_data) {
       if (isset($db->error))
          http_response(422,'Database Error: '.$db->error);
       else http_response(410,'No Product Data Records to Resequence');
       return;
    }
    $max_sequence = 0;
    foreach ($product_data as $row) {
       if ($row['sequence'] && ($row['sequence'] > $max_sequence))
          $max_sequence = $row['sequence'];
    }
    foreach ($product_data as $id => $row) {
       if (! $row['sequence'])
          $product_data[$id]['new_sequence'] = ++$max_sequence;
       else $product_data[$id]['new_sequence'] = $row['sequence'];
       if ($old_sequence == -$id) $old_sequence = $max_sequence;
       if ($new_sequence == -$id) $new_sequence = $max_sequence;
    }
    foreach ($product_data as $id => $row) {
       $current_sequence = $row['new_sequence'];   $updated_sequence = $current_sequence;
       if ($current_sequence == $old_sequence) $updated_sequence = $new_sequence;
       else if ($old_sequence > $new_sequence) {
          if (($current_sequence >= $new_sequence) && ($current_sequence < $old_sequence))
             $updated_sequence = $current_sequence + 1;
       }
       else {
          if (($current_sequence > $old_sequence) && ($current_sequence <= $new_sequence))
             $updated_sequence = $current_sequence - 1;
       }
       if (($updated_sequence != $current_sequence) ||
           ($updated_sequence != $row['sequence'])) {
          $query = 'update product_data set sequence=? where id=?';
          $query = $db->prepare_query($query,$updated_sequence,$row['id']);
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             log_error($db->error);   http_response(422,'Database Error: '.$db->error);
             return;
          }
       }
    }

    http_response(201,'Product Data Resequenced');
    log_activity('Resequenced Product Data #'.$old_sequence.' to #' .
                 $new_sequence.' for Product #'.$parent);
}

function delete_download()
{
    global $docroot;

    $filename = get_form_field('Filename');
    $full_filename = $docroot;
    if ($filename[0] != '/') $full_filename .= '/';
    $full_filename .= $filename;
    if (file_exists($full_filename) && (! unlink($full_filename))) {
       log_error('Unable to delete download file '.$full_filename);
       http_response(422,'Unable to delete download file');   return;
    }
    http_response(201,'Download File Deleted');
    log_activity('Deleted Download File '.$full_filename);
}

function delete_duplicates()
{
    $db = new DB;
    $query = 'select d2.id from product_data d1 join product_data d2 on ' .
             'd1.parent=d2.parent and d1.data_type=d2.data_type and ' .
             'd1.label=d2.label and d1.data_value=d2.data_value and ' .
             'd1.id<d2.id';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) print 'Database Error: '.$db->error."<br>\n";
       else print "No Duplicate Product Data Records Found<br>\n";
       return;
    }
    foreach ($rows as $row) {
       $query = 'delete from product_data where id=?';
       $query = $db->prepare_query($query,$row['id']);
       if (! $db->query($query)) {
          print 'Database Error: '.$db->error."<br>\n";   return;
       }
    }
    log_activity('Deleted All Duplicate Product Data Records');
    print "Deleted All Duplicate Product Data Records<br>\n";
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');
if ($cmd == 'adddata') add_data();
else if ($cmd == 'processadddata') process_add_data();
else if ($cmd == 'editdata') edit_data();
else if ($cmd == 'updatedata') update_data();
else if ($cmd == 'deletedata') delete_data();
else if ($cmd == 'resequencedata') resequence_data();
else if ($cmd == 'deletedownload') delete_download();
else if ($cmd == 'deleteduplicates') delete_duplicates();

DB::close_all();

?>
