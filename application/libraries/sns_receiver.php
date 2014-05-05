<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/**
 * SNS Recever
 * Handle HTTP(S) SNS message in Codeigniter
 *
 * LICENSE: MIT
 *
 * AUTHOR: Simeon Iliev monyiliev@gmail.com
 *
 * CREDITS: Nathan Flood https://github.com/npflood
 *
 * PHP Version: 5.2+
 *
 * This library will handle the json messages received by AWS SNS http://aws.amazon.com/sns/. The initial idea was addopted from Nathan's https://github.com/npflood/AWS-SNS-HTTP-PHP-ENDPOINT and modified to be used with Codeigniter 2.1.3.
 * Current features include:
 * - Verify Certificate Origin
 * - Compare Data Signature to Certificate
 * - Respond to Subscription Request
 * - Receive Notifications and return the raw JSON to be used in a controller
 * - Log debug messages
 */

class sns_receiver {
	/**
	 * CONFIGURATION
	 */
	
	private $debug;
	private $restrictByTopic;
	private $allowedTopic;
	private $verifyCertificate;
	private $sourceDomain;
	private $json;

	/**
	 * CodeIgniter super object and load the config
	 */
	public function __construct() {
		$this->CI =& get_instance();
		$this->CI->config->load('sns_receiver');
		$this->debug = $this->CI->config->item('debug');
		$this->restrictByTopic = $this->CI->config->item('restrictByTopic');
		$this->allowedTopic = $this->CI->config->item('allowedTopic');
		$this->verifyCertificate = $this->CI->config->item('verifyCertificate');
		$this->sourceDomain = $this->CI->config->item('sourceDomain');
	}
	

	/**
	 * OPERATION
	 */

	/**
	 * @var boolean
	 */
	private $signatureValid = false;

	/**
	 * Are Security Criteria Set Above Met? Changed programmatically to false on any security failure.
	 * @var boolean
	 */
	private $safeToProcess = true;


	/**
	 * Handle post data received from AWS SNS
	 * @param  JSON $json Get the raw post data from the request. This is the best-practice method as it does not rely on special php.ini directives like $HTTP_RAW_POST_DATA. Amazon SNS sends a JSON object as part of the raw post body.
	 * @return json or bool false
	 */
	
	public function handle_response($json) {
		$this->json = json_decode($json);
		/**
		 * Check for Restrict By Topic
		 */
		if($this->restrictByTopic){
			if($this->allowedTopic != $this->json->TopicArn){
				$this->logToFile('debug', 'Allowed Topic ARN: ' . $this->allowedTopic . ' DOES NOT MATCH Calling Topic ARN: ' . $this->json->TopicArn);
				$this->safeToProcess = false;
			}
		}

		/**
		 * Check For Certificate Source
		 * @var string
		 */
		$domain = $this->getDomainFromUrl($this->json->SigningCertURL);
		if($domain != $this->sourceDomain){
			$this->logToFile('debug', 'Key domain: ' . $domain . ' is not equal to allowed source domain:' . $this->sourceDomain);
			$this->safeToProcess = false;
		}

		/**
		 * Verify Certificate
		 * NOTE: Strongly recommend to leave this option as true in the config
		 */
		if($this->verifyCertificate){
			
			/**
			 * Build Up The String That Was Originally Encoded With The AWS Key So You Can Validate It Against Its Signature.
			 * @var string
			 */
			if ($this->json->Type == 'SubscriptionConfirmation') {
				$validationString = "";
				$validationString .= "Message\n";
				$validationString .= $this->json->Message . "\n";
				$validationString .= "MessageId\n";
				$validationString .= $this->json->MessageId . "\n";
				$validationString .= "SubscribeURL\n";
				$validationString .= $this->json->SubscribeURL . "\n";
				$validationString .= "Timestamp\n";
				$validationString .= $this->json->Timestamp . "\n";
				$validationString .= "Token\n";
				$validationString .= $this->json->Token . "\n";
				$validationString .= "TopicArn\n";
				$validationString .= $this->json->TopicArn . "\n";
				$validationString .= "Type\n";
				$validationString .= $this->json->Type . "\n";
			} else {
				$validationString = "";
				$validationString .= "Message\n";
				$validationString .= $this->json->Message . "\n";
				$validationString .= "MessageId\n";
				$validationString .= $this->json->MessageId . "\n";
				if (isset($this->json->Subject)) {
					$validationString .= "Subject\n";
					$validationString .= $this->json->Subject . "\n";
				}
				$validationString .= "Timestamp\n";
				$validationString .= $this->json->Timestamp . "\n";
				$validationString .= "TopicArn\n";
				$validationString .= $this->json->TopicArn . "\n";
				$validationString .= "Type\n";
				$validationString .= $this->json->Type . "\n";
			}
			
			$this->logToFile('debug', 'Data Validation String:' . $validationString);
			
			$this->signatureValid = $this->validateCertificate($this->json->SigningCertURL, $this->json->Signature, $validationString);
			
			if(!$this->signatureValid){
				$this->logToFile('debug', 'Data and Signature Do No Match Certificate or Certificate Error.');
				$this->safeToProcess = false;
			}else{
				$this->logToFile('debug', 'Data Validated Against Certificate.');
			}
		}

		if($this->safeToProcess){

			/**
			 * Respond to the Subscription Request Confirmation Programmatically by calling the url
			 * @var string
			 */
			if($this->json->Type == 'SubscriptionConfirmation'){
				
				$this->logToFile('debug', $this->json->SubscribeURL);
				
				$curl_handle = curl_init();
				curl_setopt($curl_handle, CURLOPT_URL, $this->json->SubscribeURL);
				curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
				curl_exec($curl_handle);
				curl_close($curl_handle);	
			}
			
			
			/**
			 * Handle a Notification Programmatically
			 * @var string
			 */
			if($this->json->Type == 'Notification'){
				//Do what you want with the data here.
				$this->logToFile('debug', 'Type: ' . $this->json->Type . ' Message: ' . $this->json->Message);
				return $this->json;
			}
		}

		return false;
	}

	/**
	 * Log debug messages if $debug = true;
	 * $level could be: debug, error, info
	 * @param  string $level could be: debug, error, info
	 * @param  string $message a descriptive message
	 */
	private function logToFile($level, $message)
	{
		if($this->debug == true){
			log_message($level, 'SNS: ' . $message);
		}
	}

	/**
	 * A Function that takes the key file, signature, and signed data and tells us if it all matches.
	 * @param  string $keyFileURL
	 * @param  string $signatureString
	 * @param  string $data This is the regenerated validationString
	 * @return bool
	 */
	function validateCertificate($keyFileURL, $signatureString, $data){
		
		$signature = base64_decode($signatureString);
		
		
		// fetch certificate from file and ready it
		$fp = fopen($keyFileURL, 'r');
		$cert = fread($fp, 8192);
		fclose($fp);
		
		$pubkeyid = openssl_get_publickey($cert);
		
		$ok = openssl_verify($data, $signature, $pubkeyid, OPENSSL_ALGO_SHA1);
		
		
		if ($ok == 1) {
		    return true;
		} elseif ($ok == 0) {
		    return false;
		} else {
		    return false;
		}	
	}

	/**
	 * A Function that takes a URL String and returns the domain portion only
	 * @param  string $urlString The string from the json that contains the domain (SigningCertURL)
	 * @return string Returns the sourceDomain string to be verified against or ERROR
	 */
	function getDomainFromUrl($urlString){
		$domain = '';
		$urlArray = parse_url($urlString);
		
		if($urlArray == false){
			$domain = 'ERROR';
		}else{
			$domain = $urlArray['host'];
		}
		
		return $domain;
	}
}
?>
