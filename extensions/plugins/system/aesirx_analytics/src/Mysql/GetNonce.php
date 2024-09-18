<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\Utilities\Uuid;

Class AesirX_Analytics_Get_Nonce extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        $inputFilter = InputFilter::getInstance();

        $validate_address = parent::aesirx_analytics_validate_address($params['address']);

        // Handle invalid address error using Joomla's error handling system
        if (!$validate_address || $validate_address instanceof Exception) {
            return Factory::getApplication()->enqueueMessage('Address is not valid', 'error');
        }

        // Generate a random number using PHP's built-in function
        $num = (string) rand(10000, 99999);

        if (is_null($params['request']['text'])) {
            $num = "Please sign nonce $num issued by {$params['domain']} in " . gmdate('Y-m-d H:i:s');
        } else {
            $text = $params['request']['text'];
            if (strpos($text, '{nonce}') === false || strpos($text, '{domain}') === false || strpos($text, '{time}') === false) {
                return false;
            }

            $num = str_replace('{nonce}', $num, $text);
            $num = str_replace('{domain}', $params['domain'], $num);
            $num = str_replace('{time}', gmdate('Y-m-d H:i:s'), $num);
        }

        $num = $inputFilter->clean($num, 'STRING');

        $wallet = parent::aesirx_analytics_find_wallet($params['network'], $params['address']);

        if ($wallet instanceof Exception) {
            return $wallet;
        }

        if ($wallet) {
            parent::aesirx_analytics_update_nonce($params['network'], $params['address'], $num);
        } else {
            $uuid = Uuid::v4();
            parent::aesirx_analytics_add_wallet($uuid, $params['network'], $params['address'], $num);
        }

        return ['nonce' => $num];
    }
}
