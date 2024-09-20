<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_Total_Consent_Tier extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $db = Factory::getDbo();

        $where_clause = [];
        $bind = [];

        parent::aesirx_analytics_add_consent_filters($params, $where_clause, $bind);

        // Build the main SQL query
        $sql = $db->getQuery(true)
            ->select([
                'ROUND(COUNT(' . $db->quoteName('visitor_consent.uuid') . ') / 2) AS total',
                "CASE 
                    WHEN " . $db->quoteName('visitor_consent.consent_uuid') . " IS NULL THEN 1
                    WHEN " . $db->quoteName('consent.web3id') . " IS NOT NULL AND " . $db->quoteName('consent.wallet_uuid') . " IS NOT NULL THEN 4
                    WHEN " . $db->quoteName('consent.web3id') . " IS NULL AND " . $db->quoteName('consent.wallet_uuid') . " IS NOT NULL THEN 3
                    WHEN " . $db->quoteName('consent.web3id') . " IS NOT NULL AND " . $db->quoteName('consent.wallet_uuid') . " IS NULL THEN 2
                    ELSE 1 
                END AS tier"
            ])
            ->from($db->quoteName('#__analytics_visitor_consent', 'visitor_consent'))
            ->leftJoin($db->quoteName('#__analytics_visitors', 'visitors') . ' ON ' . $db->quoteName('visitors.uuid') . ' = ' . $db->quoteName('visitor_consent.visitor_uuid'))
            ->leftJoin($db->quoteName('#__analytics_consent', 'consent') . ' ON ' . $db->quoteName('consent.uuid') . ' = ' . $db->quoteName('visitor_consent.consent_uuid'))
            ->where(implode(' AND ', $where_clause))
            ->group($db->quoteName('tier'));

        // Build the total SQL query to get the count of distinct tiers
        $total_sql = $db->getQuery(true)
            ->select("COUNT(DISTINCT CASE 
                    WHEN " . $db->quoteName('visitor_consent.consent_uuid') . " IS NULL THEN 1
                    WHEN " . $db->quoteName('consent.web3id') . " IS NOT NULL AND " . $db->quoteName('consent.wallet_uuid') . " IS NOT NULL THEN 4
                    WHEN " . $db->quoteName('consent.web3id') . " IS NULL AND " . $db->quoteName('consent.wallet_uuid') . " IS NOT NULL THEN 3
                    WHEN " . $db->quoteName('consent.web3id') . " IS NOT NULL AND " . $db->quoteName('consent.wallet_uuid') . " IS NULL THEN 2
                    ELSE 1 
                END) AS total")
            ->from($db->quoteName('#__analytics_visitor_consent', 'visitor_consent'))
            ->leftJoin($db->quoteName('#__analytics_visitors', 'visitors') . ' ON ' . $db->quoteName('visitors.uuid') . ' = ' . $db->quoteName('visitor_consent.visitor_uuid'))
            ->leftJoin($db->quoteName('#__analytics_consent', 'consent') . ' ON ' . $db->quoteName('consent.uuid') . ' = ' . $db->quoteName('visitor_consent.consent_uuid'))
            ->where(implode(' AND ', $where_clause));

        $sort = self::aesirx_analytics_add_sort($params, ["tier", "total"], "tier");

        if (!empty($sort)) {
            $sql->order(implode(', ', $sort));
        }

        return parent::aesirx_analytics_get_list($sql, $total_sql, $params, [], $bind);
    }
}
