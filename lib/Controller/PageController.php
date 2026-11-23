<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\AppInfo\Application;
use OCA\Groceries\Http\ShellResponse;
use OCA\Groceries\Service\CatalogService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoSameSiteCookieRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\Defaults;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Serves the installable web app: HTML shell, web app manifest and service worker.
 * All three are public because the app authenticates API calls with a per-device app password
 * (Login Flow v2) instead of the browser session, which expires and is not shared with
 * home-screen apps on iOS.
 */
class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IURLGenerator $urlGenerator,
		private IAppManager $appManager,
		private IFactory $l10nFactory,
		private Defaults $defaults,
		private CatalogService $catalog,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function index(): Response {
		$version = $this->appManager->getAppVersion(Application::APP_ID);
		$webroot = $this->urlGenerator->getWebroot();
		$lang = str_starts_with($this->l10nFactory->findLanguage(Application::APP_ID), 'de') ? 'de' : 'en';

		// The shell is the same for everybody and holds no session data (no user id, no CSRF token):
		// the service worker caches it for offline use. The app asks for the browser session's user
		// and a fresh CSRF token only at the moment it needs them (sessionUserUrl, csrfTokenUrl).
		$config = [
			'version' => $version,
			'catalogVersion' => $this->catalog->getVersion(),
			'language' => $lang,
			'serverName' => $this->defaults->getName(),
			'serverUrl' => $this->urlGenerator->getAbsoluteURL('/'),
			'appUrl' => $this->urlGenerator->linkToRoute('groceries.page.index'),
			'ocsUrl' => $webroot . '/ocs/v2.php/apps/groceries/api/v1',
			'loginFlowUrl' => $webroot . '/index.php/login/v2',
			'appPasswordUrl' => $webroot . '/ocs/v2.php/core/getapppassword',
			'serviceWorkerUrl' => $this->urlGenerator->linkToRoute('groceries.page.serviceWorker'),
			'imgUrl' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/'),
			'sessionUserUrl' => $webroot . '/ocs/v2.php/cloud/user',
			'csrfTokenUrl' => $this->urlGenerator->linkToRoute('core.CSRFToken.index'),
			// ends the browser session too, otherwise the app would log itself in again from it
			'logoutUrl' => $this->urlGenerator->linkToRoute('core.login.logout'),
			'themeColor' => $this->defaults->getColorPrimary(),
		];

		$e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$asset = fn (string $path): string => $e($this->assetUrl($path));
		$nonce = $this->getNonce();
		$nonceAttr = $nonce !== '' ? ' nonce="' . $e($nonce) . '"' : '';

		$html = '<!DOCTYPE html>
<html lang="' . $e($lang) . '">
<head>
<meta charset="utf-8">
<title>Groceries</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#13283f">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Groceries">
<meta name="format-detection" content="telephone=no">
<meta name="robots" content="noindex, nofollow">
<link rel="manifest" href="' . $e($this->urlGenerator->linkToRoute('groceries.page.manifest')) . '" crossorigin="use-credentials">
<link rel="icon" href="' . $asset('img/app-color.svg') . '" type="image/svg+xml">
<link rel="apple-touch-icon" href="' . $asset('img/apple-touch-icon.png') . '">
<link rel="stylesheet" href="' . $asset('css/groceries-main.css') . '">
<script type="module" src="' . $asset('js/groceries-main.js') . '"' . $nonceAttr . '></script>
</head>
<body>
<div id="groceries-app" data-config="' . $e((string)json_encode($config)) . '"></div>
<noscript>Groceries needs JavaScript.</noscript>
</body>
</html>';

		$response = new ShellResponse($html);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedWorkerSrcDomain("'self'");
		$csp->addAllowedConnectDomain("'self'");
		$csp->addAllowedImageDomain('data:');
		$csp->addAllowedImageDomain('blob:');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	// Browsers fetch the manifest without cookies. Without this, Nextcloud's SameSite cookie check
	// answers with a redirect loop and the browser reports the app as "not installable".
	#[NoSameSiteCookieRequired]
	public function manifest(): JSONResponse {
		$scope = $this->urlGenerator->linkToRoute('groceries.page.index');
		$img = fn (string $f) => $this->urlGenerator->linkTo(Application::APP_ID, 'img/' . $f);
		$lang = str_starts_with($this->l10nFactory->findLanguage(Application::APP_ID), 'de') ? 'de' : 'en';
		$response = new JSONResponse([
			'id' => $scope,
			'name' => 'Groceries',
			'short_name' => 'Groceries',
			'description' => $lang === 'de' ? 'Gemeinsame Einkaufslisten' : 'Shared shopping lists',
			'lang' => $lang,
			'start_url' => $scope,
			'scope' => $scope,
			'display' => 'standalone',
			'orientation' => 'portrait',
			'background_color' => '#13283f',
			'theme_color' => '#13283f',
			'categories' => ['food', 'lifestyle', 'productivity'],
			'icons' => [
				['src' => $img('app-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
				['src' => $img('app-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
				['src' => $img('app-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
				['src' => $img('app-color.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
			],
		]);
		$response->addHeader('Content-Type', 'application/manifest+json; charset=utf-8');
		$response->cacheFor(3600);
		return $response;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoSameSiteCookieRequired]
	public function serviceWorker(): Response {
		$version = $this->appManager->getAppVersion(Application::APP_ID);
		$appPath = $this->appManager->getAppPath(Application::APP_ID);
		$source = is_file($appPath . '/serviceworker/sw.js') ? (string)file_get_contents($appPath . '/serviceworker/sw.js') : '';
		$bundle = $appPath . '/js/groceries-main.js';
		$bundleHash = is_file($bundle) ? substr((string)md5_file($bundle), 0, 8) : 'dev';
		$asset = fn (string $path): string => $this->assetUrl($path);
		$js = strtr($source, [
			'__CACHE_VERSION__' => json_encode($version . '-' . $bundleHash),
			'__SHELL_URL__' => json_encode($this->urlGenerator->linkToRoute('groceries.page.index')),
			'__PRECACHE__' => json_encode([
				$asset('js/groceries-main.js'),
				$asset('css/groceries-main.css'),
				$asset('img/app-color.svg'),
				$asset('img/apple-touch-icon.png'),
				$this->urlGenerator->linkToRoute('groceries.page.manifest'),
			]),
			'__APP_ASSET_PREFIX__' => json_encode($this->urlGenerator->linkTo(Application::APP_ID, '')),
		]);
		return new DataDisplayResponse($js, 200, [
			'Content-Type' => 'application/javascript; charset=utf-8',
			'Service-Worker-Allowed' => $this->urlGenerator->linkToRoute('groceries.page.index'),
			'Cache-Control' => 'no-cache',
		]);
	}

	/**
	 * URL of an app file with a content fingerprint. Nextcloud serves app assets with
	 * "Cache-Control: max-age=15778463, immutable", so the URL must change whenever the file
	 * changes, otherwise phones keep running an old build for months.
	 */
	private function assetUrl(string $path): string {
		$file = $this->appManager->getAppPath(Application::APP_ID) . '/' . $path;
		$hash = is_file($file) ? substr((string)md5_file($file), 0, 10) : $this->appManager->getAppVersion(Application::APP_ID);
		return $this->urlGenerator->linkTo(Application::APP_ID, $path) . '?v=' . $hash;
	}

	private function getNonce(): string {
		try {
			// The CSP nonce manager has no public interface; it is what core templates use as well.
			/** @psalm-suppress UndefinedClass */
			$manager = \OCP\Server::get(\OC\Security\CSP\ContentSecurityPolicyNonceManager::class);
			return $manager->getNonce();
		} catch (\Throwable $e) {
			$this->logger->debug('Could not get CSP nonce', ['exception' => $e]);
			return '';
		}
	}
}
