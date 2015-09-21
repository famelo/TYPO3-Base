<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Scheduler;

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

use TYPO3\CMS\Extbase\Tests\Fixture\DummyController;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Extbase\Mvc\Cli\Command;
use TYPO3\CMS\Extbase\Mvc\Cli\CommandManager;
use TYPO3\CMS\Extbase\Scheduler\FieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Extbase\Tests\MockACommandController;
use TYPO3\CMS\Extbase\Scheduler\Task;

/**
 * Test case
 */
class FieldProviderTest extends UnitTestCase {

	/**
	 * @test
	 */
	public function getCommandControllerActionFieldFetchesCorrectClassNames() {

		/** @var Command|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $command1 */
		$command1 = $this->getAccessibleMock(Command::class, array(), array(), '', FALSE);
		$command1->expects($this->once())->method('isInternal')->will($this->returnValue(FALSE));
		$command1->expects($this->once())->method('getControllerClassName')->will($this->returnValue(MockACommandController::class));
		$command1->expects($this->once())->method('getControllerCommandName')->will($this->returnValue('FuncA'));
		$command1->expects($this->once())->method('getCommandIdentifier')->will($this->returnValue('extbase:mocka:funca'));

		/** @var Command|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $command2 */
		$command2 = $this->getAccessibleMock(Command::class, array(), array(), '', FALSE);
		$command2->expects($this->once())->method('isInternal')->will($this->returnValue(FALSE));
		$command2->expects($this->once())->method('getControllerClassName')->will($this->returnValue('Acme\\Mypkg\\Command\\MockBCommandController'));
		$command2->expects($this->once())->method('getControllerCommandName')->will($this->returnValue('FuncB'));
		$command2->expects($this->once())->method('getCommandIdentifier')->will($this->returnValue('mypkg:mockb:funcb'));

		/** @var Command|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $command3 */
		$command3 = $this->getAccessibleMock(Command::class, array(), array(), '', FALSE);
		$command3->expects($this->once())->method('isInternal')->will($this->returnValue(FALSE));
		$command3->expects($this->once())->method('getControllerClassName')->will($this->returnValue('Tx_Extbase_Command_MockCCommandController'));
		$command3->expects($this->once())->method('getControllerCommandName')->will($this->returnValue('FuncC'));
		$command3->expects($this->once())->method('getCommandIdentifier')->will($this->returnValue('extbase:mockc:funcc'));

		/** @var CommandManager|\PHPUnit_Framework_MockObject_MockObject $commandManager */
		$commandManager = $this->getMock(CommandManager::class, array('getAvailableCommands'));
		$commandManager->expects($this->any())->method('getAvailableCommands')->will($this->returnValue(array($command1, $command2, $command3)));

		/** @var FieldProvider|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $fieldProvider */
		$fieldProvider = $this->getAccessibleMock(
			FieldProvider::class,
			array('getActionLabel'),
			array(),
			'',
			FALSE
		);
		$fieldProvider->_set('commandManager', $commandManager);
		$fieldProvider->expects($this->once())->method('getActionLabel')->will($this->returnValue('some label'));
		$actualResult = $fieldProvider->_call('getCommandControllerActionField', array());
		$this->assertContains('<option title="test" value="extbase:mocka:funca">Extbase MockA: FuncA</option>', $actualResult['code']);
		$this->assertContains('<option title="test" value="mypkg:mockb:funcb">Mypkg MockB: FuncB</option>', $actualResult['code']);
		$this->assertContains('<option title="test" value="extbase:mockc:funcc">Extbase MockC: FuncC</option>', $actualResult['code']);
	}

	/**
	 * @test
	 */
	public function constructResolvesExtensionNameFromNamespaced() {
		$mockController = new DummyController();
		$expectedResult = 'Extbase';
		$actualResult = $mockController->getExtensionName();
		$this->assertSame($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function validateAdditionalFieldsReturnsTrue() {
		/** @var FieldProvider|\PHPUnit_Framework_MockObject_MockObject|\|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $fieldProvider */
		$fieldProvider = $this->getAccessibleMock(
			FieldProvider::class,
			array('dummy'),
			array(),
			'',
			FALSE
		);
		$submittedData = array();
		/** @var SchedulerModuleController $schedulerModule */
		$schedulerModule = $this->getMock(SchedulerModuleController::class, array(), array(), '', FALSE);
		$this->assertTrue($fieldProvider->validateAdditionalFields($submittedData, $schedulerModule));
	}

	/**
	 * @test
	 */
	public function getAdditionalFieldsRendersRightHtml() {
		$this->markTestSkipped('Incomplete mocking in a complex scenario. This should be a functional test');

		/** @var Command|\PHPUnit_Framework_MockObject_MockObject $command1 */
		$command1 = $this->getAccessibleMock(Command::class, array(), array(), '', FALSE);
		$command1->expects($this->once())->method('isInternal')->will($this->returnValue(FALSE));
		$command1->expects($this->once())->method('getControllerClassName')->will($this->returnValue(MockACommandController::class));
		$command1->expects($this->once())->method('getControllerCommandName')->will($this->returnValue('FuncA'));
		$command1->expects($this->any())->method('getCommandIdentifier')->will($this->returnValue('extbase:mocka:funca'));
		$command1->expects($this->once())->method('getArgumentDefinitions')->will($this->returnValue(array()));

		/** @var Command|\PHPUnit_Framework_MockObject_MockObject $command2 */
		$command2 = $this->getAccessibleMock(Command::class, array(), array(), '', FALSE);
		$command2->expects($this->once())->method('isInternal')->will($this->returnValue(FALSE));
		$command2->expects($this->once())->method('getControllerClassName')->will($this->returnValue('Acme\\Mypkg\\Command\\MockBCommandController'));
		$command2->expects($this->once())->method('getControllerCommandName')->will($this->returnValue('FuncB'));
		$command2->expects($this->any())->method('getCommandIdentifier')->will($this->returnValue('mypkg:mockb:funcb'));

		/** @var Command|\PHPUnit_Framework_MockObject_MockObject $command3 */
		$command3 = $this->getAccessibleMock(Command::class, array(), array(), '', FALSE);
		$command3->expects($this->once())->method('isInternal')->will($this->returnValue(FALSE));
		$command3->expects($this->once())->method('getControllerClassName')->will($this->returnValue('Tx_Extbase_Command_MockCCommandController'));
		$command3->expects($this->once())->method('getControllerCommandName')->will($this->returnValue('FuncC'));
		$command3->expects($this->any())->method('getCommandIdentifier')->will($this->returnValue('extbase:mockc:funcc'));

		/** @var CommandManager|\PHPUnit_Framework_MockObject_MockObject $commandManager */
		$commandManager = $this->getMock(CommandManager::class, array('getAvailableCommands'));
		$commandManager->expects($this->any())->method('getAvailableCommands')->will($this->returnValue(array($command1, $command2, $command3)));

		/** @var FieldProvider|\PHPUnit_Framework_MockObject_MockObject|\|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $fieldProvider */
		$fieldProvider = $this->getAccessibleMock(
			FieldProvider::class,
			array('getActionLabel', 'getArgumentLabel', 'getCommandControllerActionArgumentFields'),
			array(),
			'',
			FALSE
		);
		$fieldProvider->_set('commandManager', $commandManager);
		$actionLabel = 'action label string';
		$argumentLabel = 'argument label string';
		$fieldProvider->expects($this->any())->method('getActionLabel')->will($this->returnValue($actionLabel));
		$fieldProvider->expects($this->any())->method('getArgumentLabel')->will($this->returnValue($argumentLabel));
		$argArray['arg'] = array(
				'code' => '<input type="text" name="tx_scheduler[task_extbase][arguments][arg]" value="1" /> ',
				'label' => $argumentLabel
		);
		$fieldProvider->expects($this->any())->method('getCommandControllerActionArgumentFields')->will($this->returnValue($argArray));
		$expectedAdditionalFields = array(
			'action' => array(
				'code' => '<select name="tx_scheduler[task_extbase][action]">' . LF
					. '<option title="test" value="extbase:mocka:funca" selected="selected">Extbase MockA: FuncA</option>' . LF
					. '<option title="test" value="mypkg:mockb:funcb">Mypkg MockB: FuncB</option>' . LF
					. '<option title="test" value="extbase:mockc:funcc">Extbase MockC: FuncC</option>' . LF
					. '</select>',
				'label' => $actionLabel
			),
			'description' => array(
				'code' => '',
				'label' => '<strong></strong>'
			),
			'arg' => array(
				'code' => '<input type="text" name="tx_scheduler[task_extbase][arguments][arg]" value="1" /> ',
				'label' => $argumentLabel
			)
		);

		$taskInfo = array();
		/** @var Task $task */
		$task = new Task();
		$task->setCommandIdentifier($command1->getCommandIdentifier());
		/** @var SchedulerModuleController $schedulerModule */
		$schedulerModule = $this->getMock(SchedulerModuleController::class, array(), array(), '', FALSE);

		$this->assertEquals($expectedAdditionalFields, $fieldProvider->getAdditionalFields($taskInfo, $task, $schedulerModule));
	}

}
