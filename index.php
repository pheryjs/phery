<?php
  include ('phery.php');

  class myClass{
    function test($args){
      return phery_response::factory()->jquery('div.test')->filter(':eq(1)')->toggle('fast')->html($args['hi']);
    }
    static function test2($args){
      // Integers must be typecast, because JSON will
      // turn everything to a string, because
      // "1" + "2" = "12"
      // 1 + 2 = 3
      return phery_response::factory()->call('test', (int)$args[0], (int)$args[1]);
    }
    function data(){
      return
        phery_response::factory()
        ->jquery('#special2')
        ->data('testing', array('nice' => 'awesome'))
        ->jquery('div.test2')
        ->css(array('backgroundColor' => '#f00'))
        ->animate(array(
          'width' => "70%",
          'opacity' => 0.4,
          'marginLeft' => "0.6in",
          'fontSize' => "3em", 
          'borderWidth' => "10px"
        ), 1500);
    }
  }

  // You can return a string, or anything else than a standard
  // response, that the ajax:success event will be triggered
  // before the parsing of the usual functions, so you can parse by
  // your own methods, and signal that the event should halt there
  function test($args){
    return json_encode(array('hi' => $args['hello'],'hello' => 'good'));
  }

  function trigger(){
    return phery_response::factory()->trigger('test', 'div.test');
  }

  // data contains form data
  function form($data){
    return phery_response::factory()->html(print_r($data, true), 'div.test2');
  }

  $instance = new myClass;

  try{
    phery::factory(
      array('exceptions' => true) // Throw exceptions and return them in form of phery_response, usually for debug purposes
    )
    ->set(array(
      'test' => array($instance, 'test'), // instance method call
      'test2' => 'test', // regular function
      'test3' => function($args){ return phery_response::factory()->alert($args[0])->alert($args[1]); }, // Lambda/anonymous function
      'test4' => array('myClass', 'test2'), // static function
      'test5' => function(){ return phery_response::factory()->redirect('http://www.google.com'); }, // Lambda
      'data' => array($instance, 'data'), // Unbind ajax from all elements
      'trigger' => 'trigger', // Trigger even on another element
      'form' => 'form' // Trigger even on another element
    ))
    ->process();
  } catch (phery_exception $exc){
    // will trigger for "nonexistant"
    // This will only be reached if 'exceptions' is set to TRUE
    echo phery_response::factory()->alert($exc->getMessage());
    exit;
  }
?>
<!doctype html>
<html>
  <head>
    <script type="text/javascript" src="jquery.js"></script>
    <script type="text/javascript" src="phery.js"></script>
    <script type="text/javascript">
      /* <![CDATA[ */
      function test(x, y) {
        alert(x + y);
      }
      
      $(function(){
        $('div.test').bind({
          'test':function(){ // bind a custom even to the DIVs
            $(this).show().html('triggered!');
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
        }).attr('data-type', 'html'); // The data-type must override the type to 'html', since the default is 'json'

        /* Bind the ajax:complete, after data was received, and there was no error */
        $('#special2').bind({
          'ajax:complete':function(xhr){
            if( $(this).data('testing'))
            $('div.test2').text(('$.data for item "nice" is "' + $(this).data('testing')['nice']) + '"');
          }
        });
        
        // Let's just bind to the form, so we can apply some formatting to the text coming from print_r() PHP
        $('form').bind({
          'ajax:complete':function(){
            $div = $('div.test2');
            $div.html($div.html().replace(/\r\n|\n/g, '<br>').replace(/\s/g, "&nbsp;&nbsp;"));
          }
        })
      })
      /* ]]> */
    </script>
    <style type="text/css">
      a{
        text-decoration: underline;
        cursor: pointer;
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
    </style>
  </head>
  <body>
    <ul>
      <li><?php echo phery::link_to('Instance method call', 'test', array('confirm' => 'Are you sure?', 'args' => array('hi' => 'test'))); ?> (magic call to jquery toggle() on 'div.test' using filter(':eq(1)'))</li>
      <li><?php echo phery::link_to('Regular function', 'test2', array('confirm' => 'Are you sure?', 'id' => 'special', 'args' => array('hello' => 'Im a named argument :D'))); ?> (returns plain text in this case)</li>
      <li><?php echo phery::link_to('Call to lambda', 'test3', array('confirm' => 'Are you sure?', 'args' => array('first','second'))); ?> (call a lambda function that returns an alert according to the data here, which is 'first', then 'second')</li>
      <li><?php echo phery::link_to('Static call from class', 'test4', array('confirm' => 'Execute addition 1 + 2?', 'args' => array(1, 2))); ?> (call to an existing javascript function with two parameters)</li>
      <li><?php echo phery::link_to('Redirect to google.com', 'test5', array('confirm' => 'Are you sure?', 'tag' => 'button')); ?> (leaves the page, tag is a 'button')</li>
      <li><?php echo phery::link_to('Test data and check it on callback ajax:complete', 'data', array('tag' => 'b', 'id' => 'special2')); ?> (using 'b' tag, chain commands for css() and animate())</li>
      <li><?php echo phery::link_to('Trigger event', 'trigger'); ?> (Trigger event 'test' on both divs)</li>
      <li><?php echo phery::link_to('Call a non-existant function', 'nonexistant'); ?> (Call a non-existant function with 'exceptions' turned on)</li>
    </ul>
    
    <div class="test" style="border:solid 1px #000; padding: 20px;">Div.test</div>
    <div class="test test2" style="border:solid 1px #000; padding: 20px;">Div.test 2</div>

    <?php
      // form_for is a helper function that will create a form that is ready to be submitted through phery
      // any additional arguments can be passed through 'args', works kinda like an input hidden
      // 'all' on 'submit' will submit every field, even checkboxes that are not checked
      echo phery::form_for('', 'form', array('confirm' => 'Submit the form now?!', 'submit' => array('disabled' => false, 'all' => false), 'args' => array('whadyousay' => 'OH YEAH')));
    ?>
      <fieldset>
        <label>First Name:</label>
        <input type="text" name="field[name][first]" maxlength="12">
        <label>Last Name:</label>
        <input type="text" name="field[name][last]" maxlength="36">
        
        <label>Gender:</label>
        <label>Male:</label><input type="radio" name="gender" value="Male">
        <label>Female:</label><input type="radio" name="gender" value="Female">
        <label>Favorite Food:</label>
        <label>Steak:</label><input type="checkbox" name="food[]" value="Steak"><br>
        <label>Pizza:</label><input type="checkbox" name="food[]" value="Pizza"><br>

        <label>Chicken:</label><input type="checkbox" name="food[]" value="Chicken"><br>
        <textarea wrap="physical" cols="20" name="quote" rows="5">Enter your favorite quote!</textarea><br>
        Select a Level of Education:<br>
        <select name="education">
        <option value="Jr.High">Jr.High</option>
        <option value="HighSchool">HighSchool</option>
        <option value="College">College</option></select><br>
        Select your favorite time of day:<br>
        <select size="3" name="TofD" multiple>
        <option value="Morning">Morning</option>
        <option value="Day">Day</option>
        <option value="Night">Night</option></select><br>
        <label>Disabled input (can be submitted with submit => array('disabled' => true))</label>
        <input type="text" name="disabled-input" value="this is disabled and wont be submitted" disabled>
        <p><input type="submit" value="Send form"></p>
        
      </fieldset>
    </form>
  </body>
</html>