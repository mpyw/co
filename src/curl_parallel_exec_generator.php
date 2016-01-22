<?php

if (!function_exists('curl_parallel_exec_generator')) {

    /**
     * Process yielded cURL resources RECURSIVELY.
     * Both generators and generator functions can be passed.
     * All events are observed in a single event loop.
     *
     * @param array<Generator>|array<Function<Generator>> $generators
     * @param int $timeout
     */
    function curl_parallel_exec_generator(array $generators, $timeout) {

        static $mh, $m, $register, $resolve, $reject, $dispose, $try_with_resource;

        // initialization
        if (!$mh) {

            $mh = curl_multi_init();

            // resource mapping
            $m['id_to_generator'] = []; // array<curl resource id, Generator>
            $m['id_to_result'] = []; // array<curl resource id, string>
            $m['generator_to_curls'] = []; // array<spl_object_hash(Generator), array<curl resource id, curl resource>>
            $m['generator_to_structures'] = []; // array<spl_object_hash(Generator), mixed>

            /**
             * Dispose all resources related to a Generator.
             *
             * @param Generator|array<Generator> $gen
             */
            $dispose = function ($gen) use ($mh, &$m, &$dispose) {
                if (is_array($gen)) {
                    array_walk($gen, $dispose);
                    return;
                }
                $genhash = spl_object_hash($gen);
                if (!empty($m['generator_to_curls'][$genhash])) {
                    foreach ($m['generator_to_curls'][$genhash] as $id => $ch) {
                        unset($m['id_to_result'][$id]);
                        unset($m['id_to_generator'][$id]);
                        curl_multi_remove_handle($mh, $ch);
                    }
                }
                unset($m['generator_to_curls'][$genhash]);
                unset($m['generator_to_structures'][$genhash]);
            };

            /**
             * Automatically dispose when Exception thrown in a callback.
             *
             * @param Generator|array<Generator> $gen
             * @param callable $callback
             */
            $try_with_resource = function ($gen, callable $callback) use ($mh, &$m, $dispose) {
                try {
                    return $callback();
                } catch (\Throwable $e) { // for PHP7+
                    $dispose($gen);
                    throw $e;
                } catch (\Exception $e) { // for PHP5
                    $dispose($gen);
                    throw $e;
                }
            };

            /**
             * Register yielded cURL resources from a Generator.
             *
             * @param Generator $gen
             * @return bool whether new request registered or not
             */
            $register = function (\Generator $gen) use ($mh, &$m) {
                $genhash = spl_object_hash($gen);
                while (true) {
                    $current = $gen->current(); // NOTE: Must be called before valid() check!!
                    if (!$gen->valid()) {
                        return false;
                    }
                    // single cURL resource
                    if (is_resource($current) && get_resource_type($current) === 'curl') {
                        if (curl_multi_add_handle($mh, $current) !== 0) {
                            throw new \RuntimeException(curl_multi_strerror($mh, $current));
                        }
                        $m['generator_to_curls'][$genhash][(int)$current] = $current;
                        $m['id_to_result'][(int)$current] = null;
                        $m['id_to_generator'][(int)$current] = $gen;
                        $m['generator_to_structures'][$genhash] = $current;
                        return true;
                    }
                    // multiple cURL resources
                    if (is_array($current)) {
                        $added = false;
                        array_walk_recursive($current, function ($v) use ($mh, $gen, $genhash, &$m, &$added) {
                            if (is_resource($v) && get_resource_type($v) === 'curl') {
                                if (curl_multi_add_handle($mh, $v) !== 0) {
                                    throw new \RuntimeException(curl_multi_strerror($mh, $v));
                                }
                                $m['generator_to_curls'][$genhash][(int)$v] = $v;
                                $m['id_to_result'][(int)$v] = null;
                                $m['id_to_generator'][(int)$v] = $gen;
                                $added = true;
                            }
                        });
                        if ($added) {
                            $m['generator_to_structures'][$genhash] = $current;
                            return true;
                        }
                    }
                    // nothing found
                    $gen->send($current);
                }
            };

            /**
             * Resolve $m['generator_to_structures'] and continue.
             *
             * @param $entry array return value of curl_multi_info_read()
             * @return bool whether new request registered or not
             */
            $resolve = function ($entry) use ($mh, &$m, $register) {
                // collect informations
                $ch = $entry['handle'];
                $gen = $m['id_to_generator'][(int)$ch];
                $genhash = spl_object_hash($gen);
                $content = curl_multi_getcontent($ch);
                // update informations and dispose resources
                $m['id_to_result'][(int)$ch] = $content;
                unset($m['id_to_generator'][(int)$ch]);
                unset($m['generator_to_curls'][$genhash][(int)$ch]);
                curl_multi_remove_handle($mh, $ch);
                // check if contents are sufficient
                if (!empty($m['generator_to_curls'][$genhash])) {
                    return false;
                }
                // convert structure
                $structure = $m['generator_to_structures'][$genhash];
                unset($m['generator_to_structures'][$genhash]);
                if (is_array($structure)) {
                    // multiple cURL resources
                    array_walk_recursive($structure, function (&$v) use (&$m) {
                        if (
                            is_resource($v) &&
                            get_resource_type($v) === 'curl' &&
                            isset($m['id_to_result'][(int)$v])
                        ) {
                            $v = $m['id_to_result'][(int)$v];
                            unset($m['id_to_result'][(int)$v]);
                        }
                    });
                } elseif (
                    is_resource($structure) &&
                    get_resource_type($structure) === 'curl' &&
                    isset($m['id_to_result'][(int)$structure])
                ) {
                    // single cURL resource
                    $v = $m['id_to_result'][(int)$structure];
                    unset($m['id_to_result'][(int)$structure]);
                }
                // continue generator
                $gen->send($structure);
                return $register($gen);
            };

            /**
             * Reject $m['generator_to_structures'] and throw.
             *
             * @param $entry array return value of curl_multi_info_read()
             * @return bool whether new request registered or not
             */
            $reject = function ($entry) use ($mh, &$m, $register, $dispose) {
                // collect informations
                $ch = $entry['handle'];
                $gen = $m['id_to_generator'][(int)$ch];
                $genhash = spl_object_hash($gen);
                $error = curl_error($ch);
                // dispose resources
                $dispose($gen);
                // continue generator
                $gen->throw(new \RuntimeException($error, $entry['result']));
                return $register($gen);
            };

        }

        foreach ($generators as $i => $gen) {
            // validate as Generator or Generator function
            if (!$gen instanceof \Generator && !is_callable($gen)) {
                throw new \InvalidArgumentException('each entry must be a Generator instance or a Generator function');
            }
            if (!$gen instanceof \Generator) {
                $gen = $gen();
                if (!$gen instanceof \Generator) {
                    throw new \InvalidArgumentException('each entry must be a Generator instance or a Generator function');
                }
                $generators[$i] = $gen;
            }
            // register first yields
            $try_with_resource($generators, function () use ($register, $gen) {
                $register($gen);
            });
        }

        // start requests
        curl_multi_exec($mh, $active);

        // loop until all yields done or timeout
        do {
            // update state
            $added = false;
            $is_timeout = curl_multi_select($mh, $timeout) === 0;
            curl_multi_exec($mh, $active);
            // dequeue entries
            // NOTE: enqueuing and dequeuing at the same time causes curl_multi_info_read() hang up bugs!!
            $entries = [];
            do if ($entry = curl_multi_info_read($mh, $remains)) {
                $entries[] = $entry;
            } while ($remains);
            // resolve entries
            foreach ($entries as $entry) {
                $gen = $m['id_to_generator'][(int)$entry['handle']];
                $callback = $entry['result'] === CURLE_OK ? $resolve : $reject;
                $added = $try_with_resource($generators, function () use ($callback, $entry) {
                    return $callback($entry);
                });
            }
        } while ($added || !$is_timeout && $active);

        foreach ($generators as $gen) {
            if ($gen->valid()) {
                // throw RuntimeException if timeout
                $try_with_resource($generators, function () use ($gen) {
                    $gen->throw(new \RuntimeException('still runnning, but curl_multi_select() timeout'));
                });
            }
        }

    }

}
