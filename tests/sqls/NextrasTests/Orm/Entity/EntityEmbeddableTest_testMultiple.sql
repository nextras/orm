SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1 LIMIT 1;
START TRANSACTION;
UPDATE "books" SET "price" = 1000, "price_currency" = 'CZK', "orig_price_cents" = 330, "orig_price_currency" = 'EUR' WHERE "id" = 1;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1 LIMIT 1;
