<?php
/**
 * @package org.openpsa.invoices
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Invoice PDF Handler
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_handler_pdf extends midcom_baseclasses_components_handler
{
    private $_update_attachment = false;

    private $_invoice;

    /**
     * Datamanager2 to be used for displaying an object used for delete preview
     *
     * @var midcom_helper_datamanager2_datamanager
     */
    private $_datamanager = null;

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_pdf($handler_id, array $args, array &$data)
    {
        $this->_invoice = new org_openpsa_invoices_invoice_dba($args[0]);

        $this->_request_data['invoice_url'] = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX) . "/invoice/" . $this->_invoice->guid . "/";

        //check for manually uploaded pdf-file & if user wants to replace it
        $this->_update_attachment = true;
        if (array_key_exists('cancel', $_POST))
        {
            $_MIDCOM->relocate($this->_request_data['invoice_url']);
        }
        if (!array_key_exists('save', $_POST))
        {
            // load schema & datamanager to get attachment
            $schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb'));
            $this->_datamanager = new midcom_helper_datamanager2_datamanager($schemadb);

            if (!$this->_datamanager->autoset_storage($this->_invoice))
            {
                throw new midcom_error("Failed to create a DM2 instance for object {$this->_invoice->guid}.");
            }
            if (!empty($this->_datamanager->types['pdf_file']->attachments))
            {
                $this->_update_attachment = false;
                foreach ($this->_datamanager->types['pdf_file']->attachments as $attachment)
                {
                    $parameters = $attachment->list_parameters();
                    // check if auto generated parameter is same as md5 in current-file
                    // if not the file was manually uploaded
                    if (   array_key_exists('org.openpsa.invoices', $parameters)
                        && (array_key_exists('auto_generated', $parameters['org.openpsa.invoices'])))
                    {
                        $blob = new midgard_blob($attachment->__object);
                        // check if md5 sum equals the one saved in auto_generated
                        if ($parameters['org.openpsa.invoices']['auto_generated'] == md5_file($blob->get_path()))
                        {
                            $this->_update_attachment = true;
                        }
                    }
                }
            }
        }
        if ($this->_update_attachment)
        {
            $this->_request_data['invoice'] = $this->_invoice;
            // set customer
            if ($this->_invoice->customer)
            {
                $this->_request_data['customer'] = org_openpsa_contacts_group_dba::get_cached($this->_invoice->customer);
            }
            try
            {
                $this->_request_data['customer_contact'] = org_openpsa_contacts_person_dba::get_cached($this->_invoice->customerContact);
            }
            catch (midcom_error $e)
            {
                $this->_request_data['customer_contact'] = false;
            }
            $this->_request_data['billing_data'] = $this->_invoice->get_billing_data();
            $_MIDCOM->skip_page_style = true;
        }
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_pdf($handler_id, array &$data)
    {
        if ($this->_update_attachment)
        {
            self::render_and_attach_pdf($this->_invoice);
            $_MIDCOM->relocate($this->_request_data["invoice_url"]);
        }
        // if attachment was manually uploaded show confirm if file should be replaced
        else
        {
            midcom_show_style('show-confirm');
        }
    }

    public static function render_and_attach_pdf(org_openpsa_invoices_invoice_dba $invoice)
    {
        // renders the pdf and attaches it to the invoice
        $client_class = midcom_baseclasses_components_configuration::get('org.openpsa.invoices', 'config')->get('invoice_pdfbuilder_class');
        $pdf_builder = new $client_class($invoice);

        // tmp filename
        $tmp_dir = $GLOBALS["midcom_config"]["midcom_tempdir"];
        $title = str_replace("#", "", $invoice->get_label());

        $tmp_file = $tmp_dir . "/". $title . ".pdf";

        // render pdf to tmp filename
        $render = $pdf_builder->render($tmp_file);

        // cleanup old attachments
        $pdf_files = org_openpsa_helpers::get_attachment_urls($invoice, "pdf_file");

        if (count($pdf_files) > 0)
        {
            foreach ($pdf_files as $guid => $url)
            {
                $attachment = new midcom_db_attachment($guid);
                $attachment->delete();
            }
        }

        $attachment = $invoice->create_attachment($title . '.pdf', $title, "application/pdf");

        if (!$attachment)
        {
            debug_add("Failed to create invoice attachment for pdf");
            return false;
        }

        $copy = $attachment->copy_from_file($tmp_file);
        if (!$copy)
        {
            debug_add("Failed to copy pdf from " . $tmp_file . " to attachment");
            return false;
        }

        // set parameter for datamanager to find the pdf
        $invoice->set_parameter("midcom.helper.datamanager2.type.blobs", "guids_pdf_file", $attachment->guid . ":" . $attachment->guid);
    }
}
?>