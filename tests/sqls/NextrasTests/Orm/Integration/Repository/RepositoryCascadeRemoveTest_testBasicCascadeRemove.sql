SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1;
START TRANSACTION;
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Book 5', 1, 2, NULL, NULL, 1, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 3;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (2) ORDER BY "books"."id" DESC;
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (2);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."favorite_author_id" IN (2);
SELECT "books".* FROM "books" AS "books" WHERE "books"."translator_id" IN (2);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (2);
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (4, 3);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (3);
SELECT "books".* FROM "books" AS "books" WHERE "books"."next_part" IN (4, 3);
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1, 3);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (2);
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2);
START TRANSACTION;
UPDATE "books" SET "translator_id" = NULL WHERE "id" = 5;
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((3, 3));
DELETE FROM "books" WHERE "id" = 4;
DELETE FROM "books" WHERE "id" = 3;
DELETE FROM "tag_followers" WHERE "author_id" = 2 AND "tag_id" = 2;
DELETE FROM "public"."authors" WHERE "id" = 2;
COMMIT;
