<?php
/*
                 Inroads Shopping Cart - Public Review Functions

                      Written 2014-2015 by Randall Severy
                       Copyright 2014-2015 Inroads, LLC
*/

if (file_exists("engine/ui.php")) {
   require_once 'engine/ui.php';
   require_once 'engine/db.php';
   if (file_exists("admin/custom-config.php"))
      require_once 'admin/custom-config.php';
}
else {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   if (file_exists("../admin/custom-config.php"))
      require_once '../admin/custom-config.php';
}

function validate_product_id($product_id)
{
    if (! is_numeric($product_id)) {
       log_error("Invalid Product ID in Reviews (".$product_id.")");
       return false;
    }
    return true;
}

function get_product_info(&$product_id,&$db)
{
    global $product;

    if (is_array($product_id)) {
       global $display;
       $attributes = $product_id;
       $info = $display->return_info($attributes);
       $product_id = $info['id'];
    }
    if (isset($product) && $product) {
       $db = $product->db;
       if (! $product_id) $product_id = $product->id;
    }
    else {
       $db = new DB;
       if (! $product_id) $product_id = null;
    }
    if (! validate_product_id($product_id)) return false;
    return true;
}

function average_rating($product_id=null)
{
    $attributes = $product_id;
    if (! get_product_info($product_id,$db)) return 0.0;
    if ($product_id)
       $query = 'select AVG(rating) as average from reviews ' .
                'where (status=1) and (parent='.$product_id.')';
    else $query = 'select AVG(rating) as average from reviews';
    $row = $db->get_record($query);
    $decimals = 1;
    if(is_array($attributes) && array_key_exists('decimals',$attributes)){
      $decimals = $attributes['decimals']*1;
    }
    if ($row) return number_format($row['average'],$decimals);
    return 0.0;
}

function rating_weight($product_id=null)
{
    $average = average_rating($product_id);
    if ($average == 0.0) return 0.0;
    return ($average / 5) * 100;
}

function num_reviews($product_id=null)
{
    if (! get_product_info($product_id,$db)) return 0;
    if ($product_id)
       $query = 'select count(id) as num_reviews from reviews where ' .
                '(status=1) and (parent='.$product_id.')';
    else $query = 'select count(id) as num_reviews from reviews';
    $row = $db->get_record($query);
    if ($row) return $row['num_reviews'];
    return 0;
}
global $reviews_list;
if(!isset($reviews_list)) $reviews_list = array();
function get_current_review($attributes)
{
  global $display,$reviews_list;
  $info = $display->return_info($attributes);
  if(array_key_exists($info['id'],$reviews_list)){
    return current($reviews_list[$info['id']]);
  }
  return null;
}
function advance_reviews($attributes)
{
  global $display,$reviews_list;
  $info = $display->return_info($attributes);
  if(array_key_exists($info['id'],$reviews_list)){
    next($reviews_list[$info['id']]);
  }
}
function if_reviews($attributes=array()){
  global $display,$reviews_list;
  $info = $display->return_info($attributes);
  $id = $info['id'];
  if(!array_key_exists($id,$reviews_list)){
    $order = 1;
    if(array_key_exists('order',$attributes)){
      $order = $attributes['order'];
    }
    $reviews_list[$id] = load_reviews($display->db,$id,$order);
    if(!$reviews_list[$id]){
      $reviews_list[$id] = array();
    }
  }
  $rtn = '';
  if(count($reviews_list[$id])>0){
    $rtn = $attributes['template'];
    $display->elsecondition = false;
  }else{
    $display->elsecondition = true;
  }
  return $rtn;
}
function reviews_list($attributes=array())
{
  global $display,$reviews_list;
  $info = $display->return_info($attributes);
  $id = $info['id'];
  if(!array_key_exists($id,$reviews_list)){
    $order = 1;
    if(array_key_exists('order',$attributes)){
      $order = $attributes['order'];
    }
    $reviews_list[$id] = load_reviews($display->db,$id,$order);
    if(!$reviews_list[$id]){
      $reviews_list[$id] = array();
    }
  }
  $rtn = '';
  foreach($reviews_list[$id] as $index => $row){
    $rtn .= $attributes['template'].'{$advance_reviews}';
  }
  reset($reviews_list[$id]);
  return $rtn;
}
function review_firstname($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    $rtn .= $review['firstname'];
  }
  return $rtn;
}
function review_lastname($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    $rtn .= $review['lastname'];
  }
  return $rtn;
}
function review_rating($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    if(!array_key_exists('percentage',$attributes) || $attributes['percentage']!='false'){
      $rtn .= ($review['rating']/5*100);
    }else{
      $rtn .= $review['rating'];
    }
  }
  return $rtn;
}
function review_date($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    $format = 'm/d/Y';
    if(array_key_exists('format',$attributes) && $attributes['format']!=''){
      $format = $attributes['format'];
    }
    $rtn .= date($format,$review['create_date']);
  }
  return $rtn;
}
function review_text($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    $rtn .= $review['review'];
    $rtn = '<p>'.str_replace("\n","</p>\n<p>",$rtn).'</p>';
  }
  return $rtn;
}
function review_subject($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    $rtn .= $review['subject'];
  }
  return $rtn;
}
function review_email($attributes=array())
{
  $review = get_current_review($attributes);
  $rtn = '';
  if($review){
    $rtn .= $review['email'];
  }
  return $rtn;
}
function load_reviews($db,$product_id,$sort_order)
{
    $query = 'select * from reviews where (status=1)';
    if ($product_id) $query .= ' and (parent='.$product_id.')';
    $query .= ' order by ';
    switch ($sort_order) {
       case 1: $query .= 'create_date desc';   break;
       case 2: $query .= 'create_date asc';   break;
       case 3: $query .= 'rating desc,create_date desc';   break;
       case 4: $query .= 'rating asc,create_date desc';   break;
    }
    $reviews = $db->get_records($query,'id');
    return $reviews;
}

function format_review_html($reviews)
{
    $html = '';
    foreach ($reviews as $review) {
       $rating_width = ($review['rating'] / 5) * 100;
       $html .= '<div class="review">
  <div class="review-title">
    <div class="review-stars">
      <div class="review-stars-amount" style="width:'.$rating_width.'%;"></div>
      <div class="review-stars-mask"></div>
    </div>
    <h6>'.$review['subject'].'</h6>
    <div class="review-date">'.date("F j, Y",$review['create_date']).'</div>
    <div class="clear"><!-- --></div>
  </div>
  <div class="review-author"><span>Reviewer:</span>&nbsp;'.$review['firstname'].'</div>
  <div class="review-content">
    '.str_replace('<p></p>','','<p>'.str_replace("\n",'</p><p>',str_replace("\r",'',($review['review']).'</p>'))).'
  </div>
</div>'."\n";
    }
    return $html;
}

function reviews($product_id=null)
{
    if (! get_product_info($product_id,$db)) return null;
    $reviews = load_reviews($db,$product_id,1);
    if ($reviews) return format_review_html($reviews);
    else return null;
}

function review_count($attributes=array())
{
    global $display;

    $rtn = '';
    $num_reviews = num_reviews();
    if (isset($attributes['template'])) {
       if ($num_reviews) {
          $rtn .= $attributes['template'];
          $display->elsecondition = false;
       }
       else $display->elsecondition = true;
    }
    return $rtn;
}

function get_reviews()
{
    header("Cache-Control: no-cache");
    header("Expires: -1441");
    header("Content-type: text/html");

    $db = new DB;
    $product_id = get_form_field("id");
    if ($product_id && (! validate_product_id($product_id))) return;
    $sort_order = get_form_field("sort");
    if (! $sort_order)  $sort_order = 1;
    $reviews = load_reviews($db,$product_id,$sort_order);
    if ($reviews) print format_review_html($reviews);
    $db->close();
}

function review_record_definition()
{
    $review_record = array();
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

function add_review()
{
    $db = new DB;
    $review_record = review_record_definition();
    $db->parse_form_fields($review_record);
    $review_record['status']['value'] = (string) 0;
    $review_record['create_date']['value'] = time();
    if (! $db->insert('reviews',$review_record)) {
       print 'Database Error: '.$db->error;   return;
    }
    $review_id = $db->insert_id();
    if (defined('NEW_REVIEW_ADMIN_EMAIL') || defined('NEW_REVIEW_CUST_EMAIL')) {
       require_once '../engine/email.php';
       $review_record['id']['value'] = $review_id;
       $review = $db->convert_record_to_array($review_record);
       if (defined('NEW_REVIEW_ADMIN_EMAIL')) {
          $email = new Email(NEW_REVIEW_ADMIN_EMAIL,
                             array('review' => 'obj','review_obj' => $review));
          if (! $email->send()) {
             log_error($email->error);   print 'E-Mail Error: '.$email->error;
             return;
          }
       }
       if (defined('NEW_REVIEW_CUST_EMAIL')) {
          $email = new Email(NEW_REVIEW_CUST_EMAIL,
                             array('review' => 'obj','review_obj' => $review));
          if (! $email->send()) {
             log_error($email->error);   print 'E-Mail Error: '.$email->error;
             return;
          }
       }
    }
    print 'Success';
    $log_string = 'Added Review #'.$review_id.' to Product #' .
                  $review_record['parent']['value'];
    log_activity($log_string);
    $db->close();
}

global $global_keys,$global_loops;
if(!isset($global_keys)){
  $global_keys = array();
}
if(!isset($global_loops)){
  $global_loops = array();
}

array_push($global_keys,'reviews','rating_weight','num_reviews','review_firstname','review_lastname','review_rating','review_text','review_date',
           'review_subject','review_email','advance_reviews','average_rating');
array_push($global_loops,'review_count','reviews_list','if_reviews');

$jscmd = get_form_field("jscmd");
if ($jscmd == "reviews") get_reviews();
else if ($jscmd == "addreview") add_review();

?>
