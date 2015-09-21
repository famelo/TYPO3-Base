<?php
namespace FluidTYPO3\Vhs\ViewHelpers\Page;

/*
 * This file is part of the FluidTYPO3/Vhs project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ### Page: Static Prefix
 *
 * Use this ViewHelper to read the contents of the `plugin.tx_vhs.settings.prependPath`
 * TypoScript location - this setting stores the static prefix which gets added to all
 * relative resource URIs generated by VHS; whenever you require a ViewHelper which
 * does not respect this setting you can use this ViewHelper to prepend that setting
 * after the value is returned from the other ViewHelper.
 *
 * @author Claus Due <claus@namelesscoder.net>
 * @package Vhs
 * @subpackage ViewHelpers\Page
 */
class StaticPrefixViewHelper extends AbstractViewHelper {

	/**
	 * @return string
	 */
	public function render() {
		if (FALSE === empty($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_vhs.']['settings.']['prependPath'])) {
			return $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_vhs.']['settings.']['prependPath'];
		}
		return '';
	}

}