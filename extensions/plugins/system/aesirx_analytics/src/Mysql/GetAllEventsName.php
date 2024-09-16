<?php


use AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Get_All_Events_Name extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        $where_clause = [
            $db->quoteName('#__analytics_events.event_name') . ' = ' . $db->quote('visit'),
            $db->quoteName('#__analytics_events.event_type') . ' = ' . $db->quote('action'),
        ];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);

        // SQL query to get the events data
        $sql = $db->getQuery(true)
            ->select([
                "DATE_FORMAT(start, '%Y-%m-%d') AS date",
                $db->quoteName('#__analytics_events.event_name'),
                $db->quoteName('#__analytics_events.event_type'),
                "COUNT(DISTINCT " . $db->quoteName('#__analytics_events.visitor_uuid') . ") AS total_visitor"
            ])
            ->from($db->quoteName('#__analytics_events'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_event_attributes') . ' ON ' . $db->quoteName('#__analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('#__analytics_events.uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('date') . ', ' . $db->quoteName('#__analytics_events.event_name') . ', ' . $db->quoteName('#__analytics_events.event_type'));

        // SQL query to get the total number of records
        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT DATE_FORMAT(start, '%Y-%m-%d'), " . $db->quoteName('#__analytics_events.event_name') . ', ' . $db->quoteName('#__analytics_events.event_type') . ") AS total")
            ->from($db->quoteName('#__analytics_events'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_event_attributes') . ' ON ' . $db->quoteName('#__analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('#__analytics_events.uuid'))
            ->where(implode(' AND ', $where_clause));

         // Add sorting if applicable
        $sort = parent::aesirx_analytics_add_sort($params, ["date", "event_name", "total_visitor", "event_type"], "date");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
