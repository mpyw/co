<?php

require __DIR__ . '/../vendor/autoload.php';
set_time_limit(0);

// REST API
function curl($path, array $q = array()) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => "http://localhost:8080$path?" . http_build_query($q, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ));
    return $ch;
}

// Streaming API
function curl_streaming($path, $callback, array $q = array()) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => "http://localhost:8080$path?" . http_build_query($q, '', '&'),
        CURLOPT_WRITEFUNCTION => function ($ch, $buf) use ($callback) {
            $callback($buf);
            return strlen($buf);
        },
    ));
    return $ch;
}

// Just for debugging
function co_dump() {
    static $reg_values = '@^  \["values":"mpyw\\\\Co\\\\Co":private\]=>\n  array\(\d+?\) {\n(.*?)\n  \}$@ms';
    static $reg_gens = '@\["(\w+)"\]=>\s+object\(Generator\)#(\d+)@';
    $r = new ReflectionProperty('mpyw\Co\Co', 'self');
    $r->setAccessible(true);
    ob_start();
    var_dump($r->getValue());
    $dumped = ob_get_clean();
    if (preg_match($reg_values, $dumped, $m_value)) {
        preg_match_all($reg_gens, $m_value[1], $m_gens, PREG_SET_ORDER);
        foreach ($m_gens as $m_gen) {
            $dumped = str_replace($m_gen[1], "Generator id #$m_gen[2]", $dumped);
        }
    }
    echo $dumped;
}
