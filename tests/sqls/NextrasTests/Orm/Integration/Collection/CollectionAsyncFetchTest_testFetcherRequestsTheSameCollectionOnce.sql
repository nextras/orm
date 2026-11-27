SELECT "books".* FROM "books" AS "books";
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books") temp;
