SELECT "books".* FROM "books" AS "books" WHERE (("books"."price" >= 1000));
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE (("books"."price" >= 1000))) temp;
SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
START TRANSACTION;
UPDATE "books" SET "price" = 1000, "price_currency" = 'CZK' WHERE "id" = 1;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE (("books"."price" >= 1000));
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE (("books"."price" >= 1000))) temp;
