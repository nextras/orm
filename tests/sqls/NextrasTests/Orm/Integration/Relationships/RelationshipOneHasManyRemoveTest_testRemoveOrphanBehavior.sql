SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (2) ORDER BY "books"."id" DESC;
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (4, 3);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (3);
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (3);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
START TRANSACTION;
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((3, 3));
DELETE FROM "books" WHERE "id" = 4;
DELETE FROM "books" WHERE "id" = 3;
COMMIT;
SELECT "author_id", COUNT(DISTINCT "books"."id") as "count" FROM "books" AS "books" WHERE "books"."author_id" IN (2) GROUP BY "author_id";
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."publisher_id" IN (1);
