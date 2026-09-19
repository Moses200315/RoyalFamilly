-- Relative dashboard sample data.
-- Run this file while connected to royalfamily_db.
-- It creates one example for each dashboard monitoring category:
-- overdue, due today, reminder, and delivered/saved today.

USE royalfamily_db;

START TRANSACTION;

-- Reuse an existing admin user and staff member when available.
SET @recorded_by = (SELECT id FROM users ORDER BY id ASC LIMIT 1);
SET @staff_id = (SELECT id FROM staff ORDER BY id ASC LIMIT 1);

INSERT INTO staff (staff_code, full_name, phone, email, status)
VALUES ('stf90', 'Dashboard Sample Staff', '+255700000090', 'sample.staff@royalfamily.test', 'active')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), status = 'active';

SET @staff_id = COALESCE(@staff_id, LAST_INSERT_ID());

-- Keep the sample customers active so the dashboard includes them.
INSERT INTO customers
    (customer_code, full_name, phone1, phone2, email, address,
     service_interval_hours, service_interval_days, status, created_by)
VALUES
    ('cust9901', 'Sample Overdue Customer', '+255700000091', NULL,
     'overdue@example.test', 'Sample address - overdue',
     72, 3, 'active', @recorded_by),
    ('cust9902', 'Sample Due Today Customer', '+255700000092', NULL,
     'due.today@example.test', 'Sample address - due today',
     72, 3, 'active', @recorded_by),
    ('cust9903', 'Sample Reminder Customer', '+255700000093', NULL,
     'reminder@example.test', 'Sample address - reminder',
     72, 3, 'active', @recorded_by),
    ('cust9904', 'Sample Saved Today Customer', '+255700000094', NULL,
     'saved.today@example.test', 'Sample address - saved today',
     72, 3, 'active', @recorded_by)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    phone1 = VALUES(phone1),
    email = VALUES(email),
    address = VALUES(address),
    service_interval_hours = VALUES(service_interval_hours),
    service_interval_days = VALUES(service_interval_days),
    status = 'active',
    created_by = VALUES(created_by);

SET @overdue_customer_id = (SELECT id FROM customers WHERE customer_code = 'cust9901');
SET @due_today_customer_id = (SELECT id FROM customers WHERE customer_code = 'cust9902');
SET @reminder_customer_id = (SELECT id FROM customers WHERE customer_code = 'cust9903');
SET @saved_today_customer_id = (SELECT id FROM customers WHERE customer_code = 'cust9904');

-- Remove only these sample customers' previous sample deliveries, making the
-- script safe to run again without creating duplicate dashboard entries.
DELETE FROM service_records
WHERE customer_id IN (
    @overdue_customer_id,
    @due_today_customer_id,
    @reminder_customer_id,
    @saved_today_customer_id
);

-- Last service 5 days ago with a 3-day interval: overdue.
INSERT INTO service_records
    (customer_id, staff_id, service_date_time, gallons_delivered,
     price_per_gallon, total_amount, notes, recorded_by)
VALUES
    (@overdue_customer_id, @staff_id, DATE_SUB(NOW(), INTERVAL 5 DAY),
     10, 1000.00, 10000.00, 'Dashboard sample: overdue', @recorded_by);

-- Last service 3 days ago at the current time: due today.
INSERT INTO service_records
    (customer_id, staff_id, service_date_time, gallons_delivered,
     price_per_gallon, total_amount, notes, recorded_by)
VALUES
    (@due_today_customer_id, @staff_id, DATE_SUB(NOW(), INTERVAL 3 DAY),
     10, 1000.00, 10000.00, 'Dashboard sample: due today', @recorded_by);

-- Last service 2 days ago at the current time: next due tomorrow and in the
-- dashboard's one-day reminder window.
INSERT INTO service_records
    (customer_id, staff_id, service_date_time, gallons_delivered,
     price_per_gallon, total_amount, notes, recorded_by)
VALUES
    (@reminder_customer_id, @staff_id, DATE_SUB(NOW(), INTERVAL 2 DAY),
     10, 1000.00, 10000.00, 'Dashboard sample: reminder', @recorded_by);

-- A delivery recorded today: shown as saved/delivered today.
INSERT INTO service_records
    (customer_id, staff_id, service_date_time, gallons_delivered,
     price_per_gallon, total_amount, notes, recorded_by)
VALUES
    (@saved_today_customer_id, @staff_id, NOW(),
     10, 1000.00, 10000.00, 'Dashboard sample: saved today', @recorded_by);

COMMIT;
