<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Tests\Fixtures;

use Piwik\Date;
use Piwik\Plugins\Goals\API;
use Piwik\Tests\Fixture;

/**
 * Adds one site and tracks a couple conversions.
 */
class SomeVisitsAllConversions extends Fixture
{
    public $dateTime = '2009-01-04 00:11:42';

    public function __construct()
    {
        $this->sites = array();
        $sites['main'] = array(
            'ts_archived' => $this->idSite,
            'goals' => array( // TODO: add goals
                // First, a goal that is only recorded once per visit
                'OneConversionPerVisit' => array(
                    'name' => 'triggered js ONCE',
                    'match_attribute' => 'title',
                    'pattern' => 'Thank you',
                    'pattern_type' => 'contains',
                    'case_sensitive' => false,
                    'revenue' => 10,
                    'allow_multiple_conversions_per_visit' => false
                ),

                // Second, a goal allowing multiple conversions
                'MultipleConversionsPerVisit' => array(
                    'name' => 'triggered js MULTIPLE ALLOWED',
                    'match_attribute' => 'manually',
                    'pattern' => '',
                    'pattern_type' => '',
                    'case_sensitive' => false,
                    'revenue' => 10,
                    'allow_multiple_conversions_per_visit' => true
                ),

                'ClickEvent' => array(
                    'name' => 'click event',
                    'match_attribute' => 'event_action',
                    'pattern' => 'click',
                    'pattern_type' => 'contains'
                ),

                'CategoryEvent' => array(
                    'name' => 'category event',
                    'match_attribute' => 'event_category',
                    'pattern' => 'The_Category',
                    'pattern_type' => 'exact',
                    'case_sensitive' => true
                ),

                // including a few characters that are HTML entitiable
                'NameEvent' => array(
                    'name' => 'name event',
                    'match_attribute' => 'event_name',
                    'pattern' => '<the_\'"name>',
                    'pattern_type' => 'exact'
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
        $dateTime = $this->dateTime;
        $idSite = $this->sites['main']['idSite'];
        $idGoal_OneConversionPerVisit = $this->sites['main']['goals']['OneConversionPerVisit']['idGoal'];
        $idGoal_MultipleConversionPerVisit = $this->sites['main']['goals']['MultipleConversionPerVisit']['idGoal'];

        $t = self::getTracker($idSite, $dateTime, $defaultInit = true);

        // Record 1st goal, should only have 1 conversion
        $t->setUrl('http://example.org/index.htm');
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour(0.3)->getDatetime());
        self::checkResponse($t->doTrackPageView('Thank you mate'));
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour(0.4)->getDatetime());
        self::checkResponse($t->doTrackGoal($idGoal_OneConversionPerVisit, $revenue = 10000000));

        // Record 2nd goal, should record both conversions
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour(0.5)->getDatetime());
        self::checkResponse($t->doTrackGoal($idGoal_MultipleConversionPerVisit, $revenue = 300));
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour(0.6)->getDatetime());
        self::checkResponse($t->doTrackGoal($idGoal_MultipleConversionPerVisit, $revenue = 366));

        // Update & set to not allow multiple
        $goals = API::getInstance()->getGoals($idSite);
        $goal = $goals[$idGoal_OneConversionPerVisit];
        self::assertTrue($goal['allow_multiple'] == 0);
        API::getInstance()->updateGoal($idSite, $idGoal_OneConversionPerVisit, $goal['name'], @$goal['match_attribute'], @$goal['pattern'], @$goal['pattern_type'], @$goal['case_sensitive'], $goal['revenue'], $goal['allow_multiple'] = 1);
        self::assertTrue($goal['allow_multiple'] == 1);

        // 1st goal should Now be tracked
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour(0.61)->getDatetime());
        self::checkResponse($t->doTrackGoal($idGoal_OneConversionPerVisit, $revenue = 656));

        // few minutes later, create a new_visit
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour(0.7)->getDatetime());
        $t->setTokenAuth($this->getTokenAuth());
        $t->setForceNewVisit();
        $t->doTrackPageView('This is tracked in a new visit.');

        // should trigger two goals at once (event_category, event_action)
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.3)->getDatetime());
        self::checkResponse($t->doTrackEvent('The_Category', 'click_action', 'name'));

        // should not trigger a goal (the_category is case senstive goal)
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.4)->getDatetime());
        self::checkResponse($t->doTrackEvent('the_category', 'click_action', 'name'));

        // should trigger a goal for event_name, including a few characters that are HTML entitiable
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.4)->getDatetime());
        self::checkResponse($t->doTrackEvent('other_category', 'other_action', '<the_\'"name>'));
    }
}