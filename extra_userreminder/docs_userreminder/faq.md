# FAQ

## General

### What is User Reminder?

A Joomla extension that emails users who never activated their account, never logged in, or haven't visited for a while, and can clean up the accounts that never respond.

### Who is it for?

Any Joomla site with user registration: membership sites, communities, shops, schools and intranets that want to recover sign-ups, bring members back and keep the user list clean.

### Which Joomla and PHP versions are supported?

Joomla 4.4, 5 and 6, with PHP 8.1 or higher.

### Does it send my users to an outside service?

No. Emails go out through your own Joomla mail settings and all data stays in your database.

## Emails

### Where do I edit the email text?

In **System > Mail Templates** (or **Email Templates** in the User Reminder menu). Each reminder type has its own template, per language. See [Email Templates](features/email-templates.md).

### My 5.x email texts are gone after the upgrade. Why?

5.x kept the texts in the component options. 6.x uses Joomla mail templates, which start with the default wording. Copy your old texts into the templates.

### Can the emails carry my logo?

Yes. Turn on **Show logo** on the **Email Container (HTML)** tab and pick the logo file.

## Sending

### How are reminders sent automatically?

By the **User Reminder emails** scheduled task, which the installer creates to run every 24 hours. See [Scheduling](features/scheduling.md).

### Can I send by hand?

Yes. Click **Send Reminders** on the Registration Reminders or Active User Reminders screen, or **Send now** on the dashboard.

### Will it overload my mail server?

No. **Emails per batch** and **Maximum emails per run** keep each run small; what doesn't fit goes out in the next run.

### Can I test without emailing real users?

Yes. **Send Test Email** goes to your test user only. **Developer debug mode** simulates a full run: nothing is sent and no counters change.

## Accounts and Opt-Out

### Does it delete users?

Only if you turn on **Delete users** for a reminder type. Then users who got the last reminder and still didn't react are deleted. For inactive users you can remove them from chosen groups instead.

### How does unsubscribing work?

Every email has an `{OPTOUT_URL}` link. It opens a confirmation page on your site; after that the user gets no more reminders. The same page offers to resubscribe.

### Can I exclude staff from reminders?

Yes. Add their user groups under **Opt-out Groups**, or add single users under **Opt-out Users**.

### Does it work with Community Builder?

Yes. Turn on **Use Community Builder activation** so activation reminders contain a working CB activation link.
