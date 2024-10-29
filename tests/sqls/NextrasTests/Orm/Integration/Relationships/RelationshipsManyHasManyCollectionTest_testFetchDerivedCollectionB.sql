SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
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
    "tags"."id" ASC
  LIMIT
    1
);

SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (1)));
