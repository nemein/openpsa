<?php
/**
 * Class for rendering person records
 *
 * Uses the hCard microformat for output.
 *
 * @author Henri Bergius, http://bergie.iki.fi
 * @copyright Nemein Oy, http://www.nemein.com
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @link http://www.microformats.org/wiki/hcard hCard microformat documentation
 * @link http://www.midgard-project.org/midcom-permalink-922834501b71daad856f35ec593c7a6d Contactwidget usage documentation
 * @package org.openpsa.widgets
 */

/**
 * @package org.openpsa.widgets
 */
class org_openpsa_widgets_contact extends midcom_baseclasses_components_purecode
{
    /**
     * Do we have our contact data ?
     */
    private $_data_read_ok = false;

    /**
     * Contact information of the person being displayed
     */
    var $contact_details = array();

    /**
     * Optional URI to person details
     * @var string
     */
    var $link = null;

    /**
     * Optional HTML to be placed into the card
     * @var string
     */
    var $extra_html = null;

    /**
     * Optional HTML to be placed into the card (before any other output in the DIV)
     * @var string
     */
    var $prefix_html = null;

    /**
     * Whether to show person's groups in a list
     * @var boolean
     */
    var $show_groups = true;

    /**
     * Whether to generate links to the groups using NAP
     * @var boolean
     */
    var $link_contacts = true;

    /**
     * Default NAP org.openpsa.contacts URL to be used for linking to groups. Will be autoprobed if not supplied.
     * @var Array
     */
    var $contacts_url;

    /**
     * Initializes the class and stores the selected person to be shown
     * The argument should be a MidgardPerson object. In the future DM
     * Array format will also be supported.
     *
     * @param mixed $person Person to display either as MidgardPerson or Datamanager array
     */
    public function __construct($person = null)
    {
        parent::__construct();

        // Hack to make $contacts_url static
        static $contacts_url_local;
        $this->contacts_url =& $contacts_url_local;

        // Read properties of provided person object/DM array
        // TODO: Handle groups as well
        if (is_object($person))
        {
            $this->_data_read_ok = $this->read_object($person);
        }
        else if (is_array($person))
        {
            $this->_data_read_ok = $this->read_array($person);
        }
    }

    public static function add_head_elements()
    {
        midcom::get('head')->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.widgets/hcard.css");
    }

    /**
     * Retrieve a reference to an object, uses in-request caching
     *
     * @param mixed $src GUID of object (ids work but are discouraged)
     * @return mixed reference to device object or false
     */
    static function &get($src)
    {
        static $cache = array();

        if (isset($cache[$src]))
        {
            return $cache[$src];
        }

        try
        {
            if (class_exists('org_openpsa_contacts_person_dba'))
            {
                $person = org_openpsa_contacts_person_dba::get_cached($src);
            }
            else
            {
                $person = new midcom_db_person($src);
            }
        }
        catch (midcom_error $e)
        {
            $widget = new self();
            $cache[$src] = $widget;
            return $widget;
        }

        $widget = new self($person);

        $cache[$person->guid] = $widget;
        $cache[$person->id] =& $cache[$person->guid];
        return $cache[$person->guid];
    }

    /**
     * Read properties of a person object and populate local fields accordingly
     */
    function read_object($person)
    {
        if (   !is_object($person)
            && !$_MIDCOM->dbfactory->is_a($person, 'midcom_db_person'))
        {
            // Given $person is not one
            return false;
        }
        // Database identifiers
        $this->contact_details['guid'] = $person->guid;
        $this->contact_details['id'] = $person->id;

        if ($person->guid == "")
        {
            $this->contact_details['firstname'] = '';
            $this->contact_details['lastname'] = $this->_l10n->get('no person');
        }
        else if (   $person->firstname == ''
            && $person->lastname == '')
        {
            $this->contact_details['firstname'] = '';
            $this->contact_details['lastname'] = "Person #{$person->id}";
        }
        else
        {
            $this->contact_details['firstname'] = $person->firstname;
            $this->contact_details['lastname'] = $person->lastname;
        }

        if ($person->handphone)
        {
            $this->contact_details['handphone'] = $person->handphone;
        }

        if ($person->workphone)
        {
            $this->contact_details['workphone'] = $person->workphone;
        }

        if ($person->homephone)
        {
            $this->contact_details['homephone'] = $person->homephone;
        }

        if ($person->email)
        {
            $this->contact_details['email'] = $person->email;
        }

        if ($person->homepage)
        {
            $this->contact_details['homepage'] = $person->homepage;
        }

        if (   $this->_config->get('jabber_enable_presence')
            && $person->parameter('org.openpsa.jabber', 'jid'))
        {
            $this->contact_details['jid'] = $person->parameter('org.openpsa.jabber', 'jid');
        }

        if (   $this->_config->get('skype_enable_presence')
            && $person->parameter('org.openpsa.skype', 'name'))
        {
            $this->contact_details['skype'] = $person->parameter('org.openpsa.skype', 'name');
        }

        return true;
    }

    function determine_url()
    {
        if ($this->link)
        {
            return $this->link;
        }
        else if ($this->link_contacts
                 && $this->contact_details['guid'] != "")
        {
            if (!$this->contacts_url)
            {
                $siteconfig = org_openpsa_core_siteconfig::get_instance();
                $this->contacts_url = $siteconfig->get_node_full_url('org.openpsa.contacts');
            }

            if (!$this->contacts_url)
            {
                $this->link_contacts = false;
                return null;
            }

            $url = "{$this->contacts_url}person/{$this->contact_details['guid']}/";
            return $url;
        }
        return null;
    }

    /**
     * Show selected person object inline. Outputs hCard XHTML.
     */
    function show_inline()
    {
        if (!$this->_data_read_ok)
        {
            return '';
        }
        $inline_string = '';

        // Start the vCard
        $inline_string .= "<span class=\"vcard\">";

        if (array_key_exists('guid', $this->contact_details))
        {
            // Identifier
            $inline_string .= "<span class=\"uid\" style=\"display: none;\">{$this->contact_details['guid']}</span>";
        }

        // The Name sequence
        $inline_string .= "<span class=\"n\">";

        $linked = false;
        if (   $this->link
            || $this->link_contacts)
        {
            $url = $this->determine_url();
            if ($url)
            {
                $inline_string .= "<a href=\"{$url}\">";
                $linked = true;
            }
        }

        $inline_string .= "<span class=\"given-name\">{$this->contact_details['firstname']}</span> <span class=\"family-name\">{$this->contact_details['lastname']}</span>";

        if ($linked)
        {
            $inline_string .= "</a>";
        }

        $inline_string .= "</span>";

        $inline_string .= "</span>";

        return $inline_string;
    }

    /**
     * Show the selected person. Outputs hCard XHTML.
     */
    function show()
    {
        if (!$this->_data_read_ok)
        {
            return false;
        }
        // Start the vCard
        echo "<div class=\"vcard\" id=\"org_openpsa_widgets_contact-{$this->contact_details['guid']}\">\n";
        if ($this->prefix_html)
        {
            echo $this->prefix_html;
        }

        // Show picture
        // TODO: Implement photo also in local way
        if (   $this->_config->get('gravatar_enable')
            && array_key_exists('email', $this->contact_details))
        {
            $size = $this->_config->get('gravatar_size');
            $gravatar_url = "http://www.gravatar.com/avatar.php?gravatar_id=" . md5($this->contact_details['email']) . "&size=".$size;
            echo "<img src=\"{$gravatar_url}\" class=\"photo\" style=\"float: right; margin-left: 4px;\" />\n";
        }

        if (array_key_exists('guid', $this->contact_details))
        {
            // Identifier
            echo "<span class=\"uid\" style=\"display: none;\">{$this->contact_details['guid']}</span>";
        }

        // The Name sequence
        echo "<div class=\"n\">\n";

        $linked = false;
        if (   $this->link
            || $this->link_contacts)
        {
            $url = $this->determine_url();
            if ($url)
            {
                echo "<a href=\"{$url}\">";
                $linked = true;
            }
        }

        echo "<span class=\"given-name\">{$this->contact_details['firstname']}</span> <span class=\"family-name\">{$this->contact_details['lastname']}</span>";

        if ($linked)
        {
            echo "</a>";
        }

        echo "</div>\n";

        // Contact information sequence
        echo "<ul>\n";
        if ($this->extra_html)
        {
            echo $this->extra_html;
        }

        if (   $this->show_groups
            && !empty($this->contact_details['id']))
        {
            $mc = midcom_db_member::new_collector('uid', $this->contact_details['id']);
            $mc->add_value_property('gid');
            $mc->add_value_property('extra');
            $mc->execute();

            $memberships = $mc->list_keys();
            if ($memberships)
            {
                foreach ($memberships as $guid => $empty)
                {
                    echo "<li class=\"org\">";

                    try
                    {
                        if (class_exists('org_openpsa_contacts_group_dba'))
                        {
                            $group = org_openpsa_contacts_group_dba::get_cached($mc->get_subkey($guid, 'gid'));
                        }
                        else
                        {
                            $group = new midcom_db_group($mc->get_subkey($guid, 'gid'));
                        }
                    }
                    catch (midcom_error $e)
                    {
                        $e->log();
                        continue;
                    }
                    if ($mc->get_subkey($guid, 'extra'))
                    {
                        echo "<span class=\"title\">" . $mc->get_subkey($guid, 'extra') . "</span>, ";
                    }

                    if ($group->official)
                    {
                        $group_label = $group->official;
                    }
                    else
                    {
                        $group_label = $group->name;
                    }

                    if ($this->link_contacts)
                    {
                        if (!$this->contacts_url)
                        {
                            $siteconfig = org_openpsa_core_siteconfig::get_instance();
                            $this->contacts_url = $siteconfig->get_node_full_url('org.openpsa.contacts');
                        }

                        if (!$this->contacts_url)
                        {
                            $this->link_contacts = false;
                        }
                        else
                        {
                            $group_label = "<a href=\"{$this->contacts_url}group/{$group->guid}/\">{$group_label}</a>";
                        }
                    }

                    echo "<span class=\"organization-name\">{$group_label}</span>";
                    echo "</li>\n";
                }
            }
        }

    if ($this->_config->get('click_to_dial')) {
    $dialurl=$this->_config->get('click_to_dial_url');
    }

        if (array_key_exists('handphone', $this->contact_details))
        {
            if ($this->_config->get('click_to_dial')) {
                echo "<li class=\"tel cell\"><a title=\"Dial {$this->contact_details['handphone']}\" href=\"#\" onclick=\"javascript:window.open('$dialurl{$this->contact_details['handphone']}','dialwin','width=300,height=200')\">{$this->contact_details['handphone']}</a></li>\n";
            } else {
                echo "<li class=\"tel cell\">{$this->contact_details['handphone']}</li>\n";
            }
        }

        if (array_key_exists('workphone', $this->contact_details))
        {
            if ($this->_config->get('click_to_dial')) {
                echo "<li class=\"tel work\"><a title=\"Dial {$this->contact_details['workphone']}\" href=\"#\" onclick=\"javascript:window.open('$dialurl{$this->contact_details['workphone']}','dialwin','width=300,height=200')\">{$this->contact_details['workphone']}</a></li>\n";
            } else {
                echo "<li class=\"tel work\">{$this->contact_details['workphone']}</li>\n";
            }
        }

        if (array_key_exists('homephone', $this->contact_details))
        {
            if ($this->_config->get('click_to_dial')) {
                echo "<li class=\"tel home\"><a title=\"Dial {$this->contact_details['homephone']}\" href=\"#\" onclick=\"javascript:window.open('$dialurl{$this->contact_details['homephone']}','dialwin','width=300,height=200')\">{$this->contact_details['homephone']}</a></li>\n";
            } else {
                echo "<li class=\"tel home\">{$this->contact_details['homephone']}</li>\n";
            }
        }

        if (array_key_exists('email', $this->contact_details))
        {
            echo "<li class=\"email\"><a href=\"mailto:{$this->contact_details['email']}\">{$this->contact_details['email']}</a></li>\n";
        }

        if (array_key_exists('homepage', $this->contact_details))
        {
            echo "<li class=\"url\"><a href=\"{$this->contact_details['homepage']}\">{$this->contact_details['homepage']}</a></li>\n";
        }

        if (array_key_exists('skype', $this->contact_details))
        {
            echo "<li class=\"tel skype\"";
            if (empty($_SERVER['HTTPS']))
            {
                // TODO: either complain enough to Skype to have them allow SSL to this server or have some component (o.o.contacts) proxy the image
                echo " style=\"background-image: url('http://mystatus.skype.com/smallicon/{$this->contact_details['skype']}');\"";
            }
            echo "><a href=\"skype:{$this->contact_details['skype']}?call\">{$this->contact_details['skype']}</a></li>\n";
        }

        // Instant messaging contact information
        if (array_key_exists('jid', $this->contact_details))
        {
            echo "<li class=\"jabbber\"";
            $edgar_url = $this->_config->get('jabber_edgar_url');
            echo " style=\"background-repeat: no-repeat;background-image: url('{$edgar_url}?jid={$this->contact_details['jid']}&type=image');\"";
            echo "><a href=\"xmpp:{$this->contact_details['jid']}\">{$this->contact_details['jid']}</a></li>\n";
        }

        echo "</ul>\n";

        echo "</div>\n";
    }

    /**
     * Renderer for organization address cards
     */
    static function show_address_card(&$customer, $cards)
    {
        $cards_to_show = array();
        $multiple_addresses = false;
        $inherited_cards_only = true;
        $default_shown = false;
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $contacts_url = $siteconfig->get_node_full_url('org.openpsa.contacts');

        foreach ($cards as $cardname)
        {
            if ($cardname == 'visiting')
            {
                if ($customer->street)
                {
                    $default_shown = true;
                    $cards_to_show[] = $cardname;
                }
                continue;
            }

            $property = $cardname . 'Street';

            if (sizeof($cards_to_show) == 0)
            {
                if ($property != 'street'
                    && $customer->$property)
                {
                    $inherited_cards_only = false;
                    $cards_to_show[] = $cardname;
                }
                else if (!$default_shown
                         && $customer->street)
                {
                    $default_shown = true;
                    $cards_to_show[] = $cardname;
                }
            }
            else
            {
                if ($customer->$property
                    || ($customer->street
                        && (!$inherited_cards_only
                            && !$default_shown)))
                {
                    $inherited_cards_only = false;
                    $multiple_addresses = true;
                    $cards_to_show[] = $cardname;
                }
            }
        }

        if (sizeof($cards_to_show) == 0)
        {
            return;
        }

        $root_group = org_openpsa_contacts_interface::find_root_group();
        $parent = $customer->get_parent();
        $parent_name = false;
        if ($parent->id != $root_group->id)
        {
            $parent_name = $parent->get_label();
        }

        foreach ($cards_to_show as $cardname)
        {
            echo '<div class="vcard">';
            if ($multiple_addresses
                || ($cardname != 'visiting'
                    && !$inherited_cards_only))
            {
                echo '<div style="text-align:center"><em>' . $_MIDCOM->i18n->get_string($cardname . ' address', 'org.openpsa.contacts') . "</em></div>\n";
            }
            echo "<strong>\n";
            if ($parent_name)
            {
                echo '<a href="' . $contacts_url . 'group/' . $parent->guid . '/">' . $parent_name . "</a><br />\n";
            }

            $label = $customer->get_label();

            if ($cardname != 'visiting')
            {
                $label_property = $cardname . '_label';
                $label = $customer->$label_property;
            }

            echo $label . "\n";
            echo "</strong>\n";

            $property_street = 'street';
            $property_postcode = 'postcode';
            $property_city = 'city';

            if ($cardname != 'visiting')
            {
                $property_street = $cardname . 'Street';
                $property_postcode = $cardname . 'Postcode';
                $property_city = $cardname . 'City';
            }
            if ($customer->$property_street)
            {
                echo "<p>{$customer->$property_street}<br />\n";
                echo "{$customer->$property_postcode} {$customer->$property_city}</p>\n";
            }
            else if ($customer->street)
            {
                echo "<p>{$customer->street}<br />\n";
                echo "{$customer->postcode} {$customer->city}</p>\n";
            }
            echo "</div>\n";
        }
    }
}
?>