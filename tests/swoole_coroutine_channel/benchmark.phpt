--TEST--
swoole_coroutine_channel: 100W benchmark
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';
$time = [];

// spl queue
$time['splQueue'] = microtime(true);
$queue = new SplQueue;
for ($i = MAX_LOOPS; $i--;) {
    $queue->enqueue($i);
}
$i = MAX_LOOPS;
while (!$queue->isEmpty()) {
    assert((--$i) === $queue->shift());
}
$time['splQueue'] = microtime(true) - $time['splQueue'];

// channel
go(function () use (&$time) {
    $time['channel_raw'] = microtime(true);
    $chan = new Chan(MAX_LOOPS);
    for ($i = MAX_LOOPS; $i--;) {
        $chan->enqueue($i);
    }
    $i = MAX_LOOPS;
    while (!$chan->isEmpty()) {
        assert((--$i) === $chan->dequeue());
    }
    $time['channel_raw'] = microtime(true) - $time['channel_raw'];
});

// channel with scheduler
$chan = new Chan;
go(function () use (&$time, $chan) {
    co::sleep(0.1);
    $time['channel_scheduler'] = microtime(true);
    for ($i = MAX_LOOPS; $i--;) {
        $chan->enqueue($i);
    }
    $chan->enqueue(false);
});
go(function () use (&$time, $chan) {
    $i = MAX_LOOPS;
    while (($ret = $chan->dequeue()) !== false) {
        assert((--$i) === $ret);
    }
    $time['channel_scheduler'] = microtime(true) - $time['channel_scheduler'];
    $chan->close();
});

swoole_event_wait();
var_dump($time);
$diff = $time['channel_raw'] - $time['splQueue'];
var_dump($diff);
?>
--EXPECTF--
array(3) {
  ["splQueue"]=>
  float(%f)
  ["channel_raw"]=>
  float(%f)
  ["channel_scheduler"]=>
  float(%f)
}
float(%f)
