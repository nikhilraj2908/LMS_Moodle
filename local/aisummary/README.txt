AI Summary (local_aisummary) — fixed
------------------------------------
This build removes legacy callbacks and uses Moodle's new Hook API.
It also includes robust JS to fill TinyMCE/Atto and the hidden summary field.

Install:
1) Upload the zip via Site administration → Plugins → Install plugins.
2) Configure the plugin in Site admin → Plugins → Local plugins → AI Summary.
   - OpenRouter example model: google/gemma-2-9b-it:free
   - Or set Ollama base http://127.0.0.1:11434/v1/chat/completions with model llama3.1 (no API key).
3) Purge caches.
4) Edit a course → click “Generate with AI”.
