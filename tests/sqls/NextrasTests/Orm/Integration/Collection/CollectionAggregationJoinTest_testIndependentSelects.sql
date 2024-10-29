SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_any1" ON (
    "authors"."id" = "books_any1"."author_id"
  )
  LEFT JOIN "books" AS "books_any2" ON (
    "authors"."id" = "books_any2"."author_id"
  )
WHERE
  (
    (
      ("books_any1"."title" = 'Book 1')
      AND ("books_any1"."price" = 50)
    )
    AND (
      ("books_any2"."title" = 'Book 2')
      AND ("books_any2"."price" = 150)
    )
  )
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
      LEFT JOIN "books" AS "books_any1" ON (
        "authors"."id" = "books_any1"."author_id"
      )
      LEFT JOIN "books" AS "books_any2" ON (
        "authors"."id" = "books_any2"."author_id"
      )
    WHERE
      (
        (
          ("books_any1"."title" = 'Book 1')
          AND ("books_any1"."price" = 50)
        )
        AND (
          ("books_any2"."title" = 'Book 2')
          AND ("books_any2"."price" = 150)
        )
      )
    GROUP BY
      "authors"."id"
  ) temp;
