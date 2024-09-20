<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Conversion_Product extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_conversion_filters($params, $where_clause, $bind);

        $sql = $db->getQuery(true)
            ->select([
                $db->quoteName('name', 'product'),
                $db->quoteName('sku'),
                $db->quoteName('extension'),
                'SUM(' . $db->quoteName('quantity') . ') DIV 1 as quantity',
                'COUNT(' . $db->quoteName('quantity') . ') as items_sold',
                'SUM(' . $db->quoteName('revenue_subtotal') . ') DIV 1 as product_revenue',
                'CAST(AVG(' . $db->quoteName('price') . ') as FLOAT) as avg_price',
                'CAST((SUM(' . $db->quoteName('quantity') . ') / COUNT(' . $db->quoteName('quantity') . ')) as FLOAT) as avg_quantity'
            ])
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group([$db->quoteName('name'), $db->quoteName('sku'), $db->quoteName('extension')]);

        // Total count query
        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('name') . ', ' . $db->quoteName('sku') . ', ' . $db->quoteName('extension') . ') as total')
            ->from($db->quoteName('#__analytics_conversion'))
            ->join('LEFT', $db->quoteName('#__analytics_conversion_item') . ' ON ' . $db->quoteName('#__analytics_conversion.uuid') . ' = ' . $db->quoteName('#__analytics_conversion_item.conversion_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_conversion.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort(
            $params,
            [
                "product",
                "sku",
                "extension",
                "quantity",
                "items_sold",
                "product_revenue",
                "avg_price",
                "avg_quantity",
            ],
            "quantity"
        );

        // Add sorting to SQL if exists
        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
