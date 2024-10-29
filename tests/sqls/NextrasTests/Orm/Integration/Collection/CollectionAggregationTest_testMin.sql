SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books__MIN" ON (
    "authors"."id" = "books__MIN"."author_id"
  )
GROUP BY
  "authors"."id"
HAVING
  (
    MIN("books__MIN"."price") < 50
  )
ORDER BY
  "authors"."id" ASC;
