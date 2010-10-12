<?php
  include ('pjax.php');
  class k{
    function test($args){
      return pjax_response::factory()->jquery('div.test')->toggle('fast');
    }
    static function test2(){
      return pjax_response::factory()->call('test', 1, 2);
    }
    function data(){
      return
        pjax_response::factory()
        ->jquery('#special2')->data('testing', array('nice' => true))
        ->jquery('div.test2')->css(array('backgroundColor' => '#f00'))->animate(array(
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
  function test(){
    return 'hi';
  }

  function trigger(){
    return pjax_response::factory()->trigger('test', 'div.test');
  }

  // data contains form data
  function form($data){
    return pjax_response::factory()->html(print_r($data, true), 'div.test2');
  }

  $t = new k;

  pjax::factory()
  ->set(array(
    'test' => array($t, 'test'), // instance method call
    'test2' => 'test', // regular function
    'test3' => function($arg){ return pjax_response::factory()->alert($arg); }, // Lambda/anonymous function
    'test4' => array('k', 'test2'), // static function
    'test5' => function(){ return pjax_response::factory()->redirect('http://www.google.com'); }, // Lambda
    'data' => array($t, 'data'), // Unbind ajax from all elements
    'trigger' => 'trigger', // Trigger even on another element
    'form' => 'form' // Trigger even on another element
  ))
  ->process();
?>
<!doctype html>
<html>
  <head>
    <script type="text/javascript" src="jquery.js"></script>
    <script type="text/javascript" src="pjax.js"></script>
    <script type="text/javascript">
      /* <![CDATA[ */
      function test(x, y) {
        alert(x + y);
      }
      
      $(function(){
        $('div.test').bind({test:function(){
            $(this).html('triggered!');
        }});

        /* Manually process the result of ajax call */
        $('#special').bind('ajax:success', function(data, text, xhr){
          // The object will receive the data, return data from 'test' function, it's a string
          console.log(text);
          // Returning false will prevent the parser to continue searching for commands
          return false;
        }).attr('data-type', 'html'); // The data-type must override the type to 'html', since the default is 'json'

        /* Bind the ajax:complete */
        $('#special2').bind({
          'ajax:complete':function(xhr){
            $('div.test2').text(('$.data for item is ' + $(this).data('testing')['nice']));
          }
        });
        // Let's just bind to the form, so we can apply some formatting to the text
        $('form').bind({
          'ajax:complete':function(){
            $div = $('div.test2');
            $div.html($div.html().replace(/\n/g, '<br>').replace(/\s/g, "&nbsp;&nbsp;"));
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
    </style>
  </head>
  <body>
    <ul>
      <li><?php echo pjax::link_to('Instance method call', 'test', array('confirm' => 'Are you sure?', 'args' => array(array('hi' => 'test')))); ?> (magic call to jquery toggle() on 'div.test')</li>
      <li><?php echo pjax::link_to('Regular function', 'test2', array('confirm' => 'Are you sure?', 'id' => 'special', 'args' => array('hi'))); ?> (returns plain text in this case)</li>
      <li><?php echo pjax::link_to('Call to lambda', 'test3', array('confirm' => 'Are you sure?', 'args' => array('hi'))); ?> (call a lambda function that returns an alert according to the data here, which is 'hi')</li>
      <li><?php echo pjax::link_to('Static call from class', 'test4', array('confirm' => 'Execute addition?')); ?> (call to an existing javascript function with two parameters)</li>
      <li><?php echo pjax::link_to('Redirect to google.com', 'test5', array('confirm' => 'Are you sure?', 'tag' => 'button')); ?> (leaves the page, tag is a 'button')</li>
      <li><?php echo pjax::link_to('Test data and check it on callback ajax:complete', 'data', array('tag' => 'b', 'id' => 'special2')); ?> (using 'b' tag, chain commands for css() and animate())</li>
      <li><?php echo pjax::link_to('Trigger event', 'trigger'); ?> (Trigger event 'test' on both divs)</li>
    </ul>
    <div class="test" style="border:solid 1px #000; padding: 20px;">Div.test</div>
    <div class="test test2" style="border:solid 1px #000; padding: 20px;">Div.test 2</div>

    <?php echo pjax::form_for('', 'form', array('confirm' => 'Submit the form now?!')); ?>
      <fieldset><br/>
        First Name:<input type="text" name="Fname" maxlength="12" size="12"/> <br/>
        Last Name:<input type="text" name="Lname" maxlength="36" size="12"/> <br/>
        Gender:<br/>
        Male:<input type="radio" name="gender" value="Male"/><br/>
        Female:<input type="radio" name="gender" value="Female"/><br/>
        Favorite Food:<br/>
        Steak:<input type="checkbox" name="food[]" value="Steak"/><br/>
        Pizza:<input type="checkbox" name="food[]" value="Pizza"/><br/>

        Chicken:<input type="checkbox" name="food[]" value="Chicken"/><br/>
        <textarea wrap="physical" cols="20" name="quote" rows="5">Enter your favorite quote!</textarea><br/>
        Select a Level of Education:<br/>
        <select name="education">
        <option value="Jr.High">Jr.High</option>
        <option value="HighSchool">HighSchool</option>
        <option value="College">College</option></select><br/>
        Select your favorite time of day:<br/>
        <select size="3" name="TofD" multiple>
        <option value="Morning">Morning</option>

        <option value="Day">Day</option>
        <option value="Night">Night</option></select>
        <p><input type="submit" /></p>
        
      </fieldset>
    </form>
  </body>
</html>