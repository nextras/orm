START TRANSACTION;
UPDATE books SET title = 'uncommitted' WHERE id = 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 2;
ROLLBACK;
