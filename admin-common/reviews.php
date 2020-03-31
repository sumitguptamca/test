<?php
/*
                       Inroads Shopping Cart - Reviews Tab

                       Written 2014-2018 by Randall Severy
                        Copyright 2014-2018 Inroads, LLC
*/

require '../engine/screen.php';
require '../engine/dialog.php';
require '../engine/db.php';
require 'utility.php';
if (file_exists('../cartengine/adminperms.php')) $shopping_cart = true;
else $shopping_cart = false;
$review_status_values = array('Pending','Approved','Disapproved');

function review_record_definition()
{
    $review_record = array();
    $review_record['id'] = array('type' => INT_TYPE);
    $review_record['id']['key'] = true;
    $review_record['parent'] = array('type' => INT_TYPE);
    $review_record['status'] = array('type' => INT_TYPE);
    $review_record['create_date'] = array('type' => INT_TYPE);
    $review_record['firstname'] = array('type' => CHAR_TYPE);
    $review_record['lastname'] = array('type' => CHAR_TYPE);
    $review_record['email'] = array('type' => CHAR_TYPE);
    $review_record['subject'] = array('type' => CHAR_TYPE);
    $review_record['rating'] = array('type' => INT_TYPE);
    $review_record['review'] = array('type' => CHAR_TYPE);
    return $review_record;
}

function add_script_prefix(&$screen)
{
    global $admin_path,$shopping_cart;

    $head_block = "<script type=\"text/javascript\">\n";
    $head_block .= "      admin_path = '".$admin_path."';\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix = '../cartengine/';\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
}

function add_review_filter_row($screen,$prompt,$field_name,$data,$use_index,
                               $all_label=null)
{
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write($prompt.':');
    if ($screen->skin) $screen->write('</span>');
    else $screen->write("<br>\n");
    $screen->write("<select name=\"".$field_name."\" id=\"".$field_name."\" " .
                   "onChange=\"filter_reviews();\" " .
                   "class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    if (! $all_label) $all_label = 'All '.$prompt.'s';
    $screen->add_list_item('',$all_label,false);
    if ($use_index)
       foreach ($data as $index => $value)
          $screen->add_list_item($index,$value,false);
    else foreach ($data as $value) $screen->add_list_item($value,$value,false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write('</div>');
    else $screen->write("</td></tr>\n");
}

function add_review_filters($screen,$status_values)
{
    add_review_filter_row($screen,'Status','status',$status_values,
                          true,'All');
}

function display_review_screen()
{
    global $review_status_values;

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_style_sheet('reviews.css');
    $screen->add_script_file('reviews.js');
    add_script_prefix($screen);
    $screen->set_body_id('reviews');
    $screen->set_help('reviews');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Reviews');
       $screen->start_title_filters();
       add_review_filters($screen,$review_status_values);
       add_search_box($screen,'search_reviews','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->set_button_width(148);
    $screen->start_button_column();
    $screen->add_button('Add Review','images/AddOrder.png',
                        'add_review();');
    $screen->add_button('Edit Review','images/EditOrder.png',
                        'edit_review();');
    $screen->add_button('Delete Review','images/DeleteOrder.png',
                        'delete_review();');
    if (! $screen->skin) {
       add_review_filters($screen,$review_status_values);
       add_search_box($screen,'search_reviews','reset_search');
    }
    $screen->end_button_column();
    $screen->write("          <script>load_grid();</script>\n");
    $screen->end_body();
}

function display_review_fields($db,$dialog,$edit_type,$row)
{
    global $review_status_values;

    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field('id',get_row_value($row,'id'));
       $parent = get_row_value($row,'parent');   $product_name = null;
       if ($parent) {
          $query = 'select name from products where id=?';
          $query = $db->prepare_query($query,$parent);
          $product_row = $db->get_record($query);
          if ($product_row) $product_name = $product_row['name'];
       }
    }
    else {
       $parent = get_form_field('parent');   $product_name = null;
    }
    $frame = get_form_field('frame');
    if ($frame) $dialog->add_hidden_field('frame',$frame);
    $dialog->add_hidden_field('parent',$parent);
    if (! $frame) {
       $dialog->start_row('Product:','middle');
       $dialog->write("<span id=\"parent_display\">\n");
       if ($product_name) $dialog->write($product_name);
       $dialog->write('</span>');
       if ($product_name)
          $dialog->write("<input type=\"button\" class=\"small_button\" " .
                         "value=\"Change...\" onClick=\"select_product();\">\n");
       else {
          $dialog->write("<input id=\"parent_select_button\" type=\"button\" " .
                         "class=\"small_button\" style=\"margin-left: 0px;\" " .
                         "value=\"Select...\" onClick=\"select_product();\">\n");
          $dialog->write("<input id=\"parent_change_button\" type=\"button\" " .
                         "class=\"small_button\" style=\"display: none;\" " .
                         "value=\"Change...\" onClick=\"select_product();\">\n");
       }
       $dialog->end_row();
    }
    $dialog->start_row('Status:','middle');
    $status = get_row_value($row,'status');
    $dialog->start_choicelist('status',null);
    for ($loop = 0;  $loop < count($review_status_values);  $loop++)
       if (isset($review_status_values[$loop]))
          $dialog->add_list_item($loop,$review_status_values[$loop],$status == $loop);
    $dialog->end_choicelist();
    $dialog->end_row();
    if ($edit_type == UPDATERECORD)
       $create_date = get_row_value($row,'create_date');
    else $create_date = time();
    $dialog->add_text_row('Created:',date('F j, Y g:i:s a',$create_date));
    $dialog->add_hidden_field('create_date',$create_date);
    $dialog->add_edit_row('First Name:','firstname',
                          get_row_value($row,'firstname'),90);
    $dialog->add_edit_row('Last Name:','lastname',
                          get_row_value($row,'lastname'),90);
    $dialog->add_edit_row('E-Mail:','email',get_row_value($row,'email'),90);
    $dialog->add_edit_row('Subject:','subject',get_row_value($row,'subject'),90);
    $dialog->start_row('Rating:','middle');
    $rating = get_row_value($row,'rating');
    $dialog->start_choicelist('rating',null);
    $dialog->add_list_item('','',false);
    for ($loop = 1;  $loop < 6;  $loop++)
       $dialog->add_list_item($loop,$loop,$rating == $loop);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_textarea_row('Review:','review',
                              get_row_value($row,'review'),15,91,WRAP_SOFT);
}

function add_review()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('reviews.css');
    $dialog->add_script_file('reviews.js');
    add_script_prefix($dialog);
    $dialog->set_body_id('add_review');
    $dialog->set_help('add_review');
    $dialog->start_body('Add Review');
    $dialog->set_button_width(115);
    $dialog->start_button_column();
    $dialog->add_button('Add Review','images/AddOrder.png',
                        'process_add_review();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('reviews.php','AddReview');
    $dialog->start_field_table();
    display_review_fields($db,$dialog,ADDRECORD,array());
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_review()
{
    $db = new DB;
    $review_record = review_record_definition();
    $db->parse_form_fields($review_record);
    if (! $db->insert('reviews',$review_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Review Added');
    $log_string = 'Added Review #'.$db->insert_id().' to Product #' .
                  $review_record['parent']['value'];
    log_activity($log_string);
}

function edit_review()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from reviews where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error))
          process_error('Database Error: '.$db->error,0);
       else process_error('Review not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('reviews.css');
    $dialog->add_script_file('reviews.js');
    $dialog_title = 'Edit Review (#'.$id.')';
    add_script_prefix($dialog);
    $dialog->set_body_id('edit_review');
    $dialog->set_help('edit_review');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_review();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('reviews.php','EditReview');
    $dialog->start_field_table();
    display_review_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_review()
{
    $db = new DB;
    $review_record = review_record_definition();
    $db->parse_form_fields($review_record);
    if (! $db->update('reviews',$review_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Review Updated');
    log_activity('Updated Review #'.$review_record['id']['value']);
}

function delete_review()
{
    $id = get_form_field('id');
    $db = new DB;
    $review_record = review_record_definition();
    $review_record['id']['value'] = $id;
    if (! $db->delete('reviews',$review_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Review Deleted');
    log_activity('Deleted Review #'.$id);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'addreview') add_review();
else if ($cmd == 'processaddreview') process_add_review();
else if ($cmd == 'editreview') edit_review();
else if ($cmd == 'updatereview') update_review();
else if ($cmd == 'deletereview') delete_review();
else display_review_screen();

DB::close_all();

?>

