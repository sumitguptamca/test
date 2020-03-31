<?php
/*
            Inroads Shopping Cart - Google Shopping Report Functions

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

function add_google_report_rows(&$screen,$db)
{
    $reports = array(
       'Errors' => 'Product Submission Errors',
       'Warnings' => 'Product Submission Warnings',
       'ListItems' => 'List Items'
    );
    $screen->start_hidden_row('Google Report:','google_row',true,'middle');
    $screen->start_choicelist('google_report');
    foreach ($reports as $report => $title)
       $screen->add_list_item($report,$title,false);
    $screen->end_choicelist();
    $screen->end_row();
}

function convert_google_field($field_name,$field_value,$output_type,$totals,
                              $report_data)
{
    global $product_id;

    switch ($field_name) {
       case 'id':
          $product_id = $field_value;   break;
       case 'name':
          if ($output_type == HTML_OUTPUT)
             $field_value = "\\<a href=\"#\" onClick=\"return parent." .
                "edit_product('".$product_id."');\"\\>".$field_value."\\</a\\>";
          break;
    }
    return $field_value;
}

function run_google_report($report,&$report_data)
{
    global $report_titles;

    if ($report != 'Google') return true;

    $google_report = get_form_field('GoogleReport');
    $db = $report_data['db'];
    $company_name = get_cart_config_value('companyname',$db);
    $title = get_form_field('Title');
    foreach ($report_titles as $index => $report_title) {
       if ($report_title == 'Google Reports') {
          $report_titles[$index] = 'Google '.$title;   break;
       }
    }
    $report_data['title'] = $company_name.' - Google '.$title;

    if (($google_report == 'Errors') || ($google_report == 'Warnings')) {
       if ($google_report == 'Errors') {
          $field = 'google_shopping_error';   $label = 'Error';
       }
       else {
          $field = 'google_shopping_warnings';   $label = 'Warnings';
       }
       $query = 'select id,name,'.$field.' from products where (not isnull(' .
                $field.')) and ('.$field.'!="") order by id';
       $align = array('left','left','left');
       $report_data['query'] = $query;
       $report_data['table_width'] = '100%';
       $report_data['tables'] = array('products');
       $report_data['columns'] = array('ID','Product',$label);
       $report_data['header_align'] = $align;
       $report_data['data_align'] = $align;
       $report_data['conversion_function'] = 'convert_google_field';
       return true;
    }

    require_once 'googleshopping.php';
    $google_shopping = new GoogleShopping($db);

    switch ($google_report) {
       case 'ListItems':
          $items = $google_shopping->list_items();
          if (empty($items)) return false;
          $columns = array();   $header_align = array();
          foreach ($items[0] as $column => $field_value) {
             $columns[] = $column;   $header_align[] = 'left';
          }
          $report_data['columns'] = $columns;
          $report_data['header_align'] = $header_align;
          $report_data['data_align'] = $header_align;
          $report_data['data'] = $items;
          break;
       default:
    }

    return true;
}

?>
