<?php
/**
 * @package     AesirX.Plugin
 * @subpackage  System.AesirXAnalytics
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace aesirx_analytics\services;
defined('_JEXEC') || die;

use Aesirx\System\AesirxAnalytics\Cli\AesirxAnalyticsCli;
use Aesirx\System\AesirxAnalytics\Cli\Env;
use Aesirx\System\AesirxAnalytics\Extension\AesirxAnalyticsExtension;
use Joomla\CMS\Component\ComponentHelper;
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
		require __DIR__ . '/../vendor/autoload.php';

		// Register globally
		Factory::getContainer()->set(AesirxAnalyticsCli::class, function (Container $container) {
			/** @var \Joomla\Registry\Registry $globalConfig */
			$globalConfig = $container->get('config');
			$explodedHost = explode(':', $globalConfig->get('host'));
			return new AesirxAnalyticsCli(
				new Env(
					ComponentHelper::getParams('com_aesirx_analytics')
						->get('license', ''),
					$globalConfig->get('user'),
					$globalConfig->get('password'),
					$globalConfig->get('db'),
					$globalConfig->get('dbprefix'),
					$explodedHost[0],
					$explodedHost[1] ?? null
				),
				JPATH_ROOT . '/media/plg_system_aesirx_analytics/analytics-cli'
			);
		}, true, true);

		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config = (array) PluginHelper::getPlugin('system', 'aesirx_analytics');
				$subject = $container->get(DispatcherInterface::class);
				$plugin = new AesirxAnalyticsExtension(
					$container->get(AesirxAnalyticsCli::class),
					$subject,
					$config
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
