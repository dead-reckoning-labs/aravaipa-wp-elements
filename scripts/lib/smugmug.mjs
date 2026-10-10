/**
 * Walking a SmugMug account for Aravaipa race galleries.
 *
 * Lived inline in discover-smugmug.mjs until the nightly ingest
 * (ingest-photos.mjs) needed the same walk. Same reason as race-match.mjs:
 * two copies of the walker would drift into accepting different galleries,
 * and that drift publishes a stranger's race as Aravaipa's on one path and
 * not the other.
 *
 * The rule is match-or-reject, never include-by-default. See the header of
 * discover-smugmug.mjs for why, and the cases in
 * scripts/test/smugmug-filter.test.mjs for what it has caught.
 */

import { normalise, yearFrom, IS_RIDE } from './race-match.mjs';

// The accounts walked, and who each one is. Aravaipa's own first.
export const ACCOUNTS = [
	{ nick: 'Aravaipa', by: 'Aravaipa Photo Gallery' },
	{ nick: 'lwp', by: "Let's Wander Photography" },
	{ nick: 'springvelvet', by: 'Spring Velvet Photography' },
];

/**
 * A SmugMug API v2 client, read-only, API key only (public data).
 *
 * @param {string} key
 * @return {(path: string) => Promise<object|null>}
 */
export const smugmugApi = ( key ) => async ( path ) => {
	const url = new URL( `https://api.smugmug.com/api/v2/${ path }` );
	url.searchParams.set( 'APIKey', key );

	for ( let attempt = 1; attempt <= 3; attempt++ ) {
		const res = await fetch( url, { headers: { Accept: 'application/json' }, redirect: 'follow' } ).catch( () => null );

		if ( res && res.ok ) return res.json().catch( () => null );

		// 404 is an answer. Anything else (429, 5xx, network) is retried, and
		// after that reported as null like before.
		if ( res && 404 === res.status ) return null;
		await new Promise( ( r ) => setTimeout( r, 1500 * attempt ) );
	}

	return null;
};

/**
 * Every child of a node, across pages. SmugMug pages at the count asked
 * for, and a folder past 200 children would otherwise lose the rest
 * silently.
 */
export async function children( api, nodeId ) {
	const out = [];
	let start = 1;

	for ( let page = 0; page < 20; page++ ) {
		const res = await api( `node/${ nodeId }!children?count=200&start=${ start }` );
		const nodes = res?.Response?.Node ?? [];
		out.push( ...nodes );

		const pages = res?.Response?.Pages;
		if ( ! pages || ! nodes.length || pages.Start + pages.Count > pages.Total ) break;
		start = pages.Start + pages.Count;
	}

	return out;
}

/**
 * How many photographs are actually behind a node, albums nested inside it
 * included. A race folder is created on SmugMug when the race is scheduled,
 * not when it is shot, so the upcoming half of a season sits there as real,
 * correctly-named, completely empty folders. Counted rather than guessed
 * from the date, because a race can be shot and posted late, and a folder
 * can be seeded early.
 */
export async function photoCount( api, nodeId, depth = 0 ) {
	if ( depth > 2 ) return 0;

	let total = 0;

	for ( const child of await children( api, nodeId ) ) {
		if ( ! isPublic( child ) ) continue;

		if ( 'Album' === child.Type && child.Uris?.Album?.Uri ) {
			const album = await api( child.Uris.Album.Uri.replace( '/api/v2/', '' ) );
			total += album?.Response?.Album?.ImageCount ?? 0;
		} else if ( 'Folder' === child.Type ) {
			total += await photoCount( api, child.NodeID, depth + 1 );
		}
	}

	return total;
}

/**
 * Open to anyone with the link: no password on it or on anything above it.
 * SmugMug does not list private nodes to an API-key caller at all, so a
 * password is the remaining way a listed gallery can still be closed.
 */
export const isPublic = ( node ) =>
	( node.EffectiveSecurityType ?? node.SecurityType ?? 'None' ) === 'None';

/**
 * Is this folder a container of races, rather than a race itself?
 *
 * Only containers are descended into. Without this the walk goes inside
 * other promoters' event folders and matches whatever is in them: Let's
 * Wander's "Oregon 200 Miler 2025" holds a gallery called "Brian
 * Thrasher", which matched Aravaipa's Thrasher Night Runs on the surname.
 *
 * A container is what is left over when the year is removed: "2025 Race
 * Photography" leaves "race photography", "2026 Events" leaves "events",
 * and both are generic. "Oregon 200 Miler 2025" leaves "oregon 200 miler",
 * which is the name of somebody's race.
 */
export const CONTAINER_WORDS = new Set(
	'events event races race photography photos photo running runs run gallery galleries archive'.split( ' ' )
);

export const isContainer = ( name ) => {
	const words = normalise( name ).replace( /\b20[12]\d\b/g, ' ' ).split( ' ' ).filter( Boolean );

	// A bare year is the commonest container of all ("2026").
	return 0 === words.length || words.every( ( w ) => CONTAINER_WORDS.has( w ) );
};

const yearFromUpload = ( node ) => {
	const m = String( node.DateAdded ?? '' ).match( /^(20[12]\d)/ );
	return m ? Number( m[ 1 ] ) : 0;
};

/**
 * Walk one account, accepting the OUTERMOST folder that names a race.
 *
 * Depth matters and is not the same on every account. Aravaipa's own is
 * root > "2026 Events" > "Coldwater Rumble" > "Finish Line 1", so the race
 * is two levels down. Spring Velvet's is root > "Rock River Canyon 50K &
 * 27K Trail Race" > albums, so the race is one level down. Taking the
 * outermost match is what stops one race being accepted five times over,
 * once per "Finish Line N" album inside it.
 *
 * @param {object}   opts
 * @param {Function} opts.api       From smugmugApi().
 * @param {object}   opts.account   { nick, by }.
 * @param {Function} opts.match     name => { race, tied: string[] } | null.
 * @param {Function} [opts.yearOk]  year => bool. Galleries outside it are
 *                                  rejected before their photos are counted,
 *                                  and containers named for a year outside
 *                                  it are not walked into.
 * @param {boolean}  [opts.requirePublic] Reject password-protected nodes.
 * @return {Promise<{accepted: object[], rejected: object[]}>}
 */
export async function walkAccount( { api, account, match, yearOk = () => true, requirePublic = false } ) {
	const accepted = [];
	const rejected = [];
	const reject = ( node, ancestors, why ) =>
		rejected.push( { account: account.nick, name: node.Name, parent: ancestors.join( ' / ' ), url: node.WebUri, why } );

	async function walk( node, ancestors, depth ) {
		// Four levels is past any of these accounts' real nesting and stops a
		// pathological tree from walking forever.
		if ( depth > 3 ) return;

		const trail = [ ...ancestors, node.Name ];

		if ( requirePublic && ! isPublic( node ) ) {
			reject( node, ancestors, 'password protected' );
			return;
		}

		const hit = match( node.Name );

		if ( hit ) {
			if ( IS_RIDE.test( node.Name ) ) {
				reject( node, ancestors, 'Aravaipa Rides, belongs on aravaiparides.com' );
				return;
			}

			const year = yearFrom( ...trail.slice().reverse() ) || yearFromUpload( node );

			if ( ! year ) {
				reject( node, ancestors, `matched ${ hit.race } but no year` );
				return;
			}

			if ( ! yearOk( year ) ) {
				reject( node, ancestors, 'outside the years asked for' );
				return;
			}

			const photos = await photoCount( api, node.NodeID );

			if ( 0 === photos ) {
				reject( node, ancestors, 'matched a race but holds no photographs yet' );
				return;
			}

			accepted.push( {
				race: hit.race,
				tied: hit.tied ?? [],
				year,
				by: account.by,
				account: account.nick,
				name: node.Name,
				parent: ancestors.join( ' / ' ),
				url: node.WebUri,
				photos,
			} );
			// Outermost wins: do not descend into this race's own albums.
			return;
		}

		if ( ! node.HasChildren ) {
			reject( node, ancestors, 'no Aravaipa race in the name' );
			return;
		}

		// Not a race, and not a container of races either: another promoter's
		// event, or a wedding, or a trip. Do not go looking inside it.
		if ( depth > 0 && ! isContainer( node.Name ) ) {
			reject( node, ancestors, 'not an Aravaipa race, and not a container' );
			return;
		}

		// A container named for a year nobody asked about ("2019 Events").
		const named = yearFrom( node.Name );
		if ( named && ! yearOk( named ) ) {
			reject( node, ancestors, 'outside the years asked for' );
			return;
		}

		const kids = await children( api, node.NodeID );

		if ( ! kids.length ) {
			reject( node, ancestors, 'no Aravaipa race in the name' );
			return;
		}

		for ( const child of kids ) {
			await walk( child, trail, depth + 1 );
		}
	}

	const user = await api( `user/${ account.nick }` );
	const rootUri = user?.Response?.User?.Uris?.Node?.Uri;

	if ( ! rootUri ) {
		throw new Error( `${ account.nick }: could not read the account` );
	}

	for ( const outer of await children( api, rootUri.split( '/' ).pop() ) ) {
		await walk( outer, [], 0 );
	}

	return { accepted, rejected };
}
