<?php

if (!function_exists('curl_get_init')) {

    /**
     * Simple wrappers for curl_init(). Some default values are defined.
     *
     * @param string $url
     * @param array<CURLOPT_*, mixed> $options=[]
     * @return resource
     */
    function curl_get_init($url, array $options = []) {
        static $default;
        if (!$default) {
            $default = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => 'gzip',
                CURLOPT_FOLLOWLOCATION => (string)ini_get('open_basedir') === '',
            ];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + $default);
        return $ch;
    }
}

if (!function_exists('curl_post_init')) {

    /**
     * Simple wrappers for curl_init(). Some default values are defined.
     *
     * @param string $url
     * @param array<string, string> $postfields=[]
     * @param array<CURLOPT_*, mixed> $options=[]
     * @return resource
     */
    function curl_post_init($url, array $postfields = [], array $options = []) {
        static $default;
        if (!$default) {
            $default = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => 'gzip',
                CURLOPT_FOLLOWLOCATION => (string)ini_get('open_basedir') === '',
                CURLOPT_POST => true,
                CURLOPT_SAFE_UPLOAD => true,
            ];
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            array_filter($postfields, function ($v) { return $v instanceof \CURLFile; })
            ? $postfields
            : http_build_query($postfields, '', '&', PHP_QUERY_RFC3986)
        );
        curl_setopt_array($ch, $options + $default);
        return $ch;
    }

}
