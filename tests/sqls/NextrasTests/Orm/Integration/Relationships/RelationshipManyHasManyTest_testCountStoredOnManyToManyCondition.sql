SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "books"."id"
    FROM
      "books" AS "books"
      LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
        "books"."id" = "books_x_tags_any"."book_id"
      )
      LEFT JOIN "tags" AS "tags_any" ON (
        "books_x_tags_any"."tag_id" = "tags_any"."id"
      )
    WHERE
      "tags_any"."name" = 'Tag 2'
    GROUP BY
      "books"."id"
  ) temp;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    "books_x_tags_any"."tag_id" = "tags_any"."id"
  )
WHERE
  "tags_any"."name" = 'Tag 2'
GROUP BY
  "books"."id";

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "tags"."id"
    FROM
      "tags" AS "tags"
      LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
        "tags"."id" = "books_x_tags_any"."tag_id"
      )
      LEFT JOIN "books" AS "books_any" ON (
        "books_x_tags_any"."book_id" = "books_any"."id"
      )
    WHERE
      "books_any"."id" IN (1, 2)
    GROUP BY
      "tags"."id"
  ) temp;
