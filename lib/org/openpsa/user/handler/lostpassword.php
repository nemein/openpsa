<?php
/**
 * @package org.openpsa.user
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Lost Password handler class
 *
 * @package org.openpsa.user
 */
class org_openpsa_user_handler_lostpassword extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_nullstorage
{
    /**
     * The mode we're using (by username, by emil, by username and email or none)
     *
     * @var string
     */
    private $_mode;

    /**
     * The controller used to display the password reset dialog.
     *
     * @var midcom_helper_datamanager2_controller
     */
    private $_controller;

    /**
     * This is true if we did successfully change the password. It will then display a simple
     * password-changed-successfully response.
     *
     * @var boolean
     */
    private $_success = false;

    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_lostpassword'));
    }

    public function get_schema_name()
    {
        return $this->_mode;
    }

    /**
     * This function prepares the requestdata with all computed values.
     * A special case is the visible_data array, which maps field names
     * to prepared values, which can be used in display directly. The
     * information returned is already HTML escaped.
     *
     * @access private
     */
    private function _prepare_request_data()
    {
        $this->_request_data['formmanager'] =& $this->_controller->formmanager;
        $this->_request_data['processing_msg'] = $this->_processing_msg;
        $this->_request_data['processing_msg_raw'] = $this->_processing_msg_raw;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_lostpassword($handler_id, array $args, array &$data)
    {
        $this->_mode = $this->_config->get('lostpassword_mode');
        if ($this->_mode == 'none')
        {
            throw new midcom_error_notfound('This feature is disabled');
        }

        $this->_controller = $this->get_controller('nullstorage');

        switch ($this->_controller->process_form())
        {
            case 'save':
                $this->_reset_password();
                $this->_processing_msg = $this->_l10n->get('password reset, mail sent.');
                $this->_processing_msg_raw = 'password reset, mail sent.';
                $this->_success = true;

                break;

            case 'cancel':
                $_MIDCOM->relocate('');
                // This will exit.
        }
        $this->_prepare_request_data();

        $_MIDCOM->set_pagetitle($this->_l10n->get('lost password'));
    }

    /**
     * This is an internal helper function, resetting the password to a randomly generated one.
     */
    private function _reset_password()
    {
        if (! $_MIDCOM->auth->request_sudo($this->_component))
        {
            throw new midcom_error('Failed to request sudo privileges.');
        }

        $qb = midcom_db_person::new_query_builder();
        if (array_key_exists('username', $this->_controller->datamanager->types))
        {
            $qb->add_constraint('username', '=', $this->_controller->datamanager->types['username']->value);
        }
        if (array_key_exists('email', $this->_controller->datamanager->types))
        {
            $qb->add_constraint('email', '=', $this->_controller->datamanager->types['email']->value);
        }
        $results = $qb->execute();

        if (sizeof($results) != 1)
        {
            $_MIDCOM->auth->drop_sudo();
            throw new midcom_error("Cannot find user. For some reason the QuickForm validation failed.");
        }

        $user = new midcom_core_user($results[0]);

        // Generate a random password
        $length = max(8, $this->_config->get('password_minlength'));
        $password = midcom_admin_user_plugin::generate_password($length);

        if (! $user->update_password($password, false))
        {
            $_MIDCOM->auth->drop_sudo();
            throw new midcom_error("Could not update the password: " . midcom_connection::get_error_string());
        }

        $person = $user->get_storage();

        $_MIDCOM->auth->drop_sudo();

        $this->_send_reset_mail($person, $password);
    }

    /**
     * This is a simple function which generates and sends a password reset mail.
     *
     * @param midcom_db_person $person The newly created person account.
     */
    private function _send_reset_mail($person, $password)
    {
        $from = $this->_config->get('password_reset_mail_sender');
        if (! $from)
        {
            $from = $person->email;
        }
        $template = array
        (
            'from' => $from,
            'reply-to' => '',
            'cc' => '',
            'bcc' => '',
            'x-mailer' => '',
            'subject' => $this->_config->get('password_reset_mail_subject'),
            'body' => $this->_config->get('password_reset_mail_body'),
            'body_mime_type' => 'text/plain',
            'charset' => 'UTF-8',
        );

        $mail = new midcom_helper_mailtemplate($template);
        $parameters = array
        (
            'PERSON' => $person,
            'PASSWORD' => $password,
        );
        $mail->set_parameters($parameters);
        $mail->parse();
        $mail->send($person->email);
    }

    /**
     * Shows either the username change dialog or a success message.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_lostpassword($handler_id, array &$data)
    {
        if ($this->_success)
        {
            midcom_show_style('show-lostpassword-ok');
        }
        else
        {
            midcom_show_style('show-lostpassword');
        }
    }
}
?>