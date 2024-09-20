<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Component\ComponentHelper;

Class AesirX_Analytics_Job_Geo extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        $now = gmdate('Y-m-d H:i:s');
        // Fetching options (assuming options retrieval logic in Joomla)
        $componentParams = ComponentHelper::getParams('com_aesirx_analytics');
        $config = [
            'url_api_enrich' => 'https://api.aesirx.io/index.php?webserviceClient=site&webserviceVersion=1.0.0&option=aesir_analytics&api=hal&task=enrichVisitor',
            'license' => $inputFilter->clean($componentParams->get('license'), 'STRING')
        ];

        $list = parent::aesirx_analytics_get_ip_list_without_geo($params);

        if (count($list) == 0) {
            return;
        }

        // Create the HTTP client using Joomla's HttpFactory
        $http = HttpFactory::getHttp();
        $headers = [
            'Content-Type' => 'application/json'
        ];

        // Prepare the request body with the license and IP list
        $body = json_encode([
            'licenses' => $config['license'],
            'ip' => $list
        ]);

        // Make the POST request
        try {
            $response = $http->post($config['url_api_enrich'], $body, $headers);
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage("Error in API request: " . $e->getMessage(), 'error');
            return false;
        }
    
        // Decode the response body
        $enrich = json_decode($response->body, true);

        // Check for API errors
        if (isset($enrich['error'])) {
            Factory::getApplication()->enqueueMessage("API error: " . $enrich['error']['message'], 'error');
            return false;
        }
    
        // Update geo information for IPs
        foreach ($enrich['result'] as $result) {
            parent::aesirx_analytics_update_null_geo_per_ip(
                $result['ip'],
                [
                    'country' => [
                        'name' => $result['country_name'],
                        'code' => $result['country_code']
                    ],
                    'city' => $result['city'],
                    'region' => $result['region'],
                    'isp' => $result['isp'],
                    'created_at' => $now,
                ]
            );
        }
    }
}
