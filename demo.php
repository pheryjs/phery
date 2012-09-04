<?php
	//Ensure that it's completly compatible with strict mode and throws no notices or warnings, and not using any deprecated code
	error_reporting(E_ALL | E_STRICT);

	@ini_set('display_errors', 1);

	$memory_start = 0;
	$start_time = microtime(true);

	include ('Phery.php');

	function scrolltop()
	{
		return PheryResponse::factory('html,body')->scrollTop(0);
	}

	class myClass{
		function test($ajax_data, $callback_data)
		{
			return
				scrolltop()->merge(
				PheryResponse::factory('div.test')
				->filter(':eq(1)')
				->toggle('fast')
				->html($ajax_data['hi'])
				->jquery('a')
				// Create callbacks directly from PHP! No need to do this, just to show it's possible
				// White space (CRLF, tabs, spaces) doesnt matter to JSON, it just adds extra bytes to the response afterall
				->each(
<<<JSON
	function(i, el){
		console.log("inside each!", i);
		if ($(this).text().length > 17) {
			$(this).css({"color":"green","textDecoration":"none"});
		}
	}
JSON
));
		}

		static function test2($ajax_data, $callback_data)
		{
			// Integers must be typecast, because JSON will
			// turn everything to a string, because
			// "1" + "2" = "12"
			// 1 + 2 = 3
			foreach ($ajax_data as &$j) $j = (int)$j;

			return PheryResponse::factory()->call('test', $ajax_data);
		}

		function data($ajax_data, $callback_data)
		{
			return
				scrolltop()->merge(
				PheryResponse::factory($callback_data['submit_id']) // submit_id will have #special2
				->data('testing', array('nice' => 'awesome'))
				->j('body,html')
				->scrollTop(0)
				->jquery('div.test2')
				->css(array('backgroundColor' => '#f5a'))
				->animate(array(
					'width' => "70%",
					'opacity' => 0.8,
					'marginLeft' => "0.6in",
					'fontSize' => "1em",
					'borderWidth' => "10px"
				), 1500, 'linear',
<<<JSON
	function(){
		$(this).append("<br>yes Ive finished animating and fired from inside PHP as an animate() completion callback rawr!");
	}
JSON
				));
		}
	}

	// You can return a string, or anything else than a standard
	// response, that the phery:done event will be triggered
	// before the parsing of the usual functions, so you can parse by
	// your own methods, and signal that the event should halt there
	function test($args)
	{
		return json_encode(array('hi' => $args['hello'],'hello' => 'good'));
	}

	function trigger()
	{
		return
			PheryResponse::factory('div.test')
			->trigger('test')
			->jquery('<li/>') // Create a new element like in jQuery and append to the ul
			->css('backgroundColor', '#0f0')
			->html('<h1>Dynamically added, its already bound with phery AJAX upon creation because of jquery delegate()</h1><a data-remote="surprise">Click me (execute script calling window.location.reload)</a>')
			->appendTo('#add');
	}

	// data contains form data
	function form($data)
	{
		return
			scrolltop()->merge(
				PheryResponse::factory('div.test:eq(0)')
				->html(print_r($data, true))
			);
	}

	function thisone($data)
	{
		// When being called from non AJAX call, will be processed later in the body
		// set through 'respond_to_post'
		if ( ! Phery::is_ajax())
		{
			// You may do something different without AJAX,
			// better not to use a PheryResponse unless you're going to parse it later.
			// The best solution would be return an array or a new class, because everything
			// will be allowed when responding to post
			return array('error' => true, 'content' => 'Return this string for form submit', 'f' => $data['f']);
		}

		return
			PheryResponse::factory()
			->dump_vars($data);
	}

	function the_one_with_expr($data)
	{
		return
			PheryResponse::factory('.test2')
			->animate(array('opacity' => 0.3), 1500)
			->html($data['new-onthefly-var'])
			->show()
			->merge(thisone($data));
	}

	/**
	 * Callback that executes before calling the remote ajax function
	 * This is usually useful when dealing with repetitive tasks or to
	 * centralize all the common tasks to one big callback
	 */
	function pre_callback($data, $callback_specific_data_as_array)
	{
		// Dont mess with data that is submited without ajax
		if (Phery::is_ajax())
		{
			ob_start();
			var_export(array('$data' => $data));
			var_export(array('$callback_specific_data_as_array' => $callback_specific_data_as_array));
			$dump = ob_get_clean();
			$data['new-onthefly-var'] = $dump;
		}

		if (is_array($data))
		{
			foreach ($data as &$d)
			{
				if (is_string($d))
				{
					$d = strtoupper($d);
				}
			}
		}
		return $data; // Must return the data, or false if you want to stop further processing
	}

	/**
	 * Post callback that might add some info to the phery response
	 */
	function post_callback($data, $callback_specific_data_as_array, $PheryResponse)
	{
		if ($PheryResponse instanceof PheryResponse)
		{
			$PheryResponse->alert('alert added in post callback ;)');
		}
	}

	function timeout($data, $parameters)
	{
		$r = PheryResponse::factory();
		if (!empty($data['callback']) && !empty($parameters['retries']))
		{
			// The URL will have a _try_count when doing a retry
			return $r->alert('Second time it worked, no error callback call ;)');
		}
		sleep(60); // Sleep for 60 seconds to timeout the AJAX request, and trigger our retry
		return $r;
	}

	/**
	 * Callbacks to measure the memory used, for benchmarking reasons ;)
	 */
	function mem_start($data, $callback)
	{
		global $memory_start;
		$memory_start = memory_get_usage();
		return $data;
	}

	function mem_end($data, $callback, $phery_answer)
	{
		global $memory_start, $start_time;

		$mem =
					array(
						round(memory_get_peak_usage() / 1024, 2).'Kb',
						round((memory_get_usage() - $memory_start) / 1024, 2).'Kb',
						(string)(round(microtime(true) - $start_time, 6))
					);

		if (Phery::is_ajax())
		{
			if ($phery_answer instanceof PheryResponse)
			{
				$phery_answer->apply('memusage', $mem);
			}
		}
	}

	/**
	 * New instances of phery and our test class
	 */
	$instance = new myClass;
	$phery = new Phery;

	/* Pseudo page menu */
	$menu = '<a href="?page=home">home</a> <a href="?page=about">about us</a> <a href="?page=contact">contact us</a> <a href="?page=notfound">Doesnt exist</a>';

	/* Quick hack to emulate a controller based website */
	function pseudo_controller()
	{
		if (isset($_GET['page']))
		{
			switch ($_GET['page'])
			{
				case 'home':
$html = <<<HTML
<h1>Home</h1>
<p>Welcome to our website</p>
<p><img src="http://lipsum.lipsum.com/images/lorem.gif"></p>
HTML;
					return $html;
					break;
				case 'about':
$html = <<<HTML
<h1>About us</h1>
<p>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	Praesent ligula ante, auctor id commodo eu.
</p>
HTML;
					return $html;
					break;
				case 'contact':
$html = <<<HTML
<h1>Contact us</h1>
<p>Use the form below to contact us</p>
<form data-remote="form">
<p>
	<label>Name</label>
	<input name="name" type="text">
</p>
<p>
	<label>Email</label>
	<input name="email" type="email">
</p>
<p>
	<label>Message</label>
	<textarea name="message"></textarea>
</p>
<p>
	<input type="submit" value="Send">
</p>
</form>
HTML;
					return $html;
					break;
				default:
					return '<h1>404 Not Found</h1><p>The requested url was not found</p>';
					break;
			}
		}
		else
		{
			return '<h1>Welcome!</h1>';
		}
	}

	$content = pseudo_controller() . $menu;

	try{
		$phery->config(
			array(
				/**
				 * Throw exceptions and return them in form of PheryException,
				 * usually for debug purposes. If set to false (default), it fails
				 * silently
				 */
				'exceptions' => true,
				/**
				 * This allows the responses to receive data and respond EVEN if
				 * not called by ajax (ie on a browser with javascript disabled/blocked).
				 * Good to ensure forms submission
				 */
				'respond_to_post' => array('thisone'),
				/**
				 * Compress using DEFLATE or GZIP, in this order, whichever is available
				 * in the browser. Check if APACHE is already compressing it with gzip.
				 * If its already compressing, don't enable it.
				 */
				'compress' => true
			)
		)
		/**
		 * Set up the views, pass the global variable $menu to our
		 * container render callback
		 */
		->data(array('menu' => $menu))
		->views(array(
			'#container' => function($data, $param)
			{
				return
					PheryResponse::factory()
					->render_view(pseudo_controller().$param['menu']);
			}
		))
		/**
		 * Set the callbacks for all functions, just for benchmark
		 */
		->callback(array(
			'before' => 'mem_start',
			'after' => 'mem_end'
		))
		/**
		 * Set the aliases for the AJAX calls
		 */
		->set(array(
			// instance method call
			'test' => array($instance, 'test'),
			// regular function
			'test2' => 'test',
			// static function
			'test4' => array('myClass', 'test2'),
			// Lambda
			'test5' => function(){ return PheryResponse::factory()->redirect('http://www.google.com'); },
			// Use PHP-side animate() callback!
			'data' => array($instance, 'data'),
			// Trigger even on another element
			'trigger' => 'trigger',
			// Trigger even on another element
			'form' => 'form',
			// Call this function even if it's not been submitted by AJAX, but IS a post
			'thisone' => 'thisone',
			// Lambda, reload the page
			'surprise' => function ($data){
				return
					PheryResponse::factory()->script('window.location.reload(true)');
			},
			// Invalid Javascript to trigger "EXCEPTION" callback
			'invalid' => function(){
				return
					PheryResponse::factory()->script('if notvalid javscript')->jquery('a')->blah();
			},
			// Timeout
			'timeout' => 'timeout',
			// Select chaining results
			'chain' => function($data, $complement){
				$r = PheryResponse::factory($complement['submit_id'])->next();
				// If the select has a name, the value of the select element will be passed as
				// a key => value of it's name
				$html = array();
				switch (Phery::coalesce(@$data['named'], $data))
				{
					case 1:
						$html = array(
							'1' => '1-1',
							'2' => '1-2',
							'3' => '1-3'
						);
						break;
					case 2:
						$html = array(
							'1' => '2-1',
							'2' => '2-2',
							'3' => '2-3'
						);
						break;
					case 3:
						$html = array(
							'1' => '3-1',
							'2' => '3-2',
							'3' => '3-3',
						);
						break;
				}
				// Return a new select inside the adjacent divs
				return $r->html(Phery::select_for('msgbox', $html, array('selected' => '3')));
			},
			// just alertbox the data
			'msgbox' => function($data){
				return
					PheryResponse::factory()
					->alert($data);
			},
			// select multiple
			'selectuple' => function($data){
				return
					PheryResponse::factory()
					->alert(json_encode($data));
			},
			'before' => function($data){
				return
					PheryResponse::factory()
					->call($data['callback'], $data['alert']);
			},
			'img' => function($data){
				return
					PheryResponse::factory()
					->script('if(confirm("Do you wanna go to Wikipedia?")) window.location.assign("http://wikipedia.org");');
			},
		))
		/**
		 * process(false) mean we will call phery again with
		 * process(true)/process() to end the processing, so it doesn't
		 * block the execution of the other process() call
		 */
		->process(false);

		/**
		 * To separate the callback from the rest of the other functions,
		 * just call a second process()
		 */
		$phery
		/**
		 * Set the callbacks ONLY for "test3" and "the_one_with_expr"
		 */
		->callback(array(
			'before' => 'pre_callback',
			'after' => 'post_callback'
		))
		->set(array(
			// Set lambda with two alerts
			'test3' => function($args, $param)
			{
				// Lambda/anonymous function, without named parameters, using ordinal indexes
				return
					PheryResponse::factory()
					->alert($args[0])
					->alert($args[1])
					->alert($param['param1'])
					->alert((string)$param[0])
					->alert((string)$param[1])
					;
			},
			'the_one_with_expr' => 'the_one_with_expr',
		))
		/**
		 * Some extra data that will be passed to aliases functions
		 * and callbacks. You can pass as many arguments you want, just
		 * make sure to name them properly, so you dont get lost.
		 * If you dont pass an associative array, you'll have to access
		 * the arguments using ordinal indexes (that is the case with
		 * the 1 and the 'argument')
		 */
		->data(array('param1' => 'param2'), 1, 'argument')
		/**
		 * Finally, process for "test3" or "the_one_with_expr"
		 */
		->process(false);

		$phery
		->config(array(
			// Catch the errors
			'error_reporting' => E_ALL
		))
		->set(array(
			'on_purpose_exception' => function()
			{
				strlen($code);
			}
		))
		->process();

	} catch (PheryException $exc){
		/**
		 * will trigger for "nonexistant" call
		 * This will only be reached if 'exceptions' is set to TRUE
		 * Otherwise it will fail silently, and return an empty
		 * JSON response object {}
		 */
		die(
			PheryResponse::factory()
			->exception($exc->getMessage())
		);
	}

?>
<!doctype html>
<html>
	<head>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.js"></script>
		<meta charset="utf-8">
		<title>Phery</title>
		<script src="phery.js" type="text/javascript"></script>
		<script type="text/javascript">
			function test(number_array) {
				total = 0;
				for (var x in number_array){
					total += number_array[x];
				}
				alert(total);
			}

			var
				$peak,
				$usage,
				_page = window.location.href.replace(window.location.search, '');

			$(function(){
				// cache our DOM elements that will receive the memory info
				$peak = $('#peak'),
				$usage = $('#usage');
				$('#version').html('jQuery Version: ' + $().jquery);

				$('div.test').bind({
					'test':function(){ // bind a custom event to the DIVs
						$(this).show(0).html('triggered custom event "TEST"!');
					}
				});

				/*
				 *
				 * Manually process the result of ajax call, can be anything
				 *
				 */
				$('#special').bind({
					'phery:done': function(data, text, xhr){
						// The object will receive the text, return data from 'test' function, it's a JSON string
						alert(text);
						// Now lets convert back to an object
						var obj = $.parseJSON(text);
						console.log(obj);
						// Do stuff with new obj
						// Returning false will prevent the parser to continue executing the commands and parsing
						// for jquery calls, because this text/html answer won't have any
						return false;
					}
				})
				// The data-type must override the type to 'html', since the default is 'json'
				.data('type', 'html');

				/*
				 *
				 * Bind the phery:always, after data was received, and there was no error
				 *
				 */
				$('#special2').bind({
					'phery:always':function(xhr){
						var $this = $(this);
						$this.show(0);
						if ( $this.data('testing')){
							$('div.test2').text(('$.data for item "nice" is "' + $this.data('testing')['nice']) + '"');
						}
					}
				});

				/*
				 *
				 * Let's just bind to the form, so we can apply some formatting to the text coming from print_r() PHP
				 *
				 */
				$('form').bind({
					'phery:always':function(){
						$div = $('div.test:eq(0)');
						var text = $div.html();
						// This doesnt work for IE7 or IE8, no idea why, the CRLF wont be replaced by <br>
						$div.html('<pre>' + text + '</pre>');
					}
				});

				$form = $('#testform');

				var f = function(el, name){
					var $this = $(el);
					var $submit = $form.data('submit');
					if ($this.is(':checked')) {
						$submit[name] = true;
					} else {
						$submit[name] = false;
					}
					$form.data('submit', $submit);
				};

				$('#disable').click(function(){
					f(this, 'disabled');
				});

				$('#all').click(function(){
					f(this, 'all');
				});

				var
					$loading = $('#loading');

				/*
				 *
				 * Global phery events
				 *
				 * You can set global events to be triggered, in this case, fadeIn and out the loading div
				 * On global events, the current delegated dom node will be available in event.target
				 */
				phery.on({
					'before': function(event){
						$loading.removeClass('error').fadeIn('fast');
					},
					'always': function(event, xhr){
						$loading.fadeOut('fast');
					},
					'fail': function(event, xhr, status){
						$loading.addClass('error');
						if (status === 'timeout')
						{
							$(event.target).phery('exception', 'Timeout and gave up retrying!!');
							// or $(event.target).phery().exception('Timeout and gave up retrying!!');
						}
					},
					'exception': function(event, exception, data){
						if (data && 'code' in data) {
							var type = '';
							switch (data.code) {
								case <?php echo E_NOTICE; ?>:
									type = 'E_NOTICE';
									break;
								case <?php echo E_ERROR; ?>:
									type = 'E_ERROR';
									break;
								case <?php echo E_WARNING; ?>:
									type = 'E_WARNING';
									break;
								default:
									break;
							}

							$('#exceptions').append(
								$('<li/>', {
									'text': type + ': ' + exception + ' on file ' + data.file + ' line ' + data.line
								})
							);

						} else {
							$('#exceptions').append(
								$('<li/>', {
									'text': exception + ' on ' + event.target[0].tagName + (event.target.attr('id')?' (' + event.target.attr('id') + ')':'')
								})
							);
						}
					}
				});


				window.callme = function(e){
					alert(e);
				}

				// Modify the data before sending
				$('#modify').bind('phery:before', function(e){
					$(this).phery('set_args', {
						'alert': $(this).next('input').val(),
						'callback': 'callme'
					});
					// or $(this).phery().set_args(...)
				});

				phery.config({
					/*
					 * Retry one more time, if fails, then trigger events.error
					 */
					'ajax.retries': 1,
					/*
					 * Enable phery:* events on elements
					 */
					'enable.per_element.events': true,
					/*
					 * Enable sending "function(){}" closure callbacks
					 * directly from PHP. This is disabled by default,
					 * since it can lead to problems with other libraries
					 */
					'enable.php_string_callbacks': true,
					/*
					 * Enable options per element
					 */
					'enable.per_element.options': true,
					/*
					 * Enable logging and output to console.log.
					 * Shouldn't be enabled on production because
					 * of leaks
					 */
					'enable.log': true,
					/*
					 * Log messages that can be accessed on phery.log()
					 */
					'enable.log_history': true
				});

				// 5 seconds timeout for AJAX
				// any AJAX option can be set through jQuery
				$.ajaxSetup({timeout: 2000});

				$loading.fadeOut(0);

				$('#validate').click(function(){
					if ($(this).prop('checked')) {
						load_validate();
					}
				});

				$('#ajax').bind('click', function(){
					var $this = $(this);
					if ($this.prop('checked')) {
						/* Setup automatic view rendering using ajax */
						phery.view({
							'#container': {
								/* We want only the links inside the container
								 * to be ajaxified
								 */
								'selector': 'a'
							}
						});
						$('#container').bind({
							'phery:params': function(event, data){
								data['origin'] = window.location.href;
							}
						})
					} else {
						phery.view({
							// Disable view, page will work normally
							'#container': false
						});
					}
				}).triggerHandler('click');

				$('#debug').click(function(){
					phery.config({
						/*
						 * Enable debug verbose
						 */
						'debug.enable': !phery.config('debug.enable')
					});
					var enabled = phery.config('debug.enable');

					$(this)
					.css('color',enabled?'#0f0':'#f00')
					.find('span')
					.text(enabled?'ON':'OFF');
				});
			});

			function load_validate() {
				var name = 'validate-javascript';
				if ($('#' + name).size()) return;

				var script = $('<script/>', {
					'src': 'https://raw.github.com/jzaefferer/jquery-validation/master/jquery.validate.js',
					'type': 'text/javascript',
					'id': name
				});

				$(script).bind('load', Validatize);

				$('head')[0].appendChild(script[0]);
			}

			function memusage(peak, usage, time){
				$peak.text('Peak PHP memory usage: ' + peak);
				$usage.text('Delta PHP memory usage: ' + usage + ' / ' + (time) + ' secs');
			}
		</script>

		<script type="text/javascript">
			function Validatize() {
				$('#testform').validate({
					'submitHandler': function(form)
					{
						$(form).phery('remote');
						// or $(form).phery().remote();
					}
				});
			}
		</script>

		<style type="text/css">
			a{
				text-decoration: underline;
				cursor: pointer;
				padding: 5px;
				background: #eee;
			}
			label{
				display:block;
				margin-bottom: 10px;
			}
			input,select,textarea{
				margin-bottom: 10px;
			}
			input[type="text"],select,textarea{
				min-width: 300px;
			}
			h1,h2 {
				margin-top: 20px;
				margin-bottom: 20px;
				font-size: 24px;
			}
			h2{
				font-size: 15px;
			}
			body{
				padding: 30px;
				padding-top: 0px;
			}
			#loading {
				position:fixed;
				right: center;
				top: center;
				padding: 14px;
				display: block;
				z-index:2;
				font-size: 20px;
				font-weight: bold;
				background: #ddd;
				-moz-box-shadow: 0px 0px 4px #000;
				-webkit-box-shadow: 0px 0px 4px #000;
				box-shadow: 0px 0px 4px #000;
				-moz-border-radius: 3px;
				-webkit-border-radius: 3px;
				border-radius: 3px;
			}
			.error{
				background: #f00 !important;
			}
			#exceptions {
				position: fixed;
				bottom: 0;
				right: 0;
				z-index: 10;
				background: #000;
				max-width: 300px;
				color: #fff;
				padding-right: 20px;
			}
			#debug {
				color:#f00;
				cursor: pointer;
			}
			#exceptions li {
				padding: 4px;
			}
			.external {
				padding-right: 15px;
				background: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAVklEQVR4Xn3PgQkAMQhDUXfqTu7kTtkpd5RA8AInfArtQ2iRXFWT2QedAfttj2FsPIOE1eCOlEuoWWjgzYaB/IkeGOrxXhqB+uA9Bfcm0lAZuh+YIeAD+cAqSz4kCMUAAAAASUVORK5CYII=") no-repeat scroll right center transparent;
			}
			#memory {
				display:block;
				position:fixed;
				right:10px;
				top:10px;
				background:#fff;
				box-shadow:2px 2px 5px #000;
				padding: 10px;
			}
		</style>
	</head>
	<body>
		<div id="loading">Loading...</div>
		<div id="memory">
			<p id="usage"></p>
			<p id="peak"><?php echo 'PHP clean load memory peak usage: ', round(memory_get_peak_usage() / 1024),'Kb'; ?></p>
			<p id="version"></p>
		</div>

		<h2 id="debug">Check the Firebug/Console for the messages this page outputs, DEBUG is set to <span>OFF</span> (click to change)</h2>

		<hr>
		<div class="test" style="border:solid 1px #000; padding: 20px;">Div.test</div>
		<div class="test test2" style="border:solid 1px #000; padding: 20px;">Div.test 2</div>

		<ul id="add">
			<li>
				<h2>Magic call to jquery toggle() on 'div.test' using filter(':eq(1)')</h2>
				<?php echo Phery::link_to('Instance method call', 'test', array('confirm' => 'Are you sure?', 'args' => array('hi' => 'test'))); ?>
			</li>
			<li>
				<h2>Returns plain text in this case, processed manually with the phery:done javascript callback. id => #special</h2>
				<?php echo Phery::link_to('Regular function', 'test2', array('confirm' => 'Are you sure?', 'id' => 'special', 'args' => array('hello' => 'Im a named argument :D'))); ?>
			</li>
			<li>
				<h2>Call a lambda function that returns an alert according to the parameters passed, which is 'first', then 'second', set to uppercase by the callback function</h2>
				<?php echo Phery::link_to('Call to lambda', 'test3', array('args' => array('first','second'))); ?>
			</li>
			<li>
				<h2>Call to an existing javascript function an array of integers</h2>
				<?php echo Phery::link_to('Static call from class', 'test4', array('args' => array(1, 2, 4, 6, 19))); ?>
			</li>
			<li>
				<h2>Leaves the page, HTML tag is set to 'button'</h2>
				<?php echo Phery::link_to('Redirect to google.com', 'test5', array('confirm' => 'Are you sure?', 'tag' => 'button')); ?>
			</li>
			<li>
				<h2>Call a non-existant function with 'exceptions' set to true</h2>
				<?php echo Phery::link_to('Call a non-existant function', 'nonexistant'); ?>
			</li>
			<li>
				<h2>Call to a function that returns an animate() with a callback and executes before and after callbacks</h2>
				<?php echo Phery::link_to('Testing callbacks', 'the_one_with_expr', array('id'=> 'look-i-have-an-id', 'args' => array(1,2,3,'a','b','c'))); ?>
			</li>
			<li>
				<h2>Manual phery.remote('test', {'hi': 'test'}) onclick , with arguments</h2>
				<a onclick="phery.remote('test', {'hi': 'test'});">Inline onclick event</a>
			</li>
			<li>
				<h2>Trigger event 'test' on both divs (and a green on-the-fly div appended at the bottom)</h2>
				<?php echo Phery::link_to('Trigger event', 'trigger'); ?>
			</li>
			<li>
				<h2>Using HTML 'b' tag, chain commands for css() and animate(), id #special2</h2>
				<?php echo Phery::link_to('Test data and check it on callback phery:always', 'data', array('tag' => 'b', 'id' => 'special2')); ?>
			</li>
			<li>
				<h2>On-purpose 404 ajax call</h2>
				<?php echo Phery::link_to('Trigger global fail and change background to red', 'nonexistant', array('href' => '/pointnowhere')); ?>
			</li>
			<li>
				<h2>Global exception handler</h2>
				<?php echo Phery::link_to('Trigger global exception callback returning invalid javascript', 'invalid'); ?>
			</li>
			<li>
				<h2>Retry on timeout</h2>
				<?php echo Phery::link_to('Timeout retry then give up', 'timeout'); ?>
			</li>
			<li>
				<h2>Retry on timeout, then accept second call</h2>
				<?php echo Phery::link_to('Timeout retry then work', 'timeout', array('args' => array('callback' => true))); ?>
			</li>
			<li>
				<h2>Custom error reporting for a bit of code</h2>
				<?php echo Phery::link_to('Exception', 'on_purpose_exception'); ?>
			</li>
			<li>
				<h2>Put data in the element on-the-fly, using phery:before event and will call a callback with on-the-fly arguments</h2>
				<?php echo Phery::link_to('I have no arguments, click me', 'before', array('id' => 'modify')); ?>
				<input type="text" id="alert" value="Yes its an alert" /> &laquo; change this to set the data to be sent
			</li>
			<li>
				<h2>Image tag click (using the <i>Phery::link_to</i> builder)</h2>
				<?php echo Phery::link_to(null, 'img', array('src' => '//upload.wikimedia.org/wikipedia/commons/6/63/Wikipedia-logo.png', 'tag' => 'img', 'style' => 'cursor:pointer')) ?>
			</li>
		</ul>

		<h1>Selects</h1>

		<label>No name on select element</label>
		<?php echo Phery::select_for('chain', array('1' => 'one', '2' => 'two', '3' => 'three'), array('id' => 'incoming')); ?>
		<div></div>

		<label>Named select (will influence the data being passed to the function)</label>
		<?php echo Phery::select_for('chain', array('1' => 'one', '2' => 'two', '3' => 'three'), array('name' => 'named', 'id' => 'incoming2')); ?>
		<div></div>

		<label>Multiple (will trigger "onblur" instead of "onchange")</label>
		<?php echo Phery::select_for('selectuple', array('1' => 'one', '2' => 'two', '3' => 'three'), array('multiple' => true, 'selected' => array(1,2,3))); ?>

		<h1>Forms</h1>

		<p>
			<input id="disable" type="checkbox"> Enable submitting disabled elements
		</p>

		<p>
			<input id="all" type="checkbox"> Enable submitting all fields, even empty ones
		</p>

		<p>
			<input id="validate" type="checkbox"> Enable <a href="https://github.com/jzaefferer/jquery-validation" class="external" target="_blank">jQuery Validate</a> on form
		</p>

		<?php
			/* form_for is a helper function that will create a form that is ready to be submitted through phery
			 * any additional arguments can be passed through 'args', works kinda like an input hidden,
			 * but will only be submitted if javascript is enabled
			 * -------
			 * 'all' on 'submit' will submit every field, even checkboxes that are not checked
			 * 'disabled' on 'submit' will submit fields that are disabled
			 */
			echo Phery::form_for('', 'form', array('id' => 'testform', 'submit' => array('disabled' => false, 'all' => false), 'args' => array('whadyousay' => 'OH YEAH')));
		?>
			<fieldset>
				<label for="first_name">First Name:</label>
				<input id="first_name" type="text" name="field[name][first]" class="required" maxlength="12" minlength="3">
				<label for="last_name">Last Name:</label>
				<input id="last_name" type="text" name="field[name][last]" class="required" maxlength="36" minlength="3">

				<label>Gender:</label>
				<label for="gender_male">Male:</label>
				<input type="radio" name="gender" id="gender_male" value="Male">
				<label for="gender_female">Female:</label>
				<input type="radio" id="gender_female" name="gender" value="Female">
				<input type="hidden" name="super[unnecessarily][deep][name][for][a][input]" value="really">
				<label>Favorite Food:</label>
				<label for="food_steak">Steak:</label>
				<input type="checkbox" id="food_steak" name="food[]" value="Steak"> <!-- The correct would be food[steak] unless there's a huge list of unknown size -->
				<label for="food_pizza">Pizza:</label>
				<input type="checkbox" id="food_pizza" name="food[]" value="Pizza"> <!-- The correct would be food[pizza] unless there's a huge list of unknown size -->
				<label for="food_chicken">Chicken:</label>
				<input type="checkbox" id="food_chicken" name="food[]" value="Chicken"> <!-- The correct would be food[chicken]	unless there's a huge list of unknown size -->
				<label for="food_nuggest">Nuggets (no value):</label>
				<input type="checkbox" id="food_nuggest" name="nuggets"> <!-- Best to always provide a value. In this case it will be submitted as "on" when checked -->
				<label for="quote">Favorite Quote</label>
				<textarea cols="20" id="quote" name="quote" rows="5">Enter your favorite quote!</textarea>
				<label for="level_education">Select a Level of Education:</label>
				<select id="level_education" name="education">
					<option value="Jr.High">Jr.High</option>
					<option value="HighSchool">HighSchool</option>
					<option value="College">College</option>
				</select>
				<label for="TofD">Select your favorite time of day:</label>
				<select size="3" id="TofD" name="TofD" multiple class="required"> <!-- The correct would be TofD, but phery takes in account the 'multiple' attribute, and submit it as an array -->
					<option value="Morning">Morning</option>
					<option value="Day">Day</option>
					<option value="Night">Night</option>
				</select>
				<label for="disabled-input">Disabled input (can be submitted with submit => array('disabled' => true))</label>
				<input type="text" id="disabled-input" name="disabled-input" value="this is disabled and wont be submitted" disabled>
				<p><input type="submit" value="Send form"></p>
			</fieldset>
		<?php echo '</form>'; ?>
		<?php echo Phery::form_for('', 'thisone', array('id' => 'unob_form')); ?>
			<fieldset>
				<h5>This is a 'respond to post' form (means it doesn't need javascript/AJAX to function, but work both ways). The data will still be submitted and available. Disable javascript and submit the form to check it out</h5>
				<?php
					if (($answer = $phery->answer_for('thisone')))
					{
						echo '<h1>This form was submitted without javascript.<br/>Raw contents of POST<br/><br/><pre>'.htmlentities(print_r($_POST, true)).'</pre></h1>';
						echo '<h2>This is the function result:</br></h2>';
						echo '<pre>'.print_r($answer, true).'</pre>';
					}
				?>
				<label for="f">Data</label>
				<input name="f" id="f" type="text" value="testing">
				<p><input type="submit" value="Send form"></p>
			</fieldset>
		<?php echo '</form>';	?>

		<hr>

		<h1>Automagically rendering of views</h1>

		<p>
			<input id="ajax" checked type="checkbox"> Enable AJAX view rendering
		</p>

		<p>
			<a href="?" rel="#container">This link is outside the container, but works using rel attribute</a>
		</p>

		<p>
			<a href="?">This one doesn't</a>
		</p>

		<div id="container">
			<?php echo $content; ?>
		</div>

		<ul id="exceptions">
			<li style="cursor:pointer" onclick="$(this).parent().find('li').not(this).remove()">[ Clear ]</li>
		</ul>
	</body>
</html>