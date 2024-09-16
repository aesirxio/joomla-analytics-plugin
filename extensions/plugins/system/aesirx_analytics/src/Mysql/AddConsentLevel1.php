<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filter\InputFilter;

include __DIR__ . '/GetVisitorConsentList.php';

Class AesirX_Analytics_Add_Consent_Level1 extends AesirxAnalyticsMysqlHelper
{
    /**
     * Executes analytics MySQL query and processes visitor consent.
     *
     * This function handles the execution of a MySQL query to retrieve visitor consent data,
     * checks the retrieved consents for specific conditions (e.g., level, same consent number,
     * expiration), and adds new visitor consent if applicable.
     *
     * @param array $params Parameters for the MySQL query and consent processing.
     * @return mixed|string Returns the result of adding visitor consent or an error message if a previous consent was not expired.
     */
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get the InputFilter instance
        $inputFilter = InputFilter::getInstance();

        // Validate required parameters
        if (!isset($params['uuid']) || empty($params['uuid'])) {
            throw new Exception(Text::_('The uuid parameter is required.'), 400);
        }

        if (!isset($params['consent']) || !is_numeric($params['consent'])) {
            throw new Exception(Text::_('The consent parameter is required and must be a number.'),400);
        }

        // Sanitize the inputs using InputFilter
        $uuid = $inputFilter->clean($params['uuid'], 'string');
        $consent = $inputFilter->clean($params['consent'], 'int');

        // Get the current date and time
        $now = gmdate('Y-m-d H:i:s');

        // Instantiate the class to get visitor consent list
        $class = new \AesirX_Analytics_Get_Visitor_Consent_List();

        // Execute the MySQL query to get consents based on provided parameters
        $consents = $class->aesirx_analytics_mysql_execute($params);

        // Iterate over each retrieved consent
        foreach ($consents['visitor_consents'] as $consentData) {
            // Check if the consent is at level1 (i.e., consent_uuid is null)
            if (is_null($consentData['consent_uuid'])) {
                // Check if the consent number is the same as the provided parameter
                if (!is_null($consentData['consent']) && $consentData['consent'] != $consent) {
                    continue; // Skip to the next consent if the numbers do not match
                }

                // Check if the consent is expired  
                if (!is_null($consentData['expiration']) && $consentData['expiration'] > $now) {
                    // Return an error if the previous consent has not expired
                    throw new Exception(Text::_('Previous consent was not expired'),400);
                }
            }
        }

        // Add new visitor consent with the given parameters and calculated timestamps
        return parent::aesirx_analytics_add_visitor_consent(
            $uuid,                            // Visitor UUID
            null,                            // Consent UUID (null for new consent)
            $consent,                        // Consent level
            $now                             // Current timestamp
        );
    }
}