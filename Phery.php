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
 * @version    2.5.4
 * @license    http://opensource.org/licenses/MIT MIT License
 */

/**
 * Main class for Phery.js
 *
 * @package    Phery
 */
class Phery implements ArrayAccess {

	/**
	 * Exception on callback() function
	 * @see callback()
	 * @type int
	 */
	const ERROR_CALLBACK = 0;
	/**
	 * Exception on process() function
	 * @see process()
	 */
	const ERROR_PROCESS = 1;
	/**
	 * Exception on set() function
	 * @see set()
	 */
	const ERROR_SET = 2;
	/**
	 * Exception when the CSRF is invalid
	 * @see process()
	 */
	const ERROR_CSRF = 4;
	/**
	 * Exception on static functions
	 * @see link_to()
	 * @see select_for()
	 * @see form_for()
	 */
	const ERROR_TO = 3;
	/**
	 * Default encoding for your application
	 * @var string
	 */
	public static $encoding = 'UTF-8';
	/**
	 * Expose the paths on PheryResponse exceptions
	 * @var bool
	 */
	public static $expose_paths = false;
	/**
	 * The functions registered
	 * @var array
	 */
	protected $functions = array();
	/**
	 * The callbacks registered
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
	 * @var Phery
	 * @static
	 */
	protected static $instance = null;
	/**
	 * Render view function
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
	 * </code>
	 * @var array
	 *
	 * @see config()
	 */
	protected $config = array();
	/**
	 * If the class was just initiated
	 * @var bool
	 */
	private $init = true;

	/**
	 * Construct the new Phery instance
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
				'error_reporting' => false
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
		if (isset($callbacks['before']))
		{
			if (is_array($callbacks['before']) && !is_callable($callbacks['before']))
			{
				foreach ($callbacks['before'] as $func)
				{
					if (is_callable($func))
					{
						$this->callbacks['before'][] = $func;
					}
					else
					{
						self::exception($this, "The provided before callback function isn't callable", self::ERROR_CALLBACK);
					}
				}
			}
			else
			{
				if (is_callable($callbacks['before']))
				{
					$this->callbacks['before'][] = $callbacks['before'];
				}
				else
				{
					self::exception($this, "The provided before callback function isn't callable", self::ERROR_CALLBACK);
				}
			}
		}

		if (isset($callbacks['after']))
		{
			if (is_array($callbacks['after']) && !is_callable($callbacks['after']))
			{

				foreach ($callbacks['after'] as $func)
				{
					if (is_callable($func))
					{
						$this->callbacks['after'][] = $func;
					}
					else
					{
						self::exception($this, "The provided after callback function isn't callable", self::ERROR_CALLBACK);
					}
				}
			}
			else
			{
				if (is_callable($callbacks['after']))
				{
					$this->callbacks['after'][] = $callbacks['after'];
				}
				else
				{
					self::exception($this, "The provided after callback function isn't callable", self::ERROR_CALLBACK);
				}
			}
		}

		return $this;
	}

	/**
	 * Throw an exception if enabled
	 *
	 * @param Phery   $phery Instance
	 * @param string  $exception
	 * @param integer $code
	 *
	 * @throws PheryException
	 * @return boolean
	 */
	protected static function exception($phery, $exception, $code)
	{
		if ($phery instanceof Phery && $phery->config['exceptions'] === true)
		{
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
		foreach (func_get_args() as $arg)
		{
			if (is_array($arg))
			{
				$this->data = array_merge_recursive($arg, $this->data);
			}
			else
			{
				$this->data[] = $arg;
			}
		}

		return $this;
	}

	/**
	 * Encode PHP code to put inside data-phery-args, usually for updating the data there
	 *
	 * @param array  $data     Any data that can be converted using json_encode
	 * @param string $encoding Encoding for the arguments
	 *
	 * @return string Return json_encode'd and htmlentities'd string
	 */
	public static function args(array $data, $encoding = 'UTF-8')
	{
		return htmlentities(json_encode($data), ENT_COMPAT, $encoding, false);
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
		if ($this->config['csrf'] !== true)
		{
			return !empty($check) ? true : '';
		}

		if (session_id() == '')
		{
			@session_start();
		}

		if ($check === false)
		{
			if ((!empty($_SESSION['phery']['csrf']) && $force) || empty($_SESSION['phery']['csrf']))
			{
				$token = sha1(uniqid(microtime(true), true));

				$_SESSION['phery'] = array(
					'csrf' => $token
				);

				$token = base64_encode($token);
			}
			else
			{
				$token = base64_encode($_SESSION['phery']['csrf']);
			}

			return "<meta id=\"csrf-token\" name=\"csrf-token\" content=\"{$token}\" />\n";
		}
		else
		{
			if (empty($_SESSION['phery']['csrf']))
			{
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
		switch ($is_phery)
		{
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
		if (!empty($variable) && is_string($variable))
		{
			return stripslashes($variable);
		}

		if (!empty($variable) && is_array($variable))
		{
			foreach ($variable as $i => $value)
			{
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
		while (ob_get_level() > 0)
		{
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
		if ($handled)
		{
			self::flush();
		}

		if ($errors === true && ($error = error_get_last()) && !$handled)
		{
			self::error_handler($error["type"], $error["message"], $error["file"], $error["line"]);
		}

		if (!$handled)
		{
			self::flush();
		}

		if (!session_id())
		{
			session_write_close();
		}

		exit;
	}

	/**
	 * Helper function to properly output the headers for a PheryResponse in case you need
	 * to manually return it (like when following a redirect)
	 *
	 * @param string|PheryResponse $response The response or a string
	 * @param bool                 $echo     Echo the response
	 *
	 * @return string
	 */
	public static function respond($response, $echo = true)
	{
		if ($response instanceof PheryResponse)
		{
			if (!headers_sent())
			{
				header('Cache-Control: no-cache, must-revalidate', true);
				header('Expires: Sat, 26 Jul 1997 05:00:00 GMT', true);
				header('Content-Type: application/json; charset='.(strtolower(Phery::$encoding)), true);
				header('Connection: close', true);
			}
		}

		if ($response)
		{
			$response = "{$response}";
		}

		if ($echo === true)
		{
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
		foreach ($views as $container => $callback)
		{
			if (is_callable($callback))
			{
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
		if ($this->config['error_reporting'] !== false)
		{
			set_error_handler('Phery::error_handler', $this->config['error_reporting']);
		}

		if (empty($_POST['phery']['csrf']))
		{
			$_POST['phery']['csrf'] = '';
		}

		if ($this->csrf($_POST['phery']['csrf']) === false)
		{
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

		if (empty($_POST['phery']))
		{
			return self::exception($this, 'Non-Phery AJAX request', self::ERROR_PROCESS);
		}

		if (!empty($_GET['_']))
		{
			$this->data['requested'] = (int)$_GET['_'];
			unset($_GET['_']);
		}

		if (isset($_GET['_try_count']))
		{
			$this->data['retries'] = (int)$_GET['_try_count'];
			unset($_GET['_try_count']);
		}

		$args = array();
		$remote = false;

		if (!empty($_POST['phery']['remote']))
		{
			$remote = $_POST['phery']['remote'];
		}

		if (!empty($_POST['phery']['submit_id']))
		{
			$this->data['submit_id'] = "#{$_POST['phery']['submit_id']}";
		}

		if ($remote !== false)
		{
			$this->data['remote'] = $remote;
		}

		if (!empty($_POST['args']))
		{
			$args = get_magic_quotes_gpc() ? $this->stripslashes_recursive($_POST['args']) : $_POST['args'];

			if ($last_call === true)
			{
				unset($_POST['args']);
			}
		}

		foreach ($_POST['phery'] as $name => $post)
		{
			if (!isset($this->data[$name]))
			{
				$this->data[$name] = $post;
			}
		}

		if (count($this->callbacks['before']))
		{
			foreach ($this->callbacks['before'] as $func)
			{
				if (($args = call_user_func($func, $args, $this->data, $this)) === false)
				{
					return false;
				}
			}
		}

		if (!empty($_POST['phery']['view']))
		{
			$this->data['view'] = $_POST['phery']['view'];
		}

		if ($remote !== false)
		{
			if (isset($this->functions[$remote]))
			{
				if (isset($_POST['phery']['remote']))
				{
					unset($_POST['phery']['remote']);
				}

				$this->before_user_func();

				$response = call_user_func($this->functions[$remote], $args, $this->data, $this);

				foreach ($this->callbacks['after'] as $func)
				{
					if (call_user_func($func, $args, $this->data, $response, $this) === false)
					{
						return false;
					}
				}

				if (($response = self::respond($response, false)) === null)
				{
					$error = 'Response was void for function "'. htmlentities($remote, ENT_COMPAT, null, false). '"';
				}

				$_POST['phery']['remote'] = $remote;
			}
			else
			{
				if ($last_call)
				{
					self::exception($this, 'The function provided "' . htmlentities($remote, ENT_COMPAT, null, false) . '" isn\'t set', self::ERROR_PROCESS);
				}
			}
		}
		else
		{
			if (!empty($this->data['view']) && isset($this->views[$this->data['view']]))
			{
				$view = $this->data['view'];

				$this->before_user_func();

				$response = call_user_func($this->views[$this->data['view']], $args, $this->data, $this);

				foreach ($this->callbacks['after'] as $func)
				{
					if (call_user_func($func, $args, $this->data, $response, $this) === false)
					{
						return false;
					}
				}

				if (($response = self::respond($response, false)) === null)
				{
					$error = 'Response was void for view "'. htmlentities($this->data['view'], ENT_COMPAT, null, false) . '"';
				}
			}
			else
			{
				if ($last_call)
				{
					if (!empty($this->data['view']))
					{
						self::exception($this, 'The provided view "' . htmlentities($this->data['view'], ENT_COMPAT, null, false) . '" isn\'t set', self::ERROR_PROCESS);
					}
					else
					{
						self::exception($this, 'Empty request', self::ERROR_PROCESS);
					}
				}
			}
		}

		if ($error !== null)
		{
			self::error_handler(E_NOTICE, $error, '', 0);
		}
		elseif ($response === null && $last_call & !$view)
		{
			$response = PheryResponse::factory();
		}
		elseif ($response !== null)
		{
			ob_start();

			if (!$this->config['return'])
			{
				echo $response;
			}
		}

		if (!$this->config['return'] && $this->config['exit_allowed'] === true)
		{
			if ($last_call || $response !== null)
			{
				exit;
			}
		}
		elseif ($this->config['return'])
		{
			self::flush(true);
		}

		if ($this->config['error_reporting'] !== false)
		{
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
		if (self::is_ajax(true))
		{
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

		if (!empty($config))
		{
			if (is_array($config))
			{
				if (isset($config['exit_allowed']))
				{
					$this->config['exit_allowed'] = (bool)$config['exit_allowed'];
				}

				if (isset($config['return']))
				{
					$this->config['return'] = (bool)$config['return'];
				}

				if (isset($config['set_always_available']))
				{
					$this->config['set_always_available'] = (bool)$config['set_always_available'];
				}

				if (isset($config['exceptions']))
				{
					$this->config['exceptions'] = (bool)$config['exceptions'];
				}

				if (isset($config['csrf']))
				{
					$this->config['csrf'] = (bool)$config['csrf'];
				}

				if (isset($config['error_reporting']))
				{
					if ($config['error_reporting'] !== false)
					{
						$this->config['error_reporting'] = (int)$config['error_reporting'];
					}
					else
					{
						$this->config['error_reporting'] = false;
					}

					$register_function = true;
				}

				if ($register_function || $this->init)
				{
					register_shutdown_function('Phery::shutdown_handler', $this->config['error_reporting'] !== false);
					$this->init = false;
				}

				return $this;
			}
			elseif (!empty($config) && is_string($config) && isset($this->config[$config]))
			{
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
		if (!(self::$instance instanceof Phery))
		{
			self::$instance = new Phery($config);
		}
		else if ($config)
		{
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
		if ($this->config['set_always_available'] === false && !self::is_ajax(true))
		{
			return $this;
		}

		if (isset($functions) && is_array($functions))
		{
			foreach ($functions as $name => $func)
			{
				if (is_callable($func))
				{
					$this->functions[$name] = $func;
				}
				else
				{
					self::exception($this, 'Provided function "' . $name . '" isnt a valid function or method', self::ERROR_SET);
				}
			}
		}
		else
		{
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
		if (isset($this->functions[$name]))
		{
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
		if (isset($this->functions[$function_name]))
		{
			if (count($args))
			{
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
		if (!empty($attributes['args']))
		{
			$attributes['data-phery-args'] = json_encode($attributes['args']);
			unset($attributes['args']);
		}

		if (!empty($attributes['confirm']))
		{
			$attributes['data-phery-confirm'] = $attributes['confirm'];
			unset($attributes['confirm']);
		}

		if (!empty($attributes['cache']))
		{
			$attributes['data-phery-cache'] = "1";
			unset($attributes['cache']);
		}

		if (!empty($attributes['target']))
		{
			$attributes['data-phery-target'] = $attributes['target'];
			unset($attributes['target']);
		}

		if (!empty($attributes['related']))
		{
			$attributes['data-phery-related'] = $attributes['related'];
			unset($attributes['related']);
		}

		if (!empty($attributes['phery-type']))
		{
			$attributes['data-phery-type'] = $attributes['phery-type'];
			unset($attributes['phery-type']);
		}

		if (!empty($attributes['only']))
		{
			$attributes['data-phery-only'] = $attributes['only'];
			unset($attributes['only']);
		}

		if (isset($attributes['clickable']))
		{
			$attributes['data-phery-clickable'] = "1";
			unset($attributes['clickable']);
		}

		if ($include_method)
		{
			if (isset($attributes['method']))
			{
				$attributes['data-phery-method'] = $attributes['method'];
				unset($attributes['method']);
			}
		}

		$encoding = 'UTF-8';
		if (isset($attributes['encoding']))
		{
			$encoding = $attributes['encoding'];
			unset($attributes['encoding']);
		}

		return $encoding;
	}

	/**
	 * Helper function that generates an ajax link, defaults to "A" tag
	 *
	 * @param string $content    The content of the link. This is ignored for self closing tags, img, input, iframe
	 * @param string $function   The PHP function assigned name on Phery::set()
	 * @param array  $attributes Extra attributes that can be passed to the link, like class, style, etc
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
	 * @param Phery  $phery      Pass the current instance of phery, so it can check if the
	 *                           functions are defined, and throw exceptions
	 * @param boolean $no_close  Don't close the tag, useful if you want to create an AJAX DIV with a lot of content inside,
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
		if ($phery && !isset($phery->functions[$function]))
		{
			self::exception($phery, 'The function "' . $function . '" provided in "link_to" hasnt been set', self::ERROR_TO);
		}

		$tag = 'a';
		if (isset($attributes['tag']))
		{
			$tag = $attributes['tag'];
			unset($attributes['tag']);
		}

		$encoding = self::common_check($attributes);

		if ($function)
		{
			$attributes['data-phery-remote'] = $function;
		}

		$ret = array();
		$ret[] = "<{$tag}";
		foreach ($attributes as $attribute => $value)
		{
			$ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, $encoding, false) . "\"";
		}

		if (!in_array(strtolower($tag), array('img', 'input', 'iframe', 'hr', 'area', 'embed', 'keygen')))
		{
			$ret[] = ">{$content}";
			if (!$no_close)
			{
				$ret[] = "</{$tag}>";
			}
		}
		else
		{
			$ret[] = "/>";
		}

		return join(' ', $ret);
	}

	/**
	 * Create a <form> tag with ajax enabled. Must be closed manually with </form>
	 *
	 * @param string $action   where to go, can be empty
	 * @param string $function Registered function name
	 * @param array  $attributes Configuration of the element plus any HTML attributes
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
	 * @param Phery  $phery    Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
	 *
	 * @static
	 * @return string The mounted &lt;form&gt; HTML tag
	 */
	public static function form_for($action, $function, array $attributes = array(), Phery $phery = null)
	{
		if (!$function)
		{
			self::exception($phery, 'The "function" argument must be provided to "form_for"', self::ERROR_TO);

			return '';
		}

		if ($phery && !isset($phery->functions[$function]))
		{
			self::exception($phery, 'The function "' . $function . '" provided in "form_for" hasnt been set', self::ERROR_TO);
		}

		$encoding = self::common_check($attributes, false);

		if (isset($attributes['submit']))
		{
			$attributes['data-phery-submit'] = json_encode($attributes['submit']);
			unset($attributes['submit']);
		}

		$ret = array();
		$ret[] = '<form method="POST" action="' . $action . '" data-phery-remote="' . $function . '"';
		foreach ($attributes as $attribute => $value)
		{
			$ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, $encoding, false) . "\"";
		}
		$ret[] = '>';

		return join(' ', $ret);
	}

	/**
	 * Create a <select> element with ajax enabled "onchange" event.
	 *
	 * @param string $function Registered function name
	 * @param array  $items    Options for the select, 'value' => 'text' representation
	 * @param array  $attributes Configuration of the element plus any HTML attributes
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
	 * @param Phery  $phery    Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
	 *
	 * @static
	 * @return string The mounted &lt;select&gt; with &lt;option&gt;s inside
	 */
	public static function select_for($function, array $items, array $attributes = array(), Phery $phery = null)
	{
		if ($phery && !isset($phery->functions[$function]))
		{
			self::exception($phery, 'The function "' . $function . '" provided in "select_for" hasnt been set', self::ERROR_TO);
		}

		$encoding = self::common_check($attributes);

		$selected = array();
		if (isset($attributes['selected']))
		{
			if (is_array($attributes['selected']))
			{
				// multiple select
				$selected = $attributes['selected'];
			}
			else
			{
				// single select
				$selected = array($attributes['selected']);
			}
			unset($attributes['selected']);
		}

		if (isset($attributes['multiple']))
		{
			$attributes['multiple'] = 'multiple';
		}

		$ret = array();
		$ret[] = '<select '.($function ? 'data-phery-remote="' . $function . '"' : '');
		foreach ($attributes as $attribute => $value)
		{
			$ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, $encoding, false) . "\"";
		}
		$ret[] = '>';

		foreach ($items as $value => $text)
		{
			$_value = 'value="' . htmlentities($value, ENT_COMPAT, $encoding, false) . '"';
			if (in_array($value, $selected))
			{
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
		if (isset($this->data[$offset]))
		{
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
		if (isset($this->data[$offset]))
		{
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
	 *
	 * @return mixed
	 */
	public function __get($name)
	{
		if (isset($this->data[$name]))
		{
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
		foreach ($args as &$arg)
		{
			if (isset($arg) && !empty($arg))
			{
				return $arg;
			}
		}

		return null;
	}
}

/**
 * Standard response for the json parser
 * @package    Phery
 *
 * @method PheryResponse ajax(string $url, array $settings = null) Perform an asynchronous HTTP (Ajax) request.
 * @method PheryResponse ajaxSetup(array $obj) Set default values for future Ajax requests.
 * @method PheryResponse post(string $url, PheryFunction $success = null) Load data from the server using a HTTP POST request.
 * @method PheryResponse get(string $url, PheryFunction $success = null) Load data from the server using a HTTP GET request.
 * @method PheryResponse getJSON(string $url, PheryFunction $success = null) Load JSON-encoded data from the server using a GET HTTP request.
 * @method PheryResponse getScript(string $url, PheryFunction $success = null) Load a JavaScript file from the server using a GET HTTP request, then execute it.
 * @method PheryResponse detach() Detach a DOM element retaining the events attached to it
 * @method PheryResponse prependTo(string $target) Prepend DOM element to target
 * @method PheryResponse appendTo(string $target) Append DOM element to target
 * @method PheryResponse replaceWith(string $newContent) The content to insert. May be an HTML string, DOM element, or jQuery object.
 * @method PheryResponse css(string $propertyName, mixed $value = null) propertyName: A CSS property name. value: A value to set for the property.
 * @method PheryResponse toggle($duration_or_array_of_options, PheryFunction $complete = null)  Display or hide the matched elements.
 * @method PheryResponse is(string $selector) Check the current matched set of elements against a selector, element, or jQuery object and return true if at least one of these elements matches the given arguments.
 * @method PheryResponse hide(string $speed = 0) Hide an object, can be animated with 'fast', 'slow', 'normal'
 * @method PheryResponse show(string $speed = 0) Show an object, can be animated with 'fast', 'slow', 'normal'
 * @method PheryResponse toggleClass(string $className) Add/Remove a class from an element
 * @method PheryResponse data(string $name, mixed $data) Add data to element
 * @method PheryResponse addClass(string $className) Add a class from an element
 * @method PheryResponse removeClass(string $className) Remove a class from an element
 * @method PheryResponse animate(array $prop, int $dur, string $easing = null, PheryFunction $cb = null) Perform a custom animation of a set of CSS properties.
 * @method PheryResponse trigger(string $eventName, array $args = null) Trigger an event
 * @method PheryResponse triggerHandler(string $eventType, array $extraParameters = null) Execute all handlers attached to an element for an event.
 * @method PheryResponse fadeIn(string $speed) Fade in an element
 * @method PheryResponse filter(string $selector) Reduce the set of matched elements to those that match the selector or pass the function's test.
 * @method PheryResponse fadeTo(int $dur, float $opacity) Fade an element to opacity
 * @method PheryResponse fadeOut(string $speed) Fade out an element
 * @method PheryResponse slideUp(int $dur, PheryFunction $cb = null) Hide with slide up animation
 * @method PheryResponse slideDown(int $dur, PheryFunction $cb = null) Show with slide down animation
 * @method PheryResponse slideToggle(int $dur, PheryFunction $cb = null) Toggle show/hide the element, using slide animation
 * @method PheryResponse unbind(string $name) Unbind an event from an element
 * @method PheryResponse undelegate() Remove a handler from the event for all elements which match the current selector, now or in the future, based upon a specific set of root elements.
 * @method PheryResponse stop() Stop animation on elements
 * @method PheryResponse val(string $content) Set the value of an element
 * @method PheryResponse removeData(string $name) Remove element data added with data()
 * @method PheryResponse removeAttr(string $name) Remove an attribute from an element
 * @method PheryResponse scrollTop(int $val) Set the scroll from the top
 * @method PheryResponse scrollLeft(int $val) Set the scroll from the left
 * @method PheryResponse height(int $val = null) Get or set the height from the left
 * @method PheryResponse width(int $val = null) Get or set the width from the left
 * @method PheryResponse slice(int $start, int $end) Reduce the set of matched elements to a subset specified by a range of indices.
 * @method PheryResponse not(string $val) Remove elements from the set of matched elements.
 * @method PheryResponse eq(int $selector) Reduce the set of matched elements to the one at the specified index.
 * @method PheryResponse offset(array $coordinates) Set the current coordinates of every element in the set of matched elements, relative to the document.
 * @method PheryResponse map(PheryFunction $callback) Pass each element in the current matched set through a function, producing a new jQuery object containing the return values.
 * @method PheryResponse children(string $selector) Get the children of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse closest(string $selector) Get the first ancestor element that matches the selector, beginning at the current element and progressing up through the DOM tree.
 * @method PheryResponse find(string $selector) Get the descendants of each element in the current set of matched elements, filtered by a selector, jQuery object, or element.
 * @method PheryResponse next(string $selector = null) Get the immediately following sibling of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse nextAll(string $selector) Get all following siblings of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse nextUntil(string $selector) Get all following siblings of each element up to  but not including the element matched by the selector.
 * @method PheryResponse parentsUntil(string $selector) Get the ancestors of each element in the current set of matched elements, up to but not including the element matched by the selector.
 * @method PheryResponse offsetParent() Get the closest ancestor element that is positioned.
 * @method PheryResponse parent(string $selector = null) Get the parent of each element in the current set of matched elements, optionally filtered by a selector.
 * @method PheryResponse parents(string $selector) Get the ancestors of each element in the current set of matched elements, optionally filtered by a selector.
 * @method PheryResponse prev(string $selector = null) Get the immediately preceding sibling of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse prevAll(string $selector) Get all preceding siblings of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse prevUntil(string $selector) Get the ancestors of each element in the current set of matched elements, optionally filtered by a selector.
 * @method PheryResponse siblings(string $selector) Get the siblings of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse add(PheryResponse $selector) Add elements to the set of matched elements.
 * @method PheryResponse contents() Get the children of each element in the set of matched elements, including text nodes.
 * @method PheryResponse end() End the most recent filtering operation in the current chain and return the set of matched elements to its previous state.
 * @method PheryResponse after(string $content) Insert content, specified by the parameter, after each element in the set of matched elements.
 * @method PheryResponse before(string $content) Insert content, specified by the parameter, before each element in the set of matched elements.
 * @method PheryResponse insertAfter(string $target) Insert every element in the set of matched elements after the target.
 * @method PheryResponse insertBefore(string $target) Insert every element in the set of matched elements before the target.
 * @method PheryResponse unwrap() Remove the parents of the set of matched elements from the DOM, leaving the matched elements in their place.
 * @method PheryResponse wrap(string $wrappingElement) Wrap an HTML structure around each element in the set of matched elements.
 * @method PheryResponse wrapAll(string $wrappingElement) Wrap an HTML structure around all elements in the set of matched elements.
 * @method PheryResponse wrapInner(string $wrappingElement) Wrap an HTML structure around the content of each element in the set of matched elements.
 * @method PheryResponse delegate(string $selector, string $eventType, PheryFunction $handler) Attach a handler to one or more events for all elements that match the selector, now or in the future, based on a specific set of root elements.
 * @method PheryResponse one(string $eventType, PheryFunction $handler) Attach a handler to an event for the elements. The handler is executed at most once per element.
 * @method PheryResponse bind(string $eventType, PheryFunction $handler) Attach a handler to an event for the elements.
 * @method PheryResponse each(PheryFunction $function) Iterate over a jQ object, executing a function for each matched element.
 * @method PheryResponse phery(string $function = null, array $args = null) Access the phery() on the select element(s)
 * @method PheryResponse addBack(string $selector = null) Add the previous set of elements on the stack to the current set, optionally filtered by a selector.
 * @method PheryResponse clearQueue(string $queueName = null) Remove from the queue all items that have not yet been run.
 * @method PheryResponse clone(boolean $withDataAndEvents = null, boolean $deepWithDataAndEvents = null) Create a deep copy of the set of matched elements.
 * @method PheryResponse dblclick(array $eventData = null, PheryFunction $handler = null) Bind an event handler to the "dblclick" JavaScript event, or trigger that event on an element.
 * @method PheryResponse always(PheryFunction $callback) Bind an event handler to the "dblclick" JavaScript event, or trigger that event on an element.
 * @method PheryResponse done(PheryFunction $callback) Add handlers to be called when the Deferred object is resolved.
 * @method PheryResponse fail(PheryFunction $callback) Add handlers to be called when the Deferred object is rejected.
 * @method PheryResponse progress(PheryFunction $callback) Add handlers to be called when the Deferred object is either resolved or rejected.
 * @method PheryResponse then(PheryFunction $donecallback, PheryFunction $failcallback = null, PheryFunction $progresscallback = null) Add handlers to be called when the Deferred object is resolved, rejected, or still in progress.
 * @method PheryResponse empty() Remove all child nodes of the set of matched elements from the DOM.
 * @method PheryResponse finish(string $queue) Stop the currently-running animation, remove all queued animations, and complete all animations for the matched elements.
 * @method PheryResponse focus(array $eventData = null, PheryFunction $handler = null)  Bind an event handler to the "focusout" JavaScript event.
 * @method PheryResponse focusin(array $eventData = null, PheryFunction $handler = null)  Bind an event handler to the "focusin" event.
 * @method PheryResponse focusout(array $eventData = null, PheryFunction $handler = null) Bind an event handler to the "focus" JavaScript event, or trigger that event on an element.
 * @method PheryResponse has(string $selector) Reduce the set of matched elements to those that have a descendant that matches the selector or DOM element.
 * @method PheryResponse index(string $selector = null) Search for a given element from among the matched elements.
 * @method PheryResponse on(string $events, string $selector, array $data = null, PheryFunction $handler = null) Attach an event handler function for one or more events to the selected elements.
 * @method PheryResponse off(string $events, string $selector = null, PheryFunction $handler = null) Remove an event handler.
 * @method PheryResponse prop(string $propertyName, $data_or_function = null) Set one or more properties for the set of matched elements.
 * @method PheryResponse promise(string $type = null, array $target = null) Return a Promise object to observe when all actions of a certain type bound to the collection, queued or not, have finished.
 * @method PheryResponse pushStack(array $elements, string $name = null, array $arguments = null) Add a collection of DOM elements onto the jQuery stack.
 * @method PheryResponse removeProp(string $propertyName) Remove a property for the set of matched elements.
 * @method PheryResponse resize($eventData_or_function = null, PheryFunction $handler = null) Bind an event handler to the "resize" JavaScript event, or trigger that event on an element.
 * @method PheryResponse scroll($eventData_or_function = null, PheryFunction $handler = null) Bind an event handler to the "scroll" JavaScript event, or trigger that event on an element.
 * @method PheryResponse select($eventData_or_function = null, PheryFunction $handler = null) Bind an event handler to the "select" JavaScript event, or trigger that event on an element.
 * @method PheryResponse serializeArray() Encode a set of form elements as an array of names and values.
 * @method PheryResponse replaceAll(string $target) Replace each target element with the set of matched elements.
 * @method PheryResponse reset() Reset a form element.
 * @method PheryResponse toArray() Retrieve all the DOM elements contained in the jQuery set, as an array.
 * @property PheryResponse this The DOM element that is making the AJAX call
 * @property PheryResponse jquery The $ jQuery object, can be used to call $.getJSON, $.getScript, etc
 * @property PheryResponse window Shortcut for jquery('window') / $(window)
 * @property PheryResponse document Shortcut for jquery('document') / $(document)
 */
class PheryResponse extends ArrayObject {

	/**
	 * All responses that were created in the run, access them through their name
	 * @var PheryResponse[]
	 */
	protected static $responses = array();
	/**
	 * Common data available to all responses
	 * @var array
	 */
	protected static $global = array();
	/**
	 * Last jQuery selector defined
	 * @var string
	 */
	protected $last_selector = null;
	/**
	 * Restore the selector if set
	 * @var string
	 */
	protected $restore = null;
	/**
	 * Array containing answer data
	 * @var array
	 */
	protected $data = array();
	/**
	 * Array containing merged data
	 * @var array
	 */
	protected $merged = array();
	/**
	 * Name of the current response
	 * @var string
	 */
	protected $name = null;
	/**
	 * Internal count for multiple paths
	 * @var int
	 */
	protected static $internal_count = 0;
	/**
	 * Internal count for multiple commands
	 * @var int
	 */
	protected $internal_cmd_count = 0;
	/**
	 * Is the criteria from unless fulfilled?
	 * @var bool
	 */
	protected $matched = true;

	/**
	 * Construct a new response
	 *
	 * @param string $selector Create the object already selecting the DOM element
	 * @param array $constructor Only available if you are creating an element, like $('&lt;p/&gt;')
	 */
	public function __construct($selector = null, array $constructor = array())
	{
		parent::__construct();

		$this->jquery($selector, $constructor);

		$this->set_response_name(uniqid("", true));
	}

	/**
	 * Increment the internal counter, so there are no conflicting stacked commands
	 *
	 * @param string $type Selector
	 * @param boolean $force Force unajusted selector into place
	 * @return string The previous overwritten selector
	 */
	protected function set_internal_counter($type, $force = false)
	{
		$last = $this->last_selector;
		if ($force && $last !== null && !isset($this->data[$last])) {
			$this->data[$last] = array();
		}
		$this->last_selector = '{'.$type.(self::$internal_count++).'}';
		return $last;
	}

	/**
	 * Renew the CSRF token on a given Phery instance
	 * Resets any selectors that were being chained before
	 *
	 * @param Phery $instance Instance of Phery
	 * @return PheryResponse
	 */
	public function renew_csrf(Phery $instance)
	{
		if ($instance->config('csrf') === true)
		{
			$this->jquery('head meta#csrf-token')->replaceWith($instance->csrf());
		}

		return $this;
	}

	/**
	 * Set the name of this response
	 *
	 * @param string $name Name of current response
	 *
	 * @return PheryResponse
	 */
	public function set_response_name($name)
	{
		if (!empty($this->name))
		{
			unset(self::$responses[$this->name]);
		}
		$this->name = $name;
		self::$responses[$this->name] = $this;

		return $this;
	}

	/**
	 * Broadcast a remote message to the client to all elements that
	 * are subscribed to them. This removes the current selector if any
	 *
	 * @param string $name Name of the browser subscribed topic on the element
	 * @param array [$params] Any params to pass to the subscribed topic
	 *
	 * @return PheryResponse
	 */
	public function phery_broadcast($name, array $params = array())
	{
		$this->last_selector = null;
		return $this->cmd(12, array($name, array(self::typecast($params, true, true)), true));
	}

	/**
	 * Publish a remote message to the client that is subscribed to them
	 * This removes the current selector (if any)
	 *
	 * @param string $name Name of the browser subscribed topic on the element
	 * @param array [$params] Any params to pass to the subscribed topic
	 *
	 * @return PheryResponse
	 */
	public function publish($name, array $params = array())
	{
		$this->last_selector = null;
		return $this->cmd(12, array($name, array(self::typecast($params, true, true))));
	}

	/**
	 * Get the name of this response
	 *
	 * @return null|string
	 */
	public function get_response_name()
	{
		return $this->name;
	}

	/**
	 * Borrowed from Ruby, the next imediate instruction will be executed unless
	 * it matches this criteria.
	 *
	 * <code>
	 *   $count = 3;
	 *   PheryResponse::factory()
	 *     // if not $count equals 2 then
	 *     ->unless($count === 2)
	 *     ->call('func'); // This won't trigger, $count is 2
	 * </code>
	 *
	 * <code>
	 *   PheryResponse::factory('.widget')
	 *     ->unless(PheryFunction::factory('return !this.hasClass("active");'), true)
	 *     ->remove(); // This won't remove if the element have the active class
	 * </code>
	 *
	 *
	 * @param boolean|PheryFunction $condition
	 * When not remote, can be any criteria that evaluates to FALSE.
	 * When it's remote, if passed a PheryFunction, it will skip the next
	 * iteration unless the return value of the PheryFunction is false.
	 * Passing a PheryFunction automatically sets $remote param to true
	 *
	 * @param bool $remote
	 * Instead of doing it in the server side, do it client side, for example,
	 * append something ONLY if an element exists. The context (this) of the function
	 * will be the last selected element or the calling element.
	 *
	 * @return PheryResponse
	 */
	public function unless($condition, $remote = false)
	{
		if (!$remote && !($condition instanceof PheryFunction) && !($condition instanceof PheryResponse))
		{
			$this->matched = !$condition;
		}
		else
		{
			$this->set_internal_counter('!', true);
			$this->cmd(0xff, array(self::typecast($condition, true, true)));
		}

		return $this;
	}

    /**
	 * It's the opposite of unless(), the next command will be issued in
     * case the condition is true
     *
	 * <code>
	 *   $count = 3;
	 *   PheryResponse::factory()
     *     // if $count is greater than 2 then
	 *     ->incase($count > 2)
	 *     ->call('func'); // This will be executed, $count is greater than 2
	 * </code>
     *
     * <code>
     *   PheryResponse::factory('.widget')
     *     ->incase(PheryFunction::factory('return this.hasClass("active");'), true)
     *     ->remove(); // This will remove the element if it has the active class
     * </code>
     *
     * @param boolean|callable|PheryFunction $condition
	 * When not remote, can be any criteria that evaluates to TRUE.
	 * When it's remote, if passed a PheryFunction, it will execute the next
	 * iteration when the return value of the PheryFunction is true
	 *
	 * @param bool $remote
     * Instead of doing it in the server side, do it client side, for example,
     * append something ONLY if an element exists. The context (this) of the function
     * will be the last selected element or the calling element.
	 *
	 * @return PheryResponse
	 */
	public function incase($condition, $remote = false)
	{
		if (!$remote && !($condition instanceof PheryFunction) && !($condition instanceof PheryResponse))
		{
			$this->matched = $condition;
		}
		else
		{
			$this->set_internal_counter('=', true);
			$this->cmd(0xff, array(self::typecast($condition, true, true)));
		}

		return $this;
	}

	/**
	 * This helper function is intended to normalize the $_FILES array, because when uploading multiple
	 * files, the order gets messed up. The result will always be in the format:
	 *
	 * <code>
	 * array(
	 *    'name of the file input' => array(
	 *       array(
	 *         'name' => ...,
	 *         'tmp_name' => ...,
	 *         'type' => ...,
	 *         'error' => ...,
	 *         'size' => ...,
	 *       ),
	 *       array(
	 *         'name' => ...,
	 *         'tmp_name' => ...,
	 *         'type' => ...,
	 *         'error' => ...,
	 *         'size' => ...,
	 *       ),
	 *    )
	 * );
	 * </code>
	 *
	 * So you can always do like (regardless of one or multiple files uploads)
	 *
	 * <code>
	 * <input name="avatar" type="file" multiple>
	 * <input name="pic" type="file">
	 *
	 * <?php
	 * foreach(PheryResponse::files('avatar') as $index => $file){
	 *     if (is_uploaded_file($file['tmp_name'])){
	 *        //...
	 *     }
	 * }
	 *
	 * foreach(PheryResponse::files() as $field => $group){
	 *   foreach ($group as $file){
	 *     if (is_uploaded_file($file['tmp_name'])){
	 *       if ($field === 'avatar') {
	 *          //...
	 *       } else if ($field === 'pic') {
	 *          //...
	 *       }
	 *     }
	 *   }
	 * }
	 * ?>
	 * </code>
	 *
	 * If no files were uploaded, returns an empty array.
	 *
	 * @param string|bool $group Pluck out the file group directly
	 * @return array
	 */
	public static function files($group = false)
	{
		$result = array();

		foreach ($_FILES as $name => $keys)
		{
			if (is_array($keys))
			{
				if (is_array($keys['name']))
				{
					$len = count($keys['name']);
					for ($i = 0; $i < $len; $i++)
					{
						$result[$name][$i] = array(
							'name' => $keys['name'][$i],
							'tmp_name' => $keys['tmp_name'][$i],
							'type' => $keys['type'][$i],
							'error' => $keys['error'][$i],
							'size' => $keys['size'][$i],
						);
					}
				}
				else
				{
					$result[$name] = array(
						$keys
					);
				}
			}
		}

		return $group !== false && isset($result[$group]) ? $result[$group] : $result;
	}

	/**
	 * Set a global value that can be accessed through $pheryresponse['value']
	 * It's available in all responses, and can also be acessed using self['value']
	 *
	 * @param array|string Key => value combination or the name of the global
	 * @param mixed $value [Optional]
	 */
	public static function set_global($name, $value = null)
	{
		if (isset($name) && is_array($name))
		{
			foreach ($name as $n => $v)
			{
				self::$global[$n] = $v;
			}
		}
		else
		{
			self::$global[$name] = $value;
		}
	}

	/**
	 * Unset a global variable
	 *
	 * @param string $name Variable name
	 */
	public static function unset_global($name)
	{
		unset(self::$global[$name]);
	}

	/**
	 * Will check for globals and local values
	 *
	 * @param string|int $index
	 *
	 * @return mixed
	 */
	public function offsetExists($index)
	{
		if (isset(self::$global[$index]))
		{
			return true;
		}

		return parent::offsetExists($index);
	}

	/**
	 * Set local variables, will be available only in this instance
	 *
	 * @param string|int|null $index
	 * @param mixed           $newval
	 *
	 * @return void
	 */
	public function offsetSet($index, $newval)
	{
		if ($index === null)
		{
			$this[] = $newval;
		}
		else
		{
			parent::offsetSet($index, $newval);
		}
	}

	/**
	 * Return null if no value
	 *
	 * @param mixed $index
	 *
	 * @return mixed|null
	 */
	public function offsetGet($index)
	{
		if (parent::offsetExists($index))
		{
			return parent::offsetGet($index);
		}
		if (isset(self::$global[$index]))
		{
			return self::$global[$index];
		}

		return null;
	}

	/**
	 * Get a response by name
	 *
	 * @param string $name
	 *
	 * @return PheryResponse|null
	 */
	public static function get_response($name)
	{
		if (isset(self::$responses[$name]) && self::$responses[$name] instanceof PheryResponse)
		{
			return self::$responses[$name];
		}

		return null;
	}

	/**
	 * Get merged response data as a new PheryResponse.
	 * This method works like a constructor if the previous response was destroyed
	 *
	 * @param string $name Name of the merged response
	 * @return PheryResponse|null
	 */
	public function get_merged($name)
	{
		if (isset($this->merged[$name]))
		{
			if (isset(self::$responses[$name]))
			{
				return self::$responses[$name];
			}
			$response = new PheryResponse;
			$response->data = $this->merged[$name];
			return $response;
		}
		return null;
	}

	/**
	 * Same as phery.remote()
	 *
	 * @param string  $remote     Function
	 * @param array   $args       Arguments to pass to the
	 * @param array   $attr       Here you may set like method, target, type, cache, proxy
	 * @param boolean $directCall Setting to false returns the jQuery object, that can bind
	 *                            events, append to DOM, etc
	 *
	 * @return PheryResponse
	 */
	public function phery_remote($remote, $args = array(), $attr = array(), $directCall = true)
	{
		$this->set_internal_counter('-');

		return $this->cmd(0xff, array(
			$remote,
			$args,
			$attr,
			$directCall
		));
	}

	/**
	 * Set a global variable, that can be accessed directly through window object,
	 * can set properties inside objects if you pass an array as the variable.
	 * If it doesn't exist it will be created
	 *
	 * <code>
	 * // window.customer_info = {'name': 'John','surname': 'Doe', 'age': 39}
	 * PheryResponse::factory()->set_var('customer_info', array('name' => 'John', 'surname' => 'Doe', 'age' => 39));
	 * </code>
	 *
	 * <code>
	 * // window.customer_info.name = 'John'
	 * PheryResponse::factory()->set_var(array('customer_info','name'), 'John');
	 * </code>
	 *
	 * @param string|array $variable Global variable name
	 * @param mixed        $data     Any data
	 * @return PheryResponse
	 */
	public function set_var($variable, $data)
	{
		$this->last_selector = null;

		if (!empty($data) && is_array($data))
		{
			foreach ($data as $name => $d)
			{
				$data[$name] = self::typecast($d, true, true);
			}
		}
		else
		{
			$data = self::typecast($data, true, true);
		}

		return $this->cmd(9, array(
			!is_array($variable) ? array($variable) : $variable,
			array($data)
		));
	}

	/**
	 * Delete a global variable, that can be accessed directly through window, can unset object properties,
	 * if you pass an array
	 *
	 * <code>
	 * PheryResponse::factory()->unset('customer_info');
	 * </code>
	 *
	 * <code>
	 * PheryResponse::factory()->unset(array('customer_info','name')); // translates to delete customer_info['name']
	 * </code>
	 *
	 * @param string|array $variable Global variable name
	 * @return PheryResponse
	 */
	public function unset_var($variable)
	{
		$this->last_selector = null;

		return $this->cmd(9, array(
			!is_array($variable) ? array($variable) : $variable,
		));
	}

	/**
	 * Create a new PheryResponse instance for chaining, fast and effective for one line returns
	 *
	 * <code>
	 * function answer($data)
	 * {
	 *  return
	 *         PheryResponse::factory('a#link-'.$data['rel'])
	 *         ->attr('href', '#')
	 *         ->alert('done');
	 * }
	 * </code>
	 *
	 * @param string $selector optional
	 * @param array $constructor Same as $('&lt;p/&gt;', {})
	 *
	 * @static
	 * @return PheryResponse
	 */
	public static function factory($selector = null, array $constructor = array())
	{
		return new PheryResponse($selector, $constructor);
	}

	/**
	 * Remove a batch of calls for a selector. Won't remove for merged responses.
	 * Passing an integer, will remove commands, like dump_vars, call, etc, in the
	 * order they were called
	 *
	 * @param string|int $selector
	 *
	 * @return PheryResponse
	 */
	public function remove_selector($selector)
	{
		if ((is_string($selector) || is_int($selector)) && isset($this->data[$selector]))
		{
			unset($this->data[$selector]);
		}

		return $this;
	}

	/**
	 * Access the current calling DOM element without the need for IDs, names, etc
	 * Use $response->this (as a property) instead
	 *
	 * @deprecated
	 * @return PheryResponse
	 */
	public function this()
	{
		return $this->this;
	}

	/**
	 * Merge another response to this one.
	 * Selectors with the same name will be added in order, for example:
	 *
	 * <code>
	 * function process()
	 * {
	 *      $response = PheryResponse::factory('a.links')->remove();
	 *      // $response will execute before
	 *      // there will be no more "a.links" in the DOM, so the addClass() will fail silently
	 *      // to invert the order, merge $response to $response2
	 *      $response2 = PheryResponse::factory('a.links')->addClass('red');
	 *      return $response->merge($response2);
	 * }
	 * </code>
	 *
	 * @param PheryResponse|string $phery_response Another PheryResponse object or a name of response
	 *
	 * @return PheryResponse
	 */
	public function merge($phery_response)
	{
		if (is_string($phery_response))
		{
			if (isset(self::$responses[$phery_response]))
			{
				$this->merged[self::$responses[$phery_response]->name] = self::$responses[$phery_response]->data;
			}
		}
		elseif ($phery_response instanceof PheryResponse)
		{
			$this->merged[$phery_response->name] = $phery_response->data;
		}

		return $this;
	}

	/**
	 * Remove a previously merged response, if you pass TRUE will removed all merged responses
	 *
	 * @param PheryResponse|string|boolean $phery_response
	 *
	 * @return PheryResponse
	 */
	public function unmerge($phery_response)
	{
		if (is_string($phery_response))
		{
			if (isset(self::$responses[$phery_response]))
			{
				unset($this->merged[self::$responses[$phery_response]->name]);
			}
		}
		elseif ($phery_response instanceof PheryResponse)
		{
			unset($this->merged[$phery_response->name]);
		}
		elseif ($phery_response === true)
		{
			$this->merged = array();
		}

		return $this;
	}

	/**
	 * Pretty print to console.log
	 *
	 * @param mixed $vars,... Any var
	 *
	 * @return PheryResponse
	 */
	public function print_vars($vars)
	{
		$this->last_selector = null;

		$args = array();
		foreach (func_get_args() as $name => $arg)
		{
			if (is_object($arg))
			{
				$arg = get_object_vars($arg);
			}
			$args[$name] = array(var_export($arg, true));
		}

		return $this->cmd(6, $args);
	}

	/**
	 * Dump var to console.log
	 *
	 * @param mixed $vars,... Any var
	 *
	 * @return PheryResponse
	 */
	public function dump_vars($vars)
	{
		$this->last_selector = null;
		$args = array();
		foreach (func_get_args() as $index => $func)
		{
			if ($func instanceof PheryResponse || $func instanceof PheryFunction)
			{
				$args[$index] = array(self::typecast($func, true, true));
			}
			elseif (is_object($func))
			{
				$args[$index] = array(get_object_vars($func));
			}
			else
			{
				$args[$index] = array($func);
			}
		}

		return $this->cmd(6, $args);
	}

	/**
	 * Sets the jQuery selector, so you can chain many calls to it.
	 *
	 * <code>
	 * PheryResponse::factory()
	 * ->jquery('.slides')
	 * ->fadeTo(0,0)
	 * ->css(array('top' => '10px', 'left' => '90px'));
	 * </code>
	 *
	 * For creating an element
	 *
	 * <code>
	 * PheryResponse::factory()
	 * ->jquery('.slides', array(
	 *   'css' => array(
	 *     'left': '50%',
	 *     'textDecoration': 'underline'
	 *   )
	 * ))
	 * ->appendTo('body');
	 * </code>
	 *
	 * @param string $selector Sets the current selector for subsequent chaining, like you would using $()
	 * @param array $constructor Only available if you are creating a new element, like $('&lt;p/&gt;', {'class': 'classname'})
	 *
	 * @return PheryResponse
	 */
	public function jquery($selector, array $constructor = array())
	{
		if ($selector)
		{
			$this->last_selector = $selector;
		}

		if (isset($selector) && is_string($selector) && count($constructor) && substr($selector, 0, 1) === '<')
		{
			foreach ($constructor as $name => $value)
			{
				$this->$name($value);
			}
		}
		return $this;
	}

	/**
	 * Shortcut/alias for jquery($selector) Passing null works like jQuery.func
	 *
	 * @param string $selector Sets the current selector for subsequent chaining
	 * @param array $constructor Only available if you are creating a new element, like $('&lt;p/&gt;', {})
	 *
	 * @return PheryResponse
	 */
	public function j($selector, array $constructor = array())
	{
		return $this->jquery($selector, $constructor);
	}

	/**
	 * Show an alert box
	 *
	 * @param string $msg Message to be displayed
	 *
	 * @return PheryResponse
	 */
	public function alert($msg)
	{
		if (is_array($msg))
		{
			$msg = join("\n", $msg);
		}

		$this->last_selector = null;

		return $this->cmd(1, array(self::typecast($msg, true)));
	}

	/**
	 * Pass JSON to the browser
	 *
	 * @param mixed $obj Data to be encoded to json (usually an array or a JsonSerializable)
	 *
	 * @return PheryResponse
	 */
	public function json($obj)
	{
		$this->last_selector = null;

		return $this->cmd(4, array(json_encode($obj)));
	}

	/**
	 * Remove the current jQuery selector
	 *
	 * @param string|boolean $selector Set a selector
	 *
	 * @return PheryResponse
	 */
	public function remove($selector = null)
	{
		return $this->cmd('remove', array(), $selector);
	}

	/**
	 * Add a command to the response
	 *
	 * @param int|string|array $cmd      Integer for command, see Phery.js for more info
	 * @param array            $args     Array to pass to the response
	 * @param string           $selector Insert the jquery selector
	 *
	 * @return PheryResponse
	 */
	public function cmd($cmd, array $args = array(), $selector = null)
	{
		if (!$this->matched)
		{
			$this->matched = true;
			return $this;
		}

		$selector = Phery::coalesce($selector, $this->last_selector);

		if ($selector === null)
		{
			$this->data['0'.($this->internal_cmd_count++)] = array(
				'c' => $cmd,
				'a' => $args
			);
		}
		else
		{
			if (!isset($this->data[$selector]))
			{
				$this->data[$selector] = array();
			}
			$this->data[$selector][] = array(
				'c' => $cmd,
				'a' => $args
			);
		}

		if ($this->restore !== null)
		{
			$this->last_selector = $this->restore;
			$this->restore = null;
		}

		return $this;
	}

	/**
	 * Set the attribute of a jQuery selector
	 *
	 * Example:
	 *
	 * <code>
	 * PheryResponse::factory()
	 * ->attr('href', 'http://url.com', 'a#link-' . $args['id']);
	 * </code>
	 *
	 * @param string $attr     HTML attribute of the item
	 * @param string $data     Value
	 * @param string $selector [optional] Provide the jQuery selector directly
	 *
	 * @return PheryResponse
	 */
	public function attr($attr, $data, $selector = null)
	{
		return $this->cmd('attr', array(
			$attr,
			$data
		), $selector);
	}

	/**
	 * Trigger the phery:exception event on the calling element
	 * with additional data
	 *
	 * @param string $msg  Message to pass to the exception
	 * @param mixed  $data Any data to pass, can be anything
	 *
	 * @return PheryResponse
	 */
	public function exception($msg, $data = null)
	{
		$this->last_selector = null;

		return $this->cmd(7, array(
			$msg,
			$data
		));
	}

	/**
	 * Call a javascript function.
	 * Warning: calling this function will reset the selector jQuery selector previously stated
	 *
	 * @param string|array $func_name Function name. If you pass a string, it will be accessed on window.func.
	 *                                If you pass an array, it will access a member of an object, like array('object', 'property', 'function')
	 * @param              mixed      $args,... Any additional arguments to pass to the function
	 *
	 * @return PheryResponse
	 */
	public function call($func_name, $args = null)
	{
		$args = func_get_args();
		array_shift($args);
		$this->last_selector = null;

		return $this->cmd(2, array(
			!is_array($func_name) ? array($func_name) : $func_name,
			$args
		));
	}

	/**
	 * Call 'apply' on a javascript function.
	 * Warning: calling this function will reset the selector jQuery selector previously stated
	 *
	 * @param string|array $func_name Function name
	 * @param array        $args      Any additional arguments to pass to the function
	 *
	 * @return PheryResponse
	 */
	public function apply($func_name, array $args = array())
	{
		$this->last_selector = null;

		return $this->cmd(2, array(
			!is_array($func_name) ? array($func_name) : $func_name,
			$args
		));
	}

	/**
	 * Clear the selected attribute.
	 * Alias for attr('attribute', '')
	 *
	 * @see attr()
	 *
	 * @param string $attr     Name of the DOM attribute to clear, such as 'innerHTML', 'style', 'href', etc not the jQuery counterparts
	 * @param string $selector [optional] Provide the jQuery selector directly
	 *
	 * @return PheryResponse
	 */
	public function clear($attr, $selector = null)
	{
		return $this->attr($attr, '', $selector);
	}

	/**
	 * Set the HTML content of an element.
	 * Automatically typecasted to string, so classes that
	 * respond to __toString() will be converted automatically
	 *
	 * @param string $content
	 * @param string $selector [optional] Provide the jQuery selector directly
	 *
	 * @return PheryResponse
	 */
	public function html($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		return $this->cmd('html', array(
			self::typecast($content, true, true)
		), $selector);
	}

	/**
	 * Set the text of an element.
	 * Automatically typecasted to string, so classes that
	 * respond to __toString() will be converted automatically
	 *
	 * @param string $content
	 * @param string $selector [optional] Provide the jQuery selector directly
	 *
	 * @return PheryResponse
	 */
	public function text($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		return $this->cmd('text', array(
			self::typecast($content, true, true)
		), $selector);
	}

	/**
	 * Compile a script and call it on-the-fly.
	 * There is a closure on the executed function, so
	 * to reach out global variables, you need to use window.variable
	 * Warning: calling this function will reset the selector jQuery selector previously set
	 *
	 * @param string|array $script Script content. If provided an array, it will be joined with \n
	 *
	 * <pre>
	 * PheryResponse::factory()
	 * ->script(array("if (confirm('Are you really sure?')) $('*').remove()"));
	 * </pre>
	 *
	 * @return PheryResponse
	 */
	public function script($script)
	{
		$this->last_selector = null;

		if (is_array($script))
		{
			$script = join("\n", $script);
		}

		return $this->cmd(3, array(
			$script
		));
	}

	/**
	 * Access a global object path
	 *
	 * @param string|string[] $namespace             For accessing objects, like $.namespace.function() or
	 *                                               document.href. if you want to access a global variable,
	 *                                               use array('object','property'). You may use a mix of getter/setter
	 *                                               to apply a global value to a variable
	 *
	 * <pre>
	 * PheryResponse::factory()->set_var(array('obj','newproperty'),
	 *      PheryResponse::factory()->access(array('other_obj','enabled'))
	 * );
	 * </pre>
	 *
	 * @param boolean         $new                   Create a new instance of the object, acts like "var v = new JsClass"
	 *                                               only works on classes, don't try to use new on a variable or a property
	 *                                               that can't be instantiated
	 *
	 * @return PheryResponse
	 */
	public function access($namespace, $new = false)
	{
		$last = $this->set_internal_counter('+');

		return $this->cmd(!is_array($namespace) ? array($namespace) : $namespace, array($new, $last));
	}

	/**
	 * Render a view to the container previously specified
	 *
	 * @param string $html HTML to be replaced in the container
	 * @param array  $data Array of data to pass to the before/after functions set on Phery.view
	 *
	 * @see Phery.view() on JS
	 * @return PheryResponse
	 */
	public function render_view($html, $data = array())
	{
		$this->last_selector = null;

		if (is_array($html))
		{
			$html = join("\n", $html);
		}

		return $this->cmd(5, array(
			self::typecast($html, true, true),
			$data
		));
	}

	/**
	 * Creates a redirect
	 *
	 * @param string        $url      Complete url with http:// (according to W3 http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30)
	 * @param bool|string   $view     Internal means that phery will cancel the
	 *                                current DOM manipulation and commands and will issue another
	 *                                phery.remote to the location in url, useful if your PHP code is
	 *                                issuing redirects but you are using AJAX views.
	 *                                Passing false will issue a browser redirect
	 *
	 * @return PheryResponse
	 */
	public function redirect($url, $view = false)
	{
		if ($view === false && !preg_match('#^https?\://#i', $url))
		{
			$_url = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
			$start = substr($url, 0, 1);

			if (!empty($start))
			{
				if ($start === '?')
				{
					$_url .= str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
				}
				elseif ($start !== '/')
				{
					$_url .= '/';
				}
			}
			$_url .= $url;
		}
		else
		{
			$_url = $url;
		}

		$this->last_selector = null;

		if ($view !== false)
		{
			return $this->reset_response()->cmd(8, array(
				$_url,
				$view
			));
		}
		else
		{
			return $this->cmd(8, array(
				$_url,
				false
			));
		}
	}

	/**
	 * Prepend string/HTML to target(s)
	 *
	 * @param string $content  Content to be prepended to the selected element
	 * @param string $selector [optional] Optional jquery selector string
	 *
	 * @return PheryResponse
	 */
	public function prepend($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		return $this->cmd('prepend', array(
			self::typecast($content, true, true)
		), $selector);
	}

	/**
	 * Clear all the selectors and commands in the current response.
	 * @return PheryResponse
	 */
	public function reset_response()
	{
		$this->data = array();
		$this->last_selector = null;
		$this->merged = array();
		return $this;
	}

	/**
	 * Append string/HTML to target(s)
	 *
	 * @param string $content  Content to be appended to the selected element
	 * @param string $selector [optional] Optional jquery selector string
	 *
	 * @return PheryResponse
	 */
	public function append($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		return $this->cmd('append', array(
			self::typecast($content, true, true)
		), $selector);
	}

	/**
	 * Include a stylesheet in the head of the page
	 *
	 * @param array $path An array of stylesheets, comprising of 'id' => 'path'
	 * @param bool $replace Replace any existing ids
	 * @return PheryResponse
	 */
	public function include_stylesheet(array $path, $replace = false)
	{
		$this->last_selector = null;

		return $this->cmd(10, array(
			'c',
			$path,
			$replace
		));
	}

	/**
	 * Include a script in the head of the page
	 *
	 * @param array $path An array of scripts, comprising of 'id' => 'path'
	 * @param bool $replace Replace any existing ids
	 * @return PheryResponse
	 */
	public function include_script($path, $replace = false)
	{
		$this->last_selector = null;

		return $this->cmd(10, array(
			'j',
			$path,
			$replace
		));
	}

	/**
	 * Magically map to any additional jQuery function.
	 * To reach this magically called functions, the jquery() selector must be called prior
	 * to any jquery specific call
	 *
	 * @param string $name
	 * @param array $arguments
	 *
	 * @see jquery()
	 * @see j()
	 * @return PheryResponse
	 */
	public function __call($name, $arguments)
	{
		if ($this->last_selector)
		{
			if (count($arguments))
			{
				foreach ($arguments as $_name => $argument)
				{
					$arguments[$_name] = self::typecast($argument, true, true);
				}

				$this->cmd($name, $arguments);
			}
			else
			{
				$this->cmd($name);
			}

		}

		return $this;
	}

	/**
	 * Magic functions
	 *
	 * @param string $name
	 * @return PheryResponse
	 */
	function __get($name)
	{
		$name = strtolower($name);

		if ($name === 'this')
		{
			$this->set_internal_counter('~');
		}
		elseif ($name === 'document')
		{
			$this->jquery('document');
		}
		elseif ($name === 'window')
		{
			$this->jquery('window');
		}
		elseif ($name === 'jquery')
		{
			$this->set_internal_counter('#');
		}
		else
		{
			$this->access($name);
		}

		return $this;
	}

	/**
	 * Convert, to a maximum depth, nested responses, and typecast int properly
	 *
	 * @param mixed $argument The value
	 * @param bool $toString Call class __toString() if possible, and typecast int correctly
	 * @param bool $nested Should it look for nested arrays and classes?
	 * @param int $depth Max depth
	 * @return mixed
	 */
	protected static function typecast($argument, $toString = true, $nested = false, $depth = 4)
	{
		if ($nested)
		{
			$depth--;
			if ($argument instanceof PheryResponse)
			{
				$argument = array('PR' => $argument->process_merged());
			}
			elseif ($argument instanceof PheryFunction)
			{
				$argument = array('PF' => $argument->compile());
			}
			elseif ($depth > 0 && is_array($argument))
			{
				foreach ($argument as $name => $arg) {
					$argument[$name] = self::typecast($arg, $toString, $nested, $depth);
				}
			}
		}

		if ($toString && !empty($argument))
		{
			if (is_string($argument) && ctype_digit($argument))
			{
				$argument = (int)$argument;
			}
			elseif (is_object($argument))
			{
				$class = get_class($argument);
				if ($class !== false)
				{
					$rc = new ReflectionClass(get_class($argument));
					if ($rc->hasMethod('__toString'))
					{
						$argument = "{$argument}";
					}
					else
					{
						$argument = json_decode(json_encode($argument), true);
					}
				}
				else
				{
					$argument = json_decode(json_encode($argument), true);
				}
			}
		}

		return $argument;
	}

	/**
	 * Process merged responses
	 * @return array
	 */
	protected function process_merged()
	{
		$data = $this->data;

		if (empty($data) && $this->last_selector !== null && !$this->is_special_selector('#'))
		{
			$data[$this->last_selector] = array();
		}

		foreach ($this->merged as $r)
		{
			foreach ($r as $selector => $response)
			{
				if (!ctype_digit($selector))
				{
					if (isset($data[$selector]))
					{
						$data[$selector] = array_merge_recursive($data[$selector], $response);
					}
					else
					{
						$data[$selector] = $response;
					}
				}
				else
				{
					$selector = (int)$selector;
					while (isset($data['0'.$selector]))
					{
						$selector++;
					}
					$data['0'.$selector] = $response;
				}
			}
		}

		return $data;
	}

	/**
	 * Return the JSON encoded data
	 * @return string
	 */
	public function render()
	{
		return json_encode((object)$this->process_merged());
	}

	/**
	 * Output the current answer as a load directive, as a ready-to-use string
	 *
	 * <code>
	 *
	 * </code>
	 *
	 * @param bool $echo Automatically echo the javascript instead of returning it
	 * @return string
	 */
	public function inline_load($echo = false)
	{
		$body = addcslashes($this->render(), "\\'");

		$javascript = "phery.load('{$body}');";

		if ($echo)
		{
			echo $javascript;
		}

		return $javascript;
	}

	/**
	 * Return the JSON encoded data
	 * if the object is typecasted as a string
	 * @return string
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * Initialize the instance from a serialized state
	 *
	 * @param string $serialized
	 * @throws PheryException
	 * @return PheryResponse
	 */
	public function unserialize($serialized)
	{
		$obj = json_decode($serialized, true);
		if ($obj && is_array($obj) && json_last_error() === JSON_ERROR_NONE)
		{
			$this->exchangeArray($obj['this']);
			$this->data = (array)$obj['data'];
			$this->set_response_name((string)$obj['name']);
			$this->merged = (array)$obj['merged'];
		}
		else
		{
			throw new PheryException('Invalid data passed to unserialize');
		}
		return $this;
	}

	/**
	 * Serialize the response in JSON
	 * @return string|bool
	 */
	public function serialize()
	{
		return json_encode(array(
			'data' => $this->data,
			'this' => $this->getArrayCopy(),
			'name' => $this->name,
			'merged' => $this->merged,
		));
	}

	/**
	 * Determine if the last selector or the selector provided is an special
	 *
	 * @param string $type
	 * @param string $selector
	 * @return boolean
	 */
	protected function is_special_selector($type = null, $selector = null)
	{
		$selector = Phery::coalesce($selector, $this->last_selector);

		if ($selector && preg_match('/\{([\D]+)\d+\}/', $selector, $matches))
		{
			if ($type === null)
			{
				return true;
			}

			return ($matches[1] === $type);
		}

		return false;
	}
}

/**
 * Create an anonymous function for use on Javascript callbacks
 * @package    Phery
 */
class PheryFunction {

	/**
	 * Parameters that will be replaced inside the response
	 * @var array
	 */
	protected $parameters = array();
	/**
	 * The function string itself
	 * @var array
	 */
	protected $value = null;

	/**
	 * Sets new raw parameter to be passed, that will be eval'ed.
	 * If you don't pass the function(){ } it will be appended
	 *
	 * <code>
	 * $raw = new PheryFunction('function($val){ return $val; }');
	 * // or
	 * $raw = new PheryFunction('alert("done");'); // turns into function(){ alert("done"); }
	 * </code>
	 *
	 * @param   string|array  $value      Raw function string. If you pass an array,
	 *                                    it will be joined with a line feed \n
	 * @param   array         $parameters You can pass parameters that will be replaced
	 *                                    in the $value when compiling
	 */
	public function __construct($value, $parameters = array())
	{
		if (!empty($value))
		{
			// Set the expression string
			if (is_array($value))
			{
				$this->value = join("\n", $value);
			}
			elseif (is_string($value))
			{
				$this->value = $value;
			}

			if (!preg_match('/^\s*function/im', $this->value))
			{
				$this->value = 'function(){' . $this->value . '}';
			}

			$this->parameters = $parameters;
		}
	}

	/**
	 * Bind a variable to a parameter.
	 *
	 * @param   string  $param  parameter key to replace
	 * @param   mixed   $var    variable to use
	 * @return  PheryFunction
	 */
	public function bind($param, & $var)
	{
		$this->parameters[$param] =& $var;

		return $this;
	}

	/**
	 * Set the value of a parameter.
	 *
	 * @param   string  $param  parameter key to replace
	 * @param   mixed   $value  value to use
	 * @return  PheryFunction
	 */
	public function param($param, $value)
	{
		$this->parameters[$param] = $value;

		return $this;
	}

	/**
	 * Add multiple parameter values.
	 *
	 * @param   array   $params list of parameter values
	 * @return  PheryFunction
	 */
	public function parameters(array $params)
	{
		$this->parameters = $params + $this->parameters;

		return $this;
	}

	/**
	 * Get the value as a string.
	 *
	 * @return  string
	 */
	public function value()
	{
		return (string) $this->value;
	}

	/**
	 * Return the value of the expression as a string.
	 *
	 * <code>
	 *     echo $expression;
	 * </code>
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return $this->value();
	}

	/**
	 * Compile function and return it. Replaces any parameters with
	 * their given values.
	 *
	 * @return  string
	 */
	public function compile()
	{
		$value = $this->value();

		if ( ! empty($this->parameters))
		{
			$params = $this->parameters;
			$value = strtr($value, $params);
		}

		return $value;
	}

	/**
	 * Static instantation for PheryFunction
	 *
	 * @param string|array $value
	 * @param array        $parameters
	 *
	 * @return PheryFunction
	 */
	public static function factory($value, $parameters = array())
	{
		return new PheryFunction($value, $parameters);
	}
}

/**
 * Exception class for Phery specific exceptions
 * @package    Phery
 */
class PheryException extends Exception {

}
