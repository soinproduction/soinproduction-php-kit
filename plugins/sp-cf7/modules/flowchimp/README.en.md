# SP Flowchimp

Maps Contact Form 7 forms to Mailchimp audiences and performs a synchronous member upsert before CF7 sends its notification email.

Configuration is stored in `sp_flowchimp_options`, including the plaintext API key. The default `pending` status enables double opt-in. Remote failures are logged but do not invalidate the form submission; enabling “Skip CF7 mail” can therefore remove the fallback email even when Mailchimp fails.
