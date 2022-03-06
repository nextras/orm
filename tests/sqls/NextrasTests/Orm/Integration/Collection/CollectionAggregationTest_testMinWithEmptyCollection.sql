START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Test 3', '2021-03-21 08:23:00.000000'::timestamp, 'http://www.example.com', NULL);
SELECT CURRVAL('authors_id_seq');
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" LEFT JOIN "books" AS "books__MIN" ON ("authors"."id" = "books__MIN"."author_id") GROUP BY "authors"."id" ORDER BY MIN("books__MIN"."price") ASC NULLS FIRST, "authors"."id" ASC;
