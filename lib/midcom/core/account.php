<?php
/**
 * @package midcom
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Helper class for account management
 *
 * @package midcom
 */
class midcom_core_account
{
    /**
     * The person the account belongs to
     *
     * @param midcom_db_person
     */
    private $_person;

    /**
     * The current account
     *
     * @param object
     */
    private $_user;

    /**
     * Flag to tell us whether we run under midgard2 or not
     *
     * @param boolean
     */
    private $_midgard2 = false;

    /**
     * Currently open accounts
     *
     * @param array
     */
    private static $_instances = array();

    /**
     * Change tracking variables
     *
     * @var string
     */
    private $_new_password;
    private $_old_password;
    private $_old_username;

    public function __construct(midcom_db_person &$person)
    {
        $this->_person =& $person;
        if (method_exists('midgard_user', 'login'))
        {
            $this->_midgard2 = true;
        }
        $this->_user = $this->_get_user();
    }

    public static function &get(midcom_db_person &$person)
    {
        if (!array_key_exists($person->guid, self::$_instances))
        {
            self::$_instances[$person->guid] = new self($person);
        }
        return self::$_instances[$person->guid];
    }

    public function save()
    {
        $this->_person->require_do('midgard:update');
        if (!$this->_is_username_unique())
        {
            midcom::get('uimessages')->add(midcom::get('i18n')->get_string('org.openpsa.contacts', 'org.openpsa.contacts'), midcom::get('i18n')->get_string('username already exists', 'org.openpsa.contacts'), 'error');
            return false;
        }

        if (!$this->_user->guid)
        {
            return $this->_create_user();
        }
        else
        {
            return $this->_update();
        }
    }

    /**
     * Deletes the current user account.
     *
     * This will cleanup all information associated with
     * the user that is managed by the core (like login sessions and privilege records).
     *
     * This call requires the delete privilege on the person object, this is enforced using
     * require_do.
     *
     * @return boolean Indicating success.
     */
    public function delete()
    {
        $this->_person->require_do('midgard:delete');
        $stat = false;
        if ($this->_midgard2)
        {
            // Ratatoskr
            if (!$this->_user)
            {
                return false;
            }
            $stat = $this->_user->delete();
        }
        else
        {
            $this->_person->password = '';
            $this->_person->username = '';
            $stat = $this->_person->update();
        }
        if (!$stat)
        {
            return false;
        }
        $user = new midcom_core_user($this->_person);
        midcom::get('auth')->sessionmgr->_delete_user_sessions($user);

        // Delete all ACL records which have the user as assignee
        $qb = new midgard_query_builder('midcom_core_privilege_db');
        $qb->add_constraint('assignee', '=', $user->id);
        if ($result = @$qb->execute())
        {
            foreach ($result as $entry)
            {
                debug_add("Deleting privilege {$entry->privilegename} ID {$entry->id} on {$entry->objectguid}");
                $entry->delete();
            }
        }
        return true;
    }

    public function set_username($username)
    {
        $this->_old_username = $this->get_username();
        if ($this->_midgard2)
        {
            $this->_user->login = $username;
        }
        else
        {
            $this->_person->username = $username;
        }
    }

    /**
     * Set the account's password
     *
     * @param string $password The password to set
     * @param boolean $encode Should the password be encoded according to the configured auth type
     */
    public function set_password($password, $encode = true)
    {
        $this->_new_password = $password;
        $this->_old_password = $this->get_password();
        if ($encode)
        {
            $password = midcom_connection::prepare_password($password);
        }
        if ($this->_midgard2)
        {
            $this->_user->password = $password;
        }
        else
        {
            $this->_person->password = $password;
        }
    }

    public function get_password()
    {
        return $this->_user->password;
    }

    public function get_username()
    {
        if ($this->_midgard2)
        {
            // Ratatoskr
            return $this->_user->login;
        }
        else
        {
            // Ragnaroek
            return $this->_person->username;
        }
    }

    /**
     * Modify a query instance for searching by username, with differences between
     * mgd1 and mgd2 abstracted away
     *
     * @param midcom_core_query &$query The QB or MC instance to work on
     * @param string $operator The operator for the username constraint
     * @param string $value The value for the username constraint
     */
    public static function add_username_constraint(midcom_core_query &$query, $operator, $value)
    {
        if (method_exists('midgard_user', 'login'))
        {
            $mc = new midgard_collector('midgard_user', 'authtype', $GLOBALS['midcom_config']['auth_type']);
            $mc->set_key_property('person');
            $mc->add_constraint('login', $operator, $value);
            $mc->execute();
            $user_results = $mc->list_keys();

            if (count($user_results) < 1)
            {
                // make sure we don't return any results if no midgard_user entry was found
                $query->add_constraint('id', '=', 0);
            }
            else
            {
                $query->add_constraint('guid', 'IN', array_keys($user_results));
            }
        }
        else
        {
            $query->add_constraint('username', $operator, $value);
        }
    }

    /**
     * Add username order to a query instance, with differences between
     * mgd1 and mgd2 abstracted away.
     *
     * Note that it actually does nothing under mgd2 right now, because it's still
     * unclear how this could be implemented
     *
     * @param midcom_core_query &$query The QB or MC instance to work on
     * @param string $direction The value for the username constraint
     */
    public static function add_username_order(midcom_core_query &$query, $direction)
    {
        if (method_exists('midgard_user', 'login'))
        {
            debug_add('Ordering persons by username is not yet implemented for Midgard2', MIDCOM_LOG_ERROR);
            //@todo Find a way to do this
        }
        else
        {
            $query->add_order('username', $direction);
        }
    }

    private function _create_user()
    {
        if ($this->_user->login == '')
        {
            return false;
        }
        $this->_user->authtype = $GLOBALS['midcom_config']['auth_type'];

        if ($GLOBALS['midcom_config']['person_class'] != 'midgard_person')
        {
            $mgd_person = new midgard_person($this->_person->guid);
        }
        else
        {
            $mgd_person = $this->_person;
        }
        $this->_user->set_person($mgd_person);
        $this->_user->active = true;

        try
        {
            $stat = $this->_user->create();
        }
        catch (midgard_error_exception $e)
        {
            return false;
        }
        return $stat;
    }

    private function _update()
    {
        $stat = false;
        $new_username = $this->get_username();
        $new_password = $this->get_password();

        if ($this->_midgard2)
        {
            $this->_user->login = $new_username;
            $this->_user->password = $new_password;
            try
            {
                $stat = $this->_user->update();
            }
            catch (midgard_error_exception $e)
            {
                $e->log();
            }
        }
        else
        {
            // Ragnaroek
            $this->_person->username = $new_username;
            $this->_person->password = $new_password;
            $stat = $this->_person->update();
        }
        if (!$stat)
        {
            return false;
        }

        $user = new midcom_core_user($this->_person);

        if (   !empty($this->_old_password)
            && $this->_old_password !== $new_password)
        {
            midcom::get('auth')->sessionmgr->_update_user_password($user, $this->_new_password);
        }
        if (   !empty($this->_old_username)
            && $this->_old_username !== $new_username)
        {
            midcom::get('auth')->sessionmgr->_update_user_username($user, $new_username);
            if (!$history = @unserialize($this->_person->get_parameter('midcom', 'username_history')))
            {
                $history = array();
            }
            $history[time()] = array('old' => $this->_old_username, 'new' => $new_username);
            $this->_person->set_parameter('midcom', 'username_history', serialize($history));
        }
        return true;
    }

    private function _get_user()
    {
        if ($this->_midgard2)
        {
            $storage = new midgard_query_storage('midgard_user');
            $qs = new midgard_query_select($storage);

            $group = new midgard_query_constraint_group('AND');
            $group->add_constraint (
                new midgard_query_constraint (
                    new midgard_query_property ('person'),
                    '=',
                    new midgard_query_value ($this->_person->guid)));
            $group->add_constraint (
                new midgard_query_constraint (
                    new midgard_query_property ('authtype'),
                    '=',
                    new midgard_query_value ($GLOBALS['midcom_config']['auth_type'])));
            $qs->set_constraint($group);
            $qs->toggle_readonly(false);
            $qs->execute();

            $result = $qs->list_objects();
            if (sizeof($result) != 1)
            {
                return new midgard_user();
            }
            return $result[0];
        }
        else
        {
            return $this->_person;
        }
    }

    private function _is_username_unique()
    {
        if ($this->_midgard2)
        {
            $qb = new midgard_query_builder('midgard_user');
            $qb->add_constraint('login', '=', $this->get_username());
            $qb->add_constraint('authtype', '=', $GLOBALS['midcom_config']['auth_type']);
        }
        else
        {
            $qb = new midgard_query_builder($GLOBALS['midcom_config']['person_class']);
            $qb->add_constraint('username', '=', $this->get_username());
        }
        $qb->add_constraint('guid', '<>', $this->_user->guid);
        return ($qb->count() == 0);
    }

}
?>