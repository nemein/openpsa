<?php
/**
 * @package org.routamc.positioning
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapper class for access to cached object locations
 *
 * @package org.routamc.positioning
 */
class org_routamc_positioning_location_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_routamc_positioning_location';

    /**
     * Human-readable label for cases like Asgard navigation
     */
    function get_label()
    {
        if ($this->parent)
        {
            $parent = $this->get_parent();
            if ($parent)
            {
                $label = $parent->guid;
                if (isset($parent->title))
                {
                    $label = $parent->title;
                }
                elseif (method_exists($parent, 'get_label'))
                {
                    $label = $parent->get_label();
                }
                return "{$this->parentclass} {$label}";
            }
        }
        return "{$this->parentclass} #{$this->id}";
    }

    /**
     * Returns the object GUID the location is stored for
     *
     * @return string Parent GUID or null if there is none
     */
    function get_parent_guid_uncached()
    {
        if (!$this->parent)
        {
            return null;
        }

        try
        {
            $parent = midcom::get('dbfactory')->get_object_by_guid($this->parent);
            return $parent->guid;
        }
        catch (midcom_error $e)
        {
            return null;
        }
    }

    /**
     * Returns the object GUID the location is stored for
     *
     * @return string Parent GUID or null if there is none
     */
    public static function get_parent_guid_uncached_static($guid, $classname = __CLASS_)
    {
        $mc = new midgard_collector('org_routamc_positioning_location', 'guid', $guid);
        $mc->set_key_property('parent');
        $mc->execute();
        $link_values = $mc->list_keys();
        if (!$link_values)
        {
            return null;
        }

        foreach ($link_values as $key => $value)
        {
            return $key;
        }
    }

    /**
     * Checks after location cache creation
     */
    public function _on_created()
    {
        if (   !$this->log
            && $this->relation == ORG_ROUTAMC_POSITIONING_RELATION_IN)
        {
            // This location entry is defined as being made in a location,
            // but is stored to object directly without corresponding log,
            // create one.
            // This situation can happen for example when importing images
            // that have EXIF geo tags set
            $object = $this->get_parent();
            $log = new org_routamc_positioning_log();
            $log->date = $this->date;
            // TODO: Use 1.8 metadata authors instead?
            $log->person = $object->metadata->creator;
            $log->latitude = $this->latitude;
            $log->longitude = $this->longitude;
            $log->altitude = $this->altitude;
            $log->importer = 'objectlocation';
            // Usually the positions based on objects are manual, except in
            // case of GPS-equipped cameras etc. We still need to figure
            // out how to handle those.
            $log->accuracy = ORG_ROUTAMC_POSITIONING_ACCURACY_MANUAL;
            $log->create();
        }
    }
}
?>