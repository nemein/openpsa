<?php
/**
 * @package midcom.helper.datamanager2
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Datamanager 2 Autocomplete result lister
 *
 * @package midcom.helper.datamanager2
 */
class midcom_helper_datamanager2_ajax_autocomplete
{
    /**
     * The request data we're working on
     *
     * @var array
     */
    private $_request;

    public function __construct($data)
    {
        $this->_request = $data;
        $this->_verify_request();
    }

    private function _verify_request()
    {
        // Load component if possible
        midcom::get('componentloader')->load_graceful($this->_request['component']);

        $error = '';
        // Could not get required class defined, abort
        if (!class_exists($this->_request['class']))
        {
            $error = "Class {$this->_request['class']} could not be loaded\n";
        }

        // No fields to search by, abort
        if (empty($this->_request['searchfields']))
        {
            $error = "No fields to search for defined\n";
        }

        if (empty($this->_request["term"]))
        {
            $error = "Empty query string.";
        }

        if (empty($this->_request["id_field"]))
        {
            $error = "Empty ID field.";
        }

        if ($error != '')
        {
            throw new midcom_error($error);
        }

        if (!isset($this->_request['get_label_for']))
        {
            $this->_request['get_label_for'] = null;
        }
    }

    private function _prepare_qb()
    {
        $query = $this->_request["term"];
        $wildcard_query = $this->_add_wildcards($query);

        $qb = @call_user_func(array($this->_request['class'], 'new_query_builder'));
        if (! $qb)
        {
            debug_add("use midgard_query_builder");
            $qb = new midgard_query_builder($class);
        }

        if (   !empty($this->_request['constraints'])
            && is_array($this->_request['constraints']))
        {
            $constraints = $this->_request['constraints'];
            ksort($constraints);
            reset($constraints);
            foreach ($constraints as $key => $data)
            {
                if (   !array_key_exists('value', $data)
                    || empty($data['field'])
                    || empty($data['op']))
                {
                    debug_add("Constraint #{$key} is not correctly defined, skipping", MIDCOM_LOG_WARN);
                    continue;
                }
                $qb->add_constraint($data['field'], $data['op'], $data['value']);
            }
        }

        if (preg_match('/^%+$/', $query))
        {
            debug_add('$query is all wildcards, don\'t waste time in adding LIKE constraints');
        }
        else
        {
            $reflector = new midgard_reflection_property(midcom_helper_reflector::resolve_baseclass($this->_request['class']));

            $qb->begin_group('OR');
            foreach ($this->_request['searchfields'] as $field)
            {
                $field_type = $reflector->get_midgard_type($field);
                $operator = 'LIKE';
                if (strpos($field, '.'))
                {
                    //TODO: This should be resolved properly
                    $field_type = MGD_TYPE_STRING;
                }
                switch ($field_type)
                {
                    case MGD_TYPE_GUID:
                    case MGD_TYPE_STRING:
                    case MGD_TYPE_LONGTEXT:
                        debug_add("adding search (ORed) constraint: {$field} LIKE '{$wildcard_query}'");
                        $qb->add_constraint($field, 'LIKE', $wildcard_query);
                        break;
                    case MGD_TYPE_INT:
                    case MGD_TYPE_UINT:
                    case MGD_TYPE_FLOAT:
                        debug_add("adding search (ORed) constraint: {$field} = {$query}'");
                        $qb->add_constraint($field, '=', $query);
                        break;
                    default:
                        debug_add("can't handle field type " . $field_type, MIDCOM_LOG_WARN);
                        break;
                }
            }
            $qb->end_group();
        }

        if (   !empty($this->_request['orders'])
            && is_array($this->_request['orders']))
        {
            ksort($this->_request['orders']);
            reset($this->_request['orders']);
            foreach ($this->_request['orders'] as $data)
            {
                foreach ($data as $field => $order)
                {
                    $qb->add_order($field, $order);
                }
            }
        }
        return $qb;
    }

    private function _add_wildcards($query)
    {
        $wildcard_query = $query;
        if (   isset($this->_request['auto_wildcards'])
            && strpos($query, '%') === false)
        {
            switch ($this->_request['auto_wildcards'])
            {
                case 'start':
                    $wildcard_query = '%' . $query;
                    break;
                case 'end':
                    $wildcard_query = $query . '%';
                    break;
                case 'both':
                    $wildcard_query = '%' . $query . '%';
                    break;
                default:
                    debug_add("Don't know how to handle auto_wildcards value '" . $this->_request['auto_wildcards'] . "'", MIDCOM_LOG_WARN);
                    break;
            }
        }
        $wildcard_query = str_replace("*", "%", $wildcard_query);
        $wildcard_query = preg_replace('/%+/', '%', $wildcard_query);
        return $wildcard_query;
    }

    public function get_results()
    {
        $qb = $this->_prepare_qb();
        $results = $qb->execute();
        if (   $results === false
            || !is_array($results))
        {
            throw new midcom_error('Error when executing QB');
        }

        $items = array();

        foreach ($results as $object)
        {
            $item = array
            (
                'id' => $object->{$this->_request['id_field']},
                'label' => midcom_helper_datamanager2_widget_autocomplete::create_item_label($object, $this->_request['result_headers'], $this->_request['get_label_for']),
            );
            if (!empty($this->_request['categorize_by_parent_label']))
            {
                $item['category'] = '';
                if ($parent = $object->get_parent())
                {
                    $item['category'] = $parent->get_label();
                }
            }
            $item['value'] = $item['label'];

            $items[] = $item;
        }

        usort($items, array('midcom_helper_datamanager2_widget_autocomplete', 'sort_items'));

        return $items;
    }
}
?>