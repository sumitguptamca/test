<?php
/*
                 Inroads Shopping Cart - Amazon Report Functions

                       Written 2018-2019 by Randall Severy
                        Copyright 2018-2019 Inroads, LLC

*/

function add_amazon_report_rows(&$screen,$db)
{
    $reports = array(
       'Errors' => 'Product Submission Errors',
       'Warnings' => 'Product Submission Warnings',
       '_GET_FLAT_FILE_OPEN_LISTINGS_DATA_' => 'Inventory Report',
       '_GET_MERCHANT_LISTINGS_ALL_DATA_' => 'All Listings Report',
       '_GET_MERCHANT_LISTINGS_DATA_' => 'Active Listings Report',
       '_GET_MERCHANT_LISTINGS_INACTIVE_DATA_' => 'Inactive Listings Report',
       '_GET_MERCHANT_LISTINGS_DATA_BACK_COMPAT_' => 'Open Listings Report',
       '_GET_CATEGORY_LISTINGS_DATA_' => 'Category Listings Report',
       '_GET_MERCHANT_LISTINGS_DATA_LITE_' => 'Open Listings Report Lite',
       '_GET_MERCHANT_LISTINGS_DATA_LITER_' => 'Open Listings Report Liter',
       '_GET_MERCHANT_CANCELLED_LISTINGS_DATA_' => 'Canceled Listings Report',
       '_GET_CONVERGED_FLAT_FILE_SOLD_LISTINGS_DATA_' => 'Sold Listings Report',
       '_GET_MERCHANT_LISTINGS_DEFECT_DATA_' => 'Listing Quality and Suppressed Listing Report',
       '_GET_FLAT_FILE_ACTIONABLE_ORDER_DATA_' => 'Unshipped Orders Report',
       '_GET_FLAT_FILE_ORDERS_DATA_' => 'Orders from previous 60 days Report',
       '_GET_CONVERGED_FLAT_FILE_ORDER_REPORT_DATA_' => 'Order Report',
       '_GET_FLAT_FILE_PENDING_ORDERS_DATA_' => 'Pending Orders Report',
       '_GET_SELLER_FEEDBACK_DATA_' => 'Feedback Report',
       '_GET_AMAZON_FULFILLED_SHIPMENTS_DATA_' => 'FBA Amazon Fulfilled Shipments Report',
       '_GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_SALES_DATA_' => 'FBA Customer Shipment Sales Report',
       '_GET_FBA_FULFILMENT_CUSTOMER_SHIPMENT_PROMOTION_DATA_' => 'FBA Promotions Report',
       '_GET_AFN_INVENTORY_DATA_' => 'FBA Amazon Fulfilled Inventory Report',
       '_GET_FBA_FULFILLMENT_CURRENT_INVENTORY_DATA_' => 'FBA Daily Inventory History Report',
       '_GET_FBA_FULFILLMENT_MONTHLY_INVENTORY_DATA_' => 'FBA Monthly Inventory History Report',
       '_GET_FBA_FULFILLMENT_INVENTORY_RECEIPTS_DATA_' => 'FBA Received Inventory Report',
       '_GET_RESERVED_INVENTORY_DATA_' => 'FBA Reserved Inventory Report',
       '_GET_FBA_FULFILLMENT_INVENTORY_SUMMARY_DATA_' => 'FBA Inventory Event Detail Report',
       '_GET_FBA_FULFILLMENT_INVENTORY_ADJUSTMENTS_DATA_' => 'FBA Inventory Adjustments Report',
       '_GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA_' => 'FBA Inventory Health Report',
       '_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_' => 'FBA Manage Inventory Report',
       '_GET_FBA_MYI_ALL_INVENTORY_DATA_' => 'FBA Manage Inventory - Archived',
       '_GET_RESTOCK_INVENTORY_RECOMMENDATIONS_REPORT_' => 'Restock Inventory Report',
       '_GET_FBA_FULFILLMENT_INBOUND_NONCOMPLIANCE_DATA_' => 'FBA Inbound Performance Report',
       '_GET_STRANDED_INVENTORY_UI_DATA_' => 'FBA Stranded Inventory Report',
       '_GET_STRANDED_INVENTORY_LOADER_DATA_' => 'FBA Bulk Fix Stranded Inventory Report',
       '_GET_FBA_INVENTORY_AGED_DATA_' => 'FBA Inventory Age Report',
       '_GET_EXCESS_INVENTORY_DATA_' => 'FBA Manage Excess Inventory Report',
       '_GET_FBA_REIMBURSEMENTS_DATA_' => 'FBA Reimbursements Report',
       '_GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA_' => 'FBA Returns Report',
       '_GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_REPLACEMENT_DATA_' => 'FBA Replacements Report',
       '_GET_FBA_RECOMMENDED_REMOVAL_DATA_' => 'FBA Recommended Removal Report',
       '_GET_FBA_FULFILLMENT_REMOVAL_ORDER_DETAIL_DATA_' => 'FBA Removal Order Detail Report',
       '_GET_FBA_FULFILLMENT_REMOVAL_SHIPMENT_DETAIL_DATA_' => 'FBA Removal Shipment Detail Report'
    );
    $screen->start_hidden_row('Amazon Report:','amazon_row',true,'middle');
    $screen->start_choicelist('amazon_report');
    foreach ($reports as $report => $title)
       $screen->add_list_item($report,$title,false);
    $screen->end_choicelist();
    $screen->end_row();

    $screen->start_hidden_row('Options:','amazon_options_row',true,'middle');
    $screen->add_checkbox_field('use_last_amazon_report',
                                'Use Last Generated Report',false);
    $screen->end_row();
}

function convert_amazon_field($field_name,$field_value,$output_type,$totals,
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

function run_amazon_report($report,&$report_data)
{
    global $report_titles;

    if ($report != 'Amazon') return true;

    $amazon_report = get_form_field('AmazonReport');
    $db = $report_data['db'];
    $company_name = get_cart_config_value('companyname',$db);
    $title = get_form_field('Title');
    $use_last = get_form_field('UseLast');
    if ($use_last) $use_last = true;
    foreach ($report_titles as $index => $report_title) {
       if ($report_title == 'Amazon Reports') {
          $report_titles[$index] = 'Amazon '.$title;   break;
       }
    }
    $report_data['title'] = $company_name.' - Amazon '.$title;

    if (($amazon_report == 'Errors') || ($amazon_report == 'Warnings')) {
       if ($amazon_report == 'Errors') {
          $field = 'amazon_error';   $label = 'Error';
       }
       else {
          $field = 'amazon_warning';   $label = 'Warning';
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
       $report_data['conversion_function'] = 'convert_amazon_field';
       return true;
    }

    require_once 'amazon.php';
    require_once '../engine/http.php';

    $amazon = new Amazon;
    $amazon->debug = true;
    $amazon_data = $amazon->get_report($amazon_report,$use_last);
    if (empty($amazon_data)) return false;

    $columns = array();   $header_align = array();
    foreach ($amazon_data[0] as $column) {
       $columns[] = $column;
       $header_align[] = 'left';
    }
    unset($amazon_data[0]);
    $report_data['columns'] = $columns;
    $report_data['header_align'] = $header_align;
    $report_data['data_align'] = $header_align;
    $report_data['data'] = $amazon_data;
    return true;
}

?>
