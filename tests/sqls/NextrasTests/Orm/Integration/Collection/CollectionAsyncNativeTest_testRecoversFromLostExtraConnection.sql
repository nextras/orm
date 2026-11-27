SELECT pg_terminate_backend(5555);
SELECT 1 FROM pg_stat_activity WHERE pid = 5555;
