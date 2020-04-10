var ThunderBoard = {

	templates: null,
	timeout: 4000,
	selector: '#thunderboard-container',
	location: null,
	errors: {
		threshold: 10,
		counter: 0
	},
	isLoading: false,
	customAttributes: {
		outAnimations: [],
		inAnimations: [],
		reverseAnimation: ''
	},

	init: function() {

		if (!Turbolinks.supported) {
			return false;
		}

		var cached = Cookie.get('tb-templates');
		var $doc = $(document);

		// Load templates from the local cache to save server resources
		if (cached && typeof Storage !== 'undefined') {
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

					ThunderBoard.storage.saveTemplates(data);

				}
			});

		}

		var container = $(this.selector);

		// Handle preloading pages
		var headerHeight = $('#header').outerHeight();
		var footerHeight = $('#footer').outerHeight();

		var search = function (nameKey, myArray) {

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

		// Replace page before loading
		var showPreviewTemplate = function(event) {

			var template = '';

			if (event.originalEvent.data.url && ThunderBoard.templates) {
				template = search(event.originalEvent.data.url, ThunderBoard.templates);
			}

			if (!template) {
				template = search('global', ThunderBoard.templates);
			}

			if (template) {

				// Replace eventual dynamic content found
				var regex = /\{(.*?)\}/g,
					replacement,
					breakdown,
					dataTitle,
					attribute,
					component,
					same, elem;

				while (match = regex.exec(template)) {

					breakdown = match[1].split('|');
					dataTitle = breakdown[0].replace(/[^a-zA-Z0-9]/g, '');
					attribute = breakdown[1];
					component = ThunderBoard.component;
					same = '';
					elem = '';

					// Search the closest item we find traversing up to the highest tb-item defined
					while (!elem.length) {

						same = component;

						component = (typeof component !== 'undefined') ? component.parent().closest('*[data-tb-item]') : '';

						if (!component.length) {
							break;
						}

						elem = component.find('*[data-tb-' + dataTitle + '], ' + breakdown[0]).first();

						if (component.is(same)) {
							break;
						}

					}

					if (elem.length) {

						// Does this variable has an attribute we've got to get instead of the pure html?
						if (attribute) {
							replacement = elem.attr(attribute);
						} else {
							replacement = elem.html();
						}

					} else {
						replacement = '';
					}

					if (dataTitle == 'subject' && !replacement) {
						replacement = ThunderBoard.component.text();
					}

					template = template.replace(match[0], replacement);

					// Delete the placeholder reference if it has been replaced
					if (replacement) {

						var temp = $(template).clone().wrap('<div>')
							.find('*[data-tb-' + dataTitle + '], ' + breakdown[0]).first()
							.closest('.placeholder').removeClass('placeholder').removeAttr('style') // Clear closest
							.find('.placeholder').removeClass('placeholder').removeAttr('style').end() // Clear children
							.end().end().end()
							.parent().html();

						if (temp) {
							template = temp;
						}

					}

				}

				$('[data-thunderboard]:last')[0].innerHTML = template;

				// Set full height
				$('#content').css({
					'min-height': ($(window).height() - headerHeight - footerHeight) + 'px'
				});

			}

		};

		// Handle main operations
		// Errors – reload failing scripts
		window.onerror = function (msg, url, lineNo, columnNo, error) {
			if (error instanceof ReferenceError) {

				if (ThunderBoard.errors.counter >= ThunderBoard.errors.threshold) {
					window.location.reload();
				}

				var variable = /referenceerror:(.*?)is not defined/gi.exec(msg);
				variable = variable[1].trim();

				// Set up a check for the variable until its loaded
				var parachute = setInterval(function(variable) {

					if (typeof window[variable] === 'undefined') return false;

					// Append script (evaluating it)
					$('script:contains(' + variable + ')').appendTo('body');

					// Dispatch turbolinks' load event to take care of eventual initializers
					document.dispatchEvent(new Event('turbolinks:load'));

					ThunderBoard.errors.counter++;

					// Clear interval
					return clearInterval(parachute);

				}, 200, variable);

				return true;

			}

			return false;
		}

		ThunderBoard.location = window.location.pathname;

		// Override Turbolinks to recognize relative .php files
		Turbolinks.Location.prototype.isHTML = function() {
			var extension = this.getExtension();
			return extension == null ||
					extension === ".html" ||
					extension.match(/^(?:|\.(?:htm|html|xhtml|php))$/);
		}

		Turbolinks.setProgressBarDelay(1000000);

		var outAnimations = ThunderBoard.customAttributes.outAnimations;
		var inAnimations = ThunderBoard.customAttributes.inAnimations;

		// Page out animations
		$doc.on('turbolinks:click', function(event) {

    		// Disable for different-page navigation
    		if (new URL(event.originalEvent.data.url).pathname != ThunderBoard.location) {
        		return false;
    		}

			ThunderBoard.isLoading = true;

			// Delete off-scope elements
			$('body > *:not([data-thunderboard-context])').remove();

			var items = $('[tb-animation]');
			var length = items.length;

			$.each(items, function(k, item) {

				item = $(item);
				var animations = item.attr('tb-animation').split(',');
				if (!animations[1]) {
					animations[1] = animations[0];
				}

				item.addClass(animations[1] + ' ' + ThunderBoard.customAttributes.reverseAnimation)
					.off('webkitAnimationEnd oanimationend msAnimationEnd animationend');

				// Show preview template once the last item has finished
				if (k == (length - 1)) {
					setTimeout(function() {
						return (!ThunderBoard.isLoading) ? false : showPreviewTemplate(event);
					}, 500, event);
				}

			});

			// Fallback if no animated items
			if (!length) {
				showPreviewTemplate(event);
			}

		});

		// Page in animations
		$doc.on('turbolinks:load', function(event) {

			ThunderBoard.isLoading = false;

		    // If call was fired by TurboLinks
			if (typeof event.originalEvent.data !== 'undefined'
				&& typeof event.originalEvent.data.timing.visitStart !== 'undefined'
				&& ThunderBoard.location != window.location.href) {

				$.each($('[tb-animation]'), function(k, item) {

					item = $(item);
					var animations = item.attr('tb-animation').split(',');
					item.addClass(animations[0])
						.one('webkitAnimationEnd oanimationend msAnimationEnd animationend', function() {
							$(this).removeClass(function (index, className) {
								return (className.match(/(^|\s)uk-animation-\S+/g) || []).join(' ');
							});
						});

				});

			}

			ThunderBoard.location = window.location.pathname;

		});

/*
		$doc.on('turbolinks:before-render', function(event) {

			ThunderBoard.scrollPosition = $('[data-thunderboard]:last')[0].getBoundingClientRect().top;

		});

		// Restore scrollTop
		$doc.on('turbolinks:render', function(event) {

    		ThunderBoard.isLoading = false;

    		$('body, html').scrollTop($('[data-thunderboard]:last').offset().top - ThunderBoard.scrollPosition);

		});
*/

		// Handle get forms
		$doc.on('submit', 'form[method="get"]', function(e) {

			e.preventDefault();
			e.stopImmediatePropagation();

			var form = $(this);
			Turbolinks.visit(form.attr("action") + '?' + form.serialize());

		});

	},

	storage: {

		getTemplates: function() {

			obj = localStorage.thunderboardTemplates;

			// Wrapped in a try/catch block to prevent obj to be undefined
			try {
				obj = JSON.parse(obj);
			} catch (e) {
				obj = null;
			}

			return obj;

		},

		saveTemplates: function(obj) {

			localStorage.thunderboardTemplates = JSON.stringify(obj);

			ThunderBoard.templates = obj;

			return Cookie.set('tb-templates', new Date().getTime() / 1000);

		}

	},

};
ThunderBoard.init();
