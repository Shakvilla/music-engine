<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('APP_BASE_PATH', dirname(__DIR__, 2).'/');
require APP_BASE_PATH.'app/vendor/utils.php';
require APP_BASE_PATH.'app/request.php';
require APP_BASE_PATH.'app/vendor/classes/database.php';
date_default_timezone_set('UTC');
$db = Database::getInstance();
// A single transaction publishes all windows, including windows with no events.
if (!(int)$db->query("SELECT GET_LOCK('musicengine.chart.refresh',0)")->fetchColumn()) exit;
$db->query('START TRANSACTION');
try {
    $now = time();
    $db->query('DELETE FROM chart_counts');
    foreach (['all','this-week','this-month','last-week','last-month','this-year'] as $period) {
        $start = $period === 'all' ? 0 : get_time_relative_format($period);
        $db->query('INSERT INTO chart_counts (track,period,plays)
            SELECT track,?,COUNT(*) FROM views WHERE time BETWEEN ? AND ? GROUP BY track', $period,$start,$now);
    }
    $db->query('REPLACE INTO chart_refresh (id,refreshed_at) VALUES (1,?)',$now);
    $db->query('COMMIT');
} catch (Throwable $error) {
    $db->query('ROLLBACK');
    throw $error;
} finally {
    $db->query("SELECT RELEASE_LOCK('musicengine.chart.refresh')");
}
