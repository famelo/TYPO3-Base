<?php
namespace TYPO3\CMS\Backend\Controller;

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
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Script Class, putting the frameset together.
 */
class LoginFramesetController {

	/**
	 * @var string
	 */
	protected $content;

	/**
	 * Main function.
	 * Creates the header code and the frameset for the two frames.
	 *
	 * @return void
	 */
	public function main() {
		$title = 'TYPO3 Re-Login (' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . ')';
		$this->getDocumentTemplate()->startPage($title);

		// Create the frameset for the window
		$this->content = $this->getPageRenderer()->render(PageRenderer::PART_HEADER) . '
			<frameset rows="*,1">
				<frame name="login" src="index.php?loginRefresh=1" marginwidth="0" marginheight="0" scrolling="no" noresize="noresize" />
				<frame name="dummy" src="' . htmlspecialchars(BackendUtility::getModuleUrl('dummy')) . '" marginwidth="0" marginheight="0" scrolling="auto" noresize="noresize" />
			</frameset>
		</html>';
	}

	/**
	 * Outputs the page content.
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/**
	 * Returns an instance of DocumentTemplate
	 *
	 * @return \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	protected function getDocumentTemplate() {
		return $GLOBALS['TBE_TEMPLATE'];
	}

	/**
	 * @return PageRenderer
	 */
	protected function getPageRenderer() {
		return GeneralUtility::makeInstance(PageRenderer::class);
	}

}
