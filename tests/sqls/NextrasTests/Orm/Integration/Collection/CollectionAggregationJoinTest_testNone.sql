SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_none" ON (
    (
      "authors"."id" = "books_none"."author_id"
    )
    AND "books_none"."title" = 'Book 1'
  )
GROUP BY
  "authors"."id"
HAVING
  COUNT("books_none"."id") = 0;

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "authors"."id"
    FROM
      "public"."authors" AS "authors"
      LEFT JOIN "books" AS "books_none" ON (
        (
          "authors"."id" = "books_none"."author_id"
        )
        AND "books_none"."title" = 'Book 1'
      )
    GROUP BY
      "authors"."id"
    HAVING
      COUNT("books_none"."id") = 0
  ) temp;
