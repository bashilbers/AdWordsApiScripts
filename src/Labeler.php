<?php

namespace AdWordsApiScripts;

trait Labeler
{
    /**
     * @var LabelService
     */
    protected $labelService;

    public function setLabelService($service)
    {
        $this->labelService = $service;

        return $this;
    }

    /**
     * Makes sure that the given label exists
     *
     * @param string $name
     * @return \TextLabel
     */
    public function getOrCreateLabel($name)
    {
        $selector = new \Selector;
        $selector->fields = ['LabelId'];
        $selector->predicates = [new \Predicate('LabelName', 'EQUALS', $name)];

        $page = $this->labelService->get($selector);

        if (count($page->entries) === 0) {
            //$this->logger->info('creating label "' . $this->labelName . '"');

            $operation = new \LabelOperation;
            $operation->operand = new \TextLabel(null, $name, 'ENABLED');
            $operation->operator = 'ADD';

            $result = $labelService->mutate([$operation]);

            $label = $result->entries[0];
        } else {
            $label = $page->entries[0];
        }

        return $label;
    }
}