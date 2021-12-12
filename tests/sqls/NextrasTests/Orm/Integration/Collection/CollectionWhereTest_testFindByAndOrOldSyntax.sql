SELECT "books".* FROM "books" AS "books" WHERE (("books"."author_id" = 1) AND ("books"."next_part" IS NULL));
SELECT "books".* FROM "books" AS "books" WHERE (("books"."author_id" = 1) AND ("books"."next_part" IS NULL) AND ("books"."translator_id" IS NULL));
SELECT "tags".* FROM "tags" AS "tags" WHERE ((("tags"."name" = 'Tag 1') AND ("tags"."is_global" = 'y')) OR (("tags"."name" = 'Tag 2') AND ("tags"."is_global" = 'n')) OR (("tags"."name" = 'Tag 3') AND ("tags"."is_global" = 'n')));
