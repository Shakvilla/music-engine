<?php
// Run in the local Apache container against its published application routes.
if (PHP_SAPI !== 'cli') exit;
function requestStatus($path,$method='GET',$fields=[],$cookie='') {
    $context = stream_context_create(['http'=>[
        'method'=>$method,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,
        'header'=>"Content-Type: application/x-www-form-urlencoded\r\nCookie: ".$cookie,
        'content'=>http_build_query($fields),
    ]]);
    $body = file_get_contents('http://127.0.0.1/'.$path,false,$context);
    $headers = http_get_last_response_headers();
    preg_match('/HTTP\/\S+ (\d+)/',$headers[0] ?? '',$status);
    return [(int)($status[1] ?? 0),$body,$headers];
}
function expect($ok,$label) { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
[$status,$body,$headers] = requestStatus('');
expect($status===200 && str_contains($body,'csrf-token'),'homepage renders with browser token');
preg_match('/name="csrf-token" content="([a-f0-9]+)"/',$body,$token);
$cookie='';
foreach ($headers as $header) if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i',$header,$match)) $cookie=$match[1];
expect(requestStatus('?p=login','POST',[],$cookie)[0]===403,'browser POST requires token');
expect(requestStatus('?p=login','POST',['csrf_token'=>$token[1],'val'=>['username'=>'fixture-never-created@example.invalid','password'=>'fixture']],$cookie)[0]===200,'valid same-session POST reaches login');
expect(requestStatus('?p=api/unconfigured-key/setup')[0]===403,'API dispatch rejects unconfigured client key');
expect(requestStatus('MusicEngine.sql')[0]===403,'Apache denies SQL snapshots');
expect(requestStatus('?p=track/media/'.str_repeat('0',48))[0]===403,'unknown media grant denied');
expect(requestStatus('?p=payment/stripe/hook','POST')[0]===400,'unsigned webhook rejected');
