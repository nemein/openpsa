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
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_sales_salesproject_deliverable_adminTest extends openpsa_testcase
{
    protected static $_person;
    protected static $_salesproject;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
        self::$_salesproject = self::create_class_object('org_openpsa_sales_salesproject_dba');
    }

    public function testHandler_edit()
    {
        midcom::get('auth')->request_sudo('org.openpsa.sales');

        $deliverable_attributes = array
        (
            'salesproject' => self::$_salesproject->id,
        );

        $deliverable = $this->create_object('org_openpsa_sales_salesproject_deliverable_dba', $deliverable_attributes);
        $deliverable->set_parameter('midcom.helper.datamanager2', 'schema_name', 'subscription');

        $year = date('Y') + 1;
        $start = strtotime($year . '-10-15 00:00:00');

        $at_parameters = array
        (
            'arguments' => array
            (
                'deliverable' => $deliverable->guid,
                'cycle' => 1,
            ),
            'start' => $start,
            'component' => 'org.openpsa.sales',
            'method' => 'new_subscription_cycle'
        );

        $at_entry = $this->create_object('midcom_services_at_entry_dba', $at_parameters);
        org_openpsa_relatedto_plugin::create($at_entry, 'midcom.services.at', $deliverable, 'org.openpsa.sales');

        $data = $this->run_handler('org.openpsa.sales', array('deliverable', 'edit', $deliverable->guid));
        $this->assertEquals('deliverable_edit', $data['handler_id']);

        $group = $data['controller']->formmanager->form->getElement('next_cycle');

        $this->assertTrue($group instanceof HTML_Quickform_group, ' next cycle widget missing');
        $elements = $group->getElements();
        $this->assertEquals($year . '-10-15', $elements[0]->getValue());

        midcom::get('auth')->drop_sudo();
    }
}
?>