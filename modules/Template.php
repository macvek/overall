<?php

class Template {
    private $specialInline = '=';
    private $specialInsert = '!';
    private $inputPattern;
    private $parts = [];
    public function __construct($inputPattern) {
        $this->inputPattern = $inputPattern;
    }

    public function transform($valueMap) {
        if (count ($this->parts) == 0) {
            $this->initialize();
        }
        $output = [];
        for ($cmdIndex = 0; $cmdIndex<count($this->parts); $cmdIndex+=2) {
            $cmd = $this->parts[$cmdIndex];
            $arg = $this->parts[$cmdIndex+1];
            if ($cmd == $this->specialInline) {
                $output[] = $arg;
            }
            else if ($cmd == $this->specialInsert) {
                $lookup = $valueMap[$arg];
                if ($lookup) {
                    $output[] = $lookup;
                }
                else {
                    $output[] = '@NOT FOUND '.$arg;
                }
            }
        }

        return implode('',$output);
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
                            $this->parseBlock($blockValue);
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

    private function parseBlock($block) {
        $this->parts[] = $this->specialInsert;
        $this->parts[] = trim($block);
    }

}