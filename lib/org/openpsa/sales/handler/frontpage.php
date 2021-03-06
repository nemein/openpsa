<?php
/**
 * @package org.openpsa.sales
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * salesproject list handler
 *
 * @package org.openpsa.sales
 */
class org_openpsa_sales_handler_frontpage extends midcom_baseclasses_components_handler
{
    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_frontpage($handler_id, array $args, array &$data)
    {
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'salesproject/new/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create salesproject'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_people.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::get('auth')->can_user_do('midgard:create', null, 'org_openpsa_sales_salesproject_dba'),
            )
        );

        org_openpsa_widgets_ui::enable_ui_tab();

        $sales_url = org_openpsa_core_siteconfig::get_instance()->get_node_relative_url('org.openpsa.sales');

        $data['tabs'] = array
        (
            array
            (
                'url' => $sales_url . "list/active/",
                'title' => $this->_l10n->get('active'),
            ),
            array
            (
                'url' => $sales_url . "list/won/",
                'title' => $this->_l10n->get('won'),
            ),
            array
            (
                'url' => $sales_url . "list/delivered/",
                'title' => $this->_l10n->get('delivered'),
            ),
            array
            (
                'url' => $sales_url . "list/invoiced/",
                'title' => $this->_l10n->get('invoiced'),
            ),
            array
            (
                'url' => $sales_url . "list/canceled/",
                'title' => $this->_l10n->get('canceled'),
            ),
            array
            (
                'url' => $sales_url . "list/lost/",
                'title' => $this->_l10n->get('lost'),
            )
        );
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_frontpage($handler_id, array &$data)
    {
        midcom_show_style('show-frontpage');
    }
}
?>