# Benchmark: gigs (2026-10-29)

Input for `docs/design/gigs.md`. "Gig" covers two different products in the industry; this note compares both and says which fits ONESIS.
Facts are taken from the official help centres where reachable (links at the end); anything read only from a secondary source is marked *(secondary)*.

## 1. Two kinds of gig

| | **Shift gigs** (on-demand staffing) | **Project gigs** (freelance services) |
|---|---|---|
| Unit of work | a shift: date, start, end, place, headcount | a project or service package with a deliverable |
| Typical roles | kitchen and service crew, courier, cleaning, warehouse, event, retail | design, writing, software, data, marketing |
| Matching | worker books an open shift (instant, or after the company approves) | client posts a request and providers propose, or the provider lists a service and the client orders it |
| Proof of work | clock-in and clock-out on site, a timesheet the company approves | deliverables, milestones, client acceptance |
| Money | pay per hour or per shift; the platform usually pays out and invoices | quote per project, escrow, milestone release, platform fee |
| Benchmarks | Indeed Flex, Instawork, Staffinc (ID) | LinkedIn Services Marketplace, Upwork, Sribulancer (ID), Fastwork (ID) |

## 2. Benchmark platforms of the project

| Platform | Gig feature found | Note |
|---|---|---|
| Glints | none: freelance work is an ordinary job posting with the type "freelance" | no gig article in the help centre (Zendesk search, 2026-10-29) |
| Kalibrr | none found | Zendesk search returned nothing |
| Indeed | **Indeed Flex**, a separate product (UK, US) for shift staffing | see section 3 |
| LinkedIn | **Services Marketplace** (formerly ProFinder), project gigs | see section 4 |
| JobStreet, karir.com, loker.id, Dealls | none found; freelance and "harian lepas" are a job type | assumption: not verified in every help centre |

Our own job portal already has the same: `reference_job_types` holds `freelance`, and the offer letter has `employment_type = daily` (harian lepas)
(`phase-0-schema.md`, `phase-8-offer-letters.md`). So "a freelance job" already exists; a gig feature is only worth building as its own flow.

## 3. Shift gigs

**Indeed Flex** (official FAQ and blog)

- The company posts shifts; workers whose profile matches see and book them; some shifts need the company's approval before the worker gets the offer *(secondary)*.
- A booked shift needs the worker's confirmation *(secondary)*.
- Clock-in through a **geofence** around the site: 200 m by default, up to 500 m; outside the fence the worker sees a map and may retry, and an out-of-fence clock-in is reviewed. A countdown shows when clock-out is allowed; a forgotten clock-out closes itself 8 hours after the shift end.
- **Timesheets:** the company approves the worked hours each week (UK: by Tuesday 23:59 for the week before) so that invoicing can run; a no-show is marked "Didn't work".
- **Ratings:** the company rates each worker 1 to 5 stars; 5-star workers can be put into the company's preferred pool, which gets first choice of shifts.
- Pay: weekly, with an early-pay option (Instant Pay / Same Day Pay) *(out of our scope)*.

**Instawork** (official help centre)

- The worker browses and books shifts; **confirms 24 to 16 hours before** the start, with an SMS reminder.
- Free cancellation right after booking (5 or 15 minutes; the articles disagree); cancelling **within 24 hours** of the start is a violation. Multi-day bookings cancel as a whole, each day counting as one cancellation.
- **No-show** is a violation; repeated violations lead to suspension (no new bookings, booked shifts still to be worked) and finally deactivation. Weather and emergencies are treated leniently.
- Cancelling goes through the app ("I can't make it" with a reason), never by contacting the company directly.
- Check-in and check-out with GPS *(secondary)*.

**Staffinc** (Indonesia, official site)

- Staffing for field workers; attendance with **geofencing and face recognition**, leave and overtime requests in the "Staffinc Work" app. Shift booking details are not public.

## 4. Project gigs

- **LinkedIn Services Marketplace:** a provider has a service page tied to the profile; a client sends a request for a proposal to one provider or fills a questionnaire and receives up to 5 proposals; a proposal opens a conversation, not a contract; declining a request is final. Free for both sides *(secondary)*; payment happens outside LinkedIn.
- **Sribulancer:** the client posts a need and freelancers bid on it *(secondary)*.
- **Fastwork:** the freelancer lists a service, the client chats and receives an offer; staged payment (milestones) since 2020 *(secondary)*.
- Upwork-style platforms add escrow, milestones and a platform fee: all of it is billing, out of scope (`CLAUDE.md` rule 7).

## 5. Indonesian law that shapes shift gigs

- **PP 35/2021, Pasal 10:** a daily worker (*pekerja harian lepas*) may work **fewer than 21 days in a month** for one employer; working 21 days or more for **3 months in a row** turns the relationship into a permanent one (PKWTT). Daily pay base: monthly wage / 21 (5-day week) or / 25 (6-day week), never under the minimum wage. Sources: hukumonline, Talenta *(secondary; check JDIH Kemnaker before relying on it)*.
- An intermediary that supplies workers to a company is outsourcing (*alih daya*) and needs its own licence; a tool that lets a company hire its own daily workers is not. This decides who the employer of a gig worker is: a question for `docs/legal-review.md`.
- Location data at clock-in and an identity check are personal data (UU PDP): purpose, retention and consent belong in the legal review.

## 6. Fit with ONESIS

- Shift gigs match the first customer: PKPGROUP is kitchen (PT PKP Kuliner), courier (PT PKP Kurir) and cleaning (PT PKP Cleaning Service), all shift work with many short assignments (`career-modes.md` section 3a). They work in `company` mode, the mode every portal runs today.
- Project gigs are a marketplace product (independent clients and providers), and the marketplace features are frozen (`erp-direction.md` section 1); their core value (escrow, milestones, fees) is billing, which is out of scope.
- Shift gigs reuse a lot: job seeker accounts and profiles, company locations with latitude and longitude (`company_locations`), notifications, WhatsApp notices (`phase-8-whatsapp.md`), conversations, the talent pool, the organisation structure (units, cost centres).

## Sources

- Indeed Flex: [timesheets](https://indeedflex.com/faq/how-do-i-manage-timesheets/), [geofencing](https://indeedflex.com/faq/geofencing-feature/), [clock in and out](https://indeedflex.co.uk/blog/for-workers/weve-improved-the-clock-in-and-out-process/), [how the app works](https://indeedflex.co.uk/how-the-app-works/)
- Instawork: [first gig checklist](https://help.instawork.com/worker-faq/first-gig-checklist), [cancellation policy](https://help.instawork.com/en/articles/6122001-cancellation-policy), [canceling a shift](https://help.instawork.com/en/articles/9658420-canceling-a-shift), [best practices for booking](https://help.instawork.com/en/articles/5955377-best-practices-for-booking-shifts)
- Staffinc: [employee self service](https://staffinc.co/solusi/platform/employee-self-service), [Staffinc Work app](https://apps.apple.com/id/app/staffinc-work/id1612182139)
- LinkedIn: [Services Marketplace](https://business.linkedin.com/grow/profinder), [help: request a proposal](https://www.linkedin.com/help/lms/answer/131796), [Search Engine Land](https://searchengineland.com/freelancers-can-now-market-their-services-on-linkedin-375970)
- Sribulancer and Fastwork: [Mojok](https://mojok.co/terminal/4-rekomendasi-situs-freelance-di-indonesia-mana-yang-terbaik/), [Selular.ID](https://selular.id/2020/08/sambut-hari-kemerdekaan-indonesia-fastwork-hadirkan-pembayaran-bertahap/)
- Daily workers: [hukumonline](https://www.hukumonline.com/klinik/a/bedanya-pekerja-harian-lepas-dan-pekerja-bulanan-cl4436/), [Talenta](https://www.talenta.co/blog/aturan-pekerja-harian-lepas/), [hukumonline PP PKWT](https://www.hukumonline.com/berita/a/begini-pengaturan-lengkap-pp-pkwt-phk-lt603395220adef)
