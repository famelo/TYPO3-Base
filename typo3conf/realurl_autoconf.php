<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']=array(
	'_DEFAULT' => array(
		'init' => array(
			'enableCHashCache' => TRUE,
			'appendMissingSlash' => 'ifNotFile,redirect',
			'adminJumpToBackend' => TRUE,
			'enableUrlDecodeCache' => TRUE,
			'enableUrlEncodeCache' => TRUE,
			'emptyUrlReturnValue' => '/',
		),
		'pagePath' => array(
			'type' => 'user',
			'userFunc' => 'Tx\\Realurl\\UriGeneratorAndResolver->main',
			'spaceCharacter' => '-',
			'languageGetVar' => 'L',
			'rootpage_id' => '1',
		),
		'fileName' => array(
			'defaultToHTMLsuffixOnPrev' => 0,
			'acceptHTMLsuffix' => 1,
			'index' => array(
				'print' => array(
					'keyValues' => array(
						'type' => 98,
					),
				),
			),
		),
	),
);
