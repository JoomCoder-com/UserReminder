# Short Description

User Reminder emails Joomla users who never activated, never logged in or stopped visiting, and can clean up the accounts that never respond.

# Description

**User Reminder** is a Joomla extension that sends reminder emails to three groups of users: people who registered but never activated their account, people who activated but never logged in, and members who have not visited for a number of days you choose. After the last reminder it can delete the account, or remove an inactive member from chosen user groups.

It runs on Joomla's own tools: the emails are Joomla mail templates, sending runs as a Joomla scheduled task, and access follows Joomla permissions. A dashboard shows who is waiting, what is due and what was sent, and every list tells you what the next run will do for each user. Every email carries an unsubscribe link. Nothing leaves your site: no outside service, no per-user fees.

Works on Joomla 4.4, 5 and 6, with MySQL or MariaDB.

# Features

## Reminder Types | groups
Three groups of users, each switched on separately.
- Incomplete Registrations
  Remind users who registered but never activated, with their activation link in the email.
- Never Logged In
  Nudge users whose account is active but who never signed in.
- Inactive Members
  Bring back members who have not visited for the number of days you set.
- Your Cadence
  Choose the days between reminders, how many to send, and whether the first goes out at once.

## Emails | mail
Joomla mail templates you edit like any other site email.
- One Template per Type
  Subject, plain text and HTML body for each reminder type, per site language.
- Personal Tags
  {NAME}, {USERNAME}, {EMAIL}, {SITENAME}, {SITELINK}, {ACTIVATE_URL}, {PASSWORD_RESET_URL} and {OPTOUT_URL}.
- Branded HTML Container
  Wrap each email with your logo or title, footer text and colours (Joomla 5 and 6).
- Admin Copy
  Optionally send a BCC copy of every reminder to an address you choose.

## Scheduling | schedule
Sending runs on Joomla Scheduled Tasks.
- Ready-Made Task
  Install creates the "Send User Reminder emails" task, set to run every 24 hours.
- Lazy Scheduler or Cron
  Works with Joomla's lazy scheduler or a real server cron job.
- Send Now
  Buttons on each list send what is due right away.
- Sending Limits
  Cap the emails per run to stay inside your host's limits.

## Account Cleanup | group_remove
Tidy up accounts that never answer.
- Delete After the Last Reminder
  Delete the account on the next run after the final reminder, per reminder type.
- Remove From Groups
  Or take inactive members out of chosen user groups and keep the account.
- Clear Warnings
  The dashboard shows an alert while deletion is switched on.

## Opt-Out | unsubscribe
Respect the people who do not want reminders.
- Unsubscribe Link
  Every email links to a confirmation page, with a button to subscribe again.
- Opt-out Users
  Add or remove people by hand.
- Opt-out Groups
  Members of the groups you pick never get reminders.

## Dashboard and Log | dashboard
See what is happening and what will happen.
- Dashboard
  Pending, due now, sent, a 30-day chart, backlog aging and health checks.
- Next Action per User
  Every list shows what the next run will do for each user.
- Action Log
  Every reminder, opt-out and resubscribe, with pruning of old entries.

## Safe Testing | science
Check everything before real users get an email.
- Test Email
  Send a reminder to one user you choose.
- Debug Mode
  A full run that sends nothing and changes nothing, so you can see what would happen.

# FAQs

## Getting Started

### What is User Reminder?

User Reminder is a Joomla extension that emails users who never activated, never logged in or stopped visiting your site. After the last reminder it can delete the account or remove the user from chosen groups.

### Who is it for?

Any Joomla site with user registration: membership sites, communities, shops and intranets that want sign-ups finished, quiet members back and a clean user list.

### Do I need coding skills?

No. You install one package and set everything in the Joomla administrator. The emails are edited in Joomla's Mail Templates screen.

### How do reminders go out automatically?

Through Joomla Scheduled Tasks. Installing User Reminder creates the "Send User Reminder emails" task, which runs every 24 hours with Joomla's lazy scheduler or a server cron job. Each reminder type sends only after you switch it on in Options > Reminders.

## Features & Use

### Which users get reminded?

Three groups, each switched on separately: users who registered but never activated, users who activated but never logged in, and users who have not visited for the number of days you set.

### Can I change the emails?

Yes. Each type has its own Joomla mail template with subject, plain text and HTML body, per language. Tags such as {NAME}, {SITENAME}, {ACTIVATE_URL} and {OPTOUT_URL} are filled in for each user.

### Can it delete inactive users?

Yes, if you switch it on. Deletion happens on the next run after the user got the last reminder, and you choose it per reminder type. For inactive members you can instead remove them from chosen user groups.

### How does unsubscribing work?

Every email has an unsubscribe link. The user confirms on a page of your site and gets no more reminders. They can subscribe again from the same page. You can also manage the opt-out list and opt out whole user groups in the administrator.

### Can I test before sending to real users?

Yes. Send Test Email sends one reminder to a user you choose. Debug mode runs everything without sending or changing anything, and every list shows what the next run will do.

### Will it overload my mail server?

No. You set the maximum number of emails per run, and the rest go out on the next runs.

## Licensing & Updates

### How is User Reminder licensed?

It is a commercial extension under the GNU General Public License v2 or later. An active subscription gives you downloads, updates and support.

### How do updates work?

Enter your download key once under System > Update > Update Sites. Updates then appear under System > Update > Extensions. Your settings, opt-outs and log are kept.

### I use version 5.x. Can I update?

Yes. Install 6.x over 5.2.3. Your settings, opt-out list and log are moved over, and the old scheduler plugin is switched off in favour of the scheduled task.

## Technical

### Which Joomla and PHP versions are supported?

Joomla 4.4, 5 and 6 on PHP 8.1 or newer, with MySQL 8 or MariaDB 10.4 or newer. Each release is tested on all three Joomla versions with both databases.

### Does it work with Community Builder?

Yes. Switch on "Use Community Builder activation" and activation reminders carry the Community Builder activation link.

### Can other extensions react to reminders?

Yes. Plugins in the userreminder group get onUserReminderBeforeSend, which can stop a send, and onUserReminderAfterSend.
