SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "books"."id"
    FROM
      "books" AS "books"
      LEFT JOIN "public"."authors" AS "author" ON (
        "books"."author_id" = "author"."id"
      )
    WHERE
      "author"."name" = 'Writer 1'
    LIMIT
      5
  ) temp;

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "tag_followers"."tag_id",
      "tag_followers"."author_id"
    FROM
      "tag_followers" AS "tag_followers"
      LEFT JOIN "tags" AS "tag" ON (
        "tag_followers"."tag_id" = "tag"."id"
      )
    WHERE
      "tag"."name" = 'Tag 1'
    LIMIT
      3
  ) temp;
