<?php
// Run while application writes are paused, after a verified database and media backup.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('APP_BASE_PATH',dirname(__DIR__,2).'/');
require APP_BASE_PATH.'app/request.php';
require APP_BASE_PATH.'app/vendor/utils.php';
require APP_BASE_PATH.'app/vendor/classes/database.php';
function migrateAudioReferences(Database $db, bool $apply): array {
    $after = 0;
    $files = 0;
    $external = 0;
    $uploads = realpath(path('uploads')).'/';
    while (true) {
        $rows = $db->query('SELECT id,track_file,demo_file FROM tracks WHERE id>? ORDER BY id LIMIT 100',$after)->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) break;
        foreach ($rows as $row) {
            $after = $row['id'];
            foreach (['track_file','demo_file'] as $column) {
                $source = $row[$column];
                if (!$source || str_starts_with($source,'private-audio/')) continue;
                if (!str_starts_with($source,'uploads/')) { $external++; continue; }
                $file = realpath(path($source));
                if (!$file || !str_starts_with($file,$uploads) || !is_file($file)) throw new RuntimeException("Missing source for track ".$after);
                $suffix = strtolower(pathinfo($file,PATHINFO_EXTENSION));
                if (!preg_match('/^[a-z0-9]{1,8}$/D',$suffix)) throw new RuntimeException("Invalid extension for track ".$after);
                $files++;
                if (!$apply) continue;
                $reference = 'private-audio/'.substr(hash('sha256',$source),0,48).'.'.$suffix;
                $destination = path($reference);
                $directory = dirname($destination);
                if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Cannot create private directory');
                if (!is_file($destination)) {
                    $temporary = tempnam($directory,'migrate-');
                    try {
                        if (!copy($file,$temporary) || hash_file('sha256',$temporary)!==hash_file('sha256',$file) || !rename($temporary,$destination)) {
                            throw new RuntimeException("Copy verification failed for track ".$after);
                        }
                    } finally { if (is_file($temporary)) unlink($temporary); }
                } elseif (hash_file('sha256',$destination)!==hash_file('sha256',$file)) {
                    throw new RuntimeException("Private copy differs for track ".$after);
                }
                $db->query("UPDATE tracks SET $column=? WHERE id=? AND $column=?",$reference,$after,$source);
            }
        }
    }
    return [$files, $external];
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $apply = in_array('--apply', $argv, true);
    [$files, $external] = migrateAudioReferences(Database::getInstance(), $apply);
    printf("%s: %d local audio references, %d external references.\n",$apply ? 'Applied' : 'Dry run',$files,$external);
}
