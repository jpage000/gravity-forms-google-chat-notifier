# Gravity Forms Google Chat Notifier

Send rich **Google Chat card notifications** to any Space or Direct Message when a Gravity Form is submitted. No Zapier, no middleware — direct webhook integration.

---

## Requirements

- WordPress 5.8+
- Gravity Forms 2.5+
- PHP 7.4+

---

## Installation

1. Download the zip file.
2. In WP Admin go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**, then **Activate**.
4. No global settings needed — everything is configured per-form.

---

## Step 1 — Create a Google Chat Webhook

### For a Space
1. Open Google Chat and go to your Space.
2. Click the **Space name** at the top → **Apps & Integrations**.
3. Click **Webhooks** → **Add Webhook**.
4. Give it a name (e.g., "Lead Notifications") and click **Save**.
5. Copy the **Webhook URL** — you'll paste it into the feed settings.

### For a Direct Message (DM)
1. Open Google Chat and go to the DM.
2. Click the person's **name at the top** → **Apps & Integrations**.
3. Click **Webhooks** → **Add Webhook**, give it a name, and copy the URL.

> ⚠️ Webhook URLs look like: `https://chat.googleapis.com/v1/spaces/xxxx/messages?key=...`

---

## Step 2 — Configure a Feed

1. In WP Admin, go to **Forms** → select your form → **Settings** → **Google Chat** (look for the chat bubble icon).
2. Click **Add New**.
3. Fill in the feed settings:

| Field | Description |
|---|---|
| **Feed Name** | Internal label (e.g., "Sales Space", "John DM") |
| **Webhook URL** | Paste the Google Chat webhook URL here |
| **Card Title** | Bold header on the card — supports merge tags |
| **Card Subtitle** | Optional subheading below the title |
| **Card Body** | Main message content — use the **{Merge Tags}** button in the toolbar to insert field values |
| **Card Icon** | Optional URL or image to show in the card header |
| **View Entry Button** | Check to include an admin link button |
| **Custom Buttons** | Add up to 5 clickable link buttons (Label + URL) |

4. Optionally set **Conditional Logic** to only fire this feed when certain form answers match.
5. Click **Save Settings**.

---

## Step 3 — Test It

Submit a test entry on your form. The card should appear in your Google Chat Space or DM within a second or two.

If nothing arrives:
- Double-check the Webhook URL in the feed settings — it must start with `https://`.
- Open **Forms → Entries → [your entry] → Notes** to see any error messages from the notifier.
- Use the **💬 Google Chat Notifier** side panel on the entry detail page and click **Resend Now** to retry.

---

## Merge Tags

Use any Gravity Forms merge tag in the title, subtitle, body, and button URLs:

| Merge Tag | Output |
|---|---|
| `{Business Name:2}` | Value of field ID 2 |
| `{Name (First):1.3}` | First name sub-field |
| `{all_fields}` | All submitted fields as a formatted list |
| `{form_title}` | The form's name |
| `{entry_id}` | The entry ID number |
| `{date_mdy}` | Submission date |

In the **Card Body** editor, click the **`{Merge Tags}`** button in the toolbar to browse and insert tags.

---

## Resending Notifications

Every entry detail page has a **💬 Google Chat Notifier** panel in the sidebar with a **Resend Now** button. This re-fires all active feeds for that entry — useful for testing or recovering from a temporary webhook outage.

---

## Duplicating Feeds

On the feed list page, each feed row has a **Duplicate** link. Use this to quickly copy a feed and change just the webhook URL or conditional logic for routing to a different Space.

---

## Changelog

### 1.6.3
- **`{Merge Tags}` toolbar button** in WP Editor — browse and insert merge tags at cursor
- Duplicate feed link only shows on forms with existing feeds
- Google Chat icon in GF form settings navigation

### 1.6.0
- **TinyMCE merge tag button** — custom `{Merge Tags}` dropdown in editor toolbar
- **Duplicate feed** — clone any feed from the feed list
- Fixed TinyMCE `setup` callback timing for reliable button registration

### 1.5.9
- Google Chat icon in GF form settings tab

### 1.5.8
- Duplicate feed action + admin notice
- Merge tag helper: positioned textarea approach

### 1.5.5
- **Fixed conditional logic** — was reading wrong meta keys; now uses `GFFeedAddOn::is_condition_met()`

### 1.5.3
- **WP Editor for Card Body** — full TinyMCE with toolbar; HTML-encoded round-trip to bypass GF validator
- **Line break fix** — paragraphs and line breaks now render correctly in Google Chat

### 1.5.0
- WordPress Editor (TinyMCE) for card body
- `html_to_chat()` converter for TinyMCE output

### 1.4.6
- **Card Subtitle** — editable field with merge tag support; leave blank to hide

### 1.4.5
- **Markdown support** in body (`**bold**`, `_italic_`, etc.)
- Fixed double-spacing in messages

### 1.4.3
- Real-time URL validation with inline error messages
- Processing-time warning notes for invalid URLs

### 1.4.0
- **"Resend to Google Chat"** entry action — re-fires all active feeds for an entry

### 1.3.3
- Fixed custom buttons — switched from broken `repeater` to 5 fixed Label+URL slot pairs

### 1.3.1
- Media Library picker for Card Icon URL field

### 1.3.0
- Custom icon per feed
- Fixed buttons and feed reprocessing

### 1.2.2
- Rewrote feed processing using direct `gform_after_submission` hook (bypasses GFFeedAddOn pipeline)

### 1.0.0
- Initial release
