<?php
/*
                Inroads Control Panel/Shopping Cart - Reports Tab

                       Written 2007-2019 by Randall Severy
                        Copyright 2007-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;
   require_once 'cartconfig-common.php';
   require_once 'orders-common.php';
   require_once 'shopping-common.php';
   if (! isset($reorder_reports)) {
      if ((! empty($enable_reorders)) &&
          file_exists('../admin/reorders-reports.php')) $reorder_reports = true;
      else $reorder_reports = false;
   }
   if ($reorder_reports) require_once '../admin/reorders-reports.php';
}
else {
   $shopping_cart = false;   $reorder_reports = false;
}
require_once 'adminperms.php';
require_once 'utility.php';

$script_name = basename($_SERVER['PHP_SELF']);
if ($script_name == 'reports.php') $custom_reports_module = false;
else $custom_reports_module = true;

define('HTML_OUTPUT','html');
define('EMAIL_OUTPUT','email');

define('LOG_ACTIVITY',0);
define('LOG_ERROR',1);
define('LOG_SQL',2);
define('LOG_PAYMENT',3);
define('LOG_SHIPPING',4);
define('LOG_EYE4FRAUD',5);
define('LOG_TAXCLOUD',7);
define('LOG_CHECKOUT',9);
define('LOG_VENDORS',10);

define('REPORT_TABLE',0);
define('REPORT_FORM',1);

if ($shopping_cart) {
   $report_ids = array('Sales','SalesSummary','NewCustomers','Products',
                       'Inventory','AllCustomers','CategoryProducts');
   $report_titles = array('Gross Sales','Sales Summary','New Customers',
                          'Product Popularity','Product Inventory',
                          'List All Customers','Category Products');
   if ($reorder_reports) add_reorders_reports($report_ids,$report_titles);
}
else {
   $report_ids = array();   $report_titles = array();
}
$report_ids[] = 'LogFiles';
$report_titles[] = 'Log Files';

class Spreadsheet {
function Spreadsheet($worksheet)
{
    $this->worksheet = $worksheet;
    $this->row = 1;
    $this->column = 0;
    $this->last_column = null;
}
function get_column($column_index)
{
    $first_letter = intval($column_index / 26);
    if ($first_letter) $column = chr($first_letter + 64);
    else $column = '';
    $second_letter = $column_index % 26;
    $column .= chr($second_letter + 65);
    return $column;
}
function set_num_columns($num_columns)
{
    $this->last_column = $this->get_column($num_columns - 1);
}
function new_row()
{
    $this->row++;
    $this->column = 0;
}
function set_range_colors($range,$fg_color=null,$bg_color=null)
{
    if ($fg_color)
       $this->worksheet->getStyle($range)->getFont()->getColor()->
              setARGB($fg_color);
    if ($bg_color)
       $this->worksheet->getStyle($range)->getFill()->
          setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->
          setARGB($bg_color);
}
function set_row_colors($fg_color=null,$bg_color=null,$row=null)
{
    if ($row === null) $row = $this->row;
    $range = 'A'.$row.':'.$this->last_column.$row;
    $this->set_range_colors($range,$fg_color,$bg_color);
}
function set_cell_color($fg_color=null,$bg_color=null,$column=null,$row=null)
{
    if ($column === null) $column = $this->column;
    if ($row === null) $row = $this->row;
    $range = $this->get_column($column).$row;
    $this->set_range_colors($range,$fg_color,$bg_color);
}
function set_range_bold($range)
{
   $this->worksheet->getStyle($range)->getFont()->setBold(true);
}
function set_row_bold($row=null)
{
    if ($row === null) $row = $this->row;
    $range = 'A'.$row.':'.$this->last_column.$row;
    $this->set_range_bold($range);
}
function set_cell_bold($column=null,$row=null)
{
    if ($column === null) $column = $this->column;
    if ($row === null) $row = $this->row;
    $range = $this->get_column($column).$row;
    $this->set_range_bold($range);
}
function add_cell($cell_value,$cell_format=null)
{
    if ($cell_format) 
       $this->worksheet->setCellValueExplicitByColumnAndRow($this->column,
                            $this->row,$cell_value,$cell_format);
    else $this->worksheet->setCellValueByColumnAndRow($this->column,$this->row,
                                                      $cell_value);
    $this->column++;
}
};

function convert_sales_field($field_name,$field_value,$output_type,$totals,
                             $report_data)
{
    if ($output_type != HTML_OUTPUT) return $field_value;
    if (($field_name != 'num_orders') && ($field_value != '')) {
       if ($field_value < 0) $field_value = '-$'.number_format(-$field_value,2);
       else $field_value = '$'.number_format($field_value,2);
    }
    else if (($field_name == 'average') && $totals) {
       if ($totals[0] == 0) $field_value = '';
       else {
          $total_column = 6;
          if ($report_data['features'] & USE_COUPONS) $total_column++;
          $field_value = '$'.number_format(($totals[$total_column]/$totals[0]),2);
       }
    }
    return $field_value;
}

function convert_sales_summary_field($field_name,$field_value,$output_type,
                                     $totals,&$report_data)
{
    if (isset($report_data['orders']) &&
        (($field_name == 'coupon') || ($field_name == 'coupon_amount') ||
         ($field_name == 'tax') || ($field_name == 'discount') ||
         ($field_name == 'fees') || ($field_name == 'total')) &&
        isset($report_data['orders'][$report_data['ordernum']])) {
       if ($field_name == 'total') $report_data['ordernum'] = '';
       return '';
    }

    switch ($field_name) {
       case 'order_number':
          $report_data['ordernum'] = $field_value;
          break;
       case 'qty':
          return number_format($field_value,0);
       case 'coupon_amount':
       case 'tax':
          if ($field_value == 0) return '';
       case 'total':
          if (($field_name == 'total') && isset($report_data['orders'])) {
             $report_data['orders'][$report_data['ordernum']] = true;
             $report_data['ordernum'] = '';
          }
       case 'coupons':
       case 'discount':
       case 'fees':
       case 'payment_amount':
          if ($output_type == HTML_OUTPUT) {
             if ($field_value < 0)
                $field_value = '-$'.number_format(-$field_value,2);
             else $field_value = '$'.number_format($field_value,2);
          }
          else $field_value = number_format($field_value,2,'.','');
          break;
       case 'order_date':
       case 'payment_date':
          if ($field_value != '') $field_value = date('n/j/y',$field_value);
          break;
       case 'attribute_names':
          $attributes = explode('|',$field_value);
          $field_value = '';
          $num_attributes = count($attributes);
          for ($loop = 0;  $loop < $num_attributes;  $loop += 2) {
             if (isset($attributes[$loop]) && ($attributes[$loop] != '') &&
                 isset($attributes[$loop + 1]) &&
                 ($attributes[$loop + 1] != '')) {
                if ($field_value != '') $field_value .= ', ';
                $field_value .= $attributes[$loop].': '.$attributes[$loop + 1];
             }
          }
          break;
    }
    return $field_value;
}

function convert_new_customers_field($field_name,$field_value,$output_type,
                                     $totals,$report_data)
{
    if (($field_name == 'order_percent') || ($field_name == 'mailing_percent')) {
       if ($totals) {
          if ($field_name == 'order_percent') {
             if ($totals[1] == 0) $field_value = '';
             else if ($output_type == HTML_OUTPUT) 
                $field_value = number_format((($totals[0]/$totals[1]) * 100),2).'%';
             else $field_value = number_format((($totals[0]/$totals[1]) * 100),2,'.','').'%';
          }
          else {
             if ($totals[3] == 0) $field_value = '';
             else if ($output_type == HTML_OUTPUT) 
                $field_value = number_format((($totals[0]/$totals[3]) * 100),2).'%';
             else $field_value = number_format((($totals[0]/$totals[3]) * 100),2,'.','').'%';
          }
       }
       else if ($field_value != '') {
          if ($output_type == HTML_OUTPUT) 
             $field_value = number_format($field_value,2).'%';
          else $field_value = number_format($field_value,2,'.','').'%';
       }
    }
    return $field_value;
}

function convert_products_field($field_name,$field_value,$output_type,
                                $totals,$report_data)
{
    switch ($field_name) {
       case 'id': return '%skip%';
       case 'product_name':
          $cart = null;   $cart_item = null;
          $field_value = get_html_product_name($field_value,GET_PROD_REPORT,
                                               $cart,$cart_item);
          break;
       case 'total_qty':
          return number_format($field_value,0);
       case 'attribute_names':
          $attributes = explode('|',$field_value);
          $field_value = '';
          $num_attributes = count($attributes);
          for ($loop = 0;  $loop < $num_attributes;  $loop += 2) {
             if (isset($attributes[$loop]) && ($attributes[$loop] != '') &&
                 isset($attributes[$loop + 1]) &&
                 ($attributes[$loop + 1] != '')) {
                if ($field_value != '') $field_value .= ', ';
                $field_value .= $attributes[$loop].': '.$attributes[$loop + 1];
             }
          }
          break;
       case 'sales': if ($output_type != HTML_OUTPUT) return $field_value;
                     $field_value = '$'.number_format($field_value,2);
                     break;
    }
    return $field_value;
}

function eval_inventory_record($row,$report_data)
{
    if ((! isset($row['attributes'])) || (! $row['attributes'])) return true;
    $product_id = $row['product_id'];
    if (! isset($report_data['product_attributes'][$product_id])) return false;
    $prod_attrs = $report_data['product_attributes'][$product_id];
    if (strpos($row['attributes'],'|') !== false)
       $options = explode('|',$row['attributes']);
    else $options = explode('-',$row['attributes']);
    foreach ($options as $option_id) {
       if (! $option_id) continue;
       $option_found = false;
       foreach ($prod_attrs as $index => $attribute) {
          if (! isset($report_data['attributes'][$attribute])) return false;
          if (! $report_data['attributes'][$attribute]['sub_product']) {
             unset($prod_attrs[$index]);   continue;
          }
          $attr_options = $report_data['attributes'][$attribute]['options'];
          if (isset($attr_options[$option_id])) {
             $option_found = true;   unset($prod_attrs[$index]);
          }
       }
       if (! $option_found) return false;
    }
    if (count($prod_attrs) > 0) {
       foreach ($prod_attrs as $index => $attribute) {
          if (! $report_data['attributes'][$attribute]['required'])
             unset($prod_attrs[$index]);
       }
    }
    if (count($prod_attrs) > 0) return false;
    return true;
}

function convert_inventory_field($field_name,$field_value,$output_type,
                                 $totals,$report_data)
{
    switch ($field_name) {
       case 'product_id': return '%skip%';
       case 'product_name':
          $field_value = get_html_product_name($field_value,GET_PROD_REPORT,
                                               null,null);
          break;
       case 'attributes':
          if (! $field_value) return $field_value;
          if (strpos($field_value,'|') !== false)
             $options = explode('|',$field_value);
          else $options = explode('-',$field_value);
          $product_id = $report_data['row']['product_id'];
          $prod_attrs = $report_data['product_attributes'][$product_id];
          $field_value = '';
          foreach ($prod_attrs as $attribute) {
             if (! $report_data['attributes'][$attribute]['sub_product'])
                continue;
             if ($field_value) $field_value .= ', ';
             $attribute_data = $report_data['attributes'][$attribute];
             $field_value .= $attribute_data['name'];
             $attr_options = $attribute_data['options'];
             foreach ($options as $option_id) {
                if (isset($attr_options[$option_id])) {
                   $field_value .= ': '.$attr_options[$option_id];   break;
                }
             }
          }
          break;
    }
    return $field_value;
}

function convert_all_customers_field($field_name,$field_value,$output_type,
                                     $totals,$report_data)
{
    switch ($field_name) {
       case 'mailing':
       case 'reminders':
          if ($field_value == 1) $field_value = 'Yes';
          else $field_value = 'No';
          break;
       case 'create_date':
          $field_value = date('n/j/y',$field_value);
          break;
    }
    return $field_value;
}

function build_query($report_data,$start_date,$end_date)
{
    $query = $report_data['query'];

    if ($start_date == 0) $where_condition = '';
    else if (isset($report_data['where'])) {
       $where_condition = $report_data['where'];
       $where_condition = str_replace('%start%',$start_date,$where_condition);
       $where_condition = str_replace('%end%',$end_date,$where_condition);
    }
    else $where_condition = '';
    $query = str_replace('%where%',$where_condition,$query);

    if ($start_date == 0) $and_condition = '';
    else if (isset($report_data['and'])) {
       $and_condition = $report_data['and'];
       $and_condition = str_replace('%start%',$start_date,$and_condition);
       $and_condition = str_replace('%end%',$end_date,$and_condition);
    }
    else $and_condition = '';
    $query = str_replace('%and%',$and_condition,$query);
    if ($start_date) {
       $query = str_replace('%start%',$start_date,$query);
       $query = str_replace('%end%',$end_date,$query);
    }

    return $query;
}

function increment_date($date_value,$summary)
{
    $month = date('n',$date_value);
    $day = date('d',$date_value);
    $year = date('Y',$date_value);
    switch ($summary) {
       case 'Day': $day += 1;   break;
       case 'Week': $day += 7;   break;
       case 'Month': $month++;
                     if ($month > 12) {
                        $month = 1;   $year++;
                     }
                     break;
       case 'Year': $year++;   break;
    }
    $date_value = mktime(0,0,0,$month,$day,$year);
    return $date_value;
}

function write_print_close_table($destination)
{
    print "    <table cellpadding=\"0\" cellspacing=\"0\" class=\"print_close_table\">\n";
    print "      <tr>\n";
    print "        <td><a href=\"\" onClick=\"window.print(); return false;\"><img\n";
    print "         src=\"images/print-page.jpg\" border=\"0\" alt=\"Print This Page\" " .
          "title=\"Print This Page\"></a></td>\n";
    print "        <td valign=\"middle\" style=\"padding-left:5px\" nowrap><a " .
          "class=\"print_close_links\"\n";
    print "         href=\"\" onClick=\"window.print(); return false;\">Print Page</a></td>\n";
    if ($destination != 'onscreen') {
       if ($destination == 'dialog') $close_funct = 'top.close_current_dialog();';
       else $close_funct = 'window.close();';
       print "        <td style=\"padding-left:10px;\"><a href=\"\" onClick=\"".$close_funct .
             " return false;\"><img\n";
       print "         src=\"images/close.png\" border=\"0\" alt=\"Close This Window\" " .
             "title=\"Close This Window\"></a></td>\n";
       print "        <td valign=\"middle\" style=\"padding-left:5px\" nowrap><a " .
             "class=\"print_close_links\"\n";
       print "         href=\"\" onClick=\"".$close_funct." return false;\">Close " .
             "Window</a></td>\n";
    }
    print "      </tr>\n";
    print "    </table>\n";
}

function write_report_total_row(&$report_data,$totals,$prompt='TOTALS',
                                $blank_line=true)
{
    global $use_spout;

    if (! isset($use_spout)) $use_spout = false;
    else if ($use_spout) $current_row = array();
    $report_data['processing_totals'] = true;
    if ($report_data['output_type'] == HTML_OUTPUT) {
       if ($blank_line && (! isset($report_data['table_spacing'])))
          print "<tr><td>&nbsp;</td></tr>\n";
       print "<tr valign=\"top\" class=\"report_row";
       if (! $blank_line) {
          if ($report_data['summary']) {
             if ($report_data['summary_index'] % 2) print ' even_report_row';
             else print ' odd_report_row';
             $report_data['summary_index']++;
          }
          else {
             if ($report_data['row_index'] % 2) print ' even_report_row';
             else print ' odd_report_row';
             $report_data['row_index']++;
          }
       }
       print "\"><td class=\"report_total\">".$prompt."</td>\n";
    }
    else if ($use_spout) $current_row[] = $prompt;
    else $report_data['spreadsheet']->add_cell($prompt);
    if ($report_data['summary']) $start_loop = 0;
    else $start_loop = 1;
    for ($loop = $start_loop;  $loop < count($report_data['total_flags']);
         $loop++) {
       if ($report_data['output_type'] == HTML_OUTPUT) {
          print "<td class=\"report_total\"";
          if (isset($report_data['data_align']))
             print " align=\"".$report_data['data_align'][$loop]."\"";
          print '>';
       }
       if ($report_data['total_flags'][$loop]) {
          $field_value = $totals[$loop];
          if (isset($report_data['conversion_function'])) {
             $report_data['total_field'] = true;
             if (isset($report_data['field_names'][$loop]))
                $field_name = $report_data['field_names'][$loop];
             else $field_name = '';
             $field_value = $report_data['conversion_function']
                ($field_name,$field_value,$report_data['output_type'],$totals,
                 $report_data);
             $report_data['total_field'] = false;
          }
       }
       else $field_value = '';
       if ($report_data['output_type'] == HTML_OUTPUT) {
          if ($field_value !== '') print $field_value;
          print "</td>\n";
       }
       else if ($use_spout) $current_row[] = $field_value;
       else $report_data['spreadsheet']->add_cell($field_value);
    }
    if ($report_data['output_type'] == HTML_OUTPUT) print "</tr>\n";
    else if ($use_spout) $report_data['spreadsheet']->addRow($current_row);
    else $report_data['spreadsheet']->new_row();
}

function process_report_error($error_msg)
{
    $output_type = get_form_field('Output');
    if (($output_type === null) || ($output_type == EMAIL_OUTPUT)) {
       log_error($error_msg);
       print $error_msg."\n";
    }
    else {
       if ($output_type == HTML_OUTPUT) $history = 0;
       else $history = -1;
       process_error($error_msg,$history);
    }
}

function report_error_handler($errno,$errmsg,$filename,$linenum,$vars)
{
    if (defined('E_DEPRECATED') && ($errno == E_DEPRECATED)) return true;
    $errortype = array (E_ERROR              => 'Error',
                        E_WARNING            => 'Warning',
                        E_PARSE              => 'Parsing Error',
                        E_NOTICE             => 'Notice',
                        E_CORE_ERROR         => 'Core Error',
                        E_CORE_WARNING       => 'Core Warning',
                        E_COMPILE_ERROR      => 'Compile Error',
                        E_COMPILE_WARNING    => 'Compile Warning',
                        E_USER_ERROR         => 'User Error',
                        E_USER_WARNING       => 'User Warning',
                        E_USER_NOTICE        => 'User Notice'
                );
    if (defined('E_STRICT')) $errortype[E_STRICT] = 'Runtime Notice';
    if (defined('E_RECOVERABLE_ERROR'))
       $errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
    $error_string = $errortype[$errno].': '.$errmsg.' in ' .
                    $filename.' on line '.$linenum;
    process_report_error($error_string);
    return true;
}

function generate_report($report_data)
{
    global $report_ids,$report_titles,$report_data_row,$custom_reports_module;
    global $shopping_cart,$use_spout;

    if (! isset($use_spout)) $use_spout = false;
    foreach ($report_ids as $report_index => $report_id)
       if ($report_id == $report_data['report']) break;
    $filename = $report_titles[$report_index].'.'.$report_data['output_type'];
    if ($report_data['output_type'] == 'xls') {
       $output_format = 'Excel5';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($report_data['output_type'] == 'xlsx') {
       $output_format = 'Excel2007';
       $mime_type = 'application/vnd.ms-excel';
    }
    else if ($report_data['output_type'] == 'ods') {
       $output_format = 'ODS';
       $mime_type = 'application/vnd.oasis.opendocument.spreadsheet';
    }
    else if ($report_data['output_type'] == 'csv') {
       $output_format = 'CSV';
       $mime_type = 'text/csv;';
    }
    else if ($report_data['output_type'] == 'txt') {
       $output_format = 'CSV';
       $mime_type = 'text/csv;';
    }
    if ($report_data['output_type'] == 'email') {
       $email_output = true;   $report_data['output_type'] = 'html';
       ob_start();
    }
    else $email_output = false;

    $utf8_output = false;
    $report_format = REPORT_TABLE;
    if (isset($report_data['summary'])) {
       $summary = $report_data['summary'];
       $start_date = $report_data['start'];
       $end_date = $report_data['end'];
       if ($summary == 'All') {
          if (isset($report_data['summary_format']))
             $report_format = $report_data['summary_format'];
          $summary = null;
       }
       else $end_date = increment_date($start_date,$summary) - 1;
       $query = build_query($report_data,$start_date,$end_date);
    }
    else if (isset($report_data['where']) && isset($report_data['start']) &&
             isset($report_data['end'])) {
       $summary = null;
       $start_date = $report_data['start'];
       $end_date = $report_data['end'];
       $query = build_query($report_data,$start_date,$end_date);
    }
    else if (isset($report_data['query'])) {
       $summary = null;
       $query = $report_data['query'];
       $query = str_replace('%where%','',$query);
    }
    else {
       $summary = null;   $query = null;
    }

    if (($report_format == REPORT_TABLE) &&
        isset($report_data['total_flags'])) {
       $process_totals = true;   $totals = array();
       $field_names = array();   $first_row = true;
    }
    else $process_totals = false;
    $report_data['process_totals'] = $process_totals;
    $report_data['report_format'] = $report_format;
    if (isset($delim)) $report_data['delim'] = $delim;
    $report_data['summary'] = $summary;

    $db = $report_data['db'];
    if (! isset($report_data['data'])) {
       if (! $query) {
          process_report_error('No Report Query Specified');   return;
       }
       $report_data['data'] = $db->get_records($query);
       if (! $report_data['data']) {
          if (isset($db->error)) {
             process_report_error('Database Error: '.$db->error);   return;
          }
       }
    }
    if (empty($report_data['data'])) $num_records = 0;
    $num_records = count($report_data['data']);

    if (! empty($report_data['tables'])) {
       $encrypted_fields = array();
       foreach ($report_data['tables'] as $table_name) {
          if (! isset($db->encrypted_fields[$table_name])) continue;
          $fields = $db->encrypted_fields[$table_name];
          foreach ($fields as $field_name)
             if (! in_array($field_name,$encrypted_fields))
                $encrypted_fields[] = $field_name;
       }
       $db->encrypted_fields['reports'] = $encrypted_fields;
    }

    if ($report_data['output_type'] == HTML_OUTPUT) {
       $destination = get_form_field('Destination');
       if ($report_format == REPORT_TABLE) {
          $num_columns = count($report_data['columns']);
          if ($summary) $num_columns++;
       }
       else $num_columns = 2;
       print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\">\n";
       print "<html>\n";
       print "  <head>\n";
       print "    <title>";
       if (isset($report_data['title'])) print $report_data['title'];
       else if (isset($report_data['title1'])) {
          print $report_data['title1'];
          if (isset($report_data['title2'])) print ' - '.$report_data['title2'];
       }
       print "</title>\n";
       $charset = ini_get('default_charset');
       if (! $charset) $charset = 'ISO-8859-1';
       print "    <meta http-equiv=\"Content-Type\" content=\"text/html; " .
             "charset=".$charset."\">\n";
       if (! $email_output) {
          print "    <link rel=\"stylesheet\" href=\"";
          if ($custom_reports_module && $shopping_cart) print '../cartengine/';
          print "report.css?v=".filemtime('report.css')."\" type=\"text/css\">\n";
          print "    <link rel=\"stylesheet\" href=\"../admin/colors.css\" " .
                "type=\"text/css\">\n";
          if (isset($report_data['head_content']))
             print $report_data['head_content'];
          if ($destination == 'dialog') {
             print "    <script type=\"text/javascript\" src=\"../engine/dialog.js?v=" .
                   filemtime('../engine/dialog.js')."\"></script>\n";
             print "    <script type=\"text/javascript\">set_current_dialog_title('" .
                   str_replace("'","\\'",$report_data['title'])."');</script>\n";
          }
       }
       print "  </head>\n";
       print '  <body';
       if ($destination == 'dialog')
          print " onLoad=\"dialog_onload(document,window,null);\"";
       print ">\n";
       if ($email_output) {
          print "<style type=\"text/css\">\n";
          print file_get_contents('../cartengine/report.css');
          print file_get_contents('../admin/colors.css');
          print "</style>\n";
       }
       else write_print_close_table($destination);
       print "<table border=\"0\"";
       if (isset($report_data['table_spacing']))
          print " cellspacing=\"".$report_data['table_spacing']."\"";
       else print " cellspacing=\"0\"";
       print " cellpadding=\"0\" align=\"center\"";
       if (isset($report_data['table_width']))
          print " width=\"".$report_data['table_width']."\"";
       print " class=\"";
       if ($report_format == REPORT_TABLE) print 'report_table';
       else print 'report_form';
       print "\">\n";
       print "<tr><td colspan=\"".$num_columns."\" align=\"center\">\n";
       if (isset($report_data['title']))
          print "    <h1 align=\"center\">".$report_data['title']."</h1>\n";
       else if (isset($report_data['title1'])) {
          print "    <h1 class=\"header1\">".$report_data['title1']."</h1>\n";
          if (isset($report_data['title2']))
             print "    <h2 class=\"header2\">".$report_data['title2']."</h2>\n";
       }
       print "</td></tr>\n";
       print "<tr class=\"report_header_sep_row\"><td colspan=\"".$num_columns .
             "\">&nbsp;</td></tr>\n";
    }
    else {
       set_error_handler('report_error_handler');
       if ($use_spout) {
          require_once '../engine/spout.php';
          header('Content-Type: '.$mime_type);
          header('Content-Disposition: attachment; filename="'.$filename.'"');
          header('Cache-Control: no-cache');
          $spreadsheet = create_spout_spreadsheet($output_format);
          if ($report_data['output_type'] == 'txt')
             $spreadsheet->setFieldDelimiter("\t");
          $spreadsheet->openToFile('php://output');
          if (isset($report_data['spreadsheet_title']))
             $spreadsheet->addRow(array($report_data['spreadsheet_title']));
       }
       else {
          require_once '../engine/excel.php';
          $excel = new PHPExcel();
          $worksheet = $excel->getActiveSheet();
          $spreadsheet = new Spreadsheet($worksheet);
          $spreadsheet->set_num_columns(count($report_data['columns']));
          if (($report_data['output_type'] == 'xls') ||
              ($report_data['output_type'] == 'xlsx')) {
             if (isset($report_data['column_widths'])) {
                foreach ($report_data['column_widths'] as $column => $width)
                   $worksheet->getColumnDimension($spreadsheet->
                      get_column($column))->setWidth($width);
             }
             if (isset($report_data['data_align'])) {
                foreach ($report_data['data_align'] as $column => $align) {
                   switch ($align) {
                      case 'left':
                         $align = PHPExcel_Style_Alignment::HORIZONTAL_LEFT;
                         break;
                      case 'center':
                         $align = PHPExcel_Style_Alignment::HORIZONTAL_CENTER;
                         break;
                      case 'right':
                         $align = PHPExcel_Style_Alignment::HORIZONTAL_RIGHT;
                         break;
                   }
                   $worksheet->getStyle($spreadsheet->get_column($column))->
                      getAlignment()->setHorizontal($align);
                }
             }
          }
          if (isset($report_data['spreadsheet_title'])) {
             $spreadsheet->add_cell($report_data['spreadsheet_title']);
             $spreadsheet->new_row();
          }
       }
       $report_data['spreadsheet'] = $spreadsheet;
    }

    if (($num_records == 0) && (! $summary)) {
       if ($report_data['output_type'] == HTML_OUTPUT) {
          print "<tr><td colspan=\"".$num_columns."\" align=\"center\">\n";
          print "<h2>No Results Found</h2>\n";
          print "</td></tr>\n";
       }
       else process_report_error('No Results Found');
    }
    else {
       if ($report_data['output_type'] == HTML_OUTPUT) {
          if ($report_format == REPORT_TABLE) {
             print "<tr valign=\"bottom\" class=\"report_header_row\">";
             if ($summary) print "<th>Range</th>\n";
             foreach ($report_data['columns'] as $index => $column_name) {
                print '<th nowrap';
                if ($index == 0) print " class=\"first_column\"";
                if (isset($report_data['header_align']))
                   print " align=\"".$report_data['header_align'][$index]."\"";
                if (isset($report_data['column_width']) &&
                    $report_data['column_width'][$index])
                   print " width=\"".$report_data['column_width'][$index]."\"";
                print ">".$column_name."</th>\n";
                if ($process_totals && $report_data['total_flags'][$index])
                   $totals[$index] = 0;
             }
             print "</tr>\n";
             if (! isset($report_data['table_spacing']))
                print "<tr><td colspan=\"".$num_columns."\">&nbsp;</td></tr>\n";
          }
       }
       else {
          if ($use_spout) $current_row = array();
          if ($summary) {
             if ($use_spout) $current_row[] = 'Range';
             else $spreadsheet->add_cell('Range');
          }
          foreach ($report_data['columns'] as $index => $column_name) {
             $column_name = str_replace('<br>',' ',$column_name);
             if ($use_spout) $current_row[] = $column_name;
             else $spreadsheet->add_cell($column_name);
             if ($process_totals && $report_data['total_flags'][$index])
                $totals[$index] = 0;
          }
          if ($use_spout) $spreadsheet->addRow($current_row);
          else $spreadsheet->new_row();
       }

       if ($summary) $summary_index = 0;
       if (isset($report_data['result_function'])) {
          if ($process_totals) {
             $report_data['totals'] = $totals;
             $report_data['field_names'] = $field_names;
          }
          $report_data['result_function']($report_data);
          if ($process_totals) {
             $totals = $report_data['totals'];
             $field_names = $report_data['field_names'];
          }
       }
       else {
          while (true) {
             foreach ($report_data['data'] as $row_index => $report_data_row) {
                if (! empty($report_data['tables']))
                   $db->decrypt_record('reports',$report_data_row);
                if (isset($report_data['eval_function'])) {
                   $report_data['row_index'] = $row_index;
                   if ($summary) $report_data['summary_index'] = $summary_index;
                   if (! $report_data['eval_function']($report_data_row,
                                                       $report_data))
                      continue;
                   $row_index = $report_data['row_index'];
                   if ($summary) $summary_index = $report_data['summary_index'];
                }
                if (isset($report_data['conversion_function']))
                   $report_data['row'] = $report_data_row;
                if (($report_data['output_type'] == HTML_OUTPUT) &&
                    ($report_format == REPORT_TABLE)) {
                   print "<tr valign=\"top\" class=\"report_row";
                   if ($summary) {
                      if ($summary_index % 2) print ' even_report_row';
                      else print ' odd_report_row';
                   }
                   else if ($row_index % 2) print ' even_report_row';
                   else print ' odd_report_row';
                   print "\">";
                }
                $index = 0;
                if ($use_spout) $current_row = array();
                if ($summary) {
                   if ($row_index == 0) switch ($summary) {
                      case 'Day': $range_date = date('n/j/y',$start_date);   break;
                      case 'Week': $range_date = date('n/j/y',$start_date).'-' .
                                                 date('n/j/y',$end_date);
                                   break;
                      case 'Month': $range_date = date('M Y',$start_date);   break;
                      case 'Year': $range_date = date('Y',$start_date);   break;
                   }
                   if ($report_data['output_type'] == HTML_OUTPUT) {
                      print '<td';
                      if ($report_format == REPORT_TABLE)
                         print " class=\"first_column\"";
                      print '>'.$range_date."</td>\n";
                   }
                   else if ($use_spout) $current_row[] = $range_date;
                   else $spreadsheet->add_cell($range_date);
                }
                foreach ($report_data_row as $field_name => $field_value) {
                   if ($process_totals) {
                      if ($report_data['total_flags'][$index]) {
                         $totals[$index] += $field_value;
                         $last_total_value = $field_value;
                      }
                      else $last_total_value = null;
                      if ($first_row) $field_names[$index] = $field_name;
                   }
                   if (isset($report_data['conversion_function'])) {
                      $report_data['row_index'] = $row_index;
                      if ($summary) $report_data['summary_index'] = $summary_index;
                      $field_value = $report_data['conversion_function']($field_name,
                                        $field_value,$report_data['output_type'],
                                        null,$report_data);
                      if ((! $field_value) && $process_totals && $last_total_value)
                         $totals[$index] -= $last_total_value;
                      $row_index = $report_data['row_index'];
                      if ($summary) $summary_index = $report_data['summary_index'];
                   }
                   if ($field_value == '%skip%') {}
                   else if ($report_data['output_type'] == HTML_OUTPUT) {
                      if ($report_format == REPORT_FORM) {
                         print "<tr class=\"report_row";
                         if ($row_index % 2) print ' even_report_row';
                         else print ' odd_report_row';
                         print "\"><td class=\"report_prompt first_column\">";
                         if (isset($report_data['form_columns']))
                            print $report_data['form_columns'][$index];
                         else print $report_data['columns'][$index];
                         print ":</td>\n";
                      }
                      print '<td';
                      if (($report_format == REPORT_TABLE) &&
                          isset($report_data['data_align']) &&
                          isset($report_data['data_align'][$index]))
                         print ' align="'.$report_data['data_align'][$index].'"';
                      if ((! $summary) && ($index == 0)) {
                         if ($report_format == REPORT_TABLE) {
                            print ' class="first_column';
                            if (isset($report_data['tdclass'])) {
                               print ' '.$report_data['tdclass'];
                               unset($report_data['tdclass']);
                            }
                            print '"';
                         }
                         else {
                            if (isset($report_data['tdclass'])) {
                               print ' class="'.$report_data['tdclass'].'"';
                               unset($report_data['tdclass']);
                            }
                            if ($row_index == 0) print ' width="50%"';
                         }
                      }
                      else if (isset($report_data['tdclass'])) {
                         print ' class="'.$report_data['tdclass'].'"';
                         unset($report_data['tdclass']);
                      }
                      if (isset($report_data['tdstyle'])) {
                         print ' style="'.$report_data['tdstyle'].'"';
                         unset($report_data['tdstyle']);
                      }
                      if (isset($report_data['wrap']) &&
                          isset($report_data['wrap'][$index]) &&
                          (! $report_data['wrap'][$index])) print ' nowrap';
                      print '>';
                      $field_value = str_replace('<','&lt;',$field_value);
                      $field_value = str_replace('>','&gt;',$field_value);
                      $field_value = str_replace("\n",'<br>',$field_value);
                      $field_value = str_replace("\r",'',$field_value);
                      $field_value = str_replace("\\&lt;",'<',$field_value);
                      $field_value = str_replace("\\&gt;",'>',$field_value);
                      $field_value = str_replace("\\<br>","\n",$field_value);
                      print $field_value;
                      print "</td>\n";
                      if ($report_format == REPORT_FORM) print "</tr>\n";
                      $index++;
                   }
                   else if ($use_spout) {
                      $current_row[] = $field_value;   $index++;
                   }
                   else {
                      if ((! $utf8_output) && ($output_format == 'CSV') &&
                          detect_utf8($field_value)) $utf8_output = true;
                      if (isset($report_data['format'],
                                $report_data['format'][$index]))
                         $cell_format = $report_data['format'][$index];
                      else $cell_format = null;
                      $spreadsheet->add_cell($field_value,$cell_format);
                      $index++;
                   }
                }
                if ($report_data['output_type'] == HTML_OUTPUT) {
                   if ($report_format == REPORT_TABLE) print "</tr>\n";
                }
                else if ($use_spout) $spreadsheet->addRow($current_row);
                else $spreadsheet->new_row();
                if ($process_totals && $first_row) {
                   $first_row = false;
                   $report_data['field_names'] = $field_names;
                }
                $row_index++;
             }
             if ($summary) {
                $start_date = increment_date($start_date,$summary);
                if ($start_date >= $report_data['end']) break;
                else {
                   $end_date = increment_date($start_date,$summary) - 1;
                   $query = build_query($report_data,$start_date,$end_date);
                   $report_data['data'] = $db->get_records($query);
                   if (! $report_data['data']) {
                      if (isset($db->error)) {
                         process_report_error('Database Error: '.$db->error);   return;
                      }
                   }
                }
                $summary_index++;
             }
             else break;
          }
       }
       if (isset($report_data['eval_function'])) {
          $report_data['row_index'] = $row_index;
          if ($summary) $report_data['summary_index'] = $summary_index;
          $empty_row = array();
          $report_data['eval_function']($empty_row,$report_data);
       }
       if ($process_totals) {
          $report_data['field_names'] = $field_names;
          write_report_total_row($report_data,$totals);
       }
    }
    if ($report_data['output_type'] == HTML_OUTPUT)
       print "</table>\n  </body>\n</html>\n";
    else if ($use_spout) $spreadsheet->close();
    else {
       $excel->setActiveSheetIndex(0);
       $writer = PHPExcel_IOFactory::createWriter($excel,$output_format);
       if ($report_data['output_type'] == 'txt') $writer->setDelimiter("\t");
       header('Content-Type: '.$mime_type);
       header('Content-Disposition: attachment; filename="'.$filename.'"');
       header('Cache-Control: no-cache');
       if ($utf8_output) print "\xEF\xBB\xBF"; // UTF-8 BOM
       $writer->save('php://output');
    }

    if ($email_output) {
       $report_content = ob_get_contents();
       ob_end_clean();
       require_once '../engine/email.php';
       $report_title = $report_titles[$report_index];
       if (isset($report_data['email_address']))
          $email_address = $report_data['email_address'];
       else $email_address = get_form_field('EmailAddress');
       $query = 'select config_value from config where config_name=' .
                '"admin_email"';
       $row = $db->query($query);
       if ($row && $row['config_value']) $from_addr = $row['config_value'];
       else $from_addr = $email_address;
       if (isset($report_data['title'])) $subject = $report_data['title'];
       else $subject = $report_title;
       $email = new Email(null);
       $email->name = 'Report E-Mail';
       $email->template_info = array();
       $email->template_info['format'] = HTML_FORMAT;
       $email->template_info['subject'] = $subject;
       $email->template_info['from_addr'] = $from_addr;
       $email->template_info['to_addr'] = $email_address;
       $email->attachments = array();
       $email->template_content = $report_content;
       if (! $email->send(true,false)) {
          $error = 'Unable to send report e-mail: '.$email->error;
          if (! isset($report_data['silent'])) http_response(422,$error);
          log_error($error);   return;
       }
       if (! isset($report_data['silent'])) http_response(201,'Report Sent');
       log_activity('Sent Report '.$report_title.' (' .
                    $report_ids[$report_index].') to '.$email_address);
    }
    else log_activity('Viewed Report '.$report_titles[$report_index].' (' .
                      $report_ids[$report_index].')');
}

$month_names = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep',
                     'Oct','Nov','Dec');

function parse_log_date($buffer)
{
    global $month_names;

    $start_pos = strstr($buffer,'[');
    if (! $start_pos) return 0;
    if (strstr(substr($start_pos,0,15),'-')) {
       list($date_string,$timestr) = sscanf($start_pos,'[%s %s]');
       $date_array = explode('-',$date_string);
       if (count($date_array) != 3) return 0;
       $day = $date_array[0];   $month_string = $date_array[1];
       $year = $date_array[2];
    }
    else list($weekday,$month_string,$day,$year,$timestr) =
            sscanf($start_pos,'[%s %s %s %s %s]');
    $month = -1;
    for ($loop = 0;  $loop < 12;  $loop++)
       if ($month_names[$loop] == $month_string) {
          $month = $loop + 1;   break;
      }
    if ($month == -1) return 0;
    $date_value = mktime(12,0,0,intval($month),intval($day),intval($year));
    return $date_value;
}

function add_xmlview_link(&$buffer,$num_entries)
{
    $start_pos = strpos($buffer,'Sent: ');
    if ($start_pos === false) {
       $start_pos = strpos($buffer,'Response: ');
       $offset = 8;
    }
    else $offset = 4;
    if ($start_pos === false) return;
    if (get_browser_type() == FIREFOX)
       $anchor_tag = "<a href=\"#\" onClick=\"view_xml(".$num_entries."); return false;\">";
    else {
       $start_date = get_form_field('StartDate');
       $end_date = get_form_field('EndDate');
       $anchor_tag = "<a target=\"new\" href=\"reports.php?cmd=runreport&Report=" .
                     "LogFiles&Output=0&Type=4&StartDate=".$start_date .
                     "&EndDate=".$end_date."&XmlLine=".$num_entries."\">";
    }
    $buffer = substr($buffer,0,$start_pos).$anchor_tag .
              substr($buffer,$start_pos,$offset).'</a>' .
              substr($buffer,$start_pos + $offset);
}

function process_subcategories($category_list,$subcategories,$categories,
                               $cat_id,$parent_name,$cat_array)
{
    foreach ($subcategories as $subcategory) {
       if ($subcategory['parent'] == $cat_id) {
          $subcat_id = $subcategory['related_id'];
          if (! isset($categories[$subcat_id])) continue;
          if (in_array($subcat_id,$cat_array)) continue;
          $subcat_array = $cat_array;
          $subcat_array[] = $subcat_id;
          $category_list[$subcat_id] = $categories[$subcat_id];
          $cat_name = $categories[$subcat_id]['name'];
          if ($parent_name &&
              (substr($cat_name,0,strlen($parent_name)) == $parent_name))
             $new_name = '';
          else $new_name = $parent_name;
          if ($new_name) $new_name .= ' > ';
          $new_name .= $cat_name;
          $category_list[$subcat_id]['name'] = $new_name;
          $category_list = process_subcategories($category_list,$subcategories,
                              $categories,$subcat_id,$cat_name,$subcat_array);
       }
    }
    return $category_list;
}

function process_category_product_data(&$data,$category,$cat_products,
                                       $products,$cat_status,$prod_status)
{
    $cat_id = $category['id'];
    $data_row = array();
    $data_row[0] = $category['name'];
    $data_row[1] = $cat_id;
    if ($category['status']) $status = $category['status'];
    else $status = 0;
    if (isset($cat_status[$status])) $status = $cat_status[$status];
    $data_row[2] = $status;
    $data_row[3] = $category['display_name'];
    $data_row[4] = '';   $data_row[5] = '';   $data_row[6] = '';
    $data_row[7] = '';
    $data[] = $data_row;
    foreach ($cat_products as $cat_product) {
       if ($cat_product['parent'] == $cat_id) {
          $product_id = $cat_product['related_id'];
          if (! isset($products[$product_id])) continue;
          $product = $products[$product_id];
          $data_row = array('','','','');
          $data_row[4] = $product['name'];
          $data_row[5] = $product_id;
          if ($product['status']) $status = $product['status'];
          else $status = 0;
          if (isset($prod_status[$status])) $status = $prod_status[$status];
          $data_row[6] = $status;
          $data_row[7] = $product['display_name'];
          $data[] = $data_row;
       }
    }
}

function load_category_product_data($db)
{
    global $top_category;

    $query = 'select * from subcategories order by sequence';
    $subcategories = $db->get_records($query);
    if ((! $subcategories) && isset($db->error)) {
       process_report_error("Database Error: ".$db->error);   return null;
    }

    $query = 'select id,name,display_name,status from categories';
    $categories = $db->get_records($query,'id');
    if ((! $categories) && isset($db->error)) {
       process_report_error("Database Error: ".$db->error);   return null;
    }

    $query = 'select * from category_products order by sequence';
    $cat_products = $db->get_records($query);
    if ((! $cat_products) && isset($db->error)) {
       process_report_error("Database Error: ".$db->error);   return null;
    }

    $query = 'select id,name,display_name,status from products';
    $products = $db->get_records($query,'id');
    if ((! $products) && isset($db->error)) {
       process_report_error("Database Error: ".$db->error);   return null;
    }

    $query = 'select id,label from cart_options where table_id=1';
    $cat_status = $db->get_records($query,'id','label');
    if ((! $cat_status) && isset($db->error)) {
       process_report_error("Database Error: ".$db->error);   return null;
    }

    $query = 'select id,label from cart_options where table_id=0';
    $prod_status = $db->get_records($query,'id','label');
    if ((! $prod_status) && isset($db->error)) {
       process_report_error("Database Error: ".$db->error);   return null;
    }

    $category_list = array();
    $category_list[$top_category] = $categories[$top_category];
    $category_list[$top_category]['level'] = 0;
    $cat_array = array($top_category);
    $category_list = process_subcategories($category_list,$subcategories,
                        $categories,$top_category,'',$cat_array);

    $data = array();
    foreach ($category_list as $cat_id => $category) {
       process_category_product_data($data,$category,$cat_products,$products,
                                     $cat_status,$prod_status);
       unset($categories[$category['id']]);
    }
    foreach ($categories as $cat_id => $category)
       process_category_product_data($data,$category,$cat_products,$products,
                                     $cat_status,$prod_status);
    return $data;
}

function generate_logfiles_report($report_type,$start_date,$end_date)
{
    global $shopping_cart,$activity_log,$error_log,$db_log,$payment_log;
    global $shipping_log;
    if (! $shopping_cart) global $company_name;

    require_once '../engine/modules.php';
    if (is_numeric($report_type) && ($report_type == LOG_ACTIVITY)) {
       $log_filename = $activity_log;   $title = 'Activity Log File';
    }
    else if ($report_type == LOG_ERROR) {
       $log_filename = $error_log;   $title = 'Error Log File';
    }
    else if ($report_type == LOG_SQL) {
       $log_filename = $db_log;   $title = 'SQL Log File';
    }
    else if ($report_type == LOG_PAYMENT) {
       $log_filename = $payment_log;   $title = 'Payment Log File';
    }
    else if ($report_type == LOG_SHIPPING) {
       $log_filename = $shipping_log;   $title = 'Shipping Log File';
    }
    else if ($report_type == LOG_EYE4FRAUD) {
       $log_filename = '../admin/eye4fraud.log';   $title = 'Eye4Fraud Log File';
    }
    else if ($report_type == LOG_TAXCLOUD) {
       $log_filename = '../admin/taxcloud.log';   $title = 'TaxCloud Log File';
    }
    else if ($report_type == LOG_CHECKOUT) {
       $log_filename = '../admin/checkout.log';   $title = 'Checkout Log File';
    }
    else if ($report_type == LOG_VENDORS) {
       $log_filename = '../admin/vendors.log';   $title = 'Vendors Log File';
    }
    else if (call_module_event('report_log_file_info',
                               array($report_type,&$log_filename,&$title),null,
                               true,true)) {}
    else if ($shopping_cart &&
             call_shopping_event('report_log_file_info',
                                 array($report_type,&$log_filename,&$title),
                                 true,true)) {}
    else if (function_exists('init_custom_logfile_info') &&
             init_custom_logfile_info($report_type,$log_filename,$title)) {}

    $log_file = @fopen($log_filename,'r');
    $num_entries = 0;
    if ($report_type == LOG_SHIPPING) $xml_line = get_form_field('XmlLine');
    else $xml_line = null;

    if ($xml_line == null) {
       $destination = get_form_field('Destination');
       if ($shopping_cart)
          $company_name = get_cart_config_value('companyname');
       print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\">\n";
       print "<html>\n";
       print "  <head>\n";
       print '    <title>'.$company_name;
       print ' - '.$title."</title>\n";
       $charset = ini_get('default_charset');
       if (! $charset) $charset = 'ISO-8859-1';
       print "    <meta http-equiv=\"Content-Type\" content=\"text/html; " .
             "charset=".$charset."\">\n";
       print "    <link rel=\"stylesheet\" href=\"report.css?v=" .
             filemtime('report.css')."\" type=\"text/css\">\n";
       if (($report_type == LOG_SHIPPING) && (get_browser_type() == FIREFOX)) {
          print "    <script type=\"text/javascript\">\n";
          print "       function view_xml(line_num) {\n";
          print "          var url = top.location.href;\n";
          print "          url += '&XmlLine=' + line_num;\n";
          print "          var window_options = 'toolbar=yes,directories=no," .
                "menubar=yes,status=no,scrollbars=yes,resizable=yes';\n";
          print "          var xml_window = window.open(url,'XmlLine',window_options);\n";
          print "          if (! xml_window) alert('Unable to open report window, " .
                "please enable popups for this domain');\n";
          print "       }\n";
          print "    </script>\n";
       }
       if ($destination == 'dialog') {
          print "    <script type=\"text/javascript\" src=\"../engine/dialog.js?v=" .
                filemtime('../engine/dialog.js')."\"></script>\n";
          print "    <script type=\"text/javascript\">set_current_dialog_title('" .
                str_replace("'","\\'",$title)."');</script>\n";
       }
       print "  </head>\n";
       print '  <body';
       if ($destination == 'dialog')
          print " onLoad=\"dialog_onload(document,window,null);\"";
       print ">\n";
       write_print_close_table($destination);
       print "    <h1 align=\"center\">".$title."</h1>\n<p>\n";
       print "<pre>\n";
    }
    else header('Content-type: text/xml');

    if ($log_file) while (!feof($log_file)) {
       $buffer = fgets($log_file);
       $entry_date = parse_log_date($buffer);
       if (($entry_date < $start_date) || ($entry_date > $end_date)) continue;
       if ($xml_line != null) {
          if ($num_entries != $xml_line) {
             $num_entries++;   continue;
          }
          $start_pos = strpos($buffer,'<?xml ');
          if ($start_pos !== false) {
             $second_pos = strpos($buffer,'<?xml ',$start_pos + 5);
             if ($second_pos !== false)
                print substr($buffer,$start_pos,21).'<response>' .
                      substr($buffer,$start_pos + 21,$second_pos - $start_pos - 21) .
                      substr($buffer,$second_pos + 21).'</response>';
             else print substr($buffer,$start_pos);
          }
       }
       else {
          if (($report_type == LOG_SHIPPING) && (strpos($buffer,'<?xml ') !== false))
             $add_link = true;
          else $add_link = false;
          $buffer = str_replace('<','&lt;',$buffer);
          $buffer = str_replace('>','&gt;',$buffer);
          if ($add_link) add_xmlview_link($buffer,$num_entries);
          print $buffer;
       }
       $num_entries++;
    }

    if ($log_file) fclose($log_file);
    if ($xml_line == null) {
       print "</pre>\n";
       if ($num_entries == 0) print "<h2 align=center>No Log File Entries Found</h2>\n";
       print "  </body>\n</html>\n";
       log_activity('Viewed '.$title.' Report');
    }
    else log_activity('Viewed Line #'.$xml_line.' of '.$title.' Report');
}

function add_date_range_row($screen,$prompt,$suffix='',$range=null,
   $start_date=null,$end_date=null,$include_future=false)
{
    $months = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',
                    6=>'June',7=>'July',8=>'August',9=>'September',
                    10=>'October',11=>'November',12=>'December');
    $quarters = array(1=>'1st',2=>'2nd',3=>'3rd',4=>'4th');

    if (function_exists('get_cart_config_value')) $fiscal = 'Fiscal ';
    else $fiscal = '';
    $screen->start_hidden_row($prompt,'range'.$suffix.'_row',true,'middle');
    $screen->start_choicelist('range'.$suffix,'select_range(\''.$suffix.'\');',
                              'select range_select');
    $screen->add_list_item('All','All<br>',(! $range));
    $screen->add_list_item('Yesterday','Yesterday',$range == 'Yesterday');
    $screen->add_list_item('Today','Today',$range == 'Today');
    if ($include_future)
       $screen->add_list_item('Tomorrow','Tomorrow',$range == 'Tomorrow');
    $screen->add_list_item('LastWeek','Last Week',$range == 'LastWeek');
    $screen->add_list_item('ThisWeek','This Week',$range == 'ThisWeek');
    if ($include_future)
       $screen->add_list_item('NextWeek','Next Week',$range == 'NextWeek');
    $screen->add_list_item('LastMonth','Last Month',$range == 'LastMonth');
    $screen->add_list_item('ThisMonth','This Month',$range == 'ThisMonth');
    if ($include_future)
       $screen->add_list_item('NextMonth','Next Month',$range == 'NextMonth');
    $screen->add_list_item('LastQuarter','Last '.$fiscal.'Quarter',
                           $range == 'LastQuarter');
    $screen->add_list_item('ThisQuarter','This '.$fiscal.'Quarter',
                           $range == 'ThisQuarter');
    if ($include_future)
       $screen->add_list_item('NextQuarter','Next '.$fiscal.'Quarter',
                              $range == 'NextQuarter');
    $screen->add_list_item('LastYear','Last '.$fiscal.'Year',
                           $range == 'LastYear');
    $screen->add_list_item('ThisYear','This '.$fiscal.'Year',
                           $range == 'ThisYear');
    if ($include_future)
       $screen->add_list_item('NextYear','Next '.$fiscal.'Year',
                              $range == 'NextYear');
    $screen->add_list_item('Range','Date Range:',$range == 'Range');
    $screen->add_list_item('Month','Month:',$range == 'Month');
    $screen->add_list_item('Quarter','Quarter:',$range == 'Quarter');
    $screen->end_choicelist();

    $screen->write('<table id="range'.$suffix.'_table" style="display:');
    if ($range == 'Range') $screen->write('inline-block');
    else $screen->write('none');
    $screen->write(';" cellspacing="0" cellpadding="0">'."\n");
    $screen->write("<tr valign=\"middle\"><td>\n");
    if (! $start_date) $start_date = mktime(0,0,0,1,1,date('Y'));
    $screen->add_date_field('range'.$suffix.'_start_date',$start_date);
    $screen->write("</td><td>&nbsp;&nbsp;-&nbsp;&nbsp;</td><td>\n");
    if (! $end_date) $end_date = mktime(12,59,59,12,31,date('Y'));
    $screen->add_date_field('range'.$suffix.'_end_date',$end_date);
    $screen->end_row();
    $screen->end_table();

    $screen->write('<table id="month'.$suffix.'_table" style="display:');
    if ($range == 'Month') $screen->write('inline-block');
    else $screen->write('none');
    $screen->write(';" cellspacing="0" cellpadding="0">'."\n");
    $screen->write("<tr valign=\"middle\"><td>\n");
    $current_month = date('n');
    $screen->start_choicelist('month'.$suffix.'_month');
    foreach ($months as $month => $label)
       $screen->add_list_item($month,$label,$month == $current_month);
    $screen->end_choicelist();
    $screen->write("</td><td>\n");
    $current_year = date('Y');
    $screen->start_choicelist('month'.$suffix.'_year');
    for ($year = 2010;  $year <= $current_year;  $year++)
       $screen->add_list_item($year,$year,$year == $current_year);
    $screen->end_choicelist();
    $screen->end_row();
    $screen->end_table();

    $screen->write('<table id="quarter'.$suffix.'_table" style="display:');
    if ($range == 'Quarter') $screen->write('inline-block');
    else $screen->write('none');
    $screen->write(';" cellspacing="0" cellpadding="0">'."\n");
    $screen->write("<tr valign=\"middle\"><td>\n");
    if (function_exists('get_cart_config_value'))
       $fiscal_year_start = intval(get_cart_config_value('fiscalyear')) + 1;
    else $fiscal_year_start = 1;
    $current_quarter = 1;   $month = date('n');
    $fiscal_start = $fiscal_year_start;
    $fiscal_end = $fiscal_start + 2;
    if ($fiscal_end > 12) $fiscal_end -= 12;
    while (true) {
       if ($fiscal_end < $fiscal_start) {
          if (($month >= $fiscal_start) || ($month <= $fiscal_end)) break;
       }
       else if (($month >= $fiscal_start) && ($month <= $fiscal_end)) break;
       $current_quarter++;   $fiscal_start += 2;
       if ($fiscal_start > 12) $fiscal_start -= 12;
       $fiscal_end = $fiscal_start + 2;
       if ($fiscal_end > 12) $fiscal_end -= 12;
    }
    $screen->start_choicelist('quarter'.$suffix.'_quarter');
    foreach ($quarters as $quarter => $label)
       $screen->add_list_item($quarter,$label,$quarter == $current_quarter);
    $screen->end_choicelist();
    $screen->write("</td><td>\n");
    $current_year = date('Y');
    $screen->start_choicelist('quarter'.$suffix.'_year');
    for ($year = 2010;  $year <= $current_year;  $year++)
       $screen->add_list_item($year,$year,$year == $current_year);
    $screen->end_choicelist();
    $screen->end_row();
    $screen->end_table();

    $screen->end_row();
}

function adjust_start_date($date_value,$summary)
{
    $month = date('n',$date_value);
    $day = date('d',$date_value);
    $year = date('Y',$date_value);
    switch ($summary) {
       case 'Day': break;
       case 'Week': $day -= date('w',$date_value);   break;
       case 'Month': $day = 1;   break;
       case 'Year': $month = 1;   $day = 1;   break;
    }
    $date_value = mktime(0,0,0,$month,$day,$year);
    return $date_value;
}

function get_range_selection(&$range_title,&$start_date,&$end_date,
   $oldest_query,$summary=null,$newest_query=null,$suffix='',$db=null)
{
    $quarters = array(1=>'1st',2=>'2nd',3=>'3rd',4=>'4th');

    if (! $db) $db = new DB;
    $row = $db->get_record($oldest_query);
    if (! $row) {
       if (isset($db->error))
          process_report_error('Database Error: '.$db->error);
       $oldest_date = 0;
    }
    else $oldest_date = $row['oldest_date'];
    if ($newest_query) {
       $row = $db->get_record($newest_query);
       if (! $row) {
          if (isset($db->error))
             process_report_error('Database Error: '.$db->error);
          $newest_date = time();
       }
       else $newest_date = $row['newest_date'];
    }
    $fiscal = '';
    $range = get_form_field('Range'.$suffix);
    switch ($range) {
       case 'All':
          $start_date = $oldest_date;
          if ($newest_query) $end_date = $newest_date;
          $end_date = time();
          $range_title = '';
          break;
       case 'Today':
       case 'Yesterday':
       case 'Tomorrow':
          $start_date = mktime(0,0,0,date('n'),date('d'),date('Y'));
          if ($range == 'Yesterday') $start_date -= 86400;
          else if ($range == 'Tomorrow') $start_date += 86400;
          $end_date = $start_date + 86399;
          $range_title = $range;
          break;
       case 'ThisWeek':
       case 'LastWeek':
       case 'NextWeek':
          $day_of_week = date('w');
          $today = mktime(0,0,0,date('n'),date('d'),date('Y'));
          $start_date = $today - ($day_of_week * 86400);
          if ($range == 'LastWeek') $start_date -= 604800;
          else if ($range == 'NextWeek') $start_date += 604800;
          $end_date = $start_date + 604799;
          if ($range == 'ThisWeek') $range_title = 'This Week';
          else if ($range == 'LastWeek') $range_title = 'Last Week';
          else $range_title = 'Next Week';
          break;
       case 'ThisMonth':
          $start_date = mktime(0,0,0,date('n'),1,date('Y'));
          $end_date = mktime(23,59,59,date('n'),date('t'),date('Y'));
          $range_title = 'This Month';
          break;
       case 'LastMonth':
          $month = date('n');   $year = date('Y');
          $month--;
          if ($month == 0) {
             $month = 12;   $year--;
          }
          $month_day = mktime(12,0,0,$month,1,$year);
          $num_days = date('t',$month_day);
          $start_date = mktime(0,0,0,$month,1,$year);
          $end_date = mktime(23,59,59,$month,$num_days,$year);
          $range_title = 'Last Month';   break;
       case 'NextMonth':
          $month = date('n');   $year = date('Y');
          $month++;
          if ($month == 13) {
             $month = 1;   $year++;
          }
          $month_day = mktime(12,0,0,$month,1,$year);
          $num_days = date('t',$month_day);
          $start_date = mktime(0,0,0,$month,1,$year);
          $end_date = mktime(23,59,59,$month,$num_days,$year);
          $range_title = 'Next Month';   break;
       case 'ThisQuarter':
       case 'LastQuarter':
       case 'NextQuarter':
          if (function_exists('get_cart_config_value')) {
             $fiscal_year_start = intval(get_cart_config_value('fiscalyear')) + 1;
             $fiscal = 'Fiscal ';
          }
          else $fiscal_year_start = 1;
          $month = date('n');   $year = date('Y');
          $start_month = $fiscal_year_start;
          if ($month > $start_month)
             while (($start_month + 3) <= $month) $start_month += 3;
          else if ($start_month > $month)
             while ($start_month > $month) $start_month -= 3;
          if ($range == 'LastQuarter') $start_month -= 3;
          else if ($range == 'NextQuarter') $start_month += 3;
          if ($start_month < 1) {
             $start_month += 12;   $year--;
          }
          else if ($start_month > 12) {
             $start_month -= 12;   $year++;
          }
          $start_date = mktime(0,0,0,$start_month,1,$year);
          $end_month = $start_month + 2;
          if ($end_month > 12) {
             $end_month -= 12;   $year++;
          }
          $month_day = mktime(12,0,0,$end_month,1,$year);
          $num_days = date('t',$month_day);
          $end_date = mktime(23,59,59,$end_month,$num_days,$year);
          if ($range == 'ThisQuarter')
             $range_title = 'This '.$fiscal.'Quarter';
          else if ($range == 'LastQuarter')
             $range_title = 'Last '.$fiscal.'Quarter';
          else $range_title = 'Next '.$fiscal.'Quarter';
          break;
       case 'ThisYear':
       case 'LastYear':
       case 'NextYear':
          if (function_exists('get_cart_config_value')) {
             $fiscal_year_start = intval(get_cart_config_value('fiscalyear')) + 1;
             $fiscal = 'Fiscal ';
          }
          else $fiscal_year_start = 1;
          $month = date('n');   $year = date('Y');
          if ($range == 'LastYear') $year--;
          else if ($range == 'NextYear') $year++;
          if ($month < $fiscal_year_start) $year--;
          $start_date = mktime(0,0,0,$fiscal_year_start,1,$year);
          $end_date = mktime(0,0,0,$fiscal_year_start,1,($year + 1)) - 1;
          if ($range == 'ThisYear') $range_title = 'This '.$fiscal.'Year';
          else if ($range == 'LastYear') $range_title = 'Last '.$fiscal.'Year';
          else $range_title = 'Next '.$fiscal.'Year';
          break;
       case 'Range':
          $start_date = get_form_field('StartDate'.$suffix);
          $end_date = get_form_field('EndDate'.$suffix);
          convert_date_range($start_date,$end_date);
          $range_title = date('n/j/y',$start_date).' to '.date('n/j/y',$end_date);
          break;
       case 'Month':
          $month = get_form_field('Month'.$suffix);
          $month_info = explode('-',$month);
          $month = $month_info[0];   $year = $month_info[1];
          $start_date = mktime(0,0,0,$month,1,$year);
          $month_day = mktime(12,0,0,$month,1,$year);
          $num_days = date('t',$month_day);
          $end_date = mktime(23,59,59,$month,$num_days,$year);
          $range_title = date('F Y',$month_day);
          break;
       case 'Quarter':
          if (function_exists('get_cart_config_value')) {
             $fiscal_year_start = intval(get_cart_config_value('fiscalyear')) + 1;
             $fiscal = ' Fiscal';
          }
          else $fiscal_year_start = 1;
          $quarter = get_form_field('Quarter'.$suffix);
          $quarter_info = explode('-',$quarter);
          $quarter = $quarter_info[0];   $year = $quarter_info[1];
          $start_month = $fiscal_year_start + (($quarter - 1) * 3);
          if ($start_month > 12) {
             $start_month -= 12;   $year++;
          }
          $start_date = mktime(0,0,0,$start_month,1,$year);
          $end_month = $start_month + 2;
          if ($end_month > 12) {
             $end_month -= 12;   $year++;
          }
          $month_day = mktime(12,0,0,$end_month,1,$year);
          $num_days = date('t',$month_day);
          $end_date = mktime(23,59,59,$end_month,$num_days,$year);
          $range_title = $quarters[$quarter].$fiscal.' Quarter '.$year;
          break;
    }
    if ($start_date < $oldest_date) $start_date = $oldest_date;
    if (($start_date == $oldest_date) && $summary)
       $start_date = adjust_start_date($start_date,$summary);
    if ($newest_query) {
       if ($start_date > $newest_date) $start_date = $newest_date;
       if ($end_date > $newest_date) $end_date = $newest_date;
    }
}

function get_report_web_site_name($db,$id)
{
    $query = 'select name,domain from web_sites where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) return '';
    if ($row['name']) return $row['name'];
    return $row['domain'];
}

function run_report($report_data=null)
{
    global $customer_report_query,$customer_report_columns;
    global $customer_report_conversion_function;
    global $shopping_cart,$ignore_report_attributes,$encrypted_fields;
    global $part_number_prompt,$off_sale_option,$off_sale_options;
    global $cancelled_option,$enable_reminders,$reorder_reports;
    global $report_ids,$report_titles,$use_spout,$enable_linked_inventory;
    if (! $shopping_cart) global $company_name;

    set_time_limit(0);
    ini_set('memory_limit',-1);
    ini_set('max_execution_time',0);
    $db = new DB;
    if (! isset($ignore_report_attributes)) $ignore_report_attributes = false;
    if (! isset($part_number_prompt)) $part_number_prompt = 'Part #';
    if (! isset($off_sale_option)) $off_sale_option = 1;
    if (! isset($cancelled_option)) $cancelled_option = 3;
    if (! isset($enable_reminders)) $enable_reminders = false;
    if (! isset($use_spout)) $use_spout = false;
    require_once '../engine/modules.php';
    call_module_event('init_reports',array($db,&$report_ids,&$report_titles));
    if ($shopping_cart)
       call_shopping_event('init_reports',
                           array($db,&$report_ids,&$report_titles));
    if (function_exists('init_custom_reports')) init_custom_reports();
    if ($report_data) $report = $report_data['report'];
    else {
       $report = get_form_field('Report');
       $report_data = array();
       $report_data['report'] = $report;
       $report_data['output_type'] = get_form_field('Output');
       $report_data['summary'] = get_form_field('Summary');
    }
    if ($shopping_cart)
       $company_name = get_cart_config_value('companyname',$db);
    $report_data['companyname'] = $company_name;
    $report_data['db'] = $db;
    if (($report_data['output_type'] != HTML_OUTPUT) && (! $use_spout))
       require_once '../engine/excel.php';
    switch ($report) {
       case 'Sales':
          $features = get_cart_config_value('features',$db);
          $oldest_query = 'select min(order_date) as oldest_date from orders';
          get_range_selection($range_title,$start_date,$end_date,$oldest_query,
                              $report_data['summary']);
          $title = $company_name.' - Gross Sales';
          $query = 'select count(id) as num_orders,sum(subtotal) as subtotal,' .
                   'sum(tax) as tax,sum(shipping) as shipping,' .
                   '-sum(discount_amount) as discount,';
          if ($features & USE_COUPONS) $query .= '-sum(coupon_amount) as coupons,';
          if ($features & GIFT_CERTIFICATES) $query .= '-sum(gift_amount) as gift_certificates,';
          $query .= 'sum(fee_amount) as fees,sum(total) as total,';
          if ($features & (PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY))
             $query .= '(select sum(i.cost*i.qty) from order_items i left ' .
                       'join orders o on i.parent=o.id and i.parent_type=0' .
                       '%where%) as cog,';
          $query .= 'round(sum(total)/count(id),2) as average from orders o%where%';
          $where = ' where (order_date>=%start%) and (order_date<=%end%) and (o.status!=' .
                   $cancelled_option.')';
          if ($range_title != '') $title .= ' for '.$range_title;
          $source = get_form_field('Source');
          if (($source !== null) && ($source != '*')) {
             if (! $source) {
                $source = 'Shopping Cart';
                $where .= ' and (external_source="" or isnull(external_source))';
             }
             else $where .= ' and (external_source="'.$db->escape($source).'")';
             $title .= ' (Source: '.$source.')';
          }
          $web_site = get_form_field('WebSite');
          if ($web_site) {
             $where .= ' and (o.website='.$db->escape($web_site).')';
             $title .= ' (WebSite: '.get_report_web_site_name($db,$web_site).')';
          }
          $report_data['query'] = $query;
          $report_data['where'] = $where;
          $report_data['start'] = $start_date;
          $report_data['end'] = $end_date;
          $report_data['title'] = $title;
          $report_data['columns'] = array('Number of Orders','Sub Total','Tax',
                                          'Shipping','Discount');
          $report_data['header_align'] = array('center','center','center','center','center');
          $report_data['data_align'] = array('center','center','center','center','center');
          $report_data['total_flags'] = array(true,true,true,true,true);
          if ($features & USE_COUPONS) {
             $report_data['columns'][] = 'Coupons';
             $report_data['header_align'][] = 'center';
             $report_data['data_align'][] = 'center';
             $report_data['total_flags'][] = true;
          }
          if ($features & GIFT_CERTIFICATES) {
             $report_data['columns'][] = 'Gift Certificates';
             $report_data['header_align'][] = 'center';
             $report_data['data_align'][] = 'center';
             $report_data['total_flags'][] = true;
          }
          $report_data['columns'][] = 'Fees';
          $report_data['header_align'][] = 'center';
          $report_data['data_align'][] = 'center';
          $report_data['total_flags'][] = true;
          $report_data['columns'][] = 'Grand Total';
          $report_data['header_align'][] = 'center';
          $report_data['data_align'][] = 'center';
          $report_data['total_flags'][] = true;
          if ($features & (PRODUCT_COST_PRODUCT|PRODUCT_COST_INVENTORY)) {
             $report_data['columns'][] = 'Cost of Goods';
             $report_data['header_align'][] = 'center';
             $report_data['data_align'][] = 'center';
             $report_data['total_flags'][] = true;
          }
          $report_data['columns'][] = 'Average Order';
          $report_data['header_align'][] = 'center';
          $report_data['data_align'][] = 'center';
          $report_data['total_flags'][] = false;
          $report_data['conversion_function'] = 'convert_sales_field';
          $report_data['summary_format'] = REPORT_FORM;
          $report_data['features'] = $features;
          break;
       case 'SalesSummary':
          $features = get_cart_config_value('features');
          if (get_form_field('ItemDetails') == 'Yes')
             $include_item_details = true;
          else $include_item_details = false;
          if (get_form_field('CustomerDetails') == 'Yes')
             $include_customer_details = true;
          else $include_customer_details = false;
          if (get_form_field('PaymentDetails') == 'Yes')
             $include_payment_details = true;
          else $include_payment_details = false;
          $oldest_query = 'select min(order_date) as oldest_date from orders';
          get_range_selection($range_title,$start_date,$end_date,$oldest_query,
                              $report_data['summary']);
          $title = $company_name.' - Sales Summary';
          $query = 'select IF(o.reorder_id,concat(o.order_number," (R)"),' .
                   'o.order_number) as order_number,o.order_date,o.company,' .
                   'o.email,o.fname,o.lname,';
          if ($include_customer_details)
             $query .= 'o.customer_id,o.ip_address,b.address1 as bill_address1,' .
                       'b.city as bill_city,b.state as bill_state,b.zipcode as ' .
                       'bill_zipcode,';
          $query .= 's.shipto,s.address1,s.city,s.state,s.zipcode,';
          if ($include_item_details)
             $query .= 'i.product_name,i.attribute_names,ifnull((select ' .
                       'part_number from product_inventory where parent=' .
                       'i.product_id and attributes=i.attributes limit 1),' .
                       '(select part_number from product_inventory where ' .
                       'parent=i.product_id limit 1)) as part_number,i.qty,';
          if ($include_payment_details)
             $query .= 'p.payment_amount,p.payment_date,ifnull(' .
                       'p.payment_method,p.payment_type),';
          $query .= '(select coupon_code from coupons where id=o.coupon_id) ' .
                    'as coupon,coupon_amount,tax,total from orders o join ' .
                    'order_shipping s on s.parent=o.id and s.parent_type=0';
          if ($include_customer_details)
             $query .= ' join order_billing b on b.parent=o.id and ' .
                       's.parent_type=0';
          if ($include_item_details)
             $query .= ' join order_items i on i.parent=o.id and ' .
                       'i.parent_type=0';
          if ($include_payment_details)
             $query .= ' join order_payments p on p.parent=o.id and ' .
                       'p.parent_type=0';

          $query .= '%where%';
          $query .= ' order by o.order_date';
          $where = ' where (o.order_date>=%start%) and (o.order_date' .
                   '<=%end%) and (o.status!='.$cancelled_option.')';
          if ($range_title != '') $title .= ' for '.$range_title;
          $source = get_form_field('Source');
          if (($source !== null) && ($source != '*')) {
             if (! $source) {
                $source = 'Shopping Cart';
                $where .= ' and (external_source="" or isnull(external_source))';
             }
             else $where .= ' and external_source="'.$db->escape($source).'"';
             $title .= ' (Source: '.$source.')';
          }
          $web_site = get_form_field('WebSite');
          if ($web_site) {
             $where .= ' and (o.website='.$db->escape($web_site).')';
             $title .= ' (WebSite: '.get_report_web_site_name($db,$web_site).')';
          }
          $report_data['query'] = $query;
          $report_data['where'] = $where;
          $report_data['start'] = $start_date;
          $report_data['end'] = $end_date;
          $report_data['title'] = $title;
          $tables = array('orders','order_shipping');
          $columns = array('Order Number','Date','Company','Email','First Name',
                           'Last Name');
          $align = array('center','center','left','left','left','left');
          $total_flags = array(false,false,false,false,false,false);
          $format = array(null,null,null,null,null,null);
          if ($include_customer_details) {
             $columns = array_merge($columns,array('Customer ID','IP Address',
                           'Billing Address','City','State','Zip Code'));
             $align = array_merge($align,array('center','center','left','left',
                                               'center','center'));
             $total_flags = array_merge($total_flags,array(false,false,false,
                                                           false,false,false));
             $tables[] = 'order_billing';
             $format = array_merge($format,
                                   array(null,null,null,null,null,null));
          }
          $columns = array_merge($columns,array('Ship To','Address','City',
                                                'State','Zip Code'));
          $align = array_merge($align,array('left','left','left','center',
                                            'center'));
          $total_flags = array_merge($total_flags,array(false,false,false,
                                                        false,false));
          $format = array_merge($format,array(null,null,null,null,null));
          if ($include_item_details) {
             $columns = array_merge($columns,array('Product Name','Attributes',
                                                   $part_number_prompt,'Qty'));
             $align = array_merge($align,array('left','left','center',
                                               'center'));
             $total_flags = array_merge($total_flags,array(false,false,
                                                           false,true));
             $tables[] = 'product_inventory';
             if (($report_data['output_type'] != HTML_OUTPUT) &&
                 (! $use_spout))
                $part_number_format = PHPExcel_Cell_DataType::TYPE_STRING;
             else $part_number_format = null;
             $format = array_merge($format,array(null,null,
                                                 $part_number_format,null));
             $report_data['orders'] = array();
          }
          if ($include_payment_details) {
             $columns = array_merge($columns,array('Payment Amount',
                                    'Payment Date','Payment Type'));
             $align = array_merge($align,array('right','center','center'));
             $total_flags = array_merge($total_flags,array(true,false,false));
             $format = array_merge($format,array(null,null,null));
             $tables[] = 'order_payments';
          }
          $columns = array_merge($columns,array('Coupon','Coupon Amount','Tax',
                                                'Order Amount'));
          $align = array_merge($align,array('center','right','right','right'));
          $total_flags = array_merge($total_flags,array(false,true,true,true));
          $format = array_merge($format,array(null,null,null,null));
          $report_data['tables'] = $tables;
          $report_data['columns'] = $columns;
          $report_data['header_align'] = $align;
          $report_data['data_align'] = $align;
          $report_data['total_flags'] = $total_flags;
          $report_data['format'] = $format;
          $report_data['conversion_function'] = 'convert_sales_summary_field';
          break;
       case 'NewCustomers':
          $oldest_query = 'select min(create_date) as oldest_date from customers';
          get_range_selection($range_title,$start_date,$end_date,$oldest_query,
                              $report_data['summary']);
          $title = $company_name.' - Customer Registrations';
          $query = 'select count(c.email) as new_custs,(select count(' .
             'distinct c.email) from customers c left join orders o on c.id=' .
             'o.customer_id%where% and not isnull(o.id)) as new_orders,' .
             '((select count(distinct c.email) from customers c left join ' .
             'orders o on c.id=o.customer_id%where% and not isnull(o.id))/' .
             'count(c.email)*100) as order_percent,(select count(c.mailing) ' .
             'from customers c where (c.mailing=1)%and%) as mailing,' .
             '((select count(c.mailing) from customers c where (c.mailing=1)' .
             '%and%)/count(c.email)*100) as mailing_percent,(select ' .
             'count(c.email) from customers c left join billing_information ' .
             'b on c.id=b.parent and b.parent_type=0 where (b.country=1)%and%) ' .
             'as domestic,(select count(c.email) from customers c left join ' .
             'billing_information b on c.id=b.parent and b.parent_type=0 ' .
             'where (b.country<>1)%and%) as international from customers c' .
             '%where%';
          $where = ' where (c.create_date>=%start%) and (c.create_date<=%end%)';
          if ($range_title != '') $title .= ' for '.$range_title;
          $status = get_form_field('Status');
          if ($status != '*') {
             $status_values = load_cart_options(CUSTOMER_STATUS,$db);
             $where .= ' and (c.status='.$db->escape($status).')';
             $title .= ' (Status: ';
             if (isset($status_values[$status])) $title .= $status_values[$status];
             else $title .= $status;
             $title .= ')';
          }
          $report_data['query'] = $query;
          $report_data['where'] = $where;
          $report_data['and'] = ' and (c.create_date>=%start% and c.create_date<=%end%)';
          $report_data['start'] = $start_date;
          $report_data['end'] = $end_date;
          $report_data['title'] = $title;
          $report_data['columns'] = array('New Customers','New Customers<br>w/ Orders',
             'Ordering<br>Percentage','New Customers<br>w/ Mailing','Mailing<br>Percentage',
             'Domestic<br>Customers','International<br>Customers');
          $report_data['form_columns'] = array('New Customers','New Customers w/ Orders',
             'Ordering Percentage','New Customers w/ Mailing','Mailing Percentage',
             'Domestic Customers','International Customers');
          $report_data['header_align'] = array('center','center','center','center','center','center','center');
          $report_data['data_align'] = array('center','center','center','center','center','center','center');
          $report_data['total_flags'] = array(true,true,false,true,false,true,true);
          $report_data['conversion_function'] = 'convert_new_customers_field';
          $report_data['summary_format'] = REPORT_FORM;
          break;
       case 'Products':
          $features = get_cart_config_value('features');
          if (get_form_field('ProductIDs') == 'Yes') $include_ids = true;
          else $include_ids = false;
          if (get_form_field('Attributes') == 'Yes') $include_attributes = true;
          else $include_attributes = false;
          $oldest_query = 'select min(order_date) as oldest_date from orders';
          get_range_selection($range_title,$start_date,$end_date,$oldest_query,
                              $report_data['summary']);
          $title = $company_name.' - Product Popularity';
          $query = 'select oi.product_name,';
          if ($include_ids) $query .= 'oi.product_id,';
          if ($include_attributes) $query .= 'oi.attribute_names,';
          if ($features & USE_PART_NUMBERS)
             $query .= 'ifnull((select pi.part_number from product_inventory pi where ' .
                       '(pi.parent=oi.product_id) and ((pi.attributes=oi.attributes) or ' .
                       '((pi.attributes="") and isnull(oi.attributes)) or ' .
                       '(isnull(pi.attributes) and (oi.attributes=""))) limit 1),' .
                       '(select pi.part_number from product_inventory pi where ' .
                       '(pi.parent=oi.product_id) limit 1)),';
          $query .= 'sum(oi.qty) as total_qty,round(sum(IF(oi.flags&1,oi.price,' .
                    'oi.qty*oi.price)),2) as sales,' .
                    'count(distinct oi.parent) as num_orders,' .
                    'count(distinct case when o.phone_order=1 then o.id end) ' .
                    'as num_phone_orders from order_items ' .
                    'oi join orders o on o.id=oi.parent and oi.parent_type=0' .
                    '%where% group by oi.product_id';
          if ($include_attributes) $query .= ',oi.attribute_names';
          $query .= ' order by num_orders desc,sales desc';
          if ($range_title != '') $title .= ' for '.$range_title;
          $where = ' where (o.order_date>=%start%) and (o.order_date' .
                   '<=%end%) and (o.status!='.$cancelled_option.')';
          $source = get_form_field('Source');
          if (($source !== null) && ($source != '*')) {
             if (! $source) {
                $source = 'Shopping Cart';
                $where .= ' and (o.external_source="" or isnull(o.external_source))';
             }
             else $where .= ' and o.external_source="' .
                            $db->escape($source).'"';
             $title .= ' (Source: '.$source.')';
          }
          $report_data['query'] = $query;
          $report_data['where'] = $where;
          $report_data['start'] = $start_date;
          $report_data['end'] = $end_date;
          $report_data['title'] = $title;
          $report_data['table_width'] = '100%';
          $report_data['tables'] = array('order_items','product_inventory');
          $columns = array('Product Name');
          $align = array('left');
          $total_flags = array(false);
          $wrap = array(true);
          if ($include_ids) {
             $columns[] = 'Product ID';
             $align[] = 'center';
             $total_flags[] = false;
             $wrap[] = false;
          }
          if ($include_attributes) {
             $columns[] = 'Product<br>Attributes';
             $align[] = 'left';
             $total_flags[] = false;
             $wrap[] = true;
          }
          if ($features & USE_PART_NUMBERS) {
             $columns[] = $part_number_prompt;
             $align[] = 'center';
             $total_flags[] = false;
             $wrap[] = false;
          }
          $columns = array_merge($columns,
             array('Total<br>Ordered','Product Sales','Num Orders<br>Placed',
                   'Num Phone<br>Orders'));
          $align = array_merge($align,array('center','right','center','center'));
          $total_flags = array_merge($total_flags,array(true,true,false,false));
          $wrap = array_merge($wrap,array(false,false,false,false));
          $report_data['columns'] = $columns;
          $report_data['header_align'] = $align;
          $report_data['data_align'] = $align;
          $report_data['total_flags'] = $total_flags;
          $report_data['wrap'] = $wrap;
          $report_data['conversion_function'] = 'convert_products_field';
          break;
       case 'Inventory':
          if (get_form_field('OffSale') == 'Yes') $include_offsale = true;
          else $include_offsale = false;
          $features = get_cart_config_value('features');
          $title = $company_name.' - Product Inventory';
          $query = 'select ';
          if ($features & USE_PART_NUMBERS) $query .= 'i.part_number,';
          $query .= 'p.id as product_id,p.name,i.attributes,';
          $query .= 'i.qty,i.min_qty,';
          if ($features & LIST_PRICE_PRODUCT) $query .= 'p.list_price,';
          else if ($features & LIST_PRICE_INVENTORY) $query .= 'i.list_price,';
          else $query .= "\"\",";
          if ($features & REGULAR_PRICE_PRODUCT) $query .= 'p.price,';
          else if ($features & REGULAR_PRICE_INVENTORY) $query .= 'i.price,';
          else $query .= "\"\",";
          if ($features & SALE_PRICE_PRODUCT) $query .= 'p.sale_price';
          else if ($features & SALE_PRICE_INVENTORY) $query .= 'i.sale_price';
          else $query .= "\"\"";
          $query .= ' from products p left join product_inventory i on ' .
                    'i.parent=p.id';
          if (! $include_offsale) {
             $query .= ' where (isnull(status) or ';
             if (isset($off_sale_options))
                $query .= '(p.status not in ('.implode(',',$off_sale_options).'))';
             else $query .= '(p.status!='.$off_sale_option.'))';
          }
          if (! empty($enable_linked_inventory)) {
             if ($include_offsale) $query .= ' where ';
             else $query .= ' and ';
             $query .= '(i.id not in (select linked_id from inventory_link))';
          }
          $query .= ' order by ';
          if ($features & USE_PART_NUMBERS) $query .= 'i.part_number,';
          $query .= 'p.name,i.sequence';
          $report_data['query'] = $query;
          $report_data['title'] = $title;
          $report_data['table_width'] = '100%';
          $report_data['tables'] = array('products','product_inventory');
          if ($features & USE_PART_NUMBERS) {
             $report_data['columns'] = array($part_number_prompt,'Product Name',
                'Product Attributes','Qty','Min Qty','List Price','Price',
                'Sale Price');
             $report_data['header_align'] = array('left','left','left',
                'center','center','right','right','right');
          }
          else {
             $report_data['columns'] = array('Product Name','Product Attributes',
                'Qty','Min Qty','List Price','Price','Sale Price');
             $report_data['header_align'] = array('left','left','center',
                'center','right','right','right');
          }
          $report_data['data_align'] = $report_data['header_align'];

          $query = 'select * from product_attributes order by parent,sequence';
          $product_attributes = array();
          $rows = $db->get_records($query);
          if ((! $rows) && isset($db->error)) {
             process_report_error('Database Error: '.$db->error);   return;
          }
          if ($rows) foreach ($rows as $row) {
             if (! isset($product_attributes[$row['parent']]))
                $product_attributes[$row['parent']] = array();
             $product_attributes[$row['parent']][] = $row['related_id'];
          }
          $report_data['product_attributes'] = $product_attributes;

          $query = 'select id,name,display_name,sub_product,required from ' .
                   'attributes order by id';
          $attributes = array();
          $rows = $db->get_records($query);
          if ((! $rows) && isset($db->error)) {
             process_report_error('Database Error: '.$db->error);   return;
          }
          if ($rows) foreach ($rows as $row) {
             if ($row['display_name']) $name = $row['display_name'];
             else $name = $row['name'];
             $attributes[$row['id']] = array('name'=>$name,
                'sub_product'=>$row['sub_product'],
                'required'=>$row['required'],'options'=>array());
          }

          $query = 'select id,parent,name from attribute_options order by ' .
                   'parent,sequence';
          $rows = $db->get_records($query);
          if ((! $rows) && isset($db->error)) {
             process_report_error('Database Error: '.$db->error);   return;
          }
          if ($rows) foreach ($rows as $row) {
             if (! isset($attributes[$row['parent']])) continue;
             $attributes[$row['parent']]['options'][$row['id']] = $row['name'];
          }
          $report_data['attributes'] = $attributes;

          $report_data['eval_function'] = 'eval_inventory_record';
          $report_data['conversion_function'] = 'convert_inventory_field';
          break;
       case 'AllCustomers':
          $oldest_query = 'select min(create_date) as oldest_date from customers';
          get_range_selection($range_title,$start_date,$end_date,$oldest_query,
                              $report_data['summary']);
          $title = $company_name.' - Customer List';
          if ($range_title != '') $title .= ' for '.$range_title;
          if (isset($customer_report_query)) $query = $customer_report_query;
          else {
             $query = 'select c.email,c.company,c.mailing';
             if ($enable_reminders) $query .= ',c.reminders';
             $query .= ',c.fname,c.mname,c.lname,b.address1,b.address2,b.city,' .
                'b.state,b.zipcode,(select country from countries where ' .
                'id=b.country) as country,b.phone,b.fax,b.mobile,c.create_date ' .
                'from customers c left join billing_information b on b.parent=' .
                'c.id and b.parent_type=0%where% order by c.create_date desc';
          }
          $where = ' where (c.create_date>=%start%) and (c.create_date<=%end%)';
          $status = get_form_field('Status');
          if ($status != '*') {
             $status_values = load_cart_options(CUSTOMER_STATUS,$db);
             $where .= ' and (c.status='.$db->escape($status).')';
             $title .= ' (Status: ';
             if (isset($status_values[$status])) $title .= $status_values[$status];
             else $title .= $status;
             $title .= ')';
          }
          $report_data['query'] = $query;
          $report_data['where'] = $where;
          $report_data['and'] = ' and (c.create_date>=%start% and c.create_date<=%end%)';
          $report_data['start'] = $start_date;
          $report_data['end'] = $end_date;
          $report_data['title'] = $title;
          $report_data['table_width'] = '100%';
          $report_data['tables'] = array('customers','billing_information');
          if (isset($customer_report_columns))
             $report_data['columns'] = $customer_report_columns;
          else $report_data['columns'] = array('Email','Company','Mailing',
             'Reminders','First Name','Middle','Last Name','Address1',
             'Address2','City','State','Zip','Country','Telephone','Fax',
             'Mobile','Registered Date');
          if ((! $enable_reminders) && (! isset($customer_report_columns)))
             array_splice($report_data['columns'],3,1);
          if (isset($customer_report_conversion_function))
             $report_data['conversion_function'] = $customer_report_conversion_function;
          else $report_data['conversion_function'] = 'convert_all_customers_field';
          break;
       case 'CategoryProducts':
          $report_data['data'] = load_category_product_data($db);
          if (! $report_data['data']) return;
          $report_data['title'] = $company_name.' - Categories and Products';
          $report_data['columns'] = array('Category','ID','Status',
             'Display Name','Product','ID','Status','Display Name');
          $report_data['header_align'] = array('left','center','center',
             'left','left','center','center','left');
          $report_data['data_align'] = $report_data['header_align'];
          break;
       case 'LogFiles':
          $logfiles_type = get_form_field('Type');
          $start_date = get_form_field('StartDate');
          $end_date = get_form_field('EndDate');
          convert_date_range($start_date,$end_date);
          generate_logfiles_report($logfiles_type,$start_date,$end_date);
          return;
       default:
          if (function_exists('run_custom_report'))
             run_custom_report($report,$report_data);
          if ($reorder_reports &&
              (! run_reorders_report($report,$report_data))) return;
          if (! call_module_event('run_report',
                                  array($report,&$report_data),null,false))
                return;
          if ($shopping_cart) {
             if (! call_shopping_event('run_report',
                                       array($report,&$report_data),false))
                return;
          }
    }
    if (function_exists('update_report_data'))
       update_report_data($report_data);
    generate_report($report_data);
}

function display_reports_screen()
{
    global $report_ids,$report_titles,$shopping_cart,$payment_log;
    global $shipping_log,$script_name,$eye4fraud_status,$taxcloud_api_id;
    global $reports_url,$log_cart_errors_enabled,$admin_base_url;
    global $enable_vendors,$use_spout,$login_cookie,$reorder_reports;
    global $shopping_modules,$admin_modules;

    $db = new DB;
    require_once '../engine/modules.php';
    call_module_event('init_reports',array($db,&$report_ids,&$report_titles));
    if ($shopping_cart)
       call_shopping_event('init_reports',
                           array($db,&$report_ids,&$report_titles));
    if (function_exists('init_custom_reports')) init_custom_reports();
    if (! isset($use_spout)) $use_spout = false;
    if (get_form_field('dialog')) {
       require_once '../engine/dialog.php';
       $use_dialog = true;   $screen = new Dialog;
    }
    else {
       $use_dialog = false;   $screen = new Screen;
    }
    if (! $screen->skin) $screen->set_body_class('admin_screen_body');
    $screen->enable_calendar();
    $screen->add_style_sheet('reports.css');
    $extra_css = get_form_field('extracss');
    if ($extra_css) {
       $extra_css = explode('|',$extra_css);
       foreach ($extra_css as $css) $screen->add_style_sheet($css);
    }
    $screen->add_script_file('reports.js');
    if ($reorder_reports)
       $screen->add_script_file('../admin/reorders-reports.js');
    $extra_js = get_form_field('extrajs');
    if ($extra_js) {
       $extra_js = explode('|',$extra_js);
       foreach ($extra_js as $js) $screen->add_script_file($js);
    }
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    call_module_event('reports_head',array(&$screen,$db));
    if ($shopping_cart)
       call_shopping_event('reports_head',array(&$screen,$db));
    if (function_exists('custom_reports_head'))
       custom_reports_head($screen,$db);
    $screen->set_onload_function('reports_onload();');
    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart) $head_block .= "      script_prefix = '../cartengine/';\n";
    $head_block .= "      script_name = '".$script_name."';\n";
    if (isset($reports_url))
       $head_block .= "      reports_url = '".$reports_url."';\n";
    else if (isset($admin_base_url)) {
       if ($shopping_cart)
          $reports_url = $admin_base_url.'cartengine/reports.php';
       else $reports_url = $admin_base_url.'admin/reports.php';
       $head_block .= "      reports_url = '".$reports_url."';\n";
    }
    if ($use_dialog) {
       $head_block .= "      use_dialog = true;\n";
       $dialog = get_form_field('dialog');
       if ($dialog) $head_block .= "      dialog_name = '".$dialog."';\n";
    }
    if (! empty($admin_modules)) {
       $head_block .= '      modules = [';   $first_module = true;
       foreach ($admin_modules as $module => $module_info) {
          if ($first_module) $first_module = false;
          else $head_block .= ',';
          $head_block .= '\''.$module.'\'';
       }
       $head_block .= "];\n";
    }
    if ($shopping_cart && (! empty($shopping_modules))) {
       $head_block .= '      shopping_modules = [';   $first_module = true;
       foreach ($shopping_modules as $module) {
          if ($first_module) $first_module = false;
          else $head_block .= ',';
          $head_block .= '\''.$module.'\'';
       }
       $head_block .= "];\n";
    }
    $head_block .= "    </script>";
    $screen->add_head_line($head_block);
    $screen->set_body_id('reports');
    $screen->set_help('reports');
    if ($use_dialog) $screen->start_body('Reports');
    else {
       $screen->start_body();
       if ($screen->skin) {
          $screen->start_title_bar('Reports');
          $screen->end_title_bar();
       }
    }
    $screen->set_button_width(115);
    $screen->start_button_column();
    $screen->add_button('Run Report','images/RunReport.png',
                        'return run_report();','run_report');
    if ($use_dialog)
       $screen->add_button('Close','images/Update.png',
                           'top.close_current_dialog();');
    $screen->end_button_column();
    $screen->start_form('reports.php','Reports');
    if ($use_dialog) $screen->add_hidden_field('use_dialog','true');
    $screen->start_field_table();

    if ($use_dialog) $report_id = get_form_field('report');
    else $report_id = null;
    if ($report_id) {
       $index = array_search($report_id,$report_ids);
       if ($index !== false) {
          $screen->write('<tr><td><div class="fieldprompt ' .
                         "report_title_prompt\">Report:</div>\n");
          $screen->write('<div class="report_title">'.$report_titles[$index] .
                         "</div></td></tr>\n");
       }
       $screen->add_hidden_field('Report',$report_id);
       $screen->write('<tr>');
    }
    else {
       $screen->write("<tr valign=\"top\"><td class=\"fieldprompt\">Reports:<br>\n");
       $screen->start_listbox('Report',count($report_ids),false,'select_report();');
       foreach ($report_ids as $index => $id)
          $screen->add_list_item($id,$report_titles[$index],false);
       $screen->end_listbox();
       $screen->write("</td>");
    }
    $screen->write('<td class="fieldprompt report_options_cell"');
    if ($report_id) $screen->write(' colspan="2"');
    $screen->write(">Report Options:<br>\n");
    $screen->write("<div class=\"report_options_div\"><table " .
                   "cellspacing=\"0\" cellpadding=\"4\" " .
                   "class=\"report_options_table\">\n");

    if (function_exists('add_initial_custom_report_rows'))
       add_initial_custom_report_rows($screen);

    if ($report_id) $hide_output_row = false;
    else $hide_output_row = true;
    $screen->start_hidden_row('Output:','output_row',$hide_output_row,
                              'middle');
    $screen->start_choicelist('Output','select_output();');
    $screen->add_list_item('html','HTML',true);
    $screen->add_list_item('xlsx','Excel Workbook (*.xlsx)',false);
    if ($use_spout)
       $screen->add_list_item('ods','OpenDocument Spreadsheet (*.ods)',false);
    else $screen->add_list_item('xls','Excel 97-2003 Workbook (*.xls)',false);
    $screen->add_list_item('csv','CSV (Comma delimited) (*.csv)',false);
    $screen->add_list_item('txt','Text (Tab delimited) (*.txt)',false);
    $screen->add_list_item('email','E-Mail to:',false);
    $screen->end_choicelist();
    $screen->start_choicelist('email_address','select_email();',
                              'select_email" style="display:none;');
    $query = 'select * from users order by lastname,firstname';
    $users = $db->get_records($query);
    if ($users) {
       $admin_user = get_cookie($login_cookie);
       $db->decrypt_records('users',$users);
       foreach ($users as $user) {
          if ($user['username'] == 'default') continue;
          if (! $user['email']) continue;
          $full_name = $user['firstname'].' '.$user['lastname'];
          if ($user['username'] == $admin_user) $selected = true;
          else $selected = false;
          $screen->add_list_item($user['email'],$full_name,$selected);
       }
    }
    $screen->add_list_item('*','Other:',false);
    $screen->end_choicelist();
    $screen->write('<span id="other_email_span" style="display:none;">'."\n");
    $screen->add_input_field('other_email','',20);
    $screen->write("</span>\n");
    $screen->end_row();

    $screen->start_hidden_row('Destination:','dest_row',true,'middle');
    $screen->add_radio_field('Destination','0','On Screen',true);
    $screen->add_radio_field('Destination','1','New Tab',false);
    $screen->add_radio_field('Destination','2','Dialog',false);
    $screen->add_radio_field('Destination','3','Popup Window',false);
    $screen->end_row();

    add_website_select_row($screen,$db,'Web Site:','WebSite','website_row');

    add_date_range_row($screen,'Date Range:');

    $screen->start_hidden_row('Summarize By:','summary_row',true,'middle');
    $screen->write("<table cellspacing=0 cellpadding=0>\n");
    $screen->write('<tr><td>');
    $screen->add_radio_field('summary','All','All',true,null);
    $screen->write("</td>\n<td>");
    $screen->add_radio_field('summary','Day','Day',false,null);
    $screen->write("</td>\n<td id=\"summary_week_cell\">");
    $screen->add_radio_field('summary','Week','Week',false,null);
    $screen->write("</td>\n<td id=\"summary_month_cell\">");
    $screen->add_radio_field('summary','Month','Month',false,null);
    $screen->write("</td>\n<td id=\"summary_year_cell\">");
    $screen->add_radio_field('summary','Year','Year',false,null);
    $screen->write("</td></tr>\n</table>\n");
    $screen->end_row();

    if ($shopping_cart) {
       $screen->start_hidden_row('Options:','sales_summary_options_row',true,
                                 'top');
       $screen->add_checkbox_field('order_item_details',
                                   'Include Order Item Details',false);
       $screen->write("<br>\n");
       $screen->add_checkbox_field('customer_details',
                                   'Include Customer Details',false);
       $screen->write("<br>\n");
       $screen->add_checkbox_field('payment_details',
                                   'Include Payment Details',false);
       $screen->end_row();

       $screen->start_hidden_row('Options:','product_options_row',true,
                                 'top');
       $screen->add_checkbox_field('include_ids','Include Product IDs',false);
       $screen->write("<br>\n");
       $screen->add_checkbox_field('include_attributes',
                                   'Include Product Attributes',false);
       $screen->end_row();

       $screen->start_hidden_row('Options:','inventory_options_row',true,
                                 'middle');
       $screen->add_checkbox_field('offsale_products',
                                   'Include Off Sale Products',false);
       $screen->end_row();

       $query = 'select distinct external_source from orders order by ' .
                'external_source';
       $external_sources = $db->get_records($query);
       if ($external_sources && (count($external_sources) > 1)) {
          $screen->start_hidden_row('Order Source:','order_source_row',true,
                                    'middle');
          $screen->start_choicelist('source');
          $screen->add_list_item('*','All',true);
          foreach ($external_sources as $source) {
             $source = $source['external_source'];
             if (! $source) $source_name = 'Shopping Cart';
             else $source_name = $source;
             $screen->add_list_item($source,$source_name,false);
          }
          $screen->end_choicelist();
          $screen->end_row();
       }

       $screen->start_hidden_row('Status:','customer_status_row',true,
                                 'middle');
       $status_values = load_cart_options(CUSTOMER_STATUS,$db);
       $screen->start_choicelist('status');
       $screen->add_list_item('*','All',true);
       foreach ($status_values as $index => $status)
          $screen->add_list_item($index,$status,false);
       $screen->end_choicelist();
       $screen->end_row();

       if ($reorder_reports) add_reorders_report_filters($screen);
    }

    $screen->start_hidden_row('Log:','logfiles_type_row',true,'middle');
    $screen->start_choicelist('LogFileType');
    $screen->add_list_item('0','Activity',true);
    $screen->add_list_item('1','Error',false);
    $screen->add_list_item('2','SQL',false);
    if (isset($payment_log)) $screen->add_list_item('3','Payment',false);
    if (isset($shipping_log)) $screen->add_list_item('4','Shipping',false);
    if (isset($eye4fraud_status))
       $screen->add_list_item('5','Eye4Fraud',false);
    if (isset($taxcloud_api_id)) $screen->add_list_item('7','TaxCloud',false);
    if (isset($log_cart_errors_enabled) && $log_cart_errors_enabled)
       $screen->add_list_item('9','Checkout',false);
    if (isset($enable_vendors) && $enable_vendors)
       $screen->add_list_item('10','Vendors',false);
    if ($shopping_cart)
       call_shopping_event('add_report_log_file_type',array(&$screen));
    require_once '../engine/modules.php';
    call_module_event('add_report_log_file_type',array(&$screen));
    if (function_exists('add_custom_logfile_types'))
       add_custom_logfile_types($screen);
    $screen->end_row();

    $screen->start_hidden_row('Date Range:','logfiles_date_row',true,'middle');
    $screen->start_table();
    $screen->write("<tr valign=\"middle\"><td>\n");
    $start_date = mktime(0,0,0);
    $screen->add_date_field('logfiles_start_date',$start_date);
    $screen->write("</td><td>&nbsp;&nbsp;-&nbsp;&nbsp;</td><td>\n");
    $end_date = mktime(23,59,59);
    $screen->add_date_field('logfiles_end_date',$end_date);
    $screen->write("</td></tr>\n");
    $screen->end_table();
    $screen->end_row();

    if (function_exists('add_custom_report_rows'))
       add_custom_report_rows($screen);
    call_module_event('add_report_rows',array(&$screen,$db));
    if ($shopping_cart)
       call_shopping_event('add_report_rows',array(&$screen,$db));

    $screen->write("</table></div>\n</td></tr>\n");
    $screen->end_field_table();
    $screen->end_form();
    $screen->end_button_section();

    $screen->write("<iframe id=\"report_iframe\" name=\"report_iframe\" " .
                   "width=\"100%\" height=\"100%\" frameborder=\"0\" " .
                   "style=\"display:none;\"></iframe>\n");

    $screen->finish_body();
}

if (! $custom_reports_module) {

   if (! check_login_cookie()) exit;

   $cmd = get_form_field('cmd');

   if ($cmd == 'runreport') run_report();
   else display_reports_screen();
}

DB::close_all();

?>
