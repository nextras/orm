SELECT "books".* FROM "books" AS "books";
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (1, 2, 3, 4);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1, 2, 3);
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
    ("tags"."id" = 1)
    AND ("books_x_tags"."book_id" = 2)
  LIMIT
    1
);

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
    ("tags"."id" = 2)
    AND ("books_x_tags"."book_id" = 2)
  LIMIT
    1
);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2);
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
    ("tags"."id" = 3)
    AND ("books_x_tags"."book_id" = 2)
  LIMIT
    1
);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 4 LIMIT 1;
