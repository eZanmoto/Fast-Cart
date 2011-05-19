<?php

/**
 * A shorthand way of calling debugger::soft_breakpoint().
 *
 * Usage: eval(SOFT_BP);
 *
 * @uses debugger::soft_breakpoint()
 * @since 0.1
 */
define( 'SOFT_BP',
        'global $debugger; $debugger->soft_breakpoint();' );

/**
 * A shorthand way of calling debugger::hard_breakpoint().
 *
 * Usage: eval(HARD_BP);
 *
 * @uses debugger::hard_breakpoint()
 * @since 0.1
 */
define( 'HARD_BP',
        'global $debugger; $debugger->hard_breakpoint();' );

/**
 * PHP Debugging Object
 *
 * Original code from {@link http://ezanmoto.wordpress.com Seán M.
 * Kelleher (ezanmoto@gmail.com)}
 *
 * @package eZanmoto
 * @subpackage Debugging
 * @since 0.1
 */
class debugger {
    
    /**
     * Most functions in the debugger class won't run if this variable
     * is set to false.
     *
     * Check the comments before a function to see if that function
     * uses this variable. If it does, chances are it won't execute if
     * this variable is set to false.
     *
     * @since 0.1
     * @access private
     * @var bool
     */
    var $debugging;

    /**
     * An indexed array of alerts to be output using
     * debugger::show_alerts().
     * 
     * @since 0.1
     * @access private
     * @var array
     */
    var $alerts;

    /**
     * An associative array that contains formatting information for
     * alerts.
     *
     * Contains two elements: 'before' and 'after', whose contents are
     * output before and after an alert. They are initialized with the
     * values '<h6>Alert: ' and '</h6>' respectively.
     *
     * @since 0.1
     * @access private
     * @var array
     */
    var $alert_format;

    /**
     * An indexed array of alerts to be output using
     * debugger::show_errors().
     * 
     * @since 0.1
     * @access private
     * @var array
     */
    var $errors;

    /**
     * An associative array that contains formatting information for
     * errors.
     *
     * Contains two elements: 'before' and 'after', whose contents are
     * output before and after an error. They are initialized with the
     * values '<h6>Error: ' and '</h6>' respectively.
     *
     * @since 0.1
     * @access private
     * @var array
     */
    var $error_format;

    /**
     * Used for debugger::count_calls() to keep track of how many
     * times a function called it.
     *
     * @since 0.1
     * @access private
     * @var array
     */
    var $call_count;

    /**
     * The number of times debugger::bookmark() has been called.
     *
     * @since 0.1
     * @access private
     * @var array
     */
    var $bookmark_count;

    /**
     * Initializes the various class variables and defines the
     * output() global function.
     *
     * @uses debugger::$debugging
     * @since 0.1
     * @access public
	 *
	 * @param bool $debugging Sets whether the debugger is on or not.
     */
    function __construct($debugging) {
        $this->debugging = $debugging ? true : false;
        $this->alerts = array();
        $this->alert_format = array('before' => '<h6>Alert: ',
                                    'after' => '</h6>');
        $this->errors = array();
        $this->error_format = array('before' => '<h6>Error: ',
                                    'after' => '</h6>');

        $this->call_count = array();
        $this->bookmark_count = 0;

        /*
         * Just so any other functions with the same name aren't
         * overwritten. output() is declared in the global scope so
         * that it can be accessed easily while debugging. When the
         * debugger is turned off, output functions like a regular
         * echo statement.
         */
        if (!function_exists('output')) {

            if ($this->debugging) {
                // See definition of output, below
                function output($string) {
                    global $debugger;

                    $caller = $debugger->get_caller(2);
                    echo "<span title=\"{$caller}\">{$string}</span>";
                }

                // See definition of bookmark, below
                function bookmark() {
                    global $debugger;

                    $debugger->bookmark_count++;
                    $caller = $debugger->get_caller(2);
                    echo "<span title=\"{$caller}\">Bookmark
                         {$debugger->bookmark_count}</span><br />";
                }
            } else {

                /*
                 * This definition is so that output functions like a
                 * regular echo statement when the debugger is turned
                 * off.
                 */
                function output($string) {
                    echo $string;
                }

                /*
                 * This definition is so that bookmark does nothing
                 * when the debugger is turned off.
                 */
                function bookmark() {
                }
            }

        }
    }

    /**
     * Turns the debugger on.
     *
     * @uses debugger::$debugging
     * @since 0.1
     * @access public
     */
    function on() {
        $this->debugging = true;
    }

    /**
     * Turns the debugger off.
     *
     * @uses debugger::$debugging
     * @since 0.1
     * @access public
     */
    function off() {
        $this->debugging = false;
    }

    /**
     * Outputs the contents of the $_POST superglobal.
     *
     * @uses debugger::$debugging
     * @since 0.1
     * @access public
     */
    function dump_post() {
        if ($this->debugging) {
            $caller = $this->get_caller();
            foreach ($_POST as $key => $var) {
                echo "<span title=\"{$caller}\">";
                echo "\$_POST['{$key}'] => '{$var}'";
                echo '</span><br />';
            }
        }
    }

    /**
     * Sets debugger::$alert_format.
     *
     * @uses debugger::$alert_format
     * @since 0.1
     * @access public
     *
     * @param string $before debugger::$alert_format['before'] is set
     *  to this value.
     * @param string $after debugger::$alert_format['after'] is set
     *  to this value.
     */
    function set_alert_format($before, $after) {
        if ($before) {
            $this->alert_format['before'] = $before;
        }

        if ($after) {
            $this->alert_format['after'] = $after;
        }
    }

    /**
     * Outputs a formatted alert.
     *
     * @uses debugger::$debugging
     * @uses debugger::$alert_format
     * @since 0.1
     * @access public
     *
     * @param string $alert The alert to be output.
     */
    function alert($alert) {
        if ($this->debugging) {
            echo $this->alert_format['before'];
            echo $alert;
            echo $this->alert_format['after'];
        }
    }

    /**
     * Adds an alert to the list of alerts.
     *
     * @uses debugger::$alerts
     * @since 0.1
     * @access public
     */
    function add_alert($alert) {
        $this->alerts[] = $alert;
    }

    /**
     * Outputs the contents of debugger::$alerts.
     *
     * @uses debugger::$debugging
     * @uses debugger::$alerts
     * @uses debugger::alert()
     * @since 0.1
     * @access public
     *
     * @param bool $empty_alerts Reset the debugger::$alerts array if
     *  this is true.
     */
    function show_alerts($empty_alerts=false) {
        if ($this->debugging) {
            foreach ($this->alerts as $alert) {
                $this->alert($alert);
            }
        }

        if ($empty_alerts) {
            $this->alerts = array();
        }
    }

    /**
     * Forcefully clear the {@code debugger::$alerts} array.
     *
     * @uses debugger::$alerts
     * @since 0.1
     * @access public
     */
    function clear_alerts() {
        $this->alerts= array();
    }

    /**
     * Sets debugger::$error_format.
     *
     * @uses debugger::$error_format
     * @since 0.1
     * @access public
     *
     * @param string $before debugger::$alert_format['before'] is set
     *  to this value.
     * @param string $after debugger::$alert_format['after'] is set
     *  to this value.
     */
    function set_error_format($before, $after) {
        if ($before) {
            $this->error_format['before'] = $before;
        }

        if ($after) {
            $this->error_format['after'] = $after;
        }
    }

    /**
     * Outputs a formatted error.
     *
     * @uses debugger::$debugging
     * @uses debugger::$error_format
     * @since 0.1
     * @access public
     *
     * @param string $error The error to be output.
     */
    function error($error) {
        if ($this->debugging) {
            echo '<strong>';
            echo $this->error_format['before'];
            echo $this->get_caller();
            echo ":</strong> {$error}";
            echo $this->error_format['after'];
        }
    }

    /**
     * Adds an error to the list of errors.
     *
     * @uses debugger::$errors
     * @since 0.1
     * @access public
     */
    function add_error($error) {
        $this->errors[] = array('caller' => $this->get_caller(),
                                'detail' => $error);
    }

    /**
     * Outputs the contents of debugger::errors.
     *
     * @uses debugger::$debugging
     * @uses debugger::$errors
     * @uses debugger::error()
     * @since 0.1
     * @access public
     *
     * @param bool $empty_error Reset the debugger::$error array if
     *  this is true.
     */
    function show_errors($empty_errors) {
        if ($this->debugging) {
            foreach ($this->errors as $error) {
                echo "<strong>{$this->error_format['before']}"
                     . "{$error['caller']}:</strong> "
                     . $error['detail']
                     . $this->error_format['after'];
            }
        }

        if ($empty_errors) {
            $this->errors = array();
        }
    }

    /**
     * Forcefully clear the {@code debugger::$errors} array.
     *
     * @uses debugger::$errors
     * @since 0.1
     * @access public
     */
    function clear_errors() {
        $this->errors = array();
    }

    /**
     * Outputs a fatal error, a function trace, and exits.
     *
     * @uses debugger::$debugging
     * @uses debugger::function_trace()
     * @since 0.1
     * @access public
     *
     * @param string $error The fatal error to be output.
     */
    function fatal($error) {
        $trace = debug_backtrace();
        echo '<h4>Fatal Error:</h4>';
        echo '<strong>';
        echo $this->get_caller();
        echo ":</strong> {$error}<br /><br />";
        echo '<div>';
        echo $this->function_trace();
        echo '</div>';
        exit();
    }

    /**
     * Outputs a function trace.
     *
     * @uses debugger::$debugging
     * @uses debugger::function_trace()
     * @since 0.1
     * @access public
     */
    function soft_breakpoint() {
        echo 'Hit soft breakpoint<br />';
        echo $this->function_trace();
        echo '<br />';
    }

    /**
     * Outputs a function trace and exits.
     *
     * @uses debugger::$debugging
     * @uses debugger::function_trace()
     * @since 0.1
     * @access public
     */
    function hard_breakpoint() {
        echo 'Hit hard breakpoint<br />';
        echo $this->function_trace();
        echo '<br />';
        exit();
    }

    /**
     * Returns the name, class and line of the function that called
     * the function that called this function.
     *
     * Example usage:
     *  get_caller( 3 ) // returns the caller of the caller
     *  get_caller( 4 ) // returns the caller of the caller of the
     *   caller
     *
     * @since 0.1
     * @access public
     *
     * @param int $levels How many callers back you want to get
     *  information on.
     * @return string A line with details on the calling function.
     */
    function get_caller($level=2) {
        $trace = debug_backtrace();

        return "({$trace[$level]['class']}::"
               . "{$trace[$level]['function']}) line "
               . "{$trace[--$level]['line']}";
    }

    /**
     * Returns a trace of the calling functions.
     *
     * @since 0.1
     * @access public
     *
     * @return string Lines with details on the calling functions.
     */
    function function_trace($verbose=false) {
        $return = '';
        $trace = debug_backtrace();
        $levels = count( $trace );

        for ($level = 0; $level < $levels; $level++) {
            $result .= "<strong>{$trace[$level]['file']}, line "
                       . "{$trace[$level]['line']}</strong>"
                       . "({$trace[$level]['class']}::"
                       . "{$trace[$level]['function']})<br />";
        }

        return $result;
    }

    /**
     * Functions like echo, but text will output debugging information
     * on mouseover.
     *
     * On mouseover, a label will appear over the output text with
     * information on the function that called this function.
     *
     * @uses debugger::$debugging
     * @uses debugger::get_caller()
     * @since 0.1
     * @access public
     *
     * @param string $string The string to echo.
     */
    function output($string) {
        if ($this->debugging) {
            $caller = $this->get_caller();
            echo "<span title=\"{$caller}\">{$string}</span>";
        }
    }

    /**
     * Outputs the number of times a certain line has been passed.
     *
     * @uses debugger::$debugging
     * @uses debugger::$call_count
     * @uses debugger::get_caller()
     */
    function count_calls() {
        if ($this->debugging) {
            $caller = $this->get_caller();
            $initial = 1;
            $times = $initial;
            if (isset ($this->call_count[$caller])) {
                $this->call_count[$caller]++;
                $times = $this->call_count[$caller];
            } else {
                $this->call_count[$caller] = $initial;
            }
            echo "<strong>{$caller}</strong>: Called {$times} times";
        }
    }

    /**
     * Outputs a bookmark for getting back to a line of code.
     *
     * @uses debugger::$debugging
     * @uses debugger::$bookmark_count
     * @uses debugger::get_caller()
     */
    function bookmark() {
        if ($this->debugging) {
            $this->bookmark_count++;
            $caller = $this->get_caller();
            echo "<span title=\"{$caller}\">Bookmark
                 {$this->bookmark_count}</span><br />";
        }
    }
}

?>
