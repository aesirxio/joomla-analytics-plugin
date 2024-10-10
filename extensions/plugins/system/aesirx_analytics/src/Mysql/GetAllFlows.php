<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Http\Response;

Class AesirX_Analytics_Get_All_Flows extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        $where_clause = [
            $db->quoteName('#__analytics_visitors.ip') . ' != ""',
            $db->quoteName('#__analytics_visitors.user_agent') . ' != ""',
            $db->quoteName('#__analytics_visitors.device') . ' != ""',
            $db->quoteName('#__analytics_visitors.browser_version') . ' != ""',
            $db->quoteName('#__analytics_visitors.browser_name') . ' != ""',
            $db->quoteName('#__analytics_visitors.lang') . ' != ""',
        ];
        $bind = [];
        $detail_page = false;
        
        parent::aesirx_analytics_add_filters($params, $where_clause, $bind);

        if (isset($params['flow_uuid']) && !empty($params['flow_uuid'])) {
            $where_clause = [$db->quoteName('#__analytics_flows.uuid') . ' = ' . $db->quote($inputFilter->clean($params['flow_uuid']))];
            $detail_page = true;
        }

        // filters where clause for events

        $total_sql = $db->getQuery(true)
            ->select('COUNT(DISTINCT #__analytics_flows.uuid) as total')
            ->from($db->quoteName('#__analytics_flows'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_flows.visitor_uuid'))
            ->leftJoin($db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('#__analytics_events.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->where(implode(' AND ', $where_clause));

        $sql = $db->getQuery(true)
            ->select([
                '#__analytics_flows.*', 
                'ip', 'user_agent', 'device', 'browser_name', 'browser_version', 'domain', 'lang', 'city', 'isp', 'country_name', 'country_code', 'geo_created_at',
                'COUNT(DISTINCT #__analytics_events.uuid) AS action',
                'SUM(CASE WHEN #__analytics_events.event_type = "conversion" THEN 1 ELSE 0 END) AS conversion',
                'SUM(CASE WHEN #__analytics_events.event_name = "visit" THEN 1 ELSE 0 END) AS pageview',
                'SUM(CASE WHEN #__analytics_events.event_name != "visit" THEN 1 ELSE 0 END) AS event',
                'TIMESTAMPDIFF(SECOND, #__analytics_flows.start, #__analytics_flows.end) AS duration',
                'MAX(CASE WHEN #__analytics_event_attributes.name = "sop_id" THEN #__analytics_event_attributes.value ELSE NULL END) AS sop_id',
                'SUM(CASE WHEN #__analytics_events.event_name = "visit" THEN 1 ELSE 0 END) * 2 + SUM(CASE WHEN #__analytics_events.event_name != "visit" THEN 1 ELSE 0 END) * 5 + SUM(CASE WHEN #__analytics_events.event_type = "conversion" THEN 1 ELSE 0 END) * 10 AS ux_percent'
            ])
            ->from($db->quoteName('#__analytics_flows'))
            ->leftJoin($db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_flows.visitor_uuid'))
            ->leftJoin($db->quoteName('#__analytics_events') . ' ON ' . $db->quoteName('#__analytics_events.flow_uuid') . ' = ' . $db->quoteName('#__analytics_flows.uuid'))
            ->leftJoin($db->quoteName('#__analytics_event_attributes') . ' ON ' . $db->quoteName('#__analytics_events.uuid') . ' = ' . $db->quoteName('#__analytics_event_attributes.event_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('#__analytics_flows.uuid'));

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
                "action",
                "event",
                "conversion",
                "url",
                "ux_percent",
                "pageview",
                "bounce_rate",
                "sop_id",
                "duration",
            ],
            "start",
        );

        if (!empty($sort)) {
            $sql->order(implode(", ", $sort));
        }

        $list_response = parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);

        if ($list_response instanceof Exception) {
            return $list_response;
        }

        $list = $list_response['collection'];

        $collection = [];

        $ret = [];
        $dirs = [];

        if (!empty($list)) {
            if (isset($params['request']['with']) && !empty($params['request']['with'])) {
                $with = $params['request']['with'];
                if (in_array("events", $with)) {
                    $bind = array_map(function($e) {
                        return $e['uuid'];
                    }, $list);

                    // doing direct database calls to custom tables
                    // placeholders depends one number of $bind
                    $queryEvents = $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName('#__analytics_events'))
                        ->where($db->quoteName('flow_uuid') . ' IN (' . implode(',', array_map([$db, 'quote'], $bind)) . ')');

                    // Set the query and execute it to get events
                    $db->setQuery($queryEvents);
                    $events = $db->loadObjectList();

                    // doing direct database calls to custom tables
                    // placeholders depends one number of $bind
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

                    foreach ($events as $second) {
                        $og_title = null;
                        $og_description = null;
                        $og_image = null;

                        // OG
                        if ($detail_page == true && !empty($second->url)) {
                            // Try to fetch and parse the Open Graph data
                            $og_data = parent::aesirx_analytics_fetch_open_graph_data($second->url);
                            
                            if (!empty($og_data)) {
                                $og_title = isset($og_data['og:title']) ? $og_data['og:title'] : null;
                                $og_description = isset($og_data['og:description']) ? $og_data['og:description'] : null;
                                $og_image = isset($og_data['og:image']) ? $og_data['og:image'] : null;
                            }
                        }

                        $second->og_title = $og_title;
                        $second->og_description = $og_description;
                        $second->og_image = $og_image;

                        if (!filter_var($second->url, FILTER_VALIDATE_URL)) {
                            $status_code = 404;
                        } else {
                            // Use Joomla's HTTP client to make the HEAD request
                            $http = HttpFactory::getHttp();
                            try {
                                // Send a HEAD request to check if the URL is valid
                                $response = $http->head($second->url);
                        
                                // Check the status code of the response
                                $status_code = $response->code;
                            } catch (RuntimeException $e) {
                                // If an error occurs, set status code to 500
                                $status_code = 500;
                            }
                        }

                        $second->status_code = $status_code;
                        $second->attribute = $hash_attributes[$second->uuid] ?? [];

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
                            'og_title' => $og_title,
                            'og_description' => $og_description,
                            'og_image' => $og_image,
                            'status_code' => $status_code
                        ];

                        if (!isset($hash_map[$second->flow_uuid])) {
                            $hash_map[$second->flow_uuid] = [$second->uuid => $visitor_event];
                        } else {
                            $hash_map[$second->flow_uuid][$second->uuid] = $visitor_event;
                        }
                    }

                    if (!empty($events) && $params[1] == "flow") {
                        if ($events[0]->start == $events[0]->end) {
                            // Prepare the query
                            $queryConsents = $db->getQuery(true)
                                ->select('*')
                                ->from($db->quoteName('#__analytics_visitor_consent'))
                                ->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($events[0]->visitor_uuid))
                                ->where('UNIX_TIMESTAMP(' . $db->quoteName('datetime') . ') > ' . strtotime($events[0]->start));

                            // Set the query and execute
                            $db->setQuery($queryConsents);
                            $consents = $db->loadObjectList();
                        } else {
                            // Prepare the query
                            $queryConsents = $db->getQuery(true)
                                ->select('*')
                                ->from($db->quoteName('#__analytics_visitor_consent'))
                                ->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($events[0]->visitor_uuid))
                                ->where('UNIX_TIMESTAMP(' . $db->quoteName('datetime') . ') > ' . strtotime($events[0]->start))
                                ->where('UNIX_TIMESTAMP(' . $db->quoteName('datetime') . ') < ' . strtotime($events[0]->end));

                            // Set the query and execute
                            $db->setQuery($queryConsents);
                            $consents = $db->loadObjectList();
                        }
    
                        foreach ($consents as $consent) {
                            $consent_data = $events[0];
    
                            if ($consent->consent_uuid != null) {
                                // Prepare the query
                                $queryConsentDetail = $db->getQuery(true)
                                    ->from($db->quoteName('#__analytics_consent'))
                                    ->where($db->quoteName('uuid') . ' = ' . $db->quote($consent->consent_uuid));

                                // Set the query and execute
                                $db->setQuery($queryConsentDetail);
                                $consent_detail = $db->loadObjectList();

                                if (!isset($consent_detail->consent) || $consent_detail->consent != 1) {
                                    continue;
                                }
    
                                if (!empty($consent_detail)) {
                                    $consent_attibute = [
                                        "web3id" => $consent_detail->web3id,
                                        "network" => $consent_detail->network,
                                        "datetime" => $consent_detail->datetime,
                                        "expiration" => $consent_detail->expiration,
                                        "tier" => 1,
                                    ];
    
                                    // Prepare the query
                                    $queryWalletDetail = $db->getQuery(true)
                                        ->select('*')
                                        ->from($db->quoteName('#__analytics_wallet'))
                                        ->where($db->quoteName('uuid') . ' = ' . $db->quote($consent_detail->wallet_uuid));

                                    // Set the query and execute
                                    $db->setQuery($queryWalletDetail);
                                    $wallet_detail = $db->loadObjectList();
    
                                    if (!empty($wallet_detail)) {
                                        $consent_attibute["wallet"] = $wallet_detail->address;
                                    }
    
                                    if ($consent_detail->web3id) {
                                        $consent_attibute["tier"] = 2;
                                    }
    
                                    if ($wallet_detail->address) {
                                        $consent_attibute["tier"] = 3;
                                    }
    
                                    if ($consent_detail->web3id && $wallet_detail->address) {
                                        $consent_attibute["tier"] = 4;
                                    }
    
                                    $consent_data->attributes = $consent_attibute;
                                }
    
                                $consent_data->uuid = $consent->consent_uuid;
                                $consent_data->start = $consent_detail->datetime;
                                $consent_data->end = $consent_detail->expiration;
                            } else {

                                if ($consent->consent != 1) {
                                    continue;
                                }

                                $consent_data->start = $consent->datetime;
                                $consent_data->end = $consent->expiration;
                            }
    
                            $consent_data->event_name = 'Consent';
                            $consent_data->event_type = 'consent';
    
                            $hash_map[$consent_data->flow_uuid][] = $consent_data;
                        }
                    }
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

                if ( $params[1] == 'flows') {

                    $bad_url_count = 0;

                    if (!empty($events)) {
                        $bad_url_count = count(array_filter($events, fn($item) => $item->status_code !== 200));
                    }

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
                        'duration' => $item->duration,
                        'action' => $item->action,
                        'event' => $item->event,
                        'conversion' => $item->conversion,
                        'url' => $item->url ?? '',
                        'ux_percent' => $item->ux_percent,
                        'pageview' => $item->pageview,
                        'sop_id' => $item->sop_id,
                        'visit_actions' => $item->visit_actions ?? 0,
                        'event_actions' => $item->event_actions ?? 0,
                        'conversion_actions' => $item->conversion_actions ?? 0,
                        'bad_user' => $bad_url_count > 1 ? true : false,
                    ];
                }
                elseif ( $params[1] == 'flow' ) {
                    $collection = [
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
                        'duration' => $item->duration,
                        'action' => $item->action,
                        'event' => $item->event,
                        'conversion' => $item->conversion,
                        'url' => $item->url ?? '',
                        'ux_percent' => $item->ux_percent,
                        'pageview' => $item->pageview,
                        'sop_id' => $item->sop_id,
                        'visit_actions' => $item->visit_actions ?? 0,
                        'event_actions' => $item->event_actions ?? 0,
                        'conversion_actions' => $item->conversion_actions ?? 0,
                    ];
                }
            }
        }

        if ( $params[1] == 'flows') {
            return [
                'collection' => $collection,
                'page' => $list_response['page'],
                'page_size' => $list_response['page_size'],
                'total_pages' => $list_response['total_pages'],
                'total_elements' => $list_response['total_elements'],
            ];
        }
        elseif ( $params[1] == 'flow' ) {
            return $collection;
        }
    }
}
