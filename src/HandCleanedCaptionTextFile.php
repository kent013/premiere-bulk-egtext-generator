<?php
namespace PremiereUtil;
class HandCleanedCaptionTextFile{
    protected string $filename;
    protected array $entries;

    public function __construct(string $filename){
        $this->filename = $filename;
        $this->entries = [];
        $captions = file($this->filename);
        foreach($captions as $caption){
            $caption = trim($caption);
            if (empty($caption) === false) {
                $this->entries[] = new HandCleanedCaptionTextFileEntry($caption);
            }
        }
    }

    /**
     * Get the value of entries
     */ 
    public function getEntries()
    {
        return $this->entries;
    }
}

