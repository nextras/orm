SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE "books"."author_id" > 0) temp;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" > 0 ORDER BY "books"."author_id" ASC;
