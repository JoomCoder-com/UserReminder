# Short Description

Automatically send customisable email reminders to your Joomla users — recover abandoned registrations, re-engage inactive members, and clean up stale accounts, all from your own site.

# Description

**User Reminder** is a Joomla extension that brings inactive accounts back. It automatically emails the right message to each group — a fresh activation link for incomplete sign-ups, a friendly nudge for users who never logged in, and a "we've missed you" note for members who haven't visited in a while — then optionally removes accounts that never respond.

Everything is driven from your Joomla administrator: write branded HTML emails with personalised merge fields, decide how many reminders to send and how far apart, send on an automatic schedule, and stay anti-spam compliant with built-in opt-out. No external service and no per-user fees — your users and your data never leave your site.

# Features

## Reminder Types | groups
Target users at every stage of their lifecycle with the right message.
- Incomplete Registrations
  Remind users who signed up but never activated, and resend their activation link.
- Never-Logged-In Users
  Nudge users who activated their account but have never actually logged in.
- Inactive Members
  Re-engage existing users who have not visited your site for a set number of days.

## Personalised Emails | mail
Send branded, relevant emails with built-in personalisation.
- Custom Subject & Body
  Write the subject line and a full HTML message for each reminder type.
- Merge Fields
  Personalise with the user's name, username, site details, activation and password-reset links.
- Independent Templates
  Keep separate wording for incomplete, never-logged-in, and inactive users.

## Automation & Scheduling | schedule
Send reminders automatically on the schedule you choose.
- Built-in Scheduler
  A bundled system plugin sends reminders automatically — daily, weekly, or monthly.
- Send First Reminder Immediately
  Optionally fire the first reminder on the next run instead of waiting.
- Multiple Reminders
  Send a set number of follow-ups, spaced by a configurable number of days.

## User Cleanup | group_remove
Keep your user base tidy when reminders go unanswered.
- Auto-Delete Stale Accounts
  Remove users automatically after the final reminder, per reminder type.
- Remove From User Groups
  Instead of deleting, move inactive users out of selected user groups.
- Safe Thresholds
  Actions run only once the configured days and reminder count are reached.

## Compliance & Opt-Out | unsubscribe
Stay on the right side of anti-spam rules.
- One-Click Opt-Out
  Add an unsubscribe link to every email with the opt-out merge field.
- CAN-SPAM Friendly
  Users who opt out are automatically excluded from future reminders.
- Opt-Out List
  Review and manage everyone who has opted out from the administrator.

## Delivery Controls | tune
Work comfortably within your host's sending limits.
- Email Throttling
  Cap the maximum number of emails sent in a single run.
- Recordset Limits
  Limit how many users are processed per query for better performance.
- BCC the Admin
  Optionally blind-copy an address on every reminder for oversight.

## Testing & Targeting | science
Preview and verify before anything reaches real users.
- Test Emails
  Send a sample reminder to yourself or a chosen test user.
- Specific Test User
  Pick exactly which user receives test emails.
- Preview Before Sending
  Review the affected users and the action for each before you send.

# FAQs

## Getting Started

### What is User Reminder?

User Reminder is a Joomla extension that automatically sends customisable email reminders to your registered users — and can clean up accounts that stay inactive. It targets three groups: users who never finished registering, users who activated but never logged in, and members who have not visited for a while.

### Who is User Reminder for?

Any Joomla site with user registration — membership sites, communities, shops, and intranets — that wants to recover abandoned sign-ups, re-engage dormant members, and keep its user list clean.

### Do I need any coding skills to use it?

No. User Reminder installs like any Joomla package and is configured entirely from the administrator, with point-and-click options and a built-in HTML email editor.

### How do I send reminders automatically?

Enable Scheduled Execution in the options and make sure the bundled "System - UserReminder Scheduler" plugin is enabled. You can also send reminders manually at any time from the component.

## Features & Use

### Which users get reminded?

Three independent groups, each enabled separately: Incomplete Registrations (signed up, never activated), Never Logged In (activated, never logged in), and Inactive Members (no visit for a configurable number of days).

### Can I customise the emails?

Yes. Each reminder type has its own subject and full HTML body, with merge fields such as [NAME], [USERNAME], [SITE_NAME], [SITE_URL], [ACTIVATE_URL], [PASSWORD_RESET] and [OPTOUT].

### Can User Reminder delete inactive users?

Yes, optionally. After the configured number of reminders and days have passed, User Reminder can delete the account — or, for existing members, simply remove them from selected user groups instead.

### How does the opt-out / unsubscribe work?

Include the [OPTOUT] merge field in your emails to add an unsubscribe link. Users who opt out are excluded from future reminders, helping you stay CAN-SPAM compliant, and you can review the opt-out list in the administrator.

### Can I test before sending to real users?

Yes. Use the Test Email button to send a sample to yourself or a specific test user, and preview the list of affected users and the action for each before sending.

### Will it overload my mail server?

No. You can cap the maximum number of emails sent per run and limit how many users are processed per query, so sending stays within your host's limits.

## Licensing & Updates

### How is User Reminder licensed?

User Reminder is a commercial extension distributed under the GNU General Public License v2 or later. An active subscription gives you downloads, automatic updates, and support.

### How do updates work?

Updates are delivered through Joomla's built-in updater. Add your download key once under System > Update > Update Sites, then update in one click from Components > Update while your settings are preserved.

### What is included in the package?

A single install package sets up the User Reminder component (com_userreminder) and its system plugin (System - UserReminder Scheduler), which runs reminders automatically.

## Technical

### Which Joomla and PHP versions are supported?

User Reminder runs on Joomla 4 and 5 and requires PHP 7.4 or higher.

### Does it work with Community Builder?

Yes. If you use Community Builder to manage registrations, enable the "Use Community Builder Activation" option so reminder emails contain a valid Community Builder activation link instead of the default Joomla one.

### How does the automatic scheduler work?

The system plugin uses Joomla's onAfterRoute event, which fires on site visits, and checks whether reminders are due for your chosen schedule (for example, weekly on Tuesdays). Because it relies on site traffic, a day with no visitors simply skips that run.
