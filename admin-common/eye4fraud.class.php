<?php
class Eye4Fraud
{
    private $url = 'https://www.eye4fraud.com/api/'; // Eye4Fraud url where to post orders
    private $login;    // Provided by Eye4Fraud
    private $password; // Provided by Eye4Fraud
    private $transaction_id;
    private $bin;
    private $error = '';

    private $order = array(
        'SiteName'               => '',
        'OrderDate'              => '', // The date/time the order was submitted
        'OrderNumber'            => '', // This is the number we use for all correspondence including if we need to call the customer
        'CustomerID'             => '', // Account ID for the customer
        'BillingFirstName'       => '',
        'BillingMiddleName'      => '',
        'BillingLastName'        => '', // Please note that it is imperative that you populate as many fields as you can in the XML file. The more data we have, the quicker an order can be verified and effectively this also increases the approval rates of the verification staff.
        'BillingCompany'         => '',
        'BillingAddress1'        => '',
        'BillingAddress2'        => '',
        'BillingCity'            => '',
        'BillingState'           => '',
        'BillingZip'             => '',
        'BillingCountry'         => '',
        'BillingEveningPhone'    => '',
        'BillingDayPhone'        => '',
        'BillingCellPhone'       => '',
        'BillingEmail'           => '',
        'IPAddress'              => '', // Customer's IP address
        'ShippingMethod'         => '', // For example Ground, Overnight, Fedex Ground, UPS Overnight
        'ShippingFirstName'      => '',
        'ShippingMiddleName'     => '',
        'ShippingLastName'       => '',
        'ShippingCompany'        => '',
        'ShippingAddress1'       => '',
        'ShippingAddress2'       => '',
        'ShippingCity'           => '',
        'ShippingState'          => '',
        'ShippingZip'            => '',
        'ShippingCountry'        => '',
        'ShippingEveningPhone'   => '',
        'ShippingDayPhone'       => '',
        'ShippingCellPhone'      => '',
        'ShippingEmail'          => '',
        'ShippingCost'           => '',
        'GrandTotal'             => '',
        'CCFirst6'               => '', // Bank Identification Number. This is the first 6 digits of the credit card number.
        'CCLast4'                => '', // This is the last 4 digits of the credit card number
        'CCType'                 => '', // Visa, Master, Discover, Amex...
        'CCExpires'              => '', // Example: 05/12
        'CIDResponse'            => '', // This is the Response Code from auth.net/verisign for The CID also known as CVV2. Examples: M, N, U, P
        'GatewayStatus'          => '', // Example: Approved, Declined, Timeout
        'AVSCode'                => '', // Example: Y, Z, A, X, G, S
        'ReferringCode'          => '', // Merchant's internal referrer source code. This can be a designated number or a referring URL.
        'ConsumerReferral'       => '', // The customer's entryt response how they found out about the website. Example: Friend, google, television?.
        'Cookie'                 => '',
        'SalesRep'               => '', // For phone orders this is the name or initials of the Sales Rep. For online orders this would be what the customer chooses online
        'PromoCode'              => '', // Promotion Code
        'AlternateBillingEmail'  => '',
        'CustomerComments'       => '',
        'SalesRepComments'       => '',
        'InboundCallerID'        => '', // For phone orders
        'OutboundCallerID'       => '', // For Phone orders
        'ECICode'                => '', // Electronic Commerce Indicator Code. Used for 3D secure transactions. Examples: 06, 02, 05,?
        'CAVVResultCode'         => '', // Used for Verfied By VISA. Examples: 2, 8, 3?
        'RepeatCustomer'         => '', // Merchant's indicator if customer is a safe, non-problematic repeater. True/False
        'HighRiskDeliveryMethod' => '', // If merchant believes the shipping method choice to be a high risk (e.g. Overnight). True/False
        'ShippingDeadline'       => '', // The date/time the order must ship out
    );

    private $line_item = array(
        'SKU'                 => '',  // Manufacturer's Stock Number
        'ProductName'         => '',
        'ProductDescription'  => '',
        'ProductSellingPrice' => '',
        'ProductQty'          => '',
        'ProductCostPrice'    => '',
        'ProductBrandname'    => '',
        'ProductStockNumber'  => '',  // Merchant's Stock Number
    );

    private $line_items = array();

    public function __construct($url = '', $login = '', $password = '')
    {
        if ($url) $this->setUrl($url);
        if ($login) $this->setLogin($login);
        if ($password) $this->setPassword($password);
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setLogin($login)
    {
        $this->login = $login;
        return $this;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setTransactionId($transaction_id)
    {
        $this->transaction_id = $transaction_id;
        return $this;
    }

    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    public function setBin($bin)
    {
        $this->bin = $bin;
        return $this;
    }

    public function getBin()
    {
        return $this->bin;
    }

    public function setOrderInfo(array $values)
    {
        foreach ($values as $key => $val) {
            if (isset($this->order[$key])) {
                $this->order[$key] = $val;
            } else {
                die("Unknown order parameter: '$key'");
            }
        }
    }

    public function getOrderInfo()
    {
        return $this->order;
    }

    public function addLineItem(array $item)
    {
        // Check if item params are ok
        foreach ($item as $key => $val) {
            if (!isset($this->line_item[$key])) {
                die("Unknown line item parameter: '$key'");
            }
        }

        // Add item to line items list
        $this->line_items[] = $item;
    }

    public function getLineItems()
    {
        return $this->line_items;
    }

    public function setError($err)
    {
        $this->error = $err;
    }

    public function getError()
    {
        return $this->error;
    }

    public function log($msg)
    {
        $log_file = fopen('../admin/eye4fraud.log',"at");
        if ($log_file) {
           $msg = str_replace("\n"," ",$msg);
           fwrite($log_file,"[".date("D M d Y H:i:s")."] ".$msg."\n");
           fclose($log_file);
        }
    }

    //Post data to Eye4Fraud
    public function send()
    {
        // Determine the minimun set of values to POST
        $post_fields = array(
            'ApiLogin'      => $this->login,
            'ApiKey'        => $this->password,
            'TransactionId' => $this->transaction_id,
            'CCFirst6'      => $this->bin
        );

        // Add all the rest of order fields to the POST
        foreach ($this->order as $key => $val) {
            $post_fields[$key] = $val;
        }

        // Add line items
        if (!$this->line_items) {
            $this->addLineItem($this->line_item);
        }
        $post_fields['LineItems'] = $this->line_items;

        // Do the POST
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        $post_fields = http_build_query($post_fields);
        $this->log("Sent: ".$post_fields);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->log("Response: ".$response);

        // Get error
        $this->setError('');
        if ($response == 'ok') {
            return 1;
        } else {
            $this->setError($response);
            return 0;
        }
    }
}

?>
