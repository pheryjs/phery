# PHP + jQuery + AJAX = phery
## Introduction
Really simple unobstrutive, yet powerful, AJAX library with direct integration with PHP and jQuery, maps to any jQuery function, even extended ones from $.fn

phery creates a seamless integration with jQuery AJAX to PHP functions, with unobstrutive Javascript, binding events to elements, original concept by siong1987 @ http://github.com/rails/jquery-ujs

Uses HTML5 attributes to achieve this. Links and forms will still be able to send GET/POST requests and function properly without triggering phery.

Strict standards for PHP 5.3 and advised to use jQuery 1.4+

magic_quotes_gpc prefered to be off. you are always responsible for the security of your data, so escape your text accordingly to avoid SQL injection or XSS attacks

Uses livequery plugin from http://docs.jquery.com/Plugins/livequery

Also, relies on JSON on PHP. All AJAX requests are sent as POST by default, so it can still interact with GET requests, like paginations and such.

***

## Example code

    <?php
      include 'phery.php';

      $common_response = phery_response::factory()->jquery('select')->hide('fast');
      
      function outcall(){
        return new phery_response; // empty response
      }

      phery::factory()
      ->set(array(
        'alert' => function($data){ // Lambda function in PHP 5.3
          return
            phery_response::factory()
            ->merge($common_response) // Merge a common response to all, the place of this call influences when it's going to be executed
            ->jquery('form') // jquery selector for the next calls
            ->animate(array('opacity' => 0.5), 300) // animate the opacity of the form
            ->css(array('backgroundColor' => '#ccc')) // change the background
            ->append('<input type="password" name="field[password]">') //append HTML to the form
            ->script('document.write("test")') // execute raw script
            ->html(print_r($data, true), '#randomdata') // set the innerHTML of div#randomdata
            ->call('call_me', 'Set to this value') // Call any javascript function
            ->myfunction(); // call a jQuery extended function
        },
        'test' => function($data){
          return
            phery_response::factory()
            ->merge($common_response) // Merge a common response to all
            ->redirect('http://www.google.com');
        },
        'outcall' => 'outcall'
      ))
      ->process();
    ?>
    <!doctype html>
    <html>
      <script src="jquery.js"></script>
      <script src="phery.js"></script>
      <script>
        $.fn.myfunction = function(){
          alert(this.text());
        }
        function call_me(text) {
          var $form = $('form');
          $form[0].reset();
          $form.find('input:text').val(text);
        }
      </script>
      <?php echo phery::form_for('', 'alert', array('confirm' => 'Are you sure?')); //Generates <form data-remote="alert" action="" data-confirm="Are you sure?"> ?>
        <input type="text" name="field[text]" required>
        <input type="checkbox" name="field[checkbox]">
        <input type="radio" name="field[radio]" value="1">
        <input type="radio" name="field[radio]" value="2">
        <input type="text" name="field[multiple][1]">
        <input type="text" name="field[multiple][2]">
        <select name="field[select][]" multiple>
          <option>No value</option>
          <option value="with">With value set</option>
        </select>
        <input type="submit" value="Send">
      </form>
      <?php echo phery::link_to('Redirect to google', 'test'); // generates link ?>
      <div id="randomdata"></div>
    </html>
    
***

## Documentation

TODO