<?php
/*
     Inroads Control Panel/Shopping Cart - CMS Missing File Lookup Processing

                        Written 2010-2019 by Randall Severy
                         Copyright 2010-2019 Inroads, LLC
*/

require_once '../admin/config.php';

function log_activity($activity_msg)
{
    global $activity_log;

    $activity_file = @fopen('lookup.log','at');
    if ($activity_file) {
       fwrite($activity_file,'['.date('D M d Y H:i:s').'] '.$activity_msg."\n");
       fclose($activity_file);
    }
}

function check_rewrite_condition($data,$filename)
{
    if (substr($data[2],0,1) == '!') {
       $nomatch_return = false;   $data[2] = substr($data[2],1);
    }
    else $nomatch_return = true;
    $compare = str_replace('~',"\\~",$data[2]);
    $compare = '~'.$compare.'~';
    switch ($data[1]) {
       case '%{HTTP_HOST}':
          preg_match($compare,$_SERVER['HTTP_HOST'],$matches);
          if (count($matches) == 0) return $nomatch_return;
          break;
       case '%{SERVER_PORT}':
          preg_match($compare,$_SERVER['SERVER_PORT'],$matches);
          if (count($matches) == 0) return $nomatch_return;
          break;
       case '%{QUERY_STRING}':
          preg_match($compare,$_SERVER['QUERY_STRING'],$matches);
          if (count($matches) == 0) return $nomatch_return;
          break;
       case '%{REQUEST_URI}':
       case '%{THE_REQUEST}':
          preg_match($compare,$filename,$matches);
          if (count($matches) == 0) return $nomatch_return;
          break;
    }
    return false;
}

function lookup_multisite_filename($filename)
{
    global $multisite_prefix;

    require '../engine/db.php';

    $db = new DB;
    $query = 'select * from web_sites';
    $result = $db->query($query);
    $multisite_prefix = '';
    if (! $result) return $filename;
    while ($row = $db->fetch_assoc($result)) {
       $rootdir = $row['rootdir'];
       if ($rootdir == '/') continue;
       $length = strlen($rootdir);
       if (substr($filename,0,$length) == $rootdir) {
          $filename = substr($filename,$length - 1);
          $multisite_prefix = substr($rootdir,0,-1);   break;
       }
    }
    $db->free_result($result);
    return $filename;
}

function lookup_filename($filename)
{
    global $multisite_prefix;

    $filename = str_replace("\\",'/',$filename);
    if ($filename[0] == '/') $filename = substr($filename,1);
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (substr($script_name,0,2) == '/~') {
       $slash_pos = strpos($script_name,'/',2);
       if ($slash_pos !== false) $script_name = substr($script_name,$slash_pos);
    }
    $script_filename = $_SERVER['SCRIPT_FILENAME'];
    $htaccess_filename = substr($script_filename,0,-strlen($script_name)) .
                         $multisite_prefix.'/.htaccess';
    $htaccess_file = @fopen($htaccess_filename,'r');
    if (! $htaccess_file) return null;
    $skip_next_rewrite = false;
    while (! feof($htaccess_file)) {
       $buffer = fgets($htaccess_file);
       $data = explode(' ',$buffer);
       if ($data[0] == 'RewriteCond') {
          if (! $skip_next_rewrite)
             $skip_next_rewrite = check_rewrite_condition($data,$filename);
          continue;
       }
       if ($data[0] != 'RewriteRule') continue;
       if ($data[1] == '^(.*)$') continue;
       if ($skip_next_rewrite) {
          $skip_next_rewrite = false;   continue;
       }
       preg_match('~'.$data[1].'~',$filename,$matches);
       if (count($matches) > 0) {
          $filename = $data[2];
          if (strtolower(substr($filename,0,4)) == 'http') {
             fclose($htaccess_file);   return null;
          }
          if ($filename[0] != '/') $filename = '/'.$filename;
          $num_matches = count($matches);
          for ($loop = 1;  $loop < $num_matches;  $loop++)
             $filename = str_replace('$'.$loop,$matches[$loop],$filename);
          fclose($htaccess_file);
          if ($multisite_prefix) $filename = $multisite_prefix.$filename;
          return $filename;
       }
    }
    fclose($htaccess_file);
    return null;
}

if (! isset($enable_multisite)) $enable_multisite = false;
$multisite_prefix = '';
$filename = $_GET['Filename'];
if ($enable_multisite) $filename = lookup_multisite_filename($filename);
$filename = lookup_filename($filename);
if ($filename) print trim($filename);

?>
