SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_any" ON (
    "authors"."id" = "books_any"."author_id"
  )
WHERE
  "books_any"."title" = 'Book 1'
GROUP BY
  "authors"."id";

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "authors"."id"
    FROM
      "public"."authors" AS "authors"
      LEFT JOIN "books" AS "books_any" ON (
        "authors"."id" = "books_any"."author_id"
      )
    WHERE
      "books_any"."title" = 'Book 1'
    GROUP BY
      "authors"."id"
  ) temp;

SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_any" ON (
    "authors"."id" = "books_any"."author_id"
  )
WHERE
  "books_any"."title" = 'Book 1'
GROUP BY
  "authors"."id";

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "authors"."id"
    FROM
      "public"."authors" AS "authors"
      LEFT JOIN "books" AS "books_any" ON (
        "authors"."id" = "books_any"."author_id"
      )
    WHERE
      "books_any"."title" = 'Book 1'
    GROUP BY
      "authors"."id"
  ) temp;
