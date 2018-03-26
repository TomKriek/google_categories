<?php

$json = file_get_contents(__DIR__. '/categories.json');

echo '<pre>';
//echo $json;
print_r(json_decode($json, true));
echo '</pre>';

