<?php

App::uses('AppModel', 'Model');

class StixUuid extends AppModel {
	public $useTable = 'stix_uuids';
	public $actsAs = array('Trim');
	public $belongsTo = array(
		'Stix' => array(
			'className' => 'Stix',
			'foreignKey' => 'stix_id',
		)
	);

	public $validate = array(

	);

	public function capture($uuid, $id) {
		$stix_uuid = array(
			'uuid' => $uuid,
			'stix_id' => $id
		);
		$existingUuid = $this->find('first', array(
			'conditions' => array('AND' => $stix_uuid),
			'recursive' => -1,
			'fields' => array('StixUuid.id')
		));
		if (!empty($existingUuid)) return $existingUuid['StixUuid']['id'];
		$this->create();
		if ($this->save($stix_uuid)) {
			return $this->id;
		} else {
			return false;
		}
	}

	public function findReferencedXmls($idref) {
		$ids = $this->find('list', array(
			'conditions' => array('uuid' => $idref),
			'recursive' => -1,
			'fields' => array('stix_id')
		));
		return array_unique($ids);
	}
}
