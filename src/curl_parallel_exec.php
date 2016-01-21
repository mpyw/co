<?php

if (!function_exists('curl_parallel_exec')) {

    /**
     * Convert array of curl resources into content strings.
     *
     * @param array<resource<curl>> $curls
     * @param int $timeout
     * @return array<string>
     */
    function curl_parallel_exec(array $curls, $timeout) {

        $mh = curl_multi_init();

        // resource mapping
        $id_to_offsets = [];  // array<curl resource id, original offset>
        $results = []; // array<>

        // register resources
        foreach ($curls as $i => $ch) {
            if (!is_resource($ch) || get_resource_type($ch) !== 'curl') {
                throw new \InvalidArgumentException('each entry must be a curl resource');
            }
            $id_to_offsets[(int)$ch] = $i;
            $results[$i] = null; // value for timeout
            curl_multi_add_handle($mh, $ch);
        }

        // start requests
        curl_multi_exec($mh, $active);

        // loop until all done or timeout
        do {
            // update state
            $is_timeout = curl_multi_select($mh, $timeout) === 0;
            curl_multi_exec($mh, $active);
            // dequeue entries
            do if ($entry = curl_multi_info_read($mh, $remains)) {
                // retrive original offset
                $i = $id_to_offsets[(int)$entry['handle']];
                if ($entry['result'] !== CURLE_OK) {
                    // error
                    // NOTE: curl_errno() doesn't work with curl_multi_*.
                    $results[$i] = new \RuntimeException(curl_error($entry['handle']), $entry['result']);
                } else {
                    // success
                    $results[$i] = curl_multi_getcontent($entry['handle']);
                }
            } while ($remains);
        } while (!$is_timeout && $active);

        foreach ($results as $i => $r) {
            if ($r === null) {
                // Replace NULL into RuntimeException
                $results[$i] = new \RuntimeException('still runnning, but curl_multi_select() timeout');
            }
        }

        return $results;

    }

}
