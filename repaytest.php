<?php
include "PxPost_Curl.inc.php";

$PxPost_Url    = "https://sec.paymentexpress.com/pxpost.aspx";
# Please update below with the PxPost credentials - if the PxPost credentials are not available, please contact suppport@paymentexpress.com to receive them.
$PxPost_Username = "";
$PxPost_Password   = "";

$PxPost = new PxPost_Curl( $PxPost_Url, $PxPost_Username, $PxPost_Password );

# this is a fresh request -- display the purchase form.
repay_form();


function print_reresult($request_string1) {
  global $PxPost;

  #getResponse method in PxPost object returns PxPostResponse object
  #which encapsulates all the response data
  $rersp = $PxPost->getResponse($request_string1);

  # the following are the fields available in the PxPostResponse object
  $Success           = $rersp->getSuccess();   # =1 when request succeeds
  $Amount            = $rersp->getAmount();
  $AuthCode          = $rersp->getAuthCode();  # from bank
  $CardName          = $rersp->getCardName();  # e.g. "Visa"
  $CardNumber        = $rersp->getCardNumber(); # Truncated card number
  $DateExpiry        = $rersp->getDateExpiry(); # in mmyy format
  $DpsBillingId      = $rersp->getDpsBillingId();
  $BillingId    	   = $rersp->getBillingId();
  $CardHolderName    = $rersp->getCardHolderName();
  $DpsTxnRef	       = $rersp->getDpsTxnRef();
  $TxnType           = $rersp->getTxnType();
  $Currency          = $rersp->getCurrency();
  $ClientInfo        = $rersp->getClientInfo(); # The IP address of the user who submitted the transaction
  $TxnId             = $rersp->getTxnId();
  $InputCurrency     = $rersp->getInputCurrency();
  $MerchantReference = $rersp->getMerchantReference();
  $TxnMac            = $rersp->getTxnMac(); # An indication as to the uniqueness of a card used in relation to others
  $RecurringMode     = $rersp->getRecurringMode();


  if ($rersp->getSuccess() == "1") {
    $result1 = "The transaction was approved.";
    # Sending invoices/updating order status within database etc.
    if (!isProcessed($TxnId)) {
      # Send emails, generate invoices, update order status etc.
    }
  }
  else {
    $result1 = "The transaction was declined.";
  }

  print <<<HTMLEOF
  <html>
  <head>
  <title>Payment Express PxPost RE transaction result</title>
  </head>
  <body>
  <h1>Payment Express PxPost Re transaction result</h1>
  <p>$result1</p>
    <table border=1>
    <tr><th>Name</th>				<th>Value</th> </tr>
    <tr><td>Success</td>			<td>$Success</td></tr>
    <tr><td>TxnType</td>			<td>$TxnType</td></tr>
    <tr><td>Currency</td>		<td>$InputCurrency</td></tr>
    <tr><td>MerchantReference</td>	<td>$MerchantReference</td></tr>
    <tr><td>AuthCode</td>			<td>$AuthCode</td></tr>
    <tr><td>CardName</td>			<td>$CardName</td></tr>
    <tr><td>CardNumber</td>			<td>$CardNumber</td></tr>
    <tr><td>DateExpiry</td>			<td>$DateExpiry</td></tr>
    <tr><td>CardHolderName</td>		<td>$CardHolderName</td></tr>
    <tr><td>ClientInfo</td>			<td>$ClientInfo</td></tr>
    <tr><td>TxnId</td>				<td>$TxnId</td></tr>
    <tr><td>DpsTxnRef</td>			<td>$DpsTxnRef</td></tr>
    <tr><td>BillingId</td>			<td>$BillingId</td></tr>
    <tr><td>DpsBillingId</td>		<td>$DpsBillingId</td></tr>
    <tr><td>Amount</td>	<td>$Amount</td></tr>
    <tr><td>TxnMac</td>				<td>$TxnMac</td></tr>
    <tr><td>RecurringMode</td>		<td>$RecurringMode</td></tr>
  </table>
  </body>
  </html>
HTMLEOF;
}
function isProcessed($TxnId)
{
	# Check database if order relating to TxnId has alread been processed
	return false;
}
#******************************************************************************
# This function formats data into a request and redirects to the
# Payments Page.
#******************************************************************************
function repay_form()
{
  global $PxPost;
  $http_host   = getenv("HTTP_HOST");
  $request_uri = getenv("SCRIPT_NAME");
  $server_url  = "http://$http_host";

  # the following variables are read from the form
  $MerchantReference = 	"My PxPost test";
  //sprintf('%04x%04x-%04x-%04x',0f77,e01f,0da0,49ce | 0x4000);

  #Calculate AmountInput
  $AmountInput = 21.50;
  $TxnId = uniqid("ID"); 
  $DpsId = "0000110257164654884"; //get the dps id
  #rebill request
  $request2 = new PxPostRequest();
   
  $script_url = (version_compare(PHP_VERSION, "4.3.4", ">=")) ?"$server_url$request_uri" : "$server_url/$request_uri";
  
  $request2->setTxnType("Purchase");
  $request2->setInputCurrency("NZD");
  $request2->setAmount($AmountInput);
  $request2->setTxnId($TxnId);
  $request2->setMerchantReference($MerchantReference);
  $request2->setRecurringMode("recurring");
  $request2->setDpsBillingId($DpsId);
  $request2->setUrlFail($script_url);			# can be a dedicated failure page
  $request2->setUrlSuccess($script_url);			# can be a dedicated success page

  #Call makeRequest function to obtain input XML
  $request_string1 = $PxPost->makeRequest($request2);

  if ($request_string1) {
    print_reresult($request_string1);
  }

}
?>
