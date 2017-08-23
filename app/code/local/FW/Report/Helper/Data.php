<?php
/**
 * @category    FW
 * @package     FW_Report
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 */
class FW_Report_Helper_Data extends Mage_Core_Helper_Abstract 
{

    /**
    * Config path for using throughout the code
     * @var string $XML_PATH
     */
    const XML_PATH  = 'fw_report/';
    
    /**
     * Email address to send weekly order export to
     *
     * @param mixed $store
     * @return string
     */
    public function orderExportEmail($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH.'orderExport/orderexport_emailnotice', $store);
    }

    /**
     * Email address to send duplicate url report
     *
     * @param mixed $store
     * @return string
     */
    public function getDuplicateUrlEmail($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH . 'fw_report_url/fw_report_url_email', $store);
    }
   
}

