=== Captain Live Chat Pro ===
Contributors: devangvachheta
Tags: live chat, ai chatbot, knowledge base, white label, chat export
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: captain-live-chat
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI replies, a knowledge base, chat export and white labelling for Captain Live Chat. Bring your own AI key.

== Description ==

<strong>Captain Live Chat Pro</strong> is an add-on for the free <strong>Captain Live Chat</strong> plugin. Once both are active, extra pages and features appear inside Captain Live Chat's dashboard.

= Features =

* <strong>AI Agent</strong> - automatically answers visitors when no human agent is online. Works with your own API key for Groq, Google Gemini, OpenAI, OpenRouter or Anthropic (Claude).
* <strong>Any model</strong> - pick a listed model for each provider, or choose "Other" and type any model name the provider supports. Models a provider has retired are replaced with a working default automatically.
* <strong>Key check</strong> - test a new key or the one already saved with one click. If a saved key can no longer be read (for example after the site security keys changed), the provider shows "Key unreadable" so you know to enter it again.
* <strong>Knowledge Base</strong> - add website links and .txt or .pdf files. The AI searches them and uses the most relevant parts to answer visitor questions.
* <strong>Custom instructions</strong> - a system prompt (up to 4000 characters, with a live counter) to steer tone and topics.
* <strong>Cost control</strong> - a daily reply limit (200 by default, with a warning when set to unlimited), a per-conversation reply limit, and a "sent today" counter.
* <strong>Chat history export</strong> - download filtered chat history as a CSV file, or a single conversation as a text transcript.
* <strong>Pop-up alerts for new messages</strong> - a small pop-up (with an optional sound) on any WordPress admin page the moment a visitor writes, with a button that opens the chat. Every agent can switch it on or off for themselves on their Profile page.
* <strong>White Label</strong> - replace the plugin name and logo in the WordPress admin with your own.
* API keys are stored encrypted.
* Works in right-to-left (RTL) languages, and follows the light and dark theme of the Captain Live Chat dashboard.

= Requirements =

* The free <strong>Captain Live Chat</strong> plugin (version 1.1.1 or newer), installed and active
* WordPress 6.9 or greater
* PHP 7.4 or greater, with the OpenSSL extension
* Your own API key for at least one supported AI provider

= Good to know =

* Everything you add to the Knowledge Base can be repeated to visitors in AI answers. Do not add private or confidential material.
* PDF reading is basic: text-based PDFs work; scanned PDFs and PDFs with custom fonts (common for Hindi, Gujarati and other non-Latin text) cannot be read. Use a .txt file for those.
* Deleting this plugin removes its settings, including saved AI keys and the Knowledge Base.

== External Services ==

This plugin sends data to the AI provider that <strong>you</strong> choose and configure. Nothing is sent until you add an API key, turn on AI auto-reply and a visitor writes a message while all agents are offline. You can also press the "Test" button on the AI settings page, which sends a short test message ("Say ok in one word") to the selected provider.

<strong>What is sent, and when:</strong> each time the AI answers a visitor, the provider receives (1) the visitor's latest message, (2) your system prompt and a short built-in instruction, (3) the site name, and (4) the parts of your Knowledge Base that best match the question. Your API key is sent to authenticate the request. No other personal data of the visitor (name, email, IP address) is included.

The selected provider is one of:

* <strong>Groq</strong> (api.groq.com) - Terms: https://groq.com/terms-of-use - Privacy: https://groq.com/privacy-policy
* <strong>Google Gemini API</strong> (generativelanguage.googleapis.com) - Terms: https://ai.google.dev/gemini-api/terms - Privacy: https://policies.google.com/privacy
* <strong>OpenAI</strong> (api.openai.com) - Terms: https://openai.com/policies/terms-of-use - Privacy: https://openai.com/policies/privacy-policy
* <strong>OpenRouter</strong> (openrouter.ai) - Terms: https://openrouter.ai/terms - Privacy: https://openrouter.ai/privacy
* <strong>Anthropic</strong> (api.anthropic.com) - Terms: https://www.anthropic.com/legal/commercial-terms - Privacy: https://www.anthropic.com/legal/privacy

<strong>Knowledge Base links:</strong> when an administrator adds or refreshes a website link in the Knowledge Base, this plugin's server requests that exact URL once to read its text. Only the URL entered by the administrator is requested.

== Frequently Asked Questions ==

= Do I need the free plugin? =

Yes. Captain Live Chat Pro does nothing without the free Captain Live Chat plugin and shows a notice if it is missing or too old.

= How much does the AI cost? =

This plugin charges nothing for AI. You pay your chosen provider according to its pricing. Use the daily reply limit to cap your spending.

= Which AI model should I choose? =

Start with the first model listed for your provider - it is the fast, low-cost choice that suits most chat replies. If you want a model that is not in the list, choose "Other (type a model name)" and enter its exact name from your provider's documentation, then press "Test".

= Where are my API keys stored? =

In your WordPress database, encrypted. They are never sent to the browser after saving and are deleted when you delete the plugin.

== Changelog ==

= 1.0.0 (07/10/2026) =

* Added : First Release : First stable release, split out of the Captain Live Chat core plugin into its own add-on.
* Added : AI Agent : Automatic replies when no agent is online, with your own API key for Groq, Google Gemini, OpenAI, Anthropic or OpenRouter.
* Added : Model Choice : Up-to-date model lists for every provider, plus an "Other" option to type any model name the provider supports.
* Added : Knowledge Base : Add website links and .txt / .pdf files that the AI uses to answer visitor questions. Searches text in any language.
* Added : Cost Control : Daily reply limit (200 by default) and a per-conversation reply limit, with a "sent today" counter.
* Added : Pop-up Alerts : Pop-up for new visitor messages on any admin page, with an on/off switch and optional sound for each agent on their Profile page.
* Added : Chat Export : Bulk chat-history CSV export (spreadsheet-safe) and single-conversation transcript export.
* Added : White Label : Rename the plugin and swap its logo throughout the WordPress admin.
* Improved : Retired Models : A saved model that the provider has shut down is replaced with a working default automatically.
* Improved : Key Test : Test the saved API key without typing it again.
* Improved : System Prompt : Limited to 4000 characters, with a live character counter.
* Improved : Active Provider : "Set as active" warns when the chosen provider has no working key yet, and only re-sends your saved settings (not unsaved edits).
* Improved : Knowledge Base : Shows an error when loading or removing a source fails, notes when a preview only shows the first part of a long source, and reminds you not to add private material.
* Improved : RTL and Dark Mode : Right-to-left (RTL) stylesheet support, and colours that follow the dashboard's light and dark theme.
* Security : Daily Limit Warning : A warning is shown when the daily reply limit is set to unlimited, since every visitor message can cost API money.
* Security : Unreadable Keys : A clear "Key unreadable" notice when a saved key can no longer be decrypted (for example after the site security keys changed).
* Fix : Session Expiry : A readable "Your session expired" message instead of a failed request when the login or security token has expired.
* Fix : Missing Free Plugin : A clear notice instead of an error if the free Captain Live Chat plugin is missing or older than 1.1.1.

== Upgrade Notice ==

= 1.0.0 =
First release of Captain Live Chat Pro. Requires the free Captain Live Chat plugin 1.1.1 or newer.
