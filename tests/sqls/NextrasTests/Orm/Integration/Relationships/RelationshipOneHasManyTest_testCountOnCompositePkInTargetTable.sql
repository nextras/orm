SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2;
START TRANSACTION;
INSERT INTO "tag_followers" ("created_at", "author_id", "tag_id") VALUES ('2021-12-02 19:21:00.000000'::timestamptz, 2, 1);
COMMIT;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 1;
SELECT "tag_id", COUNT(DISTINCT "tag_followers"."author_id") as "count" FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."tag_id" IN (1) GROUP BY "tag_id";
