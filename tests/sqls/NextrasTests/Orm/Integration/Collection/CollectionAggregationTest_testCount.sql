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
  COUNT("tags__COUNT"."id") >= 2
ORDER BY
  "books"."id" ASC;

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
ORDER BY
  COUNT("tags__COUNT"."id") ASC,
  "books"."id" ASC;

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
ORDER BY
  COUNT("tags__COUNT"."id") DESC,
  "books"."id" DESC;
