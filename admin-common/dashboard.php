<?php
/*
                Inroads Control Panel/Shopping Cart - Dashboard Tab

                        Written 2015-2018 by Randall Severy
                         Copyright 2015-2018 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/db.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
require_once 'cartconfig-common.php';

define('COLUMN_CHART',0);
define('LINE_CHART',1);
define('PIE_CHART',2);
define('BAR_CHART',3);
define('AREA_CHART',4);
define('GROUP_CHART',10);
define('TABLE_CHART',11);

class ChartData {
function ChartData($type=null)
{
    $this->series = array();
    $this->labels = array();
    $this->kpis = array();
    if ($type !== null) $this->type = $type;
    else $this->type = COLUMN_CHART;
    $this->num_series = 0;
}
function add_series($series)
{
    $this->series[$this->num_series] = $series;
    $this->num_series++;
}
function add_kpi($index,$kpi)
{
    $this->kpis[$index] = $kpi;
}
};

class ChartSeries {
function ChartSeries($name=null,$type=null)
{
    $this->name = $name;
    $this->data = array();
    $this->options = new StdClass();
    if ($type !== null) $this->type = $type;
    else $this->type = COLUMN_CHART;
    switch ($this->type) {
       case COLUMN_CHART:
          $this->options->seriesDisplayType = 'column';   break;
       case LINE_CHART:
          $this->options->seriesDisplayType = 'line';   break;
       case PIE_CHART: break;
       case BAR_CHART:
          $this->options->seriesDisplayType = 'bar';   break;
       case AREA_CHART:
          $this->options->seriesDisplayType = 'area';   break;
    }
}
function add_option($option_name,$option_value)
{
    $this->options->$option_name = $option_value;
}
};

class TableData {
function TableData($num_rows)
{
    $this->num_rows = $num_rows;
    $this->columns = array();
    $this->rows = array();
    $this->kpis = array();
    $this->type = TABLE_CHART;
}
function add_column($column)
{
    $this->columns[] = $column;
}
function add_row($row_data)
{
    $row = new StdClass();
    foreach ($row_data as $col_name => $col_value)
       $row->$col_name = $col_value;
    $this->rows[] = $row;
}
function add_kpi($index,$kpi)
{
    $this->kpis[$index] = $kpi;
}
};

class TableColumn {
function TableColumn($name,$label)
{
    $this->name = $name;
    $this->label = $label;
    $this->options = new StdClass();
}
function add_option($option_name,$option_value)
{
    $this->options->$option_name = $option_value;
}
};

function add_dashboard_widgets()
{
    add_dashboard_tab('executive','Executive Summary');
    add_dashboard_widget(1,'executive',null,GROUP_CHART,12,1);
    add_dashboard_widget(2,'executive','Sales',COLUMN_CHART,4,4);
    add_dashboard_widget(4,'executive','Recent Orders',TABLE_CHART,5,4);
    add_dashboard_tab('analytics','Analytics');
    add_dashboard_widget(3,'analytics','Order Sources',PIE_CHART,4,4);
}

function load_widget()
{
    global $cancelled_option;

    if (! isset($cancelled_option)) $cancelled_option = 3;

    $db = new DB;
    $id = get_form_field('id');
    $type = get_form_field('Type');
    $widget = array('db' => $db, 'id' => $id, 'type' => $type);
    $widget['summary'] = get_form_field('Summary');

    switch ($id) {
       case 1:
          unset($widget['summary']);
          $features = get_cart_config_value('features',$db);
          $oldest_query = 'select min(order_date) as oldest_date from orders';
          get_range_selection($db,$start_date,$end_date,$oldest_query,null);
          $query = 'select count(id) as num_orders,sum(subtotal) as subtotal,' .
                   'sum(discount_amount) as discount,';
          if ($features & USE_COUPONS) $query .= 'sum(coupon_amount) as coupons,';
          else $query .= '0 as coupons,';
          $query .= 'sum(total) as total,(select sum(i.cost*i.qty) from ' .
                    'order_items i left join orders o on i.parent=o.id%where%) ' .
                    'as cog,round(sum(total)/count(id),2) as average from ' .
                    'orders o%where%';
          $where = ' where (o.order_date>=%start%) and (o.order_date<=%end%) ' .
                   'and (o.status!='.$cancelled_option.')';
          $widget['query'] = $query;
          $widget['where'] = $where;
          $widget['start'] = $start_date;
          $widget['end'] = $end_date;
          $widget_data = load_widget_data($widget);
          if (! $widget_data) return;
          $num_orders = $widget_data[0]['num_orders'];
          $total_sales = $widget_data[0]['total'];
          $profit = $widget_data[0]['subtotal'] - $widget_data[0]['coupons'] -
                    $widget_data[0]['discount'] - $widget_data[0]['cog'];
          $average = $widget_data[0]['average'];

          $chart_data = new ChartData(GROUP_CHART);
          $kpi = new StdClass();
          $kpi->caption = '# of Orders';
          $kpi->value = $num_orders;
          $chart_data->add_kpi('total_orders',$kpi);
          $kpi = new StdClass();
          $kpi->caption = 'Total Sales';
          $kpi->value = $total_sales;
          $kpi->numberPrefix = '$';
          $chart_data->add_kpi('total_sales',$kpi);
          $kpi = new StdClass();
          $kpi->caption = 'Profit';
          $kpi->value = $profit;
          $kpi->numberPrefix = '$';
          $chart_data->add_kpi('profit',$kpi);
          $kpi = new StdClass();
          $kpi->caption = 'Average Order';
          $kpi->value = $average;
          $kpi->numberPrefix = '$';
          $chart_data->add_kpi('average_order',$kpi);
          break;
       case 2:
          $oldest_query = 'select min(order_date) as oldest_date from orders';
          get_range_selection($db,$start_date,$end_date,$oldest_query,
                              $widget['summary']);
          $query = 'select count(id) as num_orders,sum(total) as total from ' .
                   'orders o%where%';
          $where = ' where (o.order_date>=%start%) and (o.order_date<=%end%) ' .
                   'and (o.status!='.$cancelled_option.')';
          $widget['query'] = $query;
          $widget['where'] = $where;
          $widget['start'] = $start_date;
          $widget['end'] = $end_date;
          $widget_data = load_widget_data($widget);
          if (! $widget_data) return;

          $chart_data = new ChartData;
          $total_series = new ChartSeries('Order Total');
          $total_series->add_option('numberPrefix','$');
          $numorders_series = new ChartSeries('Number of Orders',LINE_CHART);
          $total_sales = 0;   $num_orders = 0;
          foreach ($widget_data as $data) {
             $chart_data->labels[] = $data['range'];
             if ($data['total'] === null) $data['total'] = 0;
             $total_series->data[] = $data['total'];
             $total_sales += $data['total'];
             if ($data['num_orders'] === null) $data['num_orders'] = 0;
             $numorders_series->data[] = $data['num_orders'];
             $num_orders += $data['num_orders'];
          }
          $chart_data->add_series($total_series);
          $chart_data->add_series($numorders_series);
          $kpi = new StdClass();
          $kpi->caption = 'Total Sales';
          $kpi->value = $total_sales;
          $kpi->numberPrefix = '$';
          $chart_data->add_kpi('total_sales',$kpi);
          $kpi = new StdClass();
          $kpi->caption = 'Total # of Orders';
          $kpi->value = $num_orders;
          $chart_data->add_kpi('total_orders',$kpi);
          break;
       case 3:
          $oldest_query = 'select min(order_date) as oldest_date from orders';
          get_range_selection($db,$start_date,$end_date,$oldest_query,null);
          $query = 'select external_source,count(id) as num_orders from ' .
                   'orders';
          $query .= ' where (order_date>='.$start_date.') and (order_date<=' .
                    $end_date.') and (status!='.$cancelled_option.')';
          $query .= ' group by external_source';
          $counts = $db->get_records($query);
          if (! $counts) {
             if (isset($db->error)) http_response(422,$db->error);
             else return;
          }
          $chart_data = new ChartData(PIE_CHART);
          $series = new ChartSeries('Order Sources');
          $chart_data->labels = array();   $series->data = array();
          foreach ($counts as $count) {
             $external_source = $count['external_source'];
             if (! $external_source) $external_source = 'Shopping Cart';
             $chart_data->labels[] = $external_source;
             $series->data[] = $count['num_orders'];
          }
          $chart_data->add_series($series);
          break;
       case 4:
          $query = 'select o.order_date as date,concat(o.fname," ",o.lname) ' .
             'as customer,(select count(id) from order_items where parent=' .
             'o.id) as items,o.total from orders o where (o.status!=' .
             $cancelled_option.')';
          $query .= ' order by order_date desc limit 16';
          $orders = $db->get_records($query);
          if (! $orders) {
             if (isset($db->error)) http_response(422,$db->error);
             else return;
          }

          $chart_data = new TableData(8);
          $column = new TableColumn('date','Date/Time');
          $column->add_option('columnWidth','140px');
          $chart_data->add_column($column);
          $column = new TableColumn('customer','Customer');
          $chart_data->add_column($column);
          $column = new TableColumn('items','# Items');
          $column->add_option('textAlign','center');
          $column->add_option('columnWidth','40px');
          $chart_data->add_column($column);
          $column = new TableColumn('total','Order Total');
          $column->add_option('textAlign','right');
          $column->add_option('columnWidth','70px');
          $chart_data->add_column($column);
          foreach ($orders as $order) {
             $order['date'] = date('m/d/Y g:i:s a',$order['date']);
             $order['total'] = '$'.number_format($order['total'],2);
             $chart_data->add_row($order);
          }
          break;
    }
    print json_encode($chart_data);
}

function add_dashboard_tab($tab,$label)
{
    print '   db_tabs["'.$tab.'"] = "'.$label."\";\n";
}

function add_dashboard_widget($id,$tab,$name,$type,$width,$height)
{
    print '   db_widgets['.$id.'] = { name: "'.$name.'", tab: "'.$tab .
          '", type: '.$type.', width: '.$width.', height: '.$height." };\n";
}

function adjust_start_date($date_value,$summary)
{
    $month = date('n',$date_value);
    $day = date('d',$date_value);
    $year = date('y',$date_value);
    switch ($summary) {
       case 'Day': break;
       case 'Week': $day -= date('w',$date_value);   break;
       case 'Month': $day = 1;   break;
       case 'Year': $month = 1;   $day = 1;   break;
    }
    $date_value = mktime(0,0,0,$month,$day,$year);
    return $date_value;
}

function get_range_selection($db,&$start_date,&$end_date,$oldest_query,
                             $summary,$newest_query=null)
{
    $row = $db->get_record($oldest_query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       $oldest_date = 0;
    }
    else $oldest_date = $row['oldest_date'];
    if ($newest_query) {
       $row = $db->get_record($newest_query);
       if (! $row) {
          if (isset($db->error)) process_error('Database Error: '.$db->error,0);
          $newest_date = time();
       }
       else $newest_date = $row['newest_date'];
    }
    else $newest_date = time();
    $range = get_form_field('Range');
    switch ($range) {
       case 'All': $start_date = $oldest_date;   $end_date = $newest_date;
                   break;
       case 'ThisWeek': $day_of_week = date('w');
                        $today = mktime(0,0,0,date('n'),date('d'),date('y'));
                        $start_date = $today - ($day_of_week * 86400);
                        $end_date = $today + ((6 - $day_of_week) * 86400) + 86399;
                        break;
       case 'ThisMonth': $start_date = mktime(0,0,0,date('n'),1,date('y'));
                         $end_date = mktime(23,59,59,date('n'),date('t'),date('y'));
                         break;
       case 'LastMonth': $month = date('n');   $year = date('y');
                         $month--;
                         if ($month == 0) {
                            $month = 12;   $year--;
                         }
                         $month_day = mktime(12,0,0,$month,1,$year);
                         $num_days = date('t',$month_day);
                         $start_date = mktime(0,0,0,$month,1,$year);
                         $end_date = mktime(23,59,59,$month,$num_days,$year);
                         break;
       case 'FiscalQuarter': if (function_exists('get_cart_config_value'))
                                $fiscal_year_start = intval(get_cart_config_value('fiscalyear')) + 1;
                             else $fiscal_year_start = 1;
                             $month = date('n');   $year = date('y');
                             $start_month = $fiscal_year_start;
                             if ($month > $start_month)
                                while (($start_month + 3) <= $month) $start_month += 3;
                             else if ($start_month > $month)
                                while ($start_month > $month) $start_month -= 3;
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
                             break;
       case 'FiscalYear': if (function_exists('get_cart_config_value'))
                             $fiscal_year_start = intval(get_cart_config_value('fiscalyear')) + 1;
                          else $fiscal_year_start = 1;
                          $month = date('n');   $year = date('y');
                          if ($month < $fiscal_year_start) $year--;
                          $start_date = mktime(0,0,0,$fiscal_year_start,1,$year);
                          $end_date = mktime(0,0,0,$fiscal_year_start,1,($year + 1)) - 1;
                          break;
       case 'Range': $start_date = get_form_field('StartDate');
                     $end_date = get_form_field('EndDate');
                     convert_date_range($start_date,$end_date);
                     break;
    }
    if ($start_date < $oldest_date) $start_date = $oldest_date;
    if (($start_date == $oldest_date) && $summary)
       $start_date = adjust_start_date($start_date,$summary);
    if ($start_date > $newest_date) $start_date = $newest_date;
    if ($end_date > $newest_date) $end_date = $newest_date;
}

function build_query($widget,$start_date,$end_date)
{
    $query = $widget['query'];

    if ($start_date == 0) $where_condition = '';
    else if (isset($widget['where'])) {
       $where_condition = $widget['where'];
       $where_condition = str_replace('%start%',$start_date,$where_condition);
       $where_condition = str_replace('%end%',$end_date,$where_condition);
    }
    else $where_condition = '';
    $query = str_replace('%where%',$where_condition,$query);

    if ($start_date == 0) $and_condition = '';
    else if (isset($widget['and'])) {
       $and_condition = $widget['and'];
       $and_condition = str_replace('%start%',$start_date,$and_condition);
       $and_condition = str_replace('%end%',$end_date,$and_condition);
    }
    else $and_condition = '';
    $query = str_replace('%and%',$and_condition,$query);
    if ($start_date != 0) {
       $query = str_replace('%start%',$start_date,$query);
       $query = str_replace('%end%',$end_date,$query);
    }

    return $query;
}

function increment_date($date_value,$summary)
{
    $month = date('n',$date_value);
    $day = date('d',$date_value);
    $year = date('y',$date_value);
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

function load_widget_data($widget)
{
    $db = $widget['db'];
    if (isset($widget['summary'])) {
       $summary = $widget['summary'];
       $start_date = $widget['start'];
       $end_date = $widget['end'];
       if ($summary == 'All') $summary = null;
       else $end_date = increment_date($start_date,$summary) - 1;
       $query = build_query($widget,$start_date,$end_date);
    }
    else if (isset($widget['where']) && isset($widget['start']) &&
             isset($widget['end'])) {
       $summary = null;
       $start_date = $widget['start'];
       $end_date = $widget['end'];
       $query = build_query($widget,$start_date,$end_date);
    }
    else {
       $summary = null;
       $query = str_replace('%where%','',$widget['query']);
    }

    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) http_response(422,$db->error);
       return null;
    }
    $widget_data = array();
    while ($result) {
       while ($row = $db->fetch_assoc($result)) {
          if ($summary) {
             switch ($summary) {
                case 'Day': $range_label = date('n/j/y',$start_date);
                            break;
                case 'Week': $range_label = date('n/j/y',$start_date).'-' .
                                              date('n/j/y',$end_date);
                             break;
                case 'Month': $range_label = date('M Y',$start_date);
                              break;
                case 'Year': $range_label = date('Y',$start_date);   break;
             }
             $widget_data[] = array_merge(array('range'=>$range_label),$row);
          }
          else $widget_data[] = $row;
       }
       $db->free_result($result);
       if ($summary) {
          $start_date = increment_date($start_date,$summary);
          if ($start_date >= $widget['end']) $result = null;
          else {
             $end_date = increment_date($start_date,$summary) - 1;
             $query = build_query($widget,$start_date,$end_date);
             $result = $db->query($query);
             if (! $result) {
                http_response(422,$db->error);   return null;
             }
          }
       }
       else $result = null;
    }
    return $widget_data;
}

function start_dashboard_filter($screen,$prompt)
{
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write($prompt);
    if ($screen->skin) $screen->write("</span>");
    else $screen->write("<br>\n");
}

function add_dashboard_filters($screen)
{
    start_dashboard_filter($screen,'Date Range:');
    $screen->write("<select name=\"range\" id=\"range\" " .
                   "onChange=\"update_dashboard();\" class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    $screen->add_list_item('All','All',true);
    $screen->add_list_item('ThisWeek','This Week',false);
    $screen->add_list_item('ThisMonth','This Month',false);
    $screen->add_list_item('LastMonth','Last Month',false);
    $screen->add_list_item('FiscalQuarter','This Fiscal Quarter',false);
    $screen->add_list_item('FiscalYear','This Fiscal Year',false);
    $screen->end_choicelist();
/*
    $screen->add_list_item('Range','Range:',false);
    $screen->write("</td><td>or:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td><td>\n");
    $start_date = mktime(0,0,0,1,1,date('y'));
    $screen->add_date_field('range_start_date',$start_date);
    $screen->write("</td><td>&nbsp;&nbsp;-&nbsp;&nbsp;</td><td>\n");
    $end_date = mktime(12,59,59,12,31,date('y'));
    $screen->add_date_field('range_end_date',$end_date);
*/
    if ($screen->skin) $screen->write("</div>");
    else $screen->write("</td></tr>\n");

    start_dashboard_filter($screen,'Summarize By:');
    $screen->write("<select name=\"summary\" id=\"summary\" " .
                   "onChange=\"update_dashboard();\" class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    $screen->add_list_item('Year','Year',true);
    $screen->add_list_item('Month','Month',false);
    $screen->add_list_item('Week','Week',false);
    $screen->add_list_item('Day','Day',false);
    $screen->add_list_item('All','All',false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write("</div>");
    else $screen->write("</td></tr>\n");
}

function display_dashboard_screen()
{
    $screen = new Screen;
    $screen->enable_ajax();
    $screen->enable_calendar();
    $head_block = '<meta name="viewport" content="initial-scale=1.0, user-scalable=no">';
    $screen->add_head_line($head_block);
    $screen->add_style_sheet('dashboard.css');
    $screen->add_script_file('dashboard.js');
    $screen->add_style_sheet('RazorFlow/css/razorflow.min.css');
    $screen->add_script_file('RazorFlow/js/jquery.min.js');
    $screen->add_script_file('RazorFlow/js/razorflow.min.js');
    $screen->add_script_file('RazorFlow/js/razorflow.devtools.min.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $screen->set_onload_function('dashboard_onload();');
    $screen->set_body_id('dashboard');
    $screen->set_help('dashboard');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Dashboard');
       $screen->start_title_filters();
       add_dashboard_filters($screen);
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    else {
       $screen->start_button_column();
       add_dashboard_filters($screen);
       $screen->end_button_column();
    }
    $screen->write("<div id=\"dbTarget\" style=\"position:relative;\" " .
                   "class=\"rf\"></div>\n");
    $screen->write("<script type=\"text/javascript\">\n");
    add_dashboard_widgets();
    $screen->write("</script>\n");
    $screen->end_body();
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'loadwidget') load_widget();
else display_dashboard_screen();

DB::close_all();

?>
