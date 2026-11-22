# Quick Start

Send your first reminders in three steps.

## Step 1: Set the Cadence

Go to **Components > User Reminder** and click **Options**. On the **General** tab:

1. **Days before next reminder**: the wait between two reminders to the same user
2. **Number of reminders before deleting**: how many reminders a user gets in total
3. **Send first reminder immediately**: send the first one as soon as the user qualifies, or wait the number of days first
4. Pick a **Test User** for test emails (it defaults to you)

See [Installation](installation.md) if the component isn't installed yet.

## Step 2: Turn On a Reminder Type and Write the Email

1. On the **Reminders** tab, turn on **Incomplete Registrations**, **Activated, never logged in** and/or **Inactive Users** (set the **Inactivity threshold** in days). Leave **Delete users** off for now
2. Save
3. Open **Email Templates** in the User Reminder menu (this opens **System > Mail Templates**) and edit the subject and body of each reminder. Use tags like `{NAME}`, `{SITENAME}`, `{ACTIVATE_URL}` and `{OPTOUT_URL}`

> **Tip:** Keep `{OPTOUT_URL}` in every email so people can unsubscribe. See [Email Templates](features/email-templates.md).

## Step 3: Test, Then Let the Task Run

1. Click **Send Test Email** in the toolbar and check your inbox
2. Open **Registration Reminders** or **Active User Reminders** to see who would get an email and the action for each
3. Click **Send Reminders** to send now, or wait for the scheduled task

## What Happens Next

- **Every day:** the **User Reminder emails** task in **System > Scheduled Tasks** sends what's due. See [Scheduling](features/scheduling.md).
- **A user gets the last reminder and doesn't react:** nothing more happens, unless you turned on **Delete users** or chose groups to remove inactive users from.
- **A user clicks the unsubscribe link:** they land on a confirmation page on your site and get no more reminders. They can resubscribe from the same page.
