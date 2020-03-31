<?php
/*
                   Inroads Shopping Cart - LinkPoint API Module

                        Written 2007-2018 by Randall Severy
                         Copyright 2007-2018 Inroads, LLC
*/

function linkpoint_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'LinkPoint','linkpoint',$values);

    $dialog->add_edit_row('Hostname:','linkpoint_hostname',$values,30);
    $dialog->add_edit_row('Port:','linkpoint_port',$values,30);
    $dialog->add_edit_row('Key File:','linkpoint_keyfile',$values,30);
    $dialog->add_edit_row('Config File:','linkpoint_configfile',$values,30);
}

function linkpoint_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('linkpoint_hostname','linkpoint_port','linkpoint_keyfile',
       'linkpoint_configfile');
    if (! empty($website_settings)) $fields[] = 'linkpoint_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function linkpoint_payment_update_cart_config_field($field_name,
                                                    &$new_field_value,$db)
{
    if ($field_name == 'linkpoint_active') {
       if (get_form_field('linkpoint_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function linkpoint_active($db)
{
    return get_cart_config_value('linkpoint_active',$db);
}

function linkpoint_get_primary_module($db)
{
    return 'linkpoint';
}

class lphp
{
   var $debugging;

   ###########################################
   #
   #   F U N C T I O N    p r o c e s s ( ) 
   #
   #   process a hash table or XML string 
   #   using LIBLPHP.SO and LIBLPSSL.SO
   #
   ###########################################

   function process($data)
   {
      $using_xml = 0;
      $webspace = 1;

      if (isset($data['webspace']))
      {
         if ($data['webspace'] == 'false') // if explicitly set to false, don't use html output
            $webspace = 0;
      }

      if ( isset($data['debugging']) || isset($data['debug']) )
      {
         if ($data['debugging'] == 'true' || $data['debug'] == 'true'  )
         {
            $this->debugging = 1;
            
            # print out incoming hash
            if ($webspace)   // use html-friendly output
            {
               echo 'at process, incoming data: <br>';
            
               foreach ($data as $key => $value)
                   echo htmlspecialchars($key) . ' = ' . htmlspecialchars($value) . "<BR>\n";
            }
            else      // don't use html output
            {
               echo "at process, incoming data: \n";
               
               foreach ($data as $key => $value)
                  echo "$key = $value\n"; 
            }
         }
      }

      if (isset($data['xml'])) // if XML string is passed in, we'll use it
      {
         $using_xml = 1;
         $xml = $data['xml'];
      }
      else
      {
         //  otherwise convert incoming hash to XML string
         $xml = $this->buildXML($data);
      }

      // then set up transaction variables
      $key   = $data['keyfile'];
      $host   = $data['host'];
      $port   = $data[port];


      # FOR PERFORMANCE, Use the 'extensions' statement in your php.ini to load
      # this library at PHP startup, then comment out the next seven lines 

      // load library
      if (!extension_loaded('liblphp'))
      {
         if (!dl('liblphp.so'))
         {
            exit("cannot load liblphp.so, bye\n");
         }
      }

      if ($this->debugging)
      {
         if ($webspace)
            echo '<br>sending xml string:<br>' . htmlspecialchars($xml) . '<br><br>';    
         else
            echo "\nsending xml string:\n$xml\n\n";
      }

      // send transaction to LSGS
      $retstg = send_stg($xml, $key, $host, $port);


      if (strlen($retstg) < 4)
         exit ('cannot connect to lsgs, exiting');
      
      if ($this->debugging)
      {   
         if ($this->webspace)   // we're web space
            echo '<br>server responds:<br>' . htmlspecialchars($retstg) . '<br><br>';
         else                  // not html output
            echo "\nserver responds:\n $retstg\n\n";
      }
   
      if ($using_xml != 1)
      {
         // convert xml response back to hash
         $retarr = $this->decodeXML($retstg);
         
         // and send it back to caller
         return ($retarr);
      }
      else
      {
         // send server response back
         return $retstg;
      }
   }


   #####################################################
   #
   #   F U N C T I O N    c u r l _ p r o c e s s ( ) 
   #
   #   process hash table or xml string table using 
   #   curl, either with PHP built-in curl methods 
   #   or binary executable curl
   #
   #####################################################
   
   function curl_process($data)
   {
      $using_xml = 0;
      $webspace = 1;

      if (isset($data['webspace']))
      {
         if ($data['webspace'] == 'false') // if explicitly set to false, don't use html output
            $webspace = 0;
      }

      if (isset($data['debugging']) || isset($data['debug']) )
      {
         if ($data['debugging'] == 'true' || $data['debug'] == 'true' )
         {
            $this->debugging = 1;

            # print out incoming hash
            if ($webspace)   // use html-friendly output
            {
               echo 'at curl_process, incoming data: <br>';

               foreach ($data as $key => $value)
                   echo htmlspecialchars($key) . ' = ' . htmlspecialchars($value) . "<BR>\n";
            }
            else      // don't use html output
            {
               echo "at curl_process, incoming data: \n";
               
               foreach ($data as $key => $value)
                  echo "$key = $value\n";
            }
         }
      }

      if (isset($data['xml'])) // if XML string is passed in, we'll use it
      {
         $using_xml = 1;
         $xml = $data['xml'];
      }
      else
      {
         // otherwise convert incoming hash to XML string
         $xml = $this->buildXML($data);
      }

      if ($this->debugging)
      {
         if ($webspace)
            echo '<br>sending xml string:<br>' . htmlspecialchars($xml) . '<br><br>';    
         else
            echo "\nsending xml string:\n$xml\n\n";
      }

      // set up transaction variables
      $key = $data['keyfile'];
      $port = $data['port'];
      $host = 'https://'.$data['host'].':'.$port.'/LSGSXML';


      if (isset($data['cbin'])) //using BINARY curl methods
      {
         if ($data['cbin'] == 'true')
         {
            if (isset($data['cpath']))
               $cpath = $data['cpath'];
                  
            else // curl path has not been set, try to find curl binary
            {
               if (getenv('OS') == 'Windows_NT')
                  $cpath = 'c:\\curl\\curl.exe';
               else
                  $cpath = '/usr/bin/curl';
            }

            // look for $cargs variable, otherwise use default curl arguments
            if (isset($data['cargs']))
               $args = $data['cargs'];
            else
               $args = '-m 300 -s -S';      // default curl args; 5 min. timeout


            # TRANSACT #

            if (getenv('OS') == 'Windows_NT')
            {
               if ($this->debugging)
                  $result = exec ("$cpath -v -d \"$xml\" -E $key  -k $host", $retarr, $retnum);
               else
                  $result = exec ("$cpath -d \"$xml\" -E $key  -k $host", $retarr, $retnum);
            }
            
            else   //*nix string
            {
               if ($this->debugging)
                  $result = exec ("'$cpath' $args -v -E '$key' -d '$xml' '$host'", $retarr, $retnum);
               else
                  $result = exec ("'$cpath' $args -E '$key' -d '$xml' '$host'", $retarr, $retnum);
            }

            # EVALUATE RESPONSE #

            if (strlen($result) < 2)    // no response
            {
               $result = '<r_approved>FAILURE</r_approved><r_error>Could not connect.</r_error>'; 
               return $result;
            }

            if ($this->debugging)
            {
               if ($this->webspace)
                  echo '<br>server responds:<br>' . htmlspecialchars($result) . '<br><br>';
               else                  // non html output
                  echo "\nserver responds:\n $result\n\n";
            }

            if ($using_xml == 1)
            { 
               // return xml string straight from server
               return ($result);
            }
            else
            {
               // convert xml response back to hash
               $retarr = $this->decodeXML($result);
               
               // and send it back to caller. Done.
               return ($retarr);
            }
         }
      }

      else   // using BUILT-IN PHP curl methods
      {
         $ch = curl_init ();
         curl_setopt ($ch, CURLOPT_URL,$host);
         curl_setopt ($ch, CURLOPT_POST, 1); 
         curl_setopt ($ch, CURLOPT_POSTFIELDS, $xml);
         curl_setopt ($ch, CURLOPT_SSLCERT, $key);
         curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
         curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
         curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 20);

         if ($this->debugging)
            curl_setopt ($ch, CURLOPT_VERBOSE, 1);

         #  use curl to send the xml SSL string
         $result = curl_exec ($ch);

         curl_close($ch);

         if (strlen($result) < 2)    # no response
         {
            $result = '<r_approved>FAILURE</r_approved><r_error>Could not connect.</r_error>'; 
            return $result;
         }

         if ($this->debugging)
         {   
            if ($webspace)   // html-friendly output
               echo '<br>server responds:<br>' . htmlspecialchars($result) . '<br><br>';
            else
               echo "\nserver responds:\n $result\n\n";
         }

         if ($using_xml)
         {
            # send xml response back
            return $result;
         }
         else
         {
            #convert xml response to hash
            $retarr = $this->decodeXML($result);
            
            # and send it back
            return ($retarr);
         }
      }
   }


   #############################################   
   #
   #   F U N C T I O N   d e c o d e X M L ( ) 
   #
   #   converts the LSGS response xml string   
   #   to a hash of name-value pairs
   #
   #############################################

   function decodeXML($xmlstg)
   {
      preg_match_all ("/<(.*?)>(.*?)\</", $xmlstg, $out, PREG_SET_ORDER);
      
      $n = 0;
      while (isset($out[$n]))
      {
         $retarr[$out[$n][1]] = strip_tags($out[$n][0]);
         $n++; 
      }

      return $retarr;
   }


   ############################################
   #
   #   F U N C T I O N    b u i l d X M L ( ) 
   #
   #   converts a hash of name-value pairs
   #   to the correct XML format for LSGS
   #
   ############################################

   function buildXML($pdata)
   {

//      foreach ($pdata as $key => $value)
//          echo htmlspecialchars($key) . ' = ' . htmlspecialchars($value) . "<br>\n";


      ### ORDEROPTIONS NODE ###
      $xml = '<order><orderoptions>';

      if (isset($pdata['ordertype']))
         $xml .= '<ordertype>' . $pdata['ordertype'] . '</ordertype>';

      if (isset($pdata['result']))
         $xml .= '<result>' . $pdata['result'] . '</result>';

      $xml .= '</orderoptions>';


      ### CREDITCARD NODE ###
      $xml .= '<creditcard>';

      if (isset($pdata['cardnumber']))
         $xml .= '<cardnumber>' . $pdata['cardnumber'] . '</cardnumber>';

      if (isset($pdata['cardexpmonth']))
         $xml .= '<cardexpmonth>' . $pdata['cardexpmonth'] . '</cardexpmonth>';

      if (isset($pdata['cardexpyear']))
         $xml .= '<cardexpyear>' . $pdata['cardexpyear'] . '</cardexpyear>';

      if (isset($pdata['cvmvalue']))
         $xml .= '<cvmvalue>' . $pdata['cvmvalue'] . '</cvmvalue>';

      if (isset($pdata['cvmindicator']))
         $xml .= '<cvmindicator>' . $pdata['cvmindicator'] . '</cvmindicator>';

      if (isset($pdata['track']))
         $xml .= '<track>' . $pdata['track'] . '</track>';

      $xml .= '</creditcard>';


      ### BILLING NODE ###
      $xml .= '<billing>';

      if (isset($pdata['name']))
         $xml .= '<name>' . $pdata['name'] . '</name>';

      if (isset($pdata['company']))
         $xml .= '<company>' . $pdata['company'] . '</company>';

      if (isset($pdata['address1']))
         $xml .= '<address1>' . $pdata['address1'] . '</address1>';
      elseif (isset($pdata['address']))
         $xml .= '<address1>' . $pdata['address'] . '</address1>';

      if (isset($pdata['address2']))
         $xml .= '<address2>' . $pdata['address2'] . '</address2>';

      if (isset($pdata['city']))
         $xml .= '<city>' . $pdata['city'] . '</city>';
         
      if (isset($pdata['state']))
         $xml .= '<state>' . $pdata['state'] . '</state>';
         
      if (isset($pdata['zip']))
         $xml .= '<zip>' . $pdata['zip'] . '</zip>';

      if (isset($pdata['country']))
         $xml .= '<country>' . $pdata['country'] . '</country>';

      if (isset($pdata['userid']))
         $xml .= '<userid>' . $pdata['userid'] . '</userid>';

      if (isset($pdata['email']))
         $xml .= '<email>' . $pdata['email'] . '</email>';

      if (isset($pdata['phone']))
         $xml .= '<phone>' . $pdata['phone'] . '</phone>';

      if (isset($pdata['fax']))
         $xml .= '<fax>' . $pdata['fax'] . '</fax>';

      if (isset($pdata['addrnum']))
         $xml .= '<addrnum>' . $pdata['addrnum'] . '</addrnum>';

      $xml .= '</billing>';

      
      ## SHIPPING NODE ##
      $xml .= '<shipping>';

      if (isset($pdata['sname']))
         $xml .= '<name>' . $pdata['sname'] . '</name>';

      if (isset($pdata['saddress1']))
         $xml .= '<address1>' . $pdata['saddress1'] . '</address1>';

      if (isset($pdata['saddress2']))
         $xml .= '<address2>' . $pdata['saddress2'] . '</address2>';

      if (isset($pdata['scity']))
         $xml .= '<city>' . $pdata['scity'] . '</city>';

      if (isset($pdata['sstate']))
         $xml .= '<state>' . $pdata['sstate'] . '</state>';
      elseif (isset($pdata['state']))
         $xml .= '<state>' . $pdata['sstate'] . '</state>';

      if (isset($pdata['szip']))
         $xml .= '<zip>' . $pdata['szip'] . '</zip>';
      elseif (isset($pdata['sip']))
         $xml .= '<zip>' . $pdata['zip'] . '</zip>';

      if (isset($pdata['scountry']))
         $xml .= '<country>' . $pdata['scountry'] . '</country>';

      if (isset($pdata['scarrier']))
         $xml .= '<carrier>' . $pdata['scarrier'] . '</carrier>';

      if (isset($pdata['sitems']))
         $xml .= '<items>' . $pdata['sitems'] . '</items>';

      if (isset($pdata['sweight']))
         $xml .= '<weight>' . $pdata['sweight'] . '</weight>';

      if (isset($pdata['stotal']))
         $xml .= '<total>' . $pdata['stotal'] . '</total>';

      $xml .= '</shipping>';


      ### TRANSACTIONDETAILS NODE ###
      $xml .= '<transactiondetails>';

      if (isset($pdata['oid']))
         $xml .= '<oid>' . $pdata['oid'] . '</oid>';

      if (isset($pdata['ponumber']))
         $xml .= '<ponumber>' . $pdata['ponumber'] . '</ponumber>';

      if (isset($pdata['recurring']))
         $xml .= '<recurring>' . $pdata['recurring'] . '</recurring>';

      if (isset($pdata['taxexempt']))
         $xml .= '<taxexempt>' . $pdata['taxexempt'] . '</taxexempt>';

      if (isset($pdata['terminaltype']))
         $xml .= '<terminaltype>' . $pdata['terminaltype'] . '</terminaltype>';

      if (isset($pdata['ip']))
         $xml .= '<ip>' . $pdata['ip'] . '</ip>';

      if (isset($pdata['reference_number']))
         $xml .= '<reference_number>' . $pdata['reference_number'] . '</reference_number>';

      if (isset($pdata['transactionorigin']))
         $xml .= '<transactionorigin>' . $pdata['transactionorigin'] . '</transactionorigin>';

      if (isset($pdata['tdate']))
         $xml .= '<tdate>' . $pdata['tdate'] . '</tdate>';

      $xml .= '</transactiondetails>';


      ### MERCHANTINFO NODE ###
      $xml .= '<merchantinfo>';

      if (isset($pdata['configfile']))
         $xml .= '<configfile>' . $pdata['configfile'] . '</configfile>';

      if (isset($pdata['keyfile']))
         $xml .= '<keyfile>' . $pdata['keyfile'] . '</keyfile>';

      if (isset($pdata['host']))
         $xml .= '<host>' . $pdata['host'] . '</host>';

      if (isset($pdata['port']))
         $xml .= '<port>' . $pdata['port'] . '</port>';

      if (isset($pdata['appname']))
         $xml .= '<appname>' . $pdata['appname'] . '</appname>';

      $xml .= '</merchantinfo>';



      ### PAYMENT NODE ###
      $xml .= '<payment>';

      if (isset($pdata['chargetotal']))
         $xml .= '<chargetotal>' . $pdata['chargetotal'] . '</chargetotal>';

      if (isset($pdata['tax']))
         $xml .= '<tax>' . $pdata['tax'] . '</tax>';

      if (isset($pdata['vattax']))
         $xml .= '<vattax>' . $pdata['vattax'] . '</vattax>';

      if (isset($pdata['shipping']))
         $xml .= '<shipping>' . $pdata['shipping'] . '</shipping>';

      if (isset($pdata['subtotal']))
         $xml .= '<subtotal>' . $pdata['subtotal'] . '</subtotal>';

      $xml .= '</payment>';


      ### CHECK NODE ### 


      if (isset($pdata['voidcheck']))
      {
         $xml .= '<telecheck><void>1</void></telecheck>';
      }
      elseif (isset($pdata['routing']))
      {
         $xml .= '<telecheck>';
         $xml .= '<routing>' . $pdata['routing'] . '</routing>';

         if (isset($pdata['account']))
            $xml .= '<account>' . $pdata['account'] . '</account>';

         if (isset($pdata['bankname']))
            $xml .= '<bankname>' . $pdata['bankname'] . '</bankname>';
   
         if (isset($pdata['bankstate']))
            $xml .= '<bankstate>' . $pdata['bankstate'] . '</bankstate>';

         if (isset($pdata['ssn']))
            $xml .= '<ssn>' . $pdata['ssn'] . '</ssn>';

         if (isset($pdata['dl']))
            $xml .= '<dl>' . $pdata['dl'] . '</dl>';

         if (isset($pdata['dlstate']))
            $xml .= '<dlstate>' . $pdata['dlstate'] . '</dlstate>';

         if (isset($pdata['checknumber']))
            $xml .= '<checknumber>' . $pdata['checknumber'] . '</checknumber>';
            
         if (isset($pdata['accounttype']))
            $xml .= '<accounttype>' . $pdata['accounttype'] . '</accounttype>';

         $xml .= '</telecheck>';
      }


      ### PERIODIC NODE ###

      if (isset($pdata['startdate']))
      {
         $xml .= '<periodic>';

         $xml .= '<startdate>' . $pdata['startdate'] . '</startdate>';

         if (isset($pdata['installments']))
            $xml .= '<installments>' . $pdata['installments'] . '</installments>';

         if (isset($pdata['threshold']))
                  $xml .= '<threshold>' . $pdata['threshold'] . '</threshold>';

         if (isset($pdata['periodicity']))
                  $xml .= '<periodicity>' . $pdata['periodicity'] . '</periodicity>';

         if (isset($pdata['pbcomments']))
                  $xml .= '<comments>' . $pdata['pbcomments'] . '</comments>';

         if (isset($pdata['action']))
            $xml .= '<action>' . $pdata['action'] . '</action>';

         $xml .= '</periodic>';
      }


      ### NOTES NODE ###

      if (isset($pdata['comments']) || isset($pdata['referred']))
      {
         $xml .= '<notes>';

         if (isset($pdata['comments']))
            $xml .= '<comments>' . $pdata['comments'] . '</comments>';

         if (isset($pdata['referred']))
            $xml .= '<referred>' . $pdata['referred'] . '</referred>';

         $xml .= '</notes>';
      }

      ### ITEMS AND OPTIONS NODES ###
   
      if ($this->debugging)   // make it easy to see
      {                  // LSGS doesn't mind whitespace

         foreach ($pdata as $key => $val)
         {
            if (is_array($val))
            {
               $otag = 0;
               $ostag = 0;
               $items_array = $val;
               $xml .= "\n<items>\n";

               foreach ($items_array as $key1 => $val1)
               {
                  $xml .= "\t<item>\n";

                  foreach ($val1 as $key2 => $val2)
                  {
                     if (!is_array($val2))
                        $xml .= "\t\t<$key2>$val2</$key2>\n";

                     else
                     {
                        if (!$ostag)
                        {
                           $xml .= "\t\t<options>\n";
                           $ostag = 1;
                        }

                        $xml .= "\t\t\t<option>\n";
                        $otag = 1;
                        
                        foreach ($val2 as $key3 => $val3)
                           $xml .= "\t\t\t\t<$key3>$val3</$key3>\n";
                     }

                     if ($otag)
                     {
                        $xml .= "\t\t\t</option>\n";
                        $otag = 0;
                     }
                  }

                  if ($ostag)
                  {
                     $xml .= "\t\t</options>\n";
                     $ostag = 0;
                  }
               $xml .= "\t</item>\n";
               }
            $xml .= "</items>\n";
            }
         }
      }

      else // !debugging
      {
         foreach ($pdata as $key => $val)
         {
            if (is_array($val))
            {
               $otag = 0;
               $ostag = 0;
               $items_array = $val;
               $xml .= '<items>';

               foreach ($items_array as $key1 => $val1)
               {
                  $xml .= '<item>';

                  foreach ($val1 as $key2 => $val2)
                  {
                     if (!is_array($val2))
                        $xml .= "<$key2>$val2</$key2>";

                     else
                     {
                        if (!$ostag)
                        {
                           $xml .= '<options>';
                           $ostag = 1;
                        }

                        $xml .= '<option>';
                        $otag = 1;
                        
                        foreach ($val2 as $key3 => $val3)
                           $xml .= "<$key3>$val3</$key3>";
                     }

                     if ($otag)
                     {
                        $xml .= '</option>';
                        $otag = 0;
                     }
                  }

                  if ($ostag)
                  {
                     $xml .= '</options>';
                     $ostag = 0;
                  }
               $xml .= '</item>';
               }
            $xml .= '</items>';
            }
         }
      }

      $xml .= '</order>';

      return $xml;
   }
}

function linkpoint_process_payment(&$order)
{
    $mylphp = new lphp;
    $billing_country_info = get_country_info($order->billing_country,$order->db);
    $shipping_country_info = get_country_info($order->shipping_country,$order->db);
    $shipto = $order->customer->shipping['shipto'];
    if ((! isset($shipto)) || ($shipto == ''))
       $shipto = $order->customer->info['fname'].' '.$order->customer->info['lname'];
    if (isset($order->info['shipping']) && ($order->info['shipping'] != ''))
       $shipping = $order->info['shipping'];
    else $shipping = 0;
    if (isset($order->info['tax']) && ($order->info['tax'] != ''))
       $tax = $order->info['tax'];
    else $tax = 0;
    $order_info = array('host' => get_cart_config_value('linkpoint_hostname'),
                        'port' => get_cart_config_value('linkpoint_port'),
                        'keyfile' => get_cart_config_value('linkpoint_keyfile'),
                        'configfile' => get_cart_config_value('linkpoint_configfile'),
                        'ordertype' => 'SALE',
                        'result' => 'LIVE',
                        'transactionorigin' => 'ECI',
                        'terminaltype' => 'UNSPECIFIED',
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'subtotal' => $order->info['subtotal'],
                        'tax' => $tax,
                        'shipping' => $shipping,
                        'chargetotal' => $order->payment['payment_amount'],
                        'cardnumber' => $order->credit_card['number'],
                        'cardexpmonth' => $order->credit_card['month'],
                        'cardexpyear' => $order->credit_card['year'],
                        'cvmindicator' => 'provided',
                        'cvmvalue' => $order->credit_card['cvv'],
                        'userid' => $order->customer->info['id'],
                        'name' => $order->customer->info['fname'].' ' .
                                  $order->customer->info['lname'],
                        'address1' => $order->customer->billing['address1'],
                        'address2' => $order->customer->billing['address2'],
                        'city' => $order->customer->billing['city'],
                        'state' => $order->customer->billing['state'],
                        'zip' => $order->customer->billing['zipcode'],
                        'country' => $billing_country_info['code'],
                        'phone' => $order->customer->billing['phone'],
                        'email' => $order->customer->info['email'],
                        'sname' => $shipto,
                        'saddress1' => $order->customer->shipping['address1'],
                        'saddress2' => $order->customer->shipping['address2'],
                        'scity' => $order->customer->shipping['city'],
                        'sstate' => $order->customer->shipping['state'],
                        'szip' => $order->customer->shipping['zipcode'],
                        'scountry' => $shipping_country_info['code']);

    if ($order->payment['cardnumber'] == '4111111111111111')
       $order->payment['result'] = 'Good';

    $item_string = '';   $index = 0;
    foreach ($order->items as $id => $cart_item) {
       $item_description = get_html_product_name($cart_item['product_name'],
                                                 GET_PROD_PAYMENT_GATEWAY,
                                                 $order,$cart_item);
       $attr_array = $cart_item['attribute_array'];
       if (isset($attr_array) && (count($attr_array) > 0)) {
          foreach ($attr_array as $index => $attribute) {
             if ($index == 0) $item_description .= ' - ';
             else $item_description .= ', ';
             $item_description .= $attribute['attr'].': '.$attribute['option'];
          }
       }
       if (strlen($item_description) > 128)
          $item_description = substr($item_description,0,128);
       $order_info['items'][$index]['id'] = $cart_item['product_id'];
       $order_info['items'][$index]['description'] = $item_description;
       $order_info['items'][$index]['quantity'] = $cart_item['qty'];
       $order_info['items'][$index]['price'] = get_item_total($cart_item,false);
       $index++;
    }

    $log_string = '';
    foreach ($order_info as $key => $value) {
       if (($key == 'cardnumber') || ($key == 'cardexpmonth') ||
           ($key == 'cardexpyear') || ($key == 'cvmvalue')) continue;
       if ($key == 'items') {
          $items_array = $value;
          foreach ($items_array as $index => $item_details)
             foreach ($item_details as $key => $value) {
                if ($log_string != '') $log_string .= '&';
                $log_string .= $key.'='.urlencode($value);
             }
       }
       else {
          if ($log_string != '') $log_string .= '&';
          $log_string .= $key.'='.urlencode($value);
       }
    }
    $order->log_payment('Sent: '.$log_string);

    $result_array = $mylphp->curl_process($order_info);
    if (! is_array($result_array)) $result_array = $mylphp->decodeXML($result_array);
    $log_string = '';
    foreach ($result_array as $key => $value) {
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    $order->log_payment('Response: '.$log_string);

    if ($result_array['r_approved'] != 'APPROVED') {
       if (isset($result_array['r_message']))
          $error_msg = $result_array['r_message'].' - '.$result_array['r_error'];
       else $error_msg = $result_array['r_error'];
       log_activity('LinkPoint Declined: '.$error_msg);
       $order->error = 'Card Declined: '.$error_msg;   return false;
    }
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = $result_array['r_ordernum'];
    $order->payment['payment_code'] = $result_array['r_code'];
    $order->payment['payment_ref'] = $result_array['r_ref'];
    log_activity('LinkPoint Payment Accepted with Order #' .
                 $order->payment['payment_id'].', Approval Code ' .
                 $order->payment['payment_code'].' and Reference Number ' .
                 $order->payment['payment_ref']);
    return true;
}

?>
