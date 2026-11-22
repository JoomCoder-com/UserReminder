# Administrator Screens

All screens are in the **Components > User Reminder** menu.

## Dashboard

![Dashboard counters for pending, never logged in, inactive, due, sent and opted-out users](../images/dashboard-counters.png)

- **Counters**: pending activation, never logged in, inactive, due now, sent (total, 7 days, 30 days), opted out and total users
- **Alerts**: scheduled task missing, debug mode on, deletion on, invalid site mail address, missing BCC address, backlog bigger than a batch
- **Analytics**: emails sent in the last 30 days, split by type, and backlog aging
- **System health** and a **config snapshot** of your main options
- **Recent sends**, oldest pending activations and longest inactive users
- **Quick actions**: send now, send inactive reminders, view log, prune old logs

The dashboard is cached; click **Refresh** for fresh numbers.

![Emails sent in the last 30 days, split by type, and backlog aging](../images/dashboard-analytics.png)

![System health checks and the alert summary](../images/dashboard-health.png)

## Registration Reminders

![Users who registered but never activated, with reminders sent](../images/registration-reminders.png)

Users who never activated or never logged in, with registration date, last reminder sent, reminders sent and the action the next run will take. Search, sort, and **Send Reminders**.

## Active User Reminders

![Inactive users sorted by last login, with days inactive](../images/inactive-users.png)

Users past the inactivity threshold, with last login, days inactive, reminders sent and the next action.

## Opt-out

![Users who unsubscribed from reminders](../images/opt-out-users.png)

- **Opt-out Users**: people who unsubscribed or that you added. Click **Add opt-out users**, pick users, then **Add to opt-out list**. Select rows and **Remove from opt-out list** to undo
- **Opt-out Groups**: tick user groups whose members never get reminders, for example Administrator or Staff

![The Manager group ticked as an opt-out group](../images/opt-out-groups.png)

## Action Log

![Action log with reminders sent, opt-outs and resubscribes](../images/action-log.png)

Every reminder sent, every opt-out and resubscribe, with user and date. Search by username or action, delete selected entries or clear the log. **Prune old logs** on the dashboard removes entries older than 12 months.

## Options

![The Reminders tab of the options](../images/options-reminders.png)

| Tab | Contains |
|---|---|
| General | Test user, developer debug mode, reminder cadence |
| Email Delivery | Emails per batch, maximum per run, admin BCC |
| Links & URLs | Activation and password reset paths |
| Reminders | The three reminder types, deletion, CB activation, remove from groups |
| Email Container (HTML) | Logo, header, footer and colours |
| Permissions | Who may use and configure User Reminder |
