SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "books".* FROM "books" AS "books" LEFT JOIN "eans" AS "ean" ON ("books"."ean_id" = "ean"."id") WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC, "ean"."code" ASC;
