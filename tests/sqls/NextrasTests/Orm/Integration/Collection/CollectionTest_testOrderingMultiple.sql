SELECT "books".* FROM "books" AS "books" LEFT JOIN "public"."authors" AS "author" ON ("books"."author_id" = "author"."id") ORDER BY "author"."id" DESC, "books"."title" ASC;
SELECT "books".* FROM "books" AS "books" LEFT JOIN "public"."authors" AS "author" ON ("books"."author_id" = "author"."id") ORDER BY "author"."id" DESC, "books"."title" DESC;
