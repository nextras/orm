## Collection Functions

Collection functions are a powerful extension point that will allow you to write a custom collection filtering or an ordering behavior.

The collection function requires its own implementation for each storage implementation: ideally for both Dbal & Array storages to allow use of persisted and un-persisted collections. Both of the Dbal & Array storages are supported in a single interface.

<div class="note">

**Why do we have ArrayCollection and DbalCollection?**

Collection itself is independent of storage implementation. It is your choice if your collection function will work in both cases - for `ArrayCollection` and `DbalCollection`. Let us remind you, `ArrayCollection`s are commonly used in relationships when you set new entities into the relationship but until the relationship is persisted, you will work with an `ArrayCollection`.
</div>

Collection functions can be used in `ICollection::findBy()` or `ICollection::orderBy()` methods. A collection function is passed as an array. The first value is the function identifier (it is recommended using function's class name) and then function's arguments as other array values. Collection functions may be used together, also nest together, so you can reuse them.

```php
// collection function call
$collection->findBy([MyFunction::class, 'arg1', 'arg2']);

// or compose & nest the calls together
// ICollection::OR is also a collection function
$collection->findBy(
	[
		ICollection::OR,
		[MyFunction::class, 'arg1', 'arg2'],
		[AnotherFunction::class, 'arg3'],
	]
);
```

Functions are registered per repository. To do so, override `Repository::createCollectionFunction($name)` method to return your collection functions' instances.

```php
class UsersRepository extends Nextras\Orm\Repository\Repository
{
	public function createCollectionFunction(string $name): CollectionFunction
	{
		if ($name === MyFunction::class) {
			return new MyFunction();
		} else {
			return parent::createCollectionFunction($name);
		}
	}
}
```

To implement your own function, create a class implementing `Nextras\Orm\Collection\Functions\CollectionFunction` interface.

```php
use \Nextras\Dbal\QueryBuilder\QueryBuilder;
use \Nextras\Orm\Collection\Aggregations\Aggregator;
use \Nextras\Orm\Collection\Functions\CollectionFunction;
use \Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use \Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use \Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use \Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use \Nextras\Orm\Entity\IEntity;

final class LikeFunction implements CollectionFunction
{
    public function processDbalExpression(
        DbalQueryBuilderHelper $helper,
        QueryBuilder $builder,
        array $args,
        Aggregator|null $aggregator = null,
    ) : DbalExpressionResult
    {
        // TODO: Implement processDbalExpression() method.
    }

    public function processArrayExpression(
        rrayCollectionHelper $helper,
        IEntity $entity,
        array $args,
        Aggregator|null $aggregator = null,
    ) : ArrayExpressionResult
    {
        // TODO: Implement processArrayExpression() method.
    }
}
```

#### Dbal Implementation

The Dbal's method takes four arguments: `DbalQueryBuilderHelper` for easier user input processing, `QueryBuilder` for direct access to the builder, but usually this builder is not used directly at all, `$args` is  the user input/function parameters and lastly aggregator that is being passed around.

Collection function has to return a `DbalExpressionResult` object. This object holds parts of SQL query which may be processed by Nextras Dbal's SqlProcessor. Because you are not adding SQL parts directly to QueryBuilder but rather return them in `DbalExpressionResult`, you may compose multiple collection functions together.

Let's see an example: a "Like" collection function; We want to compare a property (expression) via SQL's LIKE operator with a prefix comparison.

```php
$users->findBy(
	[LikeFunction::class, 'phone', '+420']
);
```

In the example we would like to use the custom `LikeFunction` to filter users by their phones that start with `+420` prefix. Our function will receive `$args` with `phone` and `+420`. The column/property argument may be quite dynamic too. What if the user passes `address->zipcode` expression (i.e. a relationship expression) instead of a simple `phone`, such expression would require table joins; doing it all by hand would be difficult. Therefore, Orm provides `DbalQueryBuilderHelper` that will handle all this for you. Use `processExpression` method to obtain a `DbalExpressionResult` for the column/property argument. Then just append needed SQL to the returned expression, e.g. LIKE operator with a Dbal's argument. That's all!

```php
public function processDbalExpression(
    DbalQueryBuilderHelper $helper,
    QueryBuilder $builder,
    array $args,
    Aggregator|null $aggregator = null,
) : DbalExpressionResult
{
    // $args is for example ['phone', '+420']
    \assert(\count($args) === 2 && \is_string($args[0]) && \is_string($args[1]));

    $expression = $helper->processExpression($builder, $args[0], $aggregator);
    return $expression->append('LIKE %like_', $args[1]);
}
```

The helper processed value may not be just a column SQL name, but also a more complex expression returned from another collection function.

If you need more advance operation than appending the expression, either construct a new `DbalExpressionResult` object (and copy over all properties to retain them) or use. Use Dbal's `%ex` modifier to expand the already processed expression. Some properties of the original expression result may be lost by creating a new expression result instance; if needed, pass the original's values as additional constructor parameters.

```php
$expression = $helper->processExpression($builder, $args[0], $aggregator);
return new DbalExpressionResult(['SUBSTRING(%ex, 0, %i) = %s', $expression->args, \strlen($args[1]), $args[1]]);
```

#### Array Implementation

The implementation is different to Dbal's interface, because the filtering happens directly in PHP runtime. The method takes `ArrayCollectionHelper` instance for easier expression processing, `IEntity` instance to check if the expressions requires it (not) to be filtered out, and user input/function parameters.

Array collection function returns `ArrayExpressionResult` instance wrapping a mixed value, the kind (type) of the wrapped value depends on which context it will be user or evaluated later. Ultimately, the value will be interpreted as a boolean to indicate if the entity should be filtered out in the `ICollection::findBy()` call; alternatively, the value will be used for comparison (`<=>`) of two entities when used for `ICollection::oderBy()` call.

Let's see an example: a "Like" collection function; We want to compare a property expression to the passed user-input value using a prefix comparison.

```php
public function processArrayExpression(
    ArrayCollectionHelper $helper,
    IEntity $entity,
    array $args,
    ?Aggregator $aggregator = null,
): ArrayExpressionResult
{
    // $args is for example ['phone', '+420']
    \assert(\count($args) === 2 && \is_string($args[0]) && \is_string($args[1]));

    $valueResult = $helper->getValue($entity, $args[0], $aggregator);
    return new ArrayExpressionResult(
        value: Strings::startsWith($valueResult->value, $args[1]),
    );
}
```

Similarly as in Dbal's example, the user property expression argument may vary from a simple property name to relationship expression. Therefore, use the helper to get the property expression result for that expression, then read the value and use it for creating a new array expression result.

<div class="note">

Postgres is case-sensitive, so the value should be lowered first & a functional index should be created; Orm comes with own built-in [Like functionality](collection-filtering#toc-like-filtering).
</div>
