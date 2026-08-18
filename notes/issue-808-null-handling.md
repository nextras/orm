# Issue #808 — NULL handling in `Compare*Function`: analysis & proposal

Working document for <https://github.com/nextras/orm/issues/808>.
Target branch: 6.0-dev (`dev-main`), i.e. a behaviour change is on the table.

The issue reports that `findBy(['deletedAt<' => $date])` returns entities with `deletedAt === null`
when the filter is evaluated in PHP (`ArrayCollection` / `MemoryCollection` / unpersisted
`HasManyCollection`), while the SQL backend never returns them. The reporter proposes an opt-in
`SqlAlignedCompareXxxFunction` family.

The report is a symptom of a larger, systematic divergence. Part 1 maps the whole divergence,
Part 2 evaluates the fix strategies, Part 3 proposes the operator surface.

---

## Part 1 — Where the two evaluation engines disagree

### 1.0 What the reference semantics should be

SQL evaluates a `WHERE` predicate in three-valued logic: any comparison with a `NULL` operand
yields `UNKNOWN`, and only `TRUE` rows are returned. PHP evaluates in two-valued logic and, worse,
coerces `null` in a way that depends on the *other* operand's truthiness.

The DBAL engine is the primary one (it is what production queries run through), it is the one whose
semantics are portable and documented (ANSI SQL), and the array engine already imitates it in
places — see `CompareEqualsFunction::multiEvaluateInPhp()`
(src/Collection/Functions/CompareEqualsFunction.php:37), which special-cases "no rows in the
has-many relation + target `null`" to `true` precisely to mimic what a `LEFT JOIN` produces. So:
**SQL is the reference; the PHP engine has the bugs.**

### 1.1 Relational operators — the reported bug

`Compare{SmallerThan,SmallerThanEquals,GreaterThan,GreaterThanEquals}Function::evaluateInPhp()`
delegates either to PHP's `<`/`<=`/`>`/`>=` (no comparator) or to
`PropertyComparator::compare()`. Both are null-unsafe:

* PHP compares `null` against a non-null operand by casting **both to bool**, so the result flips
  with the truthiness of the other side: `null < 5` is `true`, but `null < 0` is `false`.
* The bundled comparators propagate `null` through `?->`:
  `DateTimeWrapper::compare()` (src/Entity/PropertyWrapper/DateTimeWrapper.php:84) evaluates
  `null?->getTimestamp() <=> $ts`, i.e. `null <=> int` — same bool coercion, always `-1` for a
  positive timestamp.

Measured (calling the real `evaluateInPhp()` implementations; SQL would answer `false` in **every**
row of this table):

| source | target | `=` | `!=` | `<` | `<=` | `>` | `>=` |
|---|---|---|---|---|---|---|---|
| `null` | `5` | false | **true** | **true** | **true** | false | false |
| `null` | `-5` | false | **true** | **true** | **true** | false | false |
| `null` | `0` | false | **true** | false | **true** | false | **true** |
| `null` | `'a'` | false | **true** | **true** | **true** | false | false |
| `null` | `''` | false | **true** | false | **true** | false | **true** |
| `5` | `null` | false | true¹ | false | false | **true** | **true** |
| `null` | `null` | true¹ | false¹ | false | **true** | false | **true** |
| `null` | `2026-01-01` (DateTime + comparator) | false | **true** | **true** | **true** | false | false |
| `2026-01-01` | `null` (DateTime + comparator) | false | true¹ | false | false | **true** | **true** |

¹ Not a divergence: `= null` / `!= null` compile to `IS NULL` / `IS NOT NULL`
(src/Collection/Functions/CompareEqualsFunction.php:78,
src/Collection/Functions/CompareNotEqualsFunction.php:69), so those columns agree with SQL.

Two observations that matter for the fix:

* `>` and `>=` are *accidentally* aligned for typical (truthy) targets — which is why the bug is
  usually reported only for `<`. They diverge as soon as the target is `0` or `''`.
* A **non-null** source with a **null** target (`'price>' => null`) is meaningless in SQL (always
  no match) but returns `true` in PHP. Nothing sensible can be meant by it; it deserves an
  exception rather than a silent value.

### 1.2 `!=` with a non-null value — the inverse trap

`CompareNotEqualsFunction::evaluateInPhp()` returns `true` for a `null` source, SQL's `col != 5`
returns `UNKNOWN` and drops the row. This is the single most common real-world surprise in the
other direction: `findBy(['deletedAt!=' => $x])` silently loses all rows with `deletedAt IS NULL`
in SQL, while the same filter over an `ArrayCollection` keeps them.

Same divergence appears through relationship traversal, where the `LEFT JOIN` manufactures NULLs:
`findBy(['translator->name!=' => 'Jon'])` — SQL drops books without a translator, PHP keeps them
(`FetchPropertyFunction` yields `null` for a broken has-one chain,
src/Collection/Functions/FetchPropertyFunction.php:120).

Note that this case is *not* fixed by "make PHP match SQL" alone: dropping NULL rows is rarely what
the user meant. It needs an operator (Part 3).

### 1.3 `IN` / `NOT IN` with `null` inside the list

`['id' => [1, null]]` and `['id!=' => [1, null]]` end up as `IN (1, NULL)` / `NOT IN (1, NULL)`
(src/Collection/Functions/CompareEqualsFunction.php:72,
src/Collection/Functions/CompareNotEqualsFunction.php:63), while PHP uses strict `in_array()`:

| expression | source | PHP | SQL |
|---|---|---|---|
| `= [1, null]` | `null` | **true** | false (`NULL IN (1,NULL)` → UNKNOWN) |
| `!= [1, null]` | `null` | false | false |
| `!= [1, null]` | `2` | **true** | **false — `NOT IN` with a NULL member never matches any row** |

`NOT IN (…, NULL)` returning the empty set regardless of data is a notorious SQL footgun; no caller
of an ORM intends it. This one is worth deviating from raw SQL semantics (see 3.4).

### 1.4 `LIKE` over a nullable property crashes in PHP

`CompareLikeFunction::evaluateInPhp()` (src/Collection/Functions/CompareLikeFunction.php:93) passes
the source value straight into `str_starts_with()` / `Strings::match()`. Under
`declare(strict_types = 1)` a `null` source is a hard `TypeError`, for every mode:

```
startsWith:  TypeError: str_starts_with(): Argument #1 ($haystack) must be of type string, null given
contains:    TypeError: Nette\Utils\Strings::match(): Argument #1 ($subject) must be of type string, null given
notContains: TypeError: …
raw:         TypeError: …
```

SQL answers `false` (`NULL LIKE 'x'` → UNKNOWN) in all four cases. Additionally
`MODE_NOT_CONTAINS` would diverge even without the crash: SQL's `NOT LIKE` drops NULL rows, a
naive PHP negation keeps them (the same shape as 1.2).

### 1.5 Aggregate functions

`NumericAggregator` emits `MIN()/MAX()/SUM()/AVG()/COUNT()` on the SQL side, which **skip NULLs**
and return `NULL` for an empty set. The PHP counterparts do not:

| function | array side | SQL side | diverges when |
|---|---|---|---|
| `CountAggregateFunction` | `count($values)` | `COUNT(expr)` | the relation has rows whose value is `NULL` (PHP counts them, SQL does not) |
| `SumAggregateFunction` | `array_sum($values)` → `0` | `SUM(expr)` → `NULL` | the set is empty or all-NULL (`0` vs `NULL`, e.g. `sum <= 5` is true in PHP, false in SQL) |
| `AvgAggregateFunction` | `array_sum / count($values)` | `AVG(expr)` | any NULL member — PHP divides by the count *including* NULLs |
| `MinAggregateFunction` | `min($values)` → `null` if any NULL member | `MIN(expr)` skips NULLs | any NULL member |
| `MaxAggregateFunction` | `max($values)` | `MAX(expr)` | accidentally aligned (`max` ignores `null` against positives) |

`CountAggregator` / `AnyAggregator` / `NoneAggregator` are fine — they count the joined primary key,
which is never NULL, and their PHP side filters on truthiness.

### 1.6 What is already consistent (do not touch)

* `= null` / `!= null` → `IS NULL` / `IS NOT NULL`.
* Empty `IN` list → `1=0`, empty `NOT IN` → `1=1`; both match the PHP result.
* Empty has-many + target `null` → `true`, mirroring the `LEFT JOIN` NULL row
  (CompareEqualsFunction.php:37).
* `ORDER BY` null placement: explicitly modelled by `ICollection::ASC_NULLS_FIRST` & co. and
  emitted per platform (src/Collection/Helpers/DbalQueryBuilderHelper.php:103-134); the array
  sorter handles nulls explicitly (src/Collection/Helpers/ArrayCollectionHelper.php:82). This is
  the precedent for how an explicit null policy should look in the public API.

### 1.7 Do we need three-valued logic in the array engine?

No — and this is the key simplification.

The array engine reduces a predicate to a `bool` (`ArrayCollection::processData()` filters on
`$filter($value)->value`, src/Collection/ArrayCollection.php:276) and `ConjunctionOperatorFunction`
/ `DisjunctionOperatorFunction` combine operands in 2VL. Introducing an `UNKNOWN` value would be a
large, invasive change (every function, every aggregator, `ArrayExpressionResult`).

It is unnecessary because the ORM has **no logical `NOT` function** — the connectives are only
`AND` and `OR`, and the row is kept only if the root evaluates to `TRUE`. Under those conditions,
mapping `UNKNOWN → FALSE` at each *leaf* is provably equivalent to full 3VL:

```
UNKNOWN AND TRUE  = UNKNOWN → dropped  |  FALSE AND TRUE  = FALSE → dropped   ✔
UNKNOWN AND FALSE = FALSE   → dropped  |  FALSE AND FALSE = FALSE → dropped   ✔
UNKNOWN OR  TRUE  = TRUE    → kept     |  FALSE OR  TRUE  = TRUE  → kept      ✔
UNKNOWN OR  FALSE = UNKNOWN → dropped  |  FALSE OR  FALSE = FALSE → dropped   ✔
```

The negation-carrying constructs (`!=`, `NOT LIKE`, `NoneAggregator`) are *leaves* in SQL too, and
SQL's own null propagation applies inside them, so the same substitution holds. `NoneAggregator`
maps to "no joined row satisfied the condition"; a NULL-valued row does not satisfy it on either
engine, so the entity is kept on both.

**Conclusion: fix the leaves (`Compare*Function::evaluateInPhp()`), leave the connectives alone.**
If a real `NotFunction` is ever added, 3VL becomes mandatory — worth a comment in the code so the
invariant is not silently broken later.

---

## Part 2 — Fix strategies

### Option A — the issue's proposal: parallel `SqlAligned*` classes, opt-in

*Pros:* zero BC break, users opt in per project.
*Cons:* doubles the number of comparison classes forever; the default stays wrong; the operator
shorthands (`<`, `!=`, …) still map to the broken classes, so opting in means rewriting every
condition into the array form or overriding `ConditionParser`; two behaviours to document and test.
Not recommended as the end state.

### Option B — fix the leaves, ship the legacy behaviour as an opt-in (recommended)

Invert Option A: SQL-aligned semantics become the default in 6.0, and the previous PHP behaviour
stays reachable — partly as new explicit operators (Part 3), partly as legacy classes a project can
re-register in one place:

```php
class MyRepository extends Repository
{
    protected function createCollectionFunction(string $name): CollectionFunction
    {
        if ($name === CompareNotEqualsFunction::class) {
            return new LegacyNullLooseCompareNotEqualsFunction(); // pre-6.0 semantics
        }
        return parent::createCollectionFunction($name);
    }
}
```

`Repository::createCollectionFunction()` (src/Repository/Repository.php) is already the documented
extension point, so no new configuration surface is needed.

*Why a BC break is acceptable here:* `main` is `6.0-dev`; the current behaviour is not a documented
contract but an accident of PHP's coercion rules; and the two engines are required to be
interchangeable — the same filter must answer the same thing on `DbalCollection` and on
`$collection->toMemoryCollection()`, or `HasManyCollection` (persisted part in SQL, unpersisted part
in PHP) is silently wrong.

### Option C — full 3VL in the array engine

Rejected, see 1.7. Revisit only together with a `NotFunction`.

### Implementation sketch for Option B

One gate in the base class keeps the subclasses trivial:

```php
abstract class BaseCompareFunction implements CollectionFunction
{
    /**
     * Whether the comparison propagates NULL, i.e. yields SQL's UNKNOWN (no match).
     * Overridden by =/!= which give NULL the "IS [NOT] NULL" meaning.
     */
    protected function propagatesNull(mixed $sourceValue, mixed $targetValue): bool
    {
        return $sourceValue === null || $targetValue === null;
    }

    private function evaluate(mixed $s, mixed $t, ?PropertyComparator $c): bool
    {
        return $this->propagatesNull($s, $t) ? false : $this->evaluateInPhp($s, $t, $c);
    }
}
```

Per function:

| function | array side after the fix | dbal side after the fix |
|---|---|---|
| `CompareEqualsFunction` | unchanged for scalars; `in_array` list containing `null` → matches only a `null` source | list containing `null` → `(col IN (…) OR col IS NULL)` (3.4) |
| `CompareNotEqualsFunction` | `null` source, non-null target → **`false`** | list containing `null` → `(col NOT IN (…) AND col IS NOT NULL)` (3.4) |
| `Compare{SmallerThan,…}Function` | either operand `null` → `false` | `null` target → throw `InvalidArgumentException` (3.3) |
| `CompareLikeFunction` | `null` source → `false` (fixes the `TypeError`) | unchanged |
| `Min/Avg/Count/SumAggregateFunction` | drop `null`s before aggregating; `SUM` of an empty/all-null set → `null` | unchanged |

`multiEvaluateInPhp()` needs no separate rule — it maps the same leaf evaluation over the
aggregated values.

---

## Part 3 — Proposed operators

Making the default SQL-aligned removes a foot-gun but also removes the only way to express
"…including the NULL rows". That intent is legitimate and common, so it needs an explicit,
readable operator instead of an accident. The guiding principle: **NULL semantics should be visible
at the call site.**

### 3.1 `IS DISTINCT FROM` — the null-safe (in)equality pair

ANSI SQL's `IS [NOT] DISTINCT FROM`: NULL is treated as a value that equals itself and differs from
everything else — exactly the semantics PHP's `!==` already has, and exactly what today's
`ArrayCollection` users rely on.

| new function | proposed shorthand | meaning |
|---|---|---|
| `CompareIsNotDistinctFromFunction` | `prop?=` | null-safe equality |
| `CompareIsDistinctFromFunction` | `prop?!=` | null-safe inequality |

```php
// "everything that is not this status, NULL statuses included"
$orm->records->findBy(['status?!=' => Status::Archived]);
```

SQL emission — because the right-hand side of a compare function is always a *value* (never another
column: `BaseCompareFunction::processDbalExpression()` normalizes `$args[1]` through
`valueNormalizer`), its null-ness is known at build time and no platform-specific operator is
needed:

| value | `?=` | `?!=` |
|---|---|---|
| `null` | `col IS NULL` | `col IS NOT NULL` |
| scalar `v` | `col = %v` | `(col IS NULL OR col != %v)` |
| list without `null` | `col IN %any[]` | `(col IS NULL OR col NOT IN %any[])` |
| list with `null` | `(col IN %any[] OR col IS NULL)` | `(col IS NOT NULL AND col NOT IN %any[])` |

PHP evaluation: `$source === $target` / `!==` (or `PropertyComparator::equals()`), i.e. today's
behaviour, verbatim.

Note that `?=` is, for a literal RHS, identical to `=` — it is worth adding anyway for symmetry,
readability, and as future-proofing for column-to-column comparisons (where it would need the real
platform operator: `IS DISTINCT FROM` on pgsql/SQL Server 2022+, `IS NOT` on SQLite, `NOT (a <=> b)`
on MySQL; the platform switch belongs in `DbalQueryBuilderHelper`, next to the existing
`processOrderDirection()` one).

**Migration story:** "if you relied on `!=` matching NULL rows in an `ArrayCollection`, change it to
`?!=` and it now behaves that way on *both* engines" — a mechanical, greppable change.

### 3.2 Null ordering for relational operators

`<`, `<=`, `>`, `>=` become null-rejecting. The other reasonable intent — "treat NULL as the
smallest / greatest value" — has no SQL operator, but the ORM already has vocabulary for it in
`ICollection::ASC_NULLS_FIRST` / `DESC_NULLS_LAST`. Reuse it as a **value wrapper**, following the
`LikeExpression` precedent (the value, not the key, carries the modifier):

```php
use Nextras\Orm\Collection\Expression\NullsExpression;

// records not yet deleted count as "deleted at the end of time"
$orm->records->findBy(['deletedAt<' => NullsExpression::last($date)]);
// → SQL: deleted_at < %dt          (NULL is greatest → never smaller)
// → PHP: $v !== null && $v < $date

$orm->records->findBy(['deletedAt<' => NullsExpression::first($date)]);
// → SQL: (deleted_at IS NULL OR deleted_at < %dt)
// → PHP: $v === null || $v < $date
```

Emission table (`v` = the wrapped value, non-null):

| operator | `NullsExpression::first` | `NullsExpression::last` |
|---|---|---|
| `<`  | `(col IS NULL OR col < v)` | `col < v` |
| `<=` | `(col IS NULL OR col <= v)` | `col <= v` |
| `>`  | `col > v` | `(col IS NULL OR col > v)` |
| `>=` | `col >= v` | `(col IS NULL OR col >= v)` |

This keeps the operator set at four instead of twelve, is symmetrical with `orderBy()`, and needs no
new registered classes — `BaseCompareFunction` unwraps the expression the same way
`CompareLikeFunction` unwraps `LikeExpression`. (An alternative — parameterized function instances
such as `[new CompareSmallerThanFunction(Nulls::First), 'deletedAt', $date]` — would additionally
require `ArrayCollectionHelper`/`DbalQueryBuilderHelper` to accept a `CollectionFunction` *instance*
as `$expression[0]`, since functions are resolved by class-string today. That is a nice enhancement
on its own, but a worse fit for the `'key' => value` shorthand.)

### 3.3 Enforcement: reject meaningless null comparisons

`['price>' => null]` cannot mean anything (SQL: never matches; PHP: `true` for any truthy value).
After the fix it would silently return nothing — better to fail loudly:

```php
throw new InvalidArgumentException(
    "Cannot compare property 'price' with null using the '>' operator; " .
    "use '=' / '!=' for null checks, or wrap the value in NullsExpression."
);
```

Same for `~` (`LikeExpression` with a `null` input).

This is the "enforce null handling" part: with `<`/`>` rejecting nulls, `!=` documented as
null-rejecting and `?!=` available for the other intent, every null-sensitive comparison in user
code is either explicit or an error — none of them is a coin flip.

### 3.4 `IN` / `NOT IN` containing `null`

Recommended: **deviate from raw SQL** and emit the null-safe rewrite shown in 3.1, for plain `=`
and `!=` as well. Rationale: `NOT IN (1, NULL)` matching zero rows is never the intent, PHP's
`in_array()` already implements the intuitive reading, and this is the only place where "align to
SQL" would align to a construct universally considered a bug.

Conservative alternative if a silent semantic change is unwanted: throw on a `null` member and
require the caller to write `[ICollection::OR, 'x' => null, 'x' => [1, 2]]`. Whichever is chosen,
`=` and `!=` must agree with each other and with the array engine.

### 3.5 Optional: explicit null predicates

`['token' => $token]` where `$token` unexpectedly holds `null` silently becomes `token IS NULL` —
a class of authentication/lookup bug. A stricter, opt-in mode could require the intent to be
spelled out:

```php
$orm->users->findBy(['token' => Nulls::isNull()]);      // explicit IS NULL
$orm->users->findBy(['token' => $token]);               // throws if $token is null, in strict mode
```

Low priority compared to 3.1–3.4, and it is a bigger BC break; listed for completeness because it
belongs to the same "make NULL intent explicit" theme.

---

## Rollout plan for 6.0

1. Fix the leaves (Part 2 sketch) + the aggregate functions (1.5).
2. Add `CompareIsDistinctFromFunction` / `CompareIsNotDistinctFromFunction`, register them in
   `Repository::createCollectionFunction()`, add `?=` / `?!=` to `ConditionParser::PATH_REGEXP`'s
   operator group (src/Collection/Helpers/ConditionParser.php:38) — order matters in the
   alternation: `\?!=|\?=|!=|<=|>=|=|>|<|~`.
3. Add `NullsExpression` (3.2) and unwrap it in `BaseCompareFunction`.
4. Throw on meaningless null comparisons (3.3) and on/rewrite null-in-list (3.4).
5. Ship legacy classes for the previous PHP behaviour so a project can re-register them (Option B).
6. Docs: a "NULL handling" section in `docs/collection-filtering.md` with the operator table, plus a
   `docs/migrate_6.0.md` entry with a before/after table.
7. Tests: a data-driven matrix test that runs *every* operator against `{null source, null target,
   both null, list containing null}` on both engines and asserts identical results — e.g.
   `tests/cases/integration/Collection/collection.nullSemantics.phpt` executing each case against
   the `DbalCollection` and against `->toMemoryCollection()`. This matrix is what would have caught
   all of 1.1–1.5 at once, and it prevents the two engines from drifting again.
8. Optional follow-up: a `nextras/orm-phpstan` rule that flags `<`/`>` comparisons against a
   nullable property when neither `NullsExpression` nor a null-safe operator is used.
