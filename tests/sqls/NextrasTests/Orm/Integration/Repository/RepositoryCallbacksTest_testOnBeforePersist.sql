START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Test', '2021-03-21 08:23:00.000000'::timestamp, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
INSERT INTO "publishers" ("name") VALUES ('Pub');
SELECT CURRVAL('public.publishers_publisher_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Test Book', 3, NULL, NULL, NULL, 4, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (3) ORDER BY "books"."id" DESC;
