<?php
namespace PremiereUtil;
use Exception;

class SRTFileEntry
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