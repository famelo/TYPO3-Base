<?php
namespace TYPO3\CMS\Dbal\Tests\Unit\Database;

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
 * Test case
 */
class DatabaseConnectionPostgresqlTest extends AbstractTestCase {

	/**
	 * @var \TYPO3\CMS\Dbal\Database\DatabaseConnection|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface
	 */
	protected $subject;

	/**
	 * Prepare a DatabaseConnection subject ready to parse mssql queries
	 *
	 * @return void
	 */
	protected function setUp() {
		$configuration = array(
			'handlerCfg' => array(
				'_DEFAULT' => array(
					'type' => 'adodb',
					'config' => array(
						'driver' => 'postgres',
					),
				),
			),
			'mapping' => array(
				'tx_templavoila_tmplobj' => array(
					'mapFieldNames' => array(
						'datastructure' => 'ds',
					),
				),
				'Members' => array(
					'mapFieldNames' => array(
						'pid' => '0',
						'cruser_id' => '1',
						'uid' => 'MemberID',
					),
				),
			),
		);
		$this->subject = $this->prepareSubject('postgres7', $configuration);
	}

	/**
	 * @test
	 */
	public function runningADOdbDriverReturnsTrueWithPostgresForPostgres8DefaultDriverConfiguration() {
		$this->assertTrue($this->subject->runningADOdbDriver('postgres'));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/15492
	 */
	public function limitIsProperlyRemapped() {
		$result = $this->subject->SELECTquery('*', 'be_users', '1=1', '', '', '20');
		$expected = 'SELECT * FROM "be_users" WHERE 1 = 1 LIMIT 20';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/15492
	 */
	public function limitWithSkipIsProperlyRemapped() {
		$result = $this->subject->SELECTquery('*', 'be_users', '1=1', '', '', '20,40');
		$expected = 'SELECT * FROM "be_users" WHERE 1 = 1 LIMIT 40 OFFSET 20';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/23087
	 */
	public function findInSetIsProperlyRemapped() {
		$result = $this->subject->SELECTquery('*', 'fe_users', 'FIND_IN_SET(10, usergroup)');
		$expected = 'SELECT * FROM "fe_users" WHERE FIND_IN_SET(10, CAST("usergroup" AS CHAR)) != 0';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/21514
	 */
	public function likeBinaryOperatorIsRemappedToLike() {
		$result = $this->subject->SELECTquery('*', 'tt_content', 'bodytext LIKE BINARY \'test\'');
		$expected = 'SELECT * FROM "tt_content" WHERE "bodytext" LIKE \'test\'';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/21514
	 */
	public function notLikeBinaryOperatorIsRemappedToNotLike() {
		$result = $this->subject->SELECTquery('*', 'tt_content', 'bodytext NOT LIKE BINARY \'test\'');
		$expected = 'SELECT * FROM "tt_content" WHERE "bodytext" NOT LIKE \'test\'';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/21514
	 */
	public function likeOperatorIsRemappedToIlike() {
		$result = $this->subject->SELECTquery('*', 'tt_content', 'bodytext LIKE \'test\'');
		$expected = 'SELECT * FROM "tt_content" WHERE "bodytext" ILIKE \'test\'';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/21514
	 */
	public function notLikeOperatorIsRemappedToNotIlike() {
		$result = $this->subject->SELECTquery('*', 'tt_content', 'bodytext NOT LIKE \'test\'');
		$expected = 'SELECT * FROM "tt_content" WHERE "bodytext" NOT ILIKE \'test\'';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/32626
	 */
	public function notEqualAnsiOperatorCanBeParsed() {
		$result = $this->subject->SELECTquery('*', 'pages', 'pid<>3');
		$expected = 'SELECT * FROM "pages" WHERE "pid" <> 3';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/67445
	 */
	public function alterTableAddKeyStatementIsRemappedToCreateIndex() {
		$parseString = 'ALTER TABLE sys_collection ADD KEY parent (pid,deleted)';
		$components = $this->subject->SQLparser->_callRef('parseALTERTABLE', $parseString);
		$this->assertInternalType('array', $components);

		$result = $this->subject->SQLparser->_callRef('compileALTERTABLE', $components);
		$expected = array('CREATE INDEX "dd81ee97_parent" ON "sys_collection" ("pid", "deleted")');
		$this->assertSame($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/67445
	 */
	public function canParseAlterTableDropKeyStatement() {
		$parseString = 'ALTER TABLE sys_collection DROP KEY parent';
		$components = $this->subject->SQLparser->_callRef('parseALTERTABLE', $parseString);
		$this->assertInternalType('array', $components);

		$result = $this->subject->SQLparser->_callRef('compileALTERTABLE', $components);
		$expected = array('DROP INDEX "dd81ee97_parent"');
		$this->assertSame($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/43262
	 */
	public function countFieldInOrderByIsInGroupBy() {
		$result = $this->subject->SELECTquery('COUNT(title)', 'pages', '', 'title', 'title');
		$expected = 'SELECT COUNT("title") FROM "pages" GROUP BY "title" ORDER BY "title"';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/43262
	 */
	public function multipleCountFieldsInOrderByAreInGroupBy() {
		$result = $this->subject->SELECTquery('COUNT(title), COUNT(pid)', 'pages', '', 'title, pid', 'title, pid');
		$expected = 'SELECT COUNT("title"), COUNT("pid") FROM "pages" GROUP BY "title", "pid" ORDER BY "title", "pid"';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/43262
	 */
	public function countFieldInOrderByIsNotInGroupBy() {
		$result = $this->subject->SELECTquery('COUNT(title)', 'pages', '', '', 'title');
		$expected = 'SELECT COUNT("title") FROM "pages"';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/43262
	 */
	public function multipleCountFieldsInOrderByAreNotInGroupBy() {
		$result = $this->subject->SELECTquery('COUNT(title), COUNT(pid)', 'pages', '', '', 'title, pid');
		$expected = 'SELECT COUNT("title"), COUNT("pid") FROM "pages"';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @see http://forge.typo3.org/issues/43262
	 */
	public function someCountFieldsInOrderByAreNotInGroupBy() {
		$result = $this->subject->SELECTquery('COUNT(title), COUNT(pid)', 'pages', '', 'title', 'title, pid');
		$expected = 'SELECT COUNT("title"), COUNT("pid") FROM "pages" GROUP BY "title" ORDER BY "title"';
		$this->assertEquals($expected, $this->cleanSql($result));
	}

	/**
	 * @test
	 * @param string $fieldSQL
	 * @param string $expected
	 * @dataProvider equivalentFieldTypeDataProvider
	 * @see http://forge.typo3.org/issues/67301
	 */
	public function suggestEquivalentFieldDefinitions($fieldSQL, $expected) {
		$actual= $this->subject->getEquivalentFieldDefinition($fieldSQL);
		$this->assertSame($expected, $actual);
	}

	/**
	 * @return array
	 */
	public function equivalentFieldTypeDataProvider() {
		return array(
			array('int(11) NOT NULL default \'0\'', 'int(11) NOT NULL default \'0\''),
			array('int(10) NOT NULL', 'int(11) NOT NULL'),
			array('tinyint(3)', 'smallint(6)'),
			array('bigint(20) NOT NULL', 'bigint(20) NOT NULL'),
			array('tinytext NOT NULL', 'varchar(255) NOT NULL default \'\''),
			array('tinytext', 'varchar(255) default NULL'),
			array('mediumtext', 'longtext')
		);
	}
}
