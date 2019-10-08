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

    public function testShouldEvaluateNestedValue() {
        $template = new Template("dictionary lookup: {input.dictValue}; array lookup: {inArray.1}");
        $value = $template->transform(['input'=>['dictValue'=>1234], 'inArray' => [1,2,3]]);
        assertEquals('dictionary lookup: 1234; array lookup: 2', $value);
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

    public function testShouldCheckIfStatement() {
        $template = new Template(
            "Should be ".
            "{ @if trueCondition }".
            "OK ".
            "{ @endif }".
            "Should end with this statement".
            "{@if falseCondition }".
            "this will not show".
            "{@endif}"
        );
        
        $value = $template->transform([
            'trueCondition'=> true,
            'falseCondition' => false
        ]);

        $expected =
            "Should be ".
            "OK ".
            "Should end with this statement";

        assertEquals($expected, $value);
        
    }

    public function testShouldCheckIfElseStatement() {
        $template = new Template(
            "Should be ".
            "{ @if trueCondition }".
            "OK ".
            "{ @else }".
            "Never shown".
            "{ @endif }".
            "Should be ".
            "{ @if falseCondition }".
            "Neven shown ".
            "{ @else }".
            "OK ".
            "{ @endif }"
        );
        
        $value = $template->transform([
            'trueCondition'=> true,
            'falseCondition' => false
        ]);

        $expected =
            "Should be ".
            "OK ".
            "Should be ".
            "OK "
        ;

        assertEquals($expected, $value);
        
    }

    public function testShouldCheckElseIfStatement() {
        $template = new Template(
            "{ @if falseCondition }".
            "FAILED 1.1".
            "{ @elseif falseCondition }".
            "FAILED 1.2".
            "{ @else }".
            "PASSED 1".
            "{ @endif }".
            "{ @if falseCondition }".
            "FAILED 2.1 ".
            "{ @elseif trueCondition}".
            "PASSED 2".
            "{ @elseif falseCondition}".
            "FAILED 2.2".
            "{ @endif }".
            "{@if falseCondition}".
            "FAILED 3.1".
            "{@elseif falseCondition}".
            "FAILED 3.2".
            "{@elseif falseCondition}".
            "FAILED 3.3".
            "{@elseif trueCondition}".
            "PASSED 3".
            "{@endif}".
            "{@if falseCondition}".
            "FAILED 4.1".
            "{@elseif falseCondition}".
            "FAILED 4.2".
            "{@endif}".
            "PASSED 4"
        );
        
        $value = $template->transform([
            'trueCondition'=> true,
            'falseCondition' => false
        ]);

        $expected =
            "PASSED 1".
            "PASSED 2".
            "PASSED 3".
            "PASSED 4"
        ;

        assertEquals($expected, $value);
        
    }

    public function testErrorCaseOnlyEndIf() {
        $template = new Template(
            "{ @endif }".
            "anything");

        $fail = false;
        try { 
            $value = $template->transform([
                'falseCondition' => false
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }

    public function testErrorCaseIfNoEndIf() {
        $template = new Template(
            "{ @if falseCondition }".
            "anything");

        $fail = false;
        try { 
            $value = $template->transform([
                'falseCondition' => false
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }

    public function testErrorCaseStartingWithElse() {
        $template = new Template(
            "{ @else falseCondition }".
            "anything");

        $fail = false;
        try { 
            $value = $template->transform([
                'falseCondition' => false
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }

    public function testErrorCaseStartingWithElseIf() {
        $template = new Template(
            "{ @elseif falseCondition }".
            "anything");

        $fail = false;
        try { 
            $value = $template->transform([
                'falseCondition' => false
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }

    public function testErrorCaseDoubleElse() {
        $template = new Template(
            "{ @if falseCondition }".
            "{ @else }".
            "{ @else }".
            "{ @endif }".
            "anything");

        $fail = false;
        try { 
            $value = $template->transform([
                'falseCondition' => false
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }

    public function testErrorCaseOnlyEach() {
        $template = new Template("{ @each element }");

        $fail = false;
        try { 
            $value = $template->transform([
                'element' => []
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }
    
    public function testErrorCaseOnlyEndEach() {
        $template = new Template("{ @endeach }");

        $fail = false;
        try { 
            $value = $template->transform([
                'element' => []
            ]);
            $fail = true;
        }
        catch(Exception $e) {
            $fail = false;
        }

        assertFalse($fail);
    }

}