<?php

use AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Revoke_Consent_Level1 extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        // Validate and sanitize each parameter in the $params array
        $validated_params = [];
        foreach ($params as $key => $value) {
            $validated_params[$key] = $inputFilter->clean($value, 'STRING');
        }

        $expiration = gmdate('Y-m-d H:i:s');
        $visitor_uuid = $validated_params['visitor_uuid'];

        // Prepare and execute the first update query for the #__analytics_visitor_consent table
        $query = $db->getQuery(true);
        $fields = [
            $db->quoteName('expiration') . ' = ' . $db->quote($expiration)
        ];
        $conditions = [
            $db->quoteName('visitor_uuid') . ' = ' . $db->quote($visitor_uuid),
            $db->quoteName('consent_uuid') . ' IS NULL',
            $db->quoteName('expiration') . ' IS NULL'
        ];

        $query->update($db->quoteName('#__analytics_visitor_consent'))
            ->set($fields)
            ->where($conditions);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage('Database update error: ' . $e->getMessage(), 'error');
            return false;
        }

        // Prepare and execute the second update query for the #__analytics_visitors table
        $query = $db->getQuery(true);
        $fields = [
            $db->quoteName('ip') . ' = ""',
            $db->quoteName('lang') . ' = ""',
            $db->quoteName('browser_version') . ' = ""',
            $db->quoteName('browser_name') . ' = ""',
            $db->quoteName('device') . ' = ""',
            $db->quoteName('user_agent') . ' = ""'
        ];
        $conditions = [
            $db->quoteName('uuid') . ' = ' . $db->quote($visitor_uuid)
        ];

        $query->update($db->quoteName('#__analytics_visitors'))
            ->set($fields)
            ->where($conditions);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage('Database update error: ' . $e->getMessage(), 'error');
            return false;
        }
        
        return true;
    }
}