# PHP + jQuery + AJAX = phery

## What do I need?

+    PHP 5.3+ with JSON functions (no legacy support for PHP 4, not tested in PHP 5.2, it MIGHT work)
+    jQuery 1.7+ (strongly suggested to use 1.8.1+)
+    Firefox 3.6+, IE 8+, Chrome 12+, Safari 5+, Opera 10+

## Introduction

**This library is PSR-0 Compatible**

Straightforward and powerful AJAX library with direct integration and mapping of all jQuery functions in PHP,
the mapping is extended to custom functions set by $.fn, can create elements just like $('<element/>') does, as **phery** creates a seamless integration with jQuery AJAX
to PHP functions, based off original idea from <https://github.com/rails/jquery-ujs> for the JS part on the delegation and data-remote

All jQuery functions listed in here are available to call directly from the PheryResponse class: <http://api.jquery.com/browser/> including any new versions of jQuery
that comes out, its compatible with jQuery forever. No need to update phery, as it will continue to work with future versions of jQuery automatically.

Uses HTML5 data attributes to achieve this, and no additional libraries are needed, even for Internet Explorer.
Links and forms will still be able to send GET/POST requests and function properly without triggering **phery** when javascript isn't enabled (or triggering it in case you still want to respond to POST requests anyway).

W3C validator might complain about data-* if you're not using <!doctype html> (HTML5 DOCTYPE). IE 7 needs html5shiv to work properly.

Strict standards for PHP 5.3+ and advised to use jQuery 1.7+. Being just one PHP file, and one javascript file, it's pretty easy to 'carry'
around or to implement in PHP auto-load scenarios, plus it's really FAST! Average processing time is around 2ms with vanilla PHP, according to Firebug and
in the demo page

PHP *magic\_quotes\_gpc* prefered to be off. you are always responsible for the security of your data, so escape your text accordingly
to avoid SQL injection or XSS attacks.

Also, relies on JSON on PHP. All AJAX requests are sent as POST only, so it can still interact with GET requests,
like paginations and such (?p=1 / ?p=2 / ...).

Even though it appears to work in Internet Explorer 6 and 7, I don't care and don't test in it, since I don't even have XP to test them.

The code is mostly commented using phpDoc and jsDoc, for a much more steep learning curve, using doc-enabled IDEs, like Netbeans, Aptana or Eclipse based IDEs.
Also, most of the important and most used functions in jQuery were added as phpDoc, as a magic method of the **PheryResponse** class.

## Example code

Check the a lot of examples and code at <https://github.com/pocesar/phery/raw/master/demo.php>

## Releases

* **1.0**: **BREAKING API CHANGES** Complete revamp of Javascript code to use 'delegate' instead of 'live', using jQuery namespace'd events and data, support for self closing HTML tags, like IMG, exposed mouse events for each element (form, select / multiple, tags) - 4th September. 2012
* **0.6b**: Javascript code additions, support for "change" event on SELECT elements and PHP helper for creating a SELECT, added encoding support, defaults to UTF-8, fix when argument passing when not an array or JSON object, added more jQuery functions to the IDE autocomplete phpDoc - 8th July. 2011
* **0.5.2b**: Improved code for cursor, added $.phery.options.ajax.retry\_limit and automatic retry abilities, updated examples in index.php and adjusted documentation, minor change in PHP side - 06th May. 2011
* **0.5.1b**: Fixed events, events will be executed as GLOBAL then PER ELEMENT. Returning false cancels propagation. Fixed console.log, updated index.php with examples and removed dependency for livequery plugin, jquery 1.5.2 got it fixed - 27th Apr. 2011
* **0.5b**:  Added $.phery.options.default\_href, added ability to call anonymous functions callbacks directly from PHP, removed closed from script() call, added exception event - 11st Mar. 2011
* **0.4b**:  Added more error checking, fixed some bugs, improved both PHP and js code, included jQuery 1.5.1, changed the way the callbacks are executed and handled, removed external JSON parser - 4th Mar. 2011
* **0.3b2**: Removed some mal functioning code from js, corrected minor things in PHP and example - 15th Nov. 2010
* **0.3b1**: Test changes to function parsing client-side, added $.callRemote(), and changes to PHP code - 23rd Oct. 2010
* **0.2b**:  Renamed project to phery, improved js code - 11st Oct. 2010
* **0.1b**:  First public release as Pjax - 30th Sep. 2010

## Documentation

It's really simple as

```php
<?php
	Phery::instance()->set(array(
		'function_name' => function($data){
				return PheryResponse::factory()->alert(print_r($data, true));
		}
	))->process();
?>
<!doctype html>
<html>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
	<script src="phery.js"></script>
	<a data-remote="function_name">Click me</a>
</html>
```

### PHP server-side

#### Phery - The main class, that should be reused everywhere (singleton style), but you can create many instances just fine

```php
<?php
	Phery::instance($config)->(...);
?>
```

#### Phery::instance()->callback(array('before' => array(), 'after' => array()))

Add a callback that will execute in all functions that are registered using Phery::instance()->set(), and can be any number of callbacks,
useful when you're going to execute the same task for all AJAX requests.

```php
<?php
	function pre_function_one($ajax_data, $callback_specific_data)
	{
		// Trim the data, assuming every item is a string, and not an array of array
		return array_map('trim', $ajax_data);
	}

	function post_function($ajax_data, $callback_specific_data, $answer)
	{
		Database::insert('table', $ajax_data); // key/value pairs for e.g.
		// $answer can be a PheryResponse
		if ($answer instanceof PheryResponse)
		{
			$answer->alert('Ive been post processed!');
		}
		return true;
	}
	Phery::instance()->callback(array(
		'before' => array('pre_function_one', 'pre_function_two'),
		'after' => array('post_function')
	));
?>
```

#### Phery::instance()->data(...)

Add any additional data, that will be accessible to either process functions or callback

```php
<?php
	Phery::instance()
	->set(array('delete' => 'process_delete'))
	->callback(array('before' => 'callback_function'))
	->data(1, 'string', array('named'=>'argument'), new myClass)
	->process();

	function callback_function($ajax_data, $parameters)
	{
		// Since we are not using named parameters, it needs to be accessed through ordinal indexes
		if ($parameters[0] === 1)
		{
			$ajax_data['new_stuff'] = $parameters[3]->execute($parameters[1]);
		}
		return $ajax_data;
	}

	function process_delete($delete, $parameters)
	{
		$id = process($delete['new_stuff']);
		$parameters[3]->clear();
		Database::delete($id);
	}
?>
```

#### Phery::instance()->config(array())

Set configuration for the current instance of phery. Passed as an associative array, and can be passed when creating a new
instance.

* `exit_allowed` => true, Defaults to true, stop further script execution
* `no_stripslashes` => false, Don't apply stripslashes on the args
* `exceptions` => false, Throw exceptions on errors
* `respond_to_post` => array(), Set the functions that will be called even if is a POST but not an AJAX call
* `compress` => false, Enable/disable GZIP/DEFLATE compression, depending on the browser support. Don't enable it if you are using Apache DEFLATE/GZIP, or zlib.output_compression Most of the time, compression will hide exceptions, because it will output plain text while the content-type is gzip
* `error_reporting` => false|E_ALL|E_DEPRECATED|..., Error reporting temporarily using error_reporting(). 'false' disables the error_reporting and wont try to catch any error. Anything else than false will throw a PheryResponse->exception() with the message, code, line and file

#### Phery::instance()->views(array)

Set up the rendering functions for phery.view() on Javascript side. Automates the rendering of partials,
and you may apply transitions between pages. Ajaxifying your website reduces the round trip by a maximum of
60% less total content size

```php
<?php
	Phery::instance()->views(array(
		'#container' => function($data, $params){
			ob_start();
			switch ($_GET['page']){
				case 'home':
				case 'about':
				case 'services':
					include $_GET['page'].'.php';
			}
			return
				PheryResponse::factory()
				->render_view(ob_get_clean());
		}
  ));
?>
```

#### Phery::is_ajax($is_phery = false)

Returns a boolean, checks if it's a POST AJAX request. Set `$is_phery` to true to check specifically for phery call

```php
<?php
	if(Phery::is_ajax()){
		var_dump($_POST);
	}
?>
```

#### Phery::instance()->answer_for($alias, $default = NULL)

Gets the answer for a form submit that wasn't sent through AJAX and is present in the unobstructive list. Returns $default if no answer available

```php
<?php
	$database_result = Phery::instance()->answer_for('alias', false); // in this case, returns a Database result
	if ($database_result === false)
	{
		$database_result = new Database_Result(...);
	}
?>
```

#### Phery::instance()->set(array $functions)

Register the functions that will be triggered by AJAX calls.
The key is the function alias, the value is the function itself.

```php
<?php
	function outside($ajax_data, $callback_data){
		return PheryResponse::factory();
	}

	class classy {
		function inside($ajax_data, $callback_data){
			return PheryResponse::factory();
		}

		static function inside_static($ajax_data, $callback_data){
			return PheryResponse::factory();
		}
	}

	$class = new classy();

	Phery::instance()->set(array(
		'alias' => function(){ return PheryResponse::factory(); },
		'outside' => 'outside',
		'class' => array($class, 'inside'),
		'class' => 'classy::inside_static',
		'namespaced' => 'namespaced\function'
	));
?>
```

Callback/response function comprises of:

```php
<?php
	function func($ajax_data, $callback_data){
		// $ajax_data = data coming from browser, via AJAX
		// $callback_data = can have anything you specify, plus additional information, like **submit_id** that
		// comes automatically from the AJAX request, containing the ID of the calling DOM element, if has an id="" set
		return PheryResponse::factory(); // In most cases, you'll want to return a PheryResponse object
	}
?>
```

#### Phery::factory(array $config = null)

Creates a new instance of phery, that is chainable

```php
<?php
	Phery::factory(array('exceptions' => true))
	->set(array('alias' => 'func'))
	->process();
?>
```

#### Phery::instance($config = null)

Singleton static method, ensures just one instance of phery

#### Phery::instance()->process($last_call = true)

Takes just one parameter, $last_call, in case you want to call process() again later, with a different set. **last_call** won't allow the
process to return until you call it again or exit the code inside a callback function.

```php
<?php
	$phery = Phery::instance();
	$phery->set(array('function' => array('class', 'funct')));
	$phery->process(false);
	// ...
	// continue execution
	$phery->set(array(
		'function2' => array('class' => 'funct2')
	))
	->callback(array(
		'post' => 'function'
	))
	->process();
	// PHP 'exit' is called, from this point and beyond won't get executed unless it doesnt map to any AJAX calls
	$i += 10;
?>
```

#### Phery::link_to($title, $function, array $attributes = array(), phery $phery = null)

Helper static method to create any element with AJAX enabled. Check sources, phpDocs or an IDE code hinting for a better scoop and detailed info

```php
<?php echo Phery::link_to('link title', 'function_name', array('class' => 'red', 'href' => '/url')); ?>
```

#### Phery::form_for($action, $function, array $attributes = array(), phery $phery = null)

Helper static method to open a form that will be able to execute AJAX submits. Check sources, phpDocs or an IDE code hinting for a better scoop and detailed info

```php
<?php echo Phery::form_for('/url-to-action/or/empty-means-current-url', 'function_name', array('class' => 'form', 'id' => 'form_id', 'submit' => array('disabled' => true, 'all' => true))) ?>
	<input type="text" name="text">
	<input type="password" name="pass">
	<input type="submit" value="Send">
</form>
```

#### Phery::select_for($function, $items, array $attributes = array(), phery $phery = null)

Helper static method to display a select element.

```php
<?php echo Phery::select_for('function_name', array(1 => 'true', 2 => 'hello', 3 => 'control'), array('selected' => 2)) ?>
```

#### Phery::coalesce(...)

Helper static method that returns the first non null/false/0 item (taken from MYSQL COALESCE). Notice that depending on error level, some notices will be THROWN, to make sure use @ in front of the variable

```php
<?php echo Phery::coalesce($undeclared_variable, UNDECLARED_CONSTANT, $data['no-index'], 10) // return 10 ?>
```

#### PheryResponse - Used as a return value to any function called using AJAX, in most cases

Check the code completion using an IDE for a better view of the functions, read the source or check the examples
Any jQuery function can be called through PheryResponse, even custom ones.

```php
<?php
	function func($data)
	{
		$user = ORM::factory('user', $data['id'])
		->values($data)
		->update();

		return
			PheryResponse::factory('#name')
			->html('<p>'.$user->name.'</p>')
			->show('fast')
			->jquery('.slider')
			->slider([
				'value' => 60,
				'orientation' => "horizontal",
				'range' => "min",
				'animate' => true
			]);
	}
?>
```

#### PheryException - Exceptions that are thrown by phery, when enabled to do so, with some descriptive errors

Check the code completion using an IDE for a better view of the functions, read the source or check the examples <https://github.com/pocesar/phery/blob/master/demo.php>

### Javascript client-side

#### $('form').serializeForm(opts)

Generate an object serialized with unlimited depth from forms. Opts can be defined as:

* `disabled`: boolean, process disabled form elements, defaults to false
* `all`: boolean, include all elements from a form, including null ones, assigning an empty string to the item, defaults to false
* `empty`: boolean, setting to false will skip empty fields (won't be submitted), setting to true will submit empty key:values pairs, this overrides "all" setting, defaults to true

```js
$('form').serializeForm({'disabled':true,'all':true,'empty':false});
```

* A form like:

```html
	<form>
		<input name="field[gender]" type="text" value="male">
		<input name="field[name]" type="text" value="John Doe">
		<input name="breakfast" type="text" value="eggs & bacon">
		<select name="select" multiple>
			<option selected value="1">1</option>
			<option selected value="2">2</option>
			<option value="3">3</option>
		</select>
	</form>
```

* will generate an object like:

```js
	{
		"field":{
			"gender":"male",
			"name":"John Doe"
		},
		"breakfast":"eggs & bacon",
		"select":[
			1, 2
		]
	}
```

#### $('form').reset()

An helper function that has been long missing from jQuery, to simply reset a form and remove all values from it. Can reset multiple forms at once

#### $('element').phery('remote') or $('element').phery().remote()

Trigger the AJAX call, takes no parameter. Only available for elements previously bound with the ajax events. Returns boolean

```js
$('a#id04').phery().remote();
```
#### phery.remote(functionName, arguments, attributes, directCall)

Calls an AJAX function directly, without binding to any existing elements, the DOM element is created and removed on-the-fly

* `functionName`: string, name of the alias defined in Phery::instance()->set() inside PHP
* `arguments`: object or array or variable, the best practice is to pass an object, since it can be easily accessed later through PHP, but any kind of parameter can be passed, from strings, ints, floats, and can also be null (won't be passed through ajax)
* `attributes`: object, set any additional information about the DOM element, usually for setting another href to it. eg: {href: '/some/other/url?p=1'}
* `directCall`: boolean, defaults to true, setting this to false, will return the created DOM element (invisible to the user) and can have events bound to it

```js
	element = phery.remote('remote', {'key':'value'}, {'href': '/new/url'}, false);
	element.bind({
		'phery:complete': function(){
			$('body').append($(this));
		}
	}).phery('remote');
```

### Options and global and element events

Global events will always trigger, and they first come empty and do nothing.
It's mainly useful to show/hide loading screens, update statuses, put an overlay over the page, or interact with other libraries

#### phery.on(event, cb)

These events are triggered globally, independently if called from an existing DOM element or through phery.remote()
The `event.target` points to the related DOM node

* `before`: `function (event)` Triggered before everything, happens right after phery.remote() call
* `after`: `function (event)` Triggered after all the data was parsed.
* `beforeSend`: `function (event, xhr)` Triggered before sending the data through AJAX, Useful to add any CSRF protections here
* `done`: `function (event, data, text, xhr)` Triggered just before the answer from the response was received successfully and will start to process the data. Returning false halts the processing, make sure to return true
* `always`: `function (event, xhr)` Triggered after the data was processed. and is triggered if there was no error.
* `fail`: `function (event, xhr, status, error)` When an error happens when requesting to the provided URL. It won't be triggered if the PHP code fails to execute
* `exception`: `function (event, exception)` Will be called if any problem happens while processing data, or executing jquery calls
* `json`: `function (event, obj)` Returns the json object sent from PHP

```js
	phery.on('before', function(){
		$('#loading').fadeIn();
	});

	phery.on('complete', function(){
		$('#loading').fadeOut();
	});

//or

	phery.on({
		'before': function(){
			$('#loading').fadeIn();
		},
		'complete': function(){
			$('#loading').fadeOut();
		}
	});
```

#### phery.off(name)

Remove a global event bound

```js
	phery.off('always');
```

#### phery.reset_to_defaults()

Reset the configuration to the defaults, see phery.config()

#### phery.log()

Wrapper for the console.log(), but keeps a local history, if you enable it in
phery.config()

#### phery.view(config)

Config the page to render AJAX partial views

```js
	phery.view({
    // Must always be an ID
    '#container': {
	 		// Optional, function to call before the
	 		// HTML was set, can interact with existing elements on page
	 		// The context of the callback is the container
	    'beforeHtml': function(data){
				document.title = data.title;
			},
	    // Optional, function to call to render the HTML,
	 		// in a custom way. This overwrites the original function,
	 		// so you might set this.html(html) manually.
	 		// The context of the callback is the container
			'render': function(html, data){
				$(this).html(html);
			},
	    // Optional, function to call after the HTML was set,
	 		// can interact with the new contents of the page
	 		// The context of the callback is the container
	    'afterHtml': function(data){
				History.saveState(data.url);
			},
	    // Optional, defaults to a[href]:not(.no-phery,[target],[data-remote],[href=":"],[rel~="nofollow"]).
	 		// Setting the selector manually will make it 'local' to the #container, like '#container a'
			// Links like <a rel="#nameofcontainer">click me!</a>, using the rel attribute will trigger too
			'selector': 'a',
			// Optional, array containing conditions for links NOT to follow,
			// can be string, regex and function (that returns boolean, receives the url clicked, return true to exclude)
	    'exclude': ['/contact', /\d$/, function(url, link){
				if (window.location.href === '/blog') return true;
			}],
	 		// any other phery event, like beforeSend, params, etc
			'params': function(event, data){
				data['href'] = window.location.href;
			}
		}
  });
```

Retrieve the data and functions associated with the container with:

```js
	// contains the data associated with the container, like every configuration
	phery.view('#container').data;
	// Call the path to render to the container
	phery.view('#container').remote('local-url/to/somewhere');
	// Contains the container $(DOM) itself
	phery.view('#container').container;
```

#### phery.config(key, value)

The current options that are available. Reminding that additional configurations and modifications on the AJAX calls can be done using [$.ajaxSetup()](http://api.jquery.com/jQuery.ajaxSetup)
The options can be set using the `key:value` in the `key` parameter, or using a string and value. Each option can be accessed using dot notation inside the string

```js
 phery.config({
   'cursor': false,
   'enable.per_element.events': false
 });

 phery.config('debug.display.config', true);
```

* `cursor` (true / false, defaults to true): change the cursor to **wait/busy** on any ajax call
* `default_href` (string / false, defaults to false): set a common url to all calls, being able to override this to any `href` property attached to the element
* `ajax.retries` (int, defaults to 0): number of retries on error before returning the error callback
* `enable.log` (true / false, defaults to false): enable displaying of exceptions or errors, for debugging purposes
* `enable.log_history` (true / false, defaults to false): keep the log of errors  that can be accessed through `phery.log()`
* `enable.php_string_callbacks` (true / false, defaults to false): jQuery functions like `animate` or `each` that take callbacks, can have the callback defined as a string inside PHP if this is enabled.
* `enable.per_element.events` (true / false, defaults to true): enable `phery:*` events on each element
* `enable.per_element.options` (true / false, defaults to false): enable `options` on each element, that override the global options
* `debug.enable` (true / false, defaults to false): enable verbose to keep track of each step defined below
* `debug.display.events` (true / false, defaults to true): display events debug
* `debug.display.remote` (true / false, defaults to true): display remote calls
* `debug.display.config` (true / false, defaults to true): display config changes
* `delegate.confirm` (string, defaults to 'click'): Data-confirm
* `delegate.form` (string, defaults to 'submit'): Form submit
* `delegate.select_multiple` (string, defaults to 'blur'): Event on select multiple
* `delegate.select` (string, defaults to 'change'): Event on select
* `delegate.tags` (string, defaults to 'click'): Clicks on data-remote elements

#### Per element events

Per element events are the same from global events. Refer to `phery.on` above for description for each event.

* `phery:before`: `function (event)`
* `phery:beforeSend`: `function (event, xhr)`
* `phery:done`: `function (event, data, text, xhr)`
* `phery:always`: `function (event, xhr)`
* `phery:fail`: `function (event, xhr, status, error)`
* `phery:after`: `function (event)`
* `phery:exception`: `function (event, exception)`
* `phery:json`: `function (event, obj)`
* `phery:params`: `function (event, obj)`

```js
	$('form').bind({
		// Enable them again
		'phery:always': function(){
			$(this).find('input').removeAttr('disabled');
		},
		// Disable form elements
		'phery:before': function(){
			$(this).find('input').attr('disabled', 'disabled');
		}
	});
```