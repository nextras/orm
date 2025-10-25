SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 2 LIMIT 1;
UPDATE "books" SET "title" = 'foo' WHERE id = 1;
UPDATE "books" SET "title" = 'bar' WHERE id = 2;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (1, 2);
