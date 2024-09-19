<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_List_Events extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);
        parent::aesirx_analytics_add_attribute_filters($params, $where_clause, $bind);

        foreach ([$params['filter'] ?? null, $params['filter_not'] ?? null] as $filter_array) {
            $is_not = $filter_array === ($params['filter_not'] ?? null);
            if (empty($filter_array)) {
                continue;
            }

            foreach ($filter_array as $key => $vals) {
                $list = is_array($vals) ? $vals : [$vals];

                switch ($key) {
                    case 'visitor_uuid':
                    case 'flow_uuid':
                    case 'uuid':
                        $where_clause[] = $db->quoteName('#__analytics_events.' . $key) . ' ' . ($is_not ? 'NOT ' : '') . 'IN (' . implode(', ', array_map([$db, 'quote'], $list)) . ')';
                        break;
                    default:
                        break;
                }
            }
        }

        // SQL for total count
        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('#__analytics_events.uuid') . ') as total')
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->leftJoin($db->quoteName('#__analytics_event_attributes') . ' ON ' . $db->quoteName('#__analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('#__analytics_events.uuid'))
            ->where(implode(' AND ', $where_clause));

        // Main SQL query
        $sql = $db->getQuery(true)
            ->select(['#__analytics_events.*', $db->quoteName('#__analytics_visitors.domain')])
            ->from($db->quoteName('#__analytics_events'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
            ->leftJoin($db->quoteName('#__analytics_event_attributes') . ' ON ' . $db->quoteName('#__analytics_event_attributes.event_uuid') . ' = ' . $db->quoteName('#__analytics_events.uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort(
            $params,
            [
                "start",
                "end",
                "url",
                "event_name",
                "event_type",
                "domain",
                "referrer",
            ],
            "start"
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
            $event_attribute_bind = array_map(function($e) {
                return $e['uuid'];
            }, $list);
            
            // Fetching related event attributes
            $query_event_attributes = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_event_attributes'))
                ->where($db->quoteName('event_uuid') . ' IN (' . implode(', ', array_map([$db, 'quote'], $event_attribute_bind)) . ')');

            $db->setQuery($query_event_attributes);
            $secondArray = $db->loadObjectList();

            $hash_map = [];

            foreach ($secondArray as $second) {
                $name = $second->name;
                $event_uuid = $second->event_uuid;
                $value = $second->value;

                if (!isset($hash_map[$event_uuid])) {
                    $sub_hash = [];
                    $sub_hash[$name] = $value;
                    $hash_map[$event_uuid] = $sub_hash;
                } else {
                    $hash_map[$event_uuid][$name] = $value;
                }
            }

            $collection = [];

            // Construct the collection
            foreach ($list as $item) {
                $item = (object) $item;
                $attributes = [];

                if (isset($hash_map[$item->uuid])) {
                    foreach ($hash_map[$item->uuid] as $attr_name => $attr_val) {
                        $attributes[] = (object)[
                            'name' => $attr_name,
                            'value' => $attr_val,
                        ];
                    }
                }

                $collection[] = (object)[
                    'uuid' => $item->uuid,
                    'visitor_uuid' => $item->visitor_uuid,
                    'flow_uuid' => $item->flow_uuid,
                    'url' => $item->url,
                    'domain' => $item->domain,
                    'referer' => $item->referer,
                    'start' => $item->start,
                    'end' => $item->end,
                    'event_name' => $item->event_name,
                    'event_type' => $item->event_type,
                    'attributes' => !empty($attributes) ? $attributes : null,
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
