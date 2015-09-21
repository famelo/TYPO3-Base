<?php
namespace TYPO3\CMS\Core\Tests\Unit\Utility\Fixtures;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Fixture for javascript minification logging
 */
class GeneralUtilityMinifyJavaScriptFixture extends GeneralUtility {

	/**
	 * Logs message to the development log.
	 *
	 * @param string $errorMessage Message (in english).
	 * @throws \UnexpectedValueException
	 * @throws \RuntimeException
	 */
	static public function devLog($errorMessage, $extKey, $severity = 0, $dataVar = FALSE) {
		if ($errorMessage !== 'Error minifying java script: foo') {
			throw new \UnexpectedValueException('broken');
		}
		throw new \RuntimeException();
	}
}
