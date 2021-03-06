<?php
/**
 * @author tarjei huse
 * @package midgard.admin.asgard
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Simple styling class to make html out of diffs and get a simple way
 * to provide rcs functionality
 *
 * This handler can be added to your module by some simple steps. Add this to your
 * request_switch array in the main handlerclass:
 *
 * <pre>
 *      $rcs_array =  no_bergfald_rcs_handler::get_plugin_handlers();
 *      foreach ($rcs_array as $key => $switch) {
 *            $this->_request_switch[] = $switch;
 *      }
 * </pre>
 *
 * If you want to have the handler do a callback to your class to add toolbars or other stuff,
 *
 *
 * Links and urls
 * Linking is done with the format rcs/rcs_action/handler_name/object_guid/<more params>
 * Where handler name is the component using nemein rcs.
 * The handler uses the component name to run a callback so the original handler
 * may control other aspects of the operation
 *
 * @todo add support for schemas.
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_rcs extends midcom_baseclasses_components_handler
{
    /**
     * Current object GUID.
     *
     * @var string
     */
    private $_guid = null;

    /**
     * RCS backend
     */
    private $_backend = null;

    /**
     * Pointer to midgard object
     *
     * @var midcom_db_object
     */
    public $_object = null;

    /**
     * Get the localized strings
     */
    private function _l10n_get($string_id)
    {
        return $this->_l10n->get($string_id);
    }

    /**
     * Static method for determining if we should display a particular field
     * in the diff or preview states
     */
    function is_field_showable($field)
    {
        switch ($field)
        {
            case '_use_rcs':
            case '_topic':
            case 'realm':
            case 'guid':
            case 'id':
            case 'sitegroup':
            case 'action':
            case 'errno':
            case 'errstr':
            case 'revised':
            case 'revisor':
            case 'revision':
            case 'created':
            case 'creator':
            case 'approved':
            case 'approver':
            case 'locked':
            case 'locker':
            case 'lang':
            case 'sid':
                return false;
            case 'password':
                return midcom::get('auth')->admin;
            default:
                return true;
        }
    }

    /**
     * Load the text_diff libaries needed to show diffs.
     */
    public function _on_initialize()
    {
        $this->add_stylesheet(MIDCOM_STATIC_URL . "/midgard.admin.asgard/rcs.css");
    }

    /**
     * Load the object and the rcs backend
     */
    private function _load_object()
    {
        $this->_object = midcom::get('dbfactory')->get_object_by_guid($this->_guid);

        if (   !$GLOBALS['midcom_config']['midcom_services_rcs_enable']
            || !$this->_object->_use_rcs)
        {
            throw new midcom_error_notfound("Revision control not supported for " . get_class($this->_object) . ".");
        }

        // Load RCS service from core.
        $rcs = midcom::get('rcs');
        $this->_backend = $rcs->load_handler($this->_object);

        if (get_class($this->_object) != 'midcom_db_topic')
        {
            $this->bind_view_to_object($this->_object);
        }
    }

    private function _prepare_request_data($handler_id)
    {
        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $this->_request_data);
    }

    /**
     * Prepare version control toolbar
     */
    private function _rcs_toolbar($args = null)
    {
        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        $keys = array_keys($this->_backend->list_history());

        if (isset($keys[0]))
        {
            $first = end($keys);
            $last = $keys[0];
        }

        $current = '';
        if (isset($this->_request_data['args'][2]))
        {
            $current = $this->_request_data['args'][2];
        }
        else if (isset($this->_request_data['args'][1]))
        {
            $current = $this->_request_data['args'][1];
        }

        $this->_request_data['rcs_toolbar'] = new midcom_helper_toolbar();
        $this->_request_data['rcs_toolbar_2'] = new midcom_helper_toolbar();

        if (isset($first))
        {
            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/preview/{$this->_guid}/{$first}",
                    MIDCOM_TOOLBAR_LABEL => $first,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/start.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $first || $this->_request_data['handler_id'] == '____mfa-asgard-object_rcs_diff'),
                )
            );
        }

        if (!empty($current))
        {
            $previous = $this->_backend->get_prev_version($current);
            if (!$previous)
            {
                $previous = $first;
            }

            $next = $this->_backend->get_next_version($current);
            if (!$next)
            {
                $next = $last;
            }

            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/preview/{$this->_guid}/{$previous}",
                    MIDCOM_TOOLBAR_LABEL => $previous,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/previous.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $first || $this->_request_data['handler_id'] == '____mfa-asgard-object_rcs_diff'),
                )
            );

            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/diff/{$this->_guid}/{$current}/{$previous}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('show differences'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/diff-previous.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $first) ? true : false,
                )
            );

            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/preview/{$current}/{$current}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n->get('version %s'), $current),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/document.png',
                    MIDCOM_TOOLBAR_ENABLED => false,
                )
            );

            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/diff/{$this->_guid}/{$current}/{$next}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('show differences'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/diff-next.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $last),
                )
            );

            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/preview/{$this->_guid}/{$next}",
                    MIDCOM_TOOLBAR_LABEL => $next,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/forward.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $last || $this->_request_data['handler_id'] == '____mfa-asgard-object_rcs_diff'),
                )
            );
        }

        if (isset($last))
        {
            $this->_request_data['rcs_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/preview/{$this->_guid}/{$last}",
                    MIDCOM_TOOLBAR_LABEL => $last,
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/finish.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $last || $this->_request_data['handler_id'] == '____mfa-asgard-object_rcs_diff'),
                )
            );
        }

        // RCS functional toolbar
        $this->_request_data['rcs_toolbar_2']->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/{$this->_guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('show history'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/history.png',
            )
        );

        if (!empty($current))
        {
            $this->_request_data['rcs_toolbar_2']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "{$prefix}__mfa/asgard/object/rcs/restore/{$this->_guid}/{$current}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n->get('restore version %s'), $current),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/repair.png',
                    MIDCOM_TOOLBAR_ENABLED => ($current !== $last),
                )
            );
        }
    }

    /**
     * Call this after loading an object
     */
    private function _prepare_toolbars($revision = '', $diff_view = false)
    {
        if ($revision == '')
        {
            return;
        }

        $before = $this->_backend->get_prev_version($revision);
        $before2 = $this->_backend->get_prev_version($before);
        $after  = $this->_backend->get_next_version($revision);

        $show_previous = false;
        if ($diff_view)
        {
            if (   $before != ''
                && $before2 != '')
            {
                // When browsing diffs we want to display buttons to previous instead of current
                $first = $before2;
                $second = $before;
                $show_previous = true;
            }
        }
        else
        {
            if ($before != '')
            {
                $first = $before;
                $second = $revision;
                $show_previous = true;
            }
        }

        if ($show_previous)
        {
            $this->_view_toolbar->add_item(
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard/object/rcs/diff/{$this->_guid}/{$first}/{$second}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n_get('view %s differences with previous (%s)'), $second, $first),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_left.png',
                )
            );
        }

        $this->_view_toolbar->add_item(
            array
            (
                MIDCOM_TOOLBAR_URL => "__mfa/asgard/object/rcs/preview/{$this->_guid}/{$revision}/",
                MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n_get('view this revision (%s)'), $revision),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/search.png',
            )
        );

        // Display restore and next buttons only if we're not in latest revision
        if ($after != '')
        {
            $this->_view_toolbar->add_item(
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard/object/rcs/restore/{$this->_guid}/{$revision}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n_get('restore this revision (%s)'), $revision),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/editpaste.png',
                    MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
                )
            );

            $this->_view_toolbar->add_item(
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard/object/rcs/diff/{$this->_guid}/{$revision}/{$after}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n_get('view %s differences with next (%s)'), $revision, $after),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_right.png',
                )
            );
        }

        $this->bind_view_to_object($this->_object);
    }

    /**
     * Show the changes done to the object
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_history($handler_id, array $args, array &$data)
    {
        $data['args'] = $args;
        midcom::get('auth')->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');

        // Check if the comparison request is valid
        if (isset($_REQUEST['compare']))
        {
            if (count($_REQUEST['compare']) !== 2)
            {
                midcom::get('uimessages')->add($this->_l10n->get('midgard.admin.asgard'), $this->_l10n->get('select exactly two choices'));
            }
            else
            {
                if (version_compare($_REQUEST['compare'][0], '<', $_REQUEST['compare'][1]))
                {
                    $first = $_REQUEST['compare'][0];
                    $last = $_REQUEST['compare'][1];
                }
                else
                {
                    $first = $_REQUEST['compare'][1];
                    $last = $_REQUEST['compare'][0];
                }

                $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
                return new midcom_response_relocate("{$prefix}__mfa/asgard/object/rcs/diff/{$args[0]}/{$first}/{$last}/");
            }
        }

        $this->_guid = $args[0];
        $this->_load_object();
        $this->_prepare_toolbars();
        $this->_prepare_request_data($handler_id);

        // Store the arguments for later use
        $data['args'] = $args;

        // Disable the "Show history" button when we're at its view
        $this->_view_toolbar->hide_item("__mfa/asgard/object/rcs/{$this->_guid}/");

        // Load the toolbars
        $this->_rcs_toolbar();

        midcom::get('head')->add_jsfile(MIDCOM_STATIC_URL . '/midgard.admin.asgard/rcs.js');
        midcom::get('head')->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/jquery.tablesorter.pack.js');
        midcom::get('head')->add_jscript("jQuery(document).ready(function()
        {
            jQuery('#midgard_admin_asgard_rcs_version_compare table').tablesorter({
                headers:
                {
                    0: {sorter: false},
                    4: {sorter: false},
                    5: {sorter: false}
                },
                sortList: [[1,1]]
            });
        });
        ");
    }

    public function _show_history()
    {
        midgard_admin_asgard_plugin::asgard_header();
        $this->_request_data['history'] = $this->_backend->list_history();
        $this->_request_data['guid']    = $this->_guid;
        midcom_show_style('midgard_admin_asgard_rcs_history');
        midgard_admin_asgard_plugin::asgard_footer();
    }

    private function _resolve_object_title()
    {
        $vars = get_object_vars($this->_object);

        if ( array_key_exists('title', $vars))
        {
            return $this->_object->title;
        }
        elseif ( array_key_exists('name', $vars))
        {
            return $this->_object->name;
        }
        else
        {
            return "#{$this->_object->id}";
        }
    }

    /**
     * Show a diff between two versions
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_diff($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');
        $this->_guid = $args[0];
        $this->_load_object();

        // Store the arguments for later use
        $data['args'] = $args;

        if (   !$this->_backend->version_exists($args[1])
            || !$this->_backend->version_exists($args[2]))
        {
            throw new midcom_error_notfound("One of the revisions {$args[1]} or {$args[2]} does not exist.");
        }

        $this->_request_data['diff'] = $this->_backend->get_diff($args[1], $args[2]);

        $this->_prepare_toolbars($args[2], true);

        $this->_request_data['comment'] = $this->_backend->get_comment($args[2]);

        // Set the version numbers
        $this->_request_data['latest_revision'] = $args[2];

        $this->_request_data['guid'] = $args[0];

        $this->_request_data['view_title'] = sprintf($this->_l10n->get('changes done in revision %s to %s'), $this->_request_data['latest_revision'], $this->_resolve_object_title());
        midcom::get('head')->set_pagetitle($this->_request_data['view_title']);

        $this->_prepare_request_data($handler_id);

        // Load the toolbars
        $this->_rcs_toolbar();
    }

    /**
     * Show the differences between the versions
     */
    public function _show_diff()
    {
        midgard_admin_asgard_plugin::asgard_header();
        midcom_show_style('midgard_admin_asgard_rcs_diff');
        midgard_admin_asgard_plugin::asgard_footer();
    }

    /**
     * View previews
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_preview($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');
        $this->_guid = $args[0];
        $data['args'] = $args;

        $revision = $args[1];

        $this->_load_object();
        $this->_prepare_toolbars($revision);
        $this->_request_data['preview'] = $this->_backend->get_revision($revision);

        $this->_view_toolbar->hide_item("__mfa/asgard/object/rcs/preview/{$this->_guid}/{$revision}/");

        $this->_request_data['view_title'] = sprintf($this->_l10n->get('viewing version %s of %s'), $revision, $this->_resolve_object_title());
        midcom::get('head')->set_pagetitle($this->_request_data['view_title']);

        $this->_prepare_request_data($handler_id);

        // Set the version numbers
        $this->_request_data['latest_revision'] = $args[1];
        $this->_request_data['guid'] = $args[0];

        // Load the toolbars
        $this->_rcs_toolbar();
    }

    public function _show_preview()
    {
        midgard_admin_asgard_plugin::asgard_header();
        midcom_show_style('midgard_admin_asgard_rcs_preview');
        midgard_admin_asgard_plugin::asgard_footer();
    }

    /**
     * Restore to diff
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_restore($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');
        $this->_guid = $args[0];
        $data['args'] = $args;
        $this->_load_object();

        // Store the arguments for later use
        $data['args'] = $args;

        $this->_object->require_do('midgard:update');
        // TODO: set another privilege for restoring?

        $this->_prepare_toolbars($args[1]);

        if (   $this->_backend->version_exists($args[1])
            && $this->_backend->restore_to_revision($args[1]))
        {
            midcom::get('uimessages')->add($this->_l10n->get('no.bergfald.rcs'), sprintf($this->_l10n->get('restore to version %s successful'), $args[1]));
            return new midcom_response_relocate("__mfa/asgard/object/view/{$this->_guid}/");
        }
        else
        {
            throw new midcom_error(sprintf($this->_l10n->get('restore to version %s failed, reason %s'), $args[1], $this->_backend->get_error()));
        }

        // Load the toolbars
        $this->_rcs_toolbar();
    }
}
?>
