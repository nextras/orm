<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Mapper\Dbal;


use Nextras\Orm\Mapper\Dbal\RelationshipCacheKeyTrait;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


class RelationshipCacheKeyTraitUser
{
	use RelationshipCacheKeyTrait; // @phpstan-ignore traitUse.internalTrait


	/**
	 * @param list<mixed> $values
	 */
	public function encode(array $values): string
	{
		return $this->encodeCacheKeyValues($values);
	}
}


class RelationshipCacheKeyTraitTest extends TestCase
{
	public function testEncoding(): void
	{
		$user = new RelationshipCacheKeyTraitUser();
		$a = [1, 2, 3];
		$b = [1, 2, 3]; // same content, different array
		$c = [1, 2, 4];
		$d = [1, 2, '3']; // different type

		Assert::same(md5((string) json_encode($a)), $user->encode($a));
		Assert::same(md5((string) json_encode($a)), $user->encode($a));
		Assert::same(md5((string) json_encode($b)), $user->encode($b));
		Assert::same(md5((string) json_encode($c)), $user->encode($c));
		Assert::same(md5((string) json_encode($a)), $user->encode($a));
		Assert::same(md5((string) json_encode($d)), $user->encode($d));
		Assert::same(md5((string) json_encode([])), $user->encode([]));
		Assert::notSame($user->encode($a), $user->encode($c));
		Assert::notSame($user->encode($a), $user->encode($d));
	}
}


$test = new RelationshipCacheKeyTraitTest();
$test->run();
