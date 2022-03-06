SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 1));
START TRANSACTION;
UPDATE "public"."authors" SET "name" = 'Test Testcase' WHERE "id" = 1;
COMMIT;
