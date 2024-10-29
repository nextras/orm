SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books__SUM" ON (
    "authors"."id" = "books__SUM"."author_id"
  )
GROUP BY
  "authors"."id"
HAVING
  (
    SUM("books__SUM"."price") <= 200
  )
ORDER BY
  "authors"."id" ASC;
