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
  LEFT JOIN "books_x_tags" AS "books_x_tags__COUNT" ON (
    "books"."id" = "books_x_tags__COUNT"."book_id"
  )
  LEFT JOIN "tags" AS "tags__COUNT" ON (
    "books_x_tags__COUNT"."tag_id" = "tags__COUNT"."id"
  )
WHERE
  "tags_any"."id" IN (2, 3)
GROUP BY
  "books"."id"
HAVING
  COUNT("tags__COUNT"."id") > 1;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_any" ON (
    "books"."id" = "books_x_tags_any"."book_id"
  )
  LEFT JOIN "tags" AS "tags_any" ON (
    (
      "books_x_tags_any"."tag_id" = "tags_any"."id"
    )
    AND "tags_any"."id" IN (2, 3)
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags__COUNT" ON (
    "books"."id" = "books_x_tags__COUNT"."book_id"
  )
  LEFT JOIN "tags" AS "tags__COUNT" ON (
    "books_x_tags__COUNT"."tag_id" = "tags__COUNT"."id"
  )
GROUP BY
  "books"."id"
HAVING
  (
    COUNT("tags_any"."id") > 0
  )
  OR (
    COUNT("tags__COUNT"."id") > 1
  );
