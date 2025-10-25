SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1 LIMIT 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 3 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE ("books"."id" NOT IN (3)) AND ("books"."translator_id" IN (2));
SELECT "books".* FROM "books" AS "books" WHERE ("books"."id" NOT IN (3)) AND ("books"."translator_id" IN (2)) ORDER BY "books"."id" ASC LIMIT 1;
