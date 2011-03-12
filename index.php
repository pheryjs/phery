<?php
	//Ensure that it's completly compatible with strict mode and throws no notices or warnings
	error_reporting(-1);

	include ('phery.php');

	class myClass{
		function test($ajax_data, $callback_data)
		{
			return
				phery_response::factory('div.test')
				->filter(':eq(1)')
				->toggle('fast')
				->html($ajax_data['hi'])
				->jquery('a')
				// Create callbacks directly from PHP! No need to do this, just to show it's possible
				// White space doesnt matter to JSON, it just adds extra bytes to the response afterall
				->each('  function(i, el){
					if (typeof console != "undefined") {
						console.log("inside each!", i);
					}
					if ($(this).text().length > 17) {
						$(this).css({"color":"green","textDecoration":"none"});
					}
				}');
		}

		static function test2($ajax_data, $callback_data)
		{
			// Integers must be typecast, because JSON will
			// turn everything to a string, because
			// "1" + "2" = "12"
			// 1 + 2 = 3
			foreach($ajax_data as &$arg) $arg = (int)$arg;
			return phery_response::factory()->call('test', $ajax_data);
		}

		function data($ajax_data, $callback_data)
		{
			return
				phery_response::factory($callback_data['submit_id']) // submit_id will have #special2
				->data('testing', array('nice' => 'awesome'))
				->jquery('div.test2')
				->css(array('backgroundColor' => '#f5a'))
				->animate(array(
					'width' => "70%",
					'opacity' => 0.8,
					'marginLeft' => "0.6in",
					'fontSize' => "1em",
					'borderWidth' => "10px"
				), 1500, 'linear',
				'function(){ 
					$(this).append("<br>yes Ive finished animating and fired inside PHP callback rawr!");
				}');
		}
	}

	// You can return a string, or anything else than a standard
	// response, that the ajax:success event will be triggered
	// before the parsing of the usual functions, so you can parse by
	// your own methods, and signal that the event should halt there
	function test($args)
	{
		return json_encode(array('hi' => $args['hello'],'hello' => 'good'));
	}

	function trigger()
	{
		return
			phery_response::factory('div.test')
			->trigger('test')
			->jquery('<li/>') // Create a new element and append to the ul
			->css('backgroundColor', '#0f0')
			->html('<h1>Dynamically added, it already bound with AJAX upon creation</h1><a data-remote="surprise">Click me (execute script calling window.location.reload)</a>')
			->appendTo('#add');
	}

	// data contains form data
	function form($data)
	{
		return phery_response::factory('div.test:eq(0)')->html(print_r($data, true))->jquery('window')->scrollTop(0);
	}

	function thisone($data)
	{
		$data = print_r($data, true);
		// When being called from non AJAX call, will be processed later in the body
		if ( ! phery::is_ajax())
		{
			// You may do something different without AJAX,
			// better not to use a phery_response unless you're going to parse it later.
			// The best solution would be return an array or a new class, because everything
			// will be allowed when called unobstructively
			return array('error' => true, 'content' => 'Return this string', 'data' => $data);
		}
		return phery_response::factory()->alert('Ajax submitted form. Data:'."\n\n".$data);
	}

	function the_one_with_expr($data)
	{
		return
			phery_response::factory('.test2')
			->animate(array('opacity' => 0.9), 1500)
			->html($data['new-onthefly-var'])
			->show()
			->merge(thisone($data));
	}

	function pre_callback($data, $callback_specific_data_as_array)
	{
		ob_start();
		echo "AJAX DATA: \r\n";
		var_dump($data);
		echo "CALLBACK DATA: \r\n";
		var_dump($callback_specific_data_as_array);
		$dump = ob_get_clean();
		$data['new-onthefly-var'] = $dump;
		foreach ($data as &$d){
			if (is_string($d))
				$d = strtoupper($d);
		}
		return $data; // Must return the data, or false if you want to stop further processing
	}

	function post_callback($data, $callback_specific_data_as_array, $phery_response)
	{
		if ($phery_response instanceof phery_response)
			$phery_response->alert('alert added in post callback ;)');
		return true;
	}

	$memory_start = 0;

	function mem_start($data, $callback)
	{
		global $memory_start;
		$memory_start = memory_get_usage();
		return $data;
	}

	function mem_end($data, $callback, $phery_answer)
	{
		if ($phery_answer instanceof phery_response)
		{
			global $memory_start;
			$phery_answer->call('memusage', round(memory_get_peak_usage() / 1024).'Kb', round((memory_get_usage() - $memory_start) / 1024).'Kb');
		}

		return true;
	}

	$instance = new myClass;
	$phery = new phery;

	try{
		$phery->config(
			array(
				'exceptions' => true, // Throw exceptions and return them in form of phery_exception, usually for debug purposes
				'unobstructive' => array('thisone')
			)
		)
		->callback(array(
			'pre' => 'mem_start',
			'post' => 'mem_end'
		))
		->set(array(
			'test' => array($instance, 'test'), // instance method call
			'test2' => 'test', // regular function
			'test4' => array('myClass', 'test2'), // static function
			'test5' => function(){ return phery_response::factory()->redirect('http://www.google.com'); }, // Lambda
			'data' => array($instance, 'data'), // Unbind ajax from all elements
			'trigger' => 'trigger', // Trigger even on another element
			'form' => 'form', // Trigger even on another element
			'thisone' => 'thisone', // Call this function even if it's not been submitted by AJAX, but IS a post
			'surprise' => function ($data){
				return
					phery_response::factory()->script('window.location.reload(true)');
			},
			'invalid' => function(){
				return phery_response::factory()->script('if notvalid javscript')->jquery('a')->blah();
			}
		))
		->process(false);

		// To separate the callback from the rest of the other functions,
		// just call a second process()
		$phery
		->set(array(
			'test3' => function($args, $callback)
			{ // Lambda/anonymous function, without named parameters, using ordinal indexes
					return phery_response::factory()->alert($args[0])->alert($args[1]);
			},
			'the_one_with_expr' => 'the_one_with_expr',
		))
		->callback(array(
			'pre' => 'pre_callback',
			'post' => 'post_callback'
		))
		->data('param1', 'param2')
		->
		process();
	} catch (phery_exception $exc){
		// will trigger for "nonexistant"
		// This will only be reached if 'exceptions' is set to TRUE
		// Otherwise it will fail silently, and return an empty
		// JSON response object {}
		echo phery_response::factory()->alert($exc->getMessage());
		exit;
	}

?>
<!doctype html>
<html>
	<head>
		<script src="http://code.jquery.com/jquery-1.5.1.js"></script>
		<script src="phery.js"></script>
		<script>
			/* <![CDATA[ */
			function test(number_array) {
				total = 0;
				for (x in number_array){
					total += number_array[x];
				}
				alert(total);
			}

			var
				$peak,
				$usage;

			$(function(){
				$peak = $('#peak'),
				$usage = $('#usage');

				$('div.test').bind({
					'test':function(){ // bind a custom event to the DIVs
						$(this).show(0).html('triggered custom event "TEST"!');
					}
				});

				/* Manually process the result of ajax call, can be anything */
				$('#special').bind('ajax:success', function(data, text, xhr){
					// The object will receive the text, return data from 'test' function, it's a JSON string
					alert(text);
					// Now lets convert back to an object
					var obj = $.parseJSON(text);
					window.log(obj);
					// Do stuff with new obj
					// Returning false will prevent the parser to continue executing the commands
					return false;
				}).data('type', 'html'); // The data-type must override the type to 'html', since the default is 'json'

				/* Bind the ajax:complete, after data was received, and there was no error */
				$('#special2').bind({
					'ajax:complete':function(xhr){
						var $this = $(this);
						$this.show(0);
						if ( $this.data('testing'))
							$('div.test2').text(('$.data for item "nice" is "' + $this.data('testing')['nice']) + '"');
					}
				});

				// Let's just bind to the form, so we can apply some formatting to the text coming from print_r() PHP
				$('form').bind({
					'ajax:complete':function(){
						$div = $('div.test:eq(0)');
						var text = $div.html();
						// This doesnt work for IE7 or IE8, no idea why, the CRLF wont be replaced by <br>
						$div.html(text.replace(/\r\n|\n/g, '<br/>').replace(/\s/g, "&nbsp;&nbsp;"));
					}
				})

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
				}

				$('#disable').click(function(){
					f(this, 'disabled');
				});

				$('#all').click(function(){
					f(this, 'all');
				});

				var
					$loading = $('#loading');

				// You can set global events to be triggered, in this case, fadeIn and out the loading div
				$.phery.events.before = function(){
					$loading.removeClass('error').fadeIn('fast');
				}

				$.phery.events.complete = function(){
					$loading.fadeOut('fast');
				}

				$.phery.events.error = function(){
					$loading.addClass('error');
				}

				$.phery.events.exception = function(el, exception){
					alert(exception)
				}

				$loading.fadeOut(0);
			});

			function memusage(peak, usage){
				$peak.text('Peak ajax memory usage: '+peak);
				$usage.text('Delta ajax memory usage: '+usage);
			}

			/* ]]> */
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
			h1 {
				margin-top: 20px;
				margin-bottom: 20px;
				font-size: 15px;
			}
			#loading {
				position:fixed;
				right: 10px;
				top: 10px;
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
		</style>
	</head>
	<body>
		<p id="usage"></p>
		<p id="peak"><?php echo 'Page load memory peak usage: ', round(memory_get_peak_usage() / 1024),'Kb'; ?></p>

		<hr>
		<div id="loading">Loading...</div>
		<div class="test" style="border:solid 1px #000; padding: 20px;">Div.test</div>
		<div class="test test2" style="border:solid 1px #000; padding: 20px;">Div.test 2</div>

		<ul id="add">
			<li>
				<h1>Magic call to jquery toggle() on 'div.test' using filter(':eq(1)')</h1>
				<?php echo phery::link_to('Instance method call', 'test', array('confirm' => 'Are you sure?', 'args' => array('hi' => 'test'))); ?>
			</li>
			<li>
				<h1>Returns plain text in this case, processed manually with the ajax:success javascript callback. id => #special</h1>
				<?php echo phery::link_to('Regular function', 'test2', array('confirm' => 'Are you sure?', 'id' => 'special', 'args' => array('hello' => 'Im a named argument :D'))); ?>
			</li>
			<li>
				<h1>Call a lambda function that returns an alert according to the parameters passed, which is 'first', then 'second', set to uppercase by the callback function</h1>
				<?php echo phery::link_to('Call to lambda', 'test3', array('args' => array('first','second'))); ?>
			</li>
			<li>
				<h1>Call to an existing javascript function an array of integers</h1>
				<?php echo phery::link_to('Static call from class', 'test4', array('args' => array(1, 2, 4, 6, 19))); ?>
			</li>
			<li>
				<h1>Leaves the page, HTML tag is set to 'button'</h1>
				<?php echo phery::link_to('Redirect to google.com', 'test5', array('confirm' => 'Are you sure?', 'tag' => 'button')); ?>
			</li>
			<li>
				<h1>Call a non-existant function with 'exceptions' turned on</h1>
				<?php echo phery::link_to('Call a non-existant function', 'nonexistant'); ?>
			</li>
			<li>
				<h1>Call to a function that returns an animate() with a callback and executes pre and post callbacks</h1>
				<?php echo phery::link_to('Testing callbacks', 'the_one_with_expr', array('id'=> 'look-i-have-an-id', 'args' => array(1,2,3,'a','b','c'))); ?>
			</li>
			<li>
				<h1>Manual callRemote() onclick , with arguments</h1>
				<a onclick="$.callRemote('test', {'hi': 'test'});">Inline onclick event</a>
			</li>
			<li>
				<h1>Trigger event 'test' on both divs (and a surprise)</h1>
				<?php echo phery::link_to('Trigger event', 'trigger'); ?>
			</li>
			<li>
				<h1>Using HTML 'b' tag, chain commands for css() and animate(), id #special2</h1>
				<?php echo phery::link_to('Test data and check it on callback ajax:complete', 'data', array('tag' => 'b', 'id' => 'special2')); ?>
			</li>
			<li>
				<h1>On-purpose 404 ajax call</h1>
				<?php echo phery::link_to('Trigger global error and change background to red', 'nonexistant', array('href' => '/pointnowhere')); ?>
			</li>
			<li>
				<h1>Global exception handler</h1>
				<?php echo phery::link_to('Trigger global exception callback returning invalid javascript', 'invalid'); ?>
			</li>
		</ul>

		<h1>Forms</h1>

		<p>
			<input id="disable" type="checkbox"> Enable/Disable submitting disabled elements
		</p>

		<p>
			<input id="all" type="checkbox"> Enable/Disable submitting all fields, even empty ones
		</p>

		<?php
			// form_for is a helper function that will create a form that is ready to be submitted through phery
			// any additional arguments can be passed through 'args', works kinda like an input hidden,
			// but will only be submitted if javascript is enabled
			// -------
			// 'all' on 'submit' will submit every field, even checkboxes that are not checked
			// 'disabled' on 'submit' will submit fields that are disabled
			echo phery::form_for('', 'form', array('id' => 'testform', 'submit' => array('disabled' => false, 'all' => false), 'args' => array('whadyousay' => 'OH YEAH')));
		?>
			<fieldset>
				<label>First Name:</label>
				<input type="text" name="field[name][first]" maxlength="12">
				<label>Last Name:</label>
				<input type="text" name="field[name][last]" maxlength="36">

				<label>Gender:</label>
				<label>Male:</label>
				<input type="radio" name="gender" value="Male">
				<label>Female:</label>
				<input type="radio" name="gender" value="Female">
				<input type="hidden" name="super[unnecessarily][deep][name][for][a][input]" value="really">
				<label>Favorite Food:</label>
				<label>Steak:</label>
				<input type="checkbox" name="food[]" value="Steak"> <!-- The correct would be food[steak] unless there's a huge list of unknown size -->
				<label>Pizza:</label>
				<input type="checkbox" name="food[]" value="Pizza"> <!-- The correct would be food[pizza] unless there's a huge list of unknown size -->
				<label>Chicken:</label>
				<input type="checkbox" name="food[]" value="Chicken"> <!-- The correct would be food[chicken]	unless there's a huge list of unknown size -->
				<label>Nuggets (no value):</label>
				<input type="checkbox" name="nuggets"> <!-- Best to always provide a value. In this case it will be submitted as "on" when checked -->
				<label>&nbsp;</label>
				<textarea wrap="physical" cols="20" name="quote" rows="5">Enter your favorite quote!</textarea>
				<label>Select a Level of Education:</label>
				<select name="education">
					<option value="Jr.High">Jr.High</option>
					<option value="HighSchool">HighSchool</option>
					<option value="College">College</option>
				</select>
				<label>Select your favorite time of day:</label>
				<select size="3" name="TofD" multiple> <!-- The correct would be TofD, but phery takes in account the 'multiple' attribute -->
					<option value="Morning">Morning</option>
					<option value="Day">Day</option>
					<option value="Night">Night</option>
				</select>
				<label>Disabled input (can be submitted with submit => array('disabled' => true))</label>
				<input type="text" name="disabled-input" value="this is disabled and wont be submitted" disabled>
				<p><input type="submit" value="Send form"></p>
			</fieldset>
		</form>
		<?php echo phery::form_for('', 'thisone', array('id' => 'unob_form')); ?>
			<fieldset>
				<h5>This is an unobstructive form (means it doesn't need javascript/AJAX to function, but work both ways). The data will still be submitted and available. Disable javascript and submit the form to check it out</h5>
				<?php
					if (($answer = $phery->answer_for('thisone')))
					{
						echo '<h1>This form was submitted without javascript. Raw contents of POST = '.htmlentities(print_r($_POST, true)).'</h1>';
						echo '<h2>This is the function result: "'.htmlentities(print_r($answer, true)).'" without the quotes</h2>';
					}
				?>
				<label>Data</label>
				<input name="f" type="text" value="testing">
				<p><input type="submit" value="Send form"></p>
			</fieldset>
		</form>
	</body>
</html>