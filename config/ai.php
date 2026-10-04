<?php
declare(strict_types=1);
$configFile=__DIR__.'/config.php';
$appConfig=is_file($configFile)?require $configFile:[];
$stored=$appConfig['ai']??[];
return [
    'api_key' => (string)($stored['api_key']??getenv('OPENAI_API_KEY')??''),
    'model' => (string)($stored['model']??getenv('OPENAI_MODEL')??'gpt-4o-mini'),
    'base_url' => rtrim((string)($stored['base_url']??getenv('OPENAI_BASE_URL')??'https://api.openai.com/v1'),'/'),
];
