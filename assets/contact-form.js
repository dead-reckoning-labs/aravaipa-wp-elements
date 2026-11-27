/**
 * Contact Us form (CF7 84199) browser helper.
 *
 * Always: Turnstile guard. A submit with an empty or stale token (older than
 * 270 s, Turnstile tokens die at 300 s) is held, the widget is reset when
 * stale, and the form is sent again once a fresh token arrives, or after
 * 30 s either way. One resubmit per attempt, never a loop.
 *
 * v2 form only (.arv-cf2): the event combobox (ARIA 1.2 combobox with a
 * listbox popup, free text kept), topic help and topic fields shown with
 * the category, and the event marked required for four topics.
 *
 * No dependencies. Exposes its parts on module.exports for node --test.
 */
(function (root, factory) {
	'use strict';
	var api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	} else {
		root.ArvContact = api;
		if (typeof document !== 'undefined') {
			api.boot(document, root);
		}
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict';

	var FORM_ID = '84199';
	var MAX_AGE = 270000;
	var WAIT_MAX = 30000;
	var POLL = 250;
	var TICK = 1000;

	function arr(list) {
		return Array.prototype.slice.call(list || []);
	}

	function isOurs(form) {
		var i = form && form.querySelector && form.querySelector('input[name="_wpcf7"]');
		return !!(i && String(i.value) === FORM_ID);
	}

	/* ------------------------------------------------------------------
	 * Turnstile guard
	 * ---------------------------------------------------------------- */

	function tokenInput(form) {
		return form.querySelector('input[name="_wpcf7_turnstile_response"]') || form.querySelector('input[name="cf-turnstile-response"]');
	}

	function widgetOf(form) {
		return form.querySelector('.cf-turnstile') || form.querySelector('.wpcf7-turnstile');
	}

	/**
	 * What to do with a submit: 'pass' when there is no widget or the token
	 * is present and fresh, 'stale' when present but old, 'empty' otherwise.
	 */
	function guardState(hasWidget, token, stamp, now) {
		if (!hasWidget) {
			return 'pass';
		}
		if (!token) {
			return 'empty';
		}
		if (stamp && now - stamp > MAX_AGE) {
			return 'stale';
		}
		return 'pass';
	}

	function initGuard(form, env) {
		var st = { stamp: 0, last: '', waiting: false, resubmit: false };
		form.arvGuard = st;

		function token() {
			var i = tokenInput(form);
			return i ? String(i.value || '') : '';
		}

		function reset() {
			var w = widgetOf(form);
			st.last = '';
			st.stamp = 0;
			try {
				if (env.turnstile && w) {
					env.turnstile.reset(w);
				}
			} catch (e) { /* widget gone: the server check still runs */ }
		}

		function tick() {
			var v = token();
			if (v && v !== st.last) {
				st.last = v;
				st.stamp = env.now();
			} else if (!v) {
				st.last = '';
				st.stamp = 0;
			}
			if (v && st.stamp && env.now() - st.stamp > MAX_AGE) {
				reset();
			}
		}
		st.tick = tick;
		env.setInterval(tick, TICK);
		tick();

		function message(on) {
			var m = form.querySelector('.arv-cf-wait');
			if (on && !m) {
				m = env.doc.createElement('span');
				m.className = 'arv-cf-wait';
				m.setAttribute('role', 'status');
				m.textContent = 'Checking the spam filter, one moment. Your message will send by itself.';
				var w = widgetOf(form);
				var host = (w && w.parentNode) || form;
				host.appendChild(m);
			} else if (!on && m && m.parentNode) {
				m.parentNode.removeChild(m);
			}
		}

		function send(submitter) {
			st.waiting = false;
			message(false);
			st.resubmit = true;
			if (typeof form.requestSubmit === 'function') {
				form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
			} else {
				var ev = env.doc.createEvent ? env.doc.createEvent('Event') : null;
				if (ev) {
					ev.initEvent('submit', true, true);
					form.dispatchEvent(ev);
				}
			}
		}

		st.onSubmit = function (e) {
			if (st.resubmit) {
				st.resubmit = false;
				return;
			}
			if (st.waiting) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return;
			}
			tick();
			var state = guardState(!!widgetOf(form), token(), st.stamp, env.now());
			if ('pass' === state) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			if ('stale' === state) {
				reset();
			}
			st.waiting = true;
			message(true);
			var started = env.now();
			var submitter = e.submitter;
			(function wait() {
				tick();
				var v = token();
				if ((v && st.stamp && env.now() - st.stamp <= MAX_AGE) || env.now() - started >= WAIT_MAX) {
					send(submitter);
					return;
				}
				env.setTimeout(wait, POLL);
			})();
		};
		return st;
	}

	/* ------------------------------------------------------------------
	 * Combobox
	 * ---------------------------------------------------------------- */

	/** Case-insensitive substring match; General always last. */
	function filterOptions(list, query, general) {
		var q = String(query || '').trim().toLowerCase();
		var out = [];
		arr(list).forEach(function (n) {
			if (n === general) {
				return;
			}
			if (!q || String(n).toLowerCase().indexOf(q) !== -1) {
				out.push(n);
			}
		});
		if (general) {
			out.push(general);
		}
		return out;
	}

	function Combobox(input, names, general, doc, timers) {
		var self = this;
		var id = (input.getAttribute('id') || 'arv-cf-event') + '-list';
		var list = doc.createElement('ul');
		var status = doc.createElement('span');
		this.input = input;
		this.list = list;
		this.items = [];
		this.active = -1;
		this.names = names;
		this.general = general;
		this.doc = doc;

		list.setAttribute('id', id);
		list.setAttribute('role', 'listbox');
		list.setAttribute('aria-label', 'Races and events');
		list.className = 'arv-cf-list';
		list.hidden = true;
		status.className = 'arv-cf-sr screen-reader-text';
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		this.status = status;

		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', id);
		input.setAttribute('autocomplete', 'off');

		var host = input.parentNode;
		host.appendChild(list);
		host.appendChild(status);

		input.addEventListener('input', function () {
			self.open();
		});
		input.addEventListener('focus', function () {
			self.open();
		});
		input.addEventListener('click', function () {
			if (list.hidden) {
				self.open();
			}
		});
		input.addEventListener('keydown', function (e) {
			self.key(e);
		});
		input.addEventListener('blur', function () {
			timers.setTimeout(function () {
				self.close();
			}, 150);
		});
	}

	Combobox.prototype.render = function () {
		var self = this;
		var q = String(this.input.value || '').trim();
		var names = filterOptions(this.names, q, this.general);
		var doc = this.doc;
		while (this.list.firstChild) {
			this.list.removeChild(this.list.firstChild);
		}
		this.items = [];
		this.active = -1;
		names.forEach(function (n, i) {
			var li = doc.createElement('li');
			li.setAttribute('role', 'option');
			li.setAttribute('id', self.list.getAttribute('id') + '-' + i);
			li.setAttribute('aria-selected', 'false');
			li.setAttribute('data-value', n);
			if (n === self.general) {
				li.className = 'arv-cf-general';
			}
			var at = q ? n.toLowerCase().indexOf(q.toLowerCase()) : -1;
			if (at >= 0 && n !== self.general) {
				li.appendChild(doc.createTextNode(n.slice(0, at)));
				var m = doc.createElement('mark');
				m.textContent = n.slice(at, at + q.length);
				li.appendChild(m);
				li.appendChild(doc.createTextNode(n.slice(at + q.length)));
			} else {
				li.textContent = n;
			}
			li.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			li.addEventListener('click', function () {
				self.choose(n);
			});
			self.list.appendChild(li);
			self.items.push(li);
		});
		var count = names.length - (this.general ? 1 : 0);
		this.status.textContent = count + (1 === count ? ' race matches' : ' races match') + '. Use the arrow keys to pick one.';
		this.input.removeAttribute('aria-activedescendant');
		return names;
	};

	Combobox.prototype.open = function () {
		this.render();
		this.list.hidden = false;
		this.input.setAttribute('aria-expanded', 'true');
	};

	Combobox.prototype.close = function () {
		this.list.hidden = true;
		this.active = -1;
		this.input.setAttribute('aria-expanded', 'false');
		this.input.removeAttribute('aria-activedescendant');
	};

	Combobox.prototype.isOpen = function () {
		return !this.list.hidden;
	};

	Combobox.prototype.move = function (d) {
		if (!this.items.length) {
			return;
		}
		if (this.active >= 0) {
			this.items[this.active].setAttribute('aria-selected', 'false');
		}
		var n = this.items.length;
		this.active = this.active < 0 ? (d > 0 ? 0 : n - 1) : (this.active + d + n) % n;
		var li = this.items[this.active];
		li.setAttribute('aria-selected', 'true');
		this.input.setAttribute('aria-activedescendant', li.getAttribute('id'));
		if (li.scrollIntoView) {
			li.scrollIntoView({ block: 'nearest' });
		}
	};

	Combobox.prototype.choose = function (value) {
		this.input.value = value;
		this.close();
		var ev = this.doc.createEvent ? this.doc.createEvent('Event') : null;
		if (ev) {
			ev.initEvent('change', true, false);
			this.input.dispatchEvent(ev);
		}
	};

	Combobox.prototype.key = function (e) {
		var k = e.key;
		if ('ArrowDown' === k || 'Down' === k) {
			e.preventDefault();
			if (!this.isOpen()) {
				this.open();
			}
			this.move(1);
		} else if ('ArrowUp' === k || 'Up' === k) {
			e.preventDefault();
			if (!this.isOpen()) {
				this.open();
			}
			this.move(-1);
		} else if ('Enter' === k) {
			if (this.isOpen() && this.active >= 0) {
				e.preventDefault();
				this.choose(this.items[this.active].getAttribute('data-value'));
			} else if (this.isOpen()) {
				// Free text: Enter keeps what was typed and just closes.
				e.preventDefault();
				this.close();
			}
		} else if ('Escape' === k || 'Esc' === k) {
			if (this.isOpen()) {
				e.preventDefault();
				this.close();
			}
		} else if ('Tab' === k) {
			this.close();
		}
	};

	/* ------------------------------------------------------------------
	 * v2: topic help, fields and required event
	 * ---------------------------------------------------------------- */

	function applyTopic(root, topic, data) {
		arr(root.querySelectorAll('.arv-cf-help')).forEach(function (h) {
			var on = h.getAttribute('data-topic') === topic;
			if (on) {
				h.classList.add('is-on');
			} else {
				h.classList.remove('is-on');
			}
		});
		arr(root.querySelectorAll('.arv-cf-topic')).forEach(function (g) {
			var on = g.getAttribute('data-topic') === topic;
			if (on) {
				g.classList.add('is-on');
			} else {
				g.classList.remove('is-on');
			}
			arr(g.querySelectorAll('input')).concat(arr(g.querySelectorAll('textarea'))).forEach(function (i) {
				i.disabled = !on;
			});
		});
		var need = arr(data.required).indexOf(topic) !== -1;
		if (need) {
			root.classList.add('arv-cf-need-event');
		} else {
			root.classList.remove('arv-cf-need-event');
		}
		var ev = root.querySelector('input[name="event"]');
		if (ev) {
			ev.setAttribute('aria-required', need ? 'true' : 'false');
		}
		return need;
	}

	function initV2(form, env) {
		var root = form.querySelector('.arv-cf2');
		var raw = root && root.querySelector('script.arv-cf-data');
		if (!root || !raw) {
			return null;
		}
		var data;
		try {
			data = JSON.parse(raw.textContent);
		} catch (e) {
			return null;
		}
		root.classList.add('arv-cf2-js');
		var select = form.querySelector('select[name="category"]');
		var input = form.querySelector('input[name="event"]');
		var combo = input ? new Combobox(input, data.events || [], data.general || '', env.doc, env) : null;
		var sync = function () {
			applyTopic(root, select ? select.value : '', data);
		};
		if (select) {
			select.addEventListener('change', sync);
		}
		sync();
		return { root: root, combo: combo, sync: sync, data: data };
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------- */

	function boot(doc, win) {
		var env = {
			doc: doc,
			now: function () {
				return Date.now();
			},
			setTimeout: function (f, ms) {
				return win.setTimeout(f, ms);
			},
			setInterval: function (f, ms) {
				return win.setInterval(f, ms);
			}
		};
		Object.defineProperty(env, 'turnstile', {
			get: function () {
				return win.turnstile;
			}
		});
		function start() {
			arr(doc.querySelectorAll('form.wpcf7-form')).forEach(function (form) {
				if (!isOurs(form) || form.arvContact) {
					return;
				}
				form.arvContact = true;
				initGuard(form, env);
				initV2(form, env);
			});
		}
		// Capture on the document runs before CF7's own submit handler.
		doc.addEventListener('submit', function (e) {
			var f = e.target;
			if (f && f.arvGuard && f.arvGuard.onSubmit) {
				f.arvGuard.onSubmit(e);
			}
		}, true);
		if ('loading' === doc.readyState) {
			doc.addEventListener('DOMContentLoaded', start);
		} else {
			start();
		}
	}

	return {
		FORM_ID: FORM_ID,
		MAX_AGE: MAX_AGE,
		WAIT_MAX: WAIT_MAX,
		isOurs: isOurs,
		guardState: guardState,
		initGuard: initGuard,
		filterOptions: filterOptions,
		Combobox: Combobox,
		applyTopic: applyTopic,
		initV2: initV2,
		boot: boot
	};
});
