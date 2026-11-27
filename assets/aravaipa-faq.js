/**
 * FAQ page search, section deep links, the Contact page top block and the
 * contact form topic answers.
 *
 * Same contract as aravaipa-articles.js: every question is already
 * rendered server side as a <details>, this only hides, shows and opens
 * what is already there, and each part no-ops on a page without its
 * markup. A visitor with JS disabled gets a plain, already readable list of
 * accordions.
 *
 * Parts:
 *   1. FAQ page ([data-arv-faq-root]): instant filter, ?q= prefill, and
 *      #question-slug deep links that open and scroll on load, on
 *      hashchange and when a question is opened by hand.
 *   2. Contact page top block ([data-arv-faq-top]): its search box goes to
 *      the FAQ page with ?q=.
 *   3. Contact form topic answers: the FAQ script listens to the form's
 *      category select and shows the 2 or 3 FAQ answers mapped to that
 *      topic, inside the form's own help container (.arv-cf-helps, from the
 *      contact form module) when it exists, else right after the select.
 */
(function (root, factory) {
	'use strict';

	var api = factory();

	if ('object' === typeof module && module.exports) {
		module.exports = api;
	}

	if ('undefined' !== typeof document) {
		api.boot(document, root);
	}
})('undefined' !== typeof window ? window : this, function () {
	'use strict';

	function arr(list) {
		return Array.prototype.slice.call(list || []);
	}

	/* ------------------------------------------------------------------
	 * Pure helpers (unit tested in scripts/test/faq.test.mjs)
	 * ---------------------------------------------------------------- */

	function normalize(text) {
		return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim();
	}

	/**
	 * Every word of the query has to appear somewhere in the haystack, so
	 * "drop bag" finds "Where do I leave my drop bag?" and "bag drop" too.
	 */
	function matches(haystack, query) {
		var q = normalize(query);

		if ('' === q) {
			return true;
		}

		var h = normalize(haystack);
		var words = q.split(' ');

		for (var i = 0; i < words.length; i++) {
			if (-1 === h.indexOf(words[i])) {
				return false;
			}
		}

		return true;
	}

	/** "#race-credits" to "race-credits", or '' for anything unsafe. */
	function hashId(hash) {
		var id = String(hash || '').replace(/^#/, '');

		try {
			id = decodeURIComponent(id);
		} catch (e) {
			return '';
		}

		return /^[a-z0-9][a-z0-9-]{0,80}$/.test(id) ? id : '';
	}

	/** The ?q= value from a location.search string, trimmed and capped. */
	function queryParam(search) {
		var m = /[?&]q=([^&#]*)/.exec(String(search || ''));

		if (!m) {
			return '';
		}

		try {
			return decodeURIComponent(m[1].replace(/\+/g, ' ')).trim().slice(0, 80);
		} catch (e) {
			return '';
		}
	}

	/**
	 * The answers for a topic label from the topic data the server printed:
	 * { topics: { label: slug }, answers: { slug: [ {id, q, a, url} ] } }.
	 */
	function answersFor(data, label) {
		if (!data || !data.topics || !data.answers || !label) {
			return [];
		}

		var slug = data.topics[label];

		return (slug && data.answers[slug]) ? data.answers[slug].slice(0, 3) : [];
	}

	/* ------------------------------------------------------------------
	 * 1. FAQ page
	 * ---------------------------------------------------------------- */

	function initFaq(doc, win) {
		var root = doc.querySelector('[data-arv-faq-root]');

		if (!root) {
			return null;
		}

		var search = root.querySelector('[data-arv-faq-search]');
		var status = root.querySelector('[data-arv-faq-status]');
		var items = arr(doc.querySelectorAll('.arv-faq-q'));
		var sections = arr(doc.querySelectorAll('.arv-faq-section'));

		if (!search || 0 === items.length) {
			return null;
		}

		var openBeforeSearch = [];
		var loggedQueries = {};
		var debounceTimer = null;

		function haystack(item) {
			if (item._arvHaystack) {
				return item._arvHaystack;
			}

			var summary = item.querySelector('summary');
			var answer = item.querySelector('.arv-faq-a');
			var text = (summary ? summary.textContent : '') + ' ' + (item.getAttribute('data-keywords') || '') + ' ' + (answer ? answer.textContent : '');
			item._arvHaystack = normalize(text);

			return item._arvHaystack;
		}

		function apply() {
			var q = normalize(search.value);
			var shown = 0;

			if ('' === q) {
				// Leaving search restores whatever the visitor had open by hand
				// rather than forcing everything shut.
				items.forEach(function (item) {
					item.hidden = false;
					item.open = openBeforeSearch.indexOf(item) !== -1;
				});
				sections.forEach(function (s) {
					s.hidden = false;
				});
				openBeforeSearch = [];

				if (status) {
					status.textContent = '';
				}

				return;
			}

			if (0 === openBeforeSearch.length) {
				items.forEach(function (item) {
					if (item.open) {
						openBeforeSearch.push(item);
					}
				});
			}

			items.forEach(function (item) {
				var hit = matches(haystack(item), q);
				item.hidden = !hit;
				item.open = hit;

				if (hit) {
					shown++;
				}
			});

			sections.forEach(function (s) {
				s.hidden = 0 === s.querySelectorAll('.arv-faq-q:not([hidden])').length;
			});

			if (status) {
				status.textContent = 0 === shown
					? 'No matches. Try different words, or contact us below.'
					: shown + (1 === shown ? ' match' : ' matches');
			}

			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				logSearch(q, shown > 0);
			}, 600);
		}

		function logSearch(q, matched) {
			if (loggedQueries[q] || q.length < 3) {
				return;
			}

			loggedQueries[q] = true;

			if (win.fetch) {
				win.fetch('/wp-json/aravaipa/v1/faq-search-log', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ q: q, matched: matched }),
					keepalive: true
				}).catch(function () {
					// A missed analytics beacon should never surface to the visitor.
				});
			}
		}

		// A link to #some-slug opens that answer and scrolls to it, not just
		// past a closed one. Section ids (#race-day) only scroll.
		function openHash(scroll) {
			var id = hashId(win.location.hash);
			var target = id ? doc.getElementById(id) : null;

			if (!target) {
				return;
			}

			if (target.classList.contains('arv-faq-q')) {
				if (search.value) {
					search.value = '';
					apply();
				}

				target.hidden = false;
				target.open = true;
			}

			if (scroll && target.scrollIntoView) {
				target.scrollIntoView({ block: 'start' });
			}
		}

		search.addEventListener('input', apply);
		win.addEventListener('hashchange', function () {
			openHash(true);
		});

		// Opening a question by hand puts its slug in the address bar, so the
		// visitor can copy a link straight to that answer.
		items.forEach(function (item) {
			item.addEventListener('toggle', function () {
				if (item.open && item.id && !search.value && win.history && win.history.replaceState) {
					win.history.replaceState(null, '', '#' + item.id);
				}
			});
		});

		var preset = queryParam(win.location.search);

		if (preset) {
			search.value = preset;
			apply();
		}

		if (win.location.hash) {
			openHash(true);
		}

		return { apply: apply, openHash: openHash };
	}

	/* ------------------------------------------------------------------
	 * 2 and 3. Contact page: top block and topic answers
	 * ---------------------------------------------------------------- */

	function readTopicData(doc) {
		var raw = doc.querySelector('script.arv-faq-topic-data');

		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw.textContent);
		} catch (e) {
			return null;
		}
	}

	function renderAnswers(doc, box, list) {
		while (box.firstChild) {
			box.removeChild(box.firstChild);
		}

		box.hidden = 0 === list.length;

		if (0 === list.length) {
			return;
		}

		var head = doc.createElement('p');
		head.className = 'arv-faq-topic__head';
		head.textContent = 'These might answer it:';
		box.appendChild(head);

		list.forEach(function (item) {
			var d = doc.createElement('details');
			d.className = 'arv-faq-q arv-faq-topic__q';
			var s = doc.createElement('summary');
			s.textContent = item.q;
			var a = doc.createElement('div');
			a.className = 'arv-faq-a';
			// Answer HTML comes from our own FAQ page content, escaped and
			// reduced to links and paragraphs on the server (arv_faq_safe_html).
			a.innerHTML = item.a;
			var more = doc.createElement('p');
			var link = doc.createElement('a');
			link.href = item.url;
			link.textContent = 'Open in the FAQ';
			more.appendChild(link);
			a.appendChild(more);
			d.appendChild(s);
			d.appendChild(a);
			box.appendChild(d);
		});
	}

	function initTopics(doc) {
		var data = readTopicData(doc);

		if (!data) {
			return [];
		}

		return arr(doc.querySelectorAll('form.wpcf7-form')).map(function (form) {
			var select = form.querySelector('select[name="category"]');

			if (!select) {
				return null;
			}

			var box = doc.createElement('div');
			box.className = 'arv-faq-topic';
			box.setAttribute('aria-live', 'polite');
			box.hidden = true;

			var helps = form.querySelector('.arv-cf-helps');

			if (helps) {
				helps.appendChild(box);
			} else {
				var anchor = select.closest ? (select.closest('p') || select) : select;
				anchor.parentNode.insertBefore(box, anchor.nextSibling);
			}

			var sync = function () {
				renderAnswers(doc, box, answersFor(data, select.value));
			};

			select.addEventListener('change', sync);
			sync();

			return { form: form, box: box, sync: sync };
		}).filter(Boolean);
	}

	function boot(doc, win) {
		var faq = initFaq(doc, win || {});
		var topics = initTopics(doc);

		return { faq: faq, topics: topics };
	}

	return {
		boot: boot,
		matches: matches,
		hashId: hashId,
		queryParam: queryParam,
		answersFor: answersFor,
		normalize: normalize
	};
});
