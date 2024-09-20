<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Conversion_Product_Chart extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_conversion_filters($params, $where_clause, $bind);

         // Main SQL query to fetch data
        $sql = $db->getQuery(true)
            ->select([
                "DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d') AS " . $db->quoteName('date'),
                "SUM(" . $db->quoteName('quantity') . ") DIV 1 AS " . $db->quoteName('quantity')
            ])
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group( $db->quoteName('date'));

        // Total count query to count distinct dates
        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d')) AS total")
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort($params, ["date", "quantity"], "date");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }
        
        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
