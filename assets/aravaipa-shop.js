/**
 * The item detail drawer: a product click opens sizes, a description and
 * a "View on Square" link on the same page, instead of leaving the site
 * for a bare product photo and a price.
 *
 * Every card is a real link to Square first, target="_blank" and all, so
 * the whole feature degrades to "click through and buy" with this script
 * absent. This only upgrades the click into a drawer that opens in place.
 *
 * The collection tiles need no script at all: they are <details>, which
 * every browser already knows how to open and close.
 */
(function () {
	var drawer = document.querySelector('[data-arv-shop-detail]');

	if (!drawer) {
		return;
	}

	var img = drawer.querySelector('[data-arv-shop-detail-img]');
	var name = drawer.querySelector('[data-arv-shop-detail-name]');
	var price = drawer.querySelector('[data-arv-shop-detail-price]');
	var desc = drawer.querySelector('[data-arv-shop-detail-desc]');
	var options = drawer.querySelector('[data-arv-shop-detail-options]');
	var buy = drawer.querySelector('[data-arv-shop-detail-buy]');
	var lastFocus = null;

	function open(data) {
		if (data.image) {
			img.src = data.image;
			img.alt = data.name || '';
			img.hidden = false;
		} else {
			img.hidden = true;
			img.src = '';
		}

		name.textContent = data.name || '';
		price.textContent = data.soldOut ? 'Sold out' : data.price || '';

		if (data.desc) {
			desc.textContent = data.desc;
			desc.hidden = false;
		} else {
			desc.hidden = true;
		}

		options.innerHTML = '';

		if (data.options && data.options.length) {
			for (var i = 0; i < data.options.length; i++) {
				var opt = data.options[i];
				var li = document.createElement('li');
				li.className = 'arv-shop__detail-option' + (opt.soldOut ? ' arv-shop__detail-option--out' : '');
				li.textContent = opt.name;
				options.appendChild(li);
			}

			options.hidden = false;
		} else {
			options.hidden = true;
		}

		buy.href = data.url;

		lastFocus = document.activeElement;
		drawer.hidden = false;
		drawer.querySelector('.arv-shop__detail-close').focus();
		document.addEventListener('keydown', onKeydown);
	}

	function close() {
		drawer.hidden = true;
		document.removeEventListener('keydown', onKeydown);

		if (lastFocus) {
			lastFocus.focus();
		}
	}

	function onKeydown(event) {
		if (event.key === 'Escape') {
			close();
		}
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-arv-shop-item]');

		if (link) {
			var data;

			try {
				data = JSON.parse(link.getAttribute('data-arv-shop-item'));
			} catch (err) {
				return; // Malformed payload: fall through to the real link.
			}

			event.preventDefault();
			open(data);
			return;
		}

		if (event.target.closest('[data-arv-shop-detail-close]')) {
			close();
		}
	});
})();
