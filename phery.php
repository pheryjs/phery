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
 * @author Paulo Cesar <gahgneh@gmail.com>
 * @version 0.1 beta
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
   */
  public $config;

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
      'no_stripslashes' => false
    );

    if (isset($config)){
      $this->config($config);
    }
  }

  /**
   * Set callbacks for pre and post filters
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
   *   $_POST['args']['one'] = null;
   *   return true;
   * }
   * </code>
   * Returning false on the callback will make the process to RETURN and won't exit. You may manually exit on the post
   * callback if desired
   * Any data that should be modified will be inside $_POST['args'] (can be accessed freely on 'pre')
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
        throw 'No function registered with that name';
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
   * These will be triggered only for AJAX requests.
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
          $this->functions[$name] = $func;
        }
      }
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
   * 'uri' => '/path/to/url' // Define another URI for the AJAX call, this refines the HREF of A 
   * 'args' => array(1, "a") // Extra arguments to pass to the AJAX function, will be stored in the args attribute as a JSON notation
   * </code>
   * @return string
   */
  static function link_to($title, $function, array $attributes = array()){

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
      $attributes['args'] = json_encode($attributes['args']);
    }

    $attributes['remote'] = $function;

    $ret = array();
    $ret[] = "<{$tag}";
    foreach ($attributes as $attribute => $value){
      $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, 'UTF-8', false) . "\"";
    }
    $ret[] = ">{$title}</{$tag}>";
    return join(' ', $ret);
  }

  /**
   * Create a <form> tag for usage with . Must be closed
   * @param string $action where to go
   * @param array $attributes
   * <code>
   * 'method' => 'POST',
   * 'remote' => true,
   * 'confirm' => 'Are you sure?',
   * 'data-type' => 'json'
   * </code>
   * @return void Echoes automatically
   */
  static function form_for($action, $function, array $attributes = array()){
    if (!isset($attributes['method'])) $attributes['method'] = 'POST';

    $ret = array();
    $ret[] = '<form action="' . $action . '" remote="' . $function . '"';
    foreach ($attributes as $attribute => $value){
      $ret[] = "{$attribute}=\"" . htmlentities($value, ENT_COMPAT, 'UTF-8', false) . "\"";
    }
    $ret[] = '>';
    return join(' ', $ret);
  }
}

/**
 * Standard response for the json parser
 */
class phery_response{

  public $selector = null;
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
    $this->selector = $selector;
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
    $this->cmd(1, array($msg));
    return $this;
  }

  /**
   * Remove the current jQuery selector
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function remove($selector = null){
    $this->cmd(2, array(
      $this->coalesce($selector, $this->selector)
    ));
    return $this;
  }

  /**
   * Add a command to the response
   * @param int $cmd Integer for command, see phery.js for more info
   * @return phery_response
   */
  function cmd($cmd, $args){
    $this->data[] = array(
      'c' => $cmd,
      'a' => $args
    );
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
    $this->cmd(3, array(
      $this->coalesce($selector, $this->selector),
      $attr,
      $data
    ));
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
    $this->cmd(5, array(
      $func_name,
      $args
    ));
    return $this;
  }

  /**
   * Clear the selected attribute. Alias for attr('name', '')
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
    $this->cmd(6, array(
      $this->coalesce($selector, $this->selector),
      $event_name,
      $args
    ));
    return $this;
  }

  /**
   * Set the HTML of an element
   * @param string $content
   * @param string $selector [optional] Provide the jQuery selector directly
   * @return phery_response
   */
  function html($content, $selector = null){
    $this->cmd(4, array(
      $this->coalesce($selector, $this->selector),
      $content
    ));
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
      $this->coalesce($selector, $this->selector),
      'text',
      $content
    ));
    return $this;
  }

  /**
   * Compile a script and call it on-the-fly. 
   * @param string $script Script content
   * <code>
   * if(confirm('Are you really sure?')) $('*').remove(); 
   * </code>
   * @return phery_response
   */
  function script($script){
    $this->cmd(7, array(
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
      $this->coalesce($selector, $this->selector),
      'prepend',
      $content
    ));
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
      $this->coalesce($selector, $this->selector),
      'prepend',
      $content
    ));
    return $this;
  }

  /**
   * Magically map to any additional jQuery function. Note that not every
   * function will work, some that require callbacks like map or filter won't
   * be able to work. To reach this magically called functions, the jquery() selector
   * must be provided
   * @see jquery()
   * @method phery_response replaceWith()
   * @method phery_response css()
   * @method phery_response toggle()
   * @method phery_response hide()
   * @method phery_response show()
   * @method phery_response toggleClass()
   * @return phery_response
   */
  function __call($name, $arguments){
    $this->cmd(0xff, array(
      $this->selector,
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

  function execute(){
    return json_encode($this->data);
  }

  function __toString(){
    return $this->execute();
  }
}