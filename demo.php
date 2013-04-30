<?php
//Ensure that it's completly compatible with strict mode and throws no notices or warnings, and not using any deprecated code
error_reporting(-1);

if (version_compare(PHP_VERSION, '5.3.3', '<'))
{
	die('This demo needs at least PHP 5.3.3');
}

ini_set('display_errors', 1);

$end_line = 902; date_default_timezone_set('UTC');
$memory_start = 0;
$start_time = microtime(true);

require_once 'Phery.php';

PheryResponse::set_global('global', true); //sleep(2); // uncomment to emulate latency

// Create a named response so we can include it later by the name
PheryResponse::factory()
->j('<div/>', array(
	'css' => array('cursor' => 'pointer','border' => 'solid 3px #000'
	)
))
->html('This is a <i>new</i> <b>element</b>')
->one('click', PheryFunction::factory(array(
		//'function(){',
			'var $this = $(this);',
			'$(this).fadeOut("slow").promise().done(function(){ $this.remove(); });',
		//'}'
	)
))
->prependTo('body')
->set_response_name('my name is'); // << Name of this response

PheryResponse::factory('html,body')->scrollTop(0)->set_response_name('scrollTop');

class myClass {

	function test($ajax_data, $callback_data)
	{
		return

			PheryResponse::factory('div.test')
			->merge('scrollTop') // merge the named response
			->filter(':eq(1)')
			->addClass('fast')
			->text($ajax_data['hi'])
			->jquery('a')
			// Create callbacks directly from PHP! No need to do this, just to show it's possible
			// White space (CRLF, tabs, spaces) doesnt matter to JSON, it just adds extra bytes
			// to the response afterall. It's created through the special class PheryFunction
			->each(PheryFunction::factory(
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
		foreach ($ajax_data as &$j)
		{
			$j = (int)$j;
		}

		return PheryResponse::factory()->call('test', $ajax_data);
	}

	function data($ajax_data, $callback_data, Phery $phery)
	{
		return
			PheryResponse::factory($callback_data['submit_id']) // submit_id will have #special2
			->merge('scrollTop')
			->data('testing', array('nice' => 'awesome'))
			->jquery('div.test2')
			->css(array('backgroundColor' => '#f5a'))
			->animate(array(
					'width' => "70%",
					'opacity' => 0.8,
					'marginLeft' => "0.6in",
					'fontSize' => "1em",
					'borderWidth' => "10px"
				), 1500, 'linear', PheryFunction::factory(
<<<JSON
	function(){
		$(this).append("<br>yes Ive finished animating and fired from inside PHP as an animate() completion callback using PheryFunction rawr!");
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
	return json_encode(array('hi' => $args['hello'], 'hello' => 'good'));
}

function trigger()
{
	return
		PheryResponse::factory('div.test')
		->trigger('test')
		->jquery('<li/>') // Create a new element like in jQuery and append to the ul
		->css('backgroundColor', '#0f0')
		->html('<h1>Dynamically added, its already bound with phery.js AJAX upon creation because of jquery delegate()</h1>' . Phery::link_to('Click me (execute script calling window.location.reload)', 'surprise'))
		->appendTo('#add');
}

// data contains form data
function form($data)
{
	$files = array();
	foreach (PheryResponse::files('file') as $file){
		$files[] = 'filename: ' . strip_tags($file['name']) . ' / size: ' . $file['size'] . 'B';
	}

	return
		PheryResponse::factory('div.test:eq(0)')
		->merge('scrollTop')
		->text(print_r($data, true))
		->unless(count($files) === 0)
		->append("\n\n-----Files Uploaded-----\n\n" . join("\n\n", $files))
		->dump_vars($data);
}

function thisone($data)
{
	return
		PheryResponse::factory()
		->dump_vars($data);
}

function the_one_with_expr($data)
{
	return
		PheryResponse::factory('.test2')
		->css(array('backgroundColor' => 'red', 'color' => '#fff'))
		->html('<pre>'. strip_tags($data['new-onthefly-var']) . '</pre>')
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
		unset($callback_specific_data_as_array['phery']);
		var_export(array(array('$data' => $data), array('$callback_specific_data_as_array' => $callback_specific_data_as_array)));
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
 * Post callback that might add some info to the phery response,
 * in this example, we will renew the CSRF after each request
 */
function post_callback($data, $callback_specific_data_as_array, $PheryResponse, $phery)
{
	if ($PheryResponse instanceof PheryResponse)
	{
		//$PheryResponse->renew_csrf($phery);
	}
}

function timeout($data, $parameters)
{
	$r = PheryResponse::factory();	session_write_close(); // Needed because it will hang future calls, when using CSRF

	if (isset($data['callback']) && !empty($parameters['retries']))
	{
		// The URL will have a retries when doing a retry
		return $r->dump_vars('Second time it worked, no error callback call ;)');
	}
	sleep(5); // Sleep for 5 seconds to timeout the AJAX request, and trigger our retry
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

	$mem = array(
		round(memory_get_peak_usage() / 1024, 2) . 'Kb',
		round((memory_get_usage() - $memory_start) / 1024, 2) . 'Kb',
		(string)(round(microtime(true) - $start_time, 6))
	);

	if ($phery_answer instanceof PheryResponse)
	{
		$phery_answer->apply('memusage', $mem);
	}
}

/**
 * New instances of phery and our test class
 */
$instance = new myClass;
$phery = new Phery;

/* Pseudo page menu */
$menu = '<a href="?page=home">home</a> <a href="?page=about">about us</a> <a href="?page=contact">contact us</a> <a href="?page=notfound">Doesnt exist</a> <a href="?page=redirect">Redirect to home (inline)</a> <a href="?page=excluded#container">Excluded (full refresh)</a>';

/* Quick hack to emulate a controller based website */
function pseudo_controller()
{
	if (isset($_GET['page']))
	{
		switch ($_GET['page'])
		{
			case 'home':
				$title = 'Home';
				$html = <<<HTML
<h1>Home</h1>
<p>Welcome to our website</p>
<p><img src="http://lipsum.lipsum.com/images/lorem.gif"></p>
HTML;

				return array($title, $html);
				break;
			case 'about':
				$title = 'About Us';
				$html = <<<HTML
<h1>About us</h1>
<p>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	Praesent ligula ante, auctor id commodo eu.
</p>
HTML;

				return array($title, $html);
				break;
			case 'contact':
				$title = 'Contact Us'; $form = Phery::form_for('', 'form');
				$html = <<<"HTML"
<h1>Contact us</h1>
<p>Use the form below to contact us</p>
{$form}
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

				return array($title, $html);
				break;
			case 'excluded':
				return array('Excluded', '<h1>This is always reached through a full page load</h1><p>Because of the exclude param when creating the view</p>');
				break;
			case 'redirect':
				return array(true, true);
				break;
			default:
				return array('Not Found', '<h1>404 Not Found</h1><p>The requested url was not found</p>');
				break;
		}
	}
	else
	{
		return array('Welcome', '<h1>Welcome!</h1>');
	}
}

$pseudo_controller = pseudo_controller();
$content = $menu . $pseudo_controller[1];

try
{
	$phery->config(
		array(
			/**
			 * Throw exceptions and return them in form of PheryException,
			 * usually for debug purposes. If set to false (default), it fails
			 * silently
			 */
			'exceptions' => true,
			/**
			 * Enable CSRF protection, needs to use Phery::instance()->csrf() on your
			 * HTML head, to print the meta
			 */
			'csrf' => true
		)
	)
	/**
	 * Set up the views, pass the global variable $menu to our
	 * container render callback
	 */
	->data(array('menu' => $menu))
	->views(array(
		'#container' => function ($data, $param)
		{
			$render = pseudo_controller();

			if ($render[0] === true)
			{
				return
					PheryResponse::factory()
					->json(array('doesnt work'))
					->j('#wont select')
					->text('because redirect clear all commands')
					->redirect('?page=home', $param['view']);
			}

			return
			PheryResponse::factory()
			->render_view($param['menu'] . $render[1], array('title' => $render[0]));
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
		'readcode' => function($config){
			$file = fopen(realpath(__FILE__), 'r');
			global $end_line;
			$r = new PheryResponse;

			if (
				!empty($config['from']) &&
				!empty($config['to']) &&
				(int)$config['from'] > 0 &&
				(int)$config['to'] > 0 &&
				(int)$config['to'] < $end_line
			)
			{
				$lines = 1;
				$code = array();
				while (fgets($file) !== false)
				{
					if (++$lines === (int)$config['from'])
					{
						break;
					}
				}
				while (($line = fgets($file)) !== false)
				{
					if ($lines++ > (int)$config['to'])
					{
						break;
					}
					else
					{
						$code[] = $line;
					}
				}
				array_unshift($code, 'Lines: '.((int)$config['from']).' to '.((int)$config['to'])."\n\n");
				$r->this->parent()->find('.code')->text(join("", $code));
			}
			fclose($file);
			return $r;
		},
		'this'=> function(){
			return
				PheryResponse::factory()
				->this
				->css(array(
					'backgroundColor' => '#000',
					'color' => '#fff'
				))
				->parent()
				->append('<p>Nice!</p>');
		},
		'dumpvars' => function ($data)
		{
			$r = new PheryResponse;
			$d = array();
			$d['dummy_info'] = 'dummy to show in print/dump';

			foreach ($data as $name => $value)
			{
				if ($name !== 'is_print')
				{
					$d[$name] = $value;
				}
			}
			if (!empty($data['is_print']))
			{
				$r->print_vars($d);
			}
			else
			{
				$r->dump_vars($d);
			}

			return $r->this->phery('append_args', array('wow' => 'true'));
		},
		// instance method call
		'test' => array($instance, 'test'),
		// regular function
		'test2' => 'test',
		// static function
		'test4' => array('myClass', 'test2'),
		// Lambda
		'test5' => function ()
		{
			return PheryResponse::factory()->redirect('http://www.google.com');
		},
		// Use PHP-side animate() callback!
		'data' => array($instance, 'data'),
		// Trigger even on another element
		'trigger' => 'trigger',
		// Trigger even on another element
		'form' => 'form',
		// Call this function even if it's not been submitted by AJAX, but IS a post
		'thisone' => 'thisone',
		// Lambda, reload the page
		'surprise' => function ($data)
		{
			return
			PheryResponse::factory()->script('window.location.reload(true)');
		},
		// Invalid Javascript to trigger "EXCEPTION" callback
		'invalid' => function ()
		{
			return
			PheryResponse::factory()->script('not valid javascript')->jquery('a')->blah();
		},
		// Timeout
		'timeout' => 'timeout',
		// Select chaining results
		'chain' => function ($data, $complement)
		{
			$r = PheryResponse::factory($complement['submit_id'])->next('div');
			// If the select has a name, the value of the select element will be passed as
			// a key => value of it's name. Or it could be used as
			// PheryResponse::factory()->this->next('div');
			$html = array();
			error_reporting(0);
			switch (Phery::coalesce($data['named'], $data[0]))
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
		'msgbox' => function ($data)
		{
			return
			PheryResponse::factory()
			->alert($data);
		},
		'json' => function($data)
		{
			$data = array();
			for ($i = 0; $i < 8; $i++)
			{
				$data[] = array(
					'id' => $i + 1000,
					'data' => array(
						'name' => 'name '.$i,
						'value' => $i * 10,
					)
				);
			}
			return PheryResponse::factory()->json($data);
		},
		// select multiple
		'selectuple' => function ($data)
		{
			return
			PheryResponse::factory()
			->dump_vars($data);
		},
		'before' => function ($data)
		{
			return
			PheryResponse::factory()
			->call($data['callback'], $data['alert']);
		},
		'reuse' => function($data, $b)
		{
			$r = PheryResponse::factory('#widgets div')->removeClass('focus-widget')
			->j($b['submit_id'])->addClass('focus-widget')
			->find('div')->show();

			if (count($data))
			{
				foreach ($data as $d)
				{
					$r->append(strip_tags($d).' ');
				}
			}

			return $r;
		},
		'img' => function ($data)
		{
			return
			PheryResponse::factory()
			->script('if(confirm("Do you wanna go to Wikipedia?")) window.location.assign("http://wikipedia.org");');
		},
		'REST' => function ($data, $params)
		{
			$r = new PheryResponse('#RESTAnswer');
			if (session_id() == '')
			{
				@session_start();
			}
			/* EMULATE A DATABASE USING SESSION */
			switch ($params['method'])
			{
				case 'GET':
					if (!empty($_SESSION['data'][$_GET['id']]))
					{
						$r->text("Exists: \n" . print_r($_SESSION['data'][$_GET['id']], true));
					}
					else
					{
						$r->text('ID doesnt exists');
					}
					break;
				case 'PUT':
					if (!empty($_SESSION['data'][$_GET['id']]))
					{
						$_SESSION['data'][$_GET['id']] = array(
							'alert' => $data['alert'],
							'incoming' => $data['incoming'],
							'named' => $data['named'],
							'holy' => $data[0],
							'one' => $data[1],
						);
						$r->text('Updated');
					}
					else
					{
						$r->text('ID doesnt exists, create it first');
					}
					break;
				case 'POST':
					if (empty($_SESSION['data'][$_GET['id']]))
					{
						$_SESSION['data'][$_GET['id']] = array(
							'alert' => $data['alert'],
							'incoming' => $data['incoming'],
						);
						$r->text('Created');
					}
					else
					{
						$r->text('Already exists');
					}
					break;
				case 'DELETE':
					if (!empty($_SESSION['data'][$_GET['id']]))
					{
						unset($_SESSION['data'][$_GET['id']]);
						$r->text('Item deleted');
					}
					else
					{
						$r->text('ID doesnt exists');
					}
					break;
			}

			return $r;
		},
		'nested'=> function(){
			/*
				This is the same as doing:
				$(this).before(
					$('<p/>', {
						'text' => 'Text content at UNIX ' + new Date().getTime()
					})
				);
			*/
			return
				PheryResponse::factory()->this->before(
					PheryResponse::factory('<p/>', array(
						'text' => 'Text content at UNIX ' . time()
					))
				);
		},
	))
	/**
	 * process(false) mean we will call phery again with
	 * process(true)/process() to end the processing, so it doesn't
	 * block the execution of the other process() call
	 */
	->process(false);	$csrf_token = $phery->csrf();

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
		'test3' => function ($args, $param)
		{
			// Lambda/anonymous function, without named parameters, using ordinal indexes
			return
			PheryResponse::factory()
			->alert($args[0])
			->alert($args[1])
			->alert($param['param1'])
			->alert((string)$param[0])
			->alert((string)$param[1]);
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
	->data(array('param1' => 'named as param1'), 1, 'argument')
	/**
	 * Finally, process for "test3" or "the_one_with_expr"
	 */
	->process(false);

	$phery
	->config(array(
		// Catch ALL the errors and use the internal error handler
		'error_reporting' => E_ALL
	))
	->callback(array('before' => array(), 'after' => array()))
	->set(array(
		'on_purpose_exception' => function ()
		{
			strlen($code);
		},
		'deep-nesting' => function(){
			$r = new PheryResponse('<h3/>');
			$d = new PheryResponse('<p/>');
			$v = new PheryResponse('<div id="blah_'.(mt_rand()).'"/>');
			return
			$r->append(
				$d
				->append(
					$v
					->text('this element can be clicked')
					->bind('click', PheryFunction::factory('function(){ alert(this.id); }'))
				)
			)->insertAfter(PheryResponse::factory()->this);
		},
		'colorbox' => function($data){
			if (!empty($data['close']))
			{
				return
					PheryResponse::factory()
					//->jquery->colorbox->close(); // or
					// ->call(array('$', 'colorbox', 'close')); // or
					->access(array('$','colorbox'))->close();
			}
			if (empty($data['other-way-around']))
			{
				return
					PheryResponse::factory()
					->jquery
					->colorbox(array(
						'html' =>
						Phery::link_to('Look, im inside PHP, loaded with everything already ;)<br>Clicking this will call $.colorbox.close();', 'colorbox', array('args' => array('close' => true))),
					));
			}
			return
				PheryResponse::factory()
				->jquery
				->colorbox(array(
					'inline' => true,
					'href' => PheryResponse::factory()->this->parent(),
				));
		},
		'fileupload' => function($data) {
			$r = new PheryResponse;
			$files = $r->files('files');
			foreach ($files as $index => $file) {
				unset($files[$index]['tmp_name']);
			}
			return $r->dump_vars($files);
		},
		'autocomplete' => function($data) {
			$r = new PheryResponse;
			$states = array(
				'Alabama',
				'Alaska',
				'Arizona',
				'Arkansas',
				'California',
				'Colorado',
				'Connecticut',
				'Delaware',
				'Florida',
				'Georgia',
				'Hawaii',
				'Idaho',
				'Illinois',
				'Indiana',
				'Iowa',
				'Kansas',
				'Kentucky',
				'Louisiana',
				'Maine',
				'Maryland',
				'Massachusetts',
				'Michigan',
				'Minnesota',
				'Mississippi',
				'Missouri',
				'Montana',
				'Nebraska',
				'Nevada',
				'New Hampshire',
				'New Jersey',
				'New Mexico',
				'New York',
				'North Carolina',
				'North Dakota',
				'Ohio',
				'Oklahoma',
				'Oregon',
				'Pennsylvania',
				'Rhode Island',
				'South Carolina',
				'South Dakota',
				'Tennessee',
				'Texas',
				'Utah',
				'Vermont',
				'Virginia',
				'Washington',
				'West Virginia',
				'Wisconsin',
				'Wyoming',
			);
			$lis = array();

			$search = trim($data['state']);

			foreach($states as $state)
			{
				if (stripos($state, $search) !== false)
				{
					$lis[] = '<li>' . $state . '</li>';
				}
			}

			$r->this->find('ul')->html(join('', $lis));
			return $r;
		},
		'unless' => function(){
			$r = new PheryResponse;

			return $r->jquery('<div>HELLO!</div>')->css('backgroundColor', 'red')->unless(PheryFunction::factory('return false;'))->appendTo('body')->alert('done!');
		},
		'incase' => function(){
			$r = new PheryResponse;

			return $r->incase(PheryResponse::factory()->this->phery('data', 'temp'))->alert('hi')->alert('2');
		},
		'getjson' => function(){
			$r = new PheryResponse;

			return $r->jquery->getJSON('https://api.twitter.com/1/statuses/user_timeline.json?include_entities=true&include_rts=true&screen_name=twitterapi&count=2');
		},
		'setvar' => function(){
			return PheryResponse::factory()->set_var('doh', array(1, PheryResponse::factory()->this));
		},
		'unsetvar' => function(){
			return PheryResponse::factory()->unset_var('doh');
		},
		'getvar' => function(){
			return PheryResponse::factory()->dump_vars('colorbox entry point', PheryResponse::factory()->access(array('$','colorbox')));
		},
		'pubsub' => function(){
			$r = new PheryResponse;
			switch (rand(1,4)):
				case 1:
					$r->publish('test', array(PheryResponse::factory()->this))->dump_vars(1);
					break;
				case 2:
					$r->publish('test2')->dump_vars(2);
					break;
				case 3:
					$r->publish('test2', array('hooray'))->dump_vars(3);
					break;
				case 4:
					$r->phery_broadcast('test', array('hooray'))->dump_vars(4);
					break;
			endswitch;
			return $r;
		}
	))
	->process();
}
catch (PheryException $exc)
{
	/**
	 * will trigger for "nonexistant" call
	 * This will only be reached if 'exceptions' is set to TRUE
	 * Otherwise it will fail silently, and return an empty
	 * JSON response object {}
	 */
	Phery::respond(
		PheryResponse::factory()
		->merge('my name is') // merge a named response
		->renew_csrf($phery)
		->exception($exc->getMessage())
	);
	exit;
}

$exception = array('from' => (__LINE__ - 17), 'to' => (__LINE__ - 2));
?>
<!doctype html>
<html>
<head>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.js"></script>
<meta charset="utf-8">
<title>phery.js AJAX jQuery</title>
<?php echo $csrf_token; ?>
<script src="colorbox/colorbox/jquery.colorbox.js" id="colorbox-script" type="text/javascript"></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css">
<script src="phery.js" type="text/javascript"></script>
<script type="text/javascript">
function test(number_array) {
	var total = 0;
	for (var x = 0; x < number_array.length; x++) {
		total += number_array[x];
	}
	alert(total);
}

var
$peak,
$usage;

$(function () {
	// cache our DOM elements that will receive the memory info
	$peak = $('#peak');
	$usage = $('#usage');
	$('#version').text('jQuery Version: ' + $().jquery + ' / phery: ' + phery.version);

	$('div.test').on({
		'test':function () { // bind a custom event to the DIVs
			$(this).show(0).text('triggered custom event "TEST"!');
		}
	});

	$.scrollTo = function(el, speed){
		el = $(el);

		if (el.length) {
			var top = el.offset().top;
			if (speed) {
				$(window).animate({'scrollTop': top}, speed);
			} else {
				$(window).scrollTop(top);
			}
		}
	};

	/*****************************
	 *  RECEIVE ANY TYPE OF DATA *
	 *****************************/
	/*
	 *
	 * Manually process the result of ajax call, can be anything
	 *
	 */
	$('#special').on({
		'phery:done':function (data, text, xhr) {
			// The object will receive the text, return data from 'test' function, it's a JSON string
			//alert(text);
			// Now lets convert back to an object
			var obj = $.parseJSON(text);
			console.log(['text: ', text, 'json: ', obj]);
			// Do stuff with new obj
			// Returning false will prevent the parser to continue executing the commands and parsing
			// for jquery calls, because this text/html answer won't have any
			return false;
		}
	})
	// The data-phery-type must override the type to 'html', since the default is 'json'
	.phery('data', 'type', 'html');

	/*
	 *
	 * Bind the phery:always, after data was received, and there was no error
	 *
	 */
	$('#special2').on({
		'phery:always':function (xhr) {
			var $this = $(this);
			$this.show(0);
			if ($this.data('testing')) {
				$('div.test2').text(('$.data for item "nice" is "' + $this.data('testing')['nice']) + '"');
			}
		}
	});

	/****************************
	 *  FORMAT CODE FROM TABLES *
	 ****************************/
	var to_pre = function (e) {
				var $div = $('div.test:eq(0)');
				var text = $div.html();
				// This doesnt work for IE7 or IE8, no idea why, the CRLF wont be replaced by <br>
				$div.html('<pre>' + text + '</pre>');
			};

	/*
	 *
	 * Let's just bind to the form, so we can apply some formatting to the text coming from print_r() PHP
	 *
	 */
	$(document).on('phery:always', 'form', to_pre);

	/**************************
	 *  TEST FORM TOGGLES     *
	 **************************/
	var $form = $('#testform');

	var f = function (el, name) {
		var $this = $(el);
		var $submit = $form.phery('data', 'submit');
		$submit[name] = $this.prop('checked');
		$form.phery('data', 'submit', $submit);
	};

	$('#disable').click(function () {
		f(this, 'disabled');
	});

	$('#all').click(function () {
		f(this, 'all');
	});

	var
	$loading = $('#loading');

	/**************************
	 *  EXCEPTION HIGHLIGHT   *
	 **************************/
	$(document)
	.on('mouseenter', '#exceptions a', function(){
		var $this = $(this);
		if ($this.data('target'))
		{
			$this.data('target').addClass('focus');
		}
	})
	.on('click', '#exceptions a', function(){
		var $this = $(this);
		$.scrollTo($this.data('target'));
	})
	.on('mouseout', '#exceptions a', function(){
		var $this = $(this);
		if ($this.data('target'))
		{
			$this.data('target').removeClass('focus');
		}
	});

	/*****************************
	 *  SEE PHP CODE/TOGGLE CODE *
	 *****************************/
	$(document)
	.on('phery:beforeSend', '.togglecode', function(e){
		var
			$this = $(this),
			code = $this.parent().find('.code'),
			empty = $.trim(code.text()) === '';

		if (!empty)
		{
			code.toggle();
			return false;
		}

		$this.text('Toggle PHP code');
		code.show();
		return true;
	});

	/**************************
	 *  GLOBAL AJAX EVENTS    *
	 **************************/
	/*
	 *
	 * Global phery.js events
	 *
	 * You can set global events to be triggered, in this case, fadeIn and out the loading div
	 * On global events, the current delegated dom node will be available in event.target
	 */
	phery.on({
		'before':function (event) {
			$loading.removeClass('error').stop(true).fadeIn('fast');

			// catch all event to apply some classes and arguments
			if (event.$target.is('#modify')) {
				event.$target.phery('set_args', {
					'alert':event.$target.next('input').val(),
					'callback':'callme'
				});
			} else {
				// disable for our AJAX container and autocomplete
				if (!event.$target.is('div#container,div.autocomplete')) {
					$(event.$target).addClass('loading');
				}
			}
		},
		'always':function (event, xhr) {
			$loading.stop(true).fadeOut('fast');
			$(event.$target).removeClass('loading');
		},
		'fail':function (event, xhr, status) {
			if (status !== 'canceled') {
				$loading.addClass('error');
			}
			if (status === 'timeout') {
				event.$target.phery('exception', 'Timeout and gave up retrying!!');
				// or event.$target.phery().exception('Timeout and gave up retrying!!');
			}
		},
		'exception':function (event, exception, data) {
			var $exceptions = $('#exceptions');

			var a = $('<a/>', {
				'text': event.$target[0].tagName + (event.$target.attr('id') ? ' (' + event.$target.attr('id') + ')' : ''),
				'title': 'Click to scroll into view'
			}).data('target', event.$target);

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

				$exceptions.append(
					$('<li/>', {
						'text':type + ': ' + exception + ' on file ' + data.file + ' line ' + data.line + ' on '
					}).append(a)
				);

			} else {
				$exceptions.append(
					$('<li/>', {
						'text':exception + ' on '
					}).append(a)
				);
			}

			var $window = $(window);

			while ($exceptions.height() > $window.height() + 10) {
				$exceptions.find('li:not(.clear):first').remove();
			}
		}
	});

	window.callme = function (e) {
		alert(e);
	};

	/**************************
	 *  CONFIG PHERY.JS       *
	 **************************/
	phery.config({
		/*
		 * Retry one more time, if fails, then trigger events.error
		 */
		'ajax.retries':1,
		/*
		 * Enable phery:* events on elements
		 */
		'enable.per_element.events':true,
		/*
		 * Enable logging and output to console.log.
		 * Shouldn't be enabled on production because
		 * of building up an internal log of messages
		 */
		'enable.log':true,
		/*
		 * Log messages that can be accessed on phery.log()
		 */
		'enable.log_history':true,
		/*
		 * Enable inline loading of responses
		 */
		'inline.enabled': true
	});

	// 2 seconds timeout for AJAX
	// any AJAX option can be set through jQuery
	$.ajaxSetup({timeout:5000});

	$loading.fadeOut(0);

	/**************************
	 *  FORM VALIDATION       *
	 **************************/
	$('#validate').click(function () {
		if ($(this).prop('checked')) {
			load_validate();
		}
	});

	/**************************
	 *  COLORBOX              *
	 **************************/
	$('#colorbox-btn').click(function(){
		phery.remote('colorbox');
	});

	/**************************
	 *  JSON LOADING DATA     *
	 **************************/
	$('#loaddata').on({
		'phery:json': function(event, data){
			var tbody = $('#datatable tbody'), tr;
			for (var x = 0; x < data.length; x++)
			{
				tr =
					$('<tr/>', {
						'id': data[x].id
					})
					.html('<td>'+data[x].id+'</td><td>'+data[x].data.name+'</td><td>'+data[x].data.value+'</td>');
				tbody.append(tr);
			}
		}
	});

	/**************************
	 *  AJAX PARTIAL VIEWS    *
	 **************************/
	$('#ajax').on('click',function () {
		var $this = $(this);
		if ($this.prop('checked')) {
			/* Setup automatic view rendering using ajax */
			phery.view({
				'#container':{
					'render': function(html, data, passdata){
						var $this = this;
						$this.fadeOut('slow').promise().done(function(){
							$this.html('').html(html);
							$this.fadeIn('fast');
						});
					},
					'exclude': ['?page=excluded'],
					/* We want only the links inside the container to be ajaxified */
					'selector':'a',
					/* Enable the browser history, and change the title */
					'afterHtml':function (data, passdata) {
						document.title = data.title;
						if (window.history && typeof window.history['pushState'] === 'function') {
							/* Good browsers get history API */
							if (typeof passdata['popstate'] === 'undefined') {
								window.history.pushState(data, data.title, data.url);
							}
						}
					},
					/* phery:params let us add params, that ends in callback params and wont get mixed with arguments */
					'params':function (event, data) {
						// Show the calling URL to PHP, since it knows nothing
						data['origin'] = window.location.href;
					}
				}
			});
			/* Good browsers get back/forward button ajax navigation ;) */
			window.onpopstate = function (e) {
				phery.view('#container').navigate_to(document.location.href, null, {'popstate':true});
			};
		} else {
			phery.view({
				// Disable view, page will work normally
				'#container':false
			});
		}
	}).triggerHandler('click');

	/**************************
	 *  ENABLE/DISABLE DEBUG *
	 *************************/
	$('#debug').click(function () {
		phery.config({
			/*
			 * Enable debug verbose
			 */
			'debug.enable':!phery.config('debug.enable')
		});
		var enabled = phery.config('debug.enable');

		$(this)
		.css('color', enabled ? '#0f0' : '#f00')
		.find('span')
		.text(enabled ? 'ON' : 'OFF');
	});

	/**************************
	 *  PROXY CALLS          *
	 *************************/
	$('#proxy').click(function(){
		phery.remote('proxy', null, {'proxy': $form});
	});

	/**************************
	 *  FILE UPLOAD ON PICK   *
	 *************************/
	$('#files').on({
		'change':function(){
			phery.remote('fileupload', null,  {'el': $(this)});
		},
		'phery:progress': function(e, progress){
			if (progress.lengthComputable){
				$('progress').val((progress.loaded / progress.total) * 100);
			}
		}
	});

	/**************************
	 *  AUTO COMPLETE        *
	 *************************/
	var typing_interval;

	$('.autocomplete').on('keyup', function(e){
		var $this = $(this);
		if (!$this.data('input')) {
			//cache it for performance
			$this.data('input', $this.find('input'));
		}

		if ($this.data('input').val().length > 2) {
			clearTimeout(typing_interval);
			typing_interval = setTimeout(function(){
				$this.phery('remote'); // our data is being fetched using 'related' attribute ;)
			}, 300);
		}
	}).on('blur', 'input', function(){
		clearTimeout(typing_interval);
	});

	var test = phery.remote('pubsub', null, null, false);
	test.phery('subscribe', {
		'test': function(){
			console.log('pub/sub "test" topic', arguments);
		},
		'test2': function(){
			console.log('pub/sub "test2" topic', arguments);
		}
	});
	test.phery('remote');
	/**************************
	 *  INLINE LOAD          *
	 *************************/
	<?php
		$str = "\\\`ç\nasdf'";
		PheryResponse::factory()->dump_vars(array('inline_load' => array('dump_vars', $str)))->inline_load(true);
	?>

});

function load_validate() {
	var name = 'validate-javascript';
	if ($('#' + name).size()) {
		return;
	}

	var script = $('<script/>', {
		'src':'https://raw.github.com/jzaefferer/jquery-validation/master/jquery.validate.js',
		'type':'text/javascript',
		'id':name
	});

	$(script).on('load', Validatize);

	$('head')[0].appendChild(script[0]);
}

function memusage(peak, usage, time) {
	$peak.text('Peak PHP memory usage: ' + peak);
	$usage.text('Delta PHP memory usage: ' + usage + ' / ' + (time) + ' secs');
}
</script>

<script type="text/javascript">
	function Validatize() {
		$('#testform').validate({
			'submitHandler':function (form) {
				$(form).phery('remote');
				// or $(form).phery().remote();
			}
		});
	}
</script>

<style type="text/css">
	a {
		text-decoration: underline;
		cursor:          pointer;
		padding:         5px;
		background:      #eee;
	}
	.readcode {
		margin-top: 15px;
		margin-bottom: 5px;
	}
	pre.code {
		font-size: 12px;
		background: #000;
		font-family: 'Lucida Console','Consolas',monospace;
		color: #d4d4d4;
		border: solid 3px #0088d8;
		overflow: auto;
		padding: 10px;
		display: none;
	}
	label {
		display:       block;
		margin-bottom: 10px;
	}

	input, select, textarea {
		margin-bottom: 10px;
	}

	input[type="text"], select, textarea {
		min-width: 300px;
	}

	h1, h2 {
		margin-top:    20px;
		margin-bottom: 20px;
		font-size:     24px;
	}

	h2 {
		font-size: 15px;
	}

	body {
		padding: 0 30px;
	}

	#loading {
		position:              fixed;
		right:                 50%;
		top:                   50%;
		padding:               14px;
		display:               block;
		z-index:               2;
		font-size:             20px;
		font-weight:           bold;
		background:            #ddd;
		-moz-box-shadow:       0 0 4px #000;
		-webkit-box-shadow:    0 0 4px #000;
		box-shadow:            0 0 4px #000;
		-moz-border-radius:    3px;
		-webkit-border-radius: 3px;
		border-radius:         3px;
	}

	.loading {
		position: relative;
		opacity: 0.5;
	}
	.loading:after {
		background: rgba(200, 200, 200, 0.9) url('//i.stack.imgur.com/KOCJh.gif') no-repeat 50% 50%;
		display: block;
		height:100%;
		width:100%;
		box-sizing: border-box;
		position: absolute;
		top: 0;
		left: 0;
		z-index: 100;
		content: ' ';
	}

	.error {
		background: #f00 !important;
	}

	#datatable td{
		border: solid 1px #000;
		padding: 5px;
	}
	#exceptions {
		position:      fixed;
		bottom:        0;
		right:         0;
		z-index:       10;
		background:    #000;
		max-width:     300px;
		color:         #fff;
		padding-right: 20px;
	}
	#exceptions a {
		background: transparent;
		color: #f00;
		padding: 0;
	}

	#debug {
		color:  #f00;
		cursor: pointer;
	}

	#debug:after {
		content: ' (Click to change)';
		color: #0088d8;
	}

	#debug span{
		text-decoration: underline;
	}

	#exceptions li {
		padding: 4px;
	}

	.focus {
		-moz-box-shadow:    3px 3px 3px 1px #f00;
		-webkit-box-shadow: 3px 3px 3px 1px #f00;
		box-shadow:         3px 3px 3px 1px #f00;
	}

	.fast {
		-webkit-transition: opacity 2s ease-in-out;
		-moz-transition:    opacity 2s ease-in-out;
		-o-transition:      opacity 2s ease-in-out;
		transition:         opacity 2s ease-in-out;
		opacity: 0.8;
	}

	.focus-widget {
		-webkit-box-shadow: inset 0px 0px 7px 8px #000000;
		-ms-box-shadow: inset 0px 0px 7px 8px #000000;
		-moz-box-shadow: inset 0px 0px 7px 8px #000000;
		box-shadow:         inset 0px 0px 7px 8px #000000;
		margin:  10px 0;
		padding: 20px;
	}

	.external {
		padding-right: 15px;
		background:    url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAVklEQVR4Xn3PgQkAMQhDUXfqTu7kTtkpd5RA8AInfArtQ2iRXFWT2QedAfttj2FsPIOE1eCOlEuoWWjgzYaB/IkeGOrxXhqB+uA9Bfcm0lAZuh+YIeAD+cAqSz4kCMUAAAAASUVORK5CYII=") no-repeat scroll right center transparent;
	}

	#memory {
		display:    block;
		position:   fixed;
		right:      10px;
		top:        10px;
		background: #fff;
		box-shadow: 2px 2px 5px #000;
		padding:    10px;
	}
</style>
</head>
<body>
<div id="loading">Loading...</div>
<div id="memory">
	<p><a href="https://github.com/pocesar/phery/archive/master.zip" target="_blank">DOWNLOAD LIBRARY NOW</a></p>
	<p><a href="docs/">See the DOCS</a></p>

	<p id="usage"></p>

	<p id="peak"><?php echo 'PHP clean load memory peak usage: ', round(memory_get_peak_usage() / 1024), 'Kb'; ?></p>

	<p id="version"></p>
</div>

<p style="font-weight: bold;font-size: 18px;">Check the Firebug/Console for the messages and object dumps this page outputs</p>

<h2 id="debug">DEBUG is set to <span>OFF</span></h2>

<hr>
<div class="test" style="border:solid 1px #000; padding: 20px;">Div.test</div>
<div class="test test2" style="border:solid 1px #000; padding: 20px;">Div.test 2</div>

<ul id="add">
	<li>
		<h2>Using HTML 'b' tag, chain commands for css() and animate(), id #special2</h2>
		<?php echo Phery::link_to('Test data and check it on callback phery:always', 'data', array('tag' => 'b', 'id' => 'special2', 'style' => 'cursor:pointer;')); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 80, 'to' => 101))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Magic call to jquery toggle() on 'div.test' using filter(':eq(1)')</h2>
		<?php echo Phery::link_to('Instance method call', 'test', array('confirm' => 'Are you sure?', 'args' => array('hi' => 'test'))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 41, 'to' => 64))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Returns plain text in this case, processed manually with the phery:done javascript callback. id => #special, using clickable DIV tag</h2>
		<?php echo Phery::link_to('Regular function', 'test2', array('id' => 'special', 'tag' => 'div', 'clickable' => true, 'args' => array('hello' => 'Im a named argument :D')), null, true); ?>
			<p>This is a P inside the DIV, but it's parent is clickable</p>
			<figure>
				<img src="http://lorempixel.com/400/200/" alt="lorempixel">
				<figcaption>This figure is inside the clickable div as well</figcaption>
			</figure>
		<?php echo "</div>"; ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 108, 'to' => 111))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Call a lambda function that returns an alert according to the parameters passed, which is 'first', then 'second', set to uppercase by the callback function</h2>
		<?php echo Phery::link_to('Call to lambda', 'test3', array('args' => array('first', 'second'))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 683, 'to' => 693))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Call to an existing javascript function an array of integers</h2>
		<?php echo Phery::link_to('Static call from class', 'test4', array('args' => array(1, 2, 4, 6, 19))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 66, 'to' => 78))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Leaves the page, HTML tag is set to 'button'</h2>
		<?php echo Phery::link_to('Redirect to google.com', 'test5', array('tag' => 'button', 'confirm' => 'Are you sure you want to go to Google?')); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 459, 'to' => 462))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Call a non-existant function with 'exceptions' set to true</h2>
		<?php echo Phery::link_to('Call a non-existant function', 'nonexistant'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => $exception)); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Call to a function that returns an animate() with a callback and executes before and after callbacks</h2>
		<?php echo Phery::link_to('Testing callbacks', 'the_one_with_expr', array('id' => 'look-i-have-an-id', 'args' => array(1, 2, 3, 'a', 'b', 'c'))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 148, 'to' => 156))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Nested PheryResponse call</h2>
		<?php echo Phery::link_to('Nested!', 'nested'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 645, 'to' => 660))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Deep nesting PheryResponse and PheryFunction calls</h2>
		<?php echo Phery::link_to('Deep Nested!', 'deep-nesting'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 721, 'to' => 734))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Old school phery.remote('test', {'hi': 'manual'}) onclick, with arguments</h2>
		<a onclick="phery.remote('test', {'hi': 'manual'});">Inline onclick event</a>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 41, 'to' => 64))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Trigger event 'test' on both divs (and a green on-the-fly div appended at the bottom)</h2>
		<?php echo Phery::link_to('Trigger event', 'trigger'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 113, 'to' => 122))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>On-purpose 404 ajax call</h2>
		<?php echo Phery::link_to('Trigger global fail and change background to red', 'nonexistant', array('href' => '/pointnowhere')); ?>
	</li>
	<li>
		<h2>Global exception handler</h2>
		<?php echo Phery::link_to('Trigger global exception callback returning invalid javascript', 'invalid'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 478, 'to' => 482))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Retry on timeout</h2>
		<?php echo Phery::link_to('Timeout retry then give up', 'timeout'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 201, 'to' => 212))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Retry on timeout, then accept second call</h2>
		<?php echo Phery::link_to('Timeout retry then work', 'timeout', array('args' => array('callback' => true))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 201, 'to' => 212))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Custom error reporting for a bit of code</h2>
		<?php echo Phery::link_to('Exception', 'on_purpose_exception'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 717, 'to' => 720))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Dump vars vs Print vars (watch the Firebug console, some ajax arguments will be appended on-the-fly after first call)</h2>
		<?php echo Phery::link_to('Dump vars', 'dumpvars'); ?>
		<?php echo Phery::link_to('Print vars', 'dumpvars', array('args' => array('is_print' => true))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 428, 'to' => 451))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Using PheryResponse::factory()->this to access the calling element</h2>
		<?php echo Phery::link_to('This', 'this'); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 417, 'to' => 427))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Put data in the element on-the-fly, using phery:before event and will call a callback with on-the-fly arguments</h2>
		<?php echo Phery::link_to('I have no arguments, click me', 'before', array('id' => 'modify')); ?>
		<input type="text" id="alert" value="Yes its an alert" /> &laquo; change this to set the data to be sent
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 551, 'to' => 556))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Image tag click (using the <i>Phery::link_to</i> builder)</h2>
		<?php echo Phery::link_to(null, 'img', array('src' => '//upload.wikimedia.org/wikipedia/commons/6/63/Wikipedia-logo.png', 'alt' => 'Wikipedia Logo', 'tag' => 'img', 'style' => 'cursor:pointer')) ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 573, 'to' => 578))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Show a colorbox with dynamic content (the logic is reversed, it isnt the colorbox that calls the AJAX, its the AJAX that shows the colorbox)</h2>
		<p>
			Needs to place "colorbox/colorbox/jquery.colorbox-min.js" and "colorbox/example1/colorbox.css" for this to work
		</p>
		<a id="colorbox-btn">Colorbox!</a> <?php echo Phery::link_to('Colorbox! (add this parent &lt;li&gt; inside the colorbox using this and the links still works as it should)', 'colorbox', array('args' => array('other-way-around' => true))); ?>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 735, 'to' => 761))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Upload multiple files with progress (only on good browsers plus the <i>el</i> attr in phery.remote, can upload multiple files at once, ctrl+click select the files)</h2>
		<input type="file" id="files" name="files" multiple="multiple">
		<progress max="100" value="0"></progress>
		<div class="readcode">
			<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 762, 'to' => 769))); ?>
			<pre class="code"></pre>
		</div>
	</li>
	<li>
		<h2>Proxy only the events to another element</h2>
		<a id="proxy">Proxy it to the form and trigger an exception on the form behalf</a>
	</li>
</ul>

<h1>Quick auto complete example</h1>
<p>
	A quick demonstration of how easy to use autocomplete with phery.js, type more than 3 letters any part of an US states name.
	Got a 300ms delay once stop typing, it's not a real delay from the library. The whole autocomplete code is only 15 lines of javascript code.
</p>
<?php echo Phery::link_to('', 'autocomplete', array('class' => 'autocomplete', 'related' => 'input', 'tag' => 'div'), null, true); ?>
	<input type="text" autocomplete="off" name="state">
	<ul></ul>
<?php echo '</div>'; ?>

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 770, 'to' => 838))); ?>
	<pre class="code"></pre>
</div>


<h1>Working with JSON (like with Backbone.js)</h1>

<p>
	Sometimes you'll want to return some data along with your DOM manipulation, for use with either a global variable or
	a library like <a target="_blank" class="external" href="http://backbonejs.org/">Backbone.js</a>, that works with collections and models, and usually have a view attached, or <a href="http://datatables.net/" target="_blank" class="external">Datatables</a>
</p>

<?php echo Phery::link_to('Load Data', 'json', array('id' => 'loaddata')); ?>
<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 529, 'to' => 543))); ?>
	<pre class="code"></pre>
</div>
<table id="datatable">
	<thead>
		<tr>
			<th>ID</th>
			<th>Name</th>
			<th>Value</th>
		</tr>
	</thead>
	<tbody></tbody>
</table>

<h1>Reusable remote call</h1>

<p>
	Following the D.R.Y. paradigm, you can use the same phery.remote call in similar widget compositions.
	If the current element doesn't have an ID, the imediate ID of the parent will be used. It's using the
	'related' special attribute to aggregate data from many sources. When using 'related', it's always relative
	to the current element, so using 'input' is like using '$(this).find('input')'
</p>

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 557, 'to' => 572))); ?>
	<pre class="code"></pre>
</div>

<table id="widgets">
	<tr>
		<td>
			<div id="id1">
				<?php echo Phery::link_to('Toggle', 'reuse') ?>
				<div style="display: none">
					The current widget value
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div id="id2">
				<?php echo Phery::link_to("Open (notice the 'local' data-phery-related)", 'reuse', array('related' => '~ input[type="text"]')) ?>
				<div style="display: none">
					The current value
				</div>
				<input type="text" value="ok">
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div id="id3">
				<?php echo Phery::link_to("Open (notice the 'local' data-phery-related)", 'reuse', array('related' => '~ input,#id2 input')) ?>
				<div style="display: none">
					The current value
				</div>
				<input type="hidden" value="hidden!">
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div id="id4">
				<?php echo Phery::link_to("Open (notice the 'local' data-phery-related)", 'reuse', array('related' => '~ .send')); ?>
				<div style="display: none">
					The current value
				</div>
				<input type="hidden" class="send" value="with class ">
			</div>
		</td>
	</tr>
</table>

<h1>Selects</h1>

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 486, 'to' => 521))); ?>
	<pre class="code"></pre>
</div>

<label>No name on select element</label>
<?php echo Phery::select_for('chain', array('1' => 'one', '2' => 'two', '3' => 'three'), array('id' => 'incoming')); ?>
<div></div>

<label>Named select (will influence the data being passed to the function)</label>
<?php echo Phery::select_for('chain', array('1' => 'one', '2' => 'two', '3' => 'three'), array('name' => 'named', 'id' => 'incoming2')); ?>
<div></div>

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 545, 'to' => 550))); ?>
	<pre class="code"></pre>
</div>

<label>Multiple (will trigger "onblur" instead of "onchange")</label>
<?php echo Phery::select_for('selectuple', array('1' => 'one', '2' => 'two', '3' => 'three'), array('multiple' => true, 'name' => 'huh', 'selected' => array(1, 2, 3))); ?>

<h1>RESTful</h1>

<p>
	REST is emulated to make it easier to reuse the same phery.js function. This is not intended to be used as CORS.
	Everything uses the same url id (as ?id=1) but with different outcomes. The <b>data-phery-related</b> attribute, when
	written in plain text, can have any jQuery selector, or a mix of jQuery selectors. The resulting value will have all
	values merged
</p>

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 579, 'to' => 644))); ?>
	<pre class="code"></pre>
</div>

<table>
	<tr style="vertical-align: top;">
		<td>
			<ul>
				<li>
					<p>GET (default, read)</p>
					<?php echo Phery::link_to('Click me', 'REST', array('method' => 'GET', 'href' => '?id=1')); ?>
				</li>
				<li>
					<p>POST (create)</p>
					<?php echo Phery::link_to('Click me', 'REST', array('method' => 'POST', 'href' => '?id=1', 'related' => '#alert,#incoming')); ?>
				</li>
				<li>
					<p>PUT (update)</p>
					<?php echo Phery::link_to('Click me', 'REST', array('method' => 'PUT', 'href' => '?id=1', 'related' => '#alert,#incoming,#incoming2', 'args' => array('holy', 1))); ?>
				</li>
				<li>
					<p>DELETE (delete)</p>
					<?php echo Phery::link_to('Click me', 'REST', array('method' => 'DELETE', 'href' => '?id=1')); ?>
				</li>
			</ul>
		</td>
		<td>
			<pre id="RESTAnswer" style="padding-left:30px;"></pre>
		</td>
	</tr>
</table>

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

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 125, 'to' => 139))); ?>
	<pre class="code"></pre>
</div>

<?php
/* form_for is a helper function that will create a form that is ready to be submitted through phery
 * any additional arguments can be passed through 'args', works kinda like an input hidden,
 * but will only be submitted if javascript is enabled
 * -------
 * 'all' on 'submit' will submit every field, even checkboxes that are not checked
 * 'disabled' on 'submit' will submit fields that are disabled
 */
echo Phery::form_for('', 'form', array('id' => 'testform', 'submit' => array('disabled' => false, 'all' => false), 'related' => '#alert', 'args' => array('whadyousay' => 'OH YEAH')));
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
	<label>Crazy field numberings</label>
	<p><input type="checkbox" value="1" name="crazy[][yes]" checked=""> Yes</p>
	<p><input type="checkbox" value="2" name="crazy[][sure]" checked=""> Sure</p>
	<p><input type="checkbox" value="3" name="crazy[][name]" checked=""> Name</p>
	<p><input type="checkbox" value="wow" name="crazy[][checking][]" checked=""> Checking?</p>
	<p><input type="checkbox" value="too" name="crazy[][checking][]" checked=""> Checking too?</p>
	<p><input type="checkbox" value="all" name="crazy[checking][][]" checked=""> Checking all?</p>
	<label for="level_education">Select a Level of Education:</label>
	<select id="level_education" name="education">
		<option value="Jr.High">Jr.High</option>
		<option value="HighSchool">HighSchool</option>
		<option value="College">College</option>
	</select>
	<label for="TofD">Select your favorite time of day:</label>
	<select size="3" id="TofD" name="TofD" multiple class="required">
		<!-- The correct would be TofD, but phery takes in account the 'multiple' attribute, and submit it as an array -->
		<option value="Morning">Morning</option>
		<option value="Day">Day</option>
		<option value="Night">Night</option>
	</select>
	<label>File</label>
	<label>This is a "one file" input</label>
	<input type="file" name="file">
	<label>This is a "multiple files" input</label>
	<input type="file" name="file" multiple="multiple">
	<label for="disabled-input">Disabled input (can be submitted with submit => array('disabled' => true))</label>
	<input type="text" id="disabled-input" name="disabled-input" value="this is disabled and wont be submitted" disabled>

	<p><input type="submit" value="Send form"></p>
</fieldset>
<?php echo '</form>'; ?>

<hr>

<h1>Automagically rendering of views through AJAX</h1>

<div class="readcode">
	<?php echo Phery::link_to('See the PHP code', 'readcode', array('class' => 'togglecode', 'args' => array('from' => 348, 'to' => 365))); ?>
	<pre class="code"></pre>
</div>

<p>
	<input id="ajax" checked type="checkbox"> Enable AJAX view rendering
</p>

<p>
	<a href="?" rel="#container">This link is outside the container, but works using rel attribute</a>
</p>

<p>
	<a href="?page=home#container">This one doesn't (?page=home)</a>
</p>

<hr>

<div id="container">
	<?php echo $content; ?>
</div>

<ul id="exceptions">
	<li style="cursor:pointer" onclick="$(this).parent().find('li').not(this).remove()" class="clear">[ Clear ]</li>
</ul>

<?php
    /* No GA in localhost will ya? */
	if (
		in_array($_SERVER['HTTP_HOST'], array('localhost','127.0.0.1')) !== false &&
		$_SERVER['REMOTE_ADDR'] !== '127.0.0.1' &&
		$_SERVER['REMOTE_ADDR'] !== '::1'
	):
?>
<script>
	var _gaq = _gaq || [];
	_gaq.push(['_setAccount', 'UA-10569443-14']);
	_gaq.push(['_trackPageview']);

	(function () {
		var ga = document.createElement('script');
		ga.type = 'text/javascript';
		ga.async = true;
		ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		var s = document.getElementsByTagName('script')[0];
		s.parentNode.insertBefore(ga, s);
	})();
</script>
<?php endif; ?>
</body>
</html>
