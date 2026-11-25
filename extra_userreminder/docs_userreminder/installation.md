# Installation

## Download

Download the latest package (`pkg_userreminder_vX.Y.Z.zip`) from your account at [joomcoder.com](https://www.joomcoder.com/).

## Install

1. Log in to your Joomla administrator
2. Go to **System > Install > Extensions**
3. Drop the ZIP file onto the upload area, or click **Browse for file**

The installer:

- adds the component, the **Task - UserReminder** plugin and the update plugin
- registers the three reminder emails in **System > Mail Templates**
- switches Joomla's mail templates to **HTML and plain text** and selects the User Reminder email container, unless you already chose a mail format or layout yourself
- creates a **User Reminder emails** scheduled task that runs every 24 hours

**Nothing is sent until you switch on at least one reminder type** in **Options > Reminders**. Continue with the [Quick Start](getting-started.md).

## Enable updates

1. Copy your **Download Key** from your account at [joomcoder.com](https://www.joomcoder.com/)
2. In Joomla go to **System > Update > Update Sites**
3. Open **UserReminder Update**, paste the key into **Download Key** and click **Save**
4. Go to **System > Update > Extensions** and click **Check for Updates**

## Updating from 6.x

Use Joomla's updater, or install the new package over the old one. Settings, counters, opt-outs and the log are kept.

## Upgrading from 5.x

Install 6.x over your current version. The upgrade keeps your users' reminder counters, opt-outs and log, converts the tables to InnoDB/utf8mb4, and replaces the old page-visit system plugin with the scheduled task. Inactive-user reminders stay on if they were on before.

After upgrading, check:

1. **System > Mail Templates**: 5.x kept the email texts in the options, 6.x uses mail templates. Copy your old wording into the templates if you had changed it
2. **System > Scheduled Tasks**: the **User Reminder emails** task is there and enabled
3. **Components > User Reminder**: the dashboard shows no red alerts

## Uninstalling

1. Go to **System > Manage > Extensions**
2. Search for **UserReminder**, tick the **package** and click **Uninstall**

This removes the component, both plugins, the reminder tables and the email container layout. Your users are not touched.
