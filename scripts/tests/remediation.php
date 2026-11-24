<?php
// Run from the application root with PHP CLI. Fixtures are rolled back.
if (PHP_SAPI !== 'cli') exit;
define('APP_BASE_PATH', getcwd().'/');
require APP_BASE_PATH.'app/request.php';
require APP_BASE_PATH.'app/vendor/utils.php';
function checkResult($ok, $description) {
    if (!$ok) throw new RuntimeException($description);
    fwrite(STDERR, "PASS $description\n");
}
$_SERVER += ['HTTP_HOST'=>'localhost','REQUEST_URI'=>'/','REQUEST_METHOD'=>'GET','SCRIPT_NAME'=>'/index.php'];
session_start();
Request::instance()->start(function ($request) {
    $c = new Controller($request);
    $request->controller = $c;
    $db = Database::getInstance();
    $db->query('START TRANSACTION');
    try {
        $users = [];
        $tag = bin2hex(random_bytes(6));
        foreach (['alice','bob','eve'] as $alias) {
            $db->query("INSERT INTO users (username,password,email,full_name,user_type,date_created,active) VALUES(?,?,?,?,1,?,1)",
                $alias.$tag,hashUserPassword($tag),$alias.$tag.'@example.invalid',$alias,time());
            $users[] = (int)$db->lastInsertId();
        }
        [$alice,$bob,$eve] = $users;
        $auth = $c->model('user');
        $token = $auth->issueCredential($alice,'mobile');
        checkResult($auth->credentialUser($token,'mobile')['id'] == $alice && !$auth->credentialUser($token,'browser'), 'mobile credential bound to kind and owner');
        $db->query("INSERT INTO auth_challenges (userid,purpose,digest,expires) VALUES(?, 'reset', UNHEX(?),?)",$alice,hash('sha256',$tag),time()+60);
        checkResult(!$auth->challengeUser($bob,'reset',$tag) && !$auth->challengeUser($alice,'activation',$tag), 'challenge bound to account and purpose');
        checkResult($auth->challengeUser($alice,'reset',$tag) && !$auth->challengeUser($alice,'reset',$tag),'one-time challenge consumption');
        $chat = $c->model('message');
        $cid = $chat->getConversationId($alice,$bob);
        $db->query("INSERT INTO chat (from_uid,to_uid,cid,message,time) VALUES(?,?,?,?,?)",$alice,$bob,$cid,'fixture',time());
        $messageId = $db->lastInsertId();
        $auth->authId = $bob;
        $rows = $chat->getMessages($cid,0,10);
        checkResult(count($rows)===1 && !array_intersect(['password','token','two_factor_auth'],array_keys($rows[0])), 'participant receives only public sender fields');
        checkResult(count($chat->getConversations())===1,'bounded inbox returns fixture');
        $auth->authId = $eve;
        checkResult($chat->getMessages($cid,0,10)===[] && !$chat->findConversation($cid) && !$chat->getMessage($messageId),'unrelated account cannot read chat');
        checkResult(!$chat->markSeen($messageId)->rowCount(),'read receipt scoped to recipient');
        checkResult(readPaginationState(paginationState(['cid'=>$cid]),['cid'])['cid']==$cid && paginationNumber('120',true)===100, 'JSON pagination round trip and page-size cap');
        $auth->authId = $alice;
        $auth->authUser = $auth->getUser($alice);
        $orders = $c->model('order');
        $price = $orders->create('pro','monthly');
        $order = $orders->current('pro','monthly','stripe');
        checkResult($order['userid']==$alice && $order['base_price']==config('pro-artist-month-price',5) && $price > 0, 'checkout amount and owner come from server');
    } finally {
        $db->query('ROLLBACK');
    }
});
