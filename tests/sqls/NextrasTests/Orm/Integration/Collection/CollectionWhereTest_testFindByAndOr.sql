SELECT "authors".* FROM "public"."authors" AS "authors" WHERE ("authors"."name" = 'Writer 1') OR ("authors"."web" = 'http://example.com/2');
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."name" IN ('Writer 1', 'Writer 2');
SELECT "books".* FROM "books" AS "books" WHERE ("books"."author_id" = 1) AND ("books"."next_part" IS NULL);
SELECT "books".* FROM "books" AS "books" WHERE ("books"."author_id" = 1) AND ("books"."next_part" IS NULL) AND ("books"."translator_id" IS NULL);
SELECT "tags".* FROM "tags" AS "tags" WHERE (("tags"."name" = 'Tag 1') AND ("tags"."is_global" = 'y')) OR (("tags"."name" = 'Tag 2') AND ("tags"."is_global" = 'n')) OR (("tags"."name" = 'Tag 3') AND ("tags"."is_global" = 'n'));
SELECT "books".* FROM "books" AS "books" WHERE (("books"."title" = 'Book 1') OR ("books"."author_id" = 1)) AND (("books"."translator_id" IS NULL) OR ("books"."next_part" = 3));
