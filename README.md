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

### 1.1.0
- Added explicit entry notes labeled **"Google Chat Notifier [Feed Name]"** on success and failure — clearly distinguishable from other webhook feeds in the GF entry detail view
- Error notes include the HTTP status code and response body for easier debugging

### 1.0.0
- Initial release
