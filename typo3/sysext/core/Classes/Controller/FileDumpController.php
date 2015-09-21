<?php
namespace TYPO3\CMS\Core\Controller;

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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ControllerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\Hook\FileDumpEIDHookInterface;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Class FileDumpController
 */
class FileDumpController implements ControllerInterface {

	/**
	 * @param ServerRequestInterface $request
	 * @return NULL|Response
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \UnexpectedValueException
	 */
	public function processRequest(ServerRequestInterface $request) {
		$parameters = array('eID' => 'dumpFile');
		$t = $this->getGetOrPost($request, 't');
		if ($t) {
			$parameters['t'] = $t;
		}
		$f = $this->getGetOrPost($request, 'f');
		if ($f) {
			$parameters['f'] = $f;
		}
		$p = $this->getGetOrPost($request, 'p');
		if ($p) {
			$parameters['p'] = $p;
		}

		if (GeneralUtility::hmac(implode('|', $parameters), 'resourceStorageDumpFile') === $this->getGetOrPost($request, 'token')) {
			if (isset($parameters['f'])) {
				$file = ResourceFactory::getInstance()->getFileObject($parameters['f']);
				if ($file->isDeleted() || $file->isMissing()) {
					$file = NULL;
				}
			} else {
				$file = GeneralUtility::makeInstance(ProcessedFileRepository::class)->findByUid($parameters['p']);
				if ($file->isDeleted()) {
					$file = NULL;
				}
			}

			if ($file === NULL) {
				HttpUtility::setResponseCodeAndExit(HttpUtility::HTTP_STATUS_404);
			}

			// Hook: allow some other process to do some security/access checks. Hook should issue 403 if access is rejected
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['FileDumpEID.php']['checkFileAccess'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['FileDumpEID.php']['checkFileAccess'] as $classRef) {
					$hookObject = GeneralUtility::getUserObj($classRef);
					if (!$hookObject instanceof FileDumpEIDHookInterface) {
						throw new \UnexpectedValueException('FileDump hook object must implement interface ' . FileDumpEIDHookInterface::class, 1394442417);
					}
					$hookObject->checkFileAccess($file);
				}
			}
			$file->getStorage()->dumpFileContents($file);
			// @todo Refactor FAL to not echo directly, but to implement a stream for output here and use response
			return NULL;
		} else {
			$response = GeneralUtility::makeInstance(Response::class);
			return $response->withStatus(403);
		}
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param string $parameter
	 * @return NULL|mixed
	 */
	protected function getGetOrPost(ServerRequestInterface $request, $parameter) {
		return isset($request->getParsedBody()[$parameter])
			? $request->getParsedBody()[$parameter]
			: isset($request->getQueryParams()[$parameter]) ? $request->getQueryParams()[$parameter] : NULL;
	}

}
