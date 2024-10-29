SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 3));
START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('123', 2);
SELECT CURRVAL('public.eans_id_seq');
UPDATE "books" SET "ean_id" = 1 WHERE "id" = 3;
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 1));
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE (("publishers"."publisher_id" = 1));
SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 4));
START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('456', 2);
SELECT CURRVAL('public.eans_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Book 5', 1, NULL, 4, 2, 1, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books" AS "nextPart" ON (
    "books"."next_part" = "nextPart"."id"
  )
  LEFT JOIN "eans" AS "nextPart_ean" ON (
    "nextPart"."ean_id" = "nextPart_ean"."id"
  )
  LEFT JOIN "books" AS "previousPart" ON (
    "books"."id" = "previousPart"."next_part"
  )
  LEFT JOIN "eans" AS "previousPart_ean" ON (
    "previousPart"."ean_id" = "previousPart_ean"."id"
  )
WHERE
  (
    ("nextPart_ean"."code" = '123')
    AND (
      "previousPart_ean"."code" = '456'
    )
  );
