<?php
namespace TYPO3\CMS\Form;

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

/**
 * Element counter for model
 */
class ElementCounter implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * Counter
	 *
	 * @var int
	 */
	protected $elementCounter = 1;

	/**
	 * Raise the element counter by one
	 *
	 * @return int
	 */
	public function getElementId() {
		$elementId = $this->elementCounter;
		$this->elementCounter++;
		return $elementId;
	}

}
