SELECT pg_terminate_backend(4148);
SELECT 1 FROM pg_stat_activity WHERE pid = 4148;
