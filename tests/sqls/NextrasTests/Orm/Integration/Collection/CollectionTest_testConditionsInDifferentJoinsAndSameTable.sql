SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (2);
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1);
START TRANSACTION;
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Books 5', 1, 2, NULL, NULL, 1, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
  LEFT JOIN "public"."authors" AS "translator" ON (
    "books"."translator_id" = "translator"."id"
  )
WHERE
  ("author"."name" = 'Writer 1')
  AND (
    "translator"."web" = 'http://example.com/2'
  );
