<?php

namespace mpyw\Co\Internal;

function curl_errno(\DummyCurl $ch) {
    return $ch->errno();
}
function curl_error(\DummyCurl $ch) {
    return $ch->errstr();
}
function curl_setopt(\DummyCurl $ch, $key, $value) {
    if ($key === CURLOPT_PRIVATE) {
        $ch->setPrivate($value);
    }
}
function curl_getinfo(\DummyCurl $ch, $key) {
    if ($key === CURLINFO_PRIVATE) {
        return $ch->getPrivate();
    }
}
function curl_multi_init() {
    return new \DummyCurlMulti;
}
function curl_multi_setopt() { }
function curl_multi_add_handle(\DummyCurlMulti $mh, \DummyCurl $ch) {
    return $mh->addHandle($ch);
}
function curl_multi_remove_handle(\DummyCurlMulti $mh, \DummyCurl $ch) {
    return $mh->removeHandle($ch);
}
function curl_multi_select() {
    return -1;
}
function curl_multi_exec(\DummyCurlMulti $mh, &$active) {
    return $mh->exec($active);
}
function curl_multi_info_read(\DummyCurlMulti $mh) {
    return $mh->infoRead();
}
function curl_multi_getcontent(\DummyCurl $ch) {
    return $ch->getContent();
}
