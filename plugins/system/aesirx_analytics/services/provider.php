<?php
/**
 * @package     AesirX.Plugin
 * @subpackage  System.AesirXAnalytics
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') || die;

use AesirxAnalytics\Extension\AesirxAnalyticsExtension;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param Container $container The DI container.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config = (array) PluginHelper::getPlugin('system', 'aesirx_analytics');
				$subject = $container->get(DispatcherInterface::class);

				$plugin = new AesirxAnalyticsExtension($subject, $config);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
