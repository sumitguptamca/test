<?php
/*
                    Inroads Shopping Cart - Amazon MWS API Module
                    Amazon Developer Account Number 0536-5778-3158

                         Written 2013-2019 by Randall Severy
                           Copyright 2013-2019 Inroads, LLC

*/

define('AMAZON_ENDPOINT','https://mws.amazonservices.com');
define('AMAZON_MARKETPLACE_ID','ATVPDKIKX0DER');

define('AMAZON_ASIN_PART_NUMBER',0);
define('AMAZON_ASIN_UPC',1);
define('AMAZON_ASIN_MPN',2);

class Amazon {

function __construct($db=null)
{
    if ($db) $this->db = $db;
    else $this->db = new DB;
    $this->merchant_id = get_cart_config_value('amazon_merchant_id',$this->db);
    $this->access_key_id = 'AKIAIBZZIQB6H4YM4PRQ';
    $this->secret_key = '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';
    $this->debug = false;
    $this->error = null;
    $status_map = get_cart_config_value('amazon_dl_status_map',$this->db);
    $status_map = explode('|',$status_map);
    if ($status_map[0] === '') $this->dl_active_status = 0;
    else $this->dl_active_status = $status_map[0];
    if (isset($status_map[1])) $this->dl_inactive_status = $status_map[1];
    else $this->dl_inactive_status = 2;
    if (isset($status_map[2])) $this->dl_incomplete_status = $status_map[2];
    else $this->dl_incomplete_status = -1;
    if (isset($status_map[3])) $this->dl_delete_status = $status_map[3];
    else $this->dl_delete_status = 2;
    $status_map = get_cart_config_value('amazon_ul_status_map',$this->db);
    $status_map = explode('|',$status_map);
    if ($status_map[0] === '') $this->ul_delete_status = 1;
    else $this->ul_delete_status = $status_map[0];
    if (isset($status_map[1])) $this->ul_inactive_status = $status_map[1];
    else $this->ul_inactive_status = 2;
    $this->features = get_cart_config_value('features',$this->db);
    $this->product_field_map = array('ASIN'=>'asin','ns2:Binding'=>'binding',
       'ns2:Brand'=>'brand','ns2:Size'=>'size',
       'ns2:Color'=>'color','ns2:Department'=>'department',
       'ns2:ItemDimensions'=>'dimensions','ns2:Label'=>'label',
       'ns2:ListPrice'=>'list_price','ns2:Manufacturer'=>'manufacturer',
       'ns2:MaterialType'=>'material','ns2:Model'=>'model',
       'ns2:PartNumber'=>'part_number','ns2:ProductGroup'=>'product_group',
       'ns2:ProductTypeName'=>'product_type','ns2:SmallImage'=>'image_url',
       'ns2:Studio'=>'studio','ns2:Title'=>'title',
       'ns2:PackageDimensions'=>'package_dimensions');
}

function Amazon($db=null)
{
    self::__construct($db);
}

function log_activity($activity_msg)
{
    global $activity_log;

    $path_parts = pathinfo($activity_log);
    $amazon_activity_log = $path_parts['dirname'].'/amazon.log';
    $activity_file = @fopen($amazon_activity_log,'at');
    if ($activity_file) {
       fwrite($activity_file,'['.date('D M d Y H:i:s').'] ' .
              $activity_msg."\n");
       fclose($activity_file);
    }
}

function process_error($error_msg,$interactive=false)
{
    if (function_exists('process_report_error'))
       process_report_error($error_msg);
    else {
       log_error($error_msg);
       print $error_msg."\n";
    }
}

function open_envelope()
{
    $charset = ini_get('default_charset');
    if (! $charset) $charset = 'iso-8859-1';
    $xml_data = '<?xml version="1.0" encoding="'.$charset.'"?>' .
       '<AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '.
       'xsi:noNamespaceSchemaLocation="amzn-envelope.xsd">';
    $xml_data .= '<Header><DocumentVersion>1.01</DocumentVersion>' .
       '<MerchantIdentifier>'.$this->merchant_id .
       '</MerchantIdentifier></Header>';
    return $xml_data;
}

function close_envelope()
{
    $xml_data = '</AmazonEnvelope>';
    return $xml_data;
}

function encode_xml_data($xml_data)
{
    $xml_data = preg_replace('/[^(\x20-\x7F)]*/','',$xml_data);
    $xml_data = str_replace('&','&amp;',$xml_data);
    $xml_data = str_replace('<','&lt;',$xml_data);
    $xml_data = str_replace('>','&gt;',$xml_data);
    return $xml_data;
}

function append_xml(&$xml,$field_name,$field_value,$max_length=0)
{
    if (! $field_value) return;
    if ($max_length && (strlen($field_value) > $max_length))
       $field_value = substr($field_value,0,$max_length);
    $xml .= '<'.$field_name.'>'.$this->encode_xml_data($field_value) .
            '</'.$field_name.'>';
}

function parse_tag($response,$tag_name)
{
    $start_tag = '<'.$tag_name.'>';
    $start_pos = strpos($response,$start_tag);
    if ($start_pos === false) return null;
    $start_pos += strlen($start_tag);
    $end_pos = strpos($response,'</'.$tag_name.'>',$start_pos);
    if ($end_pos === false) return null;
    return substr($response,$start_pos,$end_pos - $start_pos);
}

function parse_attr_tag($response,$tag_name,&$attributes)
{
    $attributes = array();
    $start_tag = '<'.$tag_name;
    $start_pos = strpos($response,$start_tag);
    if ($start_pos === false) return null;
    $start_pos += strlen($start_tag);
    $end_start = strpos($response,'>',$start_pos);
    if ($end_start === false) return null;
    $curr_pos = $start_pos;
    while (substr($response,$curr_pos,1) == ' ') {
       $curr_pos++;
       $start_quote = strpos($response,'"',$curr_pos);
       if ($start_quote === false) break;
       $attr_name = substr($response,$curr_pos,$start_quote - $curr_pos - 1);
       $start_quote++;
       $end_quote = strpos($response,'"',$start_quote);
       if ($end_quote === false) break;
       $attr_value = substr($response,$start_quote,$end_quote - $start_quote);
       $attributes[$attr_name] = $attr_value;
       $curr_pos = $end_quote + 1;
    }
    $end_start++;
    $end_pos = strpos($response,'</'.$tag_name.'>',$end_start);
    if ($end_pos === false) return null;
    return substr($response,$end_start,$end_pos - $end_start);
}

function decode($text)
{
    $text = html_entity_decode($text);
    $text = str_replace('&apos;',"'",$text);
    return $text;
}

function call($path,$action,$version,$post_data=null,$params=null,
              $remove_newlines=true)
{
    global $base_url;

    $url_info = parse_url(AMAZON_ENDPOINT);
    $base_params = array('AWSAccessKeyId' => $this->access_key_id,
                         'Action' => $action,
                         'Merchant' => $this->merchant_id,
                         'SignatureMethod' => 'HmacSHA256',
                         'SignatureVersion' => '2',
                         'Timestamp' => date('c'),
                         'Version' => $version);
    if ($params) $params = array_merge($base_params,$params);
    else $params = $base_params;
    ksort($params);
    $query_string = '';
    foreach ($params as $name => $value) {
       if ($query_string) $query_string .= '&';
       $query_string .= $name.'='.rawurlencode($value);
    }
    $string_to_sign = "POST\n".strtolower($url_info['host'])."\n".$path."\n" .
                      $query_string;
    $signature = base64_encode(hash_hmac('sha256',$string_to_sign,
                                         $this->secret_key,true));
    $query_string .= '&Signature='.rawurlencode($signature);
    $base_url_info = parse_url($base_url);

    $http = new HTTP(AMAZON_ENDPOINT.$path.'?'.$query_string);
    $http->set_content_type('text/xml');
    $user_agent = 'Inroads AxiumPro/2.0 (Language=PHP; Host=' .
                  $base_url_info['host'].')';
    $http->set_user_agent($user_agent);
    if ($post_data) {
       $headers = array('Content-MD5: '.base64_encode(md5($post_data,true)));
       $http->set_headers($headers);
    }
    if ($this->debug) {
       $this->log_activity('Sent: '.$path.'?'.$query_string);
       if ($post_data) $this->log_activity('Post Data: '.$post_data);
    }
    $response_string = $http->call($post_data);
    if (! $response_string) {
       if (empty($http->error)) $this->error = $http->status_string;
       else $this->error = $http->error;
       $this->error .= ' ('.$http->status.')';
       return null;
    }
    if ($remove_newlines) {
       $response_string = str_replace("\n",'',$response_string);
       $response_string = str_replace("\r",'',$response_string);
    }
    $this->response = $response_string;
    $this->status = $http->status;
    if ($this->debug) $this->log_activity('Response: '.$response_string);
    if (($this->status != 100) && ($this->status != 200)) {
       $this->error_code = $this->parse_tag($response_string,'Code');
       $this->error_message = $this->parse_tag($response_string,'Message');
       if ($this->error_code && $this->error_message)
          $this->error = $this->error_code.': '.$this->error_message;
       else $this->error = $response_string.' ('.$this->status.')';
       return null;
    }
    return $response_string;
}

function get_service_status()
{
    $response = $this->call('/Products/2011-10-01','GetServiceStatus',
                            '2011-10-01');
    if (! $response) {
       $this->process_error('Amazon Error: '.$this->error,true);
       return null;
    }
    $status = $this->parse_tag($response,'Status');
    return $status;
}

function feed_status($feed_id)
{
    $params = array('FeedSubmissionIdList.Id.1' => $feed_id);
    $response = $this->call('/','GetFeedSubmissionList','2009-01-01','',
                            $params);
    if (! $response) return null;
    $status = $this->parse_tag($response,'FeedProcessingStatus');
    return $status;
}

function wait_for_done($feed_id)
{
    $status = '';   $sleep_timer = 30;
    while ($status != '_DONE_') {
       $status = $this->feed_status($feed_id);
       if (! $status) {
          if (isset($this->error_code) &&
              ($this->error_code == 'RequestThrottled')) {
             $sleep_timer = 60;   sleep($sleep_timer);   continue;
          }
          else if (isset($this->error_code) &&
                   ($this->error_code == 'QuotaExceeded')) {
             sleep(600);   continue;
          }
          $this->process_error('Amazon Error: '.$this->error);   return false;
       }
       if ($status != '_DONE_') sleep($sleep_timer);
    }
    return true;
}

function feed_results($feed_id)
{
    $params = array('FeedSubmissionId' => $feed_id);
    $response = $this->call('/','GetFeedSubmissionResult','2009-01-01','',
                            $params);
    if (! $response) return null;
    $results = explode('<Result>',$response);
    if (count($results) < 2) return array();
    unset($results[0]);
    $feed_results = array();
    foreach ($results as $result) {
       $message_id = $this->parse_tag($result,'MessageID');
       $code = $this->parse_tag($result,'ResultCode');
       $message_code = $this->parse_tag($result,'ResultMessageCode');
       $description = $this->decode($this->parse_tag($result,'ResultDescription'));
       $sku = $this->parse_tag($result,'SKU');
       $feed_results[] = array('message_id'=>$message_id,'code'=>$code,
          'message_code'=>$message_code,'description'=>$description,
          'sku'=>$sku);
    }
    return $feed_results;
}

function submit_feed($feed_type,$xml_data,$wait_for_done=true)
{
    $params = array('FeedType' => $feed_type);
    $response = $this->call('/','SubmitFeed','2009-01-01',$xml_data,$params);
    if (! $response) {
       $this->process_error('Amazon Error: '.$this->error);   return 0;
    }
    $feed_id = $this->parse_tag($response,'FeedSubmissionId');
    if ($this->debug)
       $this->log_activity($feed_type.' Feed Submitted with Id '.$feed_id);
    if ($wait_for_done) {
       if (! $this->wait_for_done($feed_id)) return 0;
    }
    return $feed_id;
}

function get_feed_list($feed_ids=null)
{
    if ($feed_ids) {
       $params = array();
       foreach ($feed_ids as $index => $feed_id)
          $params['FeedSubmissionIdList.Id.'.($index + 1)] = $feed_id;
    }
    else $params = null;
    $response = $this->call('/','GetFeedSubmissionList','2009-01-01',null,
                            $params);
    if (! $response) {
       if (isset($this->error_code) &&
           ($this->error_code == 'RequestThrottled')) {
          sleep(60);   return $this->get_feed_list($feed_ids);
       }
       $this->process_error('Amazon Error: '.$this->error);
       return null;
    }
    $feed_list = explode('<FeedSubmissionInfo>',$response);
    if (count($feed_list) < 2) return array();
    unset($feed_list[0]);
    return $feed_list;
}

function get_orders(&$last_download)
{
    $params = array('LastUpdatedAfter' => $last_download,
                    'MarketplaceId.Id.1' => AMAZON_MARKETPLACE_ID,
                    'SellerId' => $this->merchant_id);
    $response = $this->call('/Orders/2013-09-01','ListOrders',
                            '2013-09-01','',$params);
    if (! $response) {
       $this->process_error('Amazon Error: '.$this->error);   return null;
    }
    $last_download = $this->parse_tag($response,'LastUpdatedBefore');
    $orders = explode('<Order>',$response);
    if (count($orders) < 2) return null;
    unset($orders[0]);
    return $orders;
}

function get_order_items($order_id)
{
    $params = array('AmazonOrderId' => $order_id,
                    'SellerId' => $this->merchant_id);
    $items_response = $this->call('/Orders/2013-09-01','ListOrderItems',
                                    '2013-09-01','',$params);
    if (! $items_response) {
       $this->process_error('Amazon Error: '.$this->error);   return null;
    }
    $items = explode('<OrderItem>',$items_response);
    if (count($items) < 2) return null;
    unset($items[0]);
    return $items;
}

function print_results($feed_id)
{
    $results = $this->feed_results($feed_id);
    if ($results === null) {
       $this->process_error('Amazon Error: '.$this->error,true);   return false;
    }
    $ret_value = true;
    foreach ($results as $result) {
       if ($result['code'] == 'Error') $ret_value = false;
       print "\n".$result['code'].': '.$result['message_code'].': ' .
             $result['description'];
       if ($result['sku']) print ' (SKU: '.$result['sku'].')';
       print "\n";
    }
    return $ret_value;
}

function append_results($feed_id,$feed_label,&$result_data)
{
    $this->latest_results = $this->feed_results($feed_id);
    if ($this->latest_results === null) {
       $this->process_error('Amazon Error: '.$this->error);   return false;
    }
    foreach ($this->latest_results as $result) {
       $result['label'] = $feed_label;
       $result_data[] = $result;
    }
    return true;
}

function report_status($request_id,&$report_id)
{
    $report_id = null;
    $params = array('ReportRequestIdList.Id.1' => $request_id);
    $response = $this->call('/Reports/2009-01-01','GetReportRequestList',
                            '2009-01-01',null,$params);
    if (! $response) return null;
    $status = $this->parse_tag($response,'ReportProcessingStatus');
    if ($status == '_DONE_')
       $report_id = $this->parse_tag($response,'GeneratedReportId');
    return $status;
}

function wait_for_report_done($request_id,&$report_id,$get_last=false)
{
    $status = '';   $sleep_timer = 15;
    while ($status != '_DONE_') {
       $status = $this->report_status($request_id,$report_id);
       if (! $status) {
          if (isset($this->error_code) &&
              ($this->error_code == 'RequestThrottled')) {
             $sleep_timer = 30;   sleep($sleep_timer);   continue;
          }
          else if (isset($this->error_code) &&
                   ($this->error_code == 'QuotaExceeded')) {
             sleep(600);   continue;
          }
          $this->process_error('Amazon Error: '.$this->error);   return false;
       }
       if ($status == '_CANCELLED_') {
          if ($get_last) {
             $report_id = '_CANCELLED_';   return true;
          }
          $this->process_error('Report Cancelled by Amazon');   return false;
       }
       if ($status != '_DONE_') sleep($sleep_timer);
    }
    return true;
}

function get_report_id($request_id)
{
    $params = array('ReportRequestIdList.Id.1' => $request_id);
    $response = $this->call('/Reports/2009-01-01','GetReportList',
                            '2009-01-01',null,$params);
    if (! $response) return null;
    $report_id = $this->parse_tag($response,'ReportId');
    return $report_id;
}

function get_report($report_type,$get_last=false)
{
    $params = array('ReportType' => $report_type);
    $response = $this->call('/Reports/2009-01-01','RequestReport','2009-01-01',
                            null,$params);
    if (! $response) {
       $this->process_error('Amazon Error: '.$this->error);   return null;
    }
    $request_id = $this->parse_tag($response,'ReportRequestId');
    if ($this->debug)
       $this->log_activity('Report Requested with Id '.$request_id);
    if (! $this->wait_for_report_done($request_id,$report_id,$get_last))
       return null;
    if ($get_last && ($report_id == '_CANCELLED_')) {
       $reports = $this->get_report_list(0,1,$report_type);
       if (! empty($reports[0]['id'])) $report_id = $reports[0]['id'];
       else $report_id = null;
    }
    if (! $report_id) $report_id = $this->get_report_id($request_id);
    if (! $report_id) {
       $this->process_error('Amazon Error: No Report Generated for Request ' .
                            $request_id);
       return null;
    }
    $params = array('ReportId' => $report_id);
    $response = $this->call('/Reports/2009-01-01','GetReport','2009-01-01',
                            null,$params,false);
    if (! $response) {
       $this->process_error('Amazon Error: '.$this->error);   return null;
    }
    $report_lines = explode("\n",$response);
    $data = array();
    foreach ($report_lines as $line) {
       $line = trim($line);
       if (! $line) continue;
       $data[] = explode("\t",$line);
    }
    return $data;
}

function get_report_list($num_days=1,$maxcount=100,$types=null)
{
    $params = array();
    if ($num_days) {
       $from_date = date('c',time() - ($num_days * 86400));
       $params['AvailableFromDate'] = $from_date;
    }
    if ($maxcount) $params['MaxCount'] = $maxcount;
    if ($types) {
       if (! is_array($types)) $types = array($types);
       foreach ($types as $index => $type)
          $params['ReportTypeList.Type.'.($index + 1)] = $type;
    }
    $response = $this->call('/Reports/2009-01-01','GetReportList',
                            '2009-01-01',null,$params);
    if (! $response) return null;
    $results = explode('<ReportInfo>',$response);
    $num_results = count($results);
    if ($num_results == 1) {
       $this->process_error('Amazon Error: No Reports Found');   return null;
    }
    $reports = array();
    for ($loop = 1;  $loop < $num_results;  $loop++) {
       $result = $results[$loop];
       $report_type = $this->parse_tag($result,'ReportType');
       $report_id = $this->parse_tag($result,'ReportId');
       $date = $this->parse_tag($result,'AvailableDate');
       $reports[] = array('id' => $report_id,'type' => $report_type,
                          'date'=>$date);
    }
    return $reports;
}

function parse_product_info($result)
{
    $product = array();   $dim_prefix = '';
    foreach ($this->product_field_map as $tag => $field) {
       $product[$field] = $this->parse_tag($result,$tag);
       switch ($field) {
          case 'package_dimensions': $dim_prefix = 'package_';
          case 'dimensions':
             $dimensions = $product[$field];
             $weight = $this->parse_attr_tag($product[$field],'ns2:Weight',
                                             $attributes);
             if ($weight !== null) $product[$dim_prefix.'weight'] = $weight;
             $height = $this->parse_attr_tag($product[$field],'ns2:Height',
                                             $attributes);
             if ($height !== null) $product[$dim_prefix.'height'] = $height;
             $width = $this->parse_attr_tag($product[$field],'ns2:Width',
                                             $attributes);
             if ($width !== null) $product[$dim_prefix.'width'] = $width;
             $length = $this->parse_attr_tag($product[$field],'ns2:Length',
                                             $attributes);
             if ($length !== null) $product[$dim_prefix.'length'] = $length;
             unset($product[$field]);
             $dim_prefix = '';
             break;
          case 'list_price':
             $product['currency'] = $this->parse_tag($product[$field],
                                                     'ns2:CurrencyCode');
             $product[$field] = $this->parse_tag($product[$field],
                                                 'ns2:Amount');
             break;
          case 'image_url':
             $product[$field] = $this->parse_tag($product[$field],
                                                 'ns2:URL');
             break;
       }
    }
    return $product;
}

function list_matching_products($query)
{
    $params = array('MarketplaceId' => AMAZON_MARKETPLACE_ID,
                    'SellerId' => $this->merchant_id,
                    'Query' => $query);
    $response = $this->call('/Products/2011-10-01','ListMatchingProducts',
                            '2011-10-01',null,$params);
    if (! $response) {
       if (isset($this->error_code) &&
           ($this->error_code == 'RequestThrottled')) {
          sleep(30);   return $this->list_matching_products($query);
       }
       else if (isset($this->error_code) &&
                ($this->error_code == 'QuotaExceeded')) {
          sleep(600);   return $this->list_matching_products($query);
       }
       $this->process_error('Amazon Error: '.$this->error);   return null;
    }
    $results = explode('<Product>',$response);
    $num_results = count($results);
    if ($num_results == 1) return array();
    $products = array();
    for ($loop = 1;  $loop < $num_results;  $loop++) {
       $products[] = $this->parse_product_info($results[$loop]);
    }
    return $products;
}

function get_products($id_type,$ids)
{
    $params = array('MarketplaceId' => AMAZON_MARKETPLACE_ID,
                    'SellerId' => $this->merchant_id,
                    'IdType' => $id_type);
    foreach ($ids as $index => $id)
       $params['IdList.Id.'.($index + 1)] = $id;
    $response = $this->call('/Products/2011-10-01','GetMatchingProductForId',
                            '2011-10-01',null,$params);
    if (! $response) {
       if (isset($this->error_code) &&
           ($this->error_code == 'RequestThrottled')) {
          sleep(30);   return $this->get_products($id_type,$ids);
       }
       else if (isset($this->error_code) &&
                ($this->error_code == 'QuotaExceeded')) {
          sleep(600);   return $this->get_products($id_type,$ids);
       }
       $this->process_error('Amazon Error: '.$this->error);   return null;
    }
    $results = explode('<GetMatchingProductForIdResult Id="',$response);
    $num_results = count($results);
    if ($num_results == 1) {
       $this->process_error('Amazon Error: No Products Found');   return null;
    }
    $products = array();
    for ($loop = 1;  $loop < $num_results;  $loop++) {
       $result = $results[$loop];
       $end_pos = strpos($result,'"');
       if ($end_pos === false) {
          $this->process_error('Amazon Error: Unable to parse '.$id_type);
          return null;
       }
       $id = substr($result,0,$end_pos);
       $product = $this->parse_product_info($result);
       $products[$id] = $product;
    }
    return $products;
}

function get_last_modified($url,$timeout=5)
{
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_HEADER,true);
    curl_setopt($ch,CURLOPT_FILETIME,true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
    curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
    curl_setopt($ch,CURLOPT_NOBODY,true);
    curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
    curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
    $head = curl_exec($ch);
    if (! $head) $curl_error = curl_error($ch);
    curl_close($ch);
    if (! $head) {
       $this->error = 'Error downloading URL '.$url.': '.$curl_error;
       return -1;
    }
    $head_lines = explode("\n",$head);
    foreach ($head_lines as $header) {
       if (substr(strtolower($header),0,15) == 'last-modified: ')
          return strtotime(substr($header,15));
    }
    return null;
}

function parse_category_info($page_data,&$category_info)
{
    $start_pos = strpos($page_data,'<link rel="canonical" href="');
    if ($start_pos !== false) {
       $start_pos += 28;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false) {
          $url = substr($page_data,$start_pos,$end_pos - $start_pos);
          $path = parse_url($url,PHP_URL_PATH);
          $path_parts = explode('/',$path);
          if (isset($path_parts[3]))
             $category_info['seo_url'] = $path_parts[3];
       }
    }
    $start_pos = strpos($page_data,'<meta name="description" content="');
    if ($start_pos !== false) {
       $start_pos += 34;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false)
          $category_info['seo_description'] =
             substr($page_data,$start_pos,$end_pos - $start_pos);
    }
    $start_pos = strpos($page_data,'<title>');
    if ($start_pos !== false) {
       $start_pos += 7;
       $end_pos = strpos($page_data,'</title>',$start_pos);
       if ($end_pos !== false) {
          $title = substr($page_data,$start_pos,$end_pos - $start_pos);
          if (substr($title,0,12) == 'Amazon.com: ')
             $title = substr($title,12);
          $category_info['seo_title'] = $title;
       }
    }
    $start_pos = strpos($page_data,'<meta name="keywords" content="');
    if ($start_pos !== false) {
       $start_pos += 31;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false)
          $category_info['seo_keywords'] =
             substr($page_data,$start_pos,$end_pos - $start_pos);
    }
}

function parse_category_products($page_data,&$category_info)
{
    $start_pos = strpos($page_data,'stores-widget-productgrid');
    if ($start_pos === false) return;
    $start_pos = strpos($page_data,'var config = ',$start_pos);
    if ($start_pos === false) return;
    $start_pos += 13;
    $end_pos = strpos($page_data,'ReactDOM.render',$start_pos);
    if ($end_pos === false) return;
    $json = trim(substr($page_data,$start_pos,$end_pos - $start_pos));
    if (substr($json,-1) == ';') $json = substr($json,0,-1);
    $json_data = json_decode($json);
    if (! $json_data) return;
    if (empty($json_data->content->ASINList)) return;
    $category_info['asins'] = $json_data->content->ASINList;
}

function scrape_category_info($cat_url,&$category_info)
{
    $url = 'https://www.amazon.com'.$cat_url;
    $http = new HTTP($url);
    $http->set_method('GET');
    $page_data = $http->call();
    if (! $page_data) {
       if (empty($http->error)) $error = $http->status_string;
       else $error = $http->error;
       $error .= ' ('.$http->status.')';
       $this->process_error('Unable to retrieve category info from ' .
                            $url.': '.$error);
       return;
    }
    if (strpos($page_data,'To discuss automated access') !== false) {
       $this->process_error('Amazon blocked access to '.$url);
       return;
    }
    $this->parse_category_info($page_data,$category_info);
    $this->parse_category_products($page_data,$category_info);
}

function get_next_pos($nav,$curr_pos,&$tag,&$level)
{
    $ul_pos = strpos($nav,'<ul',$curr_pos);
    $li_pos = strpos($nav,'<li',$curr_pos);
    $end_ul_pos = strpos($nav,'</ul>',$curr_pos);
    if ($ul_pos === false) {
       if ($li_pos === false) {
          if ($end_ul_pos === false) return false;
          $tag = '/ul';   $level--;   return $end_ul_pos;
       }
       else if ($end_ul_pos === false) {
          $tag = 'li';   return $li_pos;
       }
       else if ($li_pos < $end_ul_pos) {
          $tag = 'li';   return $li_pos;
       }
       $tag = '/ul';   $level--;   return $end_ul_pos;
    }
    else if ($li_pos === false) {
       if ($ul_pos === false) {
          if ($end_ul_pos === false) return false;
          $tag = '/ul';   $level--;   return $end_ul_pos;
       }
       else if ($end_ul_pos === false) {
          $tag = 'ul';   $level++;   return $ul_pos;
       }
       else if ($ul_pos < $end_ul_pos) {
          $tag = 'ul';   $level++;   return $ul_pos;
       }
       $tag = '/ul';   $level--;   return $end_ul_pos;
    }
    else if ($end_ul_pos === false) {
       if ($li_pos < $ul_pos) {
          $tag = 'li';   return $li_pos;
       }
       $tag = 'ul';   $level++;   return $ul_pos;
    }
    if (($li_pos < $ul_pos) && ($li_pos < $end_ul_pos)) {
       $tag = 'li';   return $li_pos;
    }
    if (($ul_pos < $li_pos) && ($ul_pos < $end_ul_pos)) {
       $tag = 'ul';   $level++;   return $ul_pos;
    }
    $tag = '/ul';   $level--;
    return $end_ul_pos;
}

function scrape_categories($store_url)
{
    $http = new HTTP($store_url);
    $http->set_method('GET');
    $page_data = $http->call();
    if (! $page_data) {
       if (empty($http->error)) $error = $http->status_string;
       else $error = $http->error;
       $error .= ' ('.$http->status.')';
       $this->process_error('Unable to retrieve category information from ' .
                            $store_url.': '.$error);
       return null;
    }
    if (strpos($page_data,'To discuss automated access') !== false) {
       $this->process_error('Amazon blocked access to '.$store_url);
       return null;
    }

    $start_pos = strpos($page_data,'BreadcrumbList');
    if ($start_pos === false) return null;
    $start_pos = strpos($page_data,'<nav',$start_pos);
    if ($start_pos === false) return null;
    $end_pos = strpos($page_data,'</nav>',$start_pos);
    if ($end_pos === false) return null;
    $nav = substr($page_data,$start_pos,$end_pos - $start_pos);

    $categories = array();   $level = 0;   $li_count = -1;   $index = 0;
    $parents = array();
    $curr_pos = strpos($nav,'</span></span>');   // Skip "Home" nav item
    if ($curr_pos === false) return $categories;
    $next_pos = $this->get_next_pos($nav,$curr_pos,$tag,$level);
    while ($next_pos !== false) {
       $next_pos = strpos($nav,'>',$next_pos);
       if ($next_pos === false) break;
       if ($tag == 'ul') {
          $curr_pos = $next_pos + 1;   $li_count = 0;
       }
       else if ($tag == '/ul') {
          $curr_pos = $next_pos + 1;   $li_count = -1;
       }
       else {
          $end_li_pos = strpos($nav,'</li>',$next_pos);
          $ul_pos = strpos($nav,'<ul',$next_pos);
          if (($end_li_pos === false) && ($ul_pos === false)) break;
          if ($ul_pos === false) $end_pos = $end_li_pos;
          else if ($end_li_pos === false) $end_pos = $ul_pos;
          else if ($ul_pos < $end_li_pos) $end_pos = $ul_pos;
          else $end_pos = $end_li_pos;
          $li = substr($nav,$next_pos,$end_pos - $next_pos);
          $curr_pos = $end_pos;

          $start_pos = strpos($li,'<a ');   $url = '';
          if ($start_pos !== false) {
             $start_pos = strpos($li,'href="',$start_pos);
             if ($start_pos !== false) {
                $start_pos += 6;
                $end_pos = strpos($li,'?',$start_pos);
                if ($end_pos === false) $end_pos = strpos($li,'"',$start_pos);
                if ($end_pos !== false)
                   $url = substr($li,$start_pos,$end_pos - $start_pos);
             }
             $start_pos = strpos($li,'<span');
             if ($start_pos === false) break;
             $start_pos = strpos($li,'>',$start_pos);
             if ($start_pos === false) break;
             $start_pos++;
             $end_pos = strpos($li,'</span>',$start_pos);
             if ($end_pos === false) break;
             $li_count++;
             if ($li_count != 1) {
                $name = substr($li,$start_pos,$end_pos - $start_pos);
                if ($level == 0) $parent = -1;
                else $parent = $parents[$level - 1];
                $categories[$index] = array('name' => $name, 'level' => $level,
                                            'parent' => $parent,'url' => $url);
                $this->scrape_category_info($url,$categories[$index]);
                $parents[$level] = $index++;
             }
          }
       }
       $next_pos = $this->get_next_pos($nav,$curr_pos,$tag,$level);
    }

    return $categories;
}

function parse_product_data($page_data)
{
    $data = array();

    $start_pos = strpos($page_data,'<link rel="canonical" href="');
    if ($start_pos !== false) {
       $start_pos += 28;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false) {
          $url = substr($page_data,$start_pos,$end_pos - $start_pos);
          $path = parse_url($url,PHP_URL_PATH);
          $end_pos = strpos($path,'/',1);
          if ($end_pos !== false)
             $data['seo_url'] = substr($path,1,$end_pos - 1);
       }
    }
    $start_pos = strpos($page_data,'<meta name="description" content="');
    if ($start_pos !== false) {
       $start_pos += 34;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false) {
          $description = substr($page_data,$start_pos,$end_pos - $start_pos);
          if (substr($description,0,13) == 'Amazon.com : ')
             $description = substr($description,13);
          $data['seo_description'] = $description;
       }
    }
    $start_pos = strpos($page_data,'<meta name="title" content="');
    if ($start_pos !== false) {
       $start_pos += 28;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false) {
          $title = substr($page_data,$start_pos,$end_pos - $start_pos);
          if (substr($title,0,13) == 'Amazon.com : ')
             $title = substr($title,13);
          $data['seo_title'] = $title;
       }
    }
    $start_pos = strpos($page_data,'<meta name="keywords" content="');
    if ($start_pos !== false) {
       $start_pos += 31;
       $end_pos = strpos($page_data,'"',$start_pos);
       if ($end_pos !== false)
          $data['seo_keywords'] =
             substr($page_data,$start_pos,$end_pos - $start_pos);
    }
    $start_pos = strpos($page_data,'<b>Shipping Weight:</b> ');
    if ($start_pos !== false) {
       $start_pos += 24;
       $end_pos = strpos($page_data,' (',$start_pos);
       if ($end_pos !== false) {
          $weight = substr($page_data,$start_pos,$end_pos - $start_pos);
          if (substr($weight,-6) == 'ounces')
             $weight = intval(substr($weight,0,-7)) / 16;
          else $weight = intval($weight);
          $data['weight'] = $weight;
       }
    }
    $start_pos = strpos($page_data,'<h2>Product Description</h2>');
    if ($start_pos !== false) {
       $start_pos += 28;
       $end_pos = strpos($page_data,'<div id="legal_feature_div"',$start_pos);
       if ($end_pos !== false)
          $data['description'] =
             trim(substr($page_data,$start_pos,$end_pos - $start_pos));
    }
    else {
       $start_pos = strpos($page_data,'<div id="productDescription"');
       if ($start_pos !== false) {
          $start_pos = strpos($page_data,'>',$start_pos);
          if ($start_pos !== false) {
             $start_pos++;
             $end_pos = strpos($page_data,'</div>',$start_pos);
             if ($end_pos !== false)
                $data['description'] =
                   trim(substr($page_data,$start_pos,$end_pos - $start_pos));
          }
       }
    }
    $start_pos = strpos($page_data,'<table id="product-specification-table"');
    if ($start_pos !== false) {
       $start_pos += 39;
       $end_pos = strpos($page_data,'</table>',$start_pos);
       if ($end_pos !== false) {
          $table = substr($page_data,$start_pos,$end_pos - $start_pos);
          $specs = explode('<tr>',$table);
          unset($specs[0]);
          foreach ($specs as $spec) {
             $label = $value = null;
             $start_pos = strpos($spec,'<th ');
             if ($start_pos !== false) {
                $start_pos += 11;
                $start_pos = strpos($spec,'">',$start_pos);
                if ($start_pos !== false) {
                   $start_pos += 2;
                   $end_pos = strpos($spec,'</th>',$start_pos);
                   if ($end_pos !== false)
                      $label = substr($spec,$start_pos,
                                      $end_pos - $start_pos);
                }
             }
             $start_pos = strpos($spec,'<td>');
             if ($start_pos !== false) {
                $start_pos += 4;
                $end_pos = strpos($spec,'</td>',$start_pos);
                if ($end_pos !== false)
                   $value = trim(substr($spec,$start_pos,
                                        $end_pos - $start_pos));
             }
             if ($label && $value) $data[$label] = $value;
          }
       }
    }
    return $data;
}

function parse_product_images($page_data)
{
    $start_pos = strpos($page_data,"'colorImages': { 'initial':");
    if ($start_pos === false) return null;
    $start_pos += 27;
    $end_pos = strpos($page_data,"'colorToAsin':",$start_pos);
    if ($end_pos === false) return null;
    $images = substr($page_data,$start_pos,$end_pos - $start_pos);
    $images = substr(trim($images),0,-2);
    $images = str_replace("\n",'',$images);
    $image_data = json_decode($images);
    if (! $image_data) return null;
    $images = array();
    foreach ($image_data as $image)
       if ($image->hiRes) $images[$image->variant] = $image->hiRes;
    return $images;
}

function parse_product_features($page_data)
{
    $start_pos = strpos($page_data,'<div id="feature-bullets"');
    if ($start_pos !== false) {
       $end_pos = strpos($page_data,'<li><span class="a-list-item">',$start_pos);
       if ($end_pos !== false)
          $end_pos = strpos($page_data,'</div>',$end_pos);
       if ($end_pos === false) return null;
       $features = substr($page_data,$start_pos,$end_pos - $start_pos);
       $features = explode('<li><span class="a-list-item">',$features);
       unset($features[0]);
       foreach ($features as $index => $feature) {
          $end_pos = strpos($feature,'</span>');
          if ($end_pos === false) {
             unset($features[$index]);   continue;
          }
          $features[$index] = trim(substr($feature,0,$end_pos));
       }
       return $features;
    }
    $start_pos = strpos($page_data,'<div id="feature-bullets-btf"');
    if ($start_pos !== false) {
       $end_pos = strpos($page_data,'<li>',$start_pos);
       if ($end_pos !== false)
          $end_pos = strpos($page_data,'</div>',$end_pos);
       if ($end_pos === false) return null;
       $features = substr($page_data,$start_pos,$end_pos - $start_pos);
       $features = explode('<li>',$features);
       unset($features[0]);
       foreach ($features as $index => $feature) {
          $end_pos = strpos($feature,'</li>');
          if ($end_pos === false) {
             unset($features[$index]);   continue;
          }
          $features[$index] = trim(substr($feature,0,$end_pos));
       }
       return $features;
    }
}

function scrape_product_data($asin)
{
    $url = 'https://www.amazon.com/dp/'.$asin.'/';
    $http = new HTTP($url);
    $http->set_method('GET');
    $page_data = $http->call();
    if (! $page_data) {
       if (empty($http->error)) $error = $http->status_string;
       else $error = $http->error;
       $error .= ' ('.$http->status.')';
       $this->process_error('Unable to retrieve product details from ' .
                            $url.': '.$error);
       return null;
    }
    if (strpos($page_data,'To discuss automated access') !== false) {
       $this->process_error('Amazon blocked access to '.$url);
       return null;
    }
    $data = $this->parse_product_data($page_data);
    $data['images'] = $this->parse_product_images($page_data);
    $data['features'] = $this->parse_product_features($page_data);
    return $data;
}

function get_cached_asin($cache_type,$cache_value)
{
    $query = 'select id,asin,expire_date from amazon_cached_asins where ' .
             '(cache_type=?) and (cache_value=?)';
    $query = $this->db->prepare_query($query,$cache_type,$cache_value);
    $row = $this->db->get_record($query);
    if (! $row) return null;
    if ($row['expire_date'] < time()) {
       $query = 'delete from amazon_cached_asins where id=?';
       $query = $this->db->prepare_query($query,$row['id']);
       $this->db->log_query($query);
       $this->db->query($query);
       return null;
    }
    return $row['asin'];
}

function add_cached_asin($cache_type,$cache_value,$asin)
{
    $expire_date = time() + (86400 * rand(1,30));
    $query = 'insert into amazon_cached_asins (cache_type,cache_value,asin,' .
             'expire_date) values(?,?,?,?)';
    $query = $this->db->prepare_query($query,$cache_type,$cache_value,$asin,
                                      $expire_date);
    $this->db->log_query($query);
    $this->db->query($query);
}

};

?>
