SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1);
START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Jon Snow', '2021-03-21'::date, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('A new book', 3, NULL, NULL, NULL, 1, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
