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
class org_openpsa_products_handler_product_editTest extends openpsa_testcase
{
    protected static $_product;

    public static function setUpBeforeClass()
    {
        $group = self::create_class_object('org_openpsa_products_product_group_dba', array('code' => 'TEST_' . __CLASS__ . time()));
        self::$_product = self::create_class_object('org_openpsa_products_product_dba', array('productGroup' => $group->id));
    }

    public function testHandler_edit()
    {
        midcom::get('auth')->request_sudo('org.openpsa.products');

        $data = $this->run_handler('org.openpsa.products', array('product', 'edit', self::$_product->guid));
        $this->assertEquals('edit_product', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>