<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Conversion_Statistic_Chart extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_conversion_filters($params, $where_clause, $bind);

        $sql = $db->getQuery(true)
            ->select([
                "DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d') AS " . $db->quoteName('date'),
                "CAST(SUM(" . $db->quoteName('revenue_total') . ") AS FLOAT) AS " . $db->quoteName('total_revenue'),
                "CAST(SUM(CASE WHEN " . $db->quoteName('order_id') . " IS NOT NULL THEN 1 ELSE 0 END) AS FLOAT) AS " . $db->quoteName('total_purchasers')
            ])
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('date'));


        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d')) AS " . $db->quoteName('total'))
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort($params, ["date", "total_revenue", "total_purchasers"], "date");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
