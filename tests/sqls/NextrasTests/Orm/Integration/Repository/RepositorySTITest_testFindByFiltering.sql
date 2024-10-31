SELECT "contents".* FROM "contents" AS "contents" WHERE ("contents"."type" = 'comment') AND ("contents"."replied_at" > '2020-01-01 17:00:00.000000'::timestamptz);
SELECT COUNT(*) AS count FROM (SELECT "contents"."id" FROM "contents" AS "contents" WHERE ("contents"."type" = 'comment') AND ("contents"."replied_at" > '2020-01-01 17:00:00.000000'::timestamptz)) temp;
