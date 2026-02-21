SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 2;
SELECT
  "books_x_tags"."book_id",
  "books_x_tags"."tag_id"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  "books_x_tags"."tag_id" IN (2);

SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (1, 2);
SELECT
  "publishers_x_tags"."publisher_id",
  "publishers_x_tags"."tag_id"
FROM
  "publishers" AS "publishers"
  LEFT JOIN "publishers_x_tags" AS "publishers_x_tags" ON (
    "publishers_x_tags"."publisher_id" = "publishers"."publisher_id"
  )
WHERE
  "publishers_x_tags"."tag_id" IN (2);

SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."tag_id" IN (2);
START TRANSACTION;
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((1, 2));
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((2, 2));
DELETE FROM "tag_followers" WHERE "author_id" = 2 AND "tag_id" = 2;
DELETE FROM "tags" WHERE "id" = 2;
