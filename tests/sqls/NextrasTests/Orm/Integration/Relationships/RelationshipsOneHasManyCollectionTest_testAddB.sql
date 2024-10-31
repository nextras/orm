SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC;
START TRANSACTION;
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('New Book #1', 1, NULL, NULL, NULL, 1, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC;
COMMIT;
