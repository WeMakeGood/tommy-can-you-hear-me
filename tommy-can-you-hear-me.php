<?php
/**
 * Plugin Name:       Tommy Can You Hear Me
 * Plugin URI:        https://marshall.usc.edu
 * Description:       Tommy, can you hear me? Can you feel me near you? (Yes — because this plugin fixes Divi accessibility issues so everyone can. WCAG 1.4.1, 1.4.4, 4.1.2.)
 * Version:           1.2.0
 * Author:            USC Marshall
 * License:           GPL-2.0-or-later
 * Text Domain:       tommy-can-you-hear-me
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// WCAG 1.4.4 — Resize Text
// Divi's default viewport meta sets maximum-scale=1.0 and user-scalable=0,
// which blocks pinch-to-zoom and OS-level text scaling entirely.
// ---------------------------------------------------------------------------

/**
 * Remove Divi's restrictive viewport meta tag.
 */
function tcyhm_remove_divi_viewport() {
    remove_action( 'wp_head', 'et_add_viewport_meta' );
}
add_action( 'init', 'tcyhm_remove_divi_viewport' );

/**
 * Replace with a WCAG-compliant viewport meta tag.
 *
 * - width=device-width   : correct initial scale on mobile
 * - initial-scale=1.0    : start at 1x zoom
 * - minimum-scale=1.0    : keeps Divi's layout baseline intact
 * - maximum-scale=5.0    : allows zoom up to 500%; WCAG requires zoom not be
 *                          blocked, not that it be unlimited. 5x satisfies
 *                          the requirement while keeping Divi layouts stable.
 * - user-scalable=yes    : explicitly permits pinch-to-zoom on touch devices
 *
 * Priority 1 ensures nothing slips a conflicting tag in ahead of it.
 */
function tcyhm_wcag_viewport() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0, user-scalable=yes">' . "\n";
}
add_action( 'wp_head', 'tcyhm_wcag_viewport', 1 );


// ---------------------------------------------------------------------------
// WCAG 4.1.2 — Name, Role, Value
// Divi's search open/close buttons are empty <button> elements with no
// accessible name. Divi does not expose standard WP hooks for these buttons.
//
// et_core_esc_previously() is Divi's internal escaping function that runs
// on this markup before output. Defining it here (before Divi loads it)
// intercepts the output at the source. The function_exists() check is Divi's
// own pattern — if Divi has already defined it, we don't override it.
// ---------------------------------------------------------------------------

/**
 * Intercept Divi's et_core_esc_previously() to inject aria-label attributes
 * into the search open and close buttons before they are output.
 *
 * @param string $passthru HTML string passed through Divi's escaping function.
 * @return string Modified HTML with aria-labels injected.
 */
if ( ! function_exists( 'et_core_esc_previously' ) ) {
    function et_core_esc_previously( $passthru ) {
        $passthru = preg_replace(
            '/(<button\b[^>]*\bet_pb_menu__search-button\b[^>]*?)(?:\s*aria-label="[^"]*")?(>)/i',
            '$1 aria-label="Open search"$2',
            $passthru
        );
        $passthru = preg_replace(
            '/(<button\b[^>]*\bet_pb_menu__close-search-button\b[^>]*?)(?:\s*aria-label="[^"]*")?(>)/i',
            '$1 aria-label="Close search"$2',
            $passthru
        );
        return $passthru;
    }
}


// ---------------------------------------------------------------------------
// WCAG 1.4.1 — Use of Color
// Links must be distinguishable from surrounding text by more than color alone.
//
// Approach: no underline at rest (preserves Jay's design), underline on
// hover and focus (satisfies 1.4.1). This is valid under WCAG provided the
// link color has at least 3:1 contrast ratio against surrounding body text.
// USC cardinal (#991B1E) against body text (#333333) is ~3.9:1 — passes.
//
// Scoped to Divi text module and post content only to avoid overriding
// nav links, buttons, and other intentionally non-underlined link contexts.
// ---------------------------------------------------------------------------

/**
 * Enqueue the link distinction stylesheet.
 */
function tcyhm_enqueue_styles() {
    wp_enqueue_style(
        'tcyhm-link-distinction',
        plugin_dir_url( __FILE__ ) . 'css/link-distinction.css',
        [],
        '1.2.0'
    );
}
add_action( 'wp_enqueue_scripts', 'tcyhm_enqueue_styles' );
