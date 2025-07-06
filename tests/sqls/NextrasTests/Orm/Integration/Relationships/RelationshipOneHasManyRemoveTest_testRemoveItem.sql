SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 3;
START TRANSACTION;
UPDATE "books" SET "translator_id" = NULL WHERE "id" = 3;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."translator_id" IN (2);
SELECT "translator_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE "books"."translator_id" IN (2) GROUP BY "translator_id";
