SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (1);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1, 2);
SELECT "books".* FROM "books" AS "books" WHERE "books"."next_part" IN (1);
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1);
START TRANSACTION;
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Book 1', 1, 1, NULL, NULL, 1, 'sciFi', '2021-12-14 21:10:04.000000'::timestamp, NULL, NULL, 50, 'CZK', NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (5, 1), (5, 2);
COMMIT;
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
