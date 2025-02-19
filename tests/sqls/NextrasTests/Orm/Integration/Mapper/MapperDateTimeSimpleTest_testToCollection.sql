START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Random Author', '2018-01-09'::date, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 3;
