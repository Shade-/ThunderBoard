var ThunderBoard = {

	templates: null,
	timeout: 4000,
	listenersInit: {},
	component: '',
	selector: '#thunderboard-container',

	init: function() {

		var localTemplates = Cookie.get('tb-templates');

		// Load templates from the local cache to save server resources
		if (localTemplates && typeof Storage !== 'undefined') {
			this.templates = this.storage.getTemplates();
		}

		if (!this.templates) {

			// Get the preview templates
			$.ajax({
				type: 'GET',
				url: 'thunderboard.php',
				success: function(data) {

					data = $.parseJSON(data);

					if (data.error) {
						return console.log(data.error);
					}

					ThunderBoard.templates = data;
					ThunderBoard.storage.saveTemplates(data);

				}
			});

		}

		var container = $(this.selector);

		// Handle preloading pages
		if (!this.listenersInit.send) {

			this.listenersInit.send = true;

			var headerHeight = $('#header').outerHeight();
			var footerHeight = $('#footer').outerHeight();

			container.on('pjax:send', function(e) {

				function search(nameKey, myArray) {

					var matchAgainst = '';

					for (var i = 0; i < myArray.length; i++) {

						var page = String(myArray[i].page);

						matchAgainst = (page.indexOf(',') !== -1) ? page.split(',') : [page];

						for (var o = 0; o < matchAgainst.length; o++) {

							if (nameKey.indexOf(matchAgainst[o]) !== -1) {
								return myArray[i].template;
							}

						}

					}

					return '';

				}

				var template = '';

				if (ThunderBoard.component.attr('href') && ThunderBoard.templates) {
					template = search(ThunderBoard.component.attr('href'), ThunderBoard.templates);
				}

				if (template) {

					// Replace eventual dynamic content found
					var regex = /\{(.*?)\}/g;
					var replacement = '';
					var breakdown = '';
					var tbData = '';
					var component = '';

					while (match = regex.exec(template)) {

						breakdown = match[1].split('|');
						tbData = breakdown[0].replace(/[^a-zA-Z0-9]/g, '');
						component = ThunderBoard.component;
						var same = '';
						var elem = '';

						// Search the closest item we find traversing up to the highest tb-item defined
						while (!elem.length) {

							same = component;

							component = component.parent().closest('*[data-tb-item]');

							if (!component.length) {
								break;
							}

							elem = component.find('*[data-tb-' + tbData + '], ' + breakdown[0]).first();

							if (component.is(same)) {
								break;
							}

						}

						if (elem.length) {

							// Does this variable has an attribute we've got to get instead of the pure html?
							if (breakdown[1]) {
								replacement = elem.attr(breakdown[1]);
							} else {
								replacement = elem.html();
							}

						} else {
							replacement = '';
						}

						if (tbData == 'subject' && !replacement) {
							replacement = ThunderBoard.component.text();
						}

						template = template.replace(match[0], replacement);

						// Delete the placeholder reference if it has been replaced
						if (replacement) {

							var temp = $(template).clone().wrap('<div>')
								.find('*[data-tb-' + tbData + '], ' + breakdown[0]).first()
								.closest('.placeholder').removeClass('placeholder').removeAttr('style') // Clear closest
								.find('.placeholder').removeClass('placeholder').removeAttr('style').end() // Clear childrens
								.end().end().end()
								.parent().html();

							if (temp) {
								template = temp;
							}

						}

					}

					$('*[data-thunderboard]')[0].innerHTML = template;

					// Set full height
					$('#content').css({
						'min-height': ($(window).height() - headerHeight - footerHeight) + 'px'
					});

				}

				// Delete off-scope elements
				$('body > *:not(#thunderboard-container):not(.loading-app)').remove();

				// Start the loading bar
				appLoading.start();

			});

		}

		// Handle end of loading bar
		if (!this.listenersInit.end) {

			this.listenersInit.end = true;

			container.on('pjax:end', function(options, xhr) {

				// Fixes overflow:hidden added by modals which makes the page unscrollable
				$('body').removeAttr('style');

				// The bar is saved within the old HTML, so when browsing back and forth without accessing the cache,
				// it shows up regardless. This fixes the issue by targetting and deleting the bar immediately
				if (xhr === null) {
					return $('.loading-app').remove();
				}
				// Stop the loading bar
				else {
					return appLoading.stop();
				}

			});

		}

		// Handle errors as if they were a normal page
		if (!this.listenersInit.error) {

			this.listenersInit.error = true;

			container.on('pjax:error', function(event, xhr, textStatus, errorThrown, options) {
				options.success(xhr.responseText, textStatus, xhr);
				return false;
			});

		}

		// Handle main operations
		if (!this.listenersInit.start) {

			this.listenersInit.start = true;

			// AJAXify the whole site
			if ($.support.pjax) {

				var $doc = $(document);

				$doc.on('click', 'a:not([data-skip]):not([href*="attachment.php"])', function(event) {

					var $this = $(this);

					if (ThunderBoard.reloadTimeout) {
						clearTimeout(ThunderBoard.reloadTimeout);
					}

					ThunderBoard.component = $this;

					$.pjax.click(event, {
						timeout: ThunderBoard.timeout,
						container: ThunderBoard.selector,
						// Create a custom replace handler to accomodate eventual page-specific stylesheets
						replacementHandler: function(context, content, options, url) {

							var stylesheets = [];
							var indexes = [];

							// Gather the stylesheets on the new page
							$.each(content, function(k, v)  {

								if (v.rel == 'stylesheet') {

									if ($('head').find('link[rel*="style"][href="' + v.href + '"]').length == 0) {
										stylesheets.push($('<link rel="stylesheet" type="text/css" href="' + v.href + '" />'));
									}

									indexes.push(k);

								}

							});

							// Remove all the stylesheets from the body (in reverse order, because otherwise we are
							// removing other nodes)
							if (indexes.length) {

								var i = indexes.length;

								while (i--) {
									content.splice(indexes[i], 1);
								}

							}

							var finishFlag = false;

							if (stylesheets.length) {

								// Append them at once
								$('head').append(stylesheets);

								var counter = stylesheets.length;

								// Add a "load" handler to all of them
								$.each(stylesheets, function(k, v) {

									v.one('load', function() {

										counter--;

										// If there are no more stylesheets remaining, replace the html
										if (counter == 0) {
											finishFlag = context.html(content);
										}

									});

								});

							} else {
								finishFlag = context.html(content);
							}

							var scrollTo = options.scrollTo;

							// Ensure browser scrolls to the element referenced by the URL anchor
							if (url.hash) {

								var name = decodeURIComponent(url.hash.slice(1));

								var anchorInterval = setInterval(function() {

									// Ensure the function has finished
									if (finishFlag === false) {
										return false;
									}

									// Check if the target is in place, and if it is, get its offset
									var target = $('#' + name);

									if (target.length) {
										scrollTo = target.offset().top;
									}

									if (typeof scrollTo == 'number') {
										
										scrollTo -= (scrollTo > 0) ? 90 : 0;

										// Finally scroll to the element, using smooth scroll
										$('html, body').animate({
											scrollTop: scrollTo
										}, 300);

									}

									return clearInterval(anchorInterval);

								}, 100); // 100ms should be enough. This function will loop until the document has been replaced

							} else if (typeof scrollTo == 'number') {
								$('html, body').animate({
									scrollTop: scrollTo
								}, 300);
							}

						}
					});

				});

			}

			// AJAXify forms
			$doc.on('submit', 'form:not([data-skip])', function(event) {

				ThunderBoard.component = $(this);

				return $.pjax.submit(event, ThunderBoard.selector);

			});

			// Add hidden inputs to forms upon submitting
			$doc.on('click', 'form:not([data-skip]) input[type="submit"]', function(event) {
				return $(this).closest('form').append($(this).clone().attr('type', 'hidden'));
			});

		}

	},

	storage: {

		getTemplates: function() {

			obj = localStorage.thunderboardTemplates;

			// Wrapped in a try/catch block to prevent obj to be undefined
			try {
				obj = JSON.parse(obj);
			} catch (e) {
				obj = {};
			}

			return obj;

		},

		saveTemplates: function(obj) {

			localStorage.thunderboardTemplates = JSON.stringify(obj);

			return Cookie.set('tb-templates', new Date().getTime() / 1000);

		}

	}

};