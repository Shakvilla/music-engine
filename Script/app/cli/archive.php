<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('APP_BASE_PATH',dirname(__DIR__,2).'/');
require APP_BASE_PATH.'app/request.php';
require APP_BASE_PATH.'app/vendor/utils.php';
require path('app/vendor/classes/database.php');
require path('app/vendor/classes/model.php');
require path('app/model/track.php');
require path('app/model/media.php');

$request = Request::instance();
foreach (include path('config.php') as $key => $value) {
    $request->setConfig($key, $value);
}
$db = Database::getInstance();
if (!(int)$db->query("SELECT GET_LOCK('musicengine.archives',0)")->fetchColumn()) return;
$db->query("DELETE FROM media_tickets WHERE expires<?",time());
$job = $db->query("SELECT * FROM archive_jobs WHERE state IN ('pending','building') ORDER BY updated_at LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$job) return;
foreach ($db->query('SELECT settings_key,settings_value FROM settings') as $setting) {
    $request->setConfig($setting['settings_key'], $setting['settings_value']);
}
$tracks = new TrackModel(null);
$dir = MediaModel::archiveDirectory();
if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Archive directory unavailable');
$tmp = $dir.'/'.$job['id'].'.part';
$published = $dir.'/'.$job['id'].'.zip';
// Only a complete archive can be renamed into the published location.
foreach (glob($dir.'/'.$job['id'].'-audio-*') ?: [] as $staged) {
    unlink($staged);
}
if (is_file($published) || $job['attempts'] >= 3) {
    if (is_file($tmp)) unlink($tmp);
    $state = is_file($published) ? 'ready' : 'failed';
    $db->query('UPDATE archive_jobs SET state=?,updated_at=? WHERE id=?',$state,time(),$job['id']);
    return;
}
$db->query("UPDATE archive_jobs SET state='building',attempts=attempts+1,updated_at=? WHERE id=?",time(),$job['id']);
$files = [];
try {
    $zip = new ZipArchive();
    if ($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Cannot open archive');
    foreach (json_decode($job['manifest'],true,512,JSON_THROW_ON_ERROR) as $entry) {
        $source = $tracks->getFileUri($entry['source'],false);
        if (preg_match('~^https?://~i',$source)) {
            if (!str_starts_with($entry['source'],'s3-') && !str_starts_with($entry['source'],'w3-')) throw new RuntimeException('Unsupported remote storage');
            $file = $dir.'/'.$job['id'].'-audio-'.bin2hex(random_bytes(12));
            $files[] = $file;
            $stream = fopen($source,'rb');
            if (!$stream || file_put_contents($file,$stream) === false) throw new RuntimeException('Cannot stage audio');
            fclose($stream);
        } else {
            $file = realpath(path($source));
            if (!$file || (!str_starts_with($file,realpath(path('uploads')).'/') && !str_starts_with($file,dirname(rtrim(APP_BASE_PATH,'/')).'/musicengine-private/'))) throw new RuntimeException('Invalid audio source');
        }
        if (!$zip->addFile($file,$entry['id'].'-'.$entry['title'].'.'.get_file_extension($entry['source']))) throw new RuntimeException('Cannot add track');
    }
    if (!$zip->close() || !rename($tmp,$published)) throw new RuntimeException('Cannot publish archive');
    $db->query("UPDATE archive_jobs SET state='ready',updated_at=? WHERE id=?",time(),$job['id']);
} catch (Throwable $error) {
    unset($zip);
    if (is_file($tmp)) unlink($tmp);
    $db->query("UPDATE archive_jobs SET state=IF(attempts>=3,'failed','pending'),updated_at=? WHERE id=?",time(),$job['id']);
    error_log('Archive build failed for '.$job['id']);
} finally {
    foreach ($files as $file) if (is_file($file)) unlink($file);
}
