<?php

/**
 * @package         Joomla.Administrator
 * @subpackage      com_workflow
 *
 * @copyright   (C) 2018 Open Source Matters, Inc. <https://www.joomla.org>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Aesirx\Component\AesirxAnalytics\Administrator\Controller;

use AesirxAnalyticsLib\Cli\AesirxAnalyticsCli;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Throwable;

\defined('_JEXEC') or die;


class DisplayController extends BaseController implements ContainerAwareInterface
{
	use ContainerAwareTrait;

	protected $default_view = 'display';

	public function download_cli()
	{
		$container = Factory::getContainer();
		/** @var AesirxAnalyticsCli $cli */
		$cli = $container->get(AesirxAnalyticsCli::class);

		try
		{
			$cli->downloadAnalyticsCli();
			$this->setMessage(Text::_('COM_AESIRX_ANALYTICS_DOWNLOADING_SUCCESSFUL'));
		}
		catch (Throwable $e)
		{
			$this->setMessage(Text::sprintf('COM_AESIRX_ANALYTICS_DOWNLOADING_FAILED', $e->getMessage()), 'warning');
		}

		$query = Uri::getInstance()->getQuery(true);

		$this->setRedirect(
			Route::_('index.php?option=com_config&view=component&component=com_aesirx_analytics&path=&return=' . $query['return'] ?? '', false)
		);
	}
}
