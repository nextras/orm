SELECT "books".* FROM "books" AS "books" WHERE "books"."id" = 1 LIMIT 1;
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

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1, 2);
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  (
    "tags"."id" NOT IN (1)
  )
  AND (
    "books_x_tags"."book_id" IN (1)
  );

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2);
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('Test tag', 'y');
SELECT CURRVAL('public.tags_id_seq');
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((1, 1));
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 4);
COMMIT;
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

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2, 4);
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  (
    "tags"."id" NOT IN (2, 4)
  )
  AND (
    "books_x_tags"."book_id" IN (1)
  );

START TRANSACTION;
DELETE FROM "books_x_tags" WHERE ("book_id", "tag_id") IN ((1, 2), (1, 4));
COMMIT;
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
