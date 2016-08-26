<?php

namespace mpyw\Co\Internal;

function defined(string $name) : bool {
    if (!\defined($name)) {
        define($name, 114514);
    }
    foreach (debug_backtrace() as $trace) {
        if (isset($trace['class']) && $trace['class'] === 'AutoPoolTest') {
            return $trace['function'] !== 'testInvalidOption';
        }
    }
    return \defined($name);
}
function curl_errno(\DummyCurl $ch) : int {
    return $ch->errno();
}
function curl_error(\DummyCurl $ch) : string {
    return $ch->errstr();
}
function curl_multi_init() : \DummyCurlMulti {
    return new \DummyCurlMulti;
}
function curl_multi_setopt(\DummyCurlMulti $ch, int $key, $value) : bool {
    return true;
}
function curl_multi_add_handle(\DummyCurlMulti $mh, \DummyCurl $ch) : int {
    return $mh->addHandle($ch);
}
function curl_multi_remove_handle(\DummyCurlMulti $mh, \DummyCurl $ch) : int {
    return $mh->removeHandle($ch);
}
function curl_multi_select(\DummyCurlMulti $mh, float $timeout = 1.0) : int {
    return -1;
}
function curl_multi_exec(\DummyCurlMulti $mh, &$active) : int {
    return $mh->exec($active);
}
function curl_multi_info_read(\DummyCurlMulti $mh, &$msgs_in_queue = null) {
    return $mh->infoRead($msgs_in_queue);
}
function curl_multi_getcontent(\DummyCurl $ch) : string {
    return $ch->getContent();
}
