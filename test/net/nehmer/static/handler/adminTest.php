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
    require_once OPENPSA_TEST_ROOT . 'rootfile.php';
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class net_nehmer_static_handler_adminTest extends openpsa_testcase
{
    protected static $_topic;
    protected static $_article;

    public static function setUpBeforeClass()
    {
        $topic_attributes = array
        (
            'component' => 'net.nehmer.static',
            'name' => __CLASS__ . time()
        );
        self::$_topic = self::create_class_object('midcom_db_topic', $topic_attributes);
        $article_properties = array
        (
            'topic' => self::$_topic->id,
            'name' => __CLASS__ . time()
        );
        self::$_article = self::create_class_object('midcom_db_article', $article_properties);
    }

    public function testHandler_edit()
    {
        midcom::get('auth')->request_sudo('net.nehmer.static');

        $data = $this->run_handler(self::$_topic, array('edit', self::$_article->guid));
        $this->assertEquals('edit', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_delete()
    {
        midcom::get('auth')->request_sudo('net.nehmer.static');

        $data = $this->run_handler(self::$_topic, array('delete', self::$_article->guid));
        $this->assertEquals('delete', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>