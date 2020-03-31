<?php
/*
              Inroads Control Panel/Shopping Cart - Google Translate Module

                        Written 2010-2012 by Randall Severy
                         Copyright 2010-2012 Inroads, LLC
*/

function get_form_field($field_name)
{
    global $HTTP_GET_VARS;
    global $HTTP_POST_VARS;

    $field_value = null;
    if (isset($_GET)) {
       if (isset($_GET[$field_name])) $field_value = $_GET[$field_name];
       else if (isset($_POST[$field_name]))
          $field_value = $_POST[$field_name];
       else if (isset($_GET["_".$field_name]))
          $field_value = $_GET["_".$field_name];
       else if (isset($_POST["_".$field_name]))
          $field_value = $_POST["_".$field_name];
    }
    else {
       if (isset($HTTP_GET_VARS[$field_name]))
          $field_value = $HTTP_GET_VARS[$field_name];
       else if (isset($HTTP_POST_VARS[$field_name]))
          $field_value = $HTTP_POST_VARS[$field_name];
       else if (isset($HTTP_GET_VARS["_".$field_name]))
          $field_value = $HTTP_GET_VARS["_".$field_name];
       else if (isset($HTTP_POST_VARS["_".$field_name]))
          $field_value = $HTTP_POST_VARS["_".$field_name];
    }
    if (isset($field_value) && get_magic_quotes_gpc()) {
       if (is_array($field_value)) strip_form_field_arrays($field_value);
       else $field_value = stripslashes($field_value);
    }
    return $field_value;
}

function join_chunks($str)
{
    for ($tmp = $str, $res = ''; (! empty($tmp)); $tmp = trim($tmp)) {
       if (($pos = strpos($tmp,"\r\n")) === false) return $str;
       $len = hexdec(substr($tmp,0,$pos));
       $res .= substr($tmp,$pos + 2,$len);
       $tmp = substr($tmp,$pos + 2 + $len);
    } 
    return $res;
} 

if (! function_exists("gzdecode")) {
   $internal_gzdecode = true;
   function gzdecode($data,$filename='',$error='',$maxlength=null)
   {
       global $debug_log;

       $len = strlen($data);
       if ($len < 18 || strcmp(substr($data,0,2),"\x1f\x8b")) {
           $error = "Not in GZIP format.";
           if ($debug_log) write_debug("gzdecode Error: ".$error,true);
           return null;  // Not GZIP format (See RFC 1952)
       }
       $method = ord(substr($data,2,1));  // Compression method
       $flags  = ord(substr($data,3,1));  // Flags
       if ($flags & 31 != $flags) {
           $error = "Reserved bits not allowed.";
           if ($debug_log) write_debug("gzdecode Error: ".$error,true);
           return null;
       }
       // NOTE: $mtime may be negative (PHP integer limitations)
       $mtime = unpack("V", substr($data,4,4));
       $mtime = $mtime[1];
       $xfl   = substr($data,8,1);
       $os    = substr($data,8,1);
       $headerlen = 10;
       $extralen  = 0;
       $extra     = "";
       if ($flags & 4) {
           // 2-byte length prefixed EXTRA data in header
           if ($len - $headerlen - 2 < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid Extra Data Length",true);
               return false;  // invalid
           }
           $extralen = unpack("v",substr($data,8,2));
           $extralen = $extralen[1];
           if ($len - $headerlen - 2 - $extralen < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid Extra Data",true);
               return false;  // invalid
           }
           $extra = substr($data,10,$extralen);
           $headerlen += 2 + $extralen;
       }
       $filenamelen = 0;
       $filename = "";
       if ($flags & 8) {
           // C-style string
           if ($len - $headerlen - 1 < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid Filename Length",true);
               return false; // invalid
           }
           $filenamelen = strpos(substr($data,$headerlen),chr(0));
           if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid Filename",true);
               return false; // invalid
           }
           $filename = substr($data,$headerlen,$filenamelen);
           $headerlen += $filenamelen + 1;
       }
       $commentlen = 0;
       $comment = "";
       if ($flags & 16) {
           // C-style string COMMENT data in header
           if ($len - $headerlen - 1 < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid header length",true);
               return false;    // invalid
           }
           $commentlen = strpos(substr($data,$headerlen),chr(0));
           if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid header format",true);
               return false;    // Invalid header format
           }
           $comment = substr($data,$headerlen,$commentlen);
           $headerlen += $commentlen + 1;
       }
       $headercrc = "";
       if ($flags & 2) {
           // 2-bytes (lowest order) of CRC32 on header present
           if ($len - $headerlen - 2 < 8) {
               if ($debug_log) write_debug("gzdecode Error: Invalid CRC",true);
               return false;    // invalid
           }
           $calccrc = crc32(substr($data,0,$headerlen)) & 0xffff;
           $headercrc = unpack("v", substr($data,$headerlen,2));
           $headercrc = $headercrc[1];
           if ($headercrc != $calccrc) {
               $error = "Header checksum failed.";
               if ($debug_log) write_debug("gzdecode Error: ".$error,true);
               return false;    // Bad header CRC
           }
           $headerlen += 2;
       }
       // GZIP FOOTER
       $datacrc = unpack("V",substr($data,-8,4));
       $datacrc = sprintf('%u',$datacrc[1] & 0xFFFFFFFF);
       $isize = unpack("V",substr($data,-4));
       $isize = $isize[1];
       // decompression:
       $bodylen = $len-$headerlen-8;
       if ($bodylen < 1) {
           // IMPLEMENTATION BUG!
           if ($debug_log) write_debug("gzdecode Error: Implementation Bug!",true);
           return null;
       }
       $body = substr($data,$headerlen,$bodylen);
       $data = "";
       if ($bodylen > 0) {
           switch ($method) {
           case 8:
               // Currently the only supported compression method:
               $data = gzinflate($body,$maxlength);
               break;
           default:
               $error = "Unknown compression method.";
               if ($debug_log) write_debug("gzdecode Error: ".$error,true);
               return false;
           }
       }  // zero-byte body content is allowed
       // Verifiy CRC32
       $crc   = sprintf("%u",crc32($data));
       $crcOK = $crc == $datacrc;
       $lenOK = $isize == strlen($data);
       if (!$lenOK || !$crcOK) {
           $error = ( $lenOK ? '' : 'Length check FAILED. ') . ( $crcOK ? '' : 'Checksum FAILED.');
           if ($debug_log) write_debug("gzdecode Error: ".$error,true);
//           return false;
           return $data;
       }
       return $data;
   }
}

function get_url_contents($url,$referer,&$cookie_data)
{
    $url_info = parse_url($url);
    if (! isset($url_info['port'])) {
       if ($url_info['scheme'] == "https") $url_info['port'] = 443;
       else $url_info['port'] = 80;
    }
    if ($url_info['scheme'] == "https") $scheme = "tls://";
    else $scheme = '';
    if (isset($url_info['path'])) $path = $url_info['path'];
    else $path = '/';
    if (isset($url_info['query'])) $path .= "?".$url_info['query'];
    if (isset($url_info['fragment'])) $path .= "#".$url_info['fragment'];
    $content_encoding = '';   $location_url = '';

    $fp = @fsockopen($scheme.$url_info['host'],$url_info['port'],$errno,
                     $error_string,60);
    if (! $fp) {
       if ($errno != 0) print $error_string." (".$errno.")";
       return null;
    }
    $request_method = 'GET';
    fputs($fp,"GET ".$path." HTTP/1.1\r\n");
    fputs($fp,"Host: ".$url_info['host']."\r\n");
    if ($referer) fputs($fp,"Referer: ".$referer."\r\n");
    if (isset($_SERVER["HTTP_ACCEPT"]))
       fputs($fp,"Accept: ".$_SERVER["HTTP_ACCEPT"]."\r\n");
    if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
       fputs($fp,"Accept-Language: ".$_SERVER["HTTP_ACCEPT_LANGUAGE"]."\r\n");
    if (isset($_SERVER["HTTP_ACCEPT_CHARSET"]))
       fputs($fp,"Accept-Charset: ".$_SERVER["HTTP_ACCEPT_CHARSET"]."\r\n");
    if (isset($_SERVER["HTTP_PRAGMA"]))
       fputs($fp,"Pragma: ".$_SERVER["HTTP_PRAGMA"]."\r\n");
    else fputs($fp,"Pragma: no-cache\r\n");
    fputs($fp,"Cache-Control: no-cache\r\n");
    if (isset($_SERVER["HTTP_ACCEPT_ENCODING"])) {
       $encoding_types = explode(',',$_SERVER["HTTP_ACCEPT_ENCODING"]);
       while (list($index,$encoding_type) = each($encoding_types)) {
          if ($encoding_type == "gzip") {
             if (! function_exists("gzencode")) unset($encoding_types[$index]);
          }
          else if ($encoding_type == "deflate") {
             if ((! function_exists("gzcompress")) ||
                 (! function_exists("gzuncompress")))
                unset($encoding_types[$index]);
          }
          else unset($encoding_types[$index]);
       }
       $accept_encoding = implode(',',$encoding_types);
       fputs($fp,"Accept-Encoding: ".$accept_encoding."\r\n");
    }
    if ($cookie_data != '') fputs($fp,"Cookie: ".$cookie_data."\r\n");
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
       $user_agent = $_SERVER['HTTP_USER_AGENT'];
       fputs($fp,"User-Agent: ".$user_agent."\r\n");
    }
    if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]))
       fputs($fp,"If-Modified-Since: ".$_SERVER["HTTP_IF_MODIFIED_SINCE"]."\r\n");
    fputs($fp,"Connection: close\r\n");
    fputs($fp,"\r\n");

    $http_status = 0;   $response_data = "";   $chunked = false;
    $inside_header = true;   $headers = array();
    while (! feof($fp)) {
       if ($inside_header) {
          $response = @fgets($fp,8124);
          $response = str_replace("\n","",$response);
          $response = str_replace("\r","",$response);
          if ($response == "") $inside_header = false;
          else {
             if (substr($response,0,5) == "HTTP/")
                $http_status = substr($response,9,3);
             if ($debug_log) write_debug("Response Header = ".$response,true);
             if (! strncasecmp($response,"Content-Length: ",16)) continue;
             if (! strncasecmp($response,"Transfer-Encoding: chunked",26)) {
                $chunked = true;  $parsing_chunk = true;   continue;
             }
             else if (! strncasecmp($response,"Content-Encoding: ",18)) {
                $content_encoding = substr($response,18);
                $content_encoding = strtolower(trim($content_encoding));
             }
             if (! strncasecmp($response,"Location: ",10))
                $location_url = substr($response,10);
             else if (! strncasecmp($response,"Set-Cookie: ",12))
                $cookie_data = substr($response,12);
          }
       }
       else {
          $response = @fread($fp,8124);
          $response_data .= $response;
       }
    }
    fclose($fp);
    if ($location_url)
       return get_url_contents($location_url,$url,$cookie_data);
    if ($chunked) $response_data = join_chunks($response_data);

    if ($content_encoding == "gzip") $response_data = gzdecode($response_data);
    else if ($content_encoding == "deflate")
       $response_data = gzuncompress($reponse_data);
    return $response_data;
}

function build_language_list()
{
    $google_url = "http://translate.google.com/translate?u=".$_SERVER["HTTP_HOST"]."/";
    $cookie_data = '';
    $content = get_url_contents($google_url,null,$cookie_data);
    $start_pos = strpos($content,"<select id=gt-tl");
    if ($start_pos === false)
       $start_pos = strpos($content,"<select name=tl");
    if ($start_pos === false) return null;
    $start_pos = strpos($content,"<option",$start_pos);
    $end_pos = strpos($content,"</select>",$start_pos);
    if ($end_pos === false) return null;
    $list_content = substr($content,$start_pos,$end_pos - $start_pos);
    $list_values = explode("<option",$list_content);
    unset($list_values[0]);
    $languages = array();
    while (list($index,$list_value) = each($list_values)) {
       $start_pos = strpos($list_value,'=');
       if ($start_pos === false) return null;
       $end_pos = strpos($list_value,'>',$start_pos + 1);
       if ($end_pos === false) return null;
       $language_code = substr($list_value,$start_pos + 1,$end_pos - $start_pos - 1);
       $start_pos = $end_pos;
       $end_pos = strpos($list_value,"</option>",$start_pos);
       if ($end_pos === false) return null;
       $language_name = substr($list_value,$start_pos + 1,$end_pos - $start_pos - 1);
       $languages[$language_code] = $language_name;
    }
    return $languages;
}

function get_language_url($language_code)
{
    $google_url = "http://translate.google.com/translate?hl=en&sl=en&tl=" .
                  $language_code."&u=".urlencode('http://'.$_SERVER["HTTP_HOST"]."/");
    $cookie_data = '';
    $content = get_url_contents($google_url,null,$cookie_data);
    $start_pos = strpos($content,"<a href=");
    if ($start_pos === false) return null;
    $start_pos += 9;
    $end_pos = strpos($content,">Translate</a>",$start_pos);
    if ($end_pos === false) return null;
    $url = substr($content,$start_pos,$end_pos - $start_pos - 1);
    $url = str_replace("&amp;","&",$url);
//    $url = str_replace("translate_p","translate_c",$url);
//    $url = str_replace("&usg=","&twu=1&usg=",$url);
    $frame_url = "http://translate.google.com".$url;
    $content = get_url_contents($frame_url,$google_url,$cookie_data);
    return $url;
}

function write_language_file()
{
    $languages = build_language_list();
    if (! $languages) return null;
    $language_data = '';
    while (list($language_code,$language_name) = each($languages)) {
       $language_data .= $language_code.'|'.$language_name."\n";
/*
       $url = get_language_url($language_code);
       if ($url)
          $language_data .= $language_code.'|'.$language_name.'|'.$url."\n";
*/
    }
    $language_file = fopen("googletranslate.dat","wt");
    if (! $language_file) return null;
    fwrite($language_file,$language_data);
    fclose($language_file);
    return $language_data;
}

function write_language_menu($language_data)
{
    print "  <script type=\"text/javascript\">\n" .
          "    function _addload(load_function) {};\n" .
          "    var _intlStrings = new Object();\n" .
          "    function _tipon(obj) {};\n" .
          "    function _tipoff() {};\n";
    print "    var language_menu = document.getElementById('language_menu');\n";
    $languages = explode("\n",$language_data);
    $encoded_host = urlencode('http://'.$_SERVER["HTTP_HOST"]);
    foreach ($languages as $language) {
       if ($language == '') continue;
       $language_info = explode('|',$language);
       print "    var li = document.createElement('li');\n";
       print "    var anchor = document.createElement('a');\n";
       print "    anchor.title = '".$language_info[1]."';\n";
       if ($language_info[0] == 'en')
          print "    anchor.href='http://".$_SERVER["HTTP_HOST"]."<\$prop:filename\$>';\n";
       else print "    anchor.href='http://translate.google.com/translate?hl=en&sl=en&tl=" .
                  $language_info[0]."&u=".$encoded_host."<\$prop:filename\$>';\n";
       print "    anchor.text='".$language_info[1]."';\n";
       print "    li.appendChild(anchor);\n";
       print "    language_menu.appendChild(li);\n";
    }
    print "  </script>\n";
}

function write_language_list($language_data)
{
    print "  <script type=\"text/javascript\">\n" .
          "    function _addload(load_function) {};\n" .
          "    var _intlStrings = new Object();\n" .
          "    function _tipon(obj) {};\n" .
          "    function _tipoff() {};\n" .
          "  </script>\n";
    print "  <div class=\"translate_div\"><form>\n" .
          "    <img class=\"translate_logo\" src=\"images/googletranslate.jpg\">\n" .
          "    <select name=\"language\" id=\"language\" class=\"translate_select\"\n";
    $encoded_host = urlencode('http://'.$_SERVER["HTTP_HOST"]."/");
    print "     onChange=\"var language=this.options[this.selectedIndex].value; " .
          "if (language=='en') var url='http://".$_SERVER["HTTP_HOST"]."'; else " .
          "var url='http://translate.google.com/translate?hl=en&sl=en&tl='" .
          " + this.options[this.selectedIndex].value + '&u=".$encoded_host."'; " .
          "location.href=url;\">\n";
    print "    </select>\n";
    print "  <script type=\"text/javascript\">\n" .
          "    function add_option(list,text,value) {\n" .
          "       var select_option = document.createElement('option');\n" .
          "       select_option.text = text;\n" .
          "       select_option.value = value;\n" .
          "       try { list.add(select_option,null); }\n" .
          "       catch(ex) { list.add(select_option); }\n" .
          "    }\n" .
          "    var language_list = document.getElementById('language');\n" .
          "    add_option(language_list,'Select Language','');\n" .
          "    add_option(language_list,'English','en');\n";
    $languages = explode("\n",$language_data);
    foreach ($languages as $language) {
       if ($language == '') continue;
       $language_info = explode('|',$language);
       if ($language_info[0] == 'en') continue;
       print "    add_option(language_list,'".$language_info[1]."','" .
             $language_info[0]."');\n";
    }
/*
          "    var num_options = language_list.length;\n" .
          "    for (var loop = 0;  loop < num_options;  loop++)\n" .
          "       if (language_list.options[loop].value == 'en') {\n" .
          "          var english_option = language_list.options[loop];\n" .
          "          break;\n" .
          "       }\n" .
          "    english_option.text = 'English';\n" .
          "    language_list.remove(loop);\n" .
          "    try { language_list.add(english_option,language_list.options[0]); }\n" .
          "    catch(ex) { language_list.add(english_option,0); }\n" .
          "    var select_option = document.createElement('option');\n" .
          "    select_option.text = 'Select Language';\n" .
          "    select_option.value = '';\n" .
          "    try { language_list.add(select_option,language_list.options[0]); }\n" .
          "    catch(ex) { language_list.add(select_option,0); }\n" .
          "    language_list.selectedIndex = 0;\n" .
*/
    print "  </script>\n";
    print "  </form></div>";
}

if (file_exists("googletranslate.dat"))
   $last_updated = filemtime("googletranslate.dat");
else $last_updated = 0;
if ($last_updated < (time() - (60*60*24*7)))
   $language_data = write_language_file();
else $language_data = file_get_contents("googletranslate.dat");
if (! $language_data) exit;
$format = get_form_field("format");
if ($format == 'menu') write_language_menu($language_data);
else write_language_list($language_data);

?>
