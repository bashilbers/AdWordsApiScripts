<?php

/**
 * AdWords scripts can easily access statistics over multiple date ranges,
 * and can therefore compare performance of campaigns through time.
 *
 * Declining Ad Groups Report fetches ad groups whose performance
 * we consider to be worsening:
 *
 * - The ad group is ENABLED and belongs to an ENABLED campaign,
 * which means it’s serving.Ad groups'
 *
 * - Click Through Rate has been decreasing for three consecutive weeks.
 * Obviously, a more sophisticated measure of "worsening" may be developed.
 */

namespace AdWordsApiScripts;

class DecliningAdGroups
{
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

    public function __invoke()
    {
        $today = $this->getDateInThePast(0);
        $oneWeekAgo = $this->getDateInThePast(7);
        $twoWeeksAgo = $this->getDateInThePast(14);
        $threeWeeksAgo = $this->getDateInThePast(21);

        $service = $this->adwords->getService('AdGroupService');

        $selector = new \Selector;
        $selector->predicates = [
            new \Predicate('Status', 'EQUALS', 'ENABLED'),
            new \Predicate('CampaignStatus', 'EQUALS', 'ENABLED')
        ];
        $selector->orderBy = new \OrderBy('ctr', 'ASCENDING');
        $selector->paging = new \Paging(0, \AdWordsConstants::RECOMMENDED_PAGE_SIZE);

        do {
            $page = $service->get($selector);

            if (is_array($page->entries)) {
                foreach ($page->entries as $adGroup) {
                    // Let's look at the trend of the ad group's CTR.
                    $statsThreeWeeksAgo = $this->getStatsFor($adGroup, $threeWeeksAgo, $twoWeeksAgo);
                    $statsTwoWeeksAgo = $this->getStatsFor($adGroup, $twoWeeksAgo, $oneWeekAgo);
                    $statsLastWeek = $this->getStatsFor($adGroup, $oneWeekAgo, $today);

                    // Week over week, the ad group is declining - record that!
                    if ($statsLastWeek->getCtr() < $statsTwoWeeksAgo->getCtr() &&
                        $statsTwoWeeksAgo->getCtr() < $statsThreeWeeksAgo->getCtr()) {
                      reportRows.push([adGroup.getCampaign().getName(), adGroup.getName(),
                          statsLastWeek.getCtr() * 100, statsLastWeek.getCost(),
                          statsTwoWeeksAgo.getCtr() * 100, statsTwoWeeksAgo.getCost(),
                          statsThreeWeeksAgo.getCtr() * 100, statsThreeWeeksAgo.getCost()]);
                    }
                }
            }

            $selector->paging->startIndex += $selector->paging->numberResults;
        } while (!is_null($page->entries));
    }

    protected function getStatsFor(\AdGroup $adGroup, $start, $end)
    {

    }

    protected function getDateInThePast($numDays)
    {
        $adjusted = new DateTime;
        $adjusted->modify('-' . $numDays . ' days');

        return $adjusted;
    }
}
