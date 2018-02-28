<?php

App::uses('AppModel', 'Model');

class Stix extends AppModel {
	public $useTable = 'stix';
	public $actsAs = array('Trim');

  public $hasMany = array(
    'StixRef' => array(
      'className' => 'StixRef',
      'foreignKey' => 'stix_id',
      'dependent'=> true,
      'order' => false
    ),
    'StixUuid' => array(
      'className' => 'StixUuid',
      'foreignKey' => 'stix_id',
      'dependent' => true,
      'order' => false
    ),
  );

	public $validate = array(

	);

  private $__taxiiPollUuids = array();
  private $__addedReferences = array();
  private $__xml = '';
  private $__ids = array();
  private $__idrefs = array();

  private $__setup = array();
  private $__unReferencedElements = array();
  private $__currentPackageOpen = false;
  private $__keyPackages = array();
  private $__shared_ns =
    'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL .
    'xmlns:taxii_11="http://taxii.mitre.org/messages/taxii_xml_binding-1.1"' . PHP_EOL .
    'xsi:schemaLocation="http://taxii.mitre.org/messages/taxii_xml_binding-1.1 http://taxii.mitre.org/messages/taxii_xml_binding-1.1"';





  private function __setDefaultHeaders($options = array()) {
    $default_headers = array(
      'Content-Type' => 'application/xml',
      'X-TAXII-Accept' => 'urn:taxii.mitre.org:message:xml:1.1',
      'X-TAXII-Content-Type' => 'urn:taxii.mitre.org:message:xml:1.1'
    );
    $this->__setup['header'] = $default_headers;
    if (isset($options['username']) && isset($options['password'])) {
      $this->__setup['header']['Authorization'] = 'Basic ' . base64_encode($options['username'] . ':' . $options['password']);
    }
    return true;
  }

  public function sendQuery($url, $body, $options = array()) {
    $options['url'] = $url;
    $options['body'] = $body;
    $this->__setDefaultHeaders($options);
    if (isset($options['callback'])) {
      $this->postCurl($options);
      return true;
    }
    return $this->postCurl($options);
  }


  private function __handleTaxiiPackage() {
    //$parser = xml_parser_create();
    //xml_set_element_handler($parser, "taxii_11:Content_Block", "taxii_11:Content_Block");
    $content_block_start = strpos($this->__xml, '<taxii_11:Content_Block');
    if (-1 == $content_block_start) {
      return false;
    }
    $temp = substr($this->__xml, $content_block_start);
    $reader = new XMLReader;
    $reader->XML($temp);
    $captured = false;
    while($reader->read()) {
      if ($reader->name == 'stix:STIX_Package') {
        $captured = trim($reader->readOuterXml());
        if (!empty($captured)) {
          $hash = sha1($reader->readInnerXml());
          break;
        }
      }
    }
    if (!empty($hash)) {
      $this->saveStixXml($captured, $hash);
    }
    $this->__xml = '';
    return true;
  }

  public function saveStixXml($xml, $hash) {
    $found = $this->find('first', array(
      'recursive' => -1,
      'conditions' => array('Stix.sha1' => $hash)
    ));
    preg_match_all('/idref=\".+([0-9a-f\-]{36}}?)\"/i', $xml, $idrefs);
    $idrefs = empty($idrefs[1]) ? array() : $idrefs[1];
    preg_match_all('/id=\".+([0-9a-f\-]{36}}?)\"/i', $xml, $ids);
    $ids = empty($ids[1]) ? array() : $ids[1];
    unset($ids[0]);
    $ids = array_values($ids);
    $stix_id = $this->capture($xml, $hash, true, 0);
    if (!in_array($stix_id, $this->__keyPackages)) $this->__keyPackages[] = $stix_id;
    if (!empty($ids)) {
      foreach ($ids as $id) {
        if (empty($this->__ids[$stix_id]) || !in_array($id, $this->__ids[$stix_id])) {
          $this->__ids[$stix_id][] = $id;
        }
        $this->StixUuid->capture($id, $stix_id);
      }
    }
    if (!empty($idrefs)) {
      foreach ($idrefs as $idref) {
        if (!in_array($idref, $this->__idrefs)) {
          $this->__idrefs[] = $idref;
        }
        $this->StixRef->capture($idref, $stix_id);
        $this->__removeRootFlag($idref);
      }
    }
    $this->__cullKeyPackages();
    return true;
  }

  private function __cullKeyPackages() {
    foreach ($this->__idrefs as $idref) {
      foreach ($this->__ids as $stix_id => $id) {
        if (in_array($idref, $id)) {
          $this->__keyPackages = array_diff($this->__keyPackages, array($stix_id));
        }
      }
    }
  }

  public function capture($xml, $hash, $root, $taxii_id) {
    $existingXml = $this->find('first', array(
      'conditions' => array(
        'sha1' => $hash
      ),
      'recursive' => -1,
      'fields' => array(
        'Stix.id'
      )
    ));
    if (!empty($existingXml)) {
      return $existingXml['Stix']['id'];
    }
    $this->create();
    $data = array(
      'xml' => gzcompress($xml),
      'sha1' => $hash,
      'root' => true,
      'taxii_id' => 0
    );
    if ($this->save($data)) {
      return $this->id;
    }
    return false;
  }

  private function __removeRootFlag($idref) {

  }

  public function handlePoll(&$ch, $chunk) {
    $this->__xml .= $chunk;
    $temp = trim($chunk);
    $len = strlen($temp);
    if (substr($temp, -25) == '</taxii_11:Content_Block>') {
      $this->__handleTaxiiPackage();
      $this->__currentPackageOpen = false;
    } else {
      $this->__currentPackageOpen = true;
    }
    return strlen($chunk);
  }

  public function postCurl($options = array()) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $options['url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $headers = array();
    foreach ($this->__setup['header'] as $k => $v) {
      $headers[] = $k . ': ' . $v;
    }
    if (!empty($headers)) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $proxy = Configure::read('Proxy');
    if (!empty($proxy['host']) && !empty($proxy['host'])) {
      curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
      curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
      if (!empty($proxy['user']) && !empty($proxy['password'])) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'] . ':' . $proxy['password']);
      }
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      $HttpSocket->configProxy($proxy['host'], $proxy['port'], $proxy['method'], $proxy['user'], $proxy['password']);
    }
    if (!empty($options['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
    if (!empty($options['callback'])) curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, $options['callback']));
    if (!empty($options['sslcert'])) {
      curl_setopt($ch, CURLOPT_SSLCERT, $options['sslcert']);
      if (!empty($options['sslcertpasswd'])) {
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $options['sslcertpasswd']);
      }
    }
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
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
            $this->__shared_ns . PHP_EOL .
            'message_id="' . $messageID . '" />';
    $response = $this->sendQuery($url, $xml, $options);
    $xmlArray = Xml::toArray(Xml::build($response));
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
          $this->__shared_ns . PHP_EOL .
          'message_id="' . $messageID . '" />' . PHP_EOL;
  $response = $this->sendQuery($url, $xml, $options);
  $xmlArray = Xml::toArray(Xml::build($response));
  return $xmlArray['Collection_Information_Response']['taxii_11:Collection'];
}

  public function poll($url, $feed, $options = array()) {
    $messageID = self::generateMessageID();
/*    $xml = array(
      '<taxii_11:Poll_Request xmlns:taxii="http://taxii.mitre.org/messages/taxii_xml_binding-1" xmlns:taxii_11="http://taxii.mitre.org/messages/taxii_xml_binding-1.1" xmlns:tdq="http://taxii.mitre.org/query/taxii_default_query-1" message_id="a0784cd6-13d9-4395-889b-8614e7ca55a4" collection_name="guest.Abuse_ch">',
      '<taxii_11:Poll_Parameters allow_asynch="false">',
      '<taxii_11:Response_Type>FULL</taxii_11:Response_Type>',
      '</taxii_11:Poll_Parameters>',
      '</taxii_11:Poll_Request>'
    );*/
    $xml = array(
      '<taxii_11:Poll_Request xmlns:taxii="http://taxii.mitre.org/messages/taxii_xml_binding-1" xmlns:taxii_11="http://taxii.mitre.org/messages/taxii_xml_binding-1.1" xmlns:tdq="http://taxii.mitre.org/query/taxii_default_query-1" message_id="' . $messageID . '" collection_name="' . $feed . '">',
      '<taxii_11:Poll_Parameters allow_asynch="false">',
      '<taxii_11:Response_Type>FULL</taxii_11:Response_Type>',
      '</taxii_11:Poll_Parameters>',
      '<taxii_11:Exclusive_Begin_Timestamp>2018-01-17T00:27:59Z</taxii_11:Exclusive_Begin_Timestamp>',
      '<taxii_11:Inclusive_End_Timestamp>2018-01-18T15:00:00Z</taxii_11:Inclusive_End_Timestamp>',
      '</taxii_11:Poll_Request>'
    );
    /*
    $xml = array(
      '<?xml version="1.0" encoding="UTF-8" ?>',
      '<taxii_11:Poll_Request',
      $this->__shared_ns,
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
    $options['callback'] = 'handlePoll';
    $response = $this->sendQuery($url, $xml, $options);
    if ($response) {
      foreach ($this->__keyPackages as $retrievedXml) {
        
      }
    }
    debug($this->__keyPackages);
    throw new Exception();
    return $response;
  }
}
