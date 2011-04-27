/**
 * Javascript library of phery v0.5.1 beta
 * @url https://github.com/gahgneh/phery
 */
(function ($) {
	window.log = function(){
		var 
			args = Array.prototype.slice.call(arguments);
			
		log.history = log.history || []; // store logs to an array for reference
		log.history.push(arguments);
		
		if (this.console){
			if(typeof console.log === 'object')	{
				// IE is still a malign force
				console.log(Array.prototype.slice.call(arguments));
			}	else {
				// Good browsers
				console.log.apply(null, args);
			}
		}
		return args.join("\n");
	}

	function countProperties(obj) {
		var count = 0;
		
		if (typeof obj === 'object') {
			for(var prop in obj) {
				if(obj.hasOwnProperty(prop))
					++count;
			}
		} else {
			if (typeof obj['length'] !== 'undefined')
				count = obj.length;
		}

		return count;
	}

	/**
	 * Manual call to an AJAX function, it returns the element so you can bind
	 * ajax events to it before calling, if directCall is set to false or null, and
	 * void if it was called directly
	 * @param functionName Name of the function
	 * @param args Arguments in form of an object. Eg: {'reload':true,items:[1,2,3]}
	 * @param attr Set any attribute (or attributes, through an object) on the element. Eg: {href: 'http://target-url'}
	 * @param directCall Automatically call the callRemote() function on the new element, and return void
	 * @return void|jQuery
	 */
	$.callRemote = function(functionName, args, attr, directCall){
		if( ! functionName) return false;

		$a = $('<a/>');

		$a.data('remote', functionName);
		$a.data('temp', true);

		if (typeof args !== 'undefined' && args !== null){
			$a.data('args', args);
		}

		if (typeof attr !== 'undefined') {
			$a.attr(attr);
		}

		if (typeof directCall === 'undefined')
			directCall = true;

		return (directCall?$a.callRemote():$a);
	}

	$.phery = {
		'events': {
			'before': function ($element) { return true;},
			'beforeSend': function ($element, xhr) { return true;},
			'success': function ($element, data, text, xhr) {return true;},
			'complete': function ($element, xhr) {return true;},
			'error': function ($element, xhr, status, error) {return true;},
			'after': function ($element) {return true;},
			'exception': function ($element, exception) {return true;}
		},
		'options':{
			'cursor': true,
			'per_element_events': true,
			'default_href': false
		}
	};

	var call_cache = [];

	str_is_function = function(str){
		if ($.type(str) !== 'string' || ! /^\s*?function/i.test(str) || ! /\}$/m.test(str)) return false;
		return true;
	}

	String.prototype.apply = function(obj){
		if ( ! str_is_function(this) ) return false;

		var str = this.toString(), cache_len = call_cache.length;
		fn = null;

		for(i = 0; i < cache_len; i++){
			if (call_cache[i].str === str) {
				fn = call_cache[i].fn;
				break;
			}
		}

		if (typeof fn != 'function'){
			$.globalEval('var fn = ' + str);
			call_cache.push({'str': str, 'fn': fn});
			cache_len = call_cache.length;
		}

		args = Array.prototype.slice.call(arguments, 1);
		
		if (typeof args[0] != 'undefined' && args[0].constructor === Array){
			args = args[0];
		}

		return fn.apply(obj, args);
	}
	
	String.prototype.call = function(obj){
		this.apply(obj, Array.prototype.slice.call(arguments, 1));
	}

	if (typeof $['type'] === 'undefined') {
		$.type = function( obj ) {
			return obj == null ?
				String( obj ) :
				class2type[ toString.call(obj) ] || "object";
		};
	}

	$.isFunction = function( obj ) {
		return $.type(obj) === "function" || (str_is_function(obj));
	}
	
	$.fn.extend({
		triggerAndReturn: function (name, data) {
			var event = $.Event(name);
			this.trigger(event, data);

			return event.result !== false;
		},
		
		triggerPheryEvent: function (event_name, data) {
			data = data || [];
			
			if ($.phery.events[event_name].apply(null, [this].concat(data)) === false) return false;
			
			if ($.phery.options.per_element_events) {
				return this.triggerAndReturn('ajax:' + event_name, data);
			}
			
			return true;
		},

		/**
		 * Serialize a form with many levels deep
		 * Input names must be arrays style
		 * name="food[]" or name="name[first]"
		 * Supports input, keygen, select, textarea
		 * @param bool disabled Submit disabled elements
		 */
		serializeForm:function(opt){
			opt = $.extend({}, opt);
			
			if (typeof opt['disabled'] === 'undefined' || opt['disabled'] === null) opt['disabled'] = false;
			if (typeof opt['all'] === 'undefined' || opt['all'] === null) opt['all'] = false;
			
			var $form = $(this);

			var
			result = {}
			formValues =
			$form
			.find('input,textarea,select,keygen')
			.filter(function(){
				var ret = true;
				if (!opt['disabled']) ret = !this.disabled;
				return ret && $.trim(this.name);
			})
			.map(function(){
				var $this = $(this),
				value = null;

				if ($this.is('input:radio') || $this.is('input:checkbox')){
					if ($this.is('input:radio')) {
						radios = $form.find('input:radio[name="' + this.name + '"]');
						if (radios.filter(':checked').size()) {
							value = radios.filter(':checked').val();
						}
						type = 'radio';
					} else if ($this.is('input:checked')) {
						value = $this.val();
						type = 'checkbox';
					}
				} else if ($this.is('select')) {
					options = $this.find('option:selected');
					if($this.attr('multiple')){
						value = options.map(function(){
							return this.value || this.innerHTML;
						}).get();
					} else {
						value = options.val() || options.text();
					}
					type = 'select';
				} else {
					value = $this.val();
					type = 'input';
				}

				return {
					'name': this.name,
					'value': value,
					'type': type
				};
			}).get();

			if (formValues){
				var i, value, name;

				for (i = 0; i < formValues.length; i++){
					name = formValues[i].name;
					value = formValues[i].value;

					if (!opt['all']){
						if (value === null) continue;
					} else {
						if (value === null) value = '';
					}

					if (!name) continue;

					$matches = name.split(/\[/);

					var len = $matches.length;

					for(var j = 1; j < len; j++){
						$matches[j] = $matches[j].replace(/\]/g, '');
					}

					var fields = [], strpath = [];


					for(j = 0; j < len; j++){
						if ($matches[j]){
							fields.push($matches[j]);
						}
					}

					/*
					 * this function ensures that the object of unknown depth
					 * exists, otherwise the javascript console will trigger for eg:
					 * "result.one is undefined"
					 */
					create = function(create_array, res, path){
						var field = fields.shift();

						if (field){
							if (typeof res[field] === "undefined" || !res[field]) res[field] = (create_array?[]:{});
							path.push('[\''+field+'\']');
							create(create_array, res[field], path);
						}
					}

					if (!$matches[len-1]) { // Check if the last is [], as in food[]
						create(true, result, strpath);
						/*
						 * build a multidimensional array of unknown size
						 * result["one"]["two"]["three"]["..."]
						 */
						eval('res = result' + strpath.join('') + ';');
						
						if(value.constructor === Array){
							for(x = 0; x < value.length; x++){
								res.push(value[x]);
							}
						} else {
							res.push(value);
						}
					} else { // Single value like 'field[name]' or 'name'
						create(false, result, strpath);
						/*
						 * Since we don't know the depth of the object
						 * we eval() it so we can assign to
						 * result["one"]["two"]["three"]["..."] = value;
						 * 
						 * where value will be converted properly, either to
						 * a integer, string, array or object, so it must go
						 * inside eval() as well
						 */
						eval('result' + strpath.join('') + ' = value;');
					}
				}
			}

			return result;
		},
		processRequest: function(data){
			if( ! this.data('remote')) return;

			if (data && countProperties(data)){
				var $jq, x, i, argv, func_name, logz, $this = this;

				for(x in data){
					is_selector = (x.toString().search(/^[0-9]+$/) == -1); // check if it has a selector

					if (is_selector){
						if (data[x].length){

							if (x.toLowerCase() === 'window') {
								$jq = $(window);
							} else if (x.toLowerCase() === 'document') {
								$jq = $(document);
							} else {
								$jq = $(x);
							}

							if ($jq.size()){
								for (i in data[x]){
									argv = data[x][i]['a'];
									try {
										func_name = argv.shift();
										if (typeof $jq[func_name] === 'function'){
											if (typeof argv[0] != 'undefined' && argv[0].constructor === Array){
												$jq = $jq[func_name].apply($jq, argv[0] || null);
											} else if (argv.constructor === Array) {
												$jq = $jq[func_name].apply($jq, argv || null);
											} else {
												$jq = $jq[func_name].call($jq, argv || null);
											}
										} else throw 'no function "' + func_name + '" found in jQuery object';
									} catch (exception) {
										logz = log(exception, argv);
										
										$this.triggerPheryEvent('exception', [logz]);
									}
								}
							}
						} else {
							logz = log('no commands to issue');

							$this.triggerPheryEvent('exception', [logz]);
						}
					} else {
						argc = data[x]['a'].length;
						argv = data[x]['a'];

						switch (parseInt(data[x]['c'], 10)) {
							// alert
							case 1:
								if (typeof argv[0] != 'undefined' && typeof argv[0] === 'string'){
									alert(argv[0]);
								} else {
									logz = log('missing message for alert()', argv);
									
									$this.triggerPheryEvent('exception', [logz]);
								}
								break;
							// call
							case 2:
								try {
									var funct = argv.shift();
									if (typeof window[funct] === 'function'){
										window[funct].apply(null, argv[0] || null);
									} else {
										throw 'no global function "' + funct + '" found';
									}
								} catch (exception) {
									logz = log(exception, argv);
									
									$this.triggerPheryEvent('exception', [logz]);
								}
								break;
							// script
							case 3:
								try {
									eval('(function(){ ' + argv[0] + '})();');
								} catch (exception) {
									logz = log(exception, argv[0]);

									$this.triggerPheryEvent('exception', [logz]);
								}
								break;
							default:
								logz = log('invalid command "' + data[x]['c'] + '" issued');
								
								$this.triggerPheryEvent('exception', [logz]);
								
								break;
						}
					}
				}
			}
		},
		callRemote: function () {
			if (this.triggerPheryEvent('before') === false) return false;

			var el      = this,
			url       = el.attr('action') || el.attr('href') || el.data('target') || $.phery.options.default_href || window.location.href,
			type      = el.data('type') || 'json',
			submit_id = el.attr('id');

			var data = {};

			if (el.data('args')) {
				try {
					data['args'] = $.extend({}, el.data('args'));
				} catch (exception) {
					log(exception);
				}
			}

			if (el.is('form')) {
				try {
					data['args'] =
					$.extend(
					{},
						data['args'],
						el.serializeForm(
							$.extend(
							{},
								el.data('submit')?el.data('submit'):{}
								)
							)
						);
				} catch (exception) {
					logz = log(exception);
					
					el.triggerPheryEvent('exception', [logz]);
				}
			}

			if (submit_id) {
				data['submit_id'] = submit_id;
			}

			data['remote'] = el.data('remote');
			requested = new Date().getTime();

			$.ajax({
				url: (url.indexOf('_=') === -1?
					(url + (url.indexOf('?') > -1?'&':'?') + '_=' + requested):
					(url.replace(/\_\=(\d+)/, '_=' + requested))
					),
				data: data,
				dataType: type,
				type: "POST",
				cache: false,
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				beforeSend: function (xhr) {
					if ($.phery.options.cursor) {
						$('body,html').css({
							'cursor':'wait'
						});
					}
					
					if (el.triggerPheryEvent('beforeSend', [xhr]) === false) return false; 
				},
				success: function (data, text, xhr) {
					if (el.triggerPheryEvent('success', [data, text, xhr]) === false) return false;
					el.processRequest(data);
				},
				complete: function (xhr) {
					if ($.phery.options.cursor) {
						$('body,html').css({
							'cursor':'auto'
						});
					}
					if (el.triggerPheryEvent('complete', [xhr]) === false) return false;
					if (el.data('temp')) el.remove();
				},
				error: function (xhr, status, error) {
					if ($.phery.options.cursor) {
						$('body,html').css({
							'cursor':'auto'
						});
					}
					
					if (el.triggerPheryEvent('error', [xhr, status, error]) === false) return false;

					if (el.data('temp')) el.remove();
				}
			});

			if (el.triggerPheryEvent('after') === false) return false;
		}
	});

	$('[data-confirm]:not(form)').live('click', function (e) {
		e.preventDefault();
		if (!confirm($(this).data('confirm'))) {
			if (typeof e['stopImmediatePropagation'] === 'function') e.stopImmediatePropagation()
			else e.cancelBubble = true;
			return false;
		}
		return true;
	});

	$('form[data-remote]').live('submit', function (e) {
		var $this = $(this);
		if($this.data('confirm')){
			if (!confirm($this.data('confirm'))) return false;
		}
		$this.find('input:hidden[name=remote]').remove()
		$this.callRemote();
		e.preventDefault();
		return false;
	});

	$('[data-remote]:not(form)').live('click', function (e) {
		$(this).callRemote();
		e.preventDefault();
		return false;
	});

})(jQuery);
