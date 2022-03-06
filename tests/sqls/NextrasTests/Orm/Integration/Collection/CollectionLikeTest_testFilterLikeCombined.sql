SELECT "books".* FROM "books" AS "books" WHERE ((("books"."title" LIKE 'Book%')) AND (("books"."translator_id" IS NOT NULL)));
SELECT "books".* FROM "books" AS "books" WHERE ((("books"."title" LIKE 'Book 1%')) OR (("books"."translator_id" IS NULL)));
