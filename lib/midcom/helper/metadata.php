<?php
/**
 * @package midcom.helper
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class is an interface to the metadata of MidCOM objects. It is not to
 * be instantiated directly, as a cache is in place to avoid duplicate metadata
 * objects for the same Midgard Object. So, basically, each of these objects is
 * a singleton.
 *
 * It will use an internal mechanism to cache repeated accesses to the same
 * metadata key during its lifetime. (Invalidating this cache will be possible
 * though.)
 *
 * <b>Metadata Key Reference</b>
 *
 * See also the schema in /midcom/config/metadata_default.inc
 *
 * - <b>timestamp schedulestart:</b> The time upon which the object should be made visible. 0 for no restriction.
 * - <b>timestamp scheduleend:</b> The time upon which the object should be made invisible. 0 for no restriction.
 * - <b>boolean navnoentry:</b> Set this to true if you do not want this object to appear in the navigation without it being completely hidden.
 * - <b>boolean hide:</b> Set this to true to hide the object on-site, overriding scheduling.
 * - <b>string keywords:</b> The keywords for this object, should be used for META HTML headers.
 * - <b>string description:</b> A short description for this object, should be used for META HTML headers.
 * - <b>string robots:</b> Search engine crawler instructions, one of '' (unset), 'noindex', 'index', 'follow' and 'nofollow'.
 *      See the corresponding META HTML header.
 * - <b>timestamp published:</b> The publication time of the object, read-only.
 * - <b>MidgardPerson publisher:</b> The person that published the object (i.e. author), read-only except on articles and pages.
 * - <b>timestamp created:</b> The creation time of the object, read-only unless an article is edited.
 * - <b>MidgardPerson creator:</b> The person that created the object, read-only.
 * - <b>timestamp revised:</b> The last-modified time of the object, read-only.
 * - <b>MidgardPerson revisor:</b> The person that modified the object, read-only.
 * - <b>timestamp approved:</b> The time of approval of the object, or 0 if not approved. Set automatically through approve/unapprove.
 * - <b>MidgardPerson approver:</b> The person that approved/unapproved the object. Set automatically through approve/unapprove.
 *
 * <b>Example Usage, Metadata Retrieval</b>
 *
 * <code>
 * <?php
 * $nap = new midcom_helper_nav();
 * $node = $nap->get_node($nap->get_current_node());
 *
 * $meta = midcom_helper_metadata::retrieve($node[MIDCOM_NAV_GUID]);
 * echo "Visible : " . $meta->is_visible() . "</br>";
 * echo "Approved : " . $meta->is_approved() . "</br>";
 * echo "Keywords: " . $meta->get('keywords') . "</br>";
 * ?>
 * </code>
 *
 * <b>Example Usage, Approval</b>
 *
 * <code>
 * <?php
 * $article = new midcom_db_article($my_article_created_id);
 *
 * $meta = midcom_helper_metadata::retrieve($article);
 * $article->approve();
 * ?>
 * </code>
 *
 * @package midcom.helper
 */
class midcom_helper_metadata
{
    /**
     * Object to which we are attached to. This object can be accessed from
     * the outside, where necessary.
     *
     * @var MidgardObject
     */
    public $__object = null;

    /**
     * Metadata object of the current object
     *
     * @var midgard_metadata
     */
    private $__metadata = null;

    /**
     * The guid of the object, it is cached for fast access to avoid repeated
     * database queries.
     *
     * @var string GUID
     */
    var $guid = '';

    /**
     * Holds the values already read from the database.
     *
     * @var Array
     */
    private $_cache = Array();

    /**
     * The schema database URL to use for this instance.
     *
     * @var string
     */
    private $_schemadb_path = null;

    /**
     * Datamanager instance for the given object.
     *
     * @var midcom_helper_datamanager2
     */
    private $_datamanager = null;

    /**
     * Translation array for the object
     *
     * @var array
     */
    private $_translations = null;

    /**
     * This will construct a new metadata object for an existing content object.
     *
     * You must never use this constructor directly, it is considered private
     * in this respect. Instead, use the get method, which may be called as a
     * class method.
     *
     * You may use objects derived from any MidgardObject will do as well as long
     * as the parameter call is available normally.
     *
     * @param GUID $guid The GUID of the object as it is in the global metadata object cache.
     * @param mixed $object The MidgardObject to attach to.
     * @param string $schemadb The URL of the schemadb to use.
     * @see midcom_helper_metadata::get()
     */
    public function __construct(&$guid, $object, $schemadb)
    {
        $this->guid =& $guid;
        $this->__metadata = $object->__object->metadata;
        $this->__object = $object;
        $this->_schemadb_path = $schemadb;
    }


    /* ------- BASIC METADATA INTERFACE --------- */

    /**
     * This function will return a single metadata key from the object. Its return
     * type depends on the metadata key that is requested (see the class introduction).
     *
     * You will not get the data from the datamanager using this calls, but the only
     * slightly post-processed metadata values. See _retrieve_value for post processing.
     *
     * @see midcom_helper_metdata::_retrieve_value()
     * @param string $key The key to retrieve
     * @return mixed The key's value.
     */
    function get($key)
    {
        if (!$this->__metadata)
        {
            return null;
        }

        if (!isset($this->_cache[$key]))
        {
            $this->_retrieve_value($key);
        }

        return $this->_cache[$key];
    }

    public function __get($key)
    {
        if ($key == 'object')
        {
            return $this->__object;
        }
        return $this->get($key);
    }

    public function __isset($key)
    {
        if (!$this->__metadata)
        {
            return false;
        }

        if (!isset($this->_cache[$key]))
        {
            $this->_retrieve_value($key);
        }

        return isset($this->_cache[$key]);
    }

    /**
     * Return a Datamanager instance for the current object.
     *
     * This is returned by reference, which must be honored, as usual.
     *
     * Also, whenever the containing datamanager stores its data, you
     * <b>must</b> call the on_update() method of this class. This is
     * very important or backwards compatibility will be broken.
     *
     * @return midcom_helper_datamanager2 A initialized Datamanager instance for the selected object.
     * @see midcom_helper_metadata::on_update()
     */
    function & get_datamanager()
    {
        if (is_null($this->_datamanager))
        {
            $this->load_datamanager();
        }
        return $this->_datamanager;
    }

    /**
     * Loads the datamanager for this instance. This will patch the schema in case we
     * are dealing with an article.
     */
    function load_datamanager()
    {
        static $schemadbs = array();
        if (!array_key_exists($this->_schemadb_path, $schemadbs))
        {
            $schemadbs[$this->_schemadb_path] = midcom_helper_datamanager2_schema::load_database($this->_schemadb_path);
        }
        $this->_schemadb = $schemadbs[$this->_schemadb_path];
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_schemadb);

        // Check if we have metadata schema defined in the schemadb specific for the object's schema or component
        $object_schema = $this->__object->get_parameter('midcom.helper.datamanager2', 'schema_name');
        $component_schema = str_replace('.', '_', midcom_core_context::get()->get_key(MIDCOM_CONTEXT_COMPONENT));
        if (   $object_schema == ''
            || !isset($this->_schemadb[$object_schema]))
        {
            if (isset($this->_schemadb[$component_schema]))
            {
                // No specific metadata schema for object, fall back to component-specific metadata schema
                $object_schema = $component_schema;
            }
            else
            {
                // No metadata schema for component, fall back to default
                $object_schema = 'metadata';
            }
        }
        $this->_datamanager->set_schema($object_schema);
        if (! $this->_datamanager->set_storage($this->__object))
        {
            throw new midcom_error('Failed to initialize the metadata datamanager instance, see the Debug Log for details.');
        }
    }

    function release_datamanager()
    {
        if (! is_null($this->_datamanager))
        {
            $this->_datamanager = null;
        }
    }

    /**
     * Frontend for setting a single metadata option
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     */
    function set($key, $value)
    {
        $return = false;
        if ($this->_set_property($key, $value))
        {
            if ($this->__object->guid)
            {
                $return = $this->__object->update();

            }
            else
            {
                $return = true;
            }
            // Update the corresponding cache variable
            $this->on_update($key);
        }
        return $return;
    }

    public function __set($key, $value)
    {
        switch ($key)
        {
            case '_schemadb':
                $this->_schemadb = $value;
                return true;
            default:
                return $this->set($key, $value);
        }
    }

    /**
     * Frontend for setting multiple metadata options
     *
     * @param Array $properties Array of key => value properties.
     */
    function set_multiple($properties)
    {
        $return = false;
        foreach ($properties as $key => $value)
        {
            if (!$this->_set_property($key, $value))
            {
                return false;
            }
        }

        if (!$this->__object->guid)
        {
            return false;
        }

        if ($this->__object->update())
        {
            $return = true;
            // Update the corresponding cache variables
            foreach ($properties as $key => $value)
            {
                $this->on_update($key);
            }
        }
        return $return;
    }

    /**
     * Directly set a metadata option.
     *
     * The passed value will be stored using the follow transformations:
     *
     * - Storing into the approver field will automatically recognize Person Objects and simple
     *   IDs and transform them into a GUID.
     * - created can only be set with articles.
     * - creator, editor and edited cannot be set.
     *
     * Any error will trigger midcom_error.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     */
    private function _set_property($key, $value)
    {
        if (is_object($value))
        {
            $classname = get_class($value);
            debug_add("Can not set metadata '{$key}' property with '{$classname}' object as value", MIDCOM_LOG_WARN);

            return false;
        }

        // Store the RCS mode
        $rcs_mode = $this->__object->_use_rcs;

        switch ($key)
        {
            // Read-only properties
            case 'creator':
            case 'created':
            case 'revisor':
            case 'revised':
            case 'locker':
            case 'locked':
            case 'revision':
            case 'size':
            case 'deleted':
            case 'exported':
            case 'imported':
                midcom_connection::set_error(MGD_ERR_ACCESS_DENIED);
                return false;

            // Writable properties
            case 'published':
            case 'schedulestart':
            case 'scheduleend':
                // Cast to ISO datetime
                if (!is_numeric($value))
                {
                    $value = 0;
                }
                if ($value == 0)
                {
                    $this->__metadata->$key = '0000-00-00 00:00:00';
                }
                else
                {
                    $this->__metadata->$key = gmstrftime('%Y-%m-%d %T', $value);
                }

                if (extension_loaded('midgard2'))
                {
                    if ($this->__metadata->$key == '0000-00-00 00:00:00')
                    {
                        $this->__metadata->$key = null;
                    }
                    else
                    {
                        $this->__metadata->$key = new midgard_datetime($this->__metadata->$key);
                    }
                }
                $value = true;
                break;

            case 'approver':
            case 'approved':
                // Prevent lock changes from creating new revisions
                $this->__object->_use_rcs = false;
                // Fall through
            case 'authors':
            case 'owner':
            case 'hidden':
            case 'navnoentry':
            case 'score':
                $this->__metadata->$key = $value;
                $value = true;
                break;

            // Fall-back for non-core properties
            default:
                $value = $this->__object->set_parameter('midcom.helper.metadata', $key, $value);
                break;
        }

        // Return the original RCS mode
        $this->__object->_use_rcs = $rcs_mode;

        return $value;
    }



    /**
     * This is the update event handler for the Metadata system. It must be called
     * whenever metadata changes to synchronize the various backwards-compatibility
     * values in place throughout the system.
     *
     * @param string $key The key that was updated. Leave empty for a complete update by the Datamanager.
     */
    function on_update($key = false)
    {
        if (   $key
            && array_key_exists($key, $this->_cache))
        {
            unset ($this->_cache[$key]);
        }
        else
        {
            $this->_cache = Array();
        }

        // TODO: Add Caching Code here, and do invalidation of the nap part manually.
        // so that we don't lose the cache of the metadata already in place.
        // Just be intelligent here :)
        if (!empty($this->guid))
        {
            midcom::get('cache')->invalidate($this->guid);
        }
    }

    /* ------- METADATA I/O INTERFACE -------- */

    /**
     * Retrieves a given metadata key, postprocesses it where necessary
     * and stores it into the local cache.
     *
     * - Person references (both guid and id) get resolved into the corresponding
     *   Person object.
     * - created, creator, edited and editor are taken from the corresponding
     *   MidgardObject fields.
     * - Parameters are accessed using their midgard-created member variables
     *   instead of accessing the database using $object->parameter directly for
     *   performance reasons (this will implicitly use the NAP cache for these
     *   values as well. (Implementation note: Variable variables have to be
     *   used for this, as we have dots in the member name.)
     *
     * Note, that we hide any errors from not existent properties explicitly,
     * as a few of the MidCOM objects do not support all of the predefined meta
     * data fields, PHP will default to "0" in these cases. For Person IDs, this
     * "0" is rewritten to "1" to use the MidgardAdministrator account instead.
     *
     * @param string $key The key to retrieve.
     */
    private function _retrieve_value($key)
    {
        switch ($key)
        {
            // Time-based properties
            case 'created':
            case 'revised':
            case 'published':
            case 'locked':
            case 'approved':
            case 'schedulestart':
            case 'scheduleend':
            case 'exported':
            case 'imported':
                if (   extension_loaded('midgard2')
                    && isset($this->__metadata->$key))
                {
                    //This is ugly, but seems the only possible way...
                    if ((string) $this->__metadata->$key === "0001-01-01T00:00:00+00:00")
                    {
                        $value = 0;
                    }
                    else
                    {
                        $value = (int) $this->__metadata->$key->format('U');
                    }
                }
                else if (   empty($this->__metadata->$key)
                         || $this->__metadata->$key == '0000-00-00 00:00:00')
                {
                    $value = 0;
                }
                else
                {
                    $value = strtotime("{$this->__metadata->$key} UTC");
                }
                break;

            // Person properties
            case 'creator':
            case 'revisor':
            case 'locker':
            case 'approver':
                $value = $this->__metadata->$key;
                if (!$value)
                {
                    // Fall back to "Midgard root user" if person is not found
                    static $root_user_guid = null;
                    if (!$root_user_guid)
                    {
                        $mc = new midgard_collector('midgard_person', 'id', 1);
                        $mc->set_key_property('guid');
                        $mc->execute();
                        $guids = $mc->list_keys();
                        if (empty($guids))
                        {
                            $root_user_guid = 'f6b665f1984503790ed91f39b11b5392';
                        }
                        else
                        {
                            foreach ($guids as $guid => $val)
                            {
                                $root_user_guid = $guid;
                            }
                        }
                    }

                    $value = $root_user_guid;
                }
                break;

            // Other midgard_metadata properties
            case 'revision':
            case 'hidden':
            case 'navnoentry':
            case 'size':
            case 'deleted':
            case 'score':
            case 'authors':
            case 'owner':
                $value = $this->__metadata->$key;
                break;

            // Fall-back for non-core properties
            default:
                $dm = $this->get_datamanager();
                if (!isset($dm->types[$key]))
                {
                    // Fall back to the parameter reader for non-core MidCOM metadata params
                    $value = $this->__object->get_parameter('midcom.helper.metadata', $key);
                }
                else
                {
                    $value = $dm->types[$key]->convert_to_csv();
                }

                break;
        }
        $this->_cache[$key] = $value;
    }


    /* ------- CONVENIENCE METADATA INTERFACE --------- */

    /**
     * Checks whether the article has been approved since its last editing.
     *
     * @return boolean Indicating approval state.
     */
    function is_approved()
    {
        return $this->__object->is_approved();
    }

    /**
     * Checks the object's visibility regarding scheduling and the hide flag.
     *
     * This does not check approval, use is_approved for that.
     *
     * @see midcom_helper_metadata::is_approved()
     * @return boolean Indicating visibility state.
     */
    function is_visible()
    {
        if ($this->get('hidden'))
        {
            return false;
        }

        $now = time();
        if (   $this->get('schedulestart')
            && $this->get('schedulestart') > $now)
        {
            return false;
        }
        if (   $this->get('scheduleend')
            && $this->get('scheduleend') < $now)
        {
            return false;
        }
        return true;
    }

    /**
     * This is a helper function which indicates whether a given object may be shown onsite
     * taking approval, scheduling and visibility settings into account. The important point
     * here is that it also checks the global configuration defaults, so that this is
     * basically the same base on which NAP decides whether to show an item or not.
     *
     * @return boolean Indicating visibility.
     */
    function is_object_visible_onsite()
    {
        return
        (   (   $GLOBALS['midcom_config']['show_hidden_objects']
             || $this->is_visible())
         && (   $GLOBALS['midcom_config']['show_unapproved_objects']
             || $this->is_approved())
        );
    }

    /**
     * Approves the object.
     *
     * This sets the approved timestamp to the current time and the
     * approver person GUID to the GUID of the person currently
     * authenticated.
     */
    function approve()
    {
        midcom::get('auth')->require_do('midcom:approve', $this->__object);
        midcom::get('auth')->require_do('midgard:update', $this->__object);

        if (!is_object($this->__object))
        {
            return false;
        }

        return $this->__object->approve();
    }

    /**
     * Approve, if object is already approved update
     * and approve.
     *
     * This is to get the approval timestamp to current time in all cases
     */
    function force_approve()
    {
        midcom::get('auth')->require_do('midcom:approve', $this->__object);
        midcom::get('auth')->require_do('midgard:update', $this->__object);
        if (!is_object($this->__object))
        {
            return false;
        }

        if ($this->__object->is_approved())
        {
            $this->__object->update();
        }
        return $this->__object->approve();
    }

    /**
     * Unapproves the object.
     *
     * This resets the approved timestamp and sets the
     * approver person GUID to the GUID of the person currently
     * authenticated.
     */
    function unapprove()
    {
        midcom::get('auth')->require_do('midcom:approve', $this->__object);
        midcom::get('auth')->require_do('midgard:update', $this->__object);

        if (!is_object($this->__object))
        {
            return false;
        }

        return $this->__object->unapprove();
    }


    /* ------- CLASS MEMBER FUNCTIONS ------- */

    /**
     * Returns a metadata object for a given content object.
     *
     * You may bass any one of the following arguments to the function:
     *
     * - Any class derived from MidgardObject, you must only ensure, that the parameter
     *   and guid member functions stays available.
     * - Any valid GUID
     * - Any NAP object structure, the content object is deduced from MIDCOM_NAV_GUID in
     *   this case.
     *
     * <b>Important note:</b> The metadata object is returned by reference. You are very
     * much encouraged to honor this reference, otherwise, the internal metadata value cache
     * won't really help.
     *
     * @param mixed $source The object to attach to, this may be either a MidgardObject, a GUID or a NAP data structure (node or leaf).
     * @return midcom_helper_metadata A reference to the created metadata object.
     */
    public static function retrieve($source)
    {
        static $_object_cache = array();

        $object = null;
        $guid = '';

        if (is_object($source))
        {
            $object = $source;
            $guid = $source->guid;
        }
        else
        {
            if (is_array($source))
            {
                if (   array_key_exists(MIDCOM_NAV_GUID, $source)
                    && ! is_null($source[MIDCOM_NAV_GUID]))
                {
                    $guid = $source[MIDCOM_NAV_GUID];
                    $object = $source[MIDCOM_NAV_OBJECT];
                }
                else
                {
                    debug_print_r('We got an invalid input, cannot return metadata:', $source);

                    return false;
                }
            }
            else
            {
                $guid = $source;
            }
        }

        if (   mgd_is_guid($guid)
            && isset($_object_cache[$guid]))
        {
            // Cache hit
            return $_object_cache[$guid];
        }

        // We don't have a cache hit, return a newly constructed object.
        if (   is_null($object)
            && mgd_is_guid($guid))
        {
            try
            {
                $object = midcom::get('dbfactory')->get_object_by_guid($guid);
            }
            catch (midcom_error $e)
            {
                debug_add("Failed to create a metadata instance for the GUID {$guid}: " . $e->getMessage(), MIDCOM_LOG_WARN);
                debug_print_r("Source was:", $source);

                return false;
            }
        }

        // $object is now populated too
        $meta = new midcom_helper_metadata($guid, $object, $GLOBALS['midcom_config']['metadata_schema']);

        if (count($_object_cache) >= $GLOBALS['midcom_config']['cache_module_nap_metadata_cachesize'])
        {
            array_shift($_object_cache);
        }
        $_object_cache[$guid] =& $meta;

        return $meta;
    }

    /**
     * Check if the requested object is locked
     *
     * @param mixed &$object    MgdSchema object
     * @return boolean          True if the object is locked, false if it isn't
     */
    public function is_locked()
    {
        // Object hasn't been marked to be edited
        if ($this->get('locked') == 0)
        {
            return false;
        }

        if (($this->get('locked') + ($GLOBALS['midcom_config']['metadata_lock_timeout'] * 60)) < time())
        {
            // lock expired, explicitly clear lock
            $this->unlock();
            return false;
        }

        // Lock was created by the user, return "not locked"
        if (   !empty(midcom::get('auth')->user->guid)
            && $this->get('locker') === midcom::get('auth')->user->guid)
        {
            return false;
        }

        // Unlocked states checked and none matched, consider locked
        return $this->__object->is_locked();
    }

    /**
     * Set the object lock
     *
     * @param int $timeout   Length of the lock timeout
     * @param String $user   GUID of the midgard_person object
     * @return boolean       Indicating success
     */
    public function lock($timeout = null, $user = null)
    {
        midcom::get('auth')->require_do('midgard:update', $this->__object);

        if (!$timeout)
        {
            $timeout = $GLOBALS['midcom_config']['metadata_lock_timeout'];
        }

        if (!is_object($this->__object))
        {
            return false;
        }

        return $this->__object->lock();
    }

    /**
     * Check whether current user can unlock the object
     *
     * @return boolean indicating privileges
     * @todo enable specifying user ?
     */
    function can_unlock()
    {
        if (   !$this->__object->can_do('midcom:unlock')
            && !midcom::get('auth')->can_user_do('midcom:unlock', null, 'midcom_services_auth', 'midcom'))
        {
            return false;
        }
        return true;
    }

    /**
     * Unlock the object
     *
     * @param boolean $soft_unlock If this is true, the changes are not written to disk
     * @return boolean    Indicating success
     */
    public function unlock($soft_unlock = false)
    {
        if (!$this->can_unlock())
        {
            return false;
        }

        if (!is_object($this->__object))
        {
            return false;
        }

        // TODO: Should we support soft unlock somehow?

        // Run the unlock call
        $stat = $this->__object->unlock();

        // Clear cache
        $this->_cache = array();

        return $stat;
    }
}
?>
