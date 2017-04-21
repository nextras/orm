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


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int|NULl $id {primary}
 *
 * @property int $test {enum self::TYPE_*}
 * @property string $string
 * @property int $int
 * @property bool $boolean
 * @property float $float
 * @property DateTimeImmutable $datetimeimmutable
 * @property array $array1
 * @property int[] $array2
 * @property object $object
 * @property scalar $scalar
 * @property mixed $mixed
 * @property ArrayHash $type
 * @property bool|NULL $nullable
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


	public function testDateTimeImmutable()
	{
		$property = $this->metadata->getProperty('datetimeimmutable');

		$val = new DateTimeImmutable();
		Assert::true($property->isValid($val));

		$val = new DateTime();
		Assert::true($property->isValid($val));
		Assert::type(DateTimeImmutable::class, $val);

		$val = '';
		Assert::false($property->isValid($val));

		$val = 'now';
		Assert::true($property->isValid($val));
		Assert::type(DateTimeImmutable::class, $val);

		$val = time();
		Assert::true($property->isValid($val));
		Assert::type(DateTimeImmutable::class, $val);
	}


	public function testString()
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


	public function testFloat()
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


	public function testInt()
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


	public function testBool()
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


	public function testArray()
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


	public function testObject()
	{
		$property = $this->metadata->getProperty('object');

		$val = (object) [];
		Assert::true($property->isValid($val));

		$val = [];
		Assert::false($property->isValid($val));
	}


	public function testScalar()
	{
		$property = $this->metadata->getProperty('scalar');

		$val = 1;
		Assert::true($property->isValid($val));

		$val = 1.0;
		Assert::true($property->isValid($val));

		$val = false;
		Assert::true($property->isValid($val));

		$val = 'string';
		Assert::true($property->isValid($val));


		$val = [];
		Assert::false($property->isValid($val));

		$val = (object) [];
		Assert::false($property->isValid($val));
	}


	public function testMixed()
	{
		$property = $this->metadata->getProperty('mixed');

		$val = [];
		Assert::true($property->isValid($val));
	}


	public function testType()
	{
		$property = $this->metadata->getProperty('type');

		$val = ArrayHash::from([]);
		Assert::true($property->isValid($val));

		$val = (object) [];
		Assert::false($property->isValid($val));
	}


	public function testNullable()
	{
		$property = $this->metadata->getProperty('nullable');

		$val = NULL;
		Assert::true($property->isValid($val));

		$val = false;
		Assert::true($property->isValid($val));

		$val = 0;
		Assert::true($property->isValid($val));
		Assert::false($val);
	}


	public function testEnum()
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

		$val = NULL;
		Assert::false($test1->isValid($val));

		$val = 'a';
		Assert::false($test1->isValid($val));

		$val = '1a';
		Assert::false($test1->isValid($val));

		$val = '0';
		Assert::false($test1->isValid($val));
	}

}


$test = new PropertyMetadataIsValidTest($dic);
$test->run();
