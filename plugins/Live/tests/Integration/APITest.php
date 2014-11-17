<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Live\tests\Integration;

use Piwik\Date;
use Piwik\Db;
use Piwik\Plugins\Goals\API as GoalsApi;
use Piwik\Plugins\Live\API;
use Piwik\Access;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

/**
 * @group Live
 * @group APITest
 * @group Plugins
 */
class APITest extends SystemTestCase
{
    /**
     * @var API
     */
    private $api;
    private $idSite = 1;

    public function setUp()
    {
        parent::setUp();

        $this->api = API::getInstance();
        $this->setSuperUser();
        $this->createSite();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasViewAccess Fake exception
     */
    public function test_GetCounters_ShouldFail_IfUserHasNoPermission()
    {
        $this->setAnonymous();
        $this->api->getCounters($this->idSite, 5);
    }

    public function test_GetCounters_ShouldReturnZeroForAllCounters_IfThereAreNoVisitsEtc()
    {
        $counters = $this->api->getCounters($this->idSite, 5);

        $this->assertEquals($this->buildCounter(0, 0, 0, 0), $counters);
    }

    public function test_GetCounters_ShouldOnlyReturnResultsOfLastMinutes()
    {
        $this->trackSomeVisits();

        $counters = $this->api->getCounters($this->idSite, 5);
        $this->assertEquals($this->buildCounter(19, 32, 16, 16), $counters);

        $counters = $this->api->getCounters($this->idSite, 20);
        $this->assertEquals($this->buildCounter(24, 60, 20, 40), $counters);

        $counters = $this->api->getCounters($this->idSite, 0);
        $this->assertEquals($this->buildCounter(0, 0, 0, 0), $counters);
    }

    private function trackSomeVisits()
    {
        $nowTimestamp = time();

        // use local tracker so mock location provider can be used
        $t = Fixture::getTracker($this->idSite, $nowTimestamp, $defaultInit = true, $useLocal = false);
        $t->enableBulkTracking();

        for ($i = 0; $i != 20; ++$i) {
            $t->setForceNewVisit();
            $t->setVisitorId( substr(md5($i * 1000), 0, $t::LENGTH_VISITOR_ID));

            $factor = 10;
            if ($i > 15) {
                $factor = 30; // make sure first 15 visits are always within 5 minutes to prevent any random fails
            }
            $time = $nowTimestamp - ($i * $factor);

            // first visit -> this one is > 5 minutes and should be ignored in one test
            $date = Date::factory($time - 600);
            $t->setForceVisitDateTime($date->getDatetime());
            $t->setUrl("http://piwik.net/space/quest/iv");
            $t->doTrackPageView("Space Quest XV");

            $t->doTrackGoal(1); // this one is > 5 minutes and should be ignored in one test

            // second visit
            $date = Date::factory($time - 1);
            $t->setForceVisitDateTime($date->getDatetime());
            $t->setUrl("http://piwik.net/space/quest/iv");
            $t->doTrackPageView("Space Quest XII");

            if ($i % 6 == 0) {
                $t->setForceNewVisit(); // to test visitors vs visits
            }

            // third visit
            $date = Date::factory($time);
            $t->setForceVisitDateTime($date->getDatetime());
            $t->setUrl("http://piwik.net/grue/$i");
            $t->doTrackPageView('It is pitch black...');

            $t->doTrackGoal(2);
        }

        $t->doBulkTrack();
    }

    private function buildCounter($visits, $actions, $visitors, $visitsConverted)
    {
        return array(array(
            'visits'   => $visits,
            'actions'  => $actions,
            'visitors' => $visitors,
            'visitsConverted' => $visitsConverted,
        ));
    }

    private function createSite()
    {
        Fixture::createWebsite('2013-01-23 01:23:45');
        GoalsApi::getInstance()->addGoal(1, 'MyName', 'manually', '', 'contains');
        GoalsApi::getInstance()->addGoal(1, 'MyGoal', 'manually', '', 'contains');
    }

    private function setSuperUser()
    {
        $pseudoMockAccess = new FakeAccess();
        FakeAccess::$superUser = true;
        Access::setSingletonInstance($pseudoMockAccess);
    }

    private function setAnonymous()
    {
        $pseudoMockAccess = new FakeAccess();
        FakeAccess::$superUser = false;
        Access::setSingletonInstance($pseudoMockAccess);
    }

}
