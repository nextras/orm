SELECT "books".* FROM "books" AS "books" ORDER BY "books"."id" ASC;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1, 2);
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 1') AND ("books"."translator_id" IN (1, 2)) ORDER BY "books"."id" ASC;
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."translator_id" IN (1, 2)) ORDER BY "books"."id" ASC;
SELECT "translator_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 1') AND ("books"."translator_id" IN (1, 2)) GROUP BY "translator_id";
SELECT "translator_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."translator_id" IN (1, 2)) GROUP BY "translator_id";
