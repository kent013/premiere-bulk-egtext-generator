<?php
require "vendor/autoload.php";

use PremiereUtil\SRTFile;
use PremiereUtil\Config;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use BabyMarkt\DeepL\DeepL;

$specs = new OptionCollection;
$specs->add('f|file:', 'srt file')
    ->isa('File');
$specs->add('o|output?', 'output srt file name')
    ->isa('String')
    ->defaultValue('output.srt');

$parser = new OptionParser($specs);

try {
    $options = $parser->parse($argv);

    $srtFile = new SRTFile($options->file);
    $deepl = new DeepL(Config::config()->deeplAPIKey);
    $texts = [];
    foreach($srtFile->getEntries() as $entry){
        $text = $entry->getText();
        $texts[] = preg_replace("/\r?\n/", '', $text);
    }
    $translation = $deepl->translate($texts, "ja", "en");
    foreach ($srtFile->getEntries() as $index => $entry) {
        $entry->setText($translation[$index]['text']);
    }

    $srtFile->save($options->output);
} catch( Exception $e ) {
    echo $e->getMessage() . "\n\n";
    $printer = new ConsoleOptionPrinter();
    echo $printer->render($specs);
}