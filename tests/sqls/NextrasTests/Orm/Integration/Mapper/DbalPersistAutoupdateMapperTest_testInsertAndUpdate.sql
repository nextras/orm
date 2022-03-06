START TRANSACTION;
INSERT INTO "book_collections" ("id", "name", "updated_at") VALUES (99, 'Test Collection 1', NULL) RETURNING "id", "updated_at";
COMMIT;
START TRANSACTION;
UPDATE "book_collections" SET "name" = 'Test Collection 11' WHERE "id" = 99 RETURNING "id", "updated_at";
COMMIT;
DELETE FROM book_collections WHERE id = 99;
START TRANSACTION;
UPDATE "book_collections" SET "name" = 'Test Collection 112' WHERE "id" = 99 RETURNING "id", "updated_at";
