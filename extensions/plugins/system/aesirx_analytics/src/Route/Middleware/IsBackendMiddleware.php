<?php

/**
 * @license	GNU General Public License version 3;
 */

namespace Aesirx\System\AesirxAnalytics\Route\Middleware;

use Joomla\CMS\Application\CMSApplication;
use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;
use Pecee\SimpleRouter\Exceptions\HttpException;

class IsBackendMiddleware implements IMiddleware
{
	/**
	 * @var CMSApplication
	 */
	private $app;

	public function __construct(CMSApplication $app)
	{
		$this->app = $app;
	}

	/**
	 * @param Request $request
	 *
	 * @throws HttpException
	 */
	public function handle(Request $request): void
	{
		if (!$this->app->isClient('administrator')
			|| !$this->app->getIdentity()->authorise('core.manage', 'com_aesirx_analytics'))
		{
			throw new HttpException('Permission denied!', 403);
		}
	}
}
