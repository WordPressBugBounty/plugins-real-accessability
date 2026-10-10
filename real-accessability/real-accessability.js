/*
 * Real Accessibility
 * Version: 1.1
 * Author: REALMEDIA
 * Website: https://realmedia.co.il
 * Dual licensed under the MIT and GPL licenses.
 */

(function($) {

	"use strict";

	$.RealAccessability = function( options ) {

		var settings = $.extend( {
			exclude: '#fb-root',
			markup: 'h1, h2, h3, h4, h5, h6, span, div, p, a, input, textarea, li, i',
			hideOnScroll: true
		}, options );

		var effects = [
			'real-accessability-grayscale',
			'real-accessability-invert'
		];

		var obj = {
			fontSize: 0,
			effect: null,
			linkHighlight: false,
			regularFont: false
		};

		var mustExclude = '#real-accessability, #real-accessability *, #wpadminbar, #wpadminbar *';

		settings.exclude = mustExclude + ', ' + settings.exclude;

		// Init
		var init = function() {
			var cookie = getSavedSettings();

			if(cookie !== null) {
				obj = cookie;
				$(settings.markup).not(settings.exclude).each(function() {
					var fontSize = parseInt( $(this).css('font-size') );
					$(this).attr('data-raofz', fontSize);
				});

				if(obj.fontSize !== 0) {
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).data('raofz') );
						$(this).css('font-size', fontSize+(2*obj.fontSize));
					});
				}

				// For themes that touched the default <?php body_class(); ?> class
				if(obj.effect !== null) {
					$('body').addClass(obj.effect);
					setPressed($('#'+obj.effect), true);
				}

				if(obj.linkHighlight === true) {
					$('body').addClass('real-accessability-linkHighlight');
					setPressed($('#real-accessability-linkHighlight'), true);
				}

				if(obj.regularFont === true) {
					$('body').addClass('real-accessability-regularFont');
					setPressed($('#real-accessability-regularFont'), true);
				}
			}

			$('#real-accessability-btn').on('click', openToolbar);
			$('#real-accessability-biggerFont').on('click', increaseFont);
			$('#real-accessability-smallerFont').on('click', decreaseFont);
			$('.real-accessability-effect').on('click', effectChange);
			$('#real-accessability-linkHighlight').on('click', linkHighlight);
			$('#real-accessability-regularFont').on('click', regularFont);
			$('#real-accessability-reset').on('click', reset);
		};

		// Get saved cookie option
		var getCookie = function(name) {
			name = name + '=';
			var arr = document.cookie.split(';');

			for(var i=0; i<arr.length; i++) {
				var c = arr[i];
				while (c.charAt(0) == ' ') c = c.substring(1);
				if (c.indexOf(name) == 0) {
					return c.substring(name.length,c.length);
				}
			}

			return '';
		};

		// Parse the saved settings, ignoring anything that is not valid
		var getSavedSettings = function() {
			var cookie = getCookie('real-accessability');
			var saved;

			if(cookie === '') {
				return null;
			}

			try {
				saved = JSON.parse(decodeURIComponent(cookie));
			} catch(e) {
				return null;
			}

			if(!saved || typeof saved !== 'object') {
				return null;
			}

			var fontSize = parseInt(saved.fontSize, 10) || 0;

			return {
				fontSize: Math.max(-3, Math.min(3, fontSize)),
				effect: $.inArray(saved.effect, effects) !== -1 ? saved.effect : null,
				linkHighlight: saved.linkHighlight === true,
				regularFont: saved.regularFont === true
			};
		};

		// Mark a toggle button as on/off
		var setPressed = function($el, pressed) {
			$el.toggleClass('clicked', pressed);
			if($el.is('[aria-pressed]')) {
				$el.attr('aria-pressed', pressed ? 'true' : 'false');
			}
		};

		// Open toolbar
		function openToolbar() {
			if($(this).parent().hasClass('open')) {
				closeToolbar();
			} else {
				$(this).parent().addClass('open');
				$(this).attr('aria-expanded', 'true');
				if(getCookie('real-accessability') == '') {
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).css('font-size') );
						$(this).attr('data-raofz', fontSize);
					});
				}
			}
		}

		var closeToolbar = function() {
			$('#real-accessability').removeClass('open');
			$('#real-accessability-btn').attr('aria-expanded', 'false');
		};

		// Make font bigger
		function increaseFont() {
			if(obj.fontSize < 3) {
				showLoader(function() {
					obj.fontSize++;
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).data('raofz') );
						$(this).css('font-size', fontSize+(2*obj.fontSize));
					});
				});
			}
		}

		// Make font smaller
		function decreaseFont() {
			if(obj.fontSize > -3) {
				showLoader(function() {
					obj.fontSize--;
					$(settings.markup).not(settings.exclude).each(function() {
						var fontSize = parseInt( $(this).data('raofz') );
						$(this).css('font-size', fontSize+(2*obj.fontSize));
					});
				});
			}
		}

		// Change effect
		function effectChange() {
			var chosenEffect = $(this).attr('id');
			obj.effect = null;

			showLoader(function() {
				for(var i=0; i<effects.length; i++) {
					if(!$('body').hasClass(chosenEffect) && chosenEffect == effects[i]) {
						$('body').addClass(effects[i]);
						setPressed($('#'+effects[i]), true);
						obj.effect = effects[i];
					} else {
						$('body').removeClass(effects[i]);
						setPressed($('#'+effects[i]), false);
					}
				}
			});
		}

		// Highlight all the links
		function linkHighlight() {
			var $this = $(this);

			showLoader(function() {
				if($('body').hasClass('real-accessability-linkHighlight')) {
					$('body').removeClass('real-accessability-linkHighlight');
					setPressed($this, false);
					obj.linkHighlight = false;
				} else {
					$('body').addClass('real-accessability-linkHighlight');
					setPressed($this, true);
					obj.linkHighlight = true;
				}
			});
		}

		// Regular font / More readable font (Arial, Helvetica, sans-serif)
		function regularFont() {
			var $this = $(this);

			showLoader(function() {
				if($('body').hasClass('real-accessability-regularFont')) {
					$('body').removeClass('real-accessability-regularFont');
					setPressed($this, false);
					obj.regularFont = false;
				} else {
					$('body').addClass('real-accessability-regularFont');
					setPressed($this, true);
					obj.regularFont = true;
				}
			});
		}

		// Reset all
		var reset = function() {
			showLoader(function() {
				setPressed($('#real-accessability ul li a'), false);

				$(settings.markup).not(settings.exclude).each(function() {
					var fontSize = parseInt( $(this).data('raofz') );
					$(this).css('font-size', fontSize);
				});

				$('body').removeClass('real-accessability-grayscale real-accessability-invert real-accessability-linkHighlight real-accessability-regularFont');

				// Reset the object
				obj.fontSize = 0;
				obj.effect = null;
				obj.linkHighlight = false;
				obj.regularFont = false;
			});
		};

		// Disable clicking on toolbar links, let Space activate them like real buttons, Escape closes the toolbar
		var disableClicking = function() {
			$('#real-accessability a[href="#"]').on('click', function(e) {
				e.preventDefault();
			});

			$('#real-accessability a[role="button"]').on('keydown', function(e) {
				if(e.key === ' ' || e.key === 'Spacebar') {
					e.preventDefault();
					$(this).trigger('click');
				}
			});

			$(document).on('keydown', function(e) {
				if((e.key === 'Escape' || e.key === 'Esc') && $('#real-accessability').hasClass('open')) {
					closeToolbar();
					$('#real-accessability-btn').trigger('focus');
				}
			});
		};

		var showLoader = function(callback) {
			$('a#real-accessability-btn i.real-accessability-icon').css('display', 'none');
			$('a#real-accessability-btn i.real-accessability-loading').show();
			setTimeout(function() {
				callback();
				$('a#real-accessability-btn i.real-accessability-loading').hide();
				$('a#real-accessability-btn i.real-accessability-icon').show();
			}, 300);
		};

		// Hide toolbar when scrolling
		var hideOnScroll = function() {
			if(settings.hideOnScroll) {
				$(window).on('scroll', function() {
					if($('#real-accessability').hasClass('open')) {
						closeToolbar();
					}
				});
			}
		};

		// Save object in cookie named 'real-accessability' when user redirect page
		var saveOnRedirect = function() {
			$(window).on('beforeunload', function() {
				document.cookie = 'real-accessability=' + encodeURIComponent(JSON.stringify(obj)) + '; path=/; SameSite=Lax';
			});
		};

		// Add role="link" to links that have no role of their own
		var addLinkRole = function() {
			$('a[href]').not('[role]').not(settings.exclude).attr('role', 'link');
		};

		var removeLinkTarget = function() {
			$('a[target]').not(settings.exclude).removeAttr('target');
		};

		// Page reader - uses the browser's built-in speech synthesis, nothing is sent to a third party
		var pageReader = function() {
			var synth = window.speechSynthesis;
			var $player = $('#real-accessability-player');
			var $play = $('#real-accessability-play');
			var $pause = $('#real-accessability-pause');
			var $stop = $('#real-accessability-stop');
			var lang = (document.documentElement.lang || navigator.language || 'en').toLowerCase();
			var langPrefix = lang.split('-')[0];
			var voice = null;
			var status = 'stopped';
			var chunks = [];
			var current = 0;
			var utterance = null;
			var run = 0;

			if(!synth || typeof window.SpeechSynthesisUtterance === 'undefined' || !$player.length) {
				return;
			}

			var findVoice = function() {
				var voices = synth.getVoices();
				var prefixes = langPrefix === 'he' ? ['he', 'iw'] : [langPrefix];
				var exact = null, partial = null;

				for(var i=0; i<voices.length; i++) {
					var voiceLang = (voices[i].lang || '').replace('_', '-').toLowerCase();
					if(voiceLang === lang && !exact) {
						exact = voices[i];
					}
					if($.inArray(voiceLang.split('-')[0], prefixes) !== -1 && !partial) {
						partial = voices[i];
					}
				}

				return exact || partial;
			};

			var showPlayerIfSupported = function() {
				voice = findVoice();
				if(voice) {
					$player.prop('hidden', false);
				}
			};

			// Text of the main content area, without the toolbar and the admin bar
			var getPageText = function() {
				var root = document.querySelector('main, [role="main"]') || document.body;
				var parts = [];

				$(root).children().not('#real-accessability, #wpadminbar, script, style, noscript, template').each(function() {
					if(this.innerText) {
						parts.push(this.innerText);
					}
				});

				return parts.join('\n');
			};

			// Split long text into short pieces, browsers cut off long utterances
			var splitText = function(text) {
				var result = [];
				var paragraphs = text.split(/\n+/);

				for(var i=0; i<paragraphs.length; i++) {
					var sentences = $.trim(paragraphs[i]).match(/[^.!?]+[.!?]*/g) || [];

					for(var j=0; j<sentences.length; j++) {
						var sentence = $.trim(sentences[j]);

						while(sentence.length > 200) {
							var cut = sentence.lastIndexOf(' ', 200);
							if(cut < 50) {
								cut = 200;
							}
							result.push(sentence.substring(0, cut));
							sentence = $.trim(sentence.substring(cut));
						}

						if(sentence.length) {
							result.push(sentence);
						}
					}
				}

				// Join short pieces (menus, post meta) so there are no long gaps between them
				var merged = [];
				for(var k=0; k<result.length; k++) {
					var last = merged.length - 1;
					if(last >= 0 && merged[last].length + result[k].length < 200) {
						merged[last] += ' ' + result[k];
					} else {
						merged.push(result[k]);
					}
				}

				return merged;
			};

			var setImage = function($btn, color) {
				var $img = $btn.find('img');
				$img.attr('src', $img.attr('src').replace(/(blue|gray|red)\.gif/, color + '.gif'));
			};

			var setStatus = function(newStatus) {
				status = newStatus;
				setImage($play, status === 'playing' ? 'gray' : 'blue');
				setImage($pause, status === 'playing' ? 'blue' : 'gray');
				setImage($stop, status === 'stopped' ? 'gray' : 'red');
			};

			var speakCurrent = function() {
				var thisRun = ++run;

				if(current >= chunks.length) {
					current = 0;
					setStatus('stopped');
					return;
				}

				utterance = new window.SpeechSynthesisUtterance(chunks[current]);
				utterance.voice = voice;
				utterance.lang = voice.lang;
				utterance.onend = function() {
					if(thisRun !== run || status !== 'playing') {
						return;
					}
					current++;
					speakCurrent();
				};
				utterance.onerror = utterance.onend;

				synth.speak(utterance);
			};

			var play = function() {
				if(status === 'playing') {
					return;
				}

				if(status === 'stopped') {
					chunks = splitText(getPageText());
					current = 0;
				}

				synth.cancel();
				setStatus('playing');
				speakCurrent();
			};

			// Pause by cancelling and remembering the position - native pause() is unreliable on mobile
			var pause = function() {
				if(status !== 'playing') {
					return;
				}
				setStatus('paused');
				run++;
				synth.cancel();
			};

			var stop = function() {
				if(status === 'stopped') {
					return;
				}
				setStatus('stopped');
				run++;
				current = 0;
				synth.cancel();
			};

			$play.on('click', play);
			$pause.on('click', pause);
			$stop.on('click', stop);

			$(window).on('beforeunload', function() {
				synth.cancel();
			});

			showPlayerIfSupported();

			if(!voice && 'onvoiceschanged' in synth) {
				synth.addEventListener('voiceschanged', showPlayerIfSupported);
			}
		};

		init();
		disableClicking();
		hideOnScroll();
		saveOnRedirect();
		addLinkRole();
		removeLinkTarget();
		pageReader();

	};

})(jQuery);
