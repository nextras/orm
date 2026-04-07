SELECT "books".* FROM "books" AS "books" ORDER BY "books"."id" ASC;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books") temp;
