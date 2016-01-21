<?php

if (!function_exists('curl_get_init')) {

    /**
     * curl_init() wrapper for GET requests.
     *
     * @param string $url
     * @param array<CURLOPT_*, mixed> $options=[]
     * @return resource<curl>
     */
    function curl_get_init($url, array $options = []) {
        static $default = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_FOLLOWLOCATION => true,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + $default);
        return $ch;
    }
}

if (!function_exists('curl_post_init')) {

    /**
     * curl_init() wrapper for POST requests.
     *
     * @param string $url
     * @param array<string, string> $postfields=[]
     * @param array<CURLOPT_*, mixed> $options=[]
     * @return resource<curl>
     */
    function curl_post_init($url, array $postfields = [], array $options = []) {
        static $default = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST => true,
            CURLOPT_SAFE_UPLOAD => true,
        ];
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
