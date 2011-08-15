<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_relatedto_handler_relatedtoTest extends openpsa_testcase
{
    protected static $_object_from;
    protected static $_object_to;
    protected static $_relation;

    public static function setUpBeforeClass()
    {
        self::$_object_from = self::create_class_object('org_openpsa_invoices_invoice_dba');
        self::$_object_to = self::create_class_object('org_openpsa_sales_salesproject_dba');

        midcom::get('auth')->request_sudo('org.openpsa.relatedto');
        self::$_relation = org_openpsa_relatedto_plugin::create(self::$_object_from, 'org.openpsa.invoices', self::$_object_to, 'org.openpsa.sales');
        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_render_sort()
    {
        midcom::get('auth')->request_sudo('org.openpsa.relatedto');
        $data = $this->run_handler('org.openpsa.invoices', array('__mfa', 'org.openpsa.relatedto', 'render', self::$_object_from->guid, 'both', 'default'));
        $this->assertEquals('____mfa-org.openpsa.relatedto-render_sort', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_render()
    {
        midcom::get('auth')->request_sudo('org.openpsa.relatedto');

        $data = $this->run_handler('org.openpsa.invoices', array('__mfa', 'org.openpsa.relatedto', 'render', self::$_object_from->guid, 'both'));
        $this->assertEquals('____mfa-org.openpsa.relatedto-render', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

}
?>