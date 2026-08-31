/**
 * Pull the Square catalogue, keep only what the storefront actually
 * publishes, and push it to WordPress.
 *
 * Runs here rather than in the plugin on purpose. The Square access token
 * can read a merchant account, so it stays on this machine and never
 * reaches the web server, exactly like the results, race and photo
 * importers keep their own credentials out of WordPress.
 *
 * The verification step is the point of this script. Square reports items
 * as ecom_visibility VISIBLE that have no storefront page at all, and a
 * product URL built from the catalogue id alone returns HTTP 200 while
 * showing the shop's front door, so "it responded" proves nothing. The
 * storefront's sitemap is the only honest list of pages that exist, so
 * every product and every collection is matched against it and anything
 * unmatched is dropped rather than linked.
 *
 *   bun run scripts/fetch-shop.mjs --dry-run
 *   bun run scripts/fetch-shop.mjs
 */

const SQUARE_API = 'https://connect.squareup.com';
const SQUARE_VERSION = '2026-06-18';
const STOREFRONT = 'https://aravaipa-shop.square.site';

const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const force = args.includes('--force');

const token = process.env.SQUARE_ACCESS_TOKEN;
const location = process.env.SQUARE_LOCATION_ID;
const wpUrl = process.env.ARAVAIPA_WP_URL || 'https://www.aravaiparunning.com';
const wpUser = process.env.ARAVAIPA_WP_USER;
const wpPass = process.env.ARAVAIPA_WP_APP_PASSWORD;

if (!token || !location) {
	console.error('SQUARE_ACCESS_TOKEN and SQUARE_LOCATION_ID must be set');
	process.exit(1);
}

/**
 * Square's catalogue, paged.
 */
async function catalog() {
	const objects = [];
	let cursor = '';

	do {
		const url = `${SQUARE_API}/v2/catalog/list?types=ITEM,CATEGORY,IMAGE` + (cursor ? `&cursor=${cursor}` : '');
		const res = await fetch(url, {
			headers: {
				Authorization: `Bearer ${token}`,
				'Square-Version': SQUARE_VERSION,
			},
		});

		if (!res.ok) {
			throw new Error(`Square catalog ${res.status}: ${await res.text()}`);
		}

		const body = await res.json();
		objects.push(...(body.objects || []));
		cursor = body.cursor || '';
	} while (cursor);

	return objects;
}

/**
 * Which variations are out of stock at the online location.
 *
 * Square reports a count per variation, so an item is only sold out when
 * every one of its variations is: a tee with one size left is still worth
 * a card, it just cannot claim to have all of them.
 */
async function soldOut() {
	const out = new Set();
	let cursor = '';

	do {
		const res = await fetch(`${SQUARE_API}/v2/inventory/counts/batch-retrieve`, {
			method: 'POST',
			headers: {
				Authorization: `Bearer ${token}`,
				'Square-Version': SQUARE_VERSION,
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({ location_ids: [location], cursor: cursor || undefined }),
		});

		if (!res.ok) {
			throw new Error(`Square inventory ${res.status}: ${await res.text()}`);
		}

		const body = await res.json();

		for (const count of body.counts || []) {
			if (count.state === 'IN_STOCK' && Number(count.quantity) <= 0) {
				out.add(count.catalog_object_id);
			}
		}

		cursor = body.cursor || '';
	} while (cursor);

	return out;
}

/**
 * Every page the storefront actually publishes, by its trailing id.
 *
 * The sitemap is the allow-list. Nothing reaches WordPress that is not in
 * here, because everything else is a link that looks fine and lands on the
 * shop's front door.
 */
async function published() {
	const res = await fetch(`${STOREFRONT}/sitemap.xml`);

	if (!res.ok) {
		throw new Error(`sitemap ${res.status}`);
	}

	const xml = await res.text();
	const products = new Map();
	const collections = new Map();

	for (const [, url, id] of xml.matchAll(/<loc>(https:\/\/[^<]*\/product\/[a-z0-9-]+\/([A-Za-z0-9]+))<\/loc>/g)) {
		products.set(id, url);
	}

	for (const [, url, id] of xml.matchAll(/<loc>(https:\/\/[^<]*\/shop\/[a-z0-9-]+\/([A-Za-z0-9]+))<\/loc>/g)) {
		collections.set(id, url);
	}

	return { products, collections };
}

/**
 * A department rather than a race.
 *
 * Square's categories mix the two and nothing in the data distinguishes
 * them, so this is the one judgement in the pipeline. Kept as an explicit
 * list rather than a heuristic: a wrong guess here puts "Men's" on a race
 * page, and the list is short enough to simply be correct.
 */
const DEPARTMENTS = new Set([
	'mens', 'womens', 'accessories', 'headwear', 'socks', 'hydration', 'lighting',
	'socks-hats', 'tops', 'bottoms', 'seasonal-collections', 'race-merch',
	'arizona-races', 'california-races', 'utah-races', 'colorado-races',
	'nevada-races', 'road-races', 'fixed-time-races', 'retail-sales',
	'2026-aravaipa-spring-summer-collection', '2026-spring-summer-classic-collection',
]);

const slug = (name) =>
	name.toLowerCase().replace(/['’]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const objects = await catalog();
const out = await soldOut();
const live = await published();

const images = new Map();
for (const o of objects) {
	if (o.type === 'IMAGE' && o.image_data?.url) {
		images.set(o.id, o.image_data.url);
	}
}

const online = (item) =>
	item.present_at_all_locations || (item.present_at_location_ids || []).includes(location);

const items = objects.filter(
	(o) => o.type === 'ITEM' && o.item_data?.ecom_visibility === 'VISIBLE' && online(o)
);

const counts = new Map();
const products = [];
let skippedProducts = 0;

for (const item of items) {
	const url = item.item_data.ecom_uri || live.products.get(item.id);

	if (!url) {
		skippedProducts++;
		continue;
	}

	const variations = item.item_data.variations || [];
	const priced = variations
		.map((v) => v.item_variation_data?.price_money?.amount)
		.filter((a) => typeof a === 'number' && a > 0);

	const ids = (item.item_data.categories || []).map((c) => c.id);
	if (!ids.length && item.item_data.category_id) ids.push(item.item_data.category_id);

	for (const id of ids) counts.set(id, (counts.get(id) || 0) + 1);

	// One size, one colour, whatever Square calls the only variation an item
	// has, is not worth showing as a chip: it is the same information the
	// price already carries. Only items with more than one are worth an
	// option list, and "Regular" alone would just read as a second price.
	const options =
		variations.length > 1
			? variations.map((v) => ({
					name: v.item_variation_data?.name || '',
					price: v.item_variation_data?.price_money?.amount || 0,
					sold_out: out.has(v.id),
				}))
			: [];

	products.push({
		id: item.id,
		name: item.item_data.name,
		url,
		image: images.get((item.item_data.image_ids || [])[0]) || item.item_data.ecom_image_uris?.[0] || '',
		desc: item.item_data.description_plaintext || '',
		price: priced.length ? Math.min(...priced) : 0,
		sold_out: variations.length > 0 && variations.every((v) => out.has(v.id)),
		options,
		collections: ids,
	});
}

const collections = [];
let skippedCollections = 0;

for (const o of objects) {
	if (o.type !== 'CATEGORY') continue;

	const url = live.collections.get(o.id);

	if (!url) {
		skippedCollections++;
		continue;
	}

	const name = o.category_data.name;

	collections.push({
		id: o.id,
		name,
		url,
		image: images.get((o.category_data.image_ids || [])[0]) || '',
		count: counts.get(o.id) || 0,
		race: !DEPARTMENTS.has(slug(name)),
	});
}

console.log(`products: ${products.length} kept, ${skippedProducts} with no published page`);
console.log(`collections: ${collections.length} kept, ${skippedCollections} with no published page`);
console.log(`  races: ${collections.filter((c) => c.race).length}, departments: ${collections.filter((c) => !c.race).length}`);
console.log(`sold out: ${products.filter((p) => p.sold_out).length}`);

if (!wpUser || !wpPass) {
	console.error('ARAVAIPA_WP_USER and ARAVAIPA_WP_APP_PASSWORD must be set to push');
	process.exit(1);
}

const res = await fetch(`${wpUrl}/wp-json/aravaipa/v1/shop/import`, {
	method: 'POST',
	headers: {
		Authorization: 'Basic ' + Buffer.from(`${wpUser}:${wpPass}`).toString('base64'),
		'Content-Type': 'application/json',
	},
	body: JSON.stringify({ collections, products, dry_run: dryRun, force }),
});

console.log(res.status, await res.text());
