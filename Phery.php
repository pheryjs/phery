<?php
/* * **** BEGIN LICENSE BLOCK *****
 * Version: MPL 2.0
 *
 * This Source Code Form is subject to the terms of the
 * Mozilla Public License, v. 2.0. If a copy of the MPL was
 * not distributed with this file, You can obtain one at
 * http://mozilla.org/MPL/2.0/. *
 *
 * ***** END LICENSE BLOCK ***** */

/**
 * @package PheryPackage
 * @link https://github.com/pocesar/phery
 * @author Paulo Cesar
 * @version 1.0
 * @license http://www.mozilla.org/MPL/ Mozilla Public License
 * @subpackage Phery
 */
class Phery implements ArrayAccess {

	/**
	 * Exception on callback() function
	 * @see callback()
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
	 * Exception on static functions
	 * @see Phery::link_to
	 * @see Phery::select_for
	 * @see Phery::form_for
	 */
	const ERROR_TO = 3;

	/**
	 * The functions registered
	 * @var array
	 */
	private $functions = array();
	/**
	 * The callbacks registered
	 * @var array
	 */
	private $callbacks = array();
	/**
	 * The callback data to be passed to callbacks and responses
	 * @var array
	 */
	private $data = array();
	/**
	 * Static instance for singleton
	 * @staticvar Phery $instance
	 */
	private static $instance = null;
	/**
	 * Will call the functions defined in this variable even
	 * if it wasn't sent by AJAX, use it wisely. (good for SEO though)
	 * @var array
	 */
	public $respond_to_post = array();
	/**
	 * Hold the answers for answer_for function
	 * @see Phery::answer_for
	 * @var array
	 */
	private $answers = array();
	/**
	 * Render view function
	 * @var array
	 */
	private $views = array();
	/**
	 * Config
	 * <pre>
	 * 'exit_allowed' (boolean)
	 * 'no_stripslashes' (boolean)
	 * 'exceptions' (boolean)
	 * 'respond_to_post' (array)
	 * 'compress' (boolean)
	 * </pre>
	 * @var array
	 * @see config()
	 */
	private $config = null;

	/**
	 * Construct the new Phery instance
	 */
	public function __construct(array $config = array())
	{
		$this->callbacks = array(
			'before' => array(),
			'after' => array()
		);

		$config = array_merge_recursive(
			array(
			'exit_allowed' => true,
			'no_stripslashes' => false,
			'exceptions' => false,
			'respond_to_post' => array(),
			'compress' => false,
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
	 * @param array $callbacks
	 * <pre>
	 * array(
	 *
	 * 	// Set a function to be called BEFORE
	 * 	// processing the request, if it's an
	 * 	// AJAX to be processed request, can be
	 * 	// an array of callbacks
	 *
	 * 	'before' => array|function,
	 *
	 * 	// Set a function to be called AFTER
	 * 	// processing the request, if it's an AJAX
	 * 	// processed request, can be an array of
	 * 	// callbacks
	 *
	 * 	'after' => array|function
	 * );
	 * </pre>
	 * The callback function should be
	 * <pre>
	 *
	 * // $additional_args is passed using the callback_data() function, in this case, a before callback
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
	 * 	 $PheryResponse->merge(PheryResponse::factory('#loading')->fadeOut());
	 *   return true;
	 * }
	 * </pre>
	 * Returning false on the callback will make the process() phase to RETURN, but won't exit.
	 * You may manually exit on the after callback if desired
	 * Any data that should be modified will be inside $_POST['args'] (can be accessed freely on 'before',
	 * will be passed to the AJAX function)
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
	 * @param Phery $phery Instance
	 * @param string $exception
	 * @param integer $code
	 */
	private static function exception($phery, $exception, $code)
	{
		if ($phery instanceof Phery && $phery->config['exceptions'] === true)
		{
			throw new PheryException($exception, $code);
		}
	}

	/**
	 * Set any data to pass to the callbacks
	 * @param mixed ... Parameters, can be anything
	 * @return Phery
	 */
	public function data()
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
	 * Encode PHP code to put inside data-args, usually for updating the data there
	 * @param mixed $data Any data that can be converted using json_encode
	 * @return string Return json_encode'd and htmlentities'd string
	 */
	public static function args($data, $encoding = 'UTF-8')
	{
		return htmlentities(json_encode($data), ENT_COMPAT, $encoding, false);
	}

	/**
	 * Check if the current call is an ajax call
	 * @param bool $is_phery Check if is an ajax call and a phery specific call
	 * @static
	 * @return bool
	 */
	public static function is_ajax($is_phery = false)
	{
		switch ($is_phery)
		{
			case true:
				return (bool) (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
					strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0 &&
					strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
					!empty($_SERVER['HTTP_X_PHERY']));
			case false:
				return (bool) (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
					strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0);
		}
	}

	protected function stripslashes_recursive($variable)
	{
		if (is_string($variable))
		{
			return stripslashes($variable);
		}

		if (is_array($variable))
		{
			foreach ($variable as $i => $value)
			{
				$variable[$i] = $this->stripslashes_recursive($value);
			}
		}

		return $variable;
	}

	/**
	 * Return the data associatated with a processed POST call
	 * @param string $alias The name of the alias for the process function
	 * @param mixed $default Any data that should be returned if there's no answer, defaults to null
	 * @return mixed Return $default if no data available, defaults to NULL
	 */
	public function answer_for($alias, $default = NULL)
	{
		if (isset($this->answers[$alias]) && !empty($this->answers[$alias]))
		{
			return $this->answers[$alias];
		}
		return $default;
	}

	public static function error_handler($errno, $errstr, $errfile, $errline)
	{
		$response =
			PheryResponse::factory()->exception($errstr, array(
			'code' => $errno,
			'file' => $errfile,
			'line' => $errline
			));

		self::respond($response);
		exit;
	}

	public static function shutdown_handler()
	{
		ob_end_flush();
	}

	private static function respond($response, $compress = false)
	{
		if ($response instanceof PheryResponse)
		{
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: 0');
			header('Content-Type: application/json');
			header('Connection: close');
		}

		$response = "{$response}";

		if ($compress &&
			strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') &&
			strlen($response) > 80)
		{
			register_shutdown_function('Phery::shutdown_handler');
			ob_start('ob_gzhandler');
			echo $response;
		}
		else
		{
			echo $response;
		}
	}

	/**
	 * Set the callback for view portions, as defined in Phery.view()
	 * @param array $views Array consisting of array('#id_of_view' => callback)
	 * The callback is like a normal phery callback, but the second parameter
	 * receives different data. But it MUST always return a PheryResponse with
	 * render_view(). You can do any manipulation like you would in regular
	 * callbacks. If you want to manipulate the DOM AFTER it was rendered, do it
	 * javascript side, using the afterHtml callback when setting up the views.
	 * <pre>
	 * Phery::instance()->views(array(
	 * 	'#container' => function($data, $params){
	 *    return
	 * 			PheryResponse::factory()
	 * 			->render_view('html', array('extra data like titles, menus, etc'));
	 *  }
	 * ));
	 * </pre>
	 * @return Phery
	 */
	public function views(array $views)
	{
		foreach ($views as $container => $callback)
		{
			if (is_callable($callback))
			{
				if ($container[0] !== '#')
				{
					$container = '#'.$container;
				}
				$this->views[$container] = $callback;
			}
		}
		return $this;
	}

	private function process_data($respond_to_post, $last_call)
	{
		$response = null;

		if (empty($_POST['phery']))
		{
			return self::exception($this, 'Non-Phery AJAX request', self::ERROR_PROCESS);
		}

		if (!empty($_GET['_']))
		{
			$this->data['requested'] = $_GET['_'];
			unset($_GET['_']);
		}

		if (!empty($_GET['_try_count']))
		{
			$this->data['retries'] = $_GET['_try_count'];
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
			$this->data['submit_id'] = '#'."{$_POST['phery']['submit_id']}";
		}

		if ($remote !== false)
		{
			$this->data['remote'] = $remote;

			if ($respond_to_post === true)
			{
				if ($this->config['no_stripslashes'] === false)
				{
					$args = $this->stripslashes_recursive($_POST);
				}
				else
				{
					$args = $_POST;
				}

				unset($args['phery']['remote']);
			}
		}

		if (!empty($_POST['args']))
		{
			if ($this->config['no_stripslashes'] === false)
			{
				$args = $this->stripslashes_recursive($_POST['args']);
			}
			else
			{
				$args = $_POST['args'];
			}

			if ($last_call === true || $respond_to_post === true)
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
				if (($args = call_user_func($func, $args, $this->data)) === false)
				{
					return;
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

				if ($this->config['error_reporting'] !== false)
				{
					set_error_handler('Phery::error_handler', $this->config['error_reporting']);

					$response = call_user_func($this->functions[$remote], $args, $this->data);

					restore_error_handler();
				}
				else
				{
					$response = call_user_func($this->functions[$remote], $args, $this->data);
				}

				foreach ($this->callbacks['after'] as $func)
				{
					if (call_user_func($func, $args, $this->data, $response) === false)
					{
						return;
					}
				}

				$_POST['phery']['remote'] = $remote;

				if ($respond_to_post === false)
				{
					self::respond($response, $this->config['compress']);
				}
				else
				{
					$this->answers[$remote] = $response;
				}
			}
			else
			{
				if ($last_call)
				{
					self::exception($this, 'The function provided "'.($remote).'" isn\'t set', self::ERROR_PROCESS);
				}
			}
		}
		else
		{
			if (!empty($this->data['view']) && isset($this->views[$this->data['view']]))
			{
				$response = call_user_func($this->views[$this->data['view']], $args, $this->data);

				self::respond($response, $this->config['compress']);
			}
			else
			{
				if ($last_call)
				{
					self::exception($this, 'The function provided "'.($remote).'" isn\'t set', self::ERROR_PROCESS);
				}
			}
		}

		if ($respond_to_post === false)
		{
			if ($this->config['exit_allowed'] === true)
			{
				if ($last_call || $response !== null)
				{
					if ($response === null && $last_call)
					{
						self::respond(Phery::factory());
					}
					exit;
				}
			}
		}
	}

	/**
	 * Process the AJAX requests if any
	 * @param bool $last_call Set this to false if any other further calls
	 * to process() will happen, otherwise it will exit
	 * @return void
	 */
	public function process($last_call = true)
	{
		if (self::is_ajax(true))
		{
			// AJAX call
			$this->process_data(false, $last_call);
		}
		elseif (count($this->respond_to_post) &&
			strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
			isset($_POST['phery']) && isset($_POST['phery']['remote']) &&
			in_array($_POST['phery']['remote'], $this->respond_to_post))
		{
			// Regular processing, respond to post, pass the $_POST variable to the function anyway
			$this->process_data(true, false);
		}
	}

	/**
	 * Config the current instance of Phery
	 * @param string|array $config Associative array containing the following options
	 * <pre>
	 * array(
	 *
	 * 	// Defaults to true, stop further script execution
	 * 	'exit_allowed' => true|false,
	 *
	 * 	// Don't apply stripslashes on the args
	 * 	'no_stripslashes' => true|false,
	 *
	 * 	// Throw exceptions on errors
	 * 	'exceptions' => true|false,
	 *
	 * 	// Set the functions that will be called even if is a
	 * 	// POST but not an AJAX call
	 * 	'respond_to_post' => array('function-alias-1','function-alias-2'),
	 *
	 * 	// Enable/disable GZIP/DEFLATE compression, depending on the browser support.
	 * 	// Don't enable it if you are using Apache DEFLATE/GZIP, or zlib.output_compression
	 * 	// Most of the time, compression will hide exceptions, because it will output plain
	 * 	// text while the content-type is gzip
	 * 	'compress' => true|false,
	 *
	 * 	// Error reporting temporarily using error_reporting(). 'false' disables
	 * 	// the error_reporting and wont try to catch any error.
	 * 	// Anything else than false will throw a PheryResponse->exception() with
	 * 	// the message
	 * 	'error_reporting' => false|E_ALL|E_DEPRECATED|...
	 *
	 * );
	 * </pre>
	 * If you pass a string, it will return the current config for the key specified
	 * Anything else, will output the current config as associative array
	 * @return Phery|string|array
	 */
	public function config($config = null)
	{
		if (!empty($config))
		{
			if (is_array($config))
			{
				if (isset($config['exit_allowed']))
				{
					$this->config['exit_allowed'] = (bool) $config['exit_allowed'];
				}
				if (isset($config['no_stripslashes']))
				{
					$this->config['no_stripslashes'] = (bool) $config['no_stripslashes'];
				}
				if (isset($config['exceptions']))
				{
					$this->config['exceptions'] = (bool) $config['exceptions'];
				}
				if (isset($config['compress']))
				{
					if (!ini_get('zlib.output_compression'))
					{
						$this->config['compress'] = (bool) $config['compress'];
					}
				}
				if (isset($config['error_reporting']))
				{
					if ($config['error_reporting'] !== false)
					{
						$this->config['error_reporting'] = (int) $config['error_reporting'];
					}
					else
					{
						$this->config['error_reporting'] = false;
					}
				}
				if (isset($config['respond_to_post']) && is_array($config['respond_to_post']))
				{
					if (count($config['respond_to_post']))
					{
						$this->respond_to_post = array_merge_recursive(
							$this->respond_to_post, $config['respond_to_post']
						);
					}
					else
					{
						$this->respond_to_post = array();
					}
				}
				return $this;
			}
			elseif (is_string($config) && isset($this->config[$config]))
			{
				return $this->config[$config];
			}
		}
		else
		{
			return $this->config;
		}
	}

	/**
	 * Generates just one instance. Useful to use in many included files. Chainable
	 * @param array $config Associative config array
	 * @see __construct()
	 * @see config()
	 * @static
	 * @return Phery
	 */
	public static function instance(array $config = null)
	{
		if (!(self::$instance instanceof Phery))
		{
			self::$instance = new Phery($config);
		}
		else if ($config !== null)
		{
			self::$instance->config($config);
		}
		return self::$instance;
	}

	/**
	 * Sets the functions to respond to the ajax call.
	 * For security reasons, these functions should not be reacheable through POST/GET requests.
	 * These will be set only for AJAX requests as it will only be called in case of an ajax request,
	 * to save resources.
	 * The answer/process function, should have the following structure:
	 * <pre>
	 * function func($ajax_data, $callback_data){
	 *   $r = new PheryResponse; // or PheryResponse::factory();
	 *
	 *   // Sometimes the $callback_data will have an item called 'submit_id',
	 *   // is the ID of the calling DOM element.
	 *   // if (isset($callback_data['submit_id'])) {  }
	 *
	 *   $r->jquery('#id')->animate(...);
	 * 	 return $r;
	 * }
	 * </pre>
	 * @param array $functions An array of functions to register to the instance.
	 * @return Phery
	 */
	public function set(array $functions)
	{
		if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST' && !isset($_SERVER['HTTP_X_PHERY']))
		{
			return $this;
		}

		if (isset($functions) && is_array($functions))
		{
			foreach ($functions as $name => $func)
			{
				if (is_callable($func))
				{
					if (isset($this->functions[$name]))
					{
						self::exception($this, 'The function "'.$name.'" already exists', self::ERROR_SET);
					}
					$this->functions[$name] = $func;
				}
				else
				{
					self::exception($this, 'Provided function "'.$name.'" isnt a valid function or method', self::ERROR_SET);
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
	 * Create a new instance of Phery that can be chained, without the need of assigning it to a variable
	 * @param array $config Associative config array
	 * @see config()
	 * @static
	 * @return Phery
	 */
	public static function factory(array $config = array())
	{
		return new Phery($config);
	}

	protected static function common_check(&$attributes)
	{
		if (isset($attributes['args']))
		{
			$attributes['data-args'] = json_encode($attributes['args']);
			unset($attributes['args']);
		}

		if (isset($attributes['confirm']))
		{
			$attributes['data-confirm'] = $attributes['confirm'];
			unset($attributes['confirm']);
		}

		if (isset($attributes['target']))
		{
			$attributes['data-target'] = $attributes['target'];
			unset($attributes['target']);
		}
	}

	/**
	 * Helper function that generates an ajax link, defaults to "A" tag
	 * @param string $content The content of the link. This is ignored for self closing tags, img, input, iframe
	 * @param string $function The PHP function assigned name on Phery::set()
	 * @param array $attributes Extra attributes that can be passed to the link, like class, style, etc
	 * <pre>
	 * array(
	 * 	// Display confirmation on click
	 * 	'confirm' => 'Are you sure?',
	 *
	 * 	// The tag for the item, defaults to a. If the tag is set to img, the 'src' must be set in attributes parameter
	 * 	'tag' => 'a',
	 *
	 * 	// Define another URI for the AJAX call, this defines the HREF of A
	 * 	'href' => '/path/to/url',
	 *
	 * 	// Extra arguments to pass to the AJAX function, will be stored
	 * 	//in the args attribute as a JSON notation
	 * 	'args' => array(1, "a"),
	 *
	 * 	// Set the "href" attribute for non-anchor (a) AJAX tags (like buttons or spans).
	 * 	// Works for A links too, it won't function without javascript
	 * 	'target' => '/default/ajax/controller',
	 *
	 * 	// Define the data-type for the communication
	 * 	'data-type' => 'json',
	 *
	 * 	// Set the encoding of the data, defaults to UTF-8
	 * 	'encoding' => 'UTF-8',
	 * );
	 * </pre>
	 * @param Phery $phery Pass the current instance of phery, so it can check if the
	 * functions are defined, and throw exceptions
	 * @static
	 * @return string The mounted HTML tag
	 */
	public static function link_to($content, $function, array $attributes = array(), Phery $phery = null)
	{
		if ($function === '')
		{
			self::exception($phery, 'The "function" argument must be provided to "link_to"', self::ERROR_TO);
			return '';
		}

		if ($phery && !isset($phery->functions[$function]))
		{
			self::exception($phery, 'The function "'.$function.'" provided in "link_to" hasnt been set', self::ERROR_TO);
		}

		$tag = 'a';
		if (isset($attributes['tag']))
		{
			$tag = $attributes['tag'];
			unset($attributes['tag']);
		}

		self::common_check($attributes);

		$encoding = 'UTF-8';
		if (isset($attributes['encoding']))
		{
			$encoding = $attributes['encoding'];
			unset($attributes['encoding']);
		}

		$attributes['data-remote'] = $function;

		$ret = array();
		$ret[] = "<{$tag}";
		foreach ($attributes as $attribute => $value)
		{
			$ret[] = "{$attribute}=\"".htmlentities($value, ENT_COMPAT, $encoding, false)."\"";
		}

		if (!in_array(strtolower($tag), array('img', 'input', 'iframe')))
		{
			$ret[] = ">{$content}</{$tag}>";
		}
		else
		{
			$ret[] = "/>";
		}
		return join(' ', $ret);
	}

	/**
	 * Create a <form> tag with ajax enabled. Must be closed manually with </form>
	 * @param string $action where to go, can be empty
	 * @param string $function Registered function name
	 * @param array $attributes
	 * <pre>
	 * array(
	 * 	//Confirmation dialog
	 * 	'confirm' => 'Are you sure?',
	 *
	 * 	// Type of call, defaults to JSON (to use PheryResponse)
	 * 	'data-type' => 'json',
	 *
	 * 	// 'all' submits all elements on the form, even empty ones
	 * 	// 'disabled' enables submitting disabled elements
	 * 	'submit' => array('all' => true, 'disabled' => true),
	 *
	 * 	// Set the encoding of the data, defaults to UTF-8
	 * 	'encoding' => 'UTF-8',
	 * );
	 * </pre>
	 * @param Phery $phery Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
	 * @static
	 * @return string The mounted <form> HTML tag
	 */
	public static function form_for($action, $function, array $attributes = array(), Phery $phery = null)
	{
		if ($function == '')
		{
			self::exception($phery, 'The "function" argument must be provided to "form_for"', self::ERROR_TO);
			return '';
		}

		if ($phery && !isset($phery->functions[$function]))
		{
			self::exception($phery, 'The function "'.$function.'" provided in "form_for" hasnt been set', self::ERROR_TO);
		}

		self::common_check($attributes);

		if (isset($attributes['submit']))
		{
			$attributes['data-submit'] = json_encode($attributes['submit']);
			unset($attributes['submit']);
		}

		$encoding = 'UTF-8';
		if (isset($attributes['encoding']))
		{
			$encoding = $attributes['encoding'];
			unset($attributes['encoding']);
		}

		$ret = array();
		$ret[] = '<form method="POST" action="'.$action.'" data-remote="'.$function.'"';
		foreach ($attributes as $attribute => $value)
		{
			$ret[] = "{$attribute}=\"".htmlentities($value, ENT_COMPAT, $encoding, false)."\"";
		}
		$ret[] = '><input type="hidden" name="phery[remote]" value="'.$function.'"/>';
		return join(' ', $ret);
	}

	/**
	 * Create a <select> element with ajax enabled "onchange" event.
	 * @param string $function Registered function name
	 * @param array $items Options for the select, 'value' => 'text' representation
	 * @param array $attributes
	 * <pre>
	 * array(
	 * 	// Confirmation dialog
	 * 	'confirm' => 'Are you sure?',
	 *
	 * 	// Type of call, defaults to JSON (to use PheryResponse)
	 * 	'data-type' => 'json',
	 *
	 * 	// The URL where it should call
	 * 	'target' => '/path/to/php',
	 *
	 * 	// Extra arguments to pass to the AJAX function, will be stored
	 * 	// in the args attribute as a JSON notation
	 * 	'args' => array(1, "a"),
	 *
	 * 	// Set the encoding of the data, defaults to UTF-8
	 * 	'encoding' => 'UTF-8',
	 *
	 * 	// The current selected value, or array(1,2) for multiple
	 * 	'selected' => 1
	 * );
	 * </pre>
	 * @param Phery $phery Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
	 * @static
	 * @return string The mounted <select> with <option>s inside
	 */
	public static function select_for($function, array $items, array $attributes = array(), Phery $phery = null)
	{
		if ($function == '')
		{
			self::exception($phery, 'The "function" argument must be provided to "select_for"', self::ERROR_TO);
			return '';
		}

		if ($phery && !isset($phery->functions[$function]))
		{
			self::exception($phery, 'The function "'.$function.'" provided in "select_for" hasnt been set', self::ERROR_TO);
		}

		self::common_check($attributes);

		$encoding = 'UTF-8';
		if (isset($attributes['encoding']))
		{
			$encoding = $attributes['encoding'];
			unset($attributes['encoding']);
		}

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
		$ret[] = '<select data-remote="'.$function.'"';
		foreach ($attributes as $attribute => $value)
		{
			$ret[] = "{$attribute}=\"".htmlentities($value, ENT_COMPAT, $encoding, false)."\"";
		}
		$ret[] = '>';

		foreach ($items as $value => $text)
		{
			$_value = 'value="'.htmlentities($value, ENT_COMPAT, $encoding, false).'"';
			if (in_array($value, $selected))
			{
				$_value .= ' selected="selected"';
			}
			$ret[] = "<option {$_value}>{$text}</option>\n";
		}
		$ret[] = '</select>';
		return join(' ', $ret);
	}

	public function offsetExists($offset)
	{
		return isset($this->data[$offset]);
	}

	public function offsetUnset($offset)
	{
		if (isset($this->data[$offset]))
		{
			unset($this->data[$offset]);
		}
	}

	public function offsetGet($offset)
	{
		if (isset($this->data[$offset]))
		{
			return $this->data[$offset];
		}
		return null;
	}

	public function offsetSet($offset, $value)
	{
		$this->data[$offset] = $value;
	}

	public function __set($name, $value)
	{
		$this->data[$name] = $value;
	}

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
	 * @param ...
	 * @return mixed
	 */
	public static function coalesce()
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
 * @package PheryPackage
 * @subpackage PheryResponse
 * @method PheryResponse detach() detach() Detach a DOM element retaining the events attached to it
 * @method PheryResponse prependTo() pretendTo($target) Prepend DOM element to target
 * @method PheryResponse appendTo() appendTo($target) Append DOM element to target
 * @method PheryResponse replaceWith() replaceWith($newContent) The content to insert. May be an HTML string, DOM element, or jQuery object.
 * @method PheryResponse css() css($propertyName, $value) propertyName: A CSS property name. value: A value to set for the property.
 * @method PheryResponse toggle() toggle($speed) Toggle an object visible or hidden, can be animated with 'fast','slow','normal'
 * @method PheryResponse hide() hide($speed) Hide an object, can be animated with 'fast','slow','normal'
 * @method PheryResponse show() show($speed) Show an object, can be animated with 'fast','slow','normal'
 * @method PheryResponse toggleClass() toggleClass($className) Add/Remove a class from an element
 * @method PheryResponse data() data($name, $data) Add data to element
 * @method PheryResponse addClass() addClass($className) Add a class from an element
 * @method PheryResponse removeClass() removeClass($className) Remove a class from an element
 * @method PheryResponse animate() animate($prop, $dur, $easing, $cb) Perform a custom animation of a set of CSS properties.
 * @method PheryResponse trigger() trigger($eventName, [$args]) Trigger an event
 * @method PheryResponse triggerHandler() triggerHandler($eventType, $extraParameters) Execute all handlers attached to an element for an event.
 * @method PheryResponse fadeIn() fadeIn($prop, $dur, $easing, $cb) Animate an element
 * @method PheryResponse filter() filter($selector) Reduce the set of matched elements to those that match the selector or pass the function's test.
 * @method PheryResponse fadeTo() fadeTo($dur, $opacity) Animate an element
 * @method PheryResponse fadeOut() fadeOut($prop, $dur, $easing, $cb) Animate an element
 * @method PheryResponse slideUp() slideUp($dur, $cb) Hide with slide up animation
 * @method PheryResponse slideDown() slideDown($dur, $cb) Show with slide down animation
 * @method PheryResponse slideToggle() slideToggle($dur, $cb) Toggle show/hide the element, using slide animation
 * @method PheryResponse unbind() unbind($name) Unbind an event from an element
 * @method PheryResponse undelegate() undelegate() Remove a handler from the event for all elements which match the current selector, now or in the future, based upon a specific set of root elements.
 * @method PheryResponse stop() stop() Stop animation on elements
 * @method PheryResponse die() die($name) Unbind an event from an element set by live()
 * @method PheryResponse val() val($content) Set the value of an element
 * @method PheryResponse removeData() removeData($name) Remove element data added with data()
 * @method PheryResponse removeAttr() removeAttr($name) Remove an attribute from an element
 * @method PheryResponse scrollTop() scrollTop($val) Set the scroll from the top
 * @method PheryResponse scrollLeft() scrollLeft($val) Set the scroll from the left
 * @method PheryResponse height() height($val) Set the height from the left
 * @method PheryResponse width() width($val) Set the width from the left
 * @method PheryResponse slice() slice($start, $end) Reduce the set of matched elements to a subset specified by a range of indices.
 * @method PheryResponse not() not($val) Remove elements from the set of matched elements.
 * @method PheryResponse eq() eq($selector) Reduce the set of matched elements to the one at the specified index.
 * @method PheryResponse offset() offset($coordinates) Set the current coordinates of every element in the set of matched elements, relative to the document.
 * @method PheryResponse map() map(callback($index, $domEl)) Pass each element in the current matched set through a function, producing a new jQuery object containing the return values.
 * @method PheryResponse children() children($selector) Get the children of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse closest() closest($selector) Get the first ancestor element that matches the selector, beginning at the current element and progressing up through the DOM tree.
 * @method PheryResponse find() find($selector) Get the descendants of each element in the current set of matched elements, filtered by a selector, jQuery object, or element.
 * @method PheryResponse next() next($selector) Get the immediately following sibling of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse nextAll() nextAll($selector) Get all following siblings of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse nextUntil() nextUntil($selector) Get all following siblings of each element up to  but not including the element matched by the selector.
 * @method PheryResponse parentsUntil() parentsUntil($selector) Get the ancestors of each element in the current set of matched elements, up to but not including the element matched by the selector.
 * @method PheryResponse offsetParent() offsetParent() Get the closest ancestor element that is positioned.
 * @method PheryResponse parent() parent($selector) Get the parent of each element in the current set of matched elements, optionally filtered by a selector.
 * @method PheryResponse parents() parents($selector) Get the ancestors of each element in the current set of matched elements, optionally filtered by a selector.
 * @method PheryResponse prev() prev($selector) Get the immediately preceding sibling of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse prevAll() prevAll($selector) Get all preceding siblings of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse prevUntil() prevUntil($selector) Get the ancestors of each element in the current set of matched elements, optionally filtered by a selector.
 * @method PheryResponse siblings() siblings($selector) Get the siblings of each element in the set of matched elements, optionally filtered by a selector.
 * @method PheryResponse add() add($selector) Add elements to the set of matched elements.
 * @method PheryResponse andSelf() andSelf() Add the previous set of elements on the stack to the current set.
 * @method PheryResponse contents() contents() Get the children of each element in the set of matched elements, including text nodes.
 * @method PheryResponse end() end() End the most recent filtering operation in the current chain and return the set of matched elements to its previous state.
 * @method PheryResponse after() after($content) Insert content, specified by the parameter, after each element in the set of matched elements.
 * @method PheryResponse before() before($content) Insert content, specified by the parameter, before each element in the set of matched elements.
 * @method PheryResponse insertAfter() insertAfter($target) Insert every element in the set of matched elements after the target.
 * @method PheryResponse insertbefore() insertBefore($target) Insert every element in the set of matched elements before the target.
 * @method PheryResponse unwrap() unwrap() Remove the parents of the set of matched elements from the DOM, leaving the matched elements in their place.
 * @method PheryResponse wrap() wrap( $wrappingElement ) Wrap an HTML structure around each element in the set of matched elements.
 * @method PheryResponse wrapAll() wrapAll( $wrappingElement ) Wrap an HTML structure around all elements in the set of matched elements.
 * @method PheryResponse wrapInner() wrapInner( $wrappingElement ) Wrap an HTML structure around the content of each element in the set of matched elements.
 * @method PheryResponse delegate() delegate($selector, $eventType, $handler ) Attach a handler to one or more events for all elements that match the selector, now or in the future, based on a specific set of root elements.
 * @method PheryResponse live() live($selector, $eventType, $handler ) Attach a handler to the event for all elements which match the current selector, now or in the future.
 * @method PheryResponse one() one($selector, $eventType, $handler ) Attach a handler to an event for the elements. The handler is executed at most once per element.
 * @method PheryResponse bind() bind($selector, $eventType, $handler ) Attach a handler to an event for the elements.
 * @method PheryResponse each() each($function) Iterate over a jQ object, executing a function for each matched element.
 */
class PheryResponse {

	/**
	 * Last jQuery selector defined
	 * @var string
	 */
	public $last_selector = null;
	/**
	 * Array containing answer data
	 * @var array
	 */
	private $data = array();
	private $arguments = array();

	/**
	 * @param string $selector Create the object already selecting the DOM element
	 */
	public function __construct($selector = null)
	{
		$this->last_selector = $selector;
	}

	/**
	 * Create a new PheryResponse instance for chaining, fast and effective for one line returns
	 * <pre>
	 * function answer($data)
	 * {
	 *  return
	 * 		PheryResponse::factory('a#link-'.$data['rel'])
	 * 		->attr('href', '#')
	 * 		->alert('done');
	 * }
	 * </pre>
	 * @param string $selector
	 * @static
	 * @return PheryResponse
	 */
	public static function factory($selector = null)
	{
		return new PheryResponse($selector);
	}

	/**
	 * Merge another response to this one.
	 * Selectors with the same name will be added in order, for example:
	 * <pre>
	 * function process()
	 * {
	 * 	$response = PheryResponse::factory('a.links')->remove(); //from $response
	 * 	// will execute before
	 *  // there will be no more "a.links", so the addClass() will fail silently
	 * 	$response2 = PheryResponse::factory('a.links')->addClass('red');
	 * 	return $response->merge($response2);
	 * }
	 * </pre>
	 * @param PheryResponse $phery Another PheryResponse object
	 * @return PheryResponse
	 */
	public function merge(PheryResponse $phery)
	{
		$this->data = array_merge_recursive($this->data, $phery->data);
		return $this;
	}

	/**
	 * Pretty print to console.log
	 * @param mixed $param Any var
	 * @return PheryResponse
	 */
	public function print_vars()
	{
		$this->last_selector = null;

		$args = array();
		foreach (func_get_args() as $name => $arg)
		{
			$args[$name] = print_r($arg, true);
		}

		$this->cmd(6, $args);

		return $this;
	}

	/**
	 * Dump var to console.log
	 * @param mixed $param Any var
	 * @return PheryResponse
	 */
	public function dump_vars()
	{
		$this->last_selector = null;
		$this->cmd(6, func_get_args());

		return $this;
	}

	/**
	 * Sets the selector, so you can chain many calls to it
	 * @param string $selector Sets the current selector for subsequent chaining
	 * <pre>
	 * PheryResponse::factory()
	 * ->jquery('.slides')
	 * ->fadeTo(0,0)
	 * ->css(array('top' => '10px', 'left' => '90px'));
	 * </pre>
	 * @return PheryResponse
	 */
	public function jquery($selector)
	{
		$this->last_selector = $selector;
		return $this;
	}

	/**
	 * Shortcut/alias for jquery($selector)
	 * @param string $selector Sets the current selector for subsequent chaining
	 * @return PheryResponse
	 */
	public function j($selector)
	{
		return $this->jquery($selector);
	}

	/**
	 * Show an alert box
	 * @param string $msg Message to be displayed
	 * @return PheryResponse
	 */
	public function alert($msg)
	{
		if (is_array($msg))
		{
			$msg = join("\n", $msg);
		}

		$this->last_selector = null;
		$this->cmd(1, array(
			"{$msg}"
		));
		return $this;
	}

	/**
	 * Pass JSON to the browser
	 * @param mixed $obj Data to be encoded to json (usually an array or a JsonSerializable)
	 * @return PheryResponse
	 */
	public function json($obj)
	{
		$this->last_selector = null;
		$this->cmd(4, array(
			json_encode($obj)
		));
		return $this;
	}

	/**
	 * Remove the current jQuery selector
	 * @param string $selector Set a selector
	 * @return PheryResponse
	 */
	public function remove($selector = null)
	{
		$this->cmd(0xff, array(
			'remove'
			), $selector);
		return $this;
	}

	/**
	 * Add a command to the response
	 * @param int $cmd Integer for command, see phery.js for more info
	 * @param array $args Array to pass to the response
	 * @param string $selector Insert the jquery selector
	 * @return PheryResponse
	 */
	public function cmd($cmd, array $args, $selector = null)
	{
		$selector = Phery::coalesce($selector, $this->last_selector);
		if ($selector === null || !is_string($selector))
		{
			$this->data[] =
				array(
					'c' => $cmd,
					'a' => $args
			);
		}
		else
		{
			if (!isset($this->data[$selector])) $this->data[$selector] = array();
			$this->data[$selector][] =
				array(
					'c' => $cmd,
					'a' => $args
			);
		}

		return $this;
	}

	/**
	 * Set the attribute of a jQuery selector
	 * Example:
	 * <pre>
	 * PheryResponse::factory()
	 * ->attr('href', 'http://url.com', 'a#link-' . $args['id']);
	 * </pre>
	 * @param string $attr HTML attribute of the item
	 * @param string $selector [optional] Provide the jQuery selector directly
	 * @return PheryResponse
	 */
	public function attr($attr, $data, $selector = null)
	{
		$this->cmd(0xff, array(
			'attr',
			$attr,
			$data
			), $selector);
		return $this;
	}

	/**
	 * Trigger the phery:exception event on the calling element
	 * with additional data
	 * @param string $msg Message to pass to the exception
	 * @param mixed $data Any data to pass, can be anything
	 * @return PheryResponse
	 */
	public function exception($msg, $data = null)
	{
		$this->last_selector = null;

		$this->cmd(7, array(
			$msg,
			$data
		));
		return $this;
	}

	/**
	 * Call a javascript function.
	 * Warning: calling this function will reset the selector jQuery selector previously stated
	 * @param string $func_name Function name
	 * @param mixed $args,... Any additional arguments to pass to the function
	 * @return PheryResponse
	 */
	public function call()
	{
		$args = func_get_args();
		$func_name = array_shift($args);
		$this->last_selector = null;

		$this->cmd(2, array(
			$func_name,
			$args
		));
		return $this;
	}

	/**
	 * Call 'apply' on a javascript function.
	 * Warning: calling this function will reset the selector jQuery selector previously stated
	 * @param string $func_name Function name
	 * @param array $args Any additional arguments to pass to the function
	 * @return PheryResponse
	 */
	public function apply($func_name, array $args = array())
	{
		$this->last_selector = null;

		$this->cmd(2, array(
			$func_name,
			$args
		));
		return $this;
	}

	/**
	 * Clear the selected attribute.
	 * Alias for attr('attrname', '')
	 * @see attr()
	 * @param string $attr Name of the attribute to clear, such as 'innerHTML', 'style', 'href', etc
	 * @param string $selector [optional] Provide the jQuery selector directly
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
	 * @param string $content
	 * @param string $selector [optional] Provide the jQuery selector directly
	 * @return PheryResponse
	 */
	public function html($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		$this->cmd(0xff, array(
			'html',
			"{$content}"
			), $selector);

		return $this;
	}

	/**
	 * Set the text of an element.
	 * Automatically typecasted to string, so classes that
	 * respond to __toString() will be converted automatically
	 * @param string $content
	 * @param string $selector [optional] Provide the jQuery selector directly
	 * @return PheryResponse
	 */
	public function text($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		$this->cmd(0xff, array(
			'text',
			"{$content}"
			), $selector);

		return $this;
	}

	/**
	 * Compile a script and call it on-the-fly.
	 * There is a closure on the executed function, so
	 * to reach out global variables, you need to use window.variable
	 * Warning: calling this function will reset the selector jQuery selector previously set
	 * @param string|array $script Script content. If provided an array, it will be joined with ;\n
	 * <pre>
	 * PheryResponse::factory()
	 * ->script(array("if (confirm('Are you really sure?')) $('*').remove()"));
	 * </pre>
	 * @return PheryResponse
	 */
	public function script($script)
	{
		$this->last_selector = null;

		if (is_array($script))
		{
			$script = join(";\n", $script);
		}

		$this->cmd(3, array(
			$script
		));
		return $this;
	}

	/**
	 * Render a view to the container previously specified
	 * @param string $html HTML to be replaced in the container
	 * @param array $data Array of data to pass to the before/after functions set on Phery.view
	 * @see Phery.view() [JS]
	 * @return PheryResponse
	 */
	public function render_view($html, $data = array())
	{
		$this->last_selector = null;

		if (is_array($html))
		{
			$html = join("\n", $html);
		}

		$this->cmd(5, array(
			"{$html}",
			$data
		));

		return $this;
	}

	/**
	 * Creates a redirect
	 * @param string $url Complete url with http:// (according to W3 http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30)
	 * @return PheryResponse
	 */
	public function redirect($url)
	{
		$this->script('window.location.href="'.htmlentities($url).'"');
		return $this;
	}

	/**
	 * Prepend string/HTML to target(s)
	 * @param string $content Content to be prepended to the selected element
	 * @param string $selector [optional] Optional jquery selector string
	 * @return PheryResponse
	 */
	public function prepend($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		$this->cmd(0xff, array(
			'prepend',
			"{$content}"
			), $selector);
		return $this;
	}

	/**
	 * Append string/HTML to target(s)
	 * @param string $content Content to be appended to the selected element
	 * @param string $selector [optional] Optional jquery selector string
	 * @return PheryResponse
	 */
	public function append($content, $selector = null)
	{
		if (is_array($content))
		{
			$content = join("\n", $content);
		}

		$this->cmd(0xff, array(
			'append',
			"{$content}"
			), $selector);
		return $this;
	}

	/**
	 * Magically map to any additional jQuery function.
	 * To reach this magically called functions, the jquery() selector must be called prior
	 * to any jquery specific call
	 * @see jquery()
	 * @see j()
	 * @return PheryResponse
	 */
	public function __call($name, $arguments)
	{
		if (count($arguments))
		{
			foreach ($arguments as &$argument)
			{
				if (ctype_digit($argument))
				{
					$argument = (int) $argument;
				}
			}
		}

		$this->cmd(0xff, array(
			$name,
			$arguments
		));

		return $this;
	}

	/**
	 * Magic function to set data to the response before processing
	 */
	public function __set($name, $value)
	{
		$this->arguments[$name] = $value;
	}

	/**
	 * Magic function to get data appended to the response object
	 */
	public function __get($name)
	{
		if (isset($this->arguments[$name]))
		{
			return $this->arguments[$name];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Return the JSON encoded data
	 * @return string
	 */
	public function render()
	{
		return json_encode((object) $this->data);
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

}

/**
 * Exception class for Phery specific exceptions
 * @package PheryPackage
 * @subpackage PheryException
 */
class PheryException extends Exception { }