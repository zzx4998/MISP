<?php
App::uses('AppModel', 'Model');
App::uses('RandomTool', 'Tools');

class Authkey extends AppModel
{
    public $belongsTo = array(
        'User'
    );

    public $validate = array(
        'user_id' => array(
            'numeric' => array(
                'rule' => array('numeric')
            )
        ),
        'authkey' => array(
            'minlength' => array(
                'rule' => array('minlength', 40),
                'message' => 'A authkey of a minimum length of 40 is required.',
                'required' => true,
            ),
            'valueNotEmpty' => array(
                'rule' => array('valueNotEmpty'),
            )
        )
    );


    public function beforeValidate($options = array())
    {
        if (empty($this->data['Authkey']['authkey'])) {
            $this->data['Authkey']['authkey'] = $this->generateAuthKey();
        }
        return true;
    }

    public function convertKey($user)
    {
        if (!empty($user['authkey'])) {
            $this->create();
            $authkey = array(
                'authkey' => $user['User']['Authkey'],
                'user_id' => $user['User']['id'],
                'expiry' => 0,
                'disabled' => 0
            );
            return $this->save($authkey);
        }
        return true;
    }

    public function generateAuthkey()
    {
        return (new RandomTool())->random_str(true, 40);
    }
}
