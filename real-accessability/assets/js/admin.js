/*
 * Real Accessibility - admin screens.
 */

(function ($) {
	'use strict';

	var A = window.realAccessibilityAdmin || {};

	/* Colors, the same way the widget derives them */

	function validHex(value) {
		return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value || '');
	}

	function fullHex(value) {
		var x = String(value).replace('#', '');
		return '#' + (x.length === 3 ? x.split('').map(function (c) { return c + c; }).join('') : x).toLowerCase();
	}

	function hex(color) {
		var x = fullHex(color).slice(1);
		return [0, 2, 4].map(function (i) { return parseInt(x.slice(i, i + 2), 16) || 0; });
	}

	function mix(a, b, weight) {
		var A1 = hex(a), B1 = hex(b);
		return '#' + A1.map(function (v, i) {
			return ('0' + Math.round(v * (1 - weight) + B1[i] * weight).toString(16)).slice(-2);
		}).join('');
	}

	function rgba(color, alpha) {
		var c = hex(color);
		return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + alpha + ')';
	}

	function luminance(color) {
		var c = hex(color).map(function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}

	function contrast(a, b) {
		var x = luminance(a), y = luminance(b);
		return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
	}

	function format(text) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(text || '').replace(/%(\d)\$s|%s/g, function (match, n) {
			return n ? args[n - 1] : args[i++];
		}).replace('%%', '%');
	}

	/* ---------- Button and menu ---------- */

	// Size of the screen each preview stands for, in CSS pixels
	var SCREENS = { desktop: [1440, 900], tablet: [820, 1180], mobile: [390, 844] };

	function initButtonTab($root) {
		var $form = $root.closest('form');
		var dirty = false;
		var submitting = false;
		var lastRounded = {};
		var lastDrawer = '';

		// Slide the preview menu in from the side it opens from, like on the site
		function slideIn() {
			var $drawer = $root.find('.ra-pv-drawer');
			if ($root.find('.ra-pv').attr('data-mode') !== 'open') {
				$drawer.removeClass('is-open');
				return;
			}
			$drawer.css('transition', 'none').removeClass('is-open');
			void $drawer[0].offsetWidth;
			$drawer.css('transition', '');
			requestAnimationFrame(function () {
				$drawer.addClass('is-open');
			});
		}

		function brand() {
			var value = $('#ra-color').val();
			return validHex(value) ? fullHex(value) : '#003cff';
		}

		function autoIcon() {
			return contrast(brand(), '#ffffff') >= 3 ? '#ffffff' : '#111111';
		}

		function iconColor() {
			if ($root.find('input[name$="[icon_mode]"]:checked').val() === 'custom' && validHex($('#ra-icon-color').val())) {
				return fullHex($('#ra-icon-color').val());
			}
			return autoIcon();
		}

		function activeDevice() {
			return $root.find('.ra-devtab.is-active').data('device') || 'desktop';
		}

		function panel(device) {
			return $root.find('.ra-device-panel[data-device="' + device + '"]');
		}

		function setDirty() {
			if (!dirty) {
				dirty = true;
				$root.find('.ra-dirty').prop('hidden', false);
			}
		}

		/* Color card */
		function updateColor() {
			var color = brand();
			var custom = $root.find('input[name$="[icon_mode]"]:checked').val() === 'custom';
			$root.find('[data-derived="light"]').css('background', mix(color, '#ffffff', 0.22));
			$root.find('[data-derived="deep"]').css('background', mix(color, '#000000', 0.22));
			$root.find('[data-derived="tint"]').css('background', mix(color, '#ffffff', 0.94));
			$root.find('.ra-icon-custom').prop('hidden', !custom);

			// Warn only when the chosen icon color is hard to see on the brand color
			var ratio = contrast(color, iconColor());
			var $badge = $root.find('.ra-contrast');
			$badge.prop('hidden', ratio >= 3);
			if (ratio < 3) {
				// The ratio in the admin language's number format (1.8 / 1,8)
				var shown = (Math.round(ratio * 10) / 10).toFixed(1);
				try {
					shown = (Math.round(ratio * 10) / 10).toLocaleString(document.documentElement.lang || undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1, numberingSystem: 'latn' });
				} catch (e) {}
				$badge.text('⚠ ' + format(A.low, shown));
			}
		}

		/* Shape cards follow the radius slider */
		function updateShape($panel) {
			var $size = $panel.find('.ra-size');
			var $radius = $panel.find('.ra-radius');
			var size = parseInt($size.val(), 10) || 52;
			var max = Math.floor(size / 2);
			var device = $panel.data('device');

			// A circle stays a circle when the size changes
			var previousMax = parseInt($radius.attr('max'), 10);
			var wasCircle = parseInt($radius.val(), 10) >= previousMax;

			$radius.attr('max', max);
			if (wasCircle || parseInt($radius.val(), 10) > max) {
				$radius.val(max);
			}

			var radius = parseInt($radius.val(), 10) || 0;
			var shape = radius === 0 ? 'square' : (radius >= max ? 'circle' : 'rounded');
			if (shape === 'rounded') {
				lastRounded[device] = radius;
			}
			$panel.find('.ra-shape').each(function () {
				$(this).attr('aria-pressed', String($(this).data('shape') === shape));
			});
		}

		/* Range fill and value */
		function updateSlider($input) {
			var min = parseFloat($input.attr('min')) || 0;
			var max = parseFloat($input.attr('max')) || 100;
			var value = parseFloat($input.val()) || 0;
			var percent = max > min ? ((value - min) / (max - min)) * 100 : 0;
			$input[0].style.setProperty('--p', percent + '%');
			$input.closest('.ra-slider-field').find('output').text(value + 'px');
		}

		/* Visibility on each device */
		function updateVisibility() {
			$root.find('.ra-show').each(function () {
				var device = $(this).data('device');
				var on = $(this).val() === '1';
				$root.find('.ra-vis-btn[data-device="' + device + '"]').attr('aria-pressed', String(on));
				$root.find('#ra-tab-' + device + ' .ra-chip').prop('hidden', on);
				panel(device).find('.ra-off-note').prop('hidden', on);
			});
		}

		/* Live preview of the active device */
		function updatePreview() {
			var device = activeDevice();
			var $panel = panel(device);
			var $pv = $root.find('.ra-pv');
			var $site = $pv.find('.ra-pv-site');
			var screen = SCREENS[device];

			$pv.attr('data-device', device);

			var scale = $site.innerWidth() / screen[0];
			var anchor = String($panel.find('.ra-anchors input:checked').val() || 'bottom-right').split('-');
			var v = anchor[0], h = anchor[1];
			var x = parseInt($panel.find('input[name$="[offset_x]"]').val(), 10) || 0;
			var y = parseInt($panel.find('input[name$="[offset_y]"]').val(), 10) || 0;
			var size = parseInt($panel.find('.ra-size').val(), 10) || 52;
			var radius = parseInt($panel.find('.ra-radius').val(), 10) || 0;
			var side = $panel.find('input[name$="[drawer]"]:checked').val() || 'left';
			var dradius = parseInt($panel.find('.ra-dradius').val(), 10) || 0;
			var show = $root.find('#ra-show-' + device).val() === '1';
			var color = brand();
			var flat = $('#ra-flat').is(':checked');
			var gradient = 'radial-gradient(90% 150% at 100% 0%, rgba(255,255,255,.16) 0%, rgba(255,255,255,0) 55%), linear-gradient(225deg, ' + mix(color, '#ffffff', 0.22) + ' 0%, ' + color + ' 50%, ' + mix(color, '#000000', 0.22) + ' 100%)';

			var s = Math.max(8, size * scale), r = Math.min(radius, size / 2) * scale;
			var css = {
				width: s, height: s, top: 'auto', bottom: 'auto', left: 'auto', right: 'auto',
				translate: (h === 'center' ? '-50%' : '0') + ' ' + (v === 'middle' ? '-50%' : '0'),
				borderRadius: r + 'px', background: flat ? color : gradient, color: iconColor(),
				boxShadow: flat ? 'none' : '0 ' + Math.round(6 * scale + 2) + 'px ' + Math.round(14 * scale + 4) + 'px -4px ' + rgba(color, 0.7)
			};

			if (v === 'top') { css.top = y * scale; } else if (v === 'bottom') { css.bottom = y * scale; } else { css.top = '50%'; }
			if (h === 'left') { css.left = x * scale; } else if (h === 'right') { css.right = x * scale; } else { css.left = '50%'; }

			if (h === 'right' && x === 0) {
				css.borderRadius = r + 'px 0 0 ' + r + 'px';
			} else if (h === 'left' && x === 0) {
				css.borderRadius = '0 ' + r + 'px ' + r + 'px 0';
			} else if (h === 'center' && v === 'bottom' && y === 0) {
				css.borderRadius = r + 'px ' + r + 'px 0 0';
			} else if (h === 'center' && v === 'top' && y === 0) {
				css.borderRadius = '0 0 ' + r + 'px ' + r + 'px';
			}

			$pv.find('.ra-pv-fab').css(css).prop('hidden', !show);
			$pv.find('.ra-pv-off').prop('hidden', show);

			var dr = dradius * scale;
			var drawer = { left: 'auto', right: 'auto', top: 'auto', bottom: 'auto', width: 'auto', height: 'auto', borderRadius: 0, marginInline: 0 };
			if (side === 'bottom') {
				drawer.left = 0;
				drawer.right = 0;
				drawer.bottom = 0;
				drawer.marginInline = 'auto';
				drawer.width = Math.min(screen[0], 640) * scale;
				drawer.height = '72%';
				drawer.borderRadius = dr + 'px ' + dr + 'px 0 0';
			} else {
				drawer[side] = 0;
				drawer.top = 0;
				drawer.bottom = 0;
				drawer.width = Math.min(424, screen[0]) * scale;
				drawer.borderRadius = side === 'left' ? '0 ' + dr + 'px ' + dr + 'px 0' : dr + 'px 0 0 ' + dr + 'px';
			}
			$pv.find('.ra-pv-drawer').css(drawer).attr('data-side', side);
			if (device + '|' + side !== lastDrawer) {
				lastDrawer = device + '|' + side;
				slideIn();
			}
			$pv.find('.ra-pv-dhead').css({ background: gradient, color: autoIcon() });
			$pv.find('.ra-pv-prof .on').css('box-shadow', '0 0 0 1px ' + color);
			$pv.find('.ra-pv-tiles .on').css('background', color);

			var anchorLabel = $panel.find('.ra-anchors input:checked').data('label') || '';
			$panel.find('.ra-anchor-name').text(anchorLabel);

		}

		function updatePanel($panel) {
			updateShape($panel);
			$panel.find('.ra-slider').each(function () { updateSlider($(this)); });
		}

		function selectDevice(device, focus) {
			var $tab = $root.find('.ra-devtab[data-device="' + device + '"]');
			$root.find('.ra-devtab').removeClass('is-active').attr({ 'aria-selected': 'false', tabindex: '-1' });
			$tab.addClass('is-active').attr('aria-selected', 'true').removeAttr('tabindex');
			$root.find('.ra-device-panel').prop('hidden', true);
			panel(device).prop('hidden', false);
			if (focus) {
				$tab.trigger('focus');
			}
			updatePreview();
		}

		/* Events */

		$root.on('click', '.ra-devtab', function () {
			selectDevice($(this).data('device'));
		});

		$root.on('keydown', '.ra-devtab', function (e) {
			var $tabs = $root.find('.ra-devtab');
			var index = $tabs.index(this);
			var rtl = $('body').hasClass('rtl');
			var move = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, Home: -index, End: $tabs.length - 1 - index }[e.key];
			if (move !== undefined) {
				var next = $tabs.eq((index + move + $tabs.length) % $tabs.length).data('device');
				selectDevice(next, true);
				e.preventDefault();
			}
		});

		$root.on('click', '.ra-vis-btn', function () {
			var device = $(this).data('device');
			var $input = $root.find('#ra-show-' + device);
			$input.val($input.val() === '1' ? '0' : '1');
			updateVisibility();
			updatePreview();
			setDirty();
		});

		$root.on('click', '.ra-shape', function () {
			var $panel = $(this).closest('.ra-device-panel');
			var $radius = $panel.find('.ra-radius');
			var max = parseInt($radius.attr('max'), 10) || 26;
			var shape = $(this).data('shape');
			var device = $panel.data('device');
			// "Rounded" goes back to the last rounded radius, or about a third of the size
			var rounded = lastRounded[device] || Math.round((parseInt($panel.find('.ra-size').val(), 10) || 52) * 0.3);
			var value = shape === 'square' ? 0 : (shape === 'circle' ? max : Math.min(max - 1, Math.max(1, rounded)));
			$radius.val(value).trigger('input');
		});

		function setPreviewMode(mode) {
			if ($root.find('.ra-pv').attr('data-mode') === mode) {
				return;
			}
			$root.find('.ra-preview-mode button').each(function () {
				$(this).attr('aria-pressed', String($(this).data('mode') === mode));
			});
			$root.find('.ra-pv').attr('data-mode', mode);
			slideIn();
		}

		$root.on('click', '.ra-preview-mode button', function () {
			setPreviewMode($(this).data('mode'));
		});

		/* Tabs inside the box - the same tab stays open when switching devices */
		function selectSection(name, focus) {
			$root.find('.ra-subtab').each(function () {
				var on = $(this).data('section') === name;
				$(this).toggleClass('is-active', on).attr('aria-selected', String(on));
				if (on) {
					$(this).removeAttr('tabindex');
				} else {
					$(this).attr('tabindex', '-1');
				}
			});
			$root.find('.ra-group[data-section]').each(function () {
				$(this).prop('hidden', $(this).data('section') !== name);
			});
			// The preview shows what the tab is about
			setPreviewMode(name === 'menu' ? 'open' : 'button');
			if (focus) {
				panel(activeDevice()).find('.ra-subtab[data-section="' + name + '"]').trigger('focus');
			}
		}

		$root.on('click', '.ra-subtab', function () {
			selectSection($(this).data('section'));
		});

		$root.on('keydown', '.ra-subtab', function (e) {
			var $tabs = $(this).closest('.ra-subtabs').find('.ra-subtab');
			var index = $tabs.index(this);
			var rtl = $('body').hasClass('rtl');
			var move = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, Home: -index, End: $tabs.length - 1 - index }[e.key];
			if (move !== undefined) {
				selectSection($tabs.eq((index + move + $tabs.length) % $tabs.length).data('section'), true);
				e.preventDefault();
			}
		});

		// Color: the text field and the native picker stay in sync
		$root.on('input change', '.ra-swatch', function () {
			$('#' + $(this).data('for')).val(this.value.toUpperCase()).trigger('input');
		});

		$root.on('input change', '.ra-hex', function () {
			if (validHex(this.value)) {
				$root.find('.ra-swatch[data-for="' + this.id + '"]').val(fullHex(this.value));
			}
			updateColor();
			updatePreview();
		});

		$root.on('input change', '.ra-device-panel input', function () {
			updatePanel($(this).closest('.ra-device-panel'));
			updatePreview();
		});

		$root.on('change', 'input[name$="[icon_mode]"]', function () {
			updateColor();
			updatePreview();
		});

		$root.on('change', '#ra-flat', updatePreview);

		$form.on('input change', function () { setDirty(); });
		$form.on('submit', function () { submitting = true; });
		$(window).on('beforeunload', function (e) {
			if (dirty && !submitting) {
				e.preventDefault();
				return A.leaveText;
			}
		});
		$(window).on('resize', updatePreview);

		// Remember the rounded radius of each device for the "Rounded" card
		$root.find('.ra-device-panel').each(function () {
			var $radius = $(this).find('.ra-radius');
			var radius = parseInt($radius.val(), 10) || 0;
			lastRounded[$(this).data('device')] = radius > 0 && radius < (parseInt($radius.attr('max'), 10) || 26) ? radius : 0;
			updatePanel($(this));
		});
		updateColor();
		updateVisibility();
		updatePreview();
	}

	/* ---------- Languages ---------- */

	function initLanguagesTab($root) {
		var $form = $root.closest('form');
		var $list = $root.find('.ra-lang-list');
		var dirty = false;
		var submitting = false;
		var color = validHex($root.data('color')) ? fullHex($root.data('color')) : '#003cff';
		var ink = contrast(color, '#ffffff') >= 3 ? '#ffffff' : '#111111';

		// Keep the field names in row order so the saved order follows the list
		function renumber() {
			$list.children('.ra-lang-row').each(function (index) {
				$(this).find('[data-field]').each(function () {
					this.name = this.name.replace(/\[languages\]\[\d+\]/, '[languages][' + index + ']');
				});
			});
		}

		function setDirty() {
			if (!dirty) {
				dirty = true;
				$root.find('.ra-dirty').prop('hidden', false);
			}
		}

		function esc(text) {
			return String(text).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		// The language picker as it looks in the menu
		function updatePreview() {
			var $active = $list.children('.ra-lang-row').filter(function () {
				return $(this).find('.ra-toggle').is(':checked');
			});
			var $default = $list.find('input[type="radio"]:checked').closest('.ra-lang-row');
			var $current = $default.find('.ra-toggle').is(':checked') ? $default : $active.first();

			$list.children('.ra-lang-row').each(function () {
				$(this).toggleClass('is-off', !$(this).find('.ra-toggle').is(':checked'));
			});

			var count = $active.length;
			var forms = A.langCount || {};
			$root.find('.ra-lang-count').text(String(forms[count] || forms[forms.length - 1] || count));

			var $pv = $root.find('.ra-langpv');
			$pv.find('.ra-langpv-head').css({
				background: 'radial-gradient(90% 150% at 100% 0%, rgba(255,255,255,.16) 0%, rgba(255,255,255,0) 55%), linear-gradient(225deg, ' + mix(color, '#ffffff', 0.22) + ' 0%, ' + color + ' 50%, ' + mix(color, '#000000', 0.22) + ' 100%)',
				color: ink
			});
			$pv.find('.ra-langpv-pill').prop('hidden', count < 2).find('img').attr('src', $current.find('.ra-flag-pick img').attr('src') || '');
			$pv.find('.ra-langpv-code').text($current.data('code') || '');

			var html = '';
			$active.each(function () {
				var $row = $(this);
				var selected = $row.is($current);
				html += '<div class="ra-langpv-opt' + (selected ? ' is-selected' : '') + '">' +
					'<img src="' + esc($row.find('.ra-flag-pick img').attr('src')) + '" alt="" width="22" height="22">' +
					'<span><bdi>' + esc($row.data('name')) + '</bdi></span>' +
					(selected ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="' + color + '" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>' : '') +
					'</div>';
			});
			$pv.find('.ra-langpv-menu').html(html).prop('hidden', count < 2);
		}

		if ($.fn.sortable) {
			$list.sortable({
				handle: '.ra-grip',
				axis: 'y',
				placeholder: 'ra-lang-placeholder',
				forcePlaceholderSize: true,
				update: function () {
					renumber();
					updatePreview();
					setDirty();
				}
			});
		}

		initFlagPicker($root);

		// Search the list: native name, the name in the admin language, English name or locale code
		var languageNames = [];
		[document.documentElement.lang || 'en', 'en'].forEach(function (locale) {
			try {
				languageNames.push(new Intl.DisplayNames([locale], { type: 'language' }));
			} catch (e) {}
		});
		$list.children('.ra-lang-row').each(function () {
			var $row = $(this);
			var locale = String($row.find('input[data-field="locale"]').val() || '');
			var words = [$row.data('name'), locale];
			languageNames.forEach(function (names) {
				try {
					words.push(names.of(locale.replace(/_formal$/, '').replace(/_/g, '-')));
				} catch (e) {}
			});
			$row.attr('data-search', words.join(' ').toLowerCase());
		});
		$root.on('input', '.ra-lang-filter', function () {
			var query = $.trim(this.value).toLowerCase();
			var shown = 0;
			$list.children('.ra-lang-row').each(function () {
				var match = !query || String($(this).attr('data-search')).indexOf(query) !== -1;
				$(this).toggle(match);
				shown += match ? 1 : 0;
			});
			$root.find('.ra-lang-noresults').prop('hidden', shown > 0);
		});

		$list.on('change', 'select[data-field="flag"]', function () {
			$(this).closest('.ra-flag-pick').find('img').attr('src', A.flagsUrl + this.value + '.svg');
			updatePreview();
		});

		$list.on('change', '.ra-toggle, input[type="radio"]', updatePreview);

		$form.on('input change', function (e) {
			// Searching the list changes nothing that is saved
			if (!$(e.target).closest('.ra-lang-search').length) {
				setDirty();
			}
		});
		$form.on('submit', function () {
			submitting = true;
			renumber();
		});
		$(window).on('beforeunload', function (e) {
			if (dirty && !submitting) {
				e.preventDefault();
				return A.leaveText;
			}
		});

		updatePreview();
	}

	/* ---------- Languages: the flag picker ---------- */

	// Countries most associated with each language, offered before the full list
	var FLAG_SUGGEST = {
		en: ['us', 'gb', 'ca', 'au', 'nz', 'ie', 'za', 'in'],
		ar: ['ae', 'sa', 'eg', 'jo', 'ma', 'iq', 'lb', 'qa', 'kw', 'bh', 'om', 'dz', 'tn', 'ps'],
		he: ['il'],
		ru: ['ru', 'by', 'kz', 'kg'],
		uk: ['ua'],
		fr: ['fr', 'be', 'ca', 'ch', 'lu', 'mc', 'sn', 'ci'],
		de: ['de', 'at', 'ch', 'lu', 'li'],
		es: ['es', 'mx', 'ar', 'co', 'cl', 'pe', 've', 'us'],
		it: ['it', 'ch', 'sm'],
		pt: ['pt', 'br', 'ao', 'mz'],
		nl: ['nl', 'be', 'sr'],
		zh: ['cn', 'tw', 'hk', 'sg'],
		fa: ['ir', 'af'],
		ur: ['pk', 'in'],
		el: ['gr', 'cy'],
		tr: ['tr', 'cy'],
		ro: ['ro', 'md'],
		sv: ['se', 'fi'],
		sr: ['rs', 'ba', 'me'],
		hr: ['hr', 'ba'],
		ms: ['my', 'sg', 'bn'],
		bn: ['bd', 'in'],
		ta: ['in', 'lk', 'sg'],
		sw: ['ke', 'tz', 'ug']
	};

	function initFlagPicker($root) {
		var T = A.flag || {};
		var locale = document.documentElement.lang || 'en';
		var names = null;
		var collator = null;
		try {
			names = new Intl.DisplayNames([locale, 'en'], { type: 'region' });
			collator = new Intl.Collator(locale);
		} catch (e) {}

		function esc(text) {
			return String(text).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		function fold(text) {
			return String(text).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
		}

		// Country names in the admin language from the browser, the English list as a fallback
		var countries = [];
		var byCode = {};
		$root.find('select[data-field="flag"]').first().find('option').each(function () {
			var code = this.value;
			var english = this.textContent;
			var name = english;
			if (names) {
				try {
					name = names.of(code.toUpperCase()) || english;
				} catch (e) {}
			}
			var country = { code: code, name: name, words: [fold(name), fold(english), code] };
			countries.push(country);
			byCode[code] = country;
		});
		countries.sort(function (a, b) {
			return collator ? collator.compare(a.name, b.name) : (a.name < b.name ? -1 : 1);
		});

		var CHECK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>';
		var SEARCH = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';

		var $pop = $('<div class="ra-flagpop" role="dialog" hidden></div>').attr('aria-label', T.choose || '');
		$pop.html('<div class="ra-flagpop-search">' + SEARCH +
			'<input type="text" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="ra-flagpop-list" autocomplete="off" spellcheck="false"></div>' +
			'<ul class="ra-flagpop-list" id="ra-flagpop-list" role="listbox"></ul>');
		$root.append($pop);

		var $input = $pop.find('input').attr({ placeholder: T.search || '', 'aria-label': T.search || '' });
		var $listbox = $pop.find('.ra-flagpop-list').attr('aria-label', T.choose || '');
		var $btn = null;
		var $sel = null;
		var active = -1;

		function label($button) {
			var country = byCode[$button.siblings('select').val()];
			var text = String(T.label || '%s').replace('%s', $button.closest('.ra-lang-row').data('name')) + (country ? ': ' + country.name : '');
			$button.attr({ 'aria-label': text, title: text });
		}

		function suggestions($row) {
			var parts = String($row.find('input[data-field="locale"]').val() || '').toLowerCase().split('_');
			var codes = [$sel.val(), $row.find('.ra-flag-pick').attr('data-default-flag'), parts[1]].concat(FLAG_SUGGEST[parts[0]] || []);
			var seen = {};
			return codes.filter(function (code) {
				if (!code || seen[code] || !byCode[code]) {
					return false;
				}
				seen[code] = true;
				return true;
			});
		}

		function option(country, prefix) {
			var selected = country.code === $sel.val();
			return '<li role="option" class="ra-flagpop-opt" id="ra-flag-' + prefix + country.code + '" data-code="' + country.code + '" aria-selected="' + selected + '">' +
				'<img src="' + esc(A.flagsUrl + country.code + '.svg') + '" alt="" width="22" height="22" loading="lazy" decoding="async">' +
				'<span>' + esc(country.name) + '</span>' + (selected ? CHECK : '') + '</li>';
		}

		function options() {
			return $listbox.children('.ra-flagpop-opt');
		}

		function setActive(index) {
			var $opts = options().removeClass('is-active');
			if (index < 0 || !$opts.length) {
				active = -1;
				$input.removeAttr('aria-activedescendant');
				return;
			}
			active = Math.max(0, Math.min(index, $opts.length - 1));
			var el = $opts.eq(active).addClass('is-active')[0];
			var box = $listbox[0];
			$input.attr('aria-activedescendant', el.id);
			if (el.offsetTop < box.scrollTop) {
				box.scrollTop = el.offsetTop - 6;
			} else if (el.offsetTop + el.offsetHeight > box.scrollTop + box.clientHeight) {
				box.scrollTop = el.offsetTop + el.offsetHeight - box.clientHeight + 6;
			}
		}

		function render() {
			var query = fold($.trim($input.val()));
			var html = '';

			if (query) {
				// Names that start with the search first, then a word that starts with it, then the rest
				var ranked = [[], [], []];
				countries.forEach(function (country) {
					var rank = 3;
					country.words.forEach(function (text) {
						var at = text.indexOf(query);
						if (at === 0) {
							rank = 0;
						} else if (at > 0) {
							rank = Math.min(rank, /[\s(-]/.test(text.charAt(at - 1)) ? 1 : 2);
						}
					});
					if (rank < 3) {
						ranked[rank].push(country);
					}
				});
				ranked[0].concat(ranked[1], ranked[2]).forEach(function (country) {
					html += option(country, 'a-');
				});
				html = html || '<li class="ra-flagpop-empty" role="presentation">' + esc(T.none || '') + '</li>';
			} else {
				var suggested = suggestions($btn.closest('.ra-lang-row'));
				html += '<li class="ra-flagpop-group" role="presentation">' + esc(T.suggested || '') + '</li>';
				suggested.forEach(function (code) {
					html += option(byCode[code], 's-');
				});
				html += '<li class="ra-flagpop-group" role="presentation">' + esc(T.all || '') + '</li>';
				countries.forEach(function (country) {
					html += option(country, 'a-');
				});
			}

			$listbox.html(html).scrollTop(0);
			setActive(query ? 0 : options().index(options().filter('[aria-selected="true"]').first()));
		}

		// Under the button, or above it when there is no room below
		function place() {
			if (!$btn) {
				return;
			}
			var r = $btn[0].getBoundingClientRect();
			var pop = $pop[0];
			var width = pop.offsetWidth;
			var height = pop.offsetHeight;
			var left = $('body').hasClass('rtl') ? r.right - width : r.left;
			var top = r.bottom + 6;
			if (top + height > window.innerHeight - 8 && r.top - height - 6 > 8) {
				top = r.top - height - 6;
			}
			pop.style.left = Math.max(8, Math.min(left, window.innerWidth - width - 8)) + 'px';
			pop.style.top = top + 'px';
		}

		function close(focusButton) {
			if (!$btn) {
				return;
			}
			var $button = $btn;
			$pop.prop('hidden', true);
			$button.attr('aria-expanded', 'false');
			$btn = $sel = null;
			$(window).off('.raflag');
			if (focusButton) {
				$button.trigger('focus');
			}
		}

		function open($button) {
			close(false);
			$btn = $button;
			$sel = $button.siblings('select');
			$btn.attr('aria-expanded', 'true');
			$input.val('');
			$pop.prop('hidden', false);
			render();
			place();
			$input.trigger('focus');
			$(window).on('scroll.raflag resize.raflag', place);
		}

		function pick(code) {
			var $button = $btn;
			var $select = $sel;
			close(true);
			if (code && $select.val() !== code) {
				$select.val(code).trigger('change');
			}
			label($button);
		}

		$root.find('.ra-flag-btn').each(function () {
			label($(this));
		});

		$root.on('click', '.ra-flag-btn', function () {
			if ($btn && $btn[0] === this) {
				close(true);
			} else {
				open($(this));
			}
		});

		$input.on('input', render);

		$input.on('keydown', function (e) {
			switch (e.key) {
				case 'ArrowDown':
				case 'ArrowUp':
				case 'PageDown':
				case 'PageUp':
					e.preventDefault();
					var step = e.key === 'ArrowDown' ? 1 : (e.key === 'ArrowUp' ? -1 : (e.key === 'PageDown' ? 7 : -7));
					setActive(active < 0 ? 0 : active + step);
					break;
				case 'Enter':
					// Never submit the settings form from here
					e.preventDefault();
					if (active >= 0) {
						pick(options().eq(active).attr('data-code'));
					}
					break;
				case 'Escape':
					e.preventDefault();
					e.stopPropagation();
					close(true);
					break;
				case 'Tab':
					close(false);
					break;
			}
		});

		// Keep the focus in the search field while choosing with the mouse
		$listbox.on('mousedown', function (e) {
			e.preventDefault();
		});

		$listbox.on('mousemove', '.ra-flagpop-opt', function () {
			var index = options().index(this);
			if (index !== active) {
				options().removeClass('is-active');
				active = index;
				$(this).addClass('is-active');
				$input.attr('aria-activedescendant', this.id);
			}
		});

		$listbox.on('click', '.ra-flagpop-opt', function () {
			pick($(this).attr('data-code'));
		});

		$(document).on('mousedown', function (e) {
			if ($btn && !$pop[0].contains(e.target) && !$btn[0].contains(e.target)) {
				close(false);
			}
		});
	}

	/* ---------- Features: the real menu in a sandboxed frame ---------- */

	function initFeaturesPreview($ft) {
		var frame = $ft.find('.ra-ft-frame')[0];
		if (!frame) {
			return;
		}
		var base;
		try {
			base = JSON.parse(frame.getAttribute('data-config'));
		} catch (e) {
			return;
		}

		function checked(pattern) {
			var keys = [];
			$ft.find('input.ra-toggle').each(function () {
				var match = this.name.match(pattern);
				if (match && this.checked) {
					keys.push(match[1]);
				}
			});
			return keys;
		}

		// What the menu shows for the switches as they are now
		function current() {
			return {
				profiles: checked(/\[profiles\]\[(\w+)\]$/),
				features: checked(/\[features\]\[(\w+)\]$/),
				hideButton: $ft.find('input[name$="[hide_button]"]').is(':checked'),
				credit: $ft.find('input[name$="[credit]"]').is(':checked') ? base.creditInfo : false
			};
		}

		// The item a switch adds to the menu, so the preview can point at it
		function focusFor(input) {
			var match = input.name.match(/\[(profiles|features)\]\[(\w+)\]$/);
			if (match) {
				return { kind: match[1] === 'profiles' ? 'profile' : 'feature', key: match[2] };
			}
			match = input.name.match(/\[(hide_button|credit)\]$/);
			return match ? { kind: match[1] === 'credit' ? 'credit' : 'hide' } : null;
		}

		function send(focus) {
			if (frame.contentWindow) {
				// The frame has no origin of its own (sandboxed), so '*' is the only target
				frame.contentWindow.postMessage({ realAccessibilityPreview: $.extend(current(), { focus: focus }) }, '*');
			}
		}

		function json(value) {
			// Safe inside a <script> element
			return JSON.stringify(value).replace(/</g, '\\u003c');
		}

		var config = $.extend({}, base, current());
		frame.srcdoc = '<!doctype html><html dir="' + ($('body').hasClass('rtl') ? 'rtl' : 'ltr') + '"><head><meta charset="utf-8">' +
			'<style>html,body{margin:0;height:100%;background:#eef0f3}</style></head><body>' +
			'<script>window.realAccessibility=' + json(config) + ';<\/script>' +
			'<script src="' + frame.getAttribute('data-icons') + '"><\/script>' +
			'<script src="' + frame.getAttribute('data-widget') + '"><\/script>' +
			'</body></html>';

		// Switches changed before the frame finished loading
		frame.addEventListener('load', function () {
			send(null);
		});

		$ft.on('change', 'input.ra-toggle', function () {
			send(this.checked ? focusFor(this) : null);
		});
	}

	/* ---------- Accessibility audit request ---------- */

	function initAudit() {
		var $form = $('.ra-audit-form');
		if (!$form.length) {
			return;
		}

		// The privacy policy opens in a popup
		var privacy = document.getElementById('ra-privacy-dialog');
		if (privacy && privacy.showModal) {
			var opener = null;
			$form.on('click', '.ra-privacy-open', function () {
				opener = this;
				privacy.showModal();
			});
			$(privacy).on('click', '.ra-dialog-close, .ra-privacy-done', function () {
				privacy.close();
			});
			// A click on the dimmed backdrop closes it too
			$(privacy).on('click', function (e) {
				if (e.target === privacy) {
					privacy.close();
				}
			});
			$(privacy).on('close', function () {
				if (opener) {
					opener.focus();
				}
			});
		}
		var $error = $form.find('.ra-audit-error');
		var $submit = $form.find('.ra-audit-submit');
		var label = $submit.text();
		var T = A.audit || {};

		initPhoneCountry($form, T);

		function showError(message) {
			$error.text(message || A.auditError).prop('hidden', false);
		}

		/* Our own checks and messages - never the browser's bubbles */

		function fieldError($input, message) {
			var $field = $input.closest('.ra-audit-field');
			var $slot = $input.is('#ra-audit-consent') ? $form.find('.ra-consent-error') : $field.find('.ra-field-error');
			var id = ($input.attr('id') || 'ra-audit') + '-error';
			$slot.attr('id', id).text(message || '').prop('hidden', !message);
			$input.attr('aria-invalid', message ? 'true' : null);
			if (message) {
				$input.attr('aria-describedby', id);
			} else {
				$input.removeAttr('aria-describedby');
			}
			$field.toggleClass('has-error', !!message);
		}

		function check($input) {
			var value = $.trim($input.val() || '');
			var name = $input.attr('name');
			if (name === 'consent') {
				return $input.is(':checked') ? '' : T.consent;
			}
			if ($input.attr('aria-required') === 'true' && !value) {
				return T.required;
			}
			if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
				return T.email;
			}
			if (name === 'phone') {
				var digits = value.replace(/\D/g, '');
				if (!/^[0-9()\s.\-]+$/.test(value) || digits.replace(/^0/, '').length < 4 || digits.length > 15) {
					return T.phone;
				}
			}
			return '';
		}

		var $checked = $form.find('#ra-audit-name, #ra-audit-phone, #ra-audit-email, #ra-audit-consent');

		// A field shows its message after it is left or sent, and clears it as soon as it is fixed
		$checked.on('blur change', function () {
			if ($(this).is(':focus') && this.type !== 'checkbox') {
				return;
			}
			fieldError($(this), check($(this)));
		});
		$checked.on('input', function () {
			if ($(this).attr('aria-invalid') === 'true') {
				fieldError($(this), check($(this)));
			}
		});

		$form.on('submit', function (e) {
			e.preventDefault();
			$error.prop('hidden', true);

			var $first = null;
			$checked.each(function () {
				var message = check($(this));
				fieldError($(this), message);
				if (message && !$first) {
					$first = $(this);
				}
			});
			if ($first) {
				$first.trigger('focus');
				return;
			}

			// Sent only through admin-ajax
			$submit.prop('disabled', true).text($submit.data('sending'));
			$.post(window.ajaxurl, $form.serialize())
				.done(function (res) {
					if (res && res.success) {
						$form.prop('hidden', true);
						$('.ra-audit-done').prop('hidden', false).attr('tabindex', '-1').trigger('focus');
					} else {
						showError(res && res.data && res.data.message);
					}
				})
				.fail(function (xhr) {
					var res = xhr.responseJSON;
					showError(res && res.data && res.data.message);
				})
				.always(function () {
					$submit.prop('disabled', false).text(label);
				});
		});
	}

	/* ---------- Audit form: the phone country picker ---------- */

	function initPhoneCountry($form, T) {
		var $button = $form.find('.ra-phone-country');
		var $hidden = $form.find('input[name="phone_country"]');
		var $phone = $form.find('#ra-audit-phone');
		var dial = T.dial || {};
		if (!$button.length) {
			return;
		}
		var F = A.flag || {};
		var locale = document.documentElement.lang || 'en';
		var names = null;
		var english = null;
		var collator = null;
		try {
			names = new Intl.DisplayNames([locale, 'en'], { type: 'region' });
			english = new Intl.DisplayNames(['en'], { type: 'region' });
			collator = new Intl.Collator(locale);
		} catch (e) {}

		function esc(text) {
			return String(text).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		function fold(text) {
			return String(text).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
		}

		function nameIn(display, code) {
			try {
				return display ? display.of(code.toUpperCase()) || '' : '';
			} catch (e) {
				return '';
			}
		}

		// Shown in the admin language, found by that name or by the English one
		var countries = Object.keys(dial).map(function (code) {
			var name = nameIn(names, code) || code.toUpperCase();
			var both = fold(name + ' ' + nameIn(english, code));
			return { code: code, name: name, dial: dial[code], words: both.split(/[\s()\-]+/), folded: both };
		});
		countries.sort(function (a, b) {
			return collator ? collator.compare(a.name, b.name) : (a.name < b.name ? -1 : 1);
		});
		var byCode = {};
		countries.forEach(function (c) { byCode[c.code] = c; });

		var $pop = $('<div class="ra-flagpop ra-phonepop" role="dialog" hidden></div>').attr('aria-label', T.code || '');
		$pop.html('<div class="ra-flagpop-search"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>' +
			'<input type="text" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="ra-phonepop-list" autocomplete="off" spellcheck="false"></div>' +
			'<ul class="ra-flagpop-list" id="ra-phonepop-list" role="listbox"></ul>');
		$form.closest('.ra-audit').append($pop);
		var $input = $pop.find('input').attr({ placeholder: F.search || '', 'aria-label': F.search || '' });
		var $list = $pop.find('ul').attr('aria-label', T.code || '');
		var active = -1;
		var isOpen = false;

		function label() {
			var c = byCode[$hidden.val()];
			$button.attr('aria-label', (T.code || '') + ': ' + (c ? c.name + ' +' + c.dial : ''));
		}

		function options() {
			return $list.children('.ra-flagpop-opt');
		}

		function setActive(index) {
			var $opts = options().removeClass('is-active');
			if (index < 0 || !$opts.length) {
				active = -1;
				$input.removeAttr('aria-activedescendant');
				return;
			}
			active = Math.max(0, Math.min(index, $opts.length - 1));
			var el = $opts.eq(active).addClass('is-active')[0];
			var box = $list[0];
			$input.attr('aria-activedescendant', el.id);
			if (el.offsetTop < box.scrollTop) {
				box.scrollTop = el.offsetTop - 6;
			} else if (el.offsetTop + el.offsetHeight > box.scrollTop + box.clientHeight) {
				box.scrollTop = el.offsetTop + el.offsetHeight - box.clientHeight + 6;
			}
		}

		function render() {
			var query = fold($.trim($input.val()).replace(/^\+/, ''));
			var current = $hidden.val();
			var html = '';
			// Best first: the exact dial code or country code, then names and codes that start with the search, then the rest
			function rank(c) {
				if (!query) {
					return 0;
				}
				if (c.dial === query || c.code === query) {
					return 0;
				}
				if (c.folded.indexOf(query) === 0 || c.dial.indexOf(query) === 0) {
					return 1;
				}
				if (c.words.some(function (w) { return w.indexOf(query) === 0; })) {
					return 2;
				}
				return c.folded.indexOf(query) !== -1 || c.dial.indexOf(query) !== -1 ? 3 : 4;
			}
			var list = countries.map(function (c) { return { c: c, r: rank(c) }; }).filter(function (x) { return x.r < 4; });
			list.sort(function (a, b) { return a.r - b.r; });
			list.map(function (x) { return x.c; }).forEach(function (c) {
				var selected = c.code === current;
				html += '<li role="option" class="ra-flagpop-opt" id="ra-phone-' + c.code + '" data-code="' + c.code + '" aria-selected="' + selected + '">' +
					'<img src="' + esc(T.flags + c.code + '.svg') + '" alt="" width="22" height="22" loading="lazy" decoding="async">' +
					'<span>' + esc(c.name) + '</span><span class="ra-flagpop-dial" dir="ltr">+' + esc(c.dial) + '</span></li>';
			});
			$list.html(html || '<li class="ra-flagpop-empty" role="presentation">' + esc(F.none || '') + '</li>').scrollTop(0);
			setActive(query ? 0 : options().index(options().filter('[aria-selected="true"]')));
		}

		function place() {
			var r = $button[0].getBoundingClientRect();
			var pop = $pop[0];
			var top = r.bottom + 6;
			if (top + pop.offsetHeight > window.innerHeight - 8 && r.top - pop.offsetHeight - 6 > 8) {
				top = r.top - pop.offsetHeight - 6;
			}
			pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - pop.offsetWidth - 8)) + 'px';
			pop.style.top = top + 'px';
		}

		function close(focusButton) {
			if (!isOpen) {
				return;
			}
			isOpen = false;
			$pop.prop('hidden', true);
			$button.attr('aria-expanded', 'false');
			$(window).off('.raphone');
			if (focusButton) {
				$button.trigger('focus');
			}
		}

		function open() {
			isOpen = true;
			$button.attr('aria-expanded', 'true');
			$input.val('');
			$pop.prop('hidden', false);
			render();
			place();
			$input.trigger('focus');
			$(window).on('scroll.raphone resize.raphone', place);
		}

		function pick(code) {
			var c = byCode[code];
			if (!c) {
				return;
			}
			$hidden.val(code);
			$button.find('img').attr('src', T.flags + code + '.svg');
			$button.find('.ra-phone-dial').text('+' + c.dial);
			label();
			close(false);
			$phone.trigger('focus');
		}

		label();
		$button.on('click', function () {
			if (isOpen) {
				close(true);
			} else {
				open();
			}
		});
		$input.on('input', render);
		$input.on('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				setActive(active < 0 ? 0 : active + (e.key === 'ArrowDown' ? 1 : -1));
			} else if (e.key === 'Enter') {
				e.preventDefault();
				if (active >= 0) {
					pick(options().eq(active).attr('data-code'));
				}
			} else if (e.key === 'Escape') {
				e.preventDefault();
				e.stopPropagation();
				close(true);
			} else if (e.key === 'Tab') {
				close(false);
			}
		});
		$list.on('mousedown', function (e) {
			e.preventDefault();
		});
		$list.on('click', '.ra-flagpop-opt', function () {
			pick($(this).attr('data-code'));
		});
		$(document).on('mousedown', function (e) {
			if (isOpen && !$pop[0].contains(e.target) && !$button[0].contains(e.target)) {
				close(false);
			}
		});
	}

	/* ---------- Turning the menu on ---------- */

	function initActivation() {
		var dialog = document.getElementById('ra-activate-dialog');
		if (!dialog || !dialog.showModal) {
			return;
		}
		var opener = null;

		function open() {
			opener = document.activeElement;
			dialog.showModal();
		}

		$('.ra-activate-open').on('click', open);

		// Turning the menu on with the switch goes through the popup too
		$('.ra-power-switch').on('click', function (e) {
			if (this.hasAttribute('data-ask')) {
				e.preventDefault();
				open();
			}
		});

		// On this screen the button in the "menu is off" notice works like the switch
		$(document).on('click', '.ra-off-activate', function (e) {
			var $switch = $('.ra-power-switch');
			if (!$switch.length) {
				return;
			}
			e.preventDefault();
			$switch.trigger('click');
		});
		$(dialog).on('click', '.ra-dialog-close, .ra-dialog-cancel', function () { dialog.close(); });
		// A click on the dimmed backdrop closes it too
		$(dialog).on('click', function (e) {
			if (e.target === dialog) {
				dialog.close();
			}
		});
		$(dialog).on('close', function () {
			if (opener && opener.focus) {
				opener.focus();
			}
		});

		// Arriving from the button on the Plugins screen
		if (window.location.hash === '#ra-activate') {
			open();
		}
	}

	$(function () {
		initActivation();
		initAudit();

		var $bm = $('.ra-bm');
		if ($bm.length) {
			initButtonTab($bm);
		}

		var $lp = $('.ra-lp');
		if ($lp.length) {
			initLanguagesTab($lp);
		}

		// Features: the live preview and the unsaved-changes indicator
		var $ft = $('.ra-ft');
		if ($ft.length) {
			initFeaturesPreview($ft);

			// Dirty only while the switches differ from what is saved - trying one and back is not a change
			var ftDirty = false, ftSubmitting = false;
			var $ftForm = $ft.closest('form');
			var ftSaved = $ftForm.serialize();
			$ftForm.on('change', function () {
				ftDirty = $ftForm.serialize() !== ftSaved;
				$ft.find('.ra-dirty').prop('hidden', !ftDirty);
			});
			$ftForm.on('submit', function () { ftSubmitting = true; });
			$(window).on('beforeunload', function (e) {
				if (ftDirty && !ftSubmitting) {
					e.preventDefault();
					return A.leaveText;
				}
			});
		}
	});
})(jQuery);
