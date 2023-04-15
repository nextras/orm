<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Entity;


/**
 * @property int|null $id   {primary}
 * @property string   $code
 * @property Book     $book {1:1 Book::$ean}
 * @property EanType  $type
 */
class Ean extends Entity
{
	public function __construct(EanType $type = EanType::EAN8)
	{
		parent::__construct();
		$this->type = $type;
	}
}
