SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 1));
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE (("publishers"."publisher_id" = 1));
START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('GoTEAN', 2);
SELECT CURRVAL('eans_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('GoT', 1, NULL, NULL, 1, 1, '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('books_id_seq');
COMMIT;
SELECT COUNT(*) AS count FROM (SELECT "eans"."id" FROM "eans" AS "eans" INNER JOIN "books" AS "book" ON (("eans"."id" = "book"."ean_id" AND "book"."title" = 'GoT') AND ("eans"."id" = "book"."ean_id")) WHERE (((1=1)))) temp;
SELECT "eans".* FROM "eans" AS "eans" INNER JOIN "books" AS "book" ON (("eans"."id" = "book"."ean_id" AND "book"."title" = 'GoT') AND ("eans"."id" = "book"."ean_id")) WHERE (((1=1))) ORDER BY "book"."title" ASC;
