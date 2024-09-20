<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_All_Flows_Date extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $where_clause = [];
        $bind = [];
        
        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        // Initialize the database object
        $db = Factory::getDbo();

        $sql = $db->getQuery(true)
            ->select([
                "DATE_FORMAT(#__analytics_events.start, '%Y-%m-%d') AS date",
                "CAST(SUM(CASE WHEN #__analytics_events.event_type = 'conversion' THEN 1 ELSE 0 END) AS SIGNED) AS conversion",
                "CAST(SUM(CASE WHEN #__analytics_events.event_name = 'visit' THEN 1 ELSE 0 END) AS SIGNED) AS pageview",
                "CAST(SUM(CASE WHEN #__analytics_events.event_name != 'visit' THEN 1 ELSE 0 END) AS SIGNED) AS event"
            ])
            ->from($db->quoteName('#__analytics_events'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('date'));

        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(#__analytics_events.start, '%Y-%m-%d')) AS total")
            ->from($db->quoteName('#__analytics_events'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = parent::aesirx_analytics_add_sort($params, ["date", "event", "conversion"], "date");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
