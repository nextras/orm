<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Extension;
use Nextras\Orm\Repository\IRepository;


class TestExtension extends Extension
{
	public function configureRepository(IRepository $repository): void
	{
		if ($repository instanceof PublishersRepository) {
			$repository->onAfterInsert[] = function (IEntity $entity) {
				dump("Publisher {$entity->getPersistedId()} inserted.");
			};
		}
	}
}
