<?php
/**
 * @package org.openpsa.imp
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.imp site interface class.
 *
 * "SSO" to Horde/Imp
 *
 * @package org.openpsa.imp
 */
class org_openpsa_imp_viewer extends midcom_baseclasses_components_request
{
    private $_server_uri = false;
    private $_imp_username = false;
    private $_imp_password = false;
    private $_global_server = false;

    /**
     * Populate request switch, which contains URL handlers for the component.
     */
    public function _on_initialize()
    {
        // Always run in uncached mode
        midcom::get('cache')->content->no_cache();
    }

    private function _populate_toolbar()
    {
        //Add icon for user settings
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'settings/',
                MIDCOM_TOOLBAR_LABEL => $this->_request_data['l10n_midcom']->get('settings'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
            )
        );

        return true;
    }

    /**
     * Tries to read settings for webmail
     */
    private function _check_imp_settings()
    {
        $current_topic = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_CONTENTTOPIC);
        $current_user_dbobj = midcom::get('auth')->user->get_storage();

        if (!is_object($current_user_dbobj))
        {
            debug_add("Current user not found", MIDCOM_LOG_ERROR);
            return false;
        }

        // Get server URI
        if ($this->_server_uri = $current_topic->parameter('org.openpsa.imp', 'imp_global_uri'))
        {
            //Global server URI found always use it
            $this->_global_server = true;
        }
        else
        {
            $this->_server_uri = $current_user_dbobj->get_parameter('org.openpsa.imp', 'imp_uri');
        }
        if (!$this->_server_uri)
        {
            debug_add("Server URI not found", MIDCOM_LOG_ERROR);
            return false;
        }

        //Get username
        $this->_imp_username = $current_user_dbobj->get_parameter('org.openpsa.imp', 'imp_username');
        if (!$this->_imp_username)
        {
            debug_add("Imp username not found", MIDCOM_LOG_ERROR);
            return false;
        }

        //Get password
        $this->_imp_password = $current_user_dbobj->get_parameter('org.openpsa.imp', 'imp_password');
        if (!$this->_imp_password)
        {
            debug_add("Imp password not found", MIDCOM_LOG_ERROR);
            return false;
        }

        return true;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_redirect($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_valid_user();

        $formData = false;
        $nextUri = false;

        if (!$this->_check_imp_settings())
        {
            throw new midcom_error("Horde/Imp settings incomplete");
        }

        //Try to get remote login form
        @$fp = fopen($this->_server_uri, 'r');
        if (!$fp)
        {
           //Could not open remote URI, this might be lack of SSL wrappers or something
           debug_print_r('Could not open %s for reading', $this->_server_uri);
        }
        else
        {
            //Read remote information
            $HTMLBody = '';
            while (!feof($fp))
            {
                 $HTMLBody .= fread($fp, 4096);
            }
            preg_match('/<form[^>]*action="([^"]+)"[^>]*>/', $HTMLBody, $matches1);
            $actionUri = $matches1[1];

            preg_match_all('/<input[^>]*name="([^"]+)" (value="([^"]*)")?[^>]*>/', $HTMLBody, $matches2);

            if (!preg_match('%^http%', $actionUri))
            {
                preg_match('%(https?://[^/]+)(.*)%', $this->_server_uri, $matches3);
                $uriServer = $matches3[1];

                if (!preg_match('%^/%', $actionUri))
                {
                    preg_match('%(https?://.+/)(.*)%', $this->_server_uri, $matches4);
                    $uriServer = $matches4[1];
                }
                $nextUri = $uriServer . $actionUri;
            }
            else
            {
                $nextUri = $actionUri;
            }

            $formData = '<form id="org_openpsa_imp_autoSubmit" method="post" action="' . $nextUri . '">' . "\n";
            while (list ($n, $k) = each ($matches2[1]))
            {
                 switch ($k)
                 {
                        default:
                             $v = $matches2[3][$n];
                        break;
                        case 'login_username':
                        case 'imapuser':
                             $v = $this->_imp_username;
                        break;
                        case 'secretkey':
                        case 'pass':
                             $v = $this->_imp_password;
                        break;
                 }
                 $formData .= '    <input type="hidden" name="'.$k.'" value="'.$v.'" />'."\n";
            }
            reset ($matches2[1]);
            $formData .= "<input type=\"submit\" value=\"".'log in'."\" />\n</form>\n";
        }

        if (!$nextUri)
        {
            //Address to post the form to not found, we try to just to redirect to the given server URI
            debug_add('Action URI not found in data, relocating to server base URI');
            return new midcom_response_relocate($this->_server_uri);
        }

        $this->_request_data['login_form_html'] = $formData;

        // We're using a popup here
        midcom::get()->skip_page_style = true;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_redirect($handler_id, array &$data)
    {
        midcom_show_style("imp-redirect");
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_settings($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_valid_user();

        $this->_check_imp_settings();

        // Load the schema definition file
        $schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_horde_account'));

        //Choose schema
        if ($this->_global_server)
        {
            $schema = 'globalserver';
        }
        else
        {
            $schema = 'default';
        }
        debug_add('Chose schema: "' . $schema . '"');

        // Instantiate datamanager
        $controller = midcom_helper_datamanager2_controller::create('simple');
        $controller->schemadb =& $schemadb;

        // Load the person record into DM
        $person_record = midcom::get('auth')->user->get_storage();

        $controller->set_storage($person_record, $schema);
        if (! $controller->initialize())
        {
            throw new midcom_error("Failed to initialize a DM2 controller instance for person {$person_record->id}.");
        }

        // Process the form
        switch ($controller->process_form())
        {
            case 'save':
                return new midcom_response_relocate(midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX));

            case 'cancel':
                return new midcom_response_relocate(midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX));
        }

        $data['controller'] = $controller;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_settings($handler_id, array &$data)
    {
        midcom_show_style("show-settings");
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_frontpage($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_valid_user();

        //If settings are not complete redirect to settings page
        if (!$this->_check_imp_settings())
        {
            debug_add("Horde/Imp settings incomplete, redirecting to settings page.");
            return new midcom_response_relocate( midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX)
                                . 'settings/');
        }

        $this->_populate_toolbar();
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_frontpage($handler_id, array &$data)
    {
        midcom_show_style("show-frontpage");
    }
}