<?php
require "vendor/autoload.php";

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;

$specs = new OptionCollection;
$specs->add('f|file:', 'file includes captions line by line' )
    ->isa('File');
$specs->add('d|duration:', 'duration of sequence')
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
$specs->add('o|output?', 'output file name')
    ->isa('String')
    ->defaultValue('output.xml');

$parser = new OptionParser($specs);

$str = "Zg8AAAAAAAB7ACIAbQBTAGgAYQBkAG8";
$decoded = base64_decode($str);
$first = mb_substr($decoded, 0, 8);
try {
    $result = $parser->parse($argv);
    $captionTemplate = file_get_contents($result->captionTemplate);
    $template = new DOMDocument();
    $template->load($result->template);
    $xpath = new DOMXPath($template);
    $xpath->query('//sequence/name')->item(0)->nodeValue = $result->name;
    $captions = file($result->file);
    $interval = (int)($result->duration / count($captions));

    $track = $xpath->query('//sequence/media/video/track[2]')->item(0);
    foreach($captions as $index => $caption){
        if($index > 1){
            $clipitem = $xpath->query('//sequence/media/video/track[2]/clipitem[2]')->item(0)->cloneNode(true);
            $clipitem->setAttribute('id', "clipitem-" . ($index + 1));
            $track->appendChild($clipitem);
        }
        $clipitemPath = '//sequence/media/video/track[2]/clipitem[' . ($index + 1) .']';
        $xpath->query("$clipitemPath/in")->item(0)->nodeValue = $interval * $index;
        $xpath->query("$clipitemPath/out")->item(0)->nodeValue = $interval * ($index + 1);
        $xpath->query("$clipitemPath/start")->item(0)->nodeValue = $interval * $index;
        $xpath->query("$clipitemPath/end")->item(0)->nodeValue = $interval * ($index + 1);
        $xpath->query("$clipitemPath/filter[1]/effect/name")->item(0)->nodeValue = trim($caption);
        $xpath->query("$clipitemPath/filter[1]/effect/parameter[1]/value")->item(0)->nodeValue = base64_encode($first . mb_convert_encoding(sprintf($captionTemplate, trim($caption)), 'UTF-16LE'));
    }
    $template->save($result->output);
        
} catch( Exception $e ) {
    echo $e->getMessage();
    $printer = new ConsoleOptionPrinter();
    echo $printer->render($specs);
    
}