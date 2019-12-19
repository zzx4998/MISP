<?php
    echo $this->element('genericElements/Form/genericForm', array(
        'form' => $this->Form,
        'data' => array(
            'title' => $this->request->params['action'] === 'admin_add' ? __('New User') : __('Edit User'),
            'model' => '',
            'fields' => array(
                array(
                    'field' => 'email',
                    'class' => 'input',
                    'type' => 'text'
                ),
                array(
                    'field' => 'enable_password',
                    'type' => 'checkbox',
                    'label' => __('Set password')
                ),
                array(
                    'field' => 'password',
                    'type' => 'password',
                    'class' => 'input password required',
                    'stayInLine' => 1
                ),
                array(
                    'field' => 'confirm_password',
                    'type' => 'password',
                    'class' => 'input password required'
                ),
                array(
                    'field' => 'org_id',
                    'class' => 'input',
                    'options' => $orgs,
                    'label' => __('Organisation'),
                    'empty' => __('Choose organisation'),
                    'stayInLine' => 1
                ),
                array(
                    'field' => 'role_id',
                    'class' => 'input',
                    'options' => $roleOptions,
                    'label' => __('Role'),
                    'default' => $default_role_id
                ),
                array(
                    'field' => 'authkey',
                    'value' => $authkey,
                    'readonly' => 'readonly',
                    'div' => 'input clear',
                    'stayInLine' => 1
                ),
                array(
                    'field' => 'nids_sid',
                    'class' => 'input',
                    'type' => 'integer'
                ),
                array(
                    'field' => 'gpgkey',
                    'class' => 'input span6',
                    'type' => 'textarea',
                    'placeholder' => __('Paste the user\'s GnuPG key here or try to retrieve it from the CIRCL key server by clicking on "Fetch GnuPG key" below.'),
                    'label' => __('GnuPG key')
                )
            ),
            'submit' => array(
                'action' => $this->request->params['action']
            )
        )
    ));
    echo $this->element('/genericElements/SideMenu/side_menu', array('menuList' => 'admin', 'menuItem' => 'addUser'));
?>

<script type="text/javascript">
var syncRoles = <?php echo json_encode($syncRoles); ?>;
$(document).ready(function() {
    syncUserSelected();
    $('#UserRoleId').change(function() {
        syncUserSelected();
    });
    checkUserPasswordEnabled();
    checkUserExternalAuth();
    $('#UserEnablePassword').change(function() {
        checkUserPasswordEnabled();
    });
    $('#UserExternalAuthRequired').change(function() {
        checkUserExternalAuth();
    });
    $('#PasswordPopover').popover("destroy").popover({
        placement: 'right',
        html: 'true',
        trigger: 'hover',
        content: '<?php echo $passwordPopover; ?>'
    });
});
</script>
