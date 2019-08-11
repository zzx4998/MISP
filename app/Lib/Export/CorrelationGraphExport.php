<?php

class CorrelationGraphExport
{
    public $additional_params = array(
        'includeGranularCorrelations' => 1
    );
    public $non_restrictive_export = true;

    private $__nodes = array();
    private $__edges = array();
    private $__lookupTables = array();

    public function handler($data, $options = array())
    {
        $eventModel = ClassRegistry::init('Event');
        $this->__lookupTables = array(
            'analysisLevels' => $eventModel->analysisLevels,
            'distributionLevels' => $eventModel->Attribute->distributionLevels
        );
        $this->__convertEvent($data);
        return '';
    }

    private function __checkAndAddNode($node)
    {;
        foreach ($this->__nodes as $k => $existingNode) {
            if ($existingNode['unique_id'] === $node['unique_id']) {
                if (!empty($node['expanded'])) {
                    $this->__nodes[$k]['expanded'] = true;
                }
                return true;
            }
        }
        $this->__nodes[] = $node;
        return true;
    }

    private function __convertEvent($event, $expanded = true)
    {
        $orgc = empty($event['Orgc']) ? $event['Event']['Orgc'] : $event['Orgc'];
        if ($this->orgImgExists($orgc['name'])) {
            $image = Configure::read('MISP.baseurl') . '/img/orgs/' . h($orgc['name']) . '.png';
        } else {
            $image = Configure::read('MISP.baseurl') . '/img/orgs/MISP.png';
        }
        $node = array(
          'unique_id' => 'event-' . $event['Event']['id'],
          'name' => '(' . $event['Event']['id'] . ') ' . (strlen($event['Event']['info']) > 32 ? substr($event['Event']['info'], 0, 31) . '...' : $event['Event']['info']),
          'type' => 'event',
          'id' => $event['Event']['id'],
          'expanded' => $expanded,
          'uuid' => $event['Event']['uuid'],
          'image' => $image,
          'info' => $event['Event']['info'],
          'org' => $orgc['name'],
          'analysis' => $this->__lookupTables['analysisLevels'][$event['Event']['analysis']],
          'distribution' => $this->__lookupTables['distributionLevels'][$event['Event']['distribution']],
          'date' => $event['Event']['date']
        );
        $this->__checkAndAddNode($node);
        $objectsToConvert = array(
            'Object',
            'Attribute',
            'EventTag',
            'Galaxy'
        );
        foreach ($objectsToConvert as $objectToConvert) {
            if (!empty($event[$objectToConvert])) {
                foreach ($event[$objectToConvert] as $object) {
                    $target = $this->{'__convert' . $objectToConvert}($object, $event);
                    if ($target) {
                        $this->__checkAndAddEdge($node['unique_id'], $target, 100);
                    }
                }
            }
        }
    }

    private function __convertObject($object, $event)
    {
        $foundCorrelation = false;
        if (!empty($object['Attribute'])) {
            $aNodes = array();
            foreach ($object['Attribute'] as $attribute) {
                $aNode = $this->__convertAttribute($attribute, $event);
                if ($aNode) {
                    $aNodes[] = $aNode;
                    $foundCorrelation = true;
                }
            }
        }
        if ($foundCorrelation) {
            $node = array(
              'unique_id' => 'object-' . $object['id'],
              'name' => $object['name'],
              'type' => 'object',
              'id' => $object['id'],
              'uuid' => $object['uuid'],
              'metacategory' => $object['meta-category'],
              'description' => $object['description'],
              'comment' => $object['comment'],
              'imgClass' => 'th-list',
            );
            $this->__checkAndAddNode($node);
            return 'object-' . $object['id'];
        } else {
            return false;
        }
    }

    private function __convertAttribute($attribute, $event)
    {
        if (empty($event['RelatedAttribute'][$attribute['id']])) {
            return false;
        }
        $node = array(
          'unique_id' => 'attribute-' . $attribute['id'],
          'name' => $attribute['value'],
          'type' => 'attribute',
          'id' => $attribute['id'],
          'uuid' => $attribute['uuid'],
          'att_category' => $attribute['category'],
          'att_type' => $attribute['type'],
          'image' => '/img/indicator.png',
          'att_ids' => $attribute['to_ids'],
          'comment' => $attribute['comment']
        );
        $this->__checkAndAddNode($node);
        foreach ($event['RelatedAttribute'][$attribute['id']] as $relatedA) {
            foreach ($event['RelatedEvent'] as $relatedE) {
                if ($relatedE['Event']['id'] === $relatedA['id']) {
                    $tempRelatedEvent = $this->__convertEvent($relatedE, false);
                    $this->__checkAndAddEdge($node['unique_id'], 'event-' . $relatedE['Event']['id'], 100);
                }
            }
        }
        return 'attribute-' . $attribute['id'];
    }

    private function __convertEventTag($eventTag, $event)
    {
        $node = array(
          'unique_id' => 'tag-' . $eventTag['Tag']['id'],
          'name' => $eventTag['Tag']['name'],
          'type' => 'tag',
          'expanded' => false,
          'id' => $eventTag['Tag']['id'],
          'colour' => $eventTag['Tag']['colour'],
          'imgClass' => empty($eventTag['Tag']['taxonomy']) ? 'tag' : 'tags',
        );
        if (!empty($this->__capturedGalaxyTags[$eventTag['Tag']['name']])) {
            return $this->__capturedGalaxyTags[$eventTag['Tag']['name']];
        }
        $this->__checkAndAddNode($node);
        return 'tag-' . $eventTag['Tag']['id'];
    }

    private function __convertGalaxy($galaxy, $event)
    {
        $node = array(
          'unique_id' => 'galaxy-' . $galaxy['GalaxyCluster'][0]['id'],
          'name' => $galaxy['GalaxyCluster'][0]['value'],
          'galaxy' => $galaxy['name'],
          'type' => 'galaxy',
          'expanded' => false,
          'id' => $galaxy['GalaxyCluster'][0]['id'],
          'source' => $galaxy['GalaxyCluster'][0]['source'],
          'tag_name' => $galaxy['GalaxyCluster'][0]['tag_name'],
          'description' => $galaxy['GalaxyCluster'][0]['description'],
          'imgClass' => empty($galaxy['icon']) ? 'globe' : $galaxy['icon'],
          'authors' => !empty($galaxy['GalaxyCluster'][0]['authors']) ? implode(',', $galaxy['GalaxyCluster'][0]['authors']) : '',
          'synonyms' => !empty($galaxy['GalaxyCluster'][0]['meta']['synonyms']) ? implode(',', $galaxy['GalaxyCluster'][0]['meta']['synonyms']) : ''
        );
        $this->__checkAndAddNode($node);
        $this->__capturedGalaxyTags[$galaxy['GalaxyCluster'][0]['tag_name']] = 'galaxy-' . $galaxy['GalaxyCluster'][0]['id'];
        return 'galaxy-' . $galaxy['GalaxyCluster'][0]['id'];
    }

    private function __checkAndAddEdge($from, $to, $distance = 150)
    {
        $edge = array('source' => $from, 'target' => $to, 'linkDistance' => $distance);
        if (!$this->__checkEdgeAlreadyExists($edge)) {
            $this->__edges[] = $edge;
        }
        return true;
    }

    private function __checkEdgeAlreadyExists($edge)
    {
        foreach ($this->__edges as $existingEdge) {
            if (
                ($existingEdge['source'] === $edge['source'] && $existingEdge['target'] === $edge['target']) ||
                ($existingEdge['source'] === $edge['target'] && $existingEdge['target'] === $edge['source'])
            ) {
                return true;
            }
        }
        return false;
    }

    public function orgImgExists($org)
    {
        if (file_exists(APP . 'webroot' . DS . 'img' . DS . 'orgs' . DS . $org . '.png')) {
            return true;
        }
        return false;
    }

    public function header($options = array())
    {
        return '';
    }

    public function footer()
    {
        $response = array('links' => $this->__edges, 'nodes' => $this->__nodes);
        return json_encode($response);
    }

    public function separator()
    {
        return '';
    }
}
