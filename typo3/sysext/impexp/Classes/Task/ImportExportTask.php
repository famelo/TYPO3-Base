<?php
namespace TYPO3\CMS\Impexp\Task;

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

/**
 * This class provides a textarea to save personal notes
 */
class ImportExportTask implements \TYPO3\CMS\Taskcenter\TaskInterface {

	/**
	 * Back-reference to the calling reports module
	 *
	 * @var \TYPO3\CMS\Taskcenter\Controller\TaskModuleController $taskObject
	 */
	protected $taskObject;

	/**
	 * Constructor
	 */
	public function __construct(\TYPO3\CMS\Taskcenter\Controller\TaskModuleController $taskObject) {
		$this->taskObject = $taskObject;
		$GLOBALS['LANG']->includeLLFile('EXT:impexp/Resources/Private/Language/locallang_csh.xlf');
	}

	/**
	 * This method renders the report
	 *
	 * @return string The status report as HTML
	 */
	public function getTask() {
		return $this->main();
	}

	/**
	 * Render an optional additional information for the 1st view in taskcenter.
	 * Empty for this task
	 *
	 * @return string Overview as HTML
	 */
	public function getOverview() {
		return '';
	}

	/**
	 * Main Task center module
	 *
	 * @return string HTML content.
	 */
	public function main() {
		$content = '';
		$id = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('display');
		// If a preset is found, it is rendered using an iframe
		if ($id > 0) {
			$url = BackendUtility::getModuleUrl(
				'xMOD_tximpexp',
				array(
					'tx_impexp[action]' => 'export',
					'preset[load]' => 1,
					'preset[select]' => $id)
			);
			return $this->taskObject->urlInIframe($url, 1);
		} else {
			// Header
			$content .= $this->taskObject->description($GLOBALS['LANG']->getLL('.alttitle'), $GLOBALS['LANG']->getLL('.description'));
			$thumbnails = ($lines = array());
			// Thumbnail folder and files:
			$tempDir = $this->getDefaultImportExportFolder()->getPublicUrl();
			if ($tempDir) {
				$thumbnails = \TYPO3\CMS\Core\Utility\GeneralUtility::getFilesInDir($tempDir, 'png,gif,jpg', 1);
			}
			$clause = $GLOBALS['BE_USER']->getPagePermsClause(1);
			$usernames = \TYPO3\CMS\Backend\Utility\BackendUtility::getUserNames();
			// Create preset links:
			$presets = $this->getPresets();
			// If any presets found
			if (is_array($presets)) {
				foreach ($presets as $key => $presetCfg) {
					$configuration = unserialize($presetCfg['preset_data']);
					$thumbnailFile = $thumbnails[$configuration['meta']['thumbnail']];
					$title = strlen($presetCfg['title']) ? $presetCfg['title'] : '[' . $presetCfg['uid'] . ']';
					$icon = 'EXT:impexp/Resources/Public/Images/export.gif';
					$description = array();
					// Is public?
					if ($presetCfg['public']) {
						$description[] = $GLOBALS['LANG']->getLL('task.public') . ': ' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_common.xlf:yes');
					}
					// Owner
					$description[] = $GLOBALS['LANG']->getLL('task.owner') . ': ' . ($presetCfg['user_uid'] === $GLOBALS['BE_USER']->user['uid'] ? $GLOBALS['LANG']->getLL('task.own') : '[' . htmlspecialchars($usernames[$presetCfg['user_uid']]['username']) . ']');
					// Page & path
					if ($configuration['pagetree']['id']) {
						$description[] = $GLOBALS['LANG']->getLL('task.page') . ': ' . $configuration['pagetree']['id'];
						$description[] = $GLOBALS['LANG']->getLL('task.path') . ': ' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::getRecordPath($configuration['pagetree']['id'], $clause, 20));
					} else {
						$description[] = $GLOBALS['LANG']->getLL('single-record');
					}
					// Meta information
					if ($configuration['meta']['title'] || $configuration['meta']['description'] || $configuration['meta']['notes']) {
						$metaInformation = '';
						if ($configuration['meta']['title']) {
							$metaInformation .= '<strong>' . htmlspecialchars($configuration['meta']['title']) . '</strong><br />';
						}
						if ($configuration['meta']['description']) {
							$metaInformation .= htmlspecialchars($configuration['meta']['description']);
						}
						if ($configuration['meta']['notes']) {
							$metaInformation .= '<br /><br />
												<strong>' . $GLOBALS['LANG']->getLL('notes') . ': </strong>
												<em>' . htmlspecialchars($configuration['meta']['notes']) . '</em>';
						}
						$description[] = '<br />' . $metaInformation;
					}
					// Collect all preset information
					$lines[$key] = array(
						'icon' => $icon,
						'title' => $title,
						'descriptionHtml' => implode('<br />', $description),
						'link' => BackendUtility::getModuleUrl('user_task') . '&SET[function]=impexp.TYPO3\\CMS\\Impexp\\Task\\ImportExportTask&display=' . $presetCfg['uid']
					);
				}
				// Render preset list
				$content .= $this->taskObject->renderListMenu($lines);
			} else {
				// No presets found
				$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
					\TYPO3\CMS\Core\Messaging\FlashMessage::class,
					$GLOBALS['LANG']->getLL('no-presets'),
					'',
					\TYPO3\CMS\Core\Messaging\FlashMessage::NOTICE
				);
				$content .= $flashMessage->render();
			}
		}
		return $content;
	}

	/**
	 * Select presets for this user
	 *
	 * @return array Array of preset records
	 */
	protected function getPresets() {
		$presets = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_impexp_presets',
			'(public > 0 OR user_uid=' . $GLOBALS['BE_USER']->user['uid'] . ')',
			'',
			'item_uid DESC, title'
		);
		return $presets;
	}

	/**
	 * Returns a \TYPO3\CMS\Core\Resource\Folder object for saving export files
	 * to the server and is also used for uploading import files.
	 *
	 * @throws \InvalidArgumentException
	 * @return NULL|\TYPO3\CMS\Core\Resource\Folder
	 */
	protected function getDefaultImportExportFolder() {
		$defaultImportExportFolder = NULL;

		$defaultTemporaryFolder = $this->getBackendUser()->getDefaultUploadTemporaryFolder();
		if ($defaultTemporaryFolder !== NULL) {

			$importExportFolderName = 'importexport';
			$createFolder = !$defaultTemporaryFolder->hasFolder($importExportFolderName);
			if ($createFolder === TRUE) {
				try {
					$defaultImportExportFolder = $defaultTemporaryFolder->createFolder($importExportFolderName);
				} catch (\TYPO3\CMS\Core\Resource\Exception $folderAccessException) {}
			} else {
				$defaultImportExportFolder = $defaultTemporaryFolder->getSubfolder($importExportFolderName);
			}
		}

		return $defaultImportExportFolder;
	}

	/**
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

}
