<?php

require __DIR__ . '/../vendor/autoload.php';
set_time_limit(0);
serve();

function fsend($con, $data) {
    if (!@fwrite($con, $data)) {
        $error = error_get_last();
        throw new \RuntimeException($error['message']);
    }
}
function respond_rest($con, $status, $message, $content) {
    try {
        $length = strlen($content);
        fsend($con, "HTTP/1.1 $status $message\r\n");
        fsend($con, "Content-Length: $length\r\n");
        fsend($con, "\r\n");
        fsend($con, $content);
    } catch (\RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
    }
    fclose($con);
}
function respond_streaming($con, $tick_function, $tick, $times) {
    try {
        fsend($con, "HTTP/1.1 200 OK\r\n");
        fsend($con, "Transfer-Encoding: chunked\r\n");
        fsend($con, "\r\n");
        for ($i = 1; $i <= $times; ++$i) {
            $content = $tick_function($i);
            $length = base_convert(strlen($content), 10, 16);
            fsend($con, "$length\r\n");
            fsend($con, "$content\r\n");
            sleep($tick);
        }
        fsend($con, "0\r\n");
        fsend($con, "\r\n");
    } catch (\RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
    }
    fclose($con);
}

/* Server launcher */
function serve() {
    $socket = stream_socket_server("tcp://localhost:8080", $errno, $errstr);
    if (!$socket) {
        fwrite(STDERR, "[Server] $errstr($errno)\n");
        exit(1);
    }
    while ($con = stream_socket_accept($socket)) {
        if (-1 === $pid = pcntl_fork()) {
            fwrite(STDERR, "[Server] Failed to fork\n");
            exit(1);
        }
        if ($pid) { // grandparant process
            continue;
        }
        if (-1 === $pid = pcntl_fork()) {
            fwrite(STDERR, "[Server] Failed to fork doubly\n");
            exit(1);
        }
        if ($pid) { // parent process
            exit(0);
        }
        $endpoints = array(
            '/rest' => function ($con, $q) {
                $sleep = isset($q['sleep']) ? (int)$q['sleep'] : 0;
                sleep($sleep);
                $id = isset($q['id']) ? (int)$q['id'] : '?';
                respond_rest($con, 200, 'OK', "Rest response #$id (sleep: $sleep sec)\n");
            },
            '/streaming' => function ($con, $q) {
                $tick = isset($q['tick']) ? (int)$q['tick'] : 1;
                $id = isset($q['id']) ? (int)$q['id'] : '?';
                $times = isset($q['times']) ? (int)$q['times'] : 10;
                respond_streaming($con, function ($i) use ($id, $times) {
                    return "Rest response #$id ($i / $times, tick: $tick sec)\n";
                }, $tick, $times);
            },
            '' => function ($con, $q) {
                respond_rest($con, 404, 'Not Found', "Undefined path\n");
            },
        );
        $parts = explode(' ', fgets($con));
        $parsed = parse_url($parts[1]);
        parse_str(isset($parsed['query']) ? $parsed['query'] : '', $q);
        foreach ($endpoints as $endpoint => $action) {
            if ($parsed['path'] === $endpoint || $endpoint === '') {
                if ($endpoint === '') $endpoint = '[Undefined]';
                fwrite(STDERR, "Request: $parts[1]\n");
                $action($con, $q);
                exit(0);
            }
        }
        exit(0); // Unreachable here
    }
}
