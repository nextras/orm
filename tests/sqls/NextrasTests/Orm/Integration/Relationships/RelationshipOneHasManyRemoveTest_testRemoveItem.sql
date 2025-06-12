SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 3;
START TRANSACTION;
UPDATE "books" SET "translator_id" = NULL WHERE "id" = 3;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."translator_id" IN (2);
SELECT "translator_id", COUNT(DISTINCT "__nextras_fix_id_count") as "count" FROM (SELECT "books"."translator_id", "books"."id" AS "__nextras_fix_id_count" FROM "books" AS "books" WHERE "books"."translator_id" IN (2)) AS "temp" GROUP BY "translator_id";
