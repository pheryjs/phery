# PHP + jQuery + AJAX = phery

## What do I need?

+    PHP 5.3+ with JSON (no legacy support)
+    jQuery 1.6+ (jQuery 1.5 won't work with the retry feature)
+    Firefox 3+, IE 7+, Chrome 5+, Safari 5+

## Introduction

Really simple unobtrusive, yet very powerful, AJAX library with direct integration and mapping of jQuery functions in PHP, 
maps even to extended and custom functions set by $.fn, can create elements just like $('<element/>') does, as **phery** creates a seamless integration with jQuery AJAX
to PHP functions, with unobtrusive event binding to elements, original idea for Ruby on Rails @ <http://github.com/rails/jquery-ujs>

Uses HTML5 data attributes to achieve this, and no additional libraries are needed, even for Internet Explorer. 
Links and forms will still be able to send GET/POST requests and function properly without triggering **phery** when javascript isn't enabled (or triggering it in case you still want to respond to POST requests anyway). 

W3C validator might complain about data-* if you're not using <!doctype html> (HTML5 DOCTYPE)

Strict standards for PHP 5.3+ and advised to use jQuery 1.6+. Being just one PHP file, and one javascript file, it's pretty easy to 'carry'
around or to implement in PHP auto-load scenarios, plus it's really FAST! Average processing time is around 3ms with vanilla PHP, according to Firebug

PHP magic_quotes_gpc prefered to be off. you are always responsible for the security of your data, so escape your text accordingly
to avoid SQL injection or XSS attacks. 

Also, relies on JSON on PHP. All AJAX requests are sent as POST only, so it can still interact with GET requests,
like paginations and such. Compatible with all major browsers, Firefox 3+, Opera 10+, Chrome 5+, Internet Explorer 7+
And tested in the new upcoming browsers, Firefox 4, Chrome 11 and IE9, and there are no browser specific hacks.

Even though it appears to work in Internet Explorer 6, I don't care and don't test in it.

The code is mostly commented using phpDoc and jsDoc, for a less-steep learning curve, using doc-enabled IDEs.
Also, most of the important and most used functions in jQuery were added as phpDoc, as a magic method of the **phery_response** class.

It appears that the RETRY option doesn't work with jQuery 1.5.x, due to a bug in jQuery, so upgrade to version 1.6

## Known compatibility issues

phery has a compatibility issue with CKEditor when it tries to open dialogs. It happens because CKEditors try to check if a variable is a function
by testing the .call method on it. The proper way would be using typeof(v) === 'function' instead, so to this fix, search for V.call?V():V and replace with the typeof definition

## TODO

+   Be able to pick what kind of event you want on an element eg: mouseenter, mouseup, focus, blur, etc
+   Be able to use many jquery nested calls like:
    
    phery_response::factory()->j('body')->append(phery_response::factory('<div><a href="#" data-remote="test">Test</a></div>')->data('args',array('hello' => true))

## Example code

Check the a lot of examples and code at <https://github.com/gahgneh/phery/raw/master/index.php>
    
## Releases

+   **0.6b**: Javascript code additions, support for "change" event on selects and PHP helper for creating selects, added encoding support, defaults to UTF-8, fix when argument passing when not an array or JSON object, added more jQuery functions to the IDE autocomplete phpDoc - 8th July. 2011
+   **0.5.2b**: Improved code for cursor, added $.phery.options.ajax.retry_limit and automatic retry abilities, updated examples in index.php and adjusted documentation, minor change in PHP side - 06th May. 2011
+   **0.5.1b**: Fixed events, events will be executed as GLOBAL then PER ELEMENT. Returning false cancels propagation. Fixed console.log, updated index.php with examples and removed dependency for livequery plugin, jquery 1.5.2 got it fixed - 27th Apr. 2011
+   **0.5b**:  Added $.phery.options.default_href, added ability to call anonymous functions callbacks directly from PHP, removed closed from script() call, added exception event - 11st Mar. 2011
+   **0.4b**:  Added more error checking, fixed some bugs, improved both PHP and js code, included jQuery 1.5.1, changed the way the callbacks are executed and handled, removed external JSON parser - 4th Mar. 2011
+   **0.3b2**: Removed some mal functioning code from js, corrected minor things in PHP and example - 15th Nov. 2010
+   **0.3b1**: Test changes to function parsing client-side, added $.callRemote(), and changes to PHP code - 23rd Oct. 2010
+   **0.2b**:  Renamed project to phery, improved js code - 11st Oct. 2010
+   **0.1b**:  First public release as Pjax - 30th Sep. 2010

## Documentation

It's really simple (mostly) as

    <?php
    phery::instance()->set(array(
      'function_name' => function($data){
         return phery_response::factory()->alert(print_r($data, true));
      }
    ))->process();
    ?>
    <html>
      <script src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
      <script src="phery.js"></script>
      <a data-remote="function_name">Click me</a>
    </html>

### PHP server-side

#### phery - The main object, that should be reused everywhere (singleton style), but you can create many instances just fine
    
    <?php
    phery::instance()->(...);
    ?>

***

#### phery->callback(array('pre' => array(), 'post' => array()))

Add a callback that will execute in all functions that are registered using phery->set(), and can be any number of callbacks,
useful when you're going to execute the same task for all AJAX requests.

    <?php
    function pre_function_one($ajax_data, $callback_specific_data)
    {
      // Trim the data, assuming every item is a string, and not an array of array
      return array_map('trim', $ajax_data);
    }

    function post_function($ajax_data, $callback_specific_data, $answer)
    {
      Database::insert('table', $ajax_data); // key/value pairs for e.g.
      // $answer can be a phery_response
      if ($answer instanceof phery_response)
      {
        $answer->alert('Ive been post processed!');
      }
      return true;
    }
    phery->callback(array(
      'pre' => array('pre_function_one', 'pre_function_two'),
      'post' => array('post_function')
    ));
    ?>

***

#### phery->data(...)

Add any additional data, that will be accessible to either process functions or callback

    <?php
    
    phery::instance()
    ->set(array('delete' => 'process_delete'))
    ->callback(array('pre' => 'callback_function'))
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

***

#### phery->config(array())

Set configuration for the current instance of phery. Passed as an associative array, and can be passed when creating a new
instance.

+   **exit_allowed**:
    Boolean, set if the code will call PHP function "exit" upon processing a valid ajax call, defaults to true

+   **no_stripslashes**:
    Boolean, setting to true won't execute strip_slashes on the incoming data, setting to true will execute, defaults to false

+   **exceptions**:
    Boolean, setting to true will make the class throw exceptions, that can be caught as phery_exception

+   **unobstructive**:
    Array of strings, passes the aliases provided as keys in phery->set() to respond to regular POST "non-ajax" calls.

***

#### phery::is_ajax()

Returns a boolean, checks if it's an AJAX request, or plain POST

    <?php
    if(phery::is_ajax()){
      var_dump($_POST);
    }
    ?>

***

#### phery->answer_for($alias, $default = NULL)

Return the answer for a function that returned unobstructively. Returns $default if none

    <?php
    $database_result = phery::instance()->answer_for('alias', false); // in this case, returns a Database result
    if ($database_result === false)
    {
      $database_result = new Database_Result(...);
    }
    ?>

***

#### phery->set(array $functions)

Register the functions that will be triggered by AJAX calls.
The key is the function alias, the value is the function itself.

    <?php
    function outside($ajax_data, $callback_data){
      return phery_response::factory();
    }
      
    class classy {
      function inside($ajax_data, $callback_data){
        return phery_response::factory();
      }
        
      static function inside_static($ajax_data, $callback_data){
        return phery_response::factory();
      }
    }
      
    $class = new classy();
      
    phery::instance()->set(array(
      'alias' => function(){ return phery_response::factory(); },
      'outside' => 'outside',
      'class' => array($class, 'inside'),
      'class' => 'classy::inside_static',
      'namespaced' => 'namespaced\function'
    ));
    ?>

Callback/response function comprises of:

    <?php
    function func($ajax_data, $callback_data){
      // $ajax_data = data coming from browser, via AJAX
      // $callback_data = can have anything you specify, plus additional information, like **submit_id** that
      // comes automatically from the AJAX request, containing the ID of the calling DOM element, if has an id="" set
      return phery_response::factory(); // In most cases, you'll want to return a phery_response object
    }
    ?>

***

#### phery::factory(array $config = null)

Creates a new instance of phery, that is chainable

    <?php
      phery::factory(array('exceptions' => true))
      ->set(array('alias' => 'func'))
      ->process();
    ?>

***

#### phery::instance($config = null)

Singleton static method, ensures just one instance of phery

***

#### phery->process($last_call = true)

Takes just one parameter, $last_call, in case you want to call process() again later, with a different set. **last_call** won't allow the
process to return until you call it again or exit the code inside a callback function.

    <?php
    $phery->process(false);
    // continue execution
    $phery->callback(array('post' => 'function'))->process();
    // exit is called, from this point and beyond won't be executed
    $i += 10;
    ?>

***

#### phery::link_to($title, $function, array $attributes = array(), phery $phery = null)

Helper static method to create any element with AJAX enabled. Check sources or code hinting for better scoop

    <?php echo phery::link_to('link title', 'function_name', array('class' => 'red', 'href' => '/url')); ?>

***

#### phery::form_for($action, $function, array $attributes = array(), phery $phery = null)

Helper static method to open a form that will be able to execute AJAX submits. Check sources or code hinting for better scoop

    <?php echo phery::form_for('/url-to-action/or/empty-means-current-url', 'function_name', array('class' => 'form', 'id' => 'form_id', 'submit' => array('disabled' => true, 'all' => true))) ?>
      <input type="text" name="text">
      <input type="password" name="pass">
      <input type="submit" value="Send">
    </form>

***

#### phery::select_for($function, $items, array $attributes = array(), phery $phery = null)

Helper static method to display a select element.

    <?php echo phery::select_for('function_name', array(1 => 'true', 2 => 'hello', 3 => 'control'), array('selected' => 2)) ?>

***

#### phery::coalesce(...)

Helper static method that returns the first non null/false/0 item

    <?php echo phery::coalesce($undeclared_variable, UNDECLARED_CONSTANT, $data['no-index'], 10) // return 10 ?>

***

#### phery_response - Used as a return value to any function called using AJAX, in most cases

Check the code completion using an IDE for a better view of the functions, read the source or check the examples

***

#### phery_exception - Exceptions that are thrown by phery, when enabled to do so, with some descriptive errors

Check the code completion using an IDE for a better view of the functions, read the source or check the examples

### Javascript client-side

***

#### $.callRemote(functionName, arguments, attributes, directCall)

Calls an AJAX function directly, without binding to any existing elements, the DOM element is created and removed on-the-fly

+   **functionName**: string, name of the alias defined in phery->set() inside PHP
+   **arguments**: object or array or variable, the best practice is to pass an object, since it can be easily accessed later through PHP, but any kind of parameter can be passed, from strings, ints, floats, and can also be null (won't be passed through ajax)
+   **attributes**: object, set any additional information about the DOM element, usually for setting another href to it. eg: {href: '/some/other/url?p=1'}
+   **directCall**: boolean, defaults to true, setting this to false, will return the created DOM element (invisible to the user) and can have events bound to it

        element = $.callRemote('remote', {'key':'value'}, {'href': '/new/url'}, false);
        element.bind({
          'ajax:complete': function(){
            $('body').append($(this));
          }
        }).callRemote();

***

#### $('form').serializeForm(opts)

Generate an object serialized with unlimited depth from forms. Opts can be defined as:

+   **disabled**: boolean, process disabled form elements, defaults to false
+   **all**: boolean, include all elements from a form, including null ones, assigning an empty string to the item, defaults to false
+   **empty**: boolean, setting to false will skip empty fields (won't be submitted), setting to true will submit empty key:values pairs, this overrides "all" setting, defaults to true

        $('form').serializeForm({'disabled':true,'all':true,'empty':false});

+   A form like:
        
        <form>
          <input name="field[gender]" type="text" value="male">
          <input name="field[name]" type="text" value="John Doe">
          <input name="breakfast" type="text" value="eggs & bacon">
          <select name="select">
            <option selected value="1">1</option>
            <option selected value="2">2</option>
            <option value="3">3</option>
          </select>
        </form>
            
+   will generate an object like:
        
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
        
***

#### $('form').reset()

Some helper function that has been long missing from jQuery, to simply reset a form and remove all values from it. Can reset multiple forms at once

***

#### $('element').triggerAndReturn(event, data)

Trigger an event and return the result. Returning false on an event will make this method return false

    var $element = $('element');
    $element.bind({
      'customevent': function(){
        return false; // stop custom event 
      }
    });
    if ( ! $element.triggerAndReturn('customevent', {'key':'value'})) return;

***
#### $('element').callRemote()

Trigger the AJAX call, takes no parameter. Only available for elements previously bound with the ajax events. Returns boolean

    $('a#id04').callRemote();

### Options and global and element events

Global events will always trigger, and they first come empty and do nothing.
It's mainly useful to show/hide loading screens, or update statuses

***

#### $.phery.events

These events are triggered globally, independently if called from an existing DOM element or through callRemote()

+   **$.phery.events.before**: function ($element)
    Triggered before everything, happens right after callRemote() call

+   **$.phery.events.beforeSend**: function ($element, xhr)
    Triggered before sending the data through AJAX, Useful to add any CSRF protections here

+   **$.phery.events.success**: function ($element, data, text, xhr)
    Triggered just before the answer from the response was received successfully and will start to process the data
    Returning false halts the processing, make sure to return true

+   **$.phery.events.complete**: function ($element, xhr)
    Triggered after the data was processed. and is triggered if there was no error.

+   **$.phery.events.error**: function ($element, xhr, status, error)
    When an error happens when requesting to the provided URL. It won't be triggered if the PHP code fails to execute

+   **$.phery.events.after**: function ($element)
    Right after the AJAX call was made (asynchronously), it doesn't wait for the complete/error/success callbacks to be fired.

+   **$.phery.events.exception**: function ($element, exception)
    Will be called if any problem happens while processing data, or executing jquery calls
    
        $.phery.events.before = function(){
          $('#loading').fadeIn();
        }
        
        $.phery.events.complete = function(){
          $('#loading').fadeOut();
        }

***

#### $.phery.options

The current options that are available. Reminding that additional configurations and modifications on the AJAX calls can be done using [$.ajaxSetup()](http://api.jquery.com/jQuery.ajaxSetup)

+   **$.phery.options.per_element_events**: Boolean, enable or disable per element events. See below. Enabled by default
+   **$.phery.options.cursor**: Boolean, change the body and html cursor to wait while the processing is happening and change back to auto after it's completed or error'ed out. Enabled by default
+   **$.phery.options.default_href**: String, if you have a default controller that will take all ajax calls, specify it here. It will be overwritten if you provide `href`, `data-target` or `action` on elements. False by default
+   **$.phery.options.ajax**: Object, adjust AJAX retry_limit for AJAX calls.
  +   **$.phery.options.ajax.retry_limit**: Retry this many times before calling error callbacks. Defaults to zero (no retries)

***

#### Per element events

Per element events are almost the same from global events, they only differ on the
parameters list, they don't take the $element argument. Refer to $.phery.events above for description for each event.

+   **ajax:before**: function ()
+   **ajax:beforeSend**: function (xhr)
+   **ajax:success**: function (data, text, xhr)
+   **ajax:complete**: function (xhr)
+   **ajax:error**: function (xhr, status, error)
+   **ajax:after**: function ()
+   **ajax:exception**: function (exception)

        $('form').bind({
          // Enable them again
          'ajax:complete': function(){ $(this).find('input').removeAttr('disabled'); },
          // Disable form elements
          'ajax:before': function(){ $(this).find('input').attr('disabled', 'disabled'); }
        });