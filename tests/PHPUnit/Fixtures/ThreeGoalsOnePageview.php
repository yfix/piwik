<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Tests\Fixtures;

use Piwik\Date;
use Piwik\Plugins\Goals\API;
use Piwik\Tests\Fixture;

/**
 * Fixture that adds one site with three goals and tracks one pageview & one manual
 * goal conversion.
 */
class ThreeGoalsOnePageview extends Fixture
{
    public $dateTime = '2009-01-04 00:11:42';

    public function __construct()
    {
        $sites = array();
        $sites['main'] = array(
            'ts_created' => $this->dateTime,
            'ecommerce' => 1,
            'goals' => array(
                'Goal1' => array(
                    'name' => 'Goal 1 - Thank you',
                    'match_attribute' => 'title',
                    'pattern' => 'Thank you',
                    'pattern_type' => 'contains',
                    'case_sensitive' => false,
                    'revenue' => 10,
                    'allow_multiple_conversions_per_visit' => 1
                ),

                'Goal2' => array(
                    'name' => 'Goal 2 - Hello',
                    'match_attribute' => 'url',
                    'pattern' => 'hellow',
                    'pattern_type' => 'contains',
                    'case_sensitive' => false,
                    'revenue' => 10,
                    'allow_multiple_conversions_per_visit' => 0
                ),

                'Goal3' => array(
                    'name' => 'triggered js',
                    'match_attribute' => 'manually',
                    'pattern' => '',
                    'pattern_type' => ''
                )
            )
        );
        $this->setSites($sites);
    }

    public function setUp()
    {
        $this->trackVisits();
    }

    private function trackVisits()
    {
        $t = self::getTracker($this->sites['main']['idSite'], $this->dateTime, $defaultInit = true);

        // Record 1st page view
        $t->setUrl('http://example.org/index.htm');
        self::checkResponse($t->doTrackPageView('0'));

        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.3)->getDatetime());
        self::checkResponse($t->doTrackGoal($this->sites['main']['goals']['Goal3']['idGoal'], $revenue = 42.256));
    }
}