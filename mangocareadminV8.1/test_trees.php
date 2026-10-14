<?php
require 'config/config.php';
$f = new Firebase();
$trees = $f->get('trees') ?? [];
print_r(array_slice($trees, 0, 2));
