-- Synthetic data for the performance audit (docs/design/phase-5-performance-audit.md, step 4).
-- Run ONLY on a rehearsal database that is migrated and seeded (tools/performance/run.sh checks the name).
-- Volume: 200,000 job seekers, 2,000 companies (6,000 members), 20,000 jobs, 1,000,000 applications.
-- Skew: applications concentrate on a few jobs; about 30% of the applications carry 1 to 3 ratings.

\set ON_ERROR_STOP on
\timing on

begin;
set local synchronous_commit = off;

-- Companies, their members, their seven default stages.
insert into identity_users (type, name, email, password, email_verified_at, status, locale, created_at, updated_at)
select 'company_member', 'Member ' || g, 'member' || g || '@perf.test', 'x', now(), 'active', 'id', now(), now()
from generate_series(1, 6000) g;

insert into company_companies (name, slug, status, industry_id, organization_type_id, province_id, city_id, is_visible, verified_at, created_at, updated_at)
select 'Company ' || g, 'company-' || g, 'active',
       (select id from reference_industries order by id offset (g % 25) limit 1),
       (select id from reference_organization_types order by id offset (g % 6) limit 1),
       c.province_id, c.id, true, now(), now(), now()
from generate_series(1, 2000) g
join lateral (select id, province_id from reference_cities order by id offset (g % 514) limit 1) c on true;

insert into company_members (company_id, user_id, role, created_at, updated_at)
select ((u.id - min_id) % 2000) + 1 + (select min(id) - 1 from company_companies), u.id,
       case when (u.id - min_id) < 2000 then 'company_admin' else 'recruiter' end, now(), now()
from identity_users u, (select min(id) as min_id from identity_users where type = 'company_member') m
where u.type = 'company_member';

insert into recruitment_stages (company_id, type, name, position, is_default, created_at, updated_at)
select c.id, t.type, initcap(t.type), t.position, true, now(), now()
from company_companies c
cross join (values ('applied', 1), ('screening', 2), ('interview', 3), ('offer', 4), ('hired', 5), ('rejected', 6), ('withdrawn', 7)) as t(type, position);

-- Jobs: 60% active, the rest closed, expired or draft.
insert into job_jobs (company_id, title, slug, status, description, category_id, role_id, job_type_id, job_level_id, work_time_id, experience_level_id,
                      education_level_id, vacancies, deadline, salary_min, salary_max, salary_period_id, salary_visible, work_mode, province_id, city_id,
                      published_at, created_by_user_id, created_at, updated_at)
select ((g - 1) % 2000) + 1 + (select min(id) - 1 from company_companies),
       (array['Software Engineer', 'Accountant', 'Sales Executive', 'Marketing Manager', 'Customer Service', 'Data Analyst', 'Driver', 'Nurse'])[1 + g % 8]
           || ' ' || (array['Junior', 'Senior', 'Lead', 'Intern'])[1 + g % 4] || ' ' || g,
       'job-' || g,
       case when g % 10 < 6 then 'active' when g % 10 < 8 then 'closed' when g % 10 < 9 then 'expired' else 'draft' end,
       'Synthetic description ' || g,
       (select id from reference_job_categories order by id offset (g % 8) limit 1),
       (select id from reference_job_roles order by id offset (g % 24) limit 1),
       (select id from reference_job_types order by id offset (g % 4) limit 1),
       (select id from reference_job_levels order by id offset (g % 3) limit 1),
       (select id from reference_work_times order by id offset (g % 3) limit 1),
       (select id from reference_experience_levels order by id offset (g % 6) limit 1),
       (select id from reference_education_levels order by id offset (g % 5) limit 1),
       1 + g % 5,
       current_date + ((g % 60) - 20),
       3000000 + (g % 20) * 500000, 6000000 + (g % 20) * 500000,
       (select id from reference_salary_periods order by id limit 1),
       g % 3 > 0,
       (array['onsite', 'hybrid', 'remote'])[1 + g % 3],
       c.province_id, c.id,
       case when g % 10 < 9 then now() - make_interval(days => g % 120, mins => g % 1440) end,
       ((g - 1) % 2000) + 1 + (select min(id) - 1 from identity_users where type = 'company_member'),
       now() - make_interval(days => 120), now()
from generate_series(1, 20000) g
join lateral (select id, province_id from reference_cities order by id offset (g % 514) limit 1) c on true;

insert into job_job_skills (job_id, skill_id, created_at)
select j.id, s.id, now()
from job_jobs j
cross join lateral (select id from reference_skills order by id offset ((j.id * 7) % 29) limit 3) s;

-- Job seekers: users, profiles (80% discoverable and complete), resumes, experiences, educations, skills.
insert into identity_users (type, name, email, password, email_verified_at, status, locale, created_at, updated_at)
select 'job_seeker', 'Seeker ' || g, 'seeker' || g || '@perf.test', 'x', now(), 'active', 'id',
       now() - make_interval(days => g % 400), now() - make_interval(days => g % 400)
from generate_series(1, 200000) g;

create temp table seekers as
select (row_number() over (order by id))::int as n, id from identity_users where type = 'job_seeker';
create unique index on seekers (n);

insert into jobseeker_profiles (user_id, phone, gender, birth_date, experience_level_id, expected_salary, availability, province_id, city_id,
                                headline, summary, is_discoverable, resume_discoverable, slug, created_at, updated_at)
select s.id, '08' || (1000000000 + s.n), (array['male', 'female'])[1 + s.n % 2], date '1960-01-01' + (s.n % 15000),
       (select id from reference_experience_levels order by id offset (s.n % 6) limit 1), 4000000 + (s.n % 30) * 500000,
       (array['open_to_work', 'employed_open_to_offers', 'not_looking'])[1 + s.n % 3],
       c.province_id, c.id,
       'Headline ' || s.n, 'Summary ' || s.n,
       s.n % 5 > 0, s.n % 2 = 0, 'seeker-' || s.n,
       now() - make_interval(days => s.n % 400), now() - make_interval(days => s.n % 400, mins => s.n % 1440)
from seekers s
join lateral (select id, province_id from reference_cities order by id offset (s.n % 514) limit 1) c on true;

insert into jobseeker_resumes (user_id, name, path, size_bytes, created_at)
select id, 'cv.pdf', 'resumes/' || id || '.pdf', 120000, now() from seekers where n % 10 > 0;

insert into jobseeker_experiences (user_id, job_title, company_name, start_date, end_date, is_current, created_at, updated_at)
select s.id, (array['Software Engineer', 'Accountant', 'Sales Executive', 'Driver', 'Nurse'])[1 + (s.n + k) % 5], 'Company ' || ((s.n * k) % 5000),
       date '2015-01-01' + (k * 500), case when k = 1 then null else date '2015-01-01' + (k * 500) + 400 end, k = 1, now(), now()
from seekers s, generate_series(1, 2) k where s.n % 10 > 1;

insert into jobseeker_educations (user_id, education_level_id, institution, field_of_study, start_year, end_year, is_current, created_at, updated_at)
select s.id, (select id from reference_education_levels order by id offset (s.n % 5) limit 1), 'University ' || (s.n % 300), 'Field ' || (s.n % 40),
       2008, 2012, false, now(), now()
from seekers s where s.n % 10 > 0;

insert into jobseeker_skills (user_id, skill_id, position)
select s.id, sk.id, sk.rn
from seekers s
cross join lateral (select id, row_number() over () as rn from reference_skills order by id offset (s.n % 25) limit 4) sk;

-- Applications: users are uniform, jobs are skewed (a few jobs get most of the applicants). Duplicate pairs are dropped by the active key.
create temp table app_candidates as
select s.id as user_id, j.id as job_id, j.company_id, g
from generate_series(1, 1100000) g
join seekers s on s.n = 1 + (g::bigint * 7919) % 200000
join lateral (select id, company_id from job_jobs where id = (select min(id) - 1 from job_jobs) + 1 + floor(20000 * power(random(), 2))::int) j on true;

insert into recruitment_applications (user_id, job_id, company_id, stage_id, stage_type, active_key, cover_letter, resume_name, resume_path, resume_size_bytes,
                                      profile_snapshot, meets_must_have, applied_at, created_at, updated_at, source, match_score)
select distinct on (a.user_id, a.job_id)
       a.user_id, a.job_id, a.company_id, st.id, st.type, case when st.type = 'withdrawn' then null else a.user_id || '-' || a.job_id end,
       'Cover letter', 'cv.pdf', 'resumes/' || a.user_id || '.pdf', 120000,
       jsonb_build_object('name', 'Seeker ' || a.user_id, 'headline', 'Headline ' || a.user_id, 'email', 'seeker' || a.user_id || '@perf.test'),
       a.g % 3 > 0, ts, ts, ts, (array['search', 'recommendation', 'alert', 'direct', 'invitation'])[1 + a.g % 5], a.g % 101
from app_candidates a
cross join lateral (select now() - make_interval(days => a.g % 365, mins => a.g % 1440) as ts) t
join lateral (
    select id, type from recruitment_stages
    where company_id = a.company_id
      and type = (array['applied', 'applied', 'applied', 'screening', 'screening', 'interview', 'offer', 'hired', 'rejected', 'rejected', 'withdrawn'])[1 + a.g % 11]
) st on true
order by a.user_id, a.job_id, a.g
limit 1000000;

-- Ratings: about 30% of the applications, 1 to 3 raters among the company's members.
insert into recruitment_application_ratings (application_id, member_id, stars, created_at, updated_at)
select a.id, m.id, 1 + (a.id + m.id) % 5, now(), now()
from recruitment_applications a
cross join lateral (select id from company_members where company_id = a.company_id order by id limit 1 + a.id % 3) m
where a.id % 10 < 3;

-- Notifications: 10 per job seeker for the first 100,000 seekers, 30% unread.
insert into notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at)
select gen_random_uuid(), 'perf.synthetic', 'user', s.id, '{"title":"Synthetic"}', case when k % 10 < 3 then null else now() end,
       now() - make_interval(days => k, mins => s.n % 1440), now()
from seekers s, generate_series(1, 10) k where s.n <= 100000;

commit;
analyze;

select 'job seekers' as entity, count(*) from jobseeker_profiles
union all select 'companies', count(*) from company_companies
union all select 'jobs', count(*) from job_jobs
union all select 'applications', count(*) from recruitment_applications
union all select 'ratings', count(*) from recruitment_application_ratings
union all select 'notifications', count(*) from notifications;
