<?php

function assertEquals($a, $b) {
    if ($a !== $b) {
        throw new Exception("assertEquals failed, a:$a b:$b");
    }
}

function assertTrue($a) {
    if ($a !== TRUE) {
        throw new Exception("assertTrue failed");
    }
}

function assertFalse($a) {
    if ($a !== FALSE) {
        throw new Exception("assertFalse failed");
    }
}