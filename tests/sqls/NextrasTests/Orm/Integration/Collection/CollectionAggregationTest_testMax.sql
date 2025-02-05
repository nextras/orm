SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books__MAX" ON (
    "authors"."id" = "books__MAX"."author_id"
  )
GROUP BY
  "authors"."id"
HAVING
  MAX("books__MAX"."price") > 150
ORDER BY
  "authors"."id" ASC;
