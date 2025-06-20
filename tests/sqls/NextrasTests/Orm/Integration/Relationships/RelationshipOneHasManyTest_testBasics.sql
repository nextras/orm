SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 1') AND ("books"."author_id" IN (1)) ORDER BY "books"."id" DESC;
SELECT "author_id", COUNT(DISTINCT "__nextras_fix_id_count") as "count" FROM (SELECT "books"."author_id", "books"."id" AS "__nextras_fix_id_count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 1') AND ("books"."author_id" IN (1))) AS "temp" GROUP BY "author_id";
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1)) ORDER BY "books"."id" DESC;
SELECT "author_id", COUNT(DISTINCT "__nextras_fix_id_count") as "count" FROM (SELECT "books"."author_id", "books"."id" AS "__nextras_fix_id_count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1))) AS "temp" GROUP BY "author_id";
SELECT "books".* FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1)) ORDER BY "books"."id" ASC;
SELECT "author_id", COUNT(DISTINCT "__nextras_fix_id_count") as "count" FROM (SELECT "books"."author_id", "books"."id" AS "__nextras_fix_id_count" FROM "books" AS "books" WHERE ("books"."title" != 'Book 3') AND ("books"."author_id" IN (1))) AS "temp" GROUP BY "author_id";
