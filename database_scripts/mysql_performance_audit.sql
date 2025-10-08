-- MySQL Performance Audit Script for Ricochet Robot
-- Run this on DreamHost to identify performance bottlenecks

-- 1. Check table sizes and row counts
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    ROUND((data_length / 1024 / 1024), 2) AS 'Data (MB)',
    ROUND((index_length / 1024 / 1024), 2) AS 'Index (MB)'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
ORDER BY (data_length + index_length) DESC;

-- 2. Check current indexes
SELECT 
    table_name,
    index_name,
    column_name,
    seq_in_index,
    non_unique,
    index_type
FROM information_schema.statistics 
WHERE table_schema = DATABASE()
ORDER BY table_name, index_name, seq_in_index;

-- 3. Check for missing indexes on foreign keys
SELECT 
    table_name,
    column_name,
    constraint_name,
    referenced_table_name,
    referenced_column_name
FROM information_schema.key_column_usage 
WHERE table_schema = DATABASE() 
    AND referenced_table_name IS NOT NULL;

-- 4. Analyze query patterns (if slow query log is enabled)
-- This will show the most expensive queries
SELECT 
    query_time,
    lock_time,
    rows_sent,
    rows_examined,
    sql_text
FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY query_time DESC 
LIMIT 10;

-- 5. Check for table fragmentation
SELECT 
    table_name,
    engine,
    table_rows,
    avg_row_length,
    data_length,
    max_data_length,
    index_length,
    data_free,
    ROUND((data_free / (data_length + index_length)) * 100, 2) AS 'Fragmentation %'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
    AND engine = 'InnoDB'
ORDER BY data_free DESC;

-- 6. Check InnoDB buffer pool usage
SHOW ENGINE INNODB STATUS\G

-- 7. Check current connections and processes
SHOW PROCESSLIST;

-- 8. Check table status
SHOW TABLE STATUS;
