### v2.5.0 - 30th April 2013
    * Added remote publish/subscribe/broadcast. Ideal to respond to commands without needing to use call(), apply(), or DOM manipulation.
    * The behavior of phery.remote changed, the same element, when passing direct_call to false, can be reused many times using phery('remote'), so to free the memory, it needs to call phery('remove')
    * Added the phery.timer function to execute polling in a set interval
    * Made a exclusive shortcut for Object.prototype.hasOwnProperty.call, kinda hard to keep writing that huge line before loops

### v2.4.7 - 24th April 2013
    * IE9 is another ugly monster and randomly fails on UTF-8 AJAX responses without explicitly setting it from PHP
    * Added global static Phery::$encoding, defaults to UTF-8

### v2.4.6 - 21st April 2013
    * Removed dependency on document ready (wasn't working on AngularJS)
    * Added the phery.json() shorthand for phery.remote(..., false).on('phery:json', cb).phery('remote')

### v2.4.5 - 1st April 2013
    * Fix bug navigate_to wasn't working with enable.only option set to true

### v2.4.4 - 26th March 2013
    * Implemented the enable.only option, so the AJAX calls (per element) can be called once a time
    * Minor doc adjustments

### v2.4.3 - 19th March 2013
    * Removed file_upload js config
    * Fixed the README.md
    * Cleaned up the php file
    * Fixed include_stylesheet and phery view event assignment (messed up hasOwnProperty there)

### v2.4.2 - 18th March 2013
    * Applied flyweight pattern on the process request to reuse most of the data that was being passed around in many functions
    * Fixed Webkit random maximum stack size RangeError when using nested responses
    * Preparing code for NodeJS version (2.5ish)
    * Upcoming support for the <template> element
    * Fixed remove data for temporary elements
    * Added call_cache functionality to purge old unused functions (clear it every minute). It would hold too many items for similar functions that changed only 1-3 bytes for example
    * Fixed event bubbling regression that the last version introduced
    * Removed 'compress' parameter, it was doing more harm than good
    * Fixed beforeSend that wasn't cancelling on return false
    * dump_vars now will process PheryResponse, if any gets passed to it
    * Fixed DOM elements that were being parsed as a plainobject, using unecessary CPU cycles
    * Normalized event target and added $target, that is a $(target) version of the DOM node
    * Implemented 'only' option, so just one task may run at a time
    * Implemented the status 'inprogress' (that can be accessed through $(el).phery('inprogress') when there's an AJAX call going on

### v2.4.0 - 17th February 2013
    * Refactored code for unit testing
    * Had to change all the data attributes, because 1.9 removed the support for namespaced data. If you were manually writing data-remote without using the Phery::link_to, it's time to start using it
	* Remade logic inside the process() method
	* Fixed problem when exit_allowed is set to false
	* Fixed CSRF renewing every call (when dynamically created in the server side)
	* Rewrite of respond() and shutdown_handler() method
	* Phery::set now overwrite previous functions, to allow polymorphism
	* Added unset_function() to remove functions added by set()
	* Added the "return" config option for frameworks that have problems with premature exit, and you can put the response where you may please, instead of echoing it
	* Updated Javascript code to handle attribute for many input types, including formaction, formmethod, etc
	* Added file upload through AJAX, only on good browsers, using XHR2
	* this() was deprecated in favor of this (as a property), so PheryResponse::factory()->this instead of PheryResponse::factory()->this()
	* jquery() have been exchanged for jquery (as a property) when using to access root jQuery functions, like `$.getJSON` or `$.when`
    * Added phery.remotes, to execute many ajax calls in order, acts like an ajax queue
    * Added proxy to attr in phery.remote, the context of all events will be the element passed in proxy
    * Added data-phery-cache, if the attribute exists, the JSON request will be cached
    * Added the progress event for any AJAX call (on good browsers and IE10)
    * Improved merge data, you may specify jQuery objects now, and the library will try it's best to see if any value can be added
    * Making use of the prop method instead of attr (since jQuery 1.8)
    * Introducing the new method lock_config() that won't allow any configurations to be changed after it's been changed (security measure), and it can't be unlocked
    * Introducing the new option autolock that locks the config after the page has been loaded
    * Introducing the new option inline.enable so you can load direct PheryResponse's inside `<script>phery.load('<?=PheryResponse::factory()->render();?>')</script>` on page load, so you may reuse the same code you'd return from an AJAX response to a page load
    * Applied Object.freeze (in browsers that support it) on phery, so no tampering on the library!
    * Fixed when retrying, not calling the always callback again
    * Added two helper jQuery selectors, :phery-remote and :phery-confirm
    * Added unless operator in PheryResponse
    * Added a helper function for file uploads, PheryResponse::files
    * Removed no_stripslashes, since PHP 5.3 made magic_quotes_gpc deprecated (and removed in 5.4).
    * Updated phpdoc magic methods from new jQuery versions (removed, deprecated, etc)
    * Removed deprecated stuff from the js library
    * Fixed excluded url that matches exactly # always
    * Fixed event bubbling for custom events
    * Element events now bubble to the document, making it possible to use event delegation with phery events
    * Renamed the library to phery.js to follow the 'trend' plus the word I picked seems to be a person name somewhere in the world (and an item from a game)
    * Exceptions now, since it passes sensitive information to the client (like the current full path of the file), it now returns only the filename.

### v2.3.1 - 2nd December 2012
	* Fixed a serious ordering JSON problem in Chrome and IE (broken by design)
	* Fixed left overs from respond_to_post support
	* Fixed redirect function in PheryResponse
	* Fixed merged responses
	* Fixed AJAX retry on error
	* Changed print_vars on PheryResponse to use var_export and return usable PHP code, instead of print_r

### v2.3.0 - 30th November 2012
    * Renamed path() to access() in PheryResponse, makes more sense
    * Consecutive calls to jquery(), access(), phery_remote(), this() now don't stack under a single common command
    * phery.view now accepts any type of selector, not only ids
    * Removed respond_to_post, there's no use for it since it's an AJAX library
    * Added $no_close option for the link_to function, you may create ajax containers and close it yourself
    * Made the library AMD compatible
    * Fixed PheryFunction function declarations starting with white space

### v2.2.3 - 27th November 2012
    * Small fix in the Javascript code, that won't try to process non-PheryResponse returns

### v2.2.2 - 25th November 2012
    * Fixed data-related for forms
    * Added include_stylesheet and include_script shortcuts in PheryResponse to automatically add scripts and stylesheets to the page head
    * Changed DOCS from phpDocumentor to ApiGen
    * Fixed phpDoc inside Phery.php to proper format

### v2.2.1 - 22nd November 2012
    * Changed the phery() call to be a magic method instead of implemented in PheryResponse, so it can be accessed using this() and selectors
    * Created HISTORY.md instead of putting the history inside README.md
    * $('element').phery('remote') now execute for all of the selected elements and return jQuery

### v2.2.0 - 21st November 2012
    * Added a new option that makes structural elements like DIV, HEADER, unclickable, but still can send data AJAX calls

### v2.1.0 - 18th November 2012
    * Nesting fix, added access method to PheryResponse
    * Removed unecessary eval()s
    * Rewrite of a couple of internal functions
    * Added CSRF protection

### v2.0.1 - 9th November 2012
    * Small fix on compressed answers

### v2.0 - 4th November 2012
    * Added data-related, to get the value from somewhere else
    * The behavior of data-args for single value has changed
    * data-method will emulate RESTful response
    * Fixed error_handler and added a meaningful exception when the callback returns void instead of PheryResponse.
    * Removed string callbacks
    * Added PheryFunction for javascript callbacks from PHP
    * Added the ability to do nested PheryResponse calls
    * Improved phery.view in all browsers
    * Fixed phery.view in IE8
    * Added a bunch of utility functions in PheryResponse
    * Implementation of this() in PheryResponse, accesses the calling element directly, simply the best function added so far

### v1.0 - 4th September. 2012
    * **BREAKING API CHANGES**
    * Complete revamp of Javascript code to use 'delegate' instead of 'live'
    * Using jQuery namespace'd events and data
    * Support for self closing HTML tags, like IMG
    * Exposed mouse events for each element (form, select / multiple, tags)

### v0.6b - 8th July. 2011
    * Javascript code additions
    * Support for "change" event on SELECT elements
    * PHP helper for creating a SELECT
    * Added encoding support defaults to UTF-8
    * Fix when argument passing when not an array or JSON object
    * Added more jQuery functions to the IDE autocomplete phpDoc

### v0.5.2b - 06th May. 2011
    * Improved code for cursor
    * Added $.phery.options.ajax.retry_limit and automatic retry abilities
    * Updated examples in index.php and adjusted documentation
    * Minor change in PHP side

### v0.5.1b - 27th Apr. 2011
    * Fixed events, events will be executed as GLOBAL then PER ELEMENT. Returning false cancels propagation.
    * Fixed console.log
    * Updated index.php with examples and removed dependency for livequery plugin, jquery 1.5.2 got it fixed

### v0.5b - 11st Mar. 2011
    * Added $.phery.options.default_href
    * Added ability to call anonymous functions callbacks directly from PHP
    * Removed closure from script() call
    * Added exception event

### v0.4b - 4th Mar. 2011
    * Added more error checking
    * Fixed some bugs
    * Improved both PHP and js code
    * Included jQuery 1.5.1
    * Changed the way the callbacks are executed and handled
    * Removed external JSON parser

### v0.3b2 - 15th Nov. 2010
    * Removed some mal functioning code from js
    * Corrected minor things in PHP and example

### v0.3b1 - 23rd Oct. 2010
    * Test changes to function parsing client-side
    * Added $.callRemote(), and changes to PHP code

### v0.2b - 11st Oct. 2010
    * Renamed project to phery
    * Improved js code

### v0.1b - 30th Sep. 2010
    * First public release as Pjax
