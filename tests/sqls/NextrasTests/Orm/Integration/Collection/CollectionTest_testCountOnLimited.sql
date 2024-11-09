SELECT "books".* FROM "books" AS "books" ORDER BY "books"."id" ASC LIMIT 1 OFFSET 1;
SELECT "books".* FROM "books" AS "books" ORDER BY "books"."id" ASC LIMIT 1 OFFSET 10;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" LIMIT 1 OFFSET 1) temp;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" LIMIT 1 OFFSET 10) temp;
