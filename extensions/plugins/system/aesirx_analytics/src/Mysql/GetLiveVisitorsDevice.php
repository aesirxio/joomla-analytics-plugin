<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Live_Visitors_Device extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        unset($params["filter"]["start"]);
        unset($params["filter"]["end"]);
    
        // Build the SELECT statement
        $select = [
            "COALESCE(COUNT(DISTINCT " . $db->quoteName('#__analytics_events.visitor_uuid') . "), 0) AS number_of_visitors",
            "COALESCE(COUNT(" . $db->quoteName('#__analytics_events.visitor_uuid') . "), 0) AS total_number_of_visitors",
            "COUNT(" . $db->quoteName('#__analytics_events.uuid') . ") AS number_of_page_views",
            "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.url') . ") AS number_of_unique_page_views",
            "COALESCE(SUM(TIMESTAMPDIFF(SECOND, " . $db->quoteName('#__analytics_events.start') . ", " . $db->quoteName('#__analytics_events.end') . ")) / COUNT(DISTINCT " . $db->quoteName('#__analytics_visitors.uuid') . "), 0) DIV 1 AS average_session_duration",
            "COALESCE((COUNT(" . $db->quoteName('#__analytics_events.uuid') . ") / COUNT(DISTINCT " . $db->quoteName('#__analytics_events.flow_uuid') . ")), 0) DIV 1 AS average_number_of_pages_per_session",
            "COALESCE((COUNT(DISTINCT CASE WHEN " . $db->quoteName('#__analytics_flows.multiple_events') . " = 0 THEN " . $db->quoteName('#__analytics_flows.uuid') . " END) * 100) / COUNT(DISTINCT " . $db->quoteName('#__analytics_flows.uuid') . "), 0) DIV 1 AS bounce_rate"
        ];

        $total_select = [];
        $bind = [];

        // Grouping criteria
        $groups = [$db->quoteName('#__analytics_visitors.device')];

        if (!empty($groups)) {
            foreach ($groups as $one_group) {
                $select[] = $one_group;
            }
            $total_select[] = "COUNT(DISTINCT " . implode(', COALESCE(', $groups) . ") AS total";
        } else {
            $total_select[] = "COUNT(" . $db->quoteName('#__analytics_events.uuid') . ") AS total";
        }

        // Building WHERE clause
        $where_clause = [
            $db->quoteName('#__analytics_events.event_name') . ' = ' . $db->quote('visit'),
            $db->quoteName('#__analytics_events.event_type') . ' = ' . $db->quote('action'),
            $db->quoteName('#__analytics_events.start') . ' = ' . $db->quoteName('#__analytics_events.end'),
            $db->quoteName('#__analytics_events.start') . ' >= NOW() - INTERVAL 30 MINUTE',
            $db->quoteName('#__analytics_visitors.device') . " != 'bot'"
        ];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        // Building total SQL query
        $total_sql = $db->getQuery(true)
            ->select(implode(", ", $total_select))
            ->from($db->quoteName('#__analytics_events'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_flows.uuid') . ' = ' . $db->quoteName('#__analytics_events.flow_uuid'))
            ->where(implode(" AND ", $where_clause));

        // Building main SQL query
        $sql = $db->getQuery(true)
            ->select(implode(", ", $select))
            ->from($db->quoteName('#__analytics_events'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_flows.uuid') . ' = ' . $db->quoteName('#__analytics_events.flow_uuid'))
            ->where(implode(" AND ", $where_clause));

        // Add GROUP BY clause if needed
        if (!empty($groups)) {
            $sql->group(implode(", ", $groups));
        }

        $allowed = [
            "number_of_visitors",
            "number_of_page_views",
            "number_of_unique_page_views",
            "average_session_duration",
            "average_number_of_pages_per_session",
            "bounce_rate",
        ];
        $default = reset($allowed);

        foreach ($groups as $one_group) {
            $allowed[] = $one_group;
            $default = $one_group;
        }

        foreach ($groups as $additional_result) {
            $allowed[] = $additional_result;
        }

        $sort = parent::aesirx_analytics_add_sort($params, $allowed, $default);

        if (!empty($sort)) {
            $sql->order(implode(", ", $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, $allowed, $bind);
    }
}
