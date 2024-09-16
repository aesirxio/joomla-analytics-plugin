<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Add_Consent_Level2 extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $web3idObj = parent::aesirx_analytics_decode_web3id($params['token']) ?? '';

        if ($web3idObj instanceof Exception) {
            return $web3idObj;
        }

        if (!$web3idObj || !isset($web3idObj['web3id'])) {
            throw new Exception(Text::_('Invalid token'), 400);
        }

        $web3id = $web3idObj['web3id'];
    
        $visitor = parent::aesirx_analytics_find_visitor_by_uuid($params['visitor_uuid']);

        if (!$visitor || $visitor instanceof Exception) {
            throw new Exception(Text::_('Visitor not found'), 400);
        }

        $found_consent = [];

        $consent_list = self::list_consent_level2($web3id, $visitor->domain, null);

        if ($consent_list instanceof Exception) {
            return $consent_list;
        }

        if ($consent_list) {
            foreach ($consent_list as $one_consent) {
                if (in_array($params['visitor_uuid'], array_column($one_consent->visitor, 'uuid'))) {
                    foreach ($params['consents'] as $consent) {
                        if (intval($consent) == $one_consent->consent) {
                            throw new Exception(Text::_('Previous consent still active'), 400);
                        }
                    }
                }
                $found_consent[$one_consent->consent] = $one_consent->uuid;
            }
        }

        foreach ($params['request']['consent'] as $consent) {
            $uuid = $found_consent[intval($consent)] ?? null;
    
            if (!$uuid) {
                $uuid = Factory::getUUID();
    
                $datetime = gmdate('Y-m-d H:i:s');
                parent::aesirx_analytics_add_consent($uuid, intval($consent), $datetime, $web3id);
            }
    
            $datetime = gmdate('Y-m-d H:i:s');
            parent::aesirx_analytics_add_visitor_consent($params['visitor_uuid'], $uuid, null, $datetime);
        }

        return true;
    }

    function list_consent_level2($web3id, $domain, $consent) {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        $cleanDomain = $inputFilter->clean($domain, 'STRING');
        $cleanWeb3id = $inputFilter->clean($web3id, 'STRING');

        $dom = $cleanDomain ? $db->quoteName('visitor.domain') . ' = ' . $db->quote($cleanDomain) : "";

        $query = $db->getQuery(true);
        $query->select('consent.*')
            ->from('#__analytics_consent AS consent')
            ->leftJoin('#__analytics_visitor_consent AS visitor_consent ON consent.uuid = visitor_consent.consent_uuid')
            ->leftJoin('#__analytics_visitors AS visitor ON visitor_consent.visitor_uuid = visitor.uuid')
            ->where('consent.wallet_uuid IS NULL')
            ->where($db->quoteName('consent.web3id') . ' = ' . $db->quote($cleanWeb3id))
            ->where($dom)
            ->group($db->quoteName('consent.uuid'));

        // Execute the query
        $db->setQuery($query);
        try {
            $consents = $db->loadObjectList();
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception(Text::_('There was a problem querying the data in the database.'), 500);
        }

        // Fetch visitors
        $query->clear()
            ->select('visitor.*, visitor_consent.consent_uuid')
            ->from('#__analytics_visitors AS visitor')
            ->leftJoin('#__analytics_visitor_consent AS visitor_consent ON visitor_consent.visitor_uuid = visitor.uuid')
            ->leftJoin('#__analytics_consent AS consent ON consent.uuid = visitor_consent.consent_uuid')
            ->where('consent.wallet_uuid IS NULL')
            ->where($db->quoteName('consent.web3id') . ' = ' . $db->quote($cleanWeb3id))
            ->where($dom);

        // Execute the query
        $db->setQuery($query);
        try {
            $visitors = $db->loadObjectList();
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            throw new Exception(Text::_('There was a problem querying the data in the database.'), 500);
        }

        // Fetch flows
        $query->clear()
            ->select('flows.*')
            ->from('#__analytics_flows AS flows')
            ->leftJoin('#__analytics_visitors AS visitor ON visitor.uuid = flows.visitor_uuid')
            ->leftJoin('#__analytics_visitor_consent AS visitor_consent ON visitor_consent.visitor_uuid = visitor.uuid')
            ->leftJoin('#__analytics_consent AS consent ON consent.uuid = visitor_consent.consent_uuid')
            ->where('consent.wallet_uuid IS NULL')
            ->where($db->quoteName('consent.web3id') . ' = ' . $db->quote($cleanWeb3id))
            ->where($dom)
            ->order('id');

        // Execute the query
        $db->setQuery($query);
        try {
            $flows = $db->loadObjectList();
        } catch (Exception $e) {
            ("Query error: " . $e->getMessage());
            throw new Exception(Text::_('There was a problem querying the data in the database.'), 500);
        }

        return parent::aesirx_analytics_list_consent_common($consents, $visitors, $flows);
    }
}