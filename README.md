sns_receiver
============

A Library to handle SNS HTTP(S) responses in Codeigniter

This library will handle the json messages received by [AWS SNS](http://aws.amazon.com/sns/). The initial idea was addopted from [Nathan Flood](https://github.com/npflood)'s [AWS-SNS-HTTP-PHP-ENDPOINT](https://github.com/npflood/AWS-SNS-HTTP-PHP-ENDPOINT) and modified to be used with Codeigniter 2.1.3.

Current features include:
 - Verify Certificate Origin
 - Compare Data Signature to Certificate
 - Respond to Subscription Request
 - Receive Notifications and return the raw JSON to be used in a controller
 - Log debug messages
 
## Usage:
### Create an SNS Subscription
- Go to your AWS account (or create one) and go to SNS (Amazon Simple Notification Service). 
- Create a new HTTP(S) Subscription or use an existing one
*TopicARN must be a unique name such us my-subscription-service and Endpoint is the http(s) where SNS will post the notification e.g.: http://example.com/webhooks/sns_notifications*
- Subscribe a service to use SNS such as [SES](http://aws.amazon.com/ses/)

### Install
- Add application/libraries/sns_receiver.php to application/libraries and application/config/sns_receiver.php to application/config
- Create a controller class with a method to handle the notifications and adjust routes as necessary.
- Fill out the necessary config parameters (Strongly recommend leaving verifyCertificate as true).
- Load the sns_receiver library and assign the response to a var or use as necessary. Basic sample usege could be:
```php
    	$json = file_get_contents('php://input');
    	$this->load->library('sns_receiver');
        $res = $this->sns_receiver->handle_response($json);
      // Do whatever is necessary with $res
```

### Dry run test
If necessary you may use curl to dry run and test the usage. For instance, if you have subscribed to receive SES bounces and complaints send a json via curl to your endpoint:
```bash
curl -X POST -d @snsTest.txt http://example.com/ebhooks/sns_notifications --header "Content-Type:application/json"
```
