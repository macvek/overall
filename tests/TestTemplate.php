<?php
include_once 'Template.php';

class TestTemplate {
    public function testSampleReplacement() {
        $template = new Template("pattern with {sampleValue}");
        $value = $template->transform(['sampleValue'=>'Replacement']);
        assertEquals('pattern with Replacement', $value);
    }

    public function testShouldEscapeOpening() {
        $template = new Template("pattern with {{escapedOpening}");
        $value = $template->transform([]);
        assertEquals('pattern with {escapedOpening}', $value);
    }

    public function testShouldEscapeMultipleOpenings() {
        $template = new Template("pattern with {{{{escapedOpening}} and {escapedOpening}");
        $value = $template->transform(['escapedOpening'=>'Replaced']);
        assertEquals('pattern with {{escapedOpening}} and Replaced', $value);
    }
}