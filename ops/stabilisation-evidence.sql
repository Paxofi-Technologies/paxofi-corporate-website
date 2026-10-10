-- Paxofi Corporate Website — evidence queries for the stabilisation and
-- operational reviews (CW-OPS2-003/004, docs/STABILISATION-REVIEW.md).
--
-- READ-ONLY: every statement is a SELECT. Run in phpMyAdmin → database
-- paxoalhu_corporate → SQL. Set the review window on the first line (the
-- default is since go-live, 2 Oct 2026). Results show counts and timings only:
-- no names, emails, messages or IP addresses, so they can be pasted into the
-- review record.

SET @since = '2026-10-02 00:00:00';

-- 1. Enquiries received in the window, by current status.
SELECT status, COUNT(*) AS enquiries
FROM enquiries
WHERE created_at >= @since
GROUP BY status
ORDER BY enquiries DESC;

-- 2. Enquiry response: hours from arrival to the first status change by staff
--    (D-007 target: a reply within 2 business days). "first_action_hours" is
--    empty for an enquiry nobody has handled yet.
SELECT e.reference_day,
       e.status,
       e.first_action_hours,
       CASE
         WHEN e.first_action_hours IS NULL AND e.age_hours > 48 THEN 'CHECK: no action yet'
         WHEN e.first_action_hours > 96 THEN 'CHECK: slow'
         ELSE 'ok'
       END AS verdict
FROM (
  SELECT DATE(q.created_at) AS reference_day,
         q.status,
         TIMESTAMPDIFF(HOUR, q.created_at, NOW()) AS age_hours,
         (SELECT TIMESTAMPDIFF(HOUR, q.created_at, MIN(a.created_at))
            FROM audit_events a
           WHERE a.target_type = 'enquiry' AND a.target_id = q.id
             AND a.action LIKE 'enquiry.status.%' AND a.outcome = 'success') AS first_action_hours
  FROM enquiries q
  WHERE q.created_at >= @since AND q.status <> 'spam'
) e
ORDER BY e.reference_day;

-- 3. Contact form protection: submissions, spam traps and rate limits.
SELECT action, outcome, COUNT(*) AS events
FROM audit_events
WHERE created_at >= @since
  AND action IN ('enquiry.submitted', 'enquiry.honeypot_triggered', 'enquiry.rate_limited',
                 'application.submitted', 'application.honeypot_triggered')
GROUP BY action, outcome
ORDER BY action, outcome;

-- 4. Applications (careers.paxofi.com) by stage, with the acknowledgement
--    check (D-019: acknowledge within 2 working days): applications still at
--    "applied" after 4 calendar days, which covers a weekend.
SELECT stage,
       COUNT(*) AS applications,
       SUM(stage = 'applied' AND created_at < NOW() - INTERVAL 4 DAY) AS waiting_over_4_days
FROM job_applications
WHERE created_at >= @since
GROUP BY stage
ORDER BY applications DESC;

-- 5. Staff sign-ins: successes, wrong passwords and blocked attempts per day.
--    A burst of failures from one day is a reason to read Audit log → Sign-ins.
SELECT DATE(created_at) AS day,
       SUM(outcome = 'success') AS succeeded,
       SUM(outcome = 'failure') AS wrong_password,
       SUM(outcome = 'denied')  AS blocked
FROM audit_events
WHERE created_at >= @since AND action = 'staff.sign_in'
GROUP BY DATE(created_at)
ORDER BY day;

-- 6. Email: what was sent, what is waiting and what failed (RB-17). "failed"
--    or old "pending" rows mean the send-mail cron job or SMTP needs a look.
SELECT kind, status, COUNT(*) AS emails, MAX(attempts) AS most_attempts,
       MIN(created_at) AS oldest
FROM email_outbox
WHERE created_at >= @since
GROUP BY kind, status
ORDER BY status, kind;

SELECT kind, attempts, last_error, created_at
FROM email_outbox
WHERE status <> 'sent' AND created_at >= @since
ORDER BY created_at DESC
LIMIT 20;

-- 7. Retention (RB-10): nothing older than the limits should remain if the
--    daily purge job runs. Every column should be 0.
SELECT
  (SELECT COUNT(*) FROM enquiries WHERE created_at < NOW() - INTERVAL 24 MONTH) AS enquiries_over_24_months,
  (SELECT COUNT(*) FROM enquiries WHERE created_at < NOW() - INTERVAL 90 DAY
      AND (source_ip IS NOT NULL OR user_agent IS NOT NULL))                    AS enquiry_ip_over_90_days,
  (SELECT COUNT(*) FROM application_uploads WHERE claimed_at IS NULL
      AND created_at < NOW() - INTERVAL 2 DAY)                                  AS unclaimed_cv_uploads,
  (SELECT COUNT(*) FROM analytics_visitors WHERE day < CURDATE() - INTERVAL 1 DAY) AS old_visitor_hashes;

-- 8. Visitors (D-014): page views and visitors per day on the public site.
SELECT day, SUM(views) AS views, SUM(visitors) AS visitors
FROM analytics_daily
WHERE day >= DATE(@since)
GROUP BY day
ORDER BY day;

-- 9. Content (D-025): review dates that have passed, and redirects in use.
--    Staff area → Content → Reviews is the full list (it also shows items
--    that have never had a date).
SELECT
  (SELECT COUNT(*) FROM content_reviews WHERE review_by < CURDATE()) AS reviews_overdue,
  (SELECT COUNT(*) FROM content_reviews WHERE review_by IS NULL)     AS reviews_without_date,
  (SELECT COUNT(*) FROM redirects)                                   AS redirects;

-- 10. Errors that reached the audit log, by action (failures other than sign-in).
SELECT action, outcome, COUNT(*) AS events
FROM audit_events
WHERE created_at >= @since AND outcome <> 'success' AND action <> 'staff.sign_in'
GROUP BY action, outcome
ORDER BY events DESC
LIMIT 20;
