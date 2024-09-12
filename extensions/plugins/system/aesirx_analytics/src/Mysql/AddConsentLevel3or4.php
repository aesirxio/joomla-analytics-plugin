<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filter\InputFilter;
use Joomla\Utilities\Uuid;

Class AesirX_Analytics_Add_Consent_Level3or4 extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Decode signature
        $decoded = base64_decode($params['request']['signature'], true);
        if ($decoded === false) {
            throw new Exception(Text::_('Invalid signature'), 400);
        }

        // Find visitor by UUID
        $visitor = parent::aesirx_analytics_find_visitor_by_uuid($params['visitor_uuid']);
        if (!$visitor || $visitor instanceof Exception) {
            throw new Exception(Text::_('Visitor not found'), 400);
        }

        // Find wallet by network and wallet address
        $wallet = parent::aesirx_analytics_find_wallet($params['network'], $params['wallet']);
        if (!$wallet || $wallet instanceof Exception) {
            throw new Exception(Text::_('Wallet not found'), 400);
        }

        // Extract nonce from wallet
        $nonce = $wallet->nonce;
        if (!$nonce) {
            throw new Exception(Text::_('Wallet nonce not found'), 400);
        }

        // Validate network using extracted details
        $validate_nonce = parent::aesirx_analytics_validate_string($nonce, $params['wallet'], $params['request']['signature']);
        if (!$validate_nonce || $validate_nonce instanceof Exception) {
            throw new Exception(Text::_('Nonce is not valid'), 400);
        }

        $web3id = null;

        if (!empty($params['token'])) {
            // Validate cocntract by token
            $validate_contract = parent::aesirx_analytics_validate_contract($params['token']);
            if (!$validate_contract || $validate_contract instanceof Exception) {
                throw new Exception(Text::_('Contract is not valid'), 400);
            }

            // Extract web3id from jwt_payload
            $web3idObj = parent::aesirx_analytics_decode_web3id($params['token']) ?? '';
            if (!$web3idObj || !isset($web3idObj['web3id'])) {
                throw new Exception(Text::_('Invalid token'), 400);
            }

            $web3id = $web3idObj['web3id'];
        }

        // Fetch existing consents for level3 or level4
        $found_consent = [];
        $consent_list = self::aesirx_analytics_list_consent_level3_or_level4(
            $web3id,
            $params['wallet'],
            $visitor->domain,
            null
        );

        if ($consent_list instanceof Exception) {
            throw $consent_list;
        }

        if ($consent_list) {
            foreach ($consent_list->consents as $one_consent) {
                // Check if consent is part of the current visitor UUID
                if (in_array($params['visitor_uuid'], array_column($one_consent->visitor, 'uuid'))) {
                    foreach ($params['consents'] as $consent) {
                        if ((int)$consent === $one_consent->consent) {
                            throw new \Exception(Text::_('Previous consent still active'), 400);
                        }
                    }
                }
                // Insert found consents into the map
                $found_consent[$one_consent->consent] = $one_consent->uuid;
            }
        }

        // Process each consent in the request
        foreach ($params['request']['consent'] as $consent) {
            // Determine UUID for consent
            $uuid = $found_consent[(int)$consent] ?? null;
            if (!$uuid) {
                $uuid = Uuid::v4();
                parent::aesirx_analytics_add_consent($uuid, (int)$consent, Factory::getDate()->toSql(), $web3id, $wallet->uuid);
            }

            // Add visitor consent record
            parent::aesirx_analytics_add_visitor_consent($params['visitor_uuid'], $uuid, null, Factory::getDate()->toSql());
        }

        // Update nonce
        parent::aesirx_analytics_update_nonce($params['network'], $params['wallet'], null);

        return true;
    }

    function aesirx_analytics_list_consent_level3_or_level4($web3id, $wallet, $domain, $expired) {
        // Get the database object
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        $web3id = $inputFilter->clean($web3id, 'STRING');
        $wallet = $inputFilter->clean($wallet, 'STRING');
        $domain = $inputFilter->clean($domain, 'STRING');

        // Prepare SQL conditions based on input parameters
        $domain_condition = $domain ? $db->quoteName('visitor.domain') . ' = ' . $db->quote($domain) : "";
        $expired_condition = !$expired ? $db->quoteName('consent.expiration') . ' IS NULL' : "";
        $web3id_condition = $web3id ? $db->quoteName('consent.web3id') . ' = ' . $db->quote($web3id) : "AND consent.web3id IS NULL";

        try {
            // Create a new query object
            $query = $db->getQuery(true);

            // Fetch consents
            $query->select('consent.*, wallet.address')
                ->from($db->quoteName('#__analytics_consent', 'consent'))
                ->leftJoin($db->quoteName('#__analytics_wallet', 'wallet') . ' ON wallet.uuid = consent.wallet_uuid')
                ->leftJoin($db->quoteName('#__analytics_visitor_consent', 'visitor_consent') . ' ON consent.uuid = visitor_consent.consent_uuid')
                ->leftJoin($db->quoteName('#__analytics_visitors', 'visitor') . ' ON visitor_consent.visitor_uuid = visitor.uuid')
                ->where($db->quoteName('wallet.address') . ' = ' . $db->quote($wallet))
                ->where($expired_condition)
                ->where($web3id_condition)
                ->where($domain_condition)
                ->group($db->quoteName('consent.uuid'));

            $consents = $db->setQuery($query)->loadObjectList();

            // Fetch visitors
            $query = $db->getQuery(true);
            $query->select('visitor.*, visitor_consent.consent_uuid')
                ->from($db->quoteName('#__analytics_visitors', 'visitor'))
                ->leftJoin($db->quoteName('#__analytics_visitor_consent', 'visitor_consent') . ' ON visitor_consent.visitor_uuid = visitor.uuid')
                ->leftJoin($db->quoteName('#__analytics_consent', 'consent') . ' ON consent.uuid = visitor_consent.consent_uuid')
                ->leftJoin($db->quoteName('#__analytics_wallet', 'wallet') . ' ON wallet.uuid = consent.wallet_uuid')
                ->where($db->quoteName('wallet.address') . ' = ' . $db->quote($wallet))
                ->where($expired_condition)
                ->where($web3id_condition)
                ->where($domain_condition);

            $visitors = $db->setQuery($query)->loadObjectList();

            // Fetch flows
            $query = $db->getQuery(true);
            $query->select('flows.*')
                ->from($db->quoteName('#__analytics_flows', 'flows'))
                ->leftJoin($db->quoteName('#__analytics_visitors', 'visitor') . ' ON visitor.uuid = flows.visitor_uuid')
                ->leftJoin($db->quoteName('#__analytics_visitor_consent', 'visitor_consent') . ' ON visitor_consent.visitor_uuid = visitor.uuid')
                ->leftJoin($db->quoteName('#__analytics_consent', 'consent') . ' ON consent.uuid = visitor_consent.consent_uuid')
                ->leftJoin($db->quoteName('#__analytics_wallet', 'wallet') . ' ON wallet.uuid = consent.wallet_uuid')
                ->where($db->quoteName('wallet.address') . ' = ' . $db->quote($wallet))
                ->where($expired_condition)
                ->where($web3id_condition)
                ->where($domain_condition)
                ->order('flows.id');

            $flows = $db->setQuery($query)->loadObjectList();

            return parent::aesirx_analytics_list_consent_common($consents, $visitors, $flows);
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(Text::_('There was a problem querying the data in the database.'), 'error');
            return new Exception(Text::_('There was a problem querying the data in the database.'), 500);
        }
    }
}