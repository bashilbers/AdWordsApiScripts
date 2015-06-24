<?php

/**
 * Account Anomaly Detector alerts the advertiser whenever an AdWords account
 * is suddenly behaving too differently from what's historically observed.
 *
 * When an issue is encountered, the script will send the user an alerting
 * email. Only a single email for an alert is sent per day.
 *
 * The script is comparing stats observed so far today with historical stats
 * for the same day of week. For instance, stats for a Tuesday,
 * 13:00 are compared with stats for 26 previous Tuesdays.
 *
 * Adjust the number of weeks to look back depending on the age and
 * stability of your account.
 */

namespace AdWordsApiScripts;

class AnomalyDetector
{
    protected static $DAYS = [
        'Sunday', 'Monday', 'Tuesday',
        'Wednesday', 'Thursday', 'Friday', 'Saturday'
    ];

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
        $currentDate = new \DateTime;
        $now = new \DateTime;
        $now->setTimeStamp($now->getTimestamp() - 3 * 3600 * 1000);
        $adjustedDate = '';
        $weeks = 3;
        
        $hours = $now->format('G');
        if (hours == 0) {
            $hours = 24;
        }

        if ($currentDate != $adjustedDate) {
            $dayToCheck = 1;
        } else {
            $dayToCheck = 0;
        }

        $dateRangeToCheck = $this->getDateInThePast($dayToCheck);
        $dateRangeToEnd = $this->getDateInThePast($dayToCheck + 1);
        $dateRangeToStart = $this->getDateInThePast($dayToCheck + 1 + $weeks * 7);

        $dayFormatted = self::$DAYS[$now->format('w')];

        $fields = 'HourOfDay,DayOfWeek,Clicks,Impressions,Cost';

        // Create report query.
        $todayReportQuery = "
            SELECT {$fields}
            FROM ACCOUNT_PERFORMANCE_REPORT
            WHERE Status IN [ENABLED]
            DURING {$dateRangeToCheck}, {$dateRangeToCheck}";

        $pastReportQuery = "
            SELECT {$fields}
            FROM ACCOUNT_PERFORMANCE_REPORT
            WHERE Status IN [ENABLED]
            AND DayOfWeek={$dayFormatted}
            DURING {$dateRangeToStart}, {$dateRangeToEnd}";

        $todayStats = $this->accumulateRows($today, $hours, 1);
        $pastStats = $this->accumulateRows($past, $hours, $weeks);
    }

    protected function getDateInThePast($numDays)
    {
        $adjusted = new DateTime;
        $adjusted->modify('-' . $numDays . ' days');

        return $adjusted;
    }
}
