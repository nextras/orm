## Architecture

Orm is clearly designed to abstract entities from database implementation; Orm separates your model into three basic layers:
- **Entities**
 [Entities](entity) are data containers that hold your data, validate values, and provide an API for value encapsulation, e.g., for relationships.
- **Repositories**
 [Repositories](repository) form a layer that takes care of your entities. Repositories manage entities and provide an API for retrieving, filtering, and persisting them.
- **Mappers**
 [Mappers](mapper) are the backend of Orm. Mappers provide interaction with the database layer. Orm uses the [Nextras\Dbal][1] database library as an abstraction layer for database connection.

All layers are connected in the central Model class. Each entity must have defined its own repository and mapper.

[1]: https://github.com/nextras/dbal
