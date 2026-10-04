<?php
declare(strict_types=1);

/**
 * AI configuration for Smetogram.
 * Set OPENAI_API_KEY (and optionally OPENAI_MODEL / OPENAI_BASE_URL)
 * on the server to enable real vision/chat analysis.
 */
return [
    'api_key' => getenv('OPENAI_API_KEY') ?: '',
    'model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
    'base_url' => rtrim(getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1', '/'),
];
