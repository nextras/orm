SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books_any" ON (
    (
      "authors"."id" = "books_any"."author_id"
    )
    AND "books_any"."title" = 'Book 1'
  )
  LEFT JOIN "tag_followers" AS "tagFollowers__COUNT" ON (
    "authors"."id" = "tagFollowers__COUNT"."author_id"
  )
GROUP BY
  "authors"."id"
HAVING
  (
    (
      (
        COUNT("books_any"."id") > 0
      )
    )
    OR (
      COUNT("tagFollowers__COUNT"."tag_id") <= 2
    )
  );

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "authors"."id"
    FROM
      "public"."authors" AS "authors"
      LEFT JOIN "books" AS "books_any" ON (
        (
          "authors"."id" = "books_any"."author_id"
        )
        AND "books_any"."title" = 'Book 1'
      )
      LEFT JOIN "tag_followers" AS "tagFollowers__COUNT" ON (
        "authors"."id" = "tagFollowers__COUNT"."author_id"
      )
    GROUP BY
      "authors"."id"
    HAVING
      (
        (
          (
            COUNT("books_any"."id") > 0
          )
        )
        OR (
          COUNT("tagFollowers__COUNT"."tag_id") <= 2
        )
      )
  ) temp;
