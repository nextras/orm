SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE (("tag_followers"."tag_id" = 3) AND ("tag_followers"."author_id" = 1));
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE (("tag_followers"."author_id" = 1) AND ("tag_followers"."tag_id" = 3));
