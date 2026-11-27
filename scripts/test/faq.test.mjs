/**
 * FAQ browser script (assets/aravaipa-faq.js), pure parts: the search
 * filter, the deep link id, the ?q= prefill and the topic answers lookup.
 * Behaviour in a real browser is covered by the job's Playwright stills.
 *
 *   node --test scripts/test/faq.test.mjs
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const faq = require('../../assets/aravaipa-faq.js');

test('exports the pure helpers and loads without a DOM', () => {
	for (const k of ['boot', 'matches', 'hashId', 'queryParam', 'answersFor', 'normalize']) {
		assert.equal(typeof faq[k], 'function', k);
	}
});

test('filter: every word must appear, any order, case and spaces ignored', () => {
	const h = 'Where do I leave my drop bag? drop bags aid station';
	assert.ok(faq.matches(h, 'drop bag'));
	assert.ok(faq.matches(h, 'BAG   drop'));
	assert.ok(faq.matches(h, '  '));
	assert.ok(!faq.matches(h, 'drop dog'));
	assert.ok(!faq.matches('', 'x'));
});

test('deep link id: only safe lowercase slugs', () => {
	assert.equal(faq.hashId('#race-credits'), 'race-credits');
	assert.equal(faq.hashId('race-day'), 'race-day');
	assert.equal(faq.hashId(''), '');
	assert.equal(faq.hashId('#Race'), '');
	assert.equal(faq.hashId('#a"b'), '');
	assert.equal(faq.hashId('#%E0%A4%A'), '');
	assert.equal(faq.hashId('#' + 'a'.repeat(90)), '');
});

test('?q= prefill: decoded, plus as space, trimmed, capped', () => {
	assert.equal(faq.queryParam('?q=race+credit'), 'race credit');
	assert.equal(faq.queryParam('?x=1&q=drop%20bag&y=2'), 'drop bag');
	assert.equal(faq.queryParam('?x=1'), '');
	assert.equal(faq.queryParam('?q=%E0%A4%A'), '');
	assert.equal(faq.queryParam('?q=' + 'a'.repeat(120)).length, 80);
});

test('topic answers: label to slug to at most three answers', () => {
	const a = (id) => ({ id, q: id, a: '<p>x</p>', url: '/faq/#' + id });
	const data = {
		topics: { 'Registration, refunds or transfers': 'registration', 'Lost and found': 'lost' },
		answers: { registration: [a('change-distance'), a('race-credits'), a('withdraw'), a('refund-policy')] }
	};
	assert.deepEqual(faq.answersFor(data, 'Registration, refunds or transfers').map((x) => x.id), ['change-distance', 'race-credits', 'withdraw']);
	assert.deepEqual(faq.answersFor(data, 'Lost and found'), []);
	assert.deepEqual(faq.answersFor(data, ''), []);
	assert.deepEqual(faq.answersFor(null, 'Lost and found'), []);
});

test('script keeps the deep link and topic hooks, no em dashes', () => {
	const src = readFileSync(new URL('../../assets/aravaipa-faq.js', import.meta.url), 'utf8');
	assert.ok(src.includes('hashchange'));
	assert.ok(src.includes('select[name="category"]'));
	assert.ok(src.includes('.arv-cf-helps'));
	assert.ok(!src.includes('—'));
});
