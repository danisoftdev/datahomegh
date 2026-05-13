<?php

declare(strict_types=1);

require_once __DIR__.'/env.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/cors.php';
require_once dirname(__DIR__).'/helpers/Response.php';
require_once dirname(__DIR__).'/helpers/Request.php';
require_once dirname(__DIR__).'/middleware/Auth.php';

if (! defined('PROJECT_ROOT')) {
    // From .../public_html/api/v1/config this is four levels up to project root.
    define('PROJECT_ROOT', dirname(__DIR__, 4));
}

api_load_env(PROJECT_ROOT.'/.env');
