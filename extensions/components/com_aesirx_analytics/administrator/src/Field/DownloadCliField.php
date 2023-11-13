<?php

namespace Aesirx\Component\AesirxAnalytics\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\FormField;

class DownloadCliField extends FormField
{
	protected $type = 'DownloadCli';

	protected $layout = 'aesirx_analytics.form.cli_button';

	protected function getLayoutPaths(): array
	{
		return array_merge(
			[JPATH_ADMINISTRATOR . '/components/com_aesirx_analytics/layouts'],
			parent::getLayoutPaths()
		);
	}
}
