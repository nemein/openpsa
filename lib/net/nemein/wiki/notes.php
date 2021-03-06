<?php
/**
 * @package net.nemein.wiki
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Wiki note helper class to be used by other components
 *
 * @package net.nemein.wiki
 */
class net_nemein_wiki_notes extends midcom_baseclasses_components_purecode
{
    var $target = null;
    var $target_node = null;
    var $wiki = null;
    var $related = array();
    private $_related_guids = array();
    var $link_target = 'wiki';
    var $new_wikipage = null;
    private $_paged_qb = null;

    public function __construct($target_node, $target_object, $new_wikipage = null)
    {
        parent::__construct();

        $this->target_node = $target_node;
        $this->target = midcom::get('dbfactory')->get_object_by_guid($target_object);
        $this->wiki = midcom_helper_misc::find_node_by_component('net.nemein.wiki');

        if ($new_wikipage)
        {
            $this->new_wikipage = rawurlencode(str_replace('/', '-', $new_wikipage));
        }
    }

    private function _list_related_guids_of_a_person($person)
    {
        // We're in person, so we need to also look events he/she participates to
        $qb = midcom::get('dbfactory')->new_query_builder('midcom_db_eventmember');
        $qb->add_constraint('uid', '=', $person->id);

        $memberships = $qb->execute();
        if ($memberships)
        {
            foreach ($memberships as $membership)
            {
                try
                {
                    // FIXME: This is slow way to do it, use a single QB instance for all instead
                    $event = new midcom_db_event($membership->eid);
                }
                catch (midcom_error $e)
                {
                    $e->log();
                    continue;
                }
                $this->_related_guids[$event->guid] = true;
            }
        }

        // And also list events directly connected to them
        $this->_related_guids[$person->guid] = true;
    }

    private function _list_related()
    {
        if (!$this->wiki)
        {
            return false;
        }

        // The depth of relation looked depends on object type
        if (is_subclass_of($this->target, 'midgard_person'))
        {
            $this->_list_related_guids_of_a_person($this->target);
        }
        else if (is_subclass_of($this->target, 'midgard_group'))
        {
            // Include notes about members of the group
            $qb = midcom::get('dbfactory')->new_query_builder('midcom_db_member');
            $qb->add_constraint('gid', '=', $this->target->id);
            $members = $qb->execute();
            foreach ($members as $member)
            {
                try
                {
                    $person = new midcom_db_person($member->uid);
                    $this->_list_related_guids_of_a_person($person);
                }
                catch (midcom_error $e)
                {
                    $e->log();
                }
            }

            // And the group itself
            $this->_related_guids[$this->target->guid] = true;
        }
        else
        {
            $this->_related_guids[$this->target->guid] = true;
        }

        if (count($this->_related_guids) > 0)
        {
            if (class_exists('org_openpsa_qbpager_direct'))
            {
                $qb = new org_openpsa_qbpager_direct('midgard_parameter', 'related_notes');
                $qb->results_per_page = 10;
                $this->_paged_qb = &$qb;
            }
            else
            {
                $qb = new midgard_query_builder('midgard_parameter');
            }

            $qb->begin_group('OR');
            foreach ($this->_related_guids as $guid => $related)
            {
                $qb->add_constraint('name', '=', $guid);
            }
            $qb->end_group();

            $qb->add_constraint('domain', '=', 'net.nemein.wiki:related_to');
            $ret = @$qb->execute();
            if (   is_array($ret)
                && count($ret) > 0)
            {
                foreach ($ret as $related_to)
                {
                    try
                    {
                        $wikipage = new net_nemein_wiki_wikipage($related_to->parentguid);
                        $this->related[$wikipage->guid] = $wikipage;
                    }
                    catch (midcom_error $e)
                    {
                        $e->log();
                    }
                }
            }
        }
    }

    function populate_toolbar(&$toolbar)
    {
        $enable_creation = false;
        if (   $this->wiki[MIDCOM_NAV_OBJECT]->can_do('midgard:create')
            && $this->new_wikipage)
        {
            $enable_creation = true;

            // Check for duplicates
            $qb = net_nemein_wiki_wikipage::new_query_builder();
            $qb->add_constraint('topic', '=', $this->wiki[MIDCOM_NAV_OBJECT]->id);
            $qb->add_constraint('title', '=', rawurldecode($this->new_wikipage));
            $result = $qb->execute();
            if (count($result) > 0)
            {
                $enable_creation = false;
            }
        }

       $toolbar->add_item
       (
            array
            (
                MIDCOM_TOOLBAR_URL => "{$this->wiki[MIDCOM_NAV_FULLURL]}create/{$this->new_wikipage}/{$this->target_node[MIDCOM_NAV_GUID]}/{$this->target->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create note'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-text.png',
                MIDCOM_TOOLBAR_ENABLED => $enable_creation,
                MIDCOM_TOOLBAR_OPTIONS => array
                (
                    'target' => 'wiki',
                ),
            )
        );
    }

    function show_related()
    {
        if (!$this->wiki)
        {
            return false;
        }

        $this->_list_related();
        if (   count($this->related) > 0)
        {
            echo "<div class=\"area net_nemein_wiki related\">\n";

            echo "<h2>".$this->_l10n->get('related notes')."</h2>\n";

            echo "<ul class=\"related\">\n";
            if ($this->_paged_qb)
            {
                $this->_paged_qb->show_pages();
            }
            foreach ($this->related as $wikipage)
            {
                echo "<li><a rel=\"note\" target=\"{$this->link_target}\" href=\"{$this->wiki[MIDCOM_NAV_FULLURL]}{$wikipage->name}/\">{$wikipage->title}</a></li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
        }
    }
}
?>