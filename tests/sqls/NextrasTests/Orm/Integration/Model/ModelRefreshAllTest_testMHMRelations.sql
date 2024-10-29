SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 2));
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (2);

SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (2, 3)));
DELETE FROM "books_x_tags" WHERE book_id = 2 AND tag_id = 3;
SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" IN (2)));
SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (2, 3)));
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (2);

SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (2)));
