<?php
namespace TYPO3\CMS\Core\ExtDirect;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Ext Direct API Generator
 */
class ExtDirectApi {

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructs this object.
	 */
	public function __construct() {
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect']) && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'])) {
			$this->settings = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect'];
		}
	}

	/**
	 * Parses the ExtDirect configuration array "$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect']"
	 * and feeds the given typo3 ajax instance with the resulting information. The get parameter
	 * "namespace" will be used to filter the configuration.
	 *
	 * This method makes usage of the reflection mechanism to fetch the methods inside the
	 * defined classes together with their amount of parameters. This information are building
	 * the API and are required by ExtDirect. The result is cached to improve the overall
	 * performance.
	 *
	 * @param array $ajaxParams Ajax parameters
	 * @param \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj Ajax object
	 * @return void
	 */
	public function getAPI($ajaxParams, \TYPO3\CMS\Core\Http\AjaxRequestHandler $ajaxObj) {
		$ajaxObj->setContent(array());
	}

	/**
	 * Get the API for a given nameapace
	 *
	 * @param array $filterNamespaces
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function getApiPhp(array $filterNamespaces) {
		$javascriptNamespaces = $this->getExtDirectApi($filterNamespaces);
		// Return the generated javascript API configuration
		if (!empty($javascriptNamespaces)) {
			return '
				if (!Ext.isObject(Ext.app.ExtDirectAPI)) {
					Ext.app.ExtDirectAPI = {};
				}
				Ext.apply(Ext.app.ExtDirectAPI, ' . json_encode($javascriptNamespaces) . ');
			';
		} else {
			$errorMessage = $this->getNamespaceError($filterNamespaces);
			throw new \InvalidArgumentException($errorMessage, 1297645190);
		}
	}

	/**
	 * Generates the API that is configured inside the ExtDirect configuration
	 * array "$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ExtDirect']".
	 *
	 * @param array $filerNamespace Namespace that should be loaded like array('TYPO3.Backend')
	 * @return array Javascript API configuration
	 */
	protected function generateAPI(array $filterNamespaces) {
		$javascriptNamespaces = array();
		if (is_array($this->settings)) {
			foreach ($this->settings as $javascriptName => $configuration) {
				$splittedJavascriptName = explode('.', $javascriptName);
				$javascriptObjectName = array_pop($splittedJavascriptName);
				$javascriptNamespace = implode('.', $splittedJavascriptName);
				// Only items inside the wanted namespace
				if (!$this->findNamespace($javascriptNamespace, $filterNamespaces)) {
					continue;
				}
				if (!isset($javascriptNamespaces[$javascriptNamespace])) {
					$javascriptNamespaces[$javascriptNamespace] = array(
						'url' => $this->getRoutingUrl($javascriptNamespace),
						'type' => 'remoting',
						'actions' => array(),
						'namespace' => $javascriptNamespace
					);
				}
				if (is_array($configuration)) {
					$className = $configuration['callbackClass'];
					$serverObject = GeneralUtility::getUserObj($className);
					$javascriptNamespaces[$javascriptNamespace]['actions'][$javascriptObjectName] = array();
					foreach (get_class_methods($serverObject) as $methodName) {
						$reflectionMethod = new \ReflectionMethod($serverObject, $methodName);
						$numberOfParameters = $reflectionMethod->getNumberOfParameters();
						$docHeader = $reflectionMethod->getDocComment();
						$formHandler = strpos($docHeader, '@formHandler') !== FALSE;
						$javascriptNamespaces[$javascriptNamespace]['actions'][$javascriptObjectName][] = array(
							'name' => $methodName,
							'len' => $numberOfParameters,
							'formHandler' => $formHandler
						);
					}
				}
			}
		}
		return $javascriptNamespaces;
	}

	/**
	 * Returns the convenient path for the routing Urls based on the TYPO3 mode.
	 *
	 * @param string $namespace
	 * @return string
	 */
	public function getRoutingUrl($namespace) {
		if (TYPO3_MODE === 'FE') {
			$url = GeneralUtility::locationHeaderUrl('?eID=ExtDirect&action=route&namespace=' . rawurlencode($namespace));
		} else {
			$url = BackendUtility::getAjaxUrl('ExtDirect::route', array('namespace' => $namespace));
		}
		return $url;
	}

	/**
	 * Generates the API or reads it from cache
	 *
	 * @param array $filterNamespaces
	 * @return string $javascriptNamespaces
	 */
	protected function getExtDirectApi(array $filterNamespaces) {
		$noCache = (bool)GeneralUtility::_GET('no_cache');
		// Look up into the cache
		$cacheIdentifier = 'ExtDirectApi';
		$cacheHash = md5($cacheIdentifier . implode(',', $filterNamespaces) . GeneralUtility::getIndpEnv('TYPO3_SSL') . serialize($this->settings) . TYPO3_MODE . GeneralUtility::getIndpEnv('HTTP_HOST'));
		// With no_cache always generate the javascript content
		// Generate the javascript content if it wasn't found inside the cache and cache it!
		if ($noCache || !is_array(($javascriptNamespaces = \TYPO3\CMS\Frontend\Page\PageRepository::getHash($cacheHash)))) {
			$javascriptNamespaces = $this->generateAPI($filterNamespaces);
			if (!empty($javascriptNamespaces)) {
				\TYPO3\CMS\Frontend\Page\PageRepository::storeHash($cacheHash, $javascriptNamespaces, $cacheIdentifier);
			}
		}
		return $javascriptNamespaces;
	}

	/**
	 * Generates the error message
	 *
	 * @param array $filterNamespaces
	 * @return string $errorMessage
	 */
	protected function getNamespaceError(array $filterNamespaces) {
		if (!empty($filterNamespaces)) {
			// Namespace error
			$errorMessage = sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:ExtDirect.namespaceError'), __CLASS__, implode(',', $filterNamespaces));
		} else {
			// No namespace given
			$errorMessage = sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:ExtDirect.noNamespace'), __CLASS__);
		}
		return $errorMessage;
	}

	/**
	 * Looks if the given namespace is present in $filterNamespaces
	 *
	 * @param string $namespace
	 * @param array $filterNamespaces
	 * @return bool
	 */
	protected function findNamespace($namespace, array $filterNamespaces) {
		if ($filterNamespaces === array('TYPO3')) {
			return TRUE;
		}
		$found = FALSE;
		foreach ($filterNamespaces as $filter) {
			if (GeneralUtility::isFirstPartOfStr($filter, $namespace)) {
				$found = TRUE;
				break;
			}
		}
		return $found;
	}

}
