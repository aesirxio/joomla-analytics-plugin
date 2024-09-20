<?php


use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Get_All_Events extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get Joomla database object
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();
        $bind = [];

        // Validate and sanitize each parameter in the $params array
        $validated_params = [];
        foreach ($params as $key => $value) {
            $validated_params[$key] = $inputFilter->clean($value, 'STRING');
        }

        // Where clauses and bindings
        $where_clause = [
            $db->quoteName('#__analytics_events.event_name') . ' = ' . $db->quote('visit'),
            $db->quoteName('#__analytics_events.event_type') . ' = ' . $db->quote('action')
        ];

        parent::aesirx_analytics_add_filters($validated_params, $where_clause, $bind);

        // Build SQL query
        $sql = $db->getQuery(true)->select([
                "DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d') as date",
                "COUNT(" . $db->quoteName('#__analytics_events.visitor_uuid') . ") as visits",
                "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.visitor_uuid') . ") as unique_visits"
            ])
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(" AND ", $where_clause))
            ->group($db->quoteName('date'));

        // Total query
        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(" . $db->quoteName('start') . ", '%Y-%m-%d')) as total")
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(" AND ", $where_clause));

        $sort = self::aesirx_analytics_add_sort($validated_params, ["date", "unique_visits", "visits"], "date");

        if (!empty($sort)) {
            $sql->order(implode(", ", $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $validated_params, [], $bind);
    }
}
