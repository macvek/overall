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

    public function testShouldEvalTwoValues() {
        $template = new Template("pattern with {first} {snd}");
        $value = $template->transform(['first'=>'1', 'snd'=>'2']);
        assertEquals('pattern with 1 2', $value);
    }

    public function testShouldEscapeMultipleOpenings() {
        $template = new Template("pattern with {{{{escapedOpening}} and {escapedOpening}");
        $value = $template->transform(['escapedOpening'=>'Replaced']);
        assertEquals('pattern with {{escapedOpening}} and Replaced', $value);
    }

    public function testShouldIterateOverList() {
        $template = new Template(
            "Introduction {intro}:".
            "{ @each element }".
            ">> {it}".
            "{ @endeach }".
            "{ @each element as each }".
            "@@ {each}".
            "{ @each element as key:value }".
            "%% {key}>{value}".
            "{ @endeach }".
            "{ @endeach }"
        );

        $value = $template->transform([
            'intro'=>"to this test",
            'element' => ['first','snd']
        ]);

        $expected = 
            "Introduction to this test:".
            ">> first".
            ">> snd".
            "@@ first".
            "%% 0>first".
            "%% 1>snd".
            "@@ snd".
            "%% 0>first".
            "%% 1>snd";
            

        assertEquals($expected, $value);
    }
}