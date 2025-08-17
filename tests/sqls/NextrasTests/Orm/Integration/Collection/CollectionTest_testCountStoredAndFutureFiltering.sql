SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "books"."id"
    FROM
      "books" AS "books"
      LEFT JOIN "public"."authors" AS "author" ON (
        "books"."author_id" = "author"."id"
      )
    WHERE
      "author"."id" > 0
  ) temp;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
WHERE
  "author"."id" > 0
ORDER BY
  "author"."id" ASC;
