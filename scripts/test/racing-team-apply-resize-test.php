<?php
/**
 * Racing Team apply server-side photo resize (job kfk1l8), without WordPress.
 *
 * Covers: an image over 2000 px or 2 MB is re-encoded to a JPEG of at most
 * 2000 px on the long edge next to the original; small images, PDFs and
 * files the editor cannot open are left alone; a failed resize or save keeps
 * the original; the before-mail hook runs first (priority 1) on our form only
 * and only for the two file fields; the private copy and the staff mail
 * attachments use the resized file; the browser helper shows "Large photo,
 * shrinking it..." and never a size error.
 *
 * A small GD-backed stand-in for WP_Image_Editor does the real pixel work.
 *
 * Run: php scripts/test/racing-team-apply-resize-test.php
 *
 * @package Aravaipa_Elements
 */

ini_set( 'memory_limit', '1024M' ); // Phone-size images, as WordPress raises it for image work.
require __DIR__ . '/fixtures/rt-apply-stubs.php';

$GLOBALS['t_editor_fail'] = '';

/** GD stand-in for WP_Image_Editor (load, get_size, resize, set_quality, save). */
class T_Editor {
	public $im;
	public $q = 90;
	public function __construct( $im ) {
		$this->im = $im;
	}
	public function get_size() {
		return array( 'width' => imagesx( $this->im ), 'height' => imagesy( $this->im ) );
	}
	public function resize( $w, $h, $crop = false ) {
		if ( 'resize' === $GLOBALS['t_editor_fail'] ) {
			return new WP_Error( 'x' );
		}
		$sw = imagesx( $this->im );
		$sh = imagesy( $this->im );
		$r  = min( $w / $sw, $h / $sh, 1 );
		$nw = max( 1, (int) round( $sw * $r ) );
		$nh = max( 1, (int) round( $sh * $r ) );
		$n  = imagecreatetruecolor( $nw, $nh );
		imagecopyresampled( $n, $this->im, 0, 0, 0, 0, $nw, $nh, $sw, $sh );
		$this->im = $n;
		return true;
	}
	public function set_quality( $q ) {
		$this->q = $q;
		return true;
	}
	public function save( $dest, $mime ) {
		if ( 'save' === $GLOBALS['t_editor_fail'] ) {
			return new WP_Error( 'x' );
		}
		$GLOBALS['t_saved_q'] = $this->q;
		imagejpeg( $this->im, $dest, $this->q );
		return array( 'path' => $dest, 'file' => basename( $dest ), 'mime-type' => $mime );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $x ) {
		return $x instanceof WP_Error;
	}
}
function wp_get_image_editor( $path ) {
	$im = @imagecreatefromstring( (string) file_get_contents( $path ) );
	return $im ? new T_Editor( $im ) : new WP_Error( 'no_editor' );
}

require dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php';

$ours = 501;
$GLOBALS['t_opts']['arv_rt_apply_form_id'] = $ours;
$dir  = $GLOBALS['t_up'] . '/up';
@mkdir( $dir, 0777, true );

/** Noisy JPEG (random pixels keep the file big, like a phone photo). */
function t_jpeg( $path, $w, $h, $q = 95 ) {
	$row  = $w * 3;
	$pad  = ( 4 - $row % 4 ) % 4;
	$data = '';
	for ( $y = 0; $y < $h; $y++ ) {
		$data .= random_bytes( $row ) . str_repeat( "\0", $pad );
	}
	$bmp = 'BM' . pack( 'VvvV', 54 + strlen( $data ), 0, 0, 54 ) . pack( 'VVVvvVVVVVV', 40, $w, $h, 1, 24, 0, strlen( $data ), 2835, 2835, 0, 0 ) . $data;
	$tmp = $path . '.bmp';
	file_put_contents( $tmp, $bmp );
	$im = imagecreatefrombmp( $tmp );
	unlink( $tmp );
	imagejpeg( $im, $path, $q );
	return $path;
}

// Big photo: 4000x3000 goes to 2000x1500 JPEG, quality 85, next to the original.
$big = t_jpeg( $dir . '/big.jpg', 4000, 3000 );
$out = arv_rt_apply_resize_image( $big );
ok( $out !== $big && is_file( $out ) && is_file( $big ), 'big: new file next to the original' );
ok( dirname( $out ) === dirname( $big ) && '.jpg' === substr( $out, -4 ), 'big: same folder, .jpg' );
$sz = getimagesize( $out );
ok( 2000 === $sz[0] && 1500 === $sz[1], 'big: long edge 2000, aspect kept' );
ok( 'image/jpeg' === $sz['mime'], 'big: JPEG' );
ok( 85 === $GLOBALS['t_saved_q'], 'big: quality 85' );
ok( filesize( $out ) < filesize( $big ), 'big: smaller file' );

// Tall portrait PNG: long edge is the height.
$im = imagecreatetruecolor( 1200, 3600 );
imagepng( $im, $dir . '/tall.png' );
$out = arv_rt_apply_resize_image( $dir . '/tall.png' );
$sz  = getimagesize( $out );
ok( 2000 === $sz[1] && 667 === $sz[0] && 'image/jpeg' === $sz['mime'], 'tall png: 667x2000 JPEG' );

// Over 2 MB but already 2000 px or smaller: re-encoded, not enlarged.
$heavy = t_jpeg( $dir . '/heavy.jpg', 1900, 1900, 100 );
if ( filesize( $heavy ) > ARV_RT_APPLY_RESIZE_BYTES ) {
	$out = arv_rt_apply_resize_image( $heavy );
	$sz  = getimagesize( $out );
	ok( $out !== $heavy && 1900 === $sz[0] && filesize( $out ) < filesize( $heavy ), 'heavy: re-encoded smaller at the same size' );
} else {
	echo "note: sample under 2 MB, heavy case skipped\n";
}

// Small photo: untouched.
$small = t_jpeg( $dir . '/small.jpg', 800, 600, 70 );
ok( $small === arv_rt_apply_resize_image( $small ), 'small: original kept' );

// PDF and unreadable files: untouched.
file_put_contents( $dir . '/cv.pdf', "%PDF-1.4\n" . str_repeat( 'x', 3 * 1048576 ) );
ok( $dir . '/cv.pdf' === arv_rt_apply_resize_image( $dir . '/cv.pdf' ), 'pdf: never touched' );
file_put_contents( $dir . '/odd.heic', "\0\0\0\x18ftypheic" . str_repeat( "\1", 3 * 1048576 ) );
ok( $dir . '/odd.heic' === arv_rt_apply_resize_image( $dir . '/odd.heic' ), 'heic the editor cannot read: original kept' );
ok( '/nope/missing.jpg' === arv_rt_apply_resize_image( '/nope/missing.jpg' ), 'missing file: path back' );

// Failures keep the original.
$GLOBALS['t_editor_fail'] = 'resize';
ok( $big === arv_rt_apply_resize_image( $big ), 'resize error: original kept' );
$GLOBALS['t_editor_fail'] = 'save';
ok( $big === arv_rt_apply_resize_image( $big ), 'save error: original kept' );
$GLOBALS['t_editor_fail'] = '';

// The before-mail hook: first, ours only, our two fields only.
ok( in_array( 'arv_rt_apply_resize_uploads', $GLOBALS['t_filters']['wpcf7_before_send_mail'], true ), 'resize hook registered on before_send_mail' );
$src = file_get_contents( dirname( __DIR__, 2 ) . '/includes/racing-team-apply.php' );
ok( false !== strpos( $src, "add_action( 'wpcf7_before_send_mail', 'arv_rt_apply_resize_uploads', 1, 1 );" ), 'resize runs at priority 1, before the private copy (5)' );

$big2  = t_jpeg( $dir . '/photo.jpg', 3000, 4000 );
$other = t_jpeg( $dir . '/other.jpg', 3000, 3000 );
$sub   = new WPCF7_Submission();
$sub->files = array( 'rt-headshot' => array( $big2 ), 'rt-something' => array( $other ) );
WPCF7_Submission::$inst = $sub;

arv_rt_apply_resize_uploads( new WPCF7_ContactForm( 777 ) );
ok( array() === $GLOBALS['arv_rt_apply_resized'], 'other form: nothing resized' );

arv_rt_apply_resize_uploads( new WPCF7_ContactForm( $ours ) );
ok( isset( $GLOBALS['arv_rt_apply_resized'][ $big2 ] ), 'ours: headshot resized' );
ok( ! isset( $GLOBALS['arv_rt_apply_resized'][ $other ] ), 'ours: other fields left alone' );
$new = arv_rt_apply_upload_path( $big2 );
ok( $new !== $big2 && 2000 === getimagesize( $new )[1], 'upload path maps to the 2000 px copy' );
ok( $other === arv_rt_apply_upload_path( $other ), 'unmapped path passes through' );

// Staff mail attaches the resized photo; mail_2 never had attachments.
class T_Mail2 { public $n; function __construct( $n ) { $this->n = $n; } function name() { return $this->n; } }
$c = arv_rt_apply_mail_components( array( 'body' => 'b', 'attachments' => array( $big2, $dir . '/cv.pdf' ) ), new WPCF7_ContactForm( $ours ), new T_Mail2( 'mail' ) );
ok( array( $new, $dir . '/cv.pdf' ) === $c['attachments'], 'staff mail: resized photo attached, pdf unchanged' );
$c = arv_rt_apply_mail_components( array( 'body' => 'b', 'attachments' => array( $big2 ) ), new WPCF7_ContactForm( 777 ), new T_Mail2( 'mail' ) );
ok( array( $big2 ) === $c['attachments'], 'other form: attachments untouched' );

// The private copy keeps the resized JPEG.
$sub->posted = array( 'rt-email' => 'a@b.co', 'rt-first-name' => 'Stella', 'rt-last-name' => 'Ruiz' );
arv_rt_apply_before_mail( new WPCF7_ContactForm( $ours ) );
$last = $GLOBALS['arv_rt_apply_last'];
ok( is_array( $last ) && isset( $last['files']['rt-headshot'] ) && '.jpg' === substr( $last['files']['rt-headshot'], -4 ), 'private copy: headshot kept' );
ok( isset( $last['files']['rt-headshot'] ) && 2000 === getimagesize( $last['files']['rt-headshot'] )[1], 'private copy: the 2000 px version' );

// Browser helper: shrink note, no size error.
$js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/aravaipa-rt-apply.js' );
ok( false !== strpos( $js, "'Large photo, shrinking it...'" ), 'js: Large photo, shrinking it...' );
ok( false !== strpos( $js, 'file.size > MAX_BYTES ?' ) && false !== strpos( $js, 'var MAX_BYTES = 2 * 1024 * 1024;' ), 'js: shrink note for every image over 2 MB' );
ok( false !== strpos( $js, "if (!isHeic(file) && file.size <= MAX_BYTES) {" ), 'js: only images 2 MB and under (and not HEIC) skip the shrink' );
ok( false === strpos( $js, 'could not be shrunk' ) && false === strpos( $js, 'over 10 MB' ) && false === strpos( $js, '10 * 1024 * 1024' ), 'js: no size error left' );

// Copy rules on the new strings.
$copy = $js . arv_rt_apply_form_template() . implode( ' ', arv_rt_apply_messages() );
ok( false === strpos( $copy, "\u{2014}" ), 'no em dashes' );

if ( is_dir( $GLOBALS['t_up'] ) ) {
	exec( 'rm -rf ' . escapeshellarg( $GLOBALS['t_up'] ) );
}
echo $fails ? "$fails of $n checks FAILED\n" : "ALL PASS ($n checks)\n";
exit( $fails ? 1 : 0 );
