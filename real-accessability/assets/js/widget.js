/*
 * Real Accessibility - accessibility menu widget.
 * Author: REALMEDIA - https://realmedia.co.il
 * License: GPLv2 or later
 */

(function () {
	'use strict';

	var C = window.realAccessibility;

	if (!C || window.realAccessibilityLoaded || !document.body || !document.body.attachShadow) {
		return;
	}

	window.realAccessibilityLoaded = true;

	var doc = document;
	var html = doc.documentElement;
	var ICONS = window.realAccessibilityIcons || {};

	// Admin preview: always open, no floating button, nothing saved
	var PREVIEW = !!C.preview;

	var STORE = 'realAccessibility';
	var LANG_STORE = 'realAccessibilityLanguage';
	var HIDDEN_STORE = 'realAccessibilityHidden';

	var FEATURES = {};
	(C.features || []).forEach(function (key) { FEATURES[key] = true; });

	var TOGGLES = ['font', 'links', 'headings', 'cursor', 'noimg', 'mag', 'guide', 'motion', 'keys'];
	var LEVELS = ['contrast', 'align', 'size', 'lh', 'ls'];
	var SIZE_SCALE = [1, 1.1, 1.25, 1.5];
	var VALUES = {
		size: ['100%', '110%', '125%', '150%'],
		lh: ['', '1.8', '2.0', '2.2'],
		ls: ['', '+0.5px', '+1px', '+1.5px']
	};

	var PROFILE_SETS = {
		seizure: { on: { motion: true }, contrast: 3 },
		vision: { on: { headings: true, links: true, cursor: true }, contrast: 1, size: 2 },
		adhd: { on: { motion: true, guide: true }, lh: 1 },
		dys: { on: { font: true, guide: true }, size: 1, lh: 1, ls: 1 },
		motor: { on: { keys: true, cursor: true }, size: 1 },
		screen: { on: { keys: true, links: true, headings: true } },
		senior: { on: { cursor: true, links: true }, size: 2, lh: 1 }
	};

	// Icon and gradient colors of each profile
	var PROFILE_LOOK = {
		seizure: ['bolt', '#FDB022', '#F79009'],
		vision: ['eye', '#53B1FD', '#1570EF'],
		adhd: ['target', '#A48AFB', '#7A5AF8'],
		dys: ['book-open-cover', '#3CCB7F', '#099250'],
		motor: ['hand', '#FD853A', '#E04F16'],
		screen: ['computer-speaker', '#8098F9', '#444CE7'],
		senior: ['glasses', '#F670C7', '#DD2590']
	};

	var TILES = [
		['contrast', 'contrast'], ['font', 'text-size'], ['links', 'link'], ['headings', 'underline'],
		['cursor', 'cursor'], ['noimg', 'picture'], ['mag', 'zoom-in'], ['align', 'align-justify']
	];
	var STEPPERS = [['size', 'text-size'], ['lh', 'text-height'], ['ls', 'text-width']];
	var SWITCHES = [['guide', 'text'], ['motion', 'pause-circle'], ['keys', 'keyboard']];

	var CONTRAST_LABELS = ['contrast', 'contrastHigh', 'contrastInvert', 'contrastGray'];
	var ALIGN_LABELS = ['align', 'alignCenter', 'alignRight', 'alignLeft'];

	/* ---------- State ---------- */

	function blank() {
		return { profile: '', on: {}, contrast: 0, align: 0, size: 0, lh: 0, ls: 0 };
	}

	function clean(saved) {
		var s = blank();
		if (!saved || typeof saved !== 'object') {
			return s;
		}
		s.profile = PROFILE_SETS[saved.profile] && (C.profiles || []).indexOf(saved.profile) !== -1 ? saved.profile : '';
		TOGGLES.forEach(function (key) {
			if (saved.on && saved.on[key] === true && FEATURES[key]) {
				s.on[key] = true;
			}
		});
		LEVELS.forEach(function (key) {
			var value = parseInt(saved[key], 10);
			if (value >= 0 && value <= 3 && FEATURES[key]) {
				s[key] = value;
			}
		});
		return s;
	}

	function storage(type) {
		try {
			return window[type];
		} catch (e) {
			return null;
		}
	}

	function read(type, key) {
		var store = storage(type);
		try {
			return store ? store.getItem(key) : null;
		} catch (e) {
			return null;
		}
	}

	function write(type, key, value) {
		if (PREVIEW) {
			return;
		}
		var store = storage(type);
		try {
			if (store) {
				if (value === null) {
					store.removeItem(key);
				} else {
					store.setItem(key, value);
				}
			}
		} catch (e) {}
	}

	var state = (function () {
		if (PREVIEW) {
			return blank();
		}
		try {
			return clean(JSON.parse(read('localStorage', STORE) || 'null'));
		} catch (e) {
			return blank();
		}
	})();

	function activeCount() {
		var count = 0;
		TOGGLES.forEach(function (key) {
			if (state.on[key]) {
				count++;
			}
		});
		LEVELS.forEach(function (key) {
			if (state[key] > 0) {
				count++;
			}
		});
		return count;
	}

	function save() {
		write('localStorage', STORE, activeCount() || state.profile ? JSON.stringify(state) : null);
	}

	/* ---------- Colors (from the brand color, with automatic contrast) ---------- */

	function hex(color) {
		var x = String(color).replace('#', '');
		var full = x.length === 3 ? x.split('').map(function (c) { return c + c; }).join('') : x;
		return [0, 2, 4].map(function (i) { return parseInt(full.slice(i, i + 2), 16) || 0; });
	}

	function mix(a, b, weight) {
		var A = hex(a), B = hex(b);
		return '#' + A.map(function (v, i) {
			return ('0' + Math.round(v * (1 - weight) + B[i] * weight).toString(16)).slice(-2);
		}).join('');
	}

	function rgba(color, alpha) {
		var c = hex(color);
		return 'rgba(' + c[0] + ', ' + c[1] + ', ' + c[2] + ', ' + alpha + ')';
	}

	function luminance(color) {
		var c = hex(color).map(function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}

	function contrastRatio(a, b) {
		var x = luminance(a), y = luminance(b);
		return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
	}

	var accent = /^#[0-9a-f]{3,6}$/i.test(C.color || '') ? C.color : '#003CFF';
	var lightOnAccent = contrastRatio(accent, '#FFFFFF') >= 3;
	var ON = lightOnAccent ? '#FFFFFF' : '#111111';

	var COLOR_VARS = {
		'--a': accent,
		'--on': ON,
		'--ink': lightOnAccent ? accent : mix(accent, '#000000', 0.5),
		'--hl': mix(accent, '#FFFFFF', 0.22),
		'--hd': mix(accent, '#000000', 0.22),
		'--tint': mix(accent, '#FFFFFF', 0.94),
		'--a85': rgba(accent, 0.85),
		'--a70': rgba(accent, 0.7),
		'--on-soft': lightOnAccent ? 'rgba(255, 255, 255, 0.85)' : 'rgba(17, 17, 17, 0.72)',
		'--dot-off': lightOnAccent ? 'rgba(255, 255, 255, 0.5)' : 'rgba(17, 17, 17, 0.35)',
		'--fab-icon': /^#[0-9a-f]{3,6}$/i.test(C.iconColor || '') ? C.iconColor : ON
	};

	/* ---------- Languages ---------- */

	var languages = C.languages || [];

	function languageInfo(locale) {
		for (var i = 0; i < languages.length; i++) {
			if (languages[i].locale === locale) {
				return languages[i];
			}
		}
		return null;
	}

	var lang = (function () {
		var saved = read('localStorage', LANG_STORE);
		if (saved && languageInfo(saved) && C.i18n[saved]) {
			return saved;
		}
		return C.i18n[C.language] ? C.language : (languages[0] && languages[0].locale) || 'en_US';
	})();

	function t(key) {
		var dict = C.i18n[lang] || {};
		var fallback = C.i18n.en_US || {};
		return dict[key] !== undefined ? dict[key] : (fallback[key] !== undefined ? fallback[key] : key);
	}

	function statusText() {
		var count = activeCount();
		if (!count) {
			return t('noActive');
		}
		var list = t('active') || {};
		return list[count] || list[String(count)] || String(count);
	}

	/* ---------- Markup helpers ---------- */

	function esc(value) {
		return String(value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function icon(name, size) {
		var d = ICONS[name];
		if (!d) {
			return '';
		}
		size = size || 24;
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>';
	}

	// The plugin icon on the floating button - a person in a wheelchair at a screen
	function fabIcon() {
		if (!ICONS.wheelchair) {
			return icon('universal-access');
		}
		return '<svg viewBox="0 0 106 97" fill="currentColor" aria-hidden="true" focusable="false"><path d="' + ICONS.wheelchair + '"/><path d="' + ICONS['wheelchair-screen'] + '"/></svg>';
	}

	function strokeIcon(d, size, width) {
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + width + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>';
	}

	var CHECK = 'M5 12l5 5 9-10';
	var CHEVRON = 'M6 9l6 6 6-6';
	var CROSS = 'M6 6l12 12M18 6L6 18';
	var MINUS = 'M5 12h14';
	var PLUS = 'M12 5v14M5 12h14';

	function flag(info, size) {
		if (!info || !info.flagUrl) {
			return '';
		}
		return '<span class="ra-flag" aria-hidden="true"' + (size ? ' style="width:' + size + 'px;height:' + size + 'px"' : '') + '><img src="' + esc(info.flagUrl) + '" alt="" width="' + (size || 18) + '" height="' + (size || 18) + '" loading="lazy" decoding="async"></span>';
	}

	function sectionHtml(title, body) {
		return body ? '<section class="ra-sec"><h3 class="ra-h3">' + esc(title) + '</h3>' + body + '</section>' : '';
	}

	function profilesHtml() {
		var cards = (C.profiles || []).filter(function (key) { return PROFILE_SETS[key]; }).map(function (key) {
			var look = PROFILE_LOOK[key];
			return '<button type="button" class="ra-prof" data-act="profile" data-key="' + key + '" aria-pressed="false">' +
				'<span class="ra-prof-top"><span class="ra-prof-ic" style="--c1:' + look[1] + ';--c2:' + look[2] + '" aria-hidden="true">' + icon(look[0], 20) + '</span>' +
				'<span class="ra-prof-check" aria-hidden="true">' + strokeIcon(CHECK, 12, 3) + '</span></span>' +
				'<span class="ra-prof-text"><span class="ra-prof-name">' + esc(t(key)) + '</span><span class="ra-prof-desc">' + esc(t(key + 'Desc')) + '</span></span>' +
				'</button>';
		}).join('');

		if (!cards) {
			return '';
		}

		return '<div class="ra-hs" data-scroller>' + cards + '</div><div class="ra-track" aria-hidden="true"><div class="ra-thumb"></div></div>';
	}

	function tilesHtml() {
		var tiles = TILES.filter(function (tile) { return FEATURES[tile[0]]; }).map(function (tile) {
			return '<button type="button" class="ra-tile" data-act="tile" data-key="' + tile[0] + '" aria-pressed="false">' + icon(tile[1]) + '<span class="ra-tile-label">' + esc(t(tile[0])) + '</span></button>';
		}).join('');
		return tiles ? '<div class="ra-tiles">' + tiles + '</div>' : '';
	}

	function steppersHtml() {
		var rows = STEPPERS.filter(function (row) { return FEATURES[row[0]]; }).map(function (row) {
			var key = row[0], label = t(key);
			return '<div class="ra-row"><span class="ra-row-ic" aria-hidden="true">' + icon(row[1]) + '</span>' +
				'<span class="ra-row-text"><span class="ra-row-label" id="ra-l-' + key + '">' + esc(label) + '</span></span>' +
				'<span class="ra-step" role="group" aria-labelledby="ra-l-' + key + '">' +
				'<button type="button" class="ra-sbtn" data-act="dec" data-key="' + key + '" aria-label="' + esc(t('decrease').replace('%s', label)) + '">' + strokeIcon(MINUS, 14, 2.4) + '</button>' +
				'<span class="ra-sval" dir="ltr" data-val="' + key + '" aria-live="polite"></span>' +
				'<button type="button" class="ra-sbtn" data-act="inc" data-key="' + key + '" aria-label="' + esc(t('increase').replace('%s', label)) + '">' + strokeIcon(PLUS, 14, 2.4) + '</button>' +
				'</span></div>';
		}).join('');
		return rows ? '<div class="ra-card">' + rows + '</div>' : '';
	}

	function switchesHtml() {
		var rows = SWITCHES.filter(function (row) { return FEATURES[row[0]]; }).map(function (row) {
			var key = row[0];
			return '<div class="ra-row ra-row-sw" data-act="switch" data-key="' + key + '"><span class="ra-row-ic" aria-hidden="true">' + icon(row[1]) + '</span>' +
				'<span class="ra-row-text"><span class="ra-row-label" id="ra-l-' + key + '">' + esc(t(key)) + '</span><span class="ra-row-desc" id="ra-d-' + key + '">' + esc(t(key + 'Desc')) + '</span></span>' +
				'<button type="button" class="ra-switch" role="switch" aria-checked="false" data-act="switch" data-key="' + key + '" aria-labelledby="ra-l-' + key + '" aria-describedby="ra-d-' + key + '"><span class="ra-knob"></span></button>' +
				'</div>';
		}).join('');
		return rows ? '<div class="ra-card">' + rows + '</div>' : '';
	}

	function languageMenuHtml() {
		if (languages.length < 2) {
			return '';
		}
		return '<div class="ra-langmenu" role="listbox" aria-label="' + esc(t('chooseLanguage')) + '" hidden>' + languages.map(function (info) {
			var langAttr = ' lang="' + esc(info.locale.replace('_', '-')) + '" dir="' + esc(info.dir) + '"';
			return '<button type="button" class="ra-opt" role="option" data-act="pick-lang" data-lang="' + esc(info.locale) + '" aria-selected="' + (info.locale === lang) + '">' +
				flag(info, 22) + '<span class="ra-opt-text"><span class="ra-opt-name"' + langAttr + '>' + esc(info.name) + '</span></span>' +
				'<span class="ra-opt-check">' + strokeIcon(CHECK, 16, 2.6) + '</span></button>';
		}).join('') + '</div>';
	}

	function drawerHtml() {
		var info = languageInfo(lang) || {};
		var langButton = languages.length > 1 ?
			'<button type="button" class="ra-hbtn ra-langbtn" data-act="lang" aria-haspopup="listbox" aria-expanded="false" aria-label="' + esc(t('menuLanguage') + ': ' + (info.name || '')) + '">' + flag(info) + '<span>' + esc(info.code || '') + '</span>' + strokeIcon(CHEVRON, 12, 2.2) + '</button>' : '';

		// Reset and Hide share the first row; the accessibility statement gets a full-width row below them
		var footButtons = '<button type="button" class="ra-fbtn" data-act="reset">' + icon('refresh', 16) + '<span>' + esc(t('reset')) + '</span></button>';
		if (C.hideButton) {
			footButtons += '<button type="button" class="ra-fbtn" data-act="hide">' + icon('eye-crossed', 16) + '<span>' + esc(t('hide')) + '</span></button>';
		}
		if (C.statement) {
			footButtons += '<a class="ra-fbtn ra-fbtn-wide" href="' + esc(C.statement) + '"' + (PREVIEW ? ' target="_blank" rel="noopener"' : '') + '>' + icon('document', 16) + '<span>' + esc(t('statement')) + '</span></a>';
		}

		var credit = C.credit ? '<a class="ra-credit" href="' + esc(C.credit.url) + '" target="_blank" rel="noopener"><span>' + esc(t('credit')) + '</span><span aria-hidden="true">·</span><img src="' + esc(C.credit.logo) + '" alt="Real Accessibility" width="93" height="16"></a>' : '';

		return '<div class="ra-drawer" id="ra-drawer" role="dialog" aria-modal="true" aria-labelledby="ra-title">' +
			'<div class="ra-head"><div class="ra-head-row">' +
			'<div class="ra-titles"><h2 class="ra-title" id="ra-title" tabindex="-1">' + esc(t('title')) + '</h2>' +
			'<span class="ra-status" aria-live="polite"><span class="ra-dot" aria-hidden="true"></span><span data-status></span></span></div>' +
			'<div class="ra-ctrls">' + langButton +
			'<button type="button" class="ra-hbtn ra-close" data-act="close" aria-label="' + esc(t('close')) + '">' + strokeIcon(CROSS, 14, 2.6) + '</button>' +
			'</div></div>' + languageMenuHtml() + '</div>' +
			'<div class="ra-vs"><div class="ra-body">' +
			sectionHtml(t('profiles'), profilesHtml()) +
			sectionHtml(t('display'), tilesHtml()) +
			sectionHtml(t('text'), steppersHtml()) +
			sectionHtml(t('reading'), switchesHtml()) +
			'</div></div>' +
			'<div class="ra-foot"><div class="ra-foot-btns">' + footButtons + '</div>' + credit + '</div>' +
			'</div>';
	}

	/* ---------- Build ---------- */

	var BREAKPOINTS = {
		desktop: '(min-width: 1025px)',
		tablet: '(min-width: 768px) and (max-width: 1024px)',
		mobile: '(max-width: 767px)'
	};

	var host = doc.createElement('div');
	host.id = 'real-accessibility';
	host.style.visibility = 'hidden';

	var shadow = host.attachShadow({ mode: 'open' });

	var link = doc.createElement('link');
	link.rel = 'stylesheet';
	link.href = C.css;
	link.addEventListener('load', function () { host.style.removeProperty('visibility'); });
	link.addEventListener('error', function () { host.style.removeProperty('visibility'); });

	var deviceStyle = doc.createElement('style');
	deviceStyle.textContent = ':host{' + Object.keys(COLOR_VARS).map(function (k) { return k + ':' + COLOR_VARS[k]; }).join(';') + '}' + deviceCss();

	var ui = doc.createElement('div');
	ui.className = 'ra-ui';

	shadow.appendChild(link);
	shadow.appendChild(deviceStyle);
	shadow.appendChild(ui);

	var fx, guide, mag, scrim, fab, drawer;
	var isOpen = PREVIEW;

	function deviceCss() {
		var css = '';
		Object.keys(BREAKPOINTS).forEach(function (device) {
			var d = C.devices && C.devices[device];
			if (d) {
				css += '@media ' + BREAKPOINTS[device] + '{' + fabCss(d) + drawerCss(d) + '}';
			}
		});
		return css;
	}

	function fabCss(d) {
		if (!d.show) {
			return '.ra-fab{display:none!important}';
		}
		var parts = String(d.anchor || 'bottom-right').split('-');
		var v = parts[0], h = parts[1];
		var size = d.size, r = Math.min(d.radius, size / 2), x = d.x, y = d.y;
		var rule = 'width:' + size + 'px;height:' + size + 'px;';

		rule += v === 'top' ? 'top:' + y + 'px;bottom:auto;' : (v === 'bottom' ? 'bottom:' + y + 'px;top:auto;' : 'top:50%;bottom:auto;');
		rule += h === 'left' ? 'left:' + x + 'px;right:auto;' : (h === 'right' ? 'right:' + x + 'px;left:auto;' : 'left:50%;right:auto;');
		rule += 'translate:' + (h === 'center' ? '-50%' : '0') + ' ' + (v === 'middle' ? '-50%' : '0') + ';';

		// Attached to the screen edge: only the inner corners are rounded
		var radius = r + 'px';
		if (h === 'right' && x === 0) {
			radius = r + 'px 0 0 ' + r + 'px';
		} else if (h === 'left' && x === 0) {
			radius = '0 ' + r + 'px ' + r + 'px 0';
		} else if (h === 'center' && v === 'bottom' && y === 0) {
			radius = r + 'px ' + r + 'px 0 0';
		} else if (h === 'center' && v === 'top' && y === 0) {
			radius = '0 0 ' + r + 'px ' + r + 'px';
		}

		return '.ra-fab{' + rule + 'border-radius:' + radius + '}';
	}

	function drawerCss(d) {
		var R = d.drawerRadius + 'px';
		if (d.drawer === 'bottom') {
			return '.ra-drawer{top:auto;bottom:0;left:0;right:0;margin-inline:auto;width:min(100%,640px);height:auto;max-height:min(88vh,820px);border-radius:' + R + ' ' + R + ' 0 0;transform:translateY(100%);box-shadow:0 0 0 1px rgba(0,0,0,.06),0 -24px 64px rgba(0,0,0,.16)}';
		}
		var left = d.drawer !== 'right';
		return '.ra-drawer{top:0;bottom:0;' + (left ? 'left:0;right:auto;' : 'right:0;left:auto;') + 'width:min(424px,100%);height:auto;max-height:none;' +
			'border-radius:' + (left ? '0 ' + R + ' ' + R + ' 0' : R + ' 0 0 ' + R) + ';transform:translateX(' + (left ? '-100%' : '100%') + ');' +
			'box-shadow:0 0 0 1px rgba(0,0,0,.06),' + (left ? '24px' : '-24px') + ' 0 64px rgba(0,0,0,.16)}';
	}

	function render() {
		var info = languageInfo(lang) || {};
		ui.setAttribute('dir', info.dir || 'ltr');
		ui.setAttribute('lang', String(lang).replace('_', '-'));
		ui.className = 'ra-ui ' + (info.dir === 'rtl' ? 'ra-rtl' : 'ra-ltr') + (isOpen ? ' ra-open' : '') + (PREVIEW ? ' ra-preview' : '') + (C.flat ? ' ra-flat' : '') + (!PREVIEW && read('sessionStorage', HIDDEN_STORE) ? ' ra-hidden' : '');

		ui.innerHTML = '<div class="ra-fx" aria-hidden="true"></div>' +
			'<div class="ra-guide" aria-hidden="true" hidden></div>' +
			'<div class="ra-mag" aria-hidden="true" hidden></div>' +
			'<div class="ra-scrim" data-act="close" aria-hidden="true"></div>' +
			'<button type="button" class="ra-fab" data-act="fab" aria-label="' + esc(t('open')) + '" aria-expanded="' + isOpen + '" aria-controls="ra-drawer">' + fabIcon() + '</button>' +
			drawerHtml();

		fx = ui.querySelector('.ra-fx');
		guide = ui.querySelector('.ra-guide');
		mag = ui.querySelector('.ra-mag');
		scrim = ui.querySelector('.ra-scrim');
		fab = ui.querySelector('.ra-fab');
		drawer = ui.querySelector('.ra-drawer');

		drawer.inert = !isOpen;

		var scroller = ui.querySelector('[data-scroller]');
		if (scroller) {
			scroller.addEventListener('scroll', updateScrollbar, { passive: true });
		}

		update();
	}

	/* ---------- Sync the UI with the state ---------- */

	function each(selector, callback) {
		Array.prototype.forEach.call(ui.querySelectorAll(selector), callback);
	}

	function update() {
		ui.classList.toggle('ra-active', activeCount() > 0);
		ui.classList.toggle('ra-nomotion', !!state.on.motion);

		var status = ui.querySelector('[data-status]');
		if (status) {
			status.textContent = statusText();
		}

		each('.ra-prof', function (el) {
			el.setAttribute('aria-pressed', String(state.profile === el.getAttribute('data-key')));
		});

		each('.ra-tile', function (el) {
			var key = el.getAttribute('data-key');
			var value = key === 'contrast' || key === 'align' ? state[key] > 0 : !!state.on[key];
			var label = key === 'contrast' ? t(CONTRAST_LABELS[state.contrast]) : (key === 'align' ? t(ALIGN_LABELS[state.align]) : t(key));
			el.setAttribute('aria-pressed', String(value));
			el.querySelector('.ra-tile-label').textContent = label;
		});

		STEPPERS.forEach(function (row) {
			var key = row[0];
			var val = ui.querySelector('[data-val="' + key + '"]');
			if (!val) {
				return;
			}
			val.textContent = VALUES[key][state[key]] || t('regular');
			ui.querySelector('[data-act="dec"][data-key="' + key + '"]').setAttribute('aria-disabled', String(state[key] <= 0));
			ui.querySelector('[data-act="inc"][data-key="' + key + '"]').setAttribute('aria-disabled', String(state[key] >= 3));
		});

		each('.ra-switch', function (el) {
			el.setAttribute('aria-checked', String(!!state.on[el.getAttribute('data-key')]));
		});

		updateScrollbar();
		applyPage();
	}

	function updateScrollbar() {
		var scroller = ui.querySelector('[data-scroller]');
		var thumb = ui.querySelector('.ra-thumb');
		if (!scroller || !thumb || !scroller.scrollWidth) {
			return;
		}
		var visible = scroller.clientWidth / scroller.scrollWidth;
		var max = scroller.scrollWidth - scroller.clientWidth;
		var progress = max > 0 ? Math.min(1, Math.abs(scroller.scrollLeft) / max) : 0;
		thumb.style.width = (visible * 100) + '%';
		thumb.style.insetInlineStart = (progress * (1 - visible) * 100) + '%';
		thumb.parentNode.hidden = visible >= 1;
	}

	/* ---------- Apply to the page ---------- */

	var classesFor = window.realAccessibilityClasses || function (s) {
		var c = [], on = s.on || {};
		if (s.contrast) { c.push(['', 'ra-c-high', 'ra-c-invert', 'ra-c-gray'][s.contrast]); }
		if (s.align) { c.push(['', 'ra-align-center', 'ra-align-right', 'ra-align-left'][s.align]); }
		if (s.lh) { c.push('ra-lh-' + s.lh); }
		if (s.ls) { c.push('ra-ls-' + s.ls); }
		['font', 'links', 'headings', 'cursor', 'noimg', 'motion', 'keys'].forEach(function (k) { if (on[k]) { c.push('ra-' + k); } });
		return c;
	};

	var MANAGED = /^ra-(c-high|c-invert|c-gray|align-center|align-right|align-left|lh-\d|ls-\d|font|links|headings|cursor|noimg|motion|keys)$/;
	var backdrop = window.CSS && CSS.supports && (CSS.supports('backdrop-filter', 'grayscale(1)') || CSS.supports('-webkit-backdrop-filter', 'grayscale(1)'));

	function applyPage() {
		Array.prototype.slice.call(html.classList).forEach(function (name) {
			if (MANAGED.test(name)) {
				html.classList.remove(name);
			}
		});
		classesFor(state).forEach(function (name) {
			if (name) {
				html.classList.add(name);
			}
		});

		html.style.setProperty('--ra-accent', accent);

		if (backdrop) {
			html.classList.add('ra-fx-ready');
			fx.className = 'ra-fx' + (state.contrast === 2 ? ' ra-fx-invert' : (state.contrast === 3 ? ' ra-fx-gray' : ''));
		}

		applyTextSize();
		applyPointerTools();
		applyMotion();
		save();
	}

	/* Text size: every element gets its original size times the scale */

	var SKIP_TAGS = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEMPLATE: 1, LINK: 1, META: 1, BR: 1, IFRAME: 1, IMG: 1, VIDEO: 1, CANVAS: 1 };
	var observer = null;
	var currentScale = 1;

	function scalable(el) {
		return el.nodeType === 1 && !SKIP_TAGS[el.tagName] && el !== host && !(el.closest && el.closest('svg'));
	}

	function rememberSize(el, parentScaled) {
		if (el.hasAttribute('data-ra-fs')) {
			return;
		}
		var size = parseFloat(getComputedStyle(el).fontSize) || 16;
		var parent = el.parentElement;

		// New content inside scaled content inherits the scaled size - store the original
		if (parentScaled && parent && parent.hasAttribute('data-ra-fs') && size === parseFloat(getComputedStyle(parent).fontSize)) {
			size = parseFloat(parent.getAttribute('data-ra-fs'));
		}

		var inline = el.style.getPropertyValue('font-size');
		el.setAttribute('data-ra-fs', size);
		el.setAttribute('data-ra-fs-in', inline ? inline + (el.style.getPropertyPriority('font-size') ? '!' : '') : '');
	}

	function restoreSize(el) {
		var inline = el.getAttribute('data-ra-fs-in');
		el.style.removeProperty('font-size');
		if (inline) {
			var important = inline.slice(-1) === '!';
			el.style.setProperty('font-size', important ? inline.slice(0, -1) : inline, important ? 'important' : '');
		}
		el.removeAttribute('data-ra-fs');
		el.removeAttribute('data-ra-fs-in');
	}

	function scaleElements(list, scale, parentScaled) {
		list = list.filter(scalable);
		list.forEach(function (el) { rememberSize(el, parentScaled); });
		list.forEach(function (el) {
			var base = parseFloat(el.getAttribute('data-ra-fs'));
			el.style.setProperty('font-size', (Math.round(base * scale * 100) / 100) + 'px', 'important');
		});
	}

	function applyTextSize() {
		var scale = FEATURES.size ? SIZE_SCALE[state.size] || 1 : 1;
		if (scale === currentScale) {
			return;
		}
		currentScale = scale;

		if (scale === 1) {
			Array.prototype.forEach.call(doc.querySelectorAll('[data-ra-fs]'), restoreSize);
			if (observer) {
				observer.disconnect();
			}
			return;
		}

		scaleElements([doc.body].concat(Array.prototype.slice.call(doc.body.querySelectorAll('*'))), scale, false);

		if (!observer && window.MutationObserver) {
			var pending = [];
			var queued = false;
			observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (m) {
					Array.prototype.forEach.call(m.addedNodes, function (node) {
						if (node.nodeType === 1 && node !== host) {
							pending.push(node);
							Array.prototype.push.apply(pending, node.querySelectorAll('*'));
						}
					});
				});
				if (pending.length && !queued) {
					queued = true;
					requestAnimationFrame(function () {
						var list = pending;
						pending = [];
						queued = false;
						if (currentScale !== 1) {
							scaleElements(list, currentScale, true);
						}
					});
				}
			});
		}
		if (observer) {
			observer.observe(doc.body, { childList: true, subtree: true });
		}
	}

	/* Reading guide and magnifier follow the pointer */

	var pointer = { x: 0, y: 0, frame: 0 };
	var pointerBound = false;

	function onPointerMove(e) {
		pointer.x = e.clientX;
		pointer.y = e.clientY;
		if (!pointer.frame) {
			pointer.frame = requestAnimationFrame(paintPointerTools);
		}
	}

	function paintPointerTools() {
		pointer.frame = 0;

		if (state.on.guide && guide) {
			guide.style.transform = 'translateY(' + Math.round(pointer.y - 32) + 'px)';
		}

		if (state.on.mag && mag) {
			var el = doc.elementFromPoint(pointer.x, pointer.y);
			var text = '';
			if (el && el !== host && el !== html && el !== doc.body) {
				text = (el.innerText || el.textContent || el.getAttribute('alt') || el.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim();
				if (text.length > 280) {
					text = '';
				}
			}
			if (!text) {
				mag.hidden = true;
				return;
			}
			mag.textContent = text;
			mag.hidden = false;
			var w = mag.offsetWidth, h = mag.offsetHeight;
			var x = pointer.x + 18, y = pointer.y + 22;
			if (x + w > window.innerWidth - 8) {
				x = Math.max(8, pointer.x - w - 18);
			}
			if (y + h > window.innerHeight - 8) {
				y = Math.max(8, pointer.y - h - 18);
			}
			mag.style.transform = 'translate(' + Math.round(x) + 'px,' + Math.round(y) + 'px)';
		}
	}

	function applyPointerTools() {
		var needed = !!(state.on.guide || state.on.mag);
		guide.hidden = !state.on.guide;
		if (!state.on.mag) {
			mag.hidden = true;
		}
		if (needed && !pointerBound) {
			doc.addEventListener('pointermove', onPointerMove, { passive: true });
			pointerBound = true;
		} else if (!needed && pointerBound) {
			doc.removeEventListener('pointermove', onPointerMove);
			pointerBound = false;
		}
		if (state.on.guide && !pointer.y) {
			pointer.y = window.innerHeight / 2;
			paintPointerTools();
		}
	}

	/* Stop animations: pause video and script animations too */

	var paused = [];

	function applyMotion() {
		if (state.on.motion) {
			Array.prototype.forEach.call(doc.querySelectorAll('video'), function (video) {
				if (!video.paused) {
					video.pause();
					paused.push(video);
				}
			});
			if (doc.getAnimations) {
				doc.getAnimations().forEach(function (animation) {
					if (animation.playState === 'running') {
						animation.pause();
						paused.push(animation);
					}
				});
			}
		} else if (paused.length) {
			paused.forEach(function (item) {
				try {
					item.play();
				} catch (e) {}
			});
			paused = [];
		}
	}

	/* ---------- Actions ---------- */

	function setState(changes, keepProfile) {
		Object.keys(changes).forEach(function (key) {
			state[key] = changes[key];
		});
		if (!keepProfile) {
			state.profile = '';
		}
		update();
	}

	function applyProfile(key) {
		if (state.profile === key) {
			state = blank();
			update();
			return;
		}
		var next = blank();
		var set = PROFILE_SETS[key];
		Object.keys(set.on || {}).forEach(function (k) {
			if (FEATURES[k]) {
				next.on[k] = true;
			}
		});
		LEVELS.forEach(function (k) {
			if (set[k] && FEATURES[k]) {
				next[k] = set[k];
			}
		});
		next.profile = key;
		state = next;
		update();
	}

	function toggleOn(key) {
		var on = {};
		Object.keys(state.on).forEach(function (k) { on[k] = state.on[k]; });
		on[key] = !on[key];
		if (!on[key]) {
			delete on[key];
		}
		setState({ on: on });
	}

	var lastFocus = null;

	function open() {
		if (isOpen) {
			return;
		}
		isOpen = true;
		lastFocus = doc.activeElement;
		ui.classList.add('ra-open');
		drawer.inert = false;
		fab.setAttribute('aria-expanded', 'true');
		updateScrollbar();
		var title = ui.querySelector('#ra-title');
		setTimeout(function () { title.focus(); }, 30);
	}

	function close(restoreFocus) {
		if (!isOpen || PREVIEW) {
			return;
		}
		isOpen = false;
		closeLanguageMenu(false);
		ui.classList.remove('ra-open');
		drawer.inert = true;
		fab.setAttribute('aria-expanded', 'false');
		if (restoreFocus !== false) {
			if (!ui.classList.contains('ra-hidden')) {
				fab.focus();
			} else if (lastFocus && lastFocus.focus && lastFocus !== host) {
				lastFocus.focus();
			}
		}
	}

	function languageMenu() {
		return ui.querySelector('.ra-langmenu');
	}

	function openLanguageMenu() {
		var menu = languageMenu();
		if (!menu) {
			return;
		}
		menu.hidden = false;
		ui.querySelector('.ra-langbtn').setAttribute('aria-expanded', 'true');
		var selected = menu.querySelector('[aria-selected="true"]') || menu.querySelector('.ra-opt');
		if (selected) {
			selected.focus();
		}
	}

	function closeLanguageMenu(focusButton) {
		var menu = languageMenu();
		if (!menu || menu.hidden) {
			return;
		}
		menu.hidden = true;
		var button = ui.querySelector('.ra-langbtn');
		button.setAttribute('aria-expanded', 'false');
		if (focusButton) {
			button.focus();
		}
	}

	function pickLanguage(locale) {
		if (!languageInfo(locale) || !C.i18n[locale]) {
			return;
		}
		lang = locale;
		write('localStorage', LANG_STORE, locale);
		render();
		var button = ui.querySelector('.ra-langbtn');
		if (button) {
			button.focus();
		}
	}

	function hideWidget() {
		write('sessionStorage', HIDDEN_STORE, '1');
		ui.classList.add('ra-hidden');
		close(true);
	}

	function handle(action, el) {
		var key = el.getAttribute('data-key');

		if (PREVIEW && (action === 'fab' || action === 'close' || action === 'hide')) {
			return;
		}

		switch (action) {
			case 'fab':
				if (isOpen) {
					close();
				} else {
					open();
				}
				break;
			case 'close':
				close();
				break;
			case 'lang':
				if (languageMenu() && languageMenu().hidden) {
					openLanguageMenu();
				} else {
					closeLanguageMenu(true);
				}
				break;
			case 'pick-lang':
				pickLanguage(el.getAttribute('data-lang'));
				break;
			case 'profile':
				applyProfile(key);
				break;
			case 'tile':
				if (key === 'contrast' || key === 'align') {
					var change = {};
					change[key] = (state[key] + 1) % 4;
					setState(change);
				} else {
					toggleOn(key);
				}
				break;
			case 'dec':
			case 'inc':
				var next = Math.max(0, Math.min(3, state[key] + (action === 'inc' ? 1 : -1)));
				if (next !== state[key]) {
					var step = {};
					step[key] = next;
					setState(step);
				}
				break;
			case 'switch':
				toggleOn(key);
				break;
			case 'reset':
				state = blank();
				update();
				break;
			case 'hide':
				hideWidget();
				break;
		}
	}

	ui.addEventListener('click', function (e) {
		var el = e.target.closest('[data-act]');
		if (!el || !ui.contains(el)) {
			return;
		}
		// A click on the switch itself also bubbles to its row - handle it once
		if (el.classList.contains('ra-row-sw') && e.target.closest('.ra-switch')) {
			return;
		}
		var menu = languageMenu();
		if (menu && !menu.hidden && !e.target.closest('.ra-langmenu') && !e.target.closest('.ra-langbtn')) {
			closeLanguageMenu(false);
		}
		handle(el.getAttribute('data-act'), el);
	});

	function focusables() {
		return Array.prototype.filter.call(drawer.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])'), function (el) {
			return !el.closest('[hidden]') && el.offsetParent !== null;
		});
	}

	ui.addEventListener('keydown', function (e) {
		var menu = languageMenu();

		if (e.key === 'Escape' || e.key === 'Esc') {
			if (menu && !menu.hidden) {
				closeLanguageMenu(true);
			} else {
				close();
			}
			e.stopPropagation();
			return;
		}

		if (menu && !menu.hidden && menu.contains(e.target) && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End')) {
			var options = Array.prototype.slice.call(menu.querySelectorAll('.ra-opt'));
			var index = options.indexOf(e.target);
			if (e.key === 'ArrowDown') {
				index = (index + 1) % options.length;
			} else if (e.key === 'ArrowUp') {
				index = (index - 1 + options.length) % options.length;
			} else {
				index = e.key === 'Home' ? 0 : options.length - 1;
			}
			options[index].focus();
			e.preventDefault();
			return;
		}

		// Keep keyboard focus inside the open menu
		if (e.key === 'Tab' && isOpen) {
			var items = focusables();
			if (!items.length) {
				return;
			}
			var first = items[0], last = items[items.length - 1];
			var active = shadow.activeElement;
			if (e.shiftKey && (active === first || active === ui.querySelector('#ra-title'))) {
				last.focus();
				e.preventDefault();
			} else if (!e.shiftKey && active === last) {
				first.focus();
				e.preventDefault();
			}
		}

		// The switch rows are not buttons themselves - Enter and Space on the switch are enough
	});

	// Escape from anywhere on the page closes the menu
	doc.addEventListener('keydown', function (e) {
		if ((e.key === 'Escape' || e.key === 'Esc') && isOpen) {
			close();
		}
	});

	// Any link to #real-accessibility opens the menu (for a link in the footer or the statement page)
	doc.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href$="#real-accessibility"]');
		if (a) {
			e.preventDefault();
			if (ui.classList.contains('ra-hidden')) {
				write('sessionStorage', HIDDEN_STORE, null);
				ui.classList.remove('ra-hidden');
			}
			open();
		}
	});

	window.addEventListener('resize', updateScrollbar, { passive: true });

	/* ---------- Admin preview: the Features screen sends each change ---------- */

	// Scroll inside the menu only - never the settings screen around the frame
	function reveal(el) {
		[ui.querySelector('.ra-vs'), el.closest('[data-scroller]')].forEach(function (box) {
			if (!box || !box.contains(el)) {
				return;
			}
			var b = box.getBoundingClientRect(), r = el.getBoundingClientRect();
			var dy = r.top < b.top + 12 ? r.top - b.top - 12 : (r.bottom > b.bottom - 12 ? r.bottom - b.bottom + 12 : 0);
			var dx = r.left < b.left ? r.left - b.left - 12 : (r.right > b.right ? r.right - b.right + 12 : 0);
			if (dx || dy) {
				box.scrollBy({ left: dx, top: dy, behavior: 'smooth' });
			}
		});
	}

	function previewTarget(focus) {
		var key = focus && /^\w+$/.test(focus.key || '') ? focus.key : '';
		switch (focus && focus.kind) {
			case 'profile':
				return key && ui.querySelector('.ra-prof[data-key="' + key + '"]');
			case 'feature':
				var el = key && ui.querySelector('[data-key="' + key + '"]');
				return el && el.closest('.ra-tile, .ra-row');
			case 'hide':
				return ui.querySelector('[data-act="hide"]');
			case 'credit':
				return ui.querySelector('.ra-credit');
		}
		return null;
	}

	if (PREVIEW) {
		window.addEventListener('message', function (e) {
			var data = e.source === window.parent && e.data && e.data.realAccessibilityPreview;
			if (!data) {
				return;
			}
			var body = ui.querySelector('.ra-vs');
			var row = ui.querySelector('[data-scroller]');
			var top = body ? body.scrollTop : 0;
			var left = row ? row.scrollLeft : 0;

			C.profiles = data.profiles || [];
			FEATURES = {};
			(data.features || []).forEach(function (key) { FEATURES[key] = true; });
			C.hideButton = !!data.hideButton;
			C.credit = data.credit || false;
			render();

			body = ui.querySelector('.ra-vs');
			row = ui.querySelector('[data-scroller]');
			if (body) {
				body.scrollTop = top;
			}
			if (row) {
				row.scrollLeft = left;
				updateScrollbar();
			}

			var target = previewTarget(data.focus);
			if (target) {
				reveal(target);
				target.classList.add('ra-flash');
			}
		});
	}

	render();
	doc.body.appendChild(host);
	updateScrollbar();
})();
