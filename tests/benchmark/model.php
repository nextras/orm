<?php declare(strict_types = 1);

/**
 * Dedicated model for the hydration benchmark (tests/benchmark/hydration.php).
 * Kept outside tests/cases so the tester runner does not pick it up.
 */

namespace NextrasBench;

use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\Embeddable\Embeddable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\PropertyWrapper\DateWrapper;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Memory\ArrayMapper;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\Repository\Repository;


enum BenchStatus: string
{
	case Draft = 'draft';
	case Active = 'active';
	case Archived = 'archived';
}


/**
 * @property-read int $width
 * @property-read int $height
 */
class BenchSize extends Embeddable
{
	public function __construct(int $width, int $height)
	{
		parent::__construct(['width' => $width, 'height' => $height]);
	}
}


/**
 * @property int|null                 $id          {primary}
 * @property string                   $name
 * @property int                      $quantity
 * @property float                    $price
 * @property bool                     $isActive
 * @property string|null              $note
 * @property DateTimeImmutable        $createdAt
 * @property DateTimeImmutable|null   $updatedAt
 * @property DateTimeImmutable        $validFrom   {wrapper DateWrapper}
 * @property BenchStatus              $status
 * @property array<mixed>             $payload
 * @property BenchSize|null           $dimensions  {embeddable}
 * @property BenchCategory            $category    {m:1 BenchCategory::$items}
 * @property OneHasMany<BenchComment> $comments    {1:m BenchComment::$item}
 */
class BenchItem extends Entity
{
}


/**
 * @property int|null              $id    {primary}
 * @property string                $name
 * @property OneHasMany<BenchItem> $items {1:m BenchItem::$category}
 */
class BenchCategory extends Entity
{
}


/**
 * @property int|null  $id   {primary}
 * @property string    $text
 * @property BenchItem $item {m:1 BenchItem::$comments}
 */
class BenchComment extends Entity
{
}


/** @extends Repository<BenchItem> */
class BenchItemsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [BenchItem::class];
	}
}


/** @extends Repository<BenchCategory> */
class BenchCategoriesRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [BenchCategory::class];
	}
}


/** @extends Repository<BenchComment> */
class BenchCommentsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [BenchComment::class];
	}
}


/**
 * @param mixed $value
 * @return mixed
 */
function jsonDecode($value)
{
	return $value === null ? null : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
}


/** @extends DbalMapper<BenchItem> */
class BenchItemsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'bench_items';
	}


	protected function createConventions(): IConventions
	{
		$conventions = parent::createConventions();
		$conventions->setMapping(
			'payload',
			'payload',
			static fn ($value) => jsonDecode($value),
			static fn ($value) => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR),
		);
		return $conventions;
	}
}


/** @extends DbalMapper<BenchCategory> */
class BenchCategoriesMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'bench_categories';
	}
}


/** @extends DbalMapper<BenchComment> */
class BenchCommentsMapper extends DbalMapper
{
	public function getTableName(): string
	{
		return 'bench_comments';
	}
}


/**
 * Memory mapper whose storage rows are injected directly (no file I/O).
 * @template E of \Nextras\Orm\Entity\IEntity
 * @extends ArrayMapper<E>
 */
class BenchArrayMapper extends ArrayMapper
{
	/** @var array<int|string, array<string, mixed>> rows in entity format keyed by id */
	public array $rows = [];


	protected function readData(): array
	{
		return [$this->rows, []];
	}


	protected function saveData(array $data): void
	{
		$this->rows = $data[0] ?? [];
	}
}
