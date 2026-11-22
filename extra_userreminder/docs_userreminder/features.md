# Features

Everything User Reminder does, at a glance.

## Reminder Types

Three groups, each turned on separately on the **Reminders** tab of the options.

- **Incomplete registrations**: blocked accounts with an activation token that never visited
- **Activated, never logged in**: active accounts that never signed in
- **Inactive users**: no visit for the **Inactivity threshold** (days)

---

## Emails

- One Joomla mail template per type, with subject, plain text and HTML body
- Translate each template per site language in **System > Mail Templates**
- Tags: `{NAME}`, `{USERNAME}`, `{SITENAME}`, `{SITELINK}`, `{ACTIVATE_URL}`, `{PASSWORD_RESET_URL}`, `{OPTOUT_URL}`
- Branded HTML container: logo or header title, footer text, footer colours

See [Email Templates](features/email-templates.md).

---

## Scheduling and Cadence

- Runs as the **Send User Reminder emails** task in Joomla Scheduled Tasks
- Choose per task whether it sends registration reminders, inactivity reminders, or both
- Days between reminders, number of reminders, first reminder at once or later
- **Send Reminders** button to run by hand

See [Scheduling](features/scheduling.md).

---

## Account Cleanup

- **Delete users** after the last reminder, per reminder type
- **Remove from user groups** for inactive users instead of deleting them
- A dashboard alert while deletion is on

---

## Opt-Out

- Unsubscribe link in every email, with a confirmation page and a resubscribe option
- **Opt-out Users**: add or remove users by hand
- **Opt-out Groups**: members of these groups never get reminders
- Opt-outs and resubscribes are written to the action log

---

## Dashboard and Log

- Pending activation, never logged in, inactive, due now, sent (total, 7 and 30 days), opted out
- 30-day chart, split by type, backlog aging, oldest pending and longest inactive users
- Health checks: scheduled task, debug mode, auto-delete, site mail address, BCC, backlog
- Action log with search, delete and pruning of entries older than 12 months

See [Administrator Screens](features/admin-screens.md).

---

## Delivery and Testing

- **Emails per batch** and **Maximum emails per run** to respect your host's limits
- BCC of every reminder to an admin address
- **Send Test Email** to a chosen test user
- **Developer debug mode**: a dry run that sends nothing and changes no counters

---

## Compatibility

- Joomla 4.4, 5 and 6
- Community Builder activation links
- Your own activation and password-reset paths
