<?php
// Run with PHP CLI in the app container. All benchmark data is connection-local.
if (PHP_SAPI !== 'cli') exit;
$config = require 'config.php';
$db = new PDO("mysql:host=".$config['db_host'].";dbname=".$config['db_name'], $config['db_username'],$config['db_password']);
$db->exec('CREATE TEMPORARY TABLE bench_views (id INT PRIMARY KEY, track INT, userid INT, KEY track_user(track,userid))');
$db->exec('SET SESSION cte_max_recursion_depth=20001');
$db->exec('INSERT INTO bench_views WITH RECURSIVE n AS (SELECT 1 i UNION ALL SELECT i+1 FROM n WHERE i<20000) SELECT i,1+MOD(i,100),MOD(i,1301) FROM n');
function measure(callable $task) {
    $task();
    $times = [];
    for ($i=0;$i<30;$i++) {
        $start = hrtime(true);
        $result = $task();
        $times[] = (hrtime(true)-$start)/1e6;
    }
    sort($times);
    return [$result,round($times[28],2),round($times[29],2)];
}
$legacy = function () use ($db) {
    $counts = [];
    for ($i=1;$i<=10;$i++) {
        $counts[$i] = $db->query("SELECT DISTINCT userid FROM bench_views IGNORE INDEX(track_user) WHERE track=$i")->rowCount();
    }
    return $counts;
};
$batch = fn() => array_map('intval',$db->query('SELECT track,COUNT(DISTINCT userid) FROM bench_views WHERE track BETWEEN 1 AND 10 GROUP BY track ORDER BY track')->fetchAll(PDO::FETCH_KEY_PAIR));
[$a,$before95,$before99]=measure($legacy);
[$b,$after95,$after99]=measure($batch);
if ($a !== $b) throw new RuntimeException('Distinct counts changed');
printf("20,000 fixture events; 10 tracks per page; 30 sequential samples; same counts. Query count 10 -> 1. p95 %.2f -> %.2f ms; p99 %.2f -> %.2f ms.\n",$before95,$after95,$before99,$after99);
