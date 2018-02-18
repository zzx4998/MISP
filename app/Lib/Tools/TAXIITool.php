<?php
class TAXIITool {
  private $__request = array();

  private $__namespaces =
    'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL .
    'xmlns:taxii_11="http://taxii.mitre.org/messages/taxii_xml_binding-1.1"' . PHP_EOL .
    'xsi:schemaLocation="http://taxii.mitre.org/messages/taxii_xml_binding-1.1 http://taxii.mitre.org/messages/taxii_xml_binding-1.1"';

  private function __setDefaultHeaders($options = array()) {
    $default_headers = array(
      'Content-Type' => 'application/xml',
      'Accept' => 'application/xml',
      'X-TAXII-Accept' => 'urn:taxii.mitre.org:message:xml:1.1',
      'X-TAXII-Content-Type' => 'urn:taxii.mitre.org:message:xml:1.1',
      'X-TAXII-Protocol' => 'urn:taxii.mitre.org:protocol:https:1.0'
    );
    $default_headers = array(
      'Content-Type' => 'application/xml',
      'X-TAXII-Accept' => 'urn:taxii.mitre.org:message:xml:1.1',
      'X-TAXII-Content-Type' => 'urn:taxii.mitre.org:message:xml:1.1'
    );
    $this->__request['header'] = $default_headers;
    if (isset($options['username']) && isset($options['password'])) {
      $this->__request['header']['Authorization'] = 'Basic ' . base64_encode($options['username'] . ':' . $options['password']);
    }
    return true;
  }

  public function sendQuery($url, $body, $options = array()) {
    $HttpSocket = $this->setupHttpSocket();
    $this->__setDefaultHeaders();
    $this->__request['body'] = $body;
    $response = $HttpSocket->post($url, false, $this->__request);
    return $response;
  }

  public function setupHttpSocket() {
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket();
		$proxy = Configure::read('Proxy');
		if (isset($proxy['host']) && !empty($proxy['host'])) {
      $HttpSocket->configProxy($proxy['host'], $proxy['port'], $proxy['method'], $proxy['user'], $proxy['password']);
    }
		return $HttpSocket;
	}

  private static function generateMessageID() {
    if (function_exists('openssl_random_pseudo_bytes')) {
      $id = bin2hex(openssl_random_pseudo_bytes(32));
    } else if (function_exists('mt_rand')) {
      $id = bin2hex(decbin(mt_rand()));
    } else {
      $id = bin2hex(decbin(rand()));
    }
    return $id;
  }


  public function discover($url, $options = array()) {
    $messageID = self::generateMessageID();
    $xml =  '<?xml version="1.0" encoding="UTF-8" ?>' . PHP_EOL .
            '<taxii_11:Discovery_Request' . PHP_EOL .
            $this->__namespaces . PHP_EOL .
            'message_id="' . $messageID . '" />';
    $response = $this->sendQuery($url, $xml, $options);
    $xmlArray = Xml::toArray(Xml::build($response->body));
    return $xmlArray['Discovery_Response']['taxii_11:Service_Instance'];
    foreach ($xmlArray['Discovery_Response']['taxii_11:Service_Instance'] as $service) {
      $services[] = array(
        'service_type' => $service['@service_type'],
        'protocol_binding' => $service['taxii_11:Protocol_Binding'],
        'address' => $service['taxii_11:Address'],
        'message_binding' => $service['taxii_11:Message_Binding'],
        'message' => $service['taxii_11:Message']
      );
    }
    return $services;
  }

  public function getCollectionInfo($url, $options = array()) {
  $messageID = self::generateMessageID();
  $xml =  '<?xml version="1.0" encoding="UTF-8" ?>' . PHP_EOL .
          '<taxii_11:Collection_Information_Request' . PHP_EOL .
          $this->__namespaces . PHP_EOL .
          'message_id="' . $messageID . '" />' . PHP_EOL;
  $response = $this->sendQuery($url, $xml, $options);
  $xmlArray = Xml::toArray(Xml::build($response->body));
  return $xmlArray['Collection_Information_Response']['taxii_11:Collection'];
}

  public function poll($url, $feed, $options = array()) {
    $messageID = self::generateMessageID();
    $xml = array(
      '<taxii_11:Poll_Request xmlns:taxii="http://taxii.mitre.org/messages/taxii_xml_binding-1" xmlns:taxii_11="http://taxii.mitre.org/messages/taxii_xml_binding-1.1" xmlns:tdq="http://taxii.mitre.org/query/taxii_default_query-1" message_id="a0784cd6-13d9-4395-889b-8614e7ca55a4" collection_name="guest.dshield_BlockList">',
      '<taxii_11:Poll_Parameters allow_asynch="false">',
      '<taxii_11:Response_Type>FULL</taxii_11:Response_Type>',
      '</taxii_11:Poll_Parameters>',
      '</taxii_11:Poll_Request>'
    );
    /*
    $xml = array(
      '<?xml version="1.0" encoding="UTF-8" ?>',
      '<taxii_11:Poll_Request',
      $this->__namespaces,
      'message_id="' . $messageID . '"',
      'collection_name="' . $feed . '">',
      '<taxii_11:Poll_Parameters allow_asynch="false">',
      '<taxii_11:Response_Type>COUNT_ONLY</taxii_11:Response_Type>',
      '<taxii_11:Content_Binding binding_id="urn:stix.mitre.org:xml:1.1.1" />',
      '</taxii_11:Poll_Parameters>',
      '</taxii_11:Poll_Request>'
    );
    */
    $xml = implode(PHP_EOL, $xml);
    $response = $this->sendQuery($url, $xml, $options);
    debug($response->body);
    throw new Exception();
    return $response;
  }
}
