<?php
namespace PremiereUtil;
use Exception;

class SRTFile
{
    protected array $entries = [];
    protected string $filename;
    protected string $content;
    protected array $timecodedCharacters;

    public function __construct($filename)
    {
        $this->filename = $filename;

        if (file_exists($this->filename) === false) {
            throw new Exception('File not found ' . $this->filename);
        }
        $this->content = file_get_contents($this->filename);

        if (preg_match_all('/^([0-9]+)\r?\n([0-9]{2}:[0-9]{2}:[0-9]{2},[0-9]{1,3}) +--> +([0-9]{2}:[0-9]{2}:[0-9]{2},[0-9]{1,3})\r?\n([^\n]+)\r?\n\r?\n/usm', $this->content, $regs) === false) {
            throw new Exception('Invalid srt file format ' . $this->filename);
        }

        for ($index = 0; $index < count($regs[1]); $index++) {
            if (empty($regs[4][$index])) {
                continue;
            }
            $entry = new SRTFileEntry($regs[1][$index], $regs[2][$index], $regs[3][$index], trim($regs[4][$index]));
            $this->entries[] = $entry;
        }
        $this->timecodedCharacters = [];
        foreach ($this->getEntries() as $entry) {
            $this->timecodedCharacters = array_merge($this->timecodedCharacters, $entry->getTimecodedCharacters());
        }
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getTimecodedCharacters(): array
    {
        return $this->timecodedCharacters;
    }
    /**
     * Get the value index of timecodedCharacters
     */
    public function getIndexOfTimecodedCharacters(int $index): ?TimecodedCharacter
    {
        if (isset($this->timecodedCharacters[$index]) == false) {
            return null;
        }
        return $this->timecodedCharacters[$index];
    }

    public function getLastTimecodedCharacter(): TimecodedCharacter{
        $lastIndex = array_key_last($this->getTimecodedCharacters());
        return $this->getIndexOfTimecodedCharacters($lastIndex);
    }

    public function getSubCharactersAsText($start, $length): string
    {
        $text = "";
        for ($i = 0; $i < $length; $i++) {
            if (is_null($this->getIndexOfTimecodedCharacters($start + $i))) {
                continue;
            }
            $text .= $this->getIndexOfTimecodedCharacters($start + $i)->getCharacter();
        }
        return $text;
    }
}
