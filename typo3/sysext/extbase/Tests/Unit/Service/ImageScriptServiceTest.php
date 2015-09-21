<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Service;

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

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Test case
 */
class ImageServiceTest extends \TYPO3\CMS\Core\Tests\UnitTestCase {

	/**
	 * @var ImageService
	 */
	protected $subject;

	/**
	 * @var EnvironmentService|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $environmentService;

	/**
	 * Initialize ImageService and environment service mock
	 */
	protected function setUp() {
		$this->subject = new ImageService();
		$this->environmentService = $this->getMock(EnvironmentService::class);
		$this->inject($this->subject, 'environmentService', $this->environmentService);
	}

	/**
	 * @test
	 */
	public function fileIsUnwrappedFromReferenceForProcessing() {
		$reference = $this->getAccessibleMock(FileReference::class, array(), array(), '', FALSE);
		$file = $this->getMock(File::class, array(), array(), '', FALSE);
		$file->expects($this->once())->method('process')->willReturn($this->getMock(ProcessedFile::class, array(), array(), '', FALSE));
		$reference->expects($this->once())->method('getOriginalFile')->willReturn($file);
		$reference->_set('file', $file);

		$this->subject->applyProcessingInstructions($reference, array());
	}

	/**
	 * @return array
	 */
	public function prefixIsCorrectlyAppliedToGetImageUriDataProvider() {
		return array(
			'with scheme' => array('http://foo.bar/img.jpg', 'http://foo.bar/img.jpg'),
			'scheme relative' => array('//foo.bar/img.jpg', '//foo.bar/img.jpg'),
			'without scheme' => array('foo.bar/img.jpg', '/prefix/foo.bar/img.jpg'),
		);
	}

	/**
	 * @test
	 * @dataProvider prefixIsCorrectlyAppliedToGetImageUriDataProvider
	 */
	public function prefixIsCorrectlyAppliedToGetImageUri($imageUri, $expected) {
		$this->environmentService->expects($this->any())->method('isEnvironmentInFrontendMode')->willReturn(TRUE);
		$GLOBALS['TSFE'] = new \stdClass();
		$GLOBALS['TSFE']->absRefPrefix = '/prefix/';

		$file = $this->getMock(File::class, array(), array(), '', FALSE);
		$file->expects($this->once())->method('getPublicUrl')->willReturn($imageUri);

		$this->assertSame($expected, $this->subject->getImageUri($file));
	}

}
