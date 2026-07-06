SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags__COUNT" ON (
    "books"."id" = "books_x_tags__COUNT"."book_id"
  )
  LEFT JOIN "tags" AS "tags__COUNT" ON (
    "books_x_tags__COUNT"."tag_id" = "tags__COUNT"."id"
  )
GROUP BY
  "books"."id"
HAVING
  COUNT("tags__COUNT"."id") > 0;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags__COUNT" ON (
    "books"."id" = "books_x_tags__COUNT"."book_id"
  )
  LEFT JOIN "tags" AS "tags__COUNT" ON (
    "books_x_tags__COUNT"."tag_id" = "tags__COUNT"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    "books_x_tags_any"."tag_id" = "tags_any"."id"
  )
GROUP BY
  "books"."id"
HAVING
  COUNT("tags__COUNT"."id") > 0;
