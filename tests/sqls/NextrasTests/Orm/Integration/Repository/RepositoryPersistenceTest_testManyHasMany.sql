START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('0', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('1', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('2', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('3', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('4', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('5', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('6', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('7', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('8', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "tags" ("name", "is_global") VALUES ('9', 'y');
SELECT CURRVAL('public.tags_id_seq');
COMMIT;
SELECT "tags".* FROM "tags" AS "tags";
START TRANSACTION;
INSERT INTO "publishers" ("name") VALUES ('Nextras Publisher');
SELECT CURRVAL('public.publishers_publisher_id_seq');
INSERT INTO "publishers_x_tags" ("publisher_id", "tag_id") VALUES (4, 1), (4, 2), (4, 3), (4, 4), (4, 5), (4, 6), (4, 7), (4, 8), (4, 9), (4, 10), (4, 11), (4, 12), (4, 13);
COMMIT;
SELECT "tags".* FROM "tags" AS "tags" ORDER BY "tags"."id" ASC;
START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('A2', '2021-03-21'::date, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
INSERT INTO "publishers" ("name") VALUES ('P2');
SELECT CURRVAL('public.publishers_publisher_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Some Book Title', 3, NULL, NULL, NULL, 5, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (5, 1);
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
  "books_x_tags"."book_id" IN (5);

SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1);
