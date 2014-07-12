<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\API;

use Piwik\Common;
use Piwik\DataTable\BaseFilter;
use Piwik\API\DataTableManipulator\Flattener;
use Piwik\API\DataTableManipulator\LabelFilter;
use Piwik\API\DataTableManipulator\ReportTotalsCalculator;
use Piwik\DataTable;
use Piwik\DataTable\Map;

/**
 * TODO
 */
class DataTablePostProcessor extends BaseFilter
{
    /**
     * Returns an array containing the information of the generic Filter
     * to be applied automatically to the data resulting from the API calls.
     *
     * Order to apply the filters:
     * 1 - Filter that remove filtered rows
     * 2 - Filter that sort the remaining rows
     * 3 - Filter that keep only a subset of the results
     * 4 - Presentation filters
     *
     * @return array  See the code for spec
     *
     * TODO: remove need for this method?
     */
    public static function getGenericFiltersInformation()
    {
        return array(
            array('Pattern',
                  array(
                      'filter_column'  => array('string', 'label'),
                      'filter_pattern' => array('string')
                  )),
            array('PatternRecursive',
                  array(
                      'filter_column_recursive'  => array('string', 'label'),
                      'filter_pattern_recursive' => array('string'),
                  )),
            array('ExcludeLowPopulation',
                  array(
                      'filter_excludelowpop'       => array('string'),
                      'filter_excludelowpop_value' => array('float', '0'),
                  )),
            array('AddColumnsProcessedMetrics',
                  array(
                      'filter_add_columns_when_show_all_columns' => array('integer')
                  )),
            array('AddColumnsProcessedMetricsGoal',
                  array(
                      'filter_update_columns_when_show_all_goals' => array('integer'),
                      'idGoal'                                    => array('string', AddColumnsProcessedMetricsGoal::GOALS_OVERVIEW),
                  )),
            array('Sort',
                  array(
                      'filter_sort_column' => array('string'),
                      'filter_sort_order'  => array('string', 'desc'),
                  )),
            array('Truncate',
                  array(
                      'filter_truncate' => array('integer'),
                  )),
            array('Limit',
                  array(
                      'filter_offset'    => array('integer', '0'),
                      'filter_limit'     => array('integer'),
                      'keep_summary_row' => array('integer', '0'),
                  )),
        );
    }

    /**
     * TODO
     */
    private $request;

    /**
     * TODO
     */
    private $apiModule;

    /**
     * TODO
     */
    private $apiAction;

    /**
     * TODO
     */
    public $resultDataTable;

    /**
     * TODO
     */
    public function __construct($apiModule, $apiAction, $request = false)
    {
        if ($request === false) {
            $request = $_GET + $_POST;
        }

        $this->request = $request;
        $this->apiModule = $apiModule;
        $this->apiAction = $apiAction;
    }

    /**
     * TODO
     */
    public function filter($datatable)
    {
        // if the flag disable_generic_filters is defined we skip the generic filters
        if (0 == Common::getRequestVar('disable_generic_filters', '0', 'string', $this->request)) {
            // if requested, flatten nested tables
            if (Common::getRequestVar('flat', '0', 'string', $this->request) == '1') {
                $flattener = new Flattener($this->apiModule, $this->apiAction, $this->request);
                if (Common::getRequestVar('include_aggregate_rows', '0', 'string', $this->request) == '1') {
                    $flattener->includeAggregateRows();
                }
                $flattener->flatten($datatable);
            }

            if (1 == Common::getRequestVar('totals', '1', 'integer', $this->request)) {
                $genericFilter = new ReportTotalsCalculator($this->apiModule, $this->apiAction, $this->request);
                $datatable     = $genericFilter->calculate($datatable);
            }

            $this->applyGenericFilters($datatable);
        }

        // if the flag disable_queued_fi
        // we automatically safe decode all datatable labels (against xss)
        $datatable->filter('SafeDecodeLabel');

        if (Common::getRequestVar('disable_queued_filters', 0, 'int', $this->request) == 0) {
            $datatable->applyQueuedFilters();
        }

        if (0 == Common::getRequestVar('disable_generic_filters', '0', 'string', $this->request)) {
            // use the ColumnDelete filter if hideColumns/showColumns is provided (must be done
            // after queued filters are run so processed metrics can be removed, too)
            $hideColumns = Common::getRequestVar('hideColumns', '', 'string', $this->request);
            $showColumns = Common::getRequestVar('showColumns', '', 'string', $this->request);
            if ($hideColumns !== '' || $showColumns !== '') {
                $datatable->filter('ColumnDelete', array($hideColumns, $showColumns));
            }

            // apply label filter: only return rows matching the label parameter (more than one if more than one label)
            $label = $this->getLabelFromRequest($this->request);
            if (!empty($label)) {
                $addLabelIndex = Common::getRequestVar('labelFilterAddLabelIndex', 0, 'int', $this->request) == 1;

                $filter = new LabelFilter($this->apiModule, $this->apiAction, $this->request);
                $datatable = $filter->filter($label, $datatable, $addLabelIndex);

                $this->applyGenericFilters($datatable);

                $datatable->filter(function ($table) {
                    foreach ($table->getRows() as $row) {
                        $row->setColumns($row->getColumns()); // force processed metrics to be calculated
                    }
                });
            }
        }

        $this->resultDataTable = $datatable; // TODO: remove after changing all 'manipulators' to modify tables in-place
    }

    /**
     * Returns the value for the label query parameter which can be either a string
     * (ie, label=...) or array (ie, label[]=...).
     *
     * @param array $request
     * @return array
     */
    static public function getLabelFromRequest($request)
    {
        $label = Common::getRequestVar('label', array(), 'array', $request);
        if (empty($label)) {
            $label = Common::getRequestVar('label', '', 'string', $request);
            if (!empty($label)) {
                $label = array($label);
            }
        }

        $label = self::unsanitizeLabelParameter($label);
        return $label;
    }

    static public function unsanitizeLabelParameter($label)
    {
        // this is needed because Proxy uses Common::getRequestVar which in turn
        // uses Common::sanitizeInputValue. This causes the > that separates recursive labels
        // to become &gt; and we need to undo that here.
        $label = Common::unsanitizeInputValues($label);
        return $label;
    }

    /**
     * Apply generic filters to the DataTable object resulting from the API Call.
     * Disable this feature by setting the parameter disable_generic_filters to 1 in the API call request.
     *
     * @param DataTable $datatable
     * @return bool
     */
    public function applyGenericFilters($datatable)
    {
        if ($datatable instanceof Map) {
            $tables = $datatable->getDataTables();
            foreach ($tables as $table) {
                $this->applyGenericFilters($table);
            }
            return;
        }

        $genericFilters = self::getGenericFiltersInformation();

        $filterApplied = false;
        foreach ($genericFilters as $filterMeta) {
            $filterName = $filterMeta[0];
            $filterParams = $filterMeta[1];
            $filterParameters = array();
            $exceptionRaised = false;

            foreach ($filterParams as $name => $info) {
                // parameter type to cast to
                $type = $info[0];

                // default value if specified, when the parameter doesn't have a value
                $defaultValue = null;
                if (isset($info[1])) {
                    $defaultValue = $info[1];
                }

                // third element in the array, if it exists, overrides the name of the request variable
                $varName = $name;
                if (isset($info[2])) {
                    $varName = $info[2];
                }

                try {
                    $value = Common::getRequestVar($name, $defaultValue, $type, $this->request);
                    settype($value, $type);
                    $filterParameters[] = $value;
                } catch (Exception $e) {
                    $exceptionRaised = true;
                    break;
                }
            }

            if (!$exceptionRaised) {
                $datatable->filter($filterName, $filterParameters);
                $filterApplied = true;
            }
        }
        return $filterApplied;
    }
}