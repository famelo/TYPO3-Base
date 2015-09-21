<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Template');
\FluidTYPO3\Flux\Core::registerProviderExtensionKey('Famelo.Template', 'Page');
\FluidTYPO3\Flux\Core::registerProviderExtensionKey('Famelo.Template', 'Content');

