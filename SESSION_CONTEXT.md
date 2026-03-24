# GF Google Chat Notifier — Session Context & Handoff
> Last updated: 2026-03-24. Covers work done through conversation be2505e2-8571-4dc6-b783-cd7b7ce75fdf.

---

## What Was Built

A **Gravity Forms add-on** that sends rich Google Chat card notifications when a form is submitted.  
It is split into two plugins — a **free** version and a **Pro** version with licensing.

---

## Plugin Repositories

| Plugin | GitHub | Version | Local Path |
|--------|--------|---------|------------|
| Free | https://github.com/jpage000/gravity-forms-google-chat-notifier | **v2.0.1** | `/Users/jpage000/.gemini/antigravity/scratch/gravity-forms-google-chat-notifier/` |
| Pro | https://github.com/jpage000/gravity-forms-google-chat-notifier-pro | **v1.0.0** | `/Users/jpage000/.gemini/antigravity/scratch/gravity-forms-google-chat-notifier-pro/` |

Zipped installers in: `/Users/jpage000/.gemini/antigravity/scratch/GF Google Chat/`

---

## Free vs Pro Features

| Feature | Free | Pro |
|---------|------|-----|
| Webhook URL | ✅ | ✅ |
| Card Title | ✅ | ✅ |
| Card Subtitle | ✅ | ✅ |
| Message Body (plain textarea + GF merge tags) | ✅ | ✅ |
| Conditional Logic | ✅ | ✅ |
| Active feeds per form | **1 max** | **Unlimited** |
| Message Body — WP Editor (rich HTML, {Merge Tags} toolbar button) | 🔒 | ✅ |
| Card Icon URL | 🔒 | ✅ |
| View Entry button | 🔒 | ✅ |
| Custom buttons (up to 5, with merge tag URLs) | 🔒 | ✅ |

Locked features show a 🔒 upgrade notice: `"🔒 [Feature] is a Pro feature. Upgrade to Pro →"` linking to `https://goat-getter.com/gf-google-chat-pro`.

---

## How the Free/Pro Gate Works

**`GF_Google_Chat_AddOn::is_pro()`** — static method in `includes/class-gf-google-chat.php`:
```php
public static function is_pro(): bool {
    return (bool) apply_filters( 'gfgc_is_pro_active', false );
}
```

The **Pro plugin** (`class-gfgc-pro-licensing.php`) hooks into this filter:
```php
add_filter( 'gfgc_is_pro_active', [ $this, 'check_pro_status' ] );
```
…and returns `true` when `gfgc_license_status = 'active'` and `gfgc_license_token` is set in `wp_options`.

**1-feed limit** in `gfgc_process_submission()` (`gravity-forms-google-chat-notifier.php`):
```php
$is_pro = apply_filters( 'gfgc_is_pro_active', false );
// inside the foreach:
if ( ! $is_pro && $processed_count >= 1 ) { break; }
$processed_count++; // incremented after each send
```

---

## Licensing System

### Infrastructure (goat-getter.com)
- **Plugin**: Gravity Pipeline Manager (`/Users/jpage000/.gemini/antigravity/scratch/gravity-pipeline-manager/`)
- **REST endpoint**: `POST https://goat-getter.com/wp-json/gp-license/v1/activate`
  - Body: `{ license_key, site_url }`
  - Returns: `{ success, activation_token, status, max_sites }`
- **DB tables**: `wp_gp_licenses`, `wp_gp_activations`
- **Admin UI**: WP Admin → Pipeline Manager → Generate License Manually
- **WooCommerce product**: "GF Google Chat Notifier Pro" (ID 1733, $49) — auto-generates keys on purchase via `class-gpm-woocommerce.php`

### Test License Key
```
HUPD-BMUQ-QB30-RVCZ
```
Email: jrpage87@gmail.com | Tier: GFGC-Pro | Max Sites: 1

### Dev Bypass (for local testing without a server call)
Any key starting with `GFGC-DEV-` activates Pro instantly without an API call:
```
GFGC-DEV-anything-here
```

### Where License is Stored in WP
- `gfgc_license_status` → `'active'` or empty
- `gfgc_license_token` → the activation token from the server
- `gfgc_license_error` → transient (1 hour) with error message if validation fails

---

## Key File Structure

### Free Plugin
```
gravity-forms-google-chat-notifier/
├── gravity-forms-google-chat-notifier.php   ← Main file, gfgc_process_submission(), 1-feed limit
├── includes/
│   ├── class-gf-google-chat.php             ← GFFeedAddOn subclass, is_pro(), settings fields, pro_notice()
│   └── class-gf-google-chat-message.php     ← Builds the Google Chat card payload
├── assets/
│   └── js/
│       ├── gfgc-settings.js                 ← TinyMCE {Merge Tags} button, media library picker
│       └── admin.js                         ← Resend-to-chat sidebar meta box JS
└── SESSION_CONTEXT.md                       ← This file
```

### Pro Plugin
```
gravity-forms-google-chat-notifier-pro/
├── gravity-forms-google-chat-notifier-pro.php   ← Main file, checks free plugin exists, loads licensing
└── includes/
    └── class-gfgc-pro-licensing.php             ← License key field, goat-getter.com validation, filter hook
```

---

## Technical Notes

### TinyMCE Merge Tags Button
The `{Merge Tags}` dropdown in the WP Editor toolbar is registered via:
- PHP: `setup` callback in `settings_textarea()` → `window.gfgcSetupEditor`
- JS: `gfgcSetupEditor(editor, mergeTagData)` defined at **script load time** (outside `document.ready`) so TinyMCE can call it synchronously during init

### Google Chat Icon
`get_menu_icon()` returns a **raw SVG string** (not base64) — this is what GF's form-settings nav requires.  
Base64 is only for WP's top-level admin sidebar menu.

### Body Encoding
The WP Editor body is stored HTML-encoded (e.g., `&lt;b&gt;`) so GF's sanitizer doesn't strip tags.  
It's decoded for display in the editor and re-encoded on save via a hidden textarea.  
`GF_Google_Chat_Message` decodes it back with `html_entity_decode()` before sending.

---

## What's Left To Do

- [ ] **End-to-end test** on a live WordPress site — install both plugins, enter `HUPD-BMUQ-QB30-RVCZ`, verify Pro features unlock
- [ ] **Product page** on goat-getter.com for "GF Google Chat Notifier Pro" (WooCommerce product ID 1733 exists, set at $49 — needs a sales/landing page)
- [ ] **README update** for the Pro repo (currently only has the free plugin README)
- [ ] **Deactivation endpoint** — when a Pro license is deactivated, call `POST /wp-json/gp-license/v1/deactivate` with `{ activation_token, site_url }` to free up the site slot
- [ ] **Periodic license re-check** — scheduled event to call `/check` endpoint and suspend Pro if license is revoked
- [ ] **Plugin update notifications** — both plugins need update checking (free via WordPress.org or custom, Pro via goat-getter.com)
- [ ] Consider whether **conditional logic should be Pro-only** (currently free)

---

## goat-getter.com Credentials
- Admin: https://goat-getter.com/wp-admin
- Username: jrpage87@gmail.com
- (Password shared in session — not stored here for security)

---

## How to Resume This Work

Start a new chat and say something like:
> "I want to continue working on the GF Google Chat Notifier free/Pro plugin split. Read `SESSION_CONTEXT.md` in the gravity-forms-google-chat-notifier repo for full context."

The AI can then read this file and pick up exactly where we left off.
