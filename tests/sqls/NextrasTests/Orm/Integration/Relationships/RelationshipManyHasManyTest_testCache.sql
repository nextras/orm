SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1;
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  ("tags"."name" != 'Tag 1')
  AND (
    "books_x_tags"."book_id" IN (1)
  )
ORDER BY
  "tags"."id" ASC;

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2);
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
  ("tags"."name" != 'Tag 1')
  AND (
    "books_x_tags"."book_id" IN (1)
  )
GROUP BY
  "books_x_tags"."book_id";

SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  ("tags"."name" != 'Tag 3')
  AND (
    "books_x_tags"."book_id" IN (1)
  )
ORDER BY
  "tags"."id" ASC;

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1, 2);
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
  ("tags"."name" != 'Tag 3')
  AND (
    "books_x_tags"."book_id" IN (1)
  )
GROUP BY
  "books_x_tags"."book_id";
