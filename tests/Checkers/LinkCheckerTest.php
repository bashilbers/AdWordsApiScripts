<?php

namespace AdWordsApiScripts\Tests\Checkers;

class LinkCheckerTest extends \PHPUnit_Framework_TestCase
{
    protected $checker;

    protected $logger;

    protected $adwords;

    public function setup()
    {
        $adwords = $this->getMock('\AdwordsUser', ['getService']);
        $logger = new \Psr\Log\NullLogger;

        $mock = $this->getMockBuilder('\AdWordsApiScripts\Checkers\LinkChecker')
            ->setConstructorArgs([$adwords, $logger])
            ->setMethods(['getOrCreateLabel'])
            ->getMock();

        $this->adwords = $adwords;
        $this->logger = $logger;
        $this->checker = $mock;
    }

    public function testLinkCheckedlabelIsCreated()
    {
        $this->checker->expects($this->once())->method('getOrCreateLabel');

        call_user_func($this->checker);
    }

    /**
     * @assertException
     */
    public function testReeturnIfNoCheckerr()
    {


        $this->checker->setCheckers([]);


    }
}