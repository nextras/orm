SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" = 1 ORDER BY "books"."id" ASC LIMIT 1;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE "books"."author_id" = 1) temp;
SELECT "authors".* FROM "public"."authors" AS "authors" ORDER BY "authors"."id" ASC;
