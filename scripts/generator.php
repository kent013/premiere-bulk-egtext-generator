<?php
require "vendor/autoload.php";

use PremiereUtil\SRTFile;
use PremiereUtil\CaptionTimeArranger;
use PremiereUtil\OutputFileGenerator;
use PremiereUtil\HandCleanedCaptionTextFile;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;

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
    ->defaultValue('resources/template.xml');
$specs->add('ct|caption-template?', 'caption template file name')
    ->isa('String')
    ->defaultValue('resources/caption_template.txt');
$specs->add('o|output?', 'output file name (srt or xml)')
    ->isa('String')
    ->defaultValue('output.xml');

$parser = new OptionParser($specs);

try {
    $options = $parser->parse($argv);

    $srtFile = new SRTFile($options->srtFile);
    $captionFile = new HandCleanedCaptionTextFile($options->file);

    CaptionTimeArranger::arrange($captionFile, $srtFile);
    OutputFileGenerator::generate($options->output, $options->template, $options->captionTemplate, $captionFile);
} catch( Exception $e ) {
    echo $e->getMessage();
    $printer = new ConsoleOptionPrinter();
    echo $printer->render($specs);
}