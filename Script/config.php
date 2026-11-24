<?php
return array(
    /**
     * Provide your MYSQL database credentials
     */
    'db_host' => getenv('DB_HOST') ?: 'YOURDBHOST',
    'db_port' => getenv('DB_PORT') ?: '',
    'db_username' => getenv('DB_USERNAME') ?: 'YOUTDBUSER',
    'db_name' => getenv('DB_NAME') ?: 'YOURDBNAME',
    'db_password' => getenv('DB_PASSWORD') ?: 'YOURDBPASS',
    'dbcharacter_encode' => true,

    /**
     * Option to know if debug is enabled or not
     */
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),

    /**
     * Option to enable https
     */
    'https' => filter_var(getenv('APP_HTTPS') ?: 'false', FILTER_VALIDATE_BOOLEAN),

    /**
     * cookie path
     */
    'cookie_path' => '/',

    'permalink' => false,


    'default_language' => 'en',

    'api-key' => 'normalKey',

    'rtl-langs' => array(
        'ar',
        'fa',
    ),

    'demo' => false,
    'site-title' => 'MusicEngine',

    'max-image-size' => '10000000',
    'image-file-types' => 'jpg,png,gif,jpeg',

);
