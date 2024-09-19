<?php

use Aesirx\System\AesirxAnalytics\AesirxAnalyticsMysqlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filter\InputFilter;

Class AesirX_Analytics_Close_Visitor_Event extends AesirxAnalyticsMysqlHelper
{
    function aesirx_analytics_mysql_execute($params = [])
    {
        // Get Joomla's database object
        $db = Factory::getDbo();
        $inputFilter = InputFilter::getInstance();

        // Validate required parameters
        if (!isset($params['request']['event_uuid']) || empty($params['request']['event_uuid'])) {
            throw new Exception(Text::_('The event uuid parameter is required.'), 400);
        }

        if (!isset($params['request']['visitor_uuid']) || empty($params['request']['visitor_uuid'])) {
            throw new Exception(Text::_('The visitor uuid parameter is required.'), 400);
        }

        $event_uuid = $inputFilter->clean($params['request']['event_uuid'], 'STRING');
        $visitor_uuid = $inputFilter->clean($params['request']['visitor_uuid'], 'STRING');
        
        // Get the current date and time
        $now = gmdate('Y-m-d H:i:s');

        // Update the analytics events table
        $query_update_event = $db->getQuery(true)
            ->update($db->quoteName('#__analytics_events'))
            ->set($db->quoteName('end') . ' = ' . $db->quote($now))
            ->where($db->quoteName('uuid') . ' = ' . $db->quote($event_uuid))
            ->where($db->quoteName('visitor_uuid') . ' = ' . $db->quote($visitor_uuid));

        $db->setQuery($query_update_event);
        try {
            $db->execute();
        } catch (RuntimeException $e) {
            throw new Exception(Text::_('Failed to update analytics events.'), 500);
        }

        // Find the event by UUID and visitor UUID
        $visitor_event = parent::aesirx_analytics_find_event_by_uuid($event_uuid, $visitor_uuid);

        if ($visitor_event === null) {
            throw new Exception(Text::_('Visitor event not found.'), 404);
        }

        // Update the analytics flows table
        $query_update_flows = $db->getQuery(true)
            ->update($db->quoteName('#__analytics_flows'))
            ->set($db->quoteName('end') . ' = ' . $db->quote($now))
            ->where($db->quoteName('uuid') . ' = ' . $db->quote($visitor_event->flow_uuid));

        $db->setQuery($query_update_flows);
        try {
            $db->execute();
        } catch (RuntimeException $e) {
            throw new Exception(Text::_('Failed to update analytics flows'), 500);
        }

        return true;
    }
}
