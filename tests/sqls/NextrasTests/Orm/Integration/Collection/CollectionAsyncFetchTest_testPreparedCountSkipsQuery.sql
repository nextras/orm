SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE "books"."author_id" = 1) temp;
