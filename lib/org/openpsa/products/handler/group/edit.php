<?php
/**
 * @package org.openpsa.products
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Product editing class
 *
 * @package org.openpsa.products
 */
class org_openpsa_products_handler_group_edit extends midcom_baseclasses_components_handler
{
    /**
     * The product to display
     *
     * @var midcom_db_group
     */
    private $_group = null;

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['group'] =& $this->_group;

        $this->add_stylesheet(MIDCOM_STATIC_URL."/midcom.helper.datamanager2/legacy.css");
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     */
    private function _update_breadcrumb_line()
    {
        $tmp = Array();
        if ($this->_group->up != 0)
        {
            $group = new org_openpsa_products_product_group_dba($this->_group->up);
            while ($group)
            {
                $parent = $group->get_parent();
                if ($group->get_parent() != null)
                {
                    $tmp[] = array
                    (
                        MIDCOM_NAV_URL => "{$parent->code}/{$group->code}",
                        MIDCOM_NAV_NAME => $group->title,
                    );
                }
                else
                {
                    $tmp[] = array
                    (
                        MIDCOM_NAV_URL => "{$group->code}/",
                        MIDCOM_NAV_NAME => $group->title,
                    );
                }
                $group = $parent;
            }
        }

        $tmp = array_reverse($tmp);

        $tmp[] = array
        (
            MIDCOM_NAV_URL => "{$this->_group->guid}/",
            MIDCOM_NAV_NAME => $this->_group->title,
        );

        $tmp[] = array
        (
            MIDCOM_NAV_URL => "edit/{$this->_group->guid}/",
            MIDCOM_NAV_NAME => $this->_l10n_midcom->get('edit'),
        );

        midcom_core_context::get()->set_custom_key('midcom.helper.nav.breadcrumb', $tmp);
    }

    /**
     * Looks up a product to display.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_edit($handler_id, array $args, array &$data)
    {
        $this->_group = new org_openpsa_products_product_group_dba($args[0]);

        $this->_request_data['controller'] = midcom_helper_datamanager2_controller::create('simple');
        $this->_request_data['controller']->schemadb =& $this->_request_data['schemadb_group'];
        $this->_request_data['controller']->set_storage($this->_group);
        if (! $this->_request_data['controller']->initialize())
        {
            throw new midcom_error("Failed to initialize a DM2 controller instance for product {$this->_group->id}.");
        }

        switch ($this->_request_data['controller']->process_form())
        {
            case 'save':

                if ($this->_config->get('index_groups'))
                {
                    // Index the group
                    $indexer = midcom::get('indexer');
                    org_openpsa_products_viewer::index($this->_request_data['controller']->datamanager, $indexer, $this->_topic);
                }
                midcom::get('cache')->invalidate($this->_topic->guid);
            case 'cancel':
                return new midcom_response_relocate("{$this->_group->guid}/");
        }

        $this->_update_breadcrumb_line();
        $this->_prepare_request_data();

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);
        $this->_view_toolbar->bind_to($this->_group);

        midcom::get('metadata')->set_request_metadata($this->_group->metadata->revised, $this->_group->guid);
        midcom::get('head')->set_pagetitle($this->_group->title);
    }

    /**
     * Shows the loaded product.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_edit($handler_id, array &$data)
    {
        $this->_request_data['view_group'] = $this->_request_data['controller']->datamanager->get_content_html();
        midcom_show_style('product_group_edit');
    }
}
?>