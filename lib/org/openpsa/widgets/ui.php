<?php
/**
 * @package org.openpsa.widgets
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Helper class to load parts of the ui
 *
 * @package org.openpsa.widgets
 */
class org_openpsa_widgets_ui extends midcom_baseclasses_components_purecode
{
    public static function get_config_value($value)
    {
        $config = midcom_baseclasses_components_configuration::get('org.openpsa.widgets', 'config');
        return $config->get($value);
    }

    /**
     * Helper function that returns information about available search providers
     *
     * @return array
     */
    public static function get_search_providers()
    {
        $providers = array();
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $configured_providers = self::get_config_value('search_providers');
        $user_id = false;

        if (!midcom::get('auth')->admin)
        {
            $user_id = midcom::get('auth')->acl->get_user_id();
        }

        foreach ($configured_providers as $component => $route)
        {
            $node_url = $siteconfig->get_node_full_url($component);
            if (   $node_url
                && (   !$user_id
                    || midcom::get('auth')->acl->can_do_byguid('midgard:read', $siteconfig->get_node_guid($component), 'midcom_db_topic', $user_id)))
            {
                $providers[] = array
                (
                    'helptext' => midcom::get('i18n')->get_string('search title', $component),
                    'url' => $node_url . $route,
                    'identifier' => $component
                );
            }
        }

        return $providers;
    }

    /**
     * Add necessary head elements for dynatree
     */
    public static function enable_dynatree()
    {
        $head = midcom::get('head');
        $head->enable_jquery();

        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.core.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.widget.min.js');

        $head->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/jquery.cookie.js');
        $head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.widgets/dynatree/jquery.dynatree.min.js');
        $head->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.widgets/dynatree/skin/ui.dynatree.css");
        $head->add_jquery_ui_theme();
    }

    public static function add_head_elements()
    {
        $head = midcom::get('head');
        $head->enable_jquery();

        $head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.widgets/ui.js');
    }

    /**
     * Function to load the necessary javascript & css files for ui_tab
     */
    public static function enable_ui_tab()
    {
        $head = midcom::get('head');
        //first enable jquery - just in case it isn't loaded
        $head->enable_jquery();

        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.core.min.js');

        //load ui-tab
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.widget.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.tabs.min.js');

        //functions needed for ui-tab to work here
        $head->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/jquery.history.js');
        $head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.widgets/tab_functions.js');

        //add the needed css-files
        $head->add_jquery_ui_theme(array('tabs'));
    }

    /**
     * Helper function to render jquery.ui tab controls. Relatedto tabs are automatically added
     * if a GUID is found
     *
     * @param string $guid The GUID, if any
     * @param array $tabdata Any custom tabs the handler wnats to add
     */
    public static function render_tabs($guid = null, $tabdata = array())
    {
        $uipage = self::get_config_value('ui_page');
        $host_prefix = substr(midcom::get()->get_host_prefix(), strlen(midcom::get()->get_host_name()));
        $prefix = $host_prefix . $uipage . '/';

        if (null !== $guid)
        {
            //pass the urls & titles for the tabs
            $tabdata[] = array
            (
               'url' => '__mfa/org.openpsa.relatedto/journalentry/' . $guid . '/html/',
               'title' => midcom::get('i18n')->get_string('journal entries', 'org.openpsa.relatedto'),
            );
            $tabdata[] = array
            (
               'url' => '__mfa/org.openpsa.relatedto/render/' . $guid . '/both/',
               'title' => midcom::get('i18n')->get_string('related objects', 'org.openpsa.relatedto'),
            );
        }

        echo '<div id="tabs">';
        echo "\n<ul>\n";
        foreach ($tabdata as $key => $tab)
        {
            echo "<li><a id='key_" . $key ."' class='tabs_link' href='" . $prefix . $tab['url'] . "' ><span> " . $tab['title'] . "</span></a></li>";
        }
        echo "\n</ul>\n";
        echo "</div>\n";

        $wait = midcom::get('i18n')->get_string('loading', 'org.openpsa.widgets');

        echo <<<JSINIT
<script type="text/javascript">
$(document).ready(
    function()
    {
        org_openpsa_widgets_tabs.initialize('{$uipage}', '{$wait}...');
    }
);
</script>
JSINIT;
    }
}
?>