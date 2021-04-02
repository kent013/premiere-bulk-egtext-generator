<?php
namespace PremiereUtil;

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
                $caption->setStartTimeInSecond($lastCaption?->getEndTimeInSecond());
            }
            $lastCaption = $caption;
            if (Config::config()->debug) {
                var_dump($katakanaCaption, $matched, $caption);
            }
        }
    }
}
