<?php
/**
 * @package org.openpsa.core
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA core stuff
 *
 * @package org.openpsa.core
 */
class org_openpsa_core_interface extends midcom_baseclasses_components_interface
{
    public function _on_initialize()
    {
        $this->define_constants();

        return true;
    }

    private function define_constants()
    {
        //Constant versions of wgtype bitmasks
        define('ORG_OPENPSA_WGTYPE_NONE', 0);
        define('ORG_OPENPSA_WGTYPE_INACTIVE', 1);
        define('ORG_OPENPSA_WGTYPE_ACTIVE', 3);

        //org.openpsa.documents object types
        define('ORG_OPENPSA_OBTYPE_DOCUMENT', 3000);

        //org.openpsa.calendar object types
        define('ORG_OPENPSA_OBTYPE_EVENT', 5000);
        define('ORG_OPENPSA_OBTYPE_EVENTPARTICIPANT', 5001);
        define('ORG_OPENPSA_OBTYPE_EVENTRESOURCE', 5002);

        //org.openpsa.reports object types
        define('ORG_OPENPSA_OBTYPE_REPORT', 7000);
        define('ORG_OPENPSA_OBTYPE_REPORT_TEMPORARY', 7001);
    }
}
?>