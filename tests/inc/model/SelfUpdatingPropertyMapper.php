<?php

namespace NextrasTests\Orm;

use Nextras\Dbal\Result\Row;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Mapper;


abstract class SelfUpdatingPropertyMapper extends Mapper
{

	/**
	 * @return string[] property names
	 */
	protected function getSelfUpdatingProperties()
	{
		return [];
	}

	/**
	 * Default 1:1 columns map
	 * @return array nextras/dbal %ex syntax
	 */
	protected function getReturningClause()
	{
		$properties = $this->getSelfUpdatingProperties();
		if (!$properties) {
			return [];
		}

		$ref = $this->getStorageReflection();
		$clause = ['RETURNING'];
		foreach ($properties as $col) {
			$clause[0] .= ' %column';
			$clause[] = $ref->convertEntityToStorageKey($col);
		}
		return $clause;
	}


	public function persist(IEntity $entity)
	{
		$this->beginTransaction();
		$data = $this->entityToArray($entity);
		$data = $this->getStorageReflection()->convertEntityToStorage($data);

		if (!$entity->isPersisted()) {
			$result = $this->connection->query('INSERT INTO %table %values %ex', $this->getTableName(), $data, $this->getReturningClause());
			$id = $entity->hasValue('id')
					? $entity->getValue('id')
					: $this->connection->getLastInsertedId($this->getStorageReflection()->getPrimarySequenceName());

		} else {
			$primary = [];
			$id = (array) $entity->getPersistedId();
			foreach ($this->getStorageReflection()->getStoragePrimaryKey() as $key) {
				$primary[$key] = array_shift($id);
			}

			$result = $this->connection->query('UPDATE %table SET %set WHERE %and %ex', $this->getTableName(), $data, $primary, $this->getReturningClause());
			$id = $entity->getPersistedId();
		}

		$this->updateFromPersist($entity, $result->fetch());
		return $id;
	}


	/**
	 * Default 1:1 map implementation
	 * @param IEntity $entity
	 * @param Row     $row
	 */
	protected function updateFromPersist(IEntity $entity, Row $row)
	{
		$ref = $this->getStorageReflection();
		foreach ($row as $storageKey => $value) {
			$property = $ref->convertStorageToEntityKey($storageKey);
			$entity->setReadOnlyValue($property, $value);
		}
	}

}
