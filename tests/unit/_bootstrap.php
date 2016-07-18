<?php
// Here you can initialize variables that will be available to your tests
//

function vd(...$args)
{
    ob_start();
    var_dump(...$args);
    $data = ob_get_clean();
    file_put_contents('php://stderr', "\n$data\n");
}
