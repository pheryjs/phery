/**
 * Part of phery
 * @author Paulo Cesar
 */
jQuery(function ($) {
  window.log = function(){
    log.history = log.history || [];   // store logs to an array for reference
    log.history.push(arguments);
    if(this.console){
      console.log( Array.prototype.slice.call(arguments) );
    }
  };
  
  $.fn.extend({
    triggerAndReturn: function (name, data) {
        var event = new $.Event(name);
        this.trigger(event, data);

        return event.result !== false;
    },
    /**
     * Serialize a form with many levels deep
     */
    serializeForm:function(){
      var
        result = {}
        formValues =
        $(this)
        .find('input,textarea,select')
        .filter(function(){
          return $.trim(this.name);
        })
        .map(function(){
          var $this = $(this),
              value = null;

          if ($this.is(':radio') || $this.is(':checkbox')){
            if($this.is(':checked')) value = $this.val();
          } else if ($this.is('select')) {
            options = $this.find('option:selected');
            if($this.attr('multiple')){
              value = options.map(function(){return this.value || this.innerHTML;}).get();
            } else {
              value = options.val() || options.text();
            }
          } else {
            value = $this.text() || $this.val();
          }
          return {'name': this.name, 'value': value};
        }).get();
        
      if (formValues){
        var i, value, name;
        
        for (i = 0; i < formValues.length; i++){
          name = formValues[i].name;
          value = formValues[i].value;
          
          if (value === null || !name) continue;

          $matches = name.split(/\[/);
          
          var len = $matches.length;

          for(var j = 1; j < len; j++){
            $matches[j] = $matches[j].replace(/\]/g, '');
          }

          var fields = [];

          for(j = 0; j < len; j++){
            if ($matches[j]){
              fields.push('["' + $matches[j] + '"]');
            }
          }

          create = function(fields, create_array){
            var field;

            for (j = 0; j < len; j++){
              field = fields.slice(0, j).join('');
              if (field){
                eval('if (typeof result' + field + ' == "undefined" || !result' + field + ') result' + field + ' = ' + (create_array?'[]':'{}') + ';');
              }
            }
          }

          joined = fields.join('');
          
          if(!$matches[len-1]) {
            create(fields, true);
            if(value.constructor == Array){
              for(x = 0; x < value.length; x++){
                eval('result' + joined + '.push(value[x]);');
              }
            } else {
              eval('result' + joined + '.push(value);');
            }
          } else {
            create(fields, false);
            eval('result' + joined + ' = value;');
          }
        }
      }
      
      return result;
    },
    callRemote: function () {
      this.trigger('ajax:before');

      var el      = this,
                    method  = el.attr('method') || 'POST',
                    url     = el.attr('action') || el.attr('href') || window.location.href,
                    type    = el.attr('data-type') || 'json';
      
      var data = {};

      if (el.attr('args')) {
        data['args'] = jQuery.parseJSON(el.attr('args'));
      }
      
      if (el.is('form')) {
        try {
          data['args'] = el.serializeForm();
        } catch (exception) {
          log(exception);
        }
      }
      
      data['method'] = method;
      data['remote'] = el.attr('remote');
      data['requested'] = new Date().getTime();
      
      $.ajax({
        url: url + (url.indexOf('?')>-1?'&':'?') + '_=' + data['requested'],
        data: data,
        dataType: type,
        type: method,
        cache: false,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        beforeSend: function (xhr) {
          el.trigger('ajax:beforeSend', [xhr]);
        },
        success: function (data, text, xhr) {
          if (!el.triggerAndReturn('ajax:success', [data, text, xhr])) return;

          if (data && data.length){
            for(x in data){
              if (typeof data[x]['c'] != 'undefined'){
                argc = data[x]['a'].length;
                argv = data[x]['a'];
                
                switch (parseInt(data[x]['c'], 10)) {
                  // alert
                  case 1:
                    if(argc == 1){
                      alert(argv[0]);
                    } else {
                      log('missing message for alert()', argv);
                    }
                    break;
                  // remove
                  case 2:
                    if(argc == 1){
                      $(argv[0]).remove();
                    } else {
                      log('missing selector for remove()', argv)
                    }
                    break;
                  // attr
                  case 3:
                    if(argc == 3){
                      $(argv[0]).attr(argv[1], argv[2]);
                    } else {
                      log('$.attr needs 3 arguments, ' + argc + ' provided', argv)
                    }
                    break;
                  // html
                  case 4:
                    if(argc == 2){
                      $(argv[0]).html(argv[1]);
                    }
                    break;
                  // call
                  case 5:
                    try {
                      if(argc > 0){
                        window[argv.shift()].apply(null, argv[0] || null);
                      }
                    } catch (exception) {
                      log(exception, argv);
                    }
                    break;
                  // trigger
                  case 6:
                    if (argc > 1){
                      $obj = $(argv.shift());
                      $obj['trigger'].apply($obj, argv || null);
                    } else{
                      log('$.trigger need 2 or more arguments', argv);
                    }
                    break;
                  // script
                  case 7:
                    try {
                      eval('(function(){' + argv[0] + '})()');
                    } catch (exception) {
                      log(exception);
                    }
                    break;
                  // map magic __call to jquery functions
                  case 0xFF:
                    if(argc > 1){
                      if(typeof argv[0] != 'undefined' && argv[0] && argv[1]){
                        try {
                          $obj = $(argv.shift());
                          $obj[argv.shift()].apply($obj, argv[0] || null);
                        } catch (exception) {
                          log(exception, argv);
                        }
                      } else {
                        log('no selector provided in mapping to jquery', argv);
                      }
                    } else {
                      log('mapping to jquery require 2 or more arguments, ' + argc + ' provided', argv)
                    }
                    break;
                }
              }
            }
          }
        },
        complete: function (xhr) {
          el.trigger('ajax:complete', [xhr]);
        },
        error: function (xhr, status, error) {
          el.trigger('ajax:error', [xhr, status, error]);
        }
      });

      el.trigger('ajax:after');
    }
  });

  $('[confirm]:not(form)').livequery('click', function () {
    if (!confirm($(this).attr('confirm'))) return false;
    return true;
  });

  $('form[remote]').livequery('submit', function (e) {
    $(this).callRemote();
    e.preventDefault();
    return false;
  });

  $('[remote]:not(form)').livequery('click', function (e) {
    $(this).callRemote();
    e.preventDefault();
    return false;
  });
});

/*! Copyright (c) 2010 Brandon Aaron (http://brandonaaron.net)
 * Dual licensed under the MIT (MIT_LICENSE.txt)
 * and GPL Version 2 (GPL_LICENSE.txt) licenses.
 *
 * Version: 1.1.1
 * Requires jQuery 1.3+
 * Docs: http://docs.jquery.com/Plugins/livequery
 */

(function($) {

$.extend($.fn, {
	livequery: function(type, fn, fn2) {
		var self = this, q;

		// Handle different call patterns
		if ($.isFunction(type))
			fn2 = fn, fn = type, type = undefined;

		// See if Live Query already exists
		$.each( $.livequery.queries, function(i, query) {
			if ( self.selector == query.selector && self.context == query.context &&
				type == query.type && (!fn || fn.$lqguid == query.fn.$lqguid) && (!fn2 || fn2.$lqguid == query.fn2.$lqguid) )
					// Found the query, exit the each loop
					return (q = query) && false;
		});

		// Create new Live Query if it wasn't found
		q = q || new $.livequery(this.selector, this.context, type, fn, fn2);

		// Make sure it is running
		q.stopped = false;

		// Run it immediately for the first time
		q.run();

		// Contnue the chain
		return this;
	},

	expire: function(type, fn, fn2) {
		var self = this;

		// Handle different call patterns
		if ($.isFunction(type))
			fn2 = fn, fn = type, type = undefined;

		// Find the Live Query based on arguments and stop it
		$.each( $.livequery.queries, function(i, query) {
			if ( self.selector == query.selector && self.context == query.context &&
				(!type || type == query.type) && (!fn || fn.$lqguid == query.fn.$lqguid) && (!fn2 || fn2.$lqguid == query.fn2.$lqguid) && !this.stopped )
					$.livequery.stop(query.id);
		});

		// Continue the chain
		return this;
	}
});

$.livequery = function(selector, context, type, fn, fn2) {
	this.selector = selector;
	this.context  = context;
	this.type     = type;
	this.fn       = fn;
	this.fn2      = fn2;
	this.elements = [];
	this.stopped  = false;

	// The id is the index of the Live Query in $.livequery.queries
	this.id = $.livequery.queries.push(this)-1;

	// Mark the functions for matching later on
	fn.$lqguid = fn.$lqguid || $.livequery.guid++;
	if (fn2) fn2.$lqguid = fn2.$lqguid || $.livequery.guid++;

	// Return the Live Query
	return this;
};

$.livequery.prototype = {
	stop: function() {
		var query = this;

		if ( this.type )
			// Unbind all bound events
			this.elements.unbind(this.type, this.fn);
		else if (this.fn2)
			// Call the second function for all matched elements
			this.elements.each(function(i, el) {
				query.fn2.apply(el);
			});

		// Clear out matched elements
		this.elements = [];

		// Stop the Live Query from running until restarted
		this.stopped = true;
	},

	run: function() {
		// Short-circuit if stopped
		if ( this.stopped ) return;
		var query = this;

		var oEls = this.elements,
			els  = $(this.selector, this.context),
			nEls = els.not(oEls);

		// Set elements to the latest set of matched elements
		this.elements = els;

		if (this.type) {
			// Bind events to newly matched elements
			nEls.bind(this.type, this.fn);

			// Unbind events to elements no longer matched
			if (oEls.length > 0)
				$.each(oEls, function(i, el) {
					if ( $.inArray(el, els) < 0 )
						$.event.remove(el, query.type, query.fn);
				});
		}
		else {
			// Call the first function for newly matched elements
			nEls.each(function() {
				query.fn.apply(this);
			});

			// Call the second function for elements no longer matched
			if ( this.fn2 && oEls.length > 0 )
				$.each(oEls, function(i, el) {
					if ( $.inArray(el, els) < 0 )
						query.fn2.apply(el);
				});
		}
	}
};

$.extend($.livequery, {
	guid: 0,
	queries: [],
	queue: [],
	running: false,
	timeout: null,

	checkQueue: function() {
		if ( $.livequery.running && $.livequery.queue.length ) {
			var length = $.livequery.queue.length;
			// Run each Live Query currently in the queue
			while ( length-- )
				$.livequery.queries[ $.livequery.queue.shift() ].run();
		}
	},

	pause: function() {
		// Don't run anymore Live Queries until restarted
		$.livequery.running = false;
	},

	play: function() {
		// Restart Live Queries
		$.livequery.running = true;
		// Request a run of the Live Queries
		$.livequery.run();
	},

	registerPlugin: function() {
		$.each( arguments, function(i,n) {
			// Short-circuit if the method doesn't exist
			if (!$.fn[n]) return;

			// Save a reference to the original method
			var old = $.fn[n];

			// Create a new method
			$.fn[n] = function() {
				// Call the original method
				var r = old.apply(this, arguments);

				// Request a run of the Live Queries
				$.livequery.run();

				// Return the original methods result
				return r;
			}
		});
	},

	run: function(id) {
		if (id != undefined) {
			// Put the particular Live Query in the queue if it doesn't already exist
			if ( $.inArray(id, $.livequery.queue) < 0 )
				$.livequery.queue.push( id );
		}
		else
			// Put each Live Query in the queue if it doesn't already exist
			$.each( $.livequery.queries, function(id) {
				if ( $.inArray(id, $.livequery.queue) < 0 )
					$.livequery.queue.push( id );
			});

		// Clear timeout if it already exists
		if ($.livequery.timeout) clearTimeout($.livequery.timeout);
		// Create a timeout to check the queue and actually run the Live Queries
		$.livequery.timeout = setTimeout($.livequery.checkQueue, 20);
	},

	stop: function(id) {
		if (id != undefined)
			// Stop are particular Live Query
			$.livequery.queries[ id ].stop();
		else
			// Stop all Live Queries
			$.each( $.livequery.queries, function(id) {
				$.livequery.queries[ id ].stop();
			});
	}
});

// Register core DOM manipulation methods
$.livequery.registerPlugin('append', 'prepend', 'after', 'before', 'wrap', 'attr', 'removeAttr', 'addClass', 'removeClass', 'toggleClass', 'empty', 'remove', 'html');

// Run Live Queries when the Document is ready
$(function() { $.livequery.play(); });

})(jQuery);