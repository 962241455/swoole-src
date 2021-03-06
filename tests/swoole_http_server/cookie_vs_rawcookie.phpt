--TEST--
swoole_http_server: cookie vs rawcookie
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';
$pm = new ProcessManager;
$pm->parentFunc = function () use ($pm) {
    go(function () use ($pm) {
        $cli = new Swoole\Coroutine\Http\Client('127.0.0.1', $pm->getFreePort());
        $cookie = '123_,; abc';
        assert($cli->get('/?cookie=' . urlencode($cookie)));
        assert($cli->statusCode === 200);
        assert($cli->set_cookie_headers ===
            [
                'cookie' => 'cookie=' . urlencode($cookie),
                'rawcookie' => 'rawcookie=' . $cookie,
            ]
        );
    });
    for ($i = MAX_CONCURRENCY_LOW; $i--;) {
        go(function () use ($pm) {
            $cli = new Swoole\Coroutine\Http\Client('127.0.0.1', $pm->getFreePort());
            $random = get_safe_random();
            assert($cli->get('/?cookie=' . $random));
            assert($cli->statusCode === 200);
            assert($cli->set_cookie_headers ===
                [
                    'cookie' => 'cookie=' . urlencode($random),
                    'rawcookie' => 'rawcookie=' . $random
                ]
            );
        });
    }
    swoole_event_wait();
    echo "SUCCESS\n";
    $pm->kill();
};
$pm->childFunc = function () use ($pm) {
    $http = new swoole_http_server('0.0.0.0', $pm->getFreePort(), SWOOLE_BASE);
    $http->set(['worker_num' => 1, 'log_file' => '/dev/null']);
    $http->on('request', function (swoole_http_request $request, swoole_http_response $response) {
        $request->get['cookie'] = urldecode($request->get['cookie']);
        $response->cookie('cookie', $request->get['cookie']);
        $response->rawcookie('rawcookie', $request->get['cookie']);
        $response->end();
    });
    $http->start();
};
$pm->childFirst();
$pm->run();
?>
--EXPECT--
SUCCESS
