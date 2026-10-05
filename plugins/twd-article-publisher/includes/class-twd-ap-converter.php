<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only tool that converts ONE Elementor-built post at a time into a
 * normal WordPress post the article popup can edit. Off unless the
 * "Elementor converter" tick-box in Settings > Article Publisher is on.
 *
 * What it touches: the post's content (post_content) and its Elementor
 * meta (every _elementor_* key), plus _wp_page_template if that was an
 * Elementor template. Title, URL, date, categories, tags, featured image,
 * excerpt and SEO meta (Yoast, Rank Math) are never touched, because the
 * same post is converted in place.
 *
 * Safety: refuses anything that is not made only of HTML or text widgets,
 * saves the original content and Elementor meta in a backup on the post
 * BEFORE changing anything, and offers Undo. Every action is re-checked on
 * the server (setting on, manage_options, edit_post, nonce); a preview is
 * never trusted.
 *
 * No hooks outside admin. Pure helpers (parse_rules, extract_content,
 * tidy) need no database so they can be tested on their own.
 */
class TWD_AP_Converter {

	const BACKUP_META = '_twd_ap_conv_backup';
	const PAGE_SLUG   = 'twd-ap-convert';

	// What an old class can be turned into by a rule.
	const TARGETS = array( 'h2', 'h3', 'h4', 'p', 'blockquote', 'strong', 'em', 'unwrap', 'remove' );

	// Elements that are kept as they are (the cleaner allows them).
	const KEEP_TAGS = array( 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'hr', 'figure', 'figcaption', 'a', 'img' );

	// Containers that carry no meaning of their own.
	const CONTAINER_TAGS = array( 'div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'address' );

	// Inline wrappers that are simply removed, keeping what is inside.
	const INLINE_UNWRAP_TAGS = array( 'span', 'font', 'center', 'small', 'mark', 'cite', 'code', 'pre', 'sup', 'sub', 'label', 'abbr', 'time' );

	// Removed together with everything inside them.
	const DROP_TAGS = array( 'script', 'style', 'noscript', 'template' );

	// Block level tags, used to decide paragraph wrapping.
	const BLOCK_TAGS = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'hr', 'figure', 'table', 'pre', 'div' );

	public static function default_rules() {
		return "# One rule per line: part of an old class name = what it should become.\n"
			. "# Targets: h2 h3 h4 p blockquote strong em unwrap remove\n"
			. "kcc-article__subhead = h2\n"
			. "kcc-article__quote = blockquote\n"
			. "kcc-article__intro = p\n"
			. "kcc-article__footer-text = p\n"
			. "kcc-body = p\n";
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_twd_ap_convert', array( __CLASS__, 'handle_convert' ) );
		add_action( 'admin_post_twd_ap_convert_undo', array( __CLASS__, 'handle_undo' ) );
		add_action( 'admin_post_twd_ap_convert_discard', array( __CLASS__, 'handle_discard' ) );
	}

	public static function is_enabled() {
		$settings = TWD_AP_Settings::get_settings();
		return ! empty( $settings['converter_enabled'] );
	}

	public static function tool_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'tools.php' ) );
	}

	// ------------------------------------------------------------------
	// Pure helpers (no WordPress database needed)
	// ------------------------------------------------------------------

	/**
	 * "old-class-part = target" per line. Blank lines and lines starting
	 * with # are ignored, as is anything malformed. Longest class part
	 * first, so a more specific rule wins over a shorter one.
	 */
	public static function parse_rules( $text ) {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$parts = explode( '=', $line, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$frag   = strtolower( trim( $parts[0] ) );
			$target = strtolower( trim( $parts[1] ) );
			if ( '' === $frag || ! preg_match( '/^[a-z0-9_\-]+$/', $frag ) || ! in_array( $target, self::TARGETS, true ) ) {
				continue;
			}
			$rules[] = array( $frag, $target );
		}
		usort(
			$rules,
			function ( $a, $b ) {
				return strlen( $b[0] ) - strlen( $a[0] );
			}
		);
		return $rules;
	}

	/**
	 * Walks Elementor's stored layout and collects the HTML from every
	 * html / text-editor widget in document order. Any other widget type
	 * is recorded under 'blocked' (spacers carry no content and are
	 * ignored). Custom CSS anywhere in the layout sets 'custom_css'.
	 */
	public static function extract_content( $data ) {
		$out = array(
			'html'       => array(),
			'blocked'    => array(),
			'custom_css' => false,
		);
		self::walk( $data, $out );
		return $out;
	}

	private static function walk( $nodes, &$out ) {
		if ( ! is_array( $nodes ) ) {
			return;
		}
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$settings = ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) ? $node['settings'] : array();
			if ( isset( $settings['custom_css'] ) && '' !== trim( (string) $settings['custom_css'] ) ) {
				$out['custom_css'] = true;
			}
			$type = isset( $node['elType'] ) ? (string) $node['elType'] : '';
			if ( 'widget' === $type ) {
				$widget = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
				if ( 'html' === $widget ) {
					$html = isset( $settings['html'] ) ? (string) $settings['html'] : '';
					if ( '' !== trim( $html ) ) {
						$out['html'][] = $html;
					}
				} elseif ( 'text-editor' === $widget ) {
					$html = isset( $settings['editor'] ) ? (string) $settings['editor'] : '';
					if ( '' !== trim( $html ) ) {
						$out['html'][] = function_exists( 'wpautop' ) ? wpautop( $html ) : $html;
					}
				} elseif ( 'spacer' !== $widget ) {
					$out['blocked'][ '' !== $widget ? $widget : 'unknown' ] = true;
				}
			}
			if ( ! empty( $node['elements'] ) ) {
				self::walk( $node['elements'], $out );
			}
		}
	}

	/**
	 * Turns widget HTML into clean article HTML BEFORE the plugin's own
	 * cleaner runs, so structure survives instead of being flattened:
	 * rule-mapped classes become real elements, wrapper divs are
	 * unwrapped, comments/scripts/styles are dropped, heading levels are
	 * fixed to what the cleaner allows, and (optionally) h3 is promoted to
	 * h2 when the post has no h2.
	 *
	 * Returns array( 'html' => string, 'notes' => array ).
	 */
	public static function tidy( $html, $rules, $promote ) {
		$notes = array(
			'rule_hits'  => array(),
			'dropped'    => array(),
			'odd_tags'   => array(),
			'dropped_cl' => array(),
			'styles'     => 0,
			'promoted'   => false,
			'skipped'    => array(),
		);

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument( '1.0', 'UTF-8' );
		$doc->loadHTML( '<?xml encoding="UTF-8"><div id="twd-conv-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$root = $doc->getElementsByTagName( 'div' )->item( 0 );
		if ( ! $root ) {
			return array( 'html' => '', 'notes' => $notes );
		}

		// Comments.
		$xp = new DOMXPath( $doc );
		foreach ( iterator_to_array( $xp->query( '//comment()' ) ) as $c ) {
			$c->parentNode->removeChild( $c );
		}

		// Scripts, styles and the like, with their contents.
		foreach ( self::DROP_TAGS as $tag ) {
			foreach ( iterator_to_array( $doc->getElementsByTagName( $tag ) ) as $el ) {
				if ( $el->parentNode ) {
					$notes['dropped'][ $tag ] = isset( $notes['dropped'][ $tag ] ) ? $notes['dropped'][ $tag ] + 1 : 1;
					$el->parentNode->removeChild( $el );
				}
			}
		}

		// Heading levels the cleaner cannot keep.
		foreach ( array( 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4' ) as $from => $to ) {
			foreach ( iterator_to_array( $doc->getElementsByTagName( $from ) ) as $el ) {
				self::rename( $doc, $el, $to );
			}
		}

		// Rules, then generic tidying, in document order.
		$nodes = iterator_to_array( $root->getElementsByTagName( '*' ) );
		foreach ( $nodes as $el ) {
			if ( ! $el instanceof DOMElement || ! self::attached( $el, $root ) ) {
				continue;
			}
			$tag = strtolower( $el->nodeName );
			if ( $el->hasAttribute( 'style' ) ) {
				$notes['styles']++;
			}

			$cls     = strtolower( trim( (string) $el->getAttribute( 'class' ) ) );
			$matched = false;
			if ( '' !== $cls ) {
				foreach ( $rules as $rule ) {
					if ( false === strpos( $cls, $rule[0] ) ) {
						continue;
					}
					$matched = true;
					$key     = $rule[0] . ' = ' . $rule[1];
					if ( self::apply_rule( $doc, $el, $rule[1], $notes ) ) {
						$notes['rule_hits'][ $key ] = isset( $notes['rule_hits'][ $key ] ) ? $notes['rule_hits'][ $key ] + 1 : 1;
					}
					break;
				}
			}
			if ( $matched ) {
				continue;
			}

			if ( in_array( $tag, self::CONTAINER_TAGS, true ) ) {
				self::note_classes( $el, $notes );
				if ( self::has_block_child( $el ) ) {
					self::unwrap( $el );
				} else {
					self::rename( $doc, $el, 'p' );
				}
			} elseif ( in_array( $tag, self::INLINE_UNWRAP_TAGS, true ) ) {
				self::note_classes( $el, $notes );
				self::unwrap( $el );
			} elseif ( in_array( $tag, self::KEEP_TAGS, true ) ) {
				if ( 'img' !== $tag && 'figure' !== $tag ) {
					self::note_classes( $el, $notes );
				}
			} else {
				$notes['odd_tags'][ $tag ] = true;
				self::note_classes( $el, $notes );
			}
		}

		// Heading hierarchy: a post with h3s but no h2 gets them promoted.
		if ( $promote && 0 === $root->getElementsByTagName( 'h2' )->length && $root->getElementsByTagName( 'h3' )->length > 0 ) {
			foreach ( iterator_to_array( $root->getElementsByTagName( 'h3' ) ) as $el ) {
				self::rename( $doc, $el, 'h2' );
			}
			foreach ( iterator_to_array( $root->getElementsByTagName( 'h4' ) ) as $el ) {
				self::rename( $doc, $el, 'h3' );
			}
			$notes['promoted'] = true;
		}

		// Empty paragraphs.
		foreach ( iterator_to_array( $root->getElementsByTagName( 'p' ) ) as $el ) {
			$text = preg_replace( '/[\s\x{00A0}]+/u', '', $el->textContent );
			if ( '' === $text && 0 === $el->getElementsByTagName( 'img' )->length ) {
				$el->parentNode->removeChild( $el );
			}
		}

		return array(
			'html'  => self::serialise( $doc, $root ),
			'notes' => $notes,
		);
	}

	private static function apply_rule( DOMDocument $doc, DOMElement $el, $target, &$notes ) {
		if ( 'remove' === $target ) {
			$el->parentNode->removeChild( $el );
			return true;
		}
		if ( 'unwrap' === $target ) {
			self::unwrap( $el );
			return true;
		}
		// Turning a wrapper that holds other blocks into a heading or a
		// paragraph would produce invalid markup, so unwrap it instead
		// and say so.
		if ( 'blockquote' !== $target && self::has_block_child( $el ) ) {
			$notes['skipped'][ strtolower( $el->nodeName ) . ' (' . $target . ')' ] = true;
			self::unwrap( $el );
			return false;
		}
		self::rename( $doc, $el, $target );
		return true;
	}

	private static function note_classes( DOMElement $el, &$notes ) {
		$cls = trim( (string) $el->getAttribute( 'class' ) );
		if ( '' === $cls ) {
			return;
		}
		foreach ( preg_split( '/\s+/', $cls ) as $token ) {
			if ( '' !== $token && count( $notes['dropped_cl'] ) < 30 ) {
				$notes['dropped_cl'][ $token ] = true;
			}
		}
	}

	private static function rename( DOMDocument $doc, DOMElement $el, $tag ) {
		$new = $doc->createElement( $tag );
		while ( $el->firstChild ) {
			$new->appendChild( $el->firstChild );
		}
		$el->parentNode->replaceChild( $new, $el );
		return $new;
	}

	private static function unwrap( DOMElement $el ) {
		$parent = $el->parentNode;
		while ( $el->firstChild ) {
			$parent->insertBefore( $el->firstChild, $el );
		}
		$parent->removeChild( $el );
	}

	private static function attached( DOMNode $node, DOMNode $root ) {
		$cur = $node;
		while ( $cur ) {
			if ( $cur->isSameNode( $root ) ) {
				return true;
			}
			$cur = $cur->parentNode;
		}
		return false;
	}

	private static function has_block_child( DOMElement $el ) {
		foreach ( self::BLOCK_TAGS as $tag ) {
			if ( $el->getElementsByTagName( $tag )->length > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Root level text and inline elements are grouped into paragraphs,
	 * blocks are written out as they are.
	 */
	private static function serialise( DOMDocument $doc, DOMElement $root ) {
		$out    = array();
		$inline = '';
		$flush  = function () use ( &$out, &$inline ) {
			if ( '' !== trim( $inline ) ) {
				$out[] = '<p>' . trim( $inline ) . '</p>';
			}
			$inline = '';
		};
		foreach ( $root->childNodes as $child ) {
			if ( $child instanceof DOMElement && in_array( strtolower( $child->nodeName ), self::BLOCK_TAGS, true ) ) {
				$flush();
				$out[] = trim( $doc->saveHTML( $child ) );
			} elseif ( $child instanceof DOMText ) {
				$inline .= htmlspecialchars( $child->nodeValue, ENT_NOQUOTES, 'UTF-8' );
			} else {
				$inline .= $doc->saveHTML( $child );
			}
		}
		$flush();
		// Source HTML is often indented, which leaves stray spaces just
		// inside a block's tags.
		$html = implode( "\n", $out );
		$html = preg_replace( '/(<(?:p|h[2-4]|li|blockquote)>)\s+/', '$1', $html );
		$html = preg_replace( '/\s+(<\/(?:p|h[2-4]|li|blockquote)>)/', '$1', $html );
		return $html;
	}

	private static function plain_length( $html ) {
		$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return strlen( preg_replace( '/[\s\x{00A0}]+/u', '', $text ) );
	}

	// ------------------------------------------------------------------
	// Analysis, conversion, undo (need WordPress)
	// ------------------------------------------------------------------

	private static function elementor_meta_keys( $post_id ) {
		$keys = array();
		foreach ( array_keys( (array) get_post_meta( $post_id ) ) as $key ) {
			if ( 0 === strpos( $key, '_elementor_' ) ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Works out what converting this post would do, without changing
	 * anything. Returns an array (status safe / warning / blocked, reasons,
	 * warnings, info, source, final html) or a WP_Error.
	 */
	public static function analyse( $post_id, $promote = true ) {
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'twd_ap_conv_not_found', __( 'That post could not be found.', 'twd-article-publisher' ) );
		}

		$result = array(
			'post_id'    => (int) $post_id,
			'status'     => 'safe',
			'reasons'    => array(),
			'warnings'   => array(),
			'info'       => array(),
			'source'     => '',
			'tidy_html'  => '',
			'final_html' => '',
			'dropped_cl' => array(),
		);

		if ( is_array( get_post_meta( $post_id, self::BACKUP_META, true ) ) ) {
			$result['status']    = 'blocked';
			$result['reasons'][] = __( 'This post has already been converted. Undo the conversion first if you want to start again.', 'twd-article-publisher' );
			return $result;
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( '' === $raw || false === $raw ) {
			return new WP_Error( 'twd_ap_conv_no_elementor', __( 'This post has no Elementor content to convert.', 'twd-article-publisher' ) );
		}
		$data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'twd_ap_conv_bad_data', __( 'The Elementor data on this post could not be read, so nothing was changed.', 'twd-article-publisher' ) );
		}

		$found = self::extract_content( $data );
		if ( ! empty( $found['blocked'] ) ) {
			$result['status']    = 'blocked';
			$result['reasons'][] = sprintf(
				/* translators: %s: comma separated list of Elementor widget names */
				__( 'This post uses other Elementor widgets (%s). Only posts made entirely of HTML or text widgets can be converted.', 'twd-article-publisher' ),
				implode( ', ', array_keys( $found['blocked'] ) )
			);
		}
		if ( empty( $found['html'] ) ) {
			$result['status']    = 'blocked';
			$result['reasons'][] = __( 'No HTML or text widget content was found in this post.', 'twd-article-publisher' );
		}
		if ( 'blocked' === $result['status'] ) {
			return $result;
		}

		$settings = TWD_AP_Settings::get_settings();
		$rules    = self::parse_rules( isset( $settings['converter_rules'] ) ? $settings['converter_rules'] : '' );
		$source   = implode( "\n", $found['html'] );

		$tidy = self::tidy( $source, $rules, $promote );
		$note = $tidy['notes'];

		$result['source']     = $source;
		$result['tidy_html']  = $tidy['html'];
		$result['final_html'] = TWD_AP_Sanitizer::clean( $tidy['html'] );
		$result['dropped_cl'] = array_keys( $note['dropped_cl'] );

		if ( '' === trim( $result['final_html'] ) ) {
			$result['status']    = 'blocked';
			$result['reasons'][] = __( 'Nothing usable was left after cleaning, so nothing was changed.', 'twd-article-publisher' );
			return $result;
		}

		// Warnings: things worth looking at before converting.
		if ( $found['custom_css'] ) {
			$result['warnings'][] = __( 'This post has custom CSS set in Elementor. It will stop applying after conversion.', 'twd-article-publisher' );
		}
		foreach ( $note['dropped'] as $tag => $count ) {
			if ( 'script' === $tag ) {
				$result['warnings'][] = sprintf(
					/* translators: %d: number of script blocks */
					_n( '%d script block will be removed (for example structured data for search engines).', '%d script blocks will be removed (for example structured data for search engines).', $count, 'twd-article-publisher' ),
					$count
				);
			} else {
				/* translators: 1: number of blocks, 2: tag name */
				$result['warnings'][] = sprintf( __( '%1$d <%2$s> block will be removed.', 'twd-article-publisher' ), $count, $tag );
			}
		}
		if ( ! empty( $note['odd_tags'] ) ) {
			$result['warnings'][] = sprintf(
				/* translators: %s: list of HTML tag names */
				__( 'These elements cannot be kept and will be removed (their text stays): %s.', 'twd-article-publisher' ),
				implode( ', ', array_keys( $note['odd_tags'] ) )
			);
		}
		if ( $note['styles'] > 0 ) {
			$result['warnings'][] = sprintf(
				/* translators: %d: number of elements with inline styles */
				_n( 'Inline styling on %d element will be removed.', 'Inline styling on %d elements will be removed.', $note['styles'], 'twd-article-publisher' ),
				$note['styles']
			);
		}
		if ( ! empty( $note['skipped'] ) ) {
			$result['warnings'][] = sprintf(
				/* translators: %s: list of elements */
				__( 'A rule could not be applied to wrapper elements that hold other blocks, so they were simply unwrapped: %s.', 'twd-article-publisher' ),
				implode( ', ', array_keys( $note['skipped'] ) )
			);
		}
		$dashes = preg_match_all( '/\x{2014}|\x{2013}/u', $tidy['html'] );
		if ( $dashes > 0 ) {
			$result['warnings'][] = sprintf(
				/* translators: %d: number of em or en dashes */
				_n( '%d em or en dash will be changed to a comma (site rule: no dashes).', '%d em or en dashes will be changed to commas (site rule: no dashes).', $dashes, 'twd-article-publisher' ),
				$dashes
			);
		}
		$before = self::plain_length( $tidy['html'] );
		$after  = self::plain_length( $result['final_html'] );
		if ( $before > 0 && $after < $before * 0.95 ) {
			$result['warnings'][] = __( 'The cleaned text is noticeably shorter than the original. Compare the two carefully before converting.', 'twd-article-publisher' );
		}
		$template = (string) get_post_meta( $post_id, '_wp_page_template', true );
		if ( 0 === strpos( $template, 'elementor_' ) ) {
			$result['warnings'][] = __( 'This post uses an Elementor page template (such as Canvas). It will be switched back to the theme default so the header and footer show.', 'twd-article-publisher' );
		}

		// Info: what the tool will do for you.
		foreach ( $note['rule_hits'] as $rule => $count ) {
			/* translators: 1: rule such as "old-class = h2", 2: number of times it applied */
			$result['info'][] = sprintf( __( 'Rule "%1$s" applied %2$d time(s).', 'twd-article-publisher' ), $rule, $count );
		}
		if ( $note['promoted'] ) {
			$result['info'][] = __( 'There were no h2 headings, so h3 headings were promoted to h2 (and h4 to h3) to give the page a correct outline.', 'twd-article-publisher' );
		}

		if ( ! empty( $result['warnings'] ) ) {
			$result['status'] = 'warning';
		}
		return $result;
	}

	/**
	 * Converts one post. Backs up first, refuses blocked posts, and checks
	 * every WordPress call. Returns the analysis array or a WP_Error.
	 */
	public static function convert( $post_id, $promote ) {
		$analysis = self::analyse( $post_id, $promote );
		if ( is_wp_error( $analysis ) ) {
			return $analysis;
		}
		if ( 'blocked' === $analysis['status'] ) {
			return new WP_Error( 'twd_ap_conv_blocked', implode( ' ', $analysis['reasons'] ) );
		}

		$post = get_post( $post_id );

		$meta = array();
		foreach ( self::elementor_meta_keys( $post_id ) as $key ) {
			$values = get_post_meta( $post_id, $key, false );
			$meta[ $key ] = array_map( 'maybe_unserialize', (array) $values );
		}
		$template = (string) get_post_meta( $post_id, '_wp_page_template', true );

		$backup = array(
			'time'           => time(),
			'user'           => get_current_user_id(),
			'plugin_version' => TWD_AP_VERSION,
			'post_content'   => $post->post_content,
			'meta'           => $meta,
			'page_template'  => $template,
			'converted_md5'  => '',
		);

		// The backup goes in FIRST. If it cannot be saved, nothing else happens.
		$saved = update_post_meta( $post_id, self::BACKUP_META, wp_slash( $backup ) );
		if ( false === $saved || ! is_array( get_post_meta( $post_id, self::BACKUP_META, true ) ) ) {
			return new WP_Error( 'twd_ap_conv_backup_failed', __( 'Could not save a backup, so nothing was changed.', 'twd-article-publisher' ) );
		}

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $analysis['final_html'],
				)
			),
			true
		);
		if ( is_wp_error( $updated ) || ! $updated ) {
			delete_post_meta( $post_id, self::BACKUP_META );
			return is_wp_error( $updated ) ? $updated : new WP_Error( 'twd_ap_conv_save_failed', __( 'WordPress could not save the converted content, so nothing was changed.', 'twd-article-publisher' ) );
		}

		foreach ( array_keys( $meta ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
		if ( 0 === strpos( $template, 'elementor_' ) ) {
			update_post_meta( $post_id, '_wp_page_template', 'default' );
		}

		clean_post_cache( $post_id );
		$now                     = get_post( $post_id );
		$backup['converted_md5'] = md5( $now ? $now->post_content : '' );
		update_post_meta( $post_id, self::BACKUP_META, wp_slash( $backup ) );

		return $analysis;
	}

	/**
	 * Puts the original content and Elementor meta back exactly. If the
	 * article was edited after conversion, that needs $force, because
	 * those edits are lost on undo.
	 */
	public static function undo( $post_id, $force ) {
		$backup = get_post_meta( $post_id, self::BACKUP_META, true );
		$post   = get_post( $post_id );
		if ( ! $post || ! is_array( $backup ) || ! isset( $backup['post_content'], $backup['meta'] ) ) {
			return new WP_Error( 'twd_ap_conv_no_backup', __( 'There is no saved backup for this post, so there is nothing to undo.', 'twd-article-publisher' ) );
		}
		if ( ! $force && ! empty( $backup['converted_md5'] ) && md5( $post->post_content ) !== $backup['converted_md5'] ) {
			return new WP_Error( 'twd_ap_conv_edited', __( 'This article has been edited since it was converted. Undoing will lose those edits. Tick the box to go ahead anyway.', 'twd-article-publisher' ) );
		}

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $backup['post_content'],
				)
			),
			true
		);
		if ( is_wp_error( $updated ) || ! $updated ) {
			return is_wp_error( $updated ) ? $updated : new WP_Error( 'twd_ap_conv_restore_failed', __( 'WordPress could not restore the original content, so the backup was kept.', 'twd-article-publisher' ) );
		}

		foreach ( self::elementor_meta_keys( $post_id ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
		foreach ( (array) $backup['meta'] as $key => $values ) {
			foreach ( (array) $values as $value ) {
				add_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}
		if ( ! empty( $backup['page_template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', $backup['page_template'] );
		}

		delete_post_meta( $post_id, self::BACKUP_META );
		clean_post_cache( $post_id );
		return true;
	}

	// ------------------------------------------------------------------
	// Admin screen and handlers
	// ------------------------------------------------------------------

	public static function add_menu() {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_management_page(
			__( 'Convert Elementor posts', 'twd-article-publisher' ),
			__( 'Convert Elementor posts', 'twd-article-publisher' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	private static function guard( $post_id ) {
		if ( ! self::is_enabled() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'twd-article-publisher' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'twd_ap_conv_' . $post_id );
	}

	private static function finish( $type, $message, $post_id ) {
		set_transient( 'twd_ap_conv_msg_' . get_current_user_id(), array( $type, $message, (int) $post_id ), 120 );
		wp_safe_redirect( self::tool_url() );
		exit;
	}

	public static function handle_convert() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		self::guard( $post_id );
		$result = self::convert( $post_id, ! empty( $_POST['promote'] ) );
		if ( is_wp_error( $result ) ) {
			self::finish( 'error', $result->get_error_message(), $post_id );
		}
		self::finish( 'success', __( 'Converted. A backup was saved, and you can undo it below.', 'twd-article-publisher' ), $post_id );
	}

	public static function handle_undo() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		self::guard( $post_id );
		$result = self::undo( $post_id, ! empty( $_POST['force'] ) );
		if ( is_wp_error( $result ) ) {
			self::finish( 'error', $result->get_error_message(), $post_id );
		}
		self::finish( 'success', __( 'Undone. The original Elementor post has been restored.', 'twd-article-publisher' ), $post_id );
	}

	public static function handle_discard() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		self::guard( $post_id );
		delete_post_meta( $post_id, self::BACKUP_META );
		self::finish( 'success', __( 'Backup removed. This conversion can no longer be undone.', 'twd-article-publisher' ), $post_id );
	}

	public static function render_page() {
		if ( ! self::is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Convert Elementor posts', 'twd-article-publisher' ) . '</h1>';

		$msg = get_transient( 'twd_ap_conv_msg_' . get_current_user_id() );
		if ( is_array( $msg ) ) {
			delete_transient( 'twd_ap_conv_msg_' . get_current_user_id() );
			$class = 'success' === $msg[0] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $msg[1] );
			if ( 'success' === $msg[0] && ! empty( $msg[2] ) ) {
				echo ' <a href="' . esc_url( get_permalink( (int) $msg[2] ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the post', 'twd-article-publisher' ) . '</a>';
			}
			echo '</p></div>';
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			self::render_preview( $post_id );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function render_list() {
		echo '<p>' . esc_html__( 'Converts one Elementor post at a time into a normal post the article popup can edit. Title, address, categories, tags, featured image and SEO details stay exactly as they are. A backup is saved first, and every conversion can be undone.', 'twd-article-publisher' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Before you start:', 'twd-article-publisher' ) . '</strong> ' . esc_html__( 'take a full site backup, and try this on a copy of the site first.', 'twd-article-publisher' ) . '</p>';

		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 30,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_elementor_data',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => self::BACKUP_META,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'No Elementor-built posts were found on this site.', 'twd-article-publisher' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Post', 'twd-article-publisher' ) . '</th><th>' . esc_html__( 'Status', 'twd-article-publisher' ) . '</th><th>' . esc_html__( 'Converter', 'twd-article-publisher' ) . '</th><th></th></tr></thead><tbody>';
		while ( $query->have_posts() ) {
			$query->the_post();
			$id        = get_the_ID();
			$backup    = get_post_meta( $id, self::BACKUP_META, true );
			$converted = is_array( $backup );
			echo '<tr><td><strong>' . esc_html( get_the_title() ) . '</strong></td>';
			echo '<td>' . esc_html( get_post_status( $id ) ) . '</td>';
			echo '<td>' . ( $converted ? esc_html__( 'Converted', 'twd-article-publisher' ) : esc_html__( 'Not checked yet', 'twd-article-publisher' ) ) . '</td><td>';
			if ( $converted ) {
				self::render_undo_forms( $id, $backup );
			} else {
				echo '<a class="button" href="' . esc_url( self::tool_url( array( 'post_id' => $id ) ) ) . '">' . esc_html__( 'Check and preview', 'twd-article-publisher' ) . '</a>';
			}
			echo '</td></tr>';
		}
		wp_reset_postdata();
		echo '</tbody></table>';

		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%', self::tool_url() ),
				'format'  => '',
				'current' => $paged,
				'total'   => (int) $query->max_num_pages,
			)
		);
		if ( $links ) {
			echo '<p>' . wp_kses_post( $links ) . '</p>';
		}
	}

	private static function render_undo_forms( $id, $backup ) {
		$post   = get_post( $id );
		$edited = $post && ! empty( $backup['converted_md5'] ) && md5( $post->post_content ) !== $backup['converted_md5'];
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;">';
		wp_nonce_field( 'twd_ap_conv_' . $id );
		echo '<input type="hidden" name="action" value="twd_ap_convert_undo" /><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '" />';
		if ( $edited ) {
			echo '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="force" value="1" /> ' . esc_html__( 'Edited since converting: discard those edits', 'twd-article-publisher' ) . '</label>';
		}
		echo '<button type="submit" class="button">' . esc_html__( 'Undo conversion', 'twd-article-publisher' ) . '</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
		wp_nonce_field( 'twd_ap_conv_' . $id );
		echo '<input type="hidden" name="action" value="twd_ap_convert_discard" /><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '" />';
		echo '<button type="submit" class="button-link">' . esc_html__( 'Keep it converted, delete the backup', 'twd-article-publisher' ) . '</button></form>';
	}

	private static function render_preview( $post_id ) {
		$promote  = isset( $_GET['preview'] ) ? ! empty( $_GET['promote'] ) : true;
		$analysis = self::analyse( $post_id, $promote );

		echo '<p><a href="' . esc_url( self::tool_url() ) . '">&larr; ' . esc_html__( 'Back to the list', 'twd-article-publisher' ) . '</a></p>';
		echo '<h2>' . esc_html( get_the_title( $post_id ) ) . '</h2>';

		if ( is_wp_error( $analysis ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $analysis->get_error_message() ) . '</p></div>';
			return;
		}

		$labels = array(
			'safe'    => __( 'Safe to convert', 'twd-article-publisher' ),
			'warning' => __( 'Convertible, with warnings', 'twd-article-publisher' ),
			'blocked' => __( 'Cannot be converted', 'twd-article-publisher' ),
		);
		echo '<p><strong>' . esc_html( $labels[ $analysis['status'] ] ) . '</strong></p>';

		if ( ! empty( $analysis['reasons'] ) ) {
			echo '<div class="notice notice-error inline"><ul style="list-style:disc;margin-left:20px;">';
			foreach ( $analysis['reasons'] as $r ) {
				echo '<li>' . esc_html( $r ) . '</li>';
			}
			echo '</ul></div>';
		}
		if ( ! empty( $analysis['warnings'] ) ) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Check these before converting:', 'twd-article-publisher' ) . '</strong></p><ul style="list-style:disc;margin-left:20px;">';
			foreach ( $analysis['warnings'] as $w ) {
				echo '<li>' . esc_html( $w ) . '</li>';
			}
			echo '</ul></div>';
		}
		if ( ! empty( $analysis['info'] ) ) {
			echo '<ul style="list-style:disc;margin-left:20px;">';
			foreach ( $analysis['info'] as $i ) {
				echo '<li>' . esc_html( $i ) . '</li>';
			}
			echo '</ul>';
		}
		if ( 'blocked' === $analysis['status'] ) {
			return;
		}
		if ( ! empty( $analysis['dropped_cl'] ) ) {
			echo '<p class="description">' . esc_html__( 'Old class names that will be dropped (if one of these carried meaning, add a rule for it in Settings > Article Publisher and check again):', 'twd-article-publisher' ) . ' <code>' . esc_html( implode( ' ', $analysis['dropped_cl'] ) ) . '</code></p>';
		}

		echo '<form method="get" style="margin:12px 0;"><input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" /><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '" /><input type="hidden" name="preview" value="1" />';
		echo '<label><input type="checkbox" name="promote" value="1" ' . checked( $promote, true, false ) . ' /> ' . esc_html__( 'If there are no h2 headings, promote h3 to h2', 'twd-article-publisher' ) . '</label> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Update preview', 'twd-article-publisher' ) . '</button></form>';

		echo '<h3>' . esc_html__( 'What the converted post will contain', 'twd-article-publisher' ) . '</h3>';
		echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:16px 24px;max-width:760px;">' . wp_kses_post( $analysis['final_html'] ) . '</div>';
		echo '<details style="margin-top:12px;"><summary>' . esc_html__( 'Show the original widget HTML', 'twd-article-publisher' ) . '</summary><pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;max-width:760px;">' . esc_html( $analysis['source'] ) . '</pre></details>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:20px;">';
		wp_nonce_field( 'twd_ap_conv_' . $post_id );
		echo '<input type="hidden" name="action" value="twd_ap_convert" /><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '" />';
		if ( $promote ) {
			echo '<input type="hidden" name="promote" value="1" />';
		}
		echo '<p class="description">' . esc_html__( 'A backup of the original is saved first. You can undo this afterwards.', 'twd-article-publisher' ) . '</p>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Convert this post', 'twd-article-publisher' ) . '</button></form>';
	}
}
