<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Attribute_Value_Date extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);

        // Total SQL query
        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('analytics_event_attributes.name') . ', DATE_FORMAT(' . $db->quoteName('analytics_events.start') . ', "%Y-%m-%d")) as total')
            ->from($db->quoteName('#__analytics_event_attributes'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('analytics_events.uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('analytics_visitors.uuid') . ' = ' . $db->quoteName('analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause));

        // Main SQL query
        $sql = $db->getQuery(true)
            ->select([
                $db->quoteName('analytics_event_attributes.name'),
                'DATE_FORMAT(' . $db->quoteName('analytics_events.start') . ', "%Y-%m-%d") as date'
            ])
            ->from($db->quoteName('#__analytics_event_attributes'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('analytics_events.uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('analytics_visitors.uuid') . ' = ' . $db->quoteName('analytics_events.visitor_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group([$db->quoteName('analytics_event_attributes.name'), 'date']);

        // Add sorting if any
        $sort = self::aesirx_analytics_add_sort($params, ['name', 'date'], 'date');
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
            $names = array_map(function($e) {
                return $e['name'];
            }, $list);

            // Second query based on names
            $secondQuery = $db->getQuery(true)
                ->select([
                    'DATE_FORMAT(' . $db->quoteName('analytics_events.start') . ', "%Y-%m-%d") as date',
                    $db->quoteName('analytics_event_attributes.name'),
                    $db->quoteName('analytics_event_attributes.value'),
                    'COUNT(' . $db->quoteName('analytics_event_attributes.id') . ') as count'
                ])
                ->from($db->quoteName('#__analytics_event_attributes'))
                ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('analytics_events.uuid'))
                ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('analytics_visitors.uuid') . ' = ' . $db->quoteName('analytics_events.visitor_uuid'))
                ->where($db->quoteName('analytics_event_attributes.name') . ' IN (' . implode(',', array_map([$db, 'quote'], $names)) . ')')
                ->group([$db->quoteName('analytics_event_attributes.name'), $db->quoteName('analytics_event_attributes.value')]);

            $db->setQuery($secondQuery);
            $secondArray = $db->loadObjectList();

            foreach ($secondArray as $second) {
                $key_string = $second->date . '-' . $second->name;

                if (!isset($hash_map[$key_string])) {
                    $sub_hash = [];
                    $sub_hash[$second->value] = $second->count;
                    $hash_map[$key_string] = $sub_hash;
                } else {
                    $hash_map[$key_string][$second->value] = $second->count;
                }
            }
            
            $collection = [];
            
            foreach ($hash_map as $key_string => $vals) {
                $vals_vec = [];

                foreach ($vals as $key_val => $val_val) {
                    $vals_vec[] = (object)[
                        'value' => $key_val,
                        'count' => $val_val,
                    ];
                }

                $key = explode('-', $key_string);

                $collection[] = (object)[
                    'date' => $key[0],
                    'name' => $key[1],
                    'values' => $vals_vec
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
