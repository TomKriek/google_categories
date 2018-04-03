<?php

require __DIR__ . '/Sorter.php';

$json = (new \TomKriek\GoogleCategories\Sorter([], true))->sort()->sortByParent()->outputArray();
