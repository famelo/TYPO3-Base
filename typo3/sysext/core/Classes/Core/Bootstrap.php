<?php
namespace TYPO3\CMS\Core\Core;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * This class encapsulates bootstrap related methods.
 * It is required directly as the very first thing in entry scripts and
 * used to define all base things like constants and pathes and so on.
 *
 * Most methods in this class have dependencies to each other. They can
 * not be called in arbitrary order. The methods are ordered top down, so
 * a method at the beginning has lower dependencies than a method further
 * down. Do not fiddle with the load order in own scripts except you know
 * exactly what you are doing!
 */
class Bootstrap {

	/**
	 * @var \TYPO3\CMS\Core\Core\Bootstrap
	 */
	static protected $instance = NULL;

	/**
	 * Unique Request ID
	 *
	 * @var string
	 */
	protected $requestId;

	/**
	 * The application context
	 *
	 * @var \TYPO3\CMS\Core\Core\ApplicationContext
	 */
	protected $applicationContext;

	/**
	 * @var array List of early instances
	 */
	protected $earlyInstances = array();

	/**
	 * @var string Path to install tool
	 */
	protected $installToolPath;

	/**
	 * A list of all registered request handlers, see the Application class / entry points for the registration
	 * @var \TYPO3\CMS\Core\Http\RequestHandlerInterface[]|\TYPO3\CMS\Core\Console\RequestHandlerInterface[]
	 */
	protected $availableRequestHandlers = array();

	/**
	 * The Response object when using Request/Response logic
	 * @var \Psr\Http\Message\ResponseInterface
	 * @see shutdown()
	 */
	protected $response;

	/**
	 * @var bool
	 */
	static protected $usesComposerClassLoading = FALSE;

	/**
	 * Disable direct creation of this object.
	 * Set unique requestId and the application context
	 *
	 * @var string Application context
	 */
	protected function __construct($applicationContext) {
		$this->requestId = substr(md5(uniqid('', TRUE)), 0, 13);
		$this->applicationContext = new ApplicationContext($applicationContext);
	}

	/**
	 * @return bool
	 */
	static public function usesComposerClassLoading() {
		return self::$usesComposerClassLoading;
	}

	/**
	 * Disable direct cloning of this object.
	 */
	protected function __clone() {

	}

	/**
	 * Return 'this' as singleton
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	static public function getInstance() {
		if (is_null(static::$instance)) {
			$applicationContext = getenv('TYPO3_CONTEXT') ?: (getenv('REDIRECT_TYPO3_CONTEXT') ?: 'Production');
			self::$instance = new static($applicationContext);
		}
		return static::$instance;
	}

	/**
	 * Gets the request's unique ID
	 *
	 * @return string Unique request ID
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function getRequestId() {
		return $this->requestId;
	}

	/**
	 * Returns the application context this bootstrap was started in.
	 *
	 * @return \TYPO3\CMS\Core\Core\ApplicationContext The application context encapsulated in an object
	 * @internal This is not a public API method, do not use in own extensions.
	 * Use \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext() instead
	 */
	public function getApplicationContext() {
		return $this->applicationContext;
	}

	/**
	 * Prevent any unwanted output that may corrupt AJAX/compression.
	 * This does not interfere with "die()" or "echo"+"exit()" messages!
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function startOutputBuffering() {
		ob_start();
		return $this;
	}

	/**
	 * Main entry point called at every request usually from Global scope. Checks if everything is correct,
	 * and loads the Configuration.
	 *
	 * Make sure that the baseSetup() is called before and the class loader is present
	 *
	 * @return Bootstrap
	 */
	public function configure() {
		$this->startOutputBuffering()
			->loadConfigurationAndInitialize()
			->loadTypo3LoadedExtAndExtLocalconf(TRUE)
			->setFinalCachingFrameworkCacheConfiguration()
			->defineLoggingAndExceptionConstants()
			->unsetReservedGlobalVariables()
			->initializeTypo3DbGlobal();

		return $this;
	}

	/**
	 * Run the base setup that checks server environment, determines pathes,
	 * populates base files and sets common configuration.
	 *
	 * Script execution will be aborted if something fails here.
	 *
	 * @param string $relativePathPart Relative path of entry script back to document root
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function baseSetup($relativePathPart = '') {
		SystemEnvironmentBuilder::run($relativePathPart);
		if (!self::$usesComposerClassLoading) {
			ClassLoadingInformation::registerClassLoadingInformation();
		}
		GeneralUtility::presetApplicationContext($this->applicationContext);
		return $this;
	}

	/**
	 * Sets the class loader to the bootstrap
	 *
	 * @param \Composer\Autoload\ClassLoader|\Helhum\ClassAliasLoader\Composer\ClassAliasLoader $classLoader an instance of the class loader
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeClassLoader($classLoader) {
		$this->setEarlyInstance(\Composer\Autoload\ClassLoader::class, $classLoader);
		if (defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE) {
			self::$usesComposerClassLoading = TRUE;
		}
		return $this;
	}

	/**
	 * checks if LocalConfiguration.php or PackageStates.php is missing,
	 * used to see if a redirect to the install tool is needed
	 *
	 * @return bool TRUE when the essential configuration is available, otherwise FALSE
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function checkIfEssentialConfigurationExists() {
		$configurationManager = new \TYPO3\CMS\Core\Configuration\ConfigurationManager;
		$this->setEarlyInstance(\TYPO3\CMS\Core\Configuration\ConfigurationManager::class, $configurationManager);
		return file_exists($configurationManager->getLocalConfigurationFileLocation()) && file_exists(PATH_typo3conf . 'PackageStates.php');
	}

	/**
	 * Redirect to install tool if LocalConfiguration.php is missing.
	 *
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function redirectToInstallTool($relativePathPart = '') {
		$backPathToSiteRoot = str_repeat('../', count(explode('/', $relativePathPart)) - 1);
		header('Location: ' . $backPathToSiteRoot . 'typo3/sysext/install/Start/Install.php');
		die;
	}

	/**
	 * Adds available request handlers usually done via an application from the outside.
	 *
	 * @param string $requestHandler class which implements the request handler interface
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function registerRequestHandlerImplementation($requestHandler) {
		$this->availableRequestHandlers[] = $requestHandler;
		return $this;
	}

	/**
	 * Fetches the request handler that suits the best based on the priority and the interface
	 * Be sure to always have the constants that are defined in $this->defineTypo3RequestTypes() are set,
	 * so most RequestHandlers can check if they can handle the request.
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface|\Symfony\Component\Console\Input\InputInterface $request
	 * @return \TYPO3\CMS\Core\Http\RequestHandlerInterface|\TYPO3\CMS\Core\Console\RequestHandlerInterface
	 * @throws \TYPO3\CMS\Core\Exception
	 * @internal This is not a public API method, do not use in own extensions
	 */
	protected function resolveRequestHandler($request) {
		$suitableRequestHandlers = array();
		foreach ($this->availableRequestHandlers as $requestHandlerClassName) {
			/** @var \TYPO3\CMS\Core\Http\RequestHandlerInterface|\TYPO3\CMS\Core\Console\RequestHandlerInterface $requestHandler */
			$requestHandler = GeneralUtility::makeInstance($requestHandlerClassName, $this);
			if ($requestHandler->canHandleRequest($request)) {
				$priority = $requestHandler->getPriority();
				if (isset($suitableRequestHandlers[$priority])) {
					throw new \TYPO3\CMS\Core\Exception('More than one request handler with the same priority can handle the request, but only one handler may be active at a time!', 1176471352);
				}
				$suitableRequestHandlers[$priority] = $requestHandler;
			}
		}
		if (empty($suitableRequestHandlers)) {
			throw new \TYPO3\CMS\Core\Exception('No suitable request handler found.', 1225418233);
		}
		ksort($suitableRequestHandlers);
		return array_pop($suitableRequestHandlers);
	}

	/**
	 * Builds a Request instance from the current process, and then resolves the request
	 * through the request handlers depending on Frontend, Backend, CLI etc.
	 *
	 * @param \Psr\Http\Message\RequestInterface|\Symfony\Component\Console\Input\InputInterface $request
	 * @return Bootstrap
	 * @throws \TYPO3\CMS\Core\Exception
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function handleRequest($request) {

		// Resolve request handler that were registered based on the Application
		$requestHandler = $this->resolveRequestHandler($request);

		// Execute the command which returns a Response object or NULL
		$this->response = $requestHandler->handleRequest($request);
		return $this;
	}

	/**
	 * Outputs content if there is a proper Response object.
	 *
	 * @return Bootstrap
	 */
	protected function sendResponse() {
		if ($this->response instanceof \Psr\Http\Message\ResponseInterface) {
			if (!headers_sent()) {
				foreach ($this->response->getHeaders() as $name => $values) {
					header($name . ': ' . implode(', ', $values));
				}
				// If the response code was not changed by legacy code (still is 200)
				// then allow the PSR-7 response object to explicitly set it.
				// Otherwise let legacy code take precedence.
				// This code path can be deprecated once we expose the response object to third party code
				if (http_response_code() === 200) {
					header('HTTP/' . $this->response->getProtocolVersion() . ' ' . $this->response->getStatusCode() . ' ' . $this->response->getReasonPhrase());
				}
			}
			echo $this->response->getBody()->__toString();
		}
		return $this;
	}

	/**
	 * Registers the instance of the specified object for an early boot stage.
	 * On finalizing the Object Manager initialization, all those instances will
	 * be transferred to the Object Manager's registry.
	 *
	 * @param string $objectName Object name, as later used by the Object Manager
	 * @param object $instance The instance to register
	 * @return void
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function setEarlyInstance($objectName, $instance) {
		$this->earlyInstances[$objectName] = $instance;
	}

	/**
	 * Returns an instance which was registered earlier through setEarlyInstance()
	 *
	 * @param string $objectName Object name of the registered instance
	 * @return object
	 * @throws \TYPO3\CMS\Core\Exception
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function getEarlyInstance($objectName) {
		if (!isset($this->earlyInstances[$objectName])) {
			throw new \TYPO3\CMS\Core\Exception('Unknown early instance "' . $objectName . '"', 1365167380);
		}
		return $this->earlyInstances[$objectName];
	}

	/**
	 * Returns all registered early instances indexed by object name
	 *
	 * @return array
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function getEarlyInstances() {
		return $this->earlyInstances;
	}

	/**
	 * Includes LocalConfiguration.php and sets several
	 * global settings depending on configuration.
	 *
	 * @param bool $allowCaching Whether to allow caching - affects cache_core (autoloader)
	 * @param string $packageManagerClassName Define an alternative package manager implementation (usually for the installer)
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function loadConfigurationAndInitialize($allowCaching = TRUE, $packageManagerClassName = \TYPO3\CMS\Core\Package\PackageManager::class) {
		$this->populateLocalConfiguration()
			->initializeErrorHandling();
		if (!$allowCaching) {
			$this->disableCoreCache();
		}
		$this->initializeCachingFramework()
			->initializePackageManagement($packageManagerClassName)
			->initializeRuntimeActivatedPackagesFromConfiguration()
			->defineDatabaseConstants()
			->defineUserAgentConstant()
			->registerExtDirectComponents()
			->transferDeprecatedCurlSettings()
			->setCacheHashOptions()
			->setDefaultTimezone()
			->initializeL10nLocales()
			->convertPageNotFoundHandlingToBoolean()
			->setMemoryLimit()
			->defineTypo3RequestTypes();
		if ($allowCaching) {
			$this->ensureClassLoadingInformationExists();
		}
		return $this;
	}

	/**
	 * Initializes the package system and loads the package configuration and settings
	 * provided by the packages.
	 *
	 * @param string $packageManagerClassName Define an alternative package manager implementation (usually for the installer)
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializePackageManagement($packageManagerClassName) {
		/** @var \TYPO3\CMS\Core\Package\PackageManager $packageManager */
		$packageManager = new $packageManagerClassName();
		$this->setEarlyInstance(\TYPO3\CMS\Core\Package\PackageManager::class, $packageManager);
		ExtensionManagementUtility::setPackageManager($packageManager);
		$packageManager->injectCoreCache($this->getEarlyInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cache_core'));
		$dependencyResolver = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Package\DependencyResolver::class);
		$dependencyResolver->injectDependencyOrderingService(GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\DependencyOrderingService::class));
		$packageManager->injectDependencyResolver($dependencyResolver);
		$packageManager->initialize($this);
		GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Package\PackageManager::class, $packageManager);
		return $this;
	}

	/**
	 * Writes class loading information if not yet present
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function ensureClassLoadingInformationExists() {
		if (!self::$usesComposerClassLoading && !ClassLoadingInformation::classLoadingInformationExists()) {
			ClassLoadingInformation::dumpClassLoadingInformation();
			ClassLoadingInformation::registerClassLoadingInformation();
		}
		return $this;
	}

	/**
	 * Activates a package during runtime. This is used in AdditionalConfiguration.php
	 * to enable extensions under conditions.
	 *
	 * @return Bootstrap
	 */
	protected function initializeRuntimeActivatedPackagesFromConfiguration() {
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['runtimeActivatedPackages']) && is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['runtimeActivatedPackages'])) {
			/** @var \TYPO3\CMS\Core\Package\PackageManager $packageManager */
			$packageManager = $this->getEarlyInstance(\TYPO3\CMS\Core\Package\PackageManager::class);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['runtimeActivatedPackages'] as $runtimeAddedPackageKey) {
				$packageManager->activatePackageDuringRuntime($runtimeAddedPackageKey);
			}
		}
		return $this;
	}

	/**
	 * Load ext_localconf of extensions
	 *
	 * @param bool $allowCaching
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function loadTypo3LoadedExtAndExtLocalconf($allowCaching = TRUE) {
		ExtensionManagementUtility::loadExtLocalconf($allowCaching);
		return $this;
	}

	/**
	 * We need an early instance of the configuration manager.
	 * Since makeInstance relies on the object configuration, we create it here with new instead.
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function populateLocalConfiguration() {
		try {
			$configurationManager = $this->getEarlyInstance(\TYPO3\CMS\Core\Configuration\ConfigurationManager::class);
		} catch(\TYPO3\CMS\Core\Exception $exception) {
			$configurationManager = new \TYPO3\CMS\Core\Configuration\ConfigurationManager();
			$this->setEarlyInstance(\TYPO3\CMS\Core\Configuration\ConfigurationManager::class, $configurationManager);
		}
		$configurationManager->exportConfiguration();
		return $this;
	}

	/**
	 * Set cache_core to null backend, effectively disabling eg. the cache for ext_localconf and PackageManager etc.
	 *
	 * @return \TYPO3\CMS\Core\Core\Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function disableCoreCache() {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_core']['backend']
			= \TYPO3\CMS\Core\Cache\Backend\NullBackend::class;
		unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_core']['options']);
		return $this;
	}

	/**
	 * Define database constants
	 *
	 * @return \TYPO3\CMS\Core\Core\Bootstrap
	 */
	protected function defineDatabaseConstants() {
		define('TYPO3_db', $GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
		define('TYPO3_db_username', $GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
		define('TYPO3_db_password', $GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
		define('TYPO3_db_host', $GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
		// Constant TYPO3_extTableDef_script is deprecated since TYPO3 CMS 7 and will be dropped with TYPO3 CMS 8
		define('TYPO3_extTableDef_script',
			isset($GLOBALS['TYPO3_CONF_VARS']['DB']['extTablesDefinitionScript'])
			? $GLOBALS['TYPO3_CONF_VARS']['DB']['extTablesDefinitionScript']
			: 'extTables.php');
		return $this;
	}

	/**
	 * Define user agent constant
	 *
	 * @return \TYPO3\CMS\Core\Core\Bootstrap
	 */
	protected function defineUserAgentConstant() {
		define('TYPO3_user_agent', 'User-Agent: ' . $GLOBALS['TYPO3_CONF_VARS']['HTTP']['userAgent']);
		return $this;
	}

	/**
	 * Register default ExtDirect components
	 *
	 * @return Bootstrap
	 */
	protected function registerExtDirectComponents() {
		if (TYPO3_MODE === 'BE') {
			ExtensionManagementUtility::registerExtDirectComponent(
				'TYPO3.Components.PageTree.DataProvider',
				\TYPO3\CMS\Backend\Tree\Pagetree\ExtdirectTreeDataProvider::class
			);
			ExtensionManagementUtility::registerExtDirectComponent(
				'TYPO3.Components.PageTree.Commands',
				\TYPO3\CMS\Backend\Tree\Pagetree\ExtdirectTreeCommands::class
			);
			ExtensionManagementUtility::registerExtDirectComponent(
				'TYPO3.Components.PageTree.ContextMenuDataProvider',
				\TYPO3\CMS\Backend\ContextMenu\Pagetree\Extdirect\ContextMenuConfiguration::class
			);
			ExtensionManagementUtility::registerExtDirectComponent(
				'TYPO3.ExtDirectStateProvider.ExtDirect',
				\TYPO3\CMS\Backend\InterfaceState\ExtDirect\DataProvider::class
			);
			ExtensionManagementUtility::registerExtDirectComponent(
				'TYPO3.Components.DragAndDrop.CommandController',
				ExtensionManagementUtility::extPath('backend') . 'Classes/View/PageLayout/Extdirect/ExtdirectPageCommands.php:' . \TYPO3\CMS\Backend\View\PageLayout\ExtDirect\ExtdirectPageCommands::class
			);
		}
		return $this;
	}

	/**
	 * Initialize caching framework, and re-initializes it (e.g. in the install tool) by recreating the instances
	 * again despite the Singleton instance
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeCachingFramework() {
		$cacheManager = new \TYPO3\CMS\Core\Cache\CacheManager();
		$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Cache\CacheManager::class, $cacheManager);

		$cacheFactory = new \TYPO3\CMS\Core\Cache\CacheFactory('production', $cacheManager);
		GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Cache\CacheFactory::class, $cacheFactory);

		$this->setEarlyInstance(\TYPO3\CMS\Core\Cache\CacheManager::class, $cacheManager);
		return $this;
	}

	/**
	 * Parse old curl options and set new http ones instead
	 *
	 * @TODO: Move this functionality to the silent updater in the Install Tool
	 * @return Bootstrap
	 */
	protected function transferDeprecatedCurlSettings() {
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer']) && empty($GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy_host'])) {
			$curlProxy = rtrim(preg_replace('#^https?://#', '', $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer']), '/');
			$proxyParts = GeneralUtility::revExplode(':', $curlProxy, 2);
			$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy_host'] = $proxyParts[0];
			$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy_port'] = $proxyParts[1];
		}
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyUserPass']) && empty($GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy_user'])) {
			$userPassParts = explode(':', $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyUserPass'], 2);
			$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy_user'] = $userPassParts[0];
			$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy_password'] = $userPassParts[1];
		}
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlUse']) {
			$GLOBALS['TYPO3_CONF_VARS']['HTTP']['adapter'] = 'curl';
		}
		return $this;
	}

	/**
	 * Set cacheHash options
	 *
	 * @return Bootstrap
	 */
	protected function setCacheHashOptions() {
		$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash'] = array(
			'cachedParametersWhiteList' => GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['cHashOnlyForParameters'], TRUE),
			'excludedParameters' => GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParameters'], TRUE),
			'requireCacheHashPresenceParameters' => GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['cHashRequiredParameters'], TRUE)
		);
		if (trim($GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParametersIfEmpty']) === '*') {
			$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludeAllEmptyParameters'] = TRUE;
		} else {
			$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParametersIfEmpty'] = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParametersIfEmpty'], TRUE);
		}
		return $this;
	}

	/**
	 * Set default timezone
	 *
	 * @return Bootstrap
	 */
	protected function setDefaultTimezone() {
		$timeZone = $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone'];
		if (empty($timeZone)) {
			// Time zone from the server environment (TZ env or OS query)
			$defaultTimeZone = @date_default_timezone_get();
			if ($defaultTimeZone !== '') {
				$timeZone = $defaultTimeZone;
			} else {
				$timeZone = 'UTC';
			}
		}
		// Set default to avoid E_WARNINGs with PHP > 5.3
		date_default_timezone_set($timeZone);
		return $this;
	}

	/**
	 * Initialize the locales handled by TYPO3
	 *
	 * @return Bootstrap
	 */
	protected function initializeL10nLocales() {
		\TYPO3\CMS\Core\Localization\Locales::initialize();
		return $this;
	}

	/**
	 * Convert type of "pageNotFound_handling" setting in case it was written as a
	 * string (e.g. if edited in Install Tool)
	 *
	 * @TODO : Remove, if the Install Tool handles such data types correctly
	 * @return Bootstrap
	 */
	protected function convertPageNotFoundHandlingToBoolean() {
		if (!strcasecmp($GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'], 'TRUE')) {
			$GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = TRUE;
		}
		return $this;
	}

	/**
	 * Configure and set up exception and error handling
	 *
	 * @return Bootstrap
	 * @throws \RuntimeException
	 */
	protected function initializeErrorHandling() {
		$productionExceptionHandlerClassName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'];
		$debugExceptionHandlerClassName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'];

		$errorHandlerClassName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['errorHandler'];
		$errorHandlerErrors = $GLOBALS['TYPO3_CONF_VARS']['SYS']['errorHandlerErrors'];
		$exceptionalErrors = $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'];

		$displayErrorsSetting = (int)$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'];
		switch ($displayErrorsSetting) {
			case 2:
				GeneralUtility::deprecationLog('The option "$TYPO3_CONF_VARS[SYS][displayErrors]" is set to "2" which is deprecated as of TYPO3 CMS 7, and will be removed with TYPO3 CMS 8. Please change the value to "-1"');
				// intentionally fall through
			case -1:
				$ipMatchesDevelopmentSystem = GeneralUtility::cmpIP(GeneralUtility::getIndpEnv('REMOTE_ADDR'), $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask']);
				$exceptionHandlerClassName = $ipMatchesDevelopmentSystem ? $debugExceptionHandlerClassName : $productionExceptionHandlerClassName;
				$displayErrors = $ipMatchesDevelopmentSystem ? 1 : 0;
				$exceptionalErrors = $ipMatchesDevelopmentSystem ? $exceptionalErrors : 0;
				break;
			case 0:
				$exceptionHandlerClassName = $productionExceptionHandlerClassName;
				$displayErrors = 0;
				break;
			case 1:
				$exceptionHandlerClassName = $debugExceptionHandlerClassName;
				$displayErrors = 1;
				break;
			default:
				// Throw exception if an invalid option is set.
				throw new \RuntimeException('The option $TYPO3_CONF_VARS[SYS][displayErrors] is not set to "-1", "0" or "1".');
		}
		@ini_set('display_errors', $displayErrors);

		if (!empty($errorHandlerClassName)) {
			// Register an error handler for the given errorHandlerError
			$errorHandler = GeneralUtility::makeInstance($errorHandlerClassName, $errorHandlerErrors);
			$errorHandler->setExceptionalErrors($exceptionalErrors);
			if (is_callable(array($errorHandler, 'setDebugMode'))) {
				$errorHandler->setDebugMode($displayErrors === 1);
			}
		}
		if (!empty($exceptionHandlerClassName)) {
			// Registering the exception handler is done in the constructor
			GeneralUtility::makeInstance($exceptionHandlerClassName);
		}
		return $this;
	}

	/**
	 * Set PHP memory limit depending on value of
	 * $GLOBALS['TYPO3_CONF_VARS']['SYS']['setMemoryLimit']
	 *
	 * @return Bootstrap
	 */
	protected function setMemoryLimit() {
		if ((int)$GLOBALS['TYPO3_CONF_VARS']['SYS']['setMemoryLimit'] > 16) {
			@ini_set('memory_limit', ((int)$GLOBALS['TYPO3_CONF_VARS']['SYS']['setMemoryLimit'] . 'm'));
		}
		return $this;
	}

	/**
	 * Define TYPO3_REQUESTTYPE* constants
	 * so devs exactly know what type of request it is
	 *
	 * @return Bootstrap
	 */
	protected function defineTypo3RequestTypes() {
		define('TYPO3_REQUESTTYPE_FE', 1);
		define('TYPO3_REQUESTTYPE_BE', 2);
		define('TYPO3_REQUESTTYPE_CLI', 4);
		define('TYPO3_REQUESTTYPE_AJAX', 8);
		define('TYPO3_REQUESTTYPE_INSTALL', 16);
		define('TYPO3_REQUESTTYPE', (TYPO3_MODE == 'FE' ? TYPO3_REQUESTTYPE_FE : 0) | (TYPO3_MODE == 'BE' ? TYPO3_REQUESTTYPE_BE : 0) | (defined('TYPO3_cliMode') && TYPO3_cliMode ? TYPO3_REQUESTTYPE_CLI : 0) | (defined('TYPO3_enterInstallScript') && TYPO3_enterInstallScript ? TYPO3_REQUESTTYPE_INSTALL : 0) | ($GLOBALS['TYPO3_AJAX'] ? TYPO3_REQUESTTYPE_AJAX : 0));
		return $this;
	}

	/**
	 * Extensions may register new caches, so we set the
	 * global cache array to the manager again at this point
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function setFinalCachingFrameworkCacheConfiguration() {
		$this->getEarlyInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		return $this;
	}

	/**
	 * Define logging and exception constants
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function defineLoggingAndExceptionConstants() {
		define('TYPO3_DLOG', $GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_DLOG']);
		define('TYPO3_ERROR_DLOG', $GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']);
		define('TYPO3_EXCEPTION_DLOG', $GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_exceptionDLOG']);
		return $this;
	}

	/**
	 * Unsetting reserved global variables:
	 * Those are set in "ext:core/ext_tables.php" file:
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function unsetReservedGlobalVariables() {
		unset($GLOBALS['PAGES_TYPES']);
		unset($GLOBALS['TCA']);
		unset($GLOBALS['TBE_MODULES']);
		unset($GLOBALS['TBE_STYLES']);
		unset($GLOBALS['BE_USER']);
		// Those set otherwise:
		unset($GLOBALS['TBE_MODULES_EXT']);
		unset($GLOBALS['TCA_DESCR']);
		unset($GLOBALS['LOCAL_LANG']);
		unset($GLOBALS['TYPO3_AJAX']);
		return $this;
	}

	/**
	 * Initialize database connection in $GLOBALS and connect if requested
	 *
	 * @return \TYPO3\CMS\Core\Core\Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeTypo3DbGlobal() {
		/** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
		$databaseConnection = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\DatabaseConnection::class);
		$databaseConnection->setDatabaseName(TYPO3_db);
		$databaseConnection->setDatabaseUsername(TYPO3_db_username);
		$databaseConnection->setDatabasePassword(TYPO3_db_password);

		$databaseHost = TYPO3_db_host;
		if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port'])) {
			$databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['port']);
		} elseif (strpos($databaseHost, ':') > 0) {
			// @TODO: Find a way to handle this case in the install tool and drop this
			list($databaseHost, $databasePort) = explode(':', $databaseHost);
			$databaseConnection->setDatabasePort($databasePort);
		}
		if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['socket'])) {
			$databaseConnection->setDatabaseSocket($GLOBALS['TYPO3_CONF_VARS']['DB']['socket']);
		}
		$databaseConnection->setDatabaseHost($databaseHost);

		$databaseConnection->debugOutput = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sqlDebug'];

		if (
			isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect'])
			&& !$GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect']
		) {
			$databaseConnection->setPersistentDatabaseConnection(TRUE);
		}

		$isDatabaseHostLocalHost = $databaseHost === 'localhost' || $databaseHost === '127.0.0.1' || $databaseHost === '::1';
		if (
			isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['dbClientCompress'])
			&& $GLOBALS['TYPO3_CONF_VARS']['SYS']['dbClientCompress']
			&& !$isDatabaseHostLocalHost
		) {
			$databaseConnection->setConnectionCompression(TRUE);
		}

		if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['setDBinit'])) {
			$commandsAfterConnect = GeneralUtility::trimExplode(
				LF,
				str_replace('\' . LF . \'', LF, $GLOBALS['TYPO3_CONF_VARS']['SYS']['setDBinit']),
				TRUE
			);
			$databaseConnection->setInitializeCommandsAfterConnect($commandsAfterConnect);
		}

		$GLOBALS['TYPO3_DB'] = $databaseConnection;
		// $GLOBALS['TYPO3_DB'] needs to be defined first in order to work for DBAL
		$GLOBALS['TYPO3_DB']->initialize();

		return $this;
	}

	/**
	 * Check adminOnly configuration variable and redirects
	 * to an URL in file typo3conf/LOCK_BACKEND or exit the script
	 *
	 * @throws \RuntimeException
	 * @param bool $forceProceeding if this option is set, the bootstrap will proceed even if the user is logged in (usually only needed for special AJAX cases, see AjaxRequestHandler)
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function checkLockedBackendAndRedirectOrDie($forceProceeding = FALSE) {
		if ($GLOBALS['TYPO3_CONF_VARS']['BE']['adminOnly'] < 0) {
			throw new \RuntimeException('TYPO3 Backend locked: Backend and Install Tool are locked for maintenance. [BE][adminOnly] is set to "' . (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['adminOnly'] . '".', 1294586847);
		}
		if (@is_file(PATH_typo3conf . 'LOCK_BACKEND') && $forceProceeding === FALSE) {
			$fileContent = GeneralUtility::getUrl(PATH_typo3conf . 'LOCK_BACKEND');
			if ($fileContent) {
				header('Location: ' . $fileContent);
			} else {
				throw new \RuntimeException('TYPO3 Backend locked: Browser backend is locked for maintenance. Remove lock by removing the file "typo3conf/LOCK_BACKEND" or use CLI-scripts.', 1294586848);
			}
			die;
		}
		return $this;
	}

	/**
	 * Compare client IP with IPmaskList and exit the script run
	 * if the client is not allowed to access the backend
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 * @throws \RuntimeException
	 */
	public function checkBackendIpOrDie() {
		if (trim($GLOBALS['TYPO3_CONF_VARS']['BE']['IPmaskList'])) {
			if (!GeneralUtility::cmpIP(GeneralUtility::getIndpEnv('REMOTE_ADDR'), $GLOBALS['TYPO3_CONF_VARS']['BE']['IPmaskList'])) {
				throw new \RuntimeException('TYPO3 Backend access denied: The IP address of your client does not match the list of allowed IP addresses.', 1389265900);
			}
		}
		return $this;
	}

	/**
	 * Check lockSSL configuration variable and redirect
	 * to https version of the backend if needed
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 * @throws \RuntimeException
	 */
	public function checkSslBackendAndRedirectIfNeeded() {
		if ((int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL']) {
			if ((int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSLPort']) {
				$sslPortSuffix = ':' . (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSLPort'];
			} else {
				$sslPortSuffix = '';
			}
			if ((int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL'] === 3) {
				$requestStr = substr(GeneralUtility::getIndpEnv('TYPO3_REQUEST_SCRIPT'), strlen(GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir));
				if ($requestStr === 'index.php' && !GeneralUtility::getIndpEnv('TYPO3_SSL')) {
					list(, $url) = explode('://', GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'), 2);
					list($server, $address) = explode('/', $url, 2);
					header('Location: https://' . $server . $sslPortSuffix . '/' . $address);
					die;
				}
			} elseif (!GeneralUtility::getIndpEnv('TYPO3_SSL')) {
				if ((int)$GLOBALS['TYPO3_CONF_VARS']['BE']['lockSSL'] === 2) {
					list(, $url) = explode('://', GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir, 2);
					list($server, $address) = explode('/', $url, 2);
					header('Location: https://' . $server . $sslPortSuffix . '/' . $address);
					die;
				} else {
					throw new \RuntimeException('TYPO3 Backend not accessed via SSL: TYPO3 Backend is configured to only be accessible through SSL. Change the URL in your browser and try again.', 1389265726);
				}
			}
		}
		return $this;
	}

	/**
	 * Load TCA for frontend
	 *
	 * This method is *only* executed in frontend scope. The idea is to execute the
	 * whole TCA and ext_tables (which manipulate TCA) on first frontend access,
	 * and then cache the full TCA on disk to be used for the next run again.
	 *
	 * This way, ext_tables.php ist not executed every time, but $GLOBALS['TCA']
	 * is still always there.
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function loadCachedTca() {
		$cacheIdentifier = 'tca_fe_' . sha1((TYPO3_version . PATH_site . 'tca_fe'));
		/** @var $codeCache \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend */
		$codeCache = $this->getEarlyInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cache_core');
		if ($codeCache->has($cacheIdentifier)) {
			// substr is necessary, because the php frontend wraps php code around the cache value
			$GLOBALS['TCA'] = unserialize(substr($codeCache->get($cacheIdentifier), 6, -2));
		} else {
			$this->loadExtensionTables(TRUE);
			$codeCache->set($cacheIdentifier, serialize($GLOBALS['TCA']));
		}
		return $this;
	}

	/**
	 * Load ext_tables and friends.
	 *
	 * This will mainly set up $TCA and several other global arrays
	 * through API's like extMgm.
	 * Executes ext_tables.php files of loaded extensions or the
	 * according cache file if exists.
	 *
	 * @param bool $allowCaching True, if reading compiled ext_tables file from cache is allowed
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function loadExtensionTables($allowCaching = TRUE) {
		ExtensionManagementUtility::loadBaseTca($allowCaching);
		ExtensionManagementUtility::loadExtTables($allowCaching);
		$this->executeExtTablesAdditionalFile();
		$this->runExtTablesPostProcessingHooks();
		return $this;
	}

	/**
	 * Execute TYPO3_extTableDef_script if defined and exists
	 *
	 * Note: For backwards compatibility some global variables are
	 * explicitly set as global to be used without $GLOBALS[] in
	 * the extension table script. It is discouraged to access variables like
	 * $TBE_MODULES directly, but we can not prohibit
	 * this without heavily breaking backwards compatibility.
	 *
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 * @return void
	 */
	protected function executeExtTablesAdditionalFile() {
		// It is discouraged to use those global variables directly, but we
		// can not prohibit this without breaking backwards compatibility
		global $T3_SERVICES, $T3_VAR, $TYPO3_CONF_VARS;
		global $TBE_MODULES, $TBE_MODULES_EXT, $TCA;
		global $PAGES_TYPES, $TBE_STYLES;
		global $_EXTKEY;
		// Load additional ext tables script if the file exists
		$extTablesFile = PATH_typo3conf . TYPO3_extTableDef_script;
		if (file_exists($extTablesFile) && is_file($extTablesFile)) {
			GeneralUtility::deprecationLog(
				'Using typo3conf/' . TYPO3_extTableDef_script . ' is deprecated and will be removed with TYPO3 CMS 8. Please move your TCA overrides'
				. ' to Configuration/TCA/Overrides of your project specific extension, or slot the signal "tcaIsBeingBuilt" for further processing.'
			);
			include $extTablesFile;
		}
	}

	/**
	 * Check for registered ext tables hooks and run them
	 *
	 * @throws \UnexpectedValueException
	 * @return void
	 */
	protected function runExtTablesPostProcessingHooks() {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['extTablesInclusion-PostProcessing'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['extTablesInclusion-PostProcessing'] as $classReference) {
				/** @var $hookObject \TYPO3\CMS\Core\Database\TableConfigurationPostProcessingHookInterface */
				$hookObject = GeneralUtility::getUserObj($classReference);
				if (!$hookObject instanceof \TYPO3\CMS\Core\Database\TableConfigurationPostProcessingHookInterface) {
					throw new \UnexpectedValueException(
						'$hookObject "' . $classReference . '" must implement interface TYPO3\\CMS\\Core\\Database\\TableConfigurationPostProcessingHookInterface',
						1320585902
					);
				}
				$hookObject->processData();
			}
		}
	}

	/**
	 * Initialize sprite manager
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeSpriteManager() {
		\TYPO3\CMS\Backend\Sprite\SpriteManager::initialize();
		return $this;
	}

	/**
	 * Initialize backend user object in globals
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeBackendUser() {
		/** @var $backendUser \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
		$backendUser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
		$backendUser->warningEmail = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
		$backendUser->lockIP = $GLOBALS['TYPO3_CONF_VARS']['BE']['lockIP'];
		$backendUser->auth_timeout_field = (int)$GLOBALS['TYPO3_CONF_VARS']['BE']['sessionTimeout'];
		if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI) {
			$backendUser->dontSetCookie = TRUE;
		}
		// The global must be available very early, because methods below
		// might trigger code which relies on it. See: #45625
		$GLOBALS['BE_USER'] = $backendUser;
		$backendUser->start();
		return $this;
	}

	/**
	 * Initializes and ensures authenticated access
	 *
	 * @internal This is not a public API method, do not use in own extensions
	 * @param bool $proceedIfNoUserIsLoggedIn if set to TRUE, no forced redirect to the login page will be done
	 * @return \TYPO3\CMS\Core\Core\Bootstrap
	 */
	public function initializeBackendAuthentication($proceedIfNoUserIsLoggedIn = FALSE) {
		$GLOBALS['BE_USER']->checkCLIuser();
		$GLOBALS['BE_USER']->backendCheckLogin($proceedIfNoUserIsLoggedIn);
		return $this;
	}

	/**
	 * Initialize language object
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeLanguageObject() {
		/** @var $GLOBALS['LANG'] \TYPO3\CMS\Lang\LanguageService */
		$GLOBALS['LANG'] = GeneralUtility::makeInstance(\TYPO3\CMS\Lang\LanguageService::class);
		$GLOBALS['LANG']->init($GLOBALS['BE_USER']->uc['lang']);
		return $this;
	}

	/**
	 * Throw away all output that may have happened during bootstrapping by weird extensions
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function endOutputBufferingAndCleanPreviousOutput() {
		ob_clean();
		return $this;
	}

	/**
	 * Initialize output compression if configured
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeOutputCompression() {
		if (extension_loaded('zlib') && $GLOBALS['TYPO3_CONF_VARS']['BE']['compressionLevel']) {
			if (MathUtility::canBeInterpretedAsInteger($GLOBALS['TYPO3_CONF_VARS']['BE']['compressionLevel'])) {
				@ini_set('zlib.output_compression_level', $GLOBALS['TYPO3_CONF_VARS']['BE']['compressionLevel']);
			}
			ob_start('ob_gzhandler');
		}
		return $this;
	}

	/**
	 * Send HTTP headers if configured
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function sendHttpHeaders() {
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['HTTP']['Response']['Headers']) && is_array($GLOBALS['TYPO3_CONF_VARS']['BE']['HTTP']['Response']['Headers'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['BE']['HTTP']['Response']['Headers'] as $header) {
				header($header);
			}
		}
		return $this;
	}

	/**
	 * Things that should be performed to shut down the framework.
	 * This method is called in all important scripts for a clean
	 * shut down of the system.
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function shutdown() {
		$this->sendResponse();
		return $this;
	}

	/**
	 * Provides an instance of "template" for backend-modules to
	 * work with.
	 *
	 * @return Bootstrap
	 * @internal This is not a public API method, do not use in own extensions
	 */
	public function initializeBackendTemplate() {
		$GLOBALS['TBE_TEMPLATE'] = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		return $this;
	}

}
