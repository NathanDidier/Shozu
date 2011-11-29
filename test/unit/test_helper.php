<?php

# Add project root to PHP include path.
$root = __DIR__ . '/../..';
set_include_path(join(PATH_SEPARATOR, array(
    get_include_path(),
    $root
)));
