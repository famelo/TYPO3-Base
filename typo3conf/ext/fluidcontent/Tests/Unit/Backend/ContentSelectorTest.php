<?php
namespace FluidTYPO3\Fluidcontent\Tests\Unit\Backend;

/*
 * This file is part of the FluidTYPO3/Fluidcontent project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Fluidcontent\Backend\ContentSelector;
use FluidTYPO3\Flux\Form;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ContentSelectorTest
 */
class ContentSelectorTest extends UnitTestCase {

	/**
	 * @return void
	 */
	public function setUp() {
		$GLOBALS['LANG'] = $this->getMock('TYPO3\\CMS\\Lang\\LanguageService', array('sL'));
		$statement = $this->getMock('TYPO3\\CMS\\Core\\Database\\PreparedStatement', array('execute', 'free', 'fetch'), array(), '', FALSE);
		$statement->expects($this->any())->method('execute')->willReturn(FALSE);
		$statement->expects($this->any())->method('fetch')->willReturn(FALSE);
		$statement->expects($this->any())->method('free');
		$GLOBALS['TYPO3_DB'] = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection',
			array('exec_SELECTquery', 'prepare_SELECTquery'), array(), '', FALSE);
		$GLOBALS['TYPO3_DB']->expects($this->any())->method('exec_SELECTquery')->willReturn(FALSE);
		$GLOBALS['TYPO3_DB']->expects($this->any())->method('prepare_SELECTquery')->willReturn($statement);
	}

	/**
	 * @return void
	 */
	public function testCreatesInstance() {
		$instance = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager')
			->get('FluidTYPO3\\Fluidcontent\\Backend\\ContentSelector');
		$this->assertInstanceOf('FluidTYPO3\\Fluidcontent\\Backend\\ContentSelector', $instance);
	}

	/**
	 * @return void
	 */
	public function testRenderFieldCreatesSelectTag() {
		$instance = new ContentSelector();
		$parameters = array(
			'itemFormElName' => 'foobar',
			'itemFormElValue' => 'foovalue'
		);
		$parent = 'unused';
		$rendered = $instance->renderField($parameters, $parent);
		$this->assertStringStartsWith('<div', $rendered);
		$this->assertContains($parameters['itemFormElName'], $rendered);
	}

	/**
	 * @return void
	 */
	public function testRenderFieldCreatesExpectedOptions() {
		$configurationService = $this->getMock(
			'FluidTYPO3\\Fluidcontent\\Service\\ConfigurationService',
			array('getContentElementFormInstances')
		);
		$forms = array(
			'myextension' => array(
				Form::create(array('id' => 'test1', 'label' => 'Test1', 'options' => array('contentElementId' => 'test1'))),
				Form::create(array('id' => 'test2', 'label' => 'Test2', 'options' => array('contentElementId' => 'test2')))
			)
		);
		$parameters = array(
			'itemFormElName' => 'foobar',
			'itemFormElValue' => 'test2'
		);
		$parent = 'unused';
		$configurationService->expects($this->once())->method('getContentElementFormInstances')->willReturn($forms);
		/** @var ContentSelector|\PHPUnit_Framework_MockObject_MockObject $instance */
		$instance = $this->getMock('FluidTYPO3\\Fluidcontent\\Backend\\ContentSelector', array('getConfigurationService'));
		$instance->expects($this->once())->method('getConfigurationService')->willReturn($configurationService);
		$rendered = $instance->renderField($parameters, $parent);
		$this->assertContains('<optgroup label="myextension">', $rendered);
		$this->assertContains('<option', $rendered);
		$this->assertContains('value="test1"', $rendered);
		$this->assertContains('value="test2"', $rendered);
	}

}
