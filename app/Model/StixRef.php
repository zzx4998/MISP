<?php

App::uses('AppModel', 'Model');

class StixRef extends AppModel {
	public $useTable = 'stix_refs';
	public $actsAs = array('Trim');
	public $belongsTo = array(
		'Stix' => array(
			'className' => 'Stix',
			'foreignKey' => 'stix_id',
		)
	);

	public $validate = array(

	);

	public function capture($idref, $id) {
		$stix_ref = array(
			'ref_uuid' => $idref,
			'stix_id' => $id
		);
		$existingRef = $this->find('first', array(
			'conditions' => array('AND' => $stix_ref),
			'recursive' => -1,
			'fields' => array('StixRef.id')
		));
		if (!empty($existingRef)) return $existingRef['StixRef']['id'];
		$this->create();
		if ($this->save($stix_ref)) {
			return $this->id;
		} else {
			return false;
		}
	}
}
