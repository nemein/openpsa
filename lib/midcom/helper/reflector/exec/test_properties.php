<?php
/**
 * @package midcom.helper.reflector
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

$test_properties = array
(
    'guid',
    'name',
    'title',
);
$test_instances = array
(
    new midgard_article(),
    new midcom_db_article(),
    'midgard_article',
    'midcom_db_article',
    new midgard_topic(),
    new midcom_db_topic(),
    'midgard_topic',
    'midcom_db_topic',
);

foreach ($test_instances as $instance)
{
    if (is_object($instance))
    {
        $instance_hr = '&lt;object of class ' . get_class($instance) . '&gt;';
    }
    else
    {
        $instance_hr = "'{$instance}'";
    }
    foreach ($test_properties as $property)
    {
        $stat = (int)midcom::get('dbfactory')->property_exists($instance, $property);
        echo "(int)\midcom::get('dbfactory')->property_exists({$instance_hr}, '{$property}') returned {$stat}<br>\n";
        $stat = (int)property_exists($instance, $property);
        echo "(int)property_exists({$instance_hr}, '{$property}') returned {$stat}<br>\n";
    }
}
?>