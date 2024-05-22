<?php

/**
 * @license	GNU General Public License version 3;
 */

namespace Aesirx\Component\AesirxAnalytics\Administrator\View\Display;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	public function display($tpl = null)
	{
		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		$user    = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance();

		ToolbarHelper::title(Text::_('COM_AESIRX_ANALYTICS_DASHBOARD'), 'bookmark banners');

		if ($user->authorise('core.admin', 'com_aesirx_analytics') || $user->authorise('core.options', 'com_aesirx_analytics')) {
			$toolbar->preferences('com_aesirx_analytics');
		}
	}

}