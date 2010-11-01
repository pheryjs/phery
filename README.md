## Introduction
Really simple unobstrutive, yet powerful, AJAX library with direct integration with PHP and jQuery, maps to any jQuery function, even extended ones from $.fn

    <?php
      include 'phery.php';

      phery::factory()
      ->set(array(
        'alert' => function($data){ // Lambda function in PHP 5.3
          return
            phery_response::factory()
            ->jquery('form') // jquery selector for the next calls
            ->animate(array('opacity' => 0.5), 300) // animate the opacity of the form
            ->css(array('backgroundColor' => '#ccc')) // change the background
            ->append('<input type="password" name="field[password]">') //append HTML to the form
            ->script('document.write("test")') // execute raw script
            ->html(print_r($data, true), '#randomdata') // set the innerHTML of div#randomdata
            ->alert(print_r($data, true)); // show the submitted data
        },
        'test' => function($data){
          return
            phery_response::factory()
            ->redirect('http://www.google.com');
        }
      )
      ->process();

      if ($_SERVER['REQUEST_METHOD'] == 'POST' AND !phery::is_ajax())
        die('Wow it wasn't an ajax call after all, lets do vanilla $_POST processing');
    ?>
    <!doctype html>
    <html>
      <script src="phery.js"></script>
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