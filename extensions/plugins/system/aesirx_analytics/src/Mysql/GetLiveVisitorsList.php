<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Live_Visitors_List extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        // Prepare the WHERE clauses
        $where_clause = [
            $db->quoteName('#__analytics_flows.start') . ' = ' . $db->quoteName('#__analytics_flows.end'),
            $db->quoteName('#__analytics_flows.start') . ' >= NOW() - INTERVAL 30 MINUTE',
            $db->quoteName('#__analytics_visitors.device') . ' != ' . $db->quote('bot')
        ];
        $bind = [];

        unset($params["filter"]["start"]);
        unset($params["filter"]["end"]);

        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        // filters where clause for events

        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('#__analytics_flows.uuid') . ') AS total')
            ->from($db->quoteName('#__analytics_flows'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_flows.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('#__analytics_events.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('#__analytics_flows.visitor_uuid'));

        // Main SQL query to fetch the flow data and event attributes
        $sql = $db->getQuery(true)
            ->select([
                $db->quoteName('#__analytics_flows') . '.*',
                $db->quoteName('ip'),
                $db->quoteName('user_agent'),
                $db->quoteName('device'),
                $db->quoteName('browser_name'),
                $db->quoteName('browser_version'),
                $db->quoteName('domain'),
                $db->quoteName('lang'),
                $db->quoteName('city'),
                $db->quoteName('isp'),
                $db->quoteName('country_name'),
                $db->quoteName('country_code'),
                $db->quoteName('geo_created_at'),
                $db->quoteName('#__analytics_visitors.uuid', 'visitor_uuid'),
                'MAX(CASE WHEN ' . $db->quoteName('#__analytics_event_attributes.name') . ' = ' . $db->quote('sop_id') . ' THEN ' . $db->quoteName('#__analytics_event_attributes.value') . ' ELSE NULL END) AS sop_id',
                $db->quoteName('#__analytics_events.url', 'url')
            ])
            ->from($db->quoteName('#__analytics_flows'))
            ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_flows.visitor_uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('#__analytics_events.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->join('LEFT', $db->quoteName('#__analytics_event_attributes') . ' ON ' . $db->quoteName('#__analytics_events.uuid') . ' = ' . $db->quoteName('#__analytics_event_attributes.event_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('#__analytics_flows.visitor_uuid'));

        $sort = parent::aesirx_analytics_add_sort(
            $params,
            [
                "start",
                "end",
                "geo.country.name",
                "geo.country.code",
                "ip",
                "device",
                "browser_name",
                "browser_version",
                "domain",
                "lang",
                "url",
                "sop_id",
            ],
            "start",
        );

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        $list_response = parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);

        if ($list_response instanceof Exception) {
            return $list_response;
        }

        $list = $list_response['collection'];

        if (!empty($list)) {
            $collection = [];

            $ret = [];
            $dirs = [];

            $bind = array_map(function($e) {
                return $e['uuid'];
            }, $list);

            $queryEvents = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_events'))
                ->where($db->quoteName('flow_uuid') . ' IN (' . implode(',', array_map([$db, 'quote'], $bind)) . ')');

            // Set the query and execute it to get events
            $db->setQuery($queryEvents);
            $events = $db->loadObjectList();

            $queryAttributes = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_event_attributes', 'attr'))
                ->join('LEFT', $db->quoteName('#__analytics_events', 'evt') . ' ON ' . $db->quoteName('evt.uuid') . ' = ' . $db->quoteName('attr.event_uuid'))
                ->where($db->quoteName('evt.flow_uuid') . ' IN (' . implode(',', array_map([$db, 'quote'], $bind)) . ')');

            // Set the query
            $db->setQuery($queryAttributes);

            // Execute the query and get the results
            $attributes = $db->loadObjectList(); 
            
            $hash_attributes = [];

            foreach ($attributes as $second) {
                $attr = (object)[
                    'name' => $second->name,
                    'value' => $second->value,
                ];
                if (!isset($hash_attributes[$second->event_uuid])) {
                    $hash_attributes[$second->event_uuid] = [$attr];
                } else {
                    $hash_attributes[$second->event_uuid][] = $attr;
                }
            }

            $hash_map = [];

            foreach ($events as $second) {
                $visitor_event = [
                    'uuid' => $second->uuid,
                    'visitor_uuid' => $second->visitor_uuid,
                    'flow_uuid' => $second->flow_uuid,
                    'url' => $second->url,
                    'referer' => $second->referer,
                    'start' => $second->start,
                    'end' => $second->end,
                    'event_name' => $second->event_name,
                    'event_type' => $second->event_type,
                    'attributes' => $hash_attributes[$second->uuid] ?? [],
                ];

                if (!isset($hash_map[$second->flow_uuid])) {
                    $hash_map[$second->flow_uuid] = [$second->uuid => $visitor_event];
                } else {
                    $hash_map[$second->flow_uuid][$second->uuid] = $visitor_event;
                }
            }

            foreach ($list as $item) {
                $item = (object) $item;
                
                if (!empty($collection) && end($collection)['uuid'] == $item->uuid) {
                    continue;
                }

                $geo = isset($item->geo_created_at) ? (object)[
                    'country' => (object)[
                        'name' => $item->country_name,
                        'code' => $item->country_code,
                    ],
                    'city' => $item->city,
                    'isp' => $item->isp,
                    'created_at' => $item->geo_created_at,
                ] : null;

                $events = isset($hash_map[$item->uuid]) ? array_values($hash_map[$item->uuid]) : null;

                $collection[] = [
                    'uuid' => $item->uuid,
                    'visitor_uuid' => $item->visitor_uuid,
                    'ip' => $item->ip,
                    'user_agent' => $item->user_agent,
                    'device' => $item->device,
                    'browser_name' => $item->browser_name,
                    'browser_version' => $item->browser_version,
                    'domain' => $item->domain,
                    'lang' => $item->lang,
                    'start' => $item->start,
                    'end' => $item->end,
                    'geo' => $geo,
                    'events' => $events,
                    'url' => $item->url,
                    'sop_id' => $item->sop_id,
                ];
            }
            
        } else {
            return [
                'collection' => [],
                'page' => 1,
                'page_size' => 1,
                'total_pages' => 1,
                'total_elements' => 0,
            ];
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
