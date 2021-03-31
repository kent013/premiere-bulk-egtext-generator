<?php
require "vendor/autoload.php";

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use Youaoi\MeCab\MeCab;

//var_dump(KatakanaConverter::convert("あいうphilo13sophyバーッ"));exit;
define("DEBUG", 1);

$specs = new OptionCollection;
$specs->add('f|file:', 'file includes captions line by line' )
    ->isa('File');
$specs->add('s|srt-file:', 'srt file')
    ->isa('File');
$specs->add('d|duration:', 'duration in sec of sequence')
    ->isa('Number');
$specs->add('n|name?', 'name of the sequence')
    ->isa('String')
    ->defaultValue('captions');
$specs->add('t|template?', 'template file name')
    ->isa('String')
    ->defaultValue('template.xml');
$specs->add('ct|caption-template?', 'caption template file name')
    ->isa('String')
    ->defaultValue('caption_template.txt');
$specs->add('o|output?', 'output file name (srt or xml)')
    ->isa('String')
    ->defaultValue('output.xml');

$parser = new OptionParser($specs);

try {
    $result = $parser->parse($argv);

    $srtFile = new SRTFile($result->srtFile);
    $captionFile = new HandCleanedCaptionTextFile($result->file);

    CaptionTimeArranger::arrange($captionFile, $srtFile);
    OutputFileGenerator::generate($result->output, $result->template, $result->captionTemplate, $captionFile);
} catch( Exception $e ) {
    echo $e->getMessage();
    $printer = new ConsoleOptionPrinter();
    echo $printer->render($specs);
}

class OutputFileGenerator
{
    public static function generate($outputFilename, $fileTemplateFilename, $captionTemplateFilename, $captionFile)
    {
        if (preg_match('/\.xml$/', $outputFilename)) {
            FinalCutProCaptionXmlGenerator::generate($outputFilename, $fileTemplateFilename, $captionTemplateFilename, $captionFile);
        } elseif (preg_match('/\.srt$/', $outputFilename)) {
            SRTFileGenerator::generate($outputFilename, $fileTemplateFilename, $captionTemplateFilename, $captionFile);
        } else {
            throw new Exception('Generator is not found.');
        }
    }
}

class FinalCutProCaptionXmlGenerator{
    protected static string $STYLE_START_MARKER = "Zg8AAAAAAAB7ACIAbQBTAGgAYQBkAG8";
    public static function generate($outputFilename, $fileTemplateFilename, $captionTemplateFilename, $captionFile){
        $styleStartByte = mb_substr(base64_decode(self::$STYLE_START_MARKER), 0, 8);

        $captionTemplate = file_get_contents($captionTemplateFilename);
        $template = new DOMDocument();
        $template->load($fileTemplateFilename);
        $xpath = new DOMXPath($template);
        $fps = (float)$xpath->query('//sequence/rate/timebase')->item(0)->nodeValue;

        $track = $xpath->query('//sequence/media/video/track[2]')->item(0);
        foreach ($captionFile->getEntries() as $index => $caption) {
            if ($index > 1) {
                $clipitem = $xpath->query('//sequence/media/video/track[2]/clipitem[2]')->item(0)->cloneNode(true);
                $clipitem->setAttribute('id', "clipitem-" . ($index + 1));
                $track->appendChild($clipitem);
            }
            $clipitemPath = '//sequence/media/video/track[2]/clipitem[' . ($index + 1) .']';
            $xpath->query("$clipitemPath/in")->item(0)->nodeValue = $caption->getStartTimeInSecond() * $fps;
            $xpath->query("$clipitemPath/out")->item(0)->nodeValue = $caption->getEndTimeInSecond() * $fps;
            $xpath->query("$clipitemPath/start")->item(0)->nodeValue = $caption->getStartTimeInSecond() * $fps;
            $xpath->query("$clipitemPath/end")->item(0)->nodeValue = $caption->getEndTimeInSecond() * $fps;
            $xpath->query("$clipitemPath/filter[1]/effect/name")->item(0)->nodeValue = $caption->getText();
            $xpath->query("$clipitemPath/filter[1]/effect/parameter[1]/value")->item(0)->nodeValue = base64_encode($styleStartByte . mb_convert_encoding(sprintf($captionTemplate, $caption->getText()), 'UTF-16LE'));
        }
        $template->save($outputFilename);
    }
}

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
        return sprintf("%02d:%02d:%02d:%03d", $hour, $minute, $second, $milisecond);
    }
}

class CaptionTimeArranger{
    public static function arrange(HandCleanedCaptionTextFile $captionFile, SRTFile $srtFile){
        $cursor = 0;
        $lastCaption = null;
        foreach ($captionFile->getEntries() as $caption) {
            if(is_null($srtFile->getIndexOfTimecodedCharacters($cursor))){
                continue;
            }
            $caption->setStartTimeInSecond($srtFile->getIndexOfTimecodedCharacters($cursor)->getTimeInSecond());
            $katakanaCaption = $caption->getKatakanaText();
            $length = mb_strlen($katakanaCaption);
            $length_min = (int)($length / 2);
            $length_max = (int)($length * 2);
            $adjust_min = (int)($length / 2);
            $adjust_max = (int)($length * 2);
            $matched = "";
            $highestScore = 0;
            $highestAdjust = 0;

            for ($count = $length_min; $count < $length_max; $count++) {
                for ($adjust = -$adjust_min; $adjust < $adjust_max; $adjust++) {
                    if($cursor + $adjust < 0){
                        continue;
                    }
                    $target = $srtFile->getSubCharactersAsText($cursor + $adjust, $count);
                    $score = 0.0;

                    similar_text($katakanaCaption, $target, $score);
                    if ($score > $highestScore) {
                        $matched = $target;
                        $highestScore = $score;
                        $highestAdjust = $adjust;
                    }
                }
            }
            $cursor += mb_strlen($matched) + $highestAdjust;
            if (is_null($srtFile->getIndexOfTimecodedCharacters($cursor + 1))) {
                $caption->setEndTimeInSecond($srtFile->getLastTimecodedCharacter()->getTimeInSecond());
            } else {
                $caption->setEndTimeInSecond($srtFile->getIndexOfTimecodedCharacters($cursor + 1)->getTimeInSecond());
            }
            if (is_null($lastCaption) === false) {
                $caption->setStartTimeInSecond($lastCaption->getEndTimeInSecond());
            }
            $lastCaption = $caption;
            if (DEBUG) {
                var_dump($katakanaCaption, $matched, $caption);
            }
        }
    }
}

class HandCleanedCaptionTextFile{
    protected string $filename;
    protected array $entries;

    public function __construct(string $filename){
        $this->filename = $filename;
        $this->entries = [];
        $captions = file($this->filename);
        foreach($captions as $caption){
            $caption = trim($caption);
            if (empty($caption) === false) {
                $this->entries[] = new HandCleanedCaptionEntry($caption);
            }
        }
    }

    /**
     * Get the value of entries
     */ 
    public function getEntries()
    {
        return $this->entries;
    }
}

class HandCleanedCaptionEntry {
    protected string $text;
    protected float $startTimeInSecond = 0.0;
    protected float $endTimeInSecond = 0.0;

    public function __construct(string $text){
        $this->text = $text;
    }
    /**
     * Get the value of text
     */
    public function getText():string
    {
        return $this->text;
    }

    /**
     * Get the value of text
     */
    public function getKatakanaText():string
    {
        return KatakanaConverter::convert($this->text);
    }

    /**
     * Get the value of startTimeInSecond
     */
    public function getStartTimeInSecond():float
    {
        return $this->startTimeInSecond;
    }

    /**
     * Get the value of endTimeInSecond
     */
    public function getEndTimeInSecond():float
    {
        return $this->endTimeInSecond;
    }

    /**
     * Set the value of startTimeInSecond
     *
     * @return  self
     */ 
    public function setStartTimeInSecond(float $startTimeInSecond): self
    {
        $this->startTimeInSecond = $startTimeInSecond;

        return $this;
    }

    /**
     * Set the value of endTimeInSecond
     *
     * @return  self
     */ 
    public function setEndTimeInSecond(float $endTimeInSecond): self
    {
        $this->endTimeInSecond = $endTimeInSecond;

        return $this;
    }
}

class SRTFile
{
    protected array $entries = [];
    protected string $filename;
    protected string $content;
    protected array $timecodedCharacters;

    public function __construct($filename)
    {
        $this->filename = $filename;

        if (file_exists($this->filename) === false) {
            throw new Exception('File not found ' . $this->filename);
        }
        $this->content = file_get_contents($this->filename);

        if (preg_match_all('/^([0-9]+)\r?\n([0-9]{2}:[0-9]{2}:[0-9]{2},[0-9]{1,3}) +--> +([0-9]{2}:[0-9]{2}:[0-9]{2},[0-9]{1,3})\r?\n([^\n]+)\r?\n\r?\n/usm', $this->content, $regs) === false) {
            throw new Exception('Invalid srt file format ' . $this->filename);
        }

        for ($index = 0; $index < count($regs[1]); $index++) {
            if (empty($regs[4][$index])) {
                continue;
            }
            $entry = new SRTEntry($regs[1][$index], $regs[2][$index], $regs[3][$index], trim($regs[4][$index]));
            $this->entries[] = $entry;
        }
        $this->timecodedCharacters = [];
        foreach ($this->getEntries() as $entry) {
            $this->timecodedCharacters = array_merge($this->timecodedCharacters, $entry->getTimecodedCharacters());
        }
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getTimecodedCharacters(): array
    {
        return $this->timecodedCharacters;
    }
    /**
     * Get the value index of timecodedCharacters
     */
    public function getIndexOfTimecodedCharacters(int $index): ?TimecodedCharacter
    {
        if (isset($this->timecodedCharacters[$index]) == false) {
            return null;
        }
        return $this->timecodedCharacters[$index];
    }

    public function getLastTimecodedCharacter(): TimecodedCharacter{
        $lastIndex = array_key_last($this->getTimecodedCharacters());
        return $this->getIndexOfTimecodedCharacters($lastIndex);
    }

    public function getSubCharactersAsText($start, $length): string
    {
        $text = "";
        for ($i = 0; $i < $length; $i++) {
            if (is_null($this->getIndexOfTimecodedCharacters($start + $i))) {
                continue;
            }
            $text .= $this->getIndexOfTimecodedCharacters($start + $i)->getCharacter();
        }
        return $text;
    }
}

class SRTEntry
{
    protected string $text;
    protected string $startTime;
    protected float $startTimeInSecond;
    protected string $endTime;
    protected float $endTimeInSecond;
    protected int $index;
    protected array $timecodedCharacters;

    public function __construct(int $index, string $startTime, string $endTime, string $text)
    {
        $this->index = $index;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->text = $text;
        
        $this->startTimeInSecond = $this->parseTime($this->startTime);
        $this->endTimeInSecond = $this->parseTime($this->endTime);

        $katakanaText = $this->getKatakanaText();
        $interval = ($this->getEndTimeInSecond() - $this->getStartTimeInSecond()) / (float)mb_strlen($katakanaText);
        $chars = preg_split('//u', $katakanaText, -1, PREG_SPLIT_NO_EMPTY);
        $this->timecodedCharacters = [];
        foreach ($chars as $index => $char) {
            $this->timecodedCharacters[] = new TimecodedCharacter($char, $this->getStartTimeInSecond() + $interval * $index);
        }
    }

    protected function parseTime(string $timeText): float
    {
        if (preg_match('/^([0-9]{2}):([0-9]{2}):([0-9]{2}),([0-9]{1,3})$/', $timeText, $regs) === false) {
            throw new Exception('Invalid time text passed ' . $timeText);
        }
        return (float)$regs[1] * 3600.0 + (float)$regs[2] * 60.0 + (float)$regs[3] + (float)$regs[4] / 1000.0;
    }

    /**
     * Get the value of text
     */
    public function getText():string
    {
        return $this->text;
    }

    /**
     * Get the value of text
     */
    public function getKatakanaText():string
    {
        return KatakanaConverter::convert($this->text);
    }

    /**
     * Get the value of startTime
     */
    public function getStartTime():string
    {
        return $this->startTime;
    }

    /**
     * Get the value of startTimeInSecond
     */
    public function getStartTimeInSecond():float
    {
        return $this->startTimeInSecond;
    }

    /**
     * Get the value of endTime
     */
    public function getEndTime():string
    {
        return $this->endTime;
    }

    /**
     * Get the value of endTimeInSecond
     */
    public function getEndTimeInSecond():float
    {
        return $this->endTimeInSecond;
    }

    /**
     * Get the value of index
     */
    public function getIndex():int
    {
        return $this->index;
    }

    /**
     * Get the value of timecodedCharacters
     */
    public function getTimecodedCharacters():array
    {
        return $this->timecodedCharacters;
    }
}


class TimecodedCharacter{
    protected string $character;
    protected float $timeInSecond;

    public function __construct(string $character, float $timeInSecond){
        $this->character = $character;
        $this->timeInSecond = $timeInSecond;
    }

    /**
     * Get the value of character
     */ 
    public function getCharacter()
    {
        return $this->character;
    }

    /**
     * Get the value of timeInSecond
     */ 
    public function getTimeInSecond()
    {
        return $this->timeInSecond;
    }
}

class KatakanaConverter{
    protected static $FILENAME = "./bep-eng.dic";
    protected static $dictionary = null;
    protected static $keys = null;
    protected static $similarDictionary = null;

    protected static $segmentNames = [
        '', 'マン', 'オク', 'チョウ', 'ケイ', 'ガイ'
      ];
    protected static $levelNames = ['', 'ジュウ', 'ヒャク', 'セン'];
    protected static $numberNames = ['イチ', 'ニ', 'サン', 'ヨン', 'ゴ', 'ロク', 'ナナ', 'ハチ', 'キュウ'];

    public static function convert($text){
        if(is_null(self::$dictionary)){
            self::load();
        }
        $katakanaText = Mecab::toSortText($text);
        $katakanaText = preg_replace_callback('/([a-z]+)/i', function($matches){
            return self::find($matches[1]);
        }, $katakanaText);
        $katakanaText = preg_replace_callback('/([0-9]+)/i', function($matches){
            return self::japaneseNumberReadableFormat($matches[1]);
        }, $katakanaText);
        return preg_replace('/[ ・　、。「」？！.,]/u', '', $katakanaText);
    }

    public static function find($word){
        $word = strtoupper($word);
        if(isset(self::$dictionary[$word])){
            return self::$dictionary[$word];
        }
        if(isset(self::$similarDictionary[$word])){
            return self::$dictionary[self::$similarDictionary[$word]];
        }

        $found = null;
        $high_score = 0;
        foreach(self::$keys as $english){
            $score = similar_text($english, $word);
            if($high_score < $score){
                $high_score = $score;
                $found = $english;
            }
        }
        self::$similarDictionary[$word] = $found;
        return self::$dictionary[$found];
    }

    protected static function load(){
        self::$dictionary = [];
        self::$keys = [];
        self::$similarDictionary = [];
        if(file_exists(self::$FILENAME) == false){
            throw new Exception('BEP file ' . self::$FILENAME  . ' is not found');
        }
        $handle = fopen(self::$FILENAME, 'r');
        if (!$handle){
            throw new Exception('BEP file ' . self::$FILENAME  . ' is not readable');
        }
        while (($line = fgets($handle)) !== false) {
            if($line[0] == '#'){
                continue;
            }
            $data = preg_split('/[ \t]+/', trim($line));
            self::$dictionary[$data[0]] = $data[1];
        }
    
        fclose($handle);
        self::$keys = array_keys(self::$dictionary);
    }

    /**
     * https://qiita.com/mpyw/items/e3e18954159ef79aa577
     */
    public static function japaneseNumberReadableFormat($amount) {
        if (!(is_int($amount) && $amount >= 0) && !ctype_digit($amount)) {
            throw new InvalidArgumentException('正整数か正整数形式文字列で渡してください');
        }
        $results = [];
        $segments = array_filter(str_split(strrev($amount), 4), 'intval');
        foreach ($segments as $i => $segment) {
            $result = '';
            $numbers = array_filter(str_split($segment));
            foreach ($numbers as $j => $number) {
                $result = 
                    ($j !== 0 && $number === '1'
                        ? ''
                        : self::$numberNames[$number - 1])
                    . self::$levelNames[$j] . $result
                ;
            }
            if (!isset(self::$segmentNames[$i])) {
                return 'ムリョウタイスウ';
            }
            $result .= self::$segmentNames[$i];
            $results[] = $result;
        }
        return $results ? implode(array_reverse($results)) : 'ゼロ';
    }
}