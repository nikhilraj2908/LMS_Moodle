<?php
$string['pluginname'] = 'AI Summary';

$string['apibase'] = 'API base URL';
$string['apibase_desc'] = 'Default: https://openrouter.ai/api/v1/chat/completions (OpenAI-compatible chat completions). You may change to another compatible endpoint.';

$string['apikey'] = 'API key';
$string['apikey_desc'] = 'Paste your provider key (OpenRouter or other).';

$string['model'] = 'Model';
$string['model_desc'] = 'Example (OpenRouter free): meta-llama/llama-3-8b-instruct:free';

$string['maxtokens'] = 'Max tokens';
$string['maxtokens_desc'] = 'Upper bound for response tokens. 600 is usually enough for 15–30 bullet lines.';

$string['missingconfig'] = 'AI Summary plugin is not configured.';
$string['badresponse'] = 'The AI service returned an error.';
$string['emptytext'] = 'No text received from the AI.';
$string['curlerror'] = 'Network error when contacting the AI service.';
