SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 2;
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

SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (1, 2);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
START TRANSACTION;
UPDATE "books" SET "author_id" = 2 WHERE "id" = 1;
UPDATE "books" SET "author_id" = 2 WHERE "id" = 2;
COMMIT;
SELECT "author_id", COUNT(DISTINCT "__nextras_fix_id_count") as "count" FROM (SELECT "books"."author_id", "books"."id" AS "__nextras_fix_id_count" FROM "books" AS "books" WHERE "books"."author_id" IN (1)) AS "temp" GROUP BY "author_id";
