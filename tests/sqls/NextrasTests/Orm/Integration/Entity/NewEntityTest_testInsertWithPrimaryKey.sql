START TRANSACTION;
INSERT INTO "public"."authors" ("id", "name", "born_on", "web", "favorite_author_id") VALUES (555, 'Jon Snow', '2021-03-21'::date, 'http://nextras.cz', NULL);
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 555;
