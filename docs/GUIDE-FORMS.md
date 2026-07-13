# Forms

Contact forms on a fully static site — submissions post to the platform API while your pages stay flat HTML.

## Adding a form

Insert the **Contact Form** block anywhere. Configure:

- **Fields** — any mix of text, email, phone, textarea, select, and checkbox, each with a label and an optional required flag.
- **Recipient email** — where submissions are sent.
- **Submit label** and **success message** — the button text and the confirmation shown after sending.

## What happens on submit

1. The submission is **stored** for the site (so nothing is lost even if email delivery hiccups).
2. A plain-text **email** is sent to the recipient address.
3. The visitor sees your success message.

## Reviewing submissions

Stored submissions are available per site in the admin (most recent first) and can be deleted individually — useful as a backup when an email goes missing, and as a simple lightweight inbox.

## Notes

- Forms are the one place a published page talks to the platform; everything else on your site is static. If the API is briefly unreachable, the form reports failure to the visitor rather than silently dropping the message.
- Spam control is intentionally minimal today; put the form behind a specific page (rather than the footer of every page) if volume becomes a problem.
