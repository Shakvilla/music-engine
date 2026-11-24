<?php
// Run with the web service account. The connection-local table shadows the catalog.
if (PHP_SAPI !== 'cli') exit;
require getcwd().'/app/cli/migrate-audio.php';
$db = Database::getInstance();
$db->query('CREATE TEMPORARY TABLE tracks (id INT PRIMARY KEY, track_file VARCHAR(255), demo_file VARCHAR(255))');
$name = bin2hex(random_bytes(24));
$source = 'uploads/'.$name.'.mp3';
$reference = 'private-audio/'.substr(hash('sha256',$source),0,48).'.mp3';
$bytes = random_bytes(65536);
function verifyMigration(bool $ok, string $label) {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}
try {
    if (file_put_contents(path($source),$bytes)!==strlen($bytes)) throw new RuntimeException('Cannot create fixture');
    $db->query('INSERT INTO tracks VALUES (1,?,?),(2,?,NULL)', $source,$source,'s3-external');
    verifyMigration(migrateAudioReferences($db,false)===[2,1] && !file_exists(path($reference)), 'inventory creates no private copy');
    file_put_contents(path($reference),'interrupted copy');
    try {
        migrateAudioReferences($db,true);
        throw new LogicException('Conflicting destination accepted');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(),'Private copy differs')) throw $e;
    }
    verifyMigration($db->query('SELECT track_file FROM tracks WHERE id=1')->fetchColumn()===$source,'conflicting copy leaves catalog intact');
    unlink(path($reference));
    verifyMigration(migrateAudioReferences($db,true)===[2,1] && file_get_contents(path($reference))===$bytes && file_get_contents(path($source))===$bytes,'migration preserves source and private bytes');
    $row=$db->query('SELECT track_file,demo_file FROM tracks WHERE id=1')->fetch(PDO::FETCH_NUM);
    verifyMigration($row===[$reference,$reference] && migrateAudioReferences($db,true)===[0,1],'shared references migrate once and rerun cleanly');
} finally {
    foreach ([$source,$reference] as $file) if (is_file(path($file))) unlink(path($file));
}
