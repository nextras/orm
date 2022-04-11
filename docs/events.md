## Events

ORM's events provide powerful extension points.

#### Model & Repository events

To subscribe to an event, add a new callback as a new array item.

| Callback registration        | Callback signature
| --- | --- |
| **`Nextras\Orm\Model\Model`**  ||
| `$onFlush` | `function (IEntity[] $persisted, IEntity[] $removed) {}`
| ||
| **`Nextras\Orm\Repository\Repository`** ||
| `$onBeforePersist` | `function (IEntity $entity) {}`
| `$onAfterPersist`  | `function (IEntity $entity) {}`
| `$onBeforeInsert`  | `function (IEntity $entity) {}`
| `$onAfterInsert`   | `function (IEntity $entity) {}`
| `$onBeforeUpdate`  | `function (IEntity $entity) {}`
| `$onAfterUpdate`   | `function (IEntity $entity) {}`
| `$onBeforeRemove`  | `function (IEntity $entity) {}`
| `$onAfterRemove`   | `function (IEntity $entity) {}`
| `$onFlush`         | `function (IEntity[] $persisted, IEntity[] $removed) {}`


```php
$orm->books->onBeforeInsert[] = function (Book $book) {
	echo "Inserting into DB " . $book->title;
};
```

#### Entity events

You may react on events also inside your entity. To implement your code, override event method. Do not forget to call parent's implementation!


| Method signature    | Description        |
| ---                 | ---                |
| `onCreate()`                                      | When a new entity is created. (ie. onLoad is not called)
| `onLoad(array $data)`                             | When loaded from DB. (ie. onCreate is not called)
| `onRefresh(array $data, bool $isPartial = true)`  | When refreshed from DB by a persist call.
| `onFree()`                                        | When all entities of the model are destroyed.
| `onAttach(IRepository $r, EntityMetadata $m)`     | When attached to the repository.
| `onDetach()`                                      | When detached from the repository.
| `onPersist(mixed $id)`                            | When inserted/updated.
| `onBeforePersist()`                               | Before insert/update.
| `onAfterPersist()`                                | After insert/update.
| `onBeforeInsert()`                                | Before insert.
| `onAfterInsert()`                                 | After insert.
| `onBeforeUpdate()`                                | Before update.
| `onAfterUpdate()`                                 | After update.
| `onBeforeRemove()`                                | Before remove.
| `onAfterRemove()`                                 | After remove.

```php
/**
 * @property int               $id        {primary}
 * @property DateTimeImmutable $createdAt
 */
class Book extends Nextras\Orm\Entity\Entity
{
	public function onCreate(): void
	{
		parent::onCreate();
		$this->createdAt = new DateTimeImmutable();
	}
}
```
