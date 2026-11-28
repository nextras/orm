START TRANSACTION;
INSERT INTO "folders" ("id", "name") VALUES ('0192f0a8-7c8e-7000-8000-000000000001', 'Inbox');
COMMIT;
START TRANSACTION;
INSERT INTO "folders" ("id", "name") VALUES ('0192f0a8-7c8e-7000-8000-000000000002', 'Archive');
COMMIT;
START TRANSACTION;
INSERT INTO "documents" ("name", "folder_id") VALUES ('Letter', '0192f0a8-7c8e-7000-8000-000000000001');
SELECT CURRVAL('public.documents_id_seq');
COMMIT;
SELECT "documents".* FROM "documents" AS "documents" WHERE "documents"."id" = 1;
SELECT "folders".* FROM "folders" AS "folders" WHERE "folders"."id" IN ('0192f0a8-7c8e-7000-8000-000000000001');
SELECT "documents".* FROM "documents" AS "documents" WHERE "documents"."folder_id" IN ('0192f0a8-7c8e-7000-8000-000000000001');
SELECT "documents".* FROM "documents" AS "documents" WHERE "documents"."folder_id" = '0192f0a8-7c8e-7000-8000-000000000001';
SELECT "documents".* FROM "documents" AS "documents" WHERE "documents"."folder_id" = '0192f0a8-7c8e-7000-8000-000000000001';
SELECT
  "documents".*
FROM
  "documents" AS "documents"
  LEFT JOIN "folders" AS "folder" ON (
    "documents"."folder_id" = "folder"."id"
  )
WHERE
  "folder"."name" = 'Inbox';

SELECT "folders".* FROM "folders" AS "folders" WHERE "folders"."id" = '0192f0a8-7c8e-7000-8000-000000000002';
START TRANSACTION;
UPDATE "documents" SET "folder_id" = '0192f0a8-7c8e-7000-8000-000000000002' WHERE "id" = 1;
COMMIT;
SELECT "folders".* FROM "folders" AS "folders" ORDER BY "folders"."id" ASC;
SELECT "documents".* FROM "documents" AS "documents" WHERE "documents"."folder_id" IN ('0192f0a8-7c8e-7000-8000-000000000001', '0192f0a8-7c8e-7000-8000-000000000002');
SELECT "folder_id", COUNT(DISTINCT "documents"."id") as "count" FROM "documents" AS "documents" WHERE "documents"."folder_id" IN ('0192f0a8-7c8e-7000-8000-000000000001', '0192f0a8-7c8e-7000-8000-000000000002') GROUP BY "folder_id";
