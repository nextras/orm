SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (1);

SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (1, 2)));
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('New Tag #1', 'y');
SELECT CURRVAL('public.tags_id_seq');
INSERT INTO "tags" ("name", "is_global") VALUES ('New Tag #2', 'y');
SELECT CURRVAL('public.tags_id_seq');
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 4), (1, 5);
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (1);

SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."id" IN (1, 2, 4, 5)));
COMMIT;
