START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Jon Snow', '2021-03-21'::date, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
INSERT INTO "publishers" ("name") VALUES ('7K');
SELECT CURRVAL('public.publishers_publisher_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "count", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('A new book', 3, NULL, NULL, NULL, 4, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, 0, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
INSERT INTO "tags" ("name", "is_global", "count") VALUES ('Awesome', 'y', 0);
SELECT CURRVAL('public.tags_id_seq');
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (5, 4);
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

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (4);
SELECT
  "books_x_tags"."book_id",
  COUNT(
    DISTINCT "books_x_tags"."tag_id"
  ) AS "count"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (5)
GROUP BY
  "books_x_tags"."book_id";
