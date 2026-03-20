# Gravity Forms Google Chat Notifier

Send rich **Google Chat card notifications with clickable buttons** to any Space or Direct Message (DM) when a Gravity Form is submitted. No Zapier, no middleware — direct webhook integration.

---

## Features

- **Per-form feeds**: Create as many feeds as you like per form. Each feed goes to a different Space or DM.
- **Rich Cards v2**: Messages appear as beautiful Google Chat cards with a header, subtitle, and body text.
- **Merge tags**: Use any Gravity Forms merge tag (`{Name:1}`, `{Email:2}`, `{all_fields}`, etc.) in titles and body copy.
- **Buttons**: Add any number of custom URL buttons to your card (e.g., "View in CRM", "Open Policy"). Supports merge tags in button URLs.
- **View Entry button**: Optionally auto-include a "📋 View Entry" button linking directly to the WP Admin entry page.
- **Conditional logic**: Use GF's built-in conditional logic to only send notifications when certain conditions are met.

---

## Requirements

- WordPress 5.8+
- Gravity Forms 2.5+
- PHP 7.4+

---

## Installation

1. Upload the `gravity-forms-google-chat-notifier` folder to `/wp-content/plugins/`.
2. Activate the plugin in **WP Admin → Plugins**.
3. No global settings needed — everything is configured per-form.

---

## Setting Up a Google Chat Webhook

### For a Space
1. Open Google Chat and go to your Space.
2. Click the ⚙️ (gear) icon next to the space name → **Apps & Integrations**.
3. Click **Webhooks** → **Add Webhook**.
4. Give it a name (e.g., "Lead Notifications"), click **Save**.
5. Copy the Webhook URL.

### For a Direct Message (DM)
1. Open Google Chat and go to the DM thread with the person.
2. Click their **name at the top** of the conversation → **Apps & Integrations**.
3. Click **Webhooks** → **Add Webhook**.
4. Give it a name, click **Save**, and copy the Webhook URL.

---

## Configuring a Feed

1. In WP Admin, go to **Forms** → *(select your form)* → **Settings** → **Google Chat Notifier**.
2. Click **Add New**.
3. Fill in:
   - **Feed Name**: Internal label (e.g., "Sales Space", "John DM").
   - **Webhook URL**: Paste the Google Chat webhook URL.
   - **Card Title**: Bold header (supports merge tags).
   - **Card Body**: Main body text (supports merge tags and newlines).
   - **View Entry Button**: Check to include an admin link button.
   - **Custom Buttons**: Add rows of Label → URL pairs for custom buttons.
4. Optionally configure Conditional Logic.
5. Click **Save Settings**.

---

## Example Merge Tags

| Merge Tag | Output |
|---|---|
| `{form_title}` | The form's name |
| `{entry_id}` | The entry ID number |
| `{all_fields}` | All submitted fields formatted as a list |
| `{Name (First):1.3}` | A specific named field value |
| `{Date Created}` | Submission date/time |

---

## Changelog

### 1.3.3
- **Fixed custom buttons** — replaced non-rendering `repeater` field with 5 fixed Label + URL slot pairs that work in all GF versions; empty slots are ignored

### 1.3.2
- **HTML formatting in card body** — `<b>`, `<i>`, `<u>`, `<s>`, `<font color="">`, `<a href="">` tags now pass through to Google Chat instead of being escaped as plain text

### 1.3.1
- **Media Library icon picker** — "Card Icon URL" field now has a "📁 Select Image" button that opens the WordPress Media Library, with a live circular thumbnail preview

### 1.3.0
- **Custom icon per feed** — new "Card Icon URL" field in feed settings; leave blank to use the default icon
- **Fixed buttons** — switched from `generic_map` to `repeater` field; `generic_map` was storing free-text entries under `custom_key`/`custom_value` instead of `key`/`value`, causing custom buttons to be ignored
- **Fixed feed reprocessing** — added `supports_async_feed_processing() = false` to skip GF's batch system on manual reprocess

### 1.2.2
- Rewrote feed processing: now uses a direct `gform_after_submission` hook with `GFAPI::get_feeds()` instead of relying on GFFeedAddOn's internal processing pipeline, which was silently failing to call `process_feed()`
- GFFeedAddOn is now used for the admin settings UI and feed storage only
- Added `error_log()` output for every send attempt (check server PHP error log for diagnostics)

### 1.2.1
- Fixed silent PHP failure: constants `GFGC_VERSION` and `GFGC_PLUGIN_FILE` moved from class property defaults into the constructor so they resolve correctly at runtime — this was preventing the add-on from registering its hooks entirely

### 1.2.0
- Fixed feed reprocessing batch error — `$_full_path` now correctly points to the main plugin file so GF can initialize the add-on during background/batch processing

### 1.1.0
- Added explicit entry notes labeled **"Google Chat Notifier [Feed Name]"** on success and failure — clearly distinguishable from other webhook feeds in the GF entry detail view
- Error notes include the HTTP status code and response body for easier debugging

### 1.0.0
- Initial release
