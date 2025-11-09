<?php

namespace Blocs\Compiler\Tag;

trait LabelTrait
{
    private function compileTagLabel($tagName, $compiledTag)
    {
        if (isset($this->optionArray['label'])) {
            if ($tagName === '/option') {
                $this->optionArray['label'] = trim($this->optionArray['label']);
                if (! isset($this->optionArray['value'])) {
                    $this->optionArray['value'] = $this->optionArray['label'];
                }

                $this->option[] = $this->optionArray;
                $this->optionArray = [];
            } else {
                $this->optionArray['label'] .= $compiledTag;
            }
        }

        if (isset($this->labelArray['label'])) {
            if ($tagName === '/label') {
                if (preg_match('/<br>$/si', $this->labelArray['label'])) {
                    $this->labelArray['label'] = substr($this->labelArray['label'], 0, -4);
                }
                if (preg_match('/<br \/>$/si', $this->labelArray['label'])) {
                    $this->labelArray['label'] = substr($this->labelArray['label'], 0, -6);
                }
                $this->labelArray['label'] = trim($this->labelArray['label']);

                if (count($this->labelArray) > 2) {
                    $this->option[] = $this->labelArray;
                } else {
                    array_unshift($this->option, $this->labelArray);
                }

                if (isset($this->labelArray['id']) && strlen($this->labelArray['label'])) {
                    $this->label[$this->labelArray['id']] = $this->labelArray['label'];
                }
                $this->labelArray = [];
            } elseif ($tagName === 'input') {
                // ラベル内に含めない
            } else {
                $this->labelArray['label'] .= $compiledTag;
            }
        }
    }
}
