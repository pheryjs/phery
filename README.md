# PHP + jQuery + AJAX + File upload
# Enter phery.js, the swiss army knife of jQuery and PHP

## Highlights

* Subscribe on the client and publish on the server, and execute functions bound to elements fast and easy, within their own contexts.
* Declarative and unobstrusive HTML instead of imperative Javascript
* AJAX file uploads for good browsers with no effort, just make the form pheryfied
* Really small footprint, no external dependencies beside jQuery
* Coded with performance and security in mind
* Code, server and client side, completely commented, all methods have docs
* Javascript config locking and Object.freeze to make it harder for tampering
* The same way you write jQuery in Javascript, you write it in PHP
* Turns static Javascript app code into a dynamic language with a chainable query builder style
* Unit tested, mocha for the javascript and PHPUnit_Selenium for PHP
* Support for old, new and upcoming jQuery functions out of the box, since nothing is hard coded
* You may nest <em>PheryResponses</em> the way you would with jQuery
* You may set Javascript callbacks directly from PHP
* You can call jQuery plugin functions directly from PHP (think colorbox for example)
* Built-in CSRF mechanism that makes each AJAX request signed
* Inline conditional execution using `unless` or `incase`
* Access or call any Javascript code on the page, set/unset variables, add functions to the page on-the-fly
* Plays really well as the transport for client-side frameworks and libraries as such [AngularJS](http://angularjs.org/), [Ember.js](http://emberjs.com/), [Knockout.js](http://knockoutjs.com/), [ExtJS](http://www.sencha.com/products/extjs), [Backbone](http://backbonejs.org/)

## Example code

Check the a lot of examples and code at http://phery-php-ajax.net/demo.php

## Introduction

**This library is PSR-0 Compatible**

The main goal of this library is to make jQuery (and Javascript), be completely dynamic instead of static and unchangeable code, returning commands on-the-fly from the server and execute them in order on the client, while still manipulating the DOM and dealing with callbacks.

This library unleashes everything you expect from an AJAX library, with nested responses, merging and unmerging responses, direct access to the DOM element that is making the AJAX call. It's loosely based on jquery-ujs for Rails concept.

It's a straightforward and powerful AJAX library with direct integration and mapping of all jQuery functions in PHP, the mapping is extended to custom functions set by $.fn, can create elements just like `$('<div/>')` does, as **phery.js** creates a seamless integration with jQuery functions, through AJAX to PHP functions, as you can call a PHP function using AJAX

All jQuery functions listed in here are available to call directly from the _PheryResponse_ class: <http://api.jquery.com/> including any new versions of jQuery that comes out, its compatible with jQuery forever, since there are no hardcoded jQuery functions in the PHP code. No need to update phery.js, as it will continue to work with future versions of jQuery automatically (unless jQuery change anything in core that breaks any existing function).

phery.js uses HTML5 `data` attributes to achieve this, and no additional libraries (besides jQuery, since it's a plugin) are needed, even for Internet Explorer. Links and forms will still be able to send GET/POST requests and function properly without when no javascript isn't enabled, because it doesn't use obtrusive techniques and relies on event delegation.

Strict standards for PHP 5.3+ and advised to use jQuery 1.8+. Being just one PHP file, and one javascript file, it's pretty easy to _carry_ around or to implement in PHP auto-load scenarios, plus it's really FAST! Average processing time is around 2ms with vanilla PHP, according to Firebug and in the demo page

The *magic\_quotes\_gpc* directive is **DEPRECATED** in 5.3 and **REMOVED** in 5.4, since you are always 100% responsible for the security of your data, so escape your text accordingly to avoid SQL injection or XSS attacks.

All AJAX requests are sent as POST only, so it can still interact with GET query strings, like paginations and such (?p=1 / ?p=2 / ...).

The code is mostly commented using phpDoc and jsDoc, for a steep learning curve, using doc-enabled IDEs, like Netbeans, Aptana, Phpstorm and Eclipse based IDEs. Also, most of the important and most used functions in jQuery were added as phpDoc (from <http://api.jquery.com/>), as a magic method of the _PheryResponse_ class .

## What do I need?

* PHP 5.3.3+
* jQuery 1.8+
* Firefox 3.6+, IE 8+, Chrome 12+, Safari 5+, Opera 10+

## Upcoming

* Mocha unit tests for the Javascript side running against multiple versions of jQuery on the repo
* PHPUnit_Selenium test cases for Javascript/PHP testing sources on the repo

## Upgrading from < 2.4.0

Version 2.4.0 isn't a drop-in replacement because of some changes, so you might need to update your code

* All the calls to `PheryResponse::factory()->this()` must be called to `PheryResponse::factory()->this`
* All the calls to `PheryResponse::factory()->jquery()->somemethod()` must be called to `PheryResponse::factory()->jquery->somemethod()`
* If you are using jQuery >=1.9, some functions were removed from the jQuery core, so fix your code accordingly
* If you found yourself adding `data-remote` manually to HTML elements like `<a data-remote="remote">stuff</a>`, you have to fix it manually or use the prefered way `Phery::link_to`, since the `data-remote` is now `data-phery-remote`
* The same with `data-confirm`, you have to fix it manually or use the prefered way `Phery::link_to`, since the `data-confirm` is now `data-phery-confirm`

## Full PHP API Documentation

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
	    <?php echo Phery::link_to('Click me', 'function_name'); ?>
	</body>
</html>
```

When clicking a link with `data-phery-remote`, it will automatically call the "function_name" callback, that will return a response and apply everything automagically

Complete class declarations and functions at http://phery-php-ajax.net/docs/

### PHP server-side

There is an special global setting to make phery.js to not expose information about your server on exceptions, that is a static configuration `Phery::$expose_paths`, which is set to `false` by default.
If you want to know exactly which file is generating the exception, use `Phery::$expose_paths = true;`

#### Phery - The main class, that should be reused everywhere (singleton style), but you can create many instances just fine

```php
<?php
Phery::instance($config)->(...);
?>
```

#### Phery::instance()->callback(array('before' => array(), 'after' => array()))

Add a callback that will execute in all functions that are registered using `Phery::instance()->set()`, and can be any number of callbacks,
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

Returns a boolean, checks if it's a AJAX request (checks for `X-Requested-With` header).
Set `$is_phery` to true to check specifically for phery.js call

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

#### Phery::respond($response, $echo = false)

Static function that makes it easy to return a `PheryResponse` anywhere.

```php
function my_own_error_handler(){
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

* `exit_allowed` => `true`
Defaults to true, stop further script execution. Set this to false on frameworks that
need to do proper cleanup and/or event triggering

* `exceptions` => `false`
Throw exceptions on errors

* `set_always_available` => `false`
By default, the function Phery::instance()->set() will only register functions when the current
request is an AJAX call, to save resources. In order to use Phery::instance()->get_function()
anytime, you need to set this config value to true

* `error_reporting` => `false|E_ALL|E_DEPRECATED|...`
Error reporting temporarily using error_reporting(). 'false' disables the error_reporting and wont try to catch any error.
Anything else than false will throw a PheryResponse->exception() with the message, code, line and file

* `csrf` => `false`
Enable CSRF protection, uses PHP sessions. The meta tag _MUST_ be inside the head of your page using
`<?php echo Phery::instance()->csrf(); ?>`

* `return` => `false`
Setting this to true will make `Phery::instance()->process()` method return the answer (and won't exit the code, overrides `exit_allowed`),
so you may do your cleanups and set the answer where you may please

#### Phery::instance($config = null)

Singleton static method, ensures just one instance of phery.js, this is prefered over creating multiple instances

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

#### Phery::instance()->unset_function($name)

Unset any previously function set with `Phery::instance()->set()`

#### Phery::instance()->csrf($check = false)

Create a new token inside PHP session to prevent CSRF attacks, and return as a `<meta>` tag.
Need to enable CSRF setting with `config()`

```php
<head>
	<?php echo Phery::instance()->csrf(); ?>
</head>
```

#### Phery::factory(array $config = null)

Creates a new instance of phery.js, that is chainable

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

You may create a non-clickable container that hold many elements that may serve as a template.
Using `link_to` as the following:

```php
<?php echo Phery::link_to('link title', 'function_name', array('class' => 'red', 'href' => '/url'), null, true); ?>
  <span class="name"></span>
  <button></button>
</div>
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
Any jQuery function can be called through `PheryResponse`, even custom ones, defined through `$.fn.extend` or `$.function`
Since version 2.0, you may nest `PheryResponses`, and the `this` property was added, to access the calling element (or form)
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
			/* Set the HTML of the #name */
			->html('<p>'.$user->name.'</p>')
			->show('fast')
			/* Call a jQuery plugin, same as $('.slider').slider(...)  */
			->jquery('.slider')
			->slider(array(
				'value' => 60,
				'orientation' => "horizontal",
				'range' => "min",
				'animate' => true
			))
			/* Creating elements on-the-fly, same $('<p/>') */
			->jquery('<p/>', array('class' => 'attention', 'text' => 'Attention!'))
			->appendTo('header .messages')
			/* Nesting responses */
			->add(PheryResponse::factory('p')->effect('highlight'))
			/* Apply to both p that was created and existing p's on the page */
			->addClass('changed');
	}
?>
```

A few special attributes:

* Responses can be serialized and saved on the database without any special effort
* You may pass functions and nest many responses, either as setters or getters (check the `demo.php` file)
* The method chaining nature of the response make it easy to return, in any order, everything you need
* You can execute next-in-chain methods conditionally using `unless` and `incase` (act as `if not` and `if`)
* `->this->` refer to the calling DOM element, like doing `$(this).`
* `->window->` translates to `$(window).`
* `->document->` translate to `$(document).`
* `->jquery->` translate to `$.`
* `->anyobj->` makes you able to access ANY global object and call methods on it. For example `$r->my_obj->execute('param1');` will do a `window.my_obj.execute('param1');`. It's a shortcut to the `PheryResponse->access()` method
* `PheryResponse->call` and `PheryResponse->apply` sets the context (`this`) of the called function to the passed object, for example `PheryResponse->call(array('obj', 'method'))`, the `this` inside method is `obj`

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
		->this
		->animate(
			array('opacity' => 0.3),
			1500,
			PheryFunction::factory('function(){ alert("done animating!"); }')
		);
}

Phery::instance()->set(array(
	'remote' => 'remote'
))->process();
```

#### PheryException - Exceptions that are thrown by phery, when enabled to do so, with some descriptive errors

Check the code completion using an IDE for a better view of the functions, read the source or check the examples http://phery-php-ajax.net/demo.php

### Javascript client-side

#### Special selectors

There are three jQuery helper selectors: `:phery-remote`, that return elements if they have phery remote functions attached,
`:phery-confirm` if the element got any confirmation before click/submit
and `:phery-view`, to determine if the calling element belongs to the view
You may check elements that have phery.js enabled using

```js
$('.widget:eq(0)').is(':phery-remote');
```

or selecting all the elements for a class

```js
$('.widget:phery-remote:visible').phery('remote'); // call their remote functions
```

Or combine them in the `remotes` function, to execute them in order

```js
// call their remote functions in order
phery.remotes($('.widget:phery-remote:visible')).done(function(){
  // do your thing
});
```

#### $('form').serializeForm(opts)

Generate an object serialized with unlimited depth from forms. Opts can be defined as:

* `disabled`: boolean, process disabled form elements, defaults to false
* `all`: boolean, include all elements from a form, including null ones, assigning an integer to the item, defaults to false
* `empty`: boolean, setting to false will skip empty fields (won't be submitted), setting to true will submit empty key:values pairs, including non checked radio and checkboxes, this overrides "all" setting, defaults to true
* `files_apart`: boolean, Return an object comprising of `{'files': {}, 'inputs': {}}`, so form inputs doesn't get mixed with file inputs

**This function can also be called directly on `select`, `input` (all types) and `textarea` DOM elements, and the transformations will happen automatically**

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
	<input type="file" name="file">
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
	],
	"file": [object File]
}
```

* if the `files_apart` is set to true :

```js
{
    "inputs": {
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
	},
	"files": {
	    "file": [object File]
	}
}
```

#### $('form').reset()

An helper function that has been long missing from jQuery, to simply reset a form and remove all values from it. Can reset multiple forms at once

#### $(el).phery()

Returns functions associated with phery.js and the element, they extend the `$.fn` part of jQuery

##### $(el).phery('remote', \[args\])
###### $(el).phery().remote(args)

Trigger the AJAX call, can pass an additional object.
Executes the phery.js data associated with the element.
Returns the jQuery AJAX object.
The data that is passed to the function will overwrite
the variables in the object if the have the same name.

```js
$(el).phery().remote();
// or $(el).phery('remote');
```

An element created with

```php
<?php echo Phery::link_to('Click me', 'function', array('args' => ('id' => 1, 'append' => true)); ?>
```

When calling e.g: `$('a').phery('remote', {'append': false});`, the `append` setting will be rewritten, so be careful
when passing temporary arguments to the `phery('remote')` function. All arguments passed using this function
are temporary, they don't get stored in the element, even if the value already exists in the element.

##### $(el).phery().data(key, value);
###### $(el).phery('data', key, value);

If this function is called without any arguments, will return all phery data associated with the current element.

```js
$(el).phery('data');
```

##### $(el).phery().exception(msg, data);
###### $(el).phery('exception', msg, data);

Trigger the exception handler on the element.
Returns the current jQuery elements.

```js
$(el).phery().exception('Exception!', {'data': true});
// or $(el).phery('exception', 'Exception!', {'data': true});
```

##### $(el).phery().append_args(...);
###### $(el).phery('append_args', ...);

Append arguments to the current element. The initial value will decide how the parameters will behave in the future.
These changes are not temporary, they last as long the DOM element is on the page
This function return the `phery()` of the element, so you may chain `remote()`

```js
// If the element had {'undo':1}, after the call, it will have {'undo': 1, 'data': true}
$(el).phery().append_args({'data': true}).remote();
// or $(el).phery('append_args', {'data': true});
```

##### $(el).phery().set_args(...);
###### $(el).phery('set_args', ...);

Set arguments to the current element. Overwrites any data previously set.
It cannot use single values, any string, number, etc will become a `[value]`
It's better to always pass preferably an object, or at least an array.
These changes are not temporary, they last as long the DOM element is on the page
This function return the `phery()` of the element, so you may chain `remote()`

```js
$(el).phery().set_args({'data': true}).remote();
// or $(el).phery('set_args', {'data': true});
```

##### $(el).phery().get_args(...);
###### $(el).phery('get_args');

Get data arguments of the current element.

```js
console.log($(el).phery().get_args());
// or console.log($(el).phery('get_args'));
```

##### $(el).phery().make();
###### $(el).phery('make');

Add phery.js to the selected element, set the AJAX function name and you may pass arguments.
Returns the current jQuery elements.

```js
$('a.ajaxify-me').phery().make('test', {'loaded':true});
// or $(el).phery('make', 'test', {'loaded': true});
```

It will make the `a` call `test` with arguments `{loaded: true}`

##### $(el).phery().remove();
###### $(el).phery('remove');

Clean up the element, and remove it from the DOM. It removes all data before so it won't memory leak on IE

```js
$(el).phery().remove();
// or $(el).phery('remove');
```

##### $(el).phery().inprogress();
###### $(el).phery('inprogress');

Returns boolean if there's already an AJAX call going on. You may prevent double form submissions for example
checking if element is in progress, returning false if there's already another in progress

```js
$('form').on('phery:before', function(){
  var $this = $(this);
  if ($this.phery('inprogress')){
    return false; // will cancel the new action
  }
});
```

You may disabled it automatically per element, when creating the element with `Phery::link_to` or `Phery::form_for`
passing `array(only => true)` or you may set it programatically using `el.phery('data', 'only', true);`
Notice that animations and asynchronous functions won't count as "in progress".

##### $(el).phery().subscribe({'name': function(){}), remove);
###### $(el).phery('subscribe', {'name': function(){}}, remove);

Subscribe to a topic and receive data from the server (or other part of the client code through `publish`)
Internally, it uses `jQuery.Callbacks`, so they will fire in order they were added.

```js
var fn = {
            'login': function(login, message){
                // true / 'message'
            }
         };
$(el).phery('subscribe', fn);
// remove it
$(el).phery('subscribe', fn, true);
```

if you set the `remove` argument to `true`, the callback will be removed

##### $(el).phery().publish('name', args);
###### $(el).phery('publish', 'name', args);

Publish a message on the topic with arguments (that are optional). The args passed must be an array (think jQuery `trigger`)

```js
   $('#login').phery('publish', 'login', [true, 'message']);
```

##### $(el).phery().one(args);
###### $(el).phery('one', args);

Executes the phery.remote on only one element from the jQuery element stack and returns a promise (like `phery.remote` does)

```js
$(el).phery('one', {id: 1}).then(
    function(){
        alert('success!');
    },
    $(el2).phery().one
); //executes the phery.remote on el2 only if the ajax on el fails
```


##### $(el).phery().unmake(unbind = false);
###### $(el).phery('unmake');

Remove phery.js AJAX functions on the select elements. Setting the unbind parameter to true will
also unbind the phery.js events that were previously set
Returns the current jQuery elements.

```js
$(el).phery().unmake();
 $(el).phery('unmake');
```

#### phery.subscribe(name, args)

Create a subscription without attaching to any DOM elements. Takes almost the same parameters as `phery.remote`

```js
var triggery = phery.subscribe('reset', {
    'subscription1': function(){
        // do your stuff
    },
    'subscription2': function(data){
        // json data ;)
    }
}, {id: 1}, {target: '/ajax/'});

phery.timer(triggery, 100); // poll every 100 miliseconds
```

#### phery.timer(element, miliseconds)

Make an ajax call automatically every miliseconds. It uses setTimeout instead of setInterval, returns two functions
to start or stop the timer. You can't pass a `phery.remote()` call directly to this function, it need to be either
a DOM element or in form of an array that you would pass in `phery.remotes`

```js
var call1 = phery.remote('remote1', null, null, false);
var call2 = phery.remote('remote2', null, null, false);

var timer = phery.timer(call1);
var timer2 = phery.timer(call2, 1000);
timer2.stop();
timer.start(1000);

phery.timer([
    ['remote1'],
    ['remote2']
], 1000);
```

#### phery.broadcast(name, args)

Broadcast a topic to all elements that has this topic subscribed. The args must be passed as an array.

```js
phery.broadcast('reset', ['param1','param2']);
```


#### phery.remotes(array)

Call a series of AJAX calls in order, waiting for the last call to finish.
Returns a promise for all the queued calls, so you can watch it with `progress`, and use `then` (or a chain of `then`).
Calls will be made in sequence regardless if they were successiful or not

An array of array of arguments, the same you'd call phery.remote with.

```js
phery.remotes([
	['function',{args:1}], //same as phery.remote('function', {args: 1});
	['function2'], // same as phery.remote('function2');
	['function3', null, {target:'/target'}] // same as phery.remote('function3', null, {target: '/target'});
]);
```

You may also pass a jQuery set of phery.js-ready elements

```js
phery.remotes($('.containers:not(.loaded)'));
```

This isn't the same as doing `$('.containers:not(.loaded)').phery('remote')`, because in that case
elements will be called at once, asynchronously, with no progress or any feedback.
Also you may observe the progress of the functions using the `progress` as part of the promise:

```js
phery.remotes($('.containers:not(.loaded)')).progress(function(jqxhr){
  //'this' is the current element or item in the array
  //'jqxhr' is the current AJAX jqXHR object from jQuery.
  // you may watch the progress of each jqxhr in case of multiple uploads
});
```

#### phery.json(function_name, args, callback)

Calls an AJAX function, pass some args if needed (pass null for no arguments) and the callback, containing a `function(data){}`
Usage:

```js
phery.json('remotefunc', {id: 4, first: true}, function(data){
    console.log(data);
});
```

It's a shorthand for the following code:

```js
    var el = phery.remote(remote, null, {'temp':true}, false);
    el.on('phery:json', function(event, data){
        return cb(data);
    });
    if (args !== undefined && args !== null) {
        el.phery('remote', args);
    } else  {
        el.phery('remote');
    }
```


#### phery.element(function_name, attrs)

Shorthand for `phery.remote('function', null, attrs, false)` to create reusable AJAX elements with their own element that isn't bound to the DOM (but may eventually be appended to the DOM)

* `function_name`: string, name of the alias defined in `Phery::instance()->set()` inside PHP
* `attr`: object, set any additional information about the DOM element, usually for setting another href to it. eg: `{href: '/some/other/url?p=1'}`

```js
var element = phery.element('function');
element.phery('remote', {id: 1});
element.on('phery:json', function(data){
  console.log(data);
});
element.subscribe('hello', function(){
  alert('Hello');
});
```

#### phery.remote(function_name, args, attr, direct_call)

Calls an AJAX function directly, without binding to any existing elements, the DOM element is **created** and **removed** on-the-fly
If directCall is false, it will return a `jQuery` `a` element, if not, it will return an `jqXHR` object

* `function_name`: string, name of the alias defined in `Phery::instance()->set()` inside PHP
* `args`: object or array or variable, the best practice is to pass an object, since it can be easily accessed later through PHP, but any kind of parameter can be passed, from strings, ints, floats, and can also be null (won't be passed through ajax)
* `attr`: object, set any additional information about the DOM element, usually for setting another href to it. eg: `{href: '/some/other/url?p=1'}`
* `direct_call`: boolean, defaults to true, setting this to false, will return the created DOM element (invisible to the user) and can have events bound to it, but will be removed right after the response is processed

```js
phery
.remote('remote', {'key':'value'}, {'href': '/new/url'}, false)
.on({
	'phery:done': function(){
		$('body').append($(this));
	}
})
.phery('remote');
```

When setting `direct_call` to `false`, the returned element can be reused many times, using `phery('remote')`, to explicitly remove it, you need
to call `phery('remove')`

If you wish to remove it as soon as the call ends, pass in the `attr` parameter, the `temp` option:

```js
phery.remote('remote', {'id': 1}, {'temp': true}, false).phery('remote');
```

If `direct_call` is `undefined` or `true` (the default) the element is removed and cleaned after each remote call.

### Options and global and element events

Global events will always trigger, and they first come empty and do nothing.
It's mainly useful to show/hide loading screens, update statuses, put an overlay over the page, or interact with other libraries.
Not to confuse with the document event bubble of these events.
Please notice that when setting views, `phery remote` elements inside a view that doesn't do `navigate_to` calls, will have
side effects for events that propagated from the children elements, so when you set an event, like `beforeSend` on your view
if you execute any other phery event that isn't the selector for your in-view link, the `beforeSend` of the child element
will propagate to the view container, and will trigger it's event.

#### phery.on(event, cb)

These events are triggered globally, independently if called from an existing DOM element or through `phery.remote()`
The `event.target` points to the related DOM node (that was clicked, or the form that was submitted), if any.
When calling `phery.remote`, the `event.target` will be the detached DOM element that were created on-the-fly
to make the AJAX call, and isn't appended to the page.
You may check if the element is temporary using `if (event.$target.phery('data', 'temp')){ /* is temp */ }`
Also, phery provides a shortcut to the event jQuery element through `event.$target`, that is the same as doing `$(event.target)`,
but it's ready to use.

* `before`: `function (event)`
Triggered before everything, happens right after `phery.remote()` call. Issuing an `event.stopImmediatePropagation()` after
returning false inside the `before` callback, will make it so the `always` event won't trigger.
* `after`: `function (event)`
Triggered after all the data was parsed.
* `beforeSend`: `function (event, xhr)`
Triggered before sending the data through AJAX, Useful to add any CSRF protections here
* `done`: `function (event, data, text, xhr)`
Triggered just before the answer from the response was received successfully and will start to process the data. Returning false halts the processing, make sure to return true
* `always`: `function (event, xhr)`
Triggered after the data was processed. and is triggered if there was no error.
* `fail`: `function (event, xhr, status, error)`
When an error happens when requesting to the provided URL. It won't be triggered if the PHP code fails to execute
* `exception`: `function (event, exception)`
Will be called if any problem happens while processing data, or executing jquery calls
* `json`: `function (event, obj)`
Returns the json object sent from PHP
* `progress`: `function (event, progress)`
File upload progress (not available in <= IE9). Can be accessed through the promise `progress(callback)` too
* `params`: `function (event, params)`
Pass extra params to the request. Won't overwrite existing params for security reasons

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
	// Any selector, prefered to be a ID
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

#### phery.lock_config()

Locks the configuration, so that nobody can change the configuration again using `phery.config()`
Works as an extra safety measure against people messing with your project settings

```js
phery.config({
	'cursor': false,
	'enable.per_element.events': false
}).lock_config();
```

### Misc options

* `cursor` (true / false, defaults to true):
Change the cursor to **wait/busy** on any ajax call
* `default_href` (string / false, defaults to false):
Set a common url to all calls, being able to override this to any `href` property
attached to the element
* `ajax.retries` (int, defaults to 0):
Number of retries on error before returning the error callback
* `enable.log` (true / false, defaults to false):
Enable displaying of exceptions or errors, for debugging purposes
* `enable.autolock` (true / false, defaults to false):
Locks the config for the instance after the window has completly loaded, making it
impossible to make changes to it, as an extra safety measure against tampering, and
works the same way as calling `phery.lock_config()` but on `$(window).on('load')`.
* `enable.log_history` (true / false, defaults to false):
Keep the log of errors  that can be accessed through `phery.log()`
* `enable.only` (true / false, defaults to false):
Make all AJAX calls, per element, be called one at a time. Multiple clicks or form submissions
won't make new AJAX until the last one is done. This option can be set using `$(el).phery('data', 'only', true);`
or creating the element using `<?php Phery::link_to('Clicky', 'function', array('only' => true)); ?>` or `data-phery-only="1"`
* `enable.php_string_callbacks` (true / false, defaults to false):
jQuery functions like `animate` or `each` that take callbacks, can have the callback
defined as a string inside PHP if this is enabled. You may also create a function that
will have the current calling element as context (the `this` keyword)
* `enable.per_element.events` (true / false, defaults to true):
Enable `phery:*` events on each element
* `enable.clickable_structure` (true / false, defaults to false):
Enable clicking on HTML structural elements, like DIV, HTML, etc. They are disabled by default,
but can be enabled manually and locally per element using `data-phery-clickable="1"` to the tag,
or using `$('div').phery('data', 'clickable', 1)`

### Inline

* `inline.enable` (true / false, defaults to false):
Enables phery.js to load responses inline, using `phery.load('<?=PheryResponse::factory()->render(); ?>');` or ready-to-use string `<?php PheryResponse::factory()->inline_load(true); ?>`
will generate the `phery.load` string automatically.
* `inline.once` (true / false, defaults to false):
Make it so it can only be loaded once on page load, and won't be able to call it again later, for security reasons.

### Debugging

* `debug.enable` (true / false, defaults to false):
Enable verbose to keep track of each step defined below. Don't enable it in production since it's really verbosely
* `debug.display.events` (true / false, defaults to true):
Display events debug
* `debug.display.remote` (true / false, defaults to true):
Display remote calls
* `debug.display.config` (true / false, defaults to true):
Display config changes

### Delegation

If you specify a string, it will be appended `phery.config('delegate.confirm', 'focusin')` becomes `['click','focusin']`, passing a array, it wil be rewritten
`phery.config('delegate.confirm', ['focusin'])` becomes `['focusin']`

* `delegate.confirm` (selector => `[data-confirm]:not(form)`) (string, defaults to \['click'\]):
Confirm alert
* `delegate.form` (selector => `form[data-remote]`) (string, defaults to \['submit'\]):
Form submission
* `delegate.select_multiple` (selector => `select[data-remote][multiple]`) (string, defaults to \['blur'\]):
Event on `select multiple` element
* `delegate.select` (selector => `select[data-remote]:not([multiple])`) (string, defaults to \['change'\]):
Event on `select` element
* `delegate.tags` (selector => `[data-remote]:not(form,select)`) (string, defaults to \['click'\]):
Clicks on elements, like `A`

#### Per element events

Per element events are the same from global events. Refer to `phery.on` above for description for each event.
They can be disabled using `phery.config('enable.per_element.events', false);` since they are enabled by default
These events will bubble to the document DOM, so you may catch them using, for example:

```js
$(document).on('phery:json', 'a.special', function(e, json){
    // e.target is the current 'a' DOM node, and e.$target is the current $(node)
    // this will only trigger for "a" DOM elements that have the .special class
});
// this can be done using phery.on as well
phery.on({
   'json': function(e, json){
      if (e.$target.is('a.special')) {

      }
   }
});
```

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
