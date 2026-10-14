SELECT "authors".* FROM "public"."authors" AS "authors" ORDER BY "authors"."id" ASC;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1, 2) ORDER BY "books"."id" DESC;
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (4, 3, 2, 1)
ORDER BY
  "tags"."id" ASC;

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1, 2, 3);
