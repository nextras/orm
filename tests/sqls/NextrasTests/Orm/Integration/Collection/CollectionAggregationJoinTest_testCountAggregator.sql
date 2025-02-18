SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_count" ON (
    (
      "authors"."id" = "books_count"."author_id"
    )
    AND "books_count"."price" >= 50
  )
GROUP BY
  "authors"."id"
HAVING
  COUNT("books_count"."id") >= 2;

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "authors"."id"
    FROM
      "public"."authors" AS "authors"
      LEFT JOIN "books" AS "books_count" ON (
        (
          "authors"."id" = "books_count"."author_id"
        )
        AND "books_count"."price" >= 50
      )
    GROUP BY
      "authors"."id"
    HAVING
      COUNT("books_count"."id") >= 2
  ) temp;

SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_count" ON (
    (
      "authors"."id" = "books_count"."author_id"
    )
    AND "books_count"."price" >= 51
  )
GROUP BY
  "authors"."id"
HAVING
  COUNT("books_count"."id") <= 1;

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "authors"."id"
    FROM
      "public"."authors" AS "authors"
      LEFT JOIN "books" AS "books_count" ON (
        (
          "authors"."id" = "books_count"."author_id"
        )
        AND "books_count"."price" >= 51
      )
    GROUP BY
      "authors"."id"
    HAVING
      COUNT("books_count"."id") <= 1
  ) temp;
