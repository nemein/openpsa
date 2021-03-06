<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This is the handling class of the cron service. When executed, it checks all component
 * manifests for cron jobs and runs them sequentially. The components are processed in the
 * order they are returned by the component loader, the jobs of a single component are run
 * in the order they are listed in the configuration array.
 *
 * <b>Cron Job configuration</b>
 *
 * Each cron job is defined by an associative array containing the following keys:
 *
 * - <i>string handler</i> holds the full class name which should handle the cron job invocation,
 *   it will be defined by the responsible component.
 * - <i>Array handler_config</i> is the handler specific configuration of the cron job. This is optional
 *   and can therefore be an empty array. It is used to customize cron job behavior on a manifest
 *   level only (use your component configuration for more specific settings.)
 * - <i>int recurrence</i> must be one of MIDCOM_CRON_* constants.
 * - <i>string component (INTERNAL)</i> holds the name of the component this Cron job is associated with.
 *   This key is created automatically.
 *
 * The Cron service uses <i>customdata</i> section of the manifest, using the key <i>midcom.services.cron</i>
 * as you might have guessed. So, an example cron entry could look like this:
 *
 * <code>
 * 'customdata' => Array
 * (
 *     'midcom.services.cron' => Array
 *     (
 *         Array
 *         (
 *             'handler' => 'net_nehmer_static_cron_test',
 *             'handler_config' => Array ('test', 'configuration', 'entries'),
 *             'recurrence' => MIDCOM_CRON_MINUTE,
 *         )
 *     ),
 * ),
 * </code>
 *
 * A simple (and useless) handler class would look like this:
 *
 * <code>
 * <?php
 * class net_nehmer_static_cron_test extends midcom_baseclasses_components_cron_handler
 * {
 *     function _on_initialize()
 *     {
 *         return true;
 *     }
 *
 *     function _on_execute()
 *     {
 *         $this->print_error("Executing...");
 *         $this->print_error(strftime('%x %X'));
 *     }
 * }
 *
 * ?>
 * </code>
 *
 * The component does not need to load the class automatically, instead, it can resort on the
 * auto-loading feature of the service. It will look for the handler in a file named after it,
 * by replacing the underscores with slashes: The above handler net_nehmer_static_cron_test
 * would be searched in net/nehmer/static/cron/test.php, relative to MIDCOM_ROOT.
 *
 * <b>Cron Job implementation suggestions</b>
 *
 * You should keep output to stdout to an absolute minimum. Normally, no output whatsoever
 * should be made, as the cron service itself is invoked using some kind of Cron Daemon. Only
 * if you output nothing, no status mail will be generated by cron.
 *
 * <b>Launching MidCOM Cron from a System Cron</b>
 *
 * You need to request the midcom-exec-midcom/cron.php page of your website to have cron running.
 * Lynx or the GET command line tools can be used, for example, to retrieve the cron page:
 *
 * <pre>
 * lynx -source http://your.site.com/midcom-exec-midcom/cron.php
 * GET http://your.site.com/midcom-exec-midcom/cron.php
 * </pre>
 *
 * The script produces no output unless anything goes wrong.
 *
 * At this time, this script does also do a request_sudo to gain Administrator privileges.
 * This is a temporary workaround until we can deal with HTTP authentication at this point.
 *
 * @package midcom.services
 */
class midcom_services_cron
{
    /**
     * The list of jobs to run. See the class introduction for a more precise definition of
     * these keys.
     *
     * @var array
     */
    private $_jobs = Array();

    /**
     * The recurrence rule to use, one of the MIDCOM_CRON_* constants (MIDCOM_CRON_MINUTE, MIDCOM_CRON_HOUR, MIDCOM_CRON_DAY).
     * Set in the constructor
     *
     * @var int
     */
    private $_recurrence = MIDCOM_CRON_MINUTE;

    /**
     * Jobs specific to the MidCOM core not covered by any component. (Services
     * use this facility for example.)
     *
     * @var Array
     * @todo Factor this out into its own configuration file.
     */
    private $_midcom_jobs = Array
    (
        Array
        (
            'handler' => 'midcom_cron_tmpservice',
            'recurrence' => MIDCOM_CRON_HOUR,
        ),
        Array
        (
            'handler' => 'midcom_cron_loginservice',
            'recurrence' => MIDCOM_CRON_HOUR,
        ),
        Array
        (
            'handler' => 'midcom_cron_purgedeleted',
            'recurrence' => MIDCOM_CRON_DAY,
        ),
    );

    /**
     * Constructor.
     */
    public function __construct($recurrence = MIDCOM_CRON_MINUTE)
    {
        $this->_recurrence = $recurrence;
    }

    /**
     * This helper function loads and validates all registered jobs. After
     * this call, all required handler classes will be available.
     */
    function _load_jobs()
    {
        $data = midcom::get('componentloader')->get_all_manifest_customdata('midcom.services.cron');
        $data['midcom'] = $this->_midcom_jobs;

        foreach ($data as $component => $jobs)
        {
            // First, verify the component is loaded
            if (   $component != 'midcom'
                && ! midcom::get('componentloader')->load_graceful($component))
            {
                $msg = "Failed to load the component {$component}. See the debug level log for further information, skipping this component.";
                debug_add($msg, MIDCOM_LOG_ERROR);
                echo "ERROR: {$msg}\n";
                continue;
            }

            foreach ($jobs as $job)
            {
                if (! $this->_validate_job($component, $job))
                {
                    // Error is printed by the validator (if applicable).
                    continue;
                }
                $job['component'] = $component;
                $this->_register_job($job);
            }
        }
    }

    /**
     * This function adds a validated job to the run queue.
     *
     * @param Array $job The job to register.
     */
    function _register_job($job)
    {
        if (! array_key_exists('handler_config', $job))
        {
            $job['handler_config'] = Array();
        }
        $this->_jobs[] = $job;
    }

    /**
     * This function checks a jobs definition for validity.
     *
     * @param string $component The name of the component the job is associated with, used for error-tracking.
     * @param Array $job The job to register.
     * @return boolean Indicating validity.
     */
    function _validate_job($component, array $job)
    {
        if (! array_key_exists('handler', $job))
        {
            $msg = "Failed to register a job for {$component}: No handler declaration.";
            debug_add($msg, MIDCOM_LOG_ERROR);
            debug_print_r('Got this job declaration:', $job);
            echo "ERROR: {$msg}\n";
            return false;
        }
        if (! array_key_exists('recurrence', $job))
        {
            $msg = "Failed to register a job for {$component}: No recurrence declaration.";
            debug_add($msg, MIDCOM_LOG_ERROR);
            debug_print_r('Got this job declaration:', $job);
            echo "ERROR: {$msg}\n";
            return false;
        }
        if (! $this->_validate_handler($job['handler']))
        {
            // Errors are logged by _validate_handler.
            return false;
        }
        switch ($job['recurrence'])
        {
            case MIDCOM_CRON_MINUTE:
            case MIDCOM_CRON_HOUR:
            case MIDCOM_CRON_DAY:
                break;

            default:
                $msg = "Failed to register a job for {$component}: Invalid recurrence.";
                debug_add($msg, MIDCOM_LOG_ERROR);
                debug_print_r('Got this job declaration:', $job);
                echo "ERROR: {$msg}\n";
                return false;
        }

        if ($job['recurrence'] == $this->_recurrence)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * This is a helper function used during validation. It ensures that handler class is
     * loaded. It has auto-class-loading support, which allows the component author to have
     * the cron classes only loaded on demand (see class introduction).
     *
     * @param string $handler_name The name of the handler to validate
     * @return boolean Indicating success
     */
    function _validate_handler($handler_name)
    {
        if (class_exists($handler_name))
        {
            return true;
        }

        // Try auto-load.
        $path = MIDCOM_ROOT . '/' . str_replace('_', '/', $handler_name) . '.php';
        if (! file_exists($path))
        {
            $msg = "Auto-loading of the class {$handler_name} from {$path} failed: File does not exist.";
            debug_add($msg, MIDCOM_LOG_ERROR);
            echo "ERROR: {$msg}\n";
            return false;
        }
        require_once($path);

        if (! class_exists($handler_name))
        {
            $msg = "Failed to register a job using {$handler_name}: Handler class is not declared.";
            debug_add($msg, MIDCOM_LOG_ERROR);
            echo "ERROR: {$msg}\n";
            return false;
        }

        return true;
    }

    /**
     * This is the main cron handler function.
     */
    function execute()
    {
        $this->_load_jobs();

        foreach ($this->_jobs as $job)
        {
            $this->_execute_job($job);
        }
    }

    /**
     * Executes the given job.
     *
     * @param Array $job The job to execute.
     */
    function _execute_job($job)
    {
        debug_print_r('Executing job:', $job);

        $handler = new $job['handler']();
        if (! $handler)
        {
            $msg = "Failed to execute a job for {$job['component']}: Could not create handler class instance.";
            debug_add($msg, MIDCOM_LOG_ERROR);
            echo "ERROR: {$msg}\n";
            return false;
        }
        if (! $handler->initialize($job))
        {
            $msg = "Failed to execute a job for {$job['component']}: Handler class failed to initialize.";
            debug_add($msg, MIDCOM_LOG_WARN);
        }
        $handler->execute();
    }
}
?>