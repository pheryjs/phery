<?php
/**
 * PHP + jQuery + AJAX = phery
 * phery creates a seamless integration with jQuery AJAX to PHP functions,
 * with unobstrutive Javascript, original concept by siong1987 @ http://github.com/rails/jquery-ujs
 *
 * Uses HTML5 attributes to achieve this. Links and forms will still be able to send GET/POST
 * requests and function properly without triggering phery.
 *
 * Strict standards for PHP 5.3 and advised to use jQuery 1.4.2+
 *
 * magic_quotes_gpc prefered to be off. you are always responsible for the security of your
 * data, so escape your text accordingly to avoid SQL injection or XSS attacks
 *
 * Uses livequery plugin from http://docs.jquery.com/Plugins/livequery
 *
 * Also, relies on JSON on PHP. All AJAX requests are sent as POST by default, so it can still
 * interact with GET requests, like paginations and such.
 *
 * Copyright (C) 2010 Paulo Cesar
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package phery
 * @author Gahgneh gahgneh@gmail.com
 * @version 0.3 beta
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 */

/**
 * Main class
 */
class phery{

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
  private $callbacks_data = array();
  /**
   * Static instance for singleton
   */
  private static $instance = null;
  /**
   * Config
   * <code>
   * 'exit_allowed',
   * 'no_stripslashes',
   * 'exceptions'
   * </code>
   * @var array
   * @see config()
   */
  public $config = null;
  /**
   * Last response
   * @var mixed
   */
  public $last_response = null;
  /**
   * Construct the new phery instance
   */
  function __construct($config = null){
    $this->callbacks = array(
      'pre' => array(),
      'post' => array()
    );

    $this->config = array(
      'exit_allowed' => true,
      'no_stripslashes' => false,
      'exceptions' => false
    );

    if (isset($config)){
      $this->config($config);
    }
  }

  /**
   * Set callbacks for pre and post filters. Callbacks are useful for example, if you have 2
   * or more AJAX functions, and you need to perform the same data manipulation, like removing an 'id'
   * from the $_POST['args'], or to check for potential CSRF or SQL injection attempts on all the functions
   * @param array $callbacks
   * <code>
   * 'pre' => array|function // Set a function to be called BEFORE processing the request, if it's an AJAX to be processed request, can be an array of callbacks
   * 'post' => array|function // Set a function to be called AFTER processing the request, if it's an AJAX processed request, can be an array of callbacks
   * </code>
   * The callback function should be
   * <code>
   * // $additional_args is passed using the callback_data() function
   * function callback($additional_args){
   *   // Do stuff
   *   $_POST['args']['id'] = $additional_args['id'];
   *   return true;
   * }
   * </code>
   * Returning false on the callback will make the process to RETURN and won't exit. You may manually exit on the post
   * callback if desired
   * Any data that should be modified will be inside $_POST['args'] (can be accessed freely on 'pre', will be passed to the
   * AJAX function)
   * @return phery
  */
  function callback(array $callbacks){
    if (isset($callbacks['pre'])){
      if (is_array($callbacks['pre']) && !is_callable($callbacks['pre'])){
        foreach($callbacks['pre'] as $func){
          if (is_callable($func)){
            $this->callbacks['pre'][] = $func;
          }
        }
      } else{
        if (is_callable($callbacks['pre'])){
          $this->callbacks['pre'][] = $callbacks['pre'];
        }
      }
    } else if (isset($callbacks['post'])){
      if (is_array($callbacks['post']) && !is_callable($callbacks['post'])){
        foreach($callbacks['post'] as $func){
          if (is_callable($func)){
            $this->callbacks['post'][] = $func;
          }
        }
      } else{
        if (is_callable($callbacks['post'])){
          $this->callbacks['post'][] = $callbacks['post'];
        }
      }
    }
    return $this;
  }

  /**
   * Set any data to pass to the callbacks
   * @return phery
   */
  function callback_data(){
    $this->callbacks_data = func_get_args();
    return $this;
  }

  /**
   * Check if the current call is an ajax call
   * @return bool
   */
  static function is_ajax(){
    return (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']) AND
    strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') == 0 AND
    strtoupper($_SERVER['REQUEST_METHOD']) == 'POST');
  }

  private function strip_slashes_recursive($variable){
    if (is_string($variable))
      return stripslashes($variable);

    if (is_array($variable))
      foreach ($variable as $i => $value)
        $variable[$i] = $this->strip_slashes_recursive($value);

    return $variable;
  }

  /**
   * Process the AJAX requests if any
   */
  function process(){
    if (self::is_ajax()){

      foreach($this->callbacks['pre'] as $func)
        if (call_user_func($func, $this->callbacks_data) === false) return;

      $remote = $_POST['remote'];
      unset($_POST['remote']);

      if (isset($this->functions[$remote])){
        $args = array();

        if (isset($_POST['args'])){
          if($this->config['no_stripslashes'] == false){
            $args = $this->strip_slashes_recursive($_POST['args']);
          } else {
            $args = $_POST['args'];
          }
          unset($_POST['args']);
        }

        $response = call_user_func_array($this->functions[$remote], array($args, $this->callbacks_data));

        if ($response instanceof phery_response){
          header("Cache-Control: no-cache, must-revalidate");
          header("Expires: 0");
          header('Content-Type: application/json');
        }
        echo $response;
      } else{
        if($this->config['exceptions'])
          throw new phery_exception('No function "'.$remote.'" set');
      }

      foreach($this->callbacks['post'] as $func)
        if (call_user_func($func, $this->callbacks_data) === false) return;

      if($this->config['exit_allowed'] == TRUE) exit;
    }
  }

  /**
   * Config the current instance of phery
   * @param array $config Associative array containing the following options
   * <code>
   * 'exit_allowed' => true/false // Defaults to true, stop further script execution
   * 'no_stripslashes' => true/false // Don't apply stripslashes on the args
   * 'exceptions' => true/false // Throw exceptions on errors
   * </code>
   * @return phery
   */
  function config($config){
    if (is_array($config)){
      if (isset($config['exit_allowed'])){
        $this->config['exit_allowed'] = (bool)$config['exit_allowed'];
      }
      if (isset($config['no_stripslashes'])){
        $this->config['no_stripslashes'] = (bool)$config['no_stripslashes'];
      }
      if (isset($config['exceptions'])){
        $this->config['exceptions'] = (bool)$config['exceptions'];
      }
    }
    return $this;
  }

  /**
   * Generates just one instance. Useful to use in many included files. Chainable
   * @param array $config
   * @see __construct()
   * @return phery
   */
  static function instance($config = null){
    if (!(self::$instance instanceof phery)){
      self::$instance = new phery($config);
    }
    return self::$instance;
  }

  /**
   * Sets the functions to respond to the ajax call..
   * These functions should not be available for direct POST/GET requests.
   * These will be set only for AJAX requests.
   * Answer function:
   * <code>
   * // $args = associative array with arguments or just one value, depending on your call
   * // $data = associative array passed from callback_data function, any additional data needed, like ids, tablenames, etc
   * function func($args, $data){
   * return phery::factory();
   * }
   * </code>
   * @param array $functions An array of functions to register to the instance
   * @return phery
   */
  function set($functions){
    if (!self::is_ajax()) return $this;

    if (is_array($functions)){
      foreach ($functions as $name => $func){
        if (is_callable($func)){
          if(isset($this->functions[$name])){
            if($this->config['exceptions'])
              throw new phery_exception ('The function "'.$name.'" already exists and will be rewritten');
          }
          $this->functions[$name] = $func;
        } else {
          if($this->config['exceptions'])
            throw new phery_exception('Provided function "'.$name.' isnt a valid function or method');
        }
      }
    } else {
      if($this->config['exceptions'])
        throw new phery_exception('Call to "set" must be provided an array');
    }
    return $this;
  }

  /**
   * Create a new instance of phery
   * @return phery
   */
  static function factory($config = null){
    return new phery($config);
  }

  /**
   * Helper function that generates an ajax link, defaults to "A" tag
   * @param string $title The content of the link
   * @param string $function The PHP function assigned name on phery::set()
   * @param array $attributes Extra attributes that can be passed to the link, like class, style, etc
   * <code>
   * 'confirm' => 'Are you sure?' // Display confirmation on click
   * 'tag' => 'a' // The tag for the item, defaults to a
   * 'uri' => '/path/to/url' // Define another URI for the AJAX call, this defines the HREF of A
   * 'args' => array(1, "a") // Extra arguments to pass to the AJAX function, will be stored in the args attribute as a JSON notation
   * 'data-type' => 'json' // Define the data-type for the communication
   * </code>
   * @param phery $phery Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
   * @return string
   */
  static function link_to($title, $function, array $attributes = array(), phery $phery = null){
    if($function == ''){
      if($phery){
        if($phery->config['exceptions'])
          throw new phery_exception('The "function" argument must be provided to "link_to"');
      }
      return '';
    }
    if($phery){
      if(!isset($phery->functions[$function])){
        if($phery->config['exceptions'])
          throw new phery_exception('The function "'.$function.'" provided in "link_to" hasnt been set');
      }
    }

    $tag = 'a';
    if (isset($attributes['tag'])){
      $tag = $attributes['tag'];
      unset($attributes['tag']);
    }

    if (isset($attributes['uri'])){
      $attributes['href'] = $attributes['uri'];
      unset($attributes['uri']);
    }

    if (isset($attributes['args'])){
      $attributes['data-args'] = json_encode($attributes['args']);
      unset($attributes['args']);
    }

    if (isset($attributes['confirm'])){
      $attributes['data-confirm'] = $attributes['confirm'];
      unset($attributes['confirm']);
    }

    $attributes['data-remote'] = $function;

    $ret = array();
    $ret[] = "<{$tag}";
    foreach ($attributes as $attribute => $value){
      $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, 'UTF-8', false) . "\"";
    }
    $ret[] = ">{$title}</{$tag}>";
    return join(' ', $ret);
  }

  /**
   * Create a <form> tag with ajax enabled. Must be closed with </form>
   * @param string $action where to go, can be empty
   * @param string $function Registered function name
   * @param array $attributes
   * <code>
   * 'data-method' => 'POST', // can be different from form "method", just for the AJAX
   * 'confirm' => 'Are you sure?',
   * 'data-type' => 'json'
   * </code>
   * @param phery $phery Pass the current instance of phery, so it can check if the functions are defined, and throw exceptions
   * @return void Echoes automatically
   */
  static function form_for($action, $function, array $attributes = array(), phery $phery = null){
    if($function == ''){
      if($phery){
        if($phery->config['exceptions'])
          throw new phery_exception('The "function" argument must be provided to "form_for"');
      }
      return '';
    }
    if($phery){
      if(!isset($phery->functions[$function])){
        if($phery->config['exceptions'])
          throw new phery_exception('The function "'.$function.'" provided in "form_for" hasnt been set');
      }
    }

    if (isset($attributes['args'])){
      $attributes['data-args'] = json_encode($attributes['args']);
      unset($attributes['args']);
    }

    if (!isset($attributes['data-method']))
      $attributes['data-method'] = 'POST';

    if (isset($attributes['confirm'])){
      $attributes['data-confirm'] = $attributes['confirm'];
      unset($attributes['confirm']);
    }

    if (isset($attributes['submit'])){
      $attributes['data-submit'] = json_encode($attributes['submit']);
      unset($attributes['submit']);
    }

    $ret = array();
    $ret[] = '<form action="' . $action . '" data-remote="' . $function . '"';
    foreach ($attributes as $attribute => $value){
      $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, 'UTF-8', false) . "\"";
    }
    $ret[] = '>';
    return join(' ', $ret);
  }
}

/**
 * Standard response for the json parser
 * @method phery_response replaceWith() replaceWith($newContent) The content to insert. May be an HTML string, DOM element, or jQuery object.
 * @method phery_response css() css($propertyName, $value) propertyName: A CSS property name. value: A value to set for the property.
 * @method phery_response toggle() toggle($speed) Toggle an object visible or hidden, can be animated with 'fast','slow','normal'
 * @method phery_response hide() hide($speed) Hide an object, can be animated with 'fast','slow','normal'
 * @method phery_response show() show($speed) Show an object, can be animated with 'fast','slow','normal'
 * @method phery_response toggleClass() toggleClass($className) Add/Remove a class from an element
 * @method phery_response addClass() addClass($className) Add a class from an element
 * @method phery_response removeClass() removeClass($className) Remove a class from an element
 * @method phery_response animate() animate($prop, $dur, $easing, $cb) Animate an element
 * @method phery_response fadeIn() fadeIn($prop, $dur, $easing, $cb) Animate an element
 * @method phery_response fadeOut() fadeOut($prop, $dur, $easing, $cb) Animate an element
 * @method phery_response slideUp() slideUp($dur, $cb) Hide with slide up animation
 * @method phery_response slideDown() slideDown($dur, $cb) Show with slide down animation
 * @method phery_response slideToggle() slideToggle($dur, $cb) Toggle show/hide the element, using slide animation
 * @method phery_response unbind() unbind($name) Unbind an event from an element
 * @method phery_response die() die($name) Unbind an event from an element set by live()
 */
class phery_response{

  public $last_selector = null;
  private $data = array();
  private $arguments = array();

  function __construct(){}

  /**
   * Utility function taken from MYSQL
   */
  private function coalesce(){
    $args = func_get_args();
    foreach ($args as &$arg){
      if (isset($arg) && !empty($arg)) return $arg;
    }
    return null;
  }

  /**
   * Create a new phery_response instance for chaining, for one liners
   * <code>
   * function answer(){
   *  return phery_response::factory()->attr('href', '#', 'a#link')->alert('done');
   * }
   * </code>
   * @return
   */
  static function factory(){
    return new phery_response;
  }

  /**
   * Sets the selector, so you can chain many calls to it
   * @param string $selector Sets the current selector for subsequent chaining
   * @return phery_response
   */
  function jquery($selector){
    $this->last_selector = $selector;
    return $this;
  }

  /**
   * Append another response
   */
  /**
   * Show an alert box
   * @param string $msg Message to be displayed
   * @return phery_response
   */
  function alert($msg){
    $this->cmd(1, array(
      $msg
    ));
    return $this;
  }

  /**
   * Remove the current jQuery selector
   * @param string $children_selector Set a children selector
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function remove($children_selector = null, $selector = null){
    $this->cmd(0xff, array(
      'remove',
      $children_selector
    ),
    $selector);
    return $this;
  }

  /**
   * Add a command to the response
   * @param int $cmd Integer for command, see phery.js for more info
   * @param array $args Array to pass to the response
   * @param string $selector Insert the jquery selector
   * @return phery_response
   */
  function cmd($cmd, array $args, $selector = null){
    $selector = $this->coalesce($this->last_selector, $selector);
    if ($selector === null){
    $this->data[] =
      array(
        'c' => $cmd,
        'a' => $args
      );
    } else {
      if(!isset($this->data[$selector])) $this->data[$selector] = array();
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
   * Example:<br>
   * <code>
   * phery_response->attr('href', 'http://url.com');
   * </code>
   * @param string $attr HTML attribute of the item
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function attr($attr, $data, $selector = null){
    $this->cmd(0xff, array(
      'attr',
      $attr,
      $data
    ),
    $selector);
    return $this;
  }

  /**
   * Call a javascript function
   * @param string $func_name Function name
   * @param mixed $args,... Any additional arguments to pass to the function
   * @return phery_response
   */
  function call(){
    $args = func_get_args();
    $func_name = array_shift($args);

    $this->cmd(2, array(
      $func_name,
      $args
    ));
    return $this;
  }

  /**
   * Clear the selected attribute. Alias for attr('attrname', '')
   * @see attr()
   * @param string $attr Name of the attribute to clear, such as 'innerHTML', 'style', 'href', etc
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function clear($attr, $selector = null){
    return $this->attr($attr, '', $selector);
  }

  /**
   * Trigger an event on elements
   * @param string $event_name Name of the event to trigger
   * @param string $selector [optional] Provide the jQuery selector directly
   * @param mixed $args [optional] any additional arguments to be passed to the trigger function
   * @return phery_response
   */
  function trigger($event_name, $selector = null){
    $args = array_slice(func_get_args(), 2);
    $this->cmd(0xff, array(
      'trigger',
      $event_name,
      $args
    ),
    $selector);
    return $this;
  }

  /**
   * Set the HTML of an element
   * @param string $content
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function html($content, $selector = null){
    $this->cmd(0xff, array(
      'html',
      "{$content}"
    ),
    $selector);
    return $this;
  }

  /**
   * Set the text of an element
   * @param string $content
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function text($content, $selector = null){
    $this->cmd(0xff, array(
      'text',
      "{$content}"
    ),
    $selector);
    return $this;
  }

  /**
   * Compile a script and call it on-the-fly. There is a closure on this script,
   * so global variables need to be assigned using
   * @param string|array $script Script content. If provided an array, it will be joined with ;\n
   * <code>
   * if(confirm('Are you really sure?')) $('*').remove();
   * </code>
   * @return phery_response
   */
  function script($script){
    if (is_array($script))
      $script = join(";\n", $script);

    $this->cmd(3, array(
      $script
    ));
    return $this;
  }

  /**
   * Creates a redirect
   * @param string $url Complete url with http:// (according W3C http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30)
   * @return phery_response
   */
  function redirect($url){
    $this->script('window.location.href="' . htmlentities($url) . '"');
    return $this;
  }

  /**
   * Prepend string/HTML to target(s)
   * @param string $content Content to be prepended to the selected element
   * @param string $selector [optional] Optional jquery selector string
   * @return phery_response
   */
  function prepend($content, $selector = null){
    $this->cmd(0xff, array(
      'prepend',
      $content
    ),
    $selector);
    return $this;
  }

  /**
   * Append string/HTML to target(s)
   * @param string $content Content to be appended to the selected element
   * @param string $selector [optional] Optional jquery selector string
   * @return phery_response
   */
  function append($content, $selector = null){
    $this->cmd(0xff, array(
      'append',
      $content
    ),
    $selector);
    return $this;
  }

  /**
   * Magically map to any additional jQuery function. Note that not every
   * function will work, some that require callbacks like map or filter won't
   * be able to work. To reach this magically called functions, the jquery() selector
   * must be provided
   * @see jquery()
   * @return phery_response
   */
  function __call($name, $arguments){
    $this->cmd(0xff, array(
      $name,
      $arguments
    ));
    return $this;
  }

  /**
   * Magic function to set data to the response before processing
   */
  public function __set($name, $value){
    $this->arguments[$name] = $value;
  }

  /**
   * Magic function to get data appended to the response object
   */
  public function __get($name){
    if(isset($this->arguments[$name]))
      return $this->arguments[$name];
    else
      return null;
  }

  function render(){
    return json_encode((object)$this->data);
  }

  function __toString(){
    return $this->render();
  }
}

interface IException
{
    /* Protected methods inherited from Exception class */
    public function getMessage();                 // Exception message
    public function getCode();                    // User-defined Exception code
    public function getFile();                    // Source filename
    public function getLine();                    // Source line
    public function getTrace();                   // An array of the backtrace()
    public function getTraceAsString();           // Formated string of trace

    /* Overrideable methods inherited from Exception class */
    public function __toString();                 // formated string for display
    public function __construct($message = null, $code = 0);
}

abstract class CustomException extends Exception implements IException
{
    protected $message = 'Unknown exception';     // Exception message
    private   $string;                            // Unknown
    protected $code    = 0;                       // User-defined exception code
    protected $file;                              // Source filename of exception
    protected $line;                              // Source line of exception
    private   $trace;                             // Unknown

    public function __construct($message = null, $code = 0)
    {
        if (!$message) {
            throw new $this('Unknown '. get_class($this));
        }
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n"
                                . "{$this->getTraceAsString()}";
    }
}

class phery_exception extends CustomException {}