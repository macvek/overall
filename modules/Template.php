<?php

class Template {
    private $specialPlaceholder='MUST_NOT_BE_EMPTY';

    private $specialEmptyValue = '@';

    private $specialInline = '=';
    private $specialInsert = '!';
    private $specialJump = '^';
    private $specialCreateFrame = '>';
    private $specialDropFrame = '<';
    
    private $specialEach = 'e';

    private $parts = [];

    public function __construct($inputPattern) {
        $this->inputPattern = $inputPattern;

        $this->evalOperators = [
            $this->specialInline => 'evalInline',
            $this->specialInsert => 'evalInsert',
            $this->specialCreateFrame => 'evalCreateFrame',
            $this->specialDropFrame => 'evalDropFrame',
            $this->specialJump => 'evalJump',
            $this->specialEach => 'evalEach'
        ];
    }

    public function transform($valueMap) {
        if (count($this->parts) == 0) {
            $this->initialize();
        }

        $frames = [$valueMap];
        $len = count($this->parts);
        $output = [];

        for ($cmdIndex = 0; $cmdIndex<$len; $cmdIndex++) {
            $cmd = $this->parts[$cmdIndex];
            if (array_key_exists($cmd, $this->evalOperators)) {
                call_user_func_array([$this, $this->evalOperators[$cmd]], [&$output, &$frames, &$cmdIndex]);
            }
            else {
                throw new Exception("Unsupported command $cmd");
            }
        }

        return implode('',$output);
    }
    
    private function evalInline(&$output, &$frames, &$cmdIndex) {
        $arg = $this->parts[++$cmdIndex];
        $output[] = $arg;
    }

    private function evalInsert(&$output, &$frames, &$cmdIndex) {
        $arg = $this->parts[++$cmdIndex];
        
        $firstArray = strpos($arg,'[');
        $firstDot = strpos($arg,'.');

        $hasArray = FALSE !== $firstArray;
        $hasDot = FALSE !== $firstDot;

        $argLen = strlen($arg);

        $lookupName = trim(substr($arg, 0, min(
            $hasArray ? $firstArray : $argLen, 
            $hasDot ? $firstDot : $argLen)));

        $lookup = $this->frameLookup($frames, $lookupName);
        if (isset($lookup)) {
            if ($hasArray || $hasDot) {
                $output[] = $this->followAccessor([$lookupName=>$lookup], $arg);
            }
            else {
                $output[] = $lookup;
            }
        }
        else {
            $output[] = "@NOTFOUND[$arg]";
        }
    }

    private function evalCreateFrame(&$output, &$frames, &$cmdIndex) {
        $frames[] = [];
    }

    private function evalDropFrame(&$output, &$frames, &$cmdIndex) {
        array_pop($frames);
    }

    private function evalJump(&$output, &$frames, &$cmdIndex) {
        $arg = $this->parts[++$cmdIndex];
        $cmdIndex = $arg-1;
    }

    private function evalEach(&$output, &$frames, &$cmdIndex) {
        $iterVar = $this->parts[$cmdIndex+1];
        $valueVar = $this->parts[$cmdIndex+2];
        $keyVar = $this->parts[$cmdIndex+3];
        $endLoopJump = $this->parts[$cmdIndex+4];
        
        $cmdIndex+=4;

        $currentFrame = &$frames[count($frames)-1];
        $localKey = '@eachLocal';

        if (!array_key_exists($localKey, $currentFrame)) {
            $iterSubject = $this->frameLookup($frames, $iterVar);
            if (!is_array($iterSubject)) {
                throw new Exception("cannot find iterable variable $iterVar");
            }
            
            $keys = array_keys($iterSubject);
            $currentFrame[$localKey] = [
                'keys' => $keys,
                'ptr' => 0,
                'subject' => $iterSubject
            ];
        }
        $eachLocal = &$currentFrame[$localKey];
        $ptr = $eachLocal['ptr'];
        $keys = $eachLocal['keys'];
        if ($ptr < count($keys)) {
            $currentKey = $keys[$ptr];
            $currentFrame[$valueVar] = $eachLocal['subject'][$currentKey];
            if ($keyVar != $this->specialEmptyValue) {
                $currentFrame[$keyVar] = $currentKey;
            }
            $eachLocal['ptr']++;
        }
        else {
            $cmdIndex = $endLoopJump-1;
        }
    }

    private function followAccessor($frame, $accessor) {
        $levels = explode('.',$accessor);
        $root = $frame;
        foreach ($levels as $level) {
            $next = $root[$level];
            $root = $next;
        }
        
        return $root;
    }

    private function initialize() {
        $len = strlen($this->inputPattern);
        $notInBlock = -100;
        $blockStart = $notInBlock;
        $inlineStart = 0;

        $depth = 0;
        for ($ptr=0;$ptr<$len;$ptr++) {
            $char = $this->inputPattern[$ptr];
            $escapeSequenceCheck = ($char == '{' && $blockStart == $ptr-1);
            if ($escapeSequenceCheck) {
                $blockStart = $notInBlock;
            }
            else {
                $insideSpecialBlock = $blockStart != $notInBlock;
                if ($insideSpecialBlock) {
                    if ($char == '{') {
                        $depth++;
                    }
                    elseif ($char == '}') {
                        if ($depth == 0) {
                            $blockValue = substr($this->inputPattern, $blockStart+1, $ptr - ($blockStart+1));
                            $this->pushInline($inlineStart, $blockStart);
                            $inlineStart = $ptr+1;
                            $this->parseBlock($blockValue, $blockStart);
                            $blockStart = $notInBlock;
                        }
                        else {
                            $depth--;
                        }
                    }
                }
                elseif ($char == '{') {
                    $blockStart = $ptr;
                }
            }
        }

        if ($inlineStart < $len) {
            $this->pushInline($inlineStart, $len);
        }
    }

    private function pushInline($from, $to) {
        $this->parts[] = $this->specialInline;
        $this->parts[] = str_replace('{{','{',substr($this->inputPattern, $from, $to-$from));
    }

    private function parseBlock($block, $tracePtr) {
        $trimmed = trim($block);

        if (strlen($trimmed) == 0) {
            throw new Exception("Received an empty block at character $tracePtr");
        }

        if ($trimmed[0] == '@') {
            $this->parseOperation(substr($trimmed, 1), $tracePtr);
        }
        else {
            $this->parts[] = $this->specialInsert;
            $this->parts[] = trim($block);
        }
    }

    private function parseOperation($callLine, $tracePtr) {
        $firstSpace = strpos($callLine,' ');
        
        if (FALSE === $firstSpace) {
            $callName = $callLine;
        }
        else {
            $callName = substr($callLine, 0, $firstSpace);
        }
        try {
            switch($callName) {
                case 'each': return $this->pushCallEach($callLine);
                case 'endeach': return $this->pushCallEndEach();
                default:
                    throw new Exception("Unsupported call name $callName at $tracePtr");
            }
        }
        catch(Exception $parseError) {
            $errorMessage = "Error while parsing operation at pos $tracePtr for line $callLine: {$parseError->getMessage()}";
            throw new Exception($errorMessage, 0, $parseError);
        }
    }

    private function pushCallEach($callLine) {
        $args = explode(' ',$callLine);
        $this->syntaxCheck('each', $args[0]);
        $argCount = count($args);

        $this->syntaxCheckIf($argCount > 1, 'each expects at least 1 argument');
        $iterOverName = $args[1];
        $valuePlaceholder = 'it';
        $keyPlaceholder = $this->specialEmptyValue;

        if ($argCount >= 3) {
            $this->syntaxCheck('as', $args[2]);
        }
        if ($argCount >= 4) {
            $forValues = explode(':',$args[3]);
            if (count($forValues) == 2) {
                $valuePlaceholder = trim($forValues[1]);
                $keyPlaceholder = trim($forValues[0]);
                $this->syntaxCheckIdentifier($keyPlaceholder, 'invalid name for key placeholder');
            }
            else {
                $valuePlaceholder = trim($forValues[0]);
            }
        }

        $this->syntaxCheckIdentifier($valuePlaceholder, 'invalid name for value placeholder');

        $this->pushCallSequence($this->specialCreateFrame);
        $valueToBeFilledByEndingTag = $this->specialPlaceholder;
        $entryCall = count($this->parts);
        $this->pushCallSequence($this->specialEach, [
            $iterOverName,
            $valuePlaceholder,
            $keyPlaceholder,
            $valueToBeFilledByEndingTag]);

        $dropFrameToBeFilled = count($this->parts)-1;
        $this->blockPointers[] = ['each', ['entryCall'=>$entryCall, 'dropFrame'=>$dropFrameToBeFilled] ];
    }

    private function pushCallEndEach() {
        $lastPointer = array_pop($this->blockPointers);
        $shouldBeEach = $lastPointer[0];
        $pointerArgs = $lastPointer[1];
        if ($shouldBeEach === 'each') {
            $this->pushCallSequence($this->specialJump, [$pointerArgs['entryCall']]);
            $this->updatePlaceholder($pointerArgs['dropFrame'], count($this->parts));
            $this->pushCallSequence($this->specialDropFrame);
        }
        else {
            throw new Exception("Expected @endeach to close @each, but found @".$shouldBeEach);
        }
    }

    private function updatePlaceholder($placeholderIndex, $newValue) {
        $currentValue = $this->parts[$placeholderIndex];
        if ($currentValue === $this->specialPlaceholder) {
            $this->parts[$placeholderIndex] = $newValue;
        }
        else {
            throw new Exception("Linking error; expected placeholder at $placeholderIndex, found $currentValue");
        }
    }

    private function pushCallSequence($key, $arguments=[]) {
        $this->parts[] = $key;
        if (count ($arguments)) { 
            $this->parts = array_merge($this->parts,$arguments);
        }
    }

    private function syntaxCheck($expected, $given) {
        if ($expected !== $given) {
            throw new Exception("Expected $expected but got $given");
        }
    }

    private function syntaxCheckIf($condition, $msg) {
        if (!$condition) {
            throw new Exception("Syntax error: $msg");
        }
    }

    private function syntaxCheckIdentifier($value, $msg) {
        if ($value[0] == '@') {
            throw new Exception("Invalid identifier name $value : $msg");
        }
    }

    private function frameLookup($frames, $key) {
        for ($i = count($frames);$i-->0;) {
            $frame = $frames[$i];
            if (array_key_exists($key, $frame)) {
                return $frame[$key];
            }
        }
    }
}