<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;


use DateTime;
use DateTimeImmutable;
use Nette\Utils\ArrayHash;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int|NULl $id {primary}
 *
 * @property int $test {enum self::TYPE_*}
 * @property string $string
 * @property int $int
 * @property boolean $boolean
 * @property float $float
 * @property DateTimeImmutable $datetimeimmutable
 * @property array<mixed> $array1
 * @property int[] $array2
 * @property object $object
 * @property mixed $mixed
 * @property ArrayHash $type
 * @property bool|NULL $nullable1
 * @property ?\DateTimeImmutable $nullable2
 */
class ValidationTestEntity
{
	const TYPE_ZERO = 0;
	const TYPE_ONE = 1;
	const TYPE_TWO = 2;


	public function __toString()
	{
		return 'hi';
	}
}


class PropertyMetadataIsValidTest extends TestCase
{
	/** @var EntityMetadata */
	private $metadata;


	protected function setUp()
	{
		parent::setUp();

		$dependencies = [];
		$parser = new MetadataParser([]);
		$this->metadata = $parser->parseMetadata(ValidationTestEntity::class, $dependencies);
	}


	public function testDateTimeImmutable(): void
	{
		$property = $this->metadata->getProperty('datetimeimmutable');

		$val = new DateTimeImmutable();
		Assert::true($property->isValid($val));

		$val = new DateTime();
		Assert::false($property->isValid($val));

		$val = '';
		Assert::false($property->isValid($val));

		$val = 'now';
		Assert::false($property->isValid($val));

		$val = time();
		Assert::false($property->isValid($val));
	}


	public function testString(): void
	{
		$property = $this->metadata->getProperty('string');

		$val = '';
		Assert::true($property->isValid($val));

		$val = 'test';
		Assert::true($property->isValid($val));

		$val = 2;
		Assert::true($property->isValid($val));
		Assert::same('2', $val);

		$val = new ValidationTestEntity();
		Assert::true($property->isValid($val));
		Assert::same('hi', $val);

		$val = 2.3;
		Assert::false($property->isValid($val));

		$val = false;
		Assert::false($property->isValid($val));

		$val = (object) [];
		Assert::false($property->isValid($val));
	}


	public function testFloat(): void
	{
		$property = $this->metadata->getProperty('float');

		$val = 2.3;
		Assert::true($property->isValid($val));

		$val = 2;
		Assert::true($property->isValid($val));
		Assert::same(2.0, $val);

		$val = '2,3';
		Assert::true($property->isValid($val));
		Assert::same(2.3, $val);

		$val = '100 122,3';
		Assert::true($property->isValid($val));
		Assert::same(100122.3, $val);
	}


	public function testInt(): void
	{
		$property = $this->metadata->getProperty('int');

		$val = 2;
		Assert::true($property->isValid($val));

		$val = 2.3;
		Assert::true($property->isValid($val));
		Assert::same(2, $val);

		$val = '2,3';
		Assert::true($property->isValid($val));
		Assert::same(2, $val);

		$val = '100 122,3';
		Assert::true($property->isValid($val));
		Assert::same(100122, $val);
	}


	public function testBool(): void
	{
		$property = $this->metadata->getProperty('boolean');

		$val = false;
		Assert::true($property->isValid($val));

		$val = 1;
		Assert::true($property->isValid($val));
		Assert::true($val);

		$val = 1.0;
		Assert::true($property->isValid($val));
		Assert::true($val);

		$val = '1';
		Assert::true($property->isValid($val));
		Assert::true($val);

		$val = 0;
		Assert::true($property->isValid($val));
		Assert::false($val);

		$val = 0.0;
		Assert::true($property->isValid($val));
		Assert::false($val);

		$val = '0';
		Assert::true($property->isValid($val));
		Assert::false($val);

		$val = '1.0';
		Assert::false($property->isValid($val));

		$val = 2;
		Assert::false($property->isValid($val));

		$val = '2';
		Assert::false($property->isValid($val));
	}


	public function testArray(): void
	{
		$property = $this->metadata->getProperty('array1');

		$val = [];
		Assert::true($property->isValid($val));

		$val = (object) [];
		Assert::false($property->isValid($val));

		$property = $this->metadata->getProperty('array2');

		$val = [];
		Assert::true($property->isValid($val));

		$val = (object) [];
		Assert::false($property->isValid($val));
	}


	public function testObject(): void
	{
		$property = $this->metadata->getProperty('object');

		$val = (object) [];
		Assert::true($property->isValid($val));

		$val = [];
		Assert::false($property->isValid($val));
	}


	public function testMixed(): void
	{
		$property = $this->metadata->getProperty('mixed');

		$val = [];
		Assert::true($property->isValid($val));
	}


	public function testType(): void
	{
		$property = $this->metadata->getProperty('type');

		$val = ArrayHash::from([]);
		Assert::true($property->isValid($val));

		$val = (object) [];
		Assert::false($property->isValid($val));
	}


	public function testNullable(): void
	{
		$property = $this->metadata->getProperty('nullable1');

		$val = null;
		Assert::true($property->isValid($val));

		$val = false;
		Assert::true($property->isValid($val));

		$val = 0;
		Assert::true($property->isValid($val));
		Assert::false($val);

		$property = $this->metadata->getProperty('nullable2');

		$val = null;
		Assert::true($property->isValid($val));

		$val = false;
		Assert::false($property->isValid($val));

		$val = new DateTimeImmutable;
		Assert::true($property->isValid($val));
	}


	public function testEnum(): void
	{
		$test1 = $this->metadata->getProperty('test');

		$val = 0;
		Assert::true($test1->isValid($val));

		$val = 1;
		Assert::true($test1->isValid($val));

		$val = 2;
		Assert::true($test1->isValid($val));

		$val = 3;
		Assert::false($test1->isValid($val));

		$val = null;
		Assert::false($test1->isValid($val));

		$val = 'a';
		Assert::false($test1->isValid($val));

		$val = '1a';
		Assert::false($test1->isValid($val));

		$val = '0';
		Assert::false($test1->isValid($val));
	}

}


$test = new PropertyMetadataIsValidTest();
$test->run();
