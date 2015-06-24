<?php

/**
 * this script tracks keyword quality scores over time. When run, the script
 * goes through all keywords you’ve marked with a certain label and checks
 * their quality scores. Changes are logged and can be emailed to you.
 * The script also writes all values into a spreadsheet, so that over
 * time you’ll get a complete history.
 * 
 * The recommended use is to run the script regularly for your top keywords
 * to see changes over time. You can use it for a quick diagnosis,
 * but you can also use the history in the spreadsheet
 * for an in depth analysis later.
 */

use Psr\Log\LoggerInterface;

namespace AdWordsApiScripts;

class TrackQualityScore
{
    protected $labelName = 'TRACK_QS';

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

    /**
     * Make sure that we have a label
     */
    protected function createLabel()
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

    public function __invoke()
    {
        $this->createLabel();

        $alert_text = [];
        $history = [];
        $currentTime = new \DateTime();
        $today = ($currentTime->format('m') + 1) + "/" + $currentTime.getDate() + "/" + $currentTime->format('Y');
        $keywordIterator;
        $line_counter = 0;

        while ($keywordIterator->hasNext()) {
            $keyword = keywordIterator.next();
            $line_counter++;
            $current_quality_score = $keyword->qualityScore;
            $keywordLabelsIterator = keyword.labels().withCondition("Name STARTS_WITH 'QS: '").get();

            if($keywordLabelsIterator->hasNext()){
                $keyword_label = $keywordLabelsIterator.next();
                $matches = new RegExp('QS: ([0-9]+)$').exec($keyword_label.getName());
                $old_quality_score = $matches[1];
            }else{
                $old_quality_score = 0;
            }

            // For the history also note the change or whether this keyword is new
            if($old_quality_score > 0) {
                $change = $current_quality_score - $old_quality_score;
            } else {
                $change = "NEW";
            }

            $row = [$today, $keyword.getCampaign().getName(), $keyword.getAdGroup().getName(), $keyword.getText(), $current_quality_score, $change];
            $history.push(row);

            // If there is a previously tracked quality score and it's different from the current one...
            if($old_quality_score > 0 && $current_quality_score != $old_quality_score) {
                // Make a note of this to log it and possibly send it via email later
                $alert_text.push($current_quality_score + "\t" + $old_quality_score + "\t" + $change + "\t" + $keyword.getText());

                // Remove the old label
                $keyword.removeLabel($keyword_label.getName());
            }

            // Store the current QS for the next time by using a label
            $keyword.applyLabel("QS: " + $current_quality_score);
        }

        if($line_counter == 0){
            $this->logger->log("Couldn't find any keywords marked for quality score tracking. To mark keywords for tracking, apply the label '" + $label_name + "' to those keywords.");
            return;
        }

        $this->logger->log("Tracked " + $line_counter + " keyword quality scores. To select different keywords for tracking, apply the label '" + $label_name + "' to those keywords.");

        // Store history
        $history_sheet = spreadsheet.getSheetByName('QS history');
        $history_sheet.getRange($history_sheet.getLastRow()+1, 1, $history.length, 6).setValues($history);

        // If there are notes for alerts then prepare a message to log and possibly send via email
        if($alert_text.length) {
            $message = "The following quality score changes were discovered:\nNew\tOld\tChange\tKeyword\n";
            for ($i = 0; $i < count($alert_text); $i++){
                $message += $alert_text[i] + "\n";
            }

            // Also include a link to the spreadsheet
            $message += "\n"
              + "The complete history is available at "
              + $spreadsheet.getUrl();

            $this->logger->log($message);

            // If there is an email address send out a notification
            if($email_address && $email_address != "YOUR_EMAIL_HERE"){
                $this->mailer->sendEmail($email_address, "Quality Score Tracker: Changes detected", $message);
            }
        }
    }

    protected function getDateInThePast($numDays)
    {
        $adjusted = new DateTime;
        $adjusted->modify('-' . $numDays . ' days');

        return $adjusted;
    }
}
