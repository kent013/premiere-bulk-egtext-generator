<?php
namespace PremiereUtil;
use DOMDocument;
use DOMXPath;
  
class FinalCutProCaptionXmlGenerator
{
    protected static string $STYLE_START_MARKER = "Zg8AAAAAAAB7ACIAbQBTAGgAYQBkAG8";
    public static function generate($outputFilename, $fileTemplateFilename, $captionTemplateFilename, $captionFile)
    {
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
