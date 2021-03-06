<?php
/*
 * BbPHP: Blackboard Web Services Library for PHP
 * Copyright (C) 2011 by St. Edward's University (www.stedwards.edu)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 * BbPHP: Blackboard Web Services Library for PHP
 * Copyright (C) 2011 by St. Edward's University (www.stedwards.edu)
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE. 
 */ 
namespace Pulsestorm\Blackboard\Soap\Legacy; 
use DOMDocument;
use Exception;
class Bbphp {
	protected $session_id   = null;
	private $services       = array('Announcement', 'Calendar', 'Content', 'Context', 'Course', 'CourseMembership', 'Gradebook', 'NotificationDistributorOperations', 'User', 'Util');
	public $url             = null;
	public $use_curl        = true;
	protected $log          = false;		
	
	public function __construct($url = null, $use_curl = true) {
		$this->url = $url;
		$this->use_curl = $use_curl;
		$this->session_id = $this->Context("initialize");
	}
	
	public function setLog($value)
	{
	    $this->log = $value;
	    return $this;
	}
	
	protected function buildHeader() {
		$stamp = gmdate("Y-m-d\TH:i:s\Z");
		
		if ($this->session_id == null) {
			$password = 'nosession';
		} else {
			$password = $this->session_id;
		}
		
		/**
		 * This header is sensitive to line breaks and you should avoid
		 * letting things like Eclipse do auto-formatting on it.    
		 * 
		 **/
		$header = <<<END
		<SOAP-ENV:Header>
	        <wsse:Security SOAP-ENV:mustunderstand="true" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
	            <wsse:Timestamp xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
	                <wsse:Created xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">$stamp</wsse:Created>
	            </wsse:Timestamp>
	            <wsse:UsernameToken xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
	                <wsse:Username xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">session</wsse:Username>
	                <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">$password</wsse:Password>
	            </wsse:UsernameToken>
	        </wsse:Security>
	    </SOAP-ENV:Header>
END;
		return $header;
	}
	
	protected function buildRequest($method = null, $service, $args = null) {
		$header = $this->buildHeader();
		
        $class = 'Pulsestorm\Blackboard\Soap\Legacy\\' . $service;
		$serviceObject = new $class();

		$body = $serviceObject->$method($args);

		$request = '<SOAP-ENV:Envelope xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
		$request .= $header.$body;
		$request .= '</SOAP-ENV:Envelope>';

		return $request;
	}
	
	/**
	 * The call() magic method is used here to access methods from BB that have not been defined in the class.
	 * In theory you can access any method that the BB object provides if you have added it to your "Context". 
	 */
	public function __call($method, $args) {
		if (!isset($args[1])) {
			$args[1] = null;
		}
		
		if (in_array($method, $this->services)) {
			return $this->doCall($args[0], $method, $args[1]);
		} else {
			return $this->doCall($method, $args[0], $args[1]);
		}
	}
	
	public function doCall($method = null, $service = "Context", $args = null) {
		
		$request = $this->buildRequest($method, $service, $args);
        $this->log($request);
        
		if ($this->use_curl) {
			$ch = curl_init();
				
			curl_setopt($ch, CURLOPT_URL, $this->url . '/webapps/ws/services/' . $service . '.WS');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml; charset=utf-8', 'SOAPAction: "' . $method . '"'));
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);
			curl_close($ch);	
		} else {
			$result = $this->doPostRequest($this->url . '/webapps/ws/services/' . $service . '.WS', $request, "Content-type: text/xml; charset=utf-8\nSOAPAction: \"" . $method . "\"");
		}

        $this->log($result);

		$result_array = $this->xmlstr_to_array($result);

		$final_result = (isset($result_array['Body'][$method . 'Response']['return'])) ? $result_array['Body'][$method . 'Response']['return'] : null;
		return $final_result;
	}	
	
	/*
	 * This function can be found here:
	 * http://wezfurlong.org/blog/2006/nov/http-post-from-php-without-curl/
	 */
	protected function doPostRequest($url, $data, $optional_headers = null) {
		$params = array('http' => array(
		            'method' => 'POST',
		            'content' => $data
		          ));
		if ($optional_headers !== null) {
			$params['http']['header'] = $optional_headers;
		}
		$ctx = stream_context_create($params);
		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp) {
			throw new Exception("Problem with $url, $php_errormsg");
		}
		$response = @stream_get_contents($fp);
		if ($response === false) {
			throw new Exception("Problem reading data from $url, $php_errormsg");
		}
		return $response;
	}	

	function xmlstr_to_array($xmlstr) {
		$doc = new DOMDocument();
		$doc->loadXML($xmlstr);
		return $this->domnode_to_array($doc->documentElement);
	}
	
	function domnode_to_array($node) {
		$output = array();
		
		switch ($node->nodeType) {
			case XML_CDATA_SECTION_NODE:
			case XML_TEXT_NODE:
				$output = trim($node->textContent);
				break;
			case XML_ELEMENT_NODE:
				for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
					$child = $node->childNodes->item($i);
					$v = $this->domnode_to_array($child);
					if(isset($child->tagName)) {
						$t = $child->tagName;
						if (strpos($t, ':') !== false) {
							$temp = explode(':', $t);
							$key = $temp[1];
						} else {
							$key = $t;
						}
						$test = substr($t, strpos($t, ':') + 1, strlen($t) - strpos($t, ':') + 1);
						if(!isset($output[$key])) {
							$output[$key] = array();
						}
						$output[$key][] = $v;
					} elseif($v) {
						$output = (string) $v;
					}
				}	

				if(is_array($output)) {
					if($node->attributes->length) {
						$a = array();
						foreach($node->attributes as $attrName => $attrNode) {
							$a[$attrName] = (string) $attrNode->value;
						}
						$output['@attributes'] = $a;
					}
					foreach ($output as $t => $v) {
						if(is_array($v) && count($v)==1 && $t!='@attributes') {
							$output[$t] = $v[0];
						}
					}
				}
			break;
		}
		
		return $output;
	}	

	public function log($string)
	{
	    if(!$this->log) { return; }
	    echo $string,"\n";
	}
	
}
?>
