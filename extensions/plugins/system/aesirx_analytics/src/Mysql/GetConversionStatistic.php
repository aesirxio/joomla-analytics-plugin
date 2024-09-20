<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Conversion_Statistic extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_conversion_filters($params, $where_clause, $bind);

        $sql = $db->getQuery(true)
            ->select([
                "CASE WHEN SUM(" . $db->quoteName('revenue_total') . ") IS NOT NULL THEN CAST(SUM(" . $db->quoteName('revenue_total') . ") AS FLOAT) ELSE 0 END AS " . $db->quoteName('total_revenue'),
                "CASE WHEN SUM(" . $db->quoteName('revenue_subtotal') . ") IS NOT NULL THEN CAST(SUM(" . $db->quoteName('revenue_subtotal') . ") AS FLOAT) ELSE 0 END AS " . $db->quoteName('conversion_rate'),
                "CASE WHEN SUM(" . $db->quoteName('revenue_total') . ") IS NOT NULL THEN CAST(AVG(" . $db->quoteName('revenue_total') . ") AS FLOAT) ELSE 0 END AS " . $db->quoteName('avg_order_value'),
                "CASE WHEN SUM(" . $db->quoteName('quantity') . ") IS NOT NULL THEN CAST(SUM(CASE WHEN " . $db->quoteName('order_id') . " IS NOT NULL THEN " . $db->quoteName('quantity') . " ELSE 0 END) AS FLOAT) ELSE 0 END AS " . $db->quoteName('total_add_to_carts'),
                "CAST(COUNT(" . $db->quoteName('#__analytics_conversion.uuid') . ") AS FLOAT) AS " . $db->quoteName('transactions')
            ])
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause));

        $total_sql = $db->getQuery(true)
        ->select("COUNT(DISTINCT " . $db->quoteName('name') . ") AS " . $db->quoteName('total'))
        ->from($db->quoteName('#__analytics_conversion'))
        ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
        ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
        ->where(implode(' AND ', $where_clause));

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
