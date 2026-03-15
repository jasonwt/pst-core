<?php

declare(strict_types=1);
use PST\Core\Collections\Enumerator;
use PST\Core\TypeHint;

require_once __DIR__ . '/vendor/autoload.php';

$gen = function(): Generator {
    yield 1;
    yield 2.2;
    yield 3;
};



$arrayEnumerator = new Enumerator($gen(), TypeHint::mixed());

$arr = $arrayEnumerator
    ->where(fn($x) => is_int($x))
    ->select(fn($x): int => $x * 2)
    ->toArray();


print_r($arr);



// foreach ($arrayEnumerator as $k => $v) {
//     echo "$k: $v\n";
// }