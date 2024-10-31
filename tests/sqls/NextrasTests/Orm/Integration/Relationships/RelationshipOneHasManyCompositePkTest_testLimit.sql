SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 2;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
START TRANSACTION;
INSERT INTO "tag_followers" ("created_at", "author_id", "tag_id") VALUES ('2021-12-02 19:21:00.000000'::timestamptz, 1, 2);
COMMIT;
SELECT "authors".* FROM "public"."authors" AS "authors" ORDER BY "authors"."id" ASC;
(SELECT "tag_followers".*, "tag_followers"."author_id", "tag_followers"."tag_id" FROM "tag_followers" AS "tag_followers" WHERE "author_id" = 1 ORDER BY "tag_followers"."tag_id" DESC LIMIT 2) UNION ALL (SELECT "tag_followers".*, "tag_followers"."author_id", "tag_followers"."tag_id" FROM "tag_followers" AS "tag_followers" WHERE "author_id" = 2 ORDER BY "tag_followers"."tag_id" DESC LIMIT 2);
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE ("tag_followers"."author_id", "tag_followers"."tag_id") IN ((1, 3), (1, 2), (2, 2));
