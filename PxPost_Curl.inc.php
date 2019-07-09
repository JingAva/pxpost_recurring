<?php

#******************************************************************************
#* Name          : PxPost_Curl.inc.php
#* Description   : Classes used interact with the PxPost interface using PHP with the cURL extension installed
#* Copyright	 : Payment Express 2017(c)
#* Date          : 2017-04-10
#*@version 		 : 2.0
#* Author 		 : Payment Express DevSupport
#******************************************************************************
# Use this class to parse an XML document
class MifMessage
{
  var $xml_;
  var $xml_index_;
  var $xml_value_;

  # Constructor:
  # Create a MifMessage with the specified XML text.
  # The constructor returns a null object if there is a parsing error.
  function __construct($xml)
  {
    $p = xml_parser_create();
    xml_parser_set_option($p,XML_OPTION_CASE_FOLDING,0);
    $ok = xml_parse_into_struct($p, $xml, $value, $index);
		xml_parser_free($p);
    if ($ok)
    {
      $this->xml_ = $xml;
      $this->xml_value_ = $value;
      $this->xml_index_ = $index;
    }
  }

  # Return the value of the specified top-level attribute.
  # This method can only return attributes of the root element.
  # If the attribute is not found, return "".
  function get_attribute($attribute)
  {
    $attributes = $this->xml_value_[0]["attributes"];
    return $attributes[$attribute];
  }

  # Return the text of the specified element.
  # The element is given as a simplified XPath-like name.
  # For example, "Link/ServerOk" refers to the ServerOk element
  # nested in the Link element (nested in the root element).
  # If the element is not found, return "".
  function get_element_text($element, $rootindex = 1 )
  {
    $index = $this->get_element_index($element, $rootindex);
    if ($index == 0)
    {
      return "";
    }
    else
    {
	#When element existent but empty
    $elementObj = $this->xml_value_[$index];
    if (! array_key_exists("value", $elementObj))
      return "";

    return $this->xml_value_[$index]["value"];
    }
  }

  # (internal method)
  # Return the index of the specified element,
  # relative to some given root element index.
  #
  function get_element_index($element, $rootindex)
  {
    #$element = strtoupper($element);
    $pos = strpos($element, "/");
    if ($pos !== false)
    {
      # element contains '/': find first part
      $start_path = substr($element,0,$pos);
      $remain_path = substr($element,$pos+1);
      $index = $this->get_element_index($start_path, $rootindex);
      if ($index == 0)
      {
        # couldn't find first part give up.
        return 0;
      }
      # recursively find rest
      return $this->get_element_index($remain_path, $index);
    }
    else
    {
      # search from the parent across all its children
      # i.e. until we get the parent's close tag.
      $level = $this->xml_value_[$rootindex]["level"];
      if ($this->xml_value_[$rootindex]["type"] == "complete")
      {
        return 0;   # no children
      }
      $index = $rootindex+1;
      while ($index<count($this->xml_value_) &&
             !($this->xml_value_[$index]["level"]==$level &&
               $this->xml_value_[$index]["type"]=="close"))
      {
        # if one below parent and tag matches, bingo
        if ($this->xml_value_[$index]["level"] == $level+1 &&
            $this->xml_value_[$index]["tag"] == $element)
        {
          return $index;
        }
        $index++;
      }
      return 0;
    }
  }
}

class PxPost_Curl
{
	var $PxPost_Password;
	var $PxPost_Url;
	var $PxPost_Username;
	function __construct($Url, $UserId, $UserPassword){
		error_reporting(E_ERROR);
		$this->PxPost_Password = $UserPassword;
		$this->PxPost_Url = $Url;
		$this->PxPost_Username = $UserId;
	}

	#******************************************************************************
	# Create a request for the PxPost interface
	#******************************************************************************
	function makeRequest($request)
	{
		#Validate the Request
		if($request->validData() == false) return "" ;

		$request->setUserId($this->PxPost_Username);
		$request->setPassword($this->PxPost_Password);

		$xml = $request->toXml();
		$result1 = $this->submitXml($xml);
		
		return $result1;

	}

	#******************************************************************************
	# Return the transaction outcome details
	#******************************************************************************
	function getResponse($result1){

		$pxresp = new PxPostResponse($result1);
		return $pxresp;
	}

	#******************************************************************************
	# Actual submission of XML using cURL. Returns output XML
	#******************************************************************************
	function submitXml($inputXml){

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->PxPost_Url);

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$inputXml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);


		$outputXml = curl_exec ($ch);

		curl_close ($ch);

		return $outputXml;
	}

}

#******************************************************************************
# Class for PxPost request messages.
#******************************************************************************
class PxPostRequest extends PxPostMessage
{
	var $UrlFail,$UrlSuccess;
	var $Amount;
	var $PostUsername;
	var $PostPassword;

	#Constructor
 	function __construct(){
		parent::__construct();

	}

	
	function setUrlFail($UrlFail){
		$this->UrlFail = $UrlFail;
	}
	function setUrlSuccess($UrlSuccess){
		$this->UrlSuccess = $UrlSuccess;
	}
	function setAmount($Amount){
		$this->Amount = sprintf("%9.2f",$Amount);
	}
	function setUserId($UserId){
		$this->PostUsername = $UserId;
	}

	function setPassword($UserPassword){
		$this->PostPassword = $UserPassword;
	}

	#******************************************************************
	#Data validation
	#******************************************************************
	function validData(){
		$msg = "";
		if($this->TxnType != "Purchase")
			if($this->TxnType != "Auth")
				$msg = "Invalid TxnType[$this->TxnType]<br>";

		if(strlen($this->MerchantReference) > 64)
			$msg = "Invalid MerchantReference [$this->MerchantReference]<br>";

		if(strlen($this->TxnId) > 16)
			$msg = "Invalid TxnId [$this->TxnId]<br>";
		if(strlen($this->TxnData1) > 255)
			$msg = "Invalid TxnData1 [$this->TxnData1]<br>";
		if(strlen($this->TxnData2) > 255)
			$msg = "Invalid TxnData2 [$this->TxnData2]<br>";
		if(strlen($this->TxnData3) > 255)
			$msg = "Invalid TxnData3 [$this->TxnData3]<br>";

		if(strlen($this->EmailAddress) > 255)
			$msg = "Invalid EmailAddress [$this->EmailAddress]<br>";

		if(strlen($this->UrlFail) > 255)
			$msg = "Invalid UrlFail [$this->UrlFail]<br>";
		if(strlen($this->UrlSuccess) > 255)
			$msg = "Invalid UrlSuccess [$this->UrlSuccess]<br>";
		if(strlen($this->BillingId) > 32)
			$msg = "Invalid BillingId [$this->BillingId]<br>";

		if ($msg != "") {
		    trigger_error($msg,E_USER_ERROR);
			return false;
		}
		return true;
	}

}

#******************************************************************************
# Abstract base class for PxPost messages.
# These are messages with certain defined elements,  which can be serialized to XML.

#******************************************************************************
class PxPostMessage {
	var $TxnType;
	var $InputCurrency;
	var $MerchantReference;
	var $TxnId;
	var $DpsBillingId;
	var $RecurringMode;

	function __construct(){

	}
	function setDpsBillingId($DpsBillingId){
		$this->DpsBillingId = $DpsBillingId;
	}
	function setTxnType($TxnType){
		$this->TxnType = $TxnType;
	}
	function getTxnType(){
		return $this->TxnType;
	}
	function setInputCurrency($InputCurrency){
		$this->InputCurrency = $InputCurrency;
	}
	function getInputCurrency(){
		return $this->InputCurrency;
	}
	function setMerchantReference($MerchantReference){
		$this->MerchantReference = $MerchantReference;
	}
	function getMerchantReference(){
		return $this->MerchantReference;
	}
	function setTxnId( $TxnId)
	{
		$this->TxnId = $TxnId;
	}
	function getTxnId(){
		return $this->TxnId;
	}
	function setRecurringMode($RecurringMode){
		$this->RecurringMode = $RecurringMode;
	}
	function getRecurringMode(){
		return $this->RecurringMode;
	}

	function toXml(){
		$arr = get_object_vars($this);
		
		$xml  = "<Txn>";
    	while (list($prop, $val) = each($arr))
        	$xml .= "<$prop>$val</$prop>" ;
		$xml .= "</Txn>";
		
		return $xml;
	}


}

#******************************************************************************
# Class for PxPost response messages.
#******************************************************************************

class PxPostResponse extends PxPostMessage
{
	var $Success;
	var $AuthCode;
	var $CardName;
	var $CardHolderName;
	var $CardNumber;
	var $DateExpiry;
	var $ClientInfo;
	var $DpsTxnRef;
  var $DpsBillingId;
	var $Amount;
	var $Currency;
	var $TxnMac;
	var $ResponseText;
	var $BillingId;
	var $TxtType;


	function __construct($xml){
		$msg = new MifMessage($xml);
		parent::__construct();

		$this->Success = $msg->get_element_text("Success", 0);
		$this->setTxnType($msg->get_element_text("TxnType"));
		$this->InputCurrency = $msg->get_element_text("InputCurrencyName");
		$this->setMerchantReference($msg->get_element_text("MerchantReference"));
		$this->AuthCode = $msg->get_element_text("AuthCode");
		$this->CardName = $msg->get_element_text("CardName");
		$this->CardHolderName = $msg->get_element_text("CardHolderName");
		$this->CardNumber = $msg->get_element_text("CardNumber");
		$this->DateExpiry = $msg->get_element_text("DateExpiry");
		$this->ClientInfo = $msg->get_element_text("ClientInfo");
		$this->TxnId = $msg->get_element_text("TxnId");
		$this->DpsTxnRef = $msg->get_element_text("DpsTxnRef", 0);
		$this->BillingId = $msg->get_element_text("BillingId");
		$this->DpsBillingId = $msg->get_element_text("DpsBillingId");
		$this->Amount = $msg->get_element_text("Amount");
		$this->Currency = $msg->get_element_text("Currency");
		$this->TxnMac = $msg->get_element_text("TxnMac");
		$this->ResponseText = $msg->get_element_text("ResponseText");
		$this->RecurringMode = $msg->get_element_text("RecurringMode");

	}

	function getBillingId($BillingId){
		return $this->BillingId;
	}

	function getSuccess(){
		return $this->Success;
	}
	function getAuthCode(){
		return $this->AuthCode;
	}
	function getCardName(){
		return $this->CardName;
	}
	function getCardHolderName(){
		return $this->CardHolderName;
	}
	function getCardNumber(){
		return $this->CardNumber;
	}
	function getDateExpiry(){
		return $this->DateExpiry;
	}
	function getClientInfo(){
		return $this->ClientInfo;
	}
	function getDpsTxnRef(){
		return $this->DpsTxnRef;
	}
	function getDpsBillingId(){
		return $this->DpsBillingId;
	}
	function getAmount(){
		return $this->Amount;
	}
	function getCurrency(){
		return $this->Currency;
	}
	function getTxnMac(){
		return $this->TxnMac;
	}
	function getResponseText(){
		return $this->ResponseText;
	}
}

?>