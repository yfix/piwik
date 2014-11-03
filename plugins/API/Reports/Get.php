<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\API\Reports;

use Piwik\Plugin\Manager;
use Piwik\Plugin\Report;

class Get extends Report
{
    /**
     * TODO
     *
     * @var Report[]
     */
    private $reportsToMerge = array();

    protected function init()
    {
        parent::init();

        $this->reportsToMerge = $this->getReportsToMerge();

        $this->category      = 'API';
        $this->name          = 'All Metrics'; // TODO: translate
        $this->documentation = ''; // TODO

        $this->processedMetrics = array();
        foreach ($this->reportsToMerge as $report) {
            if (!is_array($report->processedMetrics)) {
                continue;
            }

            $this->processedMetrics = array_merge($this->processedMetrics, $report->processedMetrics);
        }

        $this->metrics = array();
        foreach ($this->reportsToMerge as $report) {
            if (!is_array($report->metrics)) {
                continue;
            }

            $this->metrics = array_merge($this->metrics, $report->metrics);
        }

        $this->temporaryMetrics = array();
        foreach ($this->reportsToMerge as $report) {
            if (!is_array($report->temporaryMetrics)) {
                continue;
            }

            $this->temporaryMetrics = array_merge($this->temporaryMetrics, $report->temporaryMetrics);
        }

        // Used to process metrics, not displayed/used directly
//								'sum_visit_length',
//								'nb_visits_converted',
        $this->order = 1;
    }

    public function getMetrics()
    {
        $metrics = array();
        foreach ($this->reportsToMerge as $report) {
            $metrics = array_merge($metrics, $report->getMetrics());
        }
        return $metrics;
    }

    /**
     * @return Report[]
     */
    private function getReportsToMerge()
    {
        $result = array();
        foreach (Manager::getInstance()->getLoadedPluginsName() as $moduleName) {
            if ($moduleName == 'API') {
                continue;
            }

            $report = Report::factory($moduleName, 'get');
            if (empty($report)) {
                continue;
            }

            $metrics = $report->getMetrics();
            if (!empty($report->parameters)
                || empty($metrics)
            ) {
                continue;
            }

            $result[] = $report;
        }
        return $result;
    }
}