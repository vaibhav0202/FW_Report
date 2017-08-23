<?php
/**
 * @category    FW
 * @package     FW_Report
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 * @author      J.P. Daniel <jp.daniel@fwmedia.com>
 */
class FW_Report_Model_Report
{
    /**
     * Timestamp of when the model was constructed
     *
     * @var string
     */
    protected $time;

    /**
     * The base name for the reports
     *
     * @var string
     */
    protected $base;

    /**
     * The base file location for the reports
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * The location of all reports that have been created
     *
     * @var array
     */
    protected $reportList = array();

    /**
     * The total number of messages in a report
     *
     * @var array
     */
    protected $reportTotal = array();

    /**
     * Constructor called from Varien_Object Constructor
     * Sets the timestamp of when the model was constructed
     */
    public function __construct($base = null)
    {
        $this->time = date('YmdHis');                                           // Set the timestamp
        $this->base = $base ?: str_replace('_Model', '', get_class($this));     // Set the base name
        $this->setBaseUrl(str_replace('_', '/', $this->base));                  // Set base url
    }

    /**
     * Sets the base file location for the reports
     *
     * @param $base
     * @return $this
     */
    public function setBaseUrl($base)
    {
        $base .= '/' . date('Y/m/d');                       // Add timestamp to base
        $this->baseUrl = $base;                             // Set the base url with timestamp
        $base = Mage::getBaseDir('log') . '/' . $base;      // Add path to log to base
        $this->isDir($base);                                // Make sure base directory exists
        return $this;       // Return this model, so chaining is possible $this->method()->method();
    }

    public function isDir($path)
    {
        if (!is_dir($path)) mkdir($path, 0777, true);       // Make sure path exists
        return $path;
    }

    /**
     * Logs a message to the file system using core Mage::log method
     * Keeps a running total of messages in a report
     *
     * @param $message
     * @param string $report
     * @param int $level
     * @return $this
     */
    public function log($message, $report = null, $level = Zend_Log::INFO)
    {
        try {
            Mage::log($message, $level, $this->getReport('log', $report));          // log the message to the report
            $this->reportTotal[$report]++;                                          // Add to report total
        } catch (Exception $e) {                                                    // Catch errors
            if ($level != Zend_Log::ERR) $this->log($e, 'Error', Zend_Log::ERR);    // Log errors
        }
        return $this;       // Return this model, so chaining is possible $this->method()->method();
    }

    /**
     * Write a message for a CSV report
     *
     * @param $message
     * @param string $report
     * @return $this
     */
    public function csv($message, $report = null)
    {
        return $this->put($message, $report, 'csv');  // Call generic put method with CSV file type
    }

    /**
     * Write a message for any custom report type
     * Defaults to a TXT report type
     *
     * @param $message
     * @param string $report
     * @param string $fileExt
     * @return $this
     */
    public function put($message, $report = null, $fileExt = 'txt')
    {
        try {
            $message .= "\r\n";                                                             // Add a line break
            file_put_contents($this->getReport($fileExt, $report), $message, FILE_APPEND);  // Append message to report
            $this->reportTotal[$report]++;                                                  // Add to report total
        } catch (Exception $e) {                                                            // Catch errors
            $this->log($e, 'Error', Zend_Log::ERR);                                         // Log errors
        }
        return $this;       // Return this model, so chaining is possible $this->method()->method();
    }

    /**
     * Logs the total number of messages in a log report to the same log report
     *
     * @param $report
     * @return $this
     */
    public function logReportTotal($report)
    {
        return $this->log('Total: ' . $this->getReportTotal($report), $report);     // Log the total messages in report
    }

    /**
     * Logs the total number of messages in a log report to the same log report
     *
     * @param $report
     * @return $this
     */
    public function putReportTotal($report)
    {
        return $this->put('Total: ' . $this->getReportTotal($report), $report);     // Put the total messages in report
    }

    /**
     * Get the total number of messages in a report
     *
     * @param $report
     * @return int
     */
    public function getReportTotal($report)
    {
        return (!empty($this->reportTotal[$report]) ? $this->reportTotal[$report] : 0);     // Return total messages in report
    }

    /**
     * Gets the report
     *
     * @param $report
     * @param string $type
     * @return string
     */
    private function getReport($type, $report = null)
    {
        if (empty($this->reportList[$report])) {                                // If report doesn't already exist
            $this->reportList[$report] = $this->getFileUrl($type, $report);     // Build and add URL to the report list
            $this->reportTotal[$report] = 0;                                    // Set total messages for report to 0
        }
        if ($type == 'log') return $this->getPath($type, $report);              // Return path for log files
        return $this->reportList[$report];                                      // Return the file location
    }

    /**
     * Gets the location in the filesystem for a report
     *
     * @param $report
     * @param string $type
     * @return mixed
     */
    private function getFileUrl($type, $report = null)
    {
        return Mage::getBaseDir('log') . '/' . $this->getPath($type, $report);  // Return the file location
    }

    /**
     * Gets the path in the filesystem for a report
     *
     * @param $report
     * @param string $type
     * @return mixed
     */
    private function getPath($type, $report = null)
    {
        $report = $report ?: $this->base;                           // Default report if none set
        return "{$this->baseUrl}/{$report}_{$this->time}.{$type}";  // Return the url
    }

    /**
     * Sends an email containing all of the reports as attachments
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return $this
     */
    public function sendEmail($to = 'devteam@fwmedia.com', $subject = '', $body = '') {
        try {
            $zipUrl = $this->getFileUrl('zip');                                 // Get zip file location
            $zip = new ZipArchive();                                            // Create ZipArchive object
            $zip->open($zipUrl, ZipArchive::CREATE | ZipArchive::OVERWRITE);    // Open the zip file
            foreach ($this->reportList as $report) {                            // Loop through each report
                $zip->addFile($report, array_pop(explode('/', $report)));       // Add report to zip file
            }
            $zip->close();                                                      // Close the zip file
            $mail = new Zend_Mail('utf-8');                                     // Create the Zend_Mail object
            $mail->addTo(explode(',', $to));                                    // Add recipients
            $mail->setSubject($subject)->setBodyText($body);                    // Add subject and body
            $attachment = $mail->createAttachment(file_get_contents($zipUrl));  // Add zip to email as attachment
            $attachment->filename = array_pop(explode('/', $zipUrl));           // Name attachment the same as zip name
            $mail->send();                                                      // Send the email
        } catch (Exception $e) {                                                // Catch errors
            $this->log($e, 'Error', Zend_Log::ERR);                             // Log errors
        }
        return $this;       // Return this model, so chaining is possible $this->method()->method();
    }
}