<?php
namespace PremiereUtil;

use Exception;
use InvalidArgumentException;
use Youaoi\MeCab\MeCab;

class KatakanaConverter{
    protected static $FILENAME = "resources/bep-eng.dic";
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