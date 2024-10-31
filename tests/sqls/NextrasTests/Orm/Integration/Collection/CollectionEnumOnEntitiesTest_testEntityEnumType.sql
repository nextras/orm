SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE "books"."genre" IN ('horror', 'thriller', 'sciFi', 'fantasy')) temp;
SELECT "books".* FROM "books" AS "books" WHERE "books"."genre" IN ('horror', 'thriller', 'sciFi', 'fantasy') ORDER BY "books"."id" ASC;
