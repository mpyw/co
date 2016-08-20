<?php

require __DIR__ . '/../../vendor/autoload.php';
set_time_limit(0);
declare(ticks = 1);
pcntl_signal(SIGCHLD, 'signal_handler');
serve();

/**
 * Erorr-safe fwrite().
 * @param  resource $con
 * @param  string $data
 * @throws \RuntimeException
 */
function fsend($con, string $data)
{
    if (!@fwrite($con, $data)) {
        $error = error_get_last();
        throw new \RuntimeException($error['message']);
    }
}

/**
 * Respond to the REST endpoints.
 * @param  resource $con
 * @param  int      $status
 * @param  string   $message
 * @param  string   $content
 */
function respond_rest($con, int $status, string $message, string $content)
{
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

/**
 * Respond to the Streaming endpoints.
 * @param  resource $con
 * @param  callable $tick_function
 * @param  int      $tick
 * @param  Int      $times
 */
function respond_streaming($con, callable $tick_function, int $tick, int $times)
{
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

/**
 * Launch HTTP server.
 */
function serve()
{
    $socket = stream_socket_server("tcp://localhost:8080", $errno, $errstr);
    if (!$socket) {
        fwrite(STDERR, "[Server] $errstr($errno)\n");
        exit(1);
    }
    while (true) {
        $con = @stream_socket_accept($socket, -1);
        if (!$con) {
            $err = error_get_last();
            if (strpos($err['message'], 'Interrupted system call') !== false) {
                continue;
            }
            fwrite(STDERR, "[Server] $err[message]\n");
            exit(1);
        }
        if (-1 === $pid = pcntl_fork()) {
            fwrite(STDERR, "[Server] Failed to fork\n");
            exit(1);
        }
        if ($pid) {
            // parent process
            continue;
        }
        // child process
        $endpoints = [
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
        ];
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

function signal_handler(int $sig)
{
    if ($sig !== SIGCHLD) {
        return;
    }
    $ignore = NULL;
    while (($rc = pcntl_waitpid(-1, $ignore, WNOHANG)) > 0);
    if ($rc !== -1 || pcntl_get_last_error() === PCNTL_ECHILD) {
        return;
    }
    fwrite(STDERR, 'waitpid() failed: ' . pcntl_strerror(pcntl_get_last_error()) . "\n");
    exit(1);
}
