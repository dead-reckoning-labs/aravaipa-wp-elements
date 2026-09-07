/**
 * Reading a distance label well enough to rank one against another.
 *
 * A race edition's winners arrive as a list of distance labels with no
 * ordering between them, and the results page wants to lead with one of
 * them: the premier distance, the result a reader means when they ask who
 * won. Nothing in the source data says which that is, so it is worked out
 * from the labels themselves, and every label was written by a different
 * person in a different decade.
 *
 * Lived in fetch-archive-stats.mjs until fetch-ultrasignup-stats.mjs
 * needed exactly the same answer to exactly the same question. Two copies
 * of this would drift, and the drift would be silent: both would still
 * produce a headline, just not the same one, so the same race would lead
 * with its 100 Mile in the years read from a file and its 50K in the years
 * read from UltraSignup.
 */

/**
 * A distance label's length in metres, or 0 when it does not name one.
 *
 * @param {string} name Distance label, e.g. "50K", "1/2 Marathon", "100 Mile".
 * @return {number} Metres, or 0 when the label names no ground distance.
 */
export function guessMetres( name ) {
	const n = String( name || '' ).toLowerCase().trim();

	if ( /^(1\/2|half)\s*marathon$/.test( n ) ) return 21098;
	if ( /^marathon$/.test( n ) ) return 42195;

	const mi = n.match( /^([\d.]+)\s*(?:m|mi|miles?|miler)$/ );
	if ( mi ) return Math.round( +mi[ 1 ] * 1609.344 );

	const km = n.match( /^([\d.]+)\s*k(?:m|ilometers?)?$/ );
	if ( km ) return Math.round( +km[ 1 ] * 1000 );

	// Kendall Mountain Run 2012 and 2013 name their two distances "Kendall
	// Mountain Run", the race's own name standing in for its one course
	// with no distance written anywhere, and "K2 double", the same course
	// run twice with no distance of its own to guess either. Neither has a
	// unit this function can read, so both guessed 0 and tied, and a tie
	// never becomes a headline. Not a real measurement, only enough of one
	// to say a double is longer than whatever it is double of, which is
	// true regardless of what that turns out to be.
	if ( /\bdouble\b/.test( n ) ) return 1;

	return 0;
}

/**
 * A fixed-time label's length in seconds, or 0 when it does not name one.
 *
 * @param {string} name Distance label, e.g. "24 Hour", "6 Day", "72H".
 * @return {number} Seconds, or 0 when the label names no clock.
 */
export function guessSeconds( name ) {
	const n = String( name || '' ).toLowerCase().trim();

	// Steep Camp abbreviates the same way Silverton 1000 does, "5d, 3d, 2d,
	// 1d" beside its own "12h, 6h": bare "d" needed the same trust bare "h"
	// gets below, or the days lost to 0 and the file's two shortest heats,
	// the only labels left with anything on this scale, took the headline
	// instead of its longest.
	const day = n.match( /^([\d.]+)\s*(?:day|d)s?$/ );
	if ( day ) return +day[ 1 ] * 86400;

	// Silverton 1000 abbreviates every one of its shorter heats down to the
	// bare letter, "72H, 48H, 24H, 12H, 6H", the same convention guessMetres
	// already reads "50M" as 50 miles under. Without it every one of those
	// guessed 0s alongside "6 Day", the same length as each other by that
	// scale, and only sorted correctly by the accident of the file listing
	// them longest first already.
	const hr = n.match( /^([\d.]+)\s*(?:hour|hr|h)s?$/ );
	if ( hr ) return +hr[ 1 ] * 3600;

	return 0;
}

/**
 * Sort an edition's winners longest first and say whether one leads.
 *
 * Across the Years and Silverton 1000 name every one of their distances by
 * the clock, never the ground: "72 Hour, 48 Hour, 24 Hour" all measure 0m
 * on the metres scale, a tie that never resolves to a headline, "Winners, 3
 * distances" and nothing shown where every other year on the page leads
 * with a result.
 *
 * A fixed-time distance outranks a real one wherever both exist at the same
 * event, not only where every distance is fixed-time. Desert Solstice runs
 * a 24 Hour and offers a 100 Mile cutoff inside it; Juniperwood Ranch Runs
 * runs a 48 Hour and offers a 50 Mile and a Marathon inside it. The cutoff
 * distance is the shorter option within the fixed-time race, not a longer
 * race that happens to share a page with it, and Aravaipa's own read of
 * both is that the clock is what the event is, so it leads. Every winner is
 * ranked by the clock where it has one and by the ground otherwise, which
 * puts any fixed-time distance present ahead of any real one without
 * comparing the two directly: a real distance's guessSeconds is 0, lower
 * than any fixed-time race actually run.
 *
 * Mutates the array's order, which is the point: the caller stores the
 * winners in the order this leaves them, longest distance first.
 *
 * @param {Array<{distance: string}>} winners Winners, one per distance.
 * @return {boolean} Whether the first winner is the outright longest.
 */
export function rankWinners( winners ) {
	if ( ! Array.isArray( winners ) || winners.length < 2 ) {
		return Array.isArray( winners ) && winners.length === 1;
	}

	const measured = winners.map( ( w ) => {
		const secs = guessSeconds( w.distance || '' );
		return { w, m: secs > 0 ? secs : guessMetres( w.distance || '' ), timed: secs > 0 };
	} );

	// Metres and seconds still cannot be compared to each other directly,
	// only used to break a tie within whichever one a distance is actually
	// measured in: sorted timed-first, and by magnitude within each group.
	measured.sort( ( a, b ) => ( b.timed - a.timed ) || ( b.m - a.m ) );

	winners.length = 0;
	winners.push( ...measured.map( ( x ) => x.w ) );

	const longest = measured[ 0 ];

	// A label this cannot measure at all leads nothing: two unreadable
	// labels tie at 0, and one unreadable label beside a real distance is
	// not evidence that the unreadable one is the longer of the two.
	return longest.m > 0
		&& measured.filter( ( x ) => x.timed === longest.timed && x.m === longest.m ).length === 1;
}
