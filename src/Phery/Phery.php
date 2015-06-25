<?php
/**
 * The MIT License (MIT)
 *
 * Copyright © 2010-2013 Paulo Cesar, http://phery-php-ajax.net/
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the “Software”), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge,
 * publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * @link       http://phery-php-ajax.net/
 * @author     Paulo Cesar
 * @version    2.7.2
 * @license    http://opensource.org/licenses/MIT MIT License
 */

namespace Phery;

use ArrayAccess;

/**
 * Main class for Phery.js
 *
 * @package    Phery
 */
class Phery implements ArrayAccess
{

    /**
     * Exception on callback() function
     *
     * @see callback()
     * @type int
     */
    const ERROR_CALLBACK = 0;

    /**
     * Exception on process() function
     *
     * @see process()
     */
    const ERROR_PROCESS = 1;

    /**
     * Exception on set() function
     *
     * @see set()
     */
    const ERROR_SET = 2;

    /**
     * Exception when the CSRF is invalid
     *
     * @see process()
     */
    const ERROR_CSRF = 4;

    /**
     * Exception on static functions
     *
     * @see link_to()
     * @see select_for()
     * @see form_for()
     */
    const ERROR_TO = 3;

    /**
     * Default encoding for your application
     *
     * @var string
     */
    public static $encoding = 'UTF-8';

    /**
     * Expose the paths on PheryResponse exceptions
     *
     * @var bool
     */
    public static $expose_paths = false;

    /**
     * The functions registered
     *
     * @var array
     */
    protected $functions = array();

    /**
     * The callbacks registered
     *
     * @var array
     */
    protected $callbacks = array();

    /**
     * The callback data to be passed to callbacks and responses
     * @var array
     */
    protected $data = array();

    /**
     * Static instance for singleton
     *
     * @var Phery
     * @static
     */
    protected static $instance = null;

    /**
     * Render view function
     *
     * @var array
     */
    protected $views = array();

    /**
     * Config
     *
     * <code>
     * 'exit_allowed' (boolean)
     * 'exceptions' (boolean)
     * 'return' (boolean)
     * 'error_reporting' (int)
     * 'csrf' (boolean)
     * 'set_always_available' (boolean)
     * 'auto_session' (boolean)
     * </code>
     * @var array
     *
     * @see config()
     */
    protected $config = array();

    /**
     * If the class was just initiated
     *
     * @var bool
     */
    private $init = true;

    /**
     * Construct the new Phery instance
     *
     * @param array $config Config array
     */
    public function __construct(array $config = array())
    {
        $this->callbacks = array(
            'before' => array(),
            'after' => array()
        );

        $config = array_replace(
            array(
                'exit_allowed' => true,
                'exceptions' => false,
                'return' => false,
                'csrf' => false,
                'set_always_available' => false,
                'error_reporting' => false,
                'auto_session' => true,
            ), $config
        );

        $this->config($config);
    }

    /**
     * Set callbacks for before and after filters.
     * Callbacks are useful for example, if you have 2 or more AJAX functions, and you need to perform
     * the same data manipulation, like removing an 'id' from the $_POST['args'], or to check for potential
     * CSRF or SQL injection attempts on all the functions, clean data or perform START TRANSACTION for database, etc
     *
     * @param array $callbacks The callbacks
     *
     * <pre>
     * array(
     *
     *     // Set a function to be called BEFORE
     *     // processing the request, if it's an
     *     // AJAX to be processed request, can be
     *     // an array of callbacks
     *
     *     'before' => array|function,
     *
     *     // Set a function to be called AFTER
     *     // processing the request, if it's an AJAX
     *     // processed request, can be an array of
     *     // callbacks
     *
     *     'after' => array|function
     * );
     * </pre>
     *
     * The callback function should be
     *
     * <pre>
     *
     * // $additional_args is passed using the callback_data() function,
     * // in this case, a before callback
     *
     * function before_callback($ajax_data, $internal_data){
     *   // Do stuff
     *   $_POST['args']['id'] = $additional_args['id'];
     *   return true;
     * }
     *
     * // after callback would be to save the data perhaps? Just to keep the code D.R.Y.
     *
     * function after_callback($ajax_data, $internal_data, $PheryResponse){
     *   $this->database->save();
     *   $PheryResponse->merge(PheryResponse::factory('#loading')->fadeOut());
     *   return true;
     * }
     * </pre>
     *
     * Returning false on the callback will make the process() phase to RETURN, but won't exit.
     * You may manually exit on the after callback if desired
     * Any data that should be modified will be inside $_POST['args'] (can be accessed freely on 'before',
     * will be passed to the AJAX function)
     *
     * @return Phery
     */
    public function callback(array $callbacks)
    {
        if (isset($callbacks['before'])) {
            if (is_array($callbacks['before']) && !is_callable($callbacks['before'])) {
                foreach ($callbacks['before'] as $func) {
                    if (is_callable($func)) {
                        $this->callbacks['before'][] = $func;
                    } else {
                        self::exception($this, "The provided before callback function isn't callable", self::ERROR_CALLBACK);
                    }
                }
            } else {
                if (is_callable($callbacks['before'])) {
                    $this->callbacks['before'][] = $callbacks['before'];
                } else {
                    self::exception($this, "The provided before callback function isn't callable", self::ERROR_CALLBACK);
                }
            }
        }

        if (isset($callbacks['after'])) {
            if (is_array($callbacks['after']) && !is_callable($callbacks['after'])) {

                foreach ($callbacks['after'] as $func) {
                    if (is_callable($func)) {
                        $this->callbacks['after'][] = $func;
                    } else {
                        self::exception($this, "The provided after callback function isn't callable", self::ERROR_CALLBACK);
                    }
                }
            } else {
                if (is_callable($callbacks['after'])) {
                    $this->callbacks['after'][] = $callbacks['after'];
                } else {
                    self::exception($this, "The provided after callback function isn't callable", self::ERROR_CALLBACK);
                }
            }
        }

        return $this;
    }

    /**
     * Throw an exception if enabled
     *
     * @param Phery $phery Instance
     * @param string $exception
     * @param integer $code
     *
     * @throws PheryException
     * @return boolean
     */
    protected static function exception($phery, $exception, $code)
    {
        if ($phery instanceof Phery && $phery->config['exceptions'] === true) {
            throw new PheryException($exception, $code);
        }

        return false;
    }


    /**
     * Set any data to pass to the callbacks
     *
     * @param mixed $args,... Parameters, can be anything
     *
     * @return Phery
     */
    public function data($args)
    {
        foreach (func_get_args() as $arg) {
            if (is_array($arg)) {
                $this->data = array_merge_recursive($arg, $this->data);
            } else {
                $this->data[] = $arg;
            }
        }

        return $this;
    }

    /**
     * Encode PHP code to put inside data-phery-args, usually for updating the data there
     *
     * @param array $data Any data that can be converted using json_encode
     * @param string $encoding Encoding for the arguments
     *
     * @return string Return json_encode'd and htmlentities'd string
     */
    public static function args(array $data, $encoding = 'UTF-8')
    {
        return htmlentities(json_encode($data), ENT_COMPAT, $encoding, false);
    }

    /**
     * Get the current token from the $_SESSION
     *
     * @return bool
     */
    public function get_csrf_token()
    {
        if (!empty($_SESSION['phery']['csrf'])) {
            return $_SESSION['phery']['csrf'];
        }

        return false;
    }

    /**
     * Output the meta HTML with the token.
     * This method needs to use sessions through session_start
     *
     * @param bool $check Check if the current token is valid
     * @param bool $force It will renew the current hash every call
     * @return string|bool
     */
    public function csrf($check = false, $force = false)
    {
        if ($this->config['csrf'] !== true) {
            return !empty($check) ? true : '';
        }

        if (session_id() == '' && $this->config['auto_session'] === true) {
            @session_start();
        }

        if ($check === false) {
            $current_token = $this->get_csrf_token();

            if (($current_token !== false && $force) || $current_token === false) {
                $token = sha1(uniqid(microtime(true), true));

                $_SESSION['phery'] = array(
                    'csrf' => $token
                );

                $token = base64_encode($token);
            } else {
                $token = base64_encode($_SESSION['phery']['csrf']);
            }

            return "<meta id=\"csrf-token\" name=\"csrf-token\" content=\"{$token}\" />\n";
        } else {
            if (empty($_SESSION['phery']['csrf'])) {
                return false;
            }

            return $_SESSION['phery']['csrf'] === base64_decode($check, true);
        }
    }

    /**
     * Check if the current call is an ajax call
     *
     * @param bool $is_phery Check if is an ajax call and a phery specific call
     *
     * @static
     * @return bool
     */
    public static function is_ajax($is_phery = false)
    {
        switch ($is_phery) {
            case true:
                return (bool)(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0 &&
                    strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
                    !empty($_SERVER['HTTP_X_PHERY']));
            case false:
                return (bool)(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0);
        }
        return false;
    }

    /**
     * Strip slashes recursive
     *
     * @param array|string $variable
     * @return array|string
     */
    private function stripslashes_recursive($variable)
    {
        if (!empty($variable) && is_string($variable)) {
            return stripslashes($variable);
        }

        if (!empty($variable) && is_array($variable)) {
            foreach ($variable as $i => $value) {
                $variable[$i] = $this->stripslashes_recursive($value);
            }
        }

        return $variable;
    }

    /**
     * Flush loop
     *
     * @param bool $clean Discard buffers
     */
    private static function flush($clean = false)
    {
        while (ob_get_level() > 0) {
            $clean ? ob_end_clean() : ob_end_flush();
        }
    }

    /**
     * Default error handler
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    public static function error_handler($errno, $errstr, $errfile, $errline)
    {
        self::flush(true);

        $response = PheryResponse::factory()->exception($errstr, array(
            'code' => $errno,
            'file' => Phery::$expose_paths ? $errfile : pathinfo($errfile, PATHINFO_BASENAME),
            'line' => $errline
        ));

        self::respond($response);
        self::shutdown_handler(false, true);
    }

    /**
     * Default shutdown handler
     *
     * @param bool $errors
     * @param bool $handled
     */
    public static function shutdown_handler($errors = false, $handled = false)
    {
        if ($handled) {
            self::flush();
        }

        if ($errors === true && ($error = error_get_last()) && !$handled) {
            self::error_handler($error["type"], $error["message"], $error["file"], $error["line"]);
        }

        if (!$handled) {
            self::flush();
        }

        if (session_id() != '') {
            session_write_close();
        }

        exit;
    }

    /**
     * Helper function to properly output the headers for a PheryResponse in case you need
     * to manually return it (like when following a redirect)
     *
     * @param string|PheryResponse $response The response or a string
     * @param bool $echo Echo the response
     *
     * @return string
     */
    public static function respond($response, $echo = true)
    {
        if ($response instanceof PheryResponse) {
            if (!headers_sent()) {
                if (session_id() != '') {
                    session_write_close();
                }

                header('Cache-Control: no-cache, must-revalidate', true);
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT', true);
                header('Content-Type: application/json; charset=' . (strtolower(Phery::$encoding)), true);
                header('Connection: close', true);
            }
        }

        if ($response) {
            $response = "{$response}";
        }

        if ($echo === true) {
            echo $response;
        }

        return $response;
    }

    /**
     * Set the callback for view portions, as defined in Phery.view()
     *
     * @param array $views Array consisting of array('#id_of_view' => callback)
     *                     The callback is like a normal phery callback, but the second parameter
     *                     receives different data. But it MUST always return a PheryResponse with
     *                     render_view(). You can do any manipulation like you would in regular
     *                     callbacks. If you want to manipulate the DOM AFTER it was rendered, do it
     *                     javascript side, using the afterHtml callback when setting up the views.
     *
     * <pre>
     * Phery::instance()->views(array(
     *     'section#container' => function($data, $params){
     *          return
     *              PheryResponse::factory()
     *              ->render_view('html', array('extra data like titles, menus, etc'));
     *      }
     * ));
     * </pre>
     *
     * @return Phery
     */
    public function views(array $views)
    {
        foreach ($views as $container => $callback) {
            if (is_callable($callback)) {
                $this->views[$container] = $callback;
            }
        }

        return $this;
    }

    /**
     * Initialize stuff before calling the AJAX function
     *
     * @return void
     */
    protected function before_user_func()
    {
        if ($this->config['error_reporting'] !== false) {
            set_error_handler('Phery::error_handler', $this->config['error_reporting']);
        }

        if (empty($_POST['phery']['csrf'])) {
            $_POST['phery']['csrf'] = '';
        }

        if ($this->csrf($_POST['phery']['csrf']) === false) {
            self::exception($this, 'Invalid CSRF token', self::ERROR_CSRF);
        }
    }

    /**
     * Process the requests if any
     *
     * @param boolean $last_call
     *
     * @return boolean
     */
    private function process_data($last_call)
    {
        $response = null;
        $error = null;
        $view = false;

        if (empty($_POST['phery'])) {
            return self::exception($this, 'Non-Phery AJAX request', self::ERROR_PROCESS);
        }

        if (!empty($_GET['_'])) {
            $this->data['requested'] = (int)$_GET['_'];
            unset($_GET['_']);
        }

        if (isset($_GET['_try_count'])) {
            $this->data['retries'] = (int)$_GET['_try_count'];
            unset($_GET['_try_count']);
        }

        $args = array();
        $remote = false;

        if (!empty($_POST['phery']['remote'])) {
            $remote = $_POST['phery']['remote'];
        }

        if (!empty($_POST['phery']['submit_id'])) {
            $this->data['submit_id'] = "#{$_POST['phery']['submit_id']}";
        }

        if ($remote !== false) {
            $this->data['remote'] = $remote;
        }

        if (!empty($_POST['args'])) {
            $args = get_magic_quotes_gpc() ? $this->stripslashes_recursive($_POST['args']) : $_POST['args'];

            if ($last_call === true) {
                unset($_POST['args']);
            }
        }

        foreach ($_POST['phery'] as $name => $post) {
            if (!isset($this->data[$name])) {
                $this->data[$name] = $post;
            }
        }

        if (count($this->callbacks['before'])) {
            foreach ($this->callbacks['before'] as $func) {
                if (($args = call_user_func($func, $args, $this->data, $this)) === false) {
                    return false;
                }
            }
        }

        if (!empty($_POST['phery']['view'])) {
            $this->data['view'] = $_POST['phery']['view'];
        }

        if ($remote !== false) {
            if (isset($this->functions[$remote])) {
                if (isset($_POST['phery']['remote'])) {
                    unset($_POST['phery']['remote']);
                }

                $this->before_user_func();

                $response = call_user_func($this->functions[$remote], $args, $this->data, $this);

                foreach ($this->callbacks['after'] as $func) {
                    if (call_user_func($func, $args, $this->data, $response, $this) === false) {
                        return false;
                    }
                }

                if (($response = self::respond($response, false)) === null) {
                    $error = 'Response was void for function "' . htmlentities($remote, ENT_COMPAT, null, false) . '"';
                }

                $_POST['phery']['remote'] = $remote;
            } else {
                if ($last_call) {
                    self::exception($this, 'The function provided "' . htmlentities($remote, ENT_COMPAT, null, false) . '" isn\'t set', self::ERROR_PROCESS);
                }
            }
        } else {
            if (!empty($this->data['view']) && isset($this->views[$this->data['view']])) {
                $view = $this->data['view'];

                $this->before_user_func();

                $response = call_user_func($this->views[$this->data['view']], $args, $this->data, $this);

                foreach ($this->callbacks['after'] as $func) {
                    if (call_user_func($func, $args, $this->data, $response, $this) === false) {
                        return false;
                    }
                }

                if (($response = self::respond($response, false)) === null) {
                    $error = 'Response was void for view "' . htmlentities($this->data['view'], ENT_COMPAT, null, false) . '"';
                }
            } else {
                if ($last_call) {
                    if (!empty($this->data['view'])) {
                        self::exception($this, 'The provided view "' . htmlentities($this->data['view'], ENT_COMPAT, null, false) . '" isn\'t set', self::ERROR_PROCESS);
                    } else {
                        self::exception($this, 'Empty request', self::ERROR_PROCESS);
                    }
                }
            }
        }

        if ($error !== null) {
            self::error_handler(E_NOTICE, $error, '', 0);
        } elseif ($response === null && $last_call & !$view) {
            $response = PheryResponse::factory();
        } elseif ($response !== null) {
            ob_start();

            if (!$this->config['return']) {
                echo $response;
            }
        }

        if (!$this->config['return'] && $this->config['exit_allowed'] === true) {
            if ($last_call || $response !== null) {
                exit;
            }
        } elseif ($this->config['return']) {
            self::flush(true);
        }

        if ($this->config['error_reporting'] !== false) {
            restore_error_handler();
        }

        return $response;
    }

    /**
     * Process the AJAX requests if any.
     *
     * @param bool $last_call Set this to false if any other further calls
     *                        to process() will happen, otherwise it will exit
     *
     * @throws PheryException
     * @return boolean Return false if any error happened
     */
    public function process($last_call = true)
    {
        if (self::is_ajax(true)) {
            // AJAX call
            return $this->process_data($last_call);
        }
        return true;
    }

    /**
     * Config the current instance of Phery
     *
     * <code>
     * array(
     *     // Defaults to true, stop further script execution
     *     'exit_allowed' => true|false,
     *
     *     // Throw exceptions on errors
     *     'exceptions' => true|false,
     *
     *     // Return the responses in the process() call instead of echo'ing
     *     'return' => true|false,
     *
     *     // Error reporting temporarily using error_reporting(). 'false' disables
     *     // the error_reporting and wont try to catch any error.
     *     // Anything else than false will throw a PheryResponse->exception() with
     *     // the message
     *     'error_reporting' => false|E_ALL|E_DEPRECATED|...
     *
     *     // By default, the function Phery::instance()->set() will only
     *     // register functions when the current request is an AJAX call,
     *     // to save resources. In order to use Phery::instance()->get_function()
     *     // anytime, you need to set this config value to true
     *     'set_always_available' => false|true
     * );
     * </code>
     *
     * If you pass a string, it will return the current config for the key specified
     * Anything else, will output the current config as associative array
     *
     * @param string|array $config Associative array containing the following options
     *
     * @return Phery|string|array
     */
    public function config($config = null)
    {
        $register_function = false;

        if (!empty($config)) {
            if (is_array($config)) {
                if (isset($config['exit_allowed'])) {
                    $this->config['exit_allowed'] = (bool)$config['exit_allowed'];
                }

                if (isset($config['auto_session'])) {
                    $this->config['auto_session'] = (bool)$config['auto_session'];
                }

                if (isset($config['return'])) {
                    $this->config['return'] = (bool)$config['return'];
                }

                if (isset($config['set_always_available'])) {
                    $this->config['set_always_available'] = (bool)$config['set_always_available'];
                }

                if (isset($config['exceptions'])) {
                    $this->config['exceptions'] = (bool)$config['exceptions'];
                }

                if (isset($config['csrf'])) {
                    $this->config['csrf'] = (bool)$config['csrf'];
                }

                if (isset($config['error_reporting'])) {
                    if ($config['error_reporting'] !== false) {
                        $this->config['error_reporting'] = (int)$config['error_reporting'];
                    } else {
                        $this->config['error_reporting'] = false;
                    }

                    $register_function = true;
                }

                if ($register_function || $this->init) {
                    register_shutdown_function('Phery::shutdown_handler', $this->config['error_reporting'] !== false);
                    $this->init = false;
                }

                return $this;
            } elseif (!empty($config) && is_string($config) && isset($this->config[$config])) {
                return $this->config[$config];
            }
        }

        return $this->config;
    }

    /**
     * Generates just one instance. Useful to use in many included files. Chainable
     *
     * @param array $config Associative config array
     *
     * @see __construct()
     * @see config()
     * @static
     * @return Phery
     */
    public static function instance(array $config = array())
    {
        if (!(self::$instance instanceof Phery)) {
            self::$instance = new Phery($config);
        } else if ($config) {
            self::$instance->config($config);
        }

        return self::$instance;
    }

    /**
     * Sets the functions to respond to the ajax call.
     * For security reasons, these functions should not be reacheable through POST/GET requests.
     * These will be set only for AJAX requests as it will only be set in case of an ajax request,
     * to save resources.
     *
     * You may set the config option "set_always_available" to true to always register the functions
     * regardless of if it's an AJAX function or not going on.
     *
     * The answer/process function, should have the following structure:
     *
     * <code>
     * function func($ajax_data, $callback_data, $phery){
     *   $r = new PheryResponse; // or PheryResponse::factory();
     *
     *   // Sometimes the $callback_data will have an item called 'submit_id',
     *   // is the ID of the calling DOM element.
     *   // if (isset($callback_data['submit_id'])) {  }
     *   // $phery will be the current phery instance that called this callback
     *
     *   $r->jquery('#id')->animate(...);
     *   return $r; //Should always return the PheryResponse unless you are dealing with plain text
     * }
     * </code>
     *
     * @param array $functions An array of functions to register to the instance.
     * <pre>
     * array(
     *   'function1' => 'function',
     *   'function2' => array($this, 'method'),
     *   'function3' => 'StaticClass::name',
     *   'function4' => array(new ClassName, 'method'),
     *   'function5' => function($data){}
     * );
     * </pre>
     * @return Phery
     */
    public function set(array $functions)
    {
        if ($this->config['set_always_available'] === false && !self::is_ajax(true)) {
            return $this;
        }

        if (isset($functions) && is_array($functions)) {
            foreach ($functions as $name => $func) {
                if (is_callable($func)) {
                    $this->functions[$name] = $func;
                } else {
                    self::exception($this, 'Provided function "' . $name . '" isnt a valid function or method', self::ERROR_SET);
                }
            }
        } else {
            self::exception($this, 'Call to "set" must be provided an array', self::ERROR_SET);
        }

        return $this;
    }

    /**
     * Unset a function previously set with set()
     *
     * @param string $name Name of the function
     * @see set()
     * @return Phery
     */
    public function unset_function($name)
    {
        if (isset($this->functions[$name])) {
            unset($this->functions[$name]);
        }
        return $this;
    }

    /**
     * Get previously function set with set() method
     * If you pass aditional arguments, the function will be executed
     * and this function will return the PheryResponse associated with
     * that function
     *
     * <pre>
     * Phery::get_function('render', ['<html></html>'])->appendTo('body');
     * </pre>
     *
     * @param string $function_name The name of the function registed with set
     * @param array $args Any arguments to pass to the function
     * @see Phery::set()
     * @return callable|array|string|PheryResponse|null
     */
    public function get_function($function_name, array $args = array())
    {
        if (isset($this->functions[$function_name])) {
            if (count($args)) {
                return call_user_func_array($this->functions[$function_name], $args);
            }

            return $this->functions[$function_name];
        }
        return null;
    }

    /**
     * Create a new instance of Phery that can be chained, without the need of assigning it to a variable
     *
     * @param array $config Associative config array
     *
     * @see config()
     * @static
     * @return Phery
     */
    public static function factory(array $config = array())
    {
        return new Phery($config);
    }

    /**
     * Common check for all static factories
     *
     * @param array $attributes
     * @param bool $include_method
     *
     * @return string
     */
    protected static function common_check(&$attributes, $include_method = true)
    {
        if (!empty($attributes['args'])) {
            $attributes['data-phery-args'] = json_encode($attributes['args']);
            unset($attributes['args']);
        }

        if (!empty($attributes['confirm'])) {
            $attributes['data-phery-confirm'] = $attributes['confirm'];
            unset($attributes['confirm']);
        }

        if (!empty($attributes['cache'])) {
            $attributes['data-phery-cache'] = "1";
            unset($attributes['cache']);
        }

        if (!empty($attributes['target'])) {
            $attributes['data-phery-target'] = $attributes['target'];
            unset($attributes['target']);
        }

        if (!empty($attributes['related'])) {
            $attributes['data-phery-related'] = $attributes['related'];
            unset($attributes['related']);
        }

        if (!empty($attributes['phery-type'])) {
            $attributes['data-phery-type'] = $attributes['phery-type'];
            unset($attributes['phery-type']);
        }

        if (!empty($attributes['only'])) {
            $attributes['data-phery-only'] = $attributes['only'];
            unset($attributes['only']);
        }

        if (isset($attributes['clickable'])) {
            $attributes['data-phery-clickable'] = "1";
            unset($attributes['clickable']);
        }

        if ($include_method) {
            if (isset($attributes['method'])) {
                $attributes['data-phery-method'] = $attributes['method'];
                unset($attributes['method']);
            }
        }

        $encoding = 'UTF-8';
        if (isset($attributes['encoding'])) {
            $encoding = $attributes['encoding'];
            unset($attributes['encoding']);
        }

        return $encoding;
    }

    /**
     * Helper function that generates an ajax link, defaults to "A" tag
     *
     * @param string $content The content of the link. This is ignored for self closing tags, img, input, iframe
     * @param string $function The PHP function assigned name on Phery::set()
     * @param array $attributes Extra attributes that can be passed to the link, like class, style, etc
     * <pre>
     * array(
     *     // Display confirmation on click
     *     'confirm' => 'Are you sure?',
     *
     *     // The tag for the item, defaults to a. If the tag is set to img, the
     *     // 'src' must be set in attributes parameter
     *     'tag' => 'a',
     *
     *     // Define another URI for the AJAX call, this defines the HREF of A
     *     'href' => '/path/to/url',
     *
     *     // Extra arguments to pass to the AJAX function, will be stored
     *     // in the data-phery-args attribute as a JSON notation
     *     'args' => array(1, "a"),
     *
     *     // Set the "href" attribute for non-anchor (a) AJAX tags (like buttons or spans).
     *     // Works for A links too, but it won't function without javascript, through data-phery-target
     *     'target' => '/default/ajax/controller',
     *
     *     // Define the data-phery-type for the expected response, json, xml, text, etc
     *     'phery-type' => 'json',
     *
     *     // Enable clicking on structural HTML, like DIV, HEADER, HGROUP, etc
     *     'clickable' => true,
     *
     *     // Force cache of the response
     *     'cache' => true,
     *
     *     // Aggregate data from other DOM elements, can be forms, inputs (textarea, selects),
     *     // pass multiple selectors, like "#input1,#form1,~ input:hidden,select.job"
     *     // that are searched in this order:
     *     // - $(this).find(related)
     *     // - $(related)
     *     // So you can use sibling, children selectors, like ~, +, >, :parent
     *     // You can also, through Javascript, append a jQuery object to the related, using
     *     // $('#element').phery('data', 'related', $('#other_element'));
     *     'related' => true,
     *
     *     // Disables the AJAX on element while the last action is not completed
     *     'only' => true,
     *
     *     // Set the encoding of the data, defaults to UTF-8
     *     'encoding' => 'UTF-8',
     *
     *     // Set the method (for restful responses)
     *     'method' => 'PUT'
     * );
     * </pre>
     *
     * @param Phery $phery Pass the current instance of phery, so it can check if the
     *                           functions are defined, and throw exceptions
     * @param boolean $no_close Don't close the tag, useful if you want to create an AJAX DIV with a lot of content inside,
     *                           but the DIV itself isn't clikable
     *
     * <pre>
     *   <?php echo Phery::link_to('', 'remote', array('target' => '/another-url', 'args' => array('id' => 1), 'class' => 'ajaxified'), null, true); ?>
     *     <p>This new content</p>
     *     <div class="result></div>
     *   </div>
     *   <?php echo Phery::link_to('', 'remote', array('target' => '/another-url', 'args' => array('id' => 2), 'class' => 'ajaxified'), null, true); ?>
     *     <p>Another content will have div result filled</p>
     *     <div class="result></div>
     *   </div>
     *
     *   <script>
     *     $('.ajaxified').phery('remote');
     *   </script>
     * </pre>
     *
     * @static
     * @return string The mounted HTML tag
     */
    public static function link_to($content, $function, array $attributes = array(), Phery $phery = null, $no_close = false)
    {
        if ($phery && !isset($phery->functions[$function])) {
            self::exception($phery, 'The function "' . $function . '" provided in "link_to" hasnt been set', self::ERROR_TO);
        }

        $tag = 'a';
        if (isset($attributes['tag'])) {
            $tag = $attributes['tag'];
            unset($attributes['tag']);
        }

        $encoding = self::common_check($attributes);

        if ($function) {
            $attributes['data-phery-remote'] = $function;
        }

        $ret = array();
        $ret[] = "<{$tag}";
        foreach ($attributes as $attribute => $value) {
            $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, $encoding, false) . "\"";
        }

        if (!in_array(strtolower($tag), array('img', 'input', 'iframe', 'hr', 'area', 'embed', 'keygen'))) {
            $ret[] = ">{$content}";
            if (!$no_close) {
                $ret[] = "</{$tag}>";
            }
        } else {
            $ret[] = "/>";
        }

        return join(' ', $ret);
    }

    /**
     * Create a <form> tag with ajax enabled. Must be closed manually with </form>
     *
     * @param string $action where to go, can be empty
     * @param string $function Registered function name
     * @param array $attributes Configuration of the element plus any HTML attributes
     *
     * <pre>
     * array(
     *     //Confirmation dialog
     *     'confirm' => 'Are you sure?',
     *
     *     // Type of call, defaults to JSON (to use PheryResponse)
     *     'phery-type' => 'json',
     *
     *     // 'all' submits all elements on the form, even empty ones
     *     // 'disabled' enables submitting disabled elements
     *     'submit' => array('all' => true, 'disabled' => true),
     *
     *     // Disables the AJAX on element while the last action is not completed
     *     'only' => true,
     *
     *     // Set the encoding of the data, defaults to UTF-8
     *     'encoding' => 'UTF-8',
     * );
     * </pre>
     *
     * @param Phery $phery Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
     *
     * @static
     * @return string The mounted &lt;form&gt; HTML tag
     */
    public static function form_for($action, $function, array $attributes = array(), Phery $phery = null)
    {
        if (!$function) {
            self::exception($phery, 'The "function" argument must be provided to "form_for"', self::ERROR_TO);

            return '';
        }

        if ($phery && !isset($phery->functions[$function])) {
            self::exception($phery, 'The function "' . $function . '" provided in "form_for" hasnt been set', self::ERROR_TO);
        }

        $encoding = self::common_check($attributes, false);

        if (isset($attributes['submit'])) {
            $attributes['data-phery-submit'] = json_encode($attributes['submit']);
            unset($attributes['submit']);
        }

        $ret = array();
        $ret[] = '<form method="POST" action="' . $action . '" data-phery-remote="' . $function . '"';
        foreach ($attributes as $attribute => $value) {
            $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, $encoding, false) . "\"";
        }
        $ret[] = '>';

        return join(' ', $ret);
    }

    /**
     * Create a <select> element with ajax enabled "onchange" event.
     *
     * @param string $function Registered function name
     * @param array $items Options for the select, 'value' => 'text' representation
     * @param array $attributes Configuration of the element plus any HTML attributes
     *
     * <pre>
     * array(
     *     // Confirmation dialog
     *     'confirm' => 'Are you sure?',
     *
     *     // Type of call, defaults to JSON (to use PheryResponse)
     *     'phery-type' => 'json',
     *
     *     // The URL where it should call, translates to data-phery-target
     *     'target' => '/path/to/php',
     *
     *     // Extra arguments to pass to the AJAX function, will be stored
     *     // in the args attribute as a JSON notation, translates to data-phery-args
     *     'args' => array(1, "a"),
     *
     *     // Set the encoding of the data, defaults to UTF-8
     *     'encoding' => 'UTF-8',
     *
     *     // Disables the AJAX on element while the last action is not completed
     *     'only' => true,
     *
     *     // The current selected value, or array(1,2) for multiple
     *     'selected' => 1
     *
     *     // Set the method (for restful responses)
     *     'method' => 'PUT'
     * );
     * </pre>
     *
     * @param Phery $phery Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
     *
     * @static
     * @return string The mounted &lt;select&gt; with &lt;option&gt;s inside
     */
    public static function select_for($function, array $items, array $attributes = array(), Phery $phery = null)
    {
        if ($phery && !isset($phery->functions[$function])) {
            self::exception($phery, 'The function "' . $function . '" provided in "select_for" hasnt been set', self::ERROR_TO);
        }

        $encoding = self::common_check($attributes);

        $selected = array();
        if (isset($attributes['selected'])) {
            if (is_array($attributes['selected'])) {
                // multiple select
                $selected = $attributes['selected'];
            } else {
                // single select
                $selected = array($attributes['selected']);
            }
            unset($attributes['selected']);
        }

        if (isset($attributes['multiple'])) {
            $attributes['multiple'] = 'multiple';
        }

        $ret = array();
        $ret[] = '<select ' . ($function ? 'data-phery-remote="' . $function . '"' : '');
        foreach ($attributes as $attribute => $value) {
            $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, $encoding, false) . "\"";
        }
        $ret[] = '>';

        foreach ($items as $value => $text) {
            $_value = 'value="' . htmlentities($value, ENT_COMPAT, $encoding, false) . '"';
            if (in_array($value, $selected)) {
                $_value .= ' selected="selected"';
            }
            $ret[] = "<option " . ($_value) . ">{$text}</option>\n";
        }
        $ret[] = '</select>';

        return join(' ', $ret);
    }

    /**
     * OffsetExists
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * OffsetUnset
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        if (isset($this->data[$offset])) {
            unset($this->data[$offset]);
        }
    }

    /**
     * OffsetGet
     *
     * @param mixed $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        return null;
    }

    /**
     * offsetSet
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Set shared data
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Get shared data
     *
     * @param string $name

     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return null;
    }

    /**
     * Utility function taken from MYSQL.
     * To not raise any E_NOTICES (if enabled in your error reporting), call it with @ before
     * the variables. Eg.: Phery::coalesce(@$var1, @$var['asdf']);
     *
     * @param mixed $args,... Any number of arguments
     *
     * @return mixed
     */
    public static function coalesce($args)
    {
        $args = func_get_args();
        foreach ($args as &$arg) {
            if (isset($arg) && !empty($arg)) {
                return $arg;
            }
        }

        return null;
    }
}