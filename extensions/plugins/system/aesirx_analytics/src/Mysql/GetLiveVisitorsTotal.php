<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Live_Visitors_Total extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

         // Define the WHERE clause with conditions
        $where_clause = [
            $db->quoteName('#__analytics_flows.start') . ' = ' . $db->quoteName('#__analytics_flows.end'),
            $db->quoteName('#__analytics_flows.start') . ' >= NOW() - INTERVAL 30 MINUTE',
            $db->quoteName('#__analytics_visitors.device') . ' != ' . $db->quote('bot')
        ];

        unset($params["filter"]["start"]);
        unset($params["filter"]["end"]);

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

         // Build the query using Joomla's query builder
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('#__analytics_flows.visitor_uuid') . ') AS total')
            ->from($db->quoteName('#__analytics_flows'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_flows.visitor_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('#__analytics_flows.visitor_uuid'));

        // Set and execute the query
        $db->setQuery($query);
        $total = (int) $db->loadResult(); // Load the result as an integer
        
        return [
            "total" => $total
        ];
    }
}
