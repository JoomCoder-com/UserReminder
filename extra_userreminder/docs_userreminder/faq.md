# FAQ

## General

### What is User Reminder?

A Joomla extension that emails users who never activated their account, never logged in, or stopped visiting, and can clean up the accounts that never respond.

### Which Joomla and PHP versions are supported?

Joomla 4.4, 5 and 6, with PHP 8.1 or higher, on MySQL or MariaDB. See [Compatibility](product-overview/compatibility.md).

### Does it send my users' data to an outside service?

No. Emails go out through your own Joomla mail settings, and all data stays in your database.

### Where is the help?

The **Help** button on every User Reminder screen opens this documentation.

## Emails

### Where do I edit the email text?

In **System > Mail Templates**, or **Email Templates** in the User Reminder menu. Each reminder type has its own template, per language. See [Email templates](features/email-templates.md).

### My 5.x email texts are gone after the upgrade. Why?

5.x kept the texts in the component options. 6.x uses Joomla mail templates, which start with the default wording. Copy your old texts into the templates.

### Can the emails carry my logo?

Yes, on Joomla 5 and 6. Turn on **Show logo** in **Options > Email Container (HTML)** and pick the image. See [Email Container](configuration/email-container.md).

### Can I get a copy of every reminder?

Yes. Turn on **BCC admin on all reminders** in **Options > Email Delivery** and enter an address.

## Sending

### How are reminders sent automatically?

By the **User Reminder emails** scheduled task, which the installer creates to run every 24 hours. See [Scheduling](features/scheduling.md).

### Can I send by hand?

Yes. **Send Reminders** on **Registration Reminders** or **Active User Reminders**, or the send buttons on the dashboard.

### Will it overload my mail server?

No. **Maximum emails per run** keeps each run small; the rest go out in the next runs.

### Can I test without emailing real users?

Yes. **Send Test Email** goes to your test user only. **Developer debug mode** simulates a full run: nothing is sent and nothing changes. See [Testing](features/testing.md).

### Can I see who gets an email before it is sent?

Yes. The lists show the **Action to Perform** for every user: send, wait, delete, or nothing more.

## Accounts and opt-out

### Does it delete users?

Only if you turn on **Delete users** for a reminder type. Users who got the last reminder and still did not react are then deleted. For inactive users you can remove them from chosen groups instead. See [Account cleanup](features/account-cleanup.md).

### How does unsubscribing work?

Every email has an unsubscribe link. It opens a confirmation page on your site; after confirming, the user gets no more reminders. The same page offers to resubscribe. See [Opt-out](features/opt-out.md).

### Can I exclude staff?

Yes. Add their user groups under **Opt-out > User Groups**, or single users under **Opt-out Users**.

### Does it work with Community Builder?

Yes. See [Community Builder](integrations/community-builder.md).
