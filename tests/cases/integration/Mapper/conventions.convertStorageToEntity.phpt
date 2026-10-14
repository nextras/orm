<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class ConventionsConvertStorageToEntityTest extends DataTestCase
{
	protected function setUp()
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper conventions.');
		}
	}


	public function testConversion(): void
	{
		$conventions = $this->getConventions();
		$row = [
			'id' => 1,
			'author_id' => 2,
			'title' => 'Title',
			'price' => 100, // custom mapping to price->cents
			'price_currency' => 'CZK',
			'orig_price_cents' => null,
			'orig_price_currency' => null,
			'published_at' => null,
			'unknown_column' => 'x',
		];
		$expected = [
			'id' => 1,
			'author' => 2,
			'title' => 'Title',
			'price' => ['cents' => 100, 'currency' => 'CZK'],
			'origPrice' => ['cents' => null, 'currency' => null],
			'publishedAt' => null,
			'unknownColumn' => 'x',
		];

		// repeated conversion uses cached per-column plan
		Assert::same($expected, $conventions->convertStorageToEntity($row));
		Assert::same($expected, $conventions->convertStorageToEntity($row));

		// different column order & subset
		Assert::same(
			['price' => ['currency' => 'EUR', 'cents' => 5], 'id' => 2],
			$conventions->convertStorageToEntity(['price_currency' => 'EUR', 'price' => 5, 'id' => 2]),
		);
	}


	public function testMappingChangeInvalidatesCache(): void
	{
		$conventions = $this->getConventions();
		Assert::same(['title' => 'a'], $conventions->convertStorageToEntity(['title' => 'a']));

		$conventions->setMapping('title', 'title', fn ($value, string $newKey): string => strtoupper($value) . "|$newKey");
		Assert::same(['title' => 'A|title'], $conventions->convertStorageToEntity(['title' => 'a']));

		$conventions->setMapping('titleAlias', 'title');
		Assert::same(['titleAlias' => 'a'], $conventions->convertStorageToEntity(['title' => 'a']));

		$conventions->addMapping('deep->nested->value', 'deep_column', fn ($value) => $value * 2);
		Assert::same(
			['deep' => ['nested' => ['value' => 42]], 'titleAlias' => 'b'],
			$conventions->convertStorageToEntity(['deep_column' => 21, 'title' => 'b']),
		);
	}


	public function testConvertedRowHasNoReferences(): void
	{
		$conventions = $this->getConventions();
		$a = $conventions->convertStorageToEntity(['price' => 1, 'price_currency' => 'CZK', 'id' => 1]);
		$b = $a;
		$b['price']['cents'] = 2;
		Assert::same(1, $a['price']['cents']);
	}


	private function getConventions(): Conventions
	{
		$mapper = $this->orm->books->getMapper();
		assert($mapper instanceof DbalMapper);
		$conventions = $mapper->getConventions();
		assert($conventions instanceof Conventions);
		return $conventions;
	}
}


$test = new ConventionsConvertStorageToEntityTest();
$test->run();
