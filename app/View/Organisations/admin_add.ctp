<?php
    echo $this->element('genericElements/Form/genericForm', array(
        'form' => $this->Form,
        'data' => array(
            'title' => $this->request->params['action'] === 'admin_add' ? __('New Organisation') : __('Edit Organisation'),
            'model' => '',
            'fields' => array(
                array(
                    'field' => 'local',
                    'type' => 'checkbox',
                    'label' => __('Local organisation')
                ),
                array(
                    'field' => 'name',
                    'class' => 'input',
                    'placeholder' => __('Brief organisation identifier'),
                    'type' => 'text',
                    'label' => __('Organisation identifier')
                ),
                array(
                    'field' => 'uuid',
                    'class' => 'input',
                    'placeholder' => __('Paste UUID of click generate'),
                    'label' => __('UUID')
                ),
                array(
                    'field' => 'description',
                    'class' => 'input span6',
                    'type' => 'textarea',
                    'div' => 'input clear',
                    'label' => __('A brief description of the organisation'),
                    'placeholder' => __('A description of the organisation that is purely informational.')
                )
            ),
            'submit' => array(
                'action' => $this->request->params['action']
            )
        )
    ));
?>
