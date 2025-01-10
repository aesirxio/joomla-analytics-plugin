<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;

Class AesirX_Analytics_Store_Datastream_Template extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Create an input filter instance
        $inputFilter = InputFilter::getInstance();

        // Get the component parameters
        $paramsComponent = ComponentHelper::getParams('com_aesirx_analytics');

        // Initialize response array
        $response = [];

        foreach ($params as $key => $value) {
            if (is_string($key)) {
            // Filter and sanitize the input value
            $new_value = $inputFilter->clean($value, 'STRING');

            // Store the value in the component parameters (substitute for update_option)
            $paramsComponent->set('aesirx_analytics_plugin_options_datastream_' . $key, $new_value);

            // Prepare response with sanitized value
            $response[$key] = $new_value;
            }
        }

        $table = Table::getInstance('extension');
        if ($table->load(['element' => 'com_aesirx_analytics'])) {
            $table->params = $paramsComponent->toString();
            if (!$table->store()) {
                throw new Exception('Failed to save the parameters: ' . $table->getError());
            }
        } else {
            throw new Exception('Failed to load the component.');
        }

        return $response;
    }
}
