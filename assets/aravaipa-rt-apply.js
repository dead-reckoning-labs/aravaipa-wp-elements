/**
 * 2027 Racing Team application helper (job ldqh0d, 0.101.28).
 *
 * Loaded by the [arv_rt_apply] shortcode only when the form renders. No
 * dependencies. Every part fails quietly: if anything here breaks, the plain
 * Contact Form 7 form still submits.
 *
 * 1. Photos: images over 2 MB (and every HEIC/HEIF) are drawn to a canvas
 *    with a long edge of at most 2000 px and saved as JPEG under 2 MB, then
 *    swapped into the file input. HEIC decodes natively where the browser
 *    can, else through the heic2any build vendored in the plugin (assets/vendor), else the
 *    original goes up as is (the server accepts heic and heif). PDFs are
 *    never touched.
 * 2. Errors: a list of fields to fix next to Submit, the first one scrolled
 *    to and focused, each bad field outlined. Spam, mail failed, server and
 *    network errors get a plain message with an email fallback.
 * 3. Turnstile: right before submit the token is checked; empty or expired
 *    means reset, wait for a fresh one, then submit once.
 * 4. Autosave: answers (never files) are kept in localStorage and restored
 *    on reload; cleared once the application is sent.
 * 5. Beacon: network failures and missing tokens are reported (status and
 *    field names only) to the site so staff can see failed attempts.
 */
(function () {
	'use strict';

	var root = document.getElementById('rt-apply');
	if (!root || root.getAttribute('data-rt-helper') === '1') {
		return;
	}
	var form = root.querySelector('form.wpcf7-form');
	if (!form) {
		return;
	}
	root.setAttribute('data-rt-helper', '1');

	var MAIL = 'marketing@aravaiparunning.com';
	var MAILTO = 'mailto:' + MAIL + '?subject=' + encodeURIComponent('2027 Racing Team application');
	var STORE_KEY = 'arv-rt-apply-2027-v1';
	var MAX_EDGE = 2000;
	var MAX_BYTES = 2 * 1024 * 1024;
	// Files over this still reach the team by email, but are not copied to RaceReach (ARV_RT_APPLY_RR_FILE_MAX, 4 MB).
	var RR_MAX = 4 * 1024 * 1024;
	var TOKEN_WAIT_MS = 20000;
	var BEACON_URL = root.getAttribute('data-fail-url') || '';
	// heic2any 0.0.4, vendored in the plugin (assets/vendor); the plugin sets its URL in an inline script before this file.
	var HEIC_LIB = typeof window.arvRtApplyHeicUrl === 'string' ? window.arvRtApplyHeicUrl : '';
	var submitBox = form.querySelector('.rt-submit') || form;
	var pending = [];
	var bypass = false;

	/* ---------------------------------------------------------------- utils */

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) {
			e.className = cls;
		}
		if (text) {
			e.textContent = text;
		}
		return e;
	}

	function mailLink() {
		var a = el('a', '', MAIL);
		a.href = MAILTO;
		return a;
	}

	function fieldWrap(name) {
		var c = form.querySelector('[name="' + name + '"],[name="' + name + '[]"]');
		return c ? (c.closest('.rt-f') || c.closest('.wpcf7-form-control-wrap')) : null;
	}

	function labelFor(name) {
		var w = fieldWrap(name);
		if (!w) {
			return name.replace(/^rt-/, '').replace(/-/g, ' ');
		}
		var l = w.querySelector('.rt-l');
		var t = '';
		if (l) {
			var c = l.cloneNode(true);
			c.querySelectorAll('.rt-req,.rt-opt').forEach(function (x) { x.remove(); });
			t = c.textContent;
		} else {
			t = (w.querySelector('.wpcf7-list-item-label') || w).textContent;
		}
		t = t.replace(/\s+/g, ' ').trim();
		return t.length > 70 ? t.slice(0, 67).replace(/\s+\S*$/, '') + '...' : t;
	}

	function focusTarget(w) {
		var list = w.querySelectorAll('input:not([type=hidden]),select,textarea');
		for (var i = 0; i < list.length; i++) {
			if (!list[i].disabled) {
				return list[i];
			}
		}
		return null;
	}

	function goTo(name) {
		var w = fieldWrap(name);
		if (!w) {
			return;
		}
		try {
			w.scrollIntoView({ behavior: 'smooth', block: 'start' });
		} catch (e) {
			w.scrollIntoView(true);
		}
		var f = focusTarget(w);
		if (f) {
			try {
				f.focus({ preventScroll: true });
			} catch (e) {
				f.focus();
			}
		}
	}

	/* ------------------------------------------------------- notices, alerts */

	function clearBoxes() {
		form.querySelectorAll('.rt-summary,.rt-alert').forEach(function (x) { x.remove(); });
	}

	function clearBad() {
		form.querySelectorAll('.rt-bad').forEach(function (x) { x.classList.remove('rt-bad'); });
	}

	function topOfSubmit(node) {
		submitBox.insertBefore(node, submitBox.firstChild);
	}

	function showAlert(kind) {
		clearBoxes();
		var box = el('div', 'rt-alert rt-alert--' + kind);
		box.setAttribute('role', 'alert');
		box.setAttribute('aria-live', 'assertive');
		var head;
		var body;
		if (kind === 'spam') {
			head = 'We could not confirm you are human.';
			body = 'Your application did not go through. Press Submit again. If it keeps happening, email your answers to ';
		} else if (kind === 'mail_failed') {
			head = 'Your application is saved.';
			body = 'The notice to the team did not send, so there is no need to apply again. If you want to be sure, email your answers to ';
		} else if (kind === 'no_token') {
			head = 'We could not check that you are human.';
			body = 'Your application has not been sent yet. Reload the page (your answers stay on this device) and press Submit again, or email your answers to ';
		} else if (kind === 'timeout') {
			head = 'This is taking longer than usual.';
			body = 'Please wait a moment. If nothing happens, check your connection, or email your answers to ';
		} else {
			head = 'We could not reach our server.';
			body = 'Your application has not been sent yet, and your answers are saved on this device. Check your connection and press Submit again, or email your answers to ';
		}
		box.appendChild(el('strong', '', head));
		var p = el('span', '', body);
		p.appendChild(mailLink());
		p.appendChild(document.createTextNode('.'));
		box.appendChild(p);
		topOfSubmit(box);
		return box;
	}

	function showSummary(names) {
		clearBoxes();
		clearBad();
		var seen = {};
		var list = [];
		names.forEach(function (n) {
			n = String(n || '').replace(/\[\]$/, '');
			if (n && !seen[n] && fieldWrap(n)) {
				seen[n] = 1;
				list.push(n);
			}
		});
		// Top to bottom, so the list reads like the form and focus goes to the first.
		list.sort(function (a, b) {
			var pa = fieldWrap(a);
			var pb = fieldWrap(b);
			return pa === pb ? 0 : (pa.compareDocumentPosition(pb) & 4 ? -1 : 1);
		});
		var box = el('div', 'rt-summary');
		box.setAttribute('role', 'alert');
		box.setAttribute('aria-live', 'assertive');
		box.appendChild(el('strong', '', list.length === 1 ? 'One answer needs a look:' : (list.length ? 'These answers need a look:' : 'Some answers need a look.')));
		if (list.length) {
			var ul = el('ul');
			list.forEach(function (n) {
				var w = fieldWrap(n);
				w.classList.add('rt-bad');
				var li = el('li');
				var a = el('a', '', labelFor(n));
				// Plain '#' href: the click handler moves focus, so no DOM text ever lands in the URL.
				a.setAttribute('href', '#');
				a.addEventListener('click', function (e) {
					e.preventDefault();
					goTo(n);
				});
				li.appendChild(a);
				ul.appendChild(li);
			});
			box.appendChild(ul);
		}
		topOfSubmit(box);
		if (list.length) {
			goTo(list[0]);
		}
		return list;
	}

	form.addEventListener('input', clearOne, true);
	form.addEventListener('change', clearOne, true);
	function clearOne(e) {
		var w = e.target && e.target.closest ? e.target.closest('.rt-f.rt-bad') : null;
		if (w) {
			w.classList.remove('rt-bad');
		}
	}

	/* --------------------------------------------------------------- beacon */

	var beaconSent = {};
	function beacon(status, fields) {
		if (!BEACON_URL || !navigator.sendBeacon) {
			return;
		}
		var now = Date.now();
		if (beaconSent[status] && now - beaconSent[status] < 30000) {
			return;
		}
		beaconSent[status] = now;
		var names = (fields || []).map(function (n) { return String(n).replace(/\[\]$/, ''); }).filter(function (n) {
			return /^rt-[a-z0-9-]+$/.test(n);
		}).slice(0, 40);
		try {
			navigator.sendBeacon(BEACON_URL, new Blob([JSON.stringify({ status: status, fields: names })], { type: 'text/plain;charset=UTF-8' }));
		} catch (e) { /* never block */ }
	}

	/* ------------------------------------------------------- CF7 lifecycle */

	var watchdog = null;

	function unstick() {
		// CF7 ignores a submit while its status is "submitting"; a failed
		// request leaves it there, so put the form back to ready.
		if (form.wpcf7) {
			form.wpcf7.status = 'init';
		}
		form.classList.remove('submitting');
		form.setAttribute('data-status', 'init');
		form.classList.add('init');
	}

	function failNet(kind) {
		clearTimeout(watchdog);
		unstick();
		showAlert(kind === 'server' ? 'server' : 'network');
		beacon(kind === 'server' ? 'server' : 'network', []);
	}

	form.addEventListener('wpcf7beforesubmit', function () {
		clearBoxes();
		clearTimeout(watchdog);
		watchdog = setTimeout(function () {
			if (form.getAttribute('data-status') === 'submitting') {
				showAlert('timeout');
				beacon('timeout', []);
			}
		}, 60000);
	});

	form.addEventListener('wpcf7invalid', function (e) {
		var r = (e.detail && e.detail.apiResponse) || {};
		var names = (r.invalid_fields || []).map(function (f) { return f.field || ''; });
		if (!names.length) {
			form.querySelectorAll('.wpcf7-not-valid').forEach(function (c) { names.push(c.name || ''); });
		}
		showSummary(names);
	});

	form.addEventListener('wpcf7unaccepted', function () {
		var names = [];
		form.querySelectorAll('.rt-consent input[type=checkbox]').forEach(function (c) {
			var opt = c.closest('.wpcf7-acceptance');
			if (!c.checked && !(opt && opt.classList.contains('optional'))) {
				names.push(c.name);
			}
		});
		showSummary(names);
	});

	form.addEventListener('wpcf7spam', function () {
		showAlert('spam');
	});

	form.addEventListener('wpcf7mailfailed', function () {
		showAlert('mail_failed');
	});

	form.addEventListener('wpcf7submit', function (e) {
		clearTimeout(watchdog);
		var s = e.detail && e.detail.apiResponse ? e.detail.apiResponse.status : '';
		if (['mail_sent', 'validation_failed', 'acceptance_missing', 'spam', 'mail_failed'].indexOf(s) === -1) {
			showAlert('server');
			beacon(s === 'aborted' ? 'aborted' : 'server', []);
		}
	});

	form.addEventListener('wpcf7mailsent', function () {
		clearTimeout(watchdog);
		clearBoxes();
		clearBad();
		storeClear();
		form.querySelectorAll('.rt-photo-note,.rt-notice').forEach(function (x) { x.remove(); });
		// Swap the form for the full thank-you screen and bring it into view.
		var root = document.getElementById('rt-apply');
		var thanks = document.getElementById('rt-thanks');
		if (root && thanks) {
			root.setAttribute('data-sent', '1');
			var open = root.querySelector('.rt-state-open');
			if (open) { open.hidden = true; }
			thanks.hidden = false;
			try { thanks.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (x) { thanks.scrollIntoView(); }
			try { thanks.focus({ preventScroll: true }); } catch (x2) { /* old browsers */ }
		}
	});

	// CF7 throws away network errors and non-2xx answers without an event.
	// Watch its own request to our feedback endpoint so the applicant hears
	// about it and can press Submit again.
	if (window.fetch && !window.fetch.__rtWrapped) {
		var origFetch = window.fetch;
		var wrapped = function (input, init) {
			var url = typeof input === 'string' ? input : (input && input.url) || '';
			var p = origFetch.apply(this, arguments);
			if (!/contact-forms(\/|%2F)\d+(\/|%2F)feedback/i.test(url)) {
				return p;
			}
			var id = form.wpcf7 ? String(form.wpcf7.id) : (form.querySelector('input[name="_wpcf7"]') || {}).value;
			if (id && url.indexOf(id) === -1) {
				return p;
			}
			return p.then(function (resp) {
				if (!resp || resp.status < 200 || resp.status >= 300) {
					failNet('server');
				}
				return resp;
			}, function (err) {
				failNet('network');
				throw err;
			});
		};
		wrapped.__rtWrapped = true;
		window.fetch = wrapped;
	}

	/* ------------------------------------------------------------ Turnstile */

	function tsWidget() {
		return form.querySelector('.cf-turnstile');
	}

	(function () {
		var w = tsWidget();
		if (w) {
			if (!w.hasAttribute('data-refresh-expired')) {
				w.setAttribute('data-refresh-expired', 'auto');
			}
			if (!w.hasAttribute('data-retry')) {
				w.setAttribute('data-retry', 'auto');
			}
		}
	})();

	// 'ok', 'stale' (empty or expired) or 'none' (no widget or no API: CF7 decides).
	function tsState() {
		var w = tsWidget();
		var ts = window.turnstile;
		if (!w || !ts) {
			return 'none';
		}
		var tok = '';
		var expired = false;
		try {
			tok = ts.getResponse(w) || '';
		} catch (e) { /* fall back to the hidden field */ }
		if (!tok) {
			var inp = form.querySelector('[name="_wpcf7_turnstile_response"],[name="cf-turnstile-response"]');
			tok = inp ? inp.value : '';
		}
		try {
			expired = !!(ts.isExpired && ts.isExpired(w));
		} catch (e) { /* treat as not expired */ }
		return !tok || expired ? 'stale' : 'ok';
	}

	function waitForToken() {
		return new Promise(function (resolve) {
			var w = tsWidget();
			try {
				window.turnstile.reset(w);
			} catch (e) { /* keep polling anyway */ }
			var start = Date.now();
			(function poll() {
				if (tsState() === 'ok') {
					resolve(true);
				} else if (Date.now() - start > TOKEN_WAIT_MS) {
					resolve(false);
				} else {
					setTimeout(poll, 250);
				}
			})();
		});
	}

	function showChecking(text) {
		clearBoxes();
		form.querySelectorAll('.rt-checking').forEach(function (x) { x.remove(); });
		var box = el('div', 'rt-notice rt-checking', text);
		box.setAttribute('role', 'status');
		box.setAttribute('aria-live', 'polite');
		topOfSubmit(box);
		return box;
	}

	// Capture phase on document: runs before CF7's own submit listener.
	document.addEventListener('submit', function (e) {
		if (e.target !== form || bypass) {
			return;
		}
		var busy = pending.length > 0;
		var stale = tsState() === 'stale';
		if (!busy && !stale) {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		var submitter = e.submitter || form.querySelector('[type=submit]');
		var note = showChecking(busy ? 'Getting your photo ready...' : 'Checking you are human...');
		Promise.all(pending.slice()).catch(function () {}).then(function () {
			if (tsState() !== 'stale') {
				return true;
			}
			note.textContent = 'Checking you are human...';
			return waitForToken();
		}).then(function (ok) {
			note.remove();
			if (!ok) {
				showAlert('no_token');
				beacon('no_token', []);
				return;
			}
			bypass = true;
			try {
				if (form.requestSubmit) {
					form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
				} else {
					form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
				}
			} finally {
				bypass = false;
			}
		});
	}, true);

	/* --------------------------------------------------------------- photos */

	function isHeic(f) {
		return /hei[cf]/i.test(f.type || '') || /\.hei[cf]$/i.test(f.name || '');
	}

	function isImage(f) {
		return /^image\//i.test(f.type || '') || /\.(jpe?g|png|webp|gif|bmp|hei[cf]|tiff?)$/i.test(f.name || '');
	}

	function viaImg(blob) {
		return new Promise(function (resolve, reject) {
			var u = URL.createObjectURL(blob);
			var img = new Image();
			img.onload = function () {
				resolve({ src: img, w: img.naturalWidth, h: img.naturalHeight, done: function () { URL.revokeObjectURL(u); } });
			};
			img.onerror = function () {
				URL.revokeObjectURL(u);
				reject(new Error('decode'));
			};
			img.src = u;
		});
	}

	function decode(blob) {
		// imageOrientation from-image applies the EXIF rotation, so phone
		// photos are not saved sideways; <img> does the same by default.
		if (window.createImageBitmap) {
			return createImageBitmap(blob, { imageOrientation: 'from-image' }).then(function (b) {
				if (!b.width || !b.height) {
					throw new Error('empty');
				}
				return { src: b, w: b.width, h: b.height, done: function () { if (b.close) { b.close(); } } };
			}).catch(function () {
				return viaImg(blob);
			});
		}
		return viaImg(blob);
	}

	function toBlob(canvas, q) {
		return new Promise(function (resolve) {
			canvas.toBlob(function (b) { resolve(b); }, 'image/jpeg', q);
		});
	}

	function shrink(img) {
		var scale = Math.min(1, MAX_EDGE / Math.max(img.w, img.h));
		var qs = [0.86, 0.78, 0.7, 0.62, 0.54, 0.46];
		function attempt(s, i) {
			var c = document.createElement('canvas');
			c.width = Math.max(1, Math.round(img.w * s));
			c.height = Math.max(1, Math.round(img.h * s));
			var ctx = c.getContext('2d');
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, c.width, c.height);
			ctx.drawImage(img.src, 0, 0, c.width, c.height);
			return toBlob(c, qs[i]).then(function (b) {
				if (!b) {
					throw new Error('toBlob');
				}
				if (b.size <= MAX_BYTES) {
					return b;
				}
				if (i + 1 < qs.length) {
					return attempt(s, i + 1);
				}
				if (s > 0.2) {
					return attempt(s * 0.75, 2);
				}
				return b;
			});
		}
		return attempt(scale, 0);
	}

	var heicLib = null;
	function loadHeicLib() {
		if (window.heic2any) {
			return Promise.resolve(window.heic2any);
		}
		if (!HEIC_LIB) {
			return Promise.reject(new Error('heic2any url'));
		}
		if (!heicLib) {
			heicLib = new Promise(function (resolve, reject) {
				var s = document.createElement('script');
				s.src = HEIC_LIB;
				s.async = true;
				s.onload = function () {
					window.heic2any ? resolve(window.heic2any) : reject(new Error('heic2any'));
				};
				s.onerror = function () {
					heicLib = null;
					reject(new Error('heic2any load'));
				};
				document.head.appendChild(s);
			});
		}
		return heicLib;
	}

	function decodeAny(file) {
		return decode(file).catch(function (err) {
			if (!isHeic(file)) {
				throw err;
			}
			return loadHeicLib().then(function (h) {
				return h({ blob: file, toType: 'image/jpeg', quality: 0.9 });
			}).then(function (out) {
				return decode(Array.isArray(out) ? out[0] : out);
			});
		});
	}

	function photoNote(input) {
		var wrap = input.closest('.wpcf7-form-control-wrap') || input;
		var n = wrap.parentNode.querySelector('.rt-photo-note');
		if (!n) {
			n = el('span', 'rt-photo-note');
			n.setAttribute('aria-live', 'polite');
			wrap.parentNode.insertBefore(n, wrap.nextSibling);
		}
		return n;
	}

	function mb(n) {
		return (Math.round(n / 104857.6) / 10).toFixed(1) + ' MB';
	}

	function swapFile(input, blob, name) {
		var base = String(name || 'photo').replace(/\.[^.]+$/, '').replace(/[^\w.-]+/g, '-').slice(0, 60) || 'photo';
		var f = new File([blob], base + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
		var dt = new DataTransfer();
		dt.items.add(f);
		input.files = dt.files;
		return f;
	}

	function onFile(e) {
		var input = e.target;
		if (!input || input.type !== 'file' || !/^rt-(headshot|resume-file)$/.test(input.name)) {
			return;
		}
		if (input.__rtSwapping) {
			return;
		}
		var file = input.files && input.files[0];
		var note = photoNote(input);
		note.textContent = '';
		note.className = 'rt-photo-note';
		if (!file) {
			return;
		}
		if (!isImage(file) || /pdf/i.test(file.type)) {
			if (input.name === 'rt-resume-file' && file.size > RR_MAX) {
				note.className = 'rt-photo-note rt-photo-note--warn';
				note.textContent = 'This file is on the large side (' + mb(file.size) + '). It will still reach our team with your application. If you have a version under 4 MB, that is even better, or you can email it to ' + MAIL + '.';
			}
			return;
		}
		if (!isHeic(file) && file.size <= MAX_BYTES) {
			note.textContent = 'Photo ready (' + mb(file.size) + ').';
			return;
		}
		if (typeof DataTransfer === 'undefined' || !window.HTMLCanvasElement) {
			return;
		}
		note.className = 'rt-photo-note rt-photo-note--busy';
		note.textContent = 'Getting your photo ready...';
		var job = decodeAny(file).then(function (img) {
			return shrink(img).then(function (b) {
				img.done();
				return b;
			}, function (err) {
				img.done();
				throw err;
			});
		}).then(function (blob) {
			if (input.files[0] !== file) {
				return; // picked another file meanwhile
			}
			input.__rtSwapping = true;
			var f = swapFile(input, blob, file.name);
			input.__rtSwapping = false;
			note.className = 'rt-photo-note';
			note.textContent = 'Photo ready (' + mb(f.size) + ').';
		}).catch(function () {
			input.__rtSwapping = false;
			note.className = 'rt-photo-note rt-photo-note--warn';
			note.textContent = file.size > 10 * 1024 * 1024 ? 'This photo is over 10 MB and could not be shrunk here. Try a smaller one, or email it to ' + MAIL + '.' : (file.size > RR_MAX ? 'We will send your photo as it is. It is over 4 MB, so a smaller one helps if you have it.' : 'We will send your photo as it is.');
		});
		pending.push(job);
		job.then(function () {
			pending = pending.filter(function (j) { return j !== job; });
		});
	}

	form.querySelectorAll('input[type=file]').forEach(function (inp) {
		if (/^rt-(headshot|resume-file)$/.test(inp.name)) {
			var acc = inp.getAttribute('accept') || '';
			['.heic', '.heif', 'image/*'].forEach(function (a) {
				if (acc.split(',').indexOf(a) === -1) {
					acc = acc ? acc + ',' + a : a;
				}
			});
			inp.setAttribute('accept', acc);
		}
	});
	form.addEventListener('change', onFile);

	/* ------------------------------------------------------------- autosave */

	function storeGet() {
		try {
			var raw = window.localStorage.getItem(STORE_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function storeClear() {
		try {
			window.localStorage.removeItem(STORE_KEY);
		} catch (e) { /* private mode */ }
	}

	function saveable() {
		return Array.prototype.filter.call(form.querySelectorAll('input,select,textarea'), function (c) {
			var t = (c.type || '').toLowerCase();
			return /^rt-/.test(c.name || '') && ['file', 'hidden', 'submit', 'button', 'password'].indexOf(t) === -1;
		});
	}

	function collect() {
		var d = {};
		saveable().forEach(function (c) {
			var t = (c.type || '').toLowerCase();
			if (t === 'checkbox' || t === 'radio') {
				if (!d[c.name]) {
					d[c.name] = [];
				}
				if (c.checked) {
					d[c.name].push(c.value);
				}
			} else if (c.tagName === 'SELECT' && c.multiple) {
				d[c.name] = Array.prototype.filter.call(c.options, function (o) { return o.selected; }).map(function (o) { return o.value; });
			} else {
				d[c.name] = c.value;
			}
		});
		return d;
	}

	function hasAnswers(d) {
		return Object.keys(d).some(function (k) {
			var v = d[k];
			return Array.isArray(v) ? v.length > 0 : (v !== '' && v !== 'Choose');
		});
	}

	var saveTimer = null;
	function save() {
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function () {
			var d = collect();
			try {
				if (hasAnswers(d)) {
					window.localStorage.setItem(STORE_KEY, JSON.stringify({ v: 1, t: Date.now(), d: d }));
				}
			} catch (e) { /* private mode or full: skip */ }
		}, 400);
	}

	function restore() {
		var s = storeGet();
		if (!s || s.v !== 1 || !s.d || !hasAnswers(s.d)) {
			return false;
		}
		if (s.t && Date.now() - s.t > 45 * 24 * 3600 * 1000) {
			storeClear();
			return false;
		}
		var touched = [];
		saveable().forEach(function (c) {
			if (!Object.prototype.hasOwnProperty.call(s.d, c.name)) {
				return;
			}
			var v = s.d[c.name];
			var t = (c.type || '').toLowerCase();
			if (t === 'checkbox' || t === 'radio') {
				var on = Array.isArray(v) && v.indexOf(c.value) !== -1;
				if (c.checked !== on) {
					c.checked = on;
					touched.push(c);
				}
			} else if (c.tagName === 'SELECT' && c.multiple) {
				Array.prototype.forEach.call(c.options, function (o) { o.selected = Array.isArray(v) && v.indexOf(o.value) !== -1; });
				touched.push(c);
			} else if (typeof v === 'string' && v !== '' && c.value !== v) {
				c.value = v;
				touched.push(c);
			}
		});
		touched.forEach(function (c) {
			c.dispatchEvent(new Event('change', { bubbles: true }));
		});
		if (touched.length) {
			var n = el('p', 'rt-notice rt-restored', 'We restored your answers from this device. Photos and files need to be added again.');
			n.setAttribute('role', 'status');
			form.insertBefore(n, form.querySelector('.rt-form') || form.firstChild);
		}
		return touched.length > 0;
	}

	// On a cached page CF7 resets the form when the window loads and then
	// fires wpcf7reset; restoring before that would be wiped. So restore
	// after that reset, or right away when CF7 will not reset the form.
	var restored = false;
	function restoreOnce() {
		if (!restored) {
			restored = true;
			restore();
		}
	}
	function restoreWhenReady() {
		var cf7Resets = !!(window.wpcf7 && window.wpcf7.cached);
		if (!cf7Resets) {
			restoreOnce();
			return;
		}
		form.addEventListener('wpcf7reset', function () {
			// Restore again even if the fallback already ran: the reset wiped it.
			setTimeout(function () {
				restored = false;
				restoreOnce();
			}, 0);
		}, { once: true });
		var fallback = function () {
			setTimeout(restoreOnce, 4000);
		};
		if (document.readyState === 'complete') {
			fallback();
		} else {
			window.addEventListener('load', fallback, { once: true });
		}
	}

	/* ------------------------------------------- after a full page reload */

	// When CF7's script is missing the form posts the old way and the page
	// comes back with the result in data-status and the bad fields marked.
	var initial = form.getAttribute('data-status') || '';
	if (initial === 'sent') {
		storeClear();
	} else {
		restoreWhenReady();
		if (initial === 'invalid' || initial === 'unaccepted') {
			var bad = [];
			form.querySelectorAll('.wpcf7-not-valid,[aria-invalid="true"]').forEach(function (c) {
				if (c.name) {
					bad.push(c.name);
				}
			});
			form.querySelectorAll('.rt-consent .wpcf7-acceptance:not(.optional) input[type=checkbox]').forEach(function (c) {
				if (!c.checked && initial === 'unaccepted') {
					bad.push(c.name);
				}
			});
			showSummary(bad);
		} else if (initial === 'spam') {
			showAlert('spam');
		} else if (initial === 'failed') {
			showAlert('mail_failed');
		} else if (initial === 'aborted') {
			showAlert('server');
		}
	}
	form.addEventListener('input', save);
	form.addEventListener('change', save);

	window.arvRtApply = { version: 1, store: STORE_KEY, tsState: tsState };
})();
