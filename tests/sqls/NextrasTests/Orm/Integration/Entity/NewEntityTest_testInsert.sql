START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Jon Snow', '2021-03-21'::date, 'http://nextras.cz', NULL);
SELECT CURRVAL('public.authors_id_seq');
COMMIT;
