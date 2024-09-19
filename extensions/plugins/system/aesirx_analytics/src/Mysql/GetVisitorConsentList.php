<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Get_Visitor_Consent_List extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        $uuid = $inputFilter->clean($params['uuid'], 'STRING');

        // Query to get visitor data
        $visitorQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__analytics_visitors'))
            ->where($db->quoteName('uuid') . ' = ' . $db->quote($uuid));

        // Execute visitor query
        $db->setQuery($visitorQuery);
        $visitor = $db->loadObject();

        // Query to get flow data
        $flowQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__analytics_flows'))
            ->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($uuid))
            ->order($db->quoteName('id') . ' ASC');

        // Execute flow query
        $db->setQuery($flowQuery);
        $flows = $db->loadObjectList();

        $consentQuery = $db->getQuery(true);

        // handle expiration
        if (!isset($params['expired']) || is_null($params['expired']) || !$params['expired']) {
            $consentQuery
            ->select([
                'vc.*',
                'c.web3id',
                'c.consent AS consent_from_consent',
                'w.network',
                'w.address',
                'c.expiration AS consent_expiration',
                'c.datetime AS consent_datetime'
            ])
            ->from($db->quoteName('#__analytics_visitor_consent', 'vc'))
            ->leftJoin($db->quoteName('#__analytics_consent', 'c') . ' ON ' . $db->quoteName('vc.consent_uuid') . ' = ' . $db->quoteName('c.uuid'))
            ->leftJoin($db->quoteName('#__analytics_wallet', 'w') . ' ON ' . $db->quoteName('c.wallet_uuid') . ' = ' . $db->quoteName('w.uuid'))
            ->where($db->quoteName('vc.visitor_uuid') . ' = ' . $db->quote($uuid))
            ->where('(' . $db->quoteName('vc.expiration') . ' >= ' . $db->quote(gmdate('Y-m-d H:i:s')) . ' OR ' . $db->quoteName('vc.expiration') . ' IS NULL)')
            ->where('(' . $db->quoteName('c.uuid') . ' IS NULL OR ' . $db->quoteName('c.expiration') . ' IS NULL)')
            ->order($db->quoteName('vc.datetime') . ' ASC');

        } else {
            $consentQuery
            ->select([
                'vc.*',
                'c.web3id',
                'c.consent AS consent_from_consent',
                'w.network',
                'w.address',
                'c.expiration AS consent_expiration',
                'c.datetime AS consent_datetime'
            ])
            ->from($db->quoteName('#__analytics_visitor_consent', 'vc'))
            ->leftJoin($db->quoteName('#__analytics_consent', 'c') . ' ON ' . $db->quoteName('vc.consent_uuid') . ' = ' . $db->quoteName('c.uuid'))
            ->leftJoin($db->quoteName('#__analytics_wallet', 'w') . ' ON ' . $db->quoteName('c.wallet_uuid') . ' = ' . $db->quoteName('w.uuid'))
            ->where($db->quoteName('vc.visitor_uuid') . ' = ' . $db->quote($uuid))
            ->order($db->quoteName('vc.datetime') . ' ASC');
        }

        try {
            // Execute consent query
            $db->setQuery($consentQuery);
            $consents = $db->loadObjectList();
        } catch (Exception $e) {
            Log::add('Query error: ' . $e->getMessage(), Log::ERROR, 'aesirx-analytics');
            throw new Exception(Text::_('JERROR_AN_ERROR_OCCURRED'), 500);
        }

        // Prepare the result if the visitor exists
        if ($visitor) {
            $res = [
                'uuid' => $visitor->uuid,
                'ip' => $visitor->ip,
                'user_agent' => $visitor->user_agent,
                'device' => $visitor->device,
                'browser_name' => $visitor->browser_name,
                'browser_version' => $visitor->browser_version,
                'domain' => $visitor->domain,
                'lang' => $visitor->lang,
                'visitor_flows' => [],
                'geo' => null,
                'visitor_consents' => []
            ];

            // Handle geo data if available
            if ($visitor->geo_created_at) {
                $res['geo'] = [
                    'country' => [
                        'name' => $visitor->country_name,
                        'code' => $visitor->country_code
                    ],
                    'city' => $visitor->city,
                    'isp' => $visitor->isp,
                    'created_at' => $visitor->geo_created_at
                ];
            }

            // Process visitor flows
            foreach ($flows as $flow) {
                $res['visitor_flows'][] = [
                    'uuid' => $flow->uuid,
                    'start' => $flow->start,
                    'end' => $flow->end,
                    'multiple_events' => $flow->multiple_events
                ];
            }

            // Process consents
            foreach ($consents as $consent) {
                $res['visitor_consents'][] = [
                    'consent_uuid' => $consent->consent_uuid,
                    'consent' => $consent->consent_from_consent ?? $consent->consent ?? null,
                    'datetime' => $consent->consent_datetime ?? $consent->datetime,
                    'expiration' => $consent->consent_expiration ?? $consent->expiration,
                    'address' => $consent->address,
                    'network' => $consent->network,
                    'web3id' => $consent->web3id
                ];
            }

            return $res;
        } else {
            return null;
        }
    }
}
