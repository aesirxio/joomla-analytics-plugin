<?php


use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;

Class AesirX_Analytics_Get_All_Consents extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get Joomla database object
        $db = Factory::getDbo();
        $bind = [];

        $where_clause = ["COALESCE(consent.consent, visitor_consent.consent) = 1"];

        parent::aesirx_analytics_add_consent_filters($params, $where_clause, $bind);

        // Building the SELECT query
        $sql = $db->getQuery(true)
            ->select([
                'visitor_consent.consent_uuid AS uuid',
                'consent.web3id',
                'COALESCE(consent.consent, visitor_consent.consent) AS consent',
                'COALESCE(consent.datetime, visitor_consent.datetime) AS datetime',
                'COALESCE(consent.expiration, visitor_consent.expiration) AS expiration',
                'wallet.uuid AS wallet_uuid',
                'wallet.address AS address',
                'wallet.network AS network',
                'CASE 
                    WHEN visitor_consent.consent_uuid IS NULL THEN 1 
                    WHEN consent.web3id IS NOT NULL AND consent.wallet_uuid IS NOT NULL THEN 4 
                    WHEN consent.web3id IS NULL AND consent.wallet_uuid IS NOT NULL THEN 3 
                    WHEN consent.web3id IS NOT NULL AND consent.wallet_uuid IS NULL THEN 2 
                    ELSE 1 
                END AS tier',
            ])
            ->from($db->quoteName('#__analytics_visitor_consent', 'visitor_consent'))
            ->leftJoin($db->quoteName('#__analytics_visitors', 'visitors') . ' ON visitors.uuid = visitor_consent.visitor_uuid')
            ->leftJoin($db->quoteName('#__analytics_consent', 'consent') . ' ON consent.uuid = visitor_consent.consent_uuid')
            ->leftJoin($db->quoteName('#__analytics_wallet', 'wallet') . ' ON wallet.uuid = consent.wallet_uuid')
            ->where(implode(' AND ', $where_clause));

         // Build the total query
         $total_sql = $db->getQuery(true)
            ->select('COUNT(visitor_consent.uuid) AS total')
            ->from($db->quoteName('#__analytics_visitor_consent', 'visitor_consent'))
            ->leftJoin($db->quoteName('#__analytics_visitors', 'visitors') . ' ON visitors.uuid = visitor_consent.visitor_uuid')
            ->leftJoin($db->quoteName('#__analytics_consent', 'consent') . ' ON consent.uuid = visitor_consent.consent_uuid')
            ->leftJoin($db->quoteName('#__analytics_wallet', 'wallet') . ' ON wallet.uuid = consent.wallet_uuid')
            ->where(implode(' AND ', $where_clause));

        // Adding sorting
        $sort = self::aesirx_analytics_add_sort(
            $params,
            [
                "datetime",
                "expiration",
                "consent",
                "tier",
                "web3id",
                "wallet",
            ],
            "datetime"
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

        foreach ($list as $one) {
            $one = (object) $one;
            $uuid = isset($one->uuid) ? $one->uuid : null;
            $wallet = isset($one->wallet_uuid) ? (object)[
                'uuid' => $one->wallet_uuid,
                'address' => $one->address,
                'network' => $one->network,
            ] : null;
            
            $collection[] = (object)[
                'uuid' => $uuid,
                'tier' => $one->tier,
                'web3id' => $one->web3id,
                'consent' => $one->consent,
                'datetime' => $one->datetime,
                'expiration' => $one->expiration ?? null,
                'wallet' => $wallet,
            ];
        }

        // Returning the collection and pagination details
        return [
            'collection' => $collection,
            'page' => $list_response['page'],
            'page_size' => $list_response['page_size'],
            'total_pages' => $list_response['total_pages'],
            'total_elements' => $list_response['total_elements'],
        ];
    }
}
