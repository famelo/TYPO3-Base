<?php
namespace FluidTYPO3\Fluidcontent\Backend;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Database\TableConfigurationPostProcessingHookInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * @package Fluidcontent
 * @subpackage Backend
 */
class TableConfigurationPostProcessor implements TableConfigurationPostProcessingHookInterface {

	/**
	 * @return void
	 */
	public function processData() {
		/** @var \FluidTYPO3\Fluidcontent\Service\ConfigurationService $configurationService */
		$configurationService = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager')
			->get('FluidTYPO3\Fluidcontent\Service\ConfigurationService');

		ExtensionManagementUtility::addPageTSConfig($configurationService->getPageTsConfig());
	}

}
