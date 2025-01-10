<?php

use Joomla\CMS\Component\ComponentHelper;
use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;

Class AesirX_Analytics_Get_Datastream_Template extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get the component parameters (assuming the component is named 'com_aesirx_analytics')
        $componentParams = ComponentHelper::getParams('com_aesirx_analytics');

        return [
            'domain'   => $componentParams->get('aesirx_analytics_plugin_options_datastream_domain', ''),
            'template' => $componentParams->get('aesirx_analytics_plugin_options_datastream_template', ''),
            'gtag_id'  => $componentParams->get('aesirx_analytics_plugin_options_datastream_gtag_id', ''),
            'gtm_id'   => $componentParams->get('aesirx_analytics_plugin_options_datastream_gtm_id', ''),
            'consent'  => $componentParams->get('aesirx_analytics_plugin_options_datastream_consent', ''),
        ];
    }
}
