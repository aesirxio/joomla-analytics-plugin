<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory; 

Class AesirX_Analytics_Get_All_Outlinks extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [$db->quoteName('#__analytics_events.referer') . " LIKE '%//%'"];
        $bind = [];

        // Handle acquisition filter
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
            $where_clause[] = $db->quoteName('#__analytics_events.referer') . " LIKE '%google.%'";
            $where_clause[] = $db->quoteName('#__analytics_events.referer') . " LIKE '%bing.%'";
            $where_clause[] = $db->quoteName('#__analytics_events.referer') . " LIKE '%yandex.%'";
            $where_clause[] = $db->quoteName('#__analytics_events.referer') . " LIKE '%yahoo.%'";
            $where_clause[] = $db->quoteName('#__analytics_events.referer') . " LIKE '%duckduckgo.%'";
        }

        // Call parent method to add more filters if necessary
        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

         // Prepare main query
         $sql = $db->getQuery(true)
            ->select([
                "SUBSTRING_INDEX(SUBSTRING_INDEX(" . $db->quoteName('referer') . ", '://', -1), '/', 1) AS referer",
                "COUNT(" . $db->quoteName('#__analytics_events.visitor_uuid') . ") AS total_number_of_visitors",
                "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.visitor_uuid') . ") AS number_of_visitors",
                "COUNT(" . $db->quoteName('referer') . ") AS total_urls"
            ])
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('referer'));

        $total_sql =$db->getQuery(true)
            ->select("COUNT(DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(" . $db->quoteName('referer') . ", '://', -1), '/', 1)) AS total")
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort(
            $params,
            [
                "referer",
                "number_of_visitors",
                "total_number_of_visitors",
                "urls",
                "total_urls",
            ],
            "referer"
        );

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        $list_response = parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);

        if ($list_response instanceof Exception) {
            return $list_response;
        }

        $list = $list_response['collection'];

        $collection = [];

        if ($list) {
            foreach ($list as $vals) {
                if ($vals['referer'] == null) {
                    continue;
                }

                // Second query to get specific URL details
                $secondQuery = $db->getQuery(true)
                    ->select([
                        $db->quoteName('referer') . ' AS url',
                        "COUNT(" . $db->quoteName('#__analytics_events.visitor_uuid') . ") AS total_number_of_visitors",
                        "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.visitor_uuid') . ") AS number_of_visitors"
                    ])
                    ->from($db->quoteName('#__analytics_events'))
                    ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
                    ->where($db->quoteName('referer') . ' LIKE ' . $db->quote('%' . $vals['referer'] . '%'))
                    ->group($db->quoteName('referer'));

                // Execute second query and get results
                $db->setQuery($secondQuery);
                $urls = $db->loadAssocList();

                $collection[] = [
                    "referer" => $vals['referer'],
                    "urls" => $urls,
                    "total_number_of_visitors" => $vals['total_number_of_visitors'],
                    "number_of_visitors" => $vals['number_of_visitors'],
                    "total_urls" => $vals['total_urls'],
                ];
            }
        }

        return [
            'collection' => $collection,
            'page' => $list_response['page'],
            'page_size' => $list_response['page_size'],
            'total_pages' => $list_response['total_pages'],
            'total_elements' => $list_response['total_elements'],
        ];
    }
}
