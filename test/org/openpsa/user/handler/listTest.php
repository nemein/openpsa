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
class org_openpsa_user_handler_listTest extends openpsa_testcase
{
    protected static $_user;

    public static function setUpBeforeClass()
    {
        self::$_user = self::create_user(true);
    }

    public function test_handler_list()
    {
        midcom::get('auth')->request_sudo('org.openpsa.user');

        $data = $this->run_handler('org.openpsa.user');
        $this->assertEquals('user_list', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function test_handler_json()
    {
        midcom::get('auth')->request_sudo('org.openpsa.user');

        $data = $this->run_handler('org.openpsa.user', array('json'));
        $this->assertEquals('user_list_json', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>