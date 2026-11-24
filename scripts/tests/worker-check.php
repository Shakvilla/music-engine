<?php
// Run inside the local app container with the chart and archive services running.
if (PHP_SAPI !== 'cli') exit;
define('APP_BASE_PATH',getcwd().'/');
require APP_BASE_PATH.'app/request.php';
require APP_BASE_PATH.'app/vendor/utils.php';
require APP_BASE_PATH.'app/vendor/classes/database.php';
$db = Database::getInstance();
function awaitArchive($db,$id) {
    $end = time()+120;
    do {
        $state = $db->query('SELECT state FROM archive_jobs WHERE id=?',$id)->fetchColumn();
        if ($state === 'ready' || $state === 'failed') break;
        usleep(500000);
    } while (time()<$end);
    return $state;
}
$refresh = (int)$db->query('SELECT refreshed_at FROM chart_refresh WHERE id=1')->fetchColumn();
if ($refresh < time()-600) throw new RuntimeException('Chart worker has no recent refresh');
$mismatches = $db->query("SELECT COUNT(*) FROM tracks t LEFT JOIN chart_counts c ON c.track=t.id AND c.period='all'
    WHERE COALESCE(c.plays,0)<>(SELECT COUNT(*) FROM views v WHERE v.track=t.id AND v.time<=?)",$refresh)->fetchColumn();
if ($mismatches) throw new RuntimeException('Local chart totals differ');
echo "PASS published all-time counts agree with local listening events\n";
$id = bin2hex(random_bytes(32));
$ref = 'private-audio/'.bin2hex(random_bytes(24)).'.mp3';
$content = str_repeat('musicengine verification ',5000);
$file = path($ref);
$zipPath = path('uploads/.archives/'.$id.'.zip');
try {
    if (file_put_contents($file,$content) !== strlen($content)) throw new RuntimeException('Cannot create isolated test media');
    $manifest = json_encode([['id'=>1,'title'=>'fixture','source'=>$ref]]);
    $db->query('INSERT INTO archive_jobs (id,manifest,updated_at) VALUES(?,?,?)',$id,$manifest,time());
    if (awaitArchive($db,$id)!=='ready') throw new RuntimeException('New archive failed');
    $zip = new ZipArchive();
    if ($zip->open($zipPath)!==true || $zip->getFromName('1-fixture.mp3')!==$content) throw new RuntimeException('Archive content mismatch');
    $zip->close();
    echo "PASS worker published a ZIP containing the exact private media bytes\n";
    $partial = dirname($zipPath).'/'.$id.'.part';
    $staged = dirname($zipPath).'/'.$id.'-audio-fixture';
    unlink($zipPath);
    file_put_contents($partial,'incomplete archive');
    file_put_contents($staged,'staged object');
    $db->query("UPDATE archive_jobs SET state='building',attempts=1 WHERE id=?",$id);
    clearstatcache();
    if (awaitArchive($db,$id)!=='ready' || file_exists($staged) || file_exists($partial) ||
        $zip->open($zipPath)!==true || $zip->getFromName('1-fixture.mp3')!==$content) {
        throw new RuntimeException('Interrupted archive recovery failed');
    }
    $zip->close();
    echo "PASS interrupted archive rebuilt and staging files removed\n";
    $db->query("UPDATE archive_jobs SET state='building',attempts=3 WHERE id=?",$id);
    if (awaitArchive($db,$id)!=='ready') throw new RuntimeException('Completed ZIP lost on final attempt');
    echo "PASS published ZIP recovered after interrupted final database update\n";
    unlink($zipPath);
    file_put_contents($partial,'incomplete final attempt');
    $db->query("UPDATE archive_jobs SET state='building',attempts=3 WHERE id=?",$id);
    if (awaitArchive($db,$id)!=='failed' || file_exists($partial)) throw new RuntimeException('Partial final archive retained');
    echo "PASS exhausted job fails without publishing a partial ZIP\n";
} finally {
    $db->query('DELETE FROM archive_jobs WHERE id=?',$id);
    foreach (array_filter([$file,$zipPath,$partial ?? null,$staged ?? null]) as $path) if (is_file($path)) unlink($path);
}
