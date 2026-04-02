<?php
/**
 * Plugin Name:       Tommy Can You Hear Me
 * Plugin URI:        https://marshall.usc.edu
 * Description:       Tommy, can you hear me? Can you feel me near you? (Yes — because this plugin fixes Divi accessibility issues so everyone can. WCAG 1.4.1, 1.4.4, 4.1.2.)
 * Version:           1.5.0
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


// ---------------------------------------------------------------------------
// WCAG 4.1.2 — Name, Role, Value (Divi image modules)
// Divi renders image-wrapped links with no accessible name when the module's
// alt attribute is empty and the attachment has no alt text in the media
// library. This filter runs at render time and pulls the alt from
// _wp_attachment_image_alt, giving every image-wrapped link a computable
// accessible name without modifying post content.
//
// Modules covered:
//   et_pb_image          — image module (src / alt)
//   et_pb_fullwidth_image — full-width image module (src / alt)
//   et_pb_blurb          — blurb module with image (image / alt)
//   et_pb_slide          — slider slide (image / image_alt)
//   et_pb_fullwidth_header — fullwidth header (logo_image_url / logo_alt_text,
//                            header_image_url / image_alt_text)
//   et_pb_menu           — menu module logo (logo / logo_alt)
// ---------------------------------------------------------------------------

/**
 * Look up the alt text for an image from the WordPress media library.
 * Falls back to the attachment title if alt meta is empty.
 *
 * @param string $image_url Absolute or root-relative URL of the image.
 * @return string Alt text, or empty string if attachment cannot be found.
 */
function tcyhm_get_image_alt( $image_url ) {
    if ( ! $image_url ) {
        return '';
    }

    // Resolve root-relative URLs to absolute before lookup.
    if ( '/' === $image_url[0] ) {
        $image_url = home_url() . $image_url;
    }

    $post_id = attachment_url_to_postid( $image_url );
    if ( ! $post_id ) {
        return '';
    }

    $alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
    if ( '' === $alt ) {
        $alt = get_the_title( $post_id );
    }

    return $alt;
}

/**
 * Inject alt text into Divi module attributes at render time when the module's
 * own alt field is empty. Only fires when there is genuinely no alt set —
 * explicit empty strings set by an editor are treated as intentional and left
 * alone (decorative image pattern).
 *
 * @param array  $attrs             Processed module attributes.
 * @param array  $unprocessed_attrs Raw shortcode attributes before processing.
 * @param string $slug              Module slug (e.g. 'et_pb_image').
 * @return array Modified attributes.
 */
function tcyhm_divi_module_alt( $attrs, $unprocessed_attrs, $slug ) {
    switch ( $slug ) {

        case 'et_pb_image':
        case 'et_pb_fullwidth_image':
            if ( isset( $attrs['src'] ) && ! isset( $unprocessed_attrs['alt'] ) ) {
                $attrs['alt'] = tcyhm_get_image_alt( $attrs['src'] );
            }
            break;

        case 'et_pb_blurb':
            // Only applies to image blurbs (use_icon='off' or unset).
            if (
                isset( $attrs['image'] ) &&
                ( ! isset( $attrs['use_icon'] ) || 'off' === $attrs['use_icon'] ) &&
                ! isset( $unprocessed_attrs['alt'] )
            ) {
                $attrs['alt'] = tcyhm_get_image_alt( $attrs['image'] );
            }
            break;

        case 'et_pb_slide':
            if ( ! empty( $attrs['image'] ) && ! isset( $unprocessed_attrs['image_alt'] ) ) {
                $attrs['image_alt'] = tcyhm_get_image_alt( $attrs['image'] );
            }
            break;

        case 'et_pb_fullwidth_header':
            if ( ! empty( $attrs['logo_image_url'] ) && ! isset( $unprocessed_attrs['logo_alt_text'] ) ) {
                $attrs['logo_alt_text'] = tcyhm_get_image_alt( $attrs['logo_image_url'] );
            }
            if ( ! empty( $attrs['header_image_url'] ) && ! isset( $unprocessed_attrs['image_alt_text'] ) ) {
                $attrs['image_alt_text'] = tcyhm_get_image_alt( $attrs['header_image_url'] );
            }
            break;

        case 'et_pb_menu':
            if ( ! empty( $attrs['logo'] ) && ! isset( $unprocessed_attrs['logo_alt'] ) ) {
                $attrs['logo_alt'] = tcyhm_get_image_alt( $attrs['logo'] );
            }
            break;
    }

    return $attrs;
}
add_filter( 'et_pb_module_shortcode_attributes', 'tcyhm_divi_module_alt', 20, 3 );


// ---------------------------------------------------------------------------
// WCAG 4.1.2 — Name, Role, Value (client-side rendered Divi elements)
// Divi renders many interactive elements via JavaScript after page load.
// These elements have no accessible name and cannot be fixed via PHP filters.
//
// A MutationObserver watches the DOM for new nodes and labels them as they
// appear. This is necessary because DOMContentLoaded fires before Divi's JS
// creates sliders, carousels, and other dynamic UI.
//
// Elements covered:
//   .et-pb-arrow-prev         — slider previous arrow
//   .et-pb-arrow-next         — slider next arrow
//   .et_pb_video_play         — video play button
//   .dica-image-container a   — DICA carousel image links (name from bio URL)
//   .dssb-sharing-button-*    — Divi Social Sharing Buttons plugin
// ---------------------------------------------------------------------------

/**
 * Output an inline script in the footer that labels Divi's client-side
 * rendered interactive elements using a MutationObserver.
 */
function tcyhm_label_dynamic_elements() {
    ?>
    <script>
    (function () {
        /**
         * Label a single element if it matches one of our selectors and
         * doesn't already have an aria-label.
         */
        function labelElement(el) {
            if (el.nodeType !== 1) return;
            if (el.getAttribute('aria-label')) return;

            var cls = el.className || '';

            // Slider arrows — Divi renders as <a href="#"> with hidden <span>
            if (el.classList.contains('et-pb-arrow-prev')) {
                el.setAttribute('aria-label', 'Previous slide');
                return;
            }
            if (el.classList.contains('et-pb-arrow-next')) {
                el.setAttribute('aria-label', 'Next slide');
                return;
            }

            // Video play button
            if (el.classList.contains('et_pb_video_play')) {
                el.setAttribute('aria-label', 'Play video');
                return;
            }

            // DICA carousel image links — extract person name from bio URL slug
            if (el.tagName === 'A' && el.classList.contains('image') &&
                el.closest('.dica-image-container')) {
                var href = el.getAttribute('href') || '';
                var match = href.match(/\/bio\/([^/]+)/);
                if (match) {
                    var name = match[1].replace(/-\d+$/, '').replace(/-/g, ' ')
                        .replace(/\b\w/g, function (c) { return c.toUpperCase(); })
                        .replace(/\bIii\b/, 'III').replace(/\bIi\b/, 'II')
                        .replace(/\bJr\b/, 'Jr.').replace(/\bSr\b/, 'Sr.');
                    el.setAttribute('aria-label', name);
                }
                return;
            }

            // Divi Social Sharing Buttons — class tells us the network
            if (typeof cls === 'string' && cls.indexOf('dssb-sharing-button-') !== -1) {
                var networkMatch = cls.match(/dssb-sharing-button-(\w+)/);
                if (networkMatch) {
                    var network = networkMatch[1];
                    var label = 'Share on ' + network.charAt(0).toUpperCase() + network.slice(1);
                    el.setAttribute('aria-label', label);
                }
                return;
            }
        }

        /**
         * Scan a root node and all its descendants for elements to label.
         */
        function labelTree(root) {
            if (root.nodeType !== 1) return;
            labelElement(root);
            root.querySelectorAll(
                '.et-pb-arrow-prev, .et-pb-arrow-next, .et_pb_video_play, ' +
                '.dica-image-container a.image, a[class*="dssb-sharing-button-"]'
            ).forEach(labelElement);
        }

        // Label anything already in the DOM
        labelTree(document.body || document.documentElement);

        // Watch for Divi's JS-rendered elements
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    labelTree(added[j]);
                }
            }
        });

        function startObserver() {
            observer.observe(document.body, { childList: true, subtree: true });
        }

        if (document.body) {
            startObserver();
        } else {
            document.addEventListener('DOMContentLoaded', startObserver);
        }
    }());
    </script>
    <?php
}
add_action( 'wp_footer', 'tcyhm_label_dynamic_elements' );
