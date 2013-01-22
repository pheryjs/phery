# PHP + jQuery + AJAX = phery

## What do I need?

+    PHP 5.3.3+ with JSON functions (no legacy support for PHP 4 or 5.2 that had the support already dropped from PHP.net itself, relies on PHP 5.3 functions)
+    jQuery 1.7+ (strongly suggested to use 1.8.1+, not compatible with version >=1.9)
+    Firefox 3.6+, IE 8+, Chrome 12+, Safari 5+, Opera 10+

## Introduction

**This library is PSR-0 Compatible**

This library is a much better alternative to XAJAX for those who work with jQuery and AJAX, while half of the lines. It unleashes everything you expect from an AJAX library, with nested responses, merging and unmerging responses, acessing directly the calling element.

It's a straightforward and powerful AJAX library with direct integration and mapping of all jQuery functions in PHP, the mapping is extended to custom functions set by $.fn, can create elements just like `$('<div/>')` does, as **phery** creates a seamless integration with jQuery functions, through AJAX to PHP functions, as you can call a PHP function using AJAX

All jQuery functions listed in here are available to call directly from the PheryResponse class: <http://api.jquery.com/browser/> including any new versions of jQuery that comes out, its compatible with jQuery forever. No need to update phery, as it will continue to work with future versions of jQuery automatically.

Phery uses HTML5 `data` attributes to achieve this, and no additional libraries are needed, even for Internet Explorer. Links and forms will still be able to send GET/POST requests and function properly without triggering **phery** when javascript isn't enabled (or triggering it in case you still want to respond to POST requests anyway).

W3C validator might complain about `data-*` if you're not using <!doctype html> (HTML5 DOCTYPE).

Strict standards for PHP 5.3+ and advised to use jQuery 1.7+. Being just one PHP file, and one javascript file, it's pretty easy to 'carry' around or to implement in PHP auto-load scenarios, plus it's really FAST! Average processing time is around 2ms with vanilla PHP, according to Firebug and in the demo page

PHP *magic\_quotes\_gpc* prefered to be off. you are always responsible for the security of your data, so escape your text accordingly to avoid SQL injection or XSS attacks.

Also, relies on JSON on PHP. All AJAX requests are sent as POST only, so it can still interact with GET requests, like paginations and such (?p=1 / ?p=2 / ...).

The code is mostly commented using phpDoc and jsDoc, for a steep learning curve, using doc-enabled IDEs, like Netbeans, Aptana or Eclipse based IDEs. Also, most of the important and most used functions in jQuery were added as phpDoc, as a magic method of the **PheryResponse** class.

## Example code

Check the a lot of examples and code at http://phery-php-ajax.net/demo.php

## Full API Documentation

http://phery-php-ajax.net/docs/

## Documentation

It's really simple as

```php
<?php
	Phery::instance()
	->set(array(
		'function_name' => function($data){
				return
					PheryResponse::factory()
						->jquery('<div/>', array('text' => 'text content'))
						->appendTo('body')
						->call('func', 'list', true);
		}
	))->process();
?>
<!doctype html>
<html>
	<head>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
		<script src="phery.js"></script>
		<script>
			function func(name, override){
				/* function */
			}
		</script>
	</head>
	<body>
		<a data-remote="function_name">Click me</a>
	</body>
</html>
```

When clicking a link with data-remote, it will automatically call the "function_name" callback, that will return a response and apply everything automagically

Complete class declarations and functions at http://phery-php-ajax.net/docs/

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
	function pre_function_one($ajax_data, $callback_specific_data, $phery_instance)
	{
		// Trim the data, assuming every item is a string, and not an array of array
		return array_map('trim', $ajax_data);
	}

	function pre_function_two($ajax_data, $callback_specific_data, $phery_instance)
	{
		// Remove empty
		return array_filter($ajax_data);
	}

	function post_function($ajax_data, $callback_specific_data, $answer, $phery_instance)
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
	->set(array(
		'delete' => 'process_delete'
	))
	->callback(array(
		'before' => 'callback_function'
	))
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
		return PheryResponse::factory()->alert('Deleted');
	}
?>
```

#### Phery::args(array $data, $encoding = 'UTF-8')

Encode arguments that phery can understand (json_encode'd) that does inside data-args.
Doing it by hand can have some unwanted side effects, since the JSON must be perfectly made

```html
<a data-args="<?=Phery::args(array('id' => 1));?>" data-remote="remote">Click me</a>
```

#### Phery::is_ajax($is_phery = false)

Returns a boolean, checks if it's a AJAX request (checks for X-Requested-With header).
Set `$is_phery` to true to check specifically for phery call

```php
<?php
	if(Phery::is_ajax()){
		var_dump($_POST);
	} elseif (Phery::is_ajax(true)){
		Phery::respond(PheryResponse::factory());
		exit;
	}
?>
```

#### Phery::error_handler($errno, $errstr, $errfile, $errline)

Public static function that throws a exception and return a PheryResponse with the exception.
May be registered as a handler using `set_error_handler('Phery::error_handler', E_ALL);` for example

#### Phery::shudown_handler()

Public static function that should be used only with `register_shutdown_function('Phery::shutdown_handler');`
having no other useful meaning

#### Phery::respond($response, $compress = false)

Static function that makes it easy to return a `PheryResponse` anywhere.

```php
function error_handler(){
	Phery::respond(PheryResponse::factory()->exception('ERROR!'));
	exit;
}
```

#### Phery::instance()->views(array())

Set up the rendering functions for phery.view() on Javascript side. Automates the rendering of partials,
and you may apply transitions between pages. Ajaxifying your website reduces the round trip by 60% less
total content size, even less with GZIP

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
	))->process();
?>
```

#### Phery::instance()->process($last_call = true)

Takes just one parameter, $last_call, in case you want to call process() again later, with a different set.
**last_call** won't allow the process to return until you call it again or exit the code inside a callback function.

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

#### Phery::instance()->config(array())

Set configuration for the current instance of phery. Passed as an associative array, and can be passed when creating a new
instance.

* `exit_allowed` => `true`, Defaults to true, stop further script execution. Set this to false on frameworks that need to do proper cleanup
* `no_stripslashes` => `false`, Don't apply stripslashes on the args
* `exceptions` => `false`, Throw exceptions on errors
* `compress` => `false`, Enable/disable GZIP/DEFLATE compression, depending on the browser support. Don't enable it if you are using Apache DEFLATE/GZIP, or zlib.output_compression Most of the time, compression will hide exceptions, because it will output plain text while the content-type is gzip, unless you also enable `error_reporting`
* `error_reporting` => `false|E_ALL|E_DEPRECATED|...`, Error reporting temporarily using error_reporting(). 'false' disables the error_reporting and wont try to catch any error. Anything else than false will throw a PheryResponse->exception() with the message, code, line and file
* `csrf` => `false`, Enable CSRF protection, uses PHP sessions. The meta tag must go inside the head of your page using `<?php echo Phery::instance()->csrf(); ?>`

#### Phery::instance($config = null)

Singleton static method, ensures just one instance of phery, this is prefered over creating multiple instances

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
		'alias' => function(){
			return PheryResponse::factory();
		},
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
	function func($ajax_data, $callback_data, $phery_instance){
		// $ajax_data = data coming from browser, via AJAX
		//
		// $callback_data = can have anything you specify, plus additional information, like **submit_id** that
		// comes automatically from the AJAX request, containing the ID of the calling DOM element, if has an id="" set
		//
		// $phery_instance = the current instance of Phery
		//
		return PheryResponse::factory(); // In most cases, you'll want to return a PheryResponse object
	}
?>
```

#### Phery::instance()->csrf($check = false)

Create a new token inside PHP session to prevent CSRF attacks, and return as a `<meta>` tag.
Need to enable CSRF setting with `config()`

```php
<head>
	<?php echo Phery::instance()->csrf(); ?>
</head>
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

#### Phery::link_to($title, $function, array $attributes = array(), Phery $phery = null, $no_close = false)

Helper static method to create any element with AJAX enabled. Check sources, phpDocs or an IDE code hinting
for a better scoop and detailed info <http://phery-php-ajax.net/docs/class-Phery.html#_link_to>
When creating this element, if you use `data-related`, you can merge multiple forms in one AJAX call

```php
<?php echo Phery::link_to('link title', 'function_name', array('class' => 'red', 'href' => '/url')); ?>
```

#### Phery::form_for($action, $function, array $attributes = array(), Phery $phery = null)

Helper static method to open a form that will be able to execute AJAX submits.
Check sources, phpDocs or an IDE code hinting for a better scoop and detailed info
<http://phery-php-ajax.net/docs/class-Phery.html#_form_for>
When creating this element, if you use `data-related`, you can merge multiple forms in one AJAX call

```php
<?php echo Phery::form_for('/url-to-action/or/empty-means-current-url', 'function_name', array('class' => 'form', 'id' => 'form_id', 'submit' => array('disabled' => true, 'all' => true))) ?>
	<input type="text" name="text">
	<input type="password" name="pass">
	<input type="submit" value="Send">
</form>
```

#### Phery::select_for($function, $items, array $attributes = array(), Phery $phery = null)

Helper static method to display a select element that make AJAX calls on change.
<http://phery-php-ajax.net/docs/class-Phery.html#_select_for>
When creating this element, if you use `data-related`, you can merge multiple forms in one AJAX call

```php
<?php echo Phery::select_for('function_name', array(1 => 'true', 2 => 'hello', 3 => 'control'), array('selected' => 2)) ?>
```

#### Phery::coalesce(...)

Helper static method that returns the first non null/false/0 item (taken from MYSQL COALESCE). Notice that depending on error reporting, some notices will be THROWN, to make sure use @ in front of the variable

```php
<?php echo Phery::coalesce($undeclared_variable, UNDECLARED_CONSTANT, $data['no-index'], 10) // return 10 ?>
```

#### PheryResponse - Used as a return value to any function called using AJAX

Check the code completion using an IDE for a better view of the functions, read the source or check the examples
Any jQuery function can be called through PheryResponse, even custom ones, defined through `$.fn.extend` or `$.function`
Since version 2.0, you may nest PheryResponses, and the `this()` method was added, to access the calling element (or form)
directly from PHP
<http://phery-php-ajax.net/docs/class-PheryResponse.html>

```php
<?php
	function func($data, $params, $phery)
	{
		$user = ORM::factory('user', $data['id'])
		->values($data)
		->update();

		return
			PheryResponse::factory('#name')
			->html('<p>'.$user->name.'</p>')
			->show('fast')
			->jquery('.slider')
			->slider(array(
				'value' => 60,
				'orientation' => "horizontal",
				'range' => "min",
				'animate' => true
			))
			->jquery('<p/>', array('class' => 'attention', 'text' => 'Attention!'))
			->appendTo('header .messages')
			->add(PheryResponse::factory('p')->effect('highlight'))
			->addClass('changed');
	}
?>
```

#### PheryFunction - On-the-fly function callbacks

This class allows you to pass a string that will be made into an anonymous function, useful for jQuery functions that needs a callback.
You may bind parameters and variables that will be replaced inside the function, like:

```php
PheryFunction::factory('function(){ alert(":msg"); }')->bind(':msg', $msg);
```

Another example:

```php
<?php

function remote()
{
	return
		PheryResponse::factory()
		->this()
		->animate(
			array('opacity' => 0.3),
			1500,
			PheryFunction::factory('function(){ alert("done!"); }')
		);
}

Phery::instance()->set(array(
	'remote' => 'remote'
))->process();
```

#### PheryException - Exceptions that are thrown by phery, when enabled to do so, with some descriptive errors

Check the code completion using an IDE for a better view of the functions, read the source or check the examples http://phery-php-ajax.net/demo.php

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
	<input name="field[info][date]" type="date" value="12/12/1983">
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
		"name":"John Doe",
		"info": {
			"date":"12/12/1983"
		}
	},
	"breakfast":"eggs & bacon",
	"select":[
		1, 2
	]
}
```

#### $('form').reset()

An helper function that has been long missing from jQuery, to simply reset a form and remove all values from it. Can reset multiple forms at once

#### $('element').phery()

Returns functions associated with phery and the element

#### $('element').phery('remote') or $('element').phery().remote()

Trigger the AJAX call, takes no parameter. Executes the phery data associated with the element. Returns the jQuery AJAX object

```js
$('element').phery().remote(); // or $('element').phery('remote');
```

##### $('element').phery().exception(msg, data); // or $('element').phery('exception', msg, data);

Trigger the exception handler on the element, returns the $(element).phery()

```js
$('element').phery().exception('Exception!', {'data': true}); // or $('element').phery('exception', 'Exception!', {'data': true});
```

##### $('element').phery().append_args(...); // or $('element').phery('append_args', ...);

Append arguments to the current element. The initial value will decide how the parameters will behave in the future

```js
// If the element had {'undo':1}, after the call, it will have {'undo': 1, 'data': true}
$('element').phery().append_args({'data': true}); // or $('element').phery('append_args', {'data': true});
```

##### $('element').phery().set_args(...); // or $('element').phery('set_args', ...);

Set arguments to the current element. Overwrites any data previously set.
It cannot use single values, any string, number, etc will become a `[value]`
It's better to always pass at least an array or prefered an object

```js
$('element').phery().set_args({'data': true}); // or $('element').phery('set_args', {'data': true});
```

##### $('element').phery().get_args(...); // or $('element').phery('get_args');

Get arguments of the current element.

```js
console.log($('element').phery().get_args()); // or console.log($('element').phery('get_args'));
```

##### $('element').phery().make(); // or $('element').phery('make');

Add phery to the selected element, set the AJAX function name and you may pass arguments

```js
$('element').phery().make('test', {'loaded':true}); // or $('element').phery('make', 'test', {'loaded': true});
```

##### $('element').phery().remove(); // or $('element').phery('remove');

Clean up the element, and remove it from the DOM. It removes all data before so it won't memory leak

```js
$('element').phery().remove(); // or $('element').phery('remove');
```
##### $('element').phery().unmake(); // or $('element').phery('unmake');

Remove Phery AJAX functions on the select elements

```js
$('element').phery().unmake(); // or $('element').phery('unmake');
```

#### phery.remote(functionName, arguments, attributes, directCall)

Calls an AJAX function directly, without binding to any existing elements, the DOM element is created and removed on-the-fly
If directCall is false, it will return a `jQuery` element, if not, it will return an `jqXHR` object

* `functionName`: string, name of the alias defined in Phery::instance()->set() inside PHP
* `arguments`: object or array or variable, the best practice is to pass an object, since it can be easily accessed later through PHP, but any kind of parameter can be passed, from strings, ints, floats, and can also be null (won't be passed through ajax)
* `attributes`: object, set any additional information about the DOM element, usually for setting another href to it. eg: `{href: '/some/other/url?p=1'}`
* `directCall`: boolean, defaults to true, setting this to false, will return the created DOM element (invisible to the user) and can have events bound to it

```js
element = phery.remote('remote', {'key':'value'}, {'href': '/new/url'}, false);
element.bind({
	'phery:always': function(){
		$('body').append($(this));
	}
}).phery('remote');
```

### Options and global and element events

Global events will always trigger, and they first come empty and do nothing.
It's mainly useful to show/hide loading screens, update statuses, put an overlay over the page, or interact with other libraries

#### phery.on(event, cb)

These events are triggered globally, independently if called from an existing DOM element or through `phery.remote()`
The `event.target` points to the related DOM node (that was clicked, or the form that was submitted)

* `before`: `function (event)` Triggered before everything, happens right after `phery.remote()` call
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

phery.on('always', function(){
	$('#loading').fadeOut();
});

//or

phery.on({
	'before': function(){
		$('#loading').fadeIn();
	},
	'always': function(){
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

Reset the configuration to the defaults, see `phery.config()`

#### phery.log()

Wrapper for the console.log(), but keeps a local history, if you enable it in
`phery.config()`

#### phery.view(config)

Config the page to render AJAX partial views

```js
phery.view({
	// Must always be an ID and unique
	'#container': {
		// Optional, function to call before the
		// HTML was set, can interact with existing elements on page
		// The context of the callback is the container
		'beforeHtml': function(data){
			$('#menu a').removeClass('selected').filter('.' + data.class).addClass('selected');
		},
		// Optional, function to call to render the HTML,
		// in a custom way. This overwrites the original function,
		// so you might set this.html(html) manually.
		// The context of the callback is the container
		'render': function(html, data){
			/* this refers to the container, in this case #container */
			html = html.replace(' ass ', ' a** ');
			this.html(html);
			document.title = data.title;
		},
		// Optional, function to call after the HTML was set,
		// can interact with the new contents of the page
		// The context of the callback is the container.
		'afterHtml': function(data, passdata){
			if (window.history) {
				window.history.pushState(data, data.title, data.url);
			}
		},
		// Optional, defaults to a[href]:not(.no-phery,[target],[data-remote],[href*=":"],[rel~="nofollow"]).
		// Setting the selector manually will make it 'local' to the #container, like '#container a'
		// Links like <a rel="#nameofcontainer">click me!</a>, using the rel attribute will trigger too
		'selector': 'a',
		// Optional, array containing conditions for links NOT to follow,
		// can be string, regex and function (that returns boolean, receives the url clicked, return true to exclude)
		'exclude': ['/contact', /\d$/, function]
		// any other phery event, like beforeSend, params, etc
	}
});
```

Retrieve the data and functions associated with the container with:

```js
// return the instance of PheryView associated with the container
phery.view('#container')

// contains the data associated with the container, like every configuration (this is a clone of original data from 'view.phery')
phery.view('#container').data;
// Call the path to render a URL in the container
phery.view('#container').navigate_to('url/to/somewhere');
// Contains the container $(DOM) itself, got all the jquery functions on it
phery.view('#container').container;
// Check if the url is excluded, per config, returns true if excluded
phery.view('#container').is_excluded_url('url/path');
```

Example:

```js
/* Setup automatic view rendering using ajax */
phery.view({
	'#container':{
		/* We want only the links inside the container to be ajaxified */
		'selector':'a',
		/* Enable the browser history, and change the title */
		'afterHtml': function(data, passdata){
			document.title = data.title;
			if (window.history) {
				/* Good browsers get history API */
				if (typeof passdata['popstate'] === 'undefined'){
					window.history.pushState(data, data.title, data.url);
				}
			}
		},
		/* phery:params let us add params, that ends in callback params and wont get mixed with arguments */
		'params':function (event, data) {
            // from which location we are getting the call, PHP knows nothing about it
            data['origin'] = window.location.href;
        }
	}
});
/* Good browsers get back/forward button ajax navigation ;) */
window.onpopstate = function(e){
	phery.view('#container').navigate_to(document.location.href, null, {'popstate': true});
};
```

and the PHP side:

```php
<?php
Phery::instance()->views(array(
	'#container' => function ($data, $param){
		$render = pseudo_controller();

		return
			PheryResponse::factory()
				->render_view($render['html'] . $param['menu'], array('title' => $render['title']));
	}
))->process();

```

#### phery.config(key, value)

The current configuration options that are available in phery. Reminding that additional configurations and modifications on the AJAX calls can be done using [$.ajaxSetup()](http://api.jquery.com/jQuery.ajaxSetup)
The options can be set using the `key:value` in the `key` parameter, or using a string and value. Each option can be accessed using dot notation inside the string

```js
phery.config({
	'cursor': false,
	'enable.per_element.events': false
});

phery.config('debug.display.config', true);
```

### Misc options

* `cursor` (true / false, defaults to true): change the cursor to **wait/busy** on any ajax call
* `default_href` (string / false, defaults to false): set a common url to all calls, being able to override this to any `href` property attached to the element
* `ajax.retries` (int, defaults to 0): number of retries on error before returning the error callback
* `enable.log` (true / false, defaults to false): enable displaying of exceptions or errors, for debugging purposes
* `enable.log_history` (true / false, defaults to false): keep the log of errors  that can be accessed through `phery.log()`
* `enable.php_string_callbacks` (true / false, defaults to false): jQuery functions like `animate` or `each` that take callbacks, can have the callback defined as a string inside PHP if this is enabled.
* `enable.per_element.events` (true / false, defaults to true): enable `phery:*` events on each element
* `enable.clickable_structure` (true / false, defaults to false): enable clicking on HTML structural elements, like DIV, HTML, etc. They are disabled by default, but can be enabled per element using `data-clickable="1"`

### Debugging

* `debug.enable` (true / false, defaults to false): enable verbose to keep track of each step defined below
* `debug.display.events` (true / false, defaults to true): display events debug
* `debug.display.remote` (true / false, defaults to true): display remote calls
* `debug.display.config` (true / false, defaults to true): display config changes

### Delegation

If you specify a string, it will be appended `phery.config('delegate.confirm', 'focusin')` becomes `['click','focusin']`, passing a array, it wil be rewritten
`phery.config('delegate.confirm', ['focusin'])` becomes `['focusin']`

* `delegate.confirm` (selector => `[data-confirm]:not(form)`) (string, defaults to \['click'\]): Confirm alert
* `delegate.form` (selector => `form[data-remote]`) (string, defaults to \['submit'\]): Form submission
* `delegate.select_multiple` (selector => `select[data-remote][multiple]`) (string, defaults to \['blur'\]): Event on `select multiple` element
* `delegate.select` (selector => `select[data-remote]:not([multiple])`) (string, defaults to \['change'\]): Event on `select` element
* `delegate.tags` (selector => `[data-remote]:not(form,select)`) (string, defaults to \['click'\]): Clicks on elements, like `A`

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
## License

Released under the MIT license
