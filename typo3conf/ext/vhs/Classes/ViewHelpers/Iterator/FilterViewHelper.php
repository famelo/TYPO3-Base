<?php
namespace FluidTYPO3\Vhs\ViewHelpers\Iterator;

/*
 * This file is part of the FluidTYPO3/Vhs project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ### Iterator: Filter ViewHelper
 *
 * Filters an array by filtering the array, analysing each member
 * and assering if it is equal to (weak type) the `filter` parameter.
 * If `propertyName` is set, the ViewHelper will try to extract this
 * property from each member of the array.
 *
 * Iterators and ObjectStorage etc. are supported.
 *
 * @author Claus Due <claus@namelesscoder.net>
 * @package Vhs
 * @subpackage ViewHelpers\Iterator
 */
class FilterViewHelper extends AbstractViewHelper {

	/**
	 * Render method
	 *
	 * @param mixed $subject The subject iterator/array to be filtered
	 * @param mixed $filter The comparison value
	 * @param string $propertyName Optional property name to extract and use for comparison instead of the object; use on ObjectStorage etc. Note: supports dot-path expressions.
	 * @param boolean $preserveKeys If TRUE, keys in the array are preserved - even if they are numeric
	 * @param boolean $invert Invert the behavior of the view helper
	 * @param boolean $nullFilter If TRUE and $filter is NULL (not set) - to filter NULL or empty values
	 *
	 * @return mixed
	 */
	public function render($subject = NULL, $filter = NULL, $propertyName = NULL, $preserveKeys = FALSE, $invert = FALSE, $nullFilter = FALSE) {
		if (NULL === $subject) {
			$subject = $this->renderChildren();
		}
		if (NULL === $subject || (FALSE === is_array($subject) && FALSE === $subject instanceof \Traversable)) {
			return array();
		}
		if ((FALSE === (boolean) $nullFilter && TRUE === is_null($filter)) || '' === $filter) {
			return $subject;
		}
		if (TRUE === $subject instanceof \Traversable) {
			$subject = iterator_to_array($subject);
		}
		$items = array();
		$invert = (boolean) $invert;
		$invertFlag = TRUE === $invert ? FALSE : TRUE;
		foreach ($subject as $key => $item) {
			if ($invertFlag === $this->filter($item, $filter, $propertyName)) {
				$items[$key] = $item;
			}
		}
		return TRUE === $preserveKeys ? $items : array_values($items);
	}

	/**
	 * Filter an item/value according to desired filter. Returns TRUE if
	 * the item should be included, FALSE otherwise. This default method
	 * simply does a weak comparison (==) for sameness.
	 *
	 * @param mixed $item
	 * @param mixed $filter Could be a single value or an Array. If so the function returns TRUE when $item matches with any value in it.
	 * @param string $propertyName
	 * @return boolean
	 */
	protected function filter($item, $filter, $propertyName) {
		if (FALSE === empty($propertyName) && (TRUE === is_object($item) || TRUE === is_array($item))) {
			$value = ObjectAccess::getPropertyPath($item, $propertyName);
		} else {
			$value = $item;
		}
		return is_array($filter) ? in_array($value, $filter) : ($value == $filter);
	}

}
