<?php
namespace PremiereUtil;
class HandCleanedCaptionTextFileEntry {
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
