<?php
/**
 * @package org.openpsa.interviews
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Phone interview campaignr handler
 *
 * @package org.openpsa.interviews
 */
class org_openpsa_interviews_handler_campaign extends midcom_baseclasses_components_handler
{
    private function _prepare_toolbar()
    {
        $this->_view_toolbar->add_item(
            array
            (
                MIDCOM_TOOLBAR_URL => "report/all/{$this->_request_data['campaign']->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('show interviews'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
            )
        );
        $this->_view_toolbar->add_item(
            array
            (
                MIDCOM_TOOLBAR_URL => "next/{$this->_request_data['campaign']->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('next interview'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_landline-phone.png',
            )
        );
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_summary($handler_id, array $args, array &$data)
    {
        $this->_request_data['campaign'] = new org_openpsa_directmarketing_campaign_dba($args[0]);
        $this->_prepare_toolbar();

        $this->_request_data['members_waiting'] = Array();
        $this->_request_data['members_locked'] = Array();
        $this->_request_data['members_suspended'] = Array();
        $this->_request_data['members_interviewed'] = Array();
        $this->_request_data['members_unsubscribed'] = Array();

        // List members who haven't been interviewed yet
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::NORMAL);
        $qb->add_constraint('suspended', '<', time());
        $this->_request_data['members_waiting'] = $qb->execute();

        // List members who we're interviewing currently
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::LOCKED);
        $this->_request_data['members_locked'] = $qb->execute();

        // List members who we have to call later
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::NORMAL);
        $qb->add_constraint('suspended', '>', time());
        $this->_request_data['members_suspended'] = $qb->execute();

        // List members who have been interviewed
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::INTERVIEWED);
        $this->_request_data['members_interviewed'] = $qb->execute();

        // List members who asked not to be called again
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::UNSUBSCRIBED);
        $this->_request_data['members_unsubscribed'] = $qb->execute();

        $this->add_breadcrumb('', $data['campaign']->title);
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_summary($handler_id, array &$data)
    {
        midcom_show_style('show-summary');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_next($handler_id, array $args, array &$data)
    {
        $this->_request_data['campaign'] = new org_openpsa_directmarketing_campaign_dba($args[0]);
        $this->_prepare_toolbar();

        // Figure out next person to call
        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign', '=', $this->_request_data['campaign']->id);
        $qb->add_constraint('orgOpenpsaObtype', '=', org_openpsa_directmarketing_campaign_member_dba::NORMAL);
        $qb->add_constraint('suspended', '<', time());
        $qb->set_limit(1);
        $next_contact = $qb->execute();

        if (count($next_contact) == 1)
        {
            $member =& $next_contact[0];

            // Found, lock and redirect
            $member->orgOpenpsaObtype = org_openpsa_directmarketing_campaign_member_dba::LOCKED;

            if (!$member->update())
            {
                throw new midcom_error('Failed to lock the interviewee');
            }

            return new midcom_response_relocate(midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX) . "interview/{$member->guid}/");
        }
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_next($handler_id, array &$data)
    {
        midcom_show_style('show-no-next');
    }
}
?>