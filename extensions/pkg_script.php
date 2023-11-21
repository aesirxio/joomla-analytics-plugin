<?php

/**
 * @license	GNU General Public License version 3;
 */

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;

class pkg_aesirx_analyticsInstallerScript
{
	public function postflight(string $method)
	{
		if ($method == 'uninstall')
		{
			return true;
		}

		/** @var DatabaseDriver $db */
		$db    = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->update('#__extensions')
			->set('enabled = 1')
			->where('element = ' . $db->q('aesirx_analytics'))
			->where('type = ' . $db->q('plugin'))
			->where('folder = ' . $db->q('system'));

		$db->setQuery($query)
			->execute();

		return true;
	}
}
