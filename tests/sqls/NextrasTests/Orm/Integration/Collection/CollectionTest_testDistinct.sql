SELECT
  "tag_followers".*
FROM
  "tag_followers" AS "tag_followers"
  LEFT JOIN "tags" AS "tag" ON (
    "tag_followers"."tag_id" = "tag"."id"
  )
  LEFT JOIN "books_x_tags" AS "tag_books_x_tags_any" ON (
    "tag"."id" = "tag_books_x_tags_any"."tag_id"
  )
  LEFT JOIN "books" AS "tag_books_any" ON (
    "tag_books_x_tags_any"."book_id" = "tag_books_any"."id"
  )
WHERE
  (
    ("tag_books_any"."id" = 1)
  )
GROUP BY
  "tag_followers"."tag_id",
  "tag_followers"."author_id";
