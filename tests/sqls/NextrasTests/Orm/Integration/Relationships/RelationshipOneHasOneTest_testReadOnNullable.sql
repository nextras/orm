START TRANSACTION;
INSERT INTO "photo_albums" ("title", "preview_id") VALUES ('Test', NULL);
SELECT CURRVAL('public.photo_albums_id_seq');
INSERT INTO "photos" ("title", "album_id") VALUES ('Test', 1);
SELECT CURRVAL('public.photos_id_seq');
UPDATE "photo_albums" SET "preview_id" = 1 WHERE "id" = 1;
COMMIT;
SELECT "photos".* FROM "photos" AS "photos" WHERE (("photos"."id" = 1));
SELECT "photo_albums".* FROM "photo_albums" AS "photo_albums" WHERE "photo_albums"."preview_id" IN (1);
