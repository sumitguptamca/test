<?php
/*
                      Inroads Shopping Cart - SEO Functions

                       Written 2008-2019 by Randall Severy
                        Copyright 2008-2019 Inroads, LLC
*/

function build_htaccess_filename($directory)
{
    if (($directory == '/') || empty($directory))
       $htaccess_filename = '../.htaccess';
    else if ($directory[0] == '/')
       $htaccess_filename = $directory.'.htaccess';
    else $htaccess_filename = '../'.$directory.'.htaccess';
    return $htaccess_filename;
}

function update_htaccess($type,$id,$seo_url,$websites,$db,$old_id=null)
{
    global $display_category_page,$display_product_page,$cache_catalog_pages;
    global $enable_multisite,$category_seo_prefix,$product_seo_prefix;

    if (! isset($display_category_page))
       $display_category_page = 'display-category.php';
    if (! isset($display_product_page))
       $display_product_page = 'display-product.php';
    if (! isset($cache_catalog_pages)) $cache_catalog_pages = false;
    if (! isset($category_seo_prefix)) $category_seo_prefix = 'Category';
    if (! isset($product_seo_prefix)) $product_seo_prefix = 'Product';

    if ($type == 0) {
       $page_name = $display_category_page;
       $comment_line = '#'.$category_seo_prefix.'-'.$id;
       if ($old_id) $find_comment_line = '#'.$category_seo_prefix.'-'.$old_id;
       else $find_comment_line = $comment_line;
    }
    else {
       $page_name = $display_product_page;
       $comment_line = '#'.$product_seo_prefix.'-'.$id;
       if ($old_id) $find_comment_line = '#'.$product_seo_prefix.'-'.$old_id;
       else $find_comment_line = $comment_line;
     }

    if (empty($enable_multisite)) $website_array = array('/');
    else {
       $website_array = array();
       if (empty($seo_url) || empty($websites))
          $query = 'select id,rootdir from web_sites order by id';
       else {
          $query = 'select id,rootdir from web_sites where id in (?) ' .
                   'order by id';
          $query = $db->prepare_query($query,explode(',',$websites));
       }
       $website_array = $db->get_records($query,'id','rootdir');
       if (! $website_array) {
          if (isset($db->error)) return;
          else $website_array = array('/');
       }
    }

    foreach ($website_array as $website_id => $directory) {
       $htaccess_filename = build_htaccess_filename($directory);
       $comment_length = strlen($find_comment_line);
       $new_entry = true;

       $file_content = @file($htaccess_filename);
       if (! $file_content) {
          process_error('Unable to open '.$htaccess_filename,-1);   return;
       }

       for ($index = 0;  $index < sizeof($file_content);  $index++)
          if (! strncmp($file_content[$index],$find_comment_line,
                        $comment_length)) {
             $new_entry = false;   break;
          }

       if ((! $seo_url) || ($seo_url == '')) {
          if ($new_entry) return;
          $file_content[$index] = '';
          $file_content[$index + 1] = '';
          if ($type == 0) $file_content[$index + 2] = '';
       }
       else {
          if ($new_entry) $file_content[$index++] = "\n";
          $file_content[$index] = $comment_line."\n";
          if ($cache_catalog_pages) {
             if ($type == 0) {
                $file_content[$index + 1] = 'RewriteRule ^'.$seo_url .
                   '/$ cache/'.$id."-category.html [QSA,L]\n";
                $file_content[$index + 2] = 'RewriteRule ^'.$seo_url .
                   '/(.*)/$ cache/'.$id."-$1.html [QSA,L]\n";
             }
             else {
                $cache_url = 'cache/'.$seo_url.'.html';
                $file_content[$index + 1] = 'RewriteRule ^'.$seo_url.'/$ ' .
                   $cache_url." [QSA,L]\n";
             }
          }
          else {
             $file_content[$index + 1] = 'RewriteRule ^'.$seo_url.'/$ ' .
                $page_name.'?id='.$id." [QSA,L]\n";
             if ($type == 0)
                $file_content[$index + 2] = 'RewriteRule ^'.$seo_url.'/(.*)/$ ' .
                   $page_name.'?id='.$id."&product=$1 [QSA,L]\n";
          }
       }

       $htaccess_file = fopen($htaccess_filename,'w');
       if (! $htaccess_file) {
          process_error('Unable to open .htaccess file',-1);   return;
       }
       if (! fwrite($htaccess_file,implode('',$file_content))) {
          process_error('Unable to update .htaccess file',-1);   return;
       }
       fclose($htaccess_file);
    }
}

function build_redirect_filename($directory)
{
    if (($directory == '/') || empty($directory))
       $redirect_filename = '../redirect.conf';
    else if ($directory[0] == '/')
       $redirect_filename = $directory.'redirect.conf';
    else $redirect_filename = '../'.$directory.'redirect.conf';
    if (! file_exists($redirect_filename)) {
       if (($redirect_filename == '../redirect.conf') &&
           file_exists('redirect.conf')) $redirect_filename = 'redirect.conf';
       else $redirect_filename = null;
    }
    return $redirect_filename;
}

function get_redirect_websites($websites,$db)
{
    global $enable_multisite;

    if (empty($enable_multisite)) $website_array = array('/');
    else {
       $website_array = array();
       if (empty($websites))
          $query = 'select id,rootdir from web_sites order by id';
       else {
          $query = 'select id,rootdir from web_sites where id in (?) ' .
                   'order by id';
          $query = $db->prepare_query($query,explode(',',$websites));
       }
       $website_array = $db->get_records($query,'id','rootdir');
       if (! $website_array) {
          if (isset($db->error)) return;
          else $website_array = array('/');
       }
    }
    return $website_array;
}

function update_redirect($type,$id,$seo_url,$websites,$db)
{
    $website_array = get_redirect_websites($websites,$db);
    foreach ($website_array as $website_id => $directory) {
       $redirect_filename = build_redirect_filename($directory);
       if (! $redirect_filename) continue;
       $redirect_file = fopen($redirect_filename,'a');
       if (! $redirect_file) {
          process_error('Unable to open '.$redirect_filename,-1);   continue;
       }
       if ($type == 0) fwrite($redirect_file,'C:'.$seo_url.' '.$id."\n");
       else fwrite($redirect_file,'P:'.$seo_url.' '.$id."\n");
       fclose($redirect_file);
    }
}

function lookup_redirect($type,$seo_url,$websites,$db)
{
    $website_array = get_redirect_websites($websites,$db);
    foreach ($website_array as $website_id => $directory) {
       $redirect_filename = build_redirect_filename($directory);
       if (! $redirect_filename) continue;
       $redirect_content = file($redirect_filename,FILE_IGNORE_NEW_LINES);
       if (! $redirect_content) {
          log_error('Unable to open redirect.conf file');   continue;
       }
       for ($index = 0;  $index < sizeof($redirect_content);  $index++) {
          $redirect_line = $redirect_content[$index];
          if ($type == 0) {
             if (substr($redirect_line,0,2) != 'C:') continue;
          }
          else if (substr($redirect_line,0,2) != 'P:') continue;
          $redirect_info = explode(' ',substr($redirect_line,2));
          if (count($redirect_info) != 2) continue;
          if ($redirect_info[0] == $seo_url) return trim($redirect_info[1]);
       }
    }
    return null;
}

function seo_url_exists($db,$seo_url,&$location,&$error,$websites,
                        $skip_cat_id=null,$skip_prod_id=null)
{
    global $enable_multisite,$products_table,$categories_table;
    global $category_seo_prefix,$product_seo_prefix,$category_seo_name_field;
    global $product_seo_name_field;

    $error = null;
    if (! isset($category_seo_prefix)) $category_seo_prefix = 'Category';
    if (! isset($product_seo_prefix)) $product_seo_prefix = 'Product';
    if (! isset($category_seo_name_field)) $category_seo_name_field = 'name';
    if (! isset($product_seo_name_field)) $product_seo_name_field = 'name';
    if ((! empty($enable_multisite)) && $websites) {
       $website_array = explode(',',$websites);
       $multisite_query = ' and (';
       foreach ($website_array as $index => $website_id) {
          if ($index > 0) $multisite_query .= ' or ';
          $multisite_query .= "find_in_set('".$website_id."',websites)";
       }
       $multisite_query .= ')';
    }
    else $multisite_query = null;
    $query = 'select id,'.$category_seo_name_field.' from '.$categories_table .
             " where (seo_url='".$seo_url."')";
    if ($multisite_query) $query .= $multisite_query;
    if ($skip_cat_id) $query .= ' and (id!='.$skip_cat_id.')';
    $records = $db->get_records($query);
    if ($records && (count($records) > 0)) {
       $location = $category_seo_prefix.' '.$records[0]['name'] .
                   ' (#'.$records[0]['id'].')';
       return true;
    }
    else if (isset($db->error)) {
       $error = $db->error;   return true;
    }

    $query = 'select id,'.$product_seo_name_field.' from '.$products_table .
             " where seo_url='".$seo_url."'";
    if ($skip_prod_id) $query .= ' and (id!='.$skip_prod_id.')';
    if ($multisite_query) $query .= $multisite_query;
    $records = $db->get_records($query);
    if ($records && (count($records) > 0)) {
       $location = $product_seo_prefix.' '.$records[0]['name'] .
                   ' (#'.$records[0]['id'].')';
       return true;
    }
    else if (isset($db->error)) {
       $error = $db->error;   return true;
    }
    return false;
}

function validate_seo_url($db,$seo_url,$websites,&$error_code,&$error,
                          $skip_cat_id=null,$skip_prod_id=null)
{
    global $db_charset;

    if (isset($db_charset) && ($db_charset == 'utf8'))
       $valid_string = '/^[\p{L}\p{N}-.*]+$/u';
    else $valid_string = '/^[a-zA-Z0-9-.*]+$/';
    if (preg_match($valid_string,$seo_url) === 0) {
       $error = 'Invalid Characters in URL Alias '.$seo_url;
       $error_code = 406;   return false;
    }
    if (is_numeric($seo_url)) {
       $error = 'URL Alias can not be a number: '.$seo_url;
       $error_code = 406;   return false;
    }
    if (seo_url_exists($db,$seo_url,$location,$error,$websites,$skip_cat_id,
                       $skip_prod_id)) {
       if ($error) $error_code = 422;
       else {
          $error = 'That URL Alias ('.$seo_url.') is already in use by ' .
                   $location;
          $error_code = 409;
       }
       return false;
    }
    return true;
}

function build_website_array($db,$ajax)
{
    global $docroot;

    $website_id = get_form_field('website');
    if ($website_id !== null) {
       $query = 'select rootdir from web_sites where id='.$website_id;
       $row = $db->get_record($query);
       if (! $row) {
          if ($ajax) http_response(422,$db->error);
          else {
             print 'Query: '.$query."<br>\n";
             print 'Database Error: '.$db->error."<br>\n";
          }
          return null;
       }
       $website_array = array();
       $website_array[$website_id] = $row['rootdir'];
    }
    else {
       $query = 'select id,rootdir from web_sites order by id';
       $website_array = $db->get_records($query,'id','rootdir');
       if ((! $website_array) && isset($db->error)) {
          if ($ajax) http_response(422,$db->error);
          else {
             print 'Query: '.$query."<br>\n";
             print 'Database Error: '.$db->error."<br>\n";
          }
          return null;
       }
    }
    foreach ($website_array as $website_id => $rootdir) {
       if (($rootdir != '/') && (! file_exists($rootdir)) &&
           file_exists($docroot.$rootdir))
          $website_array[$website_id] = $docroot.$rootdir;
    }
    return $website_array;
}

function reset_htaccess($ajax=false,$silent=false)
{
    global $enable_multisite;

    if (isset($_SERVER['SERVER_SOFTWARE'])) $html_output = true;
    else $html_output = false;
    if (! empty($enable_multisite)) {
       $db = new DB;
       $website_array = build_website_array($db,$ajax);
       if (! $website_array) return false;
    }
    else $website_array = array('/');

    foreach ($website_array as $website_id => $directory) {
       if ((! $ajax) && (! $silent)) {
          print 'Resetting '.$directory.".htaccess file\n";
          if ($html_output) print "<br>\n";
       }
       $htaccess_filename = build_htaccess_filename($directory);
       $file_content = file($htaccess_filename);
       if (! $file_content) {
          $error_msg = 'Unable to open '.$htaccess_filename;
          if ($silent) log_error($error_msg);
          else if ($ajax) http_response(422,$error_msg);
          else print $error_msg."\n";
          return false;
       }
       $new_file_content = array();   $num_lines = sizeof($file_content);
       for ($index = 0;  $index < $num_lines;  $index++) {
          if (! strncmp($file_content[$index],'#Category-',10)) {
             if (trim($new_file_content[$index - 1]) == '')
                unset($new_file_content[$index - 1]);
             else unset($new_file_content[$index]);
             break;
          }
          else $new_file_content[] = $file_content[$index];
       }
       $htaccess_file = fopen($htaccess_filename,'w');
       if (! $htaccess_file) {
          $error_msg = 'Unable to open '.$htaccess_filename;
          if ($silent) log_error($error_msg);
          else if ($ajax) http_response(422,$error_msg);
          else print $error_msg."\n";
          return false;
       }
       if (! fwrite($htaccess_file,implode('',$new_file_content))) {
          $error_msg = 'Unable to update '.$htaccess_filename;
          if ($silent) log_error($error_msg);
          else if ($ajax) http_response(422,$error_msg);
          else print $error_msg."\n";
          return false;
       }
       fclose($htaccess_file);
       if ($ajax || $silent)
          log_activity('Reset '.$directory.'.htaccess file');
       else {
          print 'Reset of '.$directory.".htaccess file complete\n";
          if ($html_output) print "<br>\n";
       }
    }
    return true;
}

function rebuild_htaccess($ajax=false,$silent=false)
{
    global $display_category_page,$display_product_page,$cache_catalog_pages;
    global $enable_multisite,$category_seo_prefix,$product_seo_prefix;
    global $db_charset,$categories_table,$products_table;

    if (isset($_SERVER['SERVER_SOFTWARE'])) $html_output = true;
    else $html_output = false;
    if (! isset($display_category_page))
       $display_category_page = 'display-category.php';
    if (! isset($display_product_page))
       $display_product_page = 'display-product.php';
    if (! isset($cache_catalog_pages)) $cache_catalog_pages = false;
    if (! isset($category_seo_prefix)) $category_seo_prefix = 'Category';
    if (! isset($product_seo_prefix)) $product_seo_prefix = 'Product';
    if (! isset($categories_table)) $categories_table = 'categories';
    if (! isset($products_table)) $products_table = 'products';

    $db = new DB;

    if (empty($enable_multisite)) $website_array = array('/');
    else {
       $website_array = build_website_array($db,$ajax);
       if (! $website_array) return false;
    }

    foreach ($website_array as $website_id => $directory) {
       if ((! $ajax) && (! $silent)) {
          print 'Rebuilding '.$directory.".htaccess file\n";
          if ($html_output) print "<br>\n";
       }
       $query = 'select id,seo_url from '.$categories_table;
       if (! empty($enable_multisite))
          $query .= " where find_in_set('".$website_id."',websites)";
       $query .= ' order by id';
       $categories = $db->get_records($query);
       if ((! $categories) && isset($db->error)) {
          if ($ajax) http_response(422,$db->error);
          else if (! $silent) {
             print '   Query: '.$query."<br>\n";
             print '   Database Error: '.$db->error."<br>\n";
          }
          return false;
       }

       $query = 'select id,seo_url';
       if (! empty($enable_multisite)) $query .= ',websites';
       $query .= ' from '.$products_table.' where (flags & 9)';
       if (! empty($enable_multisite))
          $query .= " and find_in_set('".$website_id."',websites)";
       $query .= ' order by id';
       $products = $db->get_records($query);
       if ((! $products) && isset($db->error)) {
          if ($ajax) http_response(422,$db->error);
          else if (! $silent) {
             print '   Query: '.$query."<br>\n";
             print '   Database Error: '.$db->error."<br>\n";
          }
          return false;
       }

       $htaccess_filename = build_htaccess_filename($directory);
       $htaccess_file = fopen($htaccess_filename,'a');
       if (! $htaccess_file) {
          $error_msg = 'Unable to open '.$htaccess_filename;
          if ($silent) log_error($error_msg);
          else if ($ajax) http_response(422,$error_msg);
          else print $error_msg."\n";
          return false;
       }
       $page_name = $display_category_page;
       foreach ($categories as $row) {
          $id = $row['id'];   $seo_url = $row['seo_url'];
          if ((! $seo_url) || ($seo_url == '')) $seo_url = $id;
          if (isset($db_charset) && ($db_charset == 'utf8')) {
             if (preg_match('/^[\p{L}\p{N}-.*]+$/u',$seo_url) === 0) continue;
          }
          else if (preg_match('/^[a-zA-Z0-9-.*]+$/',$seo_url) === 0) continue;
          fwrite($htaccess_file,"\n#".$category_seo_prefix.'-'.$id."\n");
          if ($cache_catalog_pages) {
             fwrite($htaccess_file,'RewriteRule ^'.$seo_url.'/$ cache/'.$id .
                    "-category.html [QSA,L]\n");
             fwrite($htaccess_file,'RewriteRule ^'.$seo_url.'/(.*)/$ cache/' .
                    $id."-$1.html [QSA,L]\n");
          }
          else {
             fwrite($htaccess_file,'RewriteRule ^'.$seo_url.'/$ '.$page_name .
                    '?id='.$id." [QSA,L]\n");
             fwrite($htaccess_file,'RewriteRule ^'.$seo_url.'/(.*)/$ ' .
                    $page_name.'?id='.$id."&product=$1 [QSA,L]\n");
          }
       }
       $page_name = $display_product_page;
       foreach ($products as $row) {
          $id = $row['id'];   $seo_url = $row['seo_url'];
          if ((! $seo_url) || ($seo_url == '')) $seo_url = 'products/'.$id;
          if (isset($db_charset) && ($db_charset == 'utf8')) {
             if (preg_match('/^[\p{L}\p{N}-.*]+$/u',$seo_url) === 0) continue;
          }
          else if (preg_match('/^[a-zA-Z0-9-.*]+$/',$seo_url) === 0) continue;
          fwrite($htaccess_file,"\n#".$product_seo_prefix.'-'.$id."\n");
          if ($cache_catalog_pages) {
             $cache_url = $row['seo_url'];
             if ((! $cache_url) || ($cache_url == '')) $cache_url = $id;
             $cache_url = 'cache/'.$cache_url.'.html';
             fwrite($htaccess_file,'RewriteRule ^'.$seo_url.'/$ '.$cache_url .
                    " [QSA,L]\n");
          }
          else fwrite($htaccess_file,'RewriteRule ^'.$seo_url.'/$ '.$page_name .
                      '?id='.$id." [QSA,L]\n");
       }
       fclose($htaccess_file);
       if ($ajax || $silent)
          log_activity('Rebuilt '.$directory.'.htaccess file');
       else {
          print 'Rebuild of '.$directory.".htaccess file complete\n";
          if ($html_output) print "<br>\n";
       }
    }
    return true;
}

function create_default_seo_url_name($item_name)
{
    global $lowercase_default_seo_urls,$db_charset;

    if (! isset($lowercase_default_seo_urls))
       $lowercase_default_seo_urls = false;
    $seo_url = preg_replace('/<(.*?)>/s','',trim($item_name));
    if (isset($db_charset) && ($db_charset == 'utf8'))
       $seo_url = preg_replace('/[^\p{L}\p{N}-]/u','',$seo_url);
    else $seo_url = preg_replace('/[^a-zA-Z0-9-]/','',$seo_url);
    $seo_url = str_replace(' ','-',$seo_url);
    $seo_url = preg_replace('/-{2,}/','-',$seo_url);
    if (is_numeric($seo_url)) $seo_url = 'P-'.$seo_url;
    if ($lowercase_default_seo_urls) $seo_url = strtolower($seo_url);

    return $seo_url;
}

function create_default_seo_url($db,$item_name,$websites)
{
    $seo_url = create_default_seo_url_name($item_name);
    if ($seo_url) {
       $base_seo_url = $seo_url;   $sequence = 2;
       while (seo_url_exists($db,$seo_url,$location,$error,$websites)) {
          if ($error) {
             log_error($error);   return null;
          }
          $seo_url = $base_seo_url.'-'.$sequence;
          $sequence++;
       }
    }
    return $seo_url;
}

function initialize_seo_urls($ajax=false)
{
    global $categories_table,$products_table;

    $db = new DB;

    set_time_limit(0);
    ignore_user_abort(true);
    if (! isset($categories_table)) $categories_table = 'categories';
    if (! isset($products_table)) $products_table = 'products';
    $lower_seo_urls = array();
    $query = 'select seo_url from categories where not isnull(seo_url) ' .
             'and seo_url!=""';
    $category_seo_urls = $db->get_records($query);
    if ((! $category_seo_urls) && isset($db->error)) {
       if ($ajax) http_response(422,$db->error);
       else {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       return false;
    }
    foreach ($category_seo_urls as $row)
       $lower_seo_urls[] = strtolower($row['seo_url']);
    $query = 'select seo_url from '.$products_table .
             ' where (not isnull(seo_url)) and (seo_url!="")';
    $product_seo_urls = $db->get_records($query);
    if ((! $product_seo_urls) && isset($db->error)) {
       if ($ajax) http_response(422,$db->error);
       else {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       return false;
    }
    foreach ($product_seo_urls as $row)
       $lower_seo_urls[] = strtolower($row['seo_url']);

    $query = 'select id,name,display_name from '.$categories_table .
             " where (seo_url='') or isnull(seo_url)";
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'seo','initialize_seo_urls',1,$db);
    $category_seo_urls = $db->get_records($query);
    if ((! $category_seo_urls) && isset($db->error)) {
       if ($ajax) http_response(422,$db->error);
       else {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       return false;
    }
    else if (count($category_seo_urls) == 0) {
       if (! $ajax) print "All Categories have SEO URLs<br>\n";
    }
    else {
       foreach ($category_seo_urls as $row) {
          $id = $row['id'];   $name = $row['name'];
          $display_name = $row['display_name'];
          if ($display_name)
             $seo_url = create_default_seo_url_name($display_name);
          else $seo_url = create_default_seo_url_name($name);
          if ($seo_url) {
             $base_seo_url = $seo_url;   $sequence = 2;
             while (in_array(strtolower($seo_url),$lower_seo_urls)) {
                $seo_url = $base_seo_url.'-'.$sequence;
                $sequence++;
             }
             $query = 'update '.$categories_table." set seo_url='".$seo_url .
                      "' where id=".$id;
             $db->log_query($query);
             if (! $db->query($query)) {
                if ($ajax) http_response(422,$db->error);
                else {
                   print 'Query: '.$query."<br>\n";
                   print 'Database Error: '.$db->error."<br>\n";
                }
                return false;
             }
             $lower_seo_urls[] = strtolower($seo_url);
          }
       }
       if ($ajax) log_activity('Updated Category SEO URLs');
       else print "Updated Category SEO URLs<br>\n";
    }

    $query = 'select id,name,display_name from '.$products_table .
             " where (seo_url='') or isnull(seo_url)";
    $product_seo_urls = $db->get_records($query);
    if ((! $product_seo_urls) && isset($db->error)) {
       if ($ajax) http_response(422,$db->error);
       else {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       return false;
    }
    else if (count($product_seo_urls) == 0) {
       if (! $ajax) print "All Products have SEO URLs<br>\n";
    }
    else {
       foreach ($product_seo_urls as $row) {
          $id = $row['id'];   $name = $row['name'];
          $display_name = $row['display_name'];
          if ($display_name)
             $seo_url = create_default_seo_url_name($display_name);
          else $seo_url = create_default_seo_url_name($name);
          if ($seo_url) {
             $base_seo_url = $seo_url;   $sequence = 2;
             while (in_array(strtolower($seo_url),$lower_seo_urls)) {
                $seo_url = $base_seo_url.'-'.$sequence;
                $sequence++;
             }
             $query = 'update '.$products_table." set seo_url='".$seo_url .
                      "' where id=".$id;
             $db->log_query($query);
             if (! $db->query($query)) {
                if ($ajax) http_response(422,$db->error);
                else {
                   print 'Query: '.$query."<br>\n";
                   print 'Database Error: '.$db->error."<br>\n";
                }
                return false;
             }
             $lower_seo_urls[] = strtolower($seo_url);
          }
       }
       if ($ajax) log_activity('Updated Product SEO URLs');
       else print "Updated Product SEO URLs<br>\n";
    }
    return true;
}

?>
