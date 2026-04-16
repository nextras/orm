## Migration Guide for 6.0

### BC Breaks

- **`IProperty::setRawValue()` must not validate the value** - the method now only stores the passed raw value; it must neither convert it to the runtime representation nor validate it. Both the conversion and any validation are deferred to read time (`getInjectedValue()` / `getRawValue()`).

  This fixes premature initialization of non-nullable wrapped properties — reading such a property via `IEntity::getProperty()`, or creating an entity through `EntityCreator`, no longer triggers validation before the actual value is set.

  If you maintain a **custom property wrapper**:
  - Any validation previously performed in `setRawValue()` must be moved to read time — `setRawValue()` may be called with a value that is not yet known to be valid (or that is about to be overwritten before it is ever read).
  - `Nextras\Orm\Entity\ImmutableValuePropertyWrapper` already implements this: it keeps the raw value, converts it lazily via `convertFromRawValue()` on read, and validates nullability centrally. Your `convertFromRawValue()` is free to validate the (converted) value as before — it is simply invoked lazily now.

  As a consequence, an invalid raw value now surfaces its exception when the value is **read** rather than when it is **set** (i.e. during hydration / `setRawValue()`).
