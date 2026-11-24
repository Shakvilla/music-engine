<?php
// Run from the application root with PHP CLI. Uses connection-local tables for the receipt lifecycle.
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
    foreach (['users','checkout_orders','bank_transfers','transactions','notifications'] as $table) {
        $schema = $db->query("SHOW CREATE TABLE `$table`")->fetchColumn(1);
        $db->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema, 1));
    }
    $db->query("INSERT INTO users (username,password,email,full_name,user_type,date_created,active) VALUES(?,?,?,?,1,?,1)",
        'buyer','fixture','buyer@example.invalid','Checkout fixture',time());
    $auth = $c->model('user');
    $auth->authId = $db->lastInsertId();
    $auth->authUser = $auth->getUser($auth->authId);
    $orders = $c->model('order');
    $admin = $c->model('admin');
    foreach (['delete','approve'] as $action) {
        $orders->create('pro','monthly');
        $order = $orders->current('pro','monthly','bank');
        $admin->saveBankTransfer($order,'fixture.png');
        $receipt = $db->query("SELECT reference_id FROM checkout_orders WHERE id=? AND state='review'",$order['id'])->fetchColumn();
        checkResult($receipt > 0, 'receipt linked to order for '.$action);
        $admin->doBankAction($receipt,$action);
        $admin->doBankAction($receipt,$action);
        $state = $db->query('SELECT state FROM checkout_orders WHERE id=?',$order['id'])->fetchColumn();
        $count = $db->query('SELECT COUNT(*) FROM transactions WHERE sale_id=?','bank-'.$receipt)->fetchColumn();
        checkResult($action === 'delete' ? $state === 'cancelled' && $count == 0 : $state === 'paid' && $count == 1,
            'repeat bank decision settles correctly for '.$action);
    }
});
