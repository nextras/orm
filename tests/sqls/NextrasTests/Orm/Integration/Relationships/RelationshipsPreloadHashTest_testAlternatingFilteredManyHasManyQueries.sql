SELECT "books".* FROM "books" AS "books" ORDER BY "books"."id" ASC;
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
    "books_x_tags"."book_id" IN (1, 2, 3, 4)
  )
ORDER BY
  "tags"."id" ASC;

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2, 3);
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
    "books_x_tags"."book_id" IN (1, 2, 3, 4)
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
  ("tags"."name" != 'Tag 1')
  AND (
    "books_x_tags"."book_id" IN (1, 2, 3, 4)
  )
GROUP BY
  "books_x_tags"."book_id";

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
    "books_x_tags"."book_id" IN (1, 2, 3, 4)
  )
GROUP BY
  "books_x_tags"."book_id";
