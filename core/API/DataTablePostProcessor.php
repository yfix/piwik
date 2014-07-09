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

/**
 * TODO
 */
class DataTablePostProcessor extends BaseFilter
{
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

            $genericFilter = new DataTableGenericFilter($this->request);
            $genericFilter->filter($datatable);
        }

        // we automatically safe decode all datatable labels (against xss)
        $datatable->queueFilter('SafeDecodeLabel');

        // if the flag disable_queued_filters is defined we skip the filters that were queued
        if (Common::getRequestVar('disable_queued_filters', 0, 'int', $this->request) == 0) {
            $datatable->applyQueuedFilters();
        }

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
        }

        $this->resultDataTable = $datatable;
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
}