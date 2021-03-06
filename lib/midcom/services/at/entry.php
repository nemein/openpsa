<?php
/**
 * @package midcom.services.at
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapped class for access to the at-job database entries
 *
 * @package midcom.services.at
 */
class midcom_services_at_entry_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'midcom_services_at_entry_db';

    const SCHEDULED = 100;
    const RUNNING = 110;
    const FAILED = 120;

    /**
     * Unserialized form of argumentsstore
     *
     * @var array
     */
    var $arguments = array();

    /**
     * Empty constructor
     */
    public function __construct($id = null)
    {
        $this->_use_rcs = false;
        $this->_use_activitystream = false;
        parent::__construct($id);
    }

    /**
     * Makes sure $arguments is properly set
     */
    public function _on_loaded()
    {
        $this->_unserialize_arguments();
    }

    /**
     * Makes sure we have status set and arguments serialized
     *
     * @return boolean Always true
     */
    public function _on_creating()
    {
        if (!$this->status)
        {
            $this->status = self::SCHEDULED;
        }
        if (!$this->host)
        {
            $this->host = midcom_connection::get('host');
        }
        $this->_serialize_arguments();
        return true;
    }

    /**
     * Makes sure we have arguments serialized
     *
     * @return boolean Always true
     */
    public function _on_updating()
    {
        $this->_serialize_arguments();
        return true;
    }

    /**
     * Autopurge after delete
     */
    public function _on_deleted()
    {
        $this->purge();
    }

    /**
     * Unserializes argumentsstore to arguments
     */
    function _unserialize_arguments()
    {
        $unserRet = @unserialize($this->argumentsstore);
        if ($unserRet === false)
        {
            //Unserialize failed (probably newline/encoding issue), try to fix the serialized string and unserialize again
            $unserRet = @unserialize($this->_fix_serialization($this->argumentsstore));
            if ($unserRet === false)
            {
                debug_add('Failed to unserialize argumentsstore', MIDCOM_LOG_WARN);
                $this->arguments = array();
                return;
            }
        }
        $this->arguments = $unserRet;
    }

    /**
     * Serializes arguments to argumentsstore
     */
    function _serialize_arguments()
    {
        $this->argumentsstore = serialize($this->arguments);
    }

    /**
     * Fixes newline etc encoding issues in serialized data
     *
     * @param string $data The data to fix.
     * @return string $data with serializations fixed.
     */
    function _fix_serialization($data = null)
    {
        //Skip on empty data
        if (empty($data))
        {
            return $data;
        }

        $preg = '/s:([0-9]+):"(.*?)";/ms';
        preg_match_all($preg, $data, $matches);
        $cache = array();

        foreach ($matches[0] as $k => $origFullStr)
        {
              $origLen = $matches[1][$k];
              $origStr = $matches[2][$k];
              $newLen = strlen($origStr);
              if ($newLen != $origLen)
              {
                 $newFullStr="s:$newLen:\"$origStr\";";
                 //For performance we cache information on which strings have already been replaced
                 if (!array_key_exists($origFullStr, $cache))
                 {
                     $data = str_replace($origFullStr, $newFullStr, $data);
                     $cache[$origFullStr] = true;
                 }
              }
        }

        return $data;
    }

    /**
     * By default all authenticated users should be able to do
     * whatever they wish with entry objects, later we can add
     * restrictions on object level as necessary.
     *
     * @return array MidCOM privileges
     */
    function get_class_magic_default_privileges()
    {
        $privileges = parent::get_class_magic_default_privileges();
        $privileges['USERS']['midgard:create']  = MIDCOM_PRIVILEGE_ALLOW;
        $privileges['USERS']['midgard:update']  = MIDCOM_PRIVILEGE_ALLOW;
        $privileges['USERS']['midgard:read']    = MIDCOM_PRIVILEGE_ALLOW;
        return $privileges;
    }
}
?>