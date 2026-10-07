# Unstoppable Team HQ — install on Hostinger (Premium)

Files in this folder:

| File | What it does |
|---|---|
| `index.html` | The app screens |
| `app.js` | The app's logic in the browser |
| `api.php` | The server: logins, members, attendance, finance |
| `config.sample.php` | Template for `config.php`. Copy it to `config.php` on the server and fill in your database details. `config.php` is never stored in GitHub. |
| `schema.php` | Database tables (used by the installer) |
| `.htaccess` | Security: forces HTTPS, hides config.php |

## 1. Choose where it lives
Either a subdomain (recommended), e.g. `team.yourdomain.com`:
hPanel → **Domains → Subdomains** → create `team`. Its folder will be something like `public_html/team`.
Or a folder on your main site, e.g. `yourdomain.com/team` → folder `public_html/team`.

## 2. Create the database
hPanel → **Databases → MySQL Databases** → create a new database.
Write down the **database name**, **username** and **password** (they look like `u123456789_teamhq`).

## 3. Upload the files
hPanel → **Files → File Manager** → open your folder → **Upload** the ZIP → right-click → **Extract**.
Make sure `index.html` sits directly in the folder (not inside another folder).
Tip: turn on "Show hidden files" in File Manager settings to see `.htaccess`.

## 4. Put in your database details
In File Manager, copy `config.sample.php` and name the copy `config.php`. Right-click `config.php` → **Edit**. Replace the three placeholder values with your database name, username and password. Save.

## 5. Make sure HTTPS is on
hPanel → **Security → SSL** → install the free SSL for the domain/subdomain if it isn't active yet.

## 6. Run the installer
Open `https://team.yourdomain.com/install.php` in your browser.
Enter your name, email, a password, and your office names (e.g. Unstoppable Team Ondo, Unstoppable Team Akure). Click **Install**.

## 7. Delete install.php
File Manager → delete `install.php`. (It refuses to run again once an admin exists, but delete it anyway.)

## 8. Log in
Open `https://team.yourdomain.com` and log in with the email and password you just chose.

## Adding team leaders
**Leaders** page → type the leader's name, email, office → **Add leader**. Copy the login card and send it to them on WhatsApp.
Use **Reset password**, **Pause**, **Remove**, or change their office from the same page.

## Backups
Hostinger Premium takes automatic backups. To keep your own copy: hPanel → **Databases → phpMyAdmin** → select the database → **Export**.

## Time zone
Sign-in times use Nigerian time (Africa/Lagos). To change it, edit `APP_TZ` in `config.php`.

## Features added in version 2
- **Forgot password**: email reset link (valid 1 hour). You can also add a second **Admin** on the Team page.
- **Follow-ups**: log calls/visits on a member's profile or from the dashboard's "Needs a follow-up" card.
- **Prospects**: track invited people, who invited them, trainings attended, and convert them to members.
- **Trainings & fines**: record training attendance (members + guests); fines calculate automatically from the rule in Settings.
- **Business numbers**: monthly PV, BV, sales and PV targets per member.
- **Edit history**: "Last edited by" on attendance, plus a full Activity log in Settings.
- **Attendance history**: month-by-month and day-by-day on each member's profile.
- **WhatsApp/SMS alerts**: set an n8n webhook in Settings; add the daily cron job shown there.
- **All-time finance balances** per member (Finance → All-time balances).
- **Full backup to Excel** (Settings → Backup).
- **Check-in photos** (optional, Settings) to stop people signing in for friends.

The database upgrades itself automatically the first time the new version runs.
`cron.php` sends the daily digest; protect its key and don't share the URL.
