<?php

namespace AdWordsApiScripts;

interface LabelCreator
{
    public function getOrCreateLabel($name);
}