<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use DateTimeImmutable;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Exception\InvalidArgumentException;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\User;
use NextrasTests\Orm\UserStat;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityCompositePKTest extends DataTestCase
{
	public function testCompositePKDateTime(): void
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			$connection = $this->container->getByType(IConnection::class);
			$connection->query('SET IDENTITY_INSERT users ON;');
		}

		$user = new User();
		$user->id = 1;
		$this->orm->persistAndFlush($user);

		$at = new DateTimeImmutable('2018-09-09 10:09:02');

		$stat = new UserStat();
		$stat->user = $user;
		$stat->date = $at;
		$stat->value = 100;
		$this->orm->persistAndFlush($stat);

		$userId = $user->id;

		$this->orm->clear();

		$userStat = $this->orm->userStats->getBy(['user' => $userId, 'date' => $at]);
		Assert::notNull($userStat);
		Assert::type(DateTimeImmutable::class, $userStat->id[1]);

		$userStat->value = 101;
		$this->orm->persistAndFlush($userStat);
	}


	public function testGetBy(): void
	{
		$tagFollower = $this->orm->tagFollowers->getBy(['tag' => 3, 'author' => 1]);
		Assert::notNull($tagFollower);
		Assert::same($tagFollower->tag->name, 'Tag 3');
		Assert::same($tagFollower->author->name, 'Writer 1');

		$tagFollower = $this->orm->tagFollowers->getBy(['author' => 1, 'tag' => 3]);
		Assert::notNull($tagFollower);
	}


	public function testGetById(): void
	{
		$tagFollower = $this->orm->tagFollowers->getByIdChecked([1, 3]);
		Assert::same($tagFollower->tag->name, 'Tag 3');
		Assert::same($tagFollower->author->name, 'Writer 1');

		$tagFollower = $this->orm->tagFollowers->getById([3, 1]);
		Assert::null($tagFollower);
	}


	public function testGetByIdWronglyUsedWithIndexedKeys(): void
	{
		Assert::throws(function (): void {
			$this->orm->tagFollowers->getById(['author' => 1, 'tag' => 3]);
		}, InvalidArgumentException::class, 'Composite primary value has to be passed as a list, without array keys.');
	}


	public function testSetIdOnlyPartially(): void
	{
		Assert::throws(function (): void {
			$userStat = new UserStat();
			// @phpstan-ignore-next-line
			$userStat->id = 3;
		}, InvalidArgumentException::class, 'Value for NextrasTests\Orm\UserStat::$id has to be passed as array.');
	}


	public function testSetIdWithInsufficientParameters(): void
	{
		Assert::throws(function (): void {
			$userStat = new UserStat();
			$userStat->id = [1];
		}, InvalidArgumentException::class, 'Value for NextrasTests\Orm\UserStat::$id has insufficient number of parameters.');
	}
}


$test = new EntityCompositePKTest();
$test->run();
