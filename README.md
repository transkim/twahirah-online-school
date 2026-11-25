# Twahirah Online School

The new school system for Twahirah Islamic Center: one server and admin website, used by the
student and teacher apps for Android, iPhone, Windows and the web.

Built with Laravel (PHP) and MySQL. Tests run on SQLite.

## What is in this first part (stage 1 groundwork)

- **People**: one account for administrators, finance staff, teachers and students.
- **Courses**: each course runs as chat classes (the former WhatsApp classes) or Zoom classes, with levels.
- **Subscriptions** replace the old monthly fees charged from attendance:
  - Chat classes: day UGX 2,000, week 10,000, month 40,000, year 420,000.
  - Zoom classes, by days a week (2, 3 or 5): day, week, month, year (12 months) and year (52 weeks),
    each with a number of Zoom sessions. Prices are in `database/seeders/PlanSeeder.php`.
  - A Zoom subscription ends when its date passes or its sessions run out, whichever comes first.
  - A renewal bought early starts when the current one ends, so no paid days are lost.
  - Chat classes keep working for a short grace period after the end date (`SCHOOL_GRACE_DAYS`, default 2).
  - Payments start as pending and become a subscription once MTN, Airtel or the card provider confirms them.
- **Automatic teacher and group**: the first time a student joins a course, the teacher of that course and
  level with the fewest students is chosen. The student joins that teacher's class group (created if needed)
  and gets a one-to-one chat with the teacher. Admins can move a student to another teacher.
- **Attendance**: signing in marks chat-class students present for the day. Joining a live Zoom class marks
  the student present and uses one session (rejoining after a dropped connection uses nothing more).
  Teachers can correct any mark.
- **Booking live classes**: teachers set the hours they are free. For a chosen day a student sees every
  open time: a new one-to-one class, a new small group, or a seat in a group that has room.

## Run it

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed      # set ADMIN_USERNAME and ADMIN_PASSWORD in .env first for the first admin
php artisan test
```

## Still to come

Sign-in screens and API for the apps, the admin website pages, MTN/Airtel/card payment connections,
the Zoom connection (`app/Services/Zoom`), chat messaging in real time, and importing the old portal's data.
