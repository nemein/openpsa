<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . DIRECTORY_SEPARATOR);
    require_once OPENPSA_TEST_ROOT . 'rootfile.php';
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_products_handler_group_createTest extends openpsa_testcase
{
    public function testHandler_create()
    {
        midcom::get('auth')->request_sudo('org.openpsa.products');

        $group = $this->create_object('org_openpsa_products_product_group_dba');

        $data = $this->run_handler('org.openpsa.products', array('create', $group->id, 'default'));
        $this->assertEquals('create_group', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>