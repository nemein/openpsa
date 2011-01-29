<?php
/**
 * @package midcom
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM context handling
 *
 * Provides a basic context mechanism that allows each independent component
 * invocation to access its own context information.
 *
 * @package midcom
 */
class midcom_core_context
{
    /**
     * Holds the component context information.
     *
     * This is an array of arrays, the outer one indexed by context IDs, the
     * inner one indexed by context keys. Only valid of the system has left
     * the code-init phase.
     *
     * @var array
     */
    private static $_contexts = array();

    /**
     * Contains the ID of the currently active context
     *
     * @var int
     */
    private static $_currentcontext = 0;

    /**
     * The context's data
     *
     * @var array
     */
    private $_data = array();

    /**
     * The context's ID
     *
     * @var int
     */
    public $id;

    /**
     * The context's URL parser instance
     *
     * @var midcom_core_service_urlparser
     */
    public $parser;

    /**
     * Create and prepare a new component context.
     *
     * @param int $id Explicitly specify the ID for context creation (used during construction), this parameter is usually omitted.
     * @param MidgardObject $node Root node of the context
     * @return int The ID of the newly created component.
     */
    public function __construct($id = null, $node = null)
    {
        $this->_data[MIDCOM_CONTEXT_ANCHORPREFIX] = '';
        $this->_data[MIDCOM_CONTEXT_URI] = $_SERVER['REQUEST_URI'];
        if (is_object($node))
        {
            $this->_data[MIDCOM_CONTEXT_ROOTTOPIC] = $node;
            $this->_data[MIDCOM_CONTEXT_ROOTTOPICID] = $node->id;
        }
        else
        {
            $this->_data[MIDCOM_CONTEXT_ROOTTOPIC] = null;
            $this->_data[MIDCOM_CONTEXT_ROOTTOPICID] = null;
        }
        $this->_data[MIDCOM_CONTEXT_CONTENTTOPIC] = null;
        $this->_data[MIDCOM_CONTEXT_COMPONENT] = null;
        $this->_data[MIDCOM_CONTEXT_PAGETITLE] = "";
        $this->_data[MIDCOM_CONTEXT_LASTMODIFIED] = null;
        $this->_data[MIDCOM_CONTEXT_PERMALINKGUID] = null;
        $this->_data[MIDCOM_CONTEXT_CUSTOMDATA] = Array();
        $this->_data[MIDCOM_CONTEXT_URLTOPICS] = Array();

        if (is_null($id))
        {
            $id = count(self::$_contexts);
        }
        $this->id = $id;
        self::$_contexts[$id] =& $this;
    }

    /**
     * Marks context as current
     *
     * @return boolean    Indicating if the switch was successful.
     */
    public function set_current()
    {
        self::$_currentcontext = $this->id;
    }

    public static function & get($id = null)
    {
        if (is_null($id))
        {
            $id = self::$_currentcontext;
        }
        if (   $id < 0
            || $id >= count(self::$_contexts))
        {
            debug_add("Could not get invalid context $id.", MIDCOM_LOG_WARN);
            $ret = false;
            return $ret;
        }
        else
        {
            return self::$_contexts[$id];
        }
    }

    /**
     * Returns the complete context data array
     *
     * @todo This should be removed and places using this rewritten
     * @return array The data of all contexts
     */
    public static function get_all()
    {
        return self::$_contexts;
    }

    /**
     * Access the MidCOM component context
     *
     * Returns Component Context Information associated to the component with the
     * context identified by $key.
     *
     * @param int $key  The key requested
     * @return mixed    The content of the key being requested.
     */
    public function get_key($key)
    {
        if (!array_key_exists($key, $this->_data) || $key >= 1000)
        {
            debug_add("Requested Key ID $key invalid.", MIDCOM_LOG_ERROR);
            return false;
        }

        return $this->_data[$key];
    }


    /**
     * Update the component context
     *
     * This function sets a variable of the current or the given component context.
     *
     * @param int $key  The key to use
     * @param mixed $value    The value to be stored
     * @see get_context_data()
     */
    public function set_key($key, $value)
    {
        $this->_data[$key] = $value;
    }

    /**
     * Retrieve arbitrary, component-specific information in the component context
     *
     * The set call defaults to the current context, the get call's semantics are as
     * with get_context_data.
     *
     * Note, that if you are working from a library like the datamanager is, you
     * cannot override the component association done by the system. Instead you
     * should add your libraries name (like midcom.helper.datamanager) as a prefix,
     * separated by a dot. I know, that this is not really an elegant solution and
     * that it actually breaks with the encapsulation I want, but I don't have a
     * better solution yet.
     *
     * A complete example can be found with set_custom_context_data.
     *
     * @param string $component    The component name
     * @param int $key    The requested key
     * @return mixed      The requested value, which is returned by Reference!
     * @see get_key()
     * @see set_custom_key()
     */
    public function & get_custom_key($component, $key)
    {
        if (   !array_key_exists($component, $this->_data[MIDCOM_CONTEXT_CUSTOMDATA])
            || !array_key_exists($key, $this->_data[MIDCOM_CONTEXT_CUSTOMDATA][$component]))
        {
            debug_add("Requested Key ID {$key} for the component {$component} is invalid.", MIDCOM_LOG_ERROR);
            $result = false;
            return $result;
        }

        return $this->_data[MIDCOM_CONTEXT_CUSTOMDATA][$component][$key];
    }

    /**
     * Store arbitrary, component-specific information in the component context
     *
     * This method allows you to get custom data to a given context.
     * The system will automatically associate this data with the component from the
     * currently active context. You cannot access the custom data of any other
     * component this way, it is private to the context. You may attach information
     * to other contexts, which will be associated with the current component, so
     * you have a clean namespace independently from which component or context you
     * are operating of. All calls honor references of passed data, so you can use
     * this for central controlling objects.
     *
     * Note, that if you are working from a library like the datamanager is, you
     * cannot override the component association done by the system. Instead you
     * should add your libraries name (like midcom.helper.datamanager) as a prefix,
     * separated by a dot. I know, that this is not really an elegant solution and
     * that it actually breaks with the encapsulation I want, but I don't have a
     * better solution yet.
     *
     * Be aware, that this function works by-reference instead of by-value.
     *
     * A complete example could look like this:
     *
     * <code>
     * class my_component_class_one {
     *     function init () {
     *         $_MIDCOM->set_custom_context_data('classone', $this);
     *     }
     * }
     *
     * class my_component_class_two {
     *        var one;
     *     function my_component_class_two () {
     *         $this->one =& $_MIDCOM->get_custom_context_data('classone');
     *     }
     * }
     * </code>
     *
     * @param string $component The component associated to the key.
     * @param mixed $key        The key associated to the value.
     * @param mixed &$value    The value to store. (This is stored by-reference!)
     * @see get_custom_key()
     */
    function set_custom_key ($component, $key, &$value)
    {
        $this->_data[MIDCOM_CONTEXT_CUSTOMDATA][$component][$key] =& $value;
    }
}
?>