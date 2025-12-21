<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/CompilerTestCase.php';

use Test\Test;
use Test\SubTest\Example;
use Test\SubTest\AnotherExample;

$tester = new CompilerTestCase();

/**
 * 1️⃣ Namespace & Class testleri
 */
$tester->assertClassExists(Test::class);
$tester->assertClassExists(Example::class);
$tester->assertClassExists(AnotherExample::class);

/**
 * 2️⃣ Method testleri
 */
$tester->assertMethodExists(Example::class, 'getMessage');
$tester->assertMethodExists(AnotherExample::class, 'getAnotherMessage');

/**
 * 3️⃣ Runtime execution testleri
 */
$tester->assertCallable(function () {
    $test = new Test();
    $result = $test->run();

    if (!is_array($result)) {
        throw new Exception("run() did not return array");
    }
}, 'Test::run');

/**
 * 4️⃣ Bilinçli olarak olmayan sınıf testi (fatal oluşturmadan)
 */
$tester->assertCallable(function () {
    if (class_exists('Test\\SubTest\\NonExistentExample')) {
        throw new Exception("Unexpected class exists");
    }
}, 'NonExistentClass');

/**
 * 5️⃣ Rapor
 */
$tester->report();
