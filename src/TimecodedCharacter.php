<?php
namespace PremiereUtil;

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
