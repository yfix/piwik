<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Tests\Fixtures;

use Piwik\Date;
use Piwik\Plugins\Goals\API as APIGoals;
use Piwik\Plugins\SitesManager\API as APISitesManager;
use Piwik\Tests\Fixture;

/**
 * Adds two websites and tracks visits from two visitors on different days.
 */
class TwoSitesTwoVisitorsDifferentDays extends Fixture
{
    public $dateTime = '2010-01-03 11:22:33';

    public $allowConversions = false;

    public function __construct()
    {
        $ecommerce = $this->allowConversions ? 1 : 0;

        $sites = array();

        // tests run in UTC, the Tracker in UTC
        $sites['site1'] = array(
            'ts_created' => $this->dateTime,
            'ecommerce' => $ecommerce,
            'name' => 'Site 1',
        );

        if ($this->allowConversions) {
            $sites['site1']['goals'][] = array(
                'name' => 'all',
                'match_attribute' => 'url',
                'pattern' => 'http',
                'pattern_type' => 'contains',
                'revenue' => 5
            );
        }

        $sites['site2'] = array(
            'ts_created' => $this->dateTime,
            'ecommerce' => 0,
            'name' => 'Site 2'
        );

        if ($this->allowConversions) {
            $sites['site2']['goals'][] = array(
                'name' => 'all',
                'match_attribute' => 'url',
                'pattern' => 'http',
                'pattern_type' => 'contains'
            );
        }

        $this->setSites($sites);
    }

    public function setUp()
    {
        // TODO: move this to $this->sites metadata
        APISitesManager::getInstance()->updateSite(
            $this->sites['site1']['idSite'], "Site 1", $urls = null, $ecommerce = null, $siteSearch = null,
            $searchKeywordParameters = null, $searchCategoryParameters = null, $excludedIps = null,
            $excludedQueryParameters = null, $timezone = null, $currency = null, $group = null,
            $startDate = null, $excludedUserAgents = null, $keepURLFragments = 2); // KEEP_URL_FRAGMENT_NO No for idSite 1
        APISitesManager::getInstance()->updateSite(
            $this->sites['site2']['idSite'], "Site 2", $urls = null, $ecommerce = null, $siteSearch = null,
            $searchKeywordParameters = null, $searchCategoryParameters = null, $excludedIps = null,
            $excludedQueryParameters = null, $timezone = null, $currency = null, $group = null,
            $startDate = null, $excludedUserAgents = null, $keepURLFragments = 1); // KEEP_URL_FRAGMENT_YES Yes for idSite 2

        self::setUpScheduledReports($this->sites['site1']['idSite']);
        $this->trackVisits();
    }

    private function trackVisits()
    {
        $dateTime = $this->dateTime;
        $idSite = $this->sites['site1']['idSite'];
        $idSite2 = $this->sites['site2']['idSite'];

        $this->trackVisitsSite1($idSite, $dateTime);
        $this->trackVisitsSite2($idSite2, $dateTime);
    }

    /**
     * @param $idSite
     * @param $dateTime
     */
    private function trackVisitsSite1($idSite, $dateTime)
    {
// -
        // First visitor on Idsite 1: two page views
        $datetimeSpanOverTwoDays = '2010-01-03 23:55:00';
        $visitorA = self::getTracker($idSite, $datetimeSpanOverTwoDays, $defaultInit = true);
        $visitorA->setUrlReferrer('http://referrer.com/page.htm?param=valuewith some spaces');
        $visitorA->setUrl('http://example.org/index.htm#ignoredFragment');
        $visitorA->DEBUG_APPEND_URL = '&_idts=' . Date::factory($datetimeSpanOverTwoDays)->getTimestamp();
        $visitorA->setGenerationTime(123);
        self::checkResponse($visitorA->doTrackPageView('first page view'));

        $visitorA->setForceVisitDateTime(Date::factory($datetimeSpanOverTwoDays)->addHour(0.1)->getDatetime());
        // testing with empty URL and empty page title
        $visitorA->setUrl('  ');
        $visitorA->setGenerationTime(223);
        self::checkResponse($visitorA->doTrackPageView('  '));

        // -
        // Second new visitor on Idsite 1: one page view
        $visitorB = self::getTracker($idSite, $dateTime, $defaultInit = true);
        $visitorB->enableBulkTracking();
        $visitorB->setIp('100.52.156.83');
        $visitorB->setResolution(800, 300);
        $visitorB->setForceVisitDateTime(Date::factory($dateTime)->addHour(1)->getDatetime());
        $visitorB->setUrlReferrer('');
        $visitorB->setUserAgent('Opera/9.63 (Windows NT 5.1; U; en) Presto/2.1.1');
        $visitorB->setUrl('http://example.org/products');
        $visitorB->DEBUG_APPEND_URL = '&_idts=' . Date::factory($dateTime)->addHour(1)->getTimestamp();
        $visitorB->setGenerationTime(153);
        self::assertTrue($visitorB->doTrackPageView('first page view'));

        // -
        // Second visitor again on Idsite 1: 2 page views 2 days later, 2010-01-05
        // If you are thinking of *decreasing* this value, just DON'T (it's important for our test case)
        $daysToGenerateVisitsFor = 10;
        for($days = 2; $days < $daysToGenerateVisitsFor; $days++) {
            $hoursOffset = $days * 24;

            $visitorB->setForceVisitDateTime(Date::factory($dateTime)->addHour($hoursOffset)->getDatetime());
            // visitor_returning is set to 1 only when visit count more than 1
            // Temporary, until we implement 1st party cookies in PiwikTracker
            $visitorB->DEBUG_APPEND_URL .= '&_idvc=2&_viewts=' . Date::factory($dateTime)->getTimestamp();

            $visitorB->setUrlReferrer('http://referrer.com/Other_Page.htm');
            $visitorB->setUrl('http://example.org/index.htm');
            $visitorB->setGenerationTime(323);
            self::assertTrue($visitorB->doTrackPageView('second visitor/two days later/a new visit'));
            // Second page view 6 minutes later
            $visitorB->setForceVisitDateTime(Date::factory($dateTime)->addHour($hoursOffset)->addHour(0.1)->getDatetime());
            $visitorB->setUrl('http://example.org/thankyou');
            $visitorB->setGenerationTime(173);
            self::assertTrue($visitorB->doTrackPageView('second visitor/two days later/second page view'));

            // testing a strange combination causing an error in r3767
            $visitorB->setForceVisitDateTime(Date::factory($dateTime)->addHour($hoursOffset)->addHour(0.2)->getDatetime());
            self::assertTrue($visitorB->doTrackAction('mailto:test@example.org', 'link'));
            $visitorB->setForceVisitDateTime(Date::factory($dateTime)->addHour($hoursOffset)->addHour(0.25)->getDatetime());
            self::assertTrue($visitorB->doTrackAction('mailto:test@example.org/strangelink', 'link'));

            // Actions.getPageTitle tested with this title
            $visitorB->setForceVisitDateTime(Date::factory($dateTime)->addHour($hoursOffset)->addHour(0.25)->getDatetime());
            $visitorB->setGenerationTime(452);
            self::assertTrue($visitorB->doTrackPageView('Checkout / Purchasing...'));
            self::checkBulkTrackingResponse($visitorB->doBulkTrack());
        }
    }

    /**
     * @param $idSite2
     * @param $dateTime
     */
    private function trackVisitsSite2($idSite2, $dateTime)
    {
        // -
        // First visitor on Idsite 2: one page view, with Website referrer
        $visitorAsite2 = self::getTracker($idSite2, Date::factory($dateTime)->addHour(24)->getDatetime(), $defaultInit = true);
        $visitorAsite2->setUserAgent('Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0;)');
        $visitorAsite2->setUrlReferrer('http://only-homepage-referrer.com/');
        $visitorAsite2->setUrl('http://example2.com/home#notIgnoredFragment#');
        $visitorAsite2->DEBUG_APPEND_URL = '&_idts=' . Date::factory($dateTime)->addHour(24)->getTimestamp();
        $visitorAsite2->setGenerationTime(193);
        self::checkResponse($visitorAsite2->doTrackPageView('Website 2 page view'));

        // test with invalid URL
        $visitorAsite2->setUrl('this is invalid url');
        // and an empty title
        $visitorAsite2->setGenerationTime(203);
        self::checkResponse($visitorAsite2->doTrackPageView(''));

        // track a page view with a domain alias to test the aggregation of both actions
        $visitorAsite2->setUrl('http://example2alias.org/home#notIgnoredFragment#');
        $visitorAsite2->setGenerationTime(503);
        self::checkResponse($visitorAsite2->doTrackPageView(''));
    }
}