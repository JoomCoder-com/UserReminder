# Features

Everything User Reminder does, grouped by area. Each heading links to its guide.

## Reminder types

Three groups of users, each switched on separately in **Options > Reminders**. All three are off after a new install.

- [Activation reminders](features/activation-reminders.md): blocked accounts with an activation link that was never used
- [Never-logged-in reminders](features/login-reminders.md): active accounts that never signed in
- [Inactive-user reminders](features/inactive-users.md): no visit for the number of days you set

## Emails

- One [Joomla mail template](features/email-templates.md) per type, with subject, plain text and HTML body, per site language
- Tags: `{NAME}`, `{USERNAME}`, `{EMAIL}`, `{SITENAME}`, `{SITELINK}`, `{ACTIVATE_URL}`, `{PASSWORD_RESET_URL}`, `{OPTOUT_URL}`
- An [HTML container](configuration/email-container.md) around each email: logo or title, footer text and colours (Joomla 5 and 6)
- An optional [copy of every reminder](configuration/email-delivery.md#admin-copy-bcc) to an admin address

## Scheduling and cadence

- Sends through the **Send User Reminder emails** [scheduled task](features/scheduling.md), created on install to run every 24 hours
- Days between reminders, number of reminders, first reminder at once or after the wait
- **Send Reminders** buttons to send what is due right now

## Account cleanup

- [Delete users](features/account-cleanup.md) after the last reminder, per reminder type
- Or remove inactive users from chosen user groups instead
- A dashboard alert while deletion is on

## Opt-out

- An [unsubscribe link](features/opt-out.md) in every email, with a confirmation page and a resubscribe button
- **Opt-out Users**: add or remove people by hand
- **Opt-out Groups**: members of these groups never get reminders

## Dashboard and log

- [Dashboard](features/dashboard.md): who is pending, due now and sent, a 30-day chart, backlog aging and health checks
- Every list shows what the next run will do for each user
- [Action log](features/action-log.md) of every reminder, opt-out and resubscribe, with pruning

## Safe testing

- **Send Test Email** to one chosen user
- [Developer debug mode](features/testing.md): a full run that sends nothing and changes nothing

## Built for Joomla

- Joomla 4.4, 5 and 6, MySQL and MariaDB ([Compatibility](product-overview/compatibility.md))
- Joomla mail templates, scheduled tasks, user groups, [ACL](configuration/permissions.md) and update system
- [Community Builder](integrations/community-builder.md) activation links
