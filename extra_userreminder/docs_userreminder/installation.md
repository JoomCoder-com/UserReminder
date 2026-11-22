# Installation

## Download

Download the latest package (`pkg_userreminder_vX.X.X.zip`) from your account at [joomcoder.com](https://www.joomcoder.com/).

## Install

1. Log in to your Joomla administrator
2. Go to **System > Install > Extensions**
3. Drop the ZIP file onto the upload area, or click **Browse for file**
4. Joomla installs the component and the task plugin

The installer also:

- registers the three reminder emails in **System > Mail Templates**
- switches Joomla mail to HTML + plain text and selects the User Reminder email container, unless you already chose a mail layout yourself
- creates a **User Reminder emails** scheduled task that runs every 24 hours

Nothing is sent until you turn on at least one reminder type in the options.

## Enable Updates

1. Copy your **Download Key** from your account at [joomcoder.com](https://www.joomcoder.com/)
2. In Joomla go to **System > Update > Update Sites**
3. Open **UserReminder Update**, paste the key into **Download Key** and **Save**
4. Go to **System > Update > Extensions** and click **Check for Updates**

## Upgrading from 5.x

Install 6.x over your current version. The upgrade keeps your users' reminder counters, opt-outs and log, converts the tables to InnoDB/utf8mb4 and replaces the old page-hit system plugin with the scheduled task.

After upgrading, check:

1. **System > Mail Templates**: 5.x kept the email texts in the options; 6.x uses mail templates. Copy your old wording into the templates if you had customised it
2. **System > Scheduled Tasks**: the **User Reminder emails** task is there and enabled
3. **Components > User Reminder**: the dashboard shows no alerts

## Upgrading 6.x

Install the new version over the old one, or use Joomla's updater. Settings and data are kept.

## Uninstalling

1. Go to **System > Manage > Extensions**
2. Search for **UserReminder**, select the package and click **Uninstall**

This removes the component, the task plugin and the reminder data.
