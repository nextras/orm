<?php declare(strict_types=1);

namespace NextrasTests\Orm;


use DateTimeImmutable;


/**
 * @property DateTimeImmutable    $createdAt             {default "2021-12-02 20:21:00"}
 * @property-read string          $createdAtFormatted    {virtual}
 */
trait CreatedColumnTrait
{
	/**
	 * Getter for column createdAtFormatted
	 */
	protected function getterCreatedAtFormatted(): string
	{
		return $this->createdAt->format('d.m.Y H:i:s');
	}
}
