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
    AND "tags_none"."id" = 2
  )
GROUP BY
  "books"."title",
  "books"."id"
HAVING
  ("books"."title" = 'Book 1')
  OR (
    COUNT("tags_none"."id") = 0
  );

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
    AND "tags_none"."id" = 2
  )
WHERE
  "books"."title" = 'Book 1'
GROUP BY
  "books"."id"
HAVING
  COUNT("tags_none"."id") = 0;

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
    AND "tags_none"."id" = 3
  )
WHERE
  "books"."title" = 'Book 1'
GROUP BY
  "books"."id"
HAVING
  COUNT("tags_none"."id") = 0;
