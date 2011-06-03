'default' => array
(
    'description' => 'group',
    'fields'      => array
    (
        'name' => array
        (
            'title'       => 'name',
            'storage'     => 'name',
            'type'        => 'urlname',
            'widget'      => 'text',
            'index_method' => 'noindex',
            'type_config' => array
            (
                'allow_catenate' => true,
                'title_field' => 'official',
                'allow_unclean' => true,
            ),
        ),
        'official' => array
        (
            'title'       => 'official',
            'storage'     => 'official',
            'type'        => 'text',
            'widget'      => 'text',
            'required'    => true,
        ),
        'owner' => array
        (
            'title' => 'owner group',
            'storage' => 'owner',
            'type' => 'select',
            'type_config' => array
            (
                 'require_corresponding_option' => false,
                 'allow_multiple' => false,
                 'options' => array(),
            ),
            'widget' => 'chooser',
            'widget_config' => array
            (
                'clever_class' => 'group',
                'id_field' => 'id',
            ),
        ),
        'email' => array
        (
            'title'       => 'email',
            'type'        => 'text',
            'widget'      => 'text',
            'storage'     => 'email',
            'validation'  => 'email',
        ),
        'postcode' => array
        (
            'title'       => 'postcode',
            'type'        => 'text',
            'widget'      => 'text',
            'storage'     => 'postcode',
        ),
        'city' => array
        (
            'title'       => 'city',
            'type'        => 'text',
            'widget'      => 'text',
            'storage'     => 'city',
        ),
        'persons' => array
        (
            'title' => 'members',
            'storage' => null,
            'type' => 'mnrelation',
            'type_config' => array
            (
                'mapping_class_name' => 'midcom_db_member',
                'master_fieldname' => 'gid',
                'member_fieldname' => 'uid',
                'master_is_id' => true,
                'require_corresponding_option' => false,
                'option_callback' => 'midcom_helper_datamanager2_callback_select_personlister',
                'option_callback_arg' => array
                (
                    'key_field' => 'id'
                ),
            ),
            'widget' => 'chooser',
            'widget_config' => array
            (
                'id_field' => 'id',
                'result_headers' => array
                (
                    array(
                        'name' => 'name',
                        'title' => 'Name',
                    ),
                    array(
                        'name' => 'email',
                        'title' => 'Email',
                    ),
                    array(
                        'name' => 'username',
                        'title' => 'Username',
                    ),
                ),
            ),
        ),
    ),
),