<?php
namespace PremiereUtil;

class SRTFileGenerator {
    public static function generate($outputFilename, $fileTemplateFilename, $captionTemplateFilename, $captionFile){
        $output = [];
        $captionTemplate = file_get_contents($captionTemplateFilename);
        foreach ($captionFile->getEntries() as $index => $caption) {
            $block = $index + 1 . "\n";
            $block .= self::floatToTimeCode($caption->getStartTimeInSecond()) . " --> " . self::floatToTimeCode($caption->getEndTimeInSecond()) . "\n";
            $block .= trim($caption->getText()) . "\n";
            $output[] = $block;
        }
        $output = trim(implode("\n", $output));
        file_put_contents($outputFilename, $output);
    }
    protected static function floatToTimeCode(float $time){
        $hour = (int)($time / 3600);
        $minute = (int)(($time % 3600) / 60);
        $second = (int)($time % 60);
        $milisecond = ($time - $hour * 3600 - $minute * 60 - $second) * 1000;
        return sprintf("%02d:%02d:%02d,%03d", $hour, $minute, $second, $milisecond);
    }
}
