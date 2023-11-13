<?php

namespace Aesirx\Component\AesirxAnalytics\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\FormField;

class BottomBannerField extends FormField
{
	protected $type = 'BottomBanner';

	protected $layout = 'aesirx_analytics.form.bottom_banner';

	protected $hiddenLabel = true;

	protected function getLayoutPaths(): array
	{
		return array_merge(
			[JPATH_ADMINISTRATOR . '/components/com_aesirx_analytics/layouts'],
			parent::getLayoutPaths()
		);
	}
}
