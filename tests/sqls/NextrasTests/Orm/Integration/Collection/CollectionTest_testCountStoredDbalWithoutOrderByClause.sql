SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books") temp;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" LIMIT 10 OFFSET 0) temp;
