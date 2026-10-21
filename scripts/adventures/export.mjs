// Export the approved v3 Astro draft's data (trips.js, faqs.js, photos.json,
// credits) into one JSON file for import.php. Photo references stay as keys
// (path under public/photos without .jpg); import.php swaps them for Media
// Library IDs after uploading.
//
//   node scripts/adventures/export.mjs <site dir> > adventures.json
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const site = path.resolve(process.argv[2] || '.');
const { trips } = await import(pathToFileURL(path.join(site, 'src/data/trips.js')));
const { faqs } = await import(pathToFileURL(path.join(site, 'src/data/faqs.js')));
const wm = JSON.parse(fs.readFileSync(path.join(site, 'src/data/photos.json'), 'utf8'));
const pub = path.join(site, 'public');

// Upload the full-size original when there is one, not the 900px -sm copy.
const fileFor = (key) => {
  const full = key.replace(/-sm$/, '');
  for (const k of [full, key]) {
    const f = path.join(pub, 'photos', k + '.jpg');
    if (fs.existsSync(f)) return { key: k, file: f };
  }
  const f = path.join(pub, key);
  if (fs.existsSync(f)) return { key, file: f };
  throw new Error('missing photo ' + key);
};
const files = {};
const ph = (key, alt = '', credit = '') => {
  const { key: k, file } = fileFor(key);
  files[k] = file;
  return { key: k, alt, credit };
};

// Credits by base key, same text as src/pages/credits.astro.
const arv = [
  ['Cocodona 250 Training Camp hero and home tile, Sedona slickrock (2025)', 'coco-sedona-slickrock', 'Scott Rokis'],
  ['Cocodona camp story photo, Sedona slickrock view (2025)', 'coco-sedona-view', 'Scott Rokis'],
  ['Cocodona camp gallery, Cathedral Rock at golden hour (2024)', 'coco-cathedral', 'Scott Rokis'],
  ['Cocodona camp gallery, high trail (2021)', 'coco-bradshaws', 'Eliza Earle'],
  ['Cocodona camp gallery, pines above Flagstaff (2023)', 'coco-flagstaff-pines', null],
  ['Cocodona camp gallery, high desert at golden hour (2024)', 'coco-golden-hour', 'Scott Rokis'],
  ['Cocodona camp interest form, evening in the high country (2021)', 'coco-high-country', 'Scott Rokis'],
];
const tra = [
  ['Adventures hero, Fitz Roy Frontier 2027 hero, gallery', 'fitzroy-runner', 'Ultra Fitz Roy proposal (Trail Run Adventures)'],
  ['Fitz Roy Frontier 2028 hero, gallery', 'fitzroy-kayak', 'Ultra Fitz Roy proposal (Trail Run Adventures)'],
  ['Who It Is For and interest form photo, Fitz Roy story photo, gallery', 'fitzroy-laguna', 'Ultra Fitz Roy proposal (Trail Run Adventures)'],
  ['Island in the Sky intro and gallery (4 photos)', 'madeira-clouds', 'Madeira brochure (Trail Run Adventures)'],
  ['South Island Odyssey intro and gallery (5 photos)', 'nz-ridge', 'New Zealand brochure (Trail Run Adventures)'],
];
const labels = {
  home: 'Adventures interest form', 'madeira-2027': 'Island in the Sky 2027, Adventures panorama', 'madeira-2028': 'Island in the Sky 2028',
  'slovenia-2027': 'Julian Alps Traverse 2027', 'slovenia-2028': 'Julian Alps Traverse 2028',
  'new-zealand-2028': 'South Island Odyssey 2028', 'norway-2028': 'Sail & Trail 2028',
  'softrock/handies-ridge': 'Softrock hero and home tile, Handies Peak', 'softrock/island-lake': 'Softrock story photo, Ice Lake from the Island Lake trail',
  'softrock/american-basin': 'Softrock gallery, American Basin', 'softrock/ice-lake-basin': 'Softrock gallery, Ice Lake Basin',
  'softrock/silverton': 'Softrock gallery, Silverton', 'softrock/telluride': 'Softrock gallery, Telluride',
  'softrock/sloan-lake': 'Softrock interest form, Handies Peak area',
  'madeira/pr1-hikers': 'Island in the Sky gallery, PR1 ridge trail',
  'slovenia/bled-overview': 'Julian Alps Traverse story photo, Lake Bled', 'slovenia/bohinj-aerial': 'Julian Alps Traverse interest form, Lake Bohinj from above',
  'slovenia/triglav-lakes': 'Julian Alps Traverse gallery, Triglav Lakes Valley', 'slovenia/mangart-hikers': 'Julian Alps Traverse gallery, Mangart',
  'slovenia/bohinj-shore': 'Julian Alps Traverse gallery, Lake Bohinj', 'slovenia/soca-river': 'Julian Alps Traverse gallery, Soča River',
  'norway/reinefjord-dawn': 'Sail & Trail story photo, Reinefjorden', 'norway/reine-peaks': 'Sail & Trail interest form, peaks over Reine',
  'norway/reinebringen-steps': 'Sail & Trail gallery, Reinebringen steps', 'norway/reinebringen-view': 'Sail & Trail gallery, view from Reinebringen',
  'norway/ryten-reflection': 'Sail & Trail gallery, Ryten and Middagstinden', 'norway/lofoten-beach': 'Sail & Trail gallery, Kvalvika beach',
};
const used = ['madeira/pr1-hikers', 'slovenia/bled-overview', 'slovenia/bohinj-aerial', 'slovenia/triglav-lakes', 'slovenia/mangart-hikers', 'slovenia/bohinj-shore', 'slovenia/soca-river', 'norway/reinefjord-dawn', 'norway/reine-peaks', 'norway/reinebringen-steps', 'norway/reinebringen-view', 'norway/ryten-reflection', 'norway/lofoten-beach', 'softrock/handies-ridge', 'softrock/island-lake', 'softrock/american-basin', 'softrock/ice-lake-basin', 'softrock/silverton', 'softrock/telluride', 'softrock/sloan-lake', 'home', 'madeira-2027', 'madeira-2028', 'slovenia-2027', 'slovenia-2028', 'new-zealand-2028', 'norway-2028'];
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const clean = (t) => t.replace(/^File:/, '').replace(/\.(jpe?g|png)$/i, '');

const plainCredit = {};
const credits = [];
for (const [label, img, who] of arv) {
  const k = 'aravaipa/' + img;
  plainCredit[k] = `Aravaipa Running${who ? ', ' + who : ''}`;
  credits.push({ photo: ph(k), label, html: `Photo: <a href="https://www.aravaiparunning.com/cocodona-250/">Aravaipa Running</a>, Cocodona 250 race photography${who ? ' by ' + esc(who) : ''}. Resized and cropped.` });
}
for (const [label, img, src] of tra) {
  const k = 'tra/' + img;
  credits.push({ photo: ph(k), label, html: `Photo courtesy of <a href="https://www.trailrunadventures.com" rel="noopener">Trail Run Adventures</a>, from their ${esc(src)}. Resized and cropped.` });
}
for (const [k, p] of Object.entries(wm)) {
  plainCredit[k] = `${p.artist}, ${p.license}, via Wikimedia Commons`;
  if (!used.includes(k)) continue;
  const lic = p.licenseUrl ? `<a href="${esc(p.licenseUrl)}" rel="noopener">${esc(p.license)}</a>` : esc(p.license);
  credits.push({ photo: ph(k), label: labels[k] ?? k, html: `"${esc(clean(p.title))}" by ${esc(p.artist)}, ${lic}, <a href="${esc(p.page)}" rel="noopener">via Wikimedia Commons</a>. Cropped and resized.` });
}
const creditOf = (key) => {
  const base = key.replace(/-sm$/, '');
  if (plainCredit[base]) return plainCredit[base];
  if (base.startsWith('tra/')) return 'Trail Run Adventures';
  return '';
};

const out = trips.map((t, i) => {
  const sibling = trips.find((s) => s.name === t.name && s.slug !== t.slug);
  const intro = t.introPhoto ?? (sibling ? sibling.photo : t.photo);
  const form = t.formPhoto ?? (sibling ? sibling.photo : t.photo);
  return {
    slug: t.slug, name: t.name, menu_order: i,
    meta: {
      type: t.type, destination: t.destination, region: t.region ?? '', subtitle: t.subtitle ?? '', tagline: t.tagline,
      length: t.length, group_size: String(t.groupSize), min_group: t.minGroup ? String(t.minGroup) : '',
      difficulty: String(t.difficulty), difficulty_note: t.difficultyNote, dates: t.dates,
      sort_year: String(t.year), sort_month: String(t.month), airport: t.airport, host: t.host ?? '',
      price: 'TBD', single_room: 'Optional, TBD', reg_opens: 'TBD', gallery_credit: t.galleryCredit ?? '',
      note: t.note ?? '', fineprint: t.fineprint ?? '',
    },
    json: {
      overview: t.overview, highlights: t.highlights, itinerary: t.itinerary.map((d) => ({ title: d.title, ...(d.stats ? { stats: d.stats } : {}), body: d.body })),
      included: t.included, not_included: t.notIncluded, race: t.race ?? {},
      photos: {
        hero: ph(t.photo, `${t.name}, ${t.destination}`, creditOf(t.photo)),
        intro: ph(intro, t.destination, creditOf(intro)),
        form: ph(form, '', creditOf(form)),
        gallery: (t.gallery ?? []).map((g) => ph(g.src, g.alt, creditOf(g.src))),
      },
    },
  };
});

const landing = {
  hero: ph('tra/fitzroy-runner', 'Trail runner below Fitz Roy', 'Trail Run Adventures'),
  pano: ph('madeira-2027', 'Ridgeline trail above the clouds on Madeira', creditOf('madeira-2027')),
  who: ph('tra/fitzroy-laguna', 'Fitz Roy massif above Patagonian hills', 'Trail Run Adventures'),
  form: ph('home', '', creditOf('home')),
  partner_photo: ph('tra-founders.jpg', 'Trail Run Adventures founders', 'Trail Run Adventures'),
  partner_logo: ph('tra-logo.png', 'Trail Run Adventures logo', 'Trail Run Adventures'),
  faqs,
  credits,
  credits_intro: 'Cocodona photography is Aravaipa Running race photography from past Cocodona 250 races. Other trip photography is from Trail Run Adventures, our trip partner, taken on their past trips. Where we do not have partner photos yet, we use placeholder photography from Wikimedia Commons under free licenses or in the public domain, to be replaced before launch.',
  credits_outro: 'Trail Run Adventures logo and the photo of Anna Frost and Braz courtesy of <a href="https://www.trailrunadventures.com" rel="noopener">trailrunadventures.com</a>, used to identify our trip partner.',
};

const json = JSON.stringify({ trips: out, landing, files: Object.fromEntries(Object.entries(files).map(([k, f]) => [k, path.relative(pub, f)])) }, null, 1);
if (/[–—]/.test(json)) throw new Error('en/em dash in data');
process.stdout.write(json);
