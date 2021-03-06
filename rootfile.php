<?php
$GLOBALS['midcom_config_local'] = array();

// Check that the environment is a working one
if (extension_loaded('midgard2'))
{
    if (!class_exists('midgard_topic'))
    {
        throw new Exception('You need to install OpenPSA MgdSchemas from the "schemas" directory to the Midgard2 schema directory');
    }

    // Initialize the $_MIDGARD superglobal
    $_MIDGARD = array
    (
        'argv' => array(),

        'user' => 0,
        'admin' => false,
        'root' => false,

        'auth' => false,
        'cookieauth' => false,

        // General host setup
        'page' => 0,
        'debug' => false,

        'host' => 0,
        'style' => 0,
        'author' => 0,
        'config' => array
        (
            'prefix' => '',
            'quota' => false,
            'unique_host_name' => 'openpsa',
            'auth_cookie_id' => 1,
        ),

        'schema' => array
        (
        ),
    );

    $GLOBALS['midcom_config_local']['person_class'] = 'openpsa_person';

    $midgard = midgard_connection::get_instance();

    // Workaround for https://github.com/midgardproject/midgard-php5/issues/49
    if (!$midgard->is_connected())
    {
        $config = new midgard_config();
        $config->read_file_at_path(ini_get('midgard.configuration_file'));
        $midgard->open_config($config);
    }

    if (method_exists($midgard, 'enable_workspace'))
    {
        $midgard->enable_workspace(false);
    }

    // workaround for segfaults that might have something to do with https://bugs.php.net/bug.php?id=51091
    // see also https://github.com/midgardproject/midgard-php5/issues/50
    if (   function_exists('gc_enabled')
        && gc_enabled())
    {
        gc_disable();
    }

}
else if (!extension_loaded('midgard'))
{
    throw new Exception("OpenPSA requires Midgard PHP extension to run");
}

// Path to the MidCOM environment
define('MIDCOM_ROOT', __DIR__ . '/lib');

$prefix = dirname($_SERVER['SCRIPT_NAME']) . '/';
if (strpos($_SERVER['REQUEST_URI'], $prefix) !== 0)
{
    $prefix = '/';
}
define('OPENPSA2_PREFIX', $prefix);

header('Content-Type: text/html; charset=utf-8');

$GLOBALS['midcom_config_local']['theme'] = 'OpenPsa2';

if (file_exists(MIDCOM_ROOT . '/../config.inc.php'))
{
    include MIDCOM_ROOT . '/../config.inc.php';
}
else
{
    //TODO: Hook in an installation wizard here, once it is written
    include MIDCOM_ROOT . '/../config-default.inc.php';
}

if (! defined('MIDCOM_STATIC_URL'))
{
    define('MIDCOM_STATIC_URL', '/openpsa2-static');
}

if (file_exists(MIDCOM_ROOT . '/../themes/' . $GLOBALS['midcom_config_local']['theme'] . '/config.inc.php'))
{
    include MIDCOM_ROOT . '/../themes/' . $GLOBALS['midcom_config_local']['theme'] . '/config.inc.php';
}

// Include the MidCOM environment for running OpenPSA
require MIDCOM_ROOT . '/midcom.php';

// Start request processing
$midcom = midcom::get();
$midcom->codeinit();
$midcom->content();
$midcom->finish();
?>
