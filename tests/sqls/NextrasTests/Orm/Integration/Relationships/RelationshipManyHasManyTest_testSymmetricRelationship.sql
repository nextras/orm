SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" = 2));
SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 2));
SELECT
  "books_x_tags"."book_id",
  "books_x_tags"."tag_id"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  "books_x_tags"."tag_id" IN (2);

SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" IN (1, 2)));
