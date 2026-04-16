<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity;


use Mockery;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\PropertyWrapper\DateTimeWrapper;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidPropertyValueException;
use Nextras\Orm\Exception\NullValueException;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class ImmutableValuePropertyWrapperTest extends TestCase
{
	/**
	 * Setting a raw null must not validate; the actual value may not be known yet (e.g. when the wrapper is
	 * instantiated by {@see IEntity::getProperty()} before the value from the user is applied).
	 */
	public function testSetRawValueDoesNotValidateNull(): void
	{
		$wrapper = new DateTimeWrapper($this->createMetadata(isNullable: false));

		Assert::noError(function () use ($wrapper): void {
			$wrapper->setRawValue(null);
		});
	}


	/**
	 * Nullability of a non-nullable property is validated lazily, on read.
	 */
	public function testReadValidatesNullOnNonNullable(): void
	{
		$wrapper = new DateTimeWrapper($this->createMetadata(isNullable: false));
		$wrapper->setRawValue(null);

		Assert::throws(function () use ($wrapper): void {
			$wrapper->getRawValue();
		}, NullValueException::class);

		Assert::throws(function () use ($wrapper): void {
			$wrapper->getInjectedValue();
		}, NullValueException::class);
	}


	public function testNullableAllowsNull(): void
	{
		$wrapper = new DateTimeWrapper($this->createMetadata(isNullable: true));
		$wrapper->setRawValue(null);

		Assert::null($wrapper->getRawValue());
		Assert::null($wrapper->getInjectedValue());
	}


	/**
	 * The conversion of the raw value is deferred to read time; a malformed raw value therefore does not throw when
	 * set, only when it is actually read.
	 */
	public function testConversionIsDeferredToRead(): void
	{
		$wrapper = new DateTimeWrapper($this->createMetadata(isNullable: true));

		Assert::noError(function () use ($wrapper): void {
			$wrapper->setRawValue('');
		});

		Assert::throws(function () use ($wrapper): void {
			$wrapper->getInjectedValue();
		}, InvalidPropertyValueException::class);
	}


	private function createMetadata(bool $isNullable): PropertyMetadata
	{
		$metadata = Mockery::mock(PropertyMetadata::class);
		$metadata->name = 'createdAt';
		$metadata->isNullable = $isNullable;
		$metadata->types = [DateTimeImmutable::class => true];
		return $metadata;
	}
}


$test = new ImmutableValuePropertyWrapperTest();
$test->run();
