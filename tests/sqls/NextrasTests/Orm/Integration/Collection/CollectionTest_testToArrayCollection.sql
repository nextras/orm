SELECT "authors".* FROM "public"."authors" AS "authors";
SELECT COUNT(*) AS count FROM (SELECT "authors"."id" FROM "public"."authors" AS "authors") temp;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC;
SELECT "author_id", COUNT(DISTINCT "count") as "count" FROM (SELECT "books"."author_id", "books"."id" AS "count" FROM "books" AS "books" WHERE "books"."author_id" IN (1)) AS "temp" GROUP BY "author_id";
