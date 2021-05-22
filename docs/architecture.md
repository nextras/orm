## Architecture

Orm is clearly designed to abstract entities from database implementation; Orm separates your model into three basic layers:
- **Entities**  
 [Entities](entity) are data crates, hold your data, validate values and provide API for value encapsulation, e.g. for relationships.
- **Repositories**  
 [Repositories](repository) form a layer which takes care about your entities. Repositories manage entities and provide an API for their retrieving, filtering and persisting.
- **Mappers**  
 [Mappers](mapper) are the backend of Orm. Mappers provide interaction with the database layer. Orm users [Nextras\Dbal][1] database library as abstraction layer for database connection.

All layers are connected in the central Model class. Each entity must have defined its own repository and mapper.

[1]: https://github.com/nextras/dbal
