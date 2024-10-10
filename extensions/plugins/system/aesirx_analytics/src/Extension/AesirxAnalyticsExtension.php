<?php
/**
 * @package     Aesirx\System\AesirxAnalytics\Extension
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @since       __DEPLOY_VERSION__
 */

namespace Aesirx\System\AesirxAnalytics\Extension;

use AesirxAnalyticsLib\Cli\AesirxAnalyticsCli;
use AesirxAnalyticsLib\Exception\ExceptionWithErrorType;
use AesirxAnalyticsLib\Exception\ExceptionWithResponseCode;
use Aesirx\System\AesirxAnalytics\Route\Middleware\IsBackendMiddleware;
use AesirxAnalyticsLib\RouterFactory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Document\Renderer\Html\ScriptsRenderer;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Pecee\Http\Url;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Throwable;

/**
 * @method CMSApplication getApplication()
 *
 * @package     AesirxAnalytics\Extension
 *
 * @since       __DEPLOY_VERSION__
 */
class AesirxAnalyticsExtension extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	protected const TASKS_MAP = [
		'analyticsTask_r1.sleep' => [
			'langConstPrefix' => 'PLG_SYSTEM_AESIRX_ANALYTICS_GEO_CRON',
			'method'          => 'geo',
			'form'            => 'analyticsTaskForm',
		],
	];

	/**
	 * @var AesirxAnalyticsCli
	 */
	private $cli;

	public function __construct(AesirxAnalyticsCli $cli, &$subject, $config = [])
	{
		$this->autoloadLanguage = true;
		$this->cli              = $cli;
		parent::__construct($subject, $config);
	}

	private function geo(ExecuteTaskEvent $event): int
	{
		$this->logTask('Started geo cron');

		try
		{
			if ($this->analyticsConfigIsOk('internal'))
			{
				$this->cli->processAnalytics(['job', 'geo']);
			}
		}
		catch (Throwable $e)
		{
			$this->logTask('Geo cron error: ' . $e->getMessage(), 'error');

			return Status::NO_EXIT;
		}

		$this->logTask('Geo cron finished');

		return Status::OK;
	}

	public function onAfterRender()
	{
		$app = $this->getApplication();

		if ($app->getName() == 'administrator')
		{
			if ($app->input->getString('option') == 'com_aesirx_analytics'
				&& $this->analyticsConfigIsOk())
			{
				$call = function (WebAssetManager $wa): void {
					$manifest = json_decode(
						file_get_contents(JPATH_ROOT . '/media/plg_system_aesirx_analytics/assets-manifest.json', true)
					);

					foreach ($manifest->entrypoints->bi->assets->js ?? [] as $idx => $js)
					{
						$wa->registerAndUseScript('plg_system_aesirx_analytics.bi.' . $idx, 'media/plg_system_aesirx_analytics/' . $js, [], ['defer' => true]);
					}

					$uri      = Uri::getInstance();
					$params   = ComponentHelper::getParams('com_aesirx_analytics');
					$streams  = [
						[
							'name'   => $this->getApplication()->get('sitename'),
							'domain' => $uri->toString(['host']),
						],
					];
					$endpoint = $params->get('1st_party_server', 'internal') == 'internal'
						? $uri->toString(['scheme', 'user', 'pass', 'host', 'port']) . Uri::base(true) . '?path='
						: $params->get('domain');

					$clientId = $params->get('client_id');
					$clientSecret = $params->get('client_secret');

					$wa->addInlineScript(
						'window.env = {};
				window.env.REACT_APP_CLIENT_ID = "' . $clientId . '";
				window.env.REACT_APP_CLIENT_SECRET = "' . $clientSecret . '";
				window.env.REACT_APP_BI_ENDPOINT_URL = "' . $endpoint . '";
				window.env.REACT_APP_DATA_STREAM = JSON.stringify(' . json_encode($streams) . ');
				window.env.PUBLIC_URL="' . Uri::root() . 'media/plg_system_aesirx_analytics/";
				window.env.STORAGE="' . $params->get('1st_party_server') . '";' . ($params->get('1st_party_server', 'internal') === 'external' ? 'window.env.REACT_APP_HEADER_JWT="true";' : ''),
						['position' => 'before'], [], ['plg_system_aesirx_analytics.bi.0']
					);
				};
			}
			elseif ($app->input->getString('option') == 'com_config'
				&& $app->input->getString('component') == 'com_aesirx_analytics')
			{
				$call = function (WebAssetManager $wa): void {
					$manifest = json_decode(
						file_get_contents(JPATH_ROOT . '/media/plg_system_aesirx_analytics/assets-manifest.json', true)
					);

					foreach ($manifest->entrypoints->plugin->assets->js ?? [] as $idx => $js)
					{
						$wa->registerAndUseScript('plg_system_aesirx_analytics.config.' . $idx, 'media/plg_system_aesirx_analytics/' . $js, [], ['defer' => true]);
					}
				};
			}
			else
			{
				return;
			}
		}
		elseif ($app->getName() == 'site')
		{
			$call = function (WebAssetManager $wa): void {
				if (!$wa->assetExists('script', 'plg_system_aesirx_analytics.analytics'))
				{
					$wa->registerScript('plg_system_aesirx_analytics.analytics', 'media/plg_system_aesirx_analytics/assets/js/analytics.js', [], ['defer' => true]);
				}

				$wa->useScript('plg_system_aesirx_analytics.analytics');

				$params = ComponentHelper::getParams('com_aesirx_analytics');
				$uri    = Uri::getInstance();

				$domain = $params->get('1st_party_server', 'internal') == 'internal'
					? $uri->toString(['scheme', 'user', 'pass', 'host', 'port']) . Uri::base(true)
					: $params->get('domain', '');

				$consent = $params->get('consent') == '1' ? 'false' : 'true';

				$clientId = $params->get('client_id', '');
				$secret   = $params->get('client_secret', '');

				$wa->addInlineScript(
					'window.aesirx1stparty="' . rtrim($domain, '/') . '";window.disableAnalyticsConsent="' . $consent . '";window.aesirxClientID="' . $clientId . '";window.aesirxClientSecret="' . $secret . '";',
					['position' => 'before'], [], ['plg_system_aesirx_analytics.analytics']
				);
			};
		}
		else
		{
			return;
		}

		$wa = new WebAssetManager(new WebAssetRegistry);

		$call($wa);

		$app->setBody(
			str_replace(
				'</body>',
				(new ScriptsRenderer(new HtmlDocument(['webAssetManager' => $wa])))
					->render('') . '</body>',
				$app->getBody()
			)
		);
	}

	public function onAfterInitialise()
	{
		if (!in_array($this->getApplication()->getName(), ['site', 'administrator']))
		{
			return;
		}

		if (ComponentHelper::getParams('com_aesirx_analytics')->get('1st_party_server', 'internal') != 'internal')
		{
			return;
		}

		$callCommand = function (array $command): string {
			try
			{
				$data = $this->cli->processAnalytics($command);
			}
			catch (Throwable $e)
			{
				$code = 500;

				if ($e instanceof ExceptionWithErrorType)
				{
					switch ($e->getErrorType())
					{
						case "NotFoundError":
							$code = 404;
							break;
						case "ValidationError":
							$code = 400;
							break;
						case "Rejected":
							$code = 406;
							break;
					}
				}

				throw new ExceptionWithResponseCode($e->getMessage(), $code, $e->getCode(), $e);
			}

			if (!headers_sent())
			{
				header('Content-Type: application/json; charset=utf-8');
			}

			return $data;
		};

		try
		{
			$base = Uri::base(true) == '' ? null : Uri::base(true);
			$needle = '/administrator';
			$newUri = clone Uri::getInstance();

			if (!is_null($base) && substr_compare($base, $needle, -strlen($needle)) === 0)
			{
				$query = $newUri->getQuery(true);
				$path  = $query['path'] ?? null;

				if ($path)
				{
					unset($query['path']);

					// Check if the path contains a '?'
					if (strpos($path, '?') !== false) {
						// Split the path into the actual path and the query part
						list($cleanPath, $queryString) = explode('?', $path, 2);

						// Convert the query string into an associative array
						parse_str($queryString, $queryParams);
				
						// Check if 'filter' already exists in $query and $queryParams
						if (isset($query['filter']) && isset($queryParams['filter'])) {
							// Merge 'filter' arrays recursively to avoid overwriting
							$query['filter'] = array_merge_recursive($query['filter'], $queryParams['filter']);
						} else {
							// If no 'filter' in $query or $queryParams, just merge normally
							$query = array_merge($query, $queryParams);
						}
				
						// Update the path with the clean part (without the query string)
						$newUri->setPath(rtrim($newUri->getPath(), '/') . '/' . $cleanPath);
					} else {
						// No query string, just append the path as is
						$newUri->setPath(rtrim($newUri->getPath(), '/') . '/' . $path);
					}
					$newUri->setQuery($query);
				}
			}

			echo (new RouterFactory(
				$callCommand,
				new IsBackendMiddleware($this->getApplication()),
				new Url($newUri->toString()),
				$base
			))
				->getSimpleRouter()
				->start();
		}
		catch (Throwable $e)
		{
			if ($e instanceof NotFoundHttpException)
			{
				return;
			}

			if ($e instanceof ExceptionWithResponseCode)
			{
				$code = $e->getResponseCode();
			}
			else
			{
				$code = 500;
			}

			if (!headers_sent())
			{
				header('Content-Type: application/json; charset=utf-8');
			}
			http_response_code($code);
			echo json_encode([
				'error' => $e->getMessage(),
				'trace' => $e->getTrace(),
			]);
		}

		die();
	}

	protected function analyticsConfigIsOk(string $isStorage = null): bool
	{
		$params  = ComponentHelper::getParams('com_aesirx_analytics');
		$storage = $params->get('1st_party_server', 'internal');
		$res     = (!empty($storage)
			&& (
				($storage == 'internal')
				|| ($storage == 'external' && !empty($params->get('domain')))
			));

		if ($res
			&& !is_null($isStorage))
		{
			$res = $storage == $isStorage;
		}

		return $res;
	}

	public function onAfterRoute()
	{
		$app = $this->getApplication();

		if ($app->getName() != 'administrator'
			|| $app->input->getString('option') != 'com_aesirx_analytics'
			|| $this->analyticsConfigIsOk())
		{
			return;
		}

		$app->enqueueMessage(Text::_('PLG_SYSTEM_AESIRX_ANALYTICS_REDIRECT_BECAUSE_CONFIG_IS_NOT_OK'), 'warning');
		$app->redirect('index.php?option=com_config&view=component&component=com_aesirx_analytics&path=');
	}

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterInitialise'    => 'onAfterInitialise',
			'onAfterRender'        => 'onAfterRender',
			'onAfterRoute'         => 'onAfterRoute',
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}
}
