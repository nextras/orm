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
      (
        "tags_any"."id" IN (1)
      )
      OR ("tags_any"."id" IS NULL)
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
  (
    "tags_any"."id" IN (1)
  )
  OR ("tags_any"."id" IS NULL)
GROUP BY
  "books"."id";
