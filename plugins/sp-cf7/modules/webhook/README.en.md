# SP CF7 Webhook

Adds a per-form Contact Form 7 panel for synchronous POST, PUT or PATCH webhook delivery with headers, field mapping and optional request metadata.

Requests use a 15-second timeout and have no queue or retry. Transport and HTTP errors are stored as status text but do not invalidate the CF7 submission. Authentication headers are stored as plaintext post meta; keep debug disabled outside short diagnostics and avoid sending unnecessary personal data.
