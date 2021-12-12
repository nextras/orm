START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Random Author', '2018-01-09 00:00:00.000000'::timestamp, 'http://www.example.com', NULL);
SELECT CURRVAL('authors_id_seq');
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 3));
