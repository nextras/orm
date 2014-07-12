<?php

namespace Nextras\Orm\Tests\Entity\Reflection;

use Mockery;
use Nette\Utils\DateTime;
use Nextras\Orm\Entity\PropertyContainers\DateTimePropertyContainer;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @property int $test {enum self::TYPE_*}
 */
class EnumValidationTestEntity
{
	const TYPE_ZERO = 0;
	const TYPE_ONE = 1;
	const TYPE_TWO = 2;
}


/**
 * @testCase
 */
class PropertyMetadataIsValidTest extends TestCase
{

	public function testBasics()
	{
		$dp = [];
		$parser = new AnnotationParser('Nextras\Orm\Tests\Entity\Reflection\EnumValidationTestEntity');
		$metadata = $parser->getMetadata($dp);

		$test1 = $metadata->getProperty('test');

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
