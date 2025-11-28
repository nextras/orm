SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1 LIMIT 1;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 3 LIMIT 1;
START TRANSACTION;
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 3);
COMMIT;
SELECT "books".* FROM "books" AS "books" ORDER BY "books"."id" ASC;
(
  SELECT
    "books_x_tags"."tag_id",
    "books_x_tags"."book_id"
  FROM
    "tags" AS "tags"
    LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
      "books_x_tags"."tag_id" = "tags"."id"
    )
  WHERE
    "books_x_tags"."book_id" = 1
  ORDER BY
    "tags"."name" DESC
  LIMIT
    2
)
UNION ALL
  (
    SELECT
      "books_x_tags"."tag_id",
      "books_x_tags"."book_id"
    FROM
      "tags" AS "tags"
      LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
        "books_x_tags"."tag_id" = "tags"."id"
      )
    WHERE
      "books_x_tags"."book_id" = 2
    ORDER BY
      "tags"."name" DESC
    LIMIT
      2
  )
UNION ALL
  (
    SELECT
      "books_x_tags"."tag_id",
      "books_x_tags"."book_id"
    FROM
      "tags" AS "tags"
      LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
        "books_x_tags"."tag_id" = "tags"."id"
      )
    WHERE
      "books_x_tags"."book_id" = 3
    ORDER BY
      "tags"."name" DESC
    LIMIT
      2
  )
UNION ALL
  (
    SELECT
      "books_x_tags"."tag_id",
      "books_x_tags"."book_id"
    FROM
      "tags" AS "tags"
      LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
        "books_x_tags"."tag_id" = "tags"."id"
      )
    WHERE
      "books_x_tags"."book_id" = 4
    ORDER BY
      "tags"."name" DESC
    LIMIT
      2
  );

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2, 3);
(
  SELECT
    1 AS "book_id",
    COUNT(*) AS "count"
  FROM
    (
      SELECT
        "books_x_tags"."book_id"
      FROM
        "tags" AS "tags"
        LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
          "books_x_tags"."tag_id" = "tags"."id"
        )
      WHERE
        "book_id" = 1
      LIMIT
        2
    ) "temp"
)
UNION ALL
  (
    SELECT
      2 AS "book_id",
      COUNT(*) AS "count"
    FROM
      (
        SELECT
          "books_x_tags"."book_id"
        FROM
          "tags" AS "tags"
          LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
            "books_x_tags"."tag_id" = "tags"."id"
          )
        WHERE
          "book_id" = 2
        LIMIT
          2
      ) "temp"
  )
UNION ALL
  (
    SELECT
      3 AS "book_id",
      COUNT(*) AS "count"
    FROM
      (
        SELECT
          "books_x_tags"."book_id"
        FROM
          "tags" AS "tags"
          LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
            "books_x_tags"."tag_id" = "tags"."id"
          )
        WHERE
          "book_id" = 3
        LIMIT
          2
      ) "temp"
  )
UNION ALL
  (
    SELECT
      4 AS "book_id",
      COUNT(*) AS "count"
    FROM
      (
        SELECT
          "books_x_tags"."book_id"
        FROM
          "tags" AS "tags"
          LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
            "books_x_tags"."tag_id" = "tags"."id"
          )
        WHERE
          "book_id" = 4
        LIMIT
          2
      ) "temp"
  );
