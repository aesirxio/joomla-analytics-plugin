<?php
/**
 * @package     AesirxAnalytics\Extension
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @since       __DEPLOY_VERSION__
 */

namespace AesirxAnalytics\Extension;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * @method CMSApplication getApplication()
 *
 * @package     AesirxAnalytics\Extension
 *
 * @since       __DEPLOY_VERSION__
 */
class AesirxAnalyticsExtension extends CMSPlugin implements SubscriberInterface
{
	/**
	 * @return void
	 * @since __DEPLOY_VERSION__
	 */
	public function onAfterDispatch(): void
	{
		$wa = $this->getApplication()->getDocument()->getWebAssetManager();

		if (!$wa->assetExists('script', 'plg_system_aesirx_analytics.analytics'))
		{
			$wa->registerScript('plg_system_aesirx_analytics.analytics', 'plg_system_aesirx_analytics/analytics.js', [], ['defer' => true]);
		}

		$wa->useScript('plg_system_aesirx_analytics.analytics');

		$wa->addInlineScript(
			'
			window.aesirx1stparty="' . $this->params->get('domain') . '";
			',
			['position' => 'before'], [], ['plg_system_aesirx_analytics.analytics']
		);
	}

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		try
		{
			$app = Factory::getApplication();
		}
		catch (Throwable $e)
		{
			return [];
		}

		if (!in_array($app->getName(), ['site']))
		{
			return [];
		}

		return [
			'onAfterDispatch' => 'onAfterDispatch',
		];
	}
}
