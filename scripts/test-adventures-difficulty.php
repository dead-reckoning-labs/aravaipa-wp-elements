<?php
/**
 * Difficulty ranges on Adventures trips ("3-4", "1-4"), without WordPress.
 *
 * Run: php scripts/test-adventures-difficulty.php
 *
 * @package Aravaipa_Elements
 */

define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_filter() {}
function add_shortcode() {}

require __DIR__ . '/../includes/adventures.php';

$fails = 0;
function check( $label, $got, $want ) {
	global $fails;
	if ( $got !== $want ) {
		++$fails;
		echo 'FAIL ' . $label . ': got ' . var_export( $got, true ) . ', want ' . var_export( $want, true ) . "\n";
	} else {
		echo 'ok   ' . $label . "\n";
	}
}

function trip_of( $raw ) {
	list( $min, $max ) = arv_adv_difficulty_range( $raw );
	return array( 'difficulty_min' => $min, 'difficulty_max' => $max );
}

// Parsing.
check( 'single 4', arv_adv_difficulty_range( '4' ), array( 4, 4 ) );
check( 'int 3', arv_adv_difficulty_range( 3 ), array( 3, 3 ) );
check( 'range 3-4', arv_adv_difficulty_range( '3-4' ), array( 3, 4 ) );
check( 'range 1-4', arv_adv_difficulty_range( '1-4' ), array( 1, 4 ) );
check( 'range 1 to 4', arv_adv_difficulty_range( '1 to 4' ), array( 1, 4 ) );
check( 'reversed 4-3', arv_adv_difficulty_range( '4-3' ), array( 3, 4 ) );
check( 'clamped 0-9', arv_adv_difficulty_range( '0-9' ), array( 1, 5 ) );
check( 'empty', arv_adv_difficulty_range( '' ), array( 1, 1 ) );

// Text and labels.
check( 'text single', arv_adv_difficulty_text( trip_of( '4' ) ), '4' );
check( 'text 3-4', arv_adv_difficulty_text( trip_of( '3-4' ) ), '3 to 4' );
check( 'text 1-4', arv_adv_difficulty_text( trip_of( '1-4' ) ), '1 to 4' );
check( 'label single', arv_adv_difficulty_label_of( trip_of( '4' ) ), 'Challenging' );
check( 'label 3-4', arv_adv_difficulty_label_of( trip_of( '3-4' ) ), 'Moderate to Challenging' );
check( 'label 1-4', arv_adv_difficulty_label_of( trip_of( '1-4' ) ), 'Easy to Challenging' );
check( 'label 2-3 same word', arv_adv_difficulty_label_of( trip_of( '2-3' ) ), 'Moderate' );

// Scale: a single rating renders exactly as before 0.101.5.
$old = '<div class="aa-diff-scale" aria-hidden="true">';
for ( $i = 1; $i <= 5; $i++ ) {
	$old .= '<span' . ( $i <= 4 ? ' class="aa-on"' : '' ) . '>' . $i . '</span>';
}
$old .= '</div>';
check( 'scale single unchanged', arv_adv_difficulty_scale( trip_of( '4' ) ), $old );
check(
	'scale 3-4',
	arv_adv_difficulty_scale( trip_of( '3-4' ) ),
	'<div class="aa-diff-scale" aria-hidden="true"><span class="aa-on">1</span><span class="aa-on">2</span><span class="aa-here">3</span><span class="aa-here">4</span><span>5</span></div>'
);
check(
	'scale 1-4',
	arv_adv_difficulty_scale( trip_of( '1-4' ) ),
	'<div class="aa-diff-scale" aria-hidden="true"><span class="aa-here">1</span><span class="aa-here">2</span><span class="aa-here">3</span><span class="aa-here">4</span><span>5</span></div>'
);

echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
