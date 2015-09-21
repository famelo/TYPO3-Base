<?php
namespace TYPO3\CMS\Frontend\Controller;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ControllerInterface;
use TYPO3\CMS\Frontend\Utility\EidUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\AjaxRequestHandler;

/**
 * eID controller for ExtDirect
 */
class ExtDirectEidController implements ControllerInterface {

	/**
	 * Ajax Instance
	 *
	 * @var AjaxRequestHandler
	 */
	protected $ajaxObject = NULL;

	/**
	 * Routes the given eID action to the related ExtDirect method with the necessary
	 * ajax object.
	 *
	 * @param string $ajaxID
	 * @return void
	 */
	protected function routeAction($ajaxID) {
		EidUtility::initLanguage();
		$ajaxScript = $GLOBALS['TYPO3_CONF_VARS']['BE']['AJAX']['ExtDirect::' . $ajaxID]['callbackMethod'];
		$this->ajaxObject = GeneralUtility::makeInstance(AjaxRequestHandler::class, 'ExtDirect::' . $ajaxID);
		$parameters = array();
		GeneralUtility::callUserFunction($ajaxScript, $parameters, $this->ajaxObject, FALSE, TRUE);
	}

	/**
	 * Renders/Echoes the ajax output
	 *
	 * @param ServerRequestInterface $request
	 * @return ResponseInterface|NULL
	 * @throws \InvalidArgumentException
	 */
	public function processRequest(ServerRequestInterface $request) {
		$action = isset($request->getParsedBody()['action'])
			? $request->getParsedBody()['action']
			: isset($request->getQueryParams()['action']) ? $request->getQueryParams()['action'] : '';
		if (!in_array($action, array('route', 'getAPI'), TRUE)) {
			return NULL;
		}
		$this->routeAction($action);
		return $this->ajaxObject->render();
	}

}
