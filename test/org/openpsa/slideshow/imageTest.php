<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR);
    require_once OPENPSA_TEST_ROOT . 'rootfile.php';
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_slideshow_imageTest extends openpsa_testcase
{
    public function testCRUD()
    {
        $topic = $this->get_component_node('org.openpsa.slideshow');
        midcom::get('auth')->request_sudo('org.openpsa.slideshow');

        $image = new org_openpsa_slideshow_image_dba();
        $image->topic = $topic->id;
        $stat = $image->create();
        $this->assertTrue($stat);
        $this->register_object($image);

        $image->title = 'TEST';
        $stat = $image->update();
        $this->assertTrue($stat);

        $this->assertEquals('TEST', $image->title);

        $stat = $image->delete();
        $this->assertTrue($stat);

        midcom::get('auth')->drop_sudo();
    }
}
?>
