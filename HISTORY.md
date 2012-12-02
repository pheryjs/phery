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
