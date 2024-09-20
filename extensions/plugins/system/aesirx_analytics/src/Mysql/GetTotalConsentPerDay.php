<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Total_Consent_Per_Day extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_consent_filters($params, $where_clause, $bind);

        // Build the SQL query for retrieving the total count and date
        $sql = $db->getQuery(true)
            ->select([
                "ROUND(COUNT(" . $db->quoteName('visitor_consent.uuid') . ") / 2) AS total",
                "DATE_FORMAT(" . $db->quoteName('visitor_consent.datetime') . ", '%Y-%m-%d') AS `date`"
            ])
            ->from($db->quoteName('#__analytics_visitor_consent', 'visitor_consent'))
            ->leftJoin($db->quoteName('#__analytics_visitors', 'visitors') . ' ON ' . $db->quoteName('visitors.uuid') . ' = ' . $db->quoteName('visitor_consent.visitor_uuid'))
            ->where(implode(" AND ", $where_clause))
            ->group($db->quoteName('date'));

        // Build the total SQL query to get the total number of distinct dates
        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(" . $db->quoteName('visitor_consent.datetime') . ", '%Y-%m-%d')) AS total")
            ->from($db->quoteName('#__analytics_visitor_consent', 'visitor_consent'))
            ->leftJoin($db->quoteName('#__analytics_visitors', 'visitors') . ' ON ' . $db->quoteName('visitors.uuid') . ' = ' . $db->quoteName('visitor_consent.visitor_uuid'))
            ->where(implode(" AND ", $where_clause));

        $sort = self::aesirx_analytics_add_sort($params, ["date", "total"], "date");

        if (!empty($sort)) {
            $sql->order(implode(", ", $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
