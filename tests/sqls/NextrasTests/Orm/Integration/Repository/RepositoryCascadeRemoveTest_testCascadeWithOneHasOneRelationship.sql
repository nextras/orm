START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Test', '2021-03-21 00:00:00.000000'::timestamp, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
INSERT INTO "eans" ("code", "type") VALUES ('TEST', 2);
SELECT CURRVAL('public.eans_id_seq');
INSERT INTO "publishers" ("name") VALUES ('Test Publisher');
SELECT CURRVAL('public.publishers_publisher_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Title', 3, NULL, NULL, 1, 4, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (5);

SELECT "books".* FROM "books" AS "books" WHERE "books"."next_part" IN (5);
START TRANSACTION;
DELETE FROM "books" WHERE "id" = 5;
DELETE FROM "eans" WHERE "id" = 1;
COMMIT;
