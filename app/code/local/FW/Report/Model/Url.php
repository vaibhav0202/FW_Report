<?php
/**
 * @category    FW
 * @package     FW_Report
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 * @author      J.P. Daniel <jp.daniel@fwmedia.com>
 */
class FW_Report_Model_Url extends FW_Report_Model_Report
{
    /**
     * Report for visible products with duplicate urls
     *
     * @var string
     */
    const VIS_LOG = 'Vis_Dupe';

    /**
     * Report for all product with duplicate urls
     *
     * @var string
     */
    const ALL_LOG = 'All_Dupe';

    /**
     * DB Connection Adapter
     *
     * @var Varien_Db_Adapter_Pdo_Mysql
     */
    protected $connection;

    /**
     * Attribute that is being checked based on mode
     *
     * @var Mage_Eav_Model_Entity_Attribute
     */
    protected $attr;

    /**
     * Table name of the table that stores the EAV data for the attribute
     *
     * @var string
     */
    protected $table;

    /**
     * Constructor called from Varien_Object Constructor
     * Gets the connection adapter
     * Creates the report model
     *
     */
    public function __construct()
    {
        parent::__construct();      // Call parent constructor
        $this->connection = Mage::getSingleton('core/resource');            // Get the Mage_Core_Model_Resource singleton
        $this->connection = $this->connection->getConnection('eav_write');  // Get the DB connection adapter
    }

    /**
     * Inits the proper attribute and table based on mode being reported on
     *
     * @param string $mode
     */
    protected function initMode($mode = 'products')
    {
        $this->attr = Mage::getSingleton('eav/config');         // Get the Mage_Eav_Model_Config singleton
        $this->table = Mage::getSingleton('core/resource');     // Get the Mage_Core_Model_Resource singleton
        if ($mode === 'categories') {
            $this->attr = $this->attr->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'url_key');    // Get the category url_key attribute
            $this->table = $this->table->getTableName('catalog_category_entity_varchar');               // Get the category EAV varchar table name
        } else {
            $this->attr = $this->attr->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'url_key');     // Get the product url_key attribute
            $this->table = $this->table->getTableName('catalog_product_entity_varchar');                // Get the product EAV varchar table name
        }
    }

    /**
     * Gets the duplicate urls for the mode
     *
     * @param string $mode
     * @return array
     */
    protected function getDuplicateUrls($mode = 'products')
    {
        $this->initMode($mode);     // Init the current mode to set attr and table properties
        /** @var Varien_Db_Select */
        $select = $this->connection->select()->from($this->table, array(
            'num' => new Zend_Db_Expr('COUNT(*)'),
            'url_key' => 'value',
            'store' => 'store_id'
        ))
            ->where('attribute_id=?', $this->attr->getId())
            ->group('value')
            ->group('store_id')
            ->order('num')
            ->having('num > 1');
        Mage::getResourceHelper('core')->addGroupConcatColumn($select, 'entities', 'entity_id');
        return $this->connection->fetchAll($select);    // Fetch all the records from DB
    }

    /**
     * Lists the duplicate urls for the mode
     *
     * @param string $mode
     */
    public function createDuplicateUrlReport($mode = 'products')
    {
        $dupes = $this->getDuplicateUrls($mode);    // Get the duplicate urls for the mode
        foreach ($dupes as $row) {                  // Loop through each duplicate url
            $allSkus = array();                     // Create an empty array for all skus with dupe urls
            $visSkus = array();                     // Create an empty array for visible skus with dupe urls
            foreach(explode(',', $row['entities']) as $pid) {                           // Loop through each product with the duped url
                /** @var Mage_Catalog_Model_Product $product */
                $product = Mage::getModel('catalog/product')->load($pid);               // Load the full product model
                if ($product->getVisibility() != 1) $visSkus[] = $product->getSku();    // If product is visible add sku to visible sku array
                $allSkus[] = $product->getSku();                                        // Add all skus to the all sku array
            }
            if (count($visSkus) > 1) {                                          // If visible skus is still > 1
                $this->put($row['url_key'] . ' - ' . implode(', ', $visSkus), self::VIS_LOG);   // Add message to report
            }
            $this->put($row['url_key'] . ' - ' . implode(', ', $allSkus), self::ALL_LOG);       // Add message to report
        }
        $this->putReportTotal(self::VIS_LOG);       // Add total messages to the end of the visible skus report
        $this->putReportTotal(self::ALL_LOG);       // Add total messages to the end of the all skus report
    }

    /**
     * Emails the duplicate url report
     *
     * @param string $mode
     */
    public function emailDuplicateProductUrlReport($mode = 'products')
    {
        /** @var FW_Report_Helper_Data $reportHelper */
        $reportHelper = Mage::helper('fw_report');                                  // Get FW Report helper
        $emailTo = $reportHelper->getDuplicateUrlEmail();                           // Get emails from admin
        if (!$emailTo) {                                                            // If no emails configured in admin
            $this->log('No emails configured in admin', 'Error', Zend_Log::ERR);    // Log Error
            return;                                                                 // Exit
        }
        $this->createDuplicateUrlReport();                              // Create the duplicate product url reports
        $this->sendEmail($emailTo, 'FW Duplicate URL Key Report');      // Send out email with reports as attachments
    }
}