<?php

/**
 * The missing keywords tool looks at all the searched keywords
 * that converted and checks if these are added as EXACT in your account.
 *
 * By doing this you can easily get cheap longtail terms, missed misspells,
 * unnecessary paused keywords and regular missed keywords.
 */
 
use Psr\Log\LoggerInterface;

namespace AdWordsApiScripts;

class MissingKeywords
{
    const DEFAULT_DAYS_BACK = 365;
    const DEFAULT_DAYS_END = 1;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \AdwordsUser
     */
    protected $adwords;

    public function __construct(\AdWordsUser $adwords, LoggerInterface $logger)
    {
        $this->adwords = $adwords;
        $this->logger = $logger;
    }

    public function __invoke($daysBack = self::DEFAULT_DAYS_BACK, $daysEnd = self::DEFAULT_DAYS_END)
    {
        $startDate = $this->getDateInThePast($daysBack);
        $endDate = $this->getDateInThePast($daysEnd);
        //$account = AdWordsApp.currentAccount().getName();

        $fields = 'Query, Conversions, Clicks, Impressions, CampaignName, AdGroupName';

        $pastReportQuery = "
            SELECT {$fields}
            FROM SEARCH_QUERY_PERFORMANCE_REPORT
            WHERE Conversions > 0
            DURING {$startDate}, {$endDate}";

        $report;

        $map = $this->getMapOfKeywords();

        $rows = $report->rows();
        while ($rows->hasNext()) {
            $row = rows.next();
            $query = "[" + row['Query'] + "]";

            if(!array_key_exists($query, $map)) {
                // update about missing query
                $this->writer->write();
            }
		}

        $this->writer->finish();
        $this->notifyAboutReport();
    }

    protected function getMapOfKeywords()
    {
        $map = [];
        $service = $this->adwords->getService('AdGroupCriterion');

        $selector = new \Selector;
        $selector->fields = ['KeywordText'];
        $selector->predicates = [
            new \Predicate('CriteriaType', 'EQUALS', 'KEYWORD'),
            new \Predicate('KeywordMatchType', 'EQUALS', 'EXACT'),
            new \Predicate('Status', 'EQUALS', 'ENABLED'),
            new \Predicate('CampaignStatus', 'EQUALS', 'ENABLED'),
            new \Predicate('AdGroupStatus', 'EQUALS', 'ENABLED')
        ];
        $selector->paging = new \Paging(0, \AdWordsConstants::RECOMMENDED_PAGE_SIZE);

        do {
            $page = $service->get($selector);

            if (is_array($page->entries)) {
                foreach ($page->entries as $criterion) {
                    $map[$criterion->criterion->keywordText] = $criterion;
                }
            }

            $selector->paging->startIndex += $selector->paging->numberResults;
        } while (!is_null($page->entries));

        return $map;
    }

    protected function getDateInThePast($numDays)
    {
        $adjusted = new DateTime;
        $adjusted->modify('-' . $numDays . ' days');

        return $adjusted;
    }
}
