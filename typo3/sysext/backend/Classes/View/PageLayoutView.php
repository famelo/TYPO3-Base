<?php
namespace TYPO3\CMS\Backend\View;

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

use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

/**
 * Child class for the Web > Page module
 */
class PageLayoutView extends \TYPO3\CMS\Recordlist\RecordList\AbstractDatabaseRecordList {

	/**
	 * If TRUE, users/groups are shown in the page info box.
	 *
	 * @var int
	 */
	public $pI_showUser = 0;

	/**
	 * The number of successive records to edit when showing content elements.
	 *
	 * @var int
	 */
	public $nextThree = 3;

	/**
	 * If TRUE, disables the edit-column icon for tt_content elements
	 *
	 * @var int
	 */
	public $pages_noEditColumns = 0;

	/**
	 * If TRUE, new-wizards are linked to rather than the regular new-element list.
	 *
	 * @var int
	 */
	public $option_newWizard = 1;

	/**
	 * If set to "1", will link a big button to content element wizard.
	 *
	 * @var int
	 */
	public $ext_function = 0;

	/**
	 * If TRUE, elements will have edit icons (probably this is whether the user has permission to edit the page content). Set externally.
	 *
	 * @var int
	 */
	public $doEdit = 1;

	/**
	 * Age prefixes for displaying times. May be set externally to localized values.
	 *
	 * @var string
	 */
	public $agePrefixes = ' min| hrs| days| yrs| min| hour| day| year';

	/**
	 * Array of tables to be listed by the Web > Page module in addition to the default tables.
	 *
	 * @var array
	 */
	public $externalTables = array();

	/**
	 * "Pseudo" Description -table name
	 *
	 * @var string
	 */
	public $descrTable;

	/**
	 * If set TRUE, the language mode of tt_content elements will be rendered with hard binding between
	 * default language content elements and their translations!
	 *
	 * @var bool
	 */
	public $defLangBinding = FALSE;

	/**
	 * External, static: Configuration of tt_content element display:
	 *
	 * @var array
	 */
	public $tt_contentConfig = array(
		// Boolean: Display info-marks or not
		'showInfo' => 1,
		// Boolean: Display up/down arrows and edit icons for tt_content records
		'showCommands' => 1,
		'languageCols' => 0,
		'languageMode' => 0,
		'languageColsPointer' => 0,
		'showHidden' => 1,
		// Displays hidden records as well
		'sys_language_uid' => 0,
		// Which language
		'cols' => '1,0,2,3',
		'activeCols' => '1,0,2,3'
		// Which columns can be accessed by current BE user
	);

	/**
	 * Contains icon/title of pages which are listed in the tables menu (see getTableMenu() function )
	 *
	 * @var array
	 */
	public $activeTables = array();

	/**
	 * @var array
	 */
	public $tt_contentData = array(
		'nextThree' => array(),
		'prev' => array(),
		'next' => array()
	);

	/**
	 * Used to store labels for CTypes for tt_content elements
	 *
	 * @var array
	 */
	public $CType_labels = array();

	/**
	 * Used to store labels for the various fields in tt_content elements
	 *
	 * @var array
	 */
	public $itemLabels = array();

	/**
	 * @var \TYPO3\CMS\Backend\Clipboard\Clipboard
	 */
	protected $clipboard;

	/**
	 * @var array
	 */
	protected $plusPages = array();

	/**
	 * User permissions
	 *
	 * @var int
	 */
	public $ext_CALC_PERMS;

	/*****************************************
	 *
	 * Renderings
	 *
	 *****************************************/
	/**
	 * Adds the code of a single table
	 *
	 * @param string $table Table name
	 * @param int $id Current page id
	 * @param string $fields
	 * @return string HTML for listing.
	 */
	public function getTable($table, $id, $fields = '') {
		if (isset($this->externalTables[$table])) {
			return $this->getExternalTables($id, $table);
		} else {
			// Branch out based on table name:
			switch ($table) {
				case 'pages':
					return $this->getTable_pages($id);
					break;
				case 'tt_content':
					return $this->getTable_tt_content($id);
					break;
				default:
					return '';
			}
		}
	}

	/**
	 * Renders an external table from page id
	 *
	 * @param int $id Page id
	 * @param string $table Name of the table
	 * @return string HTML for the listing
	 */
	public function getExternalTables($id, $table) {
		$type = $this->getPageLayoutController()->MOD_SETTINGS[$table];
		if (!isset($type)) {
			$type = 0;
		}
		// eg. "name;title;email;company,image"
		$fList = $this->externalTables[$table][$type]['fList'];
		// The columns are separeted by comma ','.
		// Values separated by semicolon ';' are shown in the same column.
		$icon = $this->externalTables[$table][$type]['icon'];
		$addWhere = $this->externalTables[$table][$type]['addWhere'];
		// Create listing
		$out = $this->makeOrdinaryList($table, $id, $fList, $icon, $addWhere);
		return $out;
	}

	/**
	 * Renders records from the pages table from page id
	 * (Used to get information about the page tree content by "Web>Info"!)
	 *
	 * @param int $id Page id
	 * @return string HTML for the listing
	 */
	public function getTable_pages($id) {
		// Initializing:
		$out = '';
		// Select clause for pages:
		$delClause = BackendUtility::deleteClause('pages') . ' AND ' . $this->getBackendUser()->getPagePermsClause(1);
		// Select current page:
		if (!$id) {
			// The root has a pseudo record in pageinfo...
			$row = $this->getPageLayoutController()->pageinfo;
		} else {
			$result = $this->getDatabase()->exec_SELECTquery('*', 'pages', 'uid=' . (int)$id . $delClause);
			$row = $this->getDatabase()->sql_fetch_assoc($result);
			BackendUtility::workspaceOL('pages', $row);
		}
		// If there was found a page:
		if (is_array($row)) {
			// Select which fields to show:
			$pKey = $this->getPageLayoutController()->MOD_SETTINGS['pages'];
			switch ($pKey) {
				case 1:
					$this->fieldArray = array('title','uid') + array_keys($this->cleanTableNames());
					break;
				case 2:
					$this->fieldArray = array(
						'title',
						'uid',
						'lastUpdated',
						'newUntil',
						'no_cache',
						'cache_timeout',
						'php_tree_stop',
						'TSconfig',
						'is_siteroot',
						'fe_login_mode'
					);
					break;
				default:
					$this->fieldArray = array(
						'title',
						'uid',
						'alias',
						'starttime',
						'endtime',
						'fe_group',
						'target',
						'url',
						'shortcut',
						'shortcut_mode'
					);
			}
			// Getting select-depth:
			$depth = (int)$this->getPageLayoutController()->MOD_SETTINGS['pages_levels'];
			// Overriding a few things:
			$this->no_noWrap = 0;
			// Items
			$this->eCounter = $this->firstElementNumber;
			// Creating elements:
			list($flag, $code) = $this->fwd_rwd_nav();
			$out .= $code;
			$editUids = array();
			if ($flag) {
				// Getting children:
				$theRows = array();
				$theRows = $this->pages_getTree($theRows, $row['uid'], $delClause . BackendUtility::versioningPlaceholderClause('pages'), '', $depth);
				if ($this->getBackendUser()->doesUserHaveAccess($row, 2)) {
					$editUids[] = $row['uid'];
				}
				$out .= $this->pages_drawItem($row, $this->fieldArray);
				// Traverse all pages selected:
				foreach ($theRows as $sRow) {
					if ($this->getBackendUser()->doesUserHaveAccess($sRow, 2)) {
						$editUids[] = $sRow['uid'];
					}
					$out .= $this->pages_drawItem($sRow, $this->fieldArray);
				}
				$this->eCounter++;
			}
			// Header line is drawn
			$theData = array();
			$editIdList = implode(',', $editUids);
			// Traverse fields (as set above) in order to create header values:
			foreach ($this->fieldArray as $field) {
				if ($editIdList && isset($GLOBALS['TCA']['pages']['columns'][$field]) && $field != 'uid' && !$this->pages_noEditColumns) {
					$params = '&edit[pages][' . $editIdList . ']=edit&columnsOnly=' . $field;
					$iTitle = sprintf(
						$this->getLanguageService()->getLL('editThisColumn'),
						rtrim(trim($this->getLanguageService()->sL(BackendUtility::getItemLabel('pages', $field))), ':')
					);
					$eI = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params))
						. '" title="' . htmlspecialchars($iTitle) . '">' . IconUtility::getSpriteIcon('actions-document-open') . '</a>';
				} else {
					$eI = '';
				}
				switch ($field) {
					case 'title':
						$theData[$field] = '&nbsp;<strong>'
							. $this->getLanguageService()->sL($GLOBALS['TCA']['pages']['columns'][$field]['label'])
							. '</strong>' . $eI;
						break;
					case 'uid':
						$theData[$field] = '&nbsp;<strong>ID:</strong>';
						break;
					default:
						if (substr($field, 0, 6) == 'table_') {
							$f2 = substr($field, 6);
							if ($GLOBALS['TCA'][$f2]) {
								$theData[$field] = '&nbsp;' . IconUtility::getSpriteIconForRecord($f2, array(), array(
									'title' => $this->getLanguageService()->sL($GLOBALS['TCA'][$f2]['ctrl']['title'], TRUE)
									));
							}
						} else {
							$theData[$field] = '&nbsp;&nbsp;<strong>'
								. $this->getLanguageService()->sL($GLOBALS['TCA']['pages']['columns'][$field]['label'], TRUE)
								. '</strong>' . $eI;
						}
				}
			}
			// CSH:
			$out = BackendUtility::cshItem($this->descrTable, ('func_' . $pKey)) . '
				<div class="table-fit">
					<table class="table table-striped table-hover typo3-page-pages">' .
						'<thead>' .
							$this->addelement(1, '', $theData) .
						'</thead>' .
						'<tbody>' .
							$out .
						'</tbody>' .
					'</table>
				</div>';
		}
		return $out;
	}

	/**
	 * Renders Content Elements from the tt_content table from page id
	 *
	 * @param int $id Page id
	 * @return string HTML for the listing
	 */
	public function getTable_tt_content($id) {
		$this->initializeLanguages();
		$this->initializeClipboard();
		$pageTitleParamForAltDoc = '&recTitle=' . rawurlencode(BackendUtility::getRecordTitle('pages', BackendUtility::getRecordWSOL('pages', $id), TRUE));
		/** @var $pageRenderer PageRenderer */
		$pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
		$pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/LayoutModule/DragDrop');
		$pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
		$userCanEditPage = $this->ext_CALC_PERMS & Permission::PAGE_EDIT && !empty($this->id);
		if ($this->tt_contentConfig['languageColsPointer'] > 0) {
			$userCanEditPage = $this->getBackendUser()->check('tables_modify', 'pages_language_overlay');
		}
		$pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/PageActions', 'function(PageActions) {
			PageActions.setPageId(' . (int)$this->id . ');
			PageActions.setCanEditPage(' . ($userCanEditPage ? 'true' : 'false') . ');
			PageActions.setLanguageOverlayId(' . $this->tt_contentConfig['languageColsPointer'] . ');
			PageActions.initializePageTitleRenaming();
		}');
		// Get labels for CTypes and tt_content element fields in general:
		$this->CType_labels = array();
		foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $val) {
			$this->CType_labels[$val[1]] = $this->getLanguageService()->sL($val[0]);
		}
		$this->itemLabels = array();
		foreach ($GLOBALS['TCA']['tt_content']['columns'] as $name => $val) {
			$this->itemLabels[$name] = $this->getLanguageService()->sL($val['label']);
		}
		$languageColumn = array();
		$out = '';

		// Setting language list:
		$langList = $this->tt_contentConfig['sys_language_uid'];
		if ($this->tt_contentConfig['languageMode']) {
			if ($this->tt_contentConfig['languageColsPointer']) {
				$langList = '0,' . $this->tt_contentConfig['languageColsPointer'];
			} else {
				$langList = implode(',', array_keys($this->tt_contentConfig['languageCols']));
			}
			$languageColumn = array();
		}
		$langListArr = GeneralUtility::intExplode(',', $langList);
		$defLanguageCount = array();
		$defLangBinding = array();
		// For each languages... :
		// If not languageMode, then we'll only be through this once.
		foreach ($langListArr as $lP) {
			$lP = (int)$lP;

			if (!isset($this->getPageLayoutController()->contentElementCache[$lP])) {
				$this->getPageLayoutController()->contentElementCache[$lP] = array();
			}

			if (count($langListArr) === 1 || $lP === 0) {
				$showLanguage = ' AND sys_language_uid IN (' . $lP . ',-1)';
			} else {
				$showLanguage = ' AND sys_language_uid=' . $lP;
			}
			$cList = explode(',', $this->tt_contentConfig['cols']);
			$content = array();
			$head = array();

			// Select content records per column
			$contentRecordsPerColumn = $this->getContentRecordsPerColumn('table', $id, array_values($cList), $showLanguage);
			// For each column, render the content into a variable:
			foreach ($cList as $key) {
				if (!isset($this->getPageLayoutController()->contentElementCache[$lP][$key])) {
					$this->getPageLayoutController()->contentElementCache[$lP][$key] = array();
				}

				if (!$lP) {
					$defLanguageCount[$key] = array();
				}
				// Start wrapping div
				$content[$key] .= '<div data-colpos="' . $key . '" data-language-uid="' . $lP . '" class="t3js-sortable t3js-sortable-lang t3js-sortable-lang-' . $lP . ' t3-page-ce-wrapper';
				if (empty($contentRecordsPerColumn[$key])) {
					$content[$key] .= ' t3-page-ce-empty';
				}
				$content[$key] .= '">';
				// Add new content at the top most position
				$link = '';
				if ($this->getPageLayoutController()->pageIsNotLockedForEditors()) {
					$link = '<a href="#" onclick="' . htmlspecialchars($this->newContentElementOnClick($id, $key, $lP))
						. '" title="' . $this->getLanguageService()->getLL('newContentElement', TRUE) . '" class="btn btn-default btn-sm">'
						. IconUtility::getSpriteIcon('actions-document-new')
						. ' '
						. $this->getLanguageService()->getLL('content', TRUE) . '</a>';
				}
				$content[$key] .= '
				<div class="t3-page-ce t3js-page-ce" data-page="' . (int)$id . '" id="' . str_replace('.', '', uniqid('', TRUE)) . '">
					<div class="t3js-page-new-ce t3-page-ce-wrapper-new-ce" id="colpos-' . $key . '-' . 'page-' . $id . '-' . uniqid('', TRUE) . '">'
						. $link
					. '</div>
					<div class="t3-page-ce-dropzone-available t3js-page-ce-dropzone-available"></div>
				</div>
				';
				$editUidList = '';
				$rowArr = $contentRecordsPerColumn[$key];
				$this->generateTtContentDataArray($rowArr);

				foreach ((array)$rowArr as $rKey => $row) {
					$this->getPageLayoutController()->contentElementCache[$lP][$key][$row['uid']] = $row;
					if ($this->tt_contentConfig['languageMode']) {
						$languageColumn[$key][$lP] = $head[$key] . $content[$key];
						if (!$this->defLangBinding) {
							$languageColumn[$key][$lP] .= $this->newLanguageButton(
								$this->getNonTranslatedTTcontentUids($defLanguageCount[$key], $id, $lP),
								$lP,
								$key
							);
						}
					}
					if (is_array($row) && !VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
						$singleElementHTML = '';
						if (!$lP && ($this->defLangBinding || $row['sys_language_uid'] != -1)) {
							$defLanguageCount[$key][] = $row['uid'];
						}
						$editUidList .= $row['uid'] . ',';
						$disableMoveAndNewButtons = $this->defLangBinding && $lP > 0;
						if (!$this->tt_contentConfig['languageMode']) {
							$singleElementHTML .= '<div class="t3-page-ce-dragitem" id="' . str_replace('.', '', uniqid('', TRUE)) . '">';
						}
						$singleElementHTML .= $this->tt_content_drawHeader(
							$row,
							$this->tt_contentConfig['showInfo'] ? 15 : 5,
							$disableMoveAndNewButtons,
							TRUE,
							!$this->tt_contentConfig['languageMode']
						);
						$innerContent = '<div ' . ($row['_ORIG_uid'] ? ' class="ver-element"' : '') . '>'
							. $this->tt_content_drawItem($row) . '</div>';
						$singleElementHTML .= '<div class="t3-page-ce-body-inner">' . $innerContent . '</div>'
							. $this->tt_content_drawFooter($row);
						$isDisabled = $this->isDisabled('tt_content', $row);
						$statusHidden = $isDisabled ? ' t3-page-ce-hidden t3js-hidden-record' : '';
						$displayNone = !$this->tt_contentConfig['showHidden'] && $isDisabled ? ' style="display: none;"' : '';
						$singleElementHTML = '<div class="t3-page-ce t3js-page-ce t3js-page-ce-sortable ' . $statusHidden . '" id="element-tt_content-'
							. $row['uid'] . '" data-table="tt_content" data-uid="' . $row['uid'] . '"' . $displayNone . '>' . $singleElementHTML . '</div>';

						if ($this->tt_contentConfig['languageMode']) {
							$singleElementHTML .= '<div class="t3-page-ce t3js-page-ce">';
						}
						$singleElementHTML .= '<div class="t3js-page-new-ce t3-page-ce-wrapper-new-ce" id="colpos-' . $key . '-' . 'page-' . $id .
							'-' . str_replace('.', '', uniqid('', TRUE)) . '">';
						// Add icon "new content element below"
						if (!$disableMoveAndNewButtons && $this->getPageLayoutController()->pageIsNotLockedForEditors()) {
							// New content element:
							if ($this->option_newWizard) {
								$onClick = 'window.location.href=' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('new_content_element') . '&id=' . $row['pid']
									. '&sys_language_uid=' . $row['sys_language_uid'] . '&colPos=' . $row['colPos']
									. '&uid_pid=' . -$row['uid'] .
									'&returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI'))) . ';';
							} else {
								$params = '&edit[tt_content][' . -$row['uid'] . ']=new';
								$onClick = BackendUtility::editOnClick($params, $this->backPath);
							}
							$singleElementHTML .= '
								<a href="#" onclick="' . htmlspecialchars($onClick) . '" title="'
									. $this->getLanguageService()->getLL('newContentElement', TRUE) . '" class="btn btn-default btn-sm">'
									. IconUtility::getSpriteIcon('actions-document-new')
									. ' '
									. $this->getLanguageService()->getLL('content', TRUE) . '</a>
							';
						}
						$singleElementHTML .= '</div></div><div class="t3-page-ce-dropzone-available t3js-page-ce-dropzone-available"></div></div>';
						if ($this->defLangBinding && $this->tt_contentConfig['languageMode']) {
							$defLangBinding[$key][$lP][$row[$lP ? 'l18n_parent' : 'uid']] = $singleElementHTML;
						} else {
							$content[$key] .= $singleElementHTML;
						}
					} else {
						unset($rowArr[$rKey]);
					}
				}
				$content[$key] .= '</div>';
				// Add new-icon link, header:
				$newP = $this->newContentElementOnClick($id, $key, $lP);
				$colTitle = BackendUtility::getProcessedValue('tt_content', 'colPos', $key);
				$tcaItems = GeneralUtility::callUserFunction(\TYPO3\CMS\Backend\View\BackendLayoutView::class . '->getColPosListItemsParsed', $id, $this);
				foreach ($tcaItems as $item) {
					if ($item[1] == $key) {
						$colTitle = $this->getLanguageService()->sL($item[0]);
					}
				}

				$pasteP = array('colPos' => $key, 'sys_language_uid' => $lP);
				$editParam = $this->doEdit && !empty($rowArr)
					? '&edit[tt_content][' . $editUidList . ']=edit' . $pageTitleParamForAltDoc
					: '';
				$head[$key] .= $this->tt_content_drawColHeader($colTitle, $editParam, $newP, $pasteP);
			}
			// For each column, fit the rendered content into a table cell:
			$out = '';
			if ($this->tt_contentConfig['languageMode']) {
				// in language mode process the content elements, but only fill $languageColumn. output will be generated later
				$sortedLanguageColumn = array();
				foreach ($cList as $key) {
					$languageColumn[$key][$lP] = $head[$key] . $content[$key];
					if (!$this->defLangBinding) {
						$languageColumn[$key][$lP] .= $this->newLanguageButton(
							$this->getNonTranslatedTTcontentUids($defLanguageCount[$key], $id, $lP),
							$lP,
							$key
						);
					}
					// We sort $languageColumn again according to $cList as it may contain data already from above.
					$sortedLanguageColumn[$key] = $languageColumn[$key];
				}
				$languageColumn = $sortedLanguageColumn;
			} else {
				$backendLayout = $this->getBackendLayoutView()->getSelectedBackendLayout($this->id);
				// GRID VIEW:
				$grid = '<div class="t3-grid-container"><table border="0" cellspacing="0" cellpadding="0" width="100%" height="100%" class="t3-page-columns t3-grid-table t3js-page-columns">';
				// Add colgroups
				$colCount = (int)$backendLayout['__config']['backend_layout.']['colCount'];
				$rowCount = (int)$backendLayout['__config']['backend_layout.']['rowCount'];
				$grid .= '<colgroup>';
				for ($i = 0; $i < $colCount; $i++) {
					$grid .= '<col style="width:' . 100 / $colCount . '%"></col>';
				}
				$grid .= '</colgroup>';
				// Cycle through rows
				for ($row = 1; $row <= $rowCount; $row++) {
					$rowConfig = $backendLayout['__config']['backend_layout.']['rows.'][$row . '.'];
					if (!isset($rowConfig)) {
						continue;
					}
					$grid .= '<tr>';
					for ($col = 1; $col <= $colCount; $col++) {
						$columnConfig = $rowConfig['columns.'][$col . '.'];
						if (!isset($columnConfig)) {
							continue;
						}
						// Which tt_content colPos should be displayed inside this cell
						$columnKey = (int)$columnConfig['colPos'];
						// Render the grid cell
						$colSpan = (int)$columnConfig['colspan'];
						$rowSpan = (int)$columnConfig['rowspan'];
						$grid .= '<td valign="top"' .
							($colSpan > 0 ? ' colspan="' . $colSpan . '"' : '') .
							($rowSpan > 0 ? ' rowspan="' . $rowSpan . '"' : '') .
							' data-colpos="' . (int)$columnConfig['colPos'] . '" data-language-uid="' . $lP . '" class="t3js-page-lang-column-' . $lP . ' t3js-page-column t3-grid-cell t3-page-column t3-page-column-' . $columnKey .
							((!isset($columnConfig['colPos']) || $columnConfig['colPos'] === '') ? ' t3-grid-cell-unassigned' : '') .
							((isset($columnConfig['colPos']) && $columnConfig['colPos'] !== '' && !$head[$columnKey]) || !GeneralUtility::inList($this->tt_contentConfig['activeCols'], $columnConfig['colPos']) ? ' t3-grid-cell-restricted' : '') .
							($colSpan > 0 ? ' t3-gridCell-width' . $colSpan : '') .
							($rowSpan > 0 ? ' t3-gridCell-height' . $rowSpan : '') . '">';

						// Draw the pre-generated header with edit and new buttons if a colPos is assigned.
						// If not, a new header without any buttons will be generated.
						if (
							isset($columnConfig['colPos']) && $columnConfig['colPos'] !== '' && $head[$columnKey]
							&& GeneralUtility::inList($this->tt_contentConfig['activeCols'], $columnConfig['colPos'])
						) {
							$grid .= $head[$columnKey] . $content[$columnKey];
						} elseif (
							isset($columnConfig['colPos']) && $columnConfig['colPos'] !== ''
							&& GeneralUtility::inList($this->tt_contentConfig['activeCols'], $columnConfig['colPos'])
						) {
							$grid .= $this->tt_content_drawColHeader($this->getLanguageService()->getLL('noAccess'), '', '');
						} elseif (
							isset($columnConfig['colPos']) && $columnConfig['colPos'] !== ''
							&& !GeneralUtility::inList($this->tt_contentConfig['activeCols'], $columnConfig['colPos'])
						) {
							$grid .= $this->tt_content_drawColHeader($this->getLanguageService()->sL($columnConfig['name']) .
								' (' . $this->getLanguageService()->getLL('noAccess') . ')', '', '');
						} elseif (isset($columnConfig['name']) && $columnConfig['name'] !== '') {
							$grid .= $this->tt_content_drawColHeader($this->getLanguageService()->sL($columnConfig['name'])
								. ' (' . $this->getLanguageService()->getLL('notAssigned') . ')', '', '');
						} else {
							$grid .= $this->tt_content_drawColHeader($this->getLanguageService()->getLL('notAssigned'), '', '');
						}

						$grid .= '</td>';
					}
					$grid .= '</tr>';
				}
				$out .= $grid . '</table></div>';
			}
			// CSH:
			$out .= BackendUtility::cshItem($this->descrTable, 'columns_multi');
		}
		// If language mode, then make another presentation:
		// Notice that THIS presentation will override the value of $out!
		// But it needs the code above to execute since $languageColumn is filled with content we need!
		if ($this->tt_contentConfig['languageMode']) {
			// Get language selector:
			$languageSelector = $this->languageSelector($id);
			// Reset out - we will make new content here:
			$out = '';
			// Traverse languages found on the page and build up the table displaying them side by side:
			$cCont = array();
			$sCont = array();
			foreach ($langListArr as $lP) {
				// Header:
				$lP = (int)$lP;
				$cCont[$lP] = '
					<td valign="top" class="t3-page-column" data-language-uid="' . $lP . '">
						<h2>' . htmlspecialchars($this->tt_contentConfig['languageCols'][$lP]) . '</h2>
					</td>';

				// "View page" icon is added:
				$viewLink = '';
				if (!VersionState::cast($this->getPageLayoutController()->pageinfo['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
					$onClick = BackendUtility::viewOnClick($this->id, $this->backPath, BackendUtility::BEgetRootLine($this->id), '', '', ('&L=' . $lP));
					$viewLink = '<a href="#" onclick="' . htmlspecialchars($onClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.showPage', TRUE) . '">' . IconUtility::getSpriteIcon('actions-document-view') . '</a>';
				}
				// Language overlay page header:
				if ($lP) {
					list($lpRecord) = BackendUtility::getRecordsByField('pages_language_overlay', 'pid', $id, 'AND sys_language_uid=' . $lP);
					BackendUtility::workspaceOL('pages_language_overlay', $lpRecord);
					$params = '&edit[pages_language_overlay][' . $lpRecord['uid'] . ']=edit&overrideVals[pages_language_overlay][sys_language_uid]=' . $lP;
					$lPLabel = $this->getPageLayoutController()->doc->wrapClickMenuOnIcon(
						IconUtility::getSpriteIconForRecord('pages_language_overlay', $lpRecord),
						'pages_language_overlay',
						$lpRecord['uid']
					) . $viewLink . ($this->getBackendUser()->check('tables_modify', 'pages_language_overlay')
							? '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params))
								. '" title="' . $this->getLanguageService()->getLL('edit', TRUE) . '">'
								. IconUtility::getSpriteIcon('actions-document-open') . '</a>'
							: ''
						) . htmlspecialchars(GeneralUtility::fixed_lgd_cs($lpRecord['title'], 20));
				} else {
					$lPLabel = $viewLink;
				}
				$sCont[$lP] = '
					<td nowrap="nowrap" class="t3-page-column t3-page-lang-label">' . $lPLabel . '</td>';
			}
			// Add headers:
			$out .= '<tr>' . implode($cCont) . '</tr>';
			$out .= '<tr>' . implode($sCont) . '</tr>';
			unset($cCont, $sCont);

			// Traverse previously built content for the columns:
			foreach ($languageColumn as $cKey => $cCont) {
				$out .= '<tr>';
				foreach ($cCont as $languageId => $columnContent) {
					$out .= '<td valign="top" class="t3-grid-cell t3-page-column t3js-page-column t3js-page-lang-column t3js-page-lang-column-' . $languageId . '">' . $columnContent . '</td>';
				}
				$out .= '</tr>';
				if ($this->defLangBinding) {
					// "defLangBinding" mode
					foreach ($defLanguageCount[$cKey] as $defUid) {
						$cCont = array();
						foreach ($langListArr as $lP) {
							$cCont[] = $defLangBinding[$cKey][$lP][$defUid] . $this->newLanguageButton(
								$this->getNonTranslatedTTcontentUids(array($defUid), $id, $lP),
								$lP,
								$cKey
							);
						}
						$out .= '
						<tr>
							<td valign="top" class="t3-grid-cell">' . implode(('</td>' . '
							<td valign="top" class="t3-grid-cell">'), $cCont) . '</td>
						</tr>';
					}
				}
			}
			// Finally, wrap it all in a table and add the language selector on top of it:
			$out = $languageSelector . '
				<div class="t3-grid-container">
					<table cellpadding="0" cellspacing="0" class="t3-page-columns t3-grid-table t3js-page-columns">
						' . $out . '
					</table>
				</div>';
			// CSH:
			$out .= BackendUtility::cshItem($this->descrTable, 'language_list');
		}

		return $out;
	}

	/**********************************
	 *
	 * Generic listing of items
	 *
	 **********************************/
	/**
	 * Creates a standard list of elements from a table.
	 *
	 * @param string $table Table name
	 * @param int $id Page id.
	 * @param string $fList Comma list of fields to display
	 * @param bool $icon If TRUE, icon is shown
	 * @param string $addWhere Additional WHERE-clauses.
	 * @return string HTML table
	 */
	public function makeOrdinaryList($table, $id, $fList, $icon = FALSE, $addWhere = '') {
		// Initialize
		$queryParts = $this->makeQueryArray($table, $id, $addWhere);
		$this->setTotalItems($queryParts);
		$dbCount = 0;
		$result = FALSE;
		// Make query for records if there were any records found in the count operation
		if ($this->totalItems) {
			$result = $this->getDatabase()->exec_SELECT_queryArray($queryParts);
			// Will return FALSE, if $result is invalid
			$dbCount = $this->getDatabase()->sql_num_rows($result);
		}
		// If records were found, render the list
		if (!$dbCount) {
			return '';
		}
		// Set fields
		$out = '';
		$this->fieldArray = GeneralUtility::trimExplode(',', '__cmds__,' . $fList . ',__editIconLink__', TRUE);
		$theData = array();
		$theData = $this->headerFields($this->fieldArray, $table, $theData);
		// Title row
		$localizedTableTitle = $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title'], TRUE);
		$out .= '<tr class="t3-row-header">' . '<th class="col-icon"></th>'
			. '<th colspan="' . (count($theData) - 2) . '"><span class="c-table">'
			. $localizedTableTitle . '</span> (' . $dbCount . ')</td>' . '<td class="col-icon"></td>'
			. '</tr>';
		// Column's titles
		if ($this->doEdit) {
			$onClick = BackendUtility::editOnClick('&edit[' . $table . '][' . $this->id . ']=new');
			$theData['__cmds__'] = '<a href="#" onclick="' . htmlspecialchars($onClick) . '" '
				. 'title="' . $this->getLanguageService()->getLL('new', TRUE) . '">'
				. IconUtility::getSpriteIcon('actions-document-new') . '</a>';
		}
		$out .= $this->addelement(1, '', $theData, ' class="c-headLine"', 15, '', 'th');
		// Render Items
		$this->eCounter = $this->firstElementNumber;
		while ($row = $this->getDatabase()->sql_fetch_assoc($result)) {
			BackendUtility::workspaceOL($table, $row);
			if (is_array($row)) {
				list($flag, $code) = $this->fwd_rwd_nav();
				$out .= $code;
				if ($flag) {
					$params = '&edit[' . $table . '][' . $row['uid'] . ']=edit';
					$Nrow = array();
					// Setting icons links
					if ($icon) {
						$Nrow['__cmds__'] = $this->getIcon($table, $row);
					}
					// Get values:
					$Nrow = $this->dataFields($this->fieldArray, $table, $row, $Nrow);
					// Attach edit icon
					if ($this->doEdit) {
						$Nrow['__editIconLink__'] = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params))
							. '" title="' . $this->getLanguageService()->getLL('edit', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-document-open') . '</a>';
					} else {
						$Nrow['__editIconLink__'] = $this->noEditIcon();
					}
					$out .= $this->addelement(1, '', $Nrow);
				}
				$this->eCounter++;
			}
		}
		$this->getDatabase()->sql_free_result($result);
		// Wrap it all in a table:
		$out = '
			<!--
				Standard list of table "' . $table . '"
			-->
			<div class="table-fit"><table class="table table-striped">
				' . $out . '
			</table></div>';
		return $out;
	}

	/**
	 * Adds content to all data fields in $out array
	 *
	 * Each field name in $fieldArr has a special feature which is that the field name can be specified as more field names.
	 * Eg. "field1,field2;field3".
	 * Field 2 and 3 will be shown in the same cell of the table separated by <br /> while field1 will have its own cell.
	 *
	 * @param array $fieldArr Array of fields to display
	 * @param string $table Table name
	 * @param array $row Record array
	 * @param array $out Array to which the data is added
	 * @return array $out array returned after processing.
	 * @see makeOrdinaryList()
	 */
	public function dataFields($fieldArr, $table, $row, $out = array()) {
		// Check table validity
		if (!isset($GLOBALS['TCA'][$table])) {
			return $out;
		}

		$thumbsCol = $GLOBALS['TCA'][$table]['ctrl']['thumbnail'];
		// Traverse fields
		foreach ($fieldArr as $fieldName) {
			if ($GLOBALS['TCA'][$table]['columns'][$fieldName]) {
				// Each field has its own cell (if configured in TCA)
				// If the column is a thumbnail column:
				if ($fieldName == $thumbsCol) {
					$out[$fieldName] = $this->thumbCode($row, $table, $fieldName);
				} else {
					// ... otherwise just render the output:
					$out[$fieldName] = nl2br(htmlspecialchars(trim(GeneralUtility::fixed_lgd_cs(
						BackendUtility::getProcessedValue($table, $fieldName, $row[$fieldName], 0, 0, 0, $row['uid']),
						250)
					)));
				}
			} else {
				// Each field is separated by <br /> and shown in the same cell (If not a TCA field, then explode
				// the field name with ";" and check each value there as a TCA configured field)
				$theFields = explode(';', $fieldName);
				// Traverse fields, separated by ";" (displayed in a single cell).
				foreach ($theFields as $fName2) {
					if ($GLOBALS['TCA'][$table]['columns'][$fName2]) {
						$out[$fieldName] .= '<strong>' . $this->getLanguageService()->sL(
								$GLOBALS['TCA'][$table]['columns'][$fName2]['label'],
								TRUE
							) . '</strong>' . '&nbsp;&nbsp;' . htmlspecialchars(GeneralUtility::fixed_lgd_cs(
								BackendUtility::getProcessedValue($table, $fName2, $row[$fName2], 0, 0, 0, $row['uid']),
								25
							)) . '<br />';
					}
				}
			}
			// If no value, add a nbsp.
			if (!$out[$fieldName]) {
				$out[$fieldName] = '&nbsp;';
			}
			// Wrap in dimmed-span tags if record is "disabled"
			if ($this->isDisabled($table, $row)) {
				$out[$fieldName] = '<span class="text-muted">' . $out[$fieldName] . '</span>';
			}
		}
		return $out;
	}

	/**
	 * Header fields made for the listing of records
	 *
	 * @param array $fieldArr Field names
	 * @param string $table The table name
	 * @param array $out Array to which the headers are added.
	 * @return array $out returned after addition of the header fields.
	 * @see makeOrdinaryList()
	 */
	public function headerFields($fieldArr, $table, $out = array()) {
		foreach ($fieldArr as $fieldName) {
			$ll = $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['columns'][$fieldName]['label'], TRUE);
			$out[$fieldName] = $ll ? $ll : '&nbsp;';
		}
		return $out;
	}

	/**
	 * Gets content records per column.
	 * This is required for correct workspace overlays.
	 *
	 * @param string $table UNUSED (will always be queried from tt_content)
	 * @param int $id Page Id to be used (not used at all, but part of the API, see $this->pidSelect)
	 * @param array $columns colPos values to be considered to be shown
	 * @param string $additionalWhereClause Additional where clause for database select
	 * @return array Associative array for each column (colPos)
	 */
	protected function getContentRecordsPerColumn($table, $id, array $columns, $additionalWhereClause = '') {
		$columns = array_map('intval', $columns);
		$contentRecordsPerColumn = array_fill_keys($columns, array());

		$queryParts = $this->makeQueryArray('tt_content', $id, 'AND colPos IN (' . implode(',', $columns) . ')' . $additionalWhereClause);
		$result = $this->getDatabase()->exec_SELECT_queryArray($queryParts);
		// Traverse any selected elements and render their display code:
		$rowArr = $this->getResult($result);

		foreach ($rowArr as $record) {
			$columnValue = $record['colPos'];
			$contentRecordsPerColumn[$columnValue][] = $record;
		}

		return $contentRecordsPerColumn;
	}

	/**********************************
	 *
	 * Additional functions; Pages
	 *
	 **********************************/
	/**
	 * Adds pages-rows to an array, selecting recursively in the page tree.
	 *
	 * @param array $theRows Array which will accumulate page rows
	 * @param int $pid Pid to select from
	 * @param string $qWhere Query-where clause
	 * @param string $treeIcons Prefixed icon code.
	 * @param int $depth Depth (decreasing)
	 * @return array $theRows, but with added rows.
	 */
	public function pages_getTree($theRows, $pid, $qWhere, $treeIcons, $depth) {
		$depth--;
		if ($depth >= 0) {
			$res = $this->getDatabase()->exec_SELECTquery('*', 'pages', 'pid=' . (int)$pid . $qWhere, '', 'sorting');
			$c = 0;
			$rc = $this->getDatabase()->sql_num_rows($res);
			while ($row = $this->getDatabase()->sql_fetch_assoc($res)) {
				BackendUtility::workspaceOL('pages', $row);
				if (is_array($row)) {
					$c++;
					$row['treeIcons'] = $treeIcons . '<span class="treeline-icon treeline-icon-join' . ($rc === $c ? 'bottom' : '') . '"></span>';
					$theRows[] = $row;
					// Get the branch
					$spaceOutIcons = '<span class="treeline-icon treeline-icon-' . ($rc === $c ? 'clear' : 'line') . '"></span>';
					$theRows = $this->pages_getTree($theRows, $row['uid'], $qWhere, $treeIcons . $spaceOutIcons, $row['php_tree_stop'] ? 0 : $depth);
				}
			}
		} else {
			$count = $this->getDatabase()->exec_SELECTcountRows('uid', 'pages', 'pid=' . (int)$pid . $qWhere);
			if ($count) {
				$this->plusPages[$pid] = $count;
			}
		}
		return $theRows;
	}

	/**
	 * Adds a list item for the pages-rendering
	 *
	 * @param array $row Record array
	 * @param array $fieldArr Field list
	 * @return string HTML for the item
	 */
	public function pages_drawItem($row, $fieldArr) {
		// Initialization
		$theIcon = $this->getIcon('pages', $row);
		// Preparing and getting the data-array
		$theData = array();
		foreach ($fieldArr as $field) {
			switch ($field) {
				case 'title':
					$red = $this->plusPages[$row['uid']] ? '<span class="text-danger"><strong>+</strong></span>' : '';
					$pTitle = htmlspecialchars(BackendUtility::getProcessedValue('pages', $field, $row[$field], 20));
					if ($red) {
						$pTitle = '<a href="'
							. htmlspecialchars($this->script . ((strpos($this->script, '?') !== FALSE) ? '&' : '?')
							. 'id=' . $row['uid']) . '">' . $pTitle . '</a>';
					}
					$theData[$field] = $row['treeIcons'] . $theIcon . $red . $pTitle . '&nbsp;&nbsp;';
					break;
				case 'php_tree_stop':
					// Intended fall through
				case 'TSconfig':
					$theData[$field] = $row[$field] ? '&nbsp;<strong>x</strong>' : '&nbsp;';
					break;
				case 'uid':
					if ($this->getBackendUser()->doesUserHaveAccess($row, 2)) {
						$params = '&edit[pages][' . $row['uid'] . ']=edit';
						$eI = '<a href="#" onclick="'
							. htmlspecialchars(BackendUtility::editOnClick($params))
							. '" title="' . $this->getLanguageService()->getLL('editThisPage', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-document-open') . '</a>';
					} else {
						$eI = '';
					}
					$theData[$field] = '<span align="right">' . $row['uid'] . $eI . '</span>';
					break;
				case 'shortcut':
				case 'shortcut_mode':
					if ((int)$row['doktype'] === \TYPO3\CMS\Frontend\Page\PageRepository::DOKTYPE_SHORTCUT) {
						$theData[$field] = $this->getPagesTableFieldValue($field, $row);
					}
					break;
				default:
					if (substr($field, 0, 6) == 'table_') {
						$f2 = substr($field, 6);
						if ($GLOBALS['TCA'][$f2]) {
							$c = $this->numberOfRecords($f2, $row['uid']);
							$theData[$field] = '&nbsp;&nbsp;' . ($c ? $c : '');
						}
					} else {
						$theData[$field] = $this->getPagesTableFieldValue($field, $row);
					}
			}
		}
		$this->addElement_tdParams['title'] = $row['_CSSCLASS'] ? ' class="' . $row['_CSSCLASS'] . '"' : '';
		return $this->addelement(1, '', $theData);
	}

	/**
	 * Returns the HTML code for rendering a field in the pages table.
	 * The row value is processed to a human readable form and the result is parsed through htmlspecialchars().
	 *
	 * @param string $field The name of the field of which the value should be rendered.
	 * @param array $row The pages table row as an associative array.
	 * @return string The rendered table field value.
	 */
	protected function getPagesTableFieldValue($field, array $row) {
		return '&nbsp;&nbsp;' . htmlspecialchars(BackendUtility::getProcessedValue('pages', $field, $row[$field]));
	}

	/**********************************
	 *
	 * Additional functions; Content Elements
	 *
	 **********************************/
	/**
	 * Draw header for a content element column:
	 *
	 * @param string $colName Column name
	 * @param string $editParams Edit params (Syntax: &edit[...] for FormEngine)
	 * @param string $newParams New element params (Syntax: &edit[...] for FormEngine) OBSOLETE
	 * @param array|NULL $pasteParams Paste element params (i.e. array(colPos => 1, sys_language_uid => 2))
	 * @return string HTML table
	 */
	public function tt_content_drawColHeader($colName, $editParams, $newParams, array $pasteParams = NULL) {
		$iconsArr = array();
		// Create command links:
		if ($this->tt_contentConfig['showCommands']) {
			// Edit whole of column:
			if ($editParams) {
				$iconsArr['edit'] = '<a href="#" onclick="'
					. htmlspecialchars(BackendUtility::editOnClick($editParams)) . '" title="'
					. $this->getLanguageService()->getLL('editColumn', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-document-open') . '</a>';
			}
			if ($pasteParams) {
				$elFromTable = $this->clipboard->elFromTable('tt_content');
				if (!empty($elFromTable)) {
					$iconsArr['paste'] = '<a href="'
						. htmlspecialchars($this->clipboard->pasteUrl('tt_content', $this->id, TRUE, $pasteParams))
						. '" onclick="' . htmlspecialchars(('return '
						. $this->clipboard->confirmMsg('pages', $this->pageRecord, 'into', $elFromTable, $colName)))
						. '" title="' . $this->getLanguageService()->getLL('clip_paste', TRUE) . '">'
						. IconUtility::getSpriteIcon('actions-document-paste-into') . '</a>';
				}
			}
		}
		$icons = '';
		if (!empty($iconsArr)) {
			$icons = '<div class="t3-page-column-header-icons">' . implode('', $iconsArr) . '</div>';
		}
		// Create header row:
		$out = '<div class="t3-page-column-header">
					' . $icons . '
					<div class="t3-page-column-header-label">' . htmlspecialchars($colName) . '</div>
				</div>';
		return $out;
	}

	/**
	 * Draw the footer for a single tt_content element
	 *
	 * @param array $row Record array
	 * @return string HTML of the footer
	 * @throws \UnexpectedValueException
	 */
	protected function tt_content_drawFooter(array $row) {
		$content = '';
		// Get processed values:
		$info = array();
		$this->getProcessedValue('tt_content', 'starttime,endtime,fe_group,spaceBefore,spaceAfter', $row, $info);

		// Content element annotation
		if (!empty($GLOBALS['TCA']['tt_content']['ctrl']['descriptionColumn'])) {
			$info[] = htmlspecialchars($row[$GLOBALS['TCA']['tt_content']['ctrl']['descriptionColumn']]);
		}

			// Call drawFooter hooks
		$drawFooterHooks = &$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawFooter'];
		if (is_array($drawFooterHooks)) {
			foreach ($drawFooterHooks as $hookClass) {
				$hookObject = GeneralUtility::getUserObj($hookClass);
				if (!$hookObject instanceof PageLayoutViewDrawFooterHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface TYPO3\\CMS\\Backend\\View\\PageLayoutViewDrawFooterHookInterface', 1404378171);
				}
				$hookObject->preProcess($this, $info, $row);
			}
		}

		// Display info from records fields:
		if (!empty($info)) {
			$content = '<div class="t3-page-ce-info">
				' . implode('<br>', $info) . '
				</div>';
		}
		// Wrap it
		if (!empty($content)) {
			$content = '<div class="t3-page-ce-footer">' . $content . '</div>';
		}
		return $content;
	}

	/**
	 * Draw the header for a single tt_content element
	 *
	 * @param array $row Record array
	 * @param int $space Amount of pixel space above the header. UNUSED
	 * @param bool $disableMoveAndNewButtons If set the buttons for creating new elements and moving up and down are not shown.
	 * @param bool $langMode If set, we are in language mode and flags will be shown for languages
	 * @param bool $dragDropEnabled If set the move button must be hidden
	 * @return string HTML table with the record header.
	 */
	public function tt_content_drawHeader($row, $space = 0, $disableMoveAndNewButtons = FALSE, $langMode = FALSE, $dragDropEnabled = FALSE) {
		$out = '';
		// If show info is set...;
		if ($this->tt_contentConfig['showInfo'] && $this->getBackendUser()->recordEditAccessInternals('tt_content', $row)) {
			// Render control panel for the element:
			if ($this->tt_contentConfig['showCommands'] && $this->doEdit) {
				// Edit content element:
				$params = '&edit[tt_content][' . $this->tt_contentData['nextThree'][$row['uid']] . ']=edit';
				$out .= '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick(
						$params,
						'',
						GeneralUtility::getIndpEnv('REQUEST_URI') . '#element-tt_content-' . $row['uid']
					)) . '" title="' . htmlspecialchars($this->nextThree > 1
						? sprintf($this->getLanguageService()->getLL('nextThree'), $this->nextThree)
						: $this->getLanguageService()->getLL('edit'))
					. '">' . IconUtility::getSpriteIcon('actions-document-open') . '</a>';
				// Hide element:
				$hiddenField = $GLOBALS['TCA']['tt_content']['ctrl']['enablecolumns']['disabled'];
				if (
					$hiddenField && $GLOBALS['TCA']['tt_content']['columns'][$hiddenField]
					&& (!$GLOBALS['TCA']['tt_content']['columns'][$hiddenField]['exclude']
						|| $this->getBackendUser()->check('non_exclude_fields', 'tt_content:' . $hiddenField))
				) {
					if ($row[$hiddenField]) {
						$value = 0;
						$label = 'unHide';
					} else {
						$value = 1;
						$label = 'hide';
					}
					$params = '&data[tt_content][' . ($row['_ORIG_uid'] ? $row['_ORIG_uid'] : $row['uid'])
						. '][' . $hiddenField . ']=' . $value;
					$out .= '<a class="btn btn-default" href="' . htmlspecialchars($this->getPageLayoutController()->doc->issueCommand($params))
						. '" title="' . $this->getLanguageService()->getLL($label, TRUE) . '">'
						. IconUtility::getSpriteIcon('actions-edit-' . strtolower($label)) . '</a>';
				}
				// Delete
				$params = '&cmd[tt_content][' . $row['uid'] . '][delete]=1';
				$confirm = $this->getLanguageService()->getLL('deleteWarning')
					. BackendUtility::translationCount('tt_content', $row['uid'], (' '
					. $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.translationsOfRecord'))
				);
				$out .= '<a class="btn btn-default t3js-modal-trigger" href="' . htmlspecialchars($this->getPageLayoutController()->doc->issueCommand($params)) . '"'
					. ' data-severity="warning"'
					. ' data-title="' . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/locallang_alt_doc.xlf:label.confirm.delete_record.title')) . '"'
					. ' data-content="' . htmlspecialchars($confirm) . '" '
					. ' data-button-close-text="' . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:lang/locallang_common.xlf:cancel')) . '"'
					. ' title="' . $this->getLanguageService()->getLL('deleteItem', TRUE) . '">'
					. IconUtility::getSpriteIcon('actions-edit-delete') . '</a>';
				if ($out) {
					$out = '<div class="btn-group btn-group-sm" role="group">' . $out . '</div>';
				}
				if (!$disableMoveAndNewButtons) {
					$moveButtonContent = '';
					$displayMoveButtons = FALSE;
					// Move element up:
					if ($this->tt_contentData['prev'][$row['uid']]) {
						$params = '&cmd[tt_content][' . $row['uid'] . '][move]=' . $this->tt_contentData['prev'][$row['uid']];
						$moveButtonContent .= '<a class="btn btn-default" href="'
							. htmlspecialchars($this->getPageLayoutController()->doc->issueCommand($params))
							. '" title="' . $this->getLanguageService()->getLL('moveUp', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-move-up') . '</a>';
						if (!$dragDropEnabled) {
							$displayMoveButtons = TRUE;
						}
					} else {
						$moveButtonContent .= '<span class="btn btn-default disabled">' . IconUtility::getSpriteIcon('empty-empty') . '</span>';
					}
					// Move element down:
					if ($this->tt_contentData['next'][$row['uid']]) {
						$params = '&cmd[tt_content][' . $row['uid'] . '][move]= ' . $this->tt_contentData['next'][$row['uid']];
						$moveButtonContent .= '<a class="btn btn-default" href="'
							. htmlspecialchars($this->getPageLayoutController()->doc->issueCommand($params))
							. '" title="' . $this->getLanguageService()->getLL('moveDown', TRUE) . '">'
							. IconUtility::getSpriteIcon('actions-move-down') . '</a>';
						if (!$dragDropEnabled) {
							$displayMoveButtons = TRUE;
						}
					} else {
						$moveButtonContent .= '<span class="btn btn-default disabled">' . IconUtility::getSpriteIcon('empty-empty') . '</span>';
					}
					if ($displayMoveButtons) {
						$out .= '<div class="btn-group btn-group-sm" role="group">' . $moveButtonContent . '</div>';
					}
				}
			}
		}
		$additionalIcons = array();
		$additionalIcons[] = $this->getIcon('tt_content', $row) . ' ';
		$additionalIcons[] = $langMode ? $this->languageFlag($row['sys_language_uid'], FALSE) : '';
		// Get record locking status:
		if ($lockInfo = BackendUtility::isRecordLocked('tt_content', $row['uid'])) {
			$additionalIcons[] = '<a href="#" onclick="alert(' . GeneralUtility::quoteJSvalue($lockInfo['msg'])
				. ');return false;" title="' . htmlspecialchars($lockInfo['msg']) . '">'
				. IconUtility::getSpriteIcon('status-warning-in-use') . '</a>';
		}
		// Call stats information hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['recStatInfoHooks'])) {
			$_params = array('tt_content', $row['uid'], &$row);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['recStatInfoHooks'] as $_funcRef) {
				$additionalIcons[] = GeneralUtility::callUserFunction($_funcRef, $_params, $this);
			}
		}
		// Wrap the whole header
		// NOTE: end-tag for <div class="t3-page-ce-body"> is in getTable_tt_content()
		return '<div class="t3-page-ce-header ' . ($this->getBackendUser()->user['admin'] || (int)$row['editlock'] === 0 ? 't3-page-ce-header-draggable t3js-page-ce-draghandle' : '') . '">
					<div class="t3-page-ce-header-icons-left">' . implode('', $additionalIcons) . '</div>
					<div class="t3-page-ce-header-icons-right">' . ($out ? '<div class="btn-toolbar">' .$out . '</div>' : '') . '</div>
				</div>
				<div class="t3-page-ce-body">';
	}

	/**
	 * Draws the preview content for a content element
	 *
	 * @param array $row Content element
	 * @return string HTML
	 * @throws \UnexpectedValueException
	 */
	public function tt_content_drawItem($row) {
		$out = '';
		$outHeader = '';
		// Make header:

		if ($row['header']) {
			$infoArr = array();
			$this->getProcessedValue('tt_content', 'header_position,header_layout,header_link', $row, $infoArr);
			$hiddenHeaderNote = '';
			// If header layout is set to 'hidden', display an accordant note:
			if ($row['header_layout'] == 100) {
				$hiddenHeaderNote = ' <em>[' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.hidden', TRUE) . ']</em>';
			}
			$outHeader = $row['date']
				? htmlspecialchars($this->itemLabels['date'] . ' ' . BackendUtility::date($row['date'])) . '<br />'
				: '';
			$outHeader .= '<strong>' . $this->linkEditContent($this->renderText($row['header']), $row)
				. $hiddenHeaderNote . '</strong><br />';
		}
		// Make content:
		$infoArr = array();
		$drawItem = TRUE;
		// Hook: Render an own preview of a record
		$drawItemHooks = &$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem'];
		if (is_array($drawItemHooks)) {
			foreach ($drawItemHooks as $hookClass) {
				$hookObject = GeneralUtility::getUserObj($hookClass);
				if (!$hookObject instanceof PageLayoutViewDrawItemHookInterface) {
					throw new \UnexpectedValueException('$hookObject must implement interface ' . \TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface::class, 1218547409);
				}
				$hookObject->preProcess($this, $drawItem, $outHeader, $out, $row);
			}
		}
		// Draw preview of the item depending on its CType (if not disabled by previous hook):
		if ($drawItem) {
			switch ($row['CType']) {
				case 'header':
					if ($row['subheader']) {
						$out .= $this->linkEditContent($this->renderText($row['subheader']), $row) . '<br />';
					}
					break;
				case 'bullets':
				case 'table':
					if ($row['bodytext']) {
						$out .= $this->linkEditContent($this->renderText($row['bodytext']), $row) . '<br />';
					}
					break;
				case 'uploads':
					if ($row['media']) {
						$out .= $this->linkEditContent($this->getThumbCodeUnlinked($row, 'tt_content', 'media'), $row) . '<br />';
					}
					break;
				case 'menu':
					$contentType = $this->CType_labels[$row['CType']];
					$out .= $this->linkEditContent('<strong>' . htmlspecialchars($contentType) . '</strong>', $row) . '<br />';
					// Add Menu Type
					$menuTypeLabel = $this->getLanguageService()->sL(
						BackendUtility::getLabelFromItemListMerged($row['pid'], 'tt_content', 'menu_type', $row['menu_type'])
					);
					$menuTypeLabel = $menuTypeLabel ?: 'invalid menu type';
					$out .= $this->linkEditContent($menuTypeLabel, $row);
					if ($row['menu_type'] !== '2' && ($row['pages'] || $row['selected_categories'])) {
						// Show pages if menu type is not "Sitemap"
						$out .= ':' . $this->linkEditContent($this->generateListForCTypeMenu($row), $row) . '<br />';
					}
					break;
				case 'shortcut':
					if (!empty($row['records'])) {
						$shortcutContent = array();
						$recordList = explode(',', $row['records']);
						foreach ($recordList as $recordIdentifier) {
							$split = BackendUtility::splitTable_Uid($recordIdentifier);
							$tableName = empty($split[0]) ? 'tt_content' : $split[0];
							$shortcutRecord = BackendUtility::getRecord($tableName, $split[1]);
							if (is_array($shortcutRecord)) {
								$icon = IconUtility::getSpriteIconForRecord($tableName, $shortcutRecord);
								$icon = $this->getPageLayoutController()->doc->wrapClickMenuOnIcon($icon, $tableName,
									$shortcutRecord['uid'], 1, '', '+copy,info,edit,view');
								$shortcutContent[] = $icon
									. htmlspecialchars(BackendUtility::getRecordTitle($tableName, $shortcutRecord));
							}
						}
						$out .= implode('<br />', $shortcutContent) . '<br />';
					}
					break;
				case 'list':
					$hookArr = array();
					$hookOut = '';
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][$row['list_type']])) {
						$hookArr = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][$row['list_type']];
					} elseif (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['_DEFAULT'])) {
						$hookArr = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['_DEFAULT'];
					}
					if (!empty($hookArr)) {
						$_params = array('pObj' => &$this, 'row' => $row, 'infoArr' => $infoArr);
						foreach ($hookArr as $_funcRef) {
							$hookOut .= GeneralUtility::callUserFunction($_funcRef, $_params, $this);
						}
					}
					if ((string)$hookOut !== '') {
						$out .= $hookOut;
					} elseif (!empty($row['list_type'])) {
						$label = BackendUtility::getLabelFromItemListMerged($row['pid'], 'tt_content', 'list_type', $row['list_type']);
						if (!empty($label)) {
							$out .=  $this->linkEditContent('<strong>' . $this->getLanguageService()->sL($label, TRUE) . '</strong>', $row) . '<br />';
						} else {
							$message = sprintf($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.noMatchingValue'), $row['list_type']);
							$out .= GeneralUtility::makeInstance(
								FlashMessage::class,
								htmlspecialchars($message),
								'',
								FlashMessage::WARNING
							)->render();
						}
					} elseif (!empty($row['select_key'])) {
						$out .= $this->getLanguageService()->sL(BackendUtility::getItemLabel('tt_content', 'select_key'), TRUE)
							. ' ' . $row['select_key'] . '<br />';
					} else {
						$out .= '<strong>' . $this->getLanguageService()->getLL('noPluginSelected') . '</strong>';
					}
					$out .= $this->getLanguageService()->sL(
							BackendUtility::getLabelFromItemlist('tt_content', 'pages', $row['pages']),
							TRUE
						) . '<br />';
					break;
				default:
					$contentType = $this->CType_labels[$row['CType']];

					if (isset($contentType)) {
						$out .= $this->linkEditContent('<strong>' . htmlspecialchars($contentType) . '</strong>', $row) . '<br />';
						if ($row['bodytext']) {
							$out .= $this->linkEditContent($this->renderText($row['bodytext']), $row) . '<br />';
						}
						if ($row['image']) {
							$out .= $this->linkEditContent($this->getThumbCodeUnlinked($row, 'tt_content', 'image'), $row) . '<br />';
						}
					} else {
						$message = sprintf(
							$this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:labels.noMatchingValue'),
							$row['CType']
						);
						$out .= GeneralUtility::makeInstance(
							FlashMessage::class,
							htmlspecialchars($message),
							'',
							FlashMessage::WARNING
						)->render();
					}
			}
		}
		// Wrap span-tags:
		$out = '
			<span class="exampleContent">' . $out . '</span>';
		// Add header:
		$out = $outHeader . $out;
		// Return values:
		if ($this->isDisabled('tt_content', $row)) {
			return '<span class="text-muted">' . $out . '</span>';
		} else {
			return $out;
		}
	}

	/**
	 * Generates a list of selected pages or categories for the CType menu
	 *
	 * @param array $row row from pages
	 * @return string
	 */
	protected function generateListForCTypeMenu(array $row) {
		$table = 'pages';
		$field = 'pages';
		// get categories instead of pages
		if (strpos($row['menu_type'], 'categorized_') !== FALSE) {
			$table = 'sys_category';
			$field = 'selected_categories';
		}
		if (trim($row[$field]) === '') {
			return '';
		}
		$content = '';
		$uidList = explode(',', $row[$field]);
		foreach ($uidList as $uid) {
			$uid = (int)$uid;
			$record = BackendUtility::getRecord($table, $uid, 'title');
			$content .= '<br>' . $record['title'] . ' (' .$uid. ')';
		}
		return $content;
	}

	/**
	 * Filters out all tt_content uids which are already translated so only non-translated uids is left.
	 * Selects across columns, but within in the same PID. Columns are expect to be the same
	 * for translations and original but this may be a conceptual error (?)
	 *
	 * @param array $defLanguageCount Numeric array with uids of tt_content elements in the default language
	 * @param int $id Page pid
	 * @param int $lP Sys language UID
	 * @return array Modified $defLanguageCount
	 */
	public function getNonTranslatedTTcontentUids($defLanguageCount, $id, $lP) {
		if ($lP && !empty($defLanguageCount)) {
			// Select all translations here:
			$queryParts = $this->makeQueryArray('tt_content', $id, 'AND sys_language_uid=' . (int)$lP
				. ' AND l18n_parent IN (' . implode(',', $defLanguageCount) . ')');
			$result = $this->getDatabase()->exec_SELECT_queryArray($queryParts);
			// Flip uids:
			$defLanguageCount = array_flip($defLanguageCount);
			// Traverse any selected elements and unset original UID if any:
			$rowArr = $this->getResult($result);
			foreach ($rowArr as $row) {
				unset($defLanguageCount[$row['l18n_parent']]);
			}
			// Flip again:
			$defLanguageCount = array_keys($defLanguageCount);
		}
		return $defLanguageCount;
	}

	/**
	 * Creates button which is used to create copies of records..
	 *
	 * @param array $defLanguageCount Numeric array with uids of tt_content elements in the default language
	 * @param int $lP Sys language UID
	 * @param int $colPos Column position
	 * @return string "Copy languages" button, if available.
	 */
	public function newLanguageButton($defLanguageCount, $lP, $colPos = 0) {
		if (!$this->doEdit || !$lP) {
			return '';
		}

		$copyFromLanguageMenu = '';
		foreach ($this->getLanguagesToCopyFrom(GeneralUtility::_GP('id'), $lP, $colPos) as $languageId => $label) {
			$elementsInColumn = $languageId === 0 ? $defLanguageCount : $this->getPageLayoutController()->getElementsFromColumnAndLanguage(GeneralUtility::_GP('id'), $colPos, $languageId);
			if (!empty($elementsInColumn)) {
				$onClick = 'window.location.href=' . GeneralUtility::quoteJSvalue($this->getPageLayoutController()->doc->issueCommand('&cmd[tt_content][' . implode(',', $elementsInColumn) . '][copyFromLanguage]=' . GeneralUtility::_GP('id') . ',' . $lP)) . '; return false;';
				$copyFromLanguageMenu .= '<li><a href="#" onclick="' . htmlspecialchars($onClick) . '">' . $this->languageFlag($languageId, FALSE) . ' ' . htmlspecialchars($label) . '</a></li>' . LF;
			}
		}
		if ($copyFromLanguageMenu !== '') {
			$copyFromLanguageMenu =
				'<ul class="dropdown-menu">'
					. $copyFromLanguageMenu
				. '</ul>';
		}

		if (!empty($defLanguageCount)) {
			$params = '';
			foreach ($defLanguageCount as $uidVal) {
				$params .= '&cmd[tt_content][' . $uidVal . '][localize]=' . $lP;
			}

			// We have content in the default language, create a split button
			$onClick = 'window.location.href=' . GeneralUtility::quoteJSvalue($this->getPageLayoutController()->doc->issueCommand($params)) . '; return false;';
			$theNewButton =
				'<div class="btn-group">'
					. $this->getPageLayoutController()->doc->t3Button(
						$onClick,
						$this->getLanguageService()->getLL('newPageContent_copyForLang', TRUE) . ' [' . count($defLanguageCount) . ']'
					);
			if ($copyFromLanguageMenu !== '' && $this->getPageLayoutController()->isColumnEmpty($colPos, $lP)) {
				$theNewButton .= '<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
					. '<span class="caret"></span>'
					. '<span class="sr-only">Toggle Dropdown</span>'
					. '</button>'
					. $copyFromLanguageMenu;
			}
			$theNewButton .= '</div>';
		} else {
			if ($copyFromLanguageMenu !== '' && $this->getPageLayoutController()->isColumnEmpty($colPos, $lP)) {
				$theNewButton =
					'<div class="btn-group">'
					. '<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
					. $this->getLanguageService()->getLL('newPageContent_copyFromAnotherLang_button', TRUE) . ' <span class="caret"></span>'
					. '</button>'
					. $copyFromLanguageMenu
					. '</div>';
			} else {
				$theNewButton = '';
			}
		}

		return '<div class="t3-page-lang-copyce">' . $theNewButton . '</div>';
	}

	/**
	 * Creates onclick-attribute content for a new content element
	 *
	 * @param int $id Page id where to create the element.
	 * @param int $colPos Preset: Column position value
	 * @param int $sys_language Preset: Sys langauge value
	 * @return string String for onclick attribute.
	 * @see getTable_tt_content()
	 */
	public function newContentElementOnClick($id, $colPos, $sys_language) {
		if ($this->option_newWizard) {
			$onClick = 'window.location.href=' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('new_content_element') . '&id=' . $id . '&colPos=' . $colPos
				. '&sys_language_uid=' . $sys_language . '&uid_pid=' . $id
				. '&returnUrl=' . rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI'))) . ';';
		} else {
			$onClick = BackendUtility::editOnClick('&edit[tt_content][' . $id . ']=new&defVals[tt_content][colPos]='
				. $colPos . '&defVals[tt_content][sys_language_uid]=' . $sys_language);
		}
		return $onClick;
	}

	/**
	 * Will create a link on the input string and possibly a big button after the string which links to editing in the RTE.
	 * Used for content element content displayed so the user can click the content / "Edit in Rich Text Editor" button
	 *
	 * @param string $str String to link. Must be prepared for HTML output.
	 * @param array $row The row.
	 * @return string If the whole thing was editable ($this->doEdit) $str is return with link around. Otherwise just $str.
	 * @see getTable_tt_content()
	 */
	public function linkEditContent($str, $row) {
		$addButton = '';
		$onClick = '';
		if ($this->doEdit && $this->getBackendUser()->recordEditAccessInternals('tt_content', $row)) {
			// Setting onclick action for content link:
			$onClick = BackendUtility::editOnClick('&edit[tt_content][' . $row['uid'] . ']=edit');
		}
		// Return link
		return $onClick ? '<a href="#" onclick="' . htmlspecialchars($onClick)
			. '" title="' . $this->getLanguageService()->getLL('edit', TRUE) . '">' . $str . '</a>' . $addButton : $str;
	}

	/**
	 * Get available languages for a page
	 *
	 * @param int $pageId
	 * @return array
	 */
	protected function getAvailableLanguages($pageId) {
		// First, select all
		$res = $this->getPageLayoutController()->exec_languageQuery(0);
		$langSelItems = array();
		while ($row = $this->getDatabase()->sql_fetch_assoc($res)) {
			if ($this->getBackendUser()->checkLanguageAccess($row['uid'])) {
				$langSelItems[$row['uid']] = $row['title'];
			}
		}
		$this->getDatabase()->sql_free_result($res);

		// Remove disallowed languages
		if (count($langSelItems) > 1
			&& !$this->getBackendUser()->user['admin']
			&& $this->getBackendUser()->groupData['allowed_languages'] !== ''
		) {
			$allowed_languages = array_flip(explode(',', $this->getBackendUser()->groupData['allowed_languages']));
			if (!empty($allowed_languages)) {
				foreach ($langSelItems as $key => $value) {
					if (!isset($allowed_languages[$key]) && $key != 0) {
						unset($langSelItems[$key]);
					}
				}
			}
		}
		// Remove disabled languages
		$modSharedTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.SHARED');
		$disableLanguages = isset($modSharedTSconfig['properties']['disableLanguages'])
			? GeneralUtility::trimExplode(',', $modSharedTSconfig['properties']['disableLanguages'], TRUE)
			: array();
		if (!empty($langSelItems) && !empty($disableLanguages)) {
			foreach ($disableLanguages as $language) {
				if ($language != 0 && isset($langSelItems[$language])) {
					unset($langSelItems[$language]);
				}
			}
		}

		return $langSelItems;
	}

	/**
	 * Get available languages for copying into another language
	 *
	 * @param int $pageId
	 * @param int $excludeLanguage
	 * @param int $colPos
	 * @return array
	 */
	protected function getLanguagesToCopyFrom($pageId, $excludeLanguage = NULL, $colPos = 0) {
		$langSelItems = array();
		if (!$this->getPageLayoutController()->isColumnEmpty($colPos, 0)) {
			$langSelItems[0] = $this->getLanguageService()->getLL('newPageContent_translateFromDefault', TRUE);
		}

		$languages = $this->getPageLayoutController()->getUsedLanguagesInPageAndColumn($pageId, $colPos);
		foreach ($languages as $uid => $language) {
			$langSelItems[$uid] = sprintf($this->getLanguageService()->getLL('newPageContent_copyFromAnotherLang'), htmlspecialchars($language['title']));
		}

		if (isset($langSelItems[$excludeLanguage])) {
			unset($langSelItems[$excludeLanguage]);
		}

		return $langSelItems;
	}

	/**
	 * Make selector box for creating new translation in a language
	 * Displays only languages which are not yet present for the current page and
	 * that are not disabled with page TS.
	 *
	 * @param int $id Page id for which to create a new language (pages_language_overlay record)
	 * @return string <select> HTML element (if there were items for the box anyways...)
	 * @see getTable_tt_content()
	 */
	public function languageSelector($id) {
		if ($this->getBackendUser()->check('tables_modify', 'pages_language_overlay')) {
			// First, select all
			$res = $this->getPageLayoutController()->exec_languageQuery(0);
			$langSelItems = array();
			$langSelItems[0] = '
						<option value="0"></option>';
			while ($row = $this->getDatabase()->sql_fetch_assoc($res)) {
				if ($this->getBackendUser()->checkLanguageAccess($row['uid'])) {
					$langSelItems[$row['uid']] = '
							<option value="' . $row['uid'] . '">' . htmlspecialchars($row['title']) . '</option>';
				}
			}
			// Then, subtract the languages which are already on the page:
			$res = $this->getPageLayoutController()->exec_languageQuery($id);
			while ($row = $this->getDatabase()->sql_fetch_assoc($res)) {
				unset($langSelItems[$row['uid']]);
			}
			// Remove disallowed languages
			if (count($langSelItems) > 1
				&& !$this->getBackendUser()->user['admin']
				&& $this->getBackendUser()->groupData['allowed_languages'] !== ''
			) {
				$allowed_languages = array_flip(explode(',', $this->getBackendUser()->groupData['allowed_languages']));
				if (!empty($allowed_languages)) {
					foreach ($langSelItems as $key => $value) {
						if (!isset($allowed_languages[$key]) && $key != 0) {
							unset($langSelItems[$key]);
						}
					}
				}
			}
			// Remove disabled languages
			$modSharedTSconfig = BackendUtility::getModTSconfig($id, 'mod.SHARED');
			$disableLanguages = isset($modSharedTSconfig['properties']['disableLanguages'])
				? GeneralUtility::trimExplode(',', $modSharedTSconfig['properties']['disableLanguages'], TRUE)
				: array();
			if (!empty($langSelItems) && !empty($disableLanguages)) {
				foreach ($disableLanguages as $language) {
					if ($language != 0 && isset($langSelItems[$language])) {
						unset($langSelItems[$language]);
					}
				}
			}
			// If any languages are left, make selector:
			if (count($langSelItems) > 1) {
				$url = BackendUtility::getModuleUrl('record_edit', array(
					'edit[pages_language_overlay]['. $id . ']' => 'new',
					'overrideVals[pages_language_overlay][doktype]' => (int)$this->pageRecord['doktype'],
					'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
				));
				$onChangeContent = 'window.location.href=' . GeneralUtility::quoteJSvalue($url . '&overrideVals[pages_language_overlay][sys_language_uid]=') . '+this.options[this.selectedIndex].value';
				return '<div class="form-inline form-inline-spaced">'
				. '<div class="form-group">'
				. '<label for="createNewLanguage">'
				. $this->getLanguageService()->getLL('new_language', TRUE)
				. '</label>'
				. '<select class="form-control input-sm" name="createNewLanguage" onchange="' . htmlspecialchars($onChangeContent) . '">'
				. implode('', $langSelItems)
				. '</select></div></div>';
			}
		}
		return '';
	}

	/**
	 * Traverse the result pointer given, adding each record to array and setting some internal values at the same time.
	 *
	 * @param bool|\mysqli_result|object $result MySQLi result object / DBAL object
	 * @param string $table Table name defaulting to tt_content
	 * @return array The selected rows returned in this array.
	 */
	public function getResult($result, $table = 'tt_content') {
		$output = array();
		// Traverse the result:
		while ($row = $this->getDatabase()->sql_fetch_assoc($result)) {
			BackendUtility::workspaceOL($table, $row, -99, TRUE);
			if ($row) {
				// Add the row to the array:
				$output[] = $row;
			}
		}
		$this->generateTtContentDataArray($output);
		// Return selected records
		return $output;
	}

	/********************************
	 *
	 * Various helper functions
	 *
	 ********************************/

	/**
	 * Initializes the clipboard for generating paste links
	 *
	 * @return void
	 *
	 * @see \TYPO3\CMS\Recordlist\RecordList::main()
	 * @see \TYPO3\CMS\Backend\Controller\ClickMenuController::main()
	 * @see \TYPO3\CMS\Filelist\Controller\FileListController::main()
	 */
	protected function initializeClipboard() {
		// Start clipboard
		$this->clipboard = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Clipboard\Clipboard::class);

		// Initialize - reads the clipboard content from the user session
		$this->clipboard->initializeClipboard();

		// This locks the clipboard to the Normal for this request.
		$this->clipboard->lockToNormal();

		// Clean up pad
		$this->clipboard->cleanCurrent();

		// Save the clipboard content
		$this->clipboard->endClipboard();
	}

	/**
	 * Generates the data for previous and next elements which is needed for movements.
	 *
	 * @param array $rowArray
	 * @return void
	 */
	protected function generateTtContentDataArray(array $rowArray) {
		if (empty($this->tt_contentData)) {
			$this->tt_contentData = array(
				'nextThree' => array(),
				'next' => array(),
				'prev' => array(),
			);
		}
		foreach ($rowArray as $key => $value) {
			// Create the list of the next three ids (for editing links...)
			for ($i = 0; $i < $this->nextThree; $i++) {
				if (isset($rowArray[$key - $i])
					&& !GeneralUtility::inList($this->tt_contentData['nextThree'][$rowArray[$key - $i]['uid']], $value['uid'])
				) {
					$this->tt_contentData['nextThree'][$rowArray[$key - $i]['uid']] .= $value['uid'] . ',';
				}
			}

			// Create information for next and previous content elements
			if (isset($rowArray[$key - 1])) {
				if (isset($rowArray[$key - 2])) {
					$this->tt_contentData['prev'][$value['uid']] = -$rowArray[$key - 2]['uid'];
				} else {
					$this->tt_contentData['prev'][$value['uid']] = $value['pid'];
				}
				$this->tt_contentData['next'][$rowArray[$key - 1]['uid']] = -$value['uid'];
			}
		}
	}

	/**
	 * Counts and returns the number of records on the page with $pid
	 *
	 * @param string $table Table name
	 * @param int $pid Page id
	 * @return int Number of records.
	 */
	public function numberOfRecords($table, $pid) {
		$count = 0;
		if ($GLOBALS['TCA'][$table]) {
			$where = 'pid=' . (int)$pid . BackendUtility::deleteClause($table) . BackendUtility::versioningPlaceholderClause($table);
			$count = $this->getDatabase()->exec_SELECTcountRows('uid', $table, $where);
		}
		return (int)$count;
	}

	/**
	 * Processing of larger amounts of text (usually from RTE/bodytext fields) with word wrapping etc.
	 *
	 * @param string $input Input string
	 * @return string Output string
	 */
	public function renderText($input) {
		$input = strip_tags($input);
		$input = GeneralUtility::fixed_lgd_cs($input, 1500);
		return nl2br(htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8', FALSE));
	}

	/**
	 * Creates the icon image tag for record from table and wraps it in a link which will trigger the click menu.
	 *
	 * @param string $table Table name
	 * @param array $row Record array
	 * @return string HTML for the icon
	 */
	public function getIcon($table, $row) {
		// Initialization
		$altText = BackendUtility::getRecordIconAltText($row, $table);
		$icon = IconUtility::getSpriteIconForRecord($table, $row, array('title' => $altText));
		$this->counter++;
		// The icon with link
		if ($this->getBackendUser()->recordEditAccessInternals($table, $row)) {
			$icon = $this->getPageLayoutController()->doc->wrapClickMenuOnIcon($icon, $table, $row['uid']);
		}
		return $icon;
	}

	/**
	 * Creates processed values for all field names in $fieldList based on values from $row array.
	 * The result is 'returned' through $info which is passed as a reference
	 *
	 * @param string $table Table name
	 * @param string $fieldList Comma separated list of fields.
	 * @param array $row Record from which to take values for processing.
	 * @param array $info Array to which the processed values are added.
	 * @return void
	 */
	public function getProcessedValue($table, $fieldList, array $row, array &$info) {
		// Splitting values from $fieldList
		$fieldArr = explode(',', $fieldList);
		// Traverse fields from $fieldList
		foreach ($fieldArr as $field) {
			if ($row[$field]) {
				$info[] = '<strong>' . htmlspecialchars($this->itemLabels[$field]) . '</strong> '
					. htmlspecialchars(BackendUtility::getProcessedValue($table, $field, $row[$field]));
			}
		}
	}

	/**
	 * Returns TRUE, if the record given as parameters is NOT visible based on hidden/starttime/endtime (if available)
	 *
	 * @param string $table Tablename of table to test
	 * @param array $row Record row.
	 * @return bool Returns TRUE, if disabled.
	 */
	public function isDisabled($table, $row) {
		$enableCols = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'];
		return $enableCols['disabled'] && $row[$enableCols['disabled']]
			|| $enableCols['starttime'] && $row[$enableCols['starttime']] > $GLOBALS['EXEC_TIME']
			|| $enableCols['endtime'] && $row[$enableCols['endtime']] && $row[$enableCols['endtime']] < $GLOBALS['EXEC_TIME'];
	}

	/**
	 * Returns icon for "no-edit" of a record.
	 * Basically, the point is to signal that this record could have had an edit link if
	 * the circumstances were right. A placeholder for the regular edit icon...
	 *
	 * @param string $label Label key from LOCAL_LANG
	 * @return string IMG tag for icon.
	 */
	public function noEditIcon($label = 'noEditItems') {
		return IconUtility::getSpriteIcon(
			'status-edit-read-only',
			array('title' => $this->getLanguageService()->getLL($label, TRUE))
		);
	}

	/**
	 * Function, which fills in the internal array, $this->allowedTableNames with all tables to
	 * which the user has access. Also a set of standard tables (pages, static_template, sys_filemounts, etc...)
	 * are filtered out. So what is left is basically all tables which makes sense to list content from.
	 *
	 * @return array
	 */
	protected function cleanTableNames() {
		// Get all table names:
		$tableNames = array_flip(array_keys($GLOBALS['TCA']));
		// Unset common names:
		unset($tableNames['pages']);
		unset($tableNames['static_template']);
		unset($tableNames['sys_filemounts']);
		unset($tableNames['sys_action']);
		unset($tableNames['sys_workflows']);
		unset($tableNames['be_users']);
		unset($tableNames['be_groups']);
		$allowedTableNames = array();
		// Traverse table names and set them in allowedTableNames array IF they can be read-accessed by the user.
		if (is_array($tableNames)) {
			foreach ($tableNames as $k => $v) {
				if (!$GLOBALS['TCA'][$k]['ctrl']['hideTable'] && $this->getBackendUser()->check('tables_select', $k)) {
					$allowedTableNames['table_' . $k] = $k;
				}
			}
		}
		return $allowedTableNames;
	}

	/*****************************************
	 *
	 * External renderings
	 *
	 *****************************************/

	/**
	 * Creates a menu of the tables that can be listed by this function
	 * Only tables which has records on the page will be included.
	 * Notice: The function also fills in the internal variable $this->activeTables with icon/titles.
	 *
	 * @param int $id Page id from which we are listing records (the function will look up if there are records on the page)
	 * @return string HTML output.
	 */
	public function getTableMenu($id) {
		// Initialize:
		$this->activeTables = array();
		$theTables = array('tt_content');
		// External tables:
		if (is_array($this->externalTables)) {
			$theTables = array_unique(array_merge($theTables, array_keys($this->externalTables)));
		}
		$out = '';
		// Traverse tables to check:
		foreach ($theTables as $tName) {
			// Check access and whether the proper extensions are loaded:
			if ($this->getBackendUser()->check('tables_select', $tName)
				&& (isset($this->externalTables[$tName])
					|| GeneralUtility::inList('fe_users,tt_content', $tName)
					|| \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded($tName)
				)
			) {
				// Make query to count records from page:
				$c = $this->getDatabase()->exec_SELECTcountRows('uid', $tName, 'pid=' . (int)$id
					. BackendUtility::deleteClause($tName) . BackendUtility::versioningPlaceholderClause($tName));
				// If records were found (or if "tt_content" is the table...):
				if ($c || GeneralUtility::inList('tt_content', $tName)) {
					// Add row to menu:
					$out .= '
					<td><a href="#' . $tName . '"></a>' . IconUtility::getSpriteIconForRecord(
							$tName,
							array(),
							array('title' => $this->getLanguageService()->sL($GLOBALS['TCA'][$tName]['ctrl']['title'], TRUE))
						) . '</td>';
					// ... and to the internal array, activeTables we also add table icon and title (for use elsewhere)
					$this->activeTables[$tName] = IconUtility::getSpriteIconForRecord(
							$tName,
							array(),
							array('title' => $this->getLanguageService()->sL($GLOBALS['TCA'][$tName]['ctrl']['title'], TRUE)
									. ': ' . $c . ' ' . $this->getLanguageService()->getLL('records', TRUE))
						) . '&nbsp;' . $this->getLanguageService()->sL($GLOBALS['TCA'][$tName]['ctrl']['title'], TRUE);
				}
			}
		}
		// Wrap cells in table tags:
		$out = '
			<!--
				Menu of tables on the page (table menu)
			-->
			<table border="0" cellpadding="0" cellspacing="0" id="typo3-page-tblMenu">
				<tr>' . $out . '
				</tr>
			</table>';
		// Return the content:
		return $out;
	}

	/**
	 * Create thumbnail code for record/field but not linked
	 *
	 * @param mixed[] $row Record array
	 * @param string $table Table (record is from)
	 * @param string $field Field name for which thumbnail are to be rendered.
	 * @return string HTML for thumbnails, if any.
	 */
	public function getThumbCodeUnlinked($row, $table, $field) {
		return BackendUtility::thumbCode($row, $table, $field, $this->backPath, '', NULL, 0, '', '', FALSE);
	}

	/**
	 * @return BackendLayoutView
	 */
	protected function getBackendLayoutView() {
		return GeneralUtility::makeInstance(BackendLayoutView::class);
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return DatabaseConnection
	 */
	protected function getDatabase() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * @return PageLayoutController
	 */
	protected function getPageLayoutController() {
		return $GLOBALS['SOBE'];
	}

}
