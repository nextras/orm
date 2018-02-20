<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;

use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\User;
use NextrasTests\Orm\UserStat;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityCompositePKTest extends DataTestCase
{
	public function testCompositePKDateTime()
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			// An explicit value for the identity column in table 'users' can only be specified when a column list is used and IDENTITY_INSERT is ON.
			// http://stackoverflow.com/questions/2148091/syntax-for-inserting-into-a-table-with-no-values
			Environment::skip('Inserting dummy rows when no arguments are passed is not supported.');
		}

		$user = new User();
		$this->orm->persistAndFlush($user);

		$stat = new UserStat();
		$stat->user = $user;
		$stat->date = 'now';
		$stat->value = 100;
		$this->orm->persistAndFlush($stat);

		$this->orm->clear();

		$this->orm->userStats->findAll()->fetchAll();
		Environment::$checkAssertions = false;
	}
}


$test = new EntityCompositePKTest($dic);
$test->run();
