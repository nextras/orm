START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Dave Lister', '2021-03-21 08:23:00.000000'::timestamp, 'http://www.example.com', NULL);
SELECT CURRVAL('"authors_id_seq"');
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Arnold Judas Rimmer', '2021-03-21 08:23:00.000000'::timestamp, 'http://www.example.com', 3);
SELECT CURRVAL('"authors_id_seq"');
INSERT INTO "publishers" ("name") VALUES ('Jupiter Mining Corporation');
SELECT CURRVAL('"publishers_publisher_id_seq"');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Better Than Life', 4, 3, NULL, NULL, 4, '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('"books_id_seq"');
