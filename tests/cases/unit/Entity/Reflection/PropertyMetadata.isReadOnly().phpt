<?php declare(strict_types=1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Entity\Reflection;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @property int $id {primary}
 * @property-read int $test1
 * @property int $test2
 */
class ReadOnlyTestEntity extends Entity
{
}


class PropertyMetadataIsReadOnlyTest extends TestCase
{
	public function testReadOnlyProperty()
	{
		$dependencies = [];
		$parser = new MetadataParser([]);
		$metadata = $parser->parseMetadata(ReadOnlyTestEntity::class, $dependencies);

		Assert::same(true, $metadata->getProperty('test1')->isReadonly);
		Assert::same(false, $metadata->getProperty('test2')->isReadonly);
	}
}


$test = new PropertyMetadataIsReadOnlyTest($dic);
$test->run();
