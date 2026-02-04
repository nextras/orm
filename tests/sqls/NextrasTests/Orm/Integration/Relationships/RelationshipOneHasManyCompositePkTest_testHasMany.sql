SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1);
SELECT "author_id", COUNT(DISTINCT "tag_followers"."tag_id") as "count" FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1) GROUP BY "author_id";
