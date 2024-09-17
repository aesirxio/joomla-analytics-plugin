<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_All_Channels extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get Joomla database object
        $db = Factory::getDbo();
        $where_clause = [];
        $bind = [];

        $select = [
            "CASE 
                WHEN #__analytics_events.referer IS NOT NULL AND #__analytics_events.referer <> '' THEN
                    CASE 
                        WHEN #__analytics_events.referer REGEXP 'google\\.' THEN 'search'
                        WHEN #__analytics_events.referer REGEXP 'bing\\.' THEN 'search'
                        WHEN #__analytics_events.referer REGEXP 'yandex\\.' THEN 'search'
                        WHEN #__analytics_events.referer REGEXP 'yahoo\\.' THEN 'search'
                        WHEN #__analytics_events.referer REGEXP 'duckduckgo\\.' THEN 'search'
                        ELSE 'referer'
                    END
                ELSE 'direct'
            END as channel",
            "COALESCE(COUNT(DISTINCT (#__analytics_events.visitor_uuid)), 0) AS number_of_visitors",
            "COALESCE(COUNT(#__analytics_events.visitor_uuid), 0) AS total_number_of_visitors",
            "COUNT(#__analytics_events.uuid) AS number_of_page_views",
            "COUNT(DISTINCT (#__analytics_events.url)) AS number_of_unique_page_views",
            "COALESCE(SUM(TIMESTAMPDIFF(SECOND, #__analytics_events.start, #__analytics_events.end)) / COUNT(DISTINCT #__analytics_visitors.uuid), 0) DIV 1 AS average_session_duration",
            "COALESCE((COUNT(#__analytics_events.uuid) / COUNT(DISTINCT (#__analytics_events.flow_uuid))), 0) DIV 1 AS average_number_of_pages_per_session",
            "COALESCE((COUNT(DISTINCT CASE WHEN #__analytics_flows.multiple_events = 0 THEN #__analytics_flows.uuid END) * 100) / COUNT(DISTINCT (#__analytics_flows.uuid)), 0) DIV 1 AS bounce_rate",
        ];

        // Add custom filters
        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        // Acquisition filter
        $acquisition = false;
        foreach ($params['filter'] as $key => $vals) {
            if ($key === "acquisition") {
                $list = is_array($vals) ? $vals : [$vals];
                if ($list[0] === "true") {
                    $acquisition = true;
                }
                break;
            }
        }

        if ($acquisition) {
            $where_clause[] = "#__analytics_flows.multiple_events = 0";
        }

         // Build SQL query
         $sql = "SELECT " . 
         implode(", ", $select) .
         " FROM #__analytics_events
         LEFT JOIN #__analytics_visitors ON #__analytics_visitors.uuid = #__analytics_events.visitor_uuid
         LEFT JOIN #__analytics_flows ON #__analytics_flows.uuid = #__analytics_events.flow_uuid
         WHERE " . implode(" AND ", $where_clause) . 
         " GROUP BY channel";

        // Build the total query
        $total_select = "3 AS total";
        $total_sql = 
            "SELECT " . 
            $total_select . 
            " FROM #__analytics_events
            LEFT JOIN #__analytics_visitors ON #__analytics_visitors.uuid = #__analytics_events.visitor_uuid
            LEFT JOIN #__analytics_flows ON #__analytics_flows.uuid = #__analytics_events.flow_uuid
            WHERE " . implode(" AND ", $where_clause);

        // Allowed sorting columns
        $allowed = [
            "number_of_visitors",
            "number_of_page_views",
            "number_of_unique_page_views",
            "average_session_duration",
            "average_number_of_pages_per_session",
            "bounce_rate",
        ];

        // Add sorting
        $sort = self::aesirx_analytics_add_sort($params, $allowed, "channel");
        if (!empty($sort)) {
            $sql->order(implode(", ", $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, $allowed, $bind);
    }
}
