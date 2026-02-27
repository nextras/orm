SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_any" ON (
    "authors"."id" = "books_any"."author_id"
  )
WHERE
  ("books_any"."title" = 'Book 1')
  AND (
    "books_any"."translator_id" IS NULL
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
      LEFT JOIN "books" AS "books_any" ON (
        "authors"."id" = "books_any"."author_id"
      )
    WHERE
      ("books_any"."title" = 'Book 1')
      AND (
        "books_any"."translator_id" IS NULL
      )
    GROUP BY
      "authors"."id"
  ) temp;

SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_count" ON (
    (
      (
        "authors"."id" = "books_count"."author_id"
      )
      AND "books_count"."translator_id" IS NOT NULL
    )
    OR (
      (
        "authors"."id" = "books_count"."author_id"
      )
      AND "books_count"."price" < 100
    )
  )
GROUP BY
  "authors"."id"
HAVING
  (
    COUNT("books_count"."id") >= 1
    AND COUNT("books_count"."id") <= 1
  )
  OR (
    COUNT("books_count"."id") >= 1
    AND COUNT("books_count"."id") <= 1
  );

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
          (
            "authors"."id" = "books_count"."author_id"
          )
          AND "books_count"."translator_id" IS NOT NULL
        )
        OR (
          (
            "authors"."id" = "books_count"."author_id"
          )
          AND "books_count"."price" < 100
        )
      )
    GROUP BY
      "authors"."id"
    HAVING
      (
        COUNT("books_count"."id") >= 1
        AND COUNT("books_count"."id") <= 1
      )
      OR (
        COUNT("books_count"."id") >= 1
        AND COUNT("books_count"."id") <= 1
      )
  ) temp;
