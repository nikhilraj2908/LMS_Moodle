<?php
$string['pluginname'] = 'AI Summary';

$string['apibase'] = 'API base URL';
$string['apibase_desc'] = 'Default: https://openrouter.ai/api/v1/chat/completions. For Ollama, use http://127.0.0.1:11434/v1/chat/completions.';

$string['apikey'] = 'API key';
$string['apikey_desc'] = 'OpenRouter (or other provider) API key. Leave blank when using a local endpoint like Ollama.';

$string['model'] = 'Model';
$string['model_desc'] = 'Example (OpenRouter free): meta-llama/llama-3-8b-instruct:free. For Ollama, use the local tag e.g. llama3.1.';

$string['maxtokens'] = 'Max tokens';

$string['missingconfig'] = 'AI Summary plugin is not configured.';
$string['curlerror'] = 'Network error while contacting the AI service.';
$string['badresponse'] = 'The AI service returned an error.';
$string['emptytext'] = 'No text received from the AI.';
