SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE (("books"."published_at" = '2021-12-14 21:10:04.000000'::timestamp))) temp;
SELECT "books".* FROM "books" AS "books" WHERE (("books"."published_at" = '2021-12-14 21:10:04.000000'::timestamp));
