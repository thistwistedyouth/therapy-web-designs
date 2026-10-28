<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The owner's own colours for the swipe book (Customise this grid > Colours): a frame colour and an
 * accent colour, used with either the dark or the light book. Turns the two chosen hex colours into a
 * short list of CSS custom properties (--twd-sb-*) that go on the book's own element, so every rule in
 * swipebook.css keeps reading its variables and none of them needs to know a custom colour exists.
 *
 * What it takes care of, so a colour picked for looks can never make the book unreadable:
 * - the writing and the headings on the card use the accent, moved toward dark (light book) or light
 *   (dark book) until it reads at 4.5:1 against the card, while buttons use the accent as chosen with
 *   white or dark writing, whichever has the better contrast;
 * - the arrows, page numbers and buttons that sit on the frame take a writing colour chosen for the
 *   frame colour, the same way;
 * - the saved picture's solid colour stays the card's own colour (lightly tinted by the frame), so the
 *   dark writing of the light book is never put on a dark ground.
 * Only a hex colour of three or six digits is ever accepted, so nothing but numbers and fixed words can
 * reach the page.
 */
class TWD_AP_Swipebook_Colours {

	/** The card colour of each book, what the writing on a card has to read against. */
	const CARD_LIGHT = '#fffdf8';
	const CARD_DARK  = '#1b1836';

	/** The accent each book has when none is chosen, used to start the frame's own writing colour. */
	const ACCENT_LIGHT = '#8a5a00';
	const ACCENT_DARK  = '#ffd166';

	/** A hex colour: # and three or six hex digits, nothing else (a trailing newline does not count). */
	public static function clean_hex( $value ) {
		if ( is_string( $value ) && preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/iD', $value, $m ) ) {
			$h = strtolower( $m[1] );
			if ( 3 === strlen( $h ) ) {
				$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
			}
			return '#' . $h;
		}
		return '';
	}

	/**
	 * The custom properties for a book. '' when no colour is chosen, so a site that never picked
	 * one prints nothing and keeps the book's own look exactly.
	 */
	public static function style( $theme, $frame, $accent ) {
		$frame  = self::clean_hex( $frame );
		$accent = self::clean_hex( $accent );
		if ( '' === $frame && '' === $accent ) {
			return '';
		}
		$light = 'light' === $theme;
		$card  = $light ? self::CARD_LIGHT : self::CARD_DARK;
		$v     = array();

		if ( '' !== $accent ) {
			$text = self::readable( $accent, $card, $light );
			$fill = $accent;
			$ink  = self::best_ink( $fill );
			$hov  = self::contrast( $ink, self::shade( $fill, 0.14, $ink ) ) >= self::contrast( $ink, $fill ) - 0.0001 ? self::shade( $fill, 0.14, $ink ) : $fill;
			$v['--twd-sb-gold']                  = $text;
			$v['--twd-sb-gold-soft']             = self::rgba( $text, 0.94 );
			$v['--twd-sb-gold-faint']            = self::rgba( $text, 0.28 );
			$v['--twd-sb-chip']                  = self::rgba( $text, 0.08 );
			$v['--twd-sb-chip-hover']            = self::rgba( $text, 0.12 );
			$v['--twd-sb-chip-soft']             = self::rgba( $text, 0.08 );
			$v['--twd-sb-btn-rest']              = self::rgba( $text, 0.12 );
			$v['--twd-sb-btn-hover']             = self::rgba( $text, 0.24 );
			$v['--twd-sb-primary']               = $fill;
			$v['--twd-sb-primary-hover']         = $hov;
			$v['--twd-sb-primary-ink']           = $ink;
			$v['--twd-sb-primary-shadow']        = '0 4px 14px ' . self::rgba( $fill, 0.3 );
			$v['--twd-sb-primary-shadow-hover']  = '0 6px 18px ' . self::rgba( $fill, 0.38 );
		}

		if ( '' !== $frame ) {
			// Dark writing on a frame where black reads better than white (the switch is at a luminance of about 0.18).
			$f_light = self::contrast( '#000000', $frame ) >= self::contrast( '#ffffff', $frame );
			$top     = self::mix( $frame, '#ffffff', $light ? 0.38 : 0.1 );
			$bottom  = self::mix( $frame, '#000000', $light ? 0.1 : 0.38 );
			$v['--twd-sb-frame-bg']     = 'radial-gradient(ellipse at 50% 0%, rgba(255, 255, 255, ' . ( $light ? '0.3' : '0.1' ) . ') 0%, rgba(255, 255, 255, 0) 46%), linear-gradient(180deg, ' . $top . ' 0%, ' . $frame . ' 58%, ' . $bottom . ' 100%)';
			$v['--twd-sb-frame-shadow'] = '0 0 0 1px ' . self::rgba( self::mix( $frame, '#000000', 0.4 ), 0.35 ) . ', 0 30px 70px rgba(0, 0, 0, ' . ( $light ? '0.28' : '0.55' ) . ')';
			$v['--twd-sb-veil']         = self::rgba( self::mix( $frame, $light ? '#ffffff' : '#000000', $light ? 0.72 : 0.85 ), $light ? 0.82 : 0.78 );
			// The saved picture: the card's own colour, a little tinted by the frame.
			$v['--twd-sb-export-bg']    = self::mix( $card, $frame, 0.1 );
			// What sits on the frame (arrows, page numbers, Share and Save, Read the full article):
			// the accent, moved until it reads on this frame, or plain dark or light writing.
			$base   = '' !== $accent ? $accent : ( $light ? self::ACCENT_LIGHT : self::ACCENT_DARK );
			$ctl    = self::readable( $base, $frame, $f_light );
			// A pale tint of the writing colour behind the buttons would lower the contrast of the writing on
			// them; on a frame where that would take it under 4.5:1 the buttons are outlined only.
			$tint   = self::contrast( $ctl, self::mix( $frame, $ctl, 0.2 ) ) >= 4.5 ? 1 : 0;
			// Solid colours, not see-through ones: a see-through ink on a mid-tone frame loses its contrast.
			$ink    = self::readable( $f_light ? '#2b2540' : '#fff8ec', $frame, $f_light );
			$ink_hover = $f_light ? self::mix( $ink, '#000000', 0.4 ) : self::mix( $ink, '#ffffff', 0.4 );
			$v['--twd-sb-ctl']          = $ctl;
			$v['--twd-sb-ctl-line']     = self::rgba( $ctl, 0.4 );
			$v['--twd-sb-ctl-chip']     = self::rgba( $ctl, 0.1 * $tint );
			$v['--twd-sb-ctl-chip-hover'] = self::rgba( $ctl, 0.18 * $tint );
			$v['--twd-sb-ctl-rest']     = self::rgba( $ctl, 0.14 * $tint );
			$v['--twd-sb-ctl-hover']    = self::rgba( $ctl, 0.28 * $tint );
			$v['--twd-sb-read']         = $ink;
			$v['--twd-sb-read-line']    = self::rgba( $ink, 0.4 );
			$v['--twd-sb-read-hover']   = $ink_hover;
		} elseif ( '' !== $accent ) {
			// An accent without a frame colour: the page's controls follow the accent as it reads on
			// the book's own frame.
			$frame_default = $light ? '#f6eedb' : '#100e20';
			$ctl           = self::readable( $accent, $frame_default, $light );
			$v['--twd-sb-ctl']            = $ctl;
			$v['--twd-sb-ctl-line']       = self::rgba( $ctl, 0.4 );
			$v['--twd-sb-ctl-chip']       = self::rgba( $ctl, 0.1 );
			$v['--twd-sb-ctl-chip-hover'] = self::rgba( $ctl, 0.18 );
			$v['--twd-sb-ctl-rest']       = self::rgba( $ctl, 0.14 );
			$v['--twd-sb-ctl-hover']      = self::rgba( $ctl, 0.28 );
		}

		$out = '';
		foreach ( $v as $name => $value ) {
			$out .= $name . ':' . $value . ';';
		}
		return $out;
	}

	// ---- colour maths (WCAG relative luminance and contrast) ----

	private static function rgb( $hex ) {
		$h = ltrim( $hex, '#' );
		return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
	}

	private static function hex( $rgb ) {
		return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) round( $rgb[0] ) ) ), max( 0, min( 255, (int) round( $rgb[1] ) ) ), max( 0, min( 255, (int) round( $rgb[2] ) ) ) );
	}

	private static function rgba( $hex, $alpha ) {
		$c = self::rgb( $hex );
		return 'rgba(' . $c[0] . ', ' . $c[1] . ', ' . $c[2] . ', ' . rtrim( rtrim( sprintf( '%.2f', $alpha ), '0' ), '.' ) . ')';
	}

	/** $a moved $t of the way toward $b. */
	public static function mix( $a, $b, $t ) {
		$x = self::rgb( $a );
		$y = self::rgb( $b );
		return self::hex( array( $x[0] + ( $y[0] - $x[0] ) * $t, $x[1] + ( $y[1] - $x[1] ) * $t, $x[2] + ( $y[2] - $x[2] ) * $t ) );
	}

	public static function luminance( $hex ) {
		$lin = function ( $v ) {
			$v = $v / 255;
			return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		};
		$c = self::rgb( $hex );
		return 0.2126 * $lin( $c[0] ) + 0.7152 * $lin( $c[1] ) + 0.0722 * $lin( $c[2] );
	}

	public static function contrast( $a, $b ) {
		$x = self::luminance( $a );
		$y = self::luminance( $b );
		return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
	}

	/**
	 * Writing for a filled button: white, else the book's dark ink, else black, the first that reads at
	 * 4.5:1 on $bg (black always does when the other two cannot).
	 */
	public static function best_ink( $bg ) {
		foreach ( array( '#ffffff', '#1f1d2b', '#000000' ) as $ink ) {
			if ( self::contrast( $ink, $bg ) >= 4.5 ) {
				return $ink;
			}
		}
		return self::contrast( '#ffffff', $bg ) >= self::contrast( '#000000', $bg ) ? '#ffffff' : '#000000';
	}

	/** $color made a little further from $ink's opposite, for a hover that keeps its contrast. */
	private static function shade( $color, $t, $ink ) {
		return '#ffffff' === $ink ? self::mix( $color, '#000000', $t ) : self::mix( $color, '#ffffff', $t );
	}

	/**
	 * $color moved toward black (when $toward_dark) or white in small steps until it reads at least
	 * 4.5:1 against $bg; if it never does, plain black or white.
	 */
	public static function readable( $color, $bg, $toward_dark ) {
		$target = $toward_dark ? '#000000' : '#ffffff';
		for ( $i = 0; $i <= 20; $i++ ) {
			$c = self::mix( $color, $target, $i / 20 );
			if ( self::contrast( $c, $bg ) >= 4.5 ) {
				return $c;
			}
		}
		return $target;
	}
}
