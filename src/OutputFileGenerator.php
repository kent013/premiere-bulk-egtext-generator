<?php
namespace PremiereUtil;
use Exception;

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
