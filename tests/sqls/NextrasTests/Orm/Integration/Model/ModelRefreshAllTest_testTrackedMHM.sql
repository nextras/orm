SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 3 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 2 LIMIT 1;
SELECT
  "books_x_tags"."book_id",
  "books_x_tags"."tag_id"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  "books_x_tags"."tag_id" IN (3);

SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (2, 3);
START TRANSACTION;
UPDATE "books" SET "title" = 'abc' WHERE "id" = 2;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (2, 3);
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
SELECT
  "books_x_tags"."book_id",
  "books_x_tags"."tag_id"
FROM
  "books" AS "books"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  "books_x_tags"."tag_id" IN (3);

SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (2, 3);
START TRANSACTION;
UPDATE "books" SET "title" = 'xyz' WHERE "id" = 2;
