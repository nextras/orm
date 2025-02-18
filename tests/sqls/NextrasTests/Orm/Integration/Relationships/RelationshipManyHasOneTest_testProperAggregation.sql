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
  LEFT JOIN "publishers" AS "publisher" ON (
    "books"."publisher_id" = "publisher"."publisher_id"
  )
WHERE
  ("tags_any"."id" = 1)
  AND (
    "publisher"."name" = 'Nextras publisher A'
  )
GROUP BY
  "books"."id";

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags_none" ON (
    "books"."id" = "books_x_tags_none"."book_id"
  )
  LEFT JOIN "tags" AS "tags_none" ON (
    (
      "books_x_tags_none"."tag_id" = "tags_none"."id"
    )
    AND "tags_none"."id" = 1
  )
  LEFT JOIN "publishers" AS "publisher" ON (
    "books"."publisher_id" = "publisher"."publisher_id"
  )
WHERE
  "publisher"."name" = 'Nextras publisher A'
GROUP BY
  "books"."id"
HAVING
  COUNT("tags_none"."id") = 0;
