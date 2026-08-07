# SP CF7 Mail Viewer

Stores prepared Contact Form 7 mail components in the private `sp_cf7_mail` post type and exposes them under Contact → Mail Viewer.

The module keeps at most 300 entries and records message content, submitted fields, recipient/sender data, attachment paths and visitor IP. Capture happens before the mail transport result, so a stored entry does not prove delivery. Restrict access and define an appropriate privacy and retention policy.
