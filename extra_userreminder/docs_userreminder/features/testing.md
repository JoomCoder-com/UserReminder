# Testing and dry runs

Two ways to check your setup without emailing real users.

## Send Test Email

**Send Test Email** on **Registration Reminders** sends the activation and never-logged-in emails, and on **Active User Reminders** the inactivity email, to the **Test User** set in **Options > General** (you, when empty).

The test email is a real email, built exactly like a reminder: your template, the email container, a working unsubscribe link. It does not count as a reminder and is not logged.

## Developer debug mode

With **Options > General > Developer debug mode** on, every run is a dry run:

- no email is sent
- no reminder counter, date or log entry is written
- no user is deleted or removed from a group

Instead, each email the run would send is written, with its recipient and all tag values, to `administrator/logs/userreminder-mail.log` (the **Path to Log Folder** of Global Configuration). The message after **Send Reminders** says how many emails were simulated.

The dashboard shows an alert while debug mode is on. Turn it off to start sending.

## A safe first run

1. Turn on **Developer debug mode**
2. Switch on the reminder types you want, with **Delete users** off
3. Click **Send Reminders**, then read `userreminder-mail.log`
4. Check the **Action to Perform** column on the lists
5. Turn debug mode off
