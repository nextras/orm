SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" = 1));
START TRANSACTION;
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((1, 1));
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
  "books_x_tags"."book_id" IN (1);

SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (2)));
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
  "books_x_tags"."book_id" IN (1)
GROUP BY
  "books_x_tags"."book_id";
