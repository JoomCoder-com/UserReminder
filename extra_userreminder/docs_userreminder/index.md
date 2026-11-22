# User Reminder

User Reminder emails your Joomla users who never finished registering, never logged in, or haven't visited for a while, and can clean up the accounts that never respond.

## What It Does

User Reminder looks at your Joomla users and finds three kinds of quiet accounts:

- **Incomplete registrations**: registered, but never activated
- **Never logged in**: activated, but never signed in
- **Inactive users**: no visit for a number of days you choose

Each group gets its own email, repeated as often as you set. When the last reminder goes unanswered, User Reminder can delete the account or, for inactive users, remove it from chosen user groups.

It runs on Joomla's own building blocks: the reminders are **Joomla Mail Templates**, sending runs as a **Joomla Scheduled Task**, and everything stays on your site. No outside service sees your users.

## What's Included

- **com_userreminder**: the component with the dashboard, reminder lists, opt-out lists, action log and options
- **plg_task_userreminder**: the "Task - UserReminder" plugin that sends reminders from **System > Scheduled Tasks**

## Key Features

- **Three reminder types**: incomplete registrations, never logged in and inactive users, each turned on separately
- **Joomla Mail Templates**: edit subject and body per type and language in **System > Mail Templates**
- **Branded HTML container**: logo, header title and footer colours around every email
- **Scheduled Tasks**: a daily task is created on install; change the interval like any Joomla task
- **Repeat and stop**: days between reminders and the number of reminders per user
- **Account cleanup**: delete unanswered accounts, or remove inactive users from groups
- **Opt-out**: one-click unsubscribe page, opt-out users and opt-out user groups
- **Dashboard**: what's pending, due now and sent, a 30-day chart, backlog aging and health checks
- **Action log**: every reminder, opt-out and resubscribe, searchable, with pruning
- **Safe testing**: send a test email, or use debug mode to simulate a run without sending
- **Sending limits**: emails per batch and a hard cap per run, plus an optional admin BCC
- **Community Builder**: CB activation links for CB registrations

## Requirements

| Requirement | Version |
|-------------|---------|
| Joomla | 4.4, 5.x or 6.x |
| PHP | 8.1 or higher |
| Database | MySQL or MariaDB |
| Mail | A working Joomla mail configuration |
| Scheduler | Joomla Scheduled Tasks (lazy scheduler or a server cron job) |

## Support

- **Website:** [joomcoder.com](https://www.joomcoder.com/)
- **Documentation:** [joomcoder.com/docs/user-reminder](https://www.joomcoder.com/docs/user-reminder)
- **Email:** support@joomcoder.com
- **License:** GNU General Public License v2 or later
