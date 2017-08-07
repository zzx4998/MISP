<?php
App::uses('AppModel', 'Model');

class Tag extends AppModel {

	public $useTable = 'tags';

	public $displayField = 'name';

	public $actsAs = array(
			'SysLogLogable.SysLogLogable' => array(	// TODO Audit, logable
					'roleModel' => 'Tag',
					'roleKey' => 'tag_id',
					'change' => 'full'
			),
			'Containable'
	);

	public $validate = array(
			'name' => array(
					'valueNotEmpty' => array(
						'rule' => array('valueNotEmpty'),
					),
					'unique' => array(
							'rule' => 'isUnique',
							'message' => 'A similar name already exists.',
					),
			),
			'colour' => array(
					'valueNotEmpty' => array(
						'rule' => array('valueNotEmpty'),
					),
					'userdefined' => array(
							'rule' => 'validateColour',
							'message' => 'Colour has to be in the RGB format (#FFFFFF)',
					),
			),
	);

	public $hasMany = array(
		'EventTag' => array(
			'className' => 'EventTag',
			'dependent' => true
		),
		'TemplateTag',
		'FavouriteTag' => array(
			'dependent' => true
		),
		'AttributeTag' => array(
			'dependent' => true
		)
	);

	public $belongsTo = array(
		'Organisation' => array(
			'className' => 'Organisation',
			'foreignKey' => 'org_id',
		)
	);

	public function beforeValidate($options = array()) {
		parent::beforeValidate();
		if (!isset($this->data['Tag']['org_id'])) {
			$this->data['Tag']['org_id'] = 0;
		}
		if (!isset($this->data['Tag']['hide_tag'])) {
			$this->data['Tag']['hide_tag'] = Configure::read('MISP.incoming_tags_disabled_by_default') ? 1 : 0;
		}
		if (!isset($this->data['Tag']['exportable'])) {
			$this->data['Tag']['exportable'] = 1;
		}
		return true;
	}

	public function validateColour($fields) {
		if (!preg_match('/^#[0-9a-f]{6}$/i', $fields['colour'])) return false;
		return true;
	}

	// find all of the event Ids that belong to the accepted tags and the rejected tags
	public function fetchEventTagIds($accept=array(), $reject=array(), $controller='events') {
		$acceptIds = array();
		$rejectIds = array();
		$rejectedAttributeIds = array();
		if (!empty($accept)) {
			if ($controller === 'attributes') {
				$acceptIds = $this->findAttributeRulesByTagNames($accept);
				if (empty($acceptIds)) {
					$acceptIds['Event'] = -1;
				}
			} else {
				$acceptIds = $this->findEventIdsByTagNames($accept);
				if (empty($acceptIds)) $acceptIds[] = -1;
			}
		}
		if (!empty($reject)) {
			if ($controller === 'attributes') {
				$rejectIds = $this->findAttributeRulesByTagNames($reject);
			} else {
				$rejectIds = $this->findEventIdsByTagNames($reject, array('EventTag'));
				$rejectedAttributeIds = $this->findAttributeIdsByAttributeTagNames($reject);
			}
		}
		return array($acceptIds, $rejectIds, $rejectedAttributeIds);
	}

	public function findEventIdsByTagNames($array, $objects = array('AttributeTag', 'EventTag')) {
		$ids = array();
		foreach ($array as $a) {
			$conditions['OR'][] = array('LOWER(Tag.name) like' => strtolower($a));
		}
		$params = array(
				'recursive' => 1,
				//'contain' => array('EventTag', 'AttributeTag'),
				'conditions' => $conditions,
				'fields' => array('id')
		);
		$result = $this->find('list', $params);
		$ids = array(0 => array(), 1 => array());
		foreach ($objects as $k => $object) {
			$ids[$k] = $this->$object->find('list', array(
				'conditions' => array($object . '.tag_id' => $result),
				'recursive' => -1,
				'fields' => array($object . '.event_id'),
				'group' => array($object . '.event_id', $object . '.id')
			));
		}
		$ids = array_merge($ids[0], $ids[1]);
		return $ids;
	}

	public function findAttributeIdsByAttributeTagNames($array) {
		$ids = array();
		foreach ($array as $a) {
			$conditions['OR'][] = array('LOWER(Tag.name) like' => strtolower($a));
		}
		$params = array(
				'recursive' => 1,
				'conditions' => $conditions,
				'fields' => array('id')
		);
		$result = $this->find('list', $params);
		$ids = $this->AttributeTag->find('list', array(
			'conditions' => array('AttributeTag.tag_id' => $result),
			'recursive' => -1,
			'fields' => array('AttributeTag.attribute_id'),
			'group' => array('AttributeTag.attribute_id', 'AttributeTag.id')
		));
		return $ids;
	}

	public function findAttributeRulesByTagNames($array) {
		$ids = array();
		foreach ($array as $a) {
			$conditions['OR'][] = array('LOWER(name) LIKE' => strtolower($a));
		}
		$params = array(
				'recursive' => 1,
				'contain' => array('AttributeTag', 'EventTag'),
				'conditions' => $conditions
		);
		$result = $this->find('all', $params);
		$searchParams = array(
			'Attribute' => array(
				'model' => 'AttributeTag',
				'key' => 'attribute_id'
			),
			'Event' => array(
				'model' => 'EventTag',
				'key' => 'event_id'
			)
		);
		foreach ($result as $tag) {
			foreach ($searchParams as $searchKey => $searchData) {
				if (!empty($tag[$searchData['model']])) {
					foreach ($tag[$searchData['model']] as $tagObject) {
						$ids[$searchKey][] = $tagObject[$searchData['key']];
					}
				}
			}
		}
		return $ids;
	}

	public function captureTag($tag, $user) {
		$existingTag = $this->find('first', array(
				'recursive' => -1,
				'conditions' => array('LOWER(name)' => strtolower($tag['name']))
		));
		if (empty($existingTag)) {
			if ($user['Role']['perm_tag_editor']) {
				$this->create();
				if (!isset($tag['colour']) || empty($tag['colour'])) $tag['colour'] = $this->random_color();
				$tag = array(
						'name' => $tag['name'],
						'colour' => $tag['colour'],
						'exportable' => isset($tag['exportable']) ? $tag['exportable'] : 0,
						'org_id' => 0,
						'hide_tag' => Configure::read('MISP.incoming_tags_disabled_by_default') ? 1 : 0
				);
				$this->save($tag);
				return $this->id;
			} else return false;
		} else {
			if (!$user['Role']['perm_site_admin'] && $existingTag['Tag']['org_id'] != 0 && $existingTag['Tag']['org_id'] != $user['org_id']) {
				return false;
			}
		}
		return $existingTag['Tag']['id'];
	}

	// find all tags that belong to a given eventId
	public function findEventTags($eventId) {
		$tags = array();
		$params = array(
				'recursive' => 1,
				'contain' => 'EventTag',
		);
		$result = $this->find('all', $params);
		foreach ($result as $tag) {
			foreach ($tag['EventTag'] as $eventTag) {
				if ($eventTag['event_id'] == $eventId) {
					$tags[] = $tag['Tag'];
				}
			}
		}
		return $tags;
	}

	public function random_color() {
		$colour = '#';
		for ($i = 0; $i < 3; $i++) $colour .= str_pad(dechex(mt_rand(0,255)), 2, '0', STR_PAD_LEFT);
		return $colour;
	}

	public function quickAdd($name, $colour = false, $returnId = false) {
		$this->create();
		if ($colour === false) $colour = $this->random_color();
		$data = array(
			'name' => $name,
			'colour' => $colour,
			'exportable' => 1
		);
		return ($this->save($data));
	}

	public function quickEdit($tag, $name, $colour, $hide = false) {
		if ($tag['Tag']['colour'] !== $colour || $tag['Tag']['name'] !== $name || $hide !== false) {
			$tag['Tag']['name'] = $name;
			$tag['Tag']['colour'] = $colour;
			if ($hide !== false) {
				$tag['Tag']['hide_tag'] = $hide;
			}
			return ($this->save($tag['Tag']));
		}
		return true;
	}

	public function disableTags($tags) {
		foreach ($tags as $k => $v) {
			$tags[$k]['Tag']['hide_tag'] = 1;
		}
		return ($this->saveAll($tags));
	}

	public function getTagsForNamespace($namespace) {
		$contain = array('EventTag');
		$contain[] = 'AttributeTag';
		$tags_temp = $this->find('all', array(
				'recursive' => -1,
				'contain' => $contain,
				'conditions' => array('UPPER(name) LIKE' => strtoupper($namespace) . '%'),
		));
		$tags = array();
		foreach ($tags_temp as $temp) $tags[strtoupper($temp['Tag']['name'])] = $temp;
		return $tags;
	}
}
