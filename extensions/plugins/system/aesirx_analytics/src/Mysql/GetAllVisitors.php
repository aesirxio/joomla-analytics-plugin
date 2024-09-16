<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory; 

Class AesirX_Analytics_Get_All_Visitors extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        // Initialize where clauses and bind parameters
        $where_clause = [
            $db->quoteName('#__analytics_events.event_name') . " = " . $db->quote('visit'),
            $db->quoteName('#__analytics_events.event_type') . " = " . $db->quote('action')
        ];
        $bind = [];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        $sql = $db->getQuery(true)
            ->select([
                "DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d') AS date",
                "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.visitor_uuid') . ") AS visits",
                "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.url') . ") AS total_page_views"
            ])
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('date'));

        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d')) AS total")
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort($params, ["date", "visits", "total_page_views"], "date");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
