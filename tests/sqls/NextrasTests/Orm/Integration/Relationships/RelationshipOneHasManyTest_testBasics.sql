SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 1') AND ("books"."author_id" IN (1)) ORDER BY "books"."id" DESC;
SELECT "author_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 1') AND ("books"."author_id" IN (1)) GROUP BY "author_id";
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1)) ORDER BY "books"."id" DESC;
SELECT "author_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1)) GROUP BY "author_id";
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1)) ORDER BY "books"."id" ASC;
SELECT "author_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1)) GROUP BY "author_id";
