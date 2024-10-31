SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags__COUNT" ON (
    "books"."id" = "books_x_tags__COUNT"."book_id"
  )
  LEFT JOIN "tags" AS "tags__COUNT" ON (
    "books_x_tags__COUNT"."tag_id" = "tags__COUNT"."id"
  )
GROUP BY
  "author"."name",
  "books"."id"
HAVING
  ("author"."name" = 'Writer 1')
  OR (
    COUNT("tags__COUNT"."id") > 0
  );
