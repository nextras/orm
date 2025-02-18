# Contributing

If you want to fix a bug or implement a feature, you are welcome to do so. But read this first before implementing something.

Possible contributions:

- bugfixes (visual bugs, documentation bugs, API bugs),
- modifications to existing components,
- adding more documentation,
- implementing new components.

Please always open an issue first to be sure your work will be accepted (with the exception to documentation bugs). Especially all feature development (changes/new components/API) has to be discussed first. Thank you for understanding.

## Testing code changes locally

- PHPStan checks have to pass, run `composer phpstan`.
- Unit & integration have to pass, run `composer tests`.
- Commit generated/changed SQLs for all tests. These SQL files are generated only for Postgres, so it is required to run tests at least for this database.

To set up tests configuration, copy [databases.sample.ini](tests/databases.sample.ini) file, name it `database.ini` and updated sections with proper database connection credentials. You may comment out those sections not to run them locally.
