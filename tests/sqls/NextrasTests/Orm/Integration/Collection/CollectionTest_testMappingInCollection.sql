SELECT COUNT(*) AS count FROM (SELECT "tags"."id" FROM "tags" AS "tags" WHERE (("tags"."is_global" = 'y'))) temp;
SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."is_global" = 'y'));
