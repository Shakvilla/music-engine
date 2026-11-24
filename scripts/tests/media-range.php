<?php
// Run inside the development app container using temporary fixture media.
if (PHP_SAPI !== 'cli') exit;
define('APP_BASE_PATH',getcwd().'/');
require APP_BASE_PATH.'app/request.php';
require APP_BASE_PATH.'app/vendor/utils.php';
require APP_BASE_PATH.'app/vendor/classes/database.php';
$db = Database::getInstance();
$token = bin2hex(random_bytes(24));
$digest = hash('sha256',$token);
$source = 'private-audio/'.bin2hex(random_bytes(24)).'.mp3';
$bytes = random_bytes(1024);
$expected = substr($bytes,0,64);
$id = null;
try {
    if (file_put_contents(path($source),$bytes)!==strlen($bytes)) throw new RuntimeException('Cannot write private fixture');
    $db->query("INSERT INTO tracks (userid,title,tag,genre,track_file,price,time) VALUES(1,'Range fixture','',0,?,0,?)",$source,time());
    $id = $db->lastInsertId();
    $db->query("INSERT INTO media_tickets (digest,userid,track,purpose,expires) VALUES(UNHEX(?),0,?,'stream',?)",$digest,$id,time()+60);
    $multi = curl_multi_init();
    $requests = [];
    for ($i=0;$i<4;$i++) {
        $handle = curl_init('http://127.0.0.1/?p=track/media/'.$token);
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_RANGE=>'0-63',CURLOPT_TIMEOUT=>15]);
        curl_multi_add_handle($multi,$handle);
        $requests[]=$handle;
    }
    do {
        $result = curl_multi_exec($multi,$running);
        if ($result!==CURLM_OK) throw new RuntimeException('Transfer error');
        if ($running) curl_multi_select($multi,1);
    } while ($running);
    foreach ($requests as $handle) {
        $status = curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
        if ($status!==206 || curl_multi_getcontent($handle)!==$expected) throw new RuntimeException('Incorrect range response, HTTP '.$status);
        curl_multi_remove_handle($multi,$handle);
    }
    echo "PASS four concurrent authorized byte ranges returned HTTP 206 and exact audio bytes\n";
    $db->query('UPDATE tracks SET price=5, downloads=1 WHERE id=?',$id);
    foreach (['track/media/'.$token,'track/download&key='.$id] as $route) {
        $request = curl_init('http://127.0.0.1/?p='.$route);
        curl_setopt_array($request,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
        curl_exec($request);
        if (curl_getinfo($request,CURLINFO_RESPONSE_CODE)!==403) throw new RuntimeException('Unpurchased original was not denied');
    }
    echo "PASS repriced track denies prior stream ticket and unpaid download\n";
} finally {
    $db->query("DELETE FROM media_tickets WHERE digest=UNHEX(?)",$digest);
    if ($id) $db->query("DELETE FROM tracks WHERE id=? AND track_file=?",$id,$source);
    if (is_file(path($source))) unlink(path($source));
}
