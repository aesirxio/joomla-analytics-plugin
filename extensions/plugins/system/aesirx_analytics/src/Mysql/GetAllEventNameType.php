<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_All_Event_Name_Type extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get the Joomla database object
        $db = Factory::getDbo();

        // Initializing the where clause and bindings
        $where_clause = [];
        $bind = [];

        // Add filters (assuming the helper method is Joomla-compatible)
        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        // Main SQL query to retrieve event name, event type, total visitors, and unique visitors
         $sql = $db->getQuery(true)
            ->select([
                $db->quoteName('#__analytics_events.event_name'),
                $db->quoteName('#__analytics_events.event_type'),
                'COUNT(' . $db->quoteName('#__analytics_events.uuid') . ') as total_visitor',
                'COUNT(DISTINCT ' . $db->quoteName('#__analytics_events.visitor_uuid') . ') as unique_visitor'
            ])
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(" AND ", $where_clause))
            ->group($db->quoteName(['#__analytics_events.event_name', '#__analytics_events.event_type']));

        // Building the total SQL query
        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName(['#__analytics_events.event_name', '#__analytics_events.event_type']) . ') as total')
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(" AND ", $where_clause));

        // Sorting (assuming this helper method is Joomla-compatible)
        $sort = self::aesirx_analytics_add_sort($params, ['event_name', 'total_visitor', 'event_type', 'unique_visitor'], 'event_name');
        
        if (!empty($sort)) {
            $sql->order(implode(", ", $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
