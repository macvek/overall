<?php
include_once 'Template.php';

class TestTemplate {
    public function testMe() {
        assertTrue(1 == 1);
    }

    public function testAssert() {
        assertTrue(1 == 0);
    }
}