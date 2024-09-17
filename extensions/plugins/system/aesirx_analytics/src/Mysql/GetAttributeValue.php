<?php


use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory; 

Class AesirX_Analytics_Get_Attribute_Value extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        
        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);

        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('analytics_event_attributes.name') . ') as total')
            ->from($db->quoteName('#__analytics_event_attributes'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('#__analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('#__analytics_events.uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause));

        $sql = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('analytics_event_attributes.name'))
            ->from($db->quoteName('#__analytics_event_attributes'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('#__analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('#__analytics_events.uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = parent::aesirx_analytics_add_sort($params, ["name"], "name");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        $listResponse = parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);

        if ($listResponse instanceof Exception) {
            return $listResponse;
        }

        $list = $listResponse['collection'];

        $collection = [];

        if ($list) {

            $names = array_map(function($e) {
                return $e['name'];
            }, $list);

            $secondQuery = $db->getQuery(true);
            $secondQuery->select([
                $db->quoteName('analytics_event_attributes.name'),
                $db->quoteName('analytics_event_attributes.value'),
                'COUNT(' . $db->quoteName('analytics_event_attributes.id') . ') AS count',
                'COALESCE(COUNT(DISTINCT ' . $db->quoteName('analytics_events.visitor_uuid') . '), 0) AS number_of_visitors',
                'COALESCE(COUNT(' . $db->quoteName('analytics_events.visitor_uuid') . '), 0) AS total_number_of_visitors',
                'COUNT(' . $db->quoteName('analytics_events.uuid') . ') AS number_of_page_views',
                'COUNT(DISTINCT ' . $db->quoteName('analytics_events.url') . ') AS number_of_unique_page_views',
                'COALESCE(SUM(TIMESTAMPDIFF(SECOND, ' . $db->quoteName('analytics_events.start') . ', ' . $db->quoteName('analytics_events.end') . ')) / COUNT(DISTINCT ' . $db->quoteName('analytics_visitors.uuid') . '), 0) DIV 1 AS average_session_duration',
                'COALESCE(COUNT(' . $db->quoteName('analytics_events.uuid') . ') / COUNT(DISTINCT ' . $db->quoteName('analytics_events.flow_uuid') . '), 0) DIV 1 AS average_number_of_pages_per_session',
                'COALESCE((COUNT(DISTINCT CASE WHEN ' . $db->quoteName('analytics_flows.multiple_events') . ' = 0 THEN ' . $db->quoteName('analytics_flows.uuid') . ' END) * 100) / COUNT(DISTINCT ' . $db->quoteName('analytics_flows.uuid') . '), 0) DIV 1 AS bounce_rate'
            ])
            ->from($db->quoteName('#__analytics_event_attributes'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('analytics_events.uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('analytics_visitors.uuid') . ' = ' . $db->quoteName('analytics_events.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('analytics_flows.uuid') . ' = ' . $db->quoteName('analytics_events.flow_uuid'))
            ->where($db->quoteName('analytics_event_attributes.name') . ' IN (' . implode(',', array_map([$db, 'quote'], $names)) . ')')
            ->group([$db->quoteName('analytics_event_attributes.name'), $db->quoteName('analytics_event_attributes.value')]);

            $db->setQuery($secondQuery);
            $secondArray = $db->loadObjectList();

            $hash_map = [];

            foreach ($secondArray as $second) {
                $name = $second->name;
                $value = $second->value;
                $count = $second->count;

                if (!isset($hash_map[$name])) {
                    $sub_hash = [];
                    $sub_hash[$value] = $count;
                    $sub_hash['number_of_visitors'] = $second->number_of_visitors;
                    $sub_hash['number_of_page_views'] = $second->number_of_page_views;
                    $sub_hash['number_of_unique_page_views'] = $second->number_of_unique_page_views;
                    $sub_hash['average_session_duration'] = $second->average_session_duration;
                    $sub_hash['average_number_of_pages_per_session'] = $second->average_number_of_pages_per_session;
                    $sub_hash['bounce_rate'] = $second->bounce_rate;

                    $hash_map[$name] = $sub_hash;
                } else {
                    $hash_map[$name][$value] = $count;
                }
            }

            // Prepare final collection array
            $not_allowed = [
                "number_of_visitors",
                "number_of_page_views",
                "number_of_unique_page_views",
                "average_session_duration",
                "average_number_of_pages_per_session",
                "bounce_rate"
            ];

            $collection = [];

            foreach ($hash_map as $key => $vals) {
                $vals_vec = [];
                foreach ($vals as $key_val => $val_val) {
                    if (in_array($key_val, $not_allowed)) {
                        continue;
                    }

                    $vals_vec[] = (object)[
                        'value' => $key_val,
                        'count' => $val_val,
                        'number_of_visitors' => $vals['number_of_visitors'],
                        'number_of_page_views' => $vals['number_of_page_views'],
                        'number_of_unique_page_views' => $vals['number_of_unique_page_views'],
                        'average_session_duration' => $vals['average_session_duration'],
                        'average_number_of_pages_per_session' => $vals['average_number_of_pages_per_session'],
                        'bounce_rate' => $vals['bounce_rate']
                    ];
                }

                $collection[] = (object)[
                    'name' => $key,
                    'values' => $vals_vec
                ];
            }
        }

        return [
            'collection' => $collection,
            'page' => $listResponse['page'],
            'page_size' => $listResponse['page_size'],
            'total_pages' => $listResponse['total_pages'],
            'total_elements' => $listResponse['total_elements'],
        ];
    }
}
