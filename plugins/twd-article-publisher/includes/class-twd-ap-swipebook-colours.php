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
 * What it takes care of, so a colour picked for looks can never make the book unreadable (WCAG contrast,
 * 4.5:1 for writing, 3:1 for the large arrows):
 * - the card is made solid (the book's translucent card would otherwise take the colour of whatever frame
 *   is behind it), a little tinted by the frame, and the writing on it is checked against that colour;
 * - the headings and links on the card use the accent, moved toward dark (light book) or light (dark book)
 *   until it reads on the card, also on the faint tint behind the buttons, while buttons use the accent as
 *   chosen with white or dark writing, whichever has the better contrast;
 * - what sits on the frame (arrows, page numbers, Share and Save, Read the full article) is checked against
 *   EVERY colour of the frame's gradient, top glow to bottom, not only its middle; a frame so mid-toned that
 *   no writing could read on all of them is made a flat colour;
 * - the pale tint behind a button is dropped (outline only) where it would lower the writing's contrast.
 * Only a hex colour of three or six digits is ever accepted, so nothing but numbers and fixed words can
 * reach the page.
 */
class TWD_AP_Swipebook_Colours {

	/** The card colour of each book when a frame colour is chosen (tinted 10% by the frame), what the writing on a card reads against. */
	const CARD_LIGHT = '#fffdf8';
	const CARD_DARK  = '#1b1836';

	/** The card of each book with no frame colour chosen: the book's own translucent card as it looks over its own frame. */
	const CARD_LIGHT_OWN = '#fcf9f4';
	const CARD_DARK_OWN  = '#141126';

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
	 * The colours a book's own frame passes through (the glow at the top, the gradient's top, middle and
	 * bottom), what the controls on it have to read against when no frame colour is chosen.
	 */
	private static function own_stops( $light ) {
		return $light
			? array( self::mix( '#fffdf8', '#d69e2e', 0.18 ), '#fffdf8', '#f8f1e3', '#efe5d1' )
			: array( self::mix( '#1b1836', '#ffd166', 0.06 ), '#1b1836', '#100e20', '#0a0912' );
	}

	/** Whether plain black or white writing reaches 4.6:1 on every one of $stops. */
	private static function workable( $stops ) {
		return max( self::min_contrast( '#000000', $stops ), self::min_contrast( '#ffffff', $stops ) ) >= 4.6;
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
		$v     = array();

		// ---- what the colours are painted on ----
		if ( '' !== $frame ) {
			$glow   = $light ? 0.3 : 0.1;
			$top    = self::mix( $frame, '#ffffff', $light ? 0.26 : 0.06 );
			$bottom = self::mix( $frame, '#000000', $light ? 0.08 : 0.22 );
			$stops  = array( self::mix( $top, '#ffffff', $glow ), $top, $frame, $bottom );
			if ( ! self::workable( $stops ) ) {
				// Too mid-toned for any writing to read on a gradient of it: a flat frame.
				$glow   = 0;
				$top    = $frame;
				$bottom = $frame;
				$stops  = array( $frame );
			}
			$card = self::mix( $light ? self::CARD_LIGHT : self::CARD_DARK, $frame, 0.1 );
			$v['--twd-sb-frame-bg']     = 'radial-gradient(ellipse at 50% 0%, rgba(255, 255, 255, ' . rtrim( rtrim( sprintf( '%.2F', $glow ), '0' ), '.' ) . ') 0%, rgba(255, 255, 255, 0) 46%), linear-gradient(180deg, ' . $top . ' 0%, ' . $frame . ' 58%, ' . $bottom . ' 100%)';
			$v['--twd-sb-frame-shadow'] = '0 0 0 1px ' . self::rgba( self::mix( $frame, '#000000', 0.4 ), 0.35 ) . ', 0 30px 70px rgba(0, 0, 0, ' . ( $light ? '0.28' : '0.55' ) . ')';
			$v['--twd-sb-veil']         = self::rgba( self::mix( $frame, $light ? '#ffffff' : '#000000', $light ? 0.72 : 0.85 ), $light ? 0.82 : 0.78 );
			// The saved picture is the card's own colour.
			$v['--twd-sb-export-bg']    = $card;
		} else {
			$stops = self::own_stops( $light );
			$card  = $light ? self::CARD_LIGHT_OWN : self::CARD_DARK_OWN;
		}
		$v['--twd-sb-card-bg'] = $card;

		// ---- the accent on the card ----
		$base = '' !== $accent ? $accent : ( $light ? self::ACCENT_LIGHT : self::ACCENT_DARK );
		// A little over 4.5:1 so that the softer 94% variant of it (headings, labels) still reaches 4.5:1.
		$text = self::readable( $base, $card, $light, 0.12, 4.7 );
		$v['--twd-sb-gold']       = $text;
		$v['--twd-sb-gold-soft']  = self::rgba( $text, 0.94 );
		$v['--twd-sb-gold-faint'] = self::rgba( $text, 0.28 );
		$v['--twd-sb-chip']       = self::rgba( $text, 0.08 );
		$v['--twd-sb-chip-hover'] = self::rgba( $text, 0.12 );
		$v['--twd-sb-chip-soft']  = self::rgba( $text, 0.08 );
		$v['--twd-sb-btn-rest']   = self::rgba( $text, 0.12 );
		$v['--twd-sb-btn-hover']  = self::rgba( $text, 0.24 );
		if ( '' !== $accent ) {
			$fill = $accent;
			$ink  = self::best_ink( $fill );
			$hov  = self::contrast( $ink, self::shade( $fill, 0.14, $ink ) ) >= self::contrast( $ink, $fill ) - 0.0001 ? self::shade( $fill, 0.14, $ink ) : $fill;
			$v['--twd-sb-primary']              = $fill;
			$v['--twd-sb-primary-hover']        = $hov;
			$v['--twd-sb-primary-ink']          = $ink;
			$v['--twd-sb-primary-shadow']       = '0 4px 14px ' . self::rgba( $fill, 0.3 );
			$v['--twd-sb-primary-shadow-hover'] = '0 6px 18px ' . self::rgba( $fill, 0.38 );
		}
		// ---- what sits on the frame: arrows, page numbers, Share and Save, Read the full article ----
		$f_light = self::min_contrast( '#000000', $stops ) >= self::min_contrast( '#ffffff', $stops );
		$ctl     = self::readable( $base, $stops, $f_light );
		// Solid colours, not see-through ones: a see-through ink on a mid-tone frame loses its contrast.
		$ink       = self::readable( $f_light ? '#2b2540' : '#fff8ec', $stops, $f_light );
		$ink_hover = $f_light ? self::mix( $ink, '#000000', 0.4 ) : self::mix( $ink, '#ffffff', 0.4 );
		// A pale tint of the writing colour behind a button would lower the contrast of the writing on it; on a
		// frame where that would take it under the limit (4.5:1 for Share, Save and the Read link, 3:1 for the
		// big arrows, tested at the strongest tint each has) the buttons are outlined only.
		$chip_ok = true;
		$btn_ok  = true;
		foreach ( $stops as $stop ) {
			$b = self::mix( $stop, $ctl, 0.18 );
			if ( self::contrast( $ctl, $b ) < 4.5 || self::contrast( $ink, $b ) < 4.5 ) {
				$chip_ok = false;
			}
			if ( self::contrast( $ctl, self::mix( $stop, $ctl, 0.28 ) ) < 3 ) {
				$btn_ok = false;
			}
		}
		$chip = $chip_ok ? 1 : 0;
		$btn  = $btn_ok ? 1 : 0;
		$v['--twd-sb-ctl']            = $ctl;
		$v['--twd-sb-ctl-line']       = self::rgba( $ctl, 0.4 );
		$v['--twd-sb-ctl-chip']       = self::rgba( $ctl, 0.1 * $chip );
		$v['--twd-sb-ctl-chip-hover'] = self::rgba( $ctl, 0.18 * $chip );
		$v['--twd-sb-ctl-rest']       = self::rgba( $ctl, 0.14 * $btn );
		$v['--twd-sb-ctl-hover']      = self::rgba( $ctl, 0.28 * $btn );
		$v['--twd-sb-read']           = $ink;
		$v['--twd-sb-read-line']      = self::rgba( $ink, 0.4 );
		$v['--twd-sb-read-hover']     = $ink_hover;

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
		return 'rgba(' . $c[0] . ', ' . $c[1] . ', ' . $c[2] . ', ' . rtrim( rtrim( sprintf( '%.2F', $alpha ), '0' ), '.' ) . ')';
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

	/** The lowest contrast of $color against one background or a list of them. */
	public static function min_contrast( $color, $bgs ) {
		$min = null;
		foreach ( (array) $bgs as $bg ) {
			$c   = self::contrast( $color, $bg );
			$min = null === $min ? $c : min( $min, $c );
		}
		return null === $min ? 21.0 : $min;
	}

	/**
	 * $color moved toward black (when $toward_dark) or white in small steps until it reads at least
	 * $min (4.5:1) against $bg (a colour, or a list of colours it has to read on all of), and, when $tint is
	 * above 0, also on $bg tinted that much by $color itself (the pale button fill behind it); if it
	 * never does, plain black or white.
	 */
	public static function readable( $color, $bg, $toward_dark, $tint = 0.0, $min = 4.5 ) {
		$target = $toward_dark ? '#000000' : '#ffffff';
		$bgs    = (array) $bg;
		for ( $i = 0; $i <= 20; $i++ ) {
			$c  = self::mix( $color, $target, $i / 20 );
			$ok = self::min_contrast( $c, $bgs ) >= $min;
			if ( $ok && $tint > 0 ) {
				foreach ( $bgs as $b ) {
					if ( self::contrast( $c, self::mix( $b, $c, $tint ) ) < $min ) {
						$ok = false;
						break;
					}
				}
			}
			if ( $ok ) {
				return $c;
			}
		}
		return $target;
	}
}
