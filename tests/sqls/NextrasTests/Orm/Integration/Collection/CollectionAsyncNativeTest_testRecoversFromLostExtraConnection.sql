SELECT pg_backend_pid() AS id;
SELECT pg_terminate_backend(7394);
SELECT 1 FROM pg_stat_activity WHERE pid = 7394;
SELECT 1 AS i;
SELECT 1 AS i;
