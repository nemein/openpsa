<?php
/**
 * @package org.routamc.positioning
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Datamanager 2 Positioning widget
 *
 * As with all subclasses, the actual initialization is done in the initialize() function,
 * not in the constructor, to allow for error handling.
 *
 * It can only be bound to a position type (or subclass thereof), and inherits the configuration
 * from there as far as possible.
 *
 * Example:
 'location' => Array
 (
     'title' => 'location',
     'storage' => null,
     'type' => 'org_routamc_positioning_dm2_type',
     'widget' => 'org_routamc_positioning_dm2_widget',
     'widget_config' => Array
     (
         'service' => 'geonames', //Possible values are city, geonames, yahoo
     ),
 ),
 *
 * @package org.routamc.positioning
 */
class org_routamc_positioning_dm2_widget extends midcom_helper_datamanager2_widget
{
    /**
     * id of the element
     *
     * @var String
     */
    private $_element_id = "positioning_widget";

    /**
     * List of enabled positioning methods
     * Available methods: place, map, coordinates
     * Defaults to all.
     *
     * @var array
     */
    public $enabled_methods = null;

    /**
     * The service backend to use for searches. Defaults to geonames
     */
    var $service = null;

    /**
     * List of defaults used in location inputs
     * key => value pairs (ie. 'country' => 'FI')
     *
     * @var array
     */
    public $input_defaults = array();

    /**
     * List of additional XEP fields included in location
     * (ie. 'text', 'room')
     *
     * @var array
     */
    public $use_xep_keys = array();

    /**
     * The group of widgets items as QuickForm elements
     */
    var $_widget_elements = array();
    var $_main_group = array();
    var $_countrylist = array();
    var $_allowed_xep_keys = array();

    /**
     * Options to pass to the javascript widget.
     * Possible values:
     * - (int) maxRows : Maximum amount of results returned. If this is set greater than 1,
     *   the widget will show alternative results and lets user to choose the best match.
     *   Defaults to: 20
     * - (int) radius : Radius of the area we search for alternatives. (in Kilometers)
     *   Defaults to: 5
     */

    var $js_maxRows = null;
    var $js_radius = null;

    var $js_options = array();
    var $js_options_str = '';

    /**
     * The initialization event handler post-processes the maxlength setting.
     *
     * @return boolean Indicating Success
     */
    public function _on_initialize()
    {
        if (!is_a($this->_type, 'org_routamc_positioning_dm2_type'))
        {
            debug_add("Warning, the field {$this->name} is not a position type or subclass thereof, you cannot use the position widget with it.",
                MIDCOM_LOG_WARN);
            return false;
        }

        if (is_null($this->enabled_methods))
        {
            $this->enabled_methods = array
            (
                'place',
                'map',
                'coordinates'
            );
        }

        if (is_null($this->service))
        {
            $this->service = 'geonames';
        }

        $_MIDCOM->enable_jquery();

        $this->add_stylesheet(MIDCOM_STATIC_URL . '/org.routamc.positioning/widget/position_widget.css');
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/org.routamc.positioning/widget/jquery.tabs.css');
        $_MIDCOM->add_link_head
        (
            array
            (
                'condition' => 'lte IE 7',
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => MIDCOM_STATIC_URL . '/org.routamc.positioning/widget/jquery.tabs-ie.css',
                'media' => 'projection, screen',
            )
        );

        $_MIDCOM->add_jsfile(MIDCOM_STATIC_URL . '/org.routamc.positioning/widget/jquery.tabs.js');
        $_MIDCOM->add_jsfile(MIDCOM_STATIC_URL . '/org.routamc.positioning/widget/widget.js');

        $this->_element_id = "{$this->_namespace}{$this->name}_position_widget";

        $config = "{
            fxAutoHeight: false,
            fxSpeed: 'fast',
            onShow: function() {
                jQuery('#{$this->_element_id}').dm2_pw_position_map_to_current();
            }
        }";

        $script = "jQuery('#{$this->_element_id }').tabs({$config});\n";
        $_MIDCOM->add_jquery_state_script($script);

        $this->_get_country_list();
        $this->_init_widgets_js_options();

        $this->_allowed_xep_keys = array
        (
            'area',
            'building',
            'description',
            'floor',
            'room',
            'text',
            'uri',
        );

        return true;
    }

    /**
     * Creates the tab view for all enabled positioning methods
     * Also adds static options to results.
     */
    function add_elements_to_form($attributes)
    {
        // Get url to geocode handler
        $nav = new midcom_helper_nav();
        $root_node = $nav->get_node($nav->get_root_node());
        $this->_handler_url = $root_node[MIDCOM_NAV_FULLURL] . 'midcom-exec-org.routamc.positioning/geocode.php';

        $html = "<div id=\"{$this->_element_id}\" class=\"midcom_helper_datamanager2_widget_position\"><!-- widget starts -->\n";

        $html .= "<input class=\"position_widget_id\" id=\"{$this->_element_id}_id\" name=\"{$this->_element_id}_id\" type=\"hidden\" value=\"{$this->_element_id}\" />";
        $html .= "<input class=\"position_widget_backend_url\" id=\"{$this->_element_id}_backend_url\" name=\"{$this->_element_id}_backend_url\" type=\"hidden\" value=\"{$this->_handler_url}\" />";
        $html .= "<input class=\"position_widget_backend_service\" id=\"{$this->_element_id}_backend_service\" name=\"{$this->_element_id}_backend_service\" type=\"hidden\" value=\"{$this->service}\" />";

        $html .= "    <ul>\n";

        foreach ($this->enabled_methods as $method)
        {
            $html .= "        <li><a href=\"#{$this->_element_id}_tab_content_{$method}\"><span>" . $_MIDCOM->i18n->get_string($method, 'org.routamc.positioning') . "</span></a></li>\n";
        }

        $html .= "    </ul>\n";
        $this->_widget_elements[] = HTML_QuickForm::createElement
        (
            'static',
            "{$this->_element_id}_static_widget_start",
            '',
            $html
        );

        foreach ($this->enabled_methods as $method)
        {
            $function = "_add_{$method}_method_elements";
            $this->$function();
        }

        $html = "</div><!-- widget ends -->\n";
        $this->_widget_elements[] = HTML_QuickForm::createElement
        (
            'static',
            "{$this->_element_id}_static_widget_end",
            '',
            $html
        );

        $this->_main_group = $this->_form->addGroup
        (
            $this->_widget_elements,
            $this->name,
            $this->_translate($this->_field['title']),
            ''
        );
    }

    function _add_place_method_elements()
    {
        $html = "\n<div id=\"{$this->_element_id}_tab_content_place\" class=\"position_widget_tab_content position_widget_tab_content_place\"><!-- tab_content_place starts -->\n";

        $html .= "<div class=\"geodata_btn\" id='{$this->_element_id}_geodata_btn'></div>";
        $html .= "<div class=\"indicator\" id='{$this->_element_id}_indicator' style=\"display: none;\"></div>";

        $country = $this->_type->location->country;
        if (   !$country
            && isset($_REQUEST["{$this->_element_id}_input_place_country"]))
        {
            $country = $_REQUEST["{$this->_element_id}_input_place_country"];
        }
        if (   !$country
            && isset($this->input_defaults['country']))
        {
            $country = $this->input_defaults['country'];
        }

        $html .= $this->_render_country_list($country);

        $city_name = $this->_get_city_name();

        $html .= "<label for='{$this->_element_id}_input_place_city' id='{$this->_element_id}_input_place_city_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('xep_city', 'org.routamc.positioning') . "</span><span class=\"proposal\"></span>";
        $html .= "<input size=\"40\" class=\"shorttext position_widget_input position_widget_input_place_city\" id=\"{$this->_element_id}_input_place_city\" name=\"{$this->_element_id}_input_place_city\" type=\"text\" value=\"{$city_name}\" />";
        $html .= "</label>";

        $region = $this->_type->location->region;
        if (   !$region
            && isset($_REQUEST["{$this->_element_id}_input_place_region"]))
        {
            $region = $_REQUEST["{$this->_element_id}_input_place_region"];
        }
        if (   !$region
            && isset($this->input_defaults['region']))
        {
            $region = $this->input_defaults['region'];
        }

        $html .= "<label for='{$this->_element_id}_input_place_region' id='{$this->_element_id}_input_place_region_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('xep_region', 'org.routamc.positioning') . "</span><span class=\"proposal\"></span>";
        $html .= "<input size=\"40\" class=\"shorttext position_widget_input position_widget_input_place_region\" id=\"{$this->_element_id}_input_place_region\" name=\"{$this->_element_id}_input_place_region\" type=\"text\" value=\"{$region}\" />";
        $html .= "</label>";

        $street = $this->_type->location->street;
        if (   !$street
            && isset($_REQUEST["{$this->_element_id}_input_place_street"]))
        {
            $street = $_REQUEST["{$this->_element_id}_input_place_street"];
        }
        if (   !$street
            && isset($this->input_defaults['street']))
        {
            $street = $this->input_defaults['street'];
        }

        $html .= "<label for='{$this->_element_id}_input_place_street' id='{$this->_element_id}_input_place_street_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('xep_street', 'org.routamc.positioning') . "</span><span class=\"proposal\"></span>";
        $html .= "<input size=\"40\" class=\"shorttext position_widget_input position_widget_input_place_street\" id=\"{$this->_element_id}_input_place_street\" name=\"{$this->_element_id}_input_place_street\" type=\"text\" value=\"{$street}\" />";
        $html .= "</label>";

        $postalcode = $this->_type->location->postalcode;
        if (   !$postalcode
            && isset($_REQUEST["{$this->_element_id}_input_place_postalcode"]))
        {
            $postalcode = $_REQUEST["{$this->_element_id}_input_place_postalcode"];
        }
        if (   !$postalcode
            && isset($this->input_defaults['postalcode']))
        {
            $postalcode = $this->input_defaults['postalcode'];
        }

        $html .= "<label for='{$this->_element_id}_input_place_postalcode' id='{$this->_element_id}_input_place_postalcode_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('xep_postalcode', 'org.routamc.positioning') . "</span><span class=\"proposal\"></span>";
        $html .= "<input size=\"40\" class=\"shorttext position_widget_input position_widget_input_place_postalcode\" id=\"{$this->_element_id}_input_place_postalcode\" name=\"{$this->_element_id}_input_place_postalcode\" type=\"text\" value=\"{$postalcode}\" />";
        $html .= "</label>";

        $html .= $this->_render_xep_keys();

        $html .= "<div id=\"{$this->_element_id}_status_box\" class=\"status_box\"></div>";

        $html .= "\n</div><!-- tab_content_place ends -->\n";

        $this->_widget_elements[] = HTML_QuickForm::createElement
        (
            'static',
            "{$this->_element_id}_static_place",
            '',
            $html
        );
    }

    private function _get_city_name()
    {
        $city_name = '';
        $city_id = $this->_type->location->city;

        if (   !$city_id
            && isset($_REQUEST["{$this->_element_id}_input_place_city"]))
        {
            $city_id = $this->_get_city_by_name($_REQUEST["{$this->_element_id}_input_place_city"]);
            if (! $city_id)
            {
                $city_name = $_REQUEST["{$this->_element_id}_input_place_city"];
            }
        }

        if (   !$city_id
            && isset($this->input_defaults['city'])
            && is_numeric($this->input_defaults['city']))
        {
            $city_id = $this->input_defaults['city'];
        }
        if (   !$city_name
            && isset($this->input_defaults['city'])
            && is_string($this->input_defaults['city']))
        {
            $city_name = $this->input_defaults['city'];
        }

        if ($city_id)
        {
            try
            {
                $city = new org_routamc_positioning_city_dba($city_id);
                $city_name = $city->city;
            }
            catch (midcom_error $e){}
        }
        return $city_name;
    }

    private function _render_xep_keys()
    {
        $html = '';
        $inserted_xep_keys = array();

        foreach ($this->_allowed_xep_keys as $xep_key)
        {
            if (   !in_array($xep_key, $this->use_xep_keys)
                || !$_MIDCOM->dbfactory->property_exists($this->_type->location, $xep_key)
                || in_array($xep_key, $inserted_xep_keys))
            {
                // Skip
                continue;
            }
            $inserted_xep_keys[] = $xep_key;

            $xep_value = $this->_type->location->$xep_key;

            if (   !$xep_value
                && isset($_REQUEST["{$this->_element_id}_input_place_{$xep_key}"]))
            {
                $xep_value = $_REQUEST["{$this->_element_id}_input_place_{$xep_key}"];
            }
            if (   !$xep_value
                && isset($this->input_defaults[$xep_key]))
            {
                $xep_value = $this->input_defaults[$xep_key];
            }

            $html .= "<label for='{$this->_element_id}_input_place_{$xep_key}' id='{$this->_element_id}_input_place_{$xep_key}_label'>";
            $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string("xep_{$xep_key}", 'org.routamc.positioning') . "</span><span class=\"proposal\"></span>";
            $html .= "<input size=\"40\" class=\"shorttext position_widget_input position_widget_input_place_{$xep_key}\" id=\"{$this->_element_id}_input_place_{$xep_key}\" name=\"{$this->_element_id}_input_place_{$xep_key}\" type=\"text\" value=\"{$xep_value}\" />";
            $html .= "</label>";
        }
        return $html;
    }

    function _add_map_method_elements()
    {
        $html = "\n<div id=\"{$this->_element_id}_tab_content_map\" class=\"position_widget_tab_content position_widget_tab_content_map\"><!-- tab_content_map starts -->\n";

        $html .= "\n<div class=\"position_widget_actions\">\n";
        $html .= "\n<div id=\"{$this->_element_id}_position_widget_action_cam\">[ Clear alternatives ]</div> \n";
        $html .= "\n</div>\n";

        $orp_map = new org_routamc_positioning_map("{$this->_element_id}_map");
        $html .= $orp_map->show(420, 300, null, false);

        $html .= "\n</div><!-- tab_content_map ends -->\n";

        $this->_widget_elements[] = HTML_QuickForm::createElement
        (
            'static',
            "{$this->_element_id}_static_map",
            '',
            $html
        );

        $script = "init_position_widget('{$this->_element_id}', mapstraction_{$this->_element_id}_map, {$this->js_options_str});";
        $script = "jQuery('#{$this->_element_id}').dm2_position_widget(mapstraction_{$this->_element_id}_map, {$this->js_options_str});";
        $_MIDCOM->add_jquery_state_script($script);
    }

    function _add_coordinates_method_elements()
    {
        $html = "\n<div id=\"{$this->_element_id}_tab_content_coordinates\" class=\"position_widget_tab_content position_widget_tab_content_coordinates\"><!-- tab_content_coordinates starts -->\n";

        $html .= "<div class=\"geodata_btn\" id='{$this->_element_id}_revgeodata_btn'></div>";
        $html .= "<div class=\"indicator\" id='{$this->_element_id}_revindicator' style=\"display: none;\"></div>";

        $lat = $this->_type->location->latitude;
        if (   !$lat
            && isset($_REQUEST["{$this->_element_id}_input_coordinates_latitude"]))
        {
            $lat = $_REQUEST["{$this->_element_id}_input_coordinates_latitude"];
        }
        $lon = $this->_type->location->longitude;
        if (   !$lon
            && isset($_REQUEST["{$this->_element_id}_input_coordinates_longitude"]))
        {
            $lon = $_REQUEST["{$this->_element_id}_input_coordinates_longitude"];
        }

        $lat = str_replace(",", ".", $lat);
        $lon = str_replace(",", ".", $lon);

        $html .= "<label for='{$this->_element_id}_input_coordinates_latitude' id='{$this->_element_id}_input_coordinates_latitude_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('latitude', 'org.routamc.positioning') . "</span>";
        $html .= "<input size=\"20\" class=\"shorttext position_widget_input position_widget_input_coordinates_latitude\" id=\"{$this->_element_id}_input_coordinates_latitude\" name=\"{$this->_element_id}_input_coordinates_latitude\" type=\"text\" value=\"{$lat}\" />";
        $html .= "</label>";

        $html .= "<label for='{$this->_element_id}_input_coordinates_longitude' id='{$this->_element_id}_input_coordinates_longitude_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('longitude', 'org.routamc.positioning') . "</span>";
        $html .= "<input size=\"20\" class=\"shorttext position_widget_input position_widget_input_coordinates_longitude\" id=\"{$this->_element_id}_input_coordinates_longitude\" name=\"{$this->_element_id}_input_coordinates_longitude\" type=\"text\" value=\"{$lon}\" />";
        $html .= "</label>";

        $html .= "\n</div><!-- tab_content_coordinates ends -->\n";

        $this->_widget_elements[] = HTML_QuickForm::createElement
        (
            'static',
            "{$this->_element_id}_static_coordinates",
            '',
            $html
        );
    }

    function _get_country_list()
    {
        $this->_countrylist = array
        (
            '' => $_MIDCOM->i18n->get_string('select your country', 'org.routamc.positioning'),
        );

        $qb = org_routamc_positioning_country_dba::new_query_builder();
        $qb->add_constraint('code', '<>', '');
        $qb->add_order('name', 'ASC');
        $countries = $qb->execute_unchecked();

        if (count($countries) == 0)
        {
            debug_add('Cannot render country list: No countries found. You have to use org.routamc.positioning to import countries to database.');
        }

        foreach ($countries as $country)
        {
            $this->_countrylist[$country->code] = $country->name;
        }
    }

    function _render_country_list($current='')
    {
        $html = '';

        if (   empty($this->_countrylist)
            || count($this->_countrylist) == 1)
        {
            return $html;
        }

        $html .= "<label for='{$this->_element_id}_input_place_country' id='{$this->_element_id}_input_place_country_label'>";
        $html .= "<span class=\"field_text\">" . $_MIDCOM->i18n->get_string('xep_country', 'org.routamc.positioning') . "</span>";
        $html .= "<select class=\"dropdown position_widget_input position_widget_input_place_country\" id=\"{$this->_element_id}_input_place_country\" name=\"{$this->_element_id}_input_place_country\">";

        foreach ($this->_countrylist as $code => $name)
        {
            $selected = '';
            if ($code == $current)
            {
                $selected = 'selected="selected"';
            }
            $html .= "<option value=\"{$code}\" {$selected}>{$name}</option>";
        }

        $html .= "</select>";
        $html .= "</label>";

        return $html;
    }

    function _init_widgets_js_options()
    {
        $this->js_options['maxRows'] = 20;
        $this->js_options['radius'] = 5;

        if (   !is_null($this->js_maxRows)
            && $this->js_maxRows > 0)
        {
            $this->js_options['maxRows'] = $this->js_maxRows;
        }
        if (   !is_null($this->js_radius)
            && $this->js_radius > 0)
        {
            $this->js_options['radius'] = $this->js_radius;
        }

        $this->js_options_str = "{ ";
        if (! empty($this->js_options))
        {
            $opt_cnt = count($this->js_options);
            $i = 0;
            foreach ($this->js_options as $key => $value)
            {
                $i++;
                $this->js_options_str .= "{$key}: {$value}";
                if ($i < $opt_cnt)
                {
                    $this->js_options_str .= ", ";
                }
            }
        }
        $this->js_options_str .= " }";
    }

    function get_default()
    {
        try
        {
            $city = new org_routamc_positioning_city_dba($this->_type->location->city);
            $city_name = $city->city;
        }
        catch (midcom_error $e)
        {
            $city_name = '';
        }

        $lat = $this->_type->location->latitude;
        if (   !$lat
            && isset($_REQUEST["{$this->_element_id}_input_coordinates_latitude"]))
        {
            $lat = $_REQUEST["{$this->_element_id}_input_coordinates_latitude"];
        }
        $lon = $this->_type->location->longitude;
        if (   !$lon
            && isset($_REQUEST["{$this->_element_id}_input_coordinates_longitude"]))
        {
            $lon = $_REQUEST["{$this->_element_id}_input_coordinates_longitude"];
        }

        $lat = str_replace(",", ".", $lat);
        $lon = str_replace(",", ".", $lon);

        if (   !empty($lat)
            && !empty($lon))
        {
            $script = "jQuery('#{$this->_element_id}').dm2_pw_init_current_pos({$lat},{$lon});";
            $_MIDCOM->add_jquery_state_script($script);
        }

        return Array
        (
            "{$this->_element_id}_input_place_country" => $this->_type->location->country,
            "{$this->_element_id}_input_place_city" => $city_name,
            "{$this->_element_id}_input_place_street" => $this->_type->location->street,
            "{$this->_element_id}_input_place_postalcode" => $this->_type->location->postalcode,
            "{$this->_element_id}_input_coordinates_latitude" => $this->_type->location->latitude,
            "{$this->_element_id}_input_coordinates_longitude" => $this->_type->location->longitude,
        );
    }

    function _get_city_by_name($city_name, $results = array())
    {
        if (empty($city_name))
        {
            return 0;
        }
        $city_id = 0;
        $city = org_routamc_positioning_city_dba::get_by_name($city_name);
        if (   $city
            && $city->id)
        {
            $city_id = $city->id;
        }
        else if (! empty($results))
        {
            $city = new org_routamc_positioning_city_dba();
            $city->city = $city_name;

            if (   isset($results["{$this->_element_id}_input_place_country"])
                && $results["{$this->_element_id}_input_place_country"])
            {
                $city->country = $results["{$this->_element_id}_input_place_country"];
            }
            if (isset($results["{$this->_element_id}_input_place_region"]))
            {
                $city->region = $results["{$this->_element_id}_input_place_region"];
            }
            if (   isset($results["{$this->_element_id}_input_coordinates_latitude"])
                && $results["{$this->_element_id}_input_coordinates_latitude"] != '')
            {
                $city->latitude = $results["{$this->_element_id}_input_coordinates_latitude"];
            }
            if (   isset($results["{$this->_element_id}_input_coordinates_longitude"])
                && $results["{$this->_element_id}_input_coordinates_longitude"] != '')
            {
                $city->longitude = $results["{$this->_element_id}_input_coordinates_longitude"];
            }
            if (! $city->create())
            {
                debug_add("Cannot save new city '{$city_name}'");
            }

            $city_id = $city->id;
        }

        return $city_id;
    }

    function sync_type_with_widget($results)
    {
        if (isset($results["{$this->_element_id}_input_place_country"]))
        {
            $this->_type->location->country = $results["{$this->_element_id}_input_place_country"];
        }
        if (isset($results["{$this->_element_id}_input_place_city"]))
        {
            $city_id = $this->_get_city_by_name($results["{$this->_element_id}_input_place_city"], $results);
            $this->_type->location->city = $city_id;
        }
        if (isset($results["{$this->_element_id}_input_place_street"]))
        {
            $this->_type->location->street = $results["{$this->_element_id}_input_place_street"];
        }
        if (isset($results["{$this->_element_id}_input_place_region"]))
        {
            $this->_type->location->region = $results["{$this->_element_id}_input_place_region"];
        }
        if (isset($results["{$this->_element_id}_input_place_postalcode"]))
        {
            $this->_type->location->postalcode = $results["{$this->_element_id}_input_place_postalcode"];
        }

        if (   isset($results["{$this->_element_id}_input_coordinates_latitude"])
            && $results["{$this->_element_id}_input_coordinates_latitude"] != '')
        {
            $this->_type->location->latitude = $results["{$this->_element_id}_input_coordinates_latitude"];
        }
        if (   isset($results["{$this->_element_id}_input_coordinates_longitude"])
            && $results["{$this->_element_id}_input_coordinates_longitude"] != '')
        {
            $this->_type->location->longitude = $results["{$this->_element_id}_input_coordinates_longitude"];
        }

        foreach ($this->_allowed_xep_keys as $xep_key)
        {
            if (   !in_array($xep_key, $this->use_xep_keys)
                || !$_MIDCOM->dbfactory->property_exists($this->_type->location, $xep_key))
            {
                continue;
            }
            $this->_type->location->$xep_key = $results["{$this->_element_id}_input_place_{$xep_key}"];
        }
    }

    function is_frozen()
    {
        return $this->_main_group->isFrozen();
    }
}
?>