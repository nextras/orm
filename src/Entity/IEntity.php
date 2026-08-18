<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Repository\IRepository;


interface IEntity
{
	/**
	 * @return IRepository<IEntity>
	 */
	#[\NoDiscard]
	public function getRepository(): IRepository;


	/**
	 * Sets property value.
	 * @param mixed $value
	 * @return self
	 */
	public function setValue(string $name, $value);


	/**
	 * Sets read-only value.
	 * @param mixed $value
	 * @return self
	 */
	public function setReadOnlyValue(string $name, $value);


	/**
	 * Returns value.
	 * @return mixed
	 */
	#[\NoDiscard]
	public function &getValue(string $name);


	/**
	 * Returns true if property has a value (not null).
	 */
	#[\NoDiscard]
	public function hasValue(string $name): bool;


	/**
	 * Sets raw value.
	 * @param mixed $value
	 */
	public function setRawValue(string $name, $value): void;


	/**
	 * Returns raw value.
	 * Raw value is normalized value which is suitable unique identification and storing.
	 * @return mixed
	 */
	#[\NoDiscard]
	public function &getRawValue(string $name);


	/**
	 * Returns property wrapper.
	 */
	#[\NoDiscard]
	public function getProperty(string $name): IProperty;


	/**
	 * Returns property raw contents: IProperty if initialized, a raw value otherwise.
	 * @return mixed
	 */
	#[\NoDiscard]
	public function getRawProperty(string $name);


	/**
	 * Exports raw values for saving.
	 * This method exports all internal state for saving, including a primary key and all relationship data including
	 * nullability validation.
	 * Optionally you may export only modified values.
	 * Method does not return virtual properties.
	 * @return array<string, mixed>
	 * @internal
	 */
	#[\NoDiscard]
	public function getRawValues(bool $modifiedOnly = false): array;


	/**
	 * Returns entity metadata.
	 */
	#[\NoDiscard]
	public function getMetadata(): EntityMetadata;


	/**
	 * Returns true if the entity is modified or the column $name is modified.
	 */
	#[\NoDiscard]
	public function isModified(string|null $name = null): bool;


	/**
	 * Sets the entity or the column as modified.
	 */
	public function setAsModified(string|null $name = null): void;


	/**
	 * Returns true if entity is persisted.
	 */
	#[\NoDiscard]
	public function isPersisted(): bool;


	/**
	 * Returns persisted primary value.
	 * @return mixed
	 */
	#[\NoDiscard]
	public function getPersistedId();


	/**
	 * Returns true if entity is attached to its repository.
	 */
	#[\NoDiscard]
	public function isAttached(): bool;


	// === events ======================================================================================================


	/** @internal */
	public function onCreate(): void;


	/**
	 * @param array<string, mixed> $data
	 * @internal
	 */
	public function onLoad(array $data): void;


	/**
	 * @param array<string, mixed> $data
	 * @internal
	 */
	public function onRefresh(?array $data, bool $isPartial = false): void;


	/** @internal */
	public function onFree(): void;


	/**
	 * @param IRepository<IEntity> $repository
	 * @internal
	 */
	public function onAttach(IRepository $repository, EntityMetadata $metadata): void;


	/** @internal */
	public function onDetach(): void;


	/**
	 * @param mixed $id
	 * @internal
	 */
	public function onPersist($id): void;


	/** @internal */
	public function onBeforePersist(): void;


	/** @internal */
	public function onAfterPersist(): void;


	/** @internal */
	public function onBeforeInsert(): void;


	/** @internal */
	public function onAfterInsert(): void;


	/** @internal */
	public function onBeforeUpdate(): void;


	/** @internal */
	public function onAfterUpdate(): void;


	/** @internal */
	public function onBeforeRemove(): void;


	/** @internal */
	public function onAfterRemove(): void;
}
