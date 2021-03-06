<?php
/**
 * @package midgard.admin.asgard
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Helper class to create a DM2 schema from an object via reflection
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_schemadb
{
    /**
     * The object we're working with
     *
     * @var midcom_core_dbaobject
     */
    private $_object;

    /**
     * Component config for Asgard
     *
     * @var midcom_helper_configuration
     */
    private $_config;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var array
     */
    private $_schemadb;

    /**
     * Midgard reflection property instance for the current object's class.
     *
     * @var midgard_reflection_property
     */
    private $_reflector;

    /**
     * Flag that controls if fields used for copying should be added
     *
     * @var boolean
     */
    public $add_copy_fields = false;

    public function __construct($object, $config)
    {
        $this->_object = $object;
        $this->_config = $config;
        $this->_l10n = midcom::get('i18n')->get_l10n('midgard.admin.asgard');
    }

    /**
     * Generates, loads and prepares the schema database.
     *
     * The operations are done on all available schemas within the DB.
     */
    public function create($type, $include_fields)
    {
        if ($type != null)
        {
            $dba_type = $type;
            if (!midcom::get('dbclassloader')->is_midcom_db_object($type))
            {
                $dba_type = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($type);
            }
            $dummy_object = new $dba_type();
            $type_fields = $dummy_object->get_properties();
        }
        else
        {
            $type = get_class($this->_object);
            if (!midcom::get('dbclassloader')->is_midcom_db_object($type))
            {
                $this->_object = midcom::get('dbfactory')->convert_midgard_to_midcom($this->_object);
            }
            $type_fields = $this->_object->get_properties();
        }

        if (empty($include_fields))
        {
            $include_fields = null;
        }
        else if (is_string($include_fields))
        {
            $include_fields = array
            (
                $include_fields,
            );
        }
        //This is an ugly little workaround for unittesting
        $template = midcom_helper_datamanager2_schema::load_database('file:/midgard/admin/asgard/config/schemadb_default.inc');
        $empty_db = clone $template['object'];
        $this->_schemadb = array('object' => $empty_db);
        //workaround end
        $this->_reflector = new midgard_reflection_property(midcom_helper_reflector::resolve_baseclass($type));

        // Iterate through object properties

        unset($type_fields['metadata']);

        if (!extension_loaded('midgard2'))
        {
            // Midgard1 returns properties is random order so we need to sort them heuristically
            usort($type_fields, array($this, 'sort_schema_fields'));
        }

        foreach ($type_fields as $key)
        {
            if (in_array($key, $this->_config->get('object_skip_fields')))
            {
                continue;
            }

            // Skip the fields that aren't requested, if inclusion list has been defined
            if (   $include_fields
                && !in_array($key, $include_fields))
            {
                continue;
            }

            // Only hosts have lang field that we will actually display
            if (   $key == 'lang'
                && !is_a($this->_object, 'midcom_db_host'))
            {
                continue;
            }

            // Skip topic symlink field because it is a special field not meant to be touched directly
            if (   $key == 'symlink'
                && is_a($this->_object, 'midcom_db_topic'))
            {
                continue;
            }

            // Linked fields should use chooser
            if ($this->_reflector->is_link($key))
            {
                $this->_add_linked_field($key);
                // Skip rest of processing
                continue;
            }

            $field_type = $this->_reflector->get_midgard_type($key);
            switch ($field_type)
            {
                case MGD_TYPE_GUID:
                case MGD_TYPE_STRING:
                    if (   $key == 'component'
                        && is_a($this->_object, 'midcom_db_topic'))
                    {
                        $this->_add_component_dropdown($key);
                        break;
                    }

                    // Special name handling, start by checking if given type is same as $this->_object and if not making a dummy copy (we're probably in creation mode then)
                    if (midcom::get('dbfactory')->is_a($this->_object, $type))
                    {
                        $name_obj = $this->_object;
                    }
                    else
                    {
                        $name_obj = new $type();
                    }

                    if ($key === midcom_helper_reflector::get_name_property($name_obj))
                    {
                        $this->_add_name_field($key, $name_obj);
                        break;
                    }
                    unset($name_obj);

                    // Special page treatment
                    if (   $key === 'info'
                        && $type === 'midcom_db_page')
                    {
                        $this->_add_info_field_for_page($key);
                        break;
                    }

                    if (   $key === 'info'
                        && $type === 'midcom_db_pageelement')
                    {
                        $this->_schemadb['object']->append_field
                        (
                            $key,
                            array
                            (
                                'title'       => $key,
                                'storage'     => $key,
                                'type'        => 'select',
                                'type_config' => array
                                (
                                    'options' => array
                                    (
                                        '' => 'not inherited',
                                        'inherit' => 'inherited',
                                    ),
                                ),
                                'widget'      => 'select',
                            )
                        );
                        break;
                    }

                    $this->_schemadb['object']->append_field
                    (
                        $key,
                        array
                        (
                            'title'       => $key,
                            'storage'     => $key,
                            'type'        => 'text',
                            'widget'      => 'text',
                        )
                    );
                    break;
                case MGD_TYPE_LONGTEXT:
                    $this->_add_longtext_field($key, $type);
                    break;
                case MGD_TYPE_INT:
                case MGD_TYPE_UINT:
                    $this->_add_int_field($key);
                    break;
                case MGD_TYPE_FLOAT:
                    $this->_schemadb['object']->append_field
                    (
                        $key,
                        array
                        (
                            'title'       => $key,
                            'storage'     => $key,
                            'type'        => 'number',
                            'widget'      => 'text',
                        )
                    );
                    break;
                case MGD_TYPE_BOOLEAN:
                    $this->_schemadb['object']->append_field
                    (
                        $key,
                        array
                        (
                            'title'       => $key,
                            'storage'     => $key,
                            'type'        => 'boolean',
                            'widget'      => 'checkbox',
                        )
                    );
                    break;
                case MGD_TYPE_TIMESTAMP:
                    $this->_schemadb['object']->append_field
                    (
                        $key,
                        array
                        (
                            'title'       => $key,
                            'storage'     => $key,
                            'type' => 'date',
                            'widget' => 'jsdate',
                        )
                    );
                    break;
            }
        }

        $this->_add_rcs_field();

        if ($this->add_copy_fields)
        {
            $this->_add_copy_fields();
        }

        return $this->_schemadb;
    }

    private function _add_rcs_field()
    {
        $this->_schemadb['object']->append_field
        (
            '_rcs_message',
            array
            (
                'title'       => midcom::get('i18n')->get_string('revision comment', 'midgard.admin.asgard'),
                'storage'     => '_rcs_message',
                'type'        => 'text',
                'widget'      => 'text',
                'start_fieldset' => array
                (
                    'title' => midcom::get('i18n')->get_string('revision', 'midgard.admin.asgard'),
                    'css_group' => 'rcs',
                ),
                'end_fieldset' => '',
            )
        );
    }

    private function _add_int_field($key)
    {
        if (   $key == 'start'
            || $key == 'end'
            || $key == 'added'
            || $key == 'date')
        {
            // We can safely assume that INT fields called start and end store unixtimes
            $this->_schemadb['object']->append_field
            (
                $key,
                array
                (
                    'title'       => $key,
                    'storage'     => $key,
                    'type' => 'date',
                    'type_config' => array
                    (
                        'storage_type' => 'UNIXTIME'
                        ),
                    'widget' => 'jsdate',
                )
            );
        }
        else
        {
            $this->_schemadb['object']->append_field
            (
                $key,
                array
                (
                    'title'       => $key,
                    'storage'     => $key,
                    'type'        => 'number',
                    'widget'      => 'text',
                )
            );
        }
    }

    private function _add_longtext_field($key, $type)
    {
        // Figure out nice size for the editing field

        $output_mode = '';
        $widget = 'textarea';
        $dm_type = 'text';

        // Workaround for the content field of pages
        $adjusted_key = $key;
        if (   $type == 'midcom_db_page'
            && $key == 'content')
        {
            $adjusted_key = 'code';
        }

        switch ($adjusted_key)
        {
            case 'content':
            case 'description':
                $height = 30;

                // Check the user preference and configuration
                if (   midgard_admin_asgard_plugin::get_preference('tinymce_enabled')
                    || (   midgard_admin_asgard_plugin::get_preference('tinymce_enabled') !== '0'
                        && $this->_config->get('tinymce_enabled')))
                {
                    $widget = 'tinymce';
                }
                $output_mode = 'html';

                break;
            case 'value':
            case 'code':
                // These are typical "large" fields
                $height = 30;

                // Check the user preference and configuration
                if (   midgard_admin_asgard_plugin::get_preference('codemirror_enabled')
                    || (   midgard_admin_asgard_plugin::get_preference('codemirror_enabled') !== '0'
                        && $this->_config->get('codemirror_enabled')))
                {
                    $widget = 'codemirror';
                }

                $dm_type = 'php';
                $output_mode = 'code';

                break;

            default:
                $height = 6;
                break;
        }

        $this->_schemadb['object']->append_field
        (
            $key,
            array
            (
                'title'       => $key,
                'storage'     => $key,
                'type'        => $dm_type,
                'type_config' => Array
                (
                    'output_mode' => $output_mode,
                ),
                'widget'      => $widget,
                'widget_config' => Array
                (
                    'height' => $height,
                    'width' => '100%',
                ),
            )
        );
    }

    private function _add_info_field_for_page($key)
    {
        $this->_schemadb['object']->append_field
        (
            $key,
            array
            (
                'title'       => $key,
                'storage'     => $key,
                'type'        => 'select',
                'type_config' => array
                (
                    'allow_multiple' => true,
                    'multiple_separator' => ',',
                    'multiple_storagemode' => 'imploded',
                    'options' => array
                    (
                        'auth'        => 'require authentication',
                        'active'      => 'active url parsing',
                        ),
                    ),
                'widget'      => 'select',
                'widget_config' => array
                (
                    'height' => 2,
                    ),
                )
        );
    }

    private function _add_name_field($key, $name_obj)
    {
        $type_urlname_config = array();
        $allow_unclean_name_types = $this->_config->get('allow_unclean_names_for');
        foreach ($allow_unclean_name_types as $allow_unclean_name_types_type)
        {
            if (midcom::get('dbfactory')->is_a($name_obj, $allow_unclean_name_types_type))
            {
                $type_urlname_config['allow_unclean'] = true;
                break;
            }
        }

        // Enable generating the name from the title property
        $type_urlname_config['title_field'] = midcom_helper_reflector::get_title_property($name_obj);

        $this->_schemadb['object']->append_field
        (
            $key,
            array
            (
                'title'       => $key,
                'storage'     => $key,
                'type'        => 'urlname',
                'type_config' => $type_urlname_config,
                'widget'      => 'text',
                )
        );
    }

    private function _add_component_dropdown($key)
    {
        $components = array('' => '');
        foreach (midcom::get('componentloader')->manifests as $manifest)
        {
            // Skip purecode components
            if ($manifest->purecode)
            {
                continue;
            }

            $components[$manifest->name] = midcom::get('i18n')->get_string($manifest->name, $manifest->name) . " ({$manifest->name})";
        }
        asort($components);

        $this->_schemadb['object']->append_field
        (
            $key,
            array
            (
                'title'       => $key,
                'storage'     => $key,
                'type'        => 'select',
                'type_config' => array
                (
                    'options' => $components,
                ),
                'widget'      => 'midcom_admin_folder_selectcomponent',
            )
        );
    }

    private function _add_linked_field($key)
    {
        $linked_type = $this->_reflector->get_link_name($key);
        $linked_type_reflector = midcom_helper_reflector::get($linked_type);
        $field_type = $this->_reflector->get_midgard_type($key);

        if ($key == 'up')
        {
            $field_label = sprintf($this->_l10n->get('under %s'), midgard_admin_asgard_plugin::get_type_label($linked_type));
        }
        else
        {
            $type_label = midgard_admin_asgard_plugin::get_type_label($linked_type);
            if (substr($type_label, 0, strlen($key)) == $key)
            {
                // Handle abbreviations like "lang" for "language"
                $field_label = $type_label;
            }
            else if ($key == $type_label)
            {
                $field_label = $key;
            }
            else
            {
                $ref = midcom_helper_reflector::get($this->_object);
                $component_l10n = $ref->get_component_l10n();
                $field_label = sprintf($this->_l10n->get('%s (%s)'), $component_l10n->get($key), $type_label);
            }
        }

        // Get the chooser widgets
        switch ($field_type)
        {
            case MGD_TYPE_UINT:
            case MGD_TYPE_STRING:
            case MGD_TYPE_GUID:
                $class = midcom::get('dbclassloader')->get_midcom_class_name_for_mgdschema_object($linked_type);
                if (! $class)
                {
                    break;
                }
                $component = midcom::get('dbclassloader')->get_component_for_class($linked_type);
                $this->_schemadb['object']->append_field
                (
                    $key,
                    array
                    (
                        'title'       => $field_label,
                        'storage'     => $key,
                        'type'        => 'select',
                        'type_config' => array
                        (
                            'require_corresponding_option' => false,
                            'options' => array(),
                            'allow_other' => true,
                            'allow_multiple' => false,
                        ),
                        'widget' => 'chooser',
                        'widget_config' => array
                        (
                            'class' => $class,
                            'component' => $component,
                            'titlefield' => $linked_type_reflector->get_label_property(),
                            'id_field' => $this->_reflector->get_link_target($key),
                            'searchfields' => $linked_type_reflector->get_search_properties(),
                            'result_headers' => $this->_get_result_headers($linked_type_reflector),
                            'orders' => array(),
                            'creation_mode_enabled' => true,
                            'creation_handler' => midcom_connection::get_url('self') . "__mfa/asgard/object/create/chooser/{$linked_type}/",
                            'creation_default_key' => $linked_type_reflector->get_label_property(),
                            'generate_path_for' => midcom_helper_reflector::get_name_property($this->_object),
                            ),
                        )
                    );
                break;
        }
    }

    /**
     * Get headers to be used with chooser
     *
     * @return array
     */
    private function _get_result_headers($linked_type_reflector)
    {
        $headers = array();
        $properties = $linked_type_reflector->get_search_properties();
        $l10n = $linked_type_reflector->get_component_l10n();
        foreach ($properties as $property)
        {
            $headers[] = array
            (
                'name' => $property,
                'title' => ucfirst($l10n->get($property)),
            );
        }
        return $headers;
    }

    private function _add_copy_fields()
    {
        // Add switch for copying parameters
        $this->_schemadb['object']->append_field
        (
            'parameters',
            array
            (
                'title'       => $this->_l10n->get('copy parameters'),
                'storage'     => null,
                'type'        => 'boolean',
                'widget'      => 'checkbox',
                'default'     => 1,
            )
        );

        // Add switch for copying metadata
        $this->_schemadb['object']->append_field
        (
            'metadata',
            array
            (
                'title'       => $this->_l10n->get('copy metadata'),
                'storage'     => null,
                'type'        => 'boolean',
                'widget'      => 'checkbox',
                'default'     => 1,
            )
        );

        // Add switch for copying attachments
        $this->_schemadb['object']->append_field
        (
            'attachments',
            array
            (
                'title'       => $this->_l10n->get('copy attachments'),
                'storage'     => null,
                'type'        => 'boolean',
                'widget'      => 'checkbox',
                'default'     => 1,
            )
        );

        // Add switch for copying privileges
        $this->_schemadb['object']->append_field
        (
            'privileges',
            array
            (
                'title'       => $this->_l10n->get('copy privileges'),
                'storage'     => null,
                'type'        => 'boolean',
                'widget'      => 'checkbox',
                'default'     => 1,
            )
        );
    }

    function sort_schema_fields($first, $second)
    {
        $preferred_fields = $this->_config->get('object_preferred_fields');
        $timerange_fields = $this->_config->get('object_timerange_fields');
        $address_fields = $this->_config->get('object_address_fields');
        $phone_fields = $this->_config->get('object_phone_fields');
        $location_fields = $this->_config->get('object_location_fields');

        // We handle the cases, and then their subcases
        if (   in_array($first, $preferred_fields)
            && $this->_reflector->get_midgard_type($first) != MGD_TYPE_LONGTEXT)
        {
            // This is one of the preferred fields, check subcases
            if (in_array($second, $preferred_fields))
            {
                return strnatcmp($first, $second);
            }

            return -1;
        }

        if ($this->_reflector->get_midgard_type($first) == MGD_TYPE_LONGTEXT)
        {
            // This is a longtext field, they come next
            if (   in_array($second, $preferred_fields)
                && $this->_reflector->get_midgard_type($second) != MGD_TYPE_LONGTEXT)
            {
                return 1;
            }
            if ($this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT)
            {
                return strnatcmp($first, $second);
            }
            return -1;
        }

        if ($this->_reflector->is_link($first))
        {
            // This is a linked property, they come next
            if (   in_array($second, $preferred_fields)
                || $this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT)
            {
                return 1;
            }
            if ($this->_reflector->is_link($second))
            {
                return strnatcmp($first, $second);
            }
            return -1;
        }

        if (in_array($first, $timerange_fields))
        {
            if (   in_array($second, $preferred_fields)
                || $this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT
                || $this->_reflector->is_link($second))
            {
                return 1;
            }

            if (in_array($second, $timerange_fields))
            {
                // Both are phone fields, arrange them in proper order
                return (array_search($first, $timerange_fields) < array_search($second, $timerange_fields)) ? -1 : 1;
            }

            return -1;
        }

        if (in_array($first, $phone_fields))
        {
            if (   in_array($second, $preferred_fields)
                || $this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT
                || $this->_reflector->is_link($second)
                || in_array($second, $timerange_fields))
            {
                return 1;
            }

            if (in_array($second, $phone_fields))
            {
                // Both are phone fields, arrange them in proper order
                return (array_search($first, $phone_fields) < array_search($second, $phone_fields)) ? -1 : 1;
            }

            return -1;
        }

        if (in_array($first, $address_fields))
        {
            if (   in_array($second, $preferred_fields)
                || $this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT
                || $this->_reflector->is_link($second)
                || in_array($second, $timerange_fields)
                || in_array($second, $phone_fields))
            {
                return 1;
            }

            if (in_array($second, $address_fields))
            {
                // Both are address fields, arrange them in proper order
                return (array_search($first, $address_fields) < array_search($second, $address_fields)) ? -1 : 1;
            }

            return -1;
        }

        if (in_array($first, $location_fields))
        {
            if (   in_array($second, $preferred_fields)
                || $this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT
                || $this->_reflector->is_link($second)
                || in_array($second, $timerange_fields)
                || in_array($second, $phone_fields)
                || in_array($second, $address_fields))
            {
                return 1;
            }

            if (in_array($second, $location_fields))
            {
                // Both are address fields, arrange them in proper order
                return (array_search($first, $location_fields) < array_search($second, $location_fields)) ? -1 : 1;
            }

            return -1;
        }


        if (   in_array($second, $preferred_fields)
            || $this->_reflector->get_midgard_type($second) == MGD_TYPE_LONGTEXT
            || $this->_reflector->is_link($second)
            || in_array($second, $timerange_fields)
            || in_array($second, $phone_fields)
            || in_array($second, $address_fields)
            || in_array($second, $location_fields))
        {
            // First field was not a preferred field, but second is
            return 1;
        }

        // Others come as they do
        return strnatcmp($first, $second);
    }
}
?>