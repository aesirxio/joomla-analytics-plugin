<?php

namespace Aesirx\System\AesirxAnalytics;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Ramsey\Uuid\Uuid;

if (!class_exists('AesirxAnalyticsMysqlHelper')) {
    Class AesirxAnalyticsMysqlHelper
    {
        public function aesirx_analytics_get_list($sql, $total_sql, $params, $allowed, $bind = []) {
            $db = Factory::getDbo();
    
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 20;
            $skip = ($page - 1) * $pageSize;
        
            $sql->setLimit($pageSize, $skip);

            // Bind parameters
            foreach ($bind as $key => $value) {
                $total_sql->bind($key, $value);
            }

            // Execute the total elements query
            $total_elements = (int) $db->setQuery($total_sql)->loadResult();
            $total_pages = ceil($total_elements / $pageSize);
    
            try {
                // Check if there's caching
                $plugin = PluginHelper::getPlugin('system', 'aesirx_analytics');
                $plugin_params = new Registry($plugin->params);

                if ($plugin_params->get('cache_time', 0) > 0) {
                    $cache = Factory::getCache('aesirx_analytics_cache_group', '');
                    $key = md5($sql);
        
                    if ($cached_data = $cache->get($key)) {
                        $collection = $cached_data;
                    } else {
                        // Execute the main query with pagination
                        foreach ($bind as $key => $value) {
                            $sql->bind($key, $value);
                        }
        
                        $collection = $db->setQuery($sql)->loadAssocList();
        
                        // Perform post-processing on the collection
                        if (!empty($collection)) {
                            $collection = array_map(function ($row) {
                                foreach ($row as $key => $value) {
                                    if (in_array($key, ['total', 'total_visitor', 'unique_visitor', 'total_number_of_visitors'])) {
                                        $row[$key] = (int)$value;
                                    }
                                }
                                return $row;
                            }, $collection);
                        }
        
                        // Set the cache
                        $cache->store($collection, $key, $params->get('cache_time', 0));
                    }
                } else {
                    // Execute the main query without caching
                    foreach ($bind as $key => $value) {
                        $sql->bind($key, $value);
                    }
    
                    $collection = $db->setQuery($sql)->loadAssocList();
        
                    // Perform post-processing on the collection
                    if (!empty($collection)) {
                        $collection = array_map(function ($row) {
                            foreach ($row as $key => $value) {
                                if (in_array($key, ['total', 'total_visitor', 'unique_visitor', 'total_number_of_visitors'])) {
                                    $row[$key] = (int)$value;
                                }
                            }
                            return $row;
                        }, $collection);
                    }
                }

                // Build the response
                $list_response = [
                    'collection' => $collection,
                    'page' => (int) $page,
                    'page_size' => (int) $pageSize,
                    'total_pages' => $total_pages,
                    'total_elements' => $total_elements,
                ];

                // If it's a metrics request, return the first item
                if (isset($params[1]) && $params[1] == "metrics") {
                    $list_response = $list_response['collection'][0] ?? [];
                }

                return $list_response;
    
            } catch (Exception $e) {
                // Log any errors
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false;
            }
        }
    
        public function aesirx_analytics_get_statistics_per_field($groups = [], $selects = [], $params = []) {
    
            $db = Factory::getDbo();
            $bind = [];
            
            // Define the main select statements
            $select = [
                "COALESCE(COUNT(DISTINCT (#__analytics_events.visitor_uuid)), 0) AS number_of_visitors",
                "COALESCE(COUNT(#__analytics_events.visitor_uuid), 0) AS total_number_of_visitors",
                "COUNT(#__analytics_events.uuid) AS number_of_page_views",
                "COUNT(DISTINCT(#__analytics_events.url)) AS number_of_unique_page_views",
                "COALESCE(SUM(TIMESTAMPDIFF(SECOND, #__analytics_events.start, #__analytics_events.end)) / COUNT(DISTINCT #__analytics_visitors.uuid), 0) DIV 1 AS average_session_duration",
                "COALESCE((COUNT(#__analytics_events.uuid) / COUNT(DISTINCT (#__analytics_events.flow_uuid))), 0) DIV 1 AS average_number_of_pages_per_session",
                "COALESCE((COUNT(DISTINCT CASE WHEN #__analytics_flows.multiple_events = 0 THEN #__analytics_flows.uuid END) * 100) / COUNT(DISTINCT (#__analytics_flows.uuid)), 0) DIV 1 AS bounce_rate",
            ];

            $total_select = [];

            // Add groups to the select statements
            if (!empty($groups)) {
                foreach ($groups as $one_group) {
                    $select[] = $one_group;
                }
                $total_select[] = "COUNT(DISTINCT " . implode(", COALESCE(", $groups) . ") AS total";
            } else {
                $total_select[] = "COUNT(#__analytics_events.uuid) AS total";
            }
    
            // Add additional select statements
            foreach ($selects as $additional_result) {
                $select[] = $additional_result['select'] . " AS " . $additional_result['result'];
            }
    
            // Initialize where clauses and bind parameters
            $where_clause = [
                $db->quoteName('#__analytics_events.event_name') . " = " . $db->quote('visit'),
                $db->quoteName('#__analytics_events.event_type') . " = " . $db->quote('action')
            ];
    
            self::aesirx_analytics_add_filters($params, $where_clause, $bind);
    
             // Check if acquisition filter exists in the params
            $acquisition = false;
            foreach ($params['filter'] as $key => $vals) {
                if ($key === 'acquisition') {
                    $list = is_array($vals) ? $vals : [$vals];
                    if ($list[0] === 'true') {
                        $acquisition = true;
                    }
                    break;
                }
            }
    
            if ($acquisition) {
                $where_clause[] = $db->quoteName('#__analytics_flows.multiple_events') . " = " . (int)0;
            }

            // Build the total SQL query
            $total_sql = $db->getQuery(true)
                ->select($total_select)
                ->from($db->quoteName('#__analytics_events'))
                ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
                ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_flows.uuid') . ' = ' . $db->quoteName('#__analytics_events.flow_uuid'))
                ->where(implode(" AND ", $where_clause));

            // Build the main SQL query
            $sql = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__analytics_events'))
                ->join('LEFT', $db->quoteName('#__analytics_visitors') . ' ON ' . $db->quoteName('#__analytics_visitors.uuid') . ' = ' . $db->quoteName('#__analytics_events.visitor_uuid'))
                ->join('LEFT', $db->quoteName('#__analytics_flows') . ' ON ' . $db->quoteName('#__analytics_flows.uuid') . ' = ' . $db->quoteName('#__analytics_events.flow_uuid'))
                ->where(implode(" AND ", $where_clause));
    
            // Group the results by the groups if provided
            if (!empty($groups)) {
                $sql->group(implode(", ", $groups));
            }
    
            $allowed = [
                "number_of_visitors",
                "number_of_page_views",
                "number_of_unique_page_views",
                "average_session_duration",
                "average_number_of_pages_per_session",
                "bounce_rate",
            ];
            $default = reset($allowed);
    
            foreach ($groups as $one_group) {
                $allowed[] = $one_group;
                $default = $one_group;
            }
    
            foreach ($groups as $additional_result) {
                $allowed[] = $additional_result;
            }
            
            // Add sorting
            $sort = self::aesirx_analytics_add_sort($params, $allowed, $default);
    
            if (!empty($sort)) {
                $sql->order(implode(", ", $sort));
            }
    
            // Get the list of results and total count
            return self::aesirx_analytics_get_list($sql, $total_sql, $params, $allowed, $bind);
        }
    
        function aesirx_analytics_add_sort($params, $allowed, $default) {
            $ret = [];
            $dirs = [];
    
            if (isset($params['sort_direction'])) {
                foreach ($params['sort_direction'] as $pos => $value) {
                    $dirs[$pos] = $value;
                }
            }
    
            if (!isset($params['sort'])) {
                $ret[] = sprintf("%s ASC", $default);
            } else {
                foreach ($params['sort'] as $pos => $value) {
                    if (!in_array($value, $allowed)) {
                        continue;
                    }
    
                    $dir = "ASC";
                    if (isset($dirs[$pos]) && $dirs[$pos] === "desc") {
                        $dir = "DESC";
                    }
    
                    $ret[] = sprintf("%s %s", $value, $dir);
                }
    
                if (empty($ret)) {
                    $ret[] = sprintf("%s ASC", $default);
                }
            }
    
            return $ret;
        }
    
        function aesirx_analytics_add_filters($params, &$where_clause, &$bind) {
            $inputFilter = InputFilter::getInstance();

            foreach ([$params['filter'] ?? null, $params['filter_not'] ?? null] as $filter_array) {
                $is_not = $filter_array === (isset($params['filter_not']) ? $params['filter_not'] : null);
                if (empty($filter_array)) {
                    continue;
                }
        
                foreach ($filter_array as $key => $vals) {
                    $list = is_array($vals) ? $vals : [$vals];
    
                    switch ($key) {
                        case 'start':
                            try {
                                $where_clause[] = "UNIX_TIMESTAMP(#__analytics_events." . $key . ") >= " . strtotime($list[0]);
                            } catch (Exception $e) {
                                Log::add('Validation error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                                throw new InvalidArgumentException('"start" filter is not correct');
                            }
                            break;
                        case 'end':
                            try {
                                $where_clause[] = "UNIX_TIMESTAMP(#__analytics_events." . $key . ") < " . strtotime($list[0] . ' +1 day');
                            } catch (Exception $e) {
                                Log::add('Validation error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                                throw new InvalidArgumentException('"end" filter is not correct');
                            }
                            break;
                        case 'event_name':
                        case 'event_type':
                            $where_clause[] = '#__analytics_events.' . $key . ' ' . ($is_not ? 'NOT ' : '') . 'IN ("' . implode('", "', array_map([$inputFilter, 'clean'], $list)) . '")';
                            break;
                        case 'city':
                        case 'isp':
                        case 'country_code':
                        case 'country_name':
                        case 'url':
                        case 'domain':
                        case 'browser_name':
                        case 'browser_version':
                        case 'device':
                        case 'lang':
                            $where_clause[] = '#__analytics_visitors.' . $key . ' ' . ($is_not ? 'NOT ' : '') . 'IN ("' . implode('", "', array_map([$inputFilter, 'clean'], $list)) . '")';
                            break;
                        default:
                            break;
                    }
                }
            }
        }
    
        function aesirx_analytics_add_attribute_filters($params, &$where_clause, &$bind) {
            foreach ([$params['filter'] ?? null, $params['filter_not'] ?? null]as $filter_array) {
                $is_not = $filter_array === (isset($params['filter_not']) ? $params['filter_not'] : null);
                if (empty($filter_array)) {
                    continue;
                }
        
                foreach ($filter_array as $key => $val) {
                    $list = is_array($val) ? $val : [$val];
                    switch ($key) {
                        case "attribute_name":
                            if ($is_not) {
                                $where_clause[] = '#__analytics_event_attributes.event_uuid IS NULL 
                                    OR #__analytics_event_attributes.name NOT IN ("' . implode('", "', $list) . '")';
                            } else {
                                $where_clause[] = '#__analytics_event_attributes.name IN ("' . implode('", "', $list) . '")';
                            }
                            break;
                        case "attribute_value":
                            if ($is_not) {
                                $where_clause[] = '#__analytics_event_attributes.event_uuid IS NULL 
                                    OR #__analytics_event_attributes.value NOT IN ("' . implode('", "', $list) . '")';
                            } else {
                                $where_clause[] = '#__analytics_event_attributes.value IN ("' . implode('", "', $list) . '")';
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }
    
        function aesirx_analytics_validate_domain($url) {
            // Parse the URL using Joomla's Uri class
            $parsed_url = Uri::getInstance($url);
    
            if ($parsed_url === false || empty($parsed_url->getHost())) {
                Log::add(Text::_('Domain not found'), Log::ERROR, 'aesirx-analytics');
                return false;
            }
    
            $domain = $parsed_url->getHost();
    
            if (strpos($domain, 'www.') === 0) {
                $domain = substr($domain, 4);
            }

            return $domain;
        }
    
        function aesirx_analytics_find_visitor_by_fingerprint_and_domain($fingerprint, $domain) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();

            // Sanitize the inputs
            $fingerprint = $inputFilter->clean($fingerprint, 'STRING');
            $domain = $inputFilter->clean($domain, 'STRING');
    
            // Query to fetch the visitor
            try {
                $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_visitors'))
                ->where($db->quoteName('fingerprint') . ' = ' . $db->quote($fingerprint))
                ->where($db->quoteName('domain') . ' = ' . $db->quote($domain));
    
                // Execute the query
                $db->setQuery($query);
                $visitor = $db->loadObject();
    
                if ($visitor) {
                    $res = [
                        'fingerprint' => $visitor->fingerprint,
                        'uuid' => $visitor->uuid,
                        'ip' => $visitor->ip,
                        'user_agent' => $visitor->user_agent,
                        'device' => $visitor->device,
                        'browser_name' => $visitor->browser_name,
                        'browser_version' => $visitor->browser_version,
                        'domain' => $visitor->domain,
                        'lang' => $visitor->lang,
                        'visitor_flows' => null,
                        'geo' => null,
                        'visitor_consents' => [],
                    ];
    
                    // If geo information exists, include it in the response
                    if ($visitor->geo_created_at) {
                        $res['geo'] = [
                            'country' => [
                                'name' => $visitor->country_name,
                                'code' => $visitor->country_code,
                            ],
                            'city' => $visitor->city,
                            'region' => $visitor->region,
                            'isp' => $visitor->isp,
                            'created_at' => $visitor->geo_created_at,
                        ];
                    }

                    // Query to fetch visitor flows
                    $flowQuery = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__analytics_flows'))
                    ->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($visitor->uuid))
                    ->order($db->quoteName('id'));

                    $db->setQuery($flowQuery);
                    $flows = $db->loadObjectList();
    
                    // If flows exist, format the visitor flow data
                    if ($flows) {
                        $ret_flows = [];
                        foreach ($flows as $flow) {
                            $ret_flows[] = [
                                'uuid' => $flow->uuid,
                                'start' => $flow->start,
                                'end' => $flow->end,
                                'multiple_events' => $flow->multiple_events,
                            ];
                        }
                        $res['visitor_flows'] = $ret_flows;
                    }
    
                    return $res;
                }
    
                return null;
            } catch (Exception $e) {
                 // Log the error
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false; 
            }
        }
    
        function aesirx_analytics_create_visitor($visitor) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
    
            try {
                if (empty($visitor['geo'])) {
                    $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__analytics_visitors'))
                    ->columns([
                        $db->quoteName('fingerprint'),
                        $db->quoteName('uuid'),
                        $db->quoteName('ip'),
                        $db->quoteName('user_agent'),
                        $db->quoteName('device'),
                        $db->quoteName('browser_name'),
                        $db->quoteName('browser_version'),
                        $db->quoteName('domain'),
                        $db->quoteName('lang')
                    ])
                    ->values(implode(',', [
                        $db->quote($inputFilter->clean($visitor['fingerprint'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['uuid'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['ip'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['user_agent'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['device'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['browser_name'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['browser_version'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['domain'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor['lang'], 'STRING'))
                    ]));
                } else {
                    $geo = $visitor['geo'];
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__analytics_visitors'))
                        ->columns([
                            $db->quoteName('fingerprint'),
                            $db->quoteName('uuid'),
                            $db->quoteName('ip'),
                            $db->quoteName('user_agent'),
                            $db->quoteName('device'),
                            $db->quoteName('browser_name'),
                            $db->quoteName('browser_version'),
                            $db->quoteName('domain'),
                            $db->quoteName('lang'),
                            $db->quoteName('country_code'),
                            $db->quoteName('country_name'),
                            $db->quoteName('city'),
                            $db->quoteName('isp'),
                            $db->quoteName('geo_created_at')
                        ])
                        ->values(implode(',', [
                            $db->quote($inputFilter->clean($visitor['fingerprint'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['uuid'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['ip'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['user_agent'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['device'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['browser_name'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['browser_version'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['domain'], 'STRING')),
                            $db->quote($inputFilter->clean($visitor['lang'], 'STRING')),
                            $db->quote($inputFilter->clean($geo['country']['code'], 'STRING')),
                            $db->quote($inputFilter->clean($geo['country']['name'], 'STRING')),
                            $db->quote($inputFilter->clean($geo['city'], 'STRING')),
                            $db->quote($inputFilter->clean($geo['isp'], 'STRING')),
                            $db->quote($inputFilter->clean($geo['created_at'], 'STRING'))
                        ]));
                }

                // Execute the insert query
                $db->setQuery($query);
                $db->execute();
        
                if (!empty($visitor['visitor_flows'])) {
                    foreach ($visitor['visitor_flows'] as $flow) {
                        self::aesirx_analytics_create_visitor_flow($visitor['uuid'], $flow);
                    }
                }
        
                return true;
            } catch (Exception $e) {
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false;
            }
        }
    
        function aesirx_analytics_create_visitor_event($visitor_event) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
    
            try {
                // Insert event
                // Prepare the insert query for the event
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__analytics_events'))
                    ->columns([
                        $db->quoteName('uuid'),
                        $db->quoteName('visitor_uuid'),
                        $db->quoteName('flow_uuid'),
                        $db->quoteName('url'),
                        $db->quoteName('referer'),
                        $db->quoteName('start'),
                        $db->quoteName('end'),
                        $db->quoteName('event_name'),
                        $db->quoteName('event_type')
                    ])
                    ->values(implode(',', [
                        $db->quote($inputFilter->clean($visitor_event['uuid'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['visitor_uuid'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['flow_uuid'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['url'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['referer'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['start'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['end'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['event_name'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_event['event_type'], 'STRING'))
                    ]));

                // Execute the query to insert the event
                $db->setQuery($query);
                $db->execute();
    
                // Insert event attributes if they exist
                if (!empty($visitor_event['attributes'])) {
                    foreach ($visitor_event['attributes'] as $attribute) {
                        $query = $db->getQuery(true)
                            ->insert($db->quoteName('#__analytics_event_attributes'))
                            ->columns([
                                $db->quoteName('event_uuid'),
                                $db->quoteName('name'),
                                $db->quoteName('value')
                            ])
                            ->values(implode(',', [
                                $db->quote($inputFilter->clean($visitor_event['uuid'], 'STRING')),
                                $db->quote($inputFilter->clean($attribute['name'], 'STRING')),
                                $db->quote($inputFilter->clean($attribute['value'], 'STRING'))
                            ]));
                        
                        // Execute the query to insert the event attribute
                        $db->setQuery($query);
                        $db->execute();
                    }     
                }
    
                return true;
            } catch (Exception $e) {
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false;
            }
        }
    
        function aesirx_analytics_create_visitor_flow($visitor_uuid, $visitor_flow) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
    
            try {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__analytics_flows'))
                    ->columns([
                        $db->quoteName('visitor_uuid'),
                        $db->quoteName('uuid'),
                        $db->quoteName('start'),
                        $db->quoteName('end'),
                        $db->quoteName('multiple_events')
                    ])
                    ->values(implode(',', [
                        $db->quote($inputFilter->clean($visitor_uuid, 'STRING')),
                        $db->quote($inputFilter->clean($visitor_flow['uuid'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_flow['start'], 'STRING')),
                        $db->quote($inputFilter->clean($visitor_flow['end'], 'STRING')),
                        $db->quote((int) $visitor_flow['multiple_events'])
                    ]));

                // Execute the query to insert the visitor flow
                $db->setQuery($query);
                $db->execute();

                return true;
            } catch (Exception $e) {
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false;
            }
        }
    
        function aesirx_analytics_mark_visitor_flow_as_multiple($visitor_flow_uuid) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
    
            // Ensure UUID is properly formatted for the database
            $visitor_flow_uuid = (string) $inputFilter->clean($visitor_flow_uuid, 'STRING');
    
            try {
                // Create the update query
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__analytics_flows'))
                    ->set($db->quoteName('multiple_events') . ' = 1')
                    ->where($db->quoteName('uuid') . ' = ' . $db->quote($visitor_flow_uuid));

                // Set and execute the query
                $db->setQuery($query);
        
                return true;
        
            } catch (Exception $e) {
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false;
            }
        }
    
        function aesirx_analytics_add_consent_filters($params, &$where_clause, &$bind) {
            $inputFilter = InputFilter::getInstance();

            foreach ([$params['filter'] ?? null, $params['filter_not'] ?? null] as $filter_array) {
                $is_not = $filter_array === (isset($params['filter_not']) ? $params['filter_not'] : null);
                if (empty($filter_array)) {
                    continue;
                }
    
                foreach ($filter_array as $key => $vals) {
                    $list = is_array($vals) ? $vals : [$vals];
    
                    switch ($key) {
                        case 'start':
                            try {
                                $where_clause[] = "UNIX_TIMESTAMP(visitor_consent.datetime) >= " . strtotime($list[0]);
                            } catch (Exception $e) {
                                Log::add('Validation error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                                throw new Exception(Text::_('JGLOBAL_VALIDATION_ERROR') . ': ' . Text::_('"start" filter is not correct'), 400);
                            }
                            break;
                        case 'end':
                            try {
                                $where_clause[] = "UNIX_TIMESTAMP(visitor_consent.datetime) < " . strtotime($list[0] . ' +1 day');
                            } catch (Exception $e) {
                                Log::add('Validation error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                                throw new Exception(Text::_('JGLOBAL_VALIDATION_ERROR') . ': ' . Text::_('"end" filter is not correct'), 400);
                            }
                            break;
                        case 'domain':
                            $where_clause[] = 'domain ' . ($is_not ? 'NOT ' : '') . 'IN ("' . implode('", "', array_map([$inputFilter, 'clean'], $list)) . '")';
                            break;
                        default:
                            break;
                    }
                }
            }
        }
    
        function aesirx_analytics_add_conversion_filters($params, &$where_clause, &$bind) {
            foreach ($params['filter'] as $key => $vals) {
                $list = is_array($vals) ? $vals : [$vals];
    
                switch ($key) {
                    case 'start':
                        try {
                            $where_clause[] = "UNIX_TIMESTAMP(#__analytics_flows." . $key . ") >= " . strtotime($list[0]);
                        } catch (Exception $e) {
                            Log::add('Validation error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                            throw new Exception(Text::_('JGLOBAL_VALIDATION_ERROR') . ': ' . Text::_('"start" filter is not correct'), 400);
                        }
                        break;
                    case 'end':
                        try {
                            $where_clause[] = "UNIX_TIMESTAMP(#__analytics_flows." . $key . ") < " . strtotime($list[0] . ' +1 day');
                        } catch (Exception $e) {
                            Log::add('Validation error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                            throw new Exception(Text::_('JGLOBAL_VALIDATION_ERROR') . ': ' . Text::_('"end" filter is not correct'), 400);
                        }
                        break;
                    default:
                        break;
                }
            }
        }
    
        function aesirx_analytics_find_wallet($network, $address) {
            // Get the database object
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();

            // Sanitize the inputs
            $network = $inputFilter->clean($network, 'STRING');
            $address = $inputFilter->clean($address, 'STRING');

            try {
                // Build the query
                $query = $db->getQuery(true)
                            ->select('*')
                            ->from($db->quoteName('#__analytics_wallet'))
                            ->where($db->quoteName('network') . ' = ' . $db->quote($network))
                            ->where($db->quoteName('address') . ' = ' . $db->quote($address));
        
                // Execute the query
                $db->setQuery($query);
                $wallet = $db->loadObject();
        
                return $wallet;
            } catch (Exception $e) {
                // Log the error and throw an exception
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('JERROR_AN_ERROR_OCCURRED'), 500);
            }
        }
    
        function aesirx_analytics_add_wallet($uuid, $network, $address, $nonce) {
            // Get the database object
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();

            // Sanitize the inputs
            $uuid = $inputFilter->clean($uuid, 'STRING');
            $network = $inputFilter->clean($network, 'STRING');
            $address = $inputFilter->clean($address, 'STRING');
            $nonce = $inputFilter->clean($nonce, 'STRING');

            // Create a wallet object for the insert
            $wallet = (object) [
                'uuid'    => $uuid,
                'network' => $network,
                'address' => $address,
                'nonce'   => $nonce,
            ];
    
            try {
                // Insert the wallet data into the database
                $db->insertObject('#__analytics_wallet', $wallet);
        
                return true;
            } catch (Exception $e) {
                // Log the error and throw an exception
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('JERROR_AN_ERROR_OCCURRED'), 500);
            }
        }
    
        function aesirx_analytics_update_nonce($network, $address, $nonce) {
            // Get the database object
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();

            // Sanitize the input values
            $network = $inputFilter->clean($network, 'STRING');
            $address = $inputFilter->clean($address, 'STRING');
            $nonce = $inputFilter->clean($nonce, 'STRING');

            // Prepare the update query
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__analytics_wallet'))
                ->set($db->quoteName('nonce') . ' = ' . $db->quote($nonce))
                ->where($db->quoteName('network') . ' = ' . $db->quote($network))
                ->where($db->quoteName('address') . ' = ' . $db->quote($address));

            try {
                // Execute the query
                $db->setQuery($query);
                $db->execute();

                return true;
            } catch (Exception $e) {
                // Log the error and throw an exception
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('JERROR_AN_ERROR_OCCURRED'), 500);
            }
        }
    
        function aesirx_analytics_add_consent($uuid, $consent, $datetime, $web3id = null, $wallet_uuid = null, $expiration = null) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
    
            // Prepare the data array
            $data = array(
                'uuid'      => $inputFilter->clean($uuid, 'STRING'),
                'consent'   => $inputFilter->clean($consent, 'STRING'),
                'datetime'  => $datetime
            );
            
            // Conditionally add wallet_uuid
            if (!empty($wallet_uuid)) {
                $data['wallet_uuid'] = $inputFilter->clean($wallet_uuid, 'STRING');
            }

            // Conditionally add web3id
            if (!empty($web3id)) {
                $data['web3id'] = $inputFilter->clean($web3id, 'STRING');
            }

            // Conditionally add expiration
            if (!empty($expiration)) {
                $data['expiration'] = $expiration;
            }
            
            // Build the insert query
            $query = $db->getQuery(true);
            $columns = array_keys($data);
            $values = array_map(array($db, 'quote'), array_values($data));

            // Insert data into custom table 'analytics_consent'
            $query
                ->insert($db->quoteName('#__analytics_consent')) // Joomla prefix uses '#__' for table prefix
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($query);

            // Execute the insert
            try {
                $db->execute();
                return true;
            } catch (Exception $e) {
                // Log and handle error
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('JERROR_AN_ERROR_OCCURRED'), 500);
            }
        }
    
        function aesirx_analytics_add_visitor_consent($visitor_uuid, $consent_uuid = null, $consent = null, $datetime = null, $expiration = null, $params = []) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
            $uuid = Uuid::uuid4()->toString();

            // Prepare the data array
            $data = array(
                'uuid'         => $uuid,
                'visitor_uuid' => $inputFilter->clean($visitor_uuid, 'STRING')
            );

            // Conditionally add consent_uuid
            if (!empty($consent_uuid)) {
                $data['consent_uuid'] = $inputFilter->clean($consent_uuid, 'STRING');
            }

            // Conditionally add consent
            if (!empty($consent)) {
                $data['consent'] = (int) $consent;
            }

            // Conditionally add datetime
            if (!empty($datetime)) {
                $data['datetime'] = $datetime;
            }

            // Conditionally add expiration
            if (!empty($expiration)) {
                $data['expiration'] = $expiration;
            }
            
            // Insert data into the analytics_visitor_consent table
            try {
                $query = $db->getQuery(true)
                            ->insert($db->quoteName('#__analytics_visitor_consent'))
                            ->columns($db->quoteName(array_keys($data)))
                            ->values(implode(',', array_map([$db, 'quote'], array_values($data))));
                $db->setQuery($query);
                $db->execute();
            } catch (Exception $e) {
                // Log and handle error
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('PLG_SYSTEM_AESIRX_ANALYTICS_ERROR_INSERT_CONSENT'), 500);
            }

            // Fetch the visitor data
            $visitor_query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_visitors'))
                ->where($db->quoteName('uuid') . ' = ' . $db->quote($visitor_uuid));
            $db->setQuery($visitor_query);
            $visitor_data = $db->loadObject();

            // Check and update visitor data if needed
            $updated_data = [];

            if (empty($visitor_data->ip) && isset($params['request']['ip'])) {
                $updated_data['ip'] = $params['request']['ip'];
            }
            if (empty($visitor_data->browser_version)) {
                $updated_data['browser_version'] = isset($params['request']['browser_version']) ? $params['request']['browser_version'] : '';
            }
            if (empty($visitor_data->browser_name)) {
                $updated_data['browser_name'] = isset($params['request']['browser_name']) ? $params['request']['browser_name'] : '';
            }
            if (empty($visitor_data->device)) {
                $updated_data['device'] = isset($params['request']['device']) ? $params['request']['device'] : '';
            }
            if (empty($visitor_data->user_agent)) {
                $updated_data['user_agent'] = isset($params['request']['user_agent']) ? $params['request']['user_agent'] : '';
            }
            if (empty($visitor_data->lang)) {
                $updated_data['lang'] = isset($params['request']['lang']) ? $params['request']['lang'] : '';
            }

            // Update the visitor data if there are changes
            if (!empty($updated_data)) {
                try {
                    $query = $db->getQuery(true)
                                ->update($db->quoteName('#__analytics_visitors'))
                                ->set(array_map(function ($key, $value) use ($db) {
                                    return $db->quoteName($key) . ' = ' . $db->quote($value);
                                }, array_keys($updated_data), $updated_data))
                                ->where($db->quoteName('uuid') . ' = ' . $db->quote($visitor_uuid));
                    $db->setQuery($query);
                    $db->execute();
                } catch (RuntimeException $e) {
                    Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                    return false;
                }
            }

            return true;
        }
    
        function aesirx_analytics_group_consents_by_domains($consents) {
            $inputFilter = InputFilter::getInstance(); // Joomla's input filter for sanitization
            $consentDomainList = [];
    
            foreach ($consents as $consent) {
                if (empty($consent['visitor'])) {
                    continue;
                }
    
                 // Use InputFilter to sanitize data
                $visitorDomain = isset($consent['visitor'][0]['domain']) ? $inputFilter->clean($consent['visitor'][0]['domain'], 'STRING') : null;
                
                if ($visitorDomain === null) {
                    continue;
                }
    
                if (!isset($consentDomainList[$visitorDomain])) {
                    $consentDomainList[$visitorDomain] = [];
                }
    
                // Sanitize each data field
                $consentDomainList[$visitorDomain][] = [
                    'uuid' => $inputFilter->clean($consent['uuid'], 'STRING'),
                    'wallet_uuid' => $inputFilter->clean($consent['wallet_uuid'], 'STRING'),
                    'address' => $inputFilter->clean($consent['address'], 'STRING'),
                    'network' => $inputFilter->clean($consent['network'], 'STRING'),
                    'web3id' => $inputFilter->clean($consent['web3id'], 'STRING'),
                    'consent' => (int)$consent['consent'], // consent is cast to integer
                    'datetime' => $inputFilter->clean($consent['datetime'], 'STRING'),
                    'expiration' => $inputFilter->clean($consent['expiration'], 'STRING')
                ];
            }
    
            $consentsByDomain = [];

            foreach ($consentDomainList as $domain => $domainConsents) {
                $consentsByDomain[] = [
                    'domain' => $domain,
                    'consents' => $domainConsents
                ];
            }

            return $consentsByDomain;
        }
    
        function aesirx_analytics_validate_string($nonce, $wallet, $singnature) {
            $inputFilter = InputFilter::getInstance();

            $apiUrl = 'http://dev01.aesirx.io:8888/validate/string?nonce=' 
            . urlencode($inputFilter->clean($nonce, 'STRING')) . '&wallet=' 
            . urlencode($inputFilter->clean($wallet, 'STRING')) . '&signature=' 
            . urlencode($inputFilter->clean($singnature, 'STRING'));

            // Use Joomla's HttpFactory to perform a GET request
            $http = HttpFactory::getHttp();
            $options = array(
                    'Content-Type' => 'application/json',
            );

            try {
                // Execute the GET request
                $response = $http->get($apiUrl, $options);
            } catch (\RuntimeException $e) {
                // Log error and handle exception
                Log::add('API error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                Factory::getApplication()->enqueueMessage(Text::_('Something went wrong'), 'error');
                return false;
            }

            // Retrieve the response body
            $body = $response->body;
            $data = json_decode($body, true);

            return $data;
        }

        function aesirx_analytics_validate_address($wallet) {
            $inputFilter = InputFilter::getInstance();

            // Build the API URL with sanitized parameters
            $apiUrl = 'http://dev01.aesirx.io:8888/validate/wallet?wallet=' 
                . urlencode($inputFilter->clean($wallet, 'STRING'));
           // Use Joomla's HttpFactory to perform a GET request
            $http = HttpFactory::getHttp();
            $options = array(
                    'Content-Type' => 'application/json',
            );

            try {
                // Execute the GET request
                $response = $http->get($apiUrl, $options);
            } catch (\RuntimeException $e) {
                // Log error and handle exception
                Log::add('API error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                Factory::getApplication()->enqueueMessage(Text::_('Something went wrong'), 'error');
                return false;
            }
        
            // Retrieve the response body
            $body = $response->body;
            $data = json_decode($body, true);
        
            return $data;
        }

        function aesirx_analytics_validate_contract($token) {
            // Get the InputFilter instance for sanitizing inputs
            $inputFilter = InputFilter::getInstance();

            // API URL
            $apiUrl = 'http://dev01.aesirx.io:8888/validate/contract';

            // Use Joomla's HttpFactory to perform a GET request
            $http = HttpFactory::getHttp();
            $options = array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $inputFilter->clean($token, 'STRING'),
            );

            try {
                // Execute the GET request
                $response = $http->get($apiUrl, $options);
            } catch (\RuntimeException $e) {
                // Log error and handle exception
                Log::add('API error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                Factory::getApplication()->enqueueMessage(Text::_('Something went wrong'), 'error');
                return false;
            }

            // Retrieve the response body
            $body = $response->body;
            $data = json_decode($body, true);

            return $data;
        }
    
        function aesirx_analytics_expired_consent($consent_uuid, $expiration) {
            try {
                 // Get the Joomla database object
                $db = Factory::getDbo();
                $query = $db->getQuery(true);

                // Get InputFilter for sanitization
                $inputFilter = InputFilter::getInstance();

                // Prepare the data for update
                $data = [
                    'expiration' => $expiration ? $inputFilter->clean($expiration, 'STRING') : null,
                ];
                
                // Prepare the condition for the update
                $conditions = [
                    $db->quoteName('uuid') . ' = ' . $db->quote($inputFilter->clean($consent_uuid, 'STRING')),
                ];

                // Create the update query
                $query->update($db->quoteName('#__analytics_consent'))
                    ->set($db->quoteName('expiration') . ' = ' . $db->quote($data['expiration']))
                    ->where($conditions);

                // Execute the query
                $db->setQuery($query);
                $db->execute();

                // Sanitize the consent_uuid
                $consent_uuid = htmlspecialchars($consent_uuid, ENT_QUOTES, 'UTF-8');

                // Select the visitor data based on the consent_uuid
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__analytics_visitor_consent'))
                    ->where($db->quoteName('consent_uuid') . ' = ' . $db->quote($consent_uuid));

                // Execute the query and load the result
                $db->setQuery($query);
                $visitor_data = $db->loadObject();

                // Check if visitor data is found
                if ($visitor_data) {
                    // Prepare the data for the update
                    $updated_data = [
                        'ip'             => '',
                        'lang'           => '',
                        'browser_version' => '',
                        'browser_name'   => '',
                        'device'         => '',
                        'user_agent'     => ''
                    ];

                    // Perform the update
                    try {
                        $query = $db->getQuery(true)
                                    ->update($db->quoteName('#__analytics_visitors'))
                                    ->set(array_map(function ($key, $value) use ($db) {
                                        return $db->quoteName($key) . ' = ' . $db->quote($value);
                                    }, array_keys($updated_data), $updated_data))
                                    ->where($db->quoteName('uuid') . ' = ' . $db->quote($visitor_data->visitor_uuid));

                        // Set and execute the query
                        $db->setQuery($query);
                        $db->execute();
                    } catch (Exception $e) {
                        // Log any errors that occur during the update
                        Log::add('Error updating analytics visitor data: ' . $e->getMessage(), Log::ERROR, 'jerror');
                        return false;
                    }
                } else {
                    // Handle case when visitor data is not found
                    Log::add('Visitor data not found for consent_uuid: ' . $consent_uuid, Log::WARNING, 'jerror');
                    return false;
                }

                return true;
            } catch (Exception $e) {
                // Log the error and return false or handle the error
                Log::add('Database update error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                Factory::getApplication()->enqueueMessage('There was a problem updating the data in the database.', 'error');
                return false;
            }
        }
    
        function aesirx_analytics_find_visitor_by_uuid($uuid) {
            try {
                 // Get Joomla database object
                $db = Factory::getDbo();
                $inputFilter = InputFilter::getInstance();

                // Sanitize input
                $cleanedUuid = $inputFilter->clean($uuid, 'STRING');

                // Prepare the visitor query
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__analytics_visitors'))
                    ->where($db->quoteName('uuid') . ' = ' . $db->quote($cleanedUuid));

                // Execute the visitor query
                $db->setQuery($query);
                $visitorResult = $db->loadObject();

                if (!$visitorResult) {
                    return null; // No visitor found
                }

                // Prepare the flows query
                $flowsQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_flows'))
                ->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($cleanedUuid))
                ->order('id');

                // Execute the flows query
                $db->setQuery($flowsQuery);
                $flowsResult = $db->loadObjectList();
    
                // Create the visitor object
                $visitor = (object)[
                    'fingerprint' => $visitorResult->fingerprint,
                    'uuid' => $visitorResult->uuid,
                    'ip' => $visitorResult->ip,
                    'user_agent' => $visitorResult->user_agent,
                    'device' => $visitorResult->device,
                    'browser_name' => $visitorResult->browser_name,
                    'browser_version' => $visitorResult->browser_version,
                    'domain' => $visitorResult->domain,
                    'lang' => $visitorResult->lang,
                    'visitor_flows' => null,
                    'geo' => null,
                    'visitor_consents' => [], // Assuming consents will be added later
                ];
    
                 // Add geo information if available
                if ($visitorResult->geo_created_at) {
                    $visitor->geo = (object)[
                        'country' => (object)[
                            'name' => $visitorResult->country_name,
                            'code' => $visitorResult->country_code,
                        ],
                        'city' => $visitorResult->city,
                        'region' => $visitorResult->region,
                        'isp' => $visitorResult->isp,
                        'created_at' => $visitorResult->geo_created_at,
                    ];
                }
    
                // Add visitor flows if available
                if (!empty($flowsResult)) {
                    $visitor->visitor_flows = array_map(function($flow) {
                        return (object)[
                            'uuid' => $flow->uuid,
                            'start' => $flow->start,
                            'end' => $flow->end,
                            'multiple_events' => $flow->multiple_events,
                        ];
                    }, $flowsResult);
                }

                return $visitor;
            } catch (Exception $e) {
                // Log the error and return null
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                Factory::getApplication()->enqueueMessage('There was a problem with the database query.', 'error');
                return null;
            }
        }

        function aesirx_analytics_find_event_by_uuid($eventUuid, $visitorUuid = null) {
            try {
                // Get Joomla database object and input filter
                $db = Factory::getDbo();
                $inputFilter = InputFilter::getInstance();
        
                // Sanitize input
                $cleanedEventUuid = $inputFilter->clean($eventUuid, 'STRING');
                $cleanedVisitorUuid = $visitorUuid ? $inputFilter->clean($visitorUuid, 'STRING') : null;

                // Prepare the query to find the event by UUID
                $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_events'))
                ->where($db->quoteName('uuid') . ' = ' . $db->quote($cleanedEventUuid));

                // Add condition for visitor_uuid if provided
                if ($cleanedVisitorUuid !== null) {
                    $query->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($cleanedVisitorUuid));
                }

                 // Execute the query
                $db->setQuery($query);
                $event = $db->loadObject();

                if (!$event) {
                    return null; // No event found
                }

                // Prepare the query for event attributes
                $attributesQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__analytics_event_attributes'))
                ->where($db->quoteName('event_uuid') . ' = ' . $db->quote($cleanedEventUuid));

                // Execute the query for event attributes
                $db->setQuery($attributesQuery);
                $attributes = $db->loadObjectList();

                // Construct the VisitorEventRaw object
                $visitorEventRaw = (object) [
                    'uuid' => $event->uuid,
                    'visitor_uuid' => $event->visitor_uuid,
                    'flow_uuid' => $event->flow_uuid,
                    'url' => $event->url,
                    'referer' => $event->referer,
                    'start' => $event->start,
                    'end' => $event->end,
                    'event_name' => $event->event_name,
                    'event_type' => $event->event_type,
                    'attributes' => []
                ];

                // Convert attributes if available
                if (!empty($attributes)) {
                    foreach ($attributes as $attr) {
                        $visitorEventRaw->attributes[] = (object)[
                            'name' => $attr->name,
                            'value' => $attr->value
                        ];
                    }
                }
        
                return $visitorEventRaw;
            } catch (Exception $e) {
                // Log the error
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'jerror');
                Factory::getApplication()->enqueueMessage('There was a problem with the database query.', 'error');
                return null;
            }
        }
    
        function aesirx_analytics_list_consent_common($consents, $visitors, $flows) {
            $list = new \stdClass();
            $list_visitors = [];
            $list_flows = [];
    
            // Assuming $flows is an array of flow data
            foreach ($flows as $flow) {
                $flow = (array) $flow;
                $visitor_uuid = $flow['visitor_uuid'];
                $visitor_vec = isset($list_flows[$visitor_uuid]) ? $list_flows[$visitor_uuid] : [];
                $visitor_vec[] = array(
                    $flow['uuid'],
                    $flow['start'],
                    $flow['end'],
                    $flow['multiple_events']
                );
                $list_flows[$visitor_uuid] = $visitor_vec;
            }
    
            // Assuming $visitors is an array of visitor data
            foreach ($visitors as $visitor) {
                $visitor = (array) $visitor;
                $consent_uuid = $visitor['consent_uuid'];
                $visitor_vec = isset($list_visitors[$consent_uuid]) ? $list_visitors[$consent_uuid] : [];
                $geo_created_at = isset($visitor['geo_created_at']) ? $visitor['geo_created_at'] : null;
                $visitor_vec[] = [
                    'fingerprint' => isset($visitor['fingerprint']) ? $visitor['fingerprint'] : null,
                    'uuid' => $visitor['uuid'],
                    'ip' => $visitor['ip'],
                    'user_agent' => $visitor['user_agent'],
                    'device' => $visitor['device'],
                    'browser_name' => $visitor['browser_name'],
                    'browser_version' => $visitor['browser_version'],
                    'domain' => $visitor['domain'],
                    'lang' => $visitor['lang'],
                    'visitor_flows' => isset($list_flows[$visitor['uuid']]) ? $list_flows[$visitor['uuid']] : null,
                    'geo' => $geo_created_at ? array(
                        $visitor['code'] ?? null,
                        $visitor['name'] ?? null,
                        $visitor['city'] ?? null,
                        $visitor['region'] ?? null,
                        $visitor['isp'] ?? null,
                        $geo_created_at
                    ) : null
                ];
    
                $list_visitors[$consent_uuid] = $visitor_vec;
            }
    
            // Assuming $consents is an array of consent data
            foreach ($consents as $consent) {
                $consent = (array) $consent;
                $uuid_string = $consent['uuid'];
                $outgoing_consent = new \stdClass();
                $outgoing_consent->uuid = $uuid_string;
                $outgoing_consent->wallet_uuid = isset($consent['wallet_uuid']) ? $consent['wallet_uuid'] : null;
                $outgoing_consent->address = $consent['address'] ?? null;
                $outgoing_consent->network = $consent['network'] ?? null;
                $outgoing_consent->web3id = $consent['web3id'] ?? null;
                $outgoing_consent->consent = $consent['consent'];
                $outgoing_consent->datetime = $consent['datetime'];
                $outgoing_consent->expiration = isset($consent['expiration']) ? $consent['expiration'] : null;
                $outgoing_consent->visitor = isset($list_visitors[$uuid_string]) ? $list_visitors[$uuid_string] : [];
    
                $list->consents[] = $outgoing_consent;
            }
    
            if (!empty($list->consents)) {
                $list->consents_by_domain = self::aesirx_analytics_group_consents_by_domains($list->consents);
            }
    
            return $list;
        }

        function aesirx_analytics_get_ip_list_without_geo($params = []) {
            try {

                $allowed = [];
                $bind = [];
                // Get the Joomla database object
                $db = Factory::getDbo();
        
                // Prepare the query to select distinct IPs where geo data is missing
                $sql = $db->getQuery(true)
                    ->select('DISTINCT ip')
                    ->from($db->quoteName('#__analytics_visitors'))
                    ->where($db->quoteName('geo_created_at') . ' IS NULL');
        
                // Prepare the query to count the total number of distinct IPs
                $total_sql = $db->getQuery(true)
                    ->select('COUNT(DISTINCT ip) as total')
                    ->from($db->quoteName('#__analytics_visitors'))
                    ->where($db->quoteName('geo_created_at') . ' IS NULL');

                // Assuming aesirx_analytics_get_list is a custom function for handling list response
                $list_response = self::aesirx_analytics_get_list($sql, $total_sql, $params, $allowed = [], $bind = []);

                // Check if list_respons is false or returns an error and return
                if ($list_response === false || (is_array($list_response) && isset($list_response['error']))) {
                    return $list_response; // Return the error or false response
                }

                // Continue processing the response and extract IPs
                $list = $list_response['collection'];
                $ips = [];

                foreach ($list as $one) {
                    $ips[] = $one['ip'];
                }

                // Return the processed list of IPs
                return $ips;
            } catch (Exception $e) {
                    // Log any errors
                    Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                    Factory::getApplication()->enqueueMessage('There was a problem with the database query.', 'error');
                    return false;
            }
        }

        function aesirx_analytics_update_null_geo_per_ip($ip, $geo) {
            try {
                $db = Factory::getDbo();
                $inputFilter = InputFilter::getInstance();

                $sanitizedData = array(
                    'isp'           => $inputFilter->clean($geo['isp'], 'STRING'),
                    'country_code'  => $inputFilter->clean($geo['country']['code'], 'STRING'),
                    'country_name'  => $inputFilter->clean($geo['country']['name'], 'STRING'),
                    'city'          => $inputFilter->clean($geo['city'], 'STRING'),
                    'region'        => $inputFilter->clean($geo['region'], 'STRING'),
                    'geo_created_at'=> gmdate('Y-m-d H:i:s', strtotime($geo['created_at'])),
                );

                // Sanitize the IP using InputFilter
                $sanitizedIp = $inputFilter->clean($ip, 'STRING');

                // Prepare the update query
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__analytics_visitors')) // Use Joomla table with automatic prefixing
                    ->set($db->quoteName('isp') . ' = ' . $db->quote($sanitizedData['isp']))
                    ->set($db->quoteName('country_code') . ' = ' . $db->quote($sanitizedData['country_code']))
                    ->set($db->quoteName('country_name') . ' = ' . $db->quote($sanitizedData['country_name']))
                    ->set($db->quoteName('city') . ' = ' . $db->quote($sanitizedData['city']))
                    ->set($db->quoteName('region') . ' = ' . $db->quote($sanitizedData['region']))
                    ->set($db->quoteName('geo_created_at') . ' = ' . $db->quote($sanitizedData['geo_created_at']))
                    ->where($db->quoteName('geo_created_at') . ' IS NULL')
                    ->where($db->quoteName('ip') . ' = ' . $db->quote($sanitizedIp));
                
                // Execute the query
                $db->setQuery($query);
                $result = $db->execute();

                // Return the result
                return $result;
            } catch (Exception $e) {
                Log::add('Error updating geo data for IP: ' . $sanitizedIp . ' - ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return false;
            }
        }

        function aesirx_analytics_update_geo_per_uuid($uuid, $geo) {
            $db = Factory::getDbo();
            $inputFilter = InputFilter::getInstance();
        
            // Sanitize the input values
            $uuid = $inputFilter->clean($uuid, 'STRING');
            $isp = $inputFilter->clean($geo['isp'], 'STRING');
            $country_code = $inputFilter->clean($geo['country']['code'], 'STRING');
            $country_name = $inputFilter->clean($geo['country']['name'], 'STRING');
            $city = $inputFilter->clean($geo['city'], 'STRING');
            $region = $inputFilter->clean($geo['region'], 'STRING');
            $geo_created_at = gmdate('Y-m-d H:i:s', strtotime($geo['created_at']));

            // Prepare the update query
            $query = $db->getQuery(true)
            ->update($db->quoteName('#__analytics_visitors'))
            ->set($db->quoteName('isp') . ' = ' . $db->quote($isp))
            ->set($db->quoteName('country_code') . ' = ' . $db->quote($country_code))
            ->set($db->quoteName('country_name') . ' = ' . $db->quote($country_name))
            ->set($db->quoteName('city') . ' = ' . $db->quote($city))
            ->set($db->quoteName('region') . ' = ' . $db->quote($region))
            ->set($db->quoteName('geo_created_at') . ' = ' . $db->quote($geo_created_at))
            ->where($db->quoteName('uuid') . ' = ' . $db->quote($uuid));
            
            try {
                // Execute the query
                $db->setQuery($query);
                $db->execute();
        
                return true;
            } catch (Exception $e) {
                // Log the error and throw an exception
                Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('PLG_SYSTEM_AESIRX_ANALYTICS_ERROR_UPDATE_GEO_PER_IP'), 500);
            }
        }

        function aesirx_analytics_decode_web3id ($token) {
            // Sanitize token
            $inputFilter = InputFilter::getInstance();
            $token = $inputFilter->clean($token, 'STRING');

            // API URL
            $apiUrl = 'http://dev01.aesirx.io:8888/check/web3id';

            // HTTP options for Joomla
            $options = array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token
            );

            try {
                // Initialize HTTP client
                $http = HttpFactory::getHttp();
                $response = $http->get($apiUrl, $options);

                // If the request was successful
                if ($response->code === 200) {
                    $body = json_decode($response->body, true);
                    return $body;
                } else {
                    // Log the error if the status code is not 200
                    Log::add('API error: Received HTTP ' . $response->code, Log::ERROR, 'aesirx-analytics');
                    return false;
                }
            } catch (\RuntimeException $e) {
                // Log the error and return a meaningful error message
                Log::add('API request error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                throw new Exception(Text::_('JERROR_AN_ERROR_OCCURRED'), 500);
            }
        }

        function aesirx_analytics_fetch_open_graph_data($url) {
            // Initialize HTTP client
            $http = HttpFactory::getHttp();

            try {
                // Fetch the page content
                $response = $http->get($url);

                // Check if the response is valid (HTTP 200 OK)
                if ($response->code !== 200) {
                    Log::add('Failed to fetch the page: Received HTTP ' . $response->code, Log::ERROR, 'aesirx-analytics');
                    return null;
                }

                // Get the body of the response
                $html = $response->body;

                // Check if the HTML content is empty
                if (empty($html)) {
                    Log::add('Empty response body for URL: ' . $url, Log::ERROR, 'aesirx-analytics');
                    return null;
                }

                // Parse Open Graph data using DOMDocument
                $og_data = [];
                $dom = new \DOMDocument();
                @$dom->loadHTML($html);
                $xpath = new \DOMXPath($dom);

                // Extract Open Graph meta tags
                foreach ($xpath->query('//meta[@property]') as $meta) {
                    $property = $meta->getAttribute('property');
                    $content = $meta->getAttribute('content');
                    if (strpos($property, 'og:') === 0) {
                        $og_data[$property] = $content;
                    }
                }

                return $og_data;
            } catch (\RuntimeException $e) {
                // Log error and return null
                Log::add('Failed to fetch the page: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
                return null;
            }
        }
    }
}