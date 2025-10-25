SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE ("books"."translator_id" IS NULL) AND ("books"."author_id" IN (1)) ORDER BY "books"."id" DESC;
START TRANSACTION;
UPDATE "books" SET "translator_id" = 1 WHERE "id" = 2;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE ("books"."translator_id" IS NULL) AND ("books"."author_id" IN (1)) ORDER BY "books"."id" DESC;
