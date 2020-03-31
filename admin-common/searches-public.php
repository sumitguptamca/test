<?php
/*
               Inroads Shopping Cart - Searches Tab Common Functions

                        Written 2014-2015 by Randall Severy
                         Copyright 2014-2015 Inroads, LLC
*/

function search_record_definition()
{
    $search_record = array();
    $search_record['id'] = array('type' => INT_TYPE);
    $search_record['id']['key'] = true;
    $search_record['query'] = array('type' => CHAR_TYPE);
    $search_record['ip_address'] = array('type' => CHAR_TYPE);
    $search_record['search_date'] = array('type' => INT_TYPE);
    return $search_record;
}

function add_search_record($query,$db,&$error_msg)
{
    $search_record = search_record_definition();
    $search_record['query']['value'] = $query;
    $search_record['ip_address']['value'] = $_SERVER['REMOTE_ADDR'];
    $search_record['search_date']['value'] = time();
    if (! $db->insert('searches',$search_record)) {
       $error_msg = $db->error;   return false;
    }
    log_activity('Added Search "'.$query.'" (#'.$db->insert_id().')');
    return true;
}

function search_suggest_record_definition()
{
  $search_record = array();
  $search_record['id'] = array('type' => INT_TYPE);
  $search_record['id']['key'] = true;
  $search_record['keyword'] = array('type'=> CHAR_TYPE);
  $search_record['trigrams'] = array('type'=> CHAR_TYPE);
  $search_record['freq'] = array('type'=> INT_TYPE);
  return $search_record;
}
function buildTrigrams($keyword){
  $t = "__" . $keyword . "__";
  $trigrams = "";
  for ( $i=0; $i<strlen($t)-2; $i++ ){
    $trigrams .= substr ( $t, $i, 3 ) . " ";
  }
  return $trigrams;
}
function import_search_suggest($folder=null,$db=false,$error_message=false){
  if(!$db) $db = new DB;
  $db->enable_log_query(false);
  if(!$folder){
    if(!$folder=get_form_field('folder')){
      global $docroot;
      if(!isset($docroot)){
        log_error('Cannot import Search Suggest records: no root folder.');
        return false;
      }
      $folder = str_replace('/public_html','/',$docroot);
    }
  }
  if(!file_exists($folder)){
    log_error('Cannot import Search Suggest records: root folder: '.$folder.' doesn\'t exist');
    return false;
  }
  if(!file_exists($folder.'/sphinx/stopwords.txt')){
    log_error('Cannot import Search Suggest records: input file: '.$folder.'/sphinx/stopwords.txt doesn\'t exist');
    return false;
  }
  $words = file_get_contents($folder.'/sphinx/stopwords.txt');
  $words_array = explode("\n",$words);
  foreach($words_array as $index => $word){
    if($word == '') continue;
    $wordArray = explode(' ',$word);
    $word = $wordArray[0];
    $freq = $wordArray[1];
    if(strlen($word)>=3){
      $checkWordResults = $db->get_record('select * from search_suggest where keyword = "'.$word.'"');
      $suggest_record = search_suggest_record_definition();
      if($checkWordResults){
        foreach($checkWordResults as $index => $value){
          $suggest_record[$index]['value'] = $value;
        }
      }else{
        $suggest_record['keyword']['value'] = $word;
        $suggest_record['trigrams']['value'] = buildTrigrams($word);
      }
      $suggest_record['freq']['value'] = $freq;
      if(array_key_exists('value',$suggest_record['id'])){
        if (! $db->update('search_suggest',$suggest_record)){
           $error_msg = $db->error;   return false;
        }
      }else{
        if (! $db->insert('search_suggest',$suggest_record)){
           $error_msg = $db->error;   return false;
        }
      }
//      log_activity('Added SearchSuggest "'.$word.'" (#'.$db->insert_id().')');
    }
  }
  log_activity('Imported SearchSuggestions');
  return true;
}

function load_synonyms($keywords,$db=false){
  if(!isset($db)) $db = new DB;
  $keywords_ar = explode(' ',$keywords);
  $keywords_list = '"'.implode('","',$keywords_ar).'","'.$keywords.'"';
  return $db->get_records('select synonym from search_synonyms where keyword in ('.$keywords_list.')');
}

?>
