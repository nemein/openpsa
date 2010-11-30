<?php
/**
 * @package midcom.helper.datamanager2
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Interface for edit-type handlers
 *
 * @package midcom.helper.datamanager2
 */
interface midcom_helper_datamanager2_interfaces_edit
{
    /**
     * Returns the schema to use
     *
     * @return string The identifier of the schema to use  
     */
    public function get_schema_name();

    /**
     * Loads and prepares the schema database.
     *
     * @return array The prepared schema DB
     */
    public function load_schemadb();
}

?>