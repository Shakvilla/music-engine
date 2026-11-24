<?php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$secureSessionCookie = filter_var(getenv('APP_HTTPS') ?: 'false', FILTER_VALIDATE_BOOLEAN);
session_set_cookie_params(array(
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureSessionCookie,
    'httponly' => true,
    'samesite' => 'Lax'
));
session_start();
error_reporting(-1);
//header('Access-Control-Allow-Origin: *');
// Web workers inherit the bounded memory limit in php.ini.
date_default_timezone_set('UTC');
//exit(md5('250.0012rhz4c'));
//exit(date('y m d h:s:i a', '1572566400'));
/**$lastWeek = strtotime('first day of January 2018');
exit(date('y m d h:s:i a', $lastWeek));
/**
 * Important Define Constants
 */
define("APP_BASE_PATH", __DIR__.'/');


define("VERSION", '2.4.2');

//exit(file_get_contents('http://streaming.radionomy.com/JamendoLounge?lang=en-US%2cen%3bq%3d0.9'));
include_once "app/request.php";
include_once "app/vendor/utils.php";

Request::instance()->start();
