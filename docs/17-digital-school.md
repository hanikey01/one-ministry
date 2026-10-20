# Digital School Product and Implementation Specification

**Status:** Implemented foundation and full core workflow in source  
**Owner:** Product, data, permissions, interface, and operations  
**Module key:** `digital_school`

## 1. Purpose

Digital School gives a ministry a structured learning environment for discipleship, membership classes, Bible teaching, leadership development, volunteer preparation, and ministry training. It uses the installation's existing people records, authentication, branding, permissions, media providers, communications, and interactive meetings.

The module is optional. When disabled it is absent from staff and member navigation and its routes reject access server-side.

## 2. Users and access

- School managers create and publish courses, modules, lessons, assessments, and live classes and manage learner enrolments.
- Instructors can teach and review learning progress when granted the `school.instruct` permission.
- Members browse published courses, enrol according to course policy, learn, take assessments, attend live classes, and receive certificates.
- Ministry and super administrators receive the school permissions on migration. A dedicated School Manager role contains `school.manage` and `school.instruct`.

## 3. Course experience

A course contains ordered modules and lessons. Lessons may be text, video, audio, downloads, or live-class replays. Courses define a level, category, instructor, summary, description, dates, capacity, enrolment policy, passing score, and certificate policy.

Enrolment policies are open, approval required, or invitation only. Capacity is enforced during self-enrolment. Approval requests remain pending until a school manager accepts them.

Published lessons contribute to completion. Progress is recalculated after every completed lesson and assessment. A course completes only after every published lesson is complete and every published assessment has a passing attempt.

## 4. Assessments and certificates

Assessments support multiple attempts, a configurable passing score, ordered questions, answer options, scoring points, and explanations. Answers and scores are stored per attempt. Correct-answer material is never sent to the member interface before submission.

When a learner satisfies all published course requirements, the enrolment is completed and a unique certificate record is issued when certificates are enabled. Revocation is represented separately from issuance.

## 5. Live classes

A live class belongs to a course and may belong to a module. It can link to an installation-owned interactive meeting record or an approved external meeting URL. Members reach the meeting from the enrolled course experience. The meeting provider remains responsible for media capacity; the Laravel host does not carry WebRTC media.

## 6. Data and privacy

School data uses the installation's existing person record. Course progress is ministry participation data and is visible only to the learner and authorized school staff. It does not imply spiritual health or pastoral status. Assessment answers, attempts, enrolments, progress, live-class schedules, and certificates are included in the installation's backup and future export scope.

## 7. Implemented interfaces

- Staff course catalogue and course builder
- Course settings and publication controls
- Module and lesson authoring
- Module and lesson editing and removal contracts
- Assessment and question authoring
- Live-class scheduling and lifecycle controls
- Staff invitation, enrolment approval, and progress roster
- Member course catalogue and enrolment
- Member learning path with media, lesson completion, and progress
- Member assessment submission and grading
- Automatic completion certificate issuance and printable member certificate

## 8. Remaining release work

The module must remain in staff preview until migration, permission, browser, accessibility, mobile, backup, and supported-database verification pass. Zoom Video SDK live classes also depend on completion of the interactive meeting browser room and provider callback workflow.
