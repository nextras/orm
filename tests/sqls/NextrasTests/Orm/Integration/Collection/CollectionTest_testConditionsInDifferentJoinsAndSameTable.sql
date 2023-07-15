SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 1));
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 2));
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE (("publishers"."publisher_id" = 1));
START TRANSACTION;
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Books 5', 1, 2, NULL, NULL, 1, '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('books_id_seq');
COMMIT;
SELECT "books".* FROM "books" AS "books" INNER JOIN "public"."authors" AS "author" ON ("books"."author_id" = "author"."id" AND "author"."name" = 'Writer 1') INNER JOIN "public"."authors" AS "translator" ON ("books"."translator_id" = "translator"."id" AND "translator"."web" = 'http://example.com/2') WHERE (((1=1)) AND ((1=1)));
