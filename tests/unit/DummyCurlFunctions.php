<?php

namespace mpyw\Co\Internal;

function curl_errno($ch) {
    return $ch->errno();
}
function curl_error($ch) {
    return $ch->errstr();
}
function curl_multi_init() {
    return new \DummyCurlMulti;
}
function curl_multi_setopt() { }
function curl_multi_add_handle($mh, $ch) {
    return $mh->addHandle($ch);
}
function curl_multi_remove_handle($mh, $ch) {
    return $mh->removeHandle($ch);
}
function curl_multi_select() { }
function curl_multi_exec($mh, &$active) {
    return $mh->exec($active);
}
function curl_multi_info_read($mh) {
    return $mh->infoRead();
}
function curl_multi_getcontent($ch) {
    return $ch->getContent();
}
