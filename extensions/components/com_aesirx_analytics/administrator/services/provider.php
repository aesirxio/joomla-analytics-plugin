<?php

defined('_JEXEC') or die;

use Aesirx\Component\AesirxAnalytics\Administrator\Dispatcher\Dispatcher;
use Aesirx\Component\AesirxAnalytics\Administrator\Extension\AesirxAnalyticsComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->registerServiceProvider(new MVCFactory('\\Aesirx\\Component\\AesirxAnalytics'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Aesirx\\Component\\AesirxAnalytics'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new AesirxAnalyticsComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}
};
