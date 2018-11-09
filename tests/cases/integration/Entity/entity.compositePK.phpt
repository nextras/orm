<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;

use Nextras\Orm\InvalidArgumentException;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\User;
use NextrasTests\Orm\UserStat;
use NextrasTests\Orm\UserStatX;
use Tester\Assert;
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

	public function testCompositePKDateTime2()
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			// An explicit value for the identity column in table 'users' can only be specified when a column list is used and IDENTITY_INSERT is ON.
			// http://stackoverflow.com/questions/2148091/syntax-for-inserting-into-a-table-with-no-values
			Environment::skip('Inserting dummy rows when no arguments are passed is not supported.');
		}

		$user = new User();
		$this->orm->persistAndFlush($user);

		$stat = new UserStatX();
		$stat->user = $user;
		$stat->date = '2019-01-01';
		$stat->value = 100;
		$this->orm->persistAndFlush($stat);

		$this->orm->clear();

		$res = $this->orm->userStatsX->getBy(['date' => '2019-01-01']);
		Assert::same(100, $res->value);

		$res->value = 200;
		$this->orm->persistAndFlush($res);
		Assert::same(200, $res->value);

		$this->orm->clear();

		$res = $this->orm->userStatsX->getBy(['date' => '2019-01-01']);
		Assert::same(200, $res->value);
		
		Environment::$checkAssertions = false;
	}
	
	

	public function testGetBy()
	{
		$tagFollower = $this->orm->tagFollowers->getBy(['tag' => 3, 'author' => 1]);
		Assert::true($tagFollower !== null);
		Assert::same($tagFollower->tag->name, 'Tag 3');
		Assert::same($tagFollower->author->name, 'Writer 1');

		$tagFollower = $this->orm->tagFollowers->getBy(['author' => 1, 'tag' => 3]);
		Assert::true($tagFollower !== null);
	}


	public function testGetById()
	{
		$tagFollower = $this->orm->tagFollowers->getById([1, 3]);
		Assert::true($tagFollower !== null);
		Assert::same($tagFollower->tag->name, 'Tag 3');
		Assert::same($tagFollower->author->name, 'Writer 1');

		$tagFollower = $this->orm->tagFollowers->getById([3, 1]);
		Assert::null($tagFollower);
	}

	public function testGetByIdWronglyUsedWithIndexedKeys()
	{
		Assert::exception(function () {
			$this->orm->tagFollowers->getById(['author' => 1, 'tag' => 3]);
		}, InvalidArgumentException::class, 'Composite primary value has to be passed as a list, without array keys.');
	}
}


$test = new EntityCompositePKTest($dic);
$test->run();
