<?php
/**
 * WM BFSG AST Remediator — Accessibility Rules Engine
 *
 * Five fixed rules applied to the page markup before it is sent. Each names the WCAG success
 * criterion it relates to; applying a rule does not make the criterion met, and the engine does not
 * check a page for accessibility.
 *
 * @package WM_BFSG_AST_Remediator
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WM_BFSG_Rules_Engine {

	/**
	 * Rule keys, in execution order. Every key is on unless the caller switches it off.
	 */
	public const RULES = [ 'lang', 'skip_link', 'images_alt', 'form_labels', 'nav_landmarks' ];

	/**
	 * Apply the switched-on rules to a DOMDocument instance. The codes name the WCAG criterion a rule relates to
	 * and what it added; they are not conformance results.
	 *
	 * @param DOMDocument         $dom
	 * @param array<string, bool> $enabled Rule key => on/off. Missing keys default to on.
	 * @return array{fixed_rules: list<string>, count: int}
	 */
	public static function apply_rules( DOMDocument $dom, array $enabled = [] ): array {
		$fixes = [];
		$xpath = new DOMXPath( $dom );
		$on    = static fn( string $rule ): bool => (bool) ( $enabled[ $rule ] ?? true );

		// Rule 1: Document Language (WCAG 3.1.1)
		$rule1 = $on( 'lang' ) ? self::remediate_html_lang( $dom, $xpath ) : null;
		if ( $rule1 ) {
			$fixes[] = $rule1;
		}

		// Rule 2: Skip-Link & Main Landmark Anchor (WCAG 2.4.1)
		$rule2 = $on( 'skip_link' ) ? self::remediate_skip_link( $dom, $xpath ) : null;
		if ( $rule2 ) {
			$fixes[] = $rule2;
		}

		// Rule 3: Image Alt Attributes (WCAG 1.1.1)
		$rule3 = $on( 'images_alt' ) ? self::remediate_images_alt( $dom, $xpath ) : null;
		if ( $rule3 ) {
			$fixes[] = $rule3;
		}

		// Rule 4: Form Input Accessible Names (WCAG 1.3.1 / 4.1.2)
		$rule4 = $on( 'form_labels' ) ? self::remediate_form_labels( $dom, $xpath ) : null;
		if ( $rule4 ) {
			$fixes[] = $rule4;
		}

		// Rule 5: Navigation Landmarks Disambiguation (WCAG 1.3.1)
		$rule5 = $on( 'nav_landmarks' ) ? self::remediate_navigation_landmarks( $dom, $xpath ) : null;
		if ( $rule5 ) {
			$fixes[] = $rule5;
		}

		return [
			'fixed_rules' => $fixes,
			'count'       => count( $fixes ),
		];
	}

	/**
	 * Rule 1: Ensure <html lang="..."> attribute exists.
	 */
	private static function remediate_html_lang( DOMDocument $dom, DOMXPath $xpath ): ?string {
		$html_nodes = $xpath->query( '/html' );
		if ( $html_nodes && $html_nodes->length > 0 ) {
			$html = $html_nodes->item( 0 );
			if ( $html instanceof DOMElement && ( ! $html->hasAttribute( 'lang' ) || '' === trim( $html->getAttribute( 'lang' ) ) ) ) {
				$default_lang = function_exists( 'get_locale' ) ? str_replace( '_', '-', get_locale() ) : 'de';
				$html->setAttribute( 'lang', $default_lang );
				return 'WCAG_3.1.1_HTML_LANG_ADDED';
			}
		}
		return null;
	}

	/**
	 * Rule 2: a skip link to the main landmark, when the page has one and no skip link of its own.
	 *
	 * The link points at the landmark's own id (set to `main-content` only when it has none). Until
	 * 2026-09-14 the link always pointed at #main-content, so a theme whose <main> already carried an
	 * id (id="primary") got a link to nothing; it was also injected on pages without a landmark, and
	 * an inline `top:-999px` kept it off screen even when focused.
	 */
	private static function remediate_skip_link( DOMDocument $dom, DOMXPath $xpath ): ?string {
		$body_nodes = $xpath->query( '//body' );
		if ( ! $body_nodes || 0 === $body_nodes->length ) {
			return null;
		}
		$body = $body_nodes->item( 0 );
		if ( ! $body instanceof DOMElement ) {
			return null;
		}

		$main_nodes = $xpath->query( '//main | //*[@role="main"]' );
		$main       = ( $main_nodes && $main_nodes->length > 0 ) ? $main_nodes->item( 0 ) : null;
		if ( ! $main instanceof DOMElement ) {
			return null; // No landmark, no target: a skip link would lead nowhere.
		}

		$modified = false;
		$target   = trim( $main->getAttribute( 'id' ) );
		if ( '' === $target ) {
			// An id another element already carries would send the link there (the first match wins).
			// The candidates are fixed strings, so no page-controlled value reaches the XPath expression.
			$target = 'main-content';
			for ( $n = 2; ( $taken = $xpath->query( '//*[@id="' . $target . '"]' ) ) && $taken->length > 0; ++$n ) {
				$target = 'main-content-' . $n;
			}
			$main->setAttribute( 'id', $target );
			$modified = true;
		}

		if ( self::has_skip_link( $xpath, $target ) ) {
			return $modified ? 'WCAG_2.4.1_MAIN_LANDMARK_ID_ADDED' : null;
		}

		$skip_link = $dom->createElement( 'a', 'Zum Inhalt springen' );
		$skip_link->setAttribute( 'href', '#' . $target );
		$skip_link->setAttribute( 'class', 'wm-bfsg-skip-link' );

		// Off screen until focused, then at the top left. System colours follow the visitor's colour
		// scheme and forced-colours mode; the theme's design tokens are unknown here.
		$style = $dom->createElement( 'style', '.wm-bfsg-skip-link{position:absolute;left:0;top:0;z-index:100000;padding:.5em 1em;background:Canvas;color:CanvasText;border:2px solid CanvasText;transform:translateY(-200%)}.wm-bfsg-skip-link:focus{transform:none}' );

		if ( $body->firstChild ) {
			$body->insertBefore( $skip_link, $body->firstChild );
		} else {
			$body->appendChild( $skip_link );
		}

		// <style> belongs in <head>; a document without one gets it right before the link.
		$head_nodes = $xpath->query( '//head' );
		$head       = ( $head_nodes && $head_nodes->length > 0 ) ? $head_nodes->item( 0 ) : null;
		if ( $head instanceof DOMElement ) {
			$head->appendChild( $style );
		} else {
			$body->insertBefore( $style, $skip_link );
		}

		return 'WCAG_2.4.1_SKIP_LINK_ADDED';
	}

	/**
	 * A same-page link that is already a skip link: it points at the target, or its class or text
	 * says so (German and English wording).
	 */
	private static function has_skip_link( DOMXPath $xpath, string $target ): bool {
		$links = $xpath->query( '//a[starts-with(@href, "#")]' );
		if ( ! $links ) {
			return false;
		}
		foreach ( $links as $link ) {
			if ( ! $link instanceof DOMElement ) {
				continue;
			}
			$href  = $link->getAttribute( 'href' );
			$class = strtolower( $link->getAttribute( 'class' ) );
			$text  = strtolower( trim( $link->textContent . ' ' . $link->getAttribute( 'title' ) ) );
			if ( '#' . $target === $href || str_contains( $class, 'skip' ) || str_contains( $text, 'inhalt' ) || str_contains( $text, 'skip to' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rule 3: Ensure all img-Element nodes have an alt attribute (empty for decorative, descriptive fallback for content).
	 */
	private static function remediate_images_alt( DOMDocument $dom, DOMXPath $xpath ): ?string {
		$img_nodes = $xpath->query( '//img[not(@alt)]' );
		if ( ! $img_nodes || 0 === $img_nodes->length ) {
			return null;
		}

		$count = 0;
		foreach ( $img_nodes as $img ) {
			if ( $img instanceof DOMElement ) {
				$src = $img->getAttribute( 'src' );
				// wp_parse_url() returns false for a malformed URL (http:///x.jpg) and null without a path.
				// `false ?? ''` stays false, and pathinfo( false ) under strict_types ended every page with
				// such an image in a fatal error until 2026-09-15.
				$path       = wp_parse_url( $src, PHP_URL_PATH );
				$path       = is_string( $path ) ? $path : '';
				$filename   = pathinfo( $path, PATHINFO_FILENAME );
				$clean_name = trim( (string) preg_replace( '/[_\-+]+/', ' ', $filename ) );

				// Decorative guess from the path only, case-insensitive. The whole src was checked before, so
				// every image on a host or with a query containing "icon" was hidden from screen readers.
				$path_lc = strtolower( $path );
				if ( str_contains( $path_lc, 'icon' ) || str_contains( $path_lc, 'decor' ) || '' === $clean_name ) {
					$img->setAttribute( 'alt', '' );
				} else {
					$img->setAttribute( 'alt', ucfirst( $clean_name ) );
				}
				++$count;
			}
		}

		return $count > 0 ? "WCAG_1.1.1_IMAGE_ALT_ADDED_{$count}" : null;
	}

	/**
	 * Rule 4: an aria-label (placeholder, else name, else "Eingabefeld") for form controls without any
	 * accessible name.
	 *
	 * A control inside a <label>, or with a title, already has a name. Until 2026-09-14 both got an
	 * aria-label, which replaces that name: <label>Name <input name="n"></label> was announced as "N".
	 */
	private static function remediate_form_labels( DOMDocument $dom, DOMXPath $xpath ): ?string {
		$inputs = $xpath->query( '//input[not(@type="hidden" or @type="submit" or @type="button" or @type="reset" or @type="image") and not(@aria-label) and not(@aria-labelledby) and not(ancestor::label) and not(normalize-space(@title))] | //select[not(@aria-label) and not(@aria-labelledby) and not(ancestor::label) and not(normalize-space(@title))] | //textarea[not(@aria-label) and not(@aria-labelledby) and not(ancestor::label) and not(normalize-space(@title))]' );
		if ( ! $inputs || 0 === $inputs->length ) {
			return null;
		}

		// Index <label for> once. Comparing in PHP keeps page-controlled ids out of the XPath
		// expression: an id with a quote in it used to break the query silently (libxml swallows
		// the error) and every labelled control got an invented aria-label.
		$labelled_ids = [];
		$labels       = $xpath->query( '//label[@for]' );
		if ( $labels ) {
			foreach ( $labels as $label ) {
				if ( $label instanceof DOMElement ) {
					$labelled_ids[ $label->getAttribute( 'for' ) ] = true;
				}
			}
		}

		$repaired = 0;
		foreach ( $inputs as $input ) {
			if ( $input instanceof DOMElement ) {
				$id        = $input->getAttribute( 'id' );
				$has_label = '' !== $id && isset( $labelled_ids[ $id ] );

				if ( ! $has_label ) {
					$name = $input->getAttribute( 'name' );
					$placeholder = $input->getAttribute( 'placeholder' );
					$label_text = ! empty( $placeholder ) ? $placeholder : ( ! empty( $name ) ? ucfirst( $name ) : 'Eingabefeld' );
					$input->setAttribute( 'aria-label', $label_text );
					++$repaired;
				}
			}
		}

		return $repaired > 0 ? "WCAG_4.1.2_FORM_ARIA_LABEL_ADDED_{$repaired}" : null;
	}

	/**
	 * Rule 5: Ensure multiple <nav> landmarks have distinctive aria-label attributes.
	 */
	private static function remediate_navigation_landmarks( DOMDocument $dom, DOMXPath $xpath ): ?string {
		$navs = $xpath->query( '//nav[not(@aria-label) and not(@aria-labelledby)]' );
		if ( ! $navs || $navs->length <= 1 ) {
			return null;
		}

		$count = 0;
		$index = 1;
		foreach ( $navs as $nav ) {
			if ( $nav instanceof DOMElement ) {
				$class = strtolower( $nav->getAttribute( 'class' ) );
				if ( str_contains( $class, 'main' ) || str_contains( $class, 'primary' ) || str_contains( $class, 'header' ) ) {
					$nav->setAttribute( 'aria-label', 'Hauptnavigation' );
				} elseif ( str_contains( $class, 'footer' ) ) {
					$nav->setAttribute( 'aria-label', 'Fußzeilennavigation' );
				} elseif ( str_contains( $class, 'mobile' ) ) {
					$nav->setAttribute( 'aria-label', 'Mobilmenü' );
				} else {
					$nav->setAttribute( 'aria-label', 'Navigation ' . $index );
				}
				++$count;
				++$index;
			}
		}

		return $count > 0 ? "WCAG_1.3.1_NAV_ARIA_LABEL_ADDED_{$count}" : null;
	}
}
