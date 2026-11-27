// Contact Us browser helper (assets/contact-form.js) on a tiny fake DOM.
// Covers: the combobox (ARIA roles, keyboard, 'jav' finds Javelina, free
// text kept, General last), help and topic fields toggling with the
// category, required event marking, and the Turnstile submit guard
// (empty or stale token held, one resubmit, never a loop).
//
// Run: node --test scripts/test/contact-form*.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const api = require('../../assets/contact-form.js');

// ---------------------------------------------------------------- fake DOM
function parseSel(sel) {
	const m = sel.trim().match(/^([a-z0-9]*)((?:\.[\w-]+)*)((?:\[[\w-]+(?:="[^"]*")?\])*)$/i);
	if (!m) throw new Error('unsupported selector ' + sel);
	return {
		tag: m[1].toLowerCase(),
		classes: (m[2].match(/\.[\w-]+/g) || []).map((c) => c.slice(1)),
		attrs: (m[3].match(/\[[^\]]+\]/g) || []).map((a) => {
			const x = a.slice(1, -1).split('=');
			return [x[0], x.length > 1 ? x[1].replace(/^"|"$/g, '') : null];
		}),
	};
}
class Text {
	constructor(t) { this.nodeType = 3; this.data = t; this.parentNode = null; }
	get textContent() { return this.data; }
}
class El {
	constructor(tag, doc) {
		this.tagName = tag.toUpperCase();
		this.ownerDocument = doc;
		this.attrs = {};
		this.childNodes = [];
		this.parentNode = null;
		this.listeners = {};
		this.hidden = false;
		this.value = '';
		this.disabled = false;
		this.nodeType = 1;
		const self = this;
		this.classList = {
			add: (c) => { const s = new Set(self.className.split(/\s+/).filter(Boolean)); s.add(c); self.className = [...s].join(' '); },
			remove: (c) => { self.className = self.className.split(/\s+/).filter((x) => x && x !== c).join(' '); },
			contains: (c) => self.className.split(/\s+/).includes(c),
		};
	}
	get className() { return this.attrs.class || ''; }
	set className(v) { this.attrs.class = v; }
	setAttribute(k, v) { if (k === 'value') this.value = String(v); else this.attrs[k] = String(v); }
	getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }
	removeAttribute(k) { delete this.attrs[k]; }
	hasAttribute(k) { return k in this.attrs; }
	appendChild(c) { if (c.parentNode) c.parentNode.removeChild(c); c.parentNode = this; this.childNodes.push(c); return c; }
	removeChild(c) { this.childNodes = this.childNodes.filter((x) => x !== c); c.parentNode = null; return c; }
	get firstChild() { return this.childNodes[0] || null; }
	get children() { return this.childNodes.filter((c) => c.nodeType === 1); }
	get textContent() { return this.childNodes.map((c) => c.textContent).join(''); }
	set textContent(t) { this.childNodes = []; if (t !== '') this.appendChild(new Text(String(t))); }
	get form() { let p = this.parentNode; while (p && p.tagName !== 'FORM') p = p.parentNode; return p; }
	matches(sel) {
		return sel.split(',').some((one) => {
			const s = parseSel(one);
			if (s.tag && s.tag.toUpperCase() !== this.tagName) return false;
			if (!s.classes.every((c) => this.classList.contains(c))) return false;
			return s.attrs.every(([k, v]) => {
				const have = k === 'value' ? this.value : this.getAttribute(k);
				return v === null ? have !== null : have === v;
			});
		});
	}
	querySelectorAll(sel) {
		const out = [];
		const walk = (n) => n.children.forEach((c) => { if (c.matches(sel)) out.push(c); walk(c); });
		walk(this);
		return out;
	}
	querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
	addEventListener(t, f, cap) { (this.listeners[t] ||= []).push({ f, cap: !!cap }); }
	dispatchEvent(e) {
		e.target ||= this;
		const path = [];
		for (let n = this; n; n = n.parentNode) path.push(n);
		const doc = this.ownerDocument;
		if (path[path.length - 1] !== doc) path.push(doc);
		const run = (node, cap) => {
			for (const l of node.listeners[e.type] || []) {
				if (cap !== null && l.cap !== cap) continue;
				if (e._stop) return;
				e.currentTarget = node;
				l.f(e);
			}
		};
		for (let i = path.length - 1; i > 0 && !e._stopProp; i--) run(path[i], true);
		if (!e._stopProp) run(path[0], null);
		if (e.bubbles) for (let i = 1; i < path.length && !e._stopProp; i++) run(path[i], false);
		return !e.defaultPrevented;
	}
	requestSubmit(submitter) {
		this.submits = (this.submits || 0) + 1;
		const e = new Ev('submit', true, true);
		e.submitter = submitter;
		this.dispatchEvent(e);
	}
}
class Ev {
	constructor(type, bubbles = true, cancelable = true) { this.type = type; this.bubbles = bubbles; this.cancelable = cancelable; this.defaultPrevented = false; }
	initEvent(t, b, c) { this.type = t; this.bubbles = b; this.cancelable = c; }
	preventDefault() { this.defaultPrevented = true; }
	stopPropagation() { this._stopProp = true; }
	stopImmediatePropagation() { this._stopProp = true; this._stop = true; }
}
class Doc extends El {
	constructor() { super('#document', null); this.ownerDocument = this; this.readyState = 'complete'; this.parentNode = null; }
	createElement(t) { return new El(t, this); }
	createTextNode(t) { return new Text(t); }
	createEvent() { return new Ev('x'); }
}
function h(doc, tag, attrs = {}, kids = []) {
	const e = doc.createElement(tag);
	for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v);
	kids.forEach((k) => e.appendChild(typeof k === 'string' ? doc.createTextNode(k) : k));
	return e;
}

const DATA = {
	events: ['Merry Vertmas', 'Javelina Jundred', 'Jackpot Ultras', 'Black Canyon Ultras', 'Frenzy Hills Rides', 'General, not about a race'],
	general: 'General, not about a race',
	required: ['Registration, refunds or transfers', 'Race day, results or timing', 'Lost and found', 'Volunteering'],
	fields: { 'Lost and found': ['lf-item', 'lf-where', 'lf-photo'] },
};

function buildForm({ v2 = true, turnstile = true } = {}) {
	const doc = new Doc();
	const body = h(doc, 'body');
	doc.appendChild(body);
	const form = h(doc, 'form', { class: 'wpcf7-form init' });
	body.appendChild(form);
	form.appendChild(h(doc, 'input', { type: 'hidden', name: '_wpcf7', value: '84199' }));
	const root = h(doc, 'div', { class: 'arv-cf2' });
	form.appendChild(root);
	const select = h(doc, 'select', { name: 'category' });
	root.appendChild(select);
	if (v2) {
		const helps = h(doc, 'div', { class: 'arv-cf-helps' });
		['Registration, refunds or transfers', 'Race day, results or timing', 'Lost and found', 'Volunteering', 'Something else'].forEach((t) => {
			helps.appendChild(h(doc, 'div', { class: 'arv-cf-help', 'data-topic': t }, [t + ' help']));
		});
		root.appendChild(helps);
		const s = h(doc, 'script', { type: 'application/json', class: 'arv-cf-data' });
		s.textContent = JSON.stringify(DATA);
		root.appendChild(s);
	}
	const wrap = h(doc, 'span', { class: 'wpcf7-form-control-wrap', 'data-name': 'event' });
	const ev = h(doc, 'input', { type: 'text', name: 'event', id: 'arv-cf-event' });
	wrap.appendChild(ev);
	root.appendChild(h(doc, 'p', { class: 'arv-cf-row arv-cf-event' }, [wrap]));
	const reg = h(doc, 'div', { class: 'arv-cf-topic', 'data-topic': 'Registration, refunds or transfers' }, [h(doc, 'input', { name: 'reg-email' })]);
	const lf = h(doc, 'div', { class: 'arv-cf-topic', 'data-topic': 'Lost and found' }, [h(doc, 'input', { name: 'lf-item' }), h(doc, 'input', { name: 'lf-photo', type: 'file' })]);
	root.appendChild(reg);
	root.appendChild(lf);
	let tok = null;
	if (turnstile) {
		const w = h(doc, 'div', { class: 'wpcf7-turnstile cf-turnstile' });
		tok = h(doc, 'input', { type: 'hidden', name: '_wpcf7_turnstile_response' });
		w.appendChild(tok);
		root.appendChild(h(doc, 'p', { class: 'arv-cf-row' }, [w]));
	}
	return { doc, form, root, select, ev, reg, lf, tok };
}

function fakeEnv(doc) {
	const env = {
		doc,
		t: 0,
		timeouts: [],
		resets: 0,
		now: () => env.t,
		setTimeout: (f, ms) => env.timeouts.push({ f, at: env.t + ms }),
		setInterval: () => 0,
		turnstile: { reset: () => { env.resets++; } },
	};
	env.advance = (ms) => {
		const end = env.t + ms;
		for (;;) {
			env.timeouts.sort((a, b) => a.at - b.at);
			const nx = env.timeouts[0];
			if (!nx || nx.at > end) break;
			env.timeouts.shift();
			env.t = nx.at;
			nx.f();
		}
		env.t = end;
	};
	return env;
}

function key(el, k) {
	const e = new Ev('keydown');
	e.key = k;
	el.dispatchEvent(e);
	return e;
}
function fire(el, type) {
	const e = new Ev(type);
	el.dispatchEvent(e);
	return e;
}
const visible = (c) => c.items.map((li) => li.getAttribute('data-value'));

// ---------------------------------------------------------------- tests
test('filterOptions: case-insensitive substring, General always last', () => {
	assert.deepEqual(api.filterOptions(DATA.events, 'jav', DATA.general), ['Javelina Jundred', DATA.general]);
	assert.deepEqual(api.filterOptions(DATA.events, 'ULTRAS', DATA.general), ['Jackpot Ultras', 'Black Canyon Ultras', DATA.general]);
	assert.equal(api.filterOptions(DATA.events, '', DATA.general).length, DATA.events.length);
	assert.deepEqual(api.filterOptions(DATA.events, 'zzz', DATA.general), [DATA.general]);
});

test('combobox: ARIA wiring, jav finds Javelina, keyboard nav and Enter picks', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	const v = api.initV2(f.form, env);
	const c = v.combo;
	assert.equal(f.ev.getAttribute('role'), 'combobox');
	assert.equal(f.ev.getAttribute('aria-expanded'), 'false');
	assert.equal(f.ev.getAttribute('aria-autocomplete'), 'list');
	const listId = f.ev.getAttribute('aria-controls');
	assert.equal(c.list.getAttribute('id'), listId);
	assert.equal(c.list.getAttribute('role'), 'listbox');

	f.ev.value = 'jav';
	fire(f.ev, 'input');
	assert.equal(f.ev.getAttribute('aria-expanded'), 'true');
	assert.equal(c.list.hidden, false);
	assert.deepEqual(visible(c), ['Javelina Jundred', DATA.general]);
	assert.equal(c.items[0].getAttribute('role'), 'option');
	assert.equal(c.items[0].querySelector('mark').textContent, 'Jav');

	key(f.ev, 'ArrowDown');
	assert.equal(f.ev.getAttribute('aria-activedescendant'), c.items[0].getAttribute('id'));
	assert.equal(c.items[0].getAttribute('aria-selected'), 'true');
	key(f.ev, 'ArrowDown');
	assert.equal(f.ev.getAttribute('aria-activedescendant'), c.items[1].getAttribute('id'));
	assert.equal(c.items[0].getAttribute('aria-selected'), 'false');
	key(f.ev, 'ArrowDown');
	assert.equal(f.ev.getAttribute('aria-activedescendant'), c.items[0].getAttribute('id'), 'wraps');
	key(f.ev, 'ArrowUp');
	assert.equal(f.ev.getAttribute('aria-activedescendant'), c.items[1].getAttribute('id'));
	key(f.ev, 'ArrowUp');
	const e = key(f.ev, 'Enter');
	assert.ok(e.defaultPrevented, 'Enter on an option does not submit the form');
	assert.equal(f.ev.value, 'Javelina Jundred');
	assert.equal(f.ev.getAttribute('aria-expanded'), 'false');
	assert.equal(f.ev.hasAttribute('aria-activedescendant'), false);
});

test('combobox: free text kept, Escape closes, click picks', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	const c = api.initV2(f.form, env).combo;
	f.ev.value = 'My club fun run';
	fire(f.ev, 'input');
	assert.deepEqual(visible(c), [DATA.general]);
	key(f.ev, 'Enter');
	assert.equal(f.ev.value, 'My club fun run', 'Enter with no option keeps the typed text');
	fire(f.ev, 'focus');
	assert.equal(c.isOpen(), true);
	const esc = key(f.ev, 'Escape');
	assert.ok(esc.defaultPrevented);
	assert.equal(c.isOpen(), false);
	assert.equal(f.ev.value, 'My club fun run');
	fire(f.ev, 'blur');
	env.advance(200);
	assert.equal(f.ev.value, 'My club fun run', 'blur keeps free text');
	f.ev.value = 'canyon';
	fire(f.ev, 'input');
	fire(c.items[0], 'click');
	assert.equal(f.ev.value, 'Black Canyon Ultras');
	assert.equal(c.isOpen(), false);
});

test('help, topic fields and required event toggle with the category', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	const v = api.initV2(f.form, env);
	assert.ok(f.root.classList.contains('arv-cf2-js'));
	const on = () => f.root.querySelectorAll('.arv-cf-help').filter((x) => x.classList.contains('is-on')).map((x) => x.getAttribute('data-topic'));
	assert.deepEqual(on(), []);
	assert.equal(f.lf.querySelector('input').disabled, true, 'hidden topic fields disabled so they never block');

	f.select.value = 'Lost and found';
	fire(f.select, 'change');
	assert.deepEqual(on(), ['Lost and found']);
	assert.ok(f.lf.classList.contains('is-on'));
	assert.equal(f.lf.querySelector('input[name="lf-item"]').disabled, false);
	assert.equal(f.reg.classList.contains('is-on'), false);
	assert.equal(f.reg.querySelector('input').disabled, true);
	assert.ok(f.root.classList.contains('arv-cf-need-event'));
	assert.equal(f.ev.getAttribute('aria-required'), 'true');

	f.select.value = 'Registration, refunds or transfers';
	fire(f.select, 'change');
	assert.deepEqual(on(), ['Registration, refunds or transfers']);
	assert.equal(f.reg.querySelector('input').disabled, false);
	assert.equal(f.lf.querySelector('input').disabled, true);

	f.select.value = 'Something else';
	fire(f.select, 'change');
	assert.deepEqual(on(), ['Something else']);
	assert.equal(f.root.classList.contains('arv-cf-need-event'), false);
	assert.equal(f.ev.getAttribute('aria-required'), 'false');
	assert.ok(v.data.events.includes('Javelina Jundred'));
});

test('initV2 does nothing on the stored (v1) form', () => {
	const f = buildForm({ v2: false });
	assert.equal(api.initV2(f.form, fakeEnv(f.doc)), null);
	assert.equal(f.ev.getAttribute('role'), null);
});

test('guardState', () => {
	assert.equal(api.guardState(false, '', 0, 0), 'pass');
	assert.equal(api.guardState(true, '', 0, 0), 'empty');
	assert.equal(api.guardState(true, 'tok', 1000, 1000 + api.MAX_AGE + 1), 'stale');
	assert.equal(api.guardState(true, 'tok', 1000, 2000), 'pass');
});

function wireSubmit(f, env) {
	const st = api.initGuard(f.form, env);
	f.doc.addEventListener('submit', (e) => st.onSubmit(e), true);
	let cf7 = 0;
	f.form.addEventListener('submit', (e) => { e.preventDefault(); cf7++; });
	return { st, sent: () => cf7 };
}

test('guard: empty token held, sent once when the token arrives', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	const g = wireSubmit(f, env);
	f.form.dispatchEvent(new Ev('submit'));
	assert.equal(g.sent(), 0, 'CF7 did not see the tokenless submit');
	assert.ok(f.form.querySelector('.arv-cf-wait'), 'wait message shown');
	f.form.dispatchEvent(new Ev('submit'));
	assert.equal(g.sent(), 0, 'second click while waiting is swallowed');
	env.advance(1000);
	assert.equal(g.sent(), 0);
	f.tok.value = 'fresh-token';
	env.advance(300);
	assert.equal(g.sent(), 1, 'resubmitted once');
	assert.equal(f.form.submits, 1);
	assert.equal(f.form.querySelector('.arv-cf-wait'), null, 'wait message removed');
	env.advance(60000);
	assert.equal(g.sent(), 1, 'never loops');
});

test('guard: fresh token passes straight through', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	f.tok.value = 'tok';
	const g = wireSubmit(f, env);
	env.t = 5000;
	f.form.dispatchEvent(new Ev('submit'));
	assert.equal(g.sent(), 1);
	assert.equal(f.form.submits || 0, 0);
});

test('guard: stale token resets the widget, waits for a new one, resubmits once', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	f.tok.value = 'old';
	const g = wireSubmit(f, env);
	env.t = api.MAX_AGE + 5000;
	f.form.dispatchEvent(new Ev('submit'));
	assert.equal(g.sent(), 0);
	assert.equal(env.resets, 1, 'widget reset');
	f.tok.value = '';
	env.advance(500);
	f.tok.value = 'new';
	env.advance(500);
	assert.equal(g.sent(), 1);
	assert.equal(f.form.submits, 1);
});

test('guard: no token after 30 s still sends once (server reports), no loop', () => {
	const f = buildForm();
	const env = fakeEnv(f.doc);
	const g = wireSubmit(f, env);
	f.form.dispatchEvent(new Ev('submit'));
	env.advance(api.WAIT_MAX + 1000);
	assert.equal(g.sent(), 1);
	assert.equal(f.form.submits, 1);
	env.advance(api.WAIT_MAX * 3);
	assert.equal(f.form.submits, 1);
});

test('guard: form without Turnstile is never held', () => {
	const f = buildForm({ turnstile: false });
	const env = fakeEnv(f.doc);
	const g = wireSubmit(f, env);
	f.form.dispatchEvent(new Ev('submit'));
	assert.equal(g.sent(), 1);
});

test('isOurs only for 84199', () => {
	const f = buildForm();
	assert.equal(api.isOurs(f.form), true);
	f.form.querySelector('input[name="_wpcf7"]').value = '87176';
	assert.equal(api.isOurs(f.form), false);
});
