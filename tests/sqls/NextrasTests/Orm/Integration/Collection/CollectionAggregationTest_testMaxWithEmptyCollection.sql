START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Test 3', '2021-03-21 08:23:00.000000'::timestamp, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" LEFT JOIN "books" AS "books__MAX" ON ("authors"."id" = "books__MAX"."author_id") GROUP BY "authors"."id" ORDER BY MAX("books__MAX"."price") ASC NULLS FIRST, "authors"."id" ASC;
