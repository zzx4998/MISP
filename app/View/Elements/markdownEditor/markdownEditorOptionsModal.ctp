<?php
    $optionsHTML = '<h4>Options</h4>';

    $data = array(
        'title' => __('Markdown User\'s options'),
        'content' => array(
            array(
                'html' => $optionsHTML
            ),
        )
    );
    echo $this->element('genericElements/infoModal', array('data' => $data, 'type' => 'lg', 'class' => 'markdown-modal-options'));
?>
