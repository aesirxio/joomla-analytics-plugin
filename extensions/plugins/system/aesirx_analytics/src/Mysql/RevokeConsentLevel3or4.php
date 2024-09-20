<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Revoke_Consent_Level3or4 extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        // Decode the signature
        $decoded = base64_decode($params['request']['signature']);
        if ($decoded === false) {
            Factory::getApplication()->enqueueMessage(Text::_('Invalid signature'), 'error');
            return false;
        }

        // Sanitize inputs using Joomla's InputFilter
        $network = $inputFilter->clean($params['network'], 'STRING');
        $wallet = $inputFilter->clean($params['wallet'], 'STRING');

        $wallet_row = parent::aesirx_analytics_find_wallet($network, $wallet);

        if (!$wallet_row || $wallet_row instanceof Exception) {
            Factory::getApplication()->enqueueMessage(Text::_('Wallet not found'), 'error');
            return false;
        }

        // Check for nonce
        $nonce = $wallet_row->nonce;
        if (!$nonce) {
            Factory::getApplication()->enqueueMessage(Text::_('Nonce not found'), 'error');
            return false;
        }

        // Validate network using extracted details
        $validate_nonce = parent::aesirx_analytics_validate_string($nonce, $params['wallet'], $params['request']['signature']);

        if (!$validate_nonce || $validate_nonce instanceof Exception) {
            Factory::getApplication()->enqueueMessage(Text::_('Nonce is not valid'), 'error');
            return false;
        }

        if (isset($params['token']) && $params['token']) {
            $validate_contract = parent::aesirx_analytics_validate_contract($params['token']);

            if (!$validate_contract || $validate_contract instanceof Exception) {
                Factory::getApplication()->enqueueMessage(Text::_('Contract is not valid'), 'error');
                return false;
            }
        }

        // Expire the consent
        $expiration = gmdate('Y-m-d H:i:s');
        $consent_uuid = $inputFilter->clean($params['consent_uuid'], 'STRING');

        $result = parent::aesirx_analytics_expired_consent($consent_uuid, $expiration);

        if ($result === false || $result instanceof Exception) {
            Factory::getApplication()->enqueueMessage(Text::_('Failed to update consent expiration'), 'error');
            return false;
        }

        // Update the nonce to None (NULL in this context)
        $resultNonce = parent::aesirx_analytics_update_nonce($network, $wallet, null);

        if ($resultNonce === false || $resultNonce instanceof Exception) {
            Factory::getApplication()->enqueueMessage(Text::_('Failed to update nonce'), 'error');
            return false;
        }

        return true;
    }
}