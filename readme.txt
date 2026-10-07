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

**Captain Live Chat Pro** is an add-on for the free **Captain Live Chat** plugin. Once both are active, extra pages and features appear inside Captain Live Chat's dashboard.

= Features =

* **AI Agent** - automatically answers visitors when no human agent is online. Works with your own API key for Groq, Google Gemini, OpenAI, OpenRouter or Anthropic (Claude).
* **Knowledge Base** - add website links and .txt or .pdf files. The AI searches them and uses the most relevant parts to answer visitor questions.
* **Custom instructions** - a system prompt to steer tone and topics.
* **Cost control** - a daily reply limit and a per-conversation reply limit.
* **Chat history export** - download filtered chat history as a CSV file, or a single conversation as a text transcript.
* **Pop-up alerts for new messages** - a small pop-up (with an optional sound) on any WordPress admin page the moment a visitor writes, with a button that opens the chat. Every agent can switch it on or off for themselves on their Profile page.
* **White Label** - replace the plugin name and logo in the WordPress admin with your own.
* API keys are stored encrypted.

= Requirements =

* The free **Captain Live Chat** plugin (version 1.1.1 or newer), installed and active
* WordPress 6.9 or greater
* PHP 7.4 or greater, with the OpenSSL extension
* Your own API key for at least one supported AI provider

= Good to know =

* Everything you add to the Knowledge Base can be repeated to visitors in AI answers. Do not add private or confidential material.
* PDF reading is basic: text-based PDFs work; scanned PDFs and PDFs with custom fonts (common for Hindi, Gujarati and other non-Latin text) cannot be read. Use a .txt file for those.
* Deleting this plugin removes its settings, including saved AI keys and the Knowledge Base.

== External Services ==

This plugin sends data to the AI provider that **you** choose and configure. Nothing is sent until you add an API key, turn on AI auto-reply and a visitor writes a message while all agents are offline. You can also press the "Test" button on the AI settings page, which sends a short test message ("Say ok in one word") to the selected provider.

**What is sent, and when:** each time the AI answers a visitor, the provider receives (1) the visitor's latest message, (2) your system prompt and a short built-in instruction, (3) the site name, and (4) the parts of your Knowledge Base that best match the question. Your API key is sent to authenticate the request. No other personal data of the visitor (name, email, IP address) is included.

The selected provider is one of:

* **Groq** (api.groq.com) - Terms: https://groq.com/terms-of-use - Privacy: https://groq.com/privacy-policy
* **Google Gemini API** (generativelanguage.googleapis.com) - Terms: https://ai.google.dev/gemini-api/terms - Privacy: https://policies.google.com/privacy
* **OpenAI** (api.openai.com) - Terms: https://openai.com/policies/terms-of-use - Privacy: https://openai.com/policies/privacy-policy
* **OpenRouter** (openrouter.ai) - Terms: https://openrouter.ai/terms - Privacy: https://openrouter.ai/privacy
* **Anthropic** (api.anthropic.com) - Terms: https://www.anthropic.com/legal/commercial-terms - Privacy: https://www.anthropic.com/legal/privacy

**Knowledge Base links:** when an administrator adds or refreshes a website link in the Knowledge Base, this plugin's server requests that exact URL once to read its text. Only the URL entered by the administrator is requested.

== Frequently Asked Questions ==

= Do I need the free plugin? =

Yes. Captain Live Chat Pro does nothing without the free Captain Live Chat plugin and shows a notice if it is missing or too old.

= How much does the AI cost? =

This plugin charges nothing for AI. You pay your chosen provider according to its pricing. Use the daily reply limit to cap your spending.

= Where are my API keys stored? =

In your WordPress database, encrypted. They are never sent to the browser after saving and are deleted when you delete the plugin.

== Changelog ==

= 1.0.0 =
* First stable release. Split out of Captain Live Chat's core plugin into its own add-on.
* AI Agent - automatic replies when no agent is online, bring your own API key (Groq, Gemini, OpenAI, Anthropic, OpenRouter).
* Knowledge Base - add website links and .txt / .pdf files that the AI uses to answer visitor questions. Searches text in any language.
* Daily reply limit (default 200) and per-conversation reply limit.
* Pop-up alerts for new visitor messages on any admin page, with an on/off switch and optional sound for each agent.
* Bulk chat-history CSV export (spreadsheet-safe) and single-conversation transcript export.
* White Label - rename the plugin and swap its logo throughout the WordPress admin.
* Clear notice instead of an error if the free Captain Live Chat plugin is missing or older than 1.1.1.
