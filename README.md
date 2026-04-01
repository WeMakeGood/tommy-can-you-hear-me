# Tommy Can You Hear Me

WordPress plugin that applies global WCAG 2 AA accessibility fixes to USC Marshall Divi 4 sites. Deployed to ceo.usc.edu and execed.marshall.usc.edu.

**Version:** 1.1.0
**Requires:** WordPress 6.0+, Divi 4.x child theme

---

## What It Does

### Viewport Meta (WCAG 1.4.4 — Resize Text)

Divi's default viewport meta tag sets `maximum-scale=1.0` and `user-scalable=0`, which blocks browser and OS-level text scaling and pinch-to-zoom. The plugin removes Divi's tag and replaces it with:

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0, user-scalable=yes">
```

This allows zoom up to 500% while preserving Divi's responsive layout behavior.

### Search Button Labels (WCAG 4.1.2 — Name, Role, Value)

Divi renders its search open and close buttons as empty `<button>` elements with no accessible name. Screen readers encounter them as unlabeled controls. The plugin intercepts Divi's internal `et_core_esc_previously()` function — which processes this markup before output — and injects `aria-label` attributes on both buttons.

---

## Installation

**Via git clone (recommended for managed deployments):**

```bash
cd /path/to/wp-content/plugins
git clone https://github.com/WeMakeGood/tommy-can-you-hear-me.git
```

Activate via WP Admin → Plugins, or via WP-CLI:

```bash
wp plugin activate tommy-can-you-hear-me
```

**Updating:**

```bash
cd /path/to/wp-content/plugins/tommy-can-you-hear-me
git pull
```

---

## Compatibility Notes

- The viewport fix specifically targets `et_add_viewport_meta`, the Divi function responsible for the default tag. It has no effect on non-Divi themes.
- The search button fix uses `et_core_esc_previously()`. If Divi defines this function before the plugin loads, the fix will not apply — check plugin load order if the fix is not taking effect.
- Both fixes are safe to run alongside other accessibility plugins.

---

## Related

- [WeMakeGood/usc-wcag-remediation](https://github.com/WeMakeGood/usc-wcag-remediation) — full remediation project, crawler, and fix scripts
