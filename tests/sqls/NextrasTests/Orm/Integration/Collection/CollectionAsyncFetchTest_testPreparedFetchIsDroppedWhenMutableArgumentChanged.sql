SELECT "books".* FROM "books" AS "books" WHERE "published_at" >= '1999-12-31 23:00:00.000000'::timestamptz ORDER BY "books"."id" ASC;
SELECT "books".* FROM "books" AS "books" WHERE "published_at" >= '2099-12-31 23:00:00.000000'::timestamptz ORDER BY "books"."id" ASC;
