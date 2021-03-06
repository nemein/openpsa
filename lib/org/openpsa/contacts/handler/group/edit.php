<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.contacts group handler and viewer class.
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_group_edit extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_edit
{
    /**
     * What type of group are we dealing with, organization or group?
     *
     * @var string
     */
    private $_type;

    /**
     * The group we're working on
     *
     * @var org_openpsa_contacts_group_dba
     */
    private $_group = null;

    /**
     * The Controller of the organization used for editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     */
    private $_controller = null;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var Array
     */
    private $_schemadb = null;

    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_group'));
    }

    public function get_schema_name()
    {
        return $this->_type;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_edit($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_valid_user();
        // Check if we get the group
        $this->_group = new org_openpsa_contacts_group_dba($args[0]);
        $this->_group->require_do('midgard:update');

        if ($this->_group->orgOpenpsaObtype < org_openpsa_contacts_group_dba::MYCONTACTS)
        {
            $this->_type = 'group';
        }
        else
        {
            $this->_type = 'organization';
        }

        $data['controller'] = $this->get_controller('simple', $this->_group);

        switch ($data['controller']->process_form())
        {
            case 'save':
                $indexer = new org_openpsa_contacts_midcom_indexer($this->_topic);
                $indexer->index($data['controller']->datamanager);
                // *** FALL-THROUGH ***

            case 'cancel':
                $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
                return new midcom_response_relocate($prefix . "group/" . $this->_group->guid . "/");
        }

        $root_group = org_openpsa_contacts_interface::find_root_group();

        if (   $this->_group->owner
            && $this->_group->owner != $root_group->id)
        {
            $data['parent_group'] = new org_openpsa_contacts_group_dba($this->_group->owner);
        }
        else
        {
            $data['parent_group'] = false;
        }

        $data['group'] =& $this->_group;

        org_openpsa_helpers::dm2_savecancel($this);
        $this->bind_view_to_object($this->_group);

        midcom::get('head')->set_pagetitle(sprintf($this->_l10n_midcom->get('edit %s'), $this->_group->official));

        org_openpsa_contacts_viewer::add_breadcrumb_path_for_group($this->_group, $this);
        $this->add_breadcrumb("", sprintf($this->_l10n_midcom->get('edit %s'), $this->_l10n->get($this->_type)));
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_edit($handler_id, array &$data)
    {
        midcom_show_style("show-group-edit");
    }
}
?>