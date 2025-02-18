SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
ORDER BY
  "author"."name" DESC,
  "books"."id" DESC;
