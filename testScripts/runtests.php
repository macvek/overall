<?php
include 'asserts.php';

$path = get_include_path();
set_include_path($path.
    PATH_SEPARATOR.'../modules'.
    PATH_SEPARATOR.'../tests'
);

$instances = [];

foreach ($testNames as $testName) {
    require_once ($testName.'.php');
    $instances[] = new $testName();
    print '>> Loaded '.$testName."\n";
}
$failed = [];
$passed = [];

foreach ($instances as $testClass) {
    $reflected = new ReflectionClass($testClass);
    $methods = $reflected->getMethods(ReflectionMethod::IS_PUBLIC);
    $testMethods = [];
    foreach ($methods as $eachMethod) {
        if (substr($eachMethod->name, 0, 4) == 'test') {
            $testMethods[] = $eachMethod;
        }
    }

    if (count($testMethods) == 0) {
        print '[ WARN ] there are no tests defined for '.$reflected->name."\n";
    }

    foreach ($testMethods as $testMe) {
        $testName = $reflected->name.'::'.$testMe->name;
        try {
            $testMe->invoke($testClass);
            print '[  OK  ] '.$testName."\n";
            $passed[] = $testName;
        }
        catch(Exception $error) {
            print '[FAILED] '.$testName.' '.$error->getMessage()."\n";
            $failed[] = $testName;
        }
    }
}
print "=======================\n";

if (count($failed) != 0) {
    print "FOLLOWING TESTS FAILED:\n";
    foreach ($failed as $failedName) {
        print "\t$failedName\n";
    }
}
else if (count($passed) == 0) {
    print "NO TESTS WERE EXECUTED!!\n";
}
else {
    print "All tests passed ".count($passed)."\n";
}