START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('1234', 2);
SELECT CURRVAL('public.eans_id_seq');
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."ean_id" IN (1);
START TRANSACTION;
DELETE FROM "eans" WHERE "id" = 1;
COMMIT;
