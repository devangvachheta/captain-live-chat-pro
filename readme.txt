=== Captain Live Chat Pro ===
Contributors: devangvachheta
Tags: live chat, ai chatbot, knowledge base, white label, chat export
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: captain-live-chat
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI auto-replies, knowledge base, real-time live visitors, proactive greetings, CSAT surveys, email transcripts, and white labelling for Live Chat.

== Description ==

**Captain Live Chat Pro** is an add-on for the free **Captain Live Chat** plugin. Once both are active, professional team and automation tools appear directly inside your Captain Live Chat dashboard.

= Features =

* **AI Agent Auto-Replies**: Automatically answer visitor inquiries when human agents are away. Connect your own API key for Groq, Google Gemini, OpenAI, OpenRouter, or Anthropic (Claude).
* **Live Visitors**: Monitor visitors browsing your site in real time, view page paths and referrers, and reach out with proactive live chats.
* **Proactive Greetings**: Trigger automated greeting bubbles based on time delay, scroll depth, or exit intent.
* **Email Chat Transcripts**: Send full, formatted HTML transcripts with timestamps to visitors or agents.
* **Post-Chat Survey & CSAT**: Collect visitor ratings (stars, thumbs, emojis) and track customer satisfaction metrics in Analytics.
* **Knowledge Base**: Upload website links and .txt/.pdf documents for domain-specific AI answers.
* **Custom AI Instructions**: Define a custom system prompt (up to 4000 characters) to tailor tone and boundaries.
* **Cost & Rate Limits**: Set daily and per-conversation reply limits with real-time usage counters.
* **Conversation Export**: Bulk export conversation history to spreadsheet-ready CSV or download individual text transcripts.
* **Desktop Pop-up Alerts**: Real-time pop-up notifications on any admin screen with optional audio alerts.
* **White Label Branding**: Replace the plugin name, author meta, and logo across the WordPress admin.

= Requirements =

* Free **Captain Live Chat** plugin (version 1.1.3 or newer) installed and active.
* WordPress 6.0 or greater.
* PHP 7.4 or greater with OpenSSL extension.
* Your own API key for at least one supported AI provider.

== External Services ==

Captain Live Chat Pro connects only to the AI provider that you configure. No external requests are made until an API key is entered and a visitor submits a message while all agents are offline.

**Data sent to the selected AI provider:**
When answering an offline visitor, the chosen provider receives: (1) recent conversation context and the visitor's question, (2) your custom system prompt, (3) relevant snippets from your Knowledge Base, and (4) the site name/URL. Your API key is sent to authenticate the request. Personal visitor details (email, IP) are never transmitted.

Supported AI Providers:
* **Groq** (`api.groq.com`) - [Terms](https://groq.com/terms-of-use) | [Privacy](https://groq.com/privacy-policy)
* **Google Gemini** (`generativelanguage.googleapis.com`) - [Terms](https://ai.google.dev/gemini-api/terms) | [Privacy](https://policies.google.com/privacy)
* **OpenAI** (`api.openai.com`) - [Terms](https://openai.com/policies/terms-of-use) | [Privacy](https://openai.com/policies/privacy-policy)
* **OpenRouter** (`openrouter.ai`) - [Terms](https://openrouter.ai/terms) | [Privacy](https://openrouter.ai/privacy)
* **Anthropic** (`api.anthropic.com`) - [Terms](https://www.anthropic.com/legal/commercial-terms) | [Privacy](https://www.anthropic.com/legal/privacy)

**Knowledge Base Links**: When an admin enters a URL in the Knowledge Base, that specific URL is fetched once to parse its text content.

**Live Visitors & Email Transcripts**: Visitor presence tracking runs locally in your WordPress database without third-party services. Email transcripts are sent via WordPress `wp_mail()` using your server's standard mail configuration.

== Frequently Asked Questions ==

= Do I need the free plugin? =
Yes. Captain Live Chat Pro requires the free Captain Live Chat plugin (1.1.3+) to function.

= How much does AI usage cost? =
Captain Live Chat Pro charges zero fees for AI. You pay your chosen AI provider directly according to your API plan. Use the built-in daily limit to cap usage.

= Where are API keys stored? =
API keys are encrypted in your WordPress database using OpenSSL. They are never sent to the browser after saving.

== Changelog ==

= 1.0.0 (22/11/2026) =
* Added: AI Agent auto-reply with Groq, Gemini, OpenAI, Claude, and OpenRouter.
* Added: Live Visitors real-time monitoring and proactive chat initiation.
* Added: Proactive greeting triggers (time, scroll, exit intent) and unified launcher integration.
* Added: Responsive HTML email chat transcripts and localized exports.
* Added: Post-chat CSAT survey ratings and satisfaction analytics.
* Added: Knowledge Base indexing for web links, text files, and PDFs.
* Added: White label admin re-branding and chat history CSV export.

== Upgrade Notice ==

= 1.0.0 =
First stable release of Captain Live Chat Pro. Requires free Captain Live Chat 1.1.3+.
