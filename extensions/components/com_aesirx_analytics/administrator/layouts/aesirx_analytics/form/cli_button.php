<?php

/**
 * @package     Joomla.Site
 * @subpackage  Layout
 *
 * @copyright   (C) 2016 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use AesirxAnalyticsLib\Cli\AesirxAnalyticsCli;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

extract($displayData);

$query = Uri::getInstance()->getQuery(true);

$url = Route::_('index.php?option=com_aesirx_analytics&task=display.download_cli&return=' . $query['return'] ?? '');

$container = Factory::getContainer();
 /** @var AesirxAnalyticsCli $cli */
$cli = $container->get(AesirxAnalyticsCli::class);

if ($cli->analyticsCliExists())
{
	try
	{
		$cli->processAnalytics(['--version']);
		?><b class="text-success"><?php echo Text::_('COM_AESIRX_ANALYTICS_CLI_PASSED') ?></b><?php
	}
	catch (Throwable $e)
	{
		?><b class="text-danger"><?php echo Text::sprintf('COM_AESIRX_ANALYTICS_CANT_USE_INTERNAL_SERVER', $e->getMessage()) ?></b>
		<?php
	}
}
else
{
	try
	{
		$cli->getSupportedArch();
		?><a href="<?php echo $url ?>" class="btn btn-primary">
		<?php echo Text::_('COM_AESIRX_ANALYTICS_CLICK_DOWNLOAD') ?>
		</a><?php
	}
	catch (Throwable $e)
	{
		?><b class="text-danger"><?php echo Text::sprintf('COM_AESIRX_ANALYTICS_CANT_USE_INTERNAL_SERVER', $e->getMessage()) ?></b>
<?php
	}
}
