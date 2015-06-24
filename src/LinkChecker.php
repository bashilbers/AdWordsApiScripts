<?php

namespace AdWordsApiScripts;

use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as EventLoopFactory;
use WyriHaximus\React\RingPHP\HttpClientAdapter as RingAdapter;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Message\Response;

/**
 * As a website evolves, new pages get added, old pages are taken down, links
 * get broken and fixed. Keeping an AdWords campaign in sync with the website
 * is an ongoing battle for many advertisers.
 *
 * Live advertisements may be pointing to non-existent pages, and the
 * advertiser ends up paying for clicks that yield 404 pages.
 *
 * Link Checker addresses this problem by iterating through all ads and
 * keywords in your account and making sure their URLs do not produce
 * "Page not found" or other types of error responses. Whenever an issue
 * is encountered, Link Checker will send you an email about it.
 *
 * Alternatively, you can opt out for daily summary emails. Link Checker also
 * maintains a spreadsheet tracking all URLs checked
 * so far today and their statuses.
 */

/**
 * The script creates a label "link_checked" in your account and uses it to
 * track the ads and keywords that it already tested so far today.
 * The label gets removed and re-created every day.
 *
 * The script pulls in keywords and ads that a) have a destination URL,
 * and b) were not yet checked today. It then uses a http client to
 * check the URLs, and records the results into a spreadsheet.
 */

class LinkChecker
{
    const ADS = 1;

    protected $reportWriter;

    protected $labelName = 'LINK_CHECKED';

    /**
     * @var \TextLabel
     */
    protected $label;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \AdwordsUser
     */
    protected $adwords;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    protected $checkers = [self::ADS];

    protected $markedAsCheckedCount = 0;

    protected $badUrlCount = 0;

    protected $goodUrlCount = 0;

    public function __construct(\AdWordsUser $adwords, LoggerInterface $logger)
    {
        $this->adwords = $adwords;
        $this->logger = $logger;

        $this->loop = $loop = EventLoopFactory::create();
        $handler = new RingAdapter($loop);

        $this->httpClient = new HttpClient([
            'handler' => $handler
        ]);
    }

    /**
     * Main function
     *
     * @return type
     */
    public function __invoke()
    {
        if (is_null($this->reportWriter)) {
            $this->logger->warn('no report writer specified, proceeding...');
            //$this->reportWriter = new \NoopReportWriter;
        }

        if (empty($this->checkers)) {
            $this->logger->warn('requested no keywords and no ads checking. Exiting.');
            return;
        }

        $this->dealWithFirstRunOfTheDay();

        $this->createLinkCheckerLabel();

        $urlsWithEntity = [];

        foreach ($this->checkers as $check) {
            switch ($check) {
                case self::ADS:
                    $ads = $this->getAds();

                    foreach ($ads as $ad) {
                        $urlsWithEntity[$ad->ad->url] = ['field' => 'url', 'entity' => $ad];
                        $urlsWithEntity[$ad->ad->displayUrl] = ['field' => 'displayUrl', 'entity' => $ad];
                    }
                    break;
            }
        }

        $results = $this->checkUrls($urlsWithEntity);

        foreach ($results as $result) {
            $entity = $result['entity'];
            $entity->labels[] = $this->label;

            $adLabel = new \AdGroupAdLabel;
            $adLabel->adGroupId = $entity->adGroupId;
            $adLabel->adId = $entity->ad->id;
            $adLabel->labelId = $this->label->id;

            $operation = new \AdGroupAdLabelOperation;
            $operation->operand = $adLabel;
            $operation->operator = 'ADD';

            $this->adwords->getService('AdGroupAdService')
                ->mutate([$operation]);
        }

        //$this->reportWriter->finish();

        //$this->notifyAboutReport();

        $this->logger->log('Finished. All done for the day!');
    }

    /**
     * This pulls in keywords that a) have a destination URL,
     * and b) were not yet checked today
     *
     * @param int|null $offset
     * @param int|null $limit
     * @return array
     */
    /*
    protected function getKeywords($offset = 0, $limit = \AdWordsConstants::RECOMMENDED_PAGE_SIZE)
    {
        $criterionService = $this->adwords->getService('AdGroupCriterionService');

        $selector = new \Selector;
        $selector->fields = [
            'Id', 'KeywordMatchType', 'KeywordText', 'DestinationUrl',
            'FinalUrls', 'FinalMobileUrls'
        ];
        $selector->predicates = [
            new \Predicate('CriteriaType', 'EQUALS', 'KEYWORD'),
            new \Predicate('Status', 'EQUALS', 'ENABLED'),
            new \Predicate('DestinationUrl', 'STARTS_WITH_IGNORE_CASE', 'http'),
            new \Predicate('Labels', 'CONTAINS_NONE', [$this->labelName])
        ];
        $selector->ordering = new \OrderBy('DestinationUrl', 'ASCENDING');
        $selector->paging = new \Paging($offset, $limit);

        $page = $criterionService->get($selector);

        if (is_null($page->entries)) {
            return null;
        } else {
            return $page->entries;
        }
    }
     */

    protected function getAds($offset = 0, $limit = \AdWordsConstants::RECOMMENDED_PAGE_SIZE)
    {
        $criterionService = $this->adwords->getService('AdGroupAdService');

        $selector = new \Selector;
        $selector->fields = [
            'Id', 'CreativeFinalMobileUrls', 'CreativeFinalUrls',
            'DisplayUrl', 'Url', 'Status', 'Labels', 'AdGroupId'
        ];
        $selector->predicates = [
            new \Predicate('Status', 'EQUALS', 'ENABLED'),
            new \Predicate('Url', 'STARTS_WITH_IGNORE_CASE', 'http'),
            //new \Predicate('Labels', 'CONTAINS_NONE', '[\'', $this->labelName . '\']')
        ];
        $selector->ordering = new \OrderBy('Url', 'ASCENDING');
        $selector->paging = new \Paging($offset, $limit);

        $page = $criterionService->get($selector);

        if (is_null($page->entries)) {
            return null;
        } else {
            return $page->entries;
        }
    }

    protected function checkUrls(array $urlCombinations)
    {
        $results = [];

        foreach($urlCombinations as $url => $urlMeta) {
            $this->httpClient->get($url, [
                'future' => true
            ])->then(function (Response $response) use ($url, &$results, $urlMeta) {
                $okidoki = $response->getStatusCode() < 300;

                $results[$url] = array_merge($urlMeta, [
                    'result' => $okidoki
                ]);

                if ($okidoki) {
                    $this->goodUrlCount++;
                } else {
                    $this->badUrlCount++;
                }
            });
        }

        $this->loop->run();

        return $results;
    }

    protected function notifyAboutReport()
    {

    }

    protected function dealWithFirstRunOfTheDay()
    {
        $date = new \DateTime;


    }

    /**
     * Make sure that we have a "link has been checked" label
     */
    protected function createLinkCheckerLabel()
    {
        $labelService = $this->adwords->getService('LabelService');

        $selector = new \Selector;
        $selector->fields = ['LabelId'];
        $selector->predicates = [new \Predicate('LabelName', 'EQUALS', $this->labelName)];

        $page = $labelService->get($selector);

        if (count($page->entries) === 0) {
            $this->logger->info('creating label "' . $this->labelName . '"');

            $label = new \TextLabel(null, $this->labelName, 'ENABLED');
            $operation = new \LabelOperation;
            $operation->operand = $label;
            $operation->operator = 'ADD';

            $result = $labelService->mutate([$operation]);

            // get label
            // store in $this->label
        } else {
            $this->label = $page->entries[0];
        }
    }
}
