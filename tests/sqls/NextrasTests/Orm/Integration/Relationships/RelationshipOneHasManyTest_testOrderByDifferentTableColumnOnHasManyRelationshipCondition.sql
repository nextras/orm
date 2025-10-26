SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1 LIMIT 1;
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
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
WHERE
  ("tags_any"."id" = 1)
  AND (
    "books"."publisher_id" IN (1)
  )
GROUP BY
  "books"."id",
  "author"."name"
ORDER BY
  "author"."name" ASC;
