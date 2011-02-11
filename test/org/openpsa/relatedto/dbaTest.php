<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

require_once('rootfile.php');

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_relatedto_dbaTest extends openpsa_testcase
{
    public function testCRUD()
    {
        $relatedto = new org_openpsa_relatedto_dba();

        $_MIDCOM->auth->request_sudo('org.openpsa.relatedto');
        $stat = $relatedto->create();
        $this->assertTrue($stat);
        $this->assertEquals($relatedto->status, ORG_OPENPSA_RELATEDTO_STATUS_SUSPECTED);

        $relatedto->status = ORG_OPENPSA_RELATEDTO_STATUS_CONFIRMED;
        $stat = $relatedto->update();
        $this->assertTrue($stat);
        $this->assertEquals($relatedto->status, ORG_OPENPSA_RELATEDTO_STATUS_CONFIRMED);

        $stat = $relatedto->delete();
        $this->assertTrue($stat);

        $_MIDCOM->auth->drop_sudo();
    }
}
?>