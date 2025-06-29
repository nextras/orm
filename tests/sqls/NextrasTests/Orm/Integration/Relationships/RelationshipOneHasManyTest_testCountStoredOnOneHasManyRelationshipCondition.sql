SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1, 2);
SELECT
  "publisher_id",
  COUNT(DISTINCT "books"."id") as "count"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    "books_x_tags_any"."tag_id" = "tags_any"."id"
  )
WHERE
  ("tags_any"."id" = 1)
  AND (
    "books"."publisher_id" IN (1, 2)
  )
GROUP BY
  "publisher_id";

SELECT
  "publisher_id",
  COUNT(DISTINCT "books"."id") as "count"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    "books_x_tags_any"."tag_id" = "tags_any"."id"
  )
WHERE
  (
    ("books"."title" = 'Book 1')
    OR ("tags_any"."id" = 1)
  )
  AND (
    "books"."publisher_id" IN (1, 2)
  )
GROUP BY
  "publisher_id";

SELECT
  "publisher_id",
  COUNT(DISTINCT "books"."id") as "count"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    "books_x_tags_any"."tag_id" = "tags_any"."id"
  )
WHERE
  ("tags_any"."id" = 2)
  AND (
    "books"."publisher_id" IN (1, 2)
  )
GROUP BY
  "publisher_id";

SELECT
  "publisher_id",
  COUNT(DISTINCT "books"."id") as "count"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    "books_x_tags_any"."tag_id" = "tags_any"."id"
  )
WHERE
  (
    ("books"."title" = 'Book 2')
    OR ("tags_any"."id" = 2)
  )
  AND (
    "books"."publisher_id" IN (1, 2)
  )
GROUP BY
  "publisher_id";
