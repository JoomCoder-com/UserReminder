# Why Choose It

User Reminder suits Joomla sites with open registration that want to follow up on unfinished sign-ups and quiet members, using Joomla's own mail and scheduling.

## It is likely a good fit if

- Visitors can register on your site
- You want reminders to go out on a schedule, without someone clicking a button
- You want to edit the emails in Joomla's Mail Templates, per language
- You want to delete or demote accounts that never respond, with clear rules
- You send email through Joomla already (PHP mail, SMTP or a mail plugin)

## It may not be the right fit yet if

- **You need newsletters or campaigns.** User Reminder sends one kind of email per reminder type to individual users. It has no lists, campaigns or open tracking.
- **You need different timing per reminder type.** The days between reminders and the number of reminders are shared by all three types. Only the inactivity threshold is separate.
- **Your site runs on PostgreSQL.** Only MySQL and MariaDB are supported.
- **You want the branded email container on Joomla 4.** The [HTML container](../configuration/email-container.md) needs Joomla 5 or 6. On Joomla 4 the template's HTML body is sent as it is.
- **You register users with a component other than Joomla's or Community Builder.** It works if that component uses Joomla's user table and activation; you may need to set your own [activation path](../configuration/links.md).

## Try before you commit

Read the [feature list](../features.md) and the [Quick Start](../getting-started.md). With **Developer debug mode** on, you can run User Reminder on your real users and see exactly what it would send, without sending anything. Questions: support@joomcoder.com.

## Who builds it

User Reminder is built and maintained by JoomCoder, a team of experienced Joomla developers at AppWise, based in Morocco. It has been in use since 2022 and was rebuilt for Joomla 4.4, 5 and 6 in version 6.
