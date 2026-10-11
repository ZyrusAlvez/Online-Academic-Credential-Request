-- Export completed requests from a real database in the same columns as
-- ml/data/raw_requests.csv, so ml/train.py can retrain on pilot data.
--
-- Run read-only, e.g.:
--   mysql -u uphsl_tracker -p uphsl_credential_tracker --batch < ml/export_dataset.sql \
--     | sed 's/\t/,/g' > ml/data/raw_requests.csv
--
-- The student's college step is exported as COLLEGE, matching the workflow placeholder.
-- open_workload counts the requests that were in progress at the moment this one was routed.

SELECT
  r.reference_no,
  t.name AS credential_type,
  c.code AS college,
  r.student_status,
  r.copies,
  r.requires_payment,
  t.processing_days,
  (SELECT GROUP_CONCAT(IF(d.type = 'college', 'COLLEGE', d.code) ORDER BY s.position SEPARATOR '>')
     FROM request_steps s
     JOIN departments d ON d.id = s.department_id
    WHERE s.credential_request_id = r.id) AS workflow,
  (SELECT COUNT(*)
     FROM credential_requests o
    WHERE o.id <> r.id
      AND o.routed_at <= r.routed_at
      AND (o.ready_at IS NULL OR o.ready_at > r.routed_at)
      AND (o.cancelled_at IS NULL OR o.cancelled_at > r.routed_at)) AS open_workload,
  r.routed_at,
  r.ready_at
FROM credential_requests r
JOIN credential_types t ON t.id = r.credential_type_id
JOIN departments c ON c.id = r.college_id
WHERE r.routed_at IS NOT NULL
ORDER BY r.id;
