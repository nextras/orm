SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_1" ON (
    "books"."id" = "books_x_tags_1"."book_id"
  )
  LEFT JOIN "tags" AS "tags_1" ON (
    "books_x_tags_1"."tag_id" = "tags_1"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags_2" ON (
    "books"."id" = "books_x_tags_2"."book_id"
  )
  LEFT JOIN "tags" AS "tags_2" ON (
    "books_x_tags_2"."tag_id" = "tags_2"."id"
  )
WHERE
  ("tags_1"."id" = 1)
  AND ("tags_2"."id" = 2)
GROUP BY
  "books"."id";

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_3" ON (
    "books"."id" = "books_x_tags_3"."book_id"
  )
  LEFT JOIN "tags" AS "tags_3" ON (
    "books_x_tags_3"."tag_id" = "tags_3"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags__COUNT" ON (
    "books"."id" = "books_x_tags__COUNT"."book_id"
  )
  LEFT JOIN "tags" AS "tags__COUNT" ON (
    "books_x_tags__COUNT"."tag_id" = "tags__COUNT"."id"
  )
WHERE
  "tags_3"."id" = 3
GROUP BY
  "books"."id"
HAVING
  COUNT("tags__COUNT"."id") = 1;
