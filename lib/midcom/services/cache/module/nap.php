<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This is the NAP/Metadata caching module. It provides the basic management functionality
 * for the various backend databases which will be created based on the root topic of the
 * NAP trees.
 *
 * The actual handling of the various db's is done with nav/backend.php and metadata.php, this
 * class is responsible for the creation of backend instances and invalidation for both NAP
 * and Metadata cache objects. (Which implies that it is fully aware of the data structures
 * stored in the cache.)
 *
 * All entries are indexed by their Midgard Object GUID. The entries in the NAP cache
 * basically resemble the arrays within the NAP backend node/leaf cache, while the metadata
 * cache is a copy of the actual metadata property cache of the midcom_helper_metadata object.
 *
 * NAP/Metadata caches can be shared over multiple sites, as all site specific data (like
 * site prefixes) are evaluated during runtime.
 *
 * Most of the cache update work is done in midcom_helper_nav_backend,
 * so you should look there for details about the caching strategy.
 *
 * <b>Implementation notes:</b>
 *
 * Currently, the metadata object is not cached. Instead it relies on the NAP object copies
 * to work in a cached fashion: It uses the members of the object copy from the cache for
 * all basic operations (using the $object->$domain_$name feature of Midgard in combination
 * with variable variables).
 *
 * @see midcom_helper_nav_backend
 * @see midcom_helper_metadata
 *
 * @package midcom.services
 */
class midcom_services_cache_module_nap extends midcom_services_cache_module
{
    /**#@+
     * Internal runtime state variable.
     */

    /**
     * The configuration to use to start up the backend drivers. Initialized during
     * startup from the MidCOM configuration key cache_module_nap_backend.
     *
     * @var Array
     */
    private $_backend = null;

    /**
     * The cache backend instance to use.
     *
     * @var midcom_services_cache_backend
     */
    private $_cache = null;

    /**
     * The cache key prefix.
     *
     * @var string
     */
    public $_prefix = "NAP";

    /**#@-*/

    /**
     * Initialization event handler.
     *
     * It will load the cache backends for the current MidCOM topic.
     *
     * Initializes the backend configuration.
     */
    public function _on_initialize()
    {
        $this->_backend = $GLOBALS['midcom_config']['cache_module_memcache_backend'];

        if ($this->_backend)
        {
            $config = $GLOBALS['midcom_config']['cache_module_memcache_backend_config'];
            $config['driver'] = $this->_backend;
            $this->_cache = $this->_create_backend('module_nap', $config);
        }
    }

    /**
     * Invalidates all cache objects related to the GUID specified. This function is aware for
     * NAP / Metadata caches. It will invalidate the node/leaf record pair upon each invalidation.
     *
     * This function only works within the current context, because it looks on the invalidated
     * GUID to handle the invalidation correctly.
     *
     * <b>Note, for GUIDs which cannot be resolved by NAP:</b>
     *
     * It should be safe to just skip this case, because if the object to be invalidated
     * cannot be found, it is not cached anyway (deleted items could be resolved using
     * the resolve_guid code which uses the cache, so they would still be found).
     * Special cases, where objects not available through NAP are updated have to be handled
     * by the component anyway.
     *
     * This way, leaf deletions should be safe in all cases (if they are cached, they can
     * still be resolved, if not, they aren't in the cache anyway). The Datamanager tries
     * to catch leaf creations using its internal creation mode flag, invalidating the
     * current content topic instead of the actual object in this case. Note, that this happens
     * directly after object creation, not during the regular safe cycle.
     *
     * See the automatic index invalidation code of the Datamanager for additional details.
     *
     * @todo Find a way to propagate leaf additions/deletions to a topic which must be invalidated in all
     * places necessary, or MIDCOM_NAV_LEAVES will be broken.
     *
     * @param string $guid The GUID to invalidate.
     */
    function invalidate($guid)
    {
        $nav = new midcom_helper_nav();
        $napobject = $nav->resolve_guid($guid);

        if ($napobject === false)
        {
            // Ignoring this should be safe, see the method documentation for details.
            debug_add("We failed to resolve the GUID {$guid} with NAP, apparently it is not cached or no valid NAP node, skipping it therefore.",
                MIDCOM_LOG_INFO);
            return;
        }

        if ($napobject[MIDCOM_NAV_TYPE] == 'leaf')
        {
            $cached_node_id = $napobject[MIDCOM_NAV_NODEID];
            // Get parent from DB and compare to catch moves
            if ($parent = $napobject[MIDCOM_NAV_OBJECT]->get_parent())
            {
                $parent_entry_from_object = $nav->resolve_guid($parent->guid);
                if (    $parent_entry_from_object
                     && $parent_entry_from_object[MIDCOM_NAV_ID] != $cached_node_id)
                {
                    $this->_cache->remove($this->_prefix . '-' . $parent_entry_from_object[MIDCOM_NAV_ID] . '-leaves');
                }
            }
            if (!empty($napobject[MIDCOM_NAV_GUID]))
            {
                $this->_cache->remove($this->_prefix . '-' . $napobject[MIDCOM_NAV_GUID]);
            }
        }
        else
        {
            $cached_node_id = $napobject[MIDCOM_NAV_ID];

            //Invalidate subnode cache for the (cached) parent
            $parent_id = $napobject[MIDCOM_NAV_NODEID];
            $parent_entry = $this->_cache->get("{$this->_prefix}-{$parent_id}");

            if (   $parent_entry
                && array_key_exists(MIDCOM_NAV_SUBNODES, $parent_entry))
            {
                unset($parent_entry[MIDCOM_NAV_SUBNODES]);
                $this->_cache->put("{$this->_prefix}-{$parent_id}", $parent_entry);
            }

            //Cross-check parent value from object to detect topic moves
            if ($parent = $napobject[MIDCOM_NAV_OBJECT]->get_parent())
            {
                $parent_entry_from_object = $nav->resolve_guid($parent->guid);

                if (    !empty($parent_entry_from_object[MIDCOM_NAV_ID])
                     && !empty($parent_entry[MIDCOM_NAV_ID])
                     && $parent_entry_from_object[MIDCOM_NAV_ID] != $parent_entry[MIDCOM_NAV_ID])
                {
                    unset($parent_entry_from_object[MIDCOM_NAV_SUBNODES]);
                    $this->_cache->put("{$this->_prefix}-{$parent_entry_from_object[MIDCOM_NAV_ID]}", $parent_entry_from_object);
                }
            }
        }

        $leaves_key = "{$cached_node_id}-leaves";

        $this->_cache->remove("{$this->_prefix}-{$cached_node_id}");
        $this->_cache->remove($this->_prefix . '-' . $napobject[MIDCOM_NAV_GUID]);
        $this->_cache->remove("{$this->_prefix}-{$leaves_key}");
    }

    /**
     * Looks up a node in the cache and returns it. Not existent
     * keys are caught in this call as well, so you do not need
     * to call exists first.
     *
     * @param string $key The key to look up.
     * @return mixed The cached value on success, false on failure.
     */
    function get_node($key)
    {
        if ($this->_cache === null)
        {
            return false;
        }
        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$key}");
        if (   !is_array($result)
            || !isset($result[$lang_id]))
        {
            return false;
        }

        return $result[$lang_id];
    }

    /**
     * Looks up a node in the cache and returns it. Not existent
     * keys are caught in this call as well, so you do not need
     * to call exists first.
     *
     * @param string $key The key to look up.
     * @return mixed The cached value on success, false on failure.
     */
    function get_leaves($key)
    {
        $result = false;
        if ($this->_cache === null)
        {
            return $result;
        }

        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$key}");
        if (   !is_array($result)
            || !isset($result[$lang_id]))
        {
            return false;
        }

        return $result[$lang_id];
    }


    /**
     * Checks for the existence of a key in the cache.
     *
     * @param string $key The key to look up.
     * @return boolean Indicating existence
     */
    function exists($key)
    {
        if ($this->_cache === null)
        {
            return false;
        }

        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$key}");
        if (   !is_array($result)
            || !isset($result[$lang_id]))
        {
            return false;
        }

        return true;
    }

    /**
     * Sets a given node key in the cache.
     *
     * @param string $key The key to look up.
     * @param mixed $data The data to store.
     * @param int $timeout how long the data should live in the cache.
     */
    public function put_node($key, $data, $timeout = false)
    {
        if ($this->_cache === null)
        {
            return;
        }

        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$key}");
        if (!is_array($result))
        {
            $result = array($lang_id => $data);
        }
        else
        {
            $result[$lang_id] = $data;
        }
        $this->_cache->put("{$this->_prefix}-{$key}", $result, $timeout);
        $this->_cache->put($this->_prefix . '-' . $data[MIDCOM_NAV_GUID], $result, $timeout);
    }

    /**
     * Save a given array by GUID in the cache.
     *
     * @param string $guid The key to store.
     * @param mixed $data The data to store.
     * @param int $timeout how long the data should live in the cache.
     */
    public function put_guid($guid, $data, $timeout = false)
    {
        if ($this->_cache === null)
        {
            return;
        }

        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$guid}");
        if (!is_array($result))
        {
            $result = array($lang_id => $data);
        }
        else
        {
            $result[$lang_id] = $data;
        }
        $this->_cache->put($this->_prefix . '-' . $guid, $result, $timeout);
    }

    /**
     * Get a given array by GUID from the cache.
     *
     * @param string $guid The key to look up.
     * @param int $timeout how long the data should live in the cache.
     */
    public function get_guid($guid, $timeout = false)
    {
        if ($this->_cache === null)
        {
            return;
        }

        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$guid}");
        if (   !is_array($result)
            || !isset($result[$lang_id]))
        {
            return false;
        }
        return $result[$lang_id];
    }

    /**
     * Sets a given leave key in the cache
     *
     * @param string $key The key to look up.
     * @param mixed $data The data to store.
     * @param int $timeout how long the data should live in the cache.
     */
    public function put_leaves($key, $data, $timeout = false)
    {
        if ($this->_cache === null)
        {
            return;
        }

        $lang_id = midcom::get('i18n')->get_current_language();
        $result = $this->_cache->get("{$this->_prefix}-{$key}");
        if (!is_array($result))
        {
            $result = array($lang_id => $data);
        }
        else
        {
            $result[$lang_id] = $data;
        }
        $this->_cache->put("{$this->_prefix}-{$key}", $result, $timeout);
    }
}
?>
