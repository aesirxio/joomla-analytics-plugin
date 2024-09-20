<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Ramsey\Uuid\Uuid;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

Class AesirX_Analytics_Start_Fingerprint extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $start = gmdate('Y-m-d H:i:s');

         // Validate the domain
        $domain = parent::aesirx_analytics_validate_domain($params['request']['url']);
        if (!$domain || $domain instanceof Exception) {
            Factory::getApplication()->enqueueMessage(Text::_('Invalid domain'), 'error');
            return false;
        }

        $visitor = parent::aesirx_analytics_find_visitor_by_fingerprint_and_domain($params['request']['fingerprint'], $domain);

        if (!$visitor) {
            // Create a new visitor and visitor flow
            $new_visitor_flow = [
                'uuid' => Uuid::uuid4()->toString(),
                'start' => $start,
                'end' => $start,
                'multiple_events' => false,
            ];
    
            $new_visitor = [
                'fingerprint' => $params['request']['fingerprint'],
                'uuid' => Uuid::uuid4()->toString(),
                'ip' => $params['request']['ip'],
                'user_agent' => $params['request']['user_agent'],
                'device' => $params['request']['device'],
                'browser_name' => $params['request']['browser_name'],
                'browser_version' => $params['request']['browser_version'],
                'domain' => $domain,
                'lang' => $params['request']['lang'],
                'visitor_flows' => [$new_visitor_flow],
            ];
    
            $new_visitor_event = [
                'uuid' => Uuid::uuid4()->toString(),
                'visitor_uuid' => $new_visitor['uuid'],
                'flow_uuid' => $new_visitor_flow['uuid'],
                'url' => $params['request']['url'],
                'referer' => $params['request']['referer'] ?? '',
                'start' => $start,
                'end' => $start,
                'event_name' => $params['request']['event_name'] ?? 'visit',
                'event_type' => $params['request']['event_type'] ?? 'action',
                'attributes' => isset($params['request']['attributes']) ? $params['request']['attributes'] : '',
            ];
    
            parent::aesirx_analytics_create_visitor($new_visitor);
            parent::aesirx_analytics_create_visitor_event($new_visitor_event);
    
            return [
                'visitor_uuid' => $new_visitor['uuid'],
                'event_uuid' => $new_visitor_event['uuid'],
                'flow_uuid' => $new_visitor_event['flow_uuid'],
            ];
        } else {
            // Parse the URL and check if the domain matches the visitor's domain
            $url = Uri::getInstance($params['request']['url']);
            if (!$url->getHost()) {
                Factory::getApplication()->enqueueMessage(Text::_('Wrong URL format, domain not found'), 'error');
                return false;
            }

            if ($url->getHost() != $visitor['domain']) {
                Factory::getApplication()->enqueueMessage(Text::_('The domain sent in the new URL does not match the domain stored in the visitor document'), 'error');
                return false;
            }

            $create_flow = true;
            $visitor_flow = [
                'uuid' => Uuid::uuid4()->toString(),
                'start' => $start,
                'end' => $start,
                'multiple_events' => false,
            ];
            $is_already_multiple = false;
    
            if (isset($params['request']['referer']) && $params['request']['referer']) {
                $referer = Uri::getInstance($params['request']['referer']);
                if ($referer && $referer->getHost() == $url->getHost() && $visitor['visitor_flows']) {
                    $list = $visitor['visitor_flows'];
                    if (!empty($list)) {
                        $first = $list[0];
                        $max = $first['start'];
                        $visitor_flow['uuid'] = $first['uuid'];
                        $is_already_multiple = $first['multiple_events'];
                        $create_flow = false;
    
                        foreach ($list as $val) {
                            if ($max < $val['start']) {
                                $max = $val['start'];
                                $visitor_flow['uuid'] = $val['uuid'];
                            }
                        }
                    }
                }
            }
    
            if ($create_flow) {
                parent::aesirx_analytics_create_visitor_flow($visitor['uuid'], $visitor_flow);
            }
    
            // Create a new visitor event
            $visitor_event = [
                'uuid' => Uuid::uuid4()->toString(),
                'visitor_uuid' => $visitor['uuid'],
                'flow_uuid' => $visitor_flow['uuid'],
                'url' => $params['request']['url'],
                'referer' => isset($params['request']['referer']) ? $params['request']['referer'] : '',
                'start' => $start,
                'end' => $start,
                'event_name' => $params['request']['event_name'] ?? 'visit',
                'event_type' => $params['request']['event_type'] ?? 'action',
                'attributes' => $params['request']['attributes'] ?? '',
            ];
    
            parent::aesirx_analytics_create_visitor_event($visitor_event);

            if (!$create_flow && !$is_already_multiple) {
                parent::aesirx_analytics_mark_visitor_flow_as_multiple($visitor_flow['uuid']);
            }
    
            return [
                'visitor_uuid' => $visitor['uuid'],
                'event_uuid' => $visitor_event['uuid'],
                'flow_uuid' => $visitor_event['flow_uuid'],
            ];
        }
    }
}
