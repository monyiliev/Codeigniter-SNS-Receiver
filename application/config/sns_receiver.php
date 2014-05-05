<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| AWS SNS Receiver
|--------------------------------------------------------------------------
*/

/**
 * Log to file
 */
$config['debug'] = true;

/**
 * Verify by topic restriction
 */
$config['restrictByTopic'] = true;

/**
 * Enter the allowed topic
 */
$config['allowedTopic'] = 'arn:aws:sns:us-east-1:123456789012:MyTopic'; // Add the correct topic

/**
 * Should we verify the certificate?
 * For security you could (should) validate the certificate, this does add an additional time demand on the system.
 * NOTE: This also checks the origin of the certificate to ensure messages are signed by the AWS SNS SERVICE.
 * Since the allowed topicArn is part of the validation data, this ensures that your request originated from
 * the service, not somewhere else, and is from the topic you think it is, not something spoofed.
 */
$config['verifyCertificate'] = true;

/**
 * Is the post coming from the correct origin?
 */
$config['sourceDomain'] = 'sns.us-east-1.amazonaws.com'; // Add the correct origin

/* End of file sns_receiver.php */
/* Location: ./application/account/config/sns_receiver.php */